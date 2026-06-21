<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'provider',
        'merchant_oid',
        'payment_uid',
        'customer_uid',
        'checkout_uid',
        'status',
        'amount_cents',
        'captured_amount_cents',
        'currency',
        'provider_token',
        'payment_type',
        'failed_reason_code',
        'failed_reason_msg',
        'provider_payload',
        'confirmed_at',
    ];

    protected $hidden = [
        'provider_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'provider_token' => 'encrypted',
            'provider_payload' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
