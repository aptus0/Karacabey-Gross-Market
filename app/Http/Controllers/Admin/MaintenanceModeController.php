<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MaintenanceModeService;
use App\Support\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MaintenanceModeController extends Controller
{
    public function edit(Request $request, TenantResolver $tenants, MaintenanceModeService $maintenance): View
    {
        $tenant = $tenants->resolve($request);

        return view('admin.maintenance.edit', [
            'tenant' => $tenant,
            'status' => $maintenance->status($tenant),
        ]);
    }

    public function update(Request $request, TenantResolver $tenants, MaintenanceModeService $maintenance): RedirectResponse
    {
        $tenant = $tenants->resolve($request);
        $validated = $request->validate([
            'enabled' => ['nullable', 'boolean'],
            'storefront' => ['nullable', 'boolean'],
            'checkout' => ['nullable', 'boolean'],
            'api_writes' => ['nullable', 'boolean'],
            'mobile' => ['nullable', 'boolean'],
            'title' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ], [
            'title.required' => 'Bakım başlığı gerekli.',
            'message.required' => 'Müşteriye gösterilecek bakım mesajı gerekli.',
            'ends_at.after' => 'Bitiş zamanı başlangıçtan sonra olmalı.',
        ]);

        foreach (['enabled', 'storefront', 'checkout', 'api_writes', 'mobile'] as $key) {
            $validated[$key] = (bool) ($validated[$key] ?? false);
        }

        $maintenance->update($tenant, $validated, $request->user()?->email ?? $request->user()?->name);

        return redirect()
            ->route('admin.maintenance.edit')
            ->with('status', $validated['enabled'] ? 'Bakım modu ayarlandı.' : 'Bakım modu kapatıldı.');
    }
}
