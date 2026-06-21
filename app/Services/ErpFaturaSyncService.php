<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Erkur FIS_DETAY.csv üzerinden ürün/fiyat/stok senkronizasyonu yapar.
 * Bir fatura (FIS) satırları işlenerek:
 *   - Yeni ürünler products tablosuna eklenir
 *   - Mevcut ürünlerin fiyatı güncellenir (eğer değiştiyse)
 *   - Stok miktarları fatura türüne göre ayarlanır
 */
class ErpFaturaSyncService
{
    private string $dumpPath;

    public function __construct()
    {
        $this->dumpPath = base_path('erkur_dump');
    }

    /**
     * Belirli bir fatura ID'si için senkronizasyon yapar.
     * CSV tabanlı (canlı SQL yokken fallback).
     *
     * @return array{new: int, updated: int, stock_updated: int, errors: string[]}
     */
    public function syncFromCsv(int $fisId, int $tenantId): array
    {
        $detayPath = $this->dumpPath . '/FIS_DETAY.csv';
        $stokPath  = $this->dumpPath . '/STOK.csv';

        if (! file_exists($detayPath)) {
            return ['new' => 0, 'updated' => 0, 'stock_updated' => 0, 'errors' => ['FIS_DETAY.csv bulunamadı']];
        }

        // ── STOK haritası: stok_id → ad ─────────────────────────────
        $stoklar = [];
        if (file_exists($stokPath)) {
            $h   = fopen($stokPath, 'r');
            fgetcsv($h); fgetcsv($h);
            while (($row = fgetcsv($h)) !== false) {
                $stoklar[(int) $row[0]] = trim($row[3] ?? ''); // ID => AD
            }
            fclose($h);
        }

        // ── FIS_DETAY satırlarını çek ────────────────────────────────
        // Kolonlar: 0=ID, 1=FIS, 2=LOKASYON, 3=STOK, 6=BARKOD, 16=FIYAT, 17=DAHIL_FIYAT, 12=MIKTAR_GIRIS, 13=MIKTAR_CIKIS
        $satirlar = [];
        $h = fopen($detayPath, 'r');
        fgetcsv($h); fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            if ((int) ($row[1] ?? 0) === $fisId) {
                $satirlar[] = $row;
            }
        }
        fclose($h);

        if (empty($satirlar)) {
            return ['new' => 0, 'updated' => 0, 'stock_updated' => 0, 'errors' => ["Fatura {$fisId} için detay satırı bulunamadı"]];
        }

        $newCount   = 0;
        $updCount   = 0;
        $stokCount  = 0;
        $errors     = [];

        DB::beginTransaction();
        try {
            foreach ($satirlar as $row) {
                $erkurStokId = (int) ($row[3]  ?? 0);
                $barkod      = trim($row[6]  ?? '');
                $fiyat       = (float) ($row[17] ?? 0); // KDV dahil fiyat
                $fiyatKurus  = (int) round($fiyat * 100);
                $mikGiris    = (float) ($row[12] ?? 0);
                $mikCikis    = (float) ($row[13] ?? 0);
                $netMiktar   = (int) round($mikGiris - $mikCikis);

                if ($erkurStokId === 0) continue;

                $ad = $stoklar[$erkurStokId] ?? null;
                if (! $ad) continue;

                // Meta ile mevcut ürünü bul
                $product = Product::query()
                    ->where('tenant_id', $tenantId)
                    ->whereRaw("JSON_EXTRACT(metadata, '$.erkur_stok_id') = ?", [$erkurStokId])
                    ->first();

                // Barkod ile de dene
                if (! $product && $barkod) {
                    $product = Product::query()
                        ->where('tenant_id', $tenantId)
                        ->where('barcode', $barkod)
                        ->first();
                }

                if ($product) {
                    $changes = [];

                    // Fiyat değiştiyse güncelle
                    if ($fiyatKurus > 0 && $product->price_cents !== $fiyatKurus) {
                        $changes['price_cents'] = $fiyatKurus;
                    }

                    // Stok güncelle
                    if ($netMiktar !== 0) {
                        $changes['stock_quantity'] = max(0, $product->stock_quantity + $netMiktar);
                        $stokCount++;
                    }

                    if (! empty($changes)) {
                        $product->update($changes);
                        $updCount++;
                    }
                } else {
                    // Yeni ürün ekle
                    $slug     = Str::slug($ad) ?: 'urun-' . $erkurStokId;
                    $baseSlug = $slug;
                    $i        = 1;
                    while (Product::where('tenant_id', $tenantId)->where('slug', $slug)->exists()) {
                        $slug = $baseSlug . '-' . $i++;
                    }

                    Product::create([
                        'tenant_id'      => $tenantId,
                        'name'           => $ad,
                        'slug'           => $slug,
                        'barcode'        => $barkod ?: null,
                        'price_cents'    => $fiyatKurus,
                        'stock_quantity' => max(0, $netMiktar),
                        'is_active'      => true,
                        'seo'            => json_encode(['erkur_id' => $erkurStokId]),
                        'metadata'       => json_encode(['erkur_stok_id' => $erkurStokId]),
                    ]);
                    $newCount++;
                    $stokCount++;
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = $e->getMessage();
        }

        return [
            'new'           => $newCount,
            'updated'       => $updCount,
            'stock_updated' => $stokCount,
            'errors'        => $errors,
        ];
    }

    /**
     * FIS_DETAY'dan belirli bir fatura satırlarını döner.
     */
    public function getFaturaDetay(int $fisId): array
    {
        $detayPath = $this->dumpPath . '/FIS_DETAY.csv';
        $stokPath  = $this->dumpPath . '/STOK.csv';

        $stoklar = [];
        if (file_exists($stokPath)) {
            $h = fopen($stokPath, 'r');
            fgetcsv($h); fgetcsv($h);
            while (($row = fgetcsv($h)) !== false) {
                $stoklar[(int) $row[0]] = ['ad' => trim($row[3] ?? ''), 'kod' => trim($row[1] ?? '')];
            }
            fclose($h);
        }

        if (! file_exists($detayPath)) return [];

        $satirlar = [];
        $h = fopen($detayPath, 'r');
        fgetcsv($h); fgetcsv($h);
        while (($row = fgetcsv($h)) !== false) {
            if ((int) ($row[1] ?? 0) !== $fisId) continue;

            $erkurId = (int) ($row[3] ?? 0);
            $stok    = $stoklar[$erkurId] ?? ['ad' => '-', 'kod' => '-'];

            $satirlar[] = [
                'id'          => $row[0] ?? '',
                'stok_id'     => $erkurId,
                'stok_ad'     => $stok['ad'],
                'stok_kod'    => $stok['kod'],
                'barkod'      => trim($row[6] ?? ''),
                'miktar'      => (float) ($row[11] ?? 0),
                'miktar_giris'=> (float) ($row[12] ?? 0),
                'miktar_cikis'=> (float) ($row[13] ?? 0),
                'fiyat'       => (float) ($row[16] ?? 0),
                'dahil_fiyat' => (float) ($row[17] ?? 0),
                'tutar'       => (float) ($row[18] ?? 0),
                'kdv'         => (float) ($row[21] ?? 0),
            ];
        }
        fclose($h);

        return $satirlar;
    }
}
