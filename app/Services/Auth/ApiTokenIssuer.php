<?php

namespace App\Services\Auth;

use App\Models\ApiToken;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class ApiTokenIssuer
{
    public const DEFAULT_TTL_DAYS = 30;
    public const MAX_TOKENS_PER_USER = 8;

    /**
     * @return array{token: string, expires_at: CarbonImmutable}
     */
    public function issue(User $user, string $deviceName = 'default', ?int $ttlDays = null): array
    {
        $plainToken = Str::random(64);
        $expiresAt  = CarbonImmutable::now()->addDays($ttlDays ?? self::DEFAULT_TTL_DAYS);

        $user->apiTokens()->create([
            'name'       => $this->sanitizeDeviceName($deviceName),
            'token_hash' => hash('sha256', $plainToken),
            'abilities'  => ['*'],
            'expires_at' => $expiresAt,
        ]);

        $this->pruneExcess($user);

        return [
            'token'      => $plainToken,
            'expires_at' => $expiresAt,
        ];
    }

    private function sanitizeDeviceName(string $deviceName): string
    {
        $clean = trim($deviceName) !== '' ? trim($deviceName) : 'default';

        return mb_substr($clean, 0, 80);
    }

    private function pruneExcess(User $user): void
    {
        $excess = $user->apiTokens()
            ->orderByDesc('id')
            ->skip(self::MAX_TOKENS_PER_USER)
            ->take(PHP_INT_MAX)
            ->pluck('id');

        if ($excess->isNotEmpty()) {
            ApiToken::query()->whereIn('id', $excess)->delete();
        }
    }
}
