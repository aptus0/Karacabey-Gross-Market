<?php

namespace App\Services\Erp12;

use PDO;
use Illuminate\Support\Facades\DB;
use Throwable;

final class Erp12LiveAnalyticsService
{
    public function __construct(private readonly Erp12ConnectionResolver $resolver)
    {
    }

    public function available(?int $tenantId = null): bool
    {
        try {
            return $this->resolver->connect($tenantId) instanceof PDO;
        } catch (Throwable) {
            return false;
        }
    }

    public function getFaturalar(array $filters = [], ?int $tenantId = null): ?array
    {
        $pdo = $this->safeConnect($tenantId);
        if (! $pdo) {
            return $this->getFaturalarFromSnapshot($filters, $tenantId);
        }

        

        $where = ['ISNULL(f.AKTIF, 1) = 1'];
        $params = [];

        if (! empty($filters['tarih_baslangic'])) {
            $where[] = 'CONVERT(date, f.FIS_TARIHI) >= ?';
            $params[] = $filters['tarih_baslangic'];
        }
        if (! empty($filters['tarih_bitis'])) {
            $where[] = 'CONVERT(date, f.FIS_TARIHI) <= ?';
            $params[] = $filters['tarih_bitis'];
        }
        if (! empty($filters['tip'])) {
            $where[] = 'UPPER(ISNULL(ft.AD, \'\')) LIKE ?';
            $params[] = '%'.mb_strtoupper((string) $filters['tip']).'%';
        }
        if (! empty($filters['durum'])) {
            if ($filters['durum'] === 'kabul') {
                $where[] = 'f.E_FATURA_KABUL_DURUMU IS NOT NULL';
            } elseif ($filters['durum'] === 'bekliyor') {
                $where[] = 'f.E_FATURA_KABUL_DURUMU IS NULL';
            }
        }

        $whereSql = implode(' AND ', $where);

        $summary = $this->fetchOne($pdo, "
            SELECT
                COUNT(*) AS toplam,
                SUM(ISNULL(f.GENELTOPLAM, 0)) AS toplam_tutar,
                SUM(CASE WHEN f.E_FATURA_KABUL_DURUMU IS NOT NULL THEN 1 ELSE 0 END) AS kabul_sayisi
            FROM FIS f
            LEFT JOIN FIS_TURU ft ON ft.ID = f.FIS_TURU
            WHERE {$whereSql}
        ", $params);

        $stmt = $pdo->prepare("
            SELECT TOP 500
                f.ID AS id,
                f.BELGENO AS belgeno,
                CONVERT(varchar(10), f.FIS_TARIHI, 23) AS tarih,
                ft.AD AS tip,
                f.VERGI_NUMARASI AS vergi_no,
                f.CARI AS cari_id,
                f.GENELTOPLAM AS tutar,
                CASE WHEN f.E_FATURA_KABUL_DURUMU IS NOT NULL THEN 'Kabul' ELSE 'Bekliyor' END AS kabul,
                f.EMAIL AS email
            FROM FIS f
            LEFT JOIN FIS_TURU ft ON ft.ID = f.FIS_TURU
            WHERE {$whereSql}
            ORDER BY f.FIS_TARIHI DESC, f.ID DESC
        ");
        $stmt->execute($params);

        return [
            'faturalar' => array_map(fn (array $row): array => [
                'id' => (string) $row['id'],
                'belgeno' => (string) ($row['belgeno'] ?? ''),
                'tarih' => (string) ($row['tarih'] ?? ''),
                'tip' => (string) ($row['tip'] ?? ''),
                'vergi_no' => (string) ($row['vergi_no'] ?? ''),
                'cari_id' => (string) ($row['cari_id'] ?? ''),
                'tutar' => (float) ($row['tutar'] ?? 0),
                'kabul' => (string) ($row['kabul'] ?? 'Bekliyor'),
                'email' => (string) ($row['email'] ?? ''),
            ], $stmt->fetchAll() ?: []),
            'ozet' => [
                'toplam' => (int) ($summary['toplam'] ?? 0),
                'toplam_tutar' => (float) ($summary['toplam_tutar'] ?? 0),
                'kabul_sayisi' => (int) ($summary['kabul_sayisi'] ?? 0),
            ],
        ];
    }

    public function getFatura(int $fisId, ?int $tenantId = null): ?array
    {
        $pdo = $this->safeConnect($tenantId);
        if (! $pdo) {
            return $this->getFaturaFromSnapshot($fisId, $tenantId);
        }

        $row = $this->fetchOne($pdo, "
            SELECT TOP 1
                f.ID AS id,
                f.BELGENO AS belgeno,
                CONVERT(varchar(10), f.FIS_TARIHI, 23) AS tarih,
                ft.AD AS tip,
                f.VERGI_NUMARASI AS vergi_no,
                f.CARI AS cari_id,
                f.GENELTOPLAM AS tutar,
                CASE WHEN f.E_FATURA_KABUL_DURUMU IS NOT NULL THEN 'Kabul' ELSE 'Bekliyor' END AS kabul,
                f.EMAIL AS email
            FROM FIS f
            LEFT JOIN FIS_TURU ft ON ft.ID = f.FIS_TURU
            WHERE f.ID = ? AND ISNULL(f.AKTIF, 1) = 1
        ", [$fisId]);

        if (! $row) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'belgeno' => (string) ($row['belgeno'] ?? ''),
            'tarih' => (string) ($row['tarih'] ?? ''),
            'tip' => (string) ($row['tip'] ?? ''),
            'vergi_no' => (string) ($row['vergi_no'] ?? ''),
            'cari_id' => (string) ($row['cari_id'] ?? ''),
            'tutar' => (float) ($row['tutar'] ?? 0),
            'kabul' => (string) ($row['kabul'] ?? 'Bekliyor'),
            'email' => (string) ($row['email'] ?? ''),
        ];
    }

    public function getCariListesi(array $filters = [], ?int $tenantId = null): ?array
    {
        $pdo = $this->safeConnect($tenantId);
        if (! $pdo) {
            return $this->getCariListesiFromSnapshot($filters, $tenantId);
        }

        $where = ['ISNULL(c.AKTIF, 1) = 1'];
        $params = [];
        if (! empty($filters['q'])) {
            $where[] = '(c.AD LIKE ? OR c.KOD LIKE ?)';
            $params[] = '%'.$filters['q'].'%';
            $params[] = '%'.$filters['q'].'%';
        }
        if (! empty($filters['tur'])) {
            $where[] = $filters['tur'] === 'Alıcı' ? 'ISNULL(c.SATIS_YAPILIR, 0) = 1' : 'ISNULL(c.ALIS_YAPILIR, 0) = 1';
        }

        $whereSql = implode(' AND ', $where);
        $summary = $this->fetchOne($pdo, "
            SELECT
                COUNT(*) AS toplam,
                SUM(CASE WHEN ISNULL(AKTIF, 1) = 1 THEN 1 ELSE 0 END) AS aktif,
                SUM(CASE WHEN ISNULL(SATIS_YAPILIR, 0) = 1 THEN 1 ELSE 0 END) AS alici,
                SUM(CASE WHEN ISNULL(ALIS_YAPILIR, 0) = 1 THEN 1 ELSE 0 END) AS satici
            FROM CARI
        ");

        $stmt = $pdo->prepare("
            SELECT TOP 500
                c.ID AS id,
                c.KOD AS kod,
                c.AD AS ad,
                CASE WHEN ISNULL(c.SATIS_YAPILIR, 0) = 1 THEN 'Alıcı' ELSE 'Satıcı' END AS tur,
                c.VERGI_NUMARASI AS vergi_no,
                a.TELEFON AS telefon,
                il.AD AS sehir,
                c.VADE AS vade,
                ISNULL(fin.toplam_borc, 0) AS toplam_borc,
                ISNULL(fin.toplam_alacak, 0) AS toplam_alacak,
                ISNULL(fin.toplam_borc, 0) - ISNULL(fin.toplam_alacak, 0) AS bakiye
            FROM CARI c
            LEFT JOIN CARI_ADRES a ON a.CARI = c.ID AND ISNULL(a.VARSAYILAN, 0) = 1
            LEFT JOIN ILILCE il ON il.ID = a.ILILCE
            OUTER APPLY (
                SELECT
                    SUM(CASE WHEN fd.KART_BORCLU = c.ID THEN ISNULL(fd.TUTAR, 0) ELSE 0 END) AS toplam_borc,
                    SUM(CASE WHEN fd.KART_ALACAKLI = c.ID THEN ISNULL(fd.TUTAR, 0) ELSE 0 END) AS toplam_alacak
                FROM FINANS_DETAY fd
                WHERE fd.KART_BORCLU = c.ID OR fd.KART_ALACAKLI = c.ID
            ) fin
            WHERE {$whereSql}
            ORDER BY c.AD
        ");
        $stmt->execute($params);

        return [
            'cariler' => array_map(fn (array $row): array => [
                'id' => (string) $row['id'],
                'kod' => (string) ($row['kod'] ?? ''),
                'ad' => (string) ($row['ad'] ?? ''),
                'tur' => (string) ($row['tur'] ?? 'Alıcı'),
                'vergi_no' => (string) ($row['vergi_no'] ?? ''),
                'telefon' => (string) ($row['telefon'] ?? ''),
                'sehir' => (string) ($row['sehir'] ?? ''),
                'vade' => (int) ($row['vade'] ?? 0),
                'toplam_borc' => (float) ($row['toplam_borc'] ?? 0),
                'toplam_alacak' => (float) ($row['toplam_alacak'] ?? 0),
                'bakiye' => (float) ($row['bakiye'] ?? 0),
                'aktif' => true,
            ], $stmt->fetchAll() ?: []),
            'ozet' => [
                'toplam' => (int) ($summary['toplam'] ?? 0),
                'aktif' => (int) ($summary['aktif'] ?? 0),
                'alici' => (int) ($summary['alici'] ?? 0),
                'satici' => (int) ($summary['satici'] ?? 0),
            ],
        ];
    }

    public function getFaturaDetay(int $fisId, ?int $tenantId = null): ?array
    {
        $pdo = $this->safeConnect($tenantId);
        if (! $pdo) {
            return $this->getFaturaDetayFromSnapshot($fisId, $tenantId);
        }

        try {
            $stmt = $pdo->prepare("
                SELECT
                    fd.ID AS id,
                    fd.STOK AS stok_id,
                    s.KOD AS stok_kod,
                    s.AD AS stok_ad,
                    fd.BARKOD AS barkod,
                    fd.MIKTAR AS miktar,
                    fd.MIKTAR_GIRIS AS miktar_giris,
                    fd.MIKTAR_CIKIS AS miktar_cikis,
                    fd.FIYAT AS fiyat,
                    fd.DAHIL_FIYAT AS dahil_fiyat,
                    fd.TUTAR AS tutar,
                    fd.KDV AS kdv,
                    l.AD AS lokasyon
                FROM FIS_DETAY fd
                LEFT JOIN FIS f ON f.ID = fd.FIS
                LEFT JOIN STOK s ON s.ID = fd.STOK
                LEFT JOIN LOKASYON l ON l.ID = ISNULL(fd.LOKASYON, f.LOKASYON)
                WHERE fd.FIS = ?
                ORDER BY fd.ID
            ");
            $stmt->execute([$fisId]);
        } catch (Throwable) {
            return null;
        }

        return array_map(fn (array $row): array => [
            'id' => (string) ($row['id'] ?? ''),
            'stok_id' => (string) ($row['stok_id'] ?? ''),
            'stok_kod' => (string) ($row['stok_kod'] ?? ''),
            'stok_ad' => (string) ($row['stok_ad'] ?? ''),
            'barkod' => (string) ($row['barkod'] ?? ''),
            'miktar' => (float) ($row['miktar'] ?? 0),
            'miktar_giris' => (float) ($row['miktar_giris'] ?? 0),
            'miktar_cikis' => (float) ($row['miktar_cikis'] ?? 0),
            'fiyat' => (float) ($row['fiyat'] ?? 0),
            'dahil_fiyat' => (float) ($row['dahil_fiyat'] ?? 0),
            'tutar' => (float) ($row['tutar'] ?? 0),
            'kdv' => (float) ($row['kdv'] ?? 0),
            'lokasyon' => (string) ($row['lokasyon'] ?? ''),
        ], $stmt->fetchAll() ?: []);
    }

    public function getSayimListesi(?int $tenantId = null): ?array
    {
        $pdo = $this->safeConnect($tenantId);
        if (! $pdo) {
            return $this->getCariDetayFromSnapshot($cariId, $tenantId);
        }

        $summary = $this->fetchOne($pdo, "
            SELECT COUNT(*) AS toplam_sayim
            FROM SAYIM
        ");
        $detail = $this->fetchOne($pdo, "
            SELECT COUNT(*) AS toplam_detay, SUM(ABS(ISNULL(MIKTAR, 0))) AS toplam_fark
            FROM SAYIM_DETAY
        ");

        $stmt = $pdo->query("
            SELECT TOP 100
                s.ID AS id,
                CONVERT(varchar(10), s.TARIH, 23) AS tarih,
                l.AD AS lokasyon,
                s.ACIKLAMA AS aciklama,
                COUNT(sd.ID) AS satir_sayisi,
                SUM(ABS(ISNULL(sd.MIKTAR, 0))) AS fark
            FROM SAYIM s
            LEFT JOIN LOKASYON l ON l.ID = s.LOKASYON
            LEFT JOIN SAYIM_DETAY sd ON sd.SAYIM = s.ID
            GROUP BY s.ID, s.TARIH, l.AD, s.ACIKLAMA
            ORDER BY s.TARIH DESC, s.ID DESC
        ");

        return [
            'sayimlar' => array_map(fn (array $row): array => [
                'id' => (string) $row['id'],
                'no' => (string) ($row['lokasyon'] ?? ''),
                'tarih' => (string) ($row['tarih'] ?? ''),
                'satirlar' => [],
                'fark' => (float) ($row['fark'] ?? 0),
                'satir_sayisi' => (int) ($row['satir_sayisi'] ?? 0),
                'aciklama' => (string) ($row['aciklama'] ?? ''),
            ], $stmt->fetchAll() ?: []),
            'ozet' => [
                'toplam_sayim' => (int) ($summary['toplam_sayim'] ?? 0),
                'toplam_detay' => (int) ($detail['toplam_detay'] ?? 0),
                'toplam_fark' => (float) ($detail['toplam_fark'] ?? 0),
            ],
        ];
    }

    public function getPosRaporlari(array $filters = [], ?int $tenantId = null): ?array
    {
        $pdo = $this->safeConnect($tenantId);
        if (! $pdo) {
            return null;
        }

        $where = ['p.Z_TARIHI IS NOT NULL'];
        $params = [];
        if (! empty($filters['tarih_baslangic'])) {
            $where[] = 'CONVERT(date, p.Z_TARIHI) >= ?';
            $params[] = $filters['tarih_baslangic'];
        }
        if (! empty($filters['tarih_bitis'])) {
            $where[] = 'CONVERT(date, p.Z_TARIHI) <= ?';
            $params[] = $filters['tarih_bitis'];
        }
        if (! empty($filters['terminal'])) {
            $where[] = '(pc.AD LIKE ? OR CAST(p.PC_ID AS varchar(30)) = ?)';
            $params[] = '%'.$filters['terminal'].'%';
            $params[] = (string) $filters['terminal'];
        }
        $whereSql = implode(' AND ', $where);

        $summary = $this->fetchOne($pdo, "
            SELECT
                COUNT(*) AS pos_receipts,
                COUNT(DISTINCT p.FIS) AS distinct_fis,
                COUNT(DISTINCT p.PC_ID) AS terminal_count,
                COUNT(DISTINCT p.Z_NO) AS z_count,
                MIN(p.KAPANIS_TARIHI) AS min_close_date,
                MAX(p.KAPANIS_TARIHI) AS max_close_date
            FROM FIS_POS p
            LEFT JOIN PC_AD pc ON pc.ID = p.PC_ID
            WHERE {$whereSql}
        ", $params);

        $stmt = $pdo->prepare("
            SELECT TOP 300
                p.KASA_Z,
                p.Z_NO,
                CONVERT(varchar(10), p.Z_TARIHI, 23) AS z_date,
                p.PC_ID,
                pc.AD AS terminal_name,
                COUNT(*) AS receipt_count,
                SUM(ISNULL(f.GENELTOPLAM, 0)) AS gross_amount
            FROM FIS_POS p
            LEFT JOIN FIS f ON f.ID = p.FIS
            LEFT JOIN PC_AD pc ON pc.ID = p.PC_ID
            WHERE {$whereSql}
            GROUP BY p.KASA_Z, p.Z_NO, CONVERT(varchar(10), p.Z_TARIHI, 23), p.PC_ID, pc.AD
            ORDER BY z_date DESC, p.KASA_Z DESC, p.Z_NO DESC
        ");
        $stmt->execute($params);

        return [
            'raporlar' => array_map(fn (array $row): array => [
                'kasa_z' => (string) ($row['KASA_Z'] ?? ''),
                'z_no' => (string) ($row['Z_NO'] ?? ''),
                'z_tarihi' => (string) ($row['z_date'] ?? ''),
                'pc_id' => (string) ($row['PC_ID'] ?? ''),
                'terminal' => (string) ($row['terminal_name'] ?? ''),
                'fis_sayisi' => (int) ($row['receipt_count'] ?? 0),
                'tutar' => (float) ($row['gross_amount'] ?? 0),
            ], $stmt->fetchAll() ?: []),
            'ozet' => [
                'pos_fis' => (int) ($summary['pos_receipts'] ?? 0),
                'tekil_fis' => (int) ($summary['distinct_fis'] ?? 0),
                'terminal' => (int) ($summary['terminal_count'] ?? 0),
                'z_sayisi' => (int) ($summary['z_count'] ?? 0),
                'son_kapanis' => (string) ($summary['max_close_date'] ?? ''),
            ],
        ];
    }

    public function getCariDetay(int $cariId, ?int $tenantId = null): ?array
    {
        $pdo = $this->safeConnect($tenantId);
        if (! $pdo) {
            return null;
        }

        $cari = $this->fetchOne($pdo, "
            SELECT TOP 1 ID, KOD, AD, VERGI_NUMARASI, KIMLIK_NO, VERGI_DAIRESI, WEB, EMAIL, VADE, RISK, AKTIF,
                CASE WHEN ISNULL(SATIS_YAPILIR, 0) = 1 THEN 'Alıcı' ELSE 'Satıcı' END AS tur,
                CONVERT(varchar(10), TARIH, 23) AS tarih
            FROM CARI
            WHERE ID = ?
        ", [$cariId]);

        if (! $cari) {
            return null;
        }

        $adresStmt = $pdo->prepare("
            SELECT ID, AD, ADRES, TELEFON, TELEFON_CEP, EMAIL, VARSAYILAN
            FROM CARI_ADRES
            WHERE CARI = ?
            ORDER BY VARSAYILAN DESC, ID
        ");
        $adresStmt->execute([$cariId]);

        $fisStmt = $pdo->prepare("
            SELECT TOP 200 f.ID, f.FIS_TURU AS tur_kodu, ft.AD AS tur_ad, f.BELGENO,
                CONVERT(varchar(10), f.FIS_TARIHI, 23) AS tarih,
                f.GENELTOPLAM AS tutar,
                CASE WHEN ISNULL(f.DURUM, 0) = 1 THEN 'Onaylı' ELSE 'Taslak' END AS durum,
                CONVERT(varchar(10), f.VADE, 23) AS vade
            FROM FIS f
            LEFT JOIN FIS_TURU ft ON ft.ID = f.FIS_TURU
            WHERE f.CARI = ?
            ORDER BY f.FIS_TARIHI DESC, f.ID DESC
        ");
        $fisStmt->execute([$cariId]);

        $balance = $this->fetchOne($pdo, "
            WITH hareket AS (
                SELECT SUM(ISNULL(TUTAR, 0)) AS borc, CAST(0 AS decimal(38,8)) AS alacak
                FROM FINANS_DETAY WHERE KART_BORCLU = ?
                UNION ALL
                SELECT CAST(0 AS decimal(38,8)) AS borc, SUM(ISNULL(TUTAR, 0)) AS alacak
                FROM FINANS_DETAY WHERE KART_ALACAKLI = ?
            )
            SELECT SUM(borc) AS toplam_borc, SUM(alacak) AS toplam_alacak, SUM(borc) - SUM(alacak) AS bakiye
            FROM hareket
        ", [$cariId, $cariId]);

        $fisler = array_map(fn (array $row): array => [
            'id' => (string) $row['ID'],
            'tur_kodu' => (int) ($row['tur_kodu'] ?? 0),
            'tur_ad' => (string) ($row['tur_ad'] ?? ''),
            'belgeno' => (string) ($row['BELGENO'] ?? ''),
            'tarih' => (string) ($row['tarih'] ?? ''),
            'tutar' => (float) ($row['tutar'] ?? 0),
            'durum' => (string) ($row['durum'] ?? ''),
            'vade' => (string) ($row['vade'] ?? ''),
        ], $fisStmt->fetchAll() ?: []);

        return [
            'cari' => [
                'id' => (string) $cari['ID'],
                'tur' => (string) ($cari['tur'] ?? ''),
                'kod' => (string) ($cari['KOD'] ?? ''),
                'ad' => (string) ($cari['AD'] ?? ''),
                'iskonto' => 0,
                'vade' => (int) ($cari['VADE'] ?? 0),
                'risk_limiti' => (float) ($cari['RISK'] ?? 0),
                'vergi_no' => (string) ($cari['VERGI_NUMARASI'] ?? ''),
                'kimlik_no' => (string) ($cari['KIMLIK_NO'] ?? ''),
                'vergi_dairesi' => (string) ($cari['VERGI_DAIRESI'] ?? ''),
                'web' => (string) ($cari['WEB'] ?? ''),
                'email' => (string) ($cari['EMAIL'] ?? ''),
                'aktif' => (bool) ($cari['AKTIF'] ?? true),
                'tarih' => (string) ($cari['tarih'] ?? ''),
            ],
            'adresler' => array_map(fn (array $row): array => [
                'id' => (string) $row['ID'],
                'ad' => (string) ($row['AD'] ?? ''),
                'adres' => (string) ($row['ADRES'] ?? ''),
                'ililce' => '',
                'telefon' => (string) ($row['TELEFON'] ?? ''),
                'cep' => (string) ($row['TELEFON_CEP'] ?? ''),
                'email' => (string) ($row['EMAIL'] ?? ''),
                'varsayilan' => (bool) ($row['VARSAYILAN'] ?? false),
            ], $adresStmt->fetchAll() ?: []),
            'fisler' => $fisler,
            'ozet' => [
                'toplam_fis' => count($fisler),
                'toplam_alacak' => (float) ($balance['toplam_alacak'] ?? 0),
                'toplam_borc' => (float) ($balance['toplam_borc'] ?? 0),
                'bakiye' => (float) ($balance['bakiye'] ?? 0),
            ],
        ];
    }

    private function getFaturalarFromSnapshot(array $filters, ?int $tenantId): ?array
    {
        $tenantId ??= 1;
        if (! DB::getSchemaBuilder()->hasTable('erp12_faturalar')) {
            return null;
        }

        $base = DB::table('erp12_faturalar')->where('tenant_id', $tenantId);
        if (! empty($filters['tarih_baslangic'])) {
            $base->whereDate('tarih', '>=', $filters['tarih_baslangic']);
        }
        if (! empty($filters['tarih_bitis'])) {
            $base->whereDate('tarih', '<=', $filters['tarih_bitis']);
        }
        if (! empty($filters['tip'])) {
            $base->where('tip', 'like', '%'.$filters['tip'].'%');
        }
        if (! empty($filters['durum'])) {
            $base->where('durum', $filters['durum'] === 'kabul' ? 'Kabul' : 'Bekliyor');
        }

        $summary = (clone $base)->selectRaw("
            COUNT(*) AS toplam,
            COALESCE(SUM(tutar), 0) AS toplam_tutar,
            SUM(CASE WHEN durum = 'Kabul' THEN 1 ELSE 0 END) AS kabul_sayisi
        ")->first();

        $rows = (clone $base)
            ->orderByDesc('tarih')
            ->orderByDesc('external_id')
            ->limit(500)
            ->get();

        return [
            'faturalar' => $rows->map(fn ($row): array => [
                'id' => (string) $row->external_id,
                'belgeno' => (string) ($row->belgeno ?? ''),
                'tarih' => (string) ($row->tarih ?? ''),
                'tip' => (string) ($row->tip ?? ''),
                'vergi_no' => (string) ($row->vergi_no ?? ''),
                'cari_id' => (string) ($row->cari_external_id ?? ''),
                'tutar' => (float) ($row->tutar ?? 0),
                'kabul' => (string) ($row->durum ?? 'Bekliyor'),
                'email' => (string) ($row->email ?? ''),
            ])->all(),
            'ozet' => [
                'toplam' => (int) ($summary->toplam ?? 0),
                'toplam_tutar' => (float) ($summary->toplam_tutar ?? 0),
                'kabul_sayisi' => (int) ($summary->kabul_sayisi ?? 0),
            ],
        ];
    }

    private function getFaturaFromSnapshot(int $fisId, ?int $tenantId): ?array
    {
        $tenantId ??= 1;
        if (! DB::getSchemaBuilder()->hasTable('erp12_faturalar')) {
            return null;
        }

        $row = DB::table('erp12_faturalar')
            ->where('tenant_id', $tenantId)
            ->where('external_id', (string) $fisId)
            ->first();

        if (! $row) {
            return null;
        }

        return [
            'id' => (string) $row->external_id,
            'belgeno' => (string) ($row->belgeno ?? ''),
            'tarih' => (string) ($row->tarih ?? ''),
            'tip' => (string) ($row->tip ?? ''),
            'vergi_no' => (string) ($row->vergi_no ?? ''),
            'cari_id' => (string) ($row->cari_external_id ?? ''),
            'tutar' => (float) ($row->tutar ?? 0),
            'kabul' => (string) ($row->durum ?? 'Bekliyor'),
            'email' => (string) ($row->email ?? ''),
        ];
    }

    private function getFaturaDetayFromSnapshot(int $fisId, ?int $tenantId): ?array
    {
        $tenantId ??= 1;
        if (! DB::getSchemaBuilder()->hasTable('erp12_fatura_satirlari')) {
            return null;
        }

        return DB::table('erp12_fatura_satirlari')
            ->where('tenant_id', $tenantId)
            ->where('fatura_external_id', (string) $fisId)
            ->orderBy('external_id')
            ->get()
            ->map(fn ($row): array => [
                'id' => (string) $row->external_id,
                'stok_id' => (string) ($row->stok_external_id ?? ''),
                'stok_kod' => (string) ($row->stok_kod ?? ''),
                'stok_ad' => (string) ($row->stok_ad ?? ''),
                'barkod' => (string) ($row->barkod ?? ''),
                'miktar' => (float) ($row->miktar ?? 0),
                'miktar_giris' => (float) ($row->miktar_giris ?? 0),
                'miktar_cikis' => (float) ($row->miktar_cikis ?? 0),
                'fiyat' => (float) ($row->fiyat ?? 0),
                'dahil_fiyat' => (float) ($row->dahil_fiyat ?? 0),
                'tutar' => (float) ($row->tutar ?? 0),
                'kdv' => (float) ($row->kdv ?? 0),
                'lokasyon' => (string) ($row->lokasyon ?? ''),
            ])->all();
    }

    private function getCariListesiFromSnapshot(array $filters, ?int $tenantId): ?array
    {
        $tenantId ??= 1;
        if (! DB::getSchemaBuilder()->hasTable('erp12_cariler')) {
            return null;
        }

        $base = DB::table('erp12_cariler')->where('tenant_id', $tenantId);
        if (! empty($filters['q'])) {
            $q = '%'.$filters['q'].'%';
            $base->where(fn ($query) => $query->where('ad', 'like', $q)->orWhere('kod', 'like', $q));
        }
        if (! empty($filters['tur'])) {
            $base->where('tur', $filters['tur']);
        }

        $summary = DB::table('erp12_cariler')
            ->where('tenant_id', $tenantId)
            ->selectRaw("
                COUNT(*) AS toplam,
                SUM(CASE WHEN aktif = 1 THEN 1 ELSE 0 END) AS aktif,
                SUM(CASE WHEN tur = 'Alıcı' THEN 1 ELSE 0 END) AS alici,
                SUM(CASE WHEN tur = 'Satıcı' THEN 1 ELSE 0 END) AS satici
            ")->first();

        $rows = (clone $base)->orderBy('ad')->limit(500)->get();

        return [
            'cariler' => $rows->map(fn ($row): array => [
                'id' => (string) $row->external_id,
                'kod' => (string) ($row->kod ?? ''),
                'ad' => (string) ($row->ad ?? ''),
                'tur' => (string) ($row->tur ?? 'Alıcı'),
                'vergi_no' => (string) ($row->vergi_no ?? ''),
                'telefon' => (string) ($row->telefon ?? ''),
                'sehir' => (string) ($row->sehir ?? ''),
                'vade' => (int) ($row->vade ?? 0),
                'toplam_borc' => (float) ($row->toplam_borc ?? 0),
                'toplam_alacak' => (float) ($row->toplam_alacak ?? 0),
                'bakiye' => (float) ($row->bakiye ?? 0),
                'aktif' => (bool) ($row->aktif ?? true),
            ])->all(),
            'ozet' => [
                'toplam' => (int) ($summary->toplam ?? 0),
                'aktif' => (int) ($summary->aktif ?? 0),
                'alici' => (int) ($summary->alici ?? 0),
                'satici' => (int) ($summary->satici ?? 0),
            ],
        ];
    }

    private function getCariDetayFromSnapshot(int $cariId, ?int $tenantId): ?array
    {
        $tenantId ??= 1;
        if (! DB::getSchemaBuilder()->hasTable('erp12_cariler')) {
            return null;
        }

        $row = DB::table('erp12_cariler')
            ->where('tenant_id', $tenantId)
            ->where('external_id', (string) $cariId)
            ->first();

        if (! $row) {
            return null;
        }

        $fisler = DB::table('erp12_faturalar')
            ->where('tenant_id', $tenantId)
            ->where('cari_external_id', (string) $cariId)
            ->orderByDesc('tarih')
            ->orderByDesc('external_id')
            ->limit(200)
            ->get()
            ->map(fn ($fis): array => [
                'id' => (string) $fis->external_id,
                'tur_kodu' => 0,
                'tur_ad' => (string) ($fis->tip ?? ''),
                'belgeno' => (string) ($fis->belgeno ?? ''),
                'tarih' => (string) ($fis->tarih ?? ''),
                'tutar' => (float) ($fis->tutar ?? 0),
                'durum' => (string) ($fis->durum ?? ''),
                'vade' => '',
            ])->all();

        return [
            'cari' => [
                'id' => (string) $row->external_id,
                'tur' => (string) ($row->tur ?? ''),
                'kod' => (string) ($row->kod ?? ''),
                'ad' => (string) ($row->ad ?? ''),
                'iskonto' => 0,
                'vade' => (int) ($row->vade ?? 0),
                'risk_limiti' => (float) ($row->risk_limiti ?? 0),
                'vergi_no' => (string) ($row->vergi_no ?? ''),
                'kimlik_no' => (string) ($row->kimlik_no ?? ''),
                'vergi_dairesi' => (string) ($row->vergi_dairesi ?? ''),
                'web' => (string) ($row->web ?? ''),
                'email' => (string) ($row->email ?? ''),
                'aktif' => (bool) ($row->aktif ?? true),
                'tarih' => (string) ($row->erp_created_at ?? ''),
            ],
            'adresler' => [],
            'fisler' => $fisler,
            'ozet' => [
                'toplam_fis' => count($fisler),
                'toplam_alacak' => (float) ($row->toplam_alacak ?? 0),
                'toplam_borc' => (float) ($row->toplam_borc ?? 0),
                'bakiye' => (float) ($row->bakiye ?? 0),
            ],
        ];
    }

    private function safeConnect(?int $tenantId): ?PDO
    {
        try {
            return $this->resolver->connect($tenantId);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<int, mixed> $params */
    private function fetchOne(PDO $pdo, string $sql, array $params = []): array
    {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
