<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Number;

class Product extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Product $product): void {
            if ((int) $product->price_cents <= 0) {
                $product->is_active = false;
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'brand',
        'barcode',
        'price_cents',
        'compare_at_price_cents',
        'stock_quantity',
        'unit_name',
        'image_url',
        'seo',
        'metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'seo' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function formattedPrice(): string
    {
        return Number::currency($this->price_cents / 100, 'TRY', locale: 'tr');
    }

    
}
