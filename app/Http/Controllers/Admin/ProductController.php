<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\ProductBrandInferer;
use App\Support\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request, TenantResolver $tenants): View
    {
        $tenant = $tenants->resolve($request);
        $baseQuery = Product::query()->where('tenant_id', $tenant->id);
        $archiveFilter = (string) $request->input('archive', 'visible');
        $stockFilter = $request->has('stock')
            ? (string) $request->input('stock')
            : ($archiveFilter === 'archived' ? '' : 'in_stock');

        $products = (clone $baseQuery)
            ->with('categories')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%' . $request->string('q')->trim()->toString() . '%';
                $query->where(fn ($query) => $query
                    ->where('name', 'like', $term)
                    ->orWhere('brand', 'like', $term)
                    ->orWhere('barcode', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('external_ref', 'like', $term));
            })
            ->when($request->input('status') === 'active', fn ($query) => $query->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($stockFilter === 'in_stock', fn ($query) => $query->where('stock_quantity', '>', 0))
            ->when($stockFilter === 'out_of_stock', fn ($query) => $query->where('stock_quantity', '<=', 0))
            ->when($request->input('price') === 'zero', fn ($query) => $query->where('price_cents', '<=', 0))
            ->when($request->input('price') === 'priced', fn ($query) => $query->where('price_cents', '>', 0))
            ->tap(fn ($query) => $this->applyArchiveFilter($query, $archiveFilter))
            ->when($request->input('brand') === '__missing', fn ($query) => $query->where(fn ($query) => $query->whereNull('brand')->orWhere('brand', '')))
            ->when($request->filled('brand') && $request->input('brand') !== '__missing', fn ($query) => $query->where('brand', $request->input('brand')))
            ->when($request->filled('category_id'), fn ($query) => $query->whereHas('categories', fn ($query) => $query->whereKey($request->integer('category_id'))))
            ->when($request->input('image') === 'missing', fn ($query) => $query->where(fn ($q) => $q->whereNull('image_url')->orWhere('image_url', '')))
            ->when($request->input('image') === 'present', fn ($query) => $query->whereNotNull('image_url')->where('image_url', '!=', ''))
            ->tap(fn ($query) => $this->applySort($query, (string) $request->input('sort', 'latest')))
            ->paginate((int) $request->input('per_page', 25))
            ->withQueryString();

        return view('admin.products.index', [
            'products' => $products,
            'stats' => $this->catalogStats($tenant->id),
            'brands' => $this->brandOptions($tenant->id),
            'categories' => Category::query()->where('tenant_id', $tenant->id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product,
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, TenantResolver $tenants): RedirectResponse
    {
        $tenant = $tenants->resolve($request);
        $validated = $this->validated($request);

        $validated['slug'] = $this->generateSlug($validated['slug'], $validated['name']);
        $validated['image_url'] = $this->handleImageUpload($request, $validated['image_url'] ?? null);
        $validated = $this->withSeoPayload($validated);

        $product = Product::query()->create($validated + [
            'tenant_id' => $tenant->id,
        ]);

        $product->categories()->sync($request->array('category_ids'));

        return redirect()->route('admin.products.index')
            ->with('status', 'Ürün oluşturuldu.');
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product->load('categories'),
            'categories' => Category::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $this->validated($request);

        $validated['slug'] = $this->generateSlug($validated['slug'], $validated['name'], $product);
        $validated['image_url'] = $this->handleImageUpload($request, $validated['image_url'] ?? $product->image_url, $product);
        $validated = $this->withSeoPayload($validated);

        $product->update($validated);
        $product->categories()->sync($request->array('category_ids'));

        return redirect()->route('admin.products.index')
            ->with('status', 'Ürün güncellendi.');
    }

    /**
     * Toplu işlem: seçili ürünleri aktif/pasif yap veya stok görünürlüğünü düzelt.
     */
    public function bulkAction(Request $request, TenantResolver $tenants, ProductBrandInferer $brandInferer): RedirectResponse
    {
        $tenant = $tenants->resolve($request);
        $action = $request->input('action');
        $ids = array_values(array_filter($request->array('ids'), fn ($id) => is_numeric($id)));

        switch ($action) {
            case 'sync_stock_visibility':
                $activated = Product::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('stock_quantity', '>', 0)
                    ->where('price_cents', '>', 0)
                    ->where('is_active', false)
                    ->update([
                        'is_active' => true,
                        'sync_version' => DB::raw('sync_version + 1'),
                        'updated_at' => now(),
                    ]);

                $deactivated = Product::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('stock_quantity', 0)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'sync_version' => DB::raw('sync_version + 1'),
                        'updated_at' => now(),
                    ]);

                if ($activated > 0 || $deactivated > 0) {
                    $this->touchCatalogVersion($tenant->id);
                }

                return redirect()->route('admin.products.index')
                    ->with('status', "{$activated} stoklu ürün aktif edildi, {$deactivated} stoksuz ürün pasif edildi.");

            case 'deactivate_zero_price':
                $count = Product::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('price_cents', '<=', 0)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'sync_version' => DB::raw('sync_version + 1'),
                        'updated_at' => now(),
                    ]);

                if ($count > 0) {
                    $this->touchCatalogVersion($tenant->id);
                }

                return redirect()->route('admin.products.index')
                    ->with('status', "{$count} fiyatı 0 olan ürün pasif edildi.");

            case 'archive_zero_stock':
                $count = $this->archiveProducts(
                    Product::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('stock_quantity', '<=', 0),
                    'zero_stock'
                );

                if ($count > 0) {
                    $this->touchCatalogVersion($tenant->id);
                }

                return redirect()->route('admin.products.index')
                    ->with('status', "{$count} stoksuz ürün arşive alındı ve pasif edildi.");

            case 'deactivate_zero_stock':
                $count = Product::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('stock_quantity', 0)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'sync_version' => DB::raw('sync_version + 1'),
                        'updated_at' => now(),
                    ]);
                $this->touchCatalogVersion($tenant->id);

                return redirect()->route('admin.products.index')
                    ->with('status', "{$count} ürün pasif edildi (sıfır stok).");

            case 'activate':
                $count = Product::query()->where('tenant_id', $tenant->id)
                    ->whereIn('id', $ids)->update([
                        'is_active' => true,
                        'sync_version' => DB::raw('sync_version + 1'),
                        'updated_at' => now(),
                    ]);
                $this->touchCatalogVersion($tenant->id);

                return redirect()->route('admin.products.index')
                    ->with('status', $count . ' ürün aktif edildi.');

            case 'deactivate':
                $count = Product::query()->where('tenant_id', $tenant->id)
                    ->whereIn('id', $ids)->update([
                        'is_active' => false,
                        'sync_version' => DB::raw('sync_version + 1'),
                        'updated_at' => now(),
                    ]);
                $this->touchCatalogVersion($tenant->id);

                return redirect()->route('admin.products.index')
                    ->with('status', $count . ' ürün pasif edildi.');

            case 'archive_selected':
                if ($ids === []) {
                    return redirect()->route('admin.products.index')
                        ->with('error', 'Arşive almak için ürün seçin.');
                }

                $count = $this->archiveProducts(
                    Product::query()->where('tenant_id', $tenant->id)->whereIn('id', $ids),
                    'manual'
                );

                if ($count > 0) {
                    $this->touchCatalogVersion($tenant->id);
                }

                return redirect()->route('admin.products.index')
                    ->with('status', "{$count} ürün arşive alındı.");

            case 'restore_selected':
                if ($ids === []) {
                    return redirect()->route('admin.products.index')
                        ->with('error', 'Arşivden çıkarmak için ürün seçin.');
                }

                $count = $this->restoreProducts(
                    Product::query()->where('tenant_id', $tenant->id)->whereIn('id', $ids)
                );

                if ($count > 0) {
                    $this->touchCatalogVersion($tenant->id);
                }

                return redirect()->route('admin.products.index', ['archive' => 'archived', 'stock' => ''])
                    ->with('status', "{$count} ürün arşivden çıkarıldı.");

            case 'infer_brands_missing':
            case 'infer_brands_all':
                $onlyMissing = $action === 'infer_brands_missing';
                $count = $this->inferBrands($tenant->id, $brandInferer, $onlyMissing, $ids);
                $this->touchCatalogVersion($tenant->id);

                return redirect()->route('admin.products.index')
                    ->with('status', "{$count} ürünün marka bilgisi ürün adından düzenlendi.");

            case 'apply_brand':
                $brand = $brandInferer->normalizeBrand($request->string('brand_name')->toString());
                if ($brand === null || $ids === []) {
                    return redirect()->route('admin.products.index')
                        ->with('error', 'Marka uygulamak için ürün ve marka adı gerekli.');
                }
                $count = Product::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('id', $ids)
                    ->update([
                        'brand' => $brand,
                        'sync_version' => DB::raw('sync_version + 1'),
                        'updated_at' => now(),
                    ]);
                $this->touchCatalogVersion($tenant->id);

                return redirect()->route('admin.products.index')
                    ->with('status', "{$count} ürüne {$brand} markası uygulandı.");

            default:
                return redirect()->route('admin.products.index')
                    ->with('error', 'Bilinmeyen işlem.');
        }
    }

    private function applySort($query, string $sort): void
    {
        match ($sort) {
            'name' => $query->orderBy('name'),
            'brand' => $query->orderByRaw("COALESCE(NULLIF(brand,''), 'ZZZ') ASC")->orderBy('name'),
            'price_asc' => $query->orderBy('price_cents')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('price_cents')->orderByDesc('id'),
            'stock_asc' => $query->orderBy('stock_quantity')->orderBy('name'),
            'stock_desc' => $query->orderByDesc('stock_quantity')->orderBy('name'),
            default => $query->latest(),
        };
    }

    private function catalogStats(int $tenantId): array
    {
        $base = Product::query()->where('tenant_id', $tenantId);

        return [
            'total' => (clone $base)->count(),
            'active' => (clone $base)->where('is_active', true)->count(),
            'inactive' => (clone $base)->where('is_active', false)->count(),
            'in_stock' => (clone $base)->where('stock_quantity', '>', 0)->count(),
            'out_of_stock' => (clone $base)->where('stock_quantity', '<=', 0)->count(),
            'zero_price' => (clone $base)->where('price_cents', '<=', 0)->count(),
            'archived' => $this->archivedQuery((clone $base))->count(),
            'missing_brand' => (clone $base)->where(fn ($query) => $query->whereNull('brand')->orWhere('brand', ''))->count(),
            'brand_count' => (clone $base)->whereNotNull('brand')->where('brand', '!=', '')->distinct('brand')->count('brand'),
        ];
    }

    private function brandOptions(int $tenantId)
    {
        return Product::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->select('brand')
            ->selectRaw('COUNT(*) as product_count')
            ->groupBy('brand')
            ->orderBy('brand')
            ->get();
    }

    /**
     * @param array<int, int|string> $ids
     */
    private function inferBrands(int $tenantId, ProductBrandInferer $brandInferer, bool $onlyMissing, array $ids = []): int
    {
        $updated = 0;
        $query = Product::query()
            ->where('tenant_id', $tenantId)
            ->select(['id', 'name', 'brand', 'search_keywords']);

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }
        if ($onlyMissing) {
            $query->where(fn ($query) => $query->whereNull('brand')->orWhere('brand', ''));
        }

        $query->orderBy('id')->chunkById(500, function ($products) use (&$updated, $brandInferer): void {
            foreach ($products as $product) {
                $brand = $brandInferer->infer($product->name);
                if ($brand === null || $brand === $product->brand) {
                    continue;
                }

                $keywords = trim(implode(' ', array_filter([$product->name, $brand])));
                Product::query()->whereKey($product->id)->update([
                    'brand' => $brand,
                    'search_keywords' => trim($product->search_keywords ? $product->search_keywords.' '.$brand : $keywords),
                    'sync_version' => DB::raw('sync_version + 1'),
                    'updated_at' => now(),
                ]);
                $updated++;
            }
        });

        return $updated;
    }

    private function applyArchiveFilter($query, string $archiveFilter): void
    {
        match ($archiveFilter) {
            'all' => null,
            'archived' => $this->applyArchivedConstraint($query),
            default => $this->applyVisibleArchiveConstraint($query),
        };
    }

    private function archivedQuery($query)
    {
        $this->applyArchivedConstraint($query);

        return $query;
    }

    private function applyArchivedConstraint($query): void
    {
        $query->where(function ($query): void {
            $query->where('metadata->product_archive->is_archived', true)
                ->orWhere('metadata->archived', true);
        });
    }

    private function applyVisibleArchiveConstraint($query): void
    {
        $query->where(function ($query): void {
            $query->whereNull('metadata->product_archive->is_archived')
                ->orWhere('metadata->product_archive->is_archived', false);
        })->where(function ($query): void {
            $query->whereNull('metadata->archived')
                ->orWhere('metadata->archived', false);
        });
    }

    private function archiveProducts($query, string $reason): int
    {
        $count = 0;

        $query->select(['id', 'metadata'])
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$count, $reason): void {
                foreach ($products as $product) {
                    $metadata = is_array($product->metadata) ? $product->metadata : [];
                    $metadata['product_archive'] = [
                        'is_archived' => true,
                        'reason' => $reason,
                        'archived_at' => now()->toIso8601String(),
                    ];

                    Product::query()->whereKey($product->id)->update([
                        'is_active' => false,
                        'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                        'sync_version' => DB::raw('sync_version + 1'),
                        'updated_at' => now(),
                    ]);
                    $count++;
                }
            });

        return $count;
    }

    private function restoreProducts($query): int
    {
        $count = 0;

        $query->select(['id', 'metadata', 'stock_quantity', 'price_cents'])
            ->orderBy('id')
            ->chunkById(500, function ($products) use (&$count): void {
                foreach ($products as $product) {
                    $metadata = is_array($product->metadata) ? $product->metadata : [];
                    unset($metadata['product_archive'], $metadata['archived']);

                    Product::query()->whereKey($product->id)->update([
                        'is_active' => $product->stock_quantity > 0 && $product->price_cents > 0,
                        'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
                        'sync_version' => DB::raw('sync_version + 1'),
                        'updated_at' => now(),
                    ]);
                    $count++;
                }
            });

        return $count;
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

    /**
     * Güvenli slug oluşturur (benzersizlik kontrolü ile)
     */
    private function generateSlug(?string $slug, string $name, ?Product $product = null): string
    {
        $baseSlug = $slug ? Str::slug($slug) : Str::slug($name);

        $query = Product::query()->where('slug', $baseSlug);

        if ($product) {
            $query->where('id', '!=', $product->id);
        }

        if (!$query->exists()) {
            return $baseSlug;
        }

        $counter = 1;
        do {
            $newSlug = $baseSlug . '-' . $counter;
            $exists = Product::query()
                ->where('slug', $newSlug)
                ->when($product, fn ($q) => $q->where('id', '!=', $product->id))
                ->exists();
            $counter++;
        } while ($exists);

        return $newSlug;
    }

    /**
     * Güvenli görsel yükleme işlemi
     */
    private function handleImageUpload(Request $request, ?string $fallback, ?Product $product = null): ?string
    {
        if (!$request->hasFile('image_file')) {
            return $fallback;
        }

        $file = $request->file('image_file');

        if (!$file->isValid()) {
            return $fallback;
        }

        // Dosya türü doğrulama
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            return $fallback;
        }

        // Dosya uzantısı doğrulama
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return $fallback;
        }

        // Dosya boyutu kontrolü (4MB)
        if ($file->getSize() > 4 * 1024 * 1024) {
            return $fallback;
        }

        // Eski görseli sil (güncelleme durumunda)
        if ($product && $product->image_url) {
            $this->deleteOldImage($product->image_url);
        }

        // Güvenli dosya adı oluştur
        $fileName = Str::uuid() . '.' . $extension;

        // Dosyayı yükle
        $path = $file->storeAs('products', $fileName, 'public');

        if (!$path) {
            return $fallback;
        }

        return asset('storage/' . $path);
    }

    /**
     * Eski görseli storage'dan siler
     */
    private function deleteOldImage(string $imageUrl): void
    {
        try {
            $path = str_replace(asset('storage/'), '', $imageUrl);
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        } catch (\Exception $e) {
            // Loglama yapılabilir
        }
    }

    /**
     * Form validasyon kuralları
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'regex:/^[a-z0-9-]+$/'],
            'description' => ['nullable', 'string', 'max:1000'],
            'brand' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:255'],
            'price_cents' => ['required', 'integer', 'min:0'],
            'compare_at_price_cents' => ['nullable', 'integer', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'meta_image_url' => ['nullable', 'url', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
        ]) + [
            'is_active' => false,
        ];
    }

    /**
     * SEO verilerini JSON olarak hazırlar
     */
    private function withSeoPayload(array $validated): array
    {
        $seo = [
            'title' => $validated['seo_title'] ?? null,
            'description' => $validated['seo_description'] ?? null,
            'image_url' => $validated['meta_image_url'] ?? null,
        ];

        unset(
            $validated['seo_title'],
            $validated['seo_description'],
            $validated['meta_image_url'],
            $validated['image_file'],
            $validated['category_ids']
        );

        $validated['seo'] = array_filter($seo);

        return $validated;
    }
}
