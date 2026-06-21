<x-layouts.admin header="Ürünler">
    <x-slot:head>
        <style nonce="{{ request()->attributes->get('csp_nonce') }}">
            .kgm-products-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                justify-content: flex-start;
            }

            .kgm-products-actions > * {
                flex: 0 0 auto;
            }

            .kgm-product-stats-grid {
                display: grid;
                gap: 0.75rem;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .kgm-product-filter-grid {
                display: grid;
                gap: 0.75rem;
                grid-template-columns: minmax(0, 1fr);
            }

            .kgm-product-filter-actions {
                display: flex;
                gap: 0.5rem;
                align-items: center;
            }

            @media (min-width: 768px) {
                .kgm-product-stats-grid {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }

                .kgm-product-filter-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (min-width: 1024px) {
                .kgm-products-actions {
                    justify-content: flex-end;
                }

                .kgm-product-filter-grid {
                    grid-template-columns: repeat(4, minmax(0, 1fr));
                }
            }

            @media (min-width: 1280px) {
                .kgm-product-stats-grid {
                    grid-template-columns: repeat(5, minmax(0, 1fr));
                }
            }

            @media (min-width: 1536px) {
                .kgm-product-stats-grid {
                    grid-template-columns: repeat(9, minmax(0, 1fr));
                }

                .kgm-product-filter-grid {
                    grid-template-columns: minmax(260px, 1.6fr) repeat(8, minmax(112px, 1fr)) auto;
                }
            }
        </style>
    </x-slot:head>

    <div class="space-y-5" x-data="bulkSelect" data-csrf-token="{{ csrf_token() }}">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-slate-950">Ürünler</h2>
                <p class="text-sm text-slate-500">Fiyat, stok, marka ve görünürlük yönetimi.</p>
            </div>
            <div class="kgm-products-actions">
                <form method="POST" action="{{ route('admin.products.bulk') }}"
                      data-confirm-submit="Fiyatı 0 olan tüm aktif ürünler pasif edilecek. Onaylıyor musunuz?">
                    @csrf
                    <input type="hidden" name="action" value="deactivate_zero_price">
                    <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 text-sm font-semibold text-amber-800 transition hover:bg-amber-100">
                        <x-lucide-circle-slash class="h-4 w-4" />
                        Sıfır Fiyatları Pasif Et
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.products.bulk') }}"
                      data-confirm-submit="Stok adedi 0 olan ürünler arşive alınacak ve pasif edilecek. Onaylıyor musunuz?">
                    @csrf
                    <input type="hidden" name="action" value="archive_zero_stock">
                    <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                        <x-lucide-archive class="h-4 w-4" />
                        Stoksuzları Arşive Al
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.products.bulk') }}"
                      data-confirm-submit="Stokta olanlar aktif, stoku 0 olanlar pasif yapılacak. Onaylıyor musunuz?">
                    @csrf
                    <input type="hidden" name="action" value="sync_stock_visibility">
                    <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-md border border-emerald-200 bg-emerald-50 px-3 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100">
                        <x-lucide-refresh-cw class="h-4 w-4" />
                        Stok Görünürlüğünü Düzelt
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.products.bulk') }}"
                      data-confirm-submit="Stok adedi 0 olan aktif ürünler pasif edilecek. Onaylıyor musunuz?">
                    @csrf
                    <input type="hidden" name="action" value="deactivate_zero_stock">
                    <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-md border border-rose-200 bg-white px-3 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">
                        <x-lucide-archive-x class="h-4 w-4" />
                        Sıfır Stokları Pasif Et
                    </button>
                </form>
                <form method="POST" action="{{ route('admin.products.bulk') }}"
                      data-confirm-submit="Markası boş ürünlerde marka, ürün adından tahmin edilecek. Devam edilsin mi?">
                    @csrf
                    <input type="hidden" name="action" value="infer_brands_missing">
                    <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-md border border-orange-200 bg-orange-50 px-3 text-sm font-semibold text-orange-800 transition hover:bg-orange-100">
                        <x-lucide-tags class="h-4 w-4" />
                        Boş Markaları Doldur
                    </button>
                </form>
                <x-ui.button as="a" href="{{ route('admin.products.create') }}" class="h-10 rounded-md">
                    <x-lucide-plus class="mr-2 h-4 w-4" /> Ürün Ekle
                </x-ui.button>
            </div>
        </div>

        @if(session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                {{ session('status') }}
            </div>
        @endif
        @if(session('error'))
            <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        <div class="kgm-product-stats-grid">
            @foreach([
                ['label' => 'Toplam', 'value' => $stats['total'], 'tone' => 'slate'],
                ['label' => 'Aktif', 'value' => $stats['active'], 'tone' => 'emerald'],
                ['label' => 'Pasif', 'value' => $stats['inactive'], 'tone' => 'slate'],
                ['label' => 'Stokta', 'value' => $stats['in_stock'], 'tone' => 'emerald'],
                ['label' => 'Stoksuz', 'value' => $stats['out_of_stock'], 'tone' => 'rose'],
                ['label' => 'Sıfır Fiyat', 'value' => $stats['zero_price'], 'tone' => 'amber'],
                ['label' => 'Arşiv', 'value' => $stats['archived'], 'tone' => 'slate'],
                ['label' => 'Markasız', 'value' => $stats['missing_brand'], 'tone' => 'amber'],
                ['label' => 'Marka', 'value' => $stats['brand_count'], 'tone' => 'orange'],
            ] as $stat)
                @php
                    $toneClass = [
                        'amber' => 'text-amber-700',
                        'emerald' => 'text-emerald-700',
                        'orange' => 'text-orange-700',
                        'rose' => 'text-rose-700',
                        'slate' => 'text-slate-900',
                    ][$stat['tone']];
                @endphp
                <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                    <div class="text-xs font-bold uppercase tracking-wide text-slate-400">{{ $stat['label'] }}</div>
                    <div class="mt-2 text-2xl font-black {{ $toneClass }}">{{ number_format($stat['value']) }}</div>
                </div>
            @endforeach
        </div>

        <div x-show="selectedIds.length > 0" x-cloak class="rounded-md border border-orange-200 bg-orange-50 p-3">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <span class="text-sm font-bold text-orange-800">
                    <span x-text="selectedIds.length"></span> ürün seçildi
                </span>
                <div class="flex flex-1 flex-wrap items-center gap-2 lg:justify-end">
                    <form method="POST" action="{{ route('admin.products.bulk') }}" @submit="appendIds($event)">
                        @csrf
                        <input type="hidden" name="action" value="activate">
                        <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-md bg-emerald-600 px-3 text-xs font-bold text-white transition hover:bg-emerald-700">
                            <x-lucide-check class="h-3.5 w-3.5" /> Aktif Et
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.products.bulk') }}" @submit="appendIds($event)">
                        @csrf
                        <input type="hidden" name="action" value="deactivate">
                        <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-md bg-slate-700 px-3 text-xs font-bold text-white transition hover:bg-slate-800">
                            <x-lucide-eye-off class="h-3.5 w-3.5" /> Pasif Et
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.products.bulk') }}" @submit="appendIds($event)" class="flex items-center gap-2">
                        @csrf
                        <input type="hidden" name="action" value="infer_brands_all">
                        <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-md border border-orange-300 bg-white px-3 text-xs font-bold text-orange-800 transition hover:bg-orange-100">
                            <x-lucide-wand-sparkles class="h-3.5 w-3.5" /> Markayı Tahmin Et
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.products.bulk') }}" @submit="appendIds($event)">
                        @csrf
                        <input type="hidden" name="action" value="archive_selected">
                        <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 text-xs font-bold text-slate-700 transition hover:bg-slate-100">
                            <x-lucide-archive class="h-3.5 w-3.5" /> Arşive Al
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.products.bulk') }}" @submit="appendIds($event)">
                        @csrf
                        <input type="hidden" name="action" value="restore_selected">
                        <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-md border border-emerald-300 bg-white px-3 text-xs font-bold text-emerald-700 transition hover:bg-emerald-50">
                            <x-lucide-archive-restore class="h-3.5 w-3.5" /> Arşivden Çıkar
                        </button>
                    </form>
                    <form method="POST" action="{{ route('admin.products.bulk') }}" @submit="appendIds($event)" class="flex items-center gap-2">
                        @csrf
                        <input type="hidden" name="action" value="apply_brand">
                        <input type="text" name="brand_name" placeholder="Marka adı" class="h-9 w-36 rounded-md border border-slate-200 bg-white px-3 text-xs font-semibold outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100">
                        <button type="submit" class="inline-flex h-9 items-center gap-1.5 rounded-md bg-orange-600 px-3 text-xs font-bold text-white transition hover:bg-orange-700">
                            <x-lucide-tag class="h-3.5 w-3.5" /> Uygula
                        </button>
                    </form>
                    <button type="button" @click="clearAll()" class="h-9 rounded-md px-3 text-xs font-bold text-slate-500 hover:text-slate-800">Seçimi Temizle</button>
                </div>
            </div>
        </div>

        <div class="rounded-md border border-slate-200 bg-white shadow-sm">
            @php
                $selectedStock = request()->has('stock')
                    ? request('stock')
                    : (request('archive') === 'archived' ? '' : 'in_stock');
            @endphp
            <form action="{{ route('admin.products.index') }}" method="GET" class="kgm-product-filter-grid border-b border-slate-200 p-4">
                <div class="relative">
                    <x-lucide-search class="absolute left-3 top-3 h-4 w-4 text-slate-400" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Ürün, barkod, SKU ara" class="h-10 w-full rounded-md border border-slate-200 bg-slate-50 pl-9 pr-3 text-sm outline-none transition focus:border-orange-400 focus:bg-white focus:ring-2 focus:ring-orange-100">
                </div>
                <select name="status" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                    <option value="">Tüm Durumlar</option>
                    <option value="active" @selected(request('status') === 'active')>Aktif</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Pasif</option>
                </select>
                <select name="stock" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                    <option value="" @selected($selectedStock === '')>Tüm Stoklar</option>
                    <option value="in_stock" @selected($selectedStock === 'in_stock')>Stokta</option>
                    <option value="out_of_stock" @selected($selectedStock === 'out_of_stock')>Stoksuz</option>
                </select>
                <select name="price" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                    <option value="">Tüm Fiyatlar</option>
                    <option value="priced" @selected(request('price') === 'priced')>Fiyatlı</option>
                    <option value="zero" @selected(request('price') === 'zero')>Sıfır Fiyat</option>
                </select>
                <select name="archive" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                    <option value="visible" @selected(request('archive', 'visible') === 'visible')>Arşiv Dışı</option>
                    <option value="archived" @selected(request('archive') === 'archived')>Arşiv</option>
                    <option value="all" @selected(request('archive') === 'all')>Tümü</option>
                </select>
                <select name="brand" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                    <option value="">Tüm Markalar</option>
                    <option value="__missing" @selected(request('brand') === '__missing')>Markasız</option>
                    @foreach($brands as $brand)
                        <option value="{{ $brand->brand }}" @selected(request('brand') === $brand->brand)>{{ $brand->brand }} ({{ $brand->product_count }})</option>
                    @endforeach
                </select>
                <select name="category_id" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                    <option value="">Tüm Kategoriler</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                <select name="image" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                    <option value="">Tüm Görseller</option>
                    <option value="missing" @selected(request('image') === 'missing')>Görseli Yok</option>
                    <option value="present" @selected(request('image') === 'present')>Görselli</option>
                </select>
                <select name="sort" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700">
                    <option value="latest" @selected(request('sort', 'latest') === 'latest')>Son Güncellenen</option>
                    <option value="name" @selected(request('sort') === 'name')>Ada Göre</option>
                    <option value="brand" @selected(request('sort') === 'brand')>Markaya Göre</option>
                    <option value="stock_desc" @selected(request('sort') === 'stock_desc')>Stok Çoktan Aza</option>
                    <option value="stock_asc" @selected(request('sort') === 'stock_asc')>Stok Azdan Çoğa</option>
                    <option value="price_desc" @selected(request('sort') === 'price_desc')>Fiyat Çoktan Aza</option>
                    <option value="price_asc" @selected(request('sort') === 'price_asc')>Fiyat Azdan Çoğa</option>
                </select>
                <div class="kgm-product-filter-actions">
                    <button type="submit" class="inline-flex h-10 items-center gap-2 rounded-md bg-slate-900 px-4 text-sm font-bold text-white transition hover:bg-slate-800">
                        <x-lucide-filter class="h-4 w-4" /> Filtrele
                    </button>
                    <a href="{{ route('admin.products.index') }}" class="inline-flex h-10 items-center rounded-md border border-slate-200 bg-white px-3 text-sm font-bold text-slate-600 transition hover:bg-slate-50">Temizle</a>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1120px] text-left text-sm">
                    <thead class="border-b border-slate-200 bg-slate-50 text-xs font-black uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="w-10 px-4 py-3">
                                <input type="checkbox" class="rounded border-slate-300" @change="toggleAll($event)" :checked="allSelected">
                            </th>
                            <th class="px-4 py-3">Ürün</th>
                            <th class="px-4 py-3">Marka</th>
                            <th class="px-4 py-3">Kategori</th>
                            <th class="px-4 py-3">Fiyat</th>
                            <th class="px-4 py-3">Stok</th>
                            <th class="px-4 py-3">Durum</th>
                            <th class="px-4 py-3">Kaynak</th>
                            <th class="px-4 py-3 text-right">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($products as $product)
                            @php
                                $image = $product->cdn_image_url ?: $product->image_url;
                                $rawStock = data_get($product->metadata, 'erp12_stock_quantity_raw');
                            @endphp
                            <tr class="transition hover:bg-orange-50/40" :class="selectedIds.includes({{ $product->id }}) ? 'bg-orange-50' : ''">
                                <td class="px-4 py-3">
                                    <input type="checkbox" class="product-checkbox rounded border-slate-300" value="{{ $product->id }}" @change="toggle({{ $product->id }})" :checked="selectedIds.includes({{ $product->id }})">
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <div class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-md border border-slate-200 bg-slate-100">
                                            @if($image)
                                                <img src="{{ $image }}" alt="{{ $product->name }}" class="h-full w-full object-cover">
                                            @else
                                                <x-lucide-package class="h-5 w-5 text-slate-400" />
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <div class="max-w-xl truncate font-bold text-slate-950">{{ $product->name }}</div>
                                            <div class="mt-1 flex flex-wrap gap-2 text-xs text-slate-500">
                                                <span>#{{ $product->id }}</span>
                                                @if($product->barcode)<span>{{ $product->barcode }}</span>@endif
                                                @if($product->sku)<span>SKU {{ $product->sku }}</span>@endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($product->brand)
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">{{ $product->brand }}</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">Markasız</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex max-w-56 flex-wrap gap-1">
                                        @forelse($product->categories->take(2) as $category)
                                            <span class="rounded-full bg-orange-50 px-2 py-0.5 text-[11px] font-bold text-orange-800">{{ $category->name }}</span>
                                        @empty
                                            <span class="text-xs text-slate-400">-</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-black text-slate-900">{{ $product->formattedPrice() }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-1">
                                        <span class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-black {{ $product->stock_quantity > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                            {{ number_format($product->stock_quantity) }} adet
                                        </span>
                                        @if($rawStock !== null)
                                            <span class="text-[11px] font-semibold text-slate-400">ERP: {{ $rawStock }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if(data_get($product->metadata, 'product_archive.is_archived') || data_get($product->metadata, 'archived'))
                                        <span class="mb-1 inline-flex rounded-full bg-slate-200 px-2.5 py-1 text-xs font-black text-slate-700">Arşiv</span>
                                    @endif
                                    @if($product->is_active)
                                        <span class="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-800">Aktif</span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-black text-slate-600">Pasif</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-xs font-semibold text-slate-500">
                                    {{ data_get($product->metadata, 'source', 'Yerel') }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button type="button"
                                                @click="openImageSuggest({{ $product->id }}, @js($product->name))"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-md border {{ $image ? 'border-slate-200 text-slate-500 hover:border-orange-300 hover:bg-orange-50 hover:text-orange-700' : 'border-orange-300 bg-orange-50 text-orange-700 hover:bg-orange-100' }}"
                                                title="AI ile görsel öner (URL Arama · Google · Gemini)">
                                            <x-lucide-image class="h-4 w-4" />
                                        </button>
                                        <x-ui.button variant="ghost" size="icon" as="a" href="{{ route('admin.products.edit', $product) }}" class="rounded-md">
                                            <x-lucide-pencil class="h-4 w-4" />
                                        </x-ui.button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-4 py-12 text-center">
                                    <x-lucide-package-x class="mx-auto h-10 w-10 text-slate-300" />
                                    <p class="mt-3 text-sm font-bold text-slate-600">Ürün bulunamadı</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="border-t border-slate-200 bg-slate-50 px-4 py-3">
                {{ $products->links('pagination::tailwind') }}
            </div>
        </div>

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
                    {{-- Loading --}}
                    <template x-if="imageModal.loading">
                        <div class="flex items-center justify-center py-12 text-slate-500">
                            <x-lucide-loader-circle class="h-6 w-6 animate-spin text-orange-500" />
                            <span class="ml-2 text-sm font-semibold">Adaylar getiriliyor…</span>
                        </div>
                    </template>

                    {{-- Mevcut görsel --}}
                    <template x-if="!imageModal.loading && imageModal.currentImageUrl">
                        <div class="mb-4 flex items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
                            <img :src="imageModal.currentImageUrl" alt="Mevcut görsel" class="h-14 w-14 rounded-md border border-slate-200 bg-white object-cover">
                            <div class="min-w-0 text-xs">
                                <div class="font-black text-slate-700">Mevcut görsel</div>
                                <div class="truncate text-slate-500" x-text="imageModal.currentImageUrl"></div>
                            </div>
                        </div>
                    </template>

                    {{-- Hata --}}
                    <template x-if="!imageModal.loading && imageModal.error">
                        <div class="mb-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">
                            <x-lucide-triangle-alert class="mt-0.5 h-4 w-4" />
                            <span x-text="imageModal.error"></span>
                        </div>
                    </template>

                    {{-- Başarı --}}
                    <template x-if="imageModal.success">
                        <div class="mb-4 flex items-start gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800">
                            <x-lucide-circle-check class="mt-0.5 h-4 w-4" />
                            <span x-text="imageModal.success"></span>
                        </div>
                    </template>

                    {{-- Adaylar grid --}}
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
