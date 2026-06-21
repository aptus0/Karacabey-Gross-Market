<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusEvent;
use App\Services\PushNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminOrderStatusService
{
    public function transition(
        Order $order,
        OrderStatus $target,
        Request $request,
        PushNotificationService $push,
        ?string $note = null,
    ): bool {
        $transitioned = false;
        $freshOrder = null;
        $from = null;

        DB::transaction(function () use ($order, $target, $request, $note, &$transitioned, &$freshOrder, &$from): void {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $from = $lockedOrder->status?->value;

            if ($lockedOrder->status === $target) {
                $freshOrder = $lockedOrder->fresh('user');

                return;
            }

            $metadata = $lockedOrder->metadata ?? [];
            $metadata['last_status_update'] = [
                'from' => $from,
                'to' => $target->value,
                'at' => now()->toIso8601String(),
                'by' => $request->user()?->id,
                'source' => 'admin_panel',
            ];

            if ($target === OrderStatus::Approved) {
                $metadata['approved_at'] ??= now()->toIso8601String();
                $metadata['approved_by'] ??= $request->user()?->id;
            }

            $lockedOrder->forceFill([
                'status' => $target,
                'metadata' => $metadata,
                'paid_at' => $target === OrderStatus::Paid && $lockedOrder->paid_at === null
                    ? now()
                    : $lockedOrder->paid_at,
            ])->save();

            OrderStatusEvent::query()->create([
                'tenant_id' => $lockedOrder->tenant_id,
                'order_id' => $lockedOrder->id,
                'user_id' => $request->user()?->id,
                'from_status' => $from,
                'to_status' => $target->value,
                'source' => 'admin_panel',
                'note' => $note,
                'metadata' => [
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 240),
                ],
            ]);

            $transitioned = true;
            $freshOrder = $lockedOrder->fresh('user');
        });

        if (! $transitioned) {
            Log::info('Admin order status transition skipped because status is unchanged.', [
                'order_id' => $order->id,
                'status' => $target->value,
                'admin_user_id' => $request->user()?->id,
            ]);

            return false;
        }

        Log::info('Admin order status transition committed.', [
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $target->value,
            'admin_user_id' => $request->user()?->id,
        ]);

        try {
            if ($freshOrder instanceof Order) {
                $push->sendOrderStatusUpdate($freshOrder, $target->value);
            }
        } catch (\Throwable $e) {
            Log::warning('Admin order status notification failed after database commit.', [
                'order_id' => $order->id,
                'status' => $target->value,
                'admin_user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }
}
