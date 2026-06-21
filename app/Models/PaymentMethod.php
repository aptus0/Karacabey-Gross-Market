<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'utoken',
        'ctoken',
        'card_last_four',
        'card_schema',
        'card_brand',
        'card_type',
        'card_bank',
        'expiry_month',
        'expiry_year',
        'requires_cvv',
        'is_default',
    ];

    protected $hidden = [
        'utoken',
        'ctoken',
    ];

    protected function casts(): array
    {
        return [
            'utoken' => 'encrypted',
            'ctoken' => 'encrypted',
            'requires_cvv' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
