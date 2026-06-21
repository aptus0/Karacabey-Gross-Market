<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable([
    'public_uid',
    'customer_uid',
    'sync_version',
    'name',
    'phone',
    'email',
    'password',
    'is_admin',
    'google_id',
    'google_email',
    'github_id',
    'github_email',
    'facebook_id',
    'facebook_email',
    'avatar_url',
    'email_verified_at',
    'last_ip',
    'last_location',
    'last_login_at',
    'loyalty_points',
    'loyalty_points_lifetime',
    'is_vip',
    'vip_started_at',
    'vip_expires_at',
    'vip_note',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at'     => 'datetime',
            'password'          => 'hashed',
            'is_admin'          => 'boolean',
            'sync_version'      => 'integer',
            'loyalty_points'    => 'integer',
            'loyalty_points_lifetime' => 'integer',
            'is_vip'            => 'boolean',
            'vip_started_at'    => 'datetime',
            'vip_expires_at'    => 'datetime',
        ];
    }

    public function isVipActive(): bool
    {
        if (! $this->is_vip) {
            return false;
        }

        return $this->vip_expires_at === null || $this->vip_expires_at->isFuture();
    }

    public function isAdFree(): bool
    {
        return $this->isVipActive();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }
}
