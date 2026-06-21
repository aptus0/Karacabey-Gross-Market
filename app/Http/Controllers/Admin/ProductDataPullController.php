<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncDataConnectionProductsJob;
use App\Models\DataConnection;
use App\Services\DataIntegration\DataSourceBrowser;
use App\Services\DataIntegration\NetworkAccessGuard;
use App\Services\DataIntegration\ProductDataImportService;
use App\Support\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductDataPullController extends Controller
{
    public function __construct(
        private readonly DataSourceBrowser $browser,
        private readonly NetworkAccessGuard $network,
        private readonly ProductDataImportService $importer,
    ) {
    }

    public function index(Request $request, TenantResolver $tenants): View
    {
        $tenant = $tenants->resolve($request);
        $connections = DataConnection::query()
            ->where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();

        return view('admin.data-pull.products', [
            'connections' => $connections,
        ]);
    }

    public function inspect(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $validated = $request->validate([
            'connection_id' => ['required', 'integer'],
            'table' => ['nullable', 'string', 'max:120'],
            'schema' => ['nullable', 'string', 'max:120'],
        ]);

        $connection = $this->connectionForTenant((int) $validated['connection_id'], $tenant->id);

        try {
            $this->network->assertRequestAllowed($request, $connection);
            $this->network->assertSourceAllowed($connection);

            $tables = $this->browser->listTables($connection);
            if (empty($validated['table'])) {
                return response()->json([
                    'success' => true,
                    'tables' => $tables,
                    'columns' => [],
                    'rows' => [],
                    'suggested_mapping' => [],
                ]);
            }

            $preview = $this->browser->previewTable(
                $connection,
                $validated['table'],
                5,
                $validated['schema'] ?? null,
            );

            return response()->json([
                'success' => true,
                'tables' => $tables,
                'columns' => $preview['columns'],
                'rows' => $preview['rows'],
                'suggested_mapping' => $this->suggestMapping(array_column($preview['columns'], 'name')),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function import(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $validated = $request->validate([
            'connection_id' => ['required', 'integer'],
            'table' => ['required', 'string', 'max:120'],
            'schema' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'between:1,50000'],
            'price_is_cents' => ['nullable', 'boolean'],
            'deactivate_missing' => ['nullable', 'boolean'],
            'mapping' => ['required', 'array'],
            'mapping.name' => ['required', 'string', 'max:120'],
            'mapping.external_ref' => ['nullable', 'string', 'max:120'],
            'mapping.sku' => ['nullable', 'string', 'max:120'],
            'mapping.barcode' => ['nullable', 'string', 'max:120'],
            'mapping.brand' => ['nullable', 'string', 'max:120'],
            'mapping.description' => ['nullable', 'string', 'max:120'],
            'mapping.price' => ['nullable', 'string', 'max:120'],
            'mapping.compare_at_price' => ['nullable', 'string', 'max:120'],
            'mapping.stock' => ['nullable', 'string', 'max:120'],
            'mapping.image_url' => ['nullable', 'string', 'max:120'],
            'mapping.category' => ['nullable', 'string', 'max:120'],
            'mapping.active' => ['nullable', 'string', 'max:120'],
        ]);

        $connection = $this->connectionForTenant((int) $validated['connection_id'], $tenant->id);

        try {
            $this->network->assertRequestAllowed($request, $connection);
            $this->network->assertSourceAllowed($connection);
            $stats = $this->importer->import($connection, $tenant->id, $validated);

            return response()->json([
                'success' => true,
                'message' => "{$stats['created']} yeni, {$stats['updated']} güncel ürün işlendi.",
                'stats' => $stats,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function settings(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $validated = $request->validate([
            'connection_id' => ['required', 'integer'],
            'enabled' => ['nullable', 'boolean'],
            'table' => ['required', 'string', 'max:120'],
            'schema' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'between:1,50000'],
            'price_is_cents' => ['nullable', 'boolean'],
            'deactivate_missing' => ['nullable', 'boolean'],
            'allowed_client_ips' => ['nullable', 'string', 'max:1000'],
            'allowed_source_ips' => ['nullable', 'string', 'max:1000'],
            'mapping' => ['required', 'array'],
            'mapping.name' => ['required', 'string', 'max:120'],
            'mapping.external_ref' => ['nullable', 'string', 'max:120'],
            'mapping.sku' => ['nullable', 'string', 'max:120'],
            'mapping.barcode' => ['nullable', 'string', 'max:120'],
            'mapping.brand' => ['nullable', 'string', 'max:120'],
            'mapping.description' => ['nullable', 'string', 'max:120'],
            'mapping.price' => ['nullable', 'string', 'max:120'],
            'mapping.compare_at_price' => ['nullable', 'string', 'max:120'],
            'mapping.stock' => ['nullable', 'string', 'max:120'],
            'mapping.image_url' => ['nullable', 'string', 'max:120'],
            'mapping.category' => ['nullable', 'string', 'max:120'],
            'mapping.active' => ['nullable', 'string', 'max:120'],
            'run_now' => ['nullable', 'boolean'],
        ]);

        $connection = $this->connectionForTenant((int) $validated['connection_id'], $tenant->id);

        try {
            $this->network->assertRequestAllowed($request, $connection);

            $extra = $connection->extra ?? [];
            $extra['product_sync'] = [
                'enabled' => (bool) ($validated['enabled'] ?? false),
                'table' => $validated['table'],
                'schema' => $validated['schema'] ?? null,
                'limit' => (int) ($validated['limit'] ?? 5000),
                'price_is_cents' => (bool) ($validated['price_is_cents'] ?? false),
                'deactivate_missing' => (bool) ($validated['deactivate_missing'] ?? false),
                'allowed_client_ips' => $validated['allowed_client_ips'] ?? '',
                'allowed_source_ips' => $validated['allowed_source_ips'] ?? '',
                'mapping' => array_filter($validated['mapping'], fn ($value) => filled($value)),
                'updated_at' => now()->toIso8601String(),
            ];

            $connection->update(['extra' => $extra]);

            if ($validated['run_now'] ?? false) {
                SyncDataConnectionProductsJob::dispatch($connection->id);
            }

            return response()->json([
                'success' => true,
                'message' => ($extra['product_sync']['enabled'] ? 'Otomatik ürün senkronu kaydedildi.' : 'Ürün senkron ayarı kaydedildi.'),
                'settings' => $extra['product_sync'],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function connectionForTenant(int $id, int $tenantId): DataConnection
    {
        return DataConnection::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, string> $mapping
     */
    private function mapProductRow(array $row, array $mapping, int $tenantId, int $connectionId, bool $priceIsCents): ?array
    {
        $name = $this->cell($row, $mapping['name'] ?? null);
        if ($name === null || trim($name) === '') {
            return null;
        }

        $externalRef = $this->cell($row, $mapping['external_ref'] ?? null);
        $sku = $this->cell($row, $mapping['sku'] ?? null);
        $barcode = $this->cell($row, $mapping['barcode'] ?? null);
        $brand = $this->cell($row, $mapping['brand'] ?? null);
        $description = $this->cell($row, $mapping['description'] ?? null);
        $imageUrl = $this->cell($row, $mapping['image_url'] ?? null);
        $category = $this->cell($row, $mapping['category'] ?? null);

        return [
            'tenant_id' => $tenantId,
            'external_ref' => $externalRef,
            'sku' => $sku,
            'barcode' => $barcode,
            'name' => trim($name),
            'description' => $description,
            'brand' => $brand,
            'price_cents' => $this->moneyToCents($this->cell($row, $mapping['price'] ?? null), $priceIsCents),
            'compare_at_price_cents' => $this->nullableMoneyToCents($this->cell($row, $mapping['compare_at_price'] ?? null), $priceIsCents),
            'stock_quantity' => max(0, (int) round((float) str_replace(',', '.', $this->cell($row, $mapping['stock'] ?? null) ?? '0'))),
            'image_url' => $imageUrl,
            'is_active' => $this->activeValue($this->cell($row, $mapping['active'] ?? null)),
            'metadata' => json_encode([
                'source' => 'data_pull',
                'source_connection_id' => $connectionId,
                'source_category' => $category,
                'source_synced_at' => now()->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE),
            'search_keywords' => trim(implode(' ', array_filter([$name, $brand, $barcode, $sku, $externalRef, $category]))),
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function cell(array $row, ?string $column): ?string
    {
        if ($column === null || $column === '' || ! array_key_exists($column, $row)) {
            return null;
        }

        $value = $row[$column];
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_scalar($value)) {
            $clean = trim((string) $value);
            return $clean === '' ? null : $clean;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    private function moneyToCents(?string $value, bool $alreadyCents): int
    {
        return $this->nullableMoneyToCents($value, $alreadyCents) ?? 0;
    }

    private function nullableMoneyToCents(?string $value, bool $alreadyCents): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $value);
        if (! is_numeric($normalized)) {
            return null;
        }

        $number = (float) $normalized;
        return $alreadyCents ? (int) round($number) : (int) round($number * 100);
    }

    private function activeValue(?string $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return in_array(strtolower($value), ['1', 'true', 'evet', 'aktif', 'active', 'yes'], true);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function lookupForPayload(int $tenantId, array $payload): ?array
    {
        if (! empty($payload['external_ref'])) {
            return ['tenant_id' => $tenantId, 'external_ref' => $payload['external_ref']];
        }
        if (! empty($payload['barcode'])) {
            return ['tenant_id' => $tenantId, 'barcode' => $payload['barcode']];
        }
        if (! empty($payload['sku'])) {
            return ['tenant_id' => $tenantId, 'sku' => $payload['sku']];
        }

        return null;
    }

    private function uniqueSlug(string $name, ?string $externalRef): string
    {
        $base = Str::slug($name) ?: 'urun';
        if ($externalRef) {
            $base .= '-'.Str::slug($externalRef);
        }

        $slug = $base;
        for ($i = 2; DB::table('products')->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /**
     * @param array<int, string> $columns
     * @return array<string, string>
     */
    private function suggestMapping(array $columns): array
    {
        $aliases = [
            'external_ref' => ['id', 'stok_id', 'stock_id', 'external_ref', 'kod', 'code'],
            'name' => ['name', 'ad', 'urun_adi', 'ürün_adı', 'product_name', 'stok_ad'],
            'sku' => ['sku', 'kod', 'stock_code', 'stok_kod'],
            'barcode' => ['barcode', 'barkod', 'ean'],
            'brand' => ['brand', 'marka'],
            'description' => ['description', 'aciklama', 'açıklama'],
            'price' => ['price', 'fiyat', 'satis_fiyat', 'satış_fiyat', 'birim_fiyat'],
            'compare_at_price' => ['compare_at_price', 'eski_fiyat', 'liste_fiyat'],
            'stock' => ['stock', 'stok', 'quantity', 'miktar', 'adet'],
            'image_url' => ['image_url', 'resim', 'gorsel', 'görsel'],
            'category' => ['category', 'kategori', 'grup'],
            'active' => ['active', 'aktif', 'is_active'],
        ];

        $normalized = [];
        foreach ($columns as $column) {
            $normalized[strtolower(Str::ascii($column))] = $column;
        }

        $mapping = [];
        foreach ($aliases as $field => $names) {
            foreach ($names as $name) {
                $needle = strtolower(Str::ascii($name));
                foreach ($normalized as $normalizedColumn => $originalColumn) {
                    if ($normalizedColumn === $needle || str_contains($normalizedColumn, $needle)) {
                        $mapping[$field] = $originalColumn;
                        break 2;
                    }
                }
            }
        }

        return $mapping;
    }

    private function touchCatalogVersion(int $tenantId): void
    {
        $exists = DB::table('catalog_versions')
            ->where('tenant_id', $tenantId)
            ->where('scope', 'global')
            ->exists();

        if ($exists) {
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
}
