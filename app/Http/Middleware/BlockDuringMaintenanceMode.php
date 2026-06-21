<?php

namespace App\Http\Middleware;

use App\Services\MaintenanceModeService;
use App\Support\HttpStatusCatalog;
use App\Support\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockDuringMaintenanceMode
{
    public function __construct(
        private readonly TenantResolver $tenants,
        private readonly MaintenanceModeService $maintenance,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/v1*')) {
            return $next($request);
        }

        if ($request->is('api/v1/system/status')) {
            return $next($request);
        }

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $tenant = $this->tenants->resolve($request);
        $isCheckoutWrite = $request->is('api/v1/c')
            || $request->is('api/v1/cart*')
            || $request->is('api/v1/payments*');

        if ($this->maintenance->shouldBlockApiWrite($tenant) || ($isCheckoutWrite && $this->maintenance->shouldBlockCheckout($tenant))) {
            $meta = HttpStatusCatalog::find(503);
            $status = $this->maintenance->status($tenant);

            return response()
                ->json([
                    'message' => $status['message'],
                    'code' => 503,
                    'status' => $meta['text'],
                    'category' => $meta['category'],
                    'maintenance' => $status,
                    'request_uid' => $request->attributes->get('kgm_request_uid'),
                ], 503)
                ->header('Retry-After', '300')
                ->header('Cache-Control', 'no-store, no-cache, max-age=0, must-revalidate');
        }

        return $next($request);
    }
}
