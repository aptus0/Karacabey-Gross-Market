<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;

class PushNotificationService
{
    public function sendToUser(
        User $user,
        string $title,
        string $body,
        array $data = [],
        ?int $tenantId = null,
    ): Notification
    {
        return $this->sendToUserWithReport($user, $title, $body, $data, $tenantId)['notification'];
    }

    /**
     * @return array{notification: Notification, push_sent: int, push_failed: int}
     */
    public function sendToUserWithReport(
        User $user,
        string $title,
        string $body,
        array $data = [],
        ?int $tenantId = null,
    ): array {
        $payload = $this->normalizePayload($data);
        if (empty($payload['image_url'])) {
            $payload['image_url'] = $this->defaultNotificationImage((string) ($payload['type'] ?? 'general'));
        }

        $notification = Notification::create([
            'user_id' => $user->id,
            'tenant_id' => $tenantId ?? $this->resolveTenantId($payload),
            'type' => $payload['type'] ?? 'general',
            'title' => $title,
            'body' => $body,
            'data' => $payload,
            'sent_at' => now(),
        ]);
        $payload['notification_id'] = (string) $notification->id;

        $tokens = DeviceToken::where('user_id', $user->id)
            ->where('is_active', true)
            ->get();

        $pushSent = 0;
        $pushFailed = 0;
        foreach ($tokens as $token) {
            if ($this->sendFirebaseNotification($token, $title, $body, $payload, $user)) {
                $pushSent++;
            } else {
                $pushFailed++;
            }
        }

        return [
            'notification' => $notification,
            'push_sent' => $pushSent,
            'push_failed' => $pushFailed,
        ];
    }

    public function sendOrderStatusUpdate(object $order, string $status): void
    {
        if (! $order->user) {
            return;
        }

        $statusLabels = [
            'awaiting_payment' => 'Beklemede',
            'reviewing' => 'Kontrol Ediliyor',
            'paid' => 'Ödendi',
            'approved' => 'Onaylandı',
            'preparing' => 'Hazırlanıyor',
            'pending' => 'Beklemede',
            'processing' => 'İşleniyor',
            'shipped' => 'Yola Çıktı',
            'delivered' => 'Teslim Edildi',
            'cancelled' => 'İptal Edildi',
        ];

        $orderNumber = (string) ($order->merchant_oid ?? $order->id);
        $label = $statusLabels[$status] ?? $status;
        $title = match ($status) {
            'approved' => 'Siparişiniz Onaylandı',
            'preparing' => 'Siparişiniz Hazırlanıyor',
            'shipped' => 'Siparişiniz Yola Çıktı',
            'delivered' => 'Siparişiniz Teslim Edildi',
            default => 'Siparişiniz ' . $label,
        };
        $body = "#{$orderNumber} numaralı siparişinizin durumu “{$label}” olarak güncellendi.";

        $this->sendToUser($order->user, $title, $body, [
            'type' => 'order_update',
            'order_id' => (string)$order->id,
            'order_number' => $orderNumber,
            'status' => $status,
            'deep_link' => 'kgm://orders/'.$order->id,
            'action_url' => 'kgm://orders/'.$order->id,
            'image_url' => $this->defaultNotificationImage('order_update'),
            'sound' => 'kgm_notification.caf',
        ], $order->tenant_id ?? null);

        app(LiveActivityService::class)->syncOrder($order, $status);
    }

    public function sendCargoStatusUpdate(object $shipment, string $status): void
    {
        $order = $shipment->order;
        if (! $order->user) {
            return;
        }

        $title = 'Kargo Güncelleme';
        $body = "Siparişinizin kargosunda yeni bir güncelleme var: " . $status;

        $this->sendToUser($order->user, $title, $body, [
            'type' => 'cargo_update',
            'order_id' => (string)$order->id,
            'shipment_id' => (string)$shipment->id,
            'status' => $status,
            'deep_link' => 'kgm://orders/'.$order->id,
            'action_url' => 'kgm://orders/'.$order->id,
            'image_url' => $this->defaultNotificationImage('cargo_update'),
            'sound' => 'kgm_notification.caf',
        ]);

        $orderStatus = $order->status instanceof \BackedEnum ? $order->status->value : (string) $order->status;
        app(LiveActivityService::class)->syncOrder($order, $orderStatus);
    }

    public function sendPromotionalNotification(string $title, string $body, array $recipientIds = [], array $data = []): void
    {
        $query = DeviceToken::where('is_active', true);

        if (! empty($recipientIds)) {
            $query->whereIn('user_id', $recipientIds);
        }

        $tokens = $query->get();

        foreach ($tokens as $token) {
            $this->sendFirebaseNotification($token, $title, $body, $data, $token->user);
        }
    }

    private function normalizePayload(array $data): array
    {
        $payload = $data;

        if (! isset($payload['payload']) || ! is_array($payload['payload'])) {
            $payload['payload'] = [];
        }

        return $payload;
    }


    private function defaultNotificationImage(string $type): string
    {
        $file = in_array($type, ['order_update', 'cargo_update', 'payment_update'], true)
            ? 'order-status.svg'
            : 'system.svg';

        return url('/assets/notifications/' . $file);
    }

    private function resolveTenantId(array $data): int
    {
        if (isset($data['tenant_id']) && is_numeric($data['tenant_id'])) {
            return (int) $data['tenant_id'];
        }

        return (int) (Tenant::query()->where('slug', 'karacabey-gross-market')->value('id')
            ?? Tenant::query()->value('id')
            ?? 1);
    }

    private function sendFirebaseNotification(
        DeviceToken $deviceToken,
        string $title,
        string $body,
        array $data,
        ?User $user = null,
    ): bool
    {
        try {
            $pushData = $this->sanitizePushData($data);
            $imageUrl = trim((string) ($data['image_url'] ?? ''));
            $threadId = trim((string) ($data['type'] ?? 'general')) ?: 'general';
            $badge = $user
                ? Notification::query()->where('user_id', $user->id)->whereNull('read_at')->count()
                : 1;

            $messaging = app('firebase.messaging');
            $apns = ApnsConfig::fromArray([
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'kgm_notification.caf',
                        'badge' => $badge,
                        'category' => 'KGM_RICH_NOTIFICATION',
                        'thread-id' => $threadId,
                        'mutable-content' => $imageUrl !== '' ? 1 : 0,
                    ],
                ],
                'fcm_options' => $imageUrl !== '' ? ['image' => $imageUrl] : [],
            ]);

            $message = CloudMessage::new()
                ->withToken($deviceToken->token)
                ->withNotification(FirebaseNotification::create($title, $body))
                ->withData($pushData)
                ->withApnsConfig($apns);

            $messaging->send($message);
            return true;
        } catch (NotFound $e) {
            $deviceToken->forceFill(['is_active' => false])->save();
            Log::notice('Firebase device token deactivated', [
                'token_id' => $deviceToken->id,
                'error' => $e->getMessage(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Firebase notification failed', [
                'token' => substr($deviceToken->token, 0, 10) . '...',
                'error' => $e->getMessage(),
            ]);
        }

        return false;
    }

    private function sanitizePushData(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $sanitized[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                continue;
            }

            if (is_bool($value)) {
                $sanitized[$key] = $value ? '1' : '0';
                continue;
            }

            $sanitized[$key] = (string)$value;
        }

        return $sanitized;
    }
}
