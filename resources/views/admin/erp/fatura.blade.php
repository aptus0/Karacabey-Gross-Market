<x-layouts.admin header="E-Fatura Takibi">
    <div class="flex flex-col gap-5">

        {{-- Başlık --}}
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">E-Fatura Takibi</h2>
                <p class="text-sm text-slate-500">ERP12 canlı fatura kayıtları</p>
            </div>
        </div>

        {{-- Özet Kartlar --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Toplam Fatura</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ number_format($ozet['toplam']) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Toplam Tutar</p>
                <p class="mt-1 text-3xl font-bold text-orange-600">{{ number_format($ozet['toplam_tutar'], 2) }} ₺</p>
            </div>
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Kabul Edilen</p>
                <p class="mt-1 text-3xl font-bold text-emerald-600">{{ number_format($ozet['kabul_sayisi']) }}</p>
            </div>
        </div>

        {{-- Filtreler --}}
        <x-ui.card class="rounded-lg">
            <form method="GET" action="{{ route('admin.erp.fatura') }}" class="flex flex-wrap gap-3 p-4 items-end">
                <div class="space-y-1">
                    <x-ui.label>Başlangıç Tarihi</x-ui.label>
                    <x-ui.input type="date" name="baslangic" value="{{ $filters['tarih_baslangic'] ?? '' }}" />
                </div>
                <div class="space-y-1">
                    <x-ui.label>Bitiş Tarihi</x-ui.label>
                    <x-ui.input type="date" name="bitis" value="{{ $filters['tarih_bitis'] ?? '' }}" />
                </div>
                <div class="space-y-1">
                    <x-ui.label>Tip</x-ui.label>
                    <x-ui.select name="tip">
                        <option value="">Tümü</option>
                        <option value="SATIS" @selected(($filters['tip']??'') === 'SATIS')>Satış</option>
                        <option value="IADE" @selected(($filters['tip']??'') === 'IADE')>İade</option>
                        <option value="ALIS" @selected(($filters['tip']??'') === 'ALIS')>Alış</option>
                    </x-ui.select>
                </div>
                <div class="space-y-1">
                    <x-ui.label>Durum</x-ui.label>
                    <x-ui.select name="durum">
                        <option value="">Tümü</option>
                        <option value="kabul" @selected(($filters['durum']??'') === 'kabul')>Kabul Edildi</option>
                        <option value="bekliyor" @selected(($filters['durum']??'') === 'bekliyor')>Bekliyor</option>
                    </x-ui.select>
                </div>
                <x-ui.button type="submit" variant="outline" class="rounded-md">Filtrele</x-ui.button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.erp.fatura') }}" class="text-sm text-slate-400 hover:text-slate-700 self-end pb-1">Temizle</a>
                @endif
            </form>
        </x-ui.card>

        {{-- Tablo --}}
        <x-ui.card class="rounded-lg overflow-hidden">
            @if(count($faturalar) === 0)
                <div class="py-16 text-center text-slate-400">
                    <p class="text-4xl mb-3">🧾</p>
                    <p class="font-medium">Fatura bulunamadı</p>
                    <p class="text-sm mt-1">ERP12 bağlantısında fatura verisi yok veya filtreler eşleşmedi.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Belge No</th>
                                <th class="px-4 py-3 text-left">Tarih</th>
                                <th class="px-4 py-3 text-left">Tip</th>
                                <th class="px-4 py-3 text-left">Vergi No</th>
                                <th class="px-4 py-3 text-right">Tutar</th>
                                <th class="px-4 py-3 text-center">Durum</th>
                                <th class="px-4 py-3 text-center">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($faturalar as $f)
                            <tr class="hover:bg-orange-50 transition-colors">
                                <td class="px-4 py-3 font-mono text-xs text-slate-700">
                                    <a href="{{ route('admin.erp.fatura.show', $f['id']) }}" class="text-orange-600 hover:underline font-medium">
                                        {{ $f['belgeno'] ?: '#'.$f['id'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $f['tarih'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $f['tip'] ?: '-' }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $f['vergi_no'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-right font-medium text-slate-900">{{ number_format($f['tutar'], 2) }} ₺</td>
                                <td class="px-4 py-3 text-center">
                                    @if($f['kabul'] === 'Kabul')
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Kabul</span>
                                    @else
                                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Bekliyor</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('admin.erp.fatura.show', $f['id']) }}"
                                       class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-orange-100 hover:text-orange-700 transition-colors">
                                        <x-lucide-eye class="h-3 w-3" /> Detay
                                    </a>
                                </td>
                            </tr>
                            @endforeach

                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.card>

    </div>
</x-layouts.admin>
