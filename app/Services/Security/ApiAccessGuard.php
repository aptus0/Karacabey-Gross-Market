<?php

namespace App\Services\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class ApiAccessGuard
{
    public function enabled(): bool
    {
        return (bool) config('api_security.enabled', true);
    }

    public function isBlocked(Request $request): bool
    {
        $rule = $this->matchingRule($request);
        $ipAddress = $request->ip();

        if ($rule === null || $ipAddress === null || $this->isTrustedIp($ipAddress)) {
            return false;
        }

        return Cache::has($this->banCacheKey($ipAddress, $rule['path']));
    }

    public function shouldTrap(Request $request): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        $rule = $this->matchingRule($request);

        if ($rule === null) {
            return false;
        }

        return ! in_array($request->getMethod(), $rule['methods'], true);
    }

    public function block(Request $request, string $reason): void
    {
        $rule = $this->matchingRule($request);
        $ipAddress = $request->ip();

        if ($rule === null || $ipAddress === null || $this->isTrustedIp($ipAddress)) {
            return;
        }

        Cache::put($this->banCacheKey($ipAddress, $rule['path']), [
            'reason' => $reason,
            'path' => '/'.ltrim($request->path(), '/'),
            'method' => $request->getMethod(),
            'banned_at' => now()->toIso8601String(),
        ], now()->addMinutes($this->banMinutes()));
    }

    public function response(Request $request, string $reason, int $status = 403): Response
    {
        if ($request->expectsJson() || $request->wantsJson() || ! $request->acceptsHtml()) {
            return response()->json([
                'message' => 'Bu API ucu dis erisime kapatildi.',
            ], $status, [
                'Cache-Control' => 'no-store, private',
            ]);
        }

        $redirectUrl = $this->redirectUrl();

        return response()
            ->view('errors.api-protected', [
                'reason' => $reason,
                'redirectUrl' => $redirectUrl,
                'redirectDelay' => 1,
            ], $status)
            ->header('Refresh', "1;url={$redirectUrl}")
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * @return array{path: string, methods: array<int, string>}|null
     */
    protected function matchingRule(Request $request): ?array
    {
        $path = trim($request->path(), '/');

        foreach ((array) config('api_security.protected_entrypoints', []) as $protectedPath => $methods) {
            if ($path !== trim((string) $protectedPath, '/')) {
                continue;
            }

            return [
                'path' => $protectedPath,
                'methods' => array_values(array_unique(array_map('strtoupper', (array) $methods))),
            ];
        }

        return null;
    }

    protected function banMinutes(): int
    {
        return max(1, (int) config('api_security.ban_minutes', 1440));
    }

    protected function redirectUrl(): string
    {
        return rtrim((string) config('api_security.redirect_url', '/'), '/') ?: '/';
    }

    protected function banCacheKey(string $ipAddress, string $path): string
    {
        return 'api-security:ban:'.sha1($path).':'.$ipAddress;
    }

    protected function isTrustedIp(string $ipAddress): bool
    {
        $trustedIps = array_merge((array) config('api_security.trusted_ips', []), ['127.0.0.1', '::1']);

        foreach ($trustedIps as $trustedIp) {
            if ($trustedIp !== '' && IpUtils::checkIp($ipAddress, $trustedIp)) {
                return true;
            }
        }

        return false;
    }
}
