<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\OrderPaid;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.payments.index', [
            'payments' => Payment::query()
                ->with('order', 'refunds')
                ->when($request->filled('q'), function ($query) use ($request): void {
                    $term = '%'.$request->string('q')->trim()->toString().'%';
                    $query->where(function ($query) use ($term): void {
                        $query->where('merchant_oid', 'like', $term)
                            ->orWhereHas('order', fn ($query) => $query
                                ->where('customer_name', 'like', $term)
                                ->orWhere('customer_email', 'like', $term));
                    });
                })
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function approve(Payment $payment): RedirectResponse
    {
        if ($payment->status === PaymentStatus::Paid) {
            return back()->with('status', 'Ödeme zaten onaylı.');
        }

        $order = DB::transaction(function () use ($payment) {
            $payment->update([
                'status' => PaymentStatus::Paid,
                'captured_amount_cents' => $payment->amount_cents,
                'confirmed_at' => now(),
            ]);

            $order = $payment->order()->lockForUpdate()->firstOrFail();
            $metadata = $order->metadata ?? [];
            $metadata['payment_approved_at'] = now()->toIso8601String();

            $order->update([
                'status' => in_array($order->status, [OrderStatus::AwaitingPayment, OrderStatus::Reviewing], true)
                    ? OrderStatus::Preparing
                    : $order->status,
                'paid_at' => now(),
                'metadata' => $metadata,
            ]);

            return $order->fresh(['items', 'tenant.marketingSetting']);
        });

        OrderPaid::dispatch($order);

        return back()->with('status', 'Ödeme ve sipariş onaylandı.');
    }
}
