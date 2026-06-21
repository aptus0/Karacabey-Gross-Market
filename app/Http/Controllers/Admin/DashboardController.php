<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Refund;
use App\Services\ErkurAnalyticsService;
use App\Support\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private ErkurAnalyticsService $erkur) {}
    public function __invoke(Request $request, TenantResolver $tenants): View
    {
        $tenant = $tenants->resolve($request);
        $successfulStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Preparing->value,
            OrderStatus::Shipped->value,
            OrderStatus::Delivered->value,
        ];

        $now = now();
        $chartStart = $now->copy()->subDays(6)->startOfDay();
        $currentPeriodStart = $now->copy()->subDays(29)->startOfDay();
        $previousPeriodStart = $now->copy()->subDays(59)->startOfDay();
        $previousPeriodEnd = $now->copy()->subDays(30)->endOfDay();

        $orderQuery = Order::query()->where('tenant_id', $tenant->id);
        $successfulOrderQuery = Order::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('status', $successfulStatuses);
        $paidPaymentQuery = Payment::query()
            ->whereHas('order', fn ($query) => $query->where('tenant_id', $tenant->id))
            ->where('status', PaymentStatus::Paid->value);
        $refundQuery = Refund::query()
            ->whereHas('payment.order', fn ($query) => $query->where('tenant_id', $tenant->id));

        $grossSalesCents = (int) (clone $successfulOrderQuery)->sum(DB::raw('subtotal_cents + shipping_cents'));
        $discountCents = (int) (clone $successfulOrderQuery)->sum('discount_cents');
        $grossCollectedCents = (int) (clone $paidPaymentQuery)->sum(DB::raw('COALESCE(captured_amount_cents, amount_cents)'));
        $refundCents = (int) (clone $refundQuery)->sum('amount_cents');
        $netRevenueCents = max($grossCollectedCents - $refundCents, 0);
        $successfulOrders = (int) (clone $successfulOrderQuery)->count();
        $soldUnits = $this->soldUnitsForRange($tenant->id, $successfulStatuses);
        $averageBasketCents = $successfulOrders > 0 ? (int) round($netRevenueCents / $successfulOrders) : 0;
        $catalogProducts = (int) Product::query()->where('tenant_id', $tenant->id)->count();
        $activeProducts = (int) Product::query()->where('tenant_id', $tenant->id)->where('is_active', true)->count();
        $awaitingPaymentCount = (int) (clone $orderQuery)->where('status', OrderStatus::AwaitingPayment->value)->count();
        $awaitingPaymentCents = (int) (clone $orderQuery)->where('status', OrderStatus::AwaitingPayment->value)->sum('total_cents');
        $customerCount = (int) (clone $orderQuery)->distinct()->count('customer_email');
        $paidPayments = (int) (clone $paidPaymentQuery)->count();

        $currentNetRevenueCents = $this->netRevenueForRange(
            (clone $paidPaymentQuery),
            (clone $refundQuery),
            $currentPeriodStart,
            $now
        );
        $previousNetRevenueCents = $this->netRevenueForRange(
            (clone $paidPaymentQuery),
            (clone $refundQuery),
            $previousPeriodStart,
            $previousPeriodEnd
        );
        $currentOrders = $this->successfulOrdersForRange(
            (clone $successfulOrderQuery),
            $currentPeriodStart,
            $now
        );
        $previousOrders = $this->successfulOrdersForRange(
            (clone $successfulOrderQuery),
            $previousPeriodStart,
            $previousPeriodEnd
        );
        $currentSoldUnits = $this->soldUnitsForRange($tenant->id, $successfulStatuses, $currentPeriodStart, $now);
        $previousSoldUnits = $this->soldUnitsForRange($tenant->id, $successfulStatuses, $previousPeriodStart, $previousPeriodEnd);
        $currentAverageBasket = $currentOrders > 0 ? (int) round($currentNetRevenueCents / $currentOrders) : 0;
        $previousAverageBasket = $previousOrders > 0 ? (int) round($previousNetRevenueCents / $previousOrders) : 0;

        $dailyRevenue = Payment::query()
            ->selectRaw('DATE(COALESCE(confirmed_at, created_at)) as day, SUM(COALESCE(captured_amount_cents, amount_cents)) as total_cents')
            ->whereHas('order', fn ($query) => $query->where('tenant_id', $tenant->id))
            ->where('status', PaymentStatus::Paid->value)
            ->whereRaw(
                'COALESCE(confirmed_at, created_at) BETWEEN ? AND ?',
                [$chartStart->toDateTimeString(), $now->copy()->endOfDay()->toDateTimeString()]
            )
            ->groupBy(DB::raw('DATE(COALESCE(confirmed_at, created_at))'))
            ->pluck('total_cents', 'day');

        $dailyUnits = OrderItem::query()
            ->selectRaw('DATE(COALESCE(orders.paid_at, orders.created_at)) as day, SUM(order_items.quantity) as total_units')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', $tenant->id)
            ->whereIn('orders.status', $successfulStatuses)
            ->whereRaw(
                'COALESCE(orders.paid_at, orders.created_at) BETWEEN ? AND ?',
                [$chartStart->toDateTimeString(), $now->copy()->endOfDay()->toDateTimeString()]
            )
            ->groupBy(DB::raw('DATE(COALESCE(orders.paid_at, orders.created_at))'))
            ->pluck('total_units', 'day');

        $days = collect(range(6, 0))->map(function (int $days) use ($now): array {
            $date = $now->copy()->subDays($days);

            return [
                'key' => $date->toDateString(),
                'label' => $date->locale('tr')->translatedFormat('d M'),
            ];
        });

        $chartData = [
            'labels' => $days->pluck('label')->all(),
            'earnings' => $days->map(fn (array $day) => (int) round(((int) ($dailyRevenue[$day['key']] ?? 0)) / 100))->all(),
            'units' => $days->map(fn (array $day) => (int) ($dailyUnits[$day['key']] ?? 0))->all(),
        ];

        return view('admin.dashboard', [
            'tenant' => $tenant,
            'metrics' => [
                'net_revenue_cents' => $netRevenueCents,
                'gross_sales_cents' => $grossSalesCents,
                'discount_cents' => $discountCents,
                'refund_cents' => $refundCents,
                'successful_orders' => $successfulOrders,
                'sold_units' => $soldUnits,
                'average_basket_cents' => $averageBasketCents,
                'catalog_products' => $catalogProducts,
                'active_products' => $activeProducts,
                'awaiting_payment_count' => $awaitingPaymentCount,
                'awaiting_payment_cents' => $awaitingPaymentCents,
                'customer_count' => $customerCount,
                'paid_payments' => $paidPayments,
            ],
            'trends' => [
                'net_revenue' => $this->formatTrend($currentNetRevenueCents, $previousNetRevenueCents),
                'sold_units' => $this->formatTrend($currentSoldUnits, $previousSoldUnits),
                'orders' => $this->formatTrend($currentOrders, $previousOrders),
                'average_basket' => $this->formatTrend($currentAverageBasket, $previousAverageBasket),
            ],
            'orders' => (clone $orderQuery)
                ->with('payment')
                ->withSum('items as units_count', 'quantity')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (Order $order): array => [
                    'merchant_oid' => $order->merchant_oid,
                    'customer_name' => $order->customer_name,
                    'customer_email' => $order->customer_email,
                    'total_cents' => $order->total_cents,
                    'currency' => $order->currency,
                    'status_label' => $this->statusLabel($order->status),
                    'status_classes' => $this->statusClasses($order->status),
                    'created_at_human' => $order->created_at?->diffForHumans() ?? '-',
                    'created_at_formatted' => $order->created_at?->format('d.m.Y H:i') ?? '-',
                    'units_count' => (int) ($order->units_count ?? 0),
                    'show_url' => route('admin.orders.show', $order),
                ]),
            'topProducts' => OrderItem::query()
                ->selectRaw('order_items.product_id, order_items.name, SUM(order_items.quantity) as units_sold, SUM(order_items.line_total_cents) as revenue_cents')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.tenant_id', $tenant->id)
                ->whereIn('orders.status', $successfulStatuses)
                ->groupBy('order_items.product_id', 'order_items.name')
                ->orderByDesc('units_sold')
                ->orderByDesc('revenue_cents')
                ->limit(5)
                ->get(),
            'chartData' => $chartData,
            // Erkur ERP verileri
            'erkurFinans' => $this->erkur->getFinansOzeti(),
            'erkurPos' => $this->erkur->getPosOzeti(),
            'erkurPosTutar' => $this->erkur->getPosTutarOzeti(),
            'erkurFaturalar' => $this->erkur->getEFaturalar(10),
            'erkurStok' => $this->erkur->getStokOzeti(),
            'erkurCari' => $this->erkur->getCariOzeti(8),
            'erkurSayim' => $this->erkur->getSayimOzeti(),
        ]);
    }

    private function soldUnitsForRange(int $tenantId, array $successfulStatuses, $start = null, $end = null): int
    {
        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.tenant_id', $tenantId)
            ->whereIn('orders.status', $successfulStatuses);

        if ($start && $end) {
            $query->whereRaw(
                'COALESCE(orders.paid_at, orders.created_at) BETWEEN ? AND ?',
                [$start->toDateTimeString(), $end->toDateTimeString()]
            );
        }

        return (int) $query->sum('order_items.quantity');
    }

    private function successfulOrdersForRange($query, $start, $end): int
    {
        return (int) $query
            ->whereRaw('COALESCE(paid_at, created_at) BETWEEN ? AND ?', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->count();
    }

    private function netRevenueForRange($paymentQuery, $refundQuery, $start, $end): int
    {
        $gross = (int) $paymentQuery
            ->whereRaw(
                'COALESCE(confirmed_at, created_at) BETWEEN ? AND ?',
                [$start->toDateTimeString(), $end->toDateTimeString()]
            )
            ->sum(DB::raw('COALESCE(captured_amount_cents, amount_cents)'));

        $refunds = (int) $refundQuery
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->sum('amount_cents');

        return max($gross - $refunds, 0);
    }

    private function formatTrend(int $current, int $previous): array
    {
        if ($previous === 0) {
            return [
                'label' => $current > 0 ? 'Yeni hareket' : 'Degisim yok',
                'classes' => $current > 0 ? 'text-emerald-600' : 'text-slate-500',
            ];
        }

        $change = (($current - $previous) / $previous) * 100;
        $formatted = ($change > 0 ? '+' : '').number_format($change, 1, ',', '.').'% son 30 gun';

        return [
            'label' => $formatted,
            'classes' => match (true) {
                $change > 0 => 'text-emerald-600',
                $change < 0 => 'text-rose-600',
                default => 'text-slate-500',
            },
        ];
    }

    private function statusLabel(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::AwaitingPayment => 'Odeme Bekliyor',
            OrderStatus::Reviewing => 'Kontrol Ediliyor',
            OrderStatus::Paid => 'Odendi',
            OrderStatus::Approved => 'Onaylandi',
            OrderStatus::Preparing => 'Hazirlaniyor',
            OrderStatus::Shipped => 'Kargoda',
            OrderStatus::Delivered => 'Teslim Edildi',
            OrderStatus::Cancelled => 'Iptal',
            OrderStatus::Failed => 'Basarisiz',
            OrderStatus::Refunded => 'Iade Edildi',
            OrderStatus::Returned => 'Geri Dondu',
            OrderStatus::Draft => 'Taslak',
        };
    }

    private function statusClasses(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::Paid, OrderStatus::Approved, OrderStatus::Preparing, OrderStatus::Shipped, OrderStatus::Delivered => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            OrderStatus::AwaitingPayment, OrderStatus::Reviewing => 'border-amber-200 bg-amber-50 text-amber-700',
            OrderStatus::Cancelled, OrderStatus::Failed, OrderStatus::Refunded, OrderStatus::Returned => 'border-rose-200 bg-rose-50 text-rose-700',
            OrderStatus::Draft => 'border-slate-200 bg-slate-50 text-slate-600',
        };
    }
}
