<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartCoupon;
use App\Models\CartItem;
use App\Support\CouponSupport;
use App\Support\TenantResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CouponController extends Controller
{
    public function validate(Request $request, TenantResolver $tenants): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'subtotal_cents' => ['nullable', 'integer', 'min:0'],
        ]);

        $tenant = $tenants->resolve($request);
        $cartToken = $this->cartToken($request);
        $subtotalCents = $this->cartSubtotal($request, $tenant->id, $cartToken);

        if ($subtotalCents <= 0) {
            return response()->json(['message' => 'Kupon uygulamak için sepetinizde ürün olmalı.'], 422);
        }

        $support = $this->couponSupport();
        $code = $support->normalizeCode($validated['code']);
        $coupon = $support->findActiveCoupon($tenant->id, $code);
        $invalidReason = $support->invalidReason($coupon, $subtotalCents);

        if ($invalidReason !== null) {
            return response()->json(['message' => $invalidReason], 422);
        }

        $this->cartCouponQuery($request, $tenant->id, $cartToken)->delete();

        CartCoupon::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $request->user()?->id,
            'cart_token' => $request->user() ? null : $cartToken,
            'coupon_id' => $coupon->id,
        ]);

        return response()->json([
            'data' => $support->serialize($coupon, $subtotalCents),
        ]);
    }

    public function destroy(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $cartToken = $this->cartToken($request, create: false);

        $this->cartCouponQuery($request, $tenant->id, $cartToken)->delete();

        return response()->json(['data' => ['removed' => true]]);
    }

    private function cartToken(Request $request, bool $create = true): ?string
    {
        if ($request->user()) {
            return null;
        }

        $token = $request->header('X-Cart-Token') ?: $request->input('cart_token');

        if (! $token && $create) {
            return null;
        }

        return $token ? Str::limit((string) $token, 64, '') : null;
    }

    private function cartSubtotal(Request $request, int $tenantId, ?string $cartToken): int
    {
        return $this->cartQuery($request, $tenantId, $cartToken)
            ->with('product:id,price_cents')
            ->get()
            ->sum(fn (CartItem $item): int => $item->product->price_cents * $item->quantity);
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

    private function couponSupport(): CouponSupport
    {
        return app(CouponSupport::class);
    }
}
