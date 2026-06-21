<x-layouts.admin header="Galeri / Slider Yönetimi">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Galeri / Slider</h2>
                <p class="text-muted-foreground">Web ve mobil ana sayfa slider görsellerini URL veya yüksek çözünürlüklü dosya yükleme ile yönetin.</p>
            </div>
        </div>

        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
            Mobil uygulama yalnızca aktif ve “Mobil” işaretli blokları slider/hızlı vitrin için okur. Story içerikleri için ayrıca Hikayeler menüsünü kullanın.
        </div>

        <!-- Add New Block -->
        <x-ui.card>
            <div class="p-6 border-b flex flex-col space-y-1.5">
                <h3 class="font-semibold tracking-tight">Yeni Blok Ekle</h3>
            </div>
            <form action="{{ route('admin.homepage-blocks.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="p-6 grid gap-4 md:grid-cols-4">
                    <div class="space-y-2 md:col-span-1">
                        <x-ui.label for="type">Blok Tipi</x-ui.label>
                        <x-ui.select id="type" name="type">
                            @foreach($types as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="title">Başlık (İsteğe Bağlı)</x-ui.label>
                        <x-ui.input id="title" name="title" placeholder="Görsel tek başına kullanılabilir" />
                    </div>
                    <div class="space-y-2 md:col-span-1">
                        <x-ui.label for="sort_order">Sıra</x-ui.label>
                        <x-ui.input id="sort_order" name="sort_order" type="number" min="0" value="0" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="subtitle">Alt Başlık / Metin</x-ui.label>
                        <x-ui.textarea id="subtitle" name="subtitle" class="min-h-[40px]"></x-ui.textarea>
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="image_url">Görsel URL</x-ui.label>
                        <x-ui.input id="image_url" name="image_url" type="url" placeholder="https://..." />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="image_file">Görsel Yükle</x-ui.label>
                        <x-ui.input id="image_file" name="image_file" type="file" accept="image/jpeg,image/png,image/webp,image/avif" />
                        <p class="text-xs text-muted-foreground">8K/orijinal görsel kabul edilir; vitrine optimize URL servis edilir.</p>
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="link_label">Buton / Link Metni</x-ui.label>
                        <x-ui.input id="link_label" name="link_label" placeholder="Örn: Hemen İncele" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="link_url">Link URL</x-ui.label>
                        <x-ui.input id="link_url" name="link_url" placeholder="/kategori/ornek" />
                    </div>
                    <div class="flex items-center space-x-2 md:col-span-4 pt-2">
                        <x-ui.checkbox id="is_active" name="is_active" value="1" checked />
                        <x-ui.label for="is_active" class="cursor-pointer">Blok ana sayfada görünür olsun</x-ui.label>
                    </div>
                    <div class="flex items-center gap-5 md:col-span-4">
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
                <div class="p-6 border-t bg-muted/20 flex justify-end">
                    <x-ui.button type="submit">Blok Ekle</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <!-- Existing Blocks -->
        <x-ui.card>
            @foreach($blocks as $block)
                <form id="block-{{ $block->id }}" action="{{ route('admin.homepage-blocks.update', $block) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                </form>
            @endforeach

            <div class="p-6 border-b flex items-center justify-between">
                <h3 class="font-semibold tracking-tight">Blokları Yönet</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                            <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider w-[300px]">İçerik</th>
                            <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider w-[150px]">Tipi</th>
                            <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider w-[250px]">Link</th>
                            <th class="h-12 px-6 text-center align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider w-[100px]">Sıra</th>
                            <th class="h-12 px-6 text-center align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider w-[100px]">Durum</th>
                            <th class="h-12 px-6 text-right align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider w-[150px]">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($blocks as $block)
                            <tr class="border-b transition-colors hover:bg-muted/30">
                                <td class="p-4 px-6 align-middle space-y-2">
                                    <x-ui.input name="title" form="block-{{ $block->id }}" value="{{ $block->title }}" placeholder="Başlık isteğe bağlı" class="h-8" />
                                    <x-ui.input name="subtitle" form="block-{{ $block->id }}" value="{{ $block->subtitle }}" placeholder="Alt Başlık" class="h-8" />
                                    <x-ui.input name="image_url" form="block-{{ $block->id }}" value="{{ $block->image_url }}" placeholder="Görsel URL" class="h-8" />
                                    <input name="image_file" form="block-{{ $block->id }}" type="file" accept="image/jpeg,image/png,image/webp,image/avif" class="block w-full text-xs text-slate-500 file:mr-2 file:rounded-md file:border-0 file:bg-orange-50 file:px-2 file:py-1.5 file:text-xs file:font-bold file:text-orange-700">
                                </td>
                                <td class="p-4 px-6 align-middle">
                                    <x-ui.select name="type" form="block-{{ $block->id }}" class="h-8">
                                        @foreach($types as $type)
                                            <option value="{{ $type }}" @selected($block->type === $type)>{{ $type }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </td>
                                <td class="p-4 px-6 align-middle space-y-2">
                                    <x-ui.input name="link_label" form="block-{{ $block->id }}" value="{{ $block->link_label }}" placeholder="Link Metni" class="h-8" />
                                    <x-ui.input name="link_url" form="block-{{ $block->id }}" value="{{ $block->link_url }}" placeholder="Link URL" class="h-8" />
                                </td>
                                <td class="p-4 px-6 align-middle text-center">
                                    <x-ui.input name="sort_order" form="block-{{ $block->id }}" type="number" min="0" max="1000" value="{{ $block->sort_order }}" class="h-8 w-20 mx-auto text-center" />
                                </td>
                                <td class="p-4 px-6 align-middle text-center">
                                    <div class="flex items-center justify-center h-full pt-2">
                                        <x-ui.checkbox name="is_active" form="block-{{ $block->id }}" value="1" @checked($block->is_active) />
                                    </div>
                                    <div class="mt-2 flex items-center justify-center gap-3 text-[11px] font-semibold text-slate-500">
                                        <label class="inline-flex items-center gap-1">
                                            <x-ui.checkbox name="show_on_web" form="block-{{ $block->id }}" value="1" @checked($block->show_on_web) />
                                            Web
                                        </label>
                                        <label class="inline-flex items-center gap-1">
                                            <x-ui.checkbox name="show_on_mobile" form="block-{{ $block->id }}" value="1" @checked($block->show_on_mobile) />
                                            Mobil
                                        </label>
                                    </div>
                                </td>
                                <td class="p-4 px-6 align-middle text-right">
                                    <div class="flex justify-end gap-2">
                                        <x-ui.button form="block-{{ $block->id }}" type="submit" size="sm" variant="outline">
                                            Kaydet
                                        </x-ui.button>
                                        <form action="{{ route('admin.homepage-blocks.destroy', $block) }}" method="POST" class="inline-block" data-confirm-submit="Silmek istediğinize emin misiniz?">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.button type="submit" size="icon" variant="destructive" class="h-9 w-9">
                                                <x-lucide-trash-2 class="h-4 w-4" />
                                            </x-ui.button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-8 text-center text-muted-foreground">Henüz ana sayfa bloku bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($blocks->hasPages())
                <div class="p-4 px-6 border-t bg-muted/20">
                    {{ $blocks->links('pagination::tailwind') }}
                </div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.admin>
