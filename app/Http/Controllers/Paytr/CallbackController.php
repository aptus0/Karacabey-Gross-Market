<?php

namespace App\Http\Controllers\Paytr;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Http\Controllers\Controller;
use App\Models\CartCoupon;
use App\Models\CartItem;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Services\Paytr\PaytrClient;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CallbackController extends Controller
{
    public function __invoke(Request $request, PaytrClient $paytr): Response
    {
        $payload = $this->sanitizedPayload($request->all());
        $merchantOid = (string) ($payload['merchant_oid'] ?? '');

        if (! $paytr->verifyCallback($payload)) {
            PaymentEvent::query()->create([
                'provider' => 'paytr',
                'event_type' => 'callback',
                'merchant_oid' => $merchantOid ?: null,
                'hash_status' => 'failed',
                'payload' => $payload,
            ]);

            Log::warning('PayTR callback rejected because hash verification failed.', [
                'merchant_oid' => $merchantOid,
            ]);

            return response('PAYTR notification failed: bad hash', 400)
                ->header('Content-Type', 'text/plain');
        }

        $payment = Payment::query()
            ->where('merchant_oid', $merchantOid)
            ->with('order')
            ->first();

        PaymentEvent::query()->create([
            'payment_id' => $payment?->id,
            'provider' => 'paytr',
            'event_type' => 'callback',
            'merchant_oid' => $merchantOid,
            'hash_status' => 'verified',
            'payload' => $payload,
        ]);

        if (! $payment) {
            Log::warning('PayTR callback has verified hash but no local payment.', [
                'merchant_oid' => $merchantOid,
            ]);

            return response('OK')->header('Content-Type', 'text/plain');
        }

        DB::transaction(function () use ($payment, $payload): void {
            $lockedPayment = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->with('order.items')
                ->firstOrFail();

            if (in_array($lockedPayment->status, [
                PaymentStatus::Paid,
                PaymentStatus::Failed,
                PaymentStatus::Refunded,
                PaymentStatus::PartiallyRefunded,
            ], strict: true)) {
                return;
            }

            $paymentAmount = isset($payload['payment_amount'])
                ? (int) $payload['payment_amount']
                : (int) $payload['total_amount'];

            if ($paymentAmount !== $lockedPayment->amount_cents) {
                throw new \RuntimeException('PayTR callback amount mismatch.');
            }

            if (($payload['status'] ?? null) === 'success') {
                $lockedPayment->update([
                    'status' => PaymentStatus::Paid,
                    'captured_amount_cents' => (int) $payload['total_amount'],
                    'payment_type' => $payload['payment_type'] ?? null,
                    'provider_payload' => $payload,
                    'confirmed_at' => now(),
                ]);

                $lockedPayment->order->update([
                    'status' => OrderStatus::Reviewing,
                    'paid_at' => now(),
                ]);

                $this->persistCardTokenIfPresent($lockedPayment, $payload);
                $this->clearCartIfPresent($lockedPayment);

                // Sunucu-taraflı dönüşüm event'leri (Meta CAPI / GA4 MP / TikTok Events API)
                // Queued listener üzerinden async olarak gönderilir.
                OrderPaid::dispatch($lockedPayment->order->fresh());

                return;
            }

            $lockedPayment->update([
                'status' => PaymentStatus::Failed,
                'failed_reason_code' => $payload['failed_reason_code'] ?? null,
                'failed_reason_msg' => $payload['failed_reason_msg'] ?? null,
                'provider_payload' => $payload,
            ]);

            $lockedPayment->order->update(['status' => OrderStatus::Failed]);
            $this->releaseReservedStock($lockedPayment);
        });

        return response('OK')->header('Content-Type', 'text/plain');
    }

    private function persistCardTokenIfPresent(Payment $payment, array $payload): void
    {
        $userId = $payment->order->user_id;
        $utoken = $payload['utoken'] ?? null;
        $ctoken = $payload['ctoken'] ?? null;
        $lastFour = $payload['last_4'] ?? $payload['card_last_four'] ?? null;

        if (! $userId || ! $utoken || ! $ctoken || ! $lastFour) {
            return;
        }

        PaymentMethod::query()->updateOrCreate([
            'user_id' => $userId,
            'provider' => 'paytr',
            'card_last_four' => (string) $lastFour,
        ], [
            'utoken' => (string) $utoken,
            'ctoken' => (string) $ctoken,
            'card_schema' => $payload['schema'] ?? null,
            'card_brand' => $payload['card_brand'] ?? $payload['c_brand'] ?? null,
            'card_type' => $payload['card_type'] ?? $payload['c_type'] ?? null,
            'card_bank' => $payload['card_bank'] ?? $payload['c_bank'] ?? null,
            'expiry_month' => isset($payload['month']) ? (int) $payload['month'] : null,
            'expiry_year' => isset($payload['year']) ? (int) $payload['year'] : null,
            'requires_cvv' => (bool) ($payload['require_cvv'] ?? false),
        ]);
    }

    private function clearCartIfPresent(Payment $payment): void
    {
        $order = $payment->order;
        $metadata = $order->metadata ?? [];

        if ($order->user_id) {
            $order->user?->cartItems()->delete();
            CartCoupon::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('user_id', $order->user_id)
                ->delete();
        }

        if (! empty($metadata['cart_token'])) {
            CartItem::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('cart_token', $metadata['cart_token'])
                ->delete();

            CartCoupon::query()
                ->where('tenant_id', $order->tenant_id)
                ->where('cart_token', $metadata['cart_token'])
                ->delete();
        }
    }

    private function releaseReservedStock(Payment $payment): void
    {
        $order = $payment->order;
        $metadata = $order->metadata ?? [];

        if (! ($metadata['stock_reserved'] ?? false) || ($metadata['stock_released'] ?? false)) {
            return;
        }

        foreach ($order->items as $item) {
            if ($item->product_id) {
                Product::query()->whereKey($item->product_id)->increment('stock_quantity', $item->quantity);
            }
        }

        $metadata['stock_released'] = true;
        $order->update(['metadata' => $metadata]);
    }

    private function sanitizedPayload(array $payload): array
    {
        return Arr::except($payload, [
            'card_number',
            'cc_owner',
            'cvv',
            'expiry_month',
            'expiry_year',
        ]);
    }
}
