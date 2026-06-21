<x-layouts.admin header="Stok Sayımı">
    <div class="flex flex-col gap-5">
        <div>
            <h2 class="text-xl font-semibold text-slate-900">Stok Sayımı</h2>
            <p class="text-sm text-slate-500">Erkur ERP sayım kayıtları</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Toplam Sayım</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ number_format($ozet['toplam_sayim']) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Toplam Satır</p>
                <p class="mt-1 text-3xl font-bold text-blue-600">{{ number_format($ozet['toplam_detay']) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Toplam Fark</p>
                <p class="mt-1 text-3xl font-bold text-amber-600">{{ number_format($ozet['toplam_fark'], 2) }}</p>
            </div>
        </div>

        <x-ui.card class="rounded-lg overflow-hidden">
            @if(count($sayimlar) === 0)
                <div class="py-16 text-center text-slate-400">
                    <p class="text-4xl mb-3">📦</p>
                    <p class="font-medium">Sayım kaydı bulunamadı</p>
                    <p class="text-sm mt-1">Erkur ERP dump'ında SAYIM.csv verisi yok.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Sayım No</th>
                                <th class="px-4 py-3 text-left">Tarih</th>
                                <th class="px-4 py-3 text-right">Satır Sayısı</th>
                                <th class="px-4 py-3 text-right">Toplam Fark</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($sayimlar as $s)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-mono text-xs text-slate-700">{{ $s['no'] ?: '#'.$s['id'] }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $s['tarih'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ count($s['satirlar']) }}</td>
                                <td class="px-4 py-3 text-right">
                                    @if($s['fark'] > 0)
                                        <span class="text-amber-600 font-medium">{{ number_format($s['fark'], 2) }}</span>
                                    @else
                                        <span class="text-emerald-600">0</span>
                                    @endif
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
