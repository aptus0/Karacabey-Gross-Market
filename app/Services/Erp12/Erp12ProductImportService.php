<?php

namespace App\Services\Erp12;

use App\Support\ProductBrandInferer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;

final class Erp12ProductImportService
{
    /** @var array<string, int> */
    private array $categoryCache = [];

    private ?ProductBrandInferer $brandInferer = null;

    /**
     * @return array{processed:int,created:int,updated:int,skipped:int,failed:int,categorized:int}
     */
    public function import(PDO $source, int $tenantId, int $priceListId = 1016, int $limit = 50000): array
    {
        $limit = max(1, min($limit, 50000));
        $stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'categorized' => 0];

        $stmt = $source->query($this->sourceSQL($priceListId, $limit));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        DB::transaction(function () use ($rows, $tenantId, &$stats): void {
            $touchedProductIds = [];
            foreach ($rows as $row) {
                $stats['processed']++;

                try {
                    $product = $this->normalizeSourceRow($row);
                    $existing = $this->findExistingProduct($tenantId, $product);
                    $status = $this->upsertProduct($tenantId, $product, $existing);
                    $stats[$status]++;
                    if ($product['local_id'] !== null) {
                        $touchedProductIds[] = (int) $product['local_id'];
                    }

                    if ($product['category_name'] !== null) {
                        $categoryId = $this->ensureCategory($tenantId, $product['category_name'], $product['special_code_id']);
                        if ($categoryId !== null && $this->attachCategory($categoryId, $product['local_id'], true)) {
                            $stats['categorized']++;
                        }
                    }
                } catch (\Throwable) {
                    $stats['failed']++;
                }
            }

            if ($touchedProductIds !== []) {
                DB::table('products')
                    ->where('tenant_id', $tenantId)
                    ->whereNotIn('id', array_values(array_unique($touchedProductIds)))
                    ->whereJsonContains('metadata->source', 'ERP12')
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'sync_version' => DB::raw('sync_version + 1'),
                        'updated_at' => now(),
                    ]);
            }

