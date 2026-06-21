<?php

namespace App\Services\Notifications;

use App\Models\NotificationBroadcast;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class StorefrontNotificationBroadcaster
{
    public function __construct(private readonly PushNotificationService $pushNotifications) {}

    public function deliver(NotificationBroadcast $broadcast): int
    {
        $delivered = 0;
        $pushSent = 0;
        $pushFailed = 0;
        $query = $this->audienceQuery($broadcast);
        $targetCount = (clone $query)->count();

        $broadcast->forceFill([
            'status' => 'processing',
            'target_count' => $targetCount,
        ])->save();

        $query
            ->orderBy('id')
            ->chunkById(100, function (Collection $users) use ($broadcast, &$delivered, &$pushSent, &$pushFailed): void {
                foreach ($users as $user) {
                    $result = $this->pushNotifications->sendToUserWithReport(
                        $user,
                        $broadcast->title,
                        $broadcast->body,
                        [
                            'type' => $broadcast->type,
                            'action_url' => $broadcast->action_url,
                            'deep_link' => $broadcast->action_url,
                            'cta_title' => $broadcast->cta_title,
                            'image_url' => $broadcast->image_url,
                            'broadcast_id' => $broadcast->id,
                            'payload' => $broadcast->payload ?? [],
                            'tenant_id' => $broadcast->tenant_id,
                        ],
                        $broadcast->tenant_id,
                    );

                    $delivered++;
                    $pushSent += $result['push_sent'];
                    $pushFailed += $result['push_failed'];
                }
            });

        $broadcast->forceFill([
            'status' => $pushFailed > 0 ? 'partial_failed' : 'sent',
            'delivered_count' => $delivered,
            'push_sent_count' => $pushSent,
            'push_failed_count' => $pushFailed,
            'processed_at' => now(),
        ])->save();

        return $delivered;
    }

    private function audienceQuery(NotificationBroadcast $broadcast): Builder
    {
        $query = User::query()->where('is_admin', false);

        if ($broadcast->audience === 'user' && $broadcast->target_user_id) {
            $query->whereKey($broadcast->target_user_id);
        }

        return $query;
    }
}
