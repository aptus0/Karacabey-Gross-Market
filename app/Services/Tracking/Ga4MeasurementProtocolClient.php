<?php

namespace App\Services\Tracking;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GA4 Measurement Protocol (server-side events) client.
 *
 * @see https://developers.google.com/analytics/devguides/collection/protocol/ga4
 */
final class Ga4MeasurementProtocolClient
{
    private const ENDPOINT = 'https://www.google-analytics.com/mp/collect';

    public function __construct(
        private readonly ?string $measurementId,
        private readonly ?string $apiSecret,
    ) {
    }

    public function isConfigured(): bool
    {
        return ! empty($this->measurementId) && ! empty($this->apiSecret);
    }

    public function sendPurchase(Order $order, ?string $clientId = null): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        // GA4 client_id stabil olmalı — yoksa order id'den deterministik üret
        $clientId ??= sprintf('%d.%d', $order->user_id ?? 0, $order->id);

        $items = $order->items->map(fn ($item) => [
            'item_id' => (string) $item->product_id,
            'item_name' => $item->name,
            'price' => round($item->unit_price_cents / 100, 2),
            'quantity' => $item->quantity,
        ])->values()->all();

        $payload = [
            'client_id' => $clientId,
            'user_id' => $order->user_id ? (string) $order->user_id : null,
            'timestamp_micros' => ($order->paid_at ?? now())->timestamp * 1_000_000,
            'non_personalized_ads' => false,
            'events' => [[
                'name' => 'purchase',
                'params' => [
                    'transaction_id' => (string) $order->id,
                    'currency' => $order->currency ?? 'TRY',
                    'value' => round($order->total_cents / 100, 2),
                    'shipping' => round($order->shipping_cents / 100, 2),
                    'tax' => 0,
                    'items' => $items,
                ],
            ]],
        ];

        // null user_id'yi temizle
        $payload = array_filter($payload, fn ($v) => $v !== null);

        try {
            $response = Http::timeout(10)
                ->asJson()
                ->post(
                    self::ENDPOINT.'?'.http_build_query([
                        'measurement_id' => $this->measurementId,
                        'api_secret' => $this->apiSecret,
                    ]),
                    $payload,
                );

            if ($response->failed()) {
                Log::warning('GA4 MP failed', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('GA4 MP exception', ['order_id' => $order->id, 'message' => $e->getMessage()]);
            return false;
        }
    }
}
