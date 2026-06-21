<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class MarketingSettingController extends Controller
{
    public function edit(Request $request, TenantResolver $tenants): View
    {
        $tenant = $tenants->resolve($request);

        return view('admin.marketing.edit', [
            'setting' => $tenant->marketingSetting()->firstOrNew(),
        ]);
    }

    public function update(Request $request, TenantResolver $tenants): RedirectResponse
    {
        $tenant = $tenants->resolve($request);

        $validated = $request->validate([
            // General
            'announcement_text' => ['nullable', 'string', 'max:255'],
            'tracking_enabled' => ['nullable', 'boolean'],
            'server_side_events_enabled' => ['nullable', 'boolean'],

            // Google
            'google_analytics_id' => ['nullable', 'string', 'max:60', 'regex:/^G-[A-Z0-9]+$/i'],
            'google_ads_id' => ['nullable', 'string', 'max:60', 'regex:/^AW-[0-9]+$/i'],
            'google_ads_conversion_label' => ['nullable', 'string', 'max:120'],
            'google_site_verification' => ['nullable', 'string', 'max:255'],
            'google_gtm_id' => ['nullable', 'string', 'max:60', 'regex:/^GTM-[A-Z0-9]+$/i'],
            'google_merchant_id' => ['nullable', 'string', 'max:60', 'regex:/^[0-9]+$/'],
            'google_merchant_service_account_json' => ['nullable', 'string', 'max:8192'],
            'google_maps_api_key' => ['nullable', 'string', 'max:255'],
            'google_admob_app_id' => ['nullable', 'string', 'max:120', 'regex:/^ca-app-pub-[0-9]{16}~[0-9]{10}$/'],
            'google_admob_ios_banner_unit_id' => ['nullable', 'string', 'max:120', 'regex:/^ca-app-pub-[0-9]{16}\\/[0-9]{10}$/'],
            'google_admob_ios_interstitial_unit_id' => ['nullable', 'string', 'max:120', 'regex:/^ca-app-pub-[0-9]{16}\\/[0-9]{10}$/'],
            'google_admob_android_banner_unit_id' => ['nullable', 'string', 'max:120', 'regex:/^ca-app-pub-[0-9]{16}\\/[0-9]{10}$/'],
            'google_admob_android_interstitial_unit_id' => ['nullable', 'string', 'max:120', 'regex:/^ca-app-pub-[0-9]{16}\\/[0-9]{10}$/'],
            'ga4_api_secret' => ['nullable', 'string', 'max:255'],

            // Meta
            'meta_pixel_id' => ['nullable', 'string', 'max:60', 'regex:/^[0-9]+$/'],
            'meta_capi_access_token' => ['nullable', 'string', 'max:512'],
            'meta_capi_test_event_code' => ['nullable', 'string', 'max:60'],
            'meta_catalog_id' => ['nullable', 'string', 'max:60', 'regex:/^[0-9]+$/'],
            'meta_business_id' => ['nullable', 'string', 'max:60', 'regex:/^[0-9]+$/'],
            'meta_dataset_id' => ['nullable', 'string', 'max:60', 'regex:/^[0-9]+$/'],

            // Yandex
            'yandex_metrica_id' => ['nullable', 'string', 'max:32', 'regex:/^[0-9]+$/'],
            'yandex_verification' => ['nullable', 'string', 'max:120'],
            'yandex_direct_counter_id' => ['nullable', 'string', 'max:32', 'regex:/^[0-9]+$/'],

            // Microsoft / Bing
            'microsoft_uet_tag_id' => ['nullable', 'string', 'max:32', 'regex:/^[0-9]+$/'],
            'microsoft_clarity_id' => ['nullable', 'string', 'max:32', 'regex:/^[a-z0-9]+$/i'],
            'bing_verification' => ['nullable', 'string', 'max:120'],

            // TikTok
            'tiktok_pixel_id' => ['nullable', 'string', 'max:60', 'regex:/^[A-Z0-9]+$/i'],
            'tiktok_capi_access_token' => ['nullable', 'string', 'max:512'],
        ], $this->validationMessages());

        // Checkbox alanlarını normalize et (form göndermez ise false)
        $validated['tracking_enabled'] = (bool) ($validated['tracking_enabled'] ?? false);
        $validated['server_side_events_enabled'] = (bool) ($validated['server_side_events_enabled'] ?? false);

        // Service account JSON: doğru format mı? (boşsa boş bırak)
        if (! empty($validated['google_merchant_service_account_json'])) {
            $decoded = json_decode($validated['google_merchant_service_account_json'], true);
            if (! is_array($decoded) || ! isset($decoded['client_email'], $decoded['private_key'])) {
                return back()
                    ->withInput()
                    ->withErrors(['google_merchant_service_account_json' => 'Geçerli bir Google service-account JSON\'u değil.']);
            }
        }

        // Sırların boş bırakılması mevcut değeri silmesin: kullanıcı dokunmadıysa eskiyi koru.
        // Form'da boş string gelirse "değiştirmedi" sayalım — sadece null olmayan/non-empty olanları geçir.
        $existing = $tenant->marketingSetting()->firstOrNew();
        foreach (['google_merchant_service_account_json', 'ga4_api_secret', 'meta_capi_access_token', 'tiktok_capi_access_token'] as $secret) {
            if (! isset($validated[$secret]) || $validated[$secret] === '' || $validated[$secret] === null) {
                if (! empty($existing->{$secret})) {
                    // Mevcut değeri koru: array'den çıkararak güncellenmesini engelle
                    unset($validated[$secret]);
                }
            }
        }

        $tenant->marketingSetting()->updateOrCreate([], $validated);

        // Public/server cache'leri temizle
        Cache::forget("tenant:{$tenant->id}:content:marketing:v2");
        Cache::forget("tenant:{$tenant->id}:tracking:public:v1");

        return redirect()->route('admin.marketing.edit')->with('status', 'Pazarlama ayarları güncellendi.');
    }

    private function validationMessages(): array
    {
        return [
            'google_analytics_id.regex' => 'GA4 Measurement ID formatı: G-XXXXXXXXXX',
            'google_ads_id.regex' => 'Google Ads ID formatı: AW-XXXXXXXXXX',
            'google_gtm_id.regex' => 'Google Tag Manager ID formatı: GTM-XXXXXXX',
            'google_merchant_id.regex' => 'Merchant Center ID yalnızca rakamlardan oluşur.',
            'google_admob_app_id.regex' => 'AdMob App ID formatı: ca-app-pub-XXXXXXXXXXXXXXXX~XXXXXXXXXX',
            'google_admob_ios_banner_unit_id.regex' => 'AdMob iOS Banner Unit ID formatı: ca-app-pub-XXXXXXXXXXXXXXXX/XXXXXXXXXX',
            'google_admob_ios_interstitial_unit_id.regex' => 'AdMob iOS Interstitial Unit ID formatı: ca-app-pub-XXXXXXXXXXXXXXXX/XXXXXXXXXX',
            'google_admob_android_banner_unit_id.regex' => 'AdMob Android Banner Unit ID formatı: ca-app-pub-XXXXXXXXXXXXXXXX/XXXXXXXXXX',
            'google_admob_android_interstitial_unit_id.regex' => 'AdMob Android Interstitial Unit ID formatı: ca-app-pub-XXXXXXXXXXXXXXXX/XXXXXXXXXX',
            'meta_pixel_id.regex' => 'Meta Pixel ID yalnızca rakamlardan oluşur.',
            'meta_catalog_id.regex' => 'Meta Catalog ID yalnızca rakamlardan oluşur.',
            'yandex_metrica_id.regex' => 'Yandex Metrica counter ID yalnızca rakamlardan oluşur.',
            'microsoft_uet_tag_id.regex' => 'Microsoft UET Tag ID yalnızca rakamlardan oluşur.',
        ];
    }
}
