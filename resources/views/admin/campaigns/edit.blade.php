<x-layouts.admin header="Kampanya Düzenle">
    <div class="max-w-3xl mx-auto flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">{{ $campaign->name }}</h2>
                <p class="text-muted-foreground text-sm">/kampanyalar/{{ $campaign->slug }}</p>
            </div>
            <a href="{{ route('admin.campaigns.index') }}"
               class="inline-flex h-9 items-center rounded-lg border px-4 text-sm font-semibold hover:bg-muted transition">
                ← Geri
            </a>
        </div>

        @if(session('status'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-700">
            {{ session('status') }}
        </div>
        @endif

        <x-ui.card>
            <form action="{{ route('admin.campaigns.update', $campaign) }}" method="POST" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="p-6 grid gap-4 md:grid-cols-2">
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="name">Kampanya Adı *</x-ui.label>
                        <x-ui.input id="name" name="name" required value="{{ old('name', $campaign->name) }}" />
                    </div>
                    <div class="space-y-2">
                        <x-ui.label for="slug">URL Uzantısı (Slug)</x-ui.label>
                        <x-ui.input id="slug" name="slug" value="{{ old('slug', $campaign->slug) }}" />
                        <p class="text-xs text-muted-foreground">/kampanyalar/<strong>{{ $campaign->slug }}</strong></p>
                    </div>
                    <div class="space-y-2">
                        <x-ui.label for="badge_label">Rozet Etiketi</x-ui.label>
                        <x-ui.input id="badge_label" name="badge_label" value="{{ old('badge_label', $campaign->badge_label) }}" placeholder="Sınırlı Süre" maxlength="60" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="description">Kısa Açıklama</x-ui.label>
                        <x-ui.textarea id="description" name="description" rows="2">{{ old('description', $campaign->description) }}</x-ui.textarea>
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="body">Detaylı İçerik (Sayfa Gövdesi)</x-ui.label>
                        <x-ui.textarea id="body" name="body" rows="6">{{ old('body', $campaign->body) }}</x-ui.textarea>
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="banner_image">Kapak Görseli Yükle (Önerilen: 1200x630)</x-ui.label>
                        <x-ui.input id="banner_image" name="banner_image" type="file" accept=".jpg,.jpeg,.png,.webp" />
                        @if($campaign->banner_url)
                        <img src="{{ $campaign->banner_url }}" alt="Önizleme" class="mt-2 h-32 w-full rounded-lg object-cover" />
                        @endif
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="banner_image_url">Kapak Görseli URL (Harici görsel kullanacaksanız)</x-ui.label>
                        <x-ui.input id="banner_image_url" name="banner_image_url" type="url" value="{{ old('banner_image_url', $campaign->banner_image_url) }}" placeholder="https://..." />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="meta_image_url">OG / Meta Görsel URL</x-ui.label>
                        <x-ui.input id="meta_image_url" name="meta_image_url" type="url" value="{{ old('meta_image_url', $campaign->meta_image_url) }}" placeholder="https://..." />
                    </div>
                    <div class="space-y-2">
                        <x-ui.label for="color_hex">Kampanya Rengi</x-ui.label>
                        <div class="flex gap-2">
                            <input type="color" name="color_hex" id="color_hex" value="{{ old('color_hex', $campaign->color_hex ?? '#FF7A00') }}" class="h-10 w-14 rounded-lg border cursor-pointer" />
                            <span class="h-10 flex items-center px-3 rounded-lg border bg-muted text-sm font-mono">{{ $campaign->color_hex ?? '#FF7A00' }}</span>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <x-ui.label for="sort_order">Sıralama</x-ui.label>
                        <x-ui.input id="sort_order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $campaign->sort_order) }}" />
                    </div>
                    <div class="space-y-2">
                        <x-ui.label for="discount_type">İndirim Tipi</x-ui.label>
                        <x-ui.select id="discount_type" name="discount_type">
                            <option value="percent" @selected(old('discount_type', $campaign->discount_type) === 'percent')>Yüzde (%)</option>
                            <option value="fixed" @selected(old('discount_type', $campaign->discount_type) === 'fixed')>Sabit Tutar (₺)</option>
                        </x-ui.select>
                    </div>
                    <div class="space-y-2">
                        <x-ui.label for="discount_value">İndirim Değeri *</x-ui.label>
                        <x-ui.input id="discount_value" name="discount_value" type="number" min="0" value="{{ old('discount_value', $campaign->discount_value) }}" required />
                    </div>
                    <div class="space-y-2">
                        <x-ui.label for="starts_at">Başlangıç Tarihi</x-ui.label>
                        <x-ui.input id="starts_at" name="starts_at" type="datetime-local" value="{{ old('starts_at', $campaign->starts_at?->format('Y-m-d\TH:i')) }}" />
                    </div>
                    <div class="space-y-2">
                        <x-ui.label for="ends_at">Bitiş Tarihi</x-ui.label>
                        <x-ui.input id="ends_at" name="ends_at" type="datetime-local" value="{{ old('ends_at', $campaign->ends_at?->format('Y-m-d\TH:i')) }}" />
                    </div>
                    <div class="space-y-2 md:col-span-2 border-t pt-4">
                        <p class="text-sm font-semibold">SEO Ayarları</p>
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="seo_title">SEO Başlığı</x-ui.label>
                        <x-ui.input id="seo_title" name="seo_title" value="{{ old('seo_title', $campaign->seo['title'] ?? '') }}" placeholder="Boş bırakılırsa kampanya adı kullanılır" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="seo_description">SEO Açıklaması (Meta Description)</x-ui.label>
                        <x-ui.textarea id="seo_description" name="seo_description" rows="2">{{ old('seo_description', $campaign->seo['description'] ?? '') }}</x-ui.textarea>
                    </div>
                    <div class="flex items-center space-x-2 md:col-span-2 pt-2">
                        <x-ui.checkbox id="is_active" name="is_active" value="1" @checked($campaign->is_active) />
                        <x-ui.label for="is_active" class="cursor-pointer">Kampanya aktif</x-ui.label>
                    </div>
                    <div class="flex items-center gap-5 md:col-span-2">
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <x-ui.checkbox name="show_on_web" value="1" @checked($campaign->show_on_web) />
                            Web
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                            <x-ui.checkbox name="show_on_mobile" value="1" @checked($campaign->show_on_mobile) />
                            Mobil
                        </label>
                    </div>
                </div>
                <div class="p-6 border-t bg-muted/20 flex gap-3">
                    <x-ui.button type="submit" class="flex-1">Kaydet</x-ui.button>
                    <a href="{{ route('admin.campaigns.index') }}"
                       class="inline-flex h-10 items-center rounded-lg border px-6 text-sm font-semibold hover:bg-muted transition">
                        İptal
                    </a>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-layouts.admin>
