<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'customer_uid',
        'session_uid',
        'checkout_uid',
        'payment_uid',
        'merchant_oid',
        'checkout_ref',
        'status',
        'currency',
        'subtotal_cents',
        'shipping_cents',
        'discount_cents',
        'total_cents',
        'customer_name',
        'customer_email',
        'customer_phone',
        'shipping_city',
        'shipping_district',
        'shipping_address',
        'metadata',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'metadata' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    public function statusEvents(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class)->latest();
    }

    public function sourceKey(): string
    {
        $source = strtolower((string) (
            data_get($this->metadata, 'source')
            ?? data_get($this->metadata, 'order_source')
            ?? data_get($this->metadata, 'channel')
            ?? data_get($this->metadata, 'platform')
            ?? data_get($this->metadata, 'created_from')
            ?? ''
        ));

        if (in_array($source, ['ios', 'iphone', 'ipad'], true)
            || str_starts_with((string) $this->checkout_uid, 'ios-')
            || str_starts_with((string) $this->payment_uid, 'ios-')) {
            return 'ios';
        }

        if ($source === 'android') {
            return 'android';
        }

        if (in_array($source, ['mobile', 'mobil', 'app', 'mobile_app'], true)) {
            return 'mobile';
        }

        return 'web';
    }

    public function isMobileOrder(): bool
    {
        return in_array($this->sourceKey(), ['ios', 'android', 'mobile'], true);
    }

    public function isLocalDelivery(): bool
    {
        $normalize = static fn (?string $value): string => str_replace(
            ['ı', 'İ', 'ş', 'Ş', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö', 'ç', 'Ç'],
            ['i', 'i', 's', 's', 'g', 'g', 'u', 'u', 'o', 'o', 'c', 'c'],
            strtolower(trim((string) $value))
        );

        return str_contains($normalize($this->shipping_city), 'bursa')
            && str_contains($normalize($this->shipping_district), 'karacabey');
    }

    public function isCashOnDelivery(): bool
    {
        return $this->payment?->provider === 'cash_on_delivery'
            || (bool) data_get($this->metadata, 'cash_on_delivery', false);
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: int}>
     */
    public function paytrBasket(): array
    {
        return $this->items->map(fn (OrderItem $item): array => [
            $item->name,
            number_format($item->unit_price_cents / 100, 2, '.', ''),
            $item->quantity,
        ])->values()->all();
    }
}
