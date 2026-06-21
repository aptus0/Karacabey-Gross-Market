<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CargoProviderSetting;
use App\Support\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CargoOptionsController extends Controller
{
    public function index(Request $request, TenantResolver $tenants): JsonResponse
    {
        $tenant = $tenants->resolve($request);
        $orderCents = (int) $request->query('order_cents', 0);

        $options = CargoProviderSetting::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('price_cents')
            ->get()
            ->filter(fn (CargoProviderSetting $setting): bool => $setting->isConfigured())
            ->map(fn (CargoProviderSetting $s) => [
                'code'            => $s->code,
                'name'            => $s->name,
                'logo_url'        => $s->logoUrl(),
                'price_cents'     => $s->effectivePriceCents($orderCents),
                'original_price_cents' => $s->price_cents,
                'is_free'         => $s->isFreeFor($orderCents),
                'free_threshold_cents' => $s->free_threshold_cents,
                'estimated_days'  => [
                    'min' => $s->estimated_days_min,
                    'max' => $s->estimated_days_max,
                ],
            ]);

        return response()->json(['data' => $options]);
    }
}
