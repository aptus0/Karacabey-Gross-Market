<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductImageResolver;
use App\Support\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CatalogImageWorkbenchController extends Controller
{
    private const BATCH_LIMIT_MAX = 3;

    /** @var array<string, bool> */
    private array $productColumnCache = [];

    public function __construct(private ProductImageResolver $resolver) {}

    public function index(Request $request, TenantResolver $tenants): View
    {
        $tenant = $tenants->resolve($request);
        $query = Product::query()
            ->where('tenant_id', $tenant->id)
            ->with('categories:id,name');

        $this->applyFilters($query, $request);

        $products = $query
            ->orderByRaw($this->missingImageOrderExpression())
            ->orderBy('id')
            ->paginate((int) $request->input('per_page', 100))
            ->withQueryString();

        return view('admin.catalog-images.index', [
            'products' => $products,
            'stats' => $this->stats($tenant->id),
            'categories' => Category::query()
                ->where('tenant_id', $tenant->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function batch(Request $request, TenantResolver $tenants): JsonResponse
    {
        @set_time_limit(120);

        $tenant = $tenants->resolve($request);
        $limit = min(max((int) $request->input('limit', self::BATCH_LIMIT_MAX), 1), self::BATCH_LIMIT_MAX);
        $cursor = max((int) $request->input('cursor', 0), 0);

        $query = Product::query()
            ->where('tenant_id', $tenant->id)
            ->where('id', '>', $cursor)
            ->orderBy('id')
            ->limit($limit);

        $this->applyMissingImageConstraint($query);

        if ($request->filled('q')) {
            $term = '%' . $request->string('q')->trim()->toString() . '%';
            $query->where(fn ($query) => $query
                ->where('name', 'like', $term)
                ->orWhere('brand', 'like', $term)
                ->orWhere('barcode', 'like', $term)
                ->orWhere('sku', 'like', $term));
        }

        if ($request->filled('category_id')) {
            $query->whereHas('categories', fn ($query) => $query->whereKey($request->integer('category_id')));
        }

        $columns = [
            'id',
            'tenant_id',
            'name',
            'slug',
            'brand',
            'barcode',
            'sku',
            'image_url',
            'metadata',
        ];

        if ($this->hasProductColumn('cdn_image_url')) {
            $columns[] = 'cdn_image_url';
        }

        $products = $query->get($columns);

        $results = [];
        $updated = 0;
        $failed = 0;
        $lastId = $cursor;

        foreach ($products as $product) {
            $lastId = (int) $product->id;

            try {
                $resolved = $this->resolver->resolveAndStoreBest($product, true);
            } catch (\Throwable $e) {
                report($e);

                $failed++;
                $results[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'ok' => false,
                    'message' => 'Resolver hatası: ' . $e->getMessage(),
                    'candidate_count' => 0,
                ];

                continue;
            }

            if ($resolved['ok'] ?? false) {
                $candidate = $resolved['candidate'] ?? [];
                $this->applyResolvedImage($product, (string) $resolved['image_url'], is_array($candidate) ? $candidate : []);
                $updated++;

                $results[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'ok' => true,
                    'image_url' => $resolved['image_url'],
                    'source' => ($candidate['ai_ranker'] ?? null) === 'gemini' ? 'Gemini AI' : ($candidate['source'] ?? 'unknown'),
                    'score' => $candidate['ai_score'] ?? null,
                ];

                continue;
            }

            $failed++;
            $results[] = [
                'id' => $product->id,
                'name' => $product->name,
                'ok' => false,
                'message' => $resolved['message'] ?? 'Görsel bulunamadı.',
                'candidate_count' => count($resolved['candidates'] ?? []),
            ];
        }

        if ($updated > 0) {
            $this->touchCatalogVersion($tenant->id);
        }

        return response()->json([
            'ok' => true,
            'cursor' => $lastId,
            'processed' => $products->count(),
            'updated' => $updated,
            'failed' => $failed,
            'remaining' => $this->missingImagesQuery($tenant->id, $request, $lastId)->count(),
            'total_missing' => $this->missingImagesQuery($tenant->id, $request)->count(),
            'results' => $results,
        ]);
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('q')) {
            $term = '%' . $request->string('q')->trim()->toString() . '%';
            $query->where(fn ($query) => $query
                ->where('name', 'like', $term)
                ->orWhere('brand', 'like', $term)
                ->orWhere('barcode', 'like', $term)
                ->orWhere('sku', 'like', $term)
                ->orWhere('external_ref', 'like', $term));
        }

        if ($request->filled('category_id')) {
            $query->whereHas('categories', fn ($query) => $query->whereKey($request->integer('category_id')));
        }

        match ($request->input('image', 'all')) {
            'present' => $this->applyPresentImageConstraint($query),
            'missing' => $this->applyMissingImageConstraint($query),
            default => null,
        };
    }

    private function stats(int $tenantId): array
    {
        $base = Product::query()->where('tenant_id', $tenantId);
        $missing = clone $base;
        $present = clone $base;

        $this->applyMissingImageConstraint($missing);
        $this->applyPresentImageConstraint($present);

        return [
            'total' => (clone $base)->count(),
            'missing' => $missing->count(),
            'present' => $present->count(),
        ];
    }

    private function missingImagesQuery(int $tenantId, Request $request, int $afterId = 0)
    {
        $query = Product::query()
            ->where('tenant_id', $tenantId)
            ->where('id', '>', $afterId);

        $this->applyMissingImageConstraint($query);

        if ($request->filled('q')) {
            $term = '%' . $request->string('q')->trim()->toString() . '%';
            $query->where(fn ($query) => $query
                ->where('name', 'like', $term)
                ->orWhere('brand', 'like', $term)
                ->orWhere('barcode', 'like', $term)
                ->orWhere('sku', 'like', $term));
        }

        if ($request->filled('category_id')) {
            $query->whereHas('categories', fn ($query) => $query->whereKey($request->integer('category_id')));
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $candidate
     */
    private function applyResolvedImage(Product $product, string $imageUrl, array $candidate): void
    {
        $metadata = is_array($product->metadata) ? $product->metadata : [];
        $metadata['image_resolver'] = [
            'source' => $candidate['source'] ?? null,
            'ranker' => $candidate['ai_ranker'] ?? null,
            'title' => $candidate['title'] ?? null,
            'candidate_url' => $candidate['url'] ?? null,
            'ai_score' => $candidate['ai_score'] ?? null,
            'resolved_at' => now()->toIso8601String(),
        ];

        $updates = [
            'image_url' => $imageUrl,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ];

        if ($this->hasProductColumn('sync_version')) {
            $updates['sync_version'] = DB::raw('sync_version + 1');
        }

        Product::query()->whereKey($product->id)->update($updates);
    }

    private function missingImageOrderExpression(): string
    {
        if ($this->hasProductColumn('cdn_image_url')) {
            return "CASE WHEN (COALESCE(image_url, '') = '' AND COALESCE(cdn_image_url, '') = '') THEN 0 ELSE 1 END";
        }

        return "CASE WHEN COALESCE(image_url, '') = '' THEN 0 ELSE 1 END";
    }

    private function applyMissingImageConstraint($query): void
    {
        $query->where(function ($query): void {
            $query->where(fn ($query) => $query->whereNull('image_url')->orWhere('image_url', ''));

            if ($this->hasProductColumn('cdn_image_url')) {
                $query->where(fn ($query) => $query->whereNull('cdn_image_url')->orWhere('cdn_image_url', ''));
            }
        });
    }

    private function applyPresentImageConstraint($query): void
    {
        $query->where(function ($query): void {
            $query->where(fn ($query) => $query->whereNotNull('image_url')->where('image_url', '!=', ''));

            if ($this->hasProductColumn('cdn_image_url')) {
                $query->orWhere(fn ($query) => $query->whereNotNull('cdn_image_url')->where('cdn_image_url', '!=', ''));
            }
        });
    }

    private function hasProductColumn(string $column): bool
    {
        if (! array_key_exists($column, $this->productColumnCache)) {
            try {
                $this->productColumnCache[$column] = Schema::hasColumn('products', $column);
            } catch (\Throwable) {
                $this->productColumnCache[$column] = false;
            }
        }

        return $this->productColumnCache[$column];
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
