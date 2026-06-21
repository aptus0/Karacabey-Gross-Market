<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartCoupon;
use App\Models\CartItem;
use App\Models\Product;
use App\Support\CouponSupport;
use App\Support\TenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function show(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $cartToken = $this->cartToken($request);
        $items = $this->loadCartItems($request, $tenant->id, $cartToken);

        return response()->json(['data' => $this->serializeCart($items, $request, $tenant->id, $request->user() ? null : $cartToken)]);
    }

    public function store(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $cartToken = $this->cartToken($request);
        $created = false;

        DB::transaction(function () use ($request, $tenant, $validated, $cartToken, &$created): void {
            $product = Product::query()
                ->whereBelongsTo($tenant)
                ->where('is_active', true)
                ->whereKey($validated['product_id'])
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($product->stock_quantity < $validated['quantity'], 422, 'Stok yetersiz.');

            $item = $this->cartQuery($request, $tenant->id, $cartToken)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->first();

            if ($item) {
                $quantity = min(99, $item->quantity + (int) $validated['quantity']);
                abort_if($product->stock_quantity < $quantity, 422, 'Stok yetersiz.');

                $item->update(['quantity' => $quantity]);

                return;
            }

            CartItem::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $request->user()?->id,
                'cart_token' => $request->user() ? null : $cartToken,
                'product_id' => $product->id,
                'quantity' => (int) $validated['quantity'],
            ]);

            $created = true;
        });

        $items = $this->loadCartItems($request, $tenant->id, $cartToken);

        return response()->json([
            'data' => $this->serializeCart($items, $request, $tenant->id, $request->user() ? null : $cartToken),
        ], $created ? 201 : 200);
    }

    public function update(Request $request, TenantResolver $tenants, CartItem $cartItem): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $cartToken = $this->cartToken($request, create: false);

        abort_unless($this->ownsCartItem($request, $cartItem, $tenant->id, $cartToken), 403);

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $cartItem->loadMissing('product');
        abort_if($cartItem->product->stock_quantity < $validated['quantity'], 422, 'Stok yetersiz.');

        $cartItem->update(['quantity' => (int) $validated['quantity']]);

        $items = $this->loadCartItems($request, $tenant->id, $cartToken);

        return response()->json(['data' => $this->serializeCart($items, $request, $tenant->id, $request->user() ? null : $cartToken)]);
    }

    public function destroy(Request $request, TenantResolver $tenants, CartItem $cartItem): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $cartToken = $this->cartToken($request, create: false);

        abort_unless($this->ownsCartItem($request, $cartItem, $tenant->id, $cartToken), 403);

        $cartItem->delete();

        $items = $this->loadCartItems($request, $tenant->id, $cartToken);

        return response()->json(['data' => $this->serializeCart($items, $request, $tenant->id, $request->user() ? null : $cartToken)]);
    }

    public function clear(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $cartToken = $this->cartToken($request, create: false);

        $this->cartQuery($request, $tenant->id, $cartToken)->delete();
        $this->cartCouponQuery($request, $tenant->id, $cartToken)->delete();

        return response()->json(['data' => $this->serializeCart(collect(), $request, $tenant->id, $request->user() ? null : $cartToken)]);
    }

    private function cartToken(Request $request, bool $create = true): ?string
    {
        if ($request->user()) {
            return null;
        }

        $token = $request->header('X-Cart-Token') ?: $request->input('cart_token');

        if (! $token && $create) {
            $token = (string) Str::uuid();
        }

        return $token ? Str::limit((string) $token, 64, '') : null;
    }

    private function cartQuery(Request $request, int $tenantId, ?string $cartToken): Builder
    {
        return CartItem::query()
            ->where('tenant_id', $tenantId)
            ->when(
                $request->user(),
                fn (Builder $query) => $query->where('user_id', $request->user()->id),
                fn (Builder $query) => $query->where('cart_token', $cartToken)
            );
    }

    private function ownsCartItem(Request $request, CartItem $cartItem, int $tenantId, ?string $cartToken): bool
    {
        if ($cartItem->tenant_id !== $tenantId) {
            return false;
        }

        if ($request->user()) {
            return $cartItem->user_id === $request->user()->id;
        }

        return $cartToken && hash_equals((string) $cartItem->cart_token, $cartToken);
    }

    private function serializeCart($items, Request $request, int $tenantId, ?string $cartToken): array
    {
        $subtotal = $items->sum(fn (CartItem $item): int => $item->product->price_cents * $item->quantity);
        [$appliedCoupon, $discountCents] = $this->resolveAppliedCoupon($request, $tenantId, $cartToken, $subtotal);

        return [
            'cart_token' => $cartToken,
            'items' => $items->map(fn (CartItem $item): array => [
                'id' => $item->id,
                'quantity' => $item->quantity,
                'line_total_cents' => $item->product->price_cents * $item->quantity,
                'product' => [
                    'id' => $item->product->id,
                    'name' => $item->product->name,
                    'slug' => $item->product->slug,
                    'brand' => $item->product->brand,
                    'price_cents' => $item->product->price_cents,
                    'price' => $item->product->formattedPrice(),
                    'stock_quantity' => $item->product->stock_quantity,
                    'unit_name' => $item->product->unit_name ?: 'adet',
                    'image_url' => $item->product->image_url,
                ],
            ])->values(),
            'applied_coupon' => $appliedCoupon,
            'subtotal_cents' => $subtotal,
            'total_cents' => max(0, $subtotal - $discountCents),
        ];
    }

    private function cartCouponQuery(Request $request, int $tenantId, ?string $cartToken): Builder
    {
        return CartCoupon::query()
            ->where('tenant_id', $tenantId)
            ->when(
                $request->user(),
                fn (Builder $query) => $query->where('user_id', $request->user()->id),
                fn (Builder $query) => $query->where('cart_token', $cartToken)
            );
    }

    /**
     * @return array{0: array<string, int|string>|null, 1: int}
     */
    private function resolveAppliedCoupon(Request $request, int $tenantId, ?string $cartToken, int $subtotal): array
    {
        $cartCoupon = $this->cartCouponQuery($request, $tenantId, $cartToken)
            ->with('coupon')
            ->first();

        if (! $cartCoupon) {
            return [null, 0];
        }

        if ($subtotal <= 0 || ! $cartCoupon->coupon) {
            $cartCoupon->delete();

            return [null, 0];
        }

        $support = $this->couponSupport();
        $coupon = $cartCoupon->coupon;

        if ($support->invalidReason($coupon, $subtotal) !== null) {
            $cartCoupon->delete();

            return [null, 0];
        }

        $appliedCoupon = $support->serialize($coupon, $subtotal);

        return [$appliedCoupon, $appliedCoupon['discount_cents']];
    }

    private function couponSupport(): CouponSupport
    {
        return app(CouponSupport::class);
    }

    private function loadCartItems(Request $request, int $tenantId, ?string $cartToken)
    {
        return $this->cartQuery($request, $tenantId, $cartToken)
            ->with([
                'product:id,name,slug,brand,price_cents,stock_quantity,unit_name,image_url',
            ])
            ->latest()
            ->get();
    }
}
