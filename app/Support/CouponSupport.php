<?php

namespace App\Support;

use App\Models\Coupon;
use Illuminate\Support\Str;

class CouponSupport
{
    public function normalizeCode(?string $code): string
    {
        return Str::upper(trim((string) $code));
    }

    public function findActiveCoupon(int $tenantId, string $code, bool $lockForUpdate = false): ?Coupon
    {
        if ($code === '') {
            return null;
        }

        return Coupon::query()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->where('is_active', true)
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->first();
    }

    public function invalidReason(?Coupon $coupon, int $subtotalCents): ?string
    {
        if (! $coupon) {
            return 'Kupon kodu geçersiz.';
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return 'Bu kupon henüz aktif değil.';
        }

        if ($coupon->ends_at && $coupon->ends_at->isPast()) {
            return 'Bu kuponun süresi dolmuş.';
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return 'Bu kuponun kullanım limiti dolmuş.';
        }

        if ($subtotalCents < $coupon->minimum_order_cents) {
            $minAmount = number_format($coupon->minimum_order_cents / 100, 2, ',', '.') . ' ₺';

            return "Bu kupon için minimum sipariş tutarı {$minAmount}.";
        }

        return null;
    }

    public function calculateDiscount(Coupon $coupon, int $subtotalCents): int
    {
        return $coupon->discount_type === 'percent'
            ? (int) round($subtotalCents * $coupon->discount_value / 100)
            : min($coupon->discount_value, $subtotalCents);
    }

    /**
     * @return array{
     *     code: string,
     *     discount_type: string,
     *     discount_value: int,
     *     discount_cents: int,
     *     total_cents: int
     * }
     */
    public function serialize(Coupon $coupon, int $subtotalCents): array
    {
        $discountCents = $this->calculateDiscount($coupon, $subtotalCents);

        return [
            'code' => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (int) $coupon->discount_value,
            'discount_cents' => $discountCents,
            'total_cents' => max(0, $subtotalCents - $discountCents),
        ];
    }
}
