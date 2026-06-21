<?php

namespace App\Services\Erp12;

use Illuminate\Support\Facades\DB;
use PDO;

final class Erp12SnapshotImportService
{
    public function import(PDO $pdo, int $tenantId): array
    {
        return [
            'cariler' => $this->importCariler($pdo, $tenantId),
            'faturalar' => $this->importFaturalar($pdo, $tenantId),
            'satirlar' => $this->importFaturaSatirlari($pdo, $tenantId),
        ];
    }

    private function importCariler(PDO $pdo, int $tenantId): int
    {
        $stmt = $pdo->query("
            WITH finans AS (
                SELECT KART_BORCLU AS cari_id, SUM(ISNULL(TUTAR, 0)) AS borc, CAST(0 AS decimal(38,8)) AS alacak
                FROM FINANS_DETAY
                WHERE KART_BORCLU IS NOT NULL
                GROUP BY KART_BORCLU
                UNION ALL
                SELECT KART_ALACAKLI AS cari_id, CAST(0 AS decimal(38,8)) AS borc, SUM(ISNULL(TUTAR, 0)) AS alacak
                FROM FINANS_DETAY
                WHERE KART_ALACAKLI IS NOT NULL
                GROUP BY KART_ALACAKLI
            ),
            bakiye AS (
                SELECT cari_id, SUM(borc) AS toplam_borc, SUM(alacak) AS toplam_alacak, SUM(borc) - SUM(alacak) AS bakiye
                FROM finans
                GROUP BY cari_id
            )
            SELECT
                c.ID,
                c.KOD,
                c.AD,
                CASE WHEN ISNULL(c.SATIS_YAPILIR, 0) = 1 THEN 'Alıcı' ELSE 'Satıcı' END AS tur,
                c.VERGI_NUMARASI,
                c.KIMLIK_NO,
                c.VERGI_DAIRESI,
                a.TELEFON,
                a.TELEFON_CEP,
                COALESCE(a.EMAIL, c.EMAIL) AS EMAIL,
                c.WEB,
                il.AD AS sehir,
                c.VADE,
                c.RISK,
                ISNULL(b.toplam_borc, 0) AS toplam_borc,
                ISNULL(b.toplam_alacak, 0) AS toplam_alacak,
                ISNULL(b.bakiye, 0) AS bakiye,
                ISNULL(c.AKTIF, 1) AS AKTIF,
                CONVERT(varchar(10), c.TARIH, 23) AS tarih
            FROM CARI c
            LEFT JOIN CARI_ADRES a ON a.CARI = c.ID AND ISNULL(a.VARSAYILAN, 0) = 1
            LEFT JOIN ILILCE il ON il.ID = a.ILILCE
            LEFT JOIN bakiye b ON b.cari_id = c.ID
        ");

        $count = 0;
        $batch = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $batch[] = [
                'tenant_id' => $tenantId,
                'external_id' => (string) $row['ID'],
                'kod' => $this->nullString($row['KOD'] ?? null),
                'ad' => (string) ($row['AD'] ?? ''),
                'tur' => $this->nullString($row['tur'] ?? null),
                'vergi_no' => $this->nullString($row['VERGI_NUMARASI'] ?? null),
                'kimlik_no' => $this->nullString($row['KIMLIK_NO'] ?? null),
                'vergi_dairesi' => $this->nullString($row['VERGI_DAIRESI'] ?? null),
                'telefon' => $this->nullString($row['TELEFON'] ?? null),
                'cep' => $this->nullString($row['TELEFON_CEP'] ?? null),
                'email' => $this->nullString($row['EMAIL'] ?? null),
                'web' => $this->nullString($row['WEB'] ?? null),
                'sehir' => $this->nullString($row['sehir'] ?? null),
                'vade' => (int) ($row['VADE'] ?? 0),
                'risk_limiti' => (float) ($row['RISK'] ?? 0),
                'toplam_borc' => (float) ($row['toplam_borc'] ?? 0),
                'toplam_alacak' => (float) ($row['toplam_alacak'] ?? 0),
                'bakiye' => (float) ($row['bakiye'] ?? 0),
                'aktif' => (bool) ($row['AKTIF'] ?? true),
                'erp_created_at' => $this->nullString($row['tarih'] ?? null),
                'last_synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $count++;
            $this->flushIfNeeded('erp12_cariler', $batch);
        }
        $this->flush('erp12_cariler', $batch);

        return $count;
    }

    private function importFaturalar(PDO $pdo, int $tenantId): int
    {
        $stmt = $pdo->query("
            SELECT
                f.ID,
                f.BELGENO,
                CONVERT(varchar(10), f.FIS_TARIHI, 23) AS tarih,
                ft.AD AS tip,
                f.VERGI_NUMARASI,
                f.CARI,
                f.GENELTOPLAM,
                CASE WHEN f.E_FATURA_KABUL_DURUMU IS NOT NULL THEN 'Kabul' ELSE 'Bekliyor' END AS kabul,
                f.EMAIL
            FROM FIS f
            LEFT JOIN FIS_TURU ft ON ft.ID = f.FIS_TURU
            WHERE ISNULL(f.AKTIF, 1) = 1
        ");

        $count = 0;
        $batch = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $batch[] = [
                'tenant_id' => $tenantId,
                'external_id' => (string) $row['ID'],
                'belgeno' => $this->nullString($row['BELGENO'] ?? null),
                'tarih' => $this->nullString($row['tarih'] ?? null),
                'tip' => $this->nullString($row['tip'] ?? null),
                'vergi_no' => $this->nullString($row['VERGI_NUMARASI'] ?? null),
                'cari_external_id' => $this->nullString($row['CARI'] ?? null),
                'tutar' => (float) ($row['GENELTOPLAM'] ?? 0),
                'durum' => (string) ($row['kabul'] ?? 'Bekliyor'),
                'email' => $this->nullString($row['EMAIL'] ?? null),
                'last_synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $count++;
            $this->flushIfNeeded('erp12_faturalar', $batch);
        }
        $this->flush('erp12_faturalar', $batch);

        return $count;
    }

    private function importFaturaSatirlari(PDO $pdo, int $tenantId): int
    {
        $stmt = $pdo->query("
            SELECT
                fd.ID,
                fd.FIS,
                fd.STOK,
                s.KOD AS stok_kod,
                s.AD AS stok_ad,
                fd.BARKOD,
                fd.MIKTAR,
                fd.MIKTAR_GIRIS,
                fd.MIKTAR_CIKIS,
                fd.FIYAT,
                fd.DAHIL_FIYAT,
                fd.TUTAR,
                fd.KDV,
                l.AD AS lokasyon
            FROM FIS_DETAY fd
            JOIN FIS f ON f.ID = fd.FIS AND ISNULL(f.AKTIF, 1) = 1
            LEFT JOIN STOK s ON s.ID = fd.STOK
            LEFT JOIN LOKASYON l ON l.ID = ISNULL(fd.LOKASYON, f.LOKASYON)
        ");

        $count = 0;
        $batch = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $batch[] = [
                'tenant_id' => $tenantId,
                'external_id' => (string) $row['ID'],
                'fatura_external_id' => (string) ($row['FIS'] ?? ''),
                'stok_external_id' => $this->nullString($row['STOK'] ?? null),
                'stok_kod' => $this->nullString($row['stok_kod'] ?? null),
                'stok_ad' => $this->nullString($row['stok_ad'] ?? null),
                'barkod' => $this->nullString($row['BARKOD'] ?? null),
                'miktar' => (float) ($row['MIKTAR'] ?? 0),
                'miktar_giris' => (float) ($row['MIKTAR_GIRIS'] ?? 0),
                'miktar_cikis' => (float) ($row['MIKTAR_CIKIS'] ?? 0),
                'fiyat' => (float) ($row['FIYAT'] ?? 0),
                'dahil_fiyat' => (float) ($row['DAHIL_FIYAT'] ?? 0),
                'tutar' => (float) ($row['TUTAR'] ?? 0),
                'kdv' => (float) ($row['KDV'] ?? 0),
                'lokasyon' => $this->nullString($row['lokasyon'] ?? null),
                'last_synced_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $count++;
            $this->flushIfNeeded('erp12_fatura_satirlari', $batch);
        }
        $this->flush('erp12_fatura_satirlari', $batch);

        return $count;
    }

    private function flushIfNeeded(string $table, array &$batch): void
    {
        if (count($batch) >= 1000) {
            $this->flush($table, $batch);
        }
    }

    private function flush(string $table, array &$batch): void
    {
        if ($batch === []) {
            return;
        }

        DB::table($table)->upsert(
            $batch,
            ['tenant_id', 'external_id'],
            array_values(array_diff(array_keys($batch[0]), ['tenant_id', 'external_id', 'created_at']))
        );
        $batch = [];
    }

    private function nullString(mixed $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
