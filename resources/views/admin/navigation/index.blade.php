<x-layouts.admin header="Menü Navigasyonu">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Navigasyon Yönetimi</h2>
                <p class="text-muted-foreground">Header, footer ve yan menü bağlantılarını düzenleyin.</p>
            </div>
        </div>

        <!-- Add New Menu Item -->
        <x-ui.card>
            <div class="p-6 border-b flex flex-col space-y-1.5">
                <h3 class="font-semibold tracking-tight">Yeni Menü Öğesi Ekle</h3>
            </div>
            <form action="{{ route('admin.navigation.store') }}" method="POST">
                @csrf
                <div class="p-6 grid gap-4 md:grid-cols-4">
                    <div class="space-y-2 md:col-span-1">
                        <x-ui.label for="placement">Konum</x-ui.label>
                        <x-ui.select id="placement" name="placement" required>
                            @foreach($placements as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="label">Başlık *</x-ui.label>
                        <x-ui.input id="label" name="label" value="{{ old('label') }}" maxlength="80" required placeholder="Örn: Hakkımızda" />
                    </div>
                    <div class="space-y-2 md:col-span-1">
                        <x-ui.label for="sort_order">Sıra</x-ui.label>
                        <x-ui.input id="sort_order" name="sort_order" type="number" min="0" max="10000" value="{{ old('sort_order', 0) }}" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="url">URL *</x-ui.label>
                        <x-ui.input id="url" name="url" value="{{ old('url') }}" maxlength="500" required placeholder="/sayfa/hakkimizda" />
                    </div>
                    <div class="space-y-2 md:col-span-2">
                        <x-ui.label for="icon">İkon (İsteğe Bağlı)</x-ui.label>
                        <x-ui.select id="icon" name="icon">
                            <option value="">İkonsuz</option>
                            @foreach($icons as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <div class="flex items-center space-x-2 md:col-span-4 pt-2">
                        <x-ui.checkbox id="is_active" name="is_active" value="1" checked />
                        <x-ui.label for="is_active" class="cursor-pointer">Menü öğesi aktif edilsin</x-ui.label>
                    </div>
                </div>
                <div class="p-6 border-t bg-muted/20 flex justify-end">
                    <x-ui.button type="submit">Öğe Ekle</x-ui.button>
                </div>
            </form>
        </x-ui.card>

        <!-- Manage Existing Items -->
        <x-ui.card>
            @foreach($items as $item)
                <form id="nav-{{ $item->id }}" action="{{ route('admin.navigation.update', $item) }}" method="POST">
                    @csrf
                    @method('PUT')
                </form>
            @endforeach

            <div class="p-6 border-b flex items-center justify-between">
                <h3 class="font-semibold tracking-tight">Menü Öğelerini Yönet</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted">
                            <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider w-[150px]">Konum</th>
                            <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider w-[250px]">Başlık & URL</th>
                            <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider w-[150px]">İkon</th>
                            <th class="h-12 px-6 text-center align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider w-[100px]">Sıra</th>
                            <th class="h-12 px-6 text-center align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider w-[100px]">Durum</th>
                            <th class="h-12 px-6 text-right align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider w-[150px]">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($items as $item)
                            <tr class="border-b transition-colors hover:bg-muted/30">
                                <td class="p-4 px-6 align-middle">
                                    <x-ui.select name="placement" form="nav-{{ $item->id }}" class="h-8">
                                        @foreach($placements as $value => $label)
                                            <option value="{{ $value }}" @selected($item->placement === $value)>{{ $label }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </td>
                                <td class="p-4 px-6 align-middle space-y-2">
                                    <x-ui.input name="label" form="nav-{{ $item->id }}" value="{{ $item->label }}" maxlength="80" required placeholder="Başlık" class="h-8" />
                                    <x-ui.input name="url" form="nav-{{ $item->id }}" value="{{ $item->url }}" maxlength="500" required placeholder="URL" class="h-8" />
                                </td>
                                <td class="p-4 px-6 align-middle">
                                    <x-ui.select name="icon" form="nav-{{ $item->id }}" class="h-8">
                                        <option value="">İkonsuz</option>
                                        @foreach($icons as $value => $label)
                                            <option value="{{ $value }}" @selected($item->icon === $value)>{{ $label }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </td>
                                <td class="p-4 px-6 align-middle text-center">
                                    <x-ui.input name="sort_order" form="nav-{{ $item->id }}" type="number" min="0" max="10000" value="{{ $item->sort_order }}" class="h-8 w-20 mx-auto text-center" />
                                </td>
                                <td class="p-4 px-6 align-middle text-center">
                                    <div class="flex items-center justify-center h-full pt-2">
                                        <x-ui.checkbox name="is_active" form="nav-{{ $item->id }}" value="1" @checked($item->is_active) />
                                    </div>
                                </td>
                                <td class="p-4 px-6 align-middle text-right">
                                    <div class="flex justify-end gap-2">
                                        <x-ui.button form="nav-{{ $item->id }}" type="submit" size="sm" variant="outline">
                                            Kaydet
                                        </x-ui.button>
                                        <form action="{{ route('admin.navigation.destroy', $item) }}" method="POST" class="inline-block" data-confirm-submit="Silmek istediğinize emin misiniz?">
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
                                <td colspan="6" class="p-8 text-center text-muted-foreground">Henüz menü öğesi bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            
            @if($items->hasPages())
                <div class="p-4 px-6 border-t bg-muted/20">
                    {{ $items->links('pagination::tailwind') }}
                </div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.admin>
