<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CargoProviderSetting;
use App\Support\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CargoController extends Controller
{
    public function index(TenantResolver $tenants, Request $request): View
    {
        $tenant = $tenants->resolve($request);
        $definitions = CargoProviderSetting::definitions();

        // Mevcut ayarları yükle; yoksa boş model oluştur
        $settings = [];
        foreach ($definitions as $code => $def) {
            $settings[$code] = CargoProviderSetting::query()
                ->where('tenant_id', $tenant->id)
                ->where('code', $code)
                ->first() ?? new CargoProviderSetting([
                    'tenant_id'            => $tenant->id,
                    'code'                 => $code,
                    'name'                 => $def['name'],
                    'is_active'            => false,
                    'price_cents'          => 0,
                    'free_threshold_cents' => 0,
                    'estimated_days_min'   => 1,
                    'estimated_days_max'   => 3,
                ]);
        }

        return view('admin.cargo.index', compact('settings', 'definitions', 'tenant'));
    }

    public function update(Request $request, TenantResolver $tenants): RedirectResponse
    {
        $tenant = $tenants->resolve($request);
        $definitions = CargoProviderSetting::definitions();
        $providers = $request->input('providers', []);
        $disabled = [];

        foreach ($definitions as $code => $def) {
            $data = $providers[$code] ?? [];

            $credentials = [];
            $credFields = $data['credentials'] ?? [];
            foreach ($credFields as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $credentials[$key] = $value;
                }
            }

            $existing = CargoProviderSetting::query()
                ->where('tenant_id', $tenant->id)
                ->where('code', $code)
                ->first();

            $credentials = array_merge($existing?->credentials ?? [], $credentials);
            $configured = collect($def['required_credentials'] ?? [])
                ->every(fn (string $key): bool => trim((string) ($credentials[$key] ?? '')) !== '');
            $requestedActive = isset($data['is_active']);
            if ($requestedActive && ! $configured) {
                $disabled[] = $def['name'];
            }

            $priceCents = (int) round((float) str_replace(',', '.', $data['price'] ?? '0') * 100);
            $thresholdCents = (int) round((float) str_replace(',', '.', $data['free_threshold'] ?? '0') * 100);

            $updateData = [
                'name'                 => $def['name'],
                'is_active'            => $requestedActive && $configured,
                'price_cents'          => $priceCents,
                'free_threshold_cents' => $thresholdCents,
                'estimated_days_min'   => max(1, (int) ($data['days_min'] ?? 1)),
                'estimated_days_max'   => max(1, (int) ($data['days_max'] ?? 3)),
            ];

            // Kimlik bilgileri: sadece yeni değer geldiyse güncelle (boş alan mevcut değeri silmez)
            if (! empty($credentials)) {
                $updateData['credentials'] = $credentials;
            }

            if ($existing) {
                $existing->update($updateData);
            } else {
                $updateData['credentials'] = $credentials ?: null;
                CargoProviderSetting::create(array_merge($updateData, [
                    'tenant_id' => $tenant->id,
                    'code'      => $code,
                ]));
            }
        }

        $message = 'Kargo ayarları güncellendi.';
        if ($disabled !== []) {
            $message .= ' Kimlik bilgisi eksik olduğu için pasif bırakılanlar: '.implode(', ', $disabled).'.';
        }

        return redirect()->route('admin.cargo.index')->with('success', $message);
    }
}
