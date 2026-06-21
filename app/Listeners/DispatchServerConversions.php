<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Services\Tracking\ConversionEventDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class DispatchServerConversions implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $backoff = 30;
    public bool $afterCommit = true;

    public function __construct(
        private readonly ConversionEventDispatcher $dispatcher,
    ) {
    }

    public function handle(OrderPaid $event): void
    {
        $order = $event->order;

        // İlişkileri eager-load et (CAPI payload için)
        $order->loadMissing(['items', 'tenant.marketingSetting', 'user']);

        if ($order->user?->isAdFree()) {
            Log::info('Server-side conversion skipped for VIP ad-free customer', [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
            ]);

            return;
        }

        $results = $this->dispatcher->dispatchPurchase(
            order: $order,
            clientIp: $this->resolveClientIp($order),
            userAgent: $order->metadata['user_agent'] ?? null,
            fbp: $order->metadata['fbp'] ?? null,
            fbc: $order->metadata['fbc'] ?? null,
        );

        if (! empty($results)) {
            Log::info('Server-side conversion dispatched', [
                'order_id' => $order->id,
                'results' => $results,
            ]);
        }
    }

    private function resolveClientIp($order): ?string
    {
        return $order->metadata['ip'] ?? $order->metadata['client_ip'] ?? null;
    }
}
