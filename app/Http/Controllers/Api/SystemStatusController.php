<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MaintenanceModeService;
use App\Support\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemStatusController extends Controller
{
    public function __invoke(Request $request, TenantResolver $tenants, MaintenanceModeService $maintenance): JsonResponse
    {
        $tenant = $tenants->resolve($request);

        return response()
            ->json([
                'data' => [
                    'maintenance' => $maintenance->status($tenant),
                ],
            ])
            ->header('Cache-Control', 'no-store, no-cache, max-age=0, must-revalidate')
            ->header('X-KGM-System-Status', 'ok');
    }
}
