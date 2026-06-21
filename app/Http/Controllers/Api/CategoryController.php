<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $categories = Cache::remember("tenant:{$tenant->id}:categories:index:v1", now()->addSeconds((int) config('web_performance.cache.categories_ttl_seconds', 900)), function () use ($tenant): array {
            return Category::query()
                ->whereBelongsTo($tenant)
                ->where('is_active', true)
                ->whereNull('parent_id')
                ->select(['id', 'tenant_id', 'parent_id', 'name', 'slug', 'description', 'image_url', 'seo', 'sort_order', 'is_active'])
                ->with([
                    'children' => fn ($query) => $query
                        ->select(['id', 'tenant_id', 'parent_id', 'name', 'slug', 'description', 'image_url', 'seo', 'sort_order', 'is_active'])
                        ->where('is_active', true)
                        ->withCount('products'),
                ])
                ->withCount('products')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => $this->serialize($category, includeChildren: true))
                ->values()
                ->all();
        });

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function show(Request $request, TenantResolver $tenants, string $slug): JsonResponse
    {
        $tenant = $tenants->resolve($request);

        $payload = Cache::remember("tenant:{$tenant->id}:categories:show:{$slug}:v1", now()->addSeconds((int) config('web_performance.cache.categories_ttl_seconds', 900)), function () use ($slug, $tenant): array {
            $category = Category::query()
                ->whereBelongsTo($tenant)
                ->where('is_active', true)
                ->where('slug', $slug)
                ->select(['id', 'tenant_id', 'parent_id', 'name', 'slug', 'description', 'image_url', 'seo', 'sort_order', 'is_active'])
                ->with([
                    'children' => fn ($query) => $query
                        ->select(['id', 'tenant_id', 'parent_id', 'name', 'slug', 'description', 'image_url', 'seo', 'sort_order', 'is_active'])
                        ->where('is_active', true),
                    'products' => fn ($query) => $query
                        ->whereBelongsTo($tenant)
                        ->where('is_active', true)
                        ->select(['products.id', 'products.tenant_id', 'products.name', 'products.slug', 'products.price_cents', 'products.image_url'])
                        ->latest()
                        ->limit(12),
                ])
                ->firstOrFail();

            return $this->serialize($category, includeChildren: true, includeProducts: true);
        });

        return response()->json(['data' => $payload]);
    }

    private function serialize(Category $category, bool $includeChildren = false, bool $includeProducts = false): array
    {
        $data = [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'image_url' => $category->image_url,
            'seo' => $category->seo,
            'product_count' => $category->products_count ?? $category->products()->count(),
        ];

        if ($includeChildren) {
            $data['children'] = $category->children
                ->map(fn (Category $child): array => $this->serialize($child))
                ->values()
                ->all();
        }

        if ($includeProducts) {
            $data['products'] = $category->products
                ->map(fn (Product $product): array => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'slug' => $product->slug,
                    'price_cents' => $product->price_cents,
                    'price' => $product->formattedPrice(),
                    'image_url' => $product->image_url,
                ])
                ->values()
                ->all();
        }

        return $data;
    }
}
