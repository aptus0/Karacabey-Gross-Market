<x-layouts.admin header="POS / Z Raporları">
    <div class="flex flex-col gap-5">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">POS / Z Raporları</h2>
                <p class="text-sm text-slate-500">ERP12 canlı POS kapanış ve Z raporu özeti</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">POS Fişi</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ number_format($ozet['pos_fis']) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Tekil Fiş</p>
                <p class="mt-1 text-3xl font-bold text-blue-600">{{ number_format($ozet['tekil_fis']) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Terminal</p>
                <p class="mt-1 text-3xl font-bold text-emerald-600">{{ number_format($ozet['terminal']) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Z Sayısı</p>
                <p class="mt-1 text-3xl font-bold text-orange-600">{{ number_format($ozet['z_sayisi']) }}</p>
            </div>
        </div>

        <x-ui.card class="rounded-lg">
            <form method="GET" action="{{ route('admin.erp.pos') }}" class="flex flex-wrap gap-3 p-4 items-end">
                <div class="space-y-1">
                    <x-ui.label>Başlangıç Tarihi</x-ui.label>
                    <x-ui.input type="date" name="baslangic" value="{{ $filters['tarih_baslangic'] ?? '' }}" />
                </div>
                <div class="space-y-1">
                    <x-ui.label>Bitiş Tarihi</x-ui.label>
                    <x-ui.input type="date" name="bitis" value="{{ $filters['tarih_bitis'] ?? '' }}" />
                </div>
                <div class="space-y-1">
                    <x-ui.label>Terminal</x-ui.label>
                    <x-ui.input type="text" name="terminal" value="{{ $filters['terminal'] ?? '' }}" placeholder="KASA-1 veya PC ID" />
                </div>
                <x-ui.button type="submit" variant="outline" class="rounded-md">Filtrele</x-ui.button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.erp.pos') }}" class="text-sm text-slate-400 hover:text-slate-700 self-end pb-1">Temizle</a>
                @endif
            </form>
        </x-ui.card>

        <x-ui.card class="rounded-lg overflow-hidden">
            @if(count($raporlar) === 0)
                <div class="py-16 text-center text-slate-400">
                    <p class="font-medium">POS raporu bulunamadı</p>
                    <p class="text-sm mt-1">ERP12 bağlantısı yok veya filtreler eşleşmedi.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Z Tarihi</th>
                                <th class="px-4 py-3 text-left">Terminal</th>
                                <th class="px-4 py-3 text-right">Kasa Z</th>
                                <th class="px-4 py-3 text-right">Z No</th>
                                <th class="px-4 py-3 text-right">Fiş</th>
                                <th class="px-4 py-3 text-right">Tutar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($raporlar as $r)
                                <tr class="hover:bg-orange-50 transition-colors">
                                    <td class="px-4 py-3 text-slate-700">{{ $r['z_tarihi'] ?: '-' }}</td>
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-slate-900">{{ $r['terminal'] ?: 'Terminal' }}</div>
                                        <div class="font-mono text-xs text-slate-400">{{ $r['pc_id'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-xs">{{ $r['kasa_z'] ?: '-' }}</td>
                                    <td class="px-4 py-3 text-right font-mono text-xs">{{ $r['z_no'] ?: '-' }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($r['fis_sayisi']) }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-slate-900">{{ number_format($r['tutar'], 2) }} ₺</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.admin>
