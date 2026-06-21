<x-layouts.admin header="Cari Detay">
    <div class="flex flex-col gap-5">

        {{-- Başlık --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.erp.cari') }}" class="rounded-lg border p-2 hover:bg-slate-50">
                <x-lucide-arrow-left class="h-4 w-4 text-slate-600" />
            </a>
            <div class="flex-1">
                @if($cari)
                    <h2 class="text-xl font-semibold text-slate-900">{{ $cari['ad'] }}</h2>
                    <p class="text-sm text-slate-500">{{ $cari['kod'] }} &bull; {{ $cari['tur'] }}
                        @if($cari['vergi_no'])&bull; VKN: {{ $cari['vergi_no'] }}@endif
                    </p>
                @else
                    <h2 class="text-xl font-semibold text-slate-900">Cari #{{ $id }}</h2>
                    <p class="text-sm text-slate-400">Bu ID için cari kaydı bulunamadı</p>
                @endif
            </div>
            @if($cari)
                <span class="rounded-full px-3 py-1 text-xs font-medium
                    {{ $cari['aktif'] ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700' }}">
                    {{ $cari['aktif'] ? 'Aktif' : 'Pasif' }}
                </span>
            @endif
        </div>

        @if($cari)
        {{-- Bakiye Kartları --}}
        <div class="grid gap-4 sm:grid-cols-4">
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Toplam Fiş</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ number_format($ozet['toplam_fis']) }}</p>
            </div>
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Toplam Alacak</p>
                <p class="mt-1 text-2xl font-bold text-blue-600">{{ number_format($ozet['toplam_alacak'], 2) }} ₺</p>
                <p class="text-xs text-slate-400 mt-0.5">Satışlardan</p>
            </div>
            <div class="rounded-lg border bg-white p-5">
                <p class="text-xs text-slate-500 font-medium uppercase tracking-wide">Toplam Borç</p>
                <p class="mt-1 text-2xl font-bold text-rose-600">{{ number_format($ozet['toplam_borc'], 2) }} ₺</p>
                <p class="text-xs text-slate-400 mt-0.5">Alışlardan</p>
            </div>
            <div class="rounded-lg border-2 {{ $ozet['bakiye'] >= 0 ? 'border-emerald-200 bg-emerald-50' : 'border-rose-200 bg-rose-50' }} p-5">
                <p class="text-xs font-medium uppercase tracking-wide {{ $ozet['bakiye'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">Net Bakiye</p>
                <p class="mt-1 text-2xl font-bold {{ $ozet['bakiye'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                    {{ $ozet['bakiye'] >= 0 ? '+' : '' }}{{ number_format($ozet['bakiye'], 2) }} ₺
                </p>
                <p class="text-xs mt-0.5 {{ $ozet['bakiye'] >= 0 ? 'text-emerald-500' : 'text-rose-500' }}">
                    {{ $ozet['bakiye'] >= 0 ? 'Alacaklı' : 'Borçlu' }}
                </p>
            </div>
        </div>

        {{-- Profil + Adres --}}
        <div class="grid gap-4 sm:grid-cols-2">
            {{-- Profil Bilgileri --}}
            <x-ui.card class="rounded-lg">
                <div class="border-b px-5 py-4">
                    <h3 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <x-lucide-user class="h-4 w-4 text-orange-500" /> Profil Bilgileri
                    </h3>
                </div>
                <div class="p-5 space-y-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Kod</span>
                        <span class="font-mono font-medium text-slate-800">{{ $cari['kod'] ?: '-' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Tür</span>
                        <span class="font-medium text-slate-800">{{ $cari['tur'] }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Vergi No</span>
                        <span class="font-mono text-slate-800">{{ $cari['vergi_no'] ?: '-' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Vergi Dairesi</span>
                        <span class="text-slate-800">{{ $cari['vergi_dairesi'] ?: '-' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">E-posta</span>
                        <span class="text-slate-800">{{ $cari['email'] ?: '-' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Web</span>
                        <span class="text-slate-800">{{ $cari['web'] ?: '-' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Vade (gün)</span>
                        <span class="font-medium text-slate-800">{{ $cari['vade'] > 0 ? $cari['vade'] : '-' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Risk Limiti</span>
                        <span class="font-medium text-slate-800">
                            {{ $cari['risk_limiti'] > 0 ? number_format($cari['risk_limiti'], 2) . ' ₺' : '-' }}
                        </span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">İskonto</span>
                        <span class="text-slate-800">{{ $cari['iskonto'] > 0 ? '%'.$cari['iskonto'] : '-' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-slate-500">Kayıt Tarihi</span>
                        <span class="text-slate-800">{{ $cari['tarih'] ?: '-' }}</span>
                    </div>
                </div>
            </x-ui.card>

            {{-- Adresler --}}
            <x-ui.card class="rounded-lg">
                <div class="border-b px-5 py-4">
                    <h3 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                        <x-lucide-map-pin class="h-4 w-4 text-orange-500" /> Adres Bilgileri
                    </h3>
                </div>
                @if(count($adresler) === 0)
                    <div class="p-8 text-center text-slate-400 text-sm">Adres kaydı yok</div>
                @else
                    <div class="divide-y">
                        @foreach($adresler as $adres)
                        <div class="p-5 space-y-1.5">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-slate-800">{{ $adres['ad'] ?: 'Adres' }}</span>
                                @if($adres['varsayilan'])
                                    <span class="rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-700">Varsayılan</span>
                                @endif
                            </div>
                            @if($adres['adres'])
                                <p class="text-sm text-slate-600">{{ $adres['adres'] }}</p>
                            @endif
                            @if($adres['ililce'])
                                <p class="text-sm text-slate-500">{{ $adres['ililce'] }}</p>
                            @endif
                            <div class="flex gap-4 text-xs text-slate-400 mt-1">
                                @if($adres['telefon'])<span>📞 {{ $adres['telefon'] }}</span>@endif
                                @if($adres['cep'])<span>📱 {{ $adres['cep'] }}</span>@endif
                                @if($adres['email'])<span>✉️ {{ $adres['email'] }}</span>@endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </x-ui.card>
        </div>

        {{-- Fiş / Hareket Geçmişi --}}
        <x-ui.card class="rounded-lg overflow-hidden">
            <div class="border-b px-5 py-4 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-800 flex items-center gap-2">
                    <x-lucide-clock class="h-4 w-4 text-orange-500" /> Fiş / Hareket Geçmişi
                </h3>
                <span class="text-xs text-slate-400">{{ number_format($ozet['toplam_fis']) }} kayıt (son 200 gösteriliyor)</span>
            </div>

            @if(count($fisler) === 0)
                <div class="py-12 text-center text-slate-400">
                    <p class="text-3xl mb-2">📋</p>
                    <p class="text-sm">Bu cari için fiş hareketi bulunamadı.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Belge No</th>
                                <th class="px-4 py-3 text-left">İşlem Türü</th>
                                <th class="px-4 py-3 text-left">Tarih</th>
                                <th class="px-4 py-3 text-left">Vade</th>
                                <th class="px-4 py-3 text-right">Tutar</th>
                                <th class="px-4 py-3 text-center">Durum</th>
                                <th class="px-4 py-3 text-center">Detay</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($fisler as $f)
                            @php
                                $isAlacak = in_array($f['tur_kodu'], [1, 3, 5]);
                                $isBorc   = in_array($f['tur_kodu'], [2, 4, 6]);
                                $isTahsilat = $f['tur_kodu'] === 7;
                                $isOdeme    = $f['tur_kodu'] === 8;
                            @endphp
                            <tr class="hover:bg-slate-50 transition-colors">
                                <td class="px-4 py-3 font-mono text-xs text-slate-700">
                                    {{ $f['belgeno'] ?: '#'.$f['id'] }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium
                                        @if($isAlacak) bg-blue-50 text-blue-700
                                        @elseif($isBorc) bg-rose-50 text-rose-700
                                        @elseif($isTahsilat) bg-emerald-50 text-emerald-700
                                        @elseif($isOdeme) bg-purple-50 text-purple-700
                                        @else bg-slate-100 text-slate-600 @endif">
                                        {{ $f['tur_ad'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $f['tarih'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-slate-500 text-xs">{{ $f['vade'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-right font-semibold
                                    @if($isAlacak || $isTahsilat) text-blue-700
                                    @elseif($isBorc || $isOdeme) text-rose-700
                                    @else text-slate-700 @endif">
                                    {{ number_format($f['tutar'], 2) }} ₺
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($f['durum'] === 'Onaylı')
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Onaylı</span>
                                    @else
                                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs text-amber-700">Taslak</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if(in_array($f['tur_kodu'], [1, 2, 3, 4]))
                                        <a href="{{ route('admin.erp.fatura.show', $f['id']) }}"
                                           class="text-xs text-orange-600 hover:underline">Fatura</a>
                                    @else
                                        <span class="text-xs text-slate-300">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t-2 bg-slate-50">
                            <tr>
                                <td colspan="4" class="px-4 py-3 text-right text-sm font-medium text-slate-600">
                                    Net Bakiye:
                                </td>
                                <td class="px-4 py-3 text-right text-base font-bold
                                    {{ $ozet['bakiye'] >= 0 ? 'text-blue-700' : 'text-rose-700' }}">
                                    {{ $ozet['bakiye'] >= 0 ? '+' : '' }}{{ number_format($ozet['bakiye'], 2) }} ₺
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </x-ui.card>

        @else
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-8 text-center text-amber-700">
                <p class="text-4xl mb-3">⚠️</p>
                <p class="font-medium">Cari kaydı bulunamadı (ID: {{ $id }})</p>
                <p class="text-sm mt-1">Bu ID CARI.csv içinde mevcut değil.</p>
            </div>
        @endif
    </div>
</x-layouts.admin>
