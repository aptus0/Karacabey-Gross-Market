<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Http\Request;

class TenantResolver
{
    public function resolve(Request $request): Tenant
    {
        $host = $request->getHost();
        $slug = $request->header('X-Tenant');

        $query = Tenant::query()->where('is_active', true);

        $tenant = $slug
            ? (clone $query)->where('slug', $slug)->first()
            : null;

        $tenant ??= (clone $query)->where('domain', $host)->first();
        $tenant ??= (clone $query)->get()->first(function (Tenant $tenant) use ($host): bool {
            $settings = is_array($tenant->settings) ? $tenant->settings : [];
            $storefrontDomains = $settings['storefront_domains'] ?? [];
            $adminDomains = $settings['admin_domains'] ?? [];
            $apiDomains = $settings['api_domains'] ?? [];
            $allDomains = array_filter(array_map('strval', array_merge((array) $storefrontDomains, (array) $adminDomains, (array) $apiDomains)));

            return in_array($host, $allDomains, true);
        });
        $tenant ??= (clone $query)->where('slug', 'karacabey-gross-market')->first();
        $tenant ??= (clone $query)->where('domain', parse_url((string) config('commerce.domains.storefront'), PHP_URL_HOST))->first();
        $tenant ??= (clone $query)->where('domain', parse_url((string) env('COSMETICS_STOREFRONT_URL', ''), PHP_URL_HOST))->first();

        if ($tenant) {
            return $tenant;
        }

        return Tenant::query()->firstOrCreate([
            'slug' => 'karacabey-gross-market',
        ], [
            'name' => 'Karacabey Gross Market',
            'domain' => (string) config('commerce.primary_domain', 'karacabeygrossmarket.com'),
            'is_active' => true,
            'settings' => [
                'storefront_domain' => parse_url((string) config('commerce.domains.storefront'), PHP_URL_HOST),
                'admin_domain' => parse_url((string) config('commerce.domains.admin'), PHP_URL_HOST),
                'api_domain' => parse_url((string) config('commerce.domains.api'), PHP_URL_HOST),
                'cdn_domain' => parse_url((string) config('commerce.domains.cdn'), PHP_URL_HOST),
            ],
        ]);
    }
}
