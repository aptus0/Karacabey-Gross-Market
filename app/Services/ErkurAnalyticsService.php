<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ErkurAnalyticsService
{
    private string $dumpPath;

    public function __construct()
    {
        $this->dumpPath = base_path('erkur_dump');
    }

    /**
     * Finans özeti: toplam alacak, borç, bakiye.
     */
    public function getFinansOzeti(): array
    {
        return Cache::remember('erkur:finans_ozeti', now()->addMinutes(30), function () {
            $detayPath = $this->dumpPath . '/FINANS_DETAY.csv';
            if (! file_exists($detayPath)) {
                return $this->emptyFinansOzeti();
            }

            $toplamAlacak = 0.0;
            $toplamBorc = 0.0;
            $kayitSayisi = 0;

            $handle = fopen($detayPath, 'r');
            if ($handle === false) {
                return $this->emptyFinansOzeti();
            }

            // Skip header rows (first 2 lines: header + separator)
            fgetcsv($handle); // header
            fgetcsv($handle); // separator line (--)

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 8) {
                    continue;
                }
                // FINANS_DETAY: ID, FINANS, FINANS_ISLEM_TURU, DOVIZ_AD, KUR, KART_BORCLU, KART_ALACAKLI, TUTAR
                $tutar = (float) ($row[7] ?? 0);
                $toplamAlacak += $tutar;
                $kayitSayisi++;
            }

            fclose($handle);

            // FINANS.csv for transaction count
            $finansPath = $this->dumpPath . '/FINANS.csv';
            $finansSayisi = max(0, $this->countRows($finansPath) - 2);

            return [
                'toplam_islem' => $finansSayisi,
                'toplam_hareket' => $kayitSayisi,
                'toplam_alacak' => $toplamAlacak,
                'son_tarih' => $this->getLastDate($finansPath, 5),
            ];
        });
    }

    /**
     * POS/Kasa özeti: son satışlar.
     */
    public function getPosOzeti(): array
    {
        return Cache::remember('erkur:pos_ozeti', now()->addMinutes(15), function () {
            $path = $this->dumpPath . '/FIS_POS.csv';
            if (! file_exists($path)) {
                return ['toplam_fis' => 0, 'son_fisler' => []];
            }

            $rows = $this->readCsvToCollection($path, 15);

            // FIS_POS: ID, FIS, MASA, KISI, GUN_NO, KAPANIS_TARIHI, PC_ID, POS_SIPARIS_TIPI, Z_NO, ...
            $sonFisler = $rows->filter(fn ($r) => ! empty($r[5]) && $r[5] !== 'NULL')
                ->map(fn ($r) => [
                    'id' => $r[0] ?? '',
                    'fis' => $r[1] ?? '',
                    'gun_no' => $r[4] ?? '',
                    'kapanis_tarihi' => $r[5] ?? '',
                    'z_no' => $r[8] ?? 'N/A',
                    'platform' => $r[15] ?? 'Pos',
                ])
                ->values()
                ->take(8);

            return [
                'toplam_fis' => $this->countRows($path) - 2,
                'son_fisler' => $sonFisler->all(),
            ];
        });
    }

    /**
     * POS fiş toplamları (genel tutar bilgisi).
     */
    public function getPosTutarOzeti(): array
    {
        return Cache::remember('erkur:pos_tutar', now()->addMinutes(30), function () {
            $path = $this->dumpPath . '/POS_FIS_TOPLAMLAR.csv';
            if (! file_exists($path)) {
                return ['toplam_ciro' => 0.0, 'toplam_kdv' => 0.0, 'ortalama_fis' => 0.0];
            }

            $handle = fopen($path, 'r');
            if ($handle === false) {
                return ['toplam_ciro' => 0.0, 'toplam_kdv' => 0.0, 'ortalama_fis' => 0.0];
            }

            // Header: POS_GECICI, SATIRLAR_TOPLAM, SATIR_ISKONTO_TOPLAM, FIS_ISKONTO_TOPLAM, KDV_TOPLAM, GENEL_TOPLAM
            fgetcsv($handle); // header
            fgetcsv($handle); // separator

            $toplamCiro = 0.0;
            $toplamKdv = 0.0;
            $fisCount = 0;

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 6) {
                    continue;
                }
                $toplamCiro += (float) ($row[5] ?? 0); // GENEL_TOPLAM
                $toplamKdv += (float) ($row[4] ?? 0);  // KDV_TOPLAM
                $fisCount++;
            }
            fclose($handle);

            return [
                'toplam_ciro' => $toplamCiro,
                'toplam_kdv' => $toplamKdv,
                'fis_sayisi' => $fisCount,
                'ortalama_fis' => $fisCount > 0 ? round($toplamCiro / $fisCount, 2) : 0.0,
            ];
        });
    }

    /**
     * E-Fatura listesi: son 10 fatura.
     */
    public function getEFaturalar(int $limit = 10): array
    {
        return Cache::remember("erkur:e_faturalar_{$limit}", now()->addMinutes(20), function () use ($limit) {
            $path = $this->dumpPath . '/E_FATURA.csv';
            if (! file_exists($path)) {
                return [];
            }

            $rows = $this->readCsvToCollection($path, $limit + 5);

            // E_FATURA: ID, UID, E_MAIL, VERGINO, TIP, CARI, TARIH, TUTAR, ENTEGRASYON, KABUL_DURUMU, PDF_ALINDI, UBL_ALINDI, BELGENO, ALIASS
            return $rows->map(fn ($r) => [
                'id' => $r[0] ?? '',
                'vergi_no' => $r[3] ?? '',
                'tip' => $r[4] ?? '',
                'cari_id' => $r[5] ?? '',
                'tarih' => isset($r[6]) ? substr($r[6], 0, 10) : '',
                'tutar' => (float) ($r[7] ?? 0),
                'belgeno' => $r[12] ?? '',
                'kabul' => ($r[9] ?? '0') === '1' ? 'Kabul' : 'Bekliyor',
                'email' => $r[2] ?? '',
            ])->take($limit)->values()->all();
        });
    }

    /**
     * Stok özeti: stok sayısı ve son alış fiyatları yüksek olanlar.
     */
    public function getStokOzeti(): array
    {
        return Cache::remember('erkur:stok_ozeti', now()->addHours(1), function () {
            $path = $this->dumpPath . '/STOK.csv';
            if (! file_exists($path)) {
                return ['toplam' => 0, 'aktif' => 0, 'top_stoklar' => []];
            }

            $rows = $this->readCsvToCollection($path, 500);

            // STOK: ID, KOD, STOK_GRUP, AD, ..., AKTIF(23), ..., SON_ALIS_FIYAT(18), SON_ALIS_TARIHI(19)
            $aktif = $rows->filter(fn ($r) => ($r[23] ?? '0') === '1');

            $topStoklar = $aktif
                ->filter(fn ($r) => (float) ($r[18] ?? 0) > 0)
                ->sortByDesc(fn ($r) => (float) ($r[18] ?? 0))
                ->take(8)
                ->map(fn ($r) => [
                    'kod' => $r[1] ?? '',
                    'ad' => $r[3] ?? '',
                    'son_alis_fiyat' => (float) ($r[18] ?? 0),
                    'son_alis_tarihi' => isset($r[19]) ? substr($r[19], 0, 10) : '',
                ])
                ->values()
                ->all();

            return [
                'toplam' => $rows->count(),
                'aktif' => $aktif->count(),
                'top_stoklar' => $topStoklar,
            ];
        });
    }

    /**
     * Cari özeti: aktif cari sayısı + ilk N kayıt.
     */
    public function getCariOzeti(int $limit = 8): array
    {
        return Cache::remember("erkur:cari_ozeti_{$limit}", now()->addHours(1), function () use ($limit) {
            $path = $this->dumpPath . '/CARI.csv';
            if (! file_exists($path)) {
                return ['toplam' => 0, 'aktif' => 0, 'son_cariler' => []];
            }

            $rows = $this->readCsvToCollection($path, 400);

            // CARI: ID, CARI_TUR, KOD, AD, ..., AKTIF(29)
            $aktif = $rows->filter(fn ($r) => ($r[29] ?? '0') === '1');

            $sonCariler = $aktif->take($limit)
                ->map(fn ($r) => [
                    'kod' => $r[2] ?? '',
                    'ad' => $r[3] ?? '',
                    'tur' => ($r[1] ?? '1') === '1' ? 'Alıcı' : 'Satıcı',
                    'vergi_no' => $r[13] ?? '',
                    'vade' => (int) ($r[7] ?? 0),
                ])
                ->values()
                ->all();

            return [
                'toplam' => $rows->count(),
                'aktif' => $aktif->count(),
                'son_cariler' => $sonCariler,
            ];
        });
    }

    /**
     * Sayım özeti.
     */
    public function getSayimOzeti(): array
    {
        return Cache::remember('erkur:sayim_ozeti', now()->addHours(1), function () {
            $path = $this->dumpPath . '/SAYIM.csv';
            $detayPath = $this->dumpPath . '/SAYIM_DETAY.csv';
            if (! file_exists($path)) {
                return ['toplam_sayim' => 0, 'toplam_satir' => 0];
            }

            return [
                'toplam_sayim' => max(0, $this->countRows($path) - 2),
                'toplam_satir' => max(0, $this->countRows($detayPath) - 2),
            ];
        });
    }

    /**
     * Read CSV into a Collection, skipping header and separator lines.
     */
    private function readCsvToCollection(string $path, int $limit = 100): Collection
    {
        if (! file_exists($path)) {
            return collect();
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return collect();
        }

        fgetcsv($handle); // header
        fgetcsv($handle); // separator line (--)

        $rows = [];
        $count = 0;
        while (($row = fgetcsv($handle)) !== false && $count < $limit) {
            $rows[] = $row;
            $count++;
        }
        fclose($handle);

        return collect($rows);
    }

    /**
     * Count total lines in a CSV file.
     */
    private function countRows(string $path): int
    {
        if (! file_exists($path)) {
            return 0;
        }

        $count = 0;
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }
        while (fgetcsv($handle) !== false) {
            $count++;
        }
        fclose($handle);

        return $count;
    }

    /**
     * Get the last date value from a specific column index.
     */
    private function getLastDate(string $path, int $colIndex): string
    {
        if (! file_exists($path)) {
            return '-';
        }

        $lastDate = '-';
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return '-';
        }

        fgetcsv($handle); // header
        fgetcsv($handle); // separator

        while (($row = fgetcsv($handle)) !== false) {
            if (! empty($row[$colIndex]) && $row[$colIndex] !== 'NULL') {
                $lastDate = substr($row[$colIndex], 0, 10);
            }
        }
        fclose($handle);

        return $lastDate;
    }

    private function emptyFinansOzeti(): array
    {
        return [
            'toplam_islem'  => 0,
            'toplam_hareket'=> 0,
            'toplam_alacak' => 0,
            'son_tarih'     => '-',
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    //  Tam sayfa ERP veri metodları
    // ══════════════════════════════════════════════════════════════════

    /**
     * Tam fatura listesi — filtreli (fatura sayfası için)
     */
    public function getFaturalar(array $filters = []): array
    {
        // Ham CSV verisini uzun süre cache'le (filtreler sonradan uygulanır)
        $raw = Cache::remember('erkur:faturalar_raw', now()->addHours(2), function () {
            $path = $this->dumpPath . '/E_FATURA.csv';
            if (! file_exists($path)) return [];

            $rows = [];
            $handle = fopen($path, 'r');
            fgetcsv($handle); fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 8) continue;
                $rows[] = $row;
            }
            fclose($handle);
            return $rows;
        });

        $faturalar   = [];
        $toplamTutar = 0.0;
        $kabulSayisi = 0;

        foreach ($raw as $row) {
            $tarih = isset($row[6]) ? substr($row[6], 0, 10) : '';
            $tutar = (float) ($row[7] ?? 0);
            $tip   = strtoupper(trim($row[4] ?? ''));
            $kabul = ($row[9] ?? '0') === '1';

            if (! empty($filters['tarih_baslangic']) && $tarih < $filters['tarih_baslangic']) continue;
            if (! empty($filters['tarih_bitis'])     && $tarih > $filters['tarih_bitis'])     continue;
            if (! empty($filters['tip'])             && stripos($tip, $filters['tip']) === false) continue;
            if (isset($filters['durum']) && $filters['durum'] === 'kabul'    && ! $kabul) continue;
            if (isset($filters['durum']) && $filters['durum'] === 'bekliyor' &&   $kabul) continue;

            $faturalar[] = [
                'id'       => $row[0] ?? '',
                'vergi_no' => $row[3] ?? '',
                'tip'      => $tip,
                'cari_id'  => $row[5] ?? '',
                'tarih'    => $tarih,
                'tutar'    => $tutar,
                'belgeno'  => $row[12] ?? '',
                'kabul'    => $kabul ? 'Kabul' : 'Bekliyor',
                'email'    => $row[2] ?? '',
            ];

            $toplamTutar += $tutar;
            if ($kabul) $kabulSayisi++;
        }

        usort($faturalar, fn($a, $b) => strcmp($b['tarih'], $a['tarih']));

        return [
            'faturalar' => array_slice($faturalar, 0, 500),
            'ozet' => [
                'toplam'       => count($faturalar),
                'toplam_tutar' => $toplamTutar,
                'kabul_sayisi' => $kabulSayisi,
            ],
        ];
    }

    /**
     * Tam cari listesi — filtreli (cari sayfası için)
     */
    public function getCariListesi(array $filters = []): array
    {
        // Ham CSV'yi cache'le
        $raw = Cache::remember('erkur:cari_raw', now()->addHours(2), function () {
            $path = $this->dumpPath . '/CARI.csv';
            if (! file_exists($path)) return [];
            $rows = [];
            $handle = fopen($path, 'r');
            fgetcsv($handle); fgetcsv($handle);
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) < 4) continue;
                $rows[] = $row;
            }
            fclose($handle);
            return $rows;
        });

        $cariler = [];
        $aktif = $alici = $satici = 0;

        foreach ($raw as $row) {
            $isAktif = ($row[29] ?? '0') === '1';
            $ad      = trim($row[3] ?? '');
            $tur     = ($row[1] ?? '1') === '1' ? 'Alıcı' : 'Satıcı';
            $kod     = trim($row[2] ?? '');

            if (! $isAktif || empty($ad)) continue;

            if (! empty($filters['q'])) {
                $q = mb_strtolower($filters['q']);
                if (mb_strpos(mb_strtolower($ad), $q) === false &&
                    mb_strpos(mb_strtolower($kod), $q) === false) {
                    $aktif++;
                    continue;
                }
            }

            if (! empty($filters['tur']) && $tur !== $filters['tur']) {
                $aktif++;
                continue;
            }

            $cariler[] = [
                'id'       => $row[0] ?? '',
                'kod'      => $kod,
                'ad'       => $ad,
                'tur'      => $tur,
                'vergi_no' => $row[13] ?? '',
                'telefon'  => $row[16] ?? '',
                'sehir'    => $row[20] ?? '',
                'vade'     => (int) ($row[7] ?? 0),
                'aktif'    => true,
            ];

            $aktif++;
            if ($tur === 'Alıcı') $alici++; else $satici++;
        }

        usort($cariler, fn($a, $b) => strcmp($a['ad'], $b['ad']));

        return [
            'cariler' => array_slice($cariler, 0, 500),
            'ozet'    => ['toplam' => count($cariler), 'aktif' => $aktif, 'alici' => $alici, 'satici' => $satici],
        ];
    }

    /**
     * Sayım listesi (sayım sayfası için)
     */
    public function getSayimListesi(): array
    {
        return Cache::remember('erkur:sayim_listesi', now()->addHours(2), function () {
            $sayimPath = $this->dumpPath . '/SAYIM.csv';
            $detayPath = $this->dumpPath . '/SAYIM_DETAY.csv';

            $sayimlar = [];

            if (file_exists($sayimPath)) {
                $handle = fopen($sayimPath, 'r');
                fgetcsv($handle); fgetcsv($handle);
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 3) continue;
                    $sayimlar[(int)($row[0]??0)] = [
                        'id'       => $row[0] ?? '',
                        'no'       => $row[1] ?? '',
                        'tarih'    => isset($row[2]) ? substr($row[2], 0, 10) : '',
                        'satirlar' => [],
                        'fark'     => 0,
                    ];
                }
                fclose($handle);
            }

            $detaylar = [];
            if (file_exists($detayPath)) {
                $handle    = fopen($detayPath, 'r');
                $headerRow = fgetcsv($handle);
                fgetcsv($handle);
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 4) continue;
                    $sayimId   = (int) ($row[1] ?? 0);
                    $detaylar[] = [
                        'sayim_id' => $sayimId,
                        'stok'     => $row[2] ?? '',
                        'beklenen' => (float) ($row[3] ?? 0),
                        'sayilan'  => (float) ($row[4] ?? 0),
                        'fark'     => (float) ($row[4] ?? 0) - (float) ($row[3] ?? 0),
                    ];
                }
                fclose($handle);
            }

            foreach ($detaylar as $d) {
                $sid = $d['sayim_id'];
                if (isset($sayimlar[$sid])) {
                    $sayimlar[$sid]['satirlar'][] = $d;
                    $sayimlar[$sid]['fark']      += abs($d['fark']);
                }
            }

            $list = array_values($sayimlar);
            usort($list, fn($a, $b) => strcmp($b['tarih'], $a['tarih']));

            return [
                'sayimlar' => array_slice($list, 0, 100),
                'ozet' => [
                    'toplam_sayim'  => count($list),
                    'toplam_detay'  => count($detaylar),
                    'toplam_fark'   => array_sum(array_column($list, 'fark')),
                ],
            ];
        });
    }

    // ══════════════════════════════════════════════════════════════════
    //  Cari Detay — profil + adres + fiş geçmişi + bakiye
    // ══════════════════════════════════════════════════════════════════

    public function getCariDetay(int $cariId): array
    {
        return Cache::remember("erkur:cari_detay_{$cariId}", now()->addHours(2), function () use ($cariId) {
            $cariPath = $this->dumpPath . '/CARI.csv';
            $cari     = null;

            if (file_exists($cariPath)) {
                $h = fopen($cariPath, 'r');
                fgetcsv($h); fgetcsv($h);
                while (($row = fgetcsv($h)) !== false) {
                    if ((int) ($row[0] ?? 0) === $cariId) {
                        $cari = [
                            'id'            => $row[0],
                            'tur'           => ($row[1] ?? '1') === '1' ? 'Alıcı' : 'Satıcı',
                            'kod'           => trim($row[2] ?? ''),
                            'ad'            => trim($row[3] ?? ''),
                            'iskonto'       => (float) ($row[6] ?? 0),
                            'vade'          => (int) ($row[7] ?? 0),
                            'risk_limiti'   => (float) ($row[10] ?? 0),
                            'vergi_no'      => trim($row[13] ?? ''),
                            'kimlik_no'     => trim($row[14] ?? ''),
                            'vergi_dairesi' => trim($row[15] ?? ''),
                            'web'           => trim($row[16] ?? ''),
                            'email'         => trim($row[17] ?? ''),
                            'aktif'         => ($row[29] ?? '0') === '1',
                            'tarih'         => isset($row[35]) ? substr($row[35], 0, 10) : '',
                        ];
                        break;
                    }
                }
                fclose($h);
            }

            // ── Adresler ─────────────────────────────────────────────
            $adresPath = $this->dumpPath . '/CARI_ADRES.csv';
            $adresler  = [];

            if (file_exists($adresPath)) {
                $h = fopen($adresPath, 'r');
                fgetcsv($h); fgetcsv($h);
                while (($row = fgetcsv($h)) !== false) {
                    if ((int) ($row[1] ?? 0) === $cariId) {
                        $adresler[] = [
                            'id'        => $row[0] ?? '',
                            'ad'        => trim($row[2] ?? ''),
                            'adres'     => trim($row[3] ?? ''),
                            'ililce'    => trim($row[4] ?? ''),
                            'telefon'   => trim($row[10] ?? ''),
                            'cep'       => trim($row[11] ?? ''),
                            'email'     => trim($row[15] ?? ''),
                            'varsayilan'=> ($row[12] ?? '0') === '1',
                        ];
                    }
                }
                fclose($h);
            }

            // ── Fiş Geçmişi ──────────────────────────────────────────
            // FIS.csv: col4=CARI, col8=BELGENO, col9=FIS_TARIHI, col23=GENELTOPLAM, col1=FIS_TURU
            $fisPath = $this->dumpPath . '/FIS.csv';
            $fisler  = [];

            $fisTurleri = [
                1 => 'Satış', 2 => 'Alış', 3 => 'İade Satış', 4 => 'İade Alış',
                5 => 'Satış İrsaliyesi', 6 => 'Alış İrsaliyesi',
                7 => 'Tahsilat', 8 => 'Ödeme', 9 => 'Virman',
                10 => 'Sayım', 11 => 'Sarf', 12 => 'Transfer',
            ];

            if (file_exists($fisPath)) {
                $h = fopen($fisPath, 'r');
                fgetcsv($h); fgetcsv($h);
                while (($row = fgetcsv($h)) !== false) {
                    if ((int) ($row[4] ?? 0) !== $cariId) continue;
                    $fisTuru  = (int) ($row[1] ?? 0);
                    $tutar    = (float) ($row[23] ?? 0);
                    $durum    = (int) ($row[24] ?? 0);
                    $fisler[] = [
                        'id'       => $row[0] ?? '',
                        'tur_kodu' => $fisTuru,
                        'tur_ad'   => $fisTurleri[$fisTuru] ?? "Tür {$fisTuru}",
                        'belgeno'  => trim($row[8] ?? ''),
                        'tarih'    => isset($row[9]) ? substr($row[9], 0, 10) : '',
                        'tutar'    => $tutar,
                        'durum'    => $durum === 1 ? 'Onaylı' : 'Taslak',
                        'vade'     => isset($row[25]) ? substr($row[25], 0, 10) : '',
                    ];
                }
                fclose($h);
            }

            // ── Bakiye ───────────────────────────────────────────────
            $toplamAlacak = 0.0;
            $toplamBorc   = 0.0;

            foreach ($fisler as $f) {
                $t = $f['tur_kodu'];
                if (in_array($t, [1, 3, 5])) $toplamAlacak += $f['tutar'];
                elseif (in_array($t, [2, 4, 6])) $toplamBorc += $f['tutar'];
                elseif ($t === 7) $toplamAlacak -= $f['tutar'];
                elseif ($t === 8) $toplamBorc   -= $f['tutar'];
            }

            usort($fisler, fn($a, $b) => strcmp($b['tarih'], $a['tarih']));

            return [
                'cari'     => $cari,
                'adresler' => $adresler,
                'fisler'   => array_slice($fisler, 0, 200),
                'ozet'     => [
                    'toplam_fis'    => count($fisler),
                    'toplam_alacak' => $toplamAlacak,
                    'toplam_borc'   => $toplamBorc,
                    'bakiye'        => $toplamAlacak - $toplamBorc,
                ],
            ];
        });
    }

    /** Tüm ERP cache'ini temizle (import sonrası çağrılır). */
    public function clearErpCache(): void
    {
        $keys = [
            'erkur:finans_ozeti', 'erkur:pos_ozeti', 'erkur:pos_tutar',
            'erkur:stok_ozeti', 'erkur:sayim_ozeti',
            'erkur:faturalar_raw', 'erkur:cari_raw', 'erkur:sayim_listesi',
        ];
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        // Sayfalanmış cari detayları için pattern ile sil mümkün değil,
        // bu yüzden tag destekleyen driver kullanılıyorsa tag flush önerilir.
        // Şimdilik temel keyler temizlendi.
    }
}


