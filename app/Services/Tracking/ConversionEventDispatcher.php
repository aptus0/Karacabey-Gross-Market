<?php

namespace App\Services\Tracking;

use App\Models\MarketingSetting;
use App\Models\Order;

/**
 * Sipariş "Paid" durumuna geçtiğinde tüm yapılandırılmış kanallara
 * sunucu-taraflı Purchase event'lerini gönderir.
 */
final class ConversionEventDispatcher
{
    /**
     * @return array<string, bool> kanal => başarı durumu
     */
    public function dispatchPurchase(Order $order, ?string $clientIp = null, ?string $userAgent = null, ?string $fbp = null, ?string $fbc = null): array
    {
        $setting = $order->tenant->marketingSetting()->first();

        if (! $setting || ! $setting->server_side_events_enabled) {
            return [];
        }

        $results = [];

        // Meta CAPI
        $meta = new MetaConversionsApiClient(
            $setting->meta_pixel_id,
            $setting->meta_capi_access_token,
            $setting->meta_capi_test_event_code,
            $setting->meta_dataset_id,
        );
        if ($meta->isConfigured()) {
            $results['meta'] = $meta->sendPurchase($order, $clientIp, $userAgent, $fbp, $fbc);
        }

        // GA4 Measurement Protocol
        $ga4 = new Ga4MeasurementProtocolClient(
            $setting->google_analytics_id,
            $setting->ga4_api_secret,
        );
        if ($ga4->isConfigured()) {
            $results['ga4'] = $ga4->sendPurchase($order);
        }

        // TikTok Events API
        $tiktok = new TikTokEventsApiClient(
            $setting->tiktok_pixel_id,
            $setting->tiktok_capi_access_token,
        );
        if ($tiktok->isConfigured()) {
            $results['tiktok'] = $tiktok->sendPurchase($order, $clientIp, $userAgent);
        }

        return $results;
    }

    /**
     * Belirli bir tenant'ın hangi kanallar için CAPI yapılandırılmış olduğunu döndürür.
     * Admin paneli / health check için.
     */
    public function configuredChannels(MarketingSetting $setting): array
    {
        return [
            'meta' => ! empty($setting->meta_pixel_id) && ! empty($setting->meta_capi_access_token),
            'ga4' => ! empty($setting->google_analytics_id) && ! empty($setting->ga4_api_secret),
            'tiktok' => ! empty($setting->tiktok_pixel_id) && ! empty($setting->tiktok_capi_access_token),
        ];
    }
}
