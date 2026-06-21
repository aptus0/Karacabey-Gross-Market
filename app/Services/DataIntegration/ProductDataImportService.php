<?php

namespace App\Services\DataIntegration;

use App\Models\DataConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProductDataImportService
{
    public function __construct(
        private readonly DataSourceBrowser $browser,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @return array{processed:int,created:int,updated:int,skipped:int,deactivated:int}
     */
    public function import(DataConnection $connection, int $tenantId, array $options): array
    {
        $mapping = array_filter((array) ($options['mapping'] ?? []), fn ($value) => filled($value));
        $limit = (int) ($options['limit'] ?? 5000);
        $limit = max(1, min($limit, 50000));
        $seenKeys = [];
        $stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'deactivated' => 0];

        DB::transaction(function () use ($connection, $tenantId, $options, $mapping, $limit, &$seenKeys, &$stats): void {
            foreach ($this->browser->streamTable($connection, (string) $options['table'], $options['schema'] ?? null) as $row) {
                if ($stats['processed'] >= $limit) {
                    break;
                }

                $payload = $this->mapProductRow($row, $mapping, $tenantId, $connection->id, (bool) ($options['price_is_cents'] ?? false));
                if ($payload === null) {
                    $stats['skipped']++;
                    continue;
                }

                $stats['processed']++;
                $lookup = $this->lookupForPayload($tenantId, $payload);
                if ($lookup === null) {
                    $stats['skipped']++;
                    continue;
                }

                $existingId = DB::table('products')->where($lookup)->value('id');
                if ($existingId) {
                    DB::table('products')->where('id', $existingId)->update($payload + [
                        'sync_version' => DB::raw('sync_version + 1'),
                        'last_synced_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $stats['updated']++;
                } else {
                    DB::table('products')->insert($payload + [
                        'slug' => $this->uniqueSlug($payload['name'], $payload['external_ref'] ?? null),
                        'sync_version' => 1,
                        'last_synced_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $stats['created']++;
                }

                if (! empty($payload['external_ref'])) {
                    $seenKeys[] = (string) $payload['external_ref'];
                }
            }

            if (($options['deactivate_missing'] ?? false) && $seenKeys !== []) {
                $stats['deactivated'] = DB::table('products')
                    ->where('tenant_id', $tenantId)
                    ->whereNotNull('external_ref')
                    ->whereNotIn('external_ref', array_unique($seenKeys))
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'sync_version' => DB::raw('sync_version + 1'),
                        'updated_at' => now(),
                    ]);
            }

            $this->touchCatalogVersion($tenantId);
        });

        return $stats;
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

        $priceCents = $this->moneyToCents($this->cell($row, $mapping['price'] ?? null), $priceIsCents);

        return [
            'tenant_id' => $tenantId,
            'external_ref' => $externalRef,
            'sku' => $sku,
            'barcode' => $barcode,
            'name' => trim($name),
            'description' => $description,
            'brand' => $brand,
            'price_cents' => $priceCents,
            'compare_at_price_cents' => $this->nullableMoneyToCents($this->cell($row, $mapping['compare_at_price'] ?? null), $priceIsCents),
            'stock_quantity' => max(0, (int) round((float) str_replace(',', '.', $this->cell($row, $mapping['stock'] ?? null) ?? '0'))),
            'image_url' => $imageUrl,
            'is_active' => $priceCents > 0 && $this->activeValue($this->cell($row, $mapping['active'] ?? null)),
            'metadata' => json_encode([
                'source' => 'data_pull',
                'source_connection_id' => $connectionId,
                'source_category' => $category,
                'source_synced_at' => now()->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE),
            'search_keywords' => trim(implode(' ', array_filter([$name, $brand, $barcode, $sku, $externalRef, $category]))),
        ];
    }

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

    private function touchCatalogVersion(int $tenantId): void
    {
        if (DB::table('catalog_versions')->where('tenant_id', $tenantId)->exists()) {
            DB::table('catalog_versions')->where('tenant_id', $tenantId)->update([
                'products_version' => DB::raw('products_version + 1'),
                'updated_at' => now(),
            ]);
            return;
        }

        DB::table('catalog_versions')->insert([
            'tenant_id' => $tenantId,
            'products_version' => 1,
            'categories_version' => 1,
            'content_version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
