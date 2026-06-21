<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'description',
        'body',
        'banner_image_url',
        'image_path',
        'meta_image_url',
        'badge_label',
        'color_hex',
        'discount_type',
        'discount_value',
        'starts_at',
        'ends_at',
        'is_active',
        'show_on_mobile',
        'show_on_web',
        'sort_order',
        'seo',
        'influencer_name',
        'influencer_handle',
        'promo_code',
        'cta_url',
        'content_type',
    ];

    protected function casts(): array
    {
        return [
            'starts_at'  => 'datetime',
            'ends_at'    => 'datetime',
            'is_active'  => 'boolean',
            'show_on_mobile' => 'boolean',
            'show_on_web' => 'boolean',
            'sort_order' => 'integer',
            'seo'        => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function getBannerUrlAttribute(): ?string
    {
        if ($this->image_path) {
            return Storage::disk('public')->url($this->image_path);
        }

        return $this->banner_image_url ?: null;
    }

    /** Kullanıcıya gösterilecek indirim etiketi */
    public function getDiscountLabelAttribute(): string
    {
        if ($this->discount_type === 'percent') {
            return "%{$this->discount_value} İndirim";
        }

        return number_format($this->discount_value / 100, 2, ',', '.') . ' ₺ İndirim';
    }
}
