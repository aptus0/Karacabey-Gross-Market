<x-layouts.admin header="Ödemeler">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Ödemeler</h2>
                <p class="text-muted-foreground">PayTR kart ve kapıda ödeme işlemlerini inceleyin.</p>
            </div>
            <x-ui.button as="a" href="#" variant="outline">
                <x-lucide-download class="mr-2 h-4 w-4" /> Rapor İndir
            </x-ui.button>
        </div>

        @if(session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <x-ui.card>
            <form method="GET" action="{{ route('admin.payments.index') }}" class="p-6 border-b flex items-center justify-between gap-4">
                <div class="relative flex-1 max-w-md">
                    <x-lucide-search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                    <x-ui.input type="search" name="q" value="{{ request('q') }}" placeholder="Sipariş veya müşteri ara..." class="pl-9 bg-muted/50" />
                </div>
                <div class="flex items-center gap-2">
                    <x-ui.select name="status" class="w-[180px]">
                        <option value="">Tüm Durumlar</option>
                        <option value="pending" @selected(request('status') === 'pending')>Beklemede</option>
                        <option value="paid" @selected(request('status') === 'paid')>Ödendi</option>
                        <option value="failed" @selected(request('status') === 'failed')>Başarısız</option>
                        <option value="refunded" @selected(request('status') === 'refunded')>İade Edildi</option>
                    </x-ui.select>
                    <x-ui.button type="submit">Filtrele</x-ui.button>
                </div>
            </form>
            
            <x-ui.table>
                <x-slot name="header">
                    <tr>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Sipariş OID</th>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Müşteri</th>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Tutar</th>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Yöntem</th>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Durum</th>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">İadeler</th>
                        <th class="h-12 px-6 text-right align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">İşlem</th>
                    </tr>
                </x-slot>

                @forelse($payments as $payment)
                    <tr class="border-b transition-colors hover:bg-muted/30">
                        <td class="p-4 px-6 align-middle font-mono text-sm font-medium">{{ $payment->merchant_oid }}</td>
                        <td class="p-4 px-6 align-middle">
                            <div class="font-medium text-sm">{{ $payment->order?->customer_name ?? '-' }}</div>
                        </td>
                        <td class="p-4 px-6 align-middle font-semibold text-sm">
                            {{ number_format($payment->amount_cents / 100, 2, ',', '.') }} {{ $payment->currency }}
                        </td>
                        <td class="p-4 px-6 align-middle text-sm">
                            {{ $payment->provider === 'cash_on_delivery' ? 'Kapıda Ödeme' : 'PayTR Kart' }}
                        </td>
                        <td class="p-4 px-6 align-middle">
                            @if($payment->status->value === 'paid')
                                <x-ui.badge variant="default" class="bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20 border-emerald-500/20">Ödendi</x-ui.badge>
                            @else
                                <x-ui.badge variant="secondary">{{ $payment->status->value }}</x-ui.badge>
                            @endif
                        </td>
                        <td class="p-4 px-6 align-middle text-sm">
                            {{ number_format($payment->refunds->where('status', 'success')->sum('amount_cents') / 100, 2, ',', '.') }} ₺
                        </td>
                        <td class="p-4 px-6 align-middle text-right">
                            @if($payment->status->value !== 'paid')
                                <form method="POST" action="{{ route('admin.payments.approve', $payment) }}">
                                    @csrf
                                    <button class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700" type="submit">
                                        Ödemeyi Onayla
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-muted-foreground">{{ $payment->confirmed_at?->format('d.m.Y H:i') ?? $payment->created_at?->format('d.m.Y H:i') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-8 text-center text-muted-foreground">Ödeme kaydı bulunamadı.</td>
                    </tr>
                @endforelse
            </x-ui.table>

            @if($payments->hasPages())
                <div class="p-4 px-6 border-t bg-muted/20">
                    {{ $payments->links('pagination::tailwind') }}
                </div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.admin>
