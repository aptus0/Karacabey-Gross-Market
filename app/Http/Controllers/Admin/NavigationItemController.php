<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NavigationItem;
use App\Support\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NavigationItemController extends Controller
{
    public function index(): View
    {
        return view('admin.navigation.index', [
            'items' => NavigationItem::query()
                ->orderBy('placement')
                ->orderBy('sort_order')
                ->orderBy('label')
                ->paginate(40),
            'placements' => NavigationItem::PLACEMENTS,
            'icons' => NavigationItem::ICONS,
        ]);
    }

    public function store(Request $request, TenantResolver $tenants): RedirectResponse
    {
        $tenant = $tenants->resolve($request);
        $validated = $this->validated($request);

        NavigationItem::query()->create($validated + [
            'tenant_id' => $tenant->id,
        ]);
        $this->forgetNavigationCache($tenant->id);

        return redirect()->route('admin.navigation.index')->with('status', 'Menu ogesi eklendi.');
    }

    public function update(Request $request, NavigationItem $navigationItem): RedirectResponse
    {
        $navigationItem->update($this->validated($request));
        $this->forgetNavigationCache($navigationItem->tenant_id);

        return redirect()->route('admin.navigation.index')->with('status', 'Menu ogesi guncellendi.');
    }

    public function destroy(NavigationItem $navigationItem): RedirectResponse
    {
        $tenantId = $navigationItem->tenant_id;
        $navigationItem->delete();
        $this->forgetNavigationCache($tenantId);

        return redirect()->route('admin.navigation.index')->with('status', 'Menu ogesi silindi.');
    }

    private function validated(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'placement' => ['required', 'string', Rule::in(array_keys(NavigationItem::PLACEMENTS))],
            'label' => ['required', 'string', 'max:80'],
            'url' => ['required', 'string', 'max:500'],
            'icon' => ['nullable', 'string', Rule::in(array_keys(NavigationItem::ICONS))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validator->after(function ($validator) use ($request): void {
            if (! $this->isSafeUrl((string) $request->input('url'))) {
                $validator->errors()->add('url', 'Yalnizca site ici / ile baslayan veya http(s) URL girilebilir.');
            }
        });

        $validated = $validator->validate();

        return [
            'placement' => $validated['placement'],
            'label' => trim($validated['label']),
            'url' => trim($validated['url']),
            'icon' => $validated['icon'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function isSafeUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return false;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return true;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true);
    }

    private function forgetNavigationCache(int $tenantId): void
    {
        Cache::forget("tenant:{$tenantId}:content:navigation:v1");
    }
}
