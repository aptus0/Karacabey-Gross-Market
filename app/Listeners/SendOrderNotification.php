<?php

namespace App\Listeners;

use App\Events\OrderShipped;
use App\Events\OrderDelivered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendOrderNotification implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OrderShipped|OrderDelivered $event): void
    {
        $order = $event->order;
        $eventName = class_basename($event);

        // TODO: SMS veya Push servisini çağır
        Log::info("Notification Pipeline triggered for {$eventName}: Order ID {$order->id}");
    }
}
