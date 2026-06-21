<?php

namespace App\Jobs;

use App\Models\NotificationBroadcast;
use App\Services\Notifications\StorefrontNotificationBroadcaster;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DeliverNotificationBroadcast implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $broadcastId)
    {
        $this->onQueue('default');
    }

    public function handle(StorefrontNotificationBroadcaster $broadcaster): void
    {
        $broadcast = NotificationBroadcast::query()->findOrFail($this->broadcastId);
        $broadcaster->deliver($broadcast);
    }

    public function failed(Throwable $exception): void
    {
        NotificationBroadcast::query()->whereKey($this->broadcastId)->update([
            'status' => 'failed',
            'processed_at' => now(),
        ]);
    }
}
