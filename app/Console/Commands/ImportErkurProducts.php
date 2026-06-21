<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportErkurProducts extends Command
{
    protected $signature = 'erkur:import-products
                                {--fresh : Önce mevcut tüm ürün ve kategorileri sil}
                                {--limit= : Maksimum kaç ürün aktarılsın (test için)}
                                {--chunk=500 : Toplu insert boyutu}';

    protected $description = 'Erkur ERP CSV dosyalarından ürünleri ve kategorileri MySQL\'e aktarır';

    private Tenant $tenant;

    public function handle(): int
    {
        $base = base_path('erkur_dump');

        foreach (['STOK', 'STOK_STOK_BIRIM', 'STOK_STOK_BIRIM_FIYAT'] as $file) {
            if (! file_exists("{$base}/{$file}.csv")) {
                $this->error("Dosya bulunamadı: {$base}/{$file}.csv");

                return self::FAILURE;
            }
        }

        $tenant = Tenant::first();
        if (! $tenant) {
            $this->error('Tenant bulunamadı. Önce php artisan db:seed çalıştırın.');

            return self::FAILURE;
        }
        $this->tenant = $tenant;

        if ($this->option('fresh')) {
            $this->purge();
        }

        $markalar = $this->loadMarkalar("{$base}/STOK_MARKA.csv");
        $categoryMap = $this->loadKategoriler("{$base}/STOK_OZEL_KOD_1.csv");
        $prices = $this->loadFiyatlar("{$base}/STOK_STOK_BIRIM_FIYAT.csv");
        $stokBirim = $this->loadBirimler("{$base}/STOK_STOK_BIRIM.csv");
        $barkodlar = $this->loadBarkodlar("{$base}/STOK_BARKOD.csv");

        [$imported, $skipped] = $this->importProducts(
            "{$base}/STOK.csv",
            $markalar, $prices, $stokBirim, $barkodlar
        );

        $linked = $this->linkKategoriler($categoryMap);

        $this->newLine();
        $this->info('✅ Aktarım tamamlandı!');
        $this->table(
            ['Metrik', 'Değer'],
            [
                ['Aktarılan ürün',       number_format($imported)],
                ['Atlanan',              number_format($skipped)],
                ['Kategori',             number_format(count($categoryMap))],
                ['Kategorili ürün',      number_format($linked)],
            ]
        );

        return self::SUCCESS;
    }

    private function purge(): void
    {
        $this->warn('Mevcut ürünler ve kategoriler siliniyor...');
        DB::table('category_product')
            ->whereIn('product_id', DB::table('products')->where('tenant_id', $this->tenant->id)->pluck('id'))
            ->delete();
        Product::query()->where('tenant_id', $this->tenant->id)->delete();
        Category::query()->where('tenant_id', $this->tenant->id)->delete();
        $this->info('Temizlendi.');
    }

    /** @return array<int, string> erkurId => marka adı */
    private function loadMarkalar(string $path): array
    {
        $map = [];
        if (! file_exists($path)) {
            return $map;
        }

        $this->info('[1/5] Markalar yükleniyor...');
        foreach ($this->readCsv($path) as $line) {
            $id = (int) ($line[0] ?? 0);
            $ad = trim($line[1] ?? '');
            if ($id > 0 && $ad !== '') {
                $map[$id] = $ad;
            }
        }
        $this->line('  → '.number_format(count($map)).' marka');

        return $map;
    }

    /** @return array<int, int> erkurOzelId => Category.id */
    private function loadKategoriler(string $path): array
    {
        $map = [];
        if (! file_exists($path)) {
            return $map;
        }

        $this->info('[2/5] Kategoriler yükleniyor (STOK_OZEL_KOD_1)...');
        $sort = 10;
        foreach ($this->readCsv($path) as $line) {
            $erkurId = (int) ($line[0] ?? 0);
            $ad = trim($line[1] ?? '');
            if ($erkurId <= 0 || $ad === '') {
                continue;
            }

            $slug = Str::slug($ad) ?: 'kategori-'.$erkurId;
            $cat = Category::firstOrCreate(
                ['tenant_id' => $this->tenant->id, 'slug' => $slug],
                ['name' => $ad, 'is_active' => true, 'sort_order' => $sort]
            );
            $map[$erkurId] = $cat->id;
            $sort += 10;
        }
        $this->line('  → '.number_format(count($map)).' kategori');

        return $map;
    }

    /** @return array<int, int> birimId => fiyat (kuruş) */
    private function loadFiyatlar(string $path): array
    {
        $this->info('[3/5] Fiyatlar yükleniyor...');
        $map = [];
        foreach ($this->readCsv($path) as $line) {
            // ID, STOK_STOK_BIRIM, DOVIZ_AD, STOK_FIYAT_AD, FIYAT, KDV_DAHILMI
            $birimId = (int) ($line[1] ?? 0);
            $fiyatAd = (int) ($line[3] ?? 0);
            $fiyat = (float) str_replace(',', '.', $line[4] ?? '0');
            if ($birimId <= 0) {
                continue;
            }
            if ($fiyatAd === 1016 || ! isset($map[$birimId])) {
                $map[$birimId] = (int) round($fiyat * 100);
            }
        }
        $this->line('  → '.number_format(count($map)).' fiyat');

        return $map;
    }

    /** @return array<int, int> stokId => birimId (varsayılan) */
    private function loadBirimler(string $path): array
    {
        $this->info('[4/5] Birimler yükleniyor...');
        $map = [];
        foreach ($this->readCsv($path) as $line) {
            // ID, STOK, STOK_BIRIM, CARPAN, VARSAYILAN...
            $birimId = (int) ($line[0] ?? 0);
            $stokId = (int) ($line[1] ?? 0);
            $varsayilan = (int) ($line[4] ?? 0);
            if ($stokId <= 0) {
                continue;
            }
            if ($varsayilan === 1 || ! isset($map[$stokId])) {
                $map[$stokId] = $birimId;
            }
        }
        $this->line('  → '.number_format(count($map)).' birim');

        return $map;
    }

    /** @return array<int, string> birimId => barkod */
    private function loadBarkodlar(string $path): array
    {
        $map = [];
        if (! file_exists($path)) {
            return $map;
        }

        $this->info('[5/5] Barkodlar yükleniyor...');
        foreach ($this->readCsv($path) as $line) {
            // ID, STOK_STOK_BIRIM, BARKOD...
            $birimId = (int) ($line[1] ?? 0);
            $barkod = trim($line[2] ?? '');
            if ($birimId > 0 && $barkod !== '' && ! isset($map[$birimId])) {
                $map[$birimId] = $barkod;
            }
        }
        $this->line('  → '.number_format(count($map)).' barkod');

        return $map;
    }

    /** @return array{0: int, 1: int} [imported, skipped] */
    private function importProducts(
        string $path,
        array $markalar,
        array $prices,
        array $stokBirim,
        array $barkodlar,
    ): array {
        $this->info('Ürünler aktarılıyor...');

        $limit = $this->option('limit') ? (int) $this->option('limit') : PHP_INT_MAX;
        $chunkSize = (int) ($this->option('chunk') ?: 500);
        $imported = 0;
        $skipped = 0;
        $buffer = [];
        $slugsUsed = [];
        $now = now()->toDateTimeString();

        Product::query()->where('tenant_id', $this->tenant->id)->select('slug')
            ->chunk(1000, function ($rows) use (&$slugsUsed): void {
                foreach ($rows as $r) {
                    $slugsUsed[$r->slug] = true;
                }
            });

        $bar = $this->output->createProgressBar($limit === PHP_INT_MAX ? 12000 : $limit);
        $bar->start();

        $fp = fopen($path, 'r');
        $headers = fgetcsv($fp, 0, ',');
        fgetcsv($fp, 0, ','); // tip satırı (---)
        $col = array_flip(array_map('trim', $headers));

        $iId = $col['ID'] ?? 0;
        $iKod = $col['KOD'] ?? 1;
        $iGrup = $col['STOK_GRUP'] ?? 2;
        $iAd = $col['AD'] ?? 3;
        $iAlFiyat = $col['SON_ALIS_FIYAT'] ?? 18;
        $iAktif = $col['AKTIF'] ?? 23;
        $iMarka = $col['STOK_MARKA'] ?? 26;
        $iOzelKod = $col['STOK_OZEL_KOD_1'] ?? 45;

        while (($line = fgetcsv($fp, 0, ',')) !== false) {
            if ($imported >= $limit) {
                break;
            }

            $stokId = (int) ($line[$iId] ?? 0);
            $grupId = (int) ($line[$iGrup] ?? 0);
            $ad = trim($line[$iAd] ?? '');
            $aktif = (int) ($line[$iAktif] ?? 0);

            if ($aktif !== 1 || $ad === '' || $grupId === 75983) {
                $skipped++;

                continue;
            }

            $birimId = $stokBirim[$stokId] ?? null;
            $fiyatKurus = $this->resolveFiyat($birimId, $prices, (float) str_replace(',', '.', $line[$iAlFiyat] ?? '0'));
            $markaId = (int) ($line[$iMarka] ?? 0);
            $ozelId = (int) ($line[$iOzelKod] ?? 0);
            $barkod = $birimId ? ($barkodlar[$birimId] ?? null) : null;
            $marka = $markaId > 0 ? ($markalar[$markaId] ?? null) : null;
            $slug = $this->uniqueSlug($ad, $stokId, $slugsUsed);

            $buffer[] = [
                'tenant_id' => $this->tenant->id,
                'name' => $ad,
                'slug' => $slug,
                'description' => null,
                'brand' => $marka,
                'barcode' => $barkod,
                'price_cents' => $fiyatKurus,
                'compare_at_price_cents' => null,
                'stock_quantity' => 0,
                'image_url' => null,
                'seo' => json_encode(['erkur_kod' => $line[$iKod] ?? '', 'erkur_id' => $stokId]),
                'metadata' => json_encode([
                    'erkur_stok_id' => $stokId,
                    'erkur_grup_id' => $grupId,
                    'erkur_ozel_id' => $ozelId > 0 ? $ozelId : null,
                ]),
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            $imported++;
            $bar->advance();

            if (count($buffer) >= $chunkSize) {
                DB::table('products')->insertOrIgnore($buffer);
                $buffer = [];
            }
        }

        if (! empty($buffer)) {
            DB::table('products')->insertOrIgnore($buffer);
        }

        fclose($fp);
        $bar->finish();

        return [$imported, $skipped];
    }

    private function resolveFiyat(?int $birimId, array $prices, float $sonAlis): int
    {
        if ($birimId !== null && isset($prices[$birimId])) {
            return $prices[$birimId];
        }

        return $sonAlis > 0 ? (int) round($sonAlis * 1.18 * 100) : 0;
    }

    private function uniqueSlug(string $ad, int $stokId, array &$slugsUsed): string
    {
        $base = Str::slug($ad) ?: 'urun-'.$stokId;
        $slug = $base;
        $suffix = 1;
        while (isset($slugsUsed[$slug])) {
            $slug = $base.'-'.$suffix++;
        }
        $slugsUsed[$slug] = true;

        return $slug;
    }

    private function linkKategoriler(array $categoryMap): int
    {
        $this->info('Kategori ilişkileri kuruluyor...');
        $linked = 0;

        Product::query()
            ->where('tenant_id', $this->tenant->id)
            ->whereNotNull('metadata')
            ->select(['id', 'metadata'])
            ->chunk(1000, function ($products) use ($categoryMap, &$linked): void {
                $rows = [];
                foreach ($products as $product) {
                    $meta = is_array($product->metadata) ? $product->metadata : json_decode((string) $product->metadata, true);
                    $ozelId = $meta['erkur_ozel_id'] ?? null;
                    $catId = $ozelId ? ($categoryMap[$ozelId] ?? null) : null;
                    if ($catId !== null) {
                        $rows[] = ['product_id' => $product->id, 'category_id' => $catId];
                        $linked++;
                    }
                }
                if (! empty($rows)) {
                    DB::table('category_product')->insertOrIgnore($rows);
                }
            });

        $this->line("  → {$linked} ürüne kategori atandı");

        return $linked;
    }

    /**
     * CSV'yi satır satır okur, başlık ve tip satırlarını atlar.
     *
     * @return iterable<array<int, string>>
     */
    private function readCsv(string $path): iterable
    {
        $fp = fopen($path, 'r');
        $row = 0;
        while (($line = fgetcsv($fp, 0, ',')) !== false) {
            if (++$row <= 2) {
                continue; // başlık + tip satırı
            }
            yield $line;
        }
        fclose($fp);
    }
}
