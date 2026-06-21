<?php

namespace App\Listeners;

use App\Events\OrderPaid;
use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AwardMobileLoyaltyPoint implements ShouldHandleEventsAfterCommit
{
    public function handle(OrderPaid $event): void
    {
        DB::transaction(function () use ($event): void {
            /** @var Order|null $order */
            $order = Order::query()
                ->whereKey($event->order->id)
                ->lockForUpdate()
                ->first();

            if (! $order || ! $order->user_id || ! $order->isMobileOrder()) {
                return;
            }

            /** @var User|null $user */
            $user = User::query()
                ->whereKey($order->user_id)
                ->lockForUpdate()
                ->first();

            if (! $user) {
                return;
            }

            $nextBalance = (int) $user->loyalty_points + 1;

            $inserted = DB::table('customer_reward_events')->insertOrIgnore([
                'tenant_id' => $order->tenant_id,
                'user_id' => $user->id,
                'customer_uid' => $order->customer_uid ?? $user->customer_uid,
                'order_id' => $order->id,
                'event_type' => 'mobile_purchase',
                'points_delta' => 1,
                'balance_after' => $nextBalance,
                'reason' => 'Mobil alışveriş puanı',
                'metadata' => json_encode([
                    'source' => 'laravel_order_paid',
                    'order_source' => $order->sourceKey(),
                    'merchant_oid' => $order->merchant_oid,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted < 1) {
                return;
            }

            $user->forceFill([
                'loyalty_points' => $nextBalance,
                'loyalty_points_lifetime' => (int) $user->loyalty_points_lifetime + 1,
                'sync_version' => (int) floor(microtime(true) * 1_000_000),
            ])->save();

            Log::info('Mobile loyalty point awarded', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'balance_after' => $nextBalance,
            ]);
        });
    }
}