            $this->pruneOldErpCategories($tenantId);
            $this->touchCatalogVersion($tenantId);
        });

        return $stats;
    }

    private function sourceSQL(int $priceListId, int $limit): string
    {
        return <<<SQL
WITH selected_units AS (
    SELECT
        sb.ID,
        sb.STOK,
        sb.STOK_BIRIM,
        ROW_NUMBER() OVER (
            PARTITION BY sb.STOK
            ORDER BY CASE WHEN sb.VARSAYILAN = 1 THEN 0 ELSE 1 END, sb.ID
        ) AS rn
    FROM STOK_STOK_BIRIM sb
),
barcodes AS (
    SELECT
        sb.STOK,
        MIN(NULLIF(LTRIM(RTRIM(BARKOD)), '')) AS BARKOD
    FROM STOK_BARKOD
    JOIN STOK_STOK_BIRIM sb ON sb.ID = STOK_BARKOD.STOK_STOK_BIRIM
    WHERE AKTIF = 1 OR AKTIF IS NULL
    GROUP BY sb.STOK
),
stock_balances AS (
    SELECT
        fd.STOK,
        SUM(ISNULL(fd.MIKTAR_GIRIS, 0) - ISNULL(fd.MIKTAR_CIKIS, 0)) AS stock_quantity_raw
    FROM FIS_DETAY fd
    JOIN FIS fis ON fis.ID = fd.FIS
    LEFT JOIN LOKASYON lokasyon ON lokasyon.ID = ISNULL(fd.LOKASYON, fis.LOKASYON)
    WHERE ISNULL(fis.AKTIF, 1) = 1
      AND ISNULL(fis.FIS_STOK_HAREKETLERINI_ETKILER, 1) = 1
      AND (lokasyon.ID IS NULL OR (ISNULL(lokasyon.AKTIF, 1) = 1 AND ISNULL(lokasyon.FIZIKI_MI, 1) = 1))
    GROUP BY fd.STOK
)
SELECT TOP {$limit}
    s.ID AS external_ref,
    s.KOD AS sku,
    s.AD AS name,
    s.AKTIF AS active,
    s.STOK_GRUP AS stock_group_id,
    s.STOK_OZEL_KOD_1 AS special_code_id,
    marka.AD AS brand,
    COALESCE(ozel.AD, grup.AD) AS category_name,
    birim.AD AS unit_name,
    barcodes.BARKOD AS barcode,
    f.FIYAT AS price,
    f.KDV_DAHILMI AS vat_included,
    f.STOK_FIYAT_AD AS price_list_id,
    ISNULL(stock_balances.stock_quantity_raw, 0) AS stock_quantity_raw
FROM STOK s
LEFT JOIN selected_units b ON b.STOK = s.ID AND b.rn = 1
LEFT JOIN STOK_STOK_BIRIM_FIYAT f ON f.STOK_STOK_BIRIM = b.ID AND f.STOK_FIYAT_AD = {$priceListId}
LEFT JOIN STOK_MARKA marka ON marka.ID = s.STOK_MARKA
LEFT JOIN STOK_OZEL_KOD_1 ozel ON ozel.ID = s.STOK_OZEL_KOD_1
LEFT JOIN STOK_GRUP grup ON grup.ID = s.STOK_GRUP
LEFT JOIN STOK_BIRIM birim ON birim.ID = b.STOK_BIRIM
LEFT JOIN barcodes ON barcodes.STOK = s.ID
LEFT JOIN stock_balances ON stock_balances.STOK = s.ID
WHERE s.AKTIF = 1
  AND ISNULL(s.STOK_GRUP, 0) <> 75983
ORDER BY s.ID
SQL;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeSourceRow(array $row): array
    {
        $row = array_change_key_case($row, CASE_LOWER);
        $externalRef = (string) (int) $row['external_ref'];
        $name = $this->cleanString($row['name']) ?: 'Ürün '.$externalRef;
        $sku = $this->cleanString($row['sku']);
        $barcode = $this->cleanString($row['barcode']);
        $sourceBrand = $this->cleanString($row['brand']);
        $brand = $sourceBrand !== null
            ? $this->brandInferer()->normalizeBrand($sourceBrand)
            : $this->brandInferer()->infer($name);
        $categoryName = $this->normalizeCategoryName($row['category_name'] ?? null);
        $unitName = $this->cleanString($row['unit_name']) ?: 'adet';
        $priceCents = $this->decimalToCents($row['price'] ?? 0);
        $stockQuantityRaw = $this->decimalString($row['stock_quantity_raw'] ?? 0);
        $stockQuantity = max(0, (int) floor((float) $stockQuantityRaw));

        return [
            'external_ref' => $externalRef,
            'sku' => $sku,
            'name' => $name,
            'brand' => $brand,
            'barcode' => $barcode,
            'category_name' => $categoryName,
            'unit_name' => $unitName,
            'price_cents' => $priceCents,
            'stock_quantity' => $stockQuantity,
            'stock_quantity_raw' => $stockQuantityRaw,
            'active' => $this->truthy($row['active'] ?? true) && $stockQuantity > 0 && $priceCents > 0,
            'vat_included' => $this->truthy($row['vat_included'] ?? true),
            'price_list_id' => (int) ($row['price_list_id'] ?? 0),
            'stock_group_id' => $this->nullableInt($row['stock_group_id'] ?? null),
            'special_code_id' => $this->nullableInt($row['special_code_id'] ?? null),
            'local_id' => null,
        ];
    }

    /** @param array<string, mixed> $product */
    private function findExistingProduct(int $tenantId, array $product): ?object
    {
        $query = DB::table('products')->where('tenant_id', $tenantId);

        $found = (clone $query)->where('external_ref', $product['external_ref'])->first();
        if ($found) {
            return $found;
        }

        return null;
    }

    /** @param array<string, mixed> $product */
    private function upsertProduct(int $tenantId, array &$product, ?object $existing): string
    {
        $now = now();
        $metadata = [
            'source' => 'ERP12',
            'source_table' => 'STOK',
            'erkur_stok_id' => (int) $product['external_ref'],
            'erp12_price_list_id' => $product['price_list_id'],
            'erp12_stock_group_id' => $product['stock_group_id'],
            'erp12_special_code_1_id' => $product['special_code_id'],
            'erp12_vat_included' => $product['vat_included'],
            'erp12_stock_quantity_raw' => $product['stock_quantity_raw'],
            'erp12_category_name' => $product['category_name'],
        ];

        $hash = hash('sha256', json_encode([
            'external_ref' => $product['external_ref'],
            'sku' => $product['sku'],
            'barcode' => $product['barcode'],
            'name' => $product['name'],
            'brand' => $product['brand'],
            'price_cents' => $product['price_cents'],
            'stock_quantity' => $product['stock_quantity'],
            'unit_name' => $product['unit_name'],
            'active' => $product['active'],
        ], JSON_UNESCAPED_UNICODE));

        $data = [
            'external_ref' => $product['external_ref'],
            'sku' => $product['sku'],
            'name' => $product['name'],
            'brand' => $product['brand'],
            'barcode' => $product['barcode'],
            'price_cents' => $product['price_cents'],
            'stock_quantity' => $product['stock_quantity'],
            'unit_name' => $product['unit_name'],
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'search_keywords' => trim(implode(' ', array_filter([
                $product['name'],
                $product['brand'],
                $product['barcode'],
                $product['sku'],
                $product['external_ref'],
                $product['category_name'],
            ]))),
            'feed_hash' => $hash,
            'erp_updated_at' => $now,
            'last_synced_at' => $now,
            'is_active' => $product['active'],
            'updated_at' => $now,
        ];

        if ($existing) {
            $product['local_id'] = (int) $existing->id;
            if ((string) $existing->feed_hash === $hash) {
                DB::table('products')->where('id', $existing->id)->update([
                    'last_synced_at' => $now,
                    'updated_at' => $now,
                ]);

                return 'skipped';
            }

            DB::table('products')->where('id', $existing->id)->update($data + [
                'sync_version' => DB::raw('sync_version + 1'),
            ]);

            return 'updated';
        }

        $data += [
            'tenant_id' => $tenantId,
            'slug' => $this->uniqueProductSlug($product['name'], $product['external_ref']),
            'description' => null,
            'compare_at_price_cents' => null,
            'vat_rate_basis_points' => 1000,
            'image_url' => null,
            'seo' => json_encode([
                'title' => $product['name'],
                'description' => $product['name'].' Karacabey Gross Market\'te uygun fiyatla.',
            ], JSON_UNESCAPED_UNICODE),
            'sync_version' => 1,
            'created_at' => $now,
        ];

        $product['local_id'] = (int) DB::table('products')->insertGetId($data);

        return 'created';
    }

    private function ensureCategory(int $tenantId, string $name, ?int $sourceId): ?int
    {
        $sharedCategory = in_array($name, ['Genel', 'Barkodsuz'], true);
        if ($sharedCategory) {
            $sourceId = null;
        }

        $cacheKey = $tenantId.'|'.$name.'|'.($sourceId ?? '');
        if (isset($this->categoryCache[$cacheKey])) {
            return $this->categoryCache[$cacheKey];
        }

        $slug = $this->uniqueCategorySlug($tenantId, $name, $sourceId);
        $existingQuery = DB::table('categories')->where('tenant_id', $tenantId);
        $existing = $sharedCategory
            ? (clone $existingQuery)->whereRaw('LOWER(name) = ?', [mb_strtolower($name, 'UTF-8')])->orderBy('id')->first(['id'])
            : (clone $existingQuery)->where('slug', $slug)->first(['id']);

        if ($existing) {
            DB::table('categories')->where('id', $existing->id)->update([
                'name' => $name,
                'is_active' => true,
                'updated_at' => now(),
            ]);

            return $this->categoryCache[$cacheKey] = (int) $existing->id;
        }

        $id = (int) DB::table('categories')->insertGetId([
            'tenant_id' => $tenantId,
            'parent_id' => null,
            'name' => $name,
            'slug' => $slug,
            'description' => null,
            'image_url' => null,
            'seo' => json_encode([
                'title' => $name,
                'description' => $name.' ürünleri Karacabey Gross Market\'te.',
            ], JSON_UNESCAPED_UNICODE),
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $this->categoryCache[$cacheKey] = $id;
    }

    private function attachCategory(int $categoryId, ?int $productId, bool $replaceExisting = false): bool
    {
        if ($productId === null) {
            return false;
        }

        if ($replaceExisting) {
            DB::table('category_product')->where('product_id', $productId)->delete();
        }

        if (DB::table('category_product')->where('category_id', $categoryId)->where('product_id', $productId)->exists()) {
            return false;
        }

        DB::table('category_product')->insert([
            'category_id' => $categoryId,
            'product_id' => $productId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    private function pruneOldErpCategories(int $tenantId): void
    {
        DB::table('categories')
            ->where('tenant_id', $tenantId)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('category_product')
                    ->whereColumn('category_product.category_id', 'categories.id');
            })
            ->where(function ($query): void {
                $query->where('name', 'regexp', '^[0-9]{4}[[:space:]]')
                    ->orWhereIn('name', ['KDV0', 'KDV1', 'KDV10', 'KDV20']);
            })
            ->delete();
    }

    private function touchCatalogVersion(int $tenantId): void
    {
        if (DB::table('catalog_versions')->where('tenant_id', $tenantId)->where('scope', 'global')->exists()) {
            DB::table('catalog_versions')->where('tenant_id', $tenantId)->where('scope', 'global')->update([
                'version' => DB::raw('version + 1'),
                'last_changed_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('catalog_versions')->insert([
            'tenant_id' => $tenantId,
            'scope' => 'global',
            'version' => 1,
            'last_changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function uniqueProductSlug(string $name, string $externalRef): string
    {
        $base = Str::slug($name) ?: 'urun';
        $suffix = Str::slug($externalRef) ?: substr(hash('sha1', $externalRef), 0, 10);
        $candidate = substr($base, 0, max(20, 190 - strlen($suffix) - 1)).'-'.$suffix;
        $slug = $candidate;

        for ($i = 2; DB::table('products')->where('slug', $slug)->exists(); $i++) {
            $slug = substr($candidate, 0, 180).'-'.$i;
        }

        return $slug;
    }

    private function uniqueCategorySlug(int $tenantId, string $name, ?int $sourceId): string
    {
        $base = Str::slug($name) ?: 'kategori';
        $suffix = $sourceId !== null ? (string) $sourceId : substr(hash('sha1', $name), 0, 8);

        return substr($base, 0, max(20, 190 - strlen($suffix) - 1)).'-'.$suffix;
    }

    private function normalizeCategoryName(mixed $value): ?string
    {
        $raw = $this->cleanString($value);
        if ($raw === null) {
            return 'Genel';
        }

        $name = preg_replace('/^\d+\s*/', '', $raw) ?: $raw;
        $name = preg_replace('/\s+/', ' ', trim($name)) ?: 'Genel';
        $key = $this->asciiUpper($name);

        $map = [
            'DANA' => 'Dana Et',
            'KUZU' => 'Kuzu Et',
            'TAVUK' => 'Tavuk',
            'YUMURTA' => 'Yumurta',
            'SUCUK PASTIRMA' => 'Sucuk ve Pastırma',
            'SALAM SOSIS' => 'Salam ve Sosis',
            'PEYNIRLER' => 'Peynir',
            'YUFKA' => 'Yufka',
            'ZEYTINLER' => 'Zeytin',
            'TURSU YAPRAK' => 'Turşu ve Yaprak',
            'SUT URUNLERI' => 'Süt Ürünleri',
            'YOGURT URUNLERI' => 'Yoğurt',
            'COLA' => 'Kola',
            'SODALAR' => 'Soda',
            'AROMA VE GAZOZ' => 'Gazoz ve Aromalı İçecek',
            'MEYVE SULARI' => 'Meyve Suyu',
            'SULAR' => 'Su',
            'TOZ ICECEKLER' => 'Toz İçecek',
            'KASIK MAMASI' => 'Kaşık Maması',
            'TOZ MAMA' => 'Toz Mama',
            'DOKME CAY' => 'Dökme Çay',
            'POSET CAY' => 'Poşet Çay',
            'KECAP' => 'Ketçap',
            'MAYONEZ' => 'Mayonez',
            'TOZ SEKER' => 'Toz Şeker',
            'KESME SEKER' => 'Kesme Şeker',
            'BAYRAMLIK SEKER' => 'Bayramlık Şeker',
            'KURU YEMISLER' => 'Kuruyemiş',
            'CIPSLER' => 'Cips',
            'BAHARAT' => 'Baharat',
            'SAKIZLAR' => 'Sakız',
            'SEKERLEME' => 'Şekerleme',
            'PUDING JOLE' => 'Puding ve Jöle',
            'TOZ TATLILAR' => 'Toz Tatlı',
            'KAKAOLAR' => 'Kakao',
            'TATLILAR' => 'Tatlı',
            'KREM SANTILER' => 'Krem Şanti',
            'SOSLAR' => 'Sos',
            'PIRINC' => 'Pirinç',
            'BULGUR' => 'Bulgur',
            'BAKLAGILLER' => 'Baklagil',
            'MERCIMEK' => 'Mercimek',
            'UNLAR' => 'Un',
            'EKMEK' => 'Ekmek',
            'RECEL MARMELAT' => 'Reçel ve Marmelat',
            'BAL' => 'Bal',
            'AYCICEK YAGLARI' => 'Ayçiçek Yağı',
            'ZEYTIN YAGLAR' => 'Zeytinyağı',
            'MARGARINLER' => 'Margarin',
            'TEREYAGLAR' => 'Tereyağı',
            'TUZ CESNI BAR' => 'Tuz ve Çeşni',
            'BULYONLAR' => 'Bulyon',
            'HAMUR K.TOZLARI' => 'Hamur Kabartma Tozu',
            'KADIN PEDLER' => 'Kadın Pedi',
            'HAVLU PECETE' => 'Kağıt Havlu ve Peçete',
            'TUVALET KAGIDI' => 'Tuvalet Kağıdı',
            'ISLAK MENDIL' => 'Islak Mendil',
            'SIVI SABUNLAR' => 'Sıvı Sabun',
            'ROLON' => 'Roll-On',
            'SAC KREMI' => 'Saç Kremi',
            'SAMPUAN' => 'Şampuan',
            'DIS FIRCASI' => 'Diş Fırçası',
            'DIS MACUNU' => 'Diş Macunu',
            'DIGER' => 'Diğer',
            'BARKODSUZ' => 'Barkodsuz',
            'GENEL' => 'Genel',
        ];

        if (isset($map[$key])) {
            return $map[$key];
        }

        if (preg_match('/^KDV\d+$/', $key)) {
            return 'Genel';
        }

        return mb_convert_case(mb_strtolower($name, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }

    private function asciiUpper(string $value): string
    {
        $value = strtr($value, [
            'Ç' => 'C', 'Ğ' => 'G', 'İ' => 'I', 'I' => 'I', 'Ö' => 'O', 'Ş' => 'S', 'Ü' => 'U',
            'ç' => 'C', 'ğ' => 'G', 'ı' => 'I', 'i' => 'I', 'ö' => 'O', 'ş' => 'S', 'ü' => 'U',
        ]);

        return strtoupper($value);
    }

    private function cleanString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function decimalToCents(mixed $value): int
    {
        $value = str_replace(['TL', 'tl', ' '], '', (string) $value);
        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
        }
        $value = str_replace(',', '.', $value);

        return is_numeric($value) ? max(0, (int) round(((float) $value) * 100)) : 0;
    }

    private function decimalString(mixed $value): string
    {
        $value = str_replace(',', '.', trim((string) $value));
        if (! is_numeric($value)) {
            return '0';
        }

        return rtrim(rtrim(number_format((float) $value, 3, '.', ''), '0'), '.') ?: '0';
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return ! in_array(strtolower(trim((string) $value)), ['', '0', 'false', 'hayır', 'hayir', 'no'], true);
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || trim((string) $value) === '' ? null : (int) $value;
    }

    private function brandInferer(): ProductBrandInferer
    {
        return $this->brandInferer ??= new ProductBrandInferer;
    }
}
