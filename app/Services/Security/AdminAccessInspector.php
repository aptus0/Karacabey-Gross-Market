<?php

namespace App\Services\Security;

use App\Models\AdminAuthLog;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\IpUtils;

class AdminAccessInspector
{
    /**
     * @return array{
     *     ip_address: ?string,
     *     trusted: bool,
     *     failed_count: int,
     *     decoy_count: int,
     *     risk_score: int,
     *     risk_reasons: array<int, string>,
     *     should_block: bool,
     *     blocked_until: mixed
     * }
     */
    public function inspect(Request $request): array
    {
        $ipAddress = $request->ip();
        $trusted = $this->isTrustedIp($ipAddress);
        $windowMinutes = $this->windowMinutes();
        $failedCount = $this->recentFailedAttempts($ipAddress, $windowMinutes);
        $decoyCount = $this->recentDecoyHits($ipAddress, $windowMinutes);
        $activeBlock = $this->activeBlock($ipAddress);
        $riskScore = 0;
        $riskReasons = [];

        if ($failedCount > 0) {
            $riskScore += min(45, $failedCount * 12);
            $riskReasons[] = 'recent_failed_login';
        }

        if ($decoyCount > 0) {
            $riskScore += min(50, $decoyCount * 25);
            $riskReasons[] = 'decoy_route_hit';
        }

        if ($this->isDecoyRoute($request)) {
            $riskScore += 45;
            $riskReasons[] = 'mapped_decoy_route';
        }

        $userAgent = Str::lower((string) $request->userAgent());

        if ($userAgent === '') {
            $riskScore += 15;
            $riskReasons[] = 'empty_user_agent';
        } elseif ($this->matchesSuspiciousUserAgent($userAgent)) {
            $riskScore += 25;
            $riskReasons[] = 'scanner_user_agent';
        }

        $thresholdBlock = $failedCount >= $this->maxFailedAttempts()
            || $decoyCount >= $this->maxDecoyHits();

        $shouldBlock = ! $trusted && ($activeBlock !== null || $thresholdBlock);
        $blockedUntil = $activeBlock?->blocked_until;

        if ($shouldBlock && $blockedUntil === null) {
            $blockedUntil = now()->addMinutes($this->blockMinutes());
        }

        if ($trusted) {
            $riskReasons[] = 'trusted_ip';
        }

        return [
            'ip_address' => $ipAddress,
            'trusted' => $trusted,
            'failed_count' => $failedCount,
            'decoy_count' => $decoyCount,
            'risk_score' => min(100, $riskScore),
            'risk_reasons' => array_values(array_unique($riskReasons)),
            'should_block' => $shouldBlock,
            'blocked_until' => $blockedUntil,
        ];
    }

    public function recordLoginAttempt(Request $request, string $email, bool $successful): AdminAuthLog
    {
        $inspection = $this->inspect($request);

        if ($successful) {
            return $this->record($request, [
                'event_type' => 'login_attempt',
                'status' => 'success',
                'guard_action' => 'allow',
                'email' => $email,
                'risk_score' => min(25, $inspection['risk_score']),
                'risk_reasons' => $inspection['trusted'] ? ['trusted_ip'] : [],
                'meta' => ['remember' => $request->boolean('remember')],
            ], $inspection);
        }

        $nextFailedCount = $inspection['failed_count'] + 1;
        $shouldBlock = ! $inspection['trusted'] && (
            $inspection['should_block'] || $nextFailedCount >= $this->maxFailedAttempts()
        );
        $riskReasons = $inspection['risk_reasons'];

        if ($nextFailedCount >= $this->maxFailedAttempts()) {
            $riskReasons[] = 'failed_login_threshold';
        }

        return $this->record($request, [
            'event_type' => 'login_attempt',
            'status' => $shouldBlock ? 'blocked' : 'failed',
            'guard_action' => $shouldBlock ? 'block' : 'deny',
            'email' => $email,
            'risk_score' => $shouldBlock ? max(90, $inspection['risk_score']) : max(35, $inspection['risk_score']),
            'risk_reasons' => array_values(array_unique($riskReasons)),
            'blocked_until' => $shouldBlock ? ($inspection['blocked_until'] ?? now()->addMinutes($this->blockMinutes())) : null,
            'meta' => [
                'remember' => $request->boolean('remember'),
                'failed_count' => $nextFailedCount,
            ],
        ], $inspection);
    }

    public function recordBlockedRequest(Request $request, ?array $inspection = null): AdminAuthLog
    {
        $inspection ??= $this->inspect($request);

        return $this->record($request, [
            'event_type' => 'blocked_request',
            'status' => 'blocked',
            'guard_action' => 'fake_auth2',
            'risk_score' => max(90, $inspection['risk_score']),
            'risk_reasons' => $inspection['risk_reasons'],
            'blocked_until' => $inspection['blocked_until'] ?? now()->addMinutes($this->blockMinutes()),
            'meta' => [
                'query' => $request->query(),
                'failed_count' => $inspection['failed_count'],
                'decoy_count' => $inspection['decoy_count'],
            ],
        ], $inspection);
    }

