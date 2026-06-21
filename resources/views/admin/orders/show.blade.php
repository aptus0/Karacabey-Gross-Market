<x-layouts.admin :header="'Sipariş ' . $order->merchant_oid">
@php
    $statusColors = [
        'awaiting_payment' => 'bg-amber-100 text-amber-700',
        'reviewing'        => 'bg-orange-100 text-orange-700',
        'paid'             => 'bg-blue-100 text-blue-700',
        'approved'         => 'bg-emerald-100 text-emerald-700',
        'preparing'        => 'bg-indigo-100 text-indigo-700',
        'shipped'          => 'bg-cyan-100 text-cyan-700',
        'delivered'        => 'bg-green-100 text-green-700',
        'cancelled'        => 'bg-red-100 text-red-700',
        'refunded'         => 'bg-slate-100 text-slate-700',
    ];
    $statusClass = $statusColors[$order->status->value] ?? 'bg-slate-100 text-slate-700';
    $orderStatusLabels = \App\Http\Controllers\Admin\OrderController::statusLabels();
    $currentStatusLabel = $orderStatusLabels[$order->status->value] ?? ucfirst(str_replace('_', ' ', $order->status->value));

    $shipmentStatusLabels = [
        'pending'          => 'Hazırlanıyor',
        'shipped'          => 'Kargoya Verildi',
        'in_transit'       => 'Yolda',
        'out_for_delivery' => 'Dağıtımda',
        'delivered'        => 'Teslim Edildi',
        'returned'         => 'İade',
        'exception'        => 'Sorun',
    ];
    $carrierNames = [
        'YURTICI' => 'Yurtiçi Kargo',
        'ARAS'    => 'Aras Kargo',
        'PTT'     => 'PTT Kargo',
        'MNG'     => 'MNG Kargo',
    ];

    $paymentProvider = $order->payment?->provider;
    $isCashOnDelivery = $order->isCashOnDelivery();
    $paymentMethodLabel = $isCashOnDelivery ? 'Kapıda Ödeme' : 'PayTR Kart';
    $paymentStatusLabel = $order->payment?->status?->value ?? '—';

    // Mobil uygulamadan gelen siparişleri ayırmak için farklı kolon adlarına toleranslı okuma.
    // API tarafında orders.source / order_source / channel değerlerinden biri "ios", "mobile" veya "app" gelirse rozet görünür.
    $orderSourceKey = $order->sourceKey();
    $sourceLabels = [
        'web'     => 'Web',
        'ios'     => 'iOS Mobil',
        'iphone'  => 'iPhone Mobil',
        'ipad'    => 'iPad Mobil',
        'android' => 'Android Mobil',
        'mobile'  => 'Mobil Uygulama',
        'mobil'   => 'Mobil Uygulama',
        'app'     => 'Mobil Uygulama',
    ];
    $orderSourceLabel = $sourceLabels[$orderSourceKey] ?? ucfirst(str_replace(['_', '-'], ' ', $orderSourceKey));
    $isMobileOrder = $order->isMobileOrder();
@endphp

