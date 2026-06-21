<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AdminAuthLog extends Model
{
    protected $fillable = [
        'event_type',
        'status',
        'guard_action',
        'route_name',
        'path',
        'method',
        'ip_address',
        'email',
        'user_agent',
        'risk_score',
        'risk_reasons',
        'blocked_until',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'risk_reasons' => 'array',
            'blocked_until' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function scopeRecent(Builder $query, int $minutes): Builder
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }

    public function scopeActiveBlock(Builder $query): Builder
    {
        return $query->whereNotNull('blocked_until')
            ->where('blocked_until', '>', now());
    }
}
