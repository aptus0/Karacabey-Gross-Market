<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tenant_id
 * @property ?string $announcement_text
 * @property bool $tracking_enabled
 * @property bool $server_side_events_enabled
 * @property ?array $extra
 * @property ?string $google_analytics_id
 * @property ?string $google_ads_id
 * @property ?string $google_ads_conversion_label
 * @property ?string $google_site_verification
 * @property ?string $google_gtm_id
 * @property ?string $google_merchant_id
 * @property ?string $google_merchant_service_account_json
 * @property ?string $google_maps_api_key
 * @property ?string $google_admob_app_id
 * @property ?string $google_admob_ios_banner_unit_id
 * @property ?string $google_admob_ios_interstitial_unit_id
 * @property ?string $google_admob_android_banner_unit_id
 * @property ?string $google_admob_android_interstitial_unit_id
 * @property ?string $ga4_api_secret
 * @property ?string $meta_pixel_id
 * @property ?string $meta_capi_access_token
 * @property ?string $meta_capi_test_event_code
 * @property ?string $meta_catalog_id
 * @property ?string $meta_business_id
 * @property ?string $meta_dataset_id
 * @property ?string $yandex_metrica_id
 * @property ?string $yandex_verification
 * @property ?string $yandex_direct_counter_id
 * @property ?string $microsoft_uet_tag_id
 * @property ?string $microsoft_clarity_id
 * @property ?string $bing_verification
 * @property ?string $tiktok_pixel_id
 * @property ?string $tiktok_capi_access_token
 */
class MarketingSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',

        // General
        'announcement_text',
        'tracking_enabled',
        'server_side_events_enabled',
        'extra',

        // Google
        'google_analytics_id',
        'google_ads_id',
        'google_ads_conversion_label',
        'google_site_verification',
        'google_gtm_id',
        'google_merchant_id',
        'google_merchant_service_account_json',
        'google_maps_api_key',
        'google_admob_app_id',
        'google_admob_ios_banner_unit_id',
        'google_admob_ios_interstitial_unit_id',
        'google_admob_android_banner_unit_id',
        'google_admob_android_interstitial_unit_id',
        'ga4_api_secret',

        // Meta
        'meta_pixel_id',
        'meta_capi_access_token',
        'meta_capi_test_event_code',
        'meta_catalog_id',
        'meta_business_id',
        'meta_dataset_id',

        // Yandex
        'yandex_metrica_id',
        'yandex_verification',
        'yandex_direct_counter_id',

        // Microsoft / Bing
        'microsoft_uet_tag_id',
        'microsoft_clarity_id',
        'bing_verification',

        // TikTok
        'tiktok_pixel_id',
        'tiktok_capi_access_token',
    ];

    protected function casts(): array
    {
        return [
            'extra' => 'array',
            'tracking_enabled' => 'boolean',
            'server_side_events_enabled' => 'boolean',

            // Sırlar: DB'de şifreli tutulur, model üzerinden okurken otomatik decrypt
            'google_merchant_service_account_json' => 'encrypted',
            'ga4_api_secret' => 'encrypted',
            'meta_capi_access_token' => 'encrypted',
            'tiktok_capi_access_token' => 'encrypted',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Frontend'e (Next.js) gönderilebilecek public ID/key seti.
     * Sırlar (CAPI token, service account JSON, API secret) buraya KOYULMAZ.
     */
    public function publicConfig(): array
    {
        if (! $this->tracking_enabled) {
            return [
                'tracking_enabled' => false,
                'announcement_text' => $this->announcement_text,
            ];
        }

        return [
            'tracking_enabled' => true,
            'announcement_text' => $this->announcement_text,

            'google' => array_filter([
                'analytics_id' => $this->google_analytics_id,
                'ads_id' => $this->google_ads_id,
                'ads_conversion_label' => $this->google_ads_conversion_label,
                'site_verification' => $this->google_site_verification,
                'gtm_id' => $this->google_gtm_id,
                'merchant_id' => $this->google_merchant_id,
                'maps_api_key' => $this->google_maps_api_key,
                'admob_app_id' => $this->google_admob_app_id,
                'admob_ios_banner_unit_id' => $this->google_admob_ios_banner_unit_id,
                'admob_ios_interstitial_unit_id' => $this->google_admob_ios_interstitial_unit_id,
                'admob_android_banner_unit_id' => $this->google_admob_android_banner_unit_id,
                'admob_android_interstitial_unit_id' => $this->google_admob_android_interstitial_unit_id,
            ]),

            'meta' => array_filter([
                'pixel_id' => $this->meta_pixel_id,
                'catalog_id' => $this->meta_catalog_id,
                'business_id' => $this->meta_business_id,
            ]),

            'yandex' => array_filter([
                'metrica_id' => $this->yandex_metrica_id,
                'verification' => $this->yandex_verification,
                'direct_counter_id' => $this->yandex_direct_counter_id,
            ]),

            'microsoft' => array_filter([
                'uet_tag_id' => $this->microsoft_uet_tag_id,
                'clarity_id' => $this->microsoft_clarity_id,
                'bing_verification' => $this->bing_verification,
            ]),

            'tiktok' => array_filter([
                'pixel_id' => $this->tiktok_pixel_id,
            ]),
        ];
    }

    /**
     * Server-side event dispatch için gerekli (sırlar içerir) — yalnız backend kullanımı.
     */
    public function serverConfig(): array
    {
        return [
            'enabled' => (bool) $this->server_side_events_enabled,
            'meta' => [
                'pixel_id' => $this->meta_pixel_id,
                'access_token' => $this->meta_capi_access_token,
                'test_event_code' => $this->meta_capi_test_event_code,
                'dataset_id' => $this->meta_dataset_id,
            ],
            'ga4' => [
                'measurement_id' => $this->google_analytics_id,
                'api_secret' => $this->ga4_api_secret,
            ],
            'tiktok' => [
                'pixel_id' => $this->tiktok_pixel_id,
                'access_token' => $this->tiktok_capi_access_token,
            ],
            'merchant' => [
                'id' => $this->google_merchant_id,
                'service_account_json' => $this->google_merchant_service_account_json,
            ],
        ];
    }
}
