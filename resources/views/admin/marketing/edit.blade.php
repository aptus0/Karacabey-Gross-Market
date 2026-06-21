<x-layouts.admin header="Pazarlama & Reklam Kanalları">
    <div class="flex flex-col gap-6 max-w-5xl mx-auto w-full" x-data="{ tab: 'genel' }">

        {{-- Üst Başlık --}}
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Pazarlama & Reklam Kanalları</h2>
                <p class="text-sm text-muted-foreground">Tüm izleme piksellerini, reklam entegrasyonlarını ve sunucu-taraflı dönüşüm API'lerini buradan yönetin.</p>
            </div>
            @if(session('status'))
                <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-700 ring-1 ring-emerald-200">
                    <x-lucide-check-circle-2 class="h-4 w-4" />
                    {{ session('status') }}
                </div>
            @endif
        </div>

        {{-- Sekme Şeridi --}}
        <div class="sticky top-0 z-20 -mx-2 overflow-x-auto pb-1 pt-1">
            <nav class="flex gap-1 px-2 text-sm font-medium" role="tablist">
                @php
                    $tabs = [
                        ['key' => 'genel',     'label' => 'Genel',           'icon' => 'megaphone'],
                        ['key' => 'google',    'label' => 'Google',          'icon' => 'bar-chart'],
                        ['key' => 'merchant',  'label' => 'Merchant & Maps', 'icon' => 'map-pin'],
                        ['key' => 'meta',      'label' => 'Meta (FB/IG)',    'icon' => 'facebook'],
                        ['key' => 'yandex',    'label' => 'Yandex',          'icon' => 'globe'],
                        ['key' => 'microsoft', 'label' => 'Microsoft & Bing','icon' => 'square'],
                        ['key' => 'tiktok',    'label' => 'TikTok',          'icon' => 'music'],
                        ['key' => 'sunucu',    'label' => 'Sunucu (CAPI)',   'icon' => 'server'],
                    ];
                @endphp
                @foreach($tabs as $t)
                    <button type="button"
                            role="tab"
                            @click="tab = '{{ $t['key'] }}'"
                            ::class="tab === '{{ $t['key'] }}' ? 'bg-orange-500 text-white shadow-sm' : 'bg-white text-slate-600 hover:bg-slate-50'"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-2 transition-colors whitespace-nowrap">
                        <x-dynamic-component :component="'lucide-' . $t['icon']" class="h-4 w-4" />
                        {{ $t['label'] }}
                    </button>
                @endforeach
            </nav>
        </div>

        @if ($errors->any())
            <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                <p class="font-semibold mb-1">Bazı alanlarda hata var:</p>
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.marketing.update') }}" method="POST" class="flex flex-col gap-6">
            @csrf
            @method('PUT')

            {{-- ═══════════════════════ GENEL ═══════════════════════ --}}
            <div x-show="tab === 'genel'" x-cloak class="grid gap-6">
                <x-ui.card>
                    <div class="p-6 border-b flex flex-col space-y-1.5">
                        <div class="flex items-center gap-2">
                            <x-lucide-megaphone class="h-5 w-5 text-orange-500" />
                            <h3 class="font-semibold tracking-tight">Genel Duyuru & Tracking</h3>
                        </div>
                        <p class="text-sm text-muted-foreground">Site genelinde gösterilen duyuru ve genel tracking davranışı.</p>
                    </div>
                    <div class="p-6 grid gap-6">
                        <div class="space-y-2">
                            <x-ui.label for="announcement_text">Duyuru Metni (Top Banner)</x-ui.label>
                            <x-ui.input id="announcement_text" name="announcement_text" value="{{ old('announcement_text', $setting->announcement_text) }}" placeholder="Örn: Güvenli ödeme, canlı stok..." />
                            <p class="text-[0.8rem] text-muted-foreground">Sitenin her sayfasında en üstte görünecek kampanya veya bilgilendirme metni.</p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <label class="flex items-start gap-3 rounded-md border border-slate-200 p-4 cursor-pointer hover:bg-slate-50">
                                <input type="hidden" name="tracking_enabled" value="0">
                                <input type="checkbox" name="tracking_enabled" value="1" id="tracking_enabled" class="mt-0.5 h-4 w-4 rounded border-slate-300" {{ old('tracking_enabled', $setting->tracking_enabled ?? true) ? 'checked' : '' }}>
                                <div>
                                    <div class="text-sm font-medium">Client-side tracking aktif</div>
                                    <p class="text-xs text-slate-500 mt-0.5">Kapalı ise hiçbir pixel/script frontend'e enjekte edilmez (KVKK opt-out senaryoları).</p>
                                </div>
                            </label>

                            <label class="flex items-start gap-3 rounded-md border border-slate-200 p-4 cursor-pointer hover:bg-slate-50">
                                <input type="hidden" name="server_side_events_enabled" value="0">
                                <input type="checkbox" name="server_side_events_enabled" value="1" id="server_side_events_enabled" class="mt-0.5 h-4 w-4 rounded border-slate-300" {{ old('server_side_events_enabled', $setting->server_side_events_enabled ?? false) ? 'checked' : '' }}>
                                <div>
                                    <div class="text-sm font-medium">Sunucu-taraflı dönüşüm (CAPI) aktif</div>
                                    <p class="text-xs text-slate-500 mt-0.5">Açıkken sipariş tamamlandığında Meta CAPI / GA4 Measurement Protocol / TikTok Events API'ye sunucudan event gönderilir.</p>
                                </div>
                            </label>
                        </div>
                    </div>
                </x-ui.card>
            </div>

            {{-- ═══════════════════════ GOOGLE ═══════════════════════ --}}
            <div x-show="tab === 'google'" x-cloak class="grid gap-6">
                <x-ui.card>
                    <div class="p-6 border-b flex flex-col space-y-1.5">
                        <div class="flex items-center gap-2">
                            <x-lucide-bar-chart class="h-5 w-5 text-blue-600" />
                            <h3 class="font-semibold tracking-tight">Google Analytics & Tag Manager</h3>
                        </div>
                        <p class="text-sm text-muted-foreground">GA4 ve GTM kurulumlarını yapılandırın.</p>
                    </div>
                    <div class="p-6 grid gap-6 md:grid-cols-2">
                        <div class="space-y-2">
                            <x-ui.label for="google_analytics_id">GA4 Measurement ID</x-ui.label>
                            <x-ui.input id="google_analytics_id" name="google_analytics_id" value="{{ old('google_analytics_id', $setting->google_analytics_id) }}" placeholder="G-XXXXXXXXXX" />
                            <p class="text-[0.8rem] text-muted-foreground">analytics.google.com → Yönetici → Veri akışları → Akış kimliği yakınındaki <code class="text-xs">G-</code> ile başlayan değer.</p>
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="google_gtm_id">Google Tag Manager ID</x-ui.label>
                            <x-ui.input id="google_gtm_id" name="google_gtm_id" value="{{ old('google_gtm_id', $setting->google_gtm_id) }}" placeholder="GTM-XXXXXXX" />
                            <p class="text-[0.8rem] text-muted-foreground">Doldurulursa GTM container'ı sayfada otomatik enjekte edilir.</p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <x-ui.label for="google_site_verification">Google Search Console Verification</x-ui.label>
                            <x-ui.input id="google_site_verification" name="google_site_verification" value="{{ old('google_site_verification', $setting->google_site_verification) }}" placeholder="HTML meta tag içeriği (sadece content değeri)" />
                            <p class="text-[0.8rem] text-muted-foreground">Search Console → HTML etiketi yöntemi → <code class="text-xs">content="..."</code> içindeki değer.</p>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <div class="p-6 border-b flex flex-col space-y-1.5">
                        <div class="flex items-center gap-2">
                            <x-lucide-target class="h-5 w-5 text-emerald-600" />
                            <h3 class="font-semibold tracking-tight">Google Ads (Reklam İzleme)</h3>
                        </div>
                        <p class="text-sm text-muted-foreground">Ads dönüşüm izleme için Conversion ID ve etiket.</p>
                    </div>
                    <div class="p-6 grid gap-6 md:grid-cols-2">
                        <div class="space-y-2">
                            <x-ui.label for="google_ads_id">Google Ads Conversion ID</x-ui.label>
                            <x-ui.input id="google_ads_id" name="google_ads_id" value="{{ old('google_ads_id', $setting->google_ads_id) }}" placeholder="AW-XXXXXXXXXX" />
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="google_ads_conversion_label">Conversion Label</x-ui.label>
                            <x-ui.input id="google_ads_conversion_label" name="google_ads_conversion_label" value="{{ old('google_ads_conversion_label', $setting->google_ads_conversion_label) }}" placeholder="AbCdEfGhIjK1L2M3N4O" />
                            <p class="text-[0.8rem] text-muted-foreground">Ads → Dönüşümler → Etiketi içeren snippet'ten alın.</p>
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <div class="p-6 border-b flex flex-col space-y-1.5">
                        <div class="flex items-center gap-2">
                            <x-lucide-smartphone class="h-5 w-5 text-slate-700" />
                            <h3 class="font-semibold tracking-tight">Google AdMob (Mobil Reklam)</h3>
                        </div>
                        <p class="text-sm text-muted-foreground">Gerçek AdMob App ID ve reklam birimi ID'leri. VIP müşterilerde mobil uygulama bu reklam alanlarını göstermemelidir.</p>
                    </div>
                    <div class="p-6 grid gap-6 md:grid-cols-2">
                        <div class="space-y-2 md:col-span-2">
                            <x-ui.label for="google_admob_app_id">AdMob App ID</x-ui.label>
                            <x-ui.input id="google_admob_app_id" name="google_admob_app_id" value="{{ old('google_admob_app_id', $setting->google_admob_app_id) }}" placeholder="ca-app-pub-XXXXXXXXXXXXXXXX~XXXXXXXXXX" />
                            <p class="text-[0.8rem] text-muted-foreground">AdMob → Uygulamalar → Uygulama ayarları. SDK aktif edilmeden önce iOS Info.plist içinde de aynı ID olmalı.</p>
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="google_admob_ios_banner_unit_id">iOS Banner Unit ID</x-ui.label>
                            <x-ui.input id="google_admob_ios_banner_unit_id" name="google_admob_ios_banner_unit_id" value="{{ old('google_admob_ios_banner_unit_id', $setting->google_admob_ios_banner_unit_id) }}" placeholder="ca-app-pub-XXXXXXXXXXXXXXXX/XXXXXXXXXX" />
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="google_admob_ios_interstitial_unit_id">iOS Interstitial Unit ID</x-ui.label>
                            <x-ui.input id="google_admob_ios_interstitial_unit_id" name="google_admob_ios_interstitial_unit_id" value="{{ old('google_admob_ios_interstitial_unit_id', $setting->google_admob_ios_interstitial_unit_id) }}" placeholder="ca-app-pub-XXXXXXXXXXXXXXXX/XXXXXXXXXX" />
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="google_admob_android_banner_unit_id">Android Banner Unit ID</x-ui.label>
                            <x-ui.input id="google_admob_android_banner_unit_id" name="google_admob_android_banner_unit_id" value="{{ old('google_admob_android_banner_unit_id', $setting->google_admob_android_banner_unit_id) }}" placeholder="ca-app-pub-XXXXXXXXXXXXXXXX/XXXXXXXXXX" />
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="google_admob_android_interstitial_unit_id">Android Interstitial Unit ID</x-ui.label>
                            <x-ui.input id="google_admob_android_interstitial_unit_id" name="google_admob_android_interstitial_unit_id" value="{{ old('google_admob_android_interstitial_unit_id', $setting->google_admob_android_interstitial_unit_id) }}" placeholder="ca-app-pub-XXXXXXXXXXXXXXXX/XXXXXXXXXX" />
                        </div>
                    </div>
                </x-ui.card>
            </div>

            {{-- ═══════════════════════ MERCHANT & MAPS ═══════════════════════ --}}
            <div x-show="tab === 'merchant'" x-cloak class="grid gap-6">
                <x-ui.card>
                    <div class="p-6 border-b flex flex-col space-y-1.5">
                        <div class="flex items-center gap-2">
                            <x-lucide-shopping-cart class="h-5 w-5 text-indigo-600" />
                            <h3 class="font-semibold tracking-tight">Google Merchant Center (Shopping Feed)</h3>
                        </div>
                        <p class="text-sm text-muted-foreground">Ürün feed'iniz otomatik üretilir: <code class="text-xs">{{ url('/feed/google-merchant.xml') }}</code></p>
                    </div>
                    <div class="p-6 grid gap-6">
                        <div class="space-y-2 max-w-md">
                            <x-ui.label for="google_merchant_id">Merchant Center Account ID</x-ui.label>
                            <x-ui.input id="google_merchant_id" name="google_merchant_id" value="{{ old('google_merchant_id', $setting->google_merchant_id) }}" placeholder="123456789" />
                            <p class="text-[0.8rem] text-muted-foreground">merchants.google.com → Hesap kimliği (yalnızca rakam).</p>
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="google_merchant_service_account_json">Service Account JSON (opsiyonel — Content API push için)</x-ui.label>
                            <x-ui.textarea id="google_merchant_service_account_json" name="google_merchant_service_account_json" rows="6" placeholder='{"type":"service_account","project_id":"...","client_email":"...","private_key":"..."}'>{{ old('google_merchant_service_account_json') }}</x-ui.textarea>
                            <p class="text-[0.8rem] text-muted-foreground">Google Cloud → Service account anahtarı (JSON). Boş bırakılırsa mevcut değer korunur. Sadece XML feed kullanılacaksa bu alana gerek yok.</p>
                            @if($setting->exists && $setting->google_merchant_service_account_json)
                                <p class="text-[0.8rem] text-emerald-600">✓ Şu anda kayıtlı bir service account JSON'u var.</p>
                            @endif
                        </div>
                    </div>
                </x-ui.card>

                <x-ui.card>
                    <div class="p-6 border-b flex flex-col space-y-1.5">
                        <div class="flex items-center gap-2">
                            <x-lucide-map-pin class="h-5 w-5 text-rose-500" />
                            <h3 class="font-semibold tracking-tight">Google Maps</h3>
                        </div>
                        <p class="text-sm text-muted-foreground">Adres harita widget'ı, mağaza konumu için kullanılır.</p>
                    </div>
                    <div class="p-6 grid gap-6 max-w-2xl">
                        <div class="space-y-2">
                            <x-ui.label for="google_maps_api_key">Maps JavaScript API Key</x-ui.label>
                            <x-ui.input id="google_maps_api_key" name="google_maps_api_key" value="{{ old('google_maps_api_key', $setting->google_maps_api_key) }}" placeholder="AIzaSy..." />
                            <p class="text-[0.8rem] text-muted-foreground">Google Cloud Console → API'ler & Hizmetler → Kimlik bilgileri. HTTP referrer kısıtlaması: <code class="text-xs">*.karacabeygrossmarket.com/*</code></p>
                        </div>
                    </div>
                </x-ui.card>
            </div>

            {{-- ═══════════════════════ META ═══════════════════════ --}}
            <div x-show="tab === 'meta'" x-cloak class="grid gap-6">
                <x-ui.card>
                    <div class="p-6 border-b flex flex-col space-y-1.5">
                        <div class="flex items-center gap-2">
                            <x-lucide-facebook class="h-5 w-5 text-blue-600" />
                            <h3 class="font-semibold tracking-tight">Meta (Facebook & Instagram)</h3>
                        </div>
                        <p class="text-sm text-muted-foreground">Pixel + Conversions API + Katalog entegrasyonu.</p>
                    </div>
                    <div class="p-6 grid gap-6 md:grid-cols-2">
                        <div class="space-y-2">
                            <x-ui.label for="meta_pixel_id">Meta Pixel ID</x-ui.label>
                            <x-ui.input id="meta_pixel_id" name="meta_pixel_id" value="{{ old('meta_pixel_id', $setting->meta_pixel_id) }}" placeholder="123456789012345" />
                            <p class="text-[0.8rem] text-muted-foreground">Events Manager → Veri kaynakları → Pixel ID.</p>
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="meta_dataset_id">Dataset ID (CAPI için, opsiyonel)</x-ui.label>
                            <x-ui.input id="meta_dataset_id" name="meta_dataset_id" value="{{ old('meta_dataset_id', $setting->meta_dataset_id) }}" placeholder="123456789012345" />
                            <p class="text-[0.8rem] text-muted-foreground">Yeni Meta hesaplarda Pixel ID = Dataset ID. Boş bırakılırsa Pixel ID kullanılır.</p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <x-ui.label for="meta_capi_access_token">Conversions API Access Token</x-ui.label>
                            <x-ui.input id="meta_capi_access_token" name="meta_capi_access_token" type="password" placeholder="{{ $setting->exists && $setting->meta_capi_access_token ? '••••••••• (mevcut değer korunur)' : 'EAAB...' }}" />
                            <p class="text-[0.8rem] text-muted-foreground">Events Manager → Pixel → Ayarlar → Conversions API → Erişim belirteci oluştur. Sunucu-taraflı event'ler için gerekli.</p>
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="meta_capi_test_event_code">Test Event Code (opsiyonel)</x-ui.label>
                            <x-ui.input id="meta_capi_test_event_code" name="meta_capi_test_event_code" value="{{ old('meta_capi_test_event_code', $setting->meta_capi_test_event_code) }}" placeholder="TEST12345" />
                            <p class="text-[0.8rem] text-muted-foreground">Test ortamında doldur, prod'da boş bırak.</p>
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="meta_catalog_id">Katalog ID</x-ui.label>
                            <x-ui.input id="meta_catalog_id" name="meta_catalog_id" value="{{ old('meta_catalog_id', $setting->meta_catalog_id) }}" placeholder="123456789012345" />
                            <p class="text-[0.8rem] text-muted-foreground">Dynamic Ads için. Commerce Manager → Kataloglar.</p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <x-ui.label for="meta_business_id">Business Manager ID (opsiyonel)</x-ui.label>
                            <x-ui.input id="meta_business_id" name="meta_business_id" value="{{ old('meta_business_id', $setting->meta_business_id) }}" placeholder="123456789012345" />
                        </div>
                    </div>
                </x-ui.card>
            </div>

            {{-- ═══════════════════════ YANDEX ═══════════════════════ --}}
            <div x-show="tab === 'yandex'" x-cloak class="grid gap-6">
                <x-ui.card>
                    <div class="p-6 border-b flex flex-col space-y-1.5">
                        <div class="flex items-center gap-2">
                            <x-lucide-globe class="h-5 w-5 text-amber-500" />
                            <h3 class="font-semibold tracking-tight">Yandex</h3>
                        </div>
                        <p class="text-sm text-muted-foreground">Metrica analitik ve Yandex Direct reklamları.</p>
                    </div>
                    <div class="p-6 grid gap-6 md:grid-cols-2">
                        <div class="space-y-2">
                            <x-ui.label for="yandex_metrica_id">Yandex Metrica Counter ID</x-ui.label>
                            <x-ui.input id="yandex_metrica_id" name="yandex_metrica_id" value="{{ old('yandex_metrica_id', $setting->yandex_metrica_id) }}" placeholder="12345678" />
                            <p class="text-[0.8rem] text-muted-foreground">metrika.yandex.com → Sayaç numarası (yalnızca rakam).</p>
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="yandex_direct_counter_id">Yandex Direct Counter ID</x-ui.label>
                            <x-ui.input id="yandex_direct_counter_id" name="yandex_direct_counter_id" value="{{ old('yandex_direct_counter_id', $setting->yandex_direct_counter_id) }}" placeholder="12345678" />
                            <p class="text-[0.8rem] text-muted-foreground">Genelde Metrica ID ile aynıdır.</p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <x-ui.label for="yandex_verification">Yandex Webmaster Verification</x-ui.label>
                            <x-ui.input id="yandex_verification" name="yandex_verification" value="{{ old('yandex_verification', $setting->yandex_verification) }}" placeholder="meta tag content değeri" />
                        </div>
                    </div>
                </x-ui.card>
            </div>

            {{-- ═══════════════════════ MICROSOFT / BING ═══════════════════════ --}}
            <div x-show="tab === 'microsoft'" x-cloak class="grid gap-6">
                <x-ui.card>
                    <div class="p-6 border-b flex flex-col space-y-1.5">
                        <div class="flex items-center gap-2">
                            <x-lucide-square class="h-5 w-5 text-cyan-600" />
                            <h3 class="font-semibold tracking-tight">Microsoft Advertising & Bing</h3>
                        </div>
                        <p class="text-sm text-muted-foreground">UET tag, Microsoft Clarity ısı haritası ve Bing doğrulama.</p>
                    </div>
                    <div class="p-6 grid gap-6 md:grid-cols-2">
                        <div class="space-y-2">
                            <x-ui.label for="microsoft_uet_tag_id">UET Tag ID</x-ui.label>
                            <x-ui.input id="microsoft_uet_tag_id" name="microsoft_uet_tag_id" value="{{ old('microsoft_uet_tag_id', $setting->microsoft_uet_tag_id) }}" placeholder="12345678" />
                            <p class="text-[0.8rem] text-muted-foreground">ads.microsoft.com → Araçlar → UET tag.</p>
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="microsoft_clarity_id">Microsoft Clarity Project ID</x-ui.label>
                            <x-ui.input id="microsoft_clarity_id" name="microsoft_clarity_id" value="{{ old('microsoft_clarity_id', $setting->microsoft_clarity_id) }}" placeholder="abc123def4" />
                            <p class="text-[0.8rem] text-muted-foreground">clarity.microsoft.com → Proje kimliği. Isı haritası & oturum kaydı (ücretsiz).</p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <x-ui.label for="bing_verification">Bing Webmaster Verification</x-ui.label>
                            <x-ui.input id="bing_verification" name="bing_verification" value="{{ old('bing_verification', $setting->bing_verification) }}" placeholder="meta tag content değeri" />
                        </div>
                    </div>
                </x-ui.card>
            </div>

            {{-- ═══════════════════════ TIKTOK ═══════════════════════ --}}
            <div x-show="tab === 'tiktok'" x-cloak class="grid gap-6">
                <x-ui.card>
                    <div class="p-6 border-b flex flex-col space-y-1.5">
                        <div class="flex items-center gap-2">
                            <x-lucide-music class="h-5 w-5 text-pink-600" />
                            <h3 class="font-semibold tracking-tight">TikTok Pixel & Events API</h3>
                        </div>
                        <p class="text-sm text-muted-foreground">TikTok Ads için pixel ve sunucu-taraflı event API'si.</p>
                    </div>
                    <div class="p-6 grid gap-6 max-w-2xl">
                        <div class="space-y-2">
                            <x-ui.label for="tiktok_pixel_id">TikTok Pixel ID</x-ui.label>
                            <x-ui.input id="tiktok_pixel_id" name="tiktok_pixel_id" value="{{ old('tiktok_pixel_id', $setting->tiktok_pixel_id) }}" placeholder="C0XXXXXXXXXXXXXXX" />
                            <p class="text-[0.8rem] text-muted-foreground">ads.tiktok.com → Asset → Events → Web Events → Pixel kimliği.</p>
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="tiktok_capi_access_token">Events API Access Token</x-ui.label>
                            <x-ui.input id="tiktok_capi_access_token" name="tiktok_capi_access_token" type="password" placeholder="{{ $setting->exists && $setting->tiktok_capi_access_token ? '••••••••• (mevcut değer korunur)' : 'access token' }}" />
                            <p class="text-[0.8rem] text-muted-foreground">Pixel ayarları → Events API → Access Token oluştur. Sunucu-taraflı event'ler için.</p>
                        </div>
                    </div>
                </x-ui.card>
            </div>

            {{-- ═══════════════════════ SUNUCU-TARAFLI (CAPI) ═══════════════════════ --}}
            <div x-show="tab === 'sunucu'" x-cloak class="grid gap-6">
                <x-ui.card>
                    <div class="p-6 border-b flex flex-col space-y-1.5">
                        <div class="flex items-center gap-2">
                            <x-lucide-server class="h-5 w-5 text-violet-600" />
                            <h3 class="font-semibold tracking-tight">Sunucu-taraflı Event'ler</h3>
                        </div>
                        <p class="text-sm text-muted-foreground">iOS 14.5+ & ad-blocker kayıpları için sunucu-taraflı dönüşüm gönderimi.</p>
                    </div>
                    <div class="p-6 grid gap-6 max-w-2xl">
                        <div class="space-y-2">
                            <x-ui.label for="ga4_api_secret">GA4 Measurement Protocol API Secret</x-ui.label>
                            <x-ui.input id="ga4_api_secret" name="ga4_api_secret" type="password" placeholder="{{ $setting->exists && $setting->ga4_api_secret ? '••••••••• (mevcut değer korunur)' : 'API secret' }}" />
                            <p class="text-[0.8rem] text-muted-foreground">analytics.google.com → Yönetici → Veri akışı → Measurement Protocol API secrets.</p>
                        </div>

                        <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                            <p class="font-semibold mb-1 flex items-center gap-2"><x-lucide-info class="h-4 w-4" /> Sunucu-taraflı event gönderimi</p>
                            <p>Sipariş tamamlandığında <code class="text-xs bg-amber-100 px-1 py-0.5 rounded">OrderCompleted</code> event'i otomatik olarak şu kanallara gönderilir:</p>
                            <ul class="list-disc pl-5 mt-2 space-y-0.5 text-xs">
                                <li>Meta CAPI (Pixel ID + Access Token doluysa)</li>
                                <li>GA4 Measurement Protocol (Measurement ID + API Secret doluysa)</li>
                                <li>TikTok Events API (Pixel ID + Access Token doluysa)</li>
                            </ul>
                            <p class="mt-2 text-xs">Üstteki "Sunucu-taraflı dönüşüm aktif" anahtarı kapatılırsa hiçbir sunucu event'i gönderilmez.</p>
                        </div>
                    </div>
                </x-ui.card>
            </div>

            {{-- Kaydet Butonu --}}
            <div class="flex items-center justify-end border-t pt-6 pb-12 sticky bottom-0 bg-white/95 backdrop-blur-sm -mx-2 px-2 z-10">
                <x-ui.button type="submit">
                    <x-lucide-save class="mr-2 h-4 w-4" /> Tüm Ayarları Kaydet
                </x-ui.button>
            </div>
        </form>
    </div>
</x-layouts.admin>
