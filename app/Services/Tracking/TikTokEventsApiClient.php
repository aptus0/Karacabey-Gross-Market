<?php

namespace App\Services\Tracking;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * TikTok Events API client.
 *
 * @see https://business-api.tiktok.com/portal/docs?id=1771101303285761
 */
final class TikTokEventsApiClient
{
    private const ENDPOINT = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

    public function __construct(
        private readonly ?string $pixelId,
        private readonly ?string $accessToken,
    ) {
    }

    public function isConfigured(): bool
    {
        return ! empty($this->pixelId) && ! empty($this->accessToken);
    }

    public function sendPurchase(Order $order, ?string $clientIp = null, ?string $userAgent = null): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        $contents = $order->items->map(fn ($item) => [
            'content_id' => (string) $item->product_id,
            'content_name' => $item->name,
            'price' => round($item->unit_price_cents / 100, 2),
            'quantity' => $item->quantity,
        ])->values()->all();

        $payload = [
            'event_source' => 'web',
            'event_source_id' => $this->pixelId,
            'data' => [[
                'event' => 'CompletePayment',
                'event_time' => ($order->paid_at ?? now())->timestamp,
                'event_id' => 'order_'.$order->id,
                'user' => array_filter([
                    'email' => UserDataHasher::email($order->customer_email),
                    'phone' => UserDataHasher::phone($order->customer_phone),
                    'external_id' => hash('sha256', (string) $order->id),
                    'ip' => $clientIp,
                    'user_agent' => $userAgent,
                ]),
                'properties' => [
                    'currency' => $order->currency ?? 'TRY',
                    'value' => round($order->total_cents / 100, 2),
                    'contents' => $contents,
                    'order_id' => (string) $order->id,
                ],
            ]],
        ];

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Access-Token' => $this->accessToken,
                    'Content-Type' => 'application/json',
                ])
                ->post(self::ENDPOINT, $payload);

            $body = $response->json();
            if ($response->failed() || ($body['code'] ?? null) !== 0) {
                Log::warning('TikTok Events API failed', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'body' => $body,
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('TikTok Events API exception', ['order_id' => $order->id, 'message' => $e->getMessage()]);
            return false;
        }
    }
}
