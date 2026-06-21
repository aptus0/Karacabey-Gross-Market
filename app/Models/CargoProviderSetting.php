<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargoProviderSetting extends Model
{
    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'is_active',
        'price_cents',
        'free_threshold_cents',
        'estimated_days_min',
        'estimated_days_max',
        'credentials',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_active'            => 'boolean',
            'price_cents'          => 'integer',
            'free_threshold_cents' => 'integer',
            'credentials'          => 'encrypted:array',
            'settings'             => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function logoUrl(): string
    {
        $map = [
            'YURTICI' => 'cargo/yurtici.svg',
            'ARAS'    => 'cargo/aras.svg',
            'PTT'     => 'cargo/ptt.svg',
            'MNG'     => 'cargo/mng.svg',
        ];

        return asset('assets/' . ($map[$this->code] ?? 'cargo/default.svg'));
    }

    /** Verilen sipariş tutarında bu kargo bedava mı? */
    public function isFreeFor(int $orderCents): bool
    {
        return $this->free_threshold_cents > 0 && $orderCents >= $this->free_threshold_cents;
    }

    /** Sipariş için geçerli kargo ücretini döndür (kuruş) */
    public function effectivePriceCents(int $orderCents): int
    {
        return $this->isFreeFor($orderCents) ? 0 : $this->price_cents;
    }

    public function isConfigured(?array $credentials = null): bool
    {
        $credentials ??= $this->credentials ?? [];
        $required = self::definitions()[$this->code]['required_credentials'] ?? [];

        foreach ($required as $key) {
            if (trim((string) ($credentials[$key] ?? '')) === '') {
                return false;
            }
        }

        return $required !== [];
    }

    /** Tüm desteklenen sağlayıcıların tanım listesi */
    public static function definitions(): array
    {
        return [
            'YURTICI' => ['name' => 'Yurtiçi Kargo', 'color' => '#CC0000', 'required_credentials' => ['client_number', 'password']],
            'ARAS'    => ['name' => 'Aras Kargo',    'color' => '#E84C0C', 'required_credentials' => ['username', 'password', 'customer_code']],
            'PTT'     => ['name' => 'PTT Kargo',     'color' => '#F5C400', 'required_credentials' => ['api_key', 'customer_id']],
            'MNG'     => ['name' => 'MNG Kargo',     'color' => '#D40000', 'required_credentials' => ['api_key', 'merchant_code']],
        ];
    }
}
