<?php

namespace App\Http\Middleware;

use App\Services\Security\ApiAccessGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectSensitiveApiEndpoints
{
    public function __construct(private readonly ApiAccessGuard $guard) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->guard->enabled()) {
            return $next($request);
        }

        if ($this->guard->isBlocked($request)) {
            return $this->guard->response($request, 'ip_banned', 403);
        }

        if ($this->guard->shouldTrap($request)) {
            $this->guard->block($request, 'direct_sensitive_api_probe');

            return $this->guard->response($request, 'direct_sensitive_api_probe', 403);
        }

        return $next($request);
    }
}
