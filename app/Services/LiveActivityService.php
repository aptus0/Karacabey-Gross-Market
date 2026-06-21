<?php

namespace App\Services;

use App\Models\LiveActivityToken;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;

class LiveActivityService
{
    public function syncOrder(Order $order, string $status): void
    {
        $order->loadMissing('user');
        if (! $order->user_id) {
            return;
        }

        $isFinal = in_array($status, ['delivered', 'cancelled', 'failed', 'refunded', 'returned'], true);
        $activityTokens = LiveActivityToken::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('user_id', $order->user_id)
            ->where('order_id', $order->id)
            ->where('kind', 'activity')
            ->where('is_active', true)
            ->get();

        foreach ($activityTokens as $token) {
            $this->send($token, $order, $isFinal ? 'end' : 'update', $status);
        }

        if ($activityTokens->isEmpty() && ! $isFinal) {
            LiveActivityToken::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('user_id', $order->user_id)
                ->where('kind', 'push_to_start')
                ->where('is_active', true)
                ->get()
                ->each(fn (LiveActivityToken $token) => $this->send($token, $order, 'start', $status));
        }
    }

    private function send(LiveActivityToken $token, Order $order, string $event, string $status): void
    {
        $now = now()->timestamp;
        $contentState = [
            'status' => $status,
            'statusLabel' => $this->statusLabel($status),
            'progress' => $this->progress($status),
            'updatedAt' => $now,
        ];
        $aps = [
            'timestamp' => $now,
            'event' => $event,
            'content-state' => $contentState,
            'alert' => [
                'title' => 'Sipariş Güncellemesi',
                'body' => '#'.($order->merchant_oid ?: $order->id).' '.$this->statusLabel($status),
            ],
        ];

        if ($event === 'start') {
            $aps['attributes-type'] = 'OrderActivityAttributes';
            $aps['attributes'] = [
                'orderId' => (string) $order->id,
                'orderNumber' => (string) ($order->merchant_oid ?: $order->id),
                'deepLink' => 'kgm://orders/'.$order->id,
            ];
        }
        if ($event === 'end') {
            $aps['dismissal-date'] = now()->addMinutes(30)->timestamp;
        }

        try {
            $apns = ApnsConfig::fromArray([
                'headers' => ['apns-priority' => '10'],
                'payload' => ['aps' => $aps],
                'live_activity_token' => $token->token,
            ]);
            $message = CloudMessage::new()
                ->withToken($token->fcm_token)
                ->withApnsConfig($apns);
            app('firebase.messaging')->send($message);

            $token->forceFill([
                'order_id' => $order->id,
                'last_used_at' => now(),
                'is_active' => $event !== 'end' && $event !== 'start',
            ])->save();
        } catch (NotFound $e) {
            $token->forceFill(['is_active' => false])->save();
        } catch (\Throwable $e) {
            Log::error('Live Activity push failed', [
                'order_id' => $order->id,
                'token_id' => $token->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function progress(string $status): float
    {
        return match ($status) {
            'draft', 'awaiting_payment', 'reviewing', 'paid' => 0.2,
            'approved' => 0.35,
            'preparing', 'processing' => 0.5,
            'shipped', 'on_the_way' => 0.8,
            'delivered' => 1.0,
            default => 0.0,
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'reviewing' => 'Kontrol Ediliyor',
            'paid' => 'Ödeme Alındı',
            'approved' => 'Onaylandı',
            'preparing', 'processing' => 'Hazırlanıyor',
            'shipped', 'on_the_way' => 'Yola Çıktı',
            'delivered' => 'Teslim Edildi',
            'cancelled' => 'İptal Edildi',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