<div class="mx-auto max-w-5xl space-y-5">

    {{-- Flash --}}
    @if(session('success'))
        <div class="flex items-center gap-2 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            {{ session('error') }}
        </div>
    @endif

    {{-- Başlık --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.orders.index') }}"
               class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50 hover:text-slate-900">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h2 class="text-xl font-bold tracking-tight text-slate-900">{{ $order->merchant_oid }}</h2>
                <p class="text-sm text-slate-400">{{ $order->created_at?->format('d.m.Y H:i') }}</p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                {{ $orderSourceLabel }} Sipariş
            </span>

            @if($isMobileOrder)
                <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-3 py-1 text-xs font-semibold text-blue-700">
                    <x-lucide-smartphone class="h-3.5 w-3.5" />
                    Mobil / iOS
                </span>
            @endif

            @if($isCashOnDelivery)
                <span class="inline-flex items-center gap-1 rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold text-orange-700">
                    <x-lucide-banknote class="h-3.5 w-3.5" />
                    Kapıda Ödeme
                </span>
            @endif

            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                {{ $currentStatusLabel }}
            </span>
        </div>
    </div>

    @if($order->status === \App\Enums\OrderStatus::Reviewing)
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-orange-200 bg-orange-50 p-5 shadow-sm">
            <div>
                <h3 class="font-semibold text-orange-950">Sipariş kontrol ve onay bekliyor</h3>
                <p class="mt-1 text-sm text-orange-800">
                    Ödeme yöntemi:
                    <strong>{{ $paymentMethodLabel }}</strong>.
                    @if($isMobileOrder)
                        Onayladığınızda müşteriye sipariş numarasıyla mobil/iOS bildirimi gönderilir.
                    @else
                        Onayladığınızda müşteri sipariş durumunu web hesabından takip edebilir.
                    @endif
                </p>
            </div>
            <form method="POST" action="{{ route('admin.orders.approve', $order) }}">
                @csrf
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-orange-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-orange-700">
                    <x-lucide-check-circle class="h-4 w-4" />
                    Siparişi Onayla
                </button>
            </form>
        </div>
    @endif

    {{-- Sipariş Durum Yönetimi --}}
    <div class="grid gap-4 lg:grid-cols-[1.15fr_0.85fr]">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="font-semibold text-slate-900">Sipariş Durumu</h3>
                    <p class="mt-1 text-sm text-slate-500">Mobil/iOS ve web siparişlerinde müşteriye aynı anda zaman çizelgesi ve push bildirimi gider.</p>
                </div>
                <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}">{{ $currentStatusLabel }}</span>
            </div>

            <form method="POST" action="{{ route('admin.orders.status.update', $order) }}" class="mt-4 grid gap-3 md:grid-cols-[1fr_1.2fr_auto] md:items-end">
                @csrf
                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Yeni Durum</span>
                    <select name="status" class="mt-1 h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-100">
                        @foreach($orderStatusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($order->status->value === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Not / İç açıklama</span>
                    <input name="note" maxlength="500" class="mt-1 h-11 w-full rounded-xl border border-slate-200 px-3 text-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-100" placeholder="Örn: Mağaza ekibi hazırlamaya başladı">
                </label>
                <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-slate-900 px-5 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Durumu Güncelle
                </button>
            </form>

            @if($isCashOnDelivery && $order->isLocalDelivery())
                <p class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-medium text-emerald-800">
                    Karacabey içi kapıda ödemede kargo kaydı açmadan “Onaylandı → Hazırlanıyor → Yola Çıktı → Teslim Edildi” durumlarını buradan yönetebilirsiniz.
                </p>
            @endif
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-semibold text-slate-900">Durum Geçmişi</h3>
            <div class="mt-4 space-y-3">
                @forelse($order->statusEvents as $event)
                    <div class="border-l-2 border-orange-300 pl-3">
                        <p class="text-sm font-medium text-slate-900">
                            {{ $orderStatusLabels[$event->from_status] ?? 'İlk durum' }} → {{ $orderStatusLabels[$event->to_status] ?? $event->to_status }}
                        </p>
                        <p class="text-xs text-slate-500">{{ $event->created_at?->format('d.m.Y H:i') }} · {{ $event->user?->name ?? 'Sistem' }}</p>
                        @if($event->note)
                            <p class="mt-1 text-xs text-slate-500">{{ $event->note }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">Henüz durum geçmişi yok. İlk güncellemede kayıt oluşacak.</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Grid: Müşteri + Teslimat --}}
    <div class="grid gap-4 md:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-widest text-slate-400">Müşteri</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Ad Soyad</dt>
                    <dd class="font-medium text-slate-900">{{ $order->customer_name }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">E-posta</dt>
                    <dd class="font-medium text-slate-900">{{ $order->customer_email }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Telefon</dt>
                    <dd class="font-medium text-slate-900">{{ $order->customer_phone }}</dd>
                </div>
                @if($order->user)
                    <div class="flex justify-between">
                        <dt class="text-slate-500">Kayıtlı Üye</dt>
                        <dd class="font-medium text-slate-900">#{{ $order->user->id }} — {{ $order->user->name }}</dd>
                    </div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-slate-500">Sipariş Kaynağı</dt>
                    <dd class="font-medium {{ $isMobileOrder ? 'text-blue-700' : 'text-slate-900' }}">{{ $orderSourceLabel }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Ödeme Yöntemi</dt>
                    <dd class="font-medium {{ $isCashOnDelivery ? 'text-orange-700' : 'text-slate-900' }}">{{ $paymentMethodLabel }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-widest text-slate-400">Teslimat Adresi</h3>
            <p class="text-sm font-medium text-slate-900 leading-relaxed">
                {{ $order->shipping_address }}<br>
                @if($order->shipping_district || $order->shipping_city)
                    {{ $order->shipping_district }}{{ $order->shipping_district && $order->shipping_city ? ', ' : '' }}{{ $order->shipping_city }}
                @endif
            </p>
        </div>
    </div>

    @if($isCashOnDelivery && $order->isLocalDelivery())
    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-6 shadow-sm">
        <div class="flex items-start gap-4">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                <x-lucide-store class="h-5 w-5" />
            </div>
            <div>
                <h3 class="font-semibold text-emerald-950">Karacabey içi yerel teslimat</h3>
                <p class="mt-1 text-sm text-emerald-800">Kapıda ödeme seçildiği için kargo durumu ve kargo sağlayıcısı gösterilmez. Sipariş mağaza teslimat ekibi tarafından hazırlanıp teslim edilir.</p>
            </div>
        </div>
    </div>
    @else
    {{-- Kargo Paneli --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-3 border-b border-slate-100 px-6 py-4">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100">
                <svg class="h-4 w-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </div>
            <div class="flex flex-1 flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold text-slate-900">Kargo</h3>
                <div class="flex flex-wrap items-center gap-2">
                    @if($isCashOnDelivery)
                        <span class="inline-flex items-center gap-1 rounded-full bg-orange-100 px-2.5 py-1 text-xs font-semibold text-orange-700">
                            <x-lucide-banknote class="h-3.5 w-3.5" />
                            Kapıda tahsilat
                        </span>
                    @endif
                    @if($isMobileOrder)
                        <span class="inline-flex items-center gap-1 rounded-full bg-blue-100 px-2.5 py-1 text-xs font-semibold text-blue-700">
                            <x-lucide-smartphone class="h-3.5 w-3.5" />
                            {{ $orderSourceLabel }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div class="p-6">
            {{-- Kapıda ödeme + mobil kaynak bilgisi --}}
            <div class="mb-5 grid gap-3 md:grid-cols-2">
                <div class="rounded-xl border {{ $isCashOnDelivery ? 'border-orange-200 bg-orange-50' : 'border-slate-200 bg-slate-50' }} px-4 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider {{ $isCashOnDelivery ? 'text-orange-500' : 'text-slate-400' }}">Ödeme Tahsilatı</p>
                            <p class="mt-1 text-sm font-bold {{ $isCashOnDelivery ? 'text-orange-950' : 'text-slate-900' }}">{{ $paymentMethodLabel }}</p>
                        </div>
                        <span class="rounded-lg bg-white px-3 py-1.5 text-sm font-bold {{ $isCashOnDelivery ? 'text-orange-700' : 'text-slate-700' }}">
                            {{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺
                        </span>
                    </div>
                    <p class="mt-2 text-xs {{ $isCashOnDelivery ? 'text-orange-800' : 'text-slate-500' }}">
                        @if($isCashOnDelivery)
                            Kargo oluştururken bu sipariş <strong>kapıda tahsilatlı</strong> olarak işaretlenir. Kuryeden teslimatta ödeme alınması beklenir.
                        @else
                            Ödeme kart ile alındığı için kargo tarafında ekstra tahsilat gerekmez.
                        @endif
                    </p>
                </div>

                <div class="rounded-xl border {{ $isMobileOrder ? 'border-blue-200 bg-blue-50' : 'border-slate-200 bg-slate-50' }} px-4 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider {{ $isMobileOrder ? 'text-blue-500' : 'text-slate-400' }}">Sipariş Kaynağı</p>
                            <p class="mt-1 text-sm font-bold {{ $isMobileOrder ? 'text-blue-950' : 'text-slate-900' }}">{{ $orderSourceLabel }}</p>
                        </div>
                        @if($isMobileOrder)
                            <x-lucide-smartphone class="h-5 w-5 text-blue-600" />
                        @else
                            <x-lucide-monitor class="h-5 w-5 text-slate-500" />
                        @endif
                    </div>
                    <p class="mt-2 text-xs {{ $isMobileOrder ? 'text-blue-800' : 'text-slate-500' }}">
                        @if($isMobileOrder)
                            Bu sipariş mobil/iOS tarafından oluşturuldu. Kargo ve onay aksiyonlarında müşteriye mobil bildirim gönderilebilir.
                        @else
                            Bu sipariş web kanalından oluşturuldu.
                        @endif
                    </p>
                </div>
            </div>
            @if($order->shipment && $order->shipment->tracking_number)
                {{-- Kargo zaten atanmış --}}
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-3">
                        <img src="{{ asset('assets/cargo/' . strtolower($order->shipment->carrier) . '.svg') }}"
                             alt="{{ $carrierNames[$order->shipment->carrier] ?? $order->shipment->carrier }}"
                             class="h-10 w-20 rounded-lg border border-slate-100 object-contain p-1">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $carrierNames[$order->shipment->carrier] ?? $order->shipment->carrier }}</p>
                            <p class="text-xs text-slate-400">{{ $shipmentStatusLabels[$order->shipment->status] ?? $order->shipment->status }}</p>
                            <p class="mt-1 text-xs font-semibold {{ $isCashOnDelivery ? 'text-orange-700' : 'text-slate-500' }}">
                                Tahsilat: {{ $paymentMethodLabel }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 rounded-xl bg-slate-50 px-4 py-3">
                        <span class="text-sm text-slate-500">Takip No:</span>
                        <span class="font-mono text-sm font-bold text-slate-900">{{ $order->shipment->tracking_number }}</span>
                        @if($order->shipment->tracking_url)
                            <a href="{{ $order->shipment->tracking_url }}"
                               target="_blank" rel="noreferrer"
                               class="ml-auto inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                Takip Et
                            </a>
                        @endif
                    </div>

                    @if($order->shipment->shipped_at)
                        <p class="text-xs text-slate-400">
                            Kargoya verildi: {{ $order->shipment->shipped_at->format('d.m.Y H:i') }}
                        </p>
                    @endif
                </div>

            @elseif($cargoOptions->isEmpty())
                {{-- Kargo seçeneği yok --}}
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Aktif kargo sağlayıcısı bulunamadı.
                    <a href="{{ route('admin.cargo.index') }}" class="ml-1 underline">Kargo ayarlarını yapılandırın →</a>
                </div>

            @else
                {{-- Kargo Ata Formu --}}
                <form method="POST" action="{{ route('admin.orders.cargo.assign', $order) }}">
                    @csrf
                    <input type="hidden" name="payment_collection_type" value="{{ $isCashOnDelivery ? 'cash_on_delivery' : 'prepaid' }}">
                    <input type="hidden" name="order_source" value="{{ $orderSourceKey }}">

                    <p class="mb-2 text-sm text-slate-500">Bu sipariş için bir kargo sağlayıcısı seçin ve kargo kaydı oluşturun.</p>

                    @if($isCashOnDelivery || $isMobileOrder)
                        <div class="mb-4 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800">
                            @if($isCashOnDelivery)
                                <strong>Kapıda Ödeme:</strong> Kargo kaydı oluşturulurken tahsilat tipi kapıda ödeme olarak gönderilecek.
                            @endif
                            @if($isCashOnDelivery && $isMobileOrder)
                                <br>
                            @endif
                            @if($isMobileOrder)
                                <strong>Mobil Sipariş:</strong> Sipariş kaynağı {{ $orderSourceLabel }} olarak işaretli, onay/kargo durumunda mobil bildirim tetiklenebilir.
                            @endif
                        </div>
                    @endif
                    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach($cargoOptions as $option)
                            <label class="group relative flex cursor-pointer flex-col items-center gap-2 rounded-xl border-2 border-slate-200 bg-white p-3 text-center transition has-checked:border-orange-400 has-checked:bg-orange-50">
                                <input type="radio" name="carrier" value="{{ $option->code }}"
                                       class="sr-only" {{ $loop->first ? 'checked' : '' }}>
                                <img src="{{ $option->logoUrl() }}"
                                     alt="{{ $option->name }}"
                                     class="h-10 w-full object-contain">
                                <span class="text-xs font-semibold text-slate-700">{{ $option->name }}</span>
                                <span class="text-xs text-slate-400">
                                    {{ $option->price_cents > 0 ? number_format($option->price_cents / 100, 2, ',', '.') . ' ₺' : 'Bedava' }}
                                    &bull; {{ $option->estimated_days_min }}-{{ $option->estimated_days_max }} gün
                                </span>
                                {{-- Check indicator --}}
                                <span class="absolute right-2 top-2 hidden h-4 w-4 items-center justify-center rounded-full bg-orange-500 text-white group-has-checked:flex">
                                    <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        Kargo Oluştur
                    </button>
                </form>
            @endif
        </div>
    </div>
    @endif

    {{-- Ödeme Durumu ve Yöntemi Paneli (Mobil Siparişler İçin) --}}
    @if($isMobileOrder)
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-3 border-b border-slate-100 px-6 py-4">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-slate-100">
                <svg class="h-4 w-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h3 class="font-semibold text-slate-900">Ödeme Ayarları</h3>
        </div>

        <div class="p-6">
            <div class="grid gap-6 md:grid-cols-2">
                {{-- Ödeme Yöntemi --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-900 mb-3">Ödeme Yöntemi</label>
                    <form method="POST" action="{{ route('admin.orders.payment-method.update', $order) }}" class="flex gap-2">
                        @csrf
                        <select name="payment_method" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="cash_on_delivery" @selected($isCashOnDelivery)>Kapıda Ödeme</option>
                            <option value="card" @selected(!$isCashOnDelivery)>Kart ile Ödeme</option>
                        </select>
                        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Güncelle
                        </button>
                    </form>
                    <p class="mt-2 text-xs text-slate-500">
                        @if($isCashOnDelivery)
                            Müşteri kargonun tesliminde ödeme yapacaktır.
                        @else
                            Ödeme müşteri tarafından kart ile yapılmıştır.
                        @endif
                    </p>
                </div>

                {{-- Ödeme Durumu --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-900 mb-3">Ödeme Durumu</label>
                    <form method="POST" action="{{ route('admin.orders.payment-status.update', $order) }}" class="flex gap-2">
                        @csrf
                        <select name="payment_status" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="pending" @selected($paymentStatusLabel === 'pending' || $paymentStatusLabel === '—')>Beklemede</option>
                            <option value="paid" @selected($paymentStatusLabel === 'paid')>Ödendi</option>
                            <option value="failed" @selected($paymentStatusLabel === 'failed')>Başarısız</option>
                            <option value="refunded" @selected($paymentStatusLabel === 'refunded')>İade Edildi</option>
                            <option value="partially_refunded" @selected($paymentStatusLabel === 'partially_refunded')>Kısmen İade</option>
                        </select>
                        <button type="submit" class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700">
                            Kaydet
                        </button>
                    </form>
                    <p class="mt-2 text-xs text-slate-500">
                        Mevcut Durum: <strong>{{ $paymentStatusLabel === '—' ? 'Belirsiz' : ucfirst(str_replace('_', ' ', $paymentStatusLabel)) }}</strong>
                    </p>
                </div>
            </div>
        </div>
    </div>
    @endif



    {{-- Sipariş İçeriği --}}
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
            <h3 class="font-semibold text-slate-900">Sipariş İçeriği</h3>
            <span class="text-sm text-slate-400">{{ $order->items->count() }} ürün</span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-slate-100 bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-400">Ürün</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Adet</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Birim</th>
                        <th class="px-6 py-3 text-right text-xs font-semibold uppercase tracking-wider text-slate-400">Toplam</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($order->items as $item)
                        <tr class="hover:bg-slate-50/50">
                            @php
                                $rawImage = $item->product?->cdn_image_url ?? $item->product?->image_url ?? data_get($item->metadata, 'image_url');
                                $productImage = $rawImage
                                    ? (\Illuminate\Support\Str::startsWith($rawImage, ['http://', 'https://']) ? $rawImage : asset(ltrim($rawImage, '/')))
                                    : asset('assets/kgm-logo-4k.png');
                            @endphp
                            <td class="px-6 py-3">
                                <div class="flex items-center gap-3">
                                    <img src="{{ $productImage }}" alt="{{ $item->name }}" class="h-14 w-14 rounded-xl border border-slate-100 bg-slate-50 object-contain p-1" loading="lazy">
                                    <div>
                                        <p class="font-medium text-slate-900">{{ $item->name }}</p>
                                        @if($item->product?->sku)
                                            <p class="text-xs text-slate-400">SKU: {{ $item->product->sku }}</p>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-3 text-right text-slate-600">{{ $item->quantity }}</td>
                            <td class="px-6 py-3 text-right text-slate-600">{{ number_format($item->unit_price_cents / 100, 2, ',', '.') }} ₺</td>
                            <td class="px-6 py-3 text-right font-semibold text-slate-900">{{ number_format($item->line_total_cents / 100, 2, ',', '.') }} ₺</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 px-6 py-4">
            <div class="ml-auto w-full max-w-xs space-y-2 text-sm">
                <div class="flex justify-between text-slate-500">
                    <span>Ara Toplam</span>
                    <span>{{ number_format($order->subtotal_cents / 100, 2, ',', '.') }} ₺</span>
                </div>
                @if($order->shipping_cents > 0)
                    <div class="flex justify-between text-slate-500">
                        <span>Kargo</span>
                        <span>{{ number_format($order->shipping_cents / 100, 2, ',', '.') }} ₺</span>
                    </div>
                @endif
                @if($order->discount_cents > 0)
                    <div class="flex justify-between text-green-600">
                        <span>İndirim</span>
                        <span>−{{ number_format($order->discount_cents / 100, 2, ',', '.') }} ₺</span>
                    </div>
                @endif
                <div class="flex justify-between border-t border-slate-100 pt-2 text-base font-bold text-slate-900">
                    <span>Genel Toplam</span>
                    <span>{{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺</span>
                </div>
                <div class="flex justify-between text-slate-500">
                    <span>Ödeme Durumu</span>
                    <span class="font-semibold text-slate-900">{{ $paymentStatusLabel }}</span>
                </div>
                <div class="flex justify-between text-slate-500">
                    <span>Ödeme Yöntemi</span>
                    <span class="font-semibold text-slate-900">
                        {{ $paymentMethodLabel }}
                    </span>
                </div>
            </div>
        </div>
    </div>

    {{-- İade Bilgisi --}}
    @if($order->payment?->refunds?->isNotEmpty())
        <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-4">
                <h3 class="font-semibold text-slate-900">İadeler</h3>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($order->payment->refunds as $refund)
                    <div class="flex items-center justify-between px-6 py-3 text-sm">
                        <div>
                            <p class="font-medium text-slate-900">{{ number_format($refund->amount_cents / 100, 2, ',', '.') }} ₺</p>
                            <p class="text-xs text-slate-400">{{ $refund->created_at?->format('d.m.Y H:i') }}</p>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600">
                            {{ $refund->status ?? 'İşleniyor' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>
</x-layouts.admin>