    public function recordDecoy(Request $request, string $eventType, array $meta = []): AdminAuthLog
    {
        $inspection = $this->inspect($request);
        $nextDecoyCount = $inspection['decoy_count'] + 1;
        $shouldBlock = ! $inspection['trusted'] && (
            $inspection['should_block'] || $nextDecoyCount >= $this->maxDecoyHits()
        );
        $riskReasons = array_values(array_unique(array_merge(
            $inspection['risk_reasons'],
            ['decoy_route_hit']
        )));

        if ($nextDecoyCount >= $this->maxDecoyHits()) {
            $riskReasons[] = 'decoy_threshold';
        }

        return $this->record($request, [
            'event_type' => $eventType,
            'status' => $shouldBlock ? 'blocked' : 'decoy',
            'guard_action' => 'fake_auth2',
            'email' => Arr::get($meta, 'identifier'),
            'risk_score' => $shouldBlock ? max(95, $inspection['risk_score']) : max(70, $inspection['risk_score']),
            'risk_reasons' => array_values(array_unique($riskReasons)),
            'blocked_until' => $shouldBlock ? ($inspection['blocked_until'] ?? now()->addMinutes($this->blockMinutes())) : null,
            'meta' => $meta + [
                'decoy_count' => $nextDecoyCount,
            ],
        ], $inspection);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>|null  $inspection
     */
    public function record(Request $request, array $attributes, ?array $inspection = null): AdminAuthLog
    {
        $inspection ??= $this->inspect($request);
        $route = $request->route();
        $email = Arr::get($attributes, 'email');

        return AdminAuthLog::query()->create([
            'event_type' => (string) Arr::get($attributes, 'event_type', 'admin_request'),
            'status' => (string) Arr::get($attributes, 'status', 'observed'),
            'guard_action' => (string) Arr::get($attributes, 'guard_action', 'allow'),
            'route_name' => $route?->getName(),
            'path' => '/'.trim($request->path(), '/'),
            'method' => $request->method(),
            'ip_address' => $inspection['ip_address'],
            'email' => filled($email) ? Str::lower((string) $email) : null,
            'user_agent' => Str::limit((string) $request->userAgent(), 1024, ''),
            'risk_score' => (int) Arr::get($attributes, 'risk_score', $inspection['risk_score']),
            'risk_reasons' => Arr::get($attributes, 'risk_reasons', $inspection['risk_reasons']),
            'blocked_until' => Arr::get($attributes, 'blocked_until', $inspection['blocked_until']),
            'meta' => Arr::get($attributes, 'meta', []),
        ]);
    }

    protected function recentFailedAttempts(?string $ipAddress, int $windowMinutes): int
    {
        if ($ipAddress === null) {
            return 0;
        }

        return AdminAuthLog::query()
            ->where('ip_address', $ipAddress)
            ->where('event_type', 'login_attempt')
            ->whereIn('status', ['failed', 'blocked'])
            ->recent($windowMinutes)
            ->count();
    }

    protected function recentDecoyHits(?string $ipAddress, int $windowMinutes): int
    {
        if ($ipAddress === null) {
            return 0;
        }

        return AdminAuthLog::query()
            ->where('ip_address', $ipAddress)
            ->where('event_type', 'like', 'decoy_%')
            ->recent($windowMinutes)
            ->count();
    }

    protected function activeBlock(?string $ipAddress): ?AdminAuthLog
    {
        if ($ipAddress === null) {
            return null;
        }

        return AdminAuthLog::query()
            ->where('ip_address', $ipAddress)
            ->activeBlock()
            ->latest('blocked_until')
            ->first();
    }

    protected function isDecoyRoute(Request $request): bool
    {
        $routeName = (string) $request->route()?->getName();

        return Str::startsWith($routeName, 'admin.decoy')
            || Str::startsWith($routeName, 'admin.fake-auth2');
    }

    protected function isTrustedIp(?string $ipAddress): bool
    {
        if ($ipAddress === null) {
            return false;
        }

        $trustedIps = (array) config('admin_security.trusted_ips', []);

        if (app()->isLocal()) {
            $trustedIps = array_merge($trustedIps, ['127.0.0.1', '::1']);
        }

        foreach ($trustedIps as $trustedIp) {
            if ($trustedIp !== '' && IpUtils::checkIp($ipAddress, $trustedIp)) {
                return true;
            }
        }

        return false;
    }

    protected function matchesSuspiciousUserAgent(string $userAgent): bool
    {
        foreach ((array) config('admin_security.suspicious_user_agents', []) as $needle) {
            if ($needle !== '' && str_contains($userAgent, Str::lower((string) $needle))) {
                return true;
            }
        }

        return false;
    }

    protected function maxFailedAttempts(): int
    {
        return max(1, (int) config('admin_security.max_failed_attempts', 5));
    }

    protected function maxDecoyHits(): int
    {
        return max(1, (int) config('admin_security.max_decoy_hits', 2));
    }

    protected function windowMinutes(): int
    {
        return max(1, (int) config('admin_security.window_minutes', 15));
    }

    protected function blockMinutes(): int
    {
        return max(1, (int) config('admin_security.block_minutes', 30));
    }
}
