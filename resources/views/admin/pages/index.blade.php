<x-layouts.admin header="İçerik Yönetimi">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Sayfalar</h2>
                <p class="text-muted-foreground">Kurumsal sayfalarınızı ve yazılarınızı yönetin.</p>
            </div>
            <x-ui.button as="a" href="{{ route('admin.pages.create') }}">
                <x-lucide-plus class="mr-2 h-4 w-4" /> Yeni Sayfa Ekle
            </x-ui.button>
        </div>

        <x-ui.card>
            <div class="p-6 border-b flex items-center justify-between">
                <h3 class="font-semibold tracking-tight">Tüm Sayfalar</h3>
            </div>
            <x-ui.table>
                <x-slot name="header">
                    <tr>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Başlık & Uzantı</th>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Grup</th>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">SEO Meta</th>
                        <th class="h-12 px-6 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Durum</th>
                        <th class="h-12 px-6 text-right align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">İşlemler</th>
                    </tr>
                </x-slot>

                @forelse($pages as $page)
                    <tr class="border-b transition-colors hover:bg-muted/30">
                        <td class="p-4 px-6 align-middle">
                            <div class="font-medium">{{ $page->title }}</div>
                            <div class="text-xs text-muted-foreground">/{{ $page->slug }}</div>
                        </td>
                        <td class="p-4 px-6 align-middle font-medium text-sm">
                            <x-ui.badge variant="secondary">{{ $page->group ?: 'Yok' }}</x-ui.badge>
                        </td>
                        <td class="p-4 px-6 align-middle">
                            <div class="font-medium text-sm truncate max-w-[200px]" title="{{ $page->seo_title }}">{{ $page->seo_title ?: '-' }}</div>
                            <div class="text-xs text-muted-foreground truncate max-w-[200px]" title="{{ $page->seo_description }}">{{ $page->seo_description }}</div>
                        </td>
                        <td class="p-4 px-6 align-middle">
                            @if($page->is_published)
                                <x-ui.badge variant="default" class="bg-primary/20 text-primary hover:bg-primary/30">Yayında</x-ui.badge>
                            @else
                                <x-ui.badge variant="secondary">Taslak</x-ui.badge>
                            @endif
                        </td>
                        <td class="p-4 px-6 align-middle text-right">
                            <x-ui.button variant="ghost" size="icon" as="a" href="{{ route('admin.pages.edit', $page) }}">
                                <x-lucide-pencil class="h-4 w-4" />
                            </x-ui.button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center text-muted-foreground">Henüz sayfa oluşturulmamış.</td>
                    </tr>
                @endforelse
            </x-ui.table>
            @if($pages->hasPages())
                <div class="p-4 px-6 border-t bg-muted/20">
                    {{ $pages->links('pagination::tailwind') }}
                </div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.admin>
