<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorite;
use App\Models\Product;
use App\Support\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function index(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);

        $favorites = $request->user()
            ->favorites()
            ->with('product')
            ->whereHas('product', fn ($query) => $query->whereBelongsTo($tenant))
            ->latest()
            ->get()
            ->map(fn (Favorite $favorite): array => $this->serializeProduct($favorite->product));

        return response()->json(['data' => $favorites]);
    }

    public function store(Request $request, TenantResolver $tenants, Product $product): JsonResponse
    {
        $tenant = $tenants->resolve($request);

        abort_unless($product->tenant_id === $tenant->id && $product->is_active, 404);

        $favorite = $request->user()->favorites()->firstOrCreate([
            'product_id' => $product->id,
        ]);

        return response()->json(['data' => $this->serializeProduct($favorite->product()->firstOrFail())], 201);
    }

    public function destroy(Request $request, TenantResolver $tenants, Product $product): JsonResponse
    {
        $tenant = $tenants->resolve($request);

        abort_unless($product->tenant_id === $tenant->id, 404);

        $request->user()
            ->favorites()
            ->where('product_id', $product->id)
            ->delete();

        return response()->json(['data' => ['status' => 'deleted']]);
    }

    private function serializeProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'brand' => $product->brand,
            'price_cents' => $product->price_cents,
            'price' => $product->formattedPrice(),
            'image_url' => $product->image_url,
        ];
    }
}
