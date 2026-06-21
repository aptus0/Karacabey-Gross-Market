<x-layouts.admin header="Kampanyalar & Kuponlar">
    <div class="flex flex-col gap-6">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Kampanyalar & Kuponlar</h2>
                <p class="text-muted-foreground">SEO kampanyaları, kuponlar ve mobil ana sayfa slider içeriklerini yönetin.</p>
            </div>
        </div>

        <div class="rounded-xl border border-orange-200 bg-orange-50 p-4 text-sm text-orange-900">
            Kapak görseli ve aktif kampanya kayıtları mobil ana sayfa sliderında kullanılır. Önerilen mobil hero oranı 16:9; başlık kısa, görsel ürün odaklı olmalıdır.
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Create Campaign Form --}}
            <x-ui.card>
                <div class="p-6 border-b">
                    <h3 class="font-semibold tracking-tight">Yeni Kampanya Ekle</h3>
                    <p class="text-sm text-muted-foreground mt-1">Kampanya ekledikten sonra web kampanya sayfasında ve mobil slider akışında görünür.</p>
                </div>
                <form action="{{ route('admin.campaigns.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="p-6 grid gap-4 md:grid-cols-2">
                        <div class="space-y-2 md:col-span-2">
                            <x-ui.label for="name">Kampanya Adı *</x-ui.label>
                            <x-ui.input id="name" name="name" required placeholder="Örn: Yaz İndirimi %20" />
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="slug">URL Uzantısı (Slug)</x-ui.label>
                            <x-ui.input id="slug" name="slug" placeholder="yaz-indirimi" />
                            <p class="text-xs text-muted-foreground">/kampanyalar/<strong>yaz-indirimi</strong></p>
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="badge_label">Rozet Etiketi</x-ui.label>
                            <x-ui.input id="badge_label" name="badge_label" placeholder="Sınırlı Süre" maxlength="60" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <x-ui.label for="description">Kısa Açıklama (Liste & SEO)</x-ui.label>
                            <x-ui.textarea id="description" name="description" rows="2" placeholder="Kampanya hakkında kısa bir açıklama..."></x-ui.textarea>
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <x-ui.label for="body">Detaylı İçerik (Sayfa Gövdesi)</x-ui.label>
                            <x-ui.textarea id="body" name="body" rows="5" placeholder="Kampanya detayları, kurallar, ürün listeleri..."></x-ui.textarea>
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <x-ui.label for="banner_image">Kapak Görseli Yükle (Önerilen: 1200x630)</x-ui.label>
                            <x-ui.input id="banner_image" name="banner_image" type="file" accept=".jpg,.jpeg,.png,.webp" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <x-ui.label for="banner_image_url">Kapak Görseli URL (Harici)</x-ui.label>
                            <x-ui.input id="banner_image_url" name="banner_image_url" type="url" placeholder="https://..." />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <x-ui.label for="meta_image_url">OG / Meta Görsel URL (Sosyal Medya)</x-ui.label>
                            <x-ui.input id="meta_image_url" name="meta_image_url" type="url" placeholder="https://..." />
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="color_hex">Kampanya Rengi</x-ui.label>
                            <div class="flex gap-2">
                                <input type="color" name="color_hex" id="color_hex" value="#FF7A00" class="h-10 w-14 rounded-lg border cursor-pointer" />
                                <x-ui.input id="color_hex_text" name="color_hex_text" value="#FF7A00" placeholder="#FF7A00" class="font-mono" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="sort_order">Sıralama</x-ui.label>
                            <x-ui.input id="sort_order" name="sort_order" type="number" min="0" value="0" />
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="discount_type">İndirim Tipi</x-ui.label>
                            <x-ui.select id="discount_type" name="discount_type">
                                <option value="percent">Yüzde (%)</option>
                                <option value="fixed">Sabit Tutar (₺)</option>
                            </x-ui.select>
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="discount_value">İndirim Değeri *</x-ui.label>
                            <x-ui.input id="discount_value" name="discount_value" type="number" min="0" value="0" required />
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="starts_at">Başlangıç Tarihi</x-ui.label>
                            <x-ui.input id="starts_at" name="starts_at" type="datetime-local" />
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="ends_at">Bitiş Tarihi</x-ui.label>
                            <x-ui.input id="ends_at" name="ends_at" type="datetime-local" />
                        </div>
                        <div class="space-y-2 md:col-span-2 border-t pt-4">
                            <p class="text-sm font-semibold">SEO Ayarları</p>
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <x-ui.label for="seo_title">SEO Başlığı</x-ui.label>
                            <x-ui.input id="seo_title" name="seo_title" placeholder="Boş bırakılırsa kampanya adı kullanılır" />
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <x-ui.label for="seo_description">SEO Açıklaması (Meta Description)</x-ui.label>
                            <x-ui.textarea id="seo_description" name="seo_description" rows="2" placeholder="Google arama sonuçlarında görünen açıklama..."></x-ui.textarea>
                        </div>
                        <div class="flex items-center space-x-2 md:col-span-2 pt-2">
                            <x-ui.checkbox id="campaign_is_active" name="is_active" value="1" checked />
                            <x-ui.label for="campaign_is_active" class="cursor-pointer">Kampanya aktif edilsin</x-ui.label>
                        </div>
                        <div class="flex items-center gap-5 md:col-span-2">
                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <x-ui.checkbox name="show_on_web" value="1" checked />
                                Web
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                <x-ui.checkbox name="show_on_mobile" value="1" checked />
                                Mobil
                            </label>
                        </div>
                    </div>
                    <div class="p-6 border-t bg-muted/20">
                        <x-ui.button type="submit" class="w-full">Kampanya Oluştur</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            {{-- Create Coupon Form --}}
            <x-ui.card>
                <div class="p-6 border-b">
                    <h3 class="font-semibold tracking-tight">Yeni Kupon Ekle</h3>
                </div>
                <form action="{{ route('admin.coupons.store') }}" method="POST">
                    @csrf
                    <div class="p-6 grid gap-4 md:grid-cols-2">
                        <div class="space-y-2 md:col-span-2">
                            <x-ui.label for="campaign_id">Bağlı Kampanya (İsteğe Bağlı)</x-ui.label>
                            <x-ui.select id="campaign_id" name="campaign_id">
                                <option value="">Bağımsız Kupon (Kampanyasız)</option>
                                @foreach($campaigns as $campaign)
                                    <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="space-y-2 md:col-span-2">
                            <x-ui.label for="code">Kupon Kodu *</x-ui.label>
                            <x-ui.input id="code" name="code" required placeholder="Örn: YAZ2026" class="uppercase" />
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="coupon_discount_type">İndirim Tipi</x-ui.label>
                            <x-ui.select id="coupon_discount_type" name="discount_type">
                                <option value="percent">Yüzde (%)</option>
                                <option value="fixed">Sabit Tutar (₺)</option>
                            </x-ui.select>
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="coupon_discount_value">İndirim Değeri *</x-ui.label>
                            <x-ui.input id="coupon_discount_value" name="discount_value" type="number" min="0" value="0" required />
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="minimum_order_cents">Min. Sipariş (Kuruş)</x-ui.label>
                            <x-ui.input id="minimum_order_cents" name="minimum_order_cents" type="number" min="0" value="0" />
                            <p class="text-xs text-muted-foreground">1000 = 10 ₺</p>
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="usage_limit">Kullanım Limiti</x-ui.label>
                            <x-ui.input id="usage_limit" name="usage_limit" type="number" min="1" placeholder="Sınırsız" />
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="coupon_starts_at">Başlangıç</x-ui.label>
                            <x-ui.input id="coupon_starts_at" name="starts_at" type="datetime-local" />
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="coupon_ends_at">Bitiş</x-ui.label>
                            <x-ui.input id="coupon_ends_at" name="ends_at" type="datetime-local" />
                        </div>
                        <div class="flex items-center space-x-2 md:col-span-2 pt-2">
                            <x-ui.checkbox id="coupon_is_active" name="is_active" value="1" checked />
                            <x-ui.label for="coupon_is_active" class="cursor-pointer">Kupon aktif edilsin</x-ui.label>
                        </div>
                    </div>
                    <div class="p-6 border-t bg-muted/20">
                        <x-ui.button type="submit" class="w-full">Kupon Oluştur</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </div>

        {{-- Campaigns List --}}
        <x-ui.card>
            <div class="p-6 border-b flex items-center justify-between">
                <h3 class="font-semibold tracking-tight">Aktif & Pasif Kampanyalar</h3>
                <span class="text-sm text-muted-foreground">{{ $campaigns->total() }} toplam</span>
            </div>
            <div class="divide-y">
                @forelse($campaigns as $campaign)
                <div class="flex items-center gap-4 p-4">
                    @if($campaign->banner_url)
                    <img src="{{ $campaign->banner_url }}" alt="{{ $campaign->name }}"
                         class="h-14 w-20 rounded-lg object-cover shrink-0 bg-muted" />
                    @else
                    <div class="h-14 w-20 rounded-lg shrink-0 flex items-center justify-center text-white text-xs font-bold"
                         style="background-color: {{ $campaign->color_hex ?? '#FF7A00' }}">
                        {{ $campaign->discount_label }}
                    </div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <strong class="truncate text-sm">{{ $campaign->name }}</strong>
                            @if($campaign->is_active)
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">Aktif</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">Pasif</span>
                            @endif
                        </div>
                        <p class="text-xs text-muted-foreground mt-0.5 truncate">
                            /kampanyalar/{{ $campaign->slug }} &mdash; {{ $campaign->discount_label }}
                            @if($campaign->ends_at) &mdash; {{ $campaign->ends_at->format('d.m.Y') }}'e kadar @endif
                        </p>
                        <p class="text-xs text-muted-foreground">{{ $campaign->coupons_count }} kupon bağlı</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('admin.campaigns.edit', $campaign) }}"
                           class="inline-flex h-8 items-center rounded-lg border px-3 text-xs font-semibold hover:bg-muted transition">
                            Düzenle
                        </a>
                        <form action="{{ route('admin.campaigns.destroy', $campaign) }}" method="POST"
                              data-confirm-submit="Kampanyayı silmek istediğinize emin misiniz?">
                            @csrf @method('DELETE')
                            <button type="submit"
                                    class="inline-flex h-8 items-center rounded-lg border border-red-200 px-3 text-xs font-semibold text-red-600 hover:bg-red-50 transition">
                                Sil
                            </button>
                        </form>
                    </div>
                </div>
                @empty
                <div class="p-8 text-center text-sm text-muted-foreground">Henüz kampanya eklenmedi.</div>
                @endforelse
            </div>
            @if($campaigns->hasPages())
            <div class="p-4 border-t">{{ $campaigns->links() }}</div>
            @endif
        </x-ui.card>

        {{-- Coupons List --}}
        <x-ui.card>
            <div class="p-6 border-b">
                <h3 class="font-semibold tracking-tight">Kuponlar</h3>
            </div>
            <x-ui.table>
                <x-slot:head>
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Kod</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Kampanya</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">İndirim</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Min. Sipariş</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Bitiş</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Durum</th>
                    </tr>
                </x-slot:head>
                <x-slot:body>
                    @forelse($coupons as $coupon)
                    <tr class="border-t hover:bg-muted/30">
                        <td class="px-4 py-3 font-mono font-bold text-sm">{{ $coupon->code }}</td>
                        <td class="px-4 py-3 text-sm text-muted-foreground">{{ $coupon->campaign?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">
                            {{ $coupon->discount_type === 'percent' ? "%{$coupon->discount_value}" : number_format($coupon->discount_value / 100, 2, ',', '.') . ' ₺' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-muted-foreground">
                            {{ $coupon->minimum_order_cents > 0 ? number_format($coupon->minimum_order_cents / 100, 2, ',', '.') . ' ₺' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm text-muted-foreground">
                            {{ $coupon->ends_at?->format('d.m.Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if($coupon->is_active)
                                <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-700">Aktif</span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-500">Pasif</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-muted-foreground">Henüz kupon eklenmedi.</td></tr>
                    @endforelse
                </x-slot:body>
            </x-ui.table>
            @if($coupons->hasPages())
            <div class="p-4 border-t">{{ $coupons->links() }}</div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.admin>
