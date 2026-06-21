<x-layouts.admin header="Cari Takibi">
    <div class="flex flex-col gap-5">

        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-slate-900">Cari Takibi</h2>
                <p class="text-sm text-slate-500">ERP12 canlı cari hesap listesi</p>
            </div>
        </div>

        {{-- Özet Kartlar --}}
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Toplam</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ number_format($ozet['toplam']) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Aktif</p>
                <p class="mt-1 text-3xl font-bold text-emerald-600">{{ number_format($ozet['aktif']) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Alıcı</p>
                <p class="mt-1 text-3xl font-bold text-blue-600">{{ number_format($ozet['alici']) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Satıcı</p>
                <p class="mt-1 text-3xl font-bold text-purple-600">{{ number_format($ozet['satici']) }}</p>
            </div>
        </div>

        {{-- Filtreler --}}
        <x-ui.card class="rounded-lg">
            <form method="GET" action="{{ route('admin.erp.cari') }}" class="flex flex-wrap gap-3 p-4 items-end">
                <div class="space-y-1 flex-1 min-w-48">
                    <x-ui.label>Ara (ad / kod)</x-ui.label>
                    <x-ui.input type="text" name="q" value="{{ $q ?? '' }}" placeholder="Cari adı veya kodu..." />
                </div>
                <div class="space-y-1">
                    <x-ui.label>Tür</x-ui.label>
                    <x-ui.select name="tur">
                        <option value="">Tümü</option>
                        <option value="Alıcı" @selected(($tur ?? '') === 'Alıcı')>Alıcı</option>
                        <option value="Satıcı" @selected(($tur ?? '') === 'Satıcı')>Satıcı</option>
                    </x-ui.select>
                </div>
                <x-ui.button type="submit" variant="outline" class="rounded-md">Ara</x-ui.button>
                @if($q || $tur)
                    <a href="{{ route('admin.erp.cari') }}" class="text-sm text-slate-400 hover:text-slate-700 self-end pb-1">Temizle</a>
                @endif
            </form>
        </x-ui.card>

        {{-- Tablo --}}
        <x-ui.card class="rounded-lg overflow-hidden">
            @if(count($cariler) === 0)
                <div class="py-16 text-center text-slate-400">
                    <p class="text-4xl mb-3">👤</p>
                    <p class="font-medium">Cari bulunamadı</p>
                    <p class="text-sm mt-1">Erkur ERP dump'ında CARI.csv verisi yok veya arama eşleşmedi.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Kod</th>
                                <th class="px-4 py-3 text-left">Ad</th>
                                <th class="px-4 py-3 text-left">Tür</th>
                                <th class="px-4 py-3 text-left">Vergi No</th>
                                <th class="px-4 py-3 text-left">Telefon</th>
                                <th class="px-4 py-3 text-left">Şehir</th>
                                <th class="px-4 py-3 text-right">Vade (gün)</th>
                                <th class="px-4 py-3 text-right">Borç</th>
                                <th class="px-4 py-3 text-right">Alacak</th>
                                <th class="px-4 py-3 text-right">Bakiye</th>
                                <th class="px-4 py-3 text-center">Detay</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($cariler as $c)
                            <tr class="hover:bg-orange-50 transition-colors">
                                <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $c['kod'] }}</td>
                                <td class="px-4 py-3 font-medium text-slate-900">
                                    <a href="{{ route('admin.erp.cari.show', $c['id']) }}" class="hover:text-orange-600 hover:underline">
                                        {{ $c['ad'] }}
                                    </a>
                                </td>
                                <td class="px-4 py-3">
                                    @if($c['tur'] === 'Alıcı')
                                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-medium text-blue-700">Alıcı</span>
                                    @else
                                        <span class="rounded-full bg-purple-50 px-2 py-0.5 text-xs font-medium text-purple-700">Satıcı</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $c['vergi_no'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $c['telefon'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $c['sehir'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">{{ $c['vade'] > 0 ? $c['vade'] : '-' }}</td>
                                <td class="px-4 py-3 text-right font-medium text-rose-700">{{ number_format($c['toplam_borc'] ?? 0, 2) }} ₺</td>
                                <td class="px-4 py-3 text-right font-medium text-blue-700">{{ number_format($c['toplam_alacak'] ?? 0, 2) }} ₺</td>
                                <td class="px-4 py-3 text-right font-semibold {{ ($c['bakiye'] ?? 0) >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                    {{ ($c['bakiye'] ?? 0) >= 0 ? '+' : '' }}{{ number_format($c['bakiye'] ?? 0, 2) }} ₺
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <a href="{{ route('admin.erp.cari.show', $c['id']) }}"
                                       class="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-orange-100 hover:text-orange-700 transition-colors">
                                        <x-lucide-eye class="h-3 w-3" /> Detay
                                    </a>
                                </td>
                            </tr>
                            @endforeach

                        </tbody>
                    </table>
                    <div class="border-t px-4 py-3 text-xs text-slate-400">
                        {{ number_format(count($cariler)) }} cari gösteriliyor
                    </div>
                </div>
            @endif
        </x-ui.card>

    </div>
</x-layouts.admin>
