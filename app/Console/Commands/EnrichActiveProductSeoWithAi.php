<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\Tenant;
use App\Services\Catalog\AiProductSeoEnricher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EnrichActiveProductSeoWithAi extends Command
{
    protected $signature = 'kgm:ai-product-seo
        {--tenant=1 : Tenant ID}
        {--limit= : En fazla kaç ürün işlenecek}
        {--chunk=10 : Gemini batch ürün sayısı}
        {--force : Daha önce AI SEO doldurulanları da tekrar yaz}
        {--dry-run : Kaydetmeden örnek çıktı üret}';

    protected $description = 'Aktif ürünlerin açıklama ve zengin SEO alanlarını Gemini AI ile doldurur.';

    public function handle(AiProductSeoEnricher $enricher): int
    {
        $tenantId = (int) $this->option('tenant');
        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            $this->error("Tenant bulunamadı: {$tenantId}");

            return self::FAILURE;
        }

        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;
        $chunk = max(1, min(25, (int) $this->option('chunk')));
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $query = Product::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with(['categories' => function ($query) use ($tenant): void {
                $query->where('categories.tenant_id', $tenant->id)
                    ->select('categories.id', 'categories.name', 'categories.slug');
            }])
            ->select([
                'id',
                'tenant_id',
                'name',
                'slug',
                'brand',
                'description',
                'barcode',
                'sku',
                'price_cents',
                'compare_at_price_cents',
                'stock_quantity',
                'unit_name',
                'image_url',
                'cdn_image_url',
                'seo',
                'metadata',
                'is_active',
                'created_at',
                'updated_at',
            ])
            ->orderBy('id');

        if (! $force) {
            $query->where(function ($query): void {
                $query->whereNull('metadata->ai_seo->enriched_at')
                    ->orWhere('metadata->ai_seo->enriched_at', '');
            });
        }

        $available = (clone $query)->count();
        $total = $limit !== null ? min($limit, $available) : $available;

        if ($total === 0) {
            $this->info('AI SEO için işlenecek aktif ürün yok.');

            return self::SUCCESS;
        }

        $this->info("AI SEO başlıyor: {$total} aktif ürün, batch={$chunk}".($dryRun ? ' (dry-run)' : ''));

        $processed = 0;
        $updated = 0;

        $query->chunkById($chunk, function ($products) use ($enricher, &$processed, &$updated, $dryRun, $total): bool {
            $remaining = $total - $processed;
            if ($remaining <= 0) {
                return false;
            }

            $products = $products->take($remaining);
            $updates = $enricher->enrichBatch($products);

            foreach ($products as $product) {
                $update = $updates[$product->id] ?? null;
                if (! $update) {
                    continue;
                }

                $processed++;

                if ($dryRun) {
                    $this->line(sprintf(
                        '#%d %s => %s',
                        $product->id,
                        $product->name,
                        mb_substr((string) $update['description'], 0, 120, 'UTF-8')
                    ));

                    continue;
                }

                $product->forceFill([
                    'description' => $update['description'],
                    'seo' => $update['seo'],
                    'metadata' => $update['metadata'],
                ])->save();

                if (Schema::hasColumn('products', 'sync_version')) {
                    Product::query()
                        ->whereKey($product->id)
                        ->update(['sync_version' => DB::raw('sync_version + 1')]);
                }

                $updated++;
            }

            $this->info("  → {$processed} / {$total} işlendi");

            return $processed < $total;
        });

        if (! $dryRun && $updated > 0) {
            $this->touchCatalogVersion($tenant->id);
        }

        $this->info("Tamamlandı. İşlenen: {$processed}, kaydedilen: {$updated}.");

        return self::SUCCESS;
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
