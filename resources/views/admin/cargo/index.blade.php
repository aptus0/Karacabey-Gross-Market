<x-layouts.admin header="Kargo Ayarları">
@php
    $credentialFields = [
        'YURTICI' => [
            ['key' => 'client_number', 'label' => 'Müşteri Numarası', 'placeholder' => '12345678'],
            ['key' => 'password',      'label' => 'Şifre',            'placeholder' => '••••••••', 'type' => 'password'],
        ],
        'ARAS' => [
            ['key' => 'username',      'label' => 'Kullanıcı Adı',    'placeholder' => 'user@firma.com'],
            ['key' => 'password',      'label' => 'Şifre',            'placeholder' => '••••••••', 'type' => 'password'],
            ['key' => 'customer_code', 'label' => 'Müşteri Kodu',     'placeholder' => 'ARS123456'],
        ],
        'PTT' => [
            ['key' => 'api_key',     'label' => 'API Anahtarı',   'placeholder' => 'ptt_live_...', 'type' => 'password'],
            ['key' => 'customer_id', 'label' => 'Müşteri ID',     'placeholder' => '987654'],
        ],
        'MNG' => [
            ['key' => 'api_key',       'label' => 'API Anahtarı',    'placeholder' => 'mng_live_...', 'type' => 'password'],
            ['key' => 'merchant_code', 'label' => 'Mağaza Kodu',     'placeholder' => 'MNG-SHOP-001'],
        ],
    ];
@endphp

<div class="mx-auto max-w-5xl space-y-6">

    @if(session('success'))
        <div class="flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex items-center gap-4 mb-1">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100">
                <svg class="h-5 w-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            </div>
            <div>
                <h2 class="text-lg font-semibold text-slate-900">Kargo Sağlayıcıları</h2>
                <p class="text-sm text-slate-500">Müşteri checkout sırasında aktif sağlayıcılar arasından seçim yapar.</p>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.cargo.update') }}" autocomplete="off">
        @csrf
        @method('PUT')

        <div class="space-y-4">
        @foreach($definitions as $code => $def)
            @php
                $setting = $settings[$code];
                $fields  = $credentialFields[$code] ?? [];
                $isActive = $setting->is_active ?? false;
                $hasCredentials = $setting->isConfigured();
            @endphp
            <div x-data="{ open: {{ $isActive ? 'true' : 'false' }}, active: {{ $isActive ? 'true' : 'false' }} }"
                 class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all">

                {{-- Header --}}
                <div class="flex items-center gap-4 px-6 py-4">
                    {{-- Logo --}}
                    <div class="flex h-14 w-24 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-100 bg-slate-50 p-1">
                        <img src="{{ asset('assets/cargo/' . strtolower($code) . '.svg') }}"
                             alt="{{ $def['name'] }}"
                             class="h-full w-full object-contain">
                    </div>

                    {{-- Info --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <h3 class="font-semibold text-slate-900">{{ $def['name'] }}</h3>
                            @if($hasCredentials)
                                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                    API Bağlı
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                    Kimlik bilgisi eksik
                                </span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-sm text-slate-500">
                            @if($isActive)
                                {{ number_format(($setting->price_cents ?? 0) / 100, 2, ',', '.') }} ₺ &bull;
                                {{ $setting->estimated_days_min ?? 1 }}-{{ $setting->estimated_days_max ?? 3 }} iş günü
                            @else
                                Pasif
                            @endif
                        </p>
                    </div>

                    {{-- Toggle --}}
                    <label class="relative inline-flex cursor-pointer items-center gap-3">
                        <input type="checkbox"
                               name="providers[{{ $code }}][is_active]"
                               value="1"
                               {{ $isActive ? 'checked' : '' }}
                               @change="active = $event.target.checked; open = active"
                               class="peer sr-only">
                        <div class="peer h-6 w-11 rounded-full bg-slate-200 transition-colors peer-checked:bg-orange-500 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-all peer-checked:after:translate-x-5"></div>
                        <span class="text-sm font-medium text-slate-600" x-text="active ? 'Aktif' : 'Pasif'">{{ $isActive ? 'Aktif' : 'Pasif' }}</span>
                    </label>

                    {{-- Expand --}}
                    <button type="button" @click="open = !open"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-slate-600">
                        <svg class="h-4 w-4 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>

                {{-- Body --}}
                <div x-show="open" x-collapse class="border-t border-slate-100 bg-slate-50/50">
                    <div class="grid gap-6 p-6 md:grid-cols-2">

                        {{-- Fiyatlandırma --}}
                        <div class="space-y-4">
                            <h4 class="text-xs font-semibold uppercase tracking-widest text-slate-400">Fiyatlandırma</h4>

                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-700">Kargo Ücreti (₺)</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">₺</span>
                                    <input type="number"
                                           name="providers[{{ $code }}][price]"
                                           value="{{ number_format(($setting->price_cents ?? 0) / 100, 2, '.', '') }}"
                                           min="0" step="0.01"
                                           class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-8 pr-4 text-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-100">
                                </div>
                            </div>

                            <div class="space-y-1">
                                <label class="text-sm font-medium text-slate-700">Ücretsiz Kargo Eşiği (₺)</label>
                                <p class="text-xs text-slate-400">0 girilirse eşik uygulanmaz.</p>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">₺</span>
                                    <input type="number"
                                           name="providers[{{ $code }}][free_threshold]"
                                           value="{{ number_format(($setting->free_threshold_cents ?? 0) / 100, 2, '.', '') }}"
                                           min="0" step="0.01"
                                           class="w-full rounded-xl border border-slate-200 bg-white py-2.5 pl-8 pr-4 text-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-100">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700">Min Gün</label>
                                    <input type="number"
                                           name="providers[{{ $code }}][days_min]"
                                           value="{{ $setting->estimated_days_min ?? 1 }}"
                                           min="1" max="30"
                                           class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-100">
                                </div>
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700">Maks Gün</label>
                                    <input type="number"
                                           name="providers[{{ $code }}][days_max]"
                                           value="{{ $setting->estimated_days_max ?? 3 }}"
                                           min="1" max="30"
                                           class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-100">
                                </div>
                            </div>
                        </div>

                        {{-- API Kimlik Bilgileri --}}
                        <div class="space-y-4">
                            <h4 class="text-xs font-semibold uppercase tracking-widest text-slate-400">API Kimlik Bilgileri</h4>
                            @if($hasCredentials)
                                <div class="flex items-center gap-2 rounded-xl bg-green-50 border border-green-200 px-3 py-2 text-xs text-green-700">
                                    <svg class="h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                    Kimlik bilgileri kayıtlı. Değiştirmek için yeni değer girin.
                                </div>
                            @endif
                            @foreach($fields as $field)
                                <div class="space-y-1">
                                    <label class="text-sm font-medium text-slate-700">{{ $field['label'] }}</label>
                                    <input type="{{ $field['type'] ?? 'text' }}"
                                           name="providers[{{ $code }}][credentials][{{ $field['key'] }}]"
                                           placeholder="{{ $hasCredentials ? '(değiştirmek için girin)' : $field['placeholder'] }}"
                                           autocomplete="off"
                                           class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-mono focus:border-orange-400 focus:outline-none focus:ring-2 focus:ring-orange-100">
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
        </div>

        {{-- Save --}}
        <div class="mt-6 flex justify-end">
            <button type="submit"
                    class="inline-flex items-center gap-2 rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-900 focus:ring-offset-2">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Kaydet
            </button>
        </div>
    </form>
</div>
</x-layouts.admin>
