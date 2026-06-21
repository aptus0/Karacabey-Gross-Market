<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\CdnUrl;
use App\Support\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request, TenantResolver $tenants): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'category' => ['nullable', 'string', 'max:120'],
            'in_stock' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:price_asc,price_desc,newest'],
            'price_min' => ['nullable', 'integer', 'min:0'],
            'price_max' => ['nullable', 'integer', 'min:0'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:96'],
        ]);

        $tenant = $tenants->resolve($request);
        $perPage = (int) ($validated['per_page'] ?? 12);
        $page = (int) ($validated['page'] ?? 1);

        $cacheKey = sprintf(
            'tenant:%d:products:index:v3:%s',
            $tenant->id,
            sha1(json_encode([
                'q' => Str::lower((string) ($validated['q'] ?? '')),
                'category' => $validated['category'] ?? null,
                'in_stock' => (bool) ($validated['in_stock'] ?? false),
                'sort' => $validated['sort'] ?? 'newest',
                'price_min' => $validated['price_min'] ?? null,
                'price_max' => $validated['price_max'] ?? null,
                'page' => $page,
                'per_page' => $perPage,
            ], JSON_THROW_ON_ERROR))
        );

        $payload = Cache::remember($cacheKey, now()->addSeconds((int) config('web_performance.cache.products_ttl_seconds', 300)), function () use ($page, $perPage, $tenant, $validated): array {
            $query = Product::query()
                ->whereBelongsTo($tenant)
                ->where('is_active', true)
                ->where('price_cents', '>', 0)
                ->select(['id', 'tenant_id', 'name', 'slug', 'description', 'brand', 'barcode', 'price_cents', 'compare_at_price_cents', 'stock_quantity', 'unit_name', 'image_url', 'seo', 'updated_at'])
                ->with(['categories' => fn ($query) => $query->select(['categories.id', 'categories.tenant_id', 'categories.name', 'categories.slug'])->where('categories.is_active', true)])
                ->when($validated['in_stock'] ?? false, fn ($q) => $q->where('stock_quantity', '>', 0))
                ->when($validated['price_min'] ?? null, fn ($q) => $q->where('price_cents', '>=', (int) $validated['price_min']))
                ->when($validated['price_max'] ?? null, fn ($q) => $q->where('price_cents', '<=', (int) $validated['price_max']));

            match ($validated['sort'] ?? 'newest') {
                'price_asc' => $query->orderBy('price_cents')->orderByDesc('id'),
                'price_desc' => $query->orderByDesc('price_cents')->orderByDesc('id'),
                default => $query
                    ->orderByRaw('CASE WHEN image_url IS NULL OR image_url = "" THEN 1 ELSE 0 END ASC')
                    ->orderByDesc('id'),
            };

            $query->when(! empty($validated['q']), function ($query) use ($validated): void {
                $term = '%'.addcslashes(Str::squish((string) $validated['q']), '\\%_').'%';
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', $term)
                        ->orWhere('brand', 'like', $term)
                        ->orWhere('barcode', 'like', $term);
                });
            });

            if (! empty($validated['category'])) {
                $category = Category::query()
                    ->whereBelongsTo($tenant)
                    ->where('is_active', true)
                    ->where('slug', $validated['category'])
                    ->first();

                $categoryIds = $category
                    ? $category->children()->pluck('id')->push($category->id)->all()
                    : [];

                $query->when(
                    $category,
                    fn ($query) => $query->whereHas(
                        'categories',
                        fn ($query) => $query->whereIn('categories.id', $categoryIds)
                    ),
                    fn ($query) => $query->whereRaw('1 = 0')
                );
            }

            $products = $query->paginate($perPage, ['*'], 'page', $page);

            return [
                'data' => $products->getCollection()
                    ->map(fn (Product $product): array => $this->serialize($product))
                    ->values()
                    ->all(),
                'total' => $products->total(),
                'per_page' => $products->perPage(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'from' => $products->firstItem(),
                'to' => $products->lastItem(),
            ];
        });

        return response()->json($payload);
    }

    public function show(Request $request, TenantResolver $tenants, string $slug): JsonResponse
    {
        $tenant = $tenants->resolve($request);

        $payload = Cache::remember("tenant:{$tenant->id}:products:show:{$slug}:v4", now()->addSeconds((int) config('web_performance.cache.product_detail_ttl_seconds', 600)), function () use ($slug, $tenant): array {
            $product = Product::query()
                ->whereBelongsTo($tenant)
                ->where('is_active', true)
                ->where('price_cents', '>', 0)
                ->where('slug', $slug)
                ->select(['id', 'tenant_id', 'name', 'slug', 'description', 'brand', 'barcode', 'price_cents', 'compare_at_price_cents', 'stock_quantity', 'unit_name', 'image_url', 'seo', 'updated_at'])
                ->with(['categories' => fn ($query) => $query->select(['categories.id', 'categories.tenant_id', 'categories.name', 'categories.slug'])->where('categories.is_active', true)])
                ->firstOrFail();

            return $this->serialize($product);
        });

        return response()->json(['data' => $payload]);
    }

    public function suggest(Request $request, TenantResolver $tenants): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $tenant = $tenants->resolve($request);
        $term = Str::squish((string) ($validated['q'] ?? ''));

        if (mb_strlen($term) < 2) {
            return response()->json(['data' => []]);
        }

        $likeTerm = '%'.addcslashes($term, '\\%_').'%';

        $products = Cache::remember(
            "tenant:{$tenant->id}:products:suggest:".sha1(Str::lower($term)),
            now()->addSeconds((int) config('web_performance.cache.product_suggest_ttl_seconds', 120)),
            function () use ($likeTerm, $tenant) {
                return Product::query()
                    ->whereBelongsTo($tenant)
                    ->where('is_active', true)
                    ->where('price_cents', '>', 0)
                    ->select(['id', 'tenant_id', 'name', 'slug', 'brand', 'barcode', 'price_cents', 'image_url'])
                    ->with(['categories' => fn ($query) => $query->select(['categories.id', 'categories.tenant_id', 'categories.name'])->where('categories.is_active', true)])
                    ->where(function ($query) use ($likeTerm): void {
                        $query->where('name', 'like', $likeTerm)
                            ->orWhere('brand', 'like', $likeTerm)
                            ->orWhere('barcode', 'like', $likeTerm);
                    })
                    ->orderByRaw('case when name like ? then 0 else 1 end', [$likeTerm])
                    ->latest()
                    ->limit(6)
                    ->get()
                    ->map(fn (Product $product): array => $this->serializeSuggestion($product))
                    ->values()
                    ->all();
            }
        );

        return response()->json([
            'data' => $products,
        ]);
    }

    private function serialize(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'description' => $product->description,
            'brand' => $product->brand,
            'barcode' => $product->barcode,
            'price_cents' => $product->price_cents,
            'price' => $product->formattedPrice(),
            'compare_at_price_cents' => $product->compare_at_price_cents,
            'stock_quantity' => $product->stock_quantity,
            'unit_name' => $product->unit_name ?: 'adet',
            'image_url' => $this->safeImageUrl($product->image_url),
            'seo' => $product->seo,
            'updated_at' => $product->updated_at?->toIso8601String(),
            'categories' => $product->categories
                ->map(fn (Category $category): array => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ])
                ->values(),
        ];
    }

    private function serializeSuggestion(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brand,
            'price' => $product->formattedPrice(),
            'image_url' => $this->safeImageUrl($product->image_url),
            'category' => $product->categories->first()?->name,
        ];
    }

    private function safeImageUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        if (str_starts_with($url, '/')) {
            return CdnUrl::for($url);
        }

        return str_starts_with($url, 'https://') || str_starts_with($url, 'http://') ? CdnUrl::for($url) : null;
    }
}
