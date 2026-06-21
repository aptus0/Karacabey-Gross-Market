<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request, TenantResolver $tenants): View
    {
        $tenant = $tenants->resolve($request);
        $categoryQuery = Category::query()->where('tenant_id', $tenant->id);

        return view('admin.categories.index', [
            'categories' => (clone $categoryQuery)
                ->with('parent')
                ->withCount(['products', 'children'])
                ->orderByRaw('CASE WHEN parent_id IS NULL THEN 0 ELSE 1 END')
                ->orderBy('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(40),
            'parentCategories' => (clone $categoryQuery)
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
            'categoryStats' => [
                'total' => (clone $categoryQuery)->count(),
                'active' => (clone $categoryQuery)->where('is_active', true)->count(),
                'parents' => (clone $categoryQuery)->whereNull('parent_id')->count(),
                'children' => (clone $categoryQuery)->whereNotNull('parent_id')->count(),
            ],
        ]);
    }

    public function store(Request $request, TenantResolver $tenants): RedirectResponse
    {
        $tenant = $tenants->resolve($request);
        $validated = $this->validated($request, $tenant->id);

        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);

        Category::query()->create($validated + ['tenant_id' => $tenant->id]);
        $this->forgetCategoryCache($tenant->id);

        return back()->with('status', 'Kategori olusturuldu.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $this->validated($request, $category->tenant_id, $category);
        $validated['slug'] = $validated['slug'] ?: Str::slug($validated['name']);

        $category->update($validated);
        $this->forgetCategoryCache($category->tenant_id);

        return back()->with('status', 'Kategori guncellendi.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $tenantId = $category->tenant_id;
        $category->delete();
        $this->forgetCategoryCache($tenantId);

        return back()->with('status', 'Kategori silindi.');
    }

    private function validated(Request $request, int $tenantId, ?Category $category = null): array
    {
        $validated = $request->validate([
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')
                    ->where('tenant_id', $tenantId)
                    ->ignore($category?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'image_url' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'parent_id' => $validated['parent_id'] ?? null,
            'name' => trim($validated['name']),
            'slug' => trim((string) ($validated['slug'] ?? '')) ?: null,
            'description' => trim((string) ($validated['description'] ?? '')) ?: null,
            'image_url' => trim((string) ($validated['image_url'] ?? '')) ?: null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function forgetCategoryCache(int $tenantId): void
    {
        Cache::forget("tenant:{$tenantId}:categories:index:v1");
    }
}
