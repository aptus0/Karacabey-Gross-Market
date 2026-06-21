<x-layouts.admin header="Fatura Detayı">
    <div class="flex flex-col gap-5">

        {{-- Başlık --}}
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.erp.fatura') }}" class="rounded-lg border p-2 hover:bg-slate-50">
                <x-lucide-arrow-left class="h-4 w-4 text-slate-600" />
            </a>
            <div>
                <h2 class="text-xl font-semibold text-slate-900">
                    Fatura #{{ $fatura['belgeno'] ?? $fisId }}
                </h2>
                <p class="text-sm text-slate-500">
                    {{ $fatura['tarih'] ?? '-' }} &bull; {{ $fatura['tip'] ?? '-' }}
                    &bull; {{ number_format($fatura['tutar'] ?? 0, 2) }} ₺
                </p>
            </div>
            <div class="ml-auto flex items-center gap-2">
                @if($fatura && $fatura['kabul'] === 'Kabul')
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700">Kabul Edildi</span>
                @else
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-700">Bekliyor</span>
                @endif

                <form method="POST" action="{{ route('admin.erp.fatura.sync', $fisId) }}" data-confirm-submit="Bu fatura için ürün/fiyat/stok senkronizasyonu yapılacak. Onaylıyor musunuz?">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-orange-500 px-4 py-2 text-sm font-medium text-white hover:bg-orange-600 transition-colors">
                        <x-lucide-refresh-cw class="h-4 w-4" />
                        Ürün / Fiyat / Stok Senkronize Et
                    </button>
                </form>
            </div>
        </div>

        {{-- Durum mesajı --}}
        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                ✓ {{ session('status') }}
            </div>
        @endif

        {{-- Satırlar --}}
        <x-ui.card class="rounded-lg overflow-hidden">
            @if(count($satirlar) === 0)
                <div class="py-16 text-center text-slate-400">
                    <p class="text-4xl mb-3">📋</p>
                    <p class="font-medium">Fatura satırı bulunamadı</p>
                    <p class="text-sm mt-1">Bu fatura ID için ERP12 FIS_DETAY kaydı bulunamadı.</p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-xs font-medium text-slate-500 uppercase tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">Ürün</th>
                                <th class="px-4 py-3 text-left">Barkod</th>
                                <th class="px-4 py-3 text-right">Miktar</th>
                                <th class="px-4 py-3 text-right">Giriş</th>
                                <th class="px-4 py-3 text-right">Çıkış</th>
                                <th class="px-4 py-3 text-right">Birim Fiyat</th>
                                <th class="px-4 py-3 text-right">KDV Dahil</th>
                                <th class="px-4 py-3 text-right">Tutar</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($satirlar as $s)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-slate-900">{{ $s['stok_ad'] }}</div>
                                    <div class="text-xs text-slate-400 font-mono">{{ $s['stok_kod'] }}</div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $s['barkod'] ?: '-' }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ $s['miktar'] }}</td>
                                <td class="px-4 py-3 text-right text-emerald-600">+{{ $s['miktar_giris'] }}</td>
                                <td class="px-4 py-3 text-right text-rose-600">-{{ $s['miktar_cikis'] }}</td>
                                <td class="px-4 py-3 text-right text-slate-700">{{ number_format($s['fiyat'], 2) }} ₺</td>
                                <td class="px-4 py-3 text-right font-medium text-slate-900">{{ number_format($s['dahil_fiyat'], 2) }} ₺</td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900">{{ number_format($s['tutar'], 2) }} ₺</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t bg-slate-50">
                            <tr>
                                <td colspan="7" class="px-4 py-3 text-right text-sm font-medium text-slate-600">Genel Toplam:</td>
                                <td class="px-4 py-3 text-right text-sm font-bold text-slate-900">
                                    {{ number_format(array_sum(array_column($satirlar, 'tutar')), 2) }} ₺
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            @endif
        </x-ui.card>

    </div>
</x-layouts.admin>
