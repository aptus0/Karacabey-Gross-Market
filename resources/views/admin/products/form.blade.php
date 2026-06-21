<x-layouts.admin :header="$product->exists ? 'Ürün Düzenle' : 'Yeni Ürün Ekle'">
    <div class="flex flex-col gap-6 max-w-4xl mx-auto w-full" x-data="productFormImageSuggest" data-csrf-token="{{ csrf_token() }}">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold tracking-tight text-slate-900">{{ $product->exists ? 'Ürün Düzenle' : 'Yeni Ürün Ekle' }}</h2>
                <p class="text-sm text-slate-500">{{ $product->exists ? 'Ürünün bilgilerini güncelleyin.' : 'Kataloga yeni bir ürün ekleyin.' }}</p>
            </div>
            <x-ui.button as="a" href="{{ route('admin.products.index') }}" variant="outline" class="rounded-md">
                <x-lucide-arrow-left class="mr-2 h-4 w-4" /> Ürünlere Dön
            </x-ui.button>
        </div>

        <form action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}"
              method="POST"
              enctype="multipart/form-data">
            @csrf
            @if($product->exists)
                @method('PUT')
            @endif

            @if($errors->any())
                <div class="rounded-md border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-5">
                {{-- Genel Bilgiler --}}
                <x-ui.card class="rounded-lg">
                    <div class="border-b px-6 py-4">
                        <h3 class="text-sm font-semibold text-slate-800">Genel Bilgiler</h3>
                    </div>
                    <div class="p-6 grid gap-5 md:grid-cols-2">
                        <div class="space-y-1.5 md:col-span-2">
                            <x-ui.label for="name">Ürün Adı *</x-ui.label>
                            <x-ui.input id="name" name="name" value="{{ old('name', $product->name) }}" required placeholder="Örn: Günlük Süt 1 L" />
                        </div>
                        <div class="space-y-1.5 md:col-span-2">
                            <x-ui.label for="slug">URL (Slug)</x-ui.label>
                            <x-ui.input id="slug" name="slug" value="{{ old('slug', $product->slug) }}" placeholder="Boş bırakırsanız otomatik oluşturulur" />
                        </div>
                        <div class="space-y-1.5 md:col-span-2">
                            <x-ui.label for="description">Açıklama</x-ui.label>
                            <x-ui.textarea id="description" name="description" placeholder="Ürün hakkında detaylı açıklama...">{{ old('description', $product->description) }}</x-ui.textarea>
                        </div>
                        <div class="space-y-1.5">
                            <x-ui.label for="brand">Marka</x-ui.label>
                            <x-ui.input id="brand" name="brand" value="{{ old('brand', $product->brand) }}" placeholder="Örn: KGM" />
                        </div>
                        <div class="space-y-1.5">
                            <x-ui.label for="categories">Kategoriler</x-ui.label>
                            <x-ui.select id="categories" name="category_ids[]" multiple class="h-24">
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected(in_array($category->id, old('category_ids', $product->categories->pluck('id')->all())))>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Fiyat & Stok --}}
                <x-ui.card class="rounded-lg">
                    <div class="border-b px-6 py-4">
                        <h3 class="text-sm font-semibold text-slate-800">Fiyat & Stok</h3>
                    </div>
                    <div class="p-6 grid gap-5 md:grid-cols-2">
                        <div class="space-y-1.5">
                            <x-ui.label for="price_cents">Satış Fiyatı (kuruş) *</x-ui.label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-slate-400 text-sm">₺</span>
                                <x-ui.input id="price_cents" name="price_cents" type="number" min="0" value="{{ old('price_cents', $product->price_cents ?? 0) }}" class="pl-8" required />
                            </div>
                            <p class="text-xs text-slate-400">1 TL = 100 kuruş. Örn: 29,90 ₺ için 2990 girin.</p>
                        </div>
                        <div class="space-y-1.5">
                            <x-ui.label for="compare_at_price_cents">Karşılaştırma Fiyatı (kuruş)</x-ui.label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-slate-400 text-sm">₺</span>
                                <x-ui.input id="compare_at_price_cents" name="compare_at_price_cents" type="number" min="0" value="{{ old('compare_at_price_cents', $product->compare_at_price_cents) }}" class="pl-8" />
                            </div>
                        </div>
                        <div class="space-y-1.5">
                            <x-ui.label for="stock_quantity">Stok Adedi *</x-ui.label>
                            <x-ui.input id="stock_quantity" name="stock_quantity" type="number" min="0" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" required />
                        </div>
                        <div class="space-y-1.5">
                            <x-ui.label for="barcode">Barkod (SKU / EAN)</x-ui.label>
                            <x-ui.input id="barcode" name="barcode" value="{{ old('barcode', $product->barcode) }}" placeholder="Örn: 8690000000000" />
                        </div>
                    </div>
                </x-ui.card>

                {{-- Ürün Görseli --}}
                <x-ui.card class="rounded-lg">
                    <div class="flex items-center justify-between gap-3 border-b px-6 py-4">
                        <h3 class="text-sm font-semibold text-slate-800">Ürün Görseli</h3>
                        @if($product->exists)
                            <button type="button"
                                    @click="openImageSuggest({{ $product->id }}, @js($product->name))"
                                    class="inline-flex h-8 items-center gap-1.5 rounded-md border border-violet-200 bg-violet-50 px-3 text-xs font-bold text-violet-700 transition hover:bg-violet-100">
                                <x-lucide-sparkles class="h-3.5 w-3.5" />
                                AI ile görsel öner
                            </button>
                        @endif
                    </div>
                    <div class="p-6 space-y-4">
                        {{-- Mevcut görsel önizleme --}}
                        @if($product->image_url)
                            <div class="flex items-center gap-4 rounded-md border border-slate-200 bg-slate-50 p-3">
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}"
                                     class="h-20 w-20 rounded-md object-cover border border-slate-200">
                                <div>
                                    <p class="text-sm font-medium text-slate-700">Mevcut Görsel</p>
                                    <p class="mt-0.5 text-xs text-slate-400 break-all">{{ $product->image_url }}</p>
                                </div>
                            </div>
                        @endif

                        {{-- Dosya yükleme alanı --}}
                        <div x-data="imageUpload" class="space-y-3">
                            <label
                                for="image_file"
                                class="flex flex-col items-center justify-center w-full h-36 rounded-md border-2 border-dashed border-slate-300 bg-slate-50 cursor-pointer transition hover:border-orange-400 hover:bg-orange-50"
                                @dragover.prevent="dragging = true"
                                @dragleave.prevent="dragging = false"
                                @drop.prevent="onDrop($event)"
                                :class="dragging ? 'border-orange-400 bg-orange-50' : ''"
                            >
                                <template x-if="!preview">
                                    <div class="flex flex-col items-center gap-2 text-slate-400">
                                        <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>
                                        <p class="text-sm font-medium">Görsel yüklemek için tıklayın veya sürükleyin</p>
                                        <p class="text-xs">JPG, PNG, WEBP — maks. 4 MB</p>
                                    </div>
                                </template>
                                <template x-if="preview">
                                    <img :src="preview" class="h-full w-auto max-w-full rounded object-contain p-2">
                                </template>
                                <input id="image_file" name="image_file" type="file"
                                       accept="image/jpeg,image/png,image/webp"
                                       class="hidden"
                                       @change="onFileChange($event)">
                            </label>

                            <template x-if="preview">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-emerald-600 font-medium">✓ Yeni görsel seçildi</span>
                                    <button type="button" @click="clearPreview()" class="text-xs text-rose-500 hover:underline">Kaldır</button>
                                </div>
                            </template>
                        </div>

                        {{-- Alternatif: URL ile yükleme --}}
                        <div class="space-y-1.5">
                            <x-ui.label for="image_url" class="text-xs text-slate-500">Veya görsel URL girin (opsiyonel)</x-ui.label>
                            <x-ui.input id="image_url" name="image_url" type="url" value="{{ old('image_url', $product->image_url) }}" placeholder="https://..." />
                            <p class="text-xs text-slate-400">Dosya yükleme önceliklidir. Her ikisi doluysa yüklenen dosya kullanılır.</p>
                        </div>
                    </div>
                </x-ui.card>

                {{-- SEO --}}
                <x-ui.card class="rounded-lg">
                    <div class="border-b px-6 py-4">
                        <h3 class="text-sm font-semibold text-slate-800">SEO Ayarları</h3>
                    </div>
                    <div class="p-6 grid gap-5 md:grid-cols-2">
                        <div class="space-y-1.5 md:col-span-2">
                            <x-ui.label for="seo_title">SEO Başlığı</x-ui.label>
                            <x-ui.input id="seo_title" name="seo_title" value="{{ old('seo_title', $product->seo['title'] ?? '') }}" placeholder="Meta Title" />
                        </div>
                        <div class="space-y-1.5 md:col-span-2">
                            <x-ui.label for="seo_description">SEO Açıklaması</x-ui.label>
                            <x-ui.textarea id="seo_description" name="seo_description" placeholder="Meta Description">{{ old('seo_description', $product->seo['description'] ?? '') }}</x-ui.textarea>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Kaydet --}}
                <div class="flex items-center justify-between border-t pt-5 pb-12">
                    <div class="flex items-center gap-2">
                        <x-ui.checkbox id="is_active" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true)) />
                        <x-ui.label for="is_active" class="cursor-pointer">Ürün aktif ve görünür</x-ui.label>
                    </div>
                    <div class="flex gap-3">
                        <x-ui.button type="button" variant="ghost" as="a" href="{{ route('admin.products.index') }}">İptal</x-ui.button>
                        <x-ui.button type="submit" class="rounded-md">
                            <x-lucide-save class="mr-2 h-4 w-4" /> Ürünü Kaydet
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </form>

        {{-- ─── Görsel Önerisi Modal ─────────────────────────────── --}}
        <div x-show="imageModal.open"
             x-cloak
             x-transition.opacity
             class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/60 backdrop-blur-sm px-3 sm:px-6"
             @keydown.escape.window="closeImageSuggest()">
            <div class="w-full max-w-3xl overflow-hidden rounded-t-2xl sm:rounded-2xl bg-white shadow-2xl"
                 @click.outside="closeImageSuggest()">
                <div class="flex items-start justify-between gap-3 border-b border-slate-200 bg-linear-to-br from-orange-50 to-white px-5 py-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-orange-700">
                            <x-lucide-sparkles class="h-3.5 w-3.5" />
                            Görsel önerisi
                        </div>
                        <h2 class="mt-1 truncate text-lg font-black text-slate-900" x-text="imageModal.productName"></h2>
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">URL Arama ile adaylar toplanır · <span class="font-black text-violet-700">Gemini AI</span> en uygun görseli seçer · Tıklayın, ürüne uygulansın.</p>
                    </div>
                    <button type="button" @click="closeImageSuggest()"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-500 hover:bg-slate-100 hover:text-slate-900">
                        <x-lucide-x class="h-4 w-4" />
                    </button>
                </div>

                <div class="px-5 py-5">
                    <template x-if="imageModal.loading">
                        <div class="flex items-center justify-center py-12 text-slate-500">
                            <x-lucide-loader-circle class="h-6 w-6 animate-spin text-orange-500" />
                            <span class="ml-2 text-sm font-semibold">Adaylar getiriliyor…</span>
                        </div>
                    </template>

                    <template x-if="!imageModal.loading && imageModal.currentImageUrl">
                        <div class="mb-4 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <img :src="imageModal.currentImageUrl" alt="Mevcut görsel" class="h-14 w-14 rounded-md border border-slate-200 bg-white object-cover">
                            <div class="min-w-0 text-xs">
                                <div class="font-black text-slate-700">Mevcut görsel</div>
                                <div class="truncate text-slate-500" x-text="imageModal.currentImageUrl"></div>
                            </div>
                        </div>
                    </template>

                    <template x-if="!imageModal.loading && imageModal.error">
                        <div class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">
                            <x-lucide-triangle-alert class="mt-0.5 h-4 w-4" />
                            <span x-text="imageModal.error"></span>
                        </div>
                    </template>

                    <template x-if="imageModal.success">
                        <div class="mb-4 flex items-start gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800">
                            <x-lucide-circle-check class="mt-0.5 h-4 w-4" />
                            <span x-text="imageModal.success"></span>
                        </div>
                    </template>

                    <template x-if="!imageModal.loading && imageModal.candidates.length > 0">
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            <template x-for="(cand, i) in imageModal.candidates" :key="i">
                                <button type="button"
                                        @click="applyImageCandidate(cand)"
                                        :disabled="imageModal.applying"
                                        class="group relative flex flex-col overflow-hidden rounded-lg border border-slate-200 bg-white text-left transition hover:border-orange-400 hover:shadow-md disabled:opacity-50">
                                    <div class="relative aspect-square overflow-hidden bg-slate-100">
                                        <template x-if="!cand.failed">
                                            <img :src="cand.thumb || cand.url" :alt="cand.title || 'aday'" class="h-full w-full object-contain transition group-hover:scale-105" loading="lazy" x-on:error="markImageCandidateBroken(cand)">
                                        </template>
                                        <template x-if="cand.failed">
                                            <div class="flex h-full w-full items-center justify-center text-slate-400">
                                                <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="3" x2="21" y2="21"></line><path d="M10.5 10.5 15 15"></path><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L14 13"></path><path d="M3 13.518V19a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-4.5"></path><path d="M3 8v-.5A2.5 2.5 0 0 1 5.5 5h13a2.5 2.5 0 0 1 2.5 2.5V8"></path><circle cx="8.5" cy="8.5" r="1.5"></circle></svg>
                                            </div>
                                        </template>
                                        <div class="absolute right-1 top-1 rounded-full px-2 py-0.5 text-[10px] font-black uppercase tracking-wider"
                                             :class="{
                                                 'bg-violet-600 text-white': cand.ai_ranker === 'gemini' || cand.source === 'gemini',
                                                 'bg-emerald-600 text-white': cand.ai_ranker !== 'gemini' && cand.source === 'web_page',
                                                 'bg-slate-900 text-white': cand.source === 'google',
                                             }"
                                             x-text="cand.ai_ranker === 'gemini' ? 'Gemini AI' : ({ web_page: 'URL', gemini: 'Gemini', google: 'Google' }[cand.source] || cand.source)"></div>
                                        <template x-if="typeof cand.ai_score === 'number'">
                                            <div class="absolute left-1 top-1 inline-flex items-center gap-1 rounded-full bg-white/90 px-1.5 py-0.5 text-[10px] font-black text-slate-800 backdrop-blur-sm">
                                                <x-lucide-sparkles class="h-3 w-3 text-violet-600" />
                                                <span x-text="cand.ai_score + '%'"></span>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="flex flex-1 flex-col gap-1 p-2">
                                        <div class="truncate text-xs font-bold text-slate-700" x-text="cand.title || cand.url"></div>
                                        <div class="inline-flex items-center justify-center gap-1 rounded-md bg-orange-50 px-2 py-1 text-[11px] font-black text-orange-700 group-hover:bg-orange-100">
                                            <template x-if="imageModal.applying"><x-lucide-loader-circle class="h-3 w-3 animate-spin" /></template>
                                            <template x-if="!imageModal.applying"><x-lucide-check class="h-3 w-3" /></template>
                                            <span x-text="imageModal.applying ? 'Uygulanıyor…' : (cand.failed ? 'Yine de Seç' : 'Bunu seç')"></span>
                                        </div>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>

                <div class="border-t border-slate-200 bg-slate-50 px-5 py-3 text-right">
                    <button type="button" @click="closeImageSuggest()"
                            class="inline-flex h-9 items-center rounded-md border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 hover:bg-slate-100">
                        Kapat
                    </button>
                </div>
            </div>
        </div>
    </div>

</x-layouts.admin>
