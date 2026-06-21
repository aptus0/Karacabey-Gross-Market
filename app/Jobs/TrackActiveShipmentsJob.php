<?php

namespace App\Jobs;

use App\Enums\OrderStatus;
use App\Events\OrderDelivered;
use App\Models\Shipment;
use App\Services\Cargo\CargoManager;
use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TrackActiveShipmentsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(CargoManager $cargoManager, PushNotificationService $push): void
    {
        // Yalnızca kargoya verilmiş veya yolda olanları çekiyoruz
        $activeShipments = Shipment::whereIn('status', ['pending', 'shipped', 'in_transit'])
            ->whereNotNull('tracking_number')
            ->get();

        foreach ($activeShipments as $shipment) {
            try {
                $provider = $cargoManager->resolveFromSettings($shipment->carrier, $shipment->tenant_id);
                $result = $provider->track($shipment->tracking_number);

                $newStatus = $result['status'] ?? $shipment->status;

                if ($newStatus !== $shipment->status) {
                    $shipment->update([
                        'status' => $newStatus,
                        'metadata' => array_merge($shipment->metadata ?? [], [
                            'last_track_result' => $result['metadata'] ?? [],
                        ]),
                    ]);

                    // Teslim edildi durumu ise siparişi de güncelle ve event fırlat
                    if ($newStatus === 'delivered') {
                        $shipment->update(['delivered_at' => now()]);
                        
                        $order = $shipment->order;
                        if ($order) {
                            $order->update(['status' => OrderStatus::Delivered]);
                            $push->sendOrderStatusUpdate($order->fresh('user'), OrderStatus::Delivered->value);
                            OrderDelivered::dispatch($order);
                        }
                    } elseif ($shipment->order) {
                        $push->sendCargoStatusUpdate($shipment->fresh('order.user'), $newStatus);
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to track shipment {$shipment->id}: " . $e->getMessage());
            }
        }
    }
}
