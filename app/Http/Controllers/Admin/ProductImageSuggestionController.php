<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Catalog\ProductImageResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductImageSuggestionController extends Controller
{
    public function __construct(private ProductImageResolver $resolver) {}

    /**
     * GET /admin/products/{product}/image-suggestions
     * Bir ürün için görsel adaylarını JSON olarak döner. Admin modal'da render edilir.
     */
    public function suggest(Product $product): JsonResponse
    {
        $candidates = $this->resolver->suggestCandidates($product);

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'brand' => $product->brand,
                'barcode' => $product->barcode,
                'current_image_url' => $product->image_url,
            ],
            'candidates' => $candidates,
        ]);
    }

    /**
     * POST /admin/products/{product}/image-suggestions/apply
     * Adminin seçtiği aday URL'i indirip storage'a kaydeder ve image_url'i günceller.
     */
    public function apply(Request $request, Product $product): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:1000'],
            'thumb' => ['nullable', 'url', 'max:1000'],
        ]);

        $stored = $this->resolver->downloadAndStore($data['url'], $product);
        if ($stored === null && !empty($data['thumb']) && $data['thumb'] !== $data['url']) {
            $stored = $this->resolver->downloadAndStore($data['thumb'], $product);
        }

        if ($stored === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Görsel indirilemedi. Kaynak link bozuk, erişimi kapalı veya format desteklenmiyor.',
            ], 422);
        }

        $updates = [
            'image_url' => $stored,
        ];

        if (Schema::hasColumn('products', 'sync_version')) {
            $updates['sync_version'] = DB::raw('sync_version + 1');
        }

        $product->update($updates);

        return response()->json([
            'ok' => true,
            'image_url' => $stored,
            'message' => 'Görsel ürüne uygulandı.',
        ]);
    }
}
