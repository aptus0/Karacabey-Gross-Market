<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NavigationItem extends Model
{
    use HasFactory;

    public const PLACEMENTS = [
        'top' => 'Header ust menu',
        'header' => 'Header ana menu',
        'category' => 'Kategori seridi',
        'footer_primary' => 'Footer ana linkler',
        'footer_corporate' => 'Footer kurumsal',
        'footer_support' => 'Footer destek',
        'footer_account' => 'Footer hesap',
    ];

    public const ICONS = [
        'home' => 'Ana sayfa',
        'grid' => 'Kategoriler',
        'cart' => 'Sepet',
        'heart' => 'Favoriler',
        'user' => 'Hesap',
        'login' => 'Giris',
        'map-pin' => 'Adres',
        'truck' => 'Teslimat',
        'package-search' => 'Kargo takip',
        'tag' => 'Kampanya',
        'shield' => 'Guven',
        'phone' => 'Telefon',
        'file-text' => 'Sayfa',
    ];

    protected $fillable = [
        'tenant_id',
        'placement',
        'label',
        'url',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
