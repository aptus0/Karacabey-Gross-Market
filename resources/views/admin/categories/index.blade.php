<x-layouts.admin header="Kategoriler">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Kategoriler</h2>
                <p class="text-muted-foreground">Dropdown menude, katalog filtrelerinde ve mobil akışta görünen kategori yapısını yönetin.</p>
            </div>
        </div>

        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach([
                ['Toplam Kategori', $categoryStats['total'], 'Katalogdaki tüm kategori kayıtları'],
                ['Aktif Kategori', $categoryStats['active'], 'Web ve mobilde gösterilebilir'],
                ['Ana Kategori', $categoryStats['parents'], 'Üst seviye liste başlıkları'],
                ['Alt Kategori', $categoryStats['children'], 'Ana kategorilere bağlı kayıtlar'],
            ] as [$label, $value, $hint])
                <div class="rounded-2xl border bg-white p-5 shadow-sm">
                    <div class="text-xs font-bold uppercase tracking-wider text-muted-foreground">{{ $label }}</div>
                    <div class="mt-2 text-3xl font-black tracking-tight text-slate-950">{{ number_format($value, 0, ',', '.') }}</div>
                    <div class="mt-1 text-xs text-muted-foreground">{{ $hint }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
            <x-ui.card>
                <div class="border-b p-6">
                    <h3 class="font-semibold tracking-tight">Yeni Kategori</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Ana kategori ya da alt kategori oluşturabilirsiniz.</p>
                </div>

                <form action="{{ route('admin.categories.store') }}" method="POST">
                    @csrf
                    <div class="space-y-4 p-6">
                        <div class="space-y-2">
                            <x-ui.label for="parent_id">Bağlı Üst Kategori</x-ui.label>
                            <x-ui.select id="parent_id" name="parent_id">
                                <option value="">Ana kategori</option>
                                @foreach($parentCategories as $parentCategory)
                                    <option value="{{ $parentCategory->id }}">{{ $parentCategory->name }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="name">Kategori Adı *</x-ui.label>
                            <x-ui.input id="name" name="name" required />
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="slug">Slug</x-ui.label>
                            <x-ui.input id="slug" name="slug" placeholder="Bos birakirsan otomatik olusur" />
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="description">Açıklama</x-ui.label>
                            <x-ui.textarea id="description" name="description" rows="3" placeholder="Mega menu ve kategori kartlari icin kisa aciklama."></x-ui.textarea>
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="image_url">Görsel URL</x-ui.label>
                            <x-ui.input id="image_url" name="image_url" placeholder="https://..." />
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <x-ui.label for="sort_order">Sıralama</x-ui.label>
                                <x-ui.input id="sort_order" name="sort_order" type="number" min="0" value="0" />
                            </div>
                            <div class="flex items-end">
                                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-slate-300" />
                                    Aktif
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="border-t bg-muted/20 p-6">
                        <x-ui.button type="submit" class="w-full">Kategori Ekle</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card>
                <div class="border-b p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="font-semibold tracking-tight">Mevcut Kategori Yapısı</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ $categories->total() }} kategori listeleniyor.</p>
                        </div>
                    </div>
                </div>

                <div class="divide-y">
                    @forelse($categories as $category)
                        <form action="{{ route('admin.categories.update', $category) }}" method="POST" class="grid gap-4 p-5 xl:grid-cols-[minmax(0,1.2fr)_150px_140px_110px_auto] xl:items-start">
                            @csrf
                            @method('PUT')

                            <div class="space-y-3">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <x-ui.label for="name-{{ $category->id }}">Kategori Adı</x-ui.label>
                                        <x-ui.input id="name-{{ $category->id }}" name="name" value="{{ $category->name }}" />
                                    </div>
                                    <div class="space-y-2">
                                        <x-ui.label for="slug-{{ $category->id }}">Slug</x-ui.label>
                                        <x-ui.input id="slug-{{ $category->id }}" name="slug" value="{{ $category->slug }}" />
                                    </div>
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <x-ui.label for="parent-{{ $category->id }}">Üst Kategori</x-ui.label>
                                        <x-ui.select id="parent-{{ $category->id }}" name="parent_id">
                                            <option value="">Ana kategori</option>
                                            @foreach($parentCategories as $parentCategory)
                                                @continue($parentCategory->id === $category->id)
                                                <option value="{{ $parentCategory->id }}" @selected($category->parent_id === $parentCategory->id)>{{ $parentCategory->name }}</option>
                                            @endforeach
                                        </x-ui.select>
                                    </div>
                                    <div class="space-y-2">
                                        <x-ui.label for="image-{{ $category->id }}">Görsel URL</x-ui.label>
                                        <x-ui.input id="image-{{ $category->id }}" name="image_url" value="{{ $category->image_url }}" />
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <x-ui.label for="description-{{ $category->id }}">Açıklama</x-ui.label>
                                    <x-ui.textarea id="description-{{ $category->id }}" name="description" rows="2">{{ $category->description }}</x-ui.textarea>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <x-ui.label for="sort-{{ $category->id }}">Sıra</x-ui.label>
                                <x-ui.input id="sort-{{ $category->id }}" name="sort_order" type="number" min="0" value="{{ $category->sort_order }}" />
                            </div>

                            <div class="space-y-2">
                                <span class="text-sm font-medium text-slate-700">Durum</span>
                                <label class="flex h-10 items-center gap-2 rounded-lg border px-3 text-sm">
                                    <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300" @checked($category->is_active) />
                                    Görünür
                                </label>
                                <div class="text-xs text-muted-foreground">
                                    {{ $category->parent?->name ? 'Alt kategori: '.$category->parent->name : 'Ana kategori' }}
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600">{{ $category->products_count ?? 0 }} ürün</span>
                                    <span class="rounded-full bg-orange-50 px-2.5 py-1 font-semibold text-orange-700">{{ $category->children_count ?? 0 }} alt kategori</span>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <span class="text-sm font-medium text-slate-700">Önizleme</span>
                                <div class="flex h-24 items-center justify-center overflow-hidden rounded-xl border bg-slate-50">
                                    @if($category->image_url)
                                        <img src="{{ $category->image_url }}" alt="{{ $category->name }}" class="h-full w-full object-cover" />
                                    @else
                                        <span class="px-3 text-center text-xs text-slate-400">Gorsel yok</span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex flex-col gap-2 xl:items-end">
                                <x-ui.button type="submit" class="w-full xl:w-auto">Kaydet</x-ui.button>
                                <button
                                    type="submit"
                                    form="delete-category-{{ $category->id }}"
                                    class="inline-flex h-10 items-center justify-center rounded-lg border border-rose-200 px-4 text-sm font-semibold text-rose-600 transition hover:bg-rose-50"
                                >
                                    Kaldır
                                </button>
                            </div>
                        </form>
                        <form
                            id="delete-category-{{ $category->id }}"
                            action="{{ route('admin.categories.destroy', $category) }}"
                            method="POST"
                            class="hidden"
                            data-confirm-submit="Bu kategoriyi silmek istediğinize emin misiniz?"
                        >
                            @csrf
                            @method('DELETE')
                        </form>
                    @empty
                        <div class="p-10 text-center text-sm text-muted-foreground">Kategori bulunamadı.</div>
                    @endforelse
                </div>

                @if($categories->hasPages())
                    <div class="border-t bg-muted/20 p-4 px-6">
                        {{ $categories->links('pagination::tailwind') }}
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
