<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Story extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'title',
        'subtitle',
        'image_path',
        'category_slug',
        'custom_url',
        'gradient_start',
        'gradient_end',
        'icon',
        'sort_order',
        'is_active',
        'show_on_mobile',
        'show_on_web',
        'influencer_name',
        'influencer_handle',
        'promo_code',
        'cta_url',
        'content_type',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'show_on_mobile' => 'boolean',
            'show_on_web' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path
            ? Storage::disk('public')->url($this->image_path)
            : null;
    }
}
