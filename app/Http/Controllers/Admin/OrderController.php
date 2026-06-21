<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\CargoProviderSetting;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Cargo\CargoManager;
use App\Services\Orders\AdminOrderStatusService;
use App\Services\PushNotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.orders.index', [
            'orders' => $this->ordersQuery($request)->latest()->paginate(20)->withQueryString(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'kgm-orders-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Sipariş No',
                'Müşteri',
                'E-posta',
                'Telefon',
                'Kaynak',
                'Durum',
                'Ödeme Sağlayıcı',
                'Ödeme Durumu',
                'Ara Toplam',
                'Kargo',
                'İndirim',
                'Toplam',
                'Para Birimi',
                'Oluşturulma Tarihi',
            ]);

            $this->ordersQuery($request)
                ->orderBy('id')
                ->chunkById(500, function ($orders) use ($handle): void {
                    foreach ($orders as $order) {
                        fputcsv($handle, [
                            $order->merchant_oid,
                            $order->customer_name,
                            $order->customer_email,
                            $order->customer_phone,
                            $order->sourceKey(),
                            $order->status?->value,
                            $order->payment?->provider,
                            $order->payment?->status instanceof \BackedEnum
                                ? $order->payment->status->value
                                : $order->payment?->status,
                            number_format($order->subtotal_cents / 100, 2, '.', ''),
                            number_format($order->shipping_cents / 100, 2, '.', ''),
                            number_format($order->discount_cents / 100, 2, '.', ''),
                            number_format($order->total_cents / 100, 2, '.', ''),
                            $order->currency,
                            $order->created_at?->toDateTimeString(),
                        ]);
                    }
                }, 'id', 'id');

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function latest(Request $request): JsonResponse
    {
        $sinceId = max(0, (int) $request->query('since_id', 0));

        $orders = Order::query()
            ->when($sinceId > 0, fn ($query) => $query->where('id', '>', $sinceId))
            ->latest('id')
            ->limit(10)
            ->get();

        return response()->json([
            'latest_id' => (int) (Order::query()->max('id') ?? 0),
            'orders' => $orders->map(fn (Order $order): array => [
                'id' => $order->id,
                'merchant_oid' => $order->merchant_oid,
                'customer_name' => $order->customer_name,
                'total' => number_format($order->total_cents / 100, 2, ',', '.') . ' ' . $order->currency,
                'status' => $order->status->value,
                'status_label' => self::statusLabels()[$order->status->value] ?? $order->status->value,
                'created_at' => $order->created_at?->format('d.m.Y H:i'),
                'url' => route('admin.orders.show', $order),
            ])->values(),
        ]);
    }

    public function show(Order $order): View
    {
        $order->load('items.product', 'payment.refunds', 'user', 'shipment', 'statusEvents.user');

        $cargoOptions = CargoProviderSetting::query()
            ->where('tenant_id', $order->tenant_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('admin.orders.show', compact('order', 'cargoOptions'));
    }

    public function assignCargo(
        Order $order,
        Request $request,
        CargoManager $cargo,
        PushNotificationService $push,
        AdminOrderStatusService $statusService,
    ): RedirectResponse
    {
        $validated = $request->validate([
            'carrier' => ['required', 'string', 'in:YURTICI,ARAS,PTT,MNG'],
        ]);

        if ($order->shipment && $order->shipment->tracking_number) {
            return back()->with('error', 'Bu siparişe zaten kargo atanmış.');
        }

        if ($order->isCashOnDelivery() && $order->isLocalDelivery()) {
            return back()->with('error', 'Karacabey içi kapıda ödeme siparişleri yerel teslimattır; kargo kaydı oluşturulmaz. Durumu doğrudan “Yola Çıktı” olarak güncelleyebilirsiniz.');
        }

        try {
            DB::transaction(function () use ($cargo, $order, $validated): void {
                $provider = $cargo->resolveFromSettings($validated['carrier'], $order->tenant_id);
                $result   = $provider->createShipment($order);

                Shipment::updateOrCreate(
                    ['order_id' => $order->id],
                    [
                        'tenant_id'       => $order->tenant_id,
                        'carrier'         => $validated['carrier'],
                        'tracking_number' => $result['tracking_number'],
                        'tracking_url'    => $result['tracking_url'],
                        'status'          => 'pending',
                        'metadata'        => $result['metadata'],
                        'shipped_at'      => now(),
                    ]
                );

                $order->update(['cargo_carrier' => $validated['carrier']]);
            });

            $statusService->transition($order, OrderStatus::Shipped, $request, $push, 'Kargo kaydı oluşturuldu.');
        } catch (\Throwable $e) {
            Log::warning('Admin cargo assignment failed.', [
                'order_id' => $order->id,
                'admin_user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Kargo oluşturulamadı: ' . $e->getMessage());
        }

        return back()->with('success', 'Kargo başarıyla oluşturuldu ve müşteriye bildirim gönderildi.');
    }

    public function approve(
        Order $order,
        Request $request,
        PushNotificationService $push,
        AdminOrderStatusService $statusService,
    ): RedirectResponse
    {
        if (! in_array($order->status, [OrderStatus::Reviewing, OrderStatus::AwaitingPayment, OrderStatus::Paid], true)) {
            return back()->with('error', 'Bu sipariş mevcut durumundan doğrudan onaylanamaz. Durum güncelleme alanını kullanın.');
        }

        try {
            $statusService->transition($order, OrderStatus::Approved, $request, $push, 'Sipariş panelden onaylandı.');
        } catch (\Throwable $e) {
            Log::warning('Admin order approval failed.', [
                'order_id' => $order->id,
                'admin_user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Sipariş onaylanamadı: ' . $e->getMessage());
        }

        return back()->with('success', 'Sipariş onaylandı ve müşteriye görselli/sesli bildirim gönderildi.');
    }

    public function updateStatus(
        Order $order,
        Request $request,
        PushNotificationService $push,
        AdminOrderStatusService $statusService,
    ): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(self::statusLabels()))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $target = OrderStatus::from($validated['status']);
        if ($order->status === $target) {
            return back()->with('success', 'Sipariş zaten seçilen durumda.');
        }
        if ($order->status === OrderStatus::Delivered) {
            return back()->with('error', 'Teslim edilmiş sipariş geriye alınamaz.');
        }
        if ($order->status === OrderStatus::Cancelled) {
            return back()->with('error', 'İptal edilmiş sipariş yeniden işleme alınamaz.');
        }
        if ($target === OrderStatus::Cancelled && $order->status === OrderStatus::Delivered) {
            return back()->with('error', 'Teslim edilmiş sipariş iptal edilemez.');
        }

        try {
            $statusService->transition($order, $target, $request, $push, $validated['note'] ?? null);
        } catch (\Throwable $e) {
            Log::warning('Admin order status update failed.', [
                'order_id' => $order->id,
                'to_status' => $target->value,
                'admin_user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Sipariş durumu güncellenemedi: ' . $e->getMessage());
        }

        return back()->with('success', 'Sipariş durumu “' . (self::statusLabels()[$target->value] ?? $target->value) . '” olarak güncellendi.');
    }

    public function updatePaymentMethod(Order $order, Request $request): RedirectResponse
    {
        if (! $order->isMobileOrder()) {
            return back()->with('error', 'Ödeme yöntemi değişikliği yalnızca mobil siparişler için yapılabilir.');
        }

        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:cash_on_delivery,card'],
        ]);

        DB::transaction(function () use ($order, $request, $validated): void {
            $payment = $order->payment()->lockForUpdate()->first();

            if (! $payment) {
                $payment = $order->payment()->create([
                    'provider' => $validated['payment_method'],
                    'status' => 'pending',
                    'amount_cents' => $order->total_cents,
                    'currency' => $order->currency,
                ]);
            } elseif ($payment->provider !== $validated['payment_method']) {
                $payment->update([
                    'provider' => $validated['payment_method'],
                ]);
            }

            Log::info('Admin order payment method saved.', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'provider' => $validated['payment_method'],
                'admin_user_id' => $request->user()?->id,
            ]);
        });

        return back()->with('success', 'Ödeme yöntemi başarıyla güncellendi.');
    }

    public function updatePaymentStatus(Order $order, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'payment_status' => ['required', 'string', 'in:pending,paid,failed,refunded,partially_refunded'],
        ]);

        $paymentExists = $order->payment()->exists();

        if (! $paymentExists) {
            return back()->with('error', 'Bu sipariş için ödeme kaydı bulunamadı.');
        }

        DB::transaction(function () use ($order, $request, $validated): void {
            $payment = $order->payment()->lockForUpdate()->firstOrFail();
            $from = $payment->status instanceof \BackedEnum
                ? $payment->status->value
                : (string) $payment->status;

            if ($from !== $validated['payment_status']) {
                $payment->update([
                    'status' => $validated['payment_status'],
                    'confirmed_at' => $validated['payment_status'] === 'paid' ? now() : null,
                ]);
            }

            Log::info('Admin order payment status saved.', [
                'order_id' => $order->id,
                'payment_id' => $payment->id,
                'from_status' => $from,
                'to_status' => $validated['payment_status'],
                'admin_user_id' => $request->user()?->id,
            ]);
        });

        return back()->with('success', 'Ödeme durumu başarıyla güncellendi.');
    }

    private function ordersQuery(Request $request): Builder
    {
        $query = Order::query()->with('payment');
        $mobileMatcher = static function ($q): void {
            $q->whereIn('metadata->source', ['ios', 'android', 'mobile', 'mobil', 'app', 'mobile_app'])
                ->orWhereIn('metadata->order_source', ['ios', 'android', 'mobile', 'mobil', 'app', 'mobile_app'])
                ->orWhereIn('metadata->channel', ['ios', 'android', 'mobile', 'mobil', 'app', 'mobile_app'])
                ->orWhereIn('metadata->platform', ['ios', 'android', 'mobile', 'mobil', 'app', 'mobile_app'])
                ->orWhere('checkout_uid', 'like', 'ios-%')
                ->orWhere('payment_uid', 'like', 'ios-%');
        };

        $source = $request->get('source');
        if ($source === 'mobile') {
            $query->where($mobileMatcher);
        } elseif ($source === 'web') {
            $query->whereNot($mobileMatcher);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($q = $request->get('q')) {
            $query->where(function ($query) use ($q): void {
                $query->where('merchant_oid', 'like', "%{$q}%")
                      ->orWhere('customer_name', 'like', "%{$q}%")
                      ->orWhere('customer_email', 'like', "%{$q}%");
            });
        }

        return $query;
    }

    public static function statusLabels(): array
    {
        return [
            'awaiting_payment' => 'Beklemede',
            'reviewing' => 'Kontrol Ediliyor',
            'paid' => 'Ödendi',
            'approved' => 'Onaylandı',
            'preparing' => 'Hazırlanıyor',
            'shipped' => 'Yola Çıktı',
            'delivered' => 'Teslim Edildi',
            'cancelled' => 'İptal Edildi',
        ];
    }
}
