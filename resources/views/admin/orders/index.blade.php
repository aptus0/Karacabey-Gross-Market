<x-layouts.admin header="Siparişler">
    @php
        $activeSource = request('source');

        $mobileSourceKeys = ['ios', 'android', 'mobile', 'app', 'mobile_app'];

        $sourceBadge = function ($order) use ($mobileSourceKeys) {
            $sourceKey = $order->sourceKey();

            $isMobile = in_array($sourceKey, $mobileSourceKeys, true);

            $label = match ($sourceKey) {
                'ios' => 'iOS',
                'android' => 'Android',
                'mobile', 'app', 'mobile_app' => 'Mobil',
                'web', '' => 'Web',
                default => $isMobile ? 'Mobil' : 'Web',
            };

            $class = $isMobile
                ? 'bg-orange-100 text-orange-700 border-orange-200'
                : 'bg-blue-100 text-blue-700 border-blue-200';

            return [
                'key' => $sourceKey,
                'label' => $label,
                'is_mobile' => $isMobile,
                'class' => $class,
            ];
        };
    @endphp

    <div class="flex flex-col gap-6">

        {{-- Başlık --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Siparişler</h2>
                <p class="text-muted-foreground">Müşteri siparişlerini Web ve Mobil olarak yönetin.</p>
            </div>

            <x-ui.button as="a" href="{{ route('admin.orders.export', request()->query()) }}" variant="outline">
                <x-lucide-download class="mr-2 h-4 w-4" />
                CSV İndir
            </x-ui.button>
        </div>

        {{-- Kanal Hızlı Filtreleri --}}
        <div class="grid gap-3 md:grid-cols-3">
            <a href="{{ route('admin.orders.index', request()->except('source', 'page')) }}"
               class="rounded-2xl border bg-white p-4 transition hover:bg-muted/30 {{ !$activeSource ? 'border-orange-300 ring-2 ring-orange-100' : 'border-slate-200' }}">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Tüm Siparişler</p>
                        <p class="mt-1 text-xs text-muted-foreground">Web ve mobil tüm siparişler</p>
                    </div>
                    <x-lucide-list-filter class="h-5 w-5 text-slate-400" />
                </div>
            </a>

            <a href="{{ route('admin.orders.index', array_merge(request()->except('page'), ['source' => 'web'])) }}"
               class="rounded-2xl border bg-white p-4 transition hover:bg-muted/30 {{ $activeSource === 'web' ? 'border-blue-300 ring-2 ring-blue-100' : 'border-slate-200' }}">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Web Siparişleri</p>
                        <p class="mt-1 text-xs text-muted-foreground">Site üzerinden gelen siparişler</p>
                    </div>
                    <x-lucide-monitor class="h-5 w-5 text-blue-500" />
                </div>
            </a>

            <a href="{{ route('admin.orders.index', array_merge(request()->except('page'), ['source' => 'mobile'])) }}"
               class="rounded-2xl border bg-white p-4 transition hover:bg-muted/30 {{ $activeSource === 'mobile' ? 'border-orange-300 ring-2 ring-orange-100' : 'border-slate-200' }}">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Mobil Siparişleri</p>
                        <p class="mt-1 text-xs text-muted-foreground">iOS / Android uygulamadan gelenler</p>
                    </div>
                    <x-lucide-smartphone class="h-5 w-5 text-orange-500" />
                </div>
            </a>
        </div>

        <x-ui.card>
            {{-- Arama ve Filtre --}}
            <form method="GET" action="{{ route('admin.orders.index') }}"
                  class="p-6 border-b flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">

                <div class="relative flex-1 max-w-md">
                    <x-lucide-search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                    <x-ui.input
                        type="search"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Sipariş ID veya müşteri adı ile arayın..."
                        class="pl-9 bg-muted/50"
                    />
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <x-ui.select name="source" class="w-[160px]">
                        <option value="">Tüm Kanallar</option>
                        <option value="web" @selected(request('source') === 'web')>Web</option>
                        <option value="mobile" @selected(request('source') === 'mobile')>Mobil</option>
                    </x-ui.select>

                    <x-ui.select name="status" class="w-[180px]">
                        <option value="">Tüm Durumlar</option>
                        <option value="awaiting_payment" @selected(request('status') === 'awaiting_payment')>Ödeme Bekliyor</option>
                        <option value="reviewing" @selected(request('status') === 'reviewing')>Kontrol Ediliyor</option>
                        <option value="paid" @selected(request('status') === 'paid')>Ödendi</option>
                        <option value="approved" @selected(request('status') === 'approved')>Onaylandı</option>
                        <option value="preparing" @selected(request('status') === 'preparing')>Hazırlanıyor</option>
                        <option value="shipped" @selected(request('status') === 'shipped')>Yola Çıktı</option>
                        <option value="delivered" @selected(request('status') === 'delivered')>Teslim Edildi</option>
                        <option value="cancelled" @selected(request('status') === 'cancelled')>İptal Edildi</option>
                        <option value="refunded" @selected(request('status') === 'refunded')>İade Edildi</option>
                    </x-ui.select>

                    <x-ui.button type="submit">
                        Filtrele
                    </x-ui.button>

                    @if(request()->hasAny(['q', 'source', 'status']))
                        <x-ui.button as="a" href="{{ route('admin.orders.index') }}" variant="outline">
                            Temizle
                        </x-ui.button>
                    @endif
                </div>
            </form>

            <x-ui.table>
                <x-slot name="header">
                    <tr>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Sipariş ID</th>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Müşteri</th>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Tutar</th>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Ödeme</th>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Kaynak</th>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Durum</th>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Tarih</th>
                        <th class="h-12 px-6 text-right align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">İşlemler</th>
                    </tr>
                </x-slot>

                @forelse($orders as $order)
                    @php
                        $source = $sourceBadge($order);

                        $statusLabel = $order->status === \App\Enums\OrderStatus::Reviewing
                            ? 'Kontrol Ediliyor'
                            : match ($order->status->value) {
                                'awaiting_payment' => 'Ödeme Bekliyor',
                                'paid' => 'Ödendi',
                                'approved' => 'Onaylandı',
                                'preparing' => 'Hazırlanıyor',
                                'shipped' => 'Yola Çıktı',
                                'delivered' => 'Teslim Edildi',
                                'cancelled' => 'İptal Edildi',
                                'refunded' => 'İade Edildi',
                                default => ucfirst(str_replace('_', ' ', $order->status->value)),
                            };
                    @endphp

                    <tr class="border-b transition-colors hover:bg-muted/30 group">
                        <td class="p-4 px-6 align-middle font-mono text-sm font-medium">
                            <a href="{{ route('admin.orders.show', $order) }}"
                               class="hover:underline hover:text-primary transition-colors">
                                {{ $order->merchant_oid }}
                            </a>
                        </td>

                        <td class="p-4 px-6 align-middle">
                            <div class="font-medium text-sm">{{ $order->customer_name }}</div>
                            <div class="text-xs text-muted-foreground">{{ $order->customer_email }}</div>
                        </td>

                        <td class="p-4 px-6 align-middle font-semibold text-sm">
                            {{ number_format($order->total_cents / 100, 2, ',', '.') }} {{ $order->currency }}
                        </td>

                        <td class="p-4 px-6 align-middle">
                            @if($order->payment?->provider === 'cash_on_delivery')
                                <span class="inline-flex items-center rounded-full border border-orange-200 bg-orange-100 px-2.5 py-1 text-xs font-semibold text-orange-700">
                                    Kapıda Ödeme
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                    PayTR Kart
                                </span>
                            @endif
                        </td>

                        <td class="p-4 px-6 align-middle">
                            <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold {{ $source['class'] }}">
                                @if($source['is_mobile'])
                                    <x-lucide-smartphone class="h-3.5 w-3.5" />
                                @else
                                    <x-lucide-monitor class="h-3.5 w-3.5" />
                                @endif
                                {{ $source['label'] }}
                            </span>
                        </td>

                        <td class="p-4 px-6 align-middle">
                            <x-ui.badge variant="secondary">
                                {{ $statusLabel }}
                            </x-ui.badge>
                        </td>

                        <td class="p-4 px-6 align-middle text-sm text-muted-foreground">
                            {{ $order->created_at?->format('d.m.Y H:i') }}
                        </td>

                        <td class="p-4 px-6 align-middle text-right">
                            <x-ui.button variant="ghost" size="icon" as="a" href="{{ route('admin.orders.show', $order) }}">
                                <x-lucide-eye class="h-4 w-4" />
                            </x-ui.button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-8 text-center text-muted-foreground">
                            <div class="flex flex-col items-center justify-center space-y-3">
                                <x-lucide-inbox class="h-10 w-10 text-muted-foreground/50" />
                                <p class="text-lg font-medium">Sipariş bulunamadı</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </x-ui.table>

            @if($orders->hasPages())
                <div class="p-4 px-6 border-t bg-muted/20">
                    {{ $orders->links('pagination::tailwind') }}
                </div>
            @endif
        </x-ui.card>
    </div>

    @push('scripts')
        <script nonce="{{ request()->attributes->get('csp_nonce') }}">
            (() => {
                const pollUrl = @json(route('admin.orders.latest'));
                let latestSeen = Number(@json((int) ($orders->max('id') ?? 0)));
                const channel = 'BroadcastChannel' in window ? new BroadcastChannel('kgm-admin-orders') : null;
                const soundKey = 'kgm:lastOrderSoundId';

                const playDring = () => {
                    const AudioContext = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContext) return;
                    const ctx = new AudioContext();
                    const oscillator = ctx.createOscillator();
                    const gain = ctx.createGain();
                    oscillator.type = 'sine';
                    oscillator.frequency.setValueAtTime(880, ctx.currentTime);
                    oscillator.frequency.setValueAtTime(1180, ctx.currentTime + 0.12);
                    gain.gain.setValueAtTime(0.001, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.18, ctx.currentTime + 0.03);
                    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.45);
                    oscillator.connect(gain).connect(ctx.destination);
                    oscillator.start();
                    oscillator.stop(ctx.currentTime + 0.48);
                };

                const showToast = (order) => {
                    const toast = document.createElement('a');
                    toast.href = order.url;
                    toast.className = 'fixed right-5 top-5 z-[9999] w-80 rounded-2xl border border-orange-200 bg-white p-4 text-sm shadow-xl';
                    toast.innerHTML = `<div class="font-semibold text-slate-900">Yeni sipariş geldi</div><div class="mt-1 text-slate-600">${order.customer_name || 'Müşteri'} · ${order.total}</div><div class="mt-2 text-orange-600">Detaya git →</div>`;
                    document.body.appendChild(toast);
                    setTimeout(() => toast.remove(), 7000);
                };

                const notifyOnce = (order) => {
                    const id = String(order.id);
                    if (localStorage.getItem(soundKey) === id) return;
                    localStorage.setItem(soundKey, id);
                    channel?.postMessage({ type: 'order-sounded', id });
                    playDring();
                    showToast(order);
                    if ('Notification' in window && Notification.permission === 'granted') {
                        new Notification('Karacabey Gross Market', { body: `Yeni sipariş: ${order.total}` });
                    }
                };

                channel?.addEventListener('message', (event) => {
                    if (event.data?.type === 'order-sounded') {
                        localStorage.setItem(soundKey, String(event.data.id));
                    }
                });

                document.addEventListener('click', () => {
                    if ('Notification' in window && Notification.permission === 'default') {
                        Notification.requestPermission().catch(() => null);
                    }
                }, { once: true });

                const poll = async () => {
                    try {
                        const response = await fetch(`${pollUrl}?since_id=${latestSeen}`, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                        if (!response.ok) return;
                        const payload = await response.json();
                        const orders = Array.isArray(payload.orders) ? payload.orders : [];
                        if (orders.length > 0) {
                            const newest = orders.sort((a, b) => Number(b.id) - Number(a.id))[0];
                            notifyOnce(newest);
                        }
                        latestSeen = Math.max(latestSeen, Number(payload.latest_id || latestSeen));
                    } catch (_) {}
                };
                setInterval(poll, 10000);
            })();
        </script>
    @endpush

</x-layouts.admin>
