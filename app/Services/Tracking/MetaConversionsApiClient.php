<?php

namespace App\Services\Tracking;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Meta (Facebook/Instagram) Conversions API client.
 *
 * @see https://developers.facebook.com/docs/marketing-api/conversions-api
 */
final class MetaConversionsApiClient
{
    private const API_VERSION = 'v19.0';
    private const ENDPOINT = 'https://graph.facebook.com';

    public function __construct(
        private readonly ?string $pixelId,
        private readonly ?string $accessToken,
        private readonly ?string $testEventCode = null,
        private readonly ?string $datasetId = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return ! empty($this->pixelId) && ! empty($this->accessToken);
    }

    public function sendPurchase(Order $order, ?string $clientIp = null, ?string $userAgent = null, ?string $fbp = null, ?string $fbc = null): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $endpointId = $this->datasetId ?: $this->pixelId;
        $url = sprintf('%s/%s/%s/events', self::ENDPOINT, self::API_VERSION, $endpointId);

        $userData = array_filter([
            'em' => [UserDataHasher::email($order->customer_email)],
            'ph' => [UserDataHasher::phone($order->customer_phone)],
            'fn' => [UserDataHasher::name(explode(' ', (string) $order->customer_name)[0] ?? null)],
            'ct' => [UserDataHasher::city($order->shipping_city)],
            'country' => [UserDataHasher::country('tr')],
            'client_ip_address' => $clientIp,
            'client_user_agent' => $userAgent,
            'fbp' => $fbp,
            'fbc' => $fbc,
            'external_id' => [hash('sha256', (string) $order->id)],
        ], fn ($v) => $v !== null && $v !== [null]);

        $contents = $order->items->map(fn ($item) => [
            'id' => (string) $item->product_id,
            'quantity' => $item->quantity,
            'item_price' => round($item->unit_price_cents / 100, 2),
        ])->values()->all();

        $payload = [
            'data' => [[
                'event_name' => 'Purchase',
                'event_time' => ($order->paid_at ?? $order->updated_at)->timestamp,
                'event_id' => 'order_'.$order->id,
                'event_source_url' => config('app.url'),
                'action_source' => 'website',
                'user_data' => $userData,
                'custom_data' => [
                    'currency' => $order->currency ?? 'TRY',
                    'value' => round($order->total_cents / 100, 2),
                    'order_id' => (string) $order->id,
                    'contents' => $contents,
                    'content_type' => 'product',
                    'num_items' => $order->items->sum('quantity'),
                ],
            ]],
            'access_token' => $this->accessToken,
        ];

        if ($this->testEventCode) {
            $payload['test_event_code'] = $this->testEventCode;
        }

        try {
            $response = Http::timeout(10)->asJson()->post($url, $payload);

            if ($response->failed()) {
                Log::warning('Meta CAPI failed', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Meta CAPI exception', ['order_id' => $order->id, 'message' => $e->getMessage()]);
            return false;
        }
    }
}
