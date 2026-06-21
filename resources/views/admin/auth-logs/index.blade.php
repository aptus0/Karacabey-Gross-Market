<x-layouts.admin header="Auth Log">
    @php
        $statusLabels = [
            'success' => 'Basarili',
            'failed' => 'Hatali',
            'blocked' => 'Engelli',
            'decoy' => 'Tuzak',
            'observed' => 'Izlendi',
        ];
        $statusClasses = [
            'success' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
            'failed' => 'bg-amber-100 text-amber-800 border-amber-200',
            'blocked' => 'bg-rose-100 text-rose-700 border-rose-200',
            'decoy' => 'bg-orange-100 text-orange-800 border-orange-200',
            'observed' => 'bg-slate-100 text-slate-700 border-slate-200',
        ];
        $statCards = [
            ['label' => '24 Saat Olay', 'value' => $stats['total_24h'] ?? 0, 'icon' => 'activity'],
            ['label' => 'Engellenen', 'value' => $stats['blocked_24h'] ?? 0, 'icon' => 'shield-x'],
            ['label' => 'Tuzak Hit', 'value' => $stats['decoy_24h'] ?? 0, 'icon' => 'route'],
            ['label' => 'Tekil IP', 'value' => $stats['unique_ips_24h'] ?? 0, 'icon' => 'network'],
        ];
    @endphp

    <div class="flex flex-col gap-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach($statCards as $card)
                <x-ui.card>
                    <div class="flex items-center justify-between gap-4 p-1">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">{{ $card['label'] }}</p>
                            <p class="mt-1 text-3xl font-black text-slate-900">{{ number_format((int) $card['value']) }}</p>
                        </div>
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-orange-100 text-orange-700">
                            @if($card['icon'] === 'activity')
                                <x-lucide-activity class="h-5 w-5" />
                            @elseif($card['icon'] === 'shield-x')
                                <x-lucide-shield-x class="h-5 w-5" />
                            @elseif($card['icon'] === 'route')
                                <x-lucide-route class="h-5 w-5" />
                            @else
                                <x-lucide-network class="h-5 w-5" />
                            @endif
                        </div>
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        @if($activeBlocks->isNotEmpty())
            <x-ui.card>
                <x-slot name="title">Aktif IP Blokları</x-slot>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    @foreach($activeBlocks as $block)
                        <div class="rounded-lg border border-rose-100 bg-rose-50/70 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-mono text-sm font-bold text-rose-700">{{ $block->ip_address }}</p>
                                    <p class="mt-1 text-xs text-rose-600">
                                        {{ \Illuminate\Support\Carbon::parse($block->blocked_until)->format('d.m.Y H:i') }} tarihine kadar
                                    </p>
                                </div>
                                <span class="rounded-full bg-white px-2 py-1 text-xs font-bold text-rose-700">
                                    Risk {{ $block->risk_score }}
                                </span>
                            </div>
                            <p class="mt-3 text-xs text-rose-600">{{ $block->events_count }} olay ile tetiklendi.</p>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        @endif

        <x-ui.card>
            <div class="p-6 pb-5 border-b">
                <form method="GET" action="{{ route('admin.auth-logs.index') }}" class="grid gap-3 lg:grid-cols-[1fr_180px_210px_auto]">
                    <div class="relative">
                        <x-lucide-search class="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                        <x-ui.input
                            type="search"
                            name="q"
                            value="{{ $filters['q'] ?? '' }}"
                            placeholder="IP, e-posta, route veya cihaz ara..."
                            class="pl-9 bg-muted/50"
                        />
                    </div>

                    <x-ui.select name="status">
                        <option value="">Tüm durumlar</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>
                                {{ $statusLabels[$status] ?? $status }}
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select name="event">
                        <option value="">Tüm olaylar</option>
                        @foreach($events as $event)
                            <option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>
                                {{ $event }}
                            </option>
                        @endforeach
                    </x-ui.select>

                    <div class="flex gap-2">
                        <x-ui.button type="submit" class="gap-2">
                            <x-lucide-filter class="h-4 w-4" />
                            Filtrele
                        </x-ui.button>
                        <x-ui.button as="a" href="{{ route('admin.auth-logs.index') }}" variant="outline" size="icon" aria-label="Filtreleri temizle">
                            <x-lucide-x class="h-4 w-4" />
                        </x-ui.button>
                    </div>
                </form>
            </div>

            <x-ui.table>
                <x-slot name="header">
                    <tr>
                        <th scope="col" class="h-12 px-4 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Zaman</th>
                        <th scope="col" class="h-12 px-4 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Durum</th>
                        <th scope="col" class="h-12 px-4 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">IP / Risk</th>
                        <th scope="col" class="h-12 px-4 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Route</th>
                        <th scope="col" class="h-12 px-4 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Kimlik</th>
                        <th scope="col" class="h-12 px-4 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Cihaz</th>
                    </tr>
                </x-slot>

                @forelse($logs as $log)
                    <tr class="border-b transition-colors hover:bg-muted/30">
                        <td class="p-4 align-top">
                            <div class="whitespace-nowrap text-sm font-semibold text-slate-900">{{ $log->created_at?->format('d.m.Y') }}</div>
                            <div class="text-xs text-muted-foreground">{{ $log->created_at?->format('H:i:s') }}</div>
                        </td>
                        <td class="p-4 align-top">
                            <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-bold {{ $statusClasses[$log->status] ?? $statusClasses['observed'] }}">
                                {{ $statusLabels[$log->status] ?? $log->status }}
                            </span>
                            <div class="mt-2 text-xs font-mono text-muted-foreground">{{ $log->event_type }}</div>
                            <div class="text-xs text-muted-foreground">{{ $log->guard_action }}</div>
                        </td>
                        <td class="p-4 align-top">
                            <div class="font-mono text-sm font-bold text-slate-900">{{ $log->ip_address ?? '-' }}</div>
                            <div class="mt-1 flex flex-wrap gap-1">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-700">Risk {{ $log->risk_score }}</span>
                                @foreach((array) $log->risk_reasons as $reason)
                                    <span class="rounded-full bg-orange-50 px-2 py-0.5 text-xs font-semibold text-orange-700">
                                        {{ str_replace('_', ' ', $reason) }}
                                    </span>
                                @endforeach
                            </div>
                            @if($log->blocked_until)
                                <div class="mt-2 text-xs font-semibold text-rose-600">
                                    Blok bitis: {{ $log->blocked_until->format('d.m.Y H:i') }}
                                </div>
                            @endif
                        </td>
                        <td class="p-4 align-top">
                            <div class="max-w-[220px] truncate font-mono text-sm text-slate-900">{{ $log->path }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">{{ $log->method }} · {{ $log->route_name ?? 'route yok' }}</div>
                        </td>
                        <td class="p-4 align-top">
                            @if($log->email)
                                <div class="max-w-[220px] truncate text-sm font-semibold text-slate-900">{{ $log->email }}</div>
                            @else
                                <span class="text-xs text-muted-foreground">-</span>
                            @endif
                        </td>
                        <td class="p-4 align-top">
                            <div class="max-w-[320px] truncate text-xs text-muted-foreground" title="{{ $log->user_agent }}">
                                {{ $log->user_agent ?: '-' }}
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-10 text-center text-muted-foreground">Auth log kaydi bulunmadi.</td>
                    </tr>
                @endforelse
            </x-ui.table>

            @if($logs->hasPages())
                <div class="p-4 px-6 border-t bg-muted/20">
                    {{ $logs->links('pagination::tailwind') }}
                </div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.admin>
