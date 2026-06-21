<?php

namespace App\Console\Commands;

use App\Support\ProductBrandInferer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PDOException;
use Throwable;

class SyncErp12Prices extends Command
{
    protected $signature = 'erp12:sync-prices
        {--host=192.168.1.17 : SQL Server host}
        {--port= : SQL Server TCP port. When omitted, --instance is discovered through SQL Browser}
        {--instance=ERP12 : SQL Server instance name for port discovery}
        {--database=ERP12 : SQL Server database}
        {--username=sa : SQL Server username}
        {--password= : SQL Server password. Falls back to ERP12_DB_PASSWORD}
        {--tenant=1 : Local tenant id}
        {--price-list=1016 : STOK_FIYAT_AD id to import}
        {--chunk=500 : Local DB write chunk size}
        {--limit= : Limit source rows for testing}
        {--dry-run : Read ERP rows without writing local catalog}';

    protected $description = 'ERP12 SQL Server ürün perakende fiyatlarını ve stok adetlerini yerel kataloğa aktarır';

    /** @var array<string, int> */
    private array $categoryCache = [];

    private ?ProductBrandInferer $brandInferer = null;

    public function handle(): int
    {
        if (! in_array('dblib', PDO::getAvailableDrivers(), true)) {
            $this->error('pdo_dblib sürücüsü bulunamadı. SQL Server bağlantısı için pdo_dblib gerekli.');

            return self::FAILURE;
        }

        $tenantId = (int) $this->option('tenant');
        if (! DB::table('tenants')->where('id', $tenantId)->exists()) {
            $this->error("tenant_id={$tenantId} tenants tablosunda yok.");

            return self::FAILURE;
        }

        $password = (string) ($this->option('password') ?: env('ERP12_DB_PASSWORD', ''));
        if ($password === '') {
            $this->error('SQL Server parolası gerekli. --password veya ERP12_DB_PASSWORD kullanın.');

            return self::FAILURE;
        }

        $host = (string) $this->option('host');
        $instance = (string) $this->option('instance');
        $port = $this->resolvePort($host, $instance);
        if ($port <= 0) {
            return self::FAILURE;
        }

        $priceListId = (int) $this->option('price-list');
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        try {
            $source = $this->connectSource($host, $port, (string) $this->option('database'), (string) $this->option('username'), $password);
            $total = $this->countSourceRows($source, $priceListId, $limit);
        } catch (Throwable $e) {
            $this->error('ERP12 bağlantısı veya sorgusu başarısız: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'ERP12 %s:%d/%s fiyat listesi %d: %s satır%s',
            $host,
            $port,
            (string) $this->option('database'),
            $priceListId,
            number_format($total),
            $dryRun ? ' (dry-run)' : ''
        ));

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $metrics = ['inserted' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'categorized' => 0];
        $sampleRows = [];
        $buffer = [];

        try {
            $stmt = $source->query($this->sourceSQL($priceListId, $limit));
            while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
                if ($dryRun && count($sampleRows) < 5) {
                    $sampleRows[] = $this->normalizeSourceRow($row);
                }

                $buffer[] = $row;
                if (count($buffer) >= $chunkSize) {
                    $this->processChunk($buffer, $tenantId, $dryRun, $metrics);
                    $bar->advance(count($buffer));
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                $this->processChunk($buffer, $tenantId, $dryRun, $metrics);
                $bar->advance(count($buffer));
            }
        } catch (Throwable $e) {
            $bar->finish();
            $this->newLine(2);
            $this->error('Aktarım yarıda kaldı: '.$e->getMessage());

            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        if (! $dryRun) {
            $this->touchCatalogVersion($tenantId);
        }

        if ($dryRun && $sampleRows !== []) {
            $this->table(
                ['ERP stok', 'SKU', 'Ad', 'Barkod', 'Fiyat kuruş', 'Stok', 'ERP bakiye', 'Kategori'],
                array_map(fn (array $row): array => [
                    $row['external_ref'],
                    $row['sku'] ?? '',
                    Str::limit($row['name'], 48),
                    $row['barcode'] ?? '',
                    $row['price_cents'],
                    $row['stock_quantity'],
                    $row['stock_quantity_raw'],
                    $row['category_name'] ?? '',
                ], $sampleRows)
            );
        }

        $this->table(
            ['Metrik', 'Değer'],
            [
                ['Yeni ürün', number_format($metrics['inserted'])],
                ['Güncellenen', number_format($metrics['updated'])],
                ['Aynı kaldı', number_format($metrics['skipped'])],
                ['Hatalı', number_format($metrics['failed'])],
                ['Kategori bağlantısı', number_format($metrics['categorized'])],
            ]
        );

        return $metrics['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolvePort(string $host, string $instance): int
    {
        $optionPort = $this->option('port');
        if ($optionPort !== null && $optionPort !== '') {
            return (int) $optionPort;
        }

        if ($instance === '') {
            $this->error('--port veya --instance gerekli.');

            return 0;
        }

        $port = $this->discoverInstancePort($host, $instance);
        if ($port === null) {
            $this->error("SQL Browser {$host}:1434 üzerinden {$instance} instance portu bulunamadı. --port ile belirtin.");

            return 0;
        }

        return $port;
    }

    private function connectSource(string $host, int $port, string $database, string $username, string $password): PDO
    {
        putenv('TDSVER='.env('ERP12_TDS_VERSION', '7.4'));

        $dsn = sprintf('dblib:host=%s:%d;dbname=%s;charset=UTF-8', $host, $port, $database);

        try {
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    private function countSourceRows(PDO $source, int $priceListId, ?int $limit): int
    {
        if ($limit !== null) {
            return min($limit, (int) $source->query($this->countSQL($priceListId))->fetchColumn());
        }

        return (int) $source->query($this->countSQL($priceListId))->fetchColumn();
    }

    private function countSQL(int $priceListId): string
    {
        return <<<SQL
WITH selected_units AS (
    SELECT
        sb.ID,
        sb.STOK,
        ROW_NUMBER() OVER (
            PARTITION BY sb.STOK
            ORDER BY CASE WHEN sb.VARSAYILAN = 1 THEN 0 ELSE 1 END, sb.ID
        ) AS rn
    FROM STOK_STOK_BIRIM sb
)
SELECT COUNT(*)
FROM STOK s
JOIN selected_units b ON b.STOK = s.ID AND b.rn = 1
JOIN STOK_STOK_BIRIM_FIYAT f ON f.STOK_STOK_BIRIM = b.ID AND f.STOK_FIYAT_AD = {$priceListId}
WHERE s.AKTIF = 1
  AND ISNULL(s.STOK_GRUP, 0) <> 75983
  AND f.FIYAT IS NOT NULL
SQL;
    }

    private function sourceSQL(int $priceListId, ?int $limit): string
    {
        $top = $limit !== null ? 'TOP ('.$limit.')' : '';

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
        STOK_STOK_BIRIM,
        MIN(NULLIF(LTRIM(RTRIM(BARKOD)), '')) AS BARKOD
    FROM STOK_BARKOD
    WHERE AKTIF = 1 OR AKTIF IS NULL
    GROUP BY STOK_STOK_BIRIM
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
SELECT {$top}
    s.ID AS external_ref,
    s.KOD AS sku,
    s.AD AS name,
    s.AKTIF AS active,
    s.STOK_GRUP AS stock_group_id,
    s.STOK_OZEL_KOD_1 AS special_code_id,
    marka.AD AS brand,
    ozel.AD AS category_name,
    birim.AD AS unit_name,
    barcodes.BARKOD AS barcode,
    f.FIYAT AS price,
    f.KDV_DAHILMI AS vat_included,
    f.STOK_FIYAT_AD AS price_list_id,
    ISNULL(stock_balances.stock_quantity_raw, 0) AS stock_quantity_raw
FROM STOK s
JOIN selected_units b ON b.STOK = s.ID AND b.rn = 1
JOIN STOK_STOK_BIRIM_FIYAT f ON f.STOK_STOK_BIRIM = b.ID AND f.STOK_FIYAT_AD = {$priceListId}
LEFT JOIN STOK_MARKA marka ON marka.ID = s.STOK_MARKA
LEFT JOIN STOK_OZEL_KOD_1 ozel ON ozel.ID = s.STOK_OZEL_KOD_1
LEFT JOIN STOK_BIRIM birim ON birim.ID = b.STOK_BIRIM
LEFT JOIN barcodes ON barcodes.STOK_STOK_BIRIM = b.ID
LEFT JOIN stock_balances ON stock_balances.STOK = s.ID
WHERE s.AKTIF = 1
  AND ISNULL(s.STOK_GRUP, 0) <> 75983
  AND f.FIYAT IS NOT NULL
ORDER BY s.ID
SQL;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array{inserted:int, updated:int, skipped:int, failed:int, categorized:int} $metrics
     */
    private function processChunk(array $rows, int $tenantId, bool $dryRun, array &$metrics): void
    {
        if ($dryRun) {
            $metrics['skipped'] += count($rows);

            return;
        }

        DB::transaction(function () use ($rows, $tenantId, &$metrics): void {
            foreach ($rows as $row) {
                try {
                    $product = $this->normalizeSourceRow($row);
                    $existing = $this->findExistingProduct($tenantId, $product);
                    $status = $this->upsertProduct($tenantId, $product, $existing);
                    $metrics[$status]++;

                    if ($product['category_name'] !== null) {
                        $categoryId = $this->ensureCategory($tenantId, $product['category_name'], $product['special_code_id']);
                        if ($categoryId !== null && $this->attachCategory($categoryId, $product['local_id'])) {
                            $metrics['categorized']++;
                        }
                    }
                } catch (Throwable) {
                    $metrics['failed']++;
                }
            }
        });
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function normalizeSourceRow(array $row): array
    {
        $externalRef = (string) (int) $row['external_ref'];
        $name = $this->cleanString($row['name']) ?: 'Ürün '.$externalRef;
        $sku = $this->cleanString($row['sku']);
        $barcode = $this->cleanString($row['barcode']);
        $sourceBrand = $this->cleanString($row['brand']);
        $brand = $sourceBrand !== null
            ? $this->brandInferer()->normalizeBrand($sourceBrand)
            : $this->brandInferer()->infer($name);
        $categoryName = $this->cleanString($row['category_name']);
        $unitName = $this->cleanString($row['unit_name']) ?: 'adet';
        $priceCents = $this->decimalToCents($row['price'] ?? 0);
        $stockQuantityRaw = $this->decimalString($row['stock_quantity_raw'] ?? 0);
        $stockQuantity = $this->stockToQuantity($stockQuantityRaw);

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

    /**
     * @param array<string, mixed> $product
     */
    private function findExistingProduct(int $tenantId, array $product): ?object
    {
        $query = DB::table('products')->where('tenant_id', $tenantId);

        $found = (clone $query)->where('external_ref', $product['external_ref'])->first();
        if ($found) {
            return $found;
        }

        $found = (clone $query)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.erkur_stok_id')) = ?", [$product['external_ref']])
            ->first();
        if ($found) {
            return $found;
        }

        if ($product['barcode'] !== null) {
            return (clone $query)->where('barcode', $product['barcode'])->first();
        }

        return null;
    }

    /**
     * @param array<string, mixed> $product
     */
    private function upsertProduct(int $tenantId, array &$product, ?object $existing): string
    {
        $now = now();
        $existingMetadata = $existing?->metadata ? json_decode((string) $existing->metadata, true) : [];
        if (! is_array($existingMetadata)) {
            $existingMetadata = [];
        }

        $metadata = array_merge($existingMetadata, [
            'source' => 'ERP12',
            'source_table' => 'STOK',
            'erkur_stok_id' => (int) $product['external_ref'],
            'erp12_price_list_id' => $product['price_list_id'],
            'erp12_stock_group_id' => $product['stock_group_id'],
            'erp12_special_code_1_id' => $product['special_code_id'],
            'erp12_vat_included' => $product['vat_included'],
            'erp12_stock_quantity_raw' => $product['stock_quantity_raw'],
        ]);

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
                'description' => $product['name'].' Karacabey Gross Market’te uygun fiyatla.',
            ], JSON_UNESCAPED_UNICODE),
            'sync_version' => 1,
            'created_at' => $now,
        ];

        $product['local_id'] = (int) DB::table('products')->insertGetId($data);

        return 'inserted';
    }

    private function ensureCategory(int $tenantId, string $name, ?int $sourceId): ?int
    {
        $cacheKey = $tenantId.'|'.$name.'|'.($sourceId ?? '');
        if (isset($this->categoryCache[$cacheKey])) {
            return $this->categoryCache[$cacheKey];
        }

        $slug = $this->uniqueCategorySlug($tenantId, $name, $sourceId);
        $existing = DB::table('categories')
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->first(['id']);

        if ($existing) {
            return $this->categoryCache[$cacheKey] = (int) $existing->id;
        }

        $now = now();
        $id = (int) DB::table('categories')->insertGetId([
            'tenant_id' => $tenantId,
            'parent_id' => null,
            'name' => $name,
            'slug' => $slug,
            'description' => null,
            'image_url' => null,
            'seo' => json_encode([
                'title' => $name,
                'description' => $name.' ürünleri Karacabey Gross Market’te.',
            ], JSON_UNESCAPED_UNICODE),
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->categoryCache[$cacheKey] = $id;
    }

    private function attachCategory(int $categoryId, ?int $productId): bool
    {
        if ($productId === null) {
            return false;
        }

        $exists = DB::table('category_product')
            ->where('category_id', $categoryId)
            ->where('product_id', $productId)
            ->exists();

        if ($exists) {
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

    private function touchCatalogVersion(int $tenantId): void
    {
        $existing = DB::table('catalog_versions')
            ->where('tenant_id', $tenantId)
            ->where('scope', 'global')
            ->exists();

        if ($existing) {
            DB::table('catalog_versions')
                ->where('tenant_id', $tenantId)
                ->where('scope', 'global')
                ->update([
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
        $room = max(20, 190 - strlen($suffix) - 1);
        $candidate = substr($base, 0, $room).'-'.$suffix;
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
        $room = max(20, 190 - strlen($suffix) - 1);

        return substr($base, 0, $room).'-'.$suffix;
    }

    private function discoverInstancePort(string $host, string $instance): ?int
    {
        $socket = @stream_socket_client("udp://{$host}:1434", $errno, $errstr, 3);
        if (! is_resource($socket)) {
            return null;
        }

        stream_set_timeout($socket, 3);
        fwrite($socket, "\x03");
        $response = fread($socket, 4096);
        fclose($socket);

        if ($response === false || $response === '') {
            return null;
        }

        $parts = array_values(array_filter(
            explode(';', trim(substr($response, 3), ";\0\r\n\t ")),
            fn (string $part): bool => $part !== ''
        ));
        $current = [];
        for ($i = 0; $i + 1 < count($parts); $i += 2) {
            $key = strtolower($parts[$i]);
            $value = $parts[$i + 1];
            if ($key === 'servername' && $current !== []) {
                $port = $this->portFromInstancePayload($current, $instance);
                if ($port !== null) {
                    return $port;
                }
                $current = [];
            }
            $current[$key] = $value;
        }

        return $this->portFromInstancePayload($current, $instance);
    }

    /** @param array<string, string> $payload */
    private function portFromInstancePayload(array $payload, string $instance): ?int
    {
        if (strcasecmp($payload['instancename'] ?? '', $instance) !== 0) {
            return null;
        }

        $port = (int) ($payload['tcp'] ?? 0);

        return $port > 0 ? $port : null;
    }

    private function cleanString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function decimalToCents(mixed $value): int
    {
        $value = str_replace(['₺', 'TL', 'tl', ' '], '', (string) $value);
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

    private function stockToQuantity(string $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        return max(0, (int) floor((float) $value));
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
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (int) $value;
    }

    private function brandInferer(): ProductBrandInferer
    {
        return $this->brandInferer ??= new ProductBrandInferer;
    }
}
