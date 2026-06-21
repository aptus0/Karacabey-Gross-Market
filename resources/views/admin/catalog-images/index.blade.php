<x-layouts.admin header="Katalog Görsel Atölyesi">
    <div
        class="space-y-5"
        x-data="catalogImageWorkbench"
        data-endpoint="{{ route('admin.catalog-images.batch') }}"
        data-csrf-token="{{ csrf_token() }}"
        data-initial-cursor="0"
        data-query="{{ request('q', '') }}"
        data-category-id="{{ request('category_id', '') }}"
        data-batch-size="3"
    >
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-violet-200 bg-violet-50 px-3 py-1 text-xs font-black uppercase tracking-wider text-violet-700">
                    <x-lucide-sparkles class="h-3.5 w-3.5" />
                    Gemini AI · URL Arama · Google CSE
                </div>
                <h2 class="mt-3 text-2xl font-black tracking-tight text-slate-950">Katalog Görsel Atölyesi</h2>
                <p class="mt-1 max-w-3xl text-sm font-medium leading-6 text-slate-500">
                    Ürün adını, markayı ve barkodu analiz eder; aday görselleri URL aramasıyla toplar, Gemini AI ile en uygun olanı seçip indirir. Toplu işlem her adımda küçük partilerle ilerler.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button type="button"
                        @click="runBatch(false)"
                        :disabled="running"
                        class="inline-flex h-10 items-center gap-2 rounded-md bg-slate-950 px-4 text-sm font-black text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-60">
                    <template x-if="running"><x-lucide-loader-circle class="h-4 w-4 animate-spin" /></template>
                    <template x-if="!running"><x-lucide-play class="h-4 w-4" /></template>
                    Sıradaki Ürünleri Bul
                </button>
                <button type="button"
                        @click="toggleAuto()"
                        class="inline-flex h-10 items-center gap-2 rounded-md border px-4 text-sm font-black transition"
                        :class="autoRun ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100' : 'border-orange-200 bg-orange-50 text-orange-800 hover:bg-orange-100'">
                    <template x-if="autoRun"><x-lucide-square class="h-4 w-4" /></template>
                    <template x-if="!autoRun"><x-lucide-repeat class="h-4 w-4" /></template>
                    <span x-text="autoRun ? 'Otomatiği Durdur' : 'Otomatik Devam'"></span>
                </button>
                <a href="{{ route('admin.products.index', ['image' => 'missing']) }}"
                   class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-200 bg-white px-4 text-sm font-bold text-slate-700 transition hover:bg-slate-50">
                    <x-lucide-package-search class="h-4 w-4" />
                    Ürün Listesi
                </a>
            </div>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
            <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-xs font-black uppercase tracking-wide text-slate-400">Toplam Ürün</div>
                <div class="mt-2 text-3xl font-black text-slate-950">{{ number_format($stats['total']) }}</div>
            </div>
            <div class="rounded-md border border-amber-200 bg-amber-50 p-4 shadow-sm">
                <div class="text-xs font-black uppercase tracking-wide text-amber-700">Görseli Eksik</div>
                <div class="mt-2 text-3xl font-black text-amber-800">{{ number_format($stats['missing']) }}</div>
            </div>
            <div class="rounded-md border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
                <div class="text-xs font-black uppercase tracking-wide text-emerald-700">Görselli</div>
                <div class="mt-2 text-3xl font-black text-emerald-800">{{ number_format($stats['present']) }}</div>
            </div>
        </div>

        <div class="rounded-md border border-slate-200 bg-white shadow-sm">
            <form action="{{ route('admin.catalog-images.index') }}" method="GET" class="grid gap-3 border-b border-slate-200 p-4 lg:grid-cols-[1.5fr_1fr_1fr_1fr_auto]">
                <div class="relative">
                    <x-lucide-search class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Ürün, marka, barkod veya SKU ara" class="h-10 w-full rounded-md border border-slate-200 bg-slate-50 pl-9 pr-3 text-sm outline-none transition focus:border-orange-400 focus:bg-white focus:ring-2 focus:ring-orange-100">
                </div>
                <select name="category_id" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                    <option value="">Tüm Kategoriler</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select name="image" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                    <option value="all" @selected(request('image', 'all') === 'all')>Tüm Ürünler</option>
                    <option value="missing" @selected(request('image') === 'missing')>Sadece Eksikler</option>
                    <option value="present" @selected(request('image') === 'present')>Görselli Ürünler</option>
                </select>
                <select name="per_page" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                    @foreach([50, 100, 200] as $size)
                        <option value="{{ $size }}" @selected((int) request('per_page', 100) === $size)>{{ $size }} satır</option>
                    @endforeach
                </select>
                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-md bg-slate-900 px-4 text-sm font-bold text-white transition hover:bg-slate-800">
                        <x-lucide-filter class="h-4 w-4" /> Filtrele
                    </button>
                    <a href="{{ route('admin.catalog-images.index') }}" class="inline-flex h-10 items-center rounded-md border border-slate-200 bg-white px-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50">Temizle</a>
                </div>
            </form>

            <div class="grid gap-3 border-b border-slate-200 bg-slate-50 p-4 lg:grid-cols-[1fr_2fr]">
                <div class="rounded-md border border-slate-200 bg-white p-3">
                    <div class="flex items-center justify-between text-xs font-black uppercase tracking-wide text-slate-400">
                        <span>Batch Durumu</span>
                        <span x-text="cursor ? ('Cursor #' + cursor) : 'Başlangıç'"></span>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-md bg-slate-50 p-2">
                            <div class="text-lg font-black text-slate-950" x-text="totals.processed"></div>
                            <div class="text-[10px] font-bold text-slate-400">İşlenen</div>
                        </div>
                        <div class="rounded-md bg-emerald-50 p-2">
                            <div class="text-lg font-black text-emerald-700" x-text="totals.updated"></div>
                            <div class="text-[10px] font-bold text-emerald-600">Eklenen</div>
                        </div>
                        <div class="rounded-md bg-rose-50 p-2">
                            <div class="text-lg font-black text-rose-700" x-text="totals.failed"></div>
                            <div class="text-[10px] font-bold text-rose-600">Bulunamadı</div>
                        </div>
                    </div>
                    <div class="mt-3 text-xs font-semibold text-slate-500" x-show="message" x-text="message"></div>
                </div>

                <div class="max-h-36 overflow-auto rounded-md border border-slate-200 bg-white">
                    <template x-if="logs.length === 0">
                        <div class="px-3 py-4 text-sm font-semibold text-slate-400">Henüz batch çalışmadı.</div>
                    </template>
                    <template x-for="log in logs" :key="log.key">
                        <div class="flex items-center gap-2 border-b border-slate-100 px-3 py-2 text-xs last:border-b-0">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-full font-black"
                                  :class="log.ok ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'">
                                <span x-text="log.ok ? '✓' : '!'"></span>
                            </span>
                            <span class="w-14 shrink-0 font-black text-slate-500" x-text="'#' + log.id"></span>
                            <span class="min-w-0 flex-1 truncate font-semibold text-slate-700" x-text="log.name"></span>
                            <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 font-bold text-slate-500" x-text="log.ok ? (log.source || 'ok') : (log.message || 'bulunamadı')"></span>
                        </div>
                    </template>
                </div>
            </div>

            <div class="overflow-auto">
                <table class="w-full min-w-[1320px] border-separate border-spacing-0 text-left text-xs">
                    <thead class="sticky top-0 z-10 bg-slate-100 text-[11px] font-black uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="border-b border-r border-slate-200 px-3 py-2">ID</th>
                            <th class="border-b border-r border-slate-200 px-3 py-2">Görsel</th>
                            <th class="border-b border-r border-slate-200 px-3 py-2">Ürün Adı</th>
                            <th class="border-b border-r border-slate-200 px-3 py-2">Marka</th>
                            <th class="border-b border-r border-slate-200 px-3 py-2">Barkod</th>
                            <th class="border-b border-r border-slate-200 px-3 py-2">SKU</th>
                            <th class="border-b border-r border-slate-200 px-3 py-2">Kategori</th>
                            <th class="border-b border-r border-slate-200 px-3 py-2">Stok</th>
                            <th class="border-b border-r border-slate-200 px-3 py-2">Durum</th>
                            <th class="border-b border-r border-slate-200 px-3 py-2">Resolver</th>
                            <th class="border-b border-slate-200 px-3 py-2">Link</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($products as $product)
                            @php
                                $image = $product->cdn_image_url ?: $product->image_url;
                                $resolver = data_get($product->metadata, 'image_resolver');
                            @endphp
                            <tr class="odd:bg-white even:bg-slate-50/60 hover:bg-orange-50/50">
                                <td class="border-b border-r border-slate-100 px-3 py-2 font-black text-slate-600">{{ $product->id }}</td>
                                <td class="border-b border-r border-slate-100 px-3 py-2">
                                    <div class="flex h-12 w-12 items-center justify-center overflow-hidden rounded border border-slate-200 bg-white">
                                        @if($image)
                                            <img src="{{ $image }}" alt="{{ $product->name }}" class="h-full w-full object-contain">
                                        @else
                                            <x-lucide-image-off class="h-4 w-4 text-slate-300" />
                                        @endif
                                    </div>
                                </td>
                                <td class="border-b border-r border-slate-100 px-3 py-2">
                                    <div class="max-w-md truncate font-bold text-slate-900">{{ $product->name }}</div>
                                    <div class="mt-1 truncate text-[11px] font-semibold text-slate-400">{{ $product->slug }}</div>
                                </td>
                                <td class="border-b border-r border-slate-100 px-3 py-2 font-semibold text-slate-700">{{ $product->brand ?: '-' }}</td>
                                <td class="border-b border-r border-slate-100 px-3 py-2 font-mono text-slate-600">{{ $product->barcode ?: '-' }}</td>
                                <td class="border-b border-r border-slate-100 px-3 py-2 font-mono text-slate-600">{{ $product->sku ?: '-' }}</td>
                                <td class="border-b border-r border-slate-100 px-3 py-2">
                                    <div class="max-w-52 truncate font-semibold text-slate-600">
                                        {{ $product->categories->pluck('name')->take(2)->join(', ') ?: '-' }}
                                    </div>
                                </td>
                                <td class="border-b border-r border-slate-100 px-3 py-2 font-black {{ $product->stock_quantity > 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                    {{ number_format($product->stock_quantity) }}
                                </td>
                                <td class="border-b border-r border-slate-100 px-3 py-2">
                                    @if($image)
                                        <span class="rounded-full bg-emerald-100 px-2 py-1 font-black text-emerald-700">Hazır</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2 py-1 font-black text-amber-700">Eksik</span>
                                    @endif
                                </td>
                                <td class="border-b border-r border-slate-100 px-3 py-2">
                                    @if(is_array($resolver))
                                        <div class="font-black text-violet-700">
                                            {{ ($resolver['ranker'] ?? null) === 'gemini' ? 'Gemini AI' : ($resolver['source'] ?? 'resolver') }}
                                        </div>
                                        <div class="mt-1 text-[11px] text-slate-400">{{ $resolver['resolved_at'] ?? '' }}</div>
                                    @else
                                        <span class="text-slate-300">-</span>
                                    @endif
                                </td>
                                <td class="border-b border-slate-100 px-3 py-2">
                                    <a href="{{ route('admin.products.edit', $product) }}" class="inline-flex h-8 items-center gap-1 rounded-md border border-slate-200 bg-white px-2 font-bold text-slate-600 hover:bg-slate-50">
                                        <x-lucide-pencil class="h-3.5 w-3.5" />
                                        Düzenle
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-12 text-center text-sm font-bold text-slate-500">Ürün bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 bg-slate-50 px-4 py-3">
                {{ $products->links('pagination::tailwind') }}
            </div>
        </div>
    </div>

</x-layouts.admin>
