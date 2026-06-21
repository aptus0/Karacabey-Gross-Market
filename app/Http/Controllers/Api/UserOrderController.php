<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with('items')
            ->latest()
            ->paginate(20);

        return response()->json($orders->through(fn (Order $order): array => $this->serialize($order)));
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->load('items');

        return response()->json(['data' => $this->serialize($order)]);
    }

    private function serialize(Order $order): array
    {
        return [
            'id' => $order->id,
            'merchant_oid' => $order->merchant_oid,
            'checkout_ref' => $order->checkout_ref,
            'status' => $order->status->value,
            'status_label' => $this->statusLabel($order->status),
            'currency' => $order->currency,
            'subtotal_cents' => $order->subtotal_cents,
            'shipping_cents' => $order->shipping_cents,
            'discount_cents' => $order->discount_cents,
            'total_cents' => $order->total_cents,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'shipping_city' => $order->shipping_city,
            'shipping_district' => $order->shipping_district,
            'shipping_address' => $order->shipping_address,
            'paid_at' => $order->paid_at?->toISOString(),
            'created_at' => $order->created_at->toISOString(),
            'items' => $order->items->map(fn (OrderItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
                'line_total_cents' => $item->line_total_cents,
            ])->values(),
        ];
    }

    private function statusLabel(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Draft => 'Taslak',
            OrderStatus::AwaitingPayment => 'Ödeme Bekleniyor',
            OrderStatus::Reviewing => 'Kontrol Ediliyor',
            OrderStatus::Paid => 'Ödendi',
            OrderStatus::Approved => 'Onaylandı',
            OrderStatus::Preparing => 'Hazırlanıyor',
            OrderStatus::Shipped => 'Yola Çıktı',
            OrderStatus::Delivered => 'Teslim Edildi',
            OrderStatus::Failed => 'Başarısız',
            OrderStatus::Cancelled => 'İptal Edildi',
            OrderStatus::Refunded => 'İade Edildi',
            OrderStatus::Returned => 'İade Sürecinde',
        };
    }
}
