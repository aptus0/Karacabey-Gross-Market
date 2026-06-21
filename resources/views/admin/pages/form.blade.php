<x-layouts.admin :header="$page->exists ? 'Sayfa Düzenle' : 'Yeni Sayfa'">
    <div class="flex flex-col gap-6 max-w-4xl mx-auto w-full">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">{{ $page->exists ? 'Sayfa Düzenle' : 'Yeni Sayfa Oluştur' }}</h2>
                <p class="text-muted-foreground">İçeriklerinizi ve SEO ayarlarınızı düzenleyin.</p>
            </div>
            <x-ui.button as="a" href="{{ route('admin.pages.index') }}" variant="outline">
                <x-lucide-arrow-left class="mr-2 h-4 w-4" /> Sayfalara Dön
            </x-ui.button>
        </div>

        <form action="{{ $page->exists ? route('admin.pages.update', $page) : route('admin.pages.store') }}" method="POST">
            @csrf
            @if($page->exists)
                @method('PUT')
            @endif

            <div class="grid gap-6">
                <!-- Content Section -->
                <x-ui.card>
                    <div class="p-6 border-b flex flex-col space-y-1.5">
                        <h3 class="font-semibold tracking-tight">İçerik</h3>
                        <p class="text-sm text-muted-foreground">Sayfanın ana detayları.</p>
                    </div>
                    <div class="p-6 grid gap-6">
                        <div class="grid gap-6 md:grid-cols-2">
                            <div class="space-y-2 md:col-span-2">
                                <x-ui.label for="title">Sayfa Başlığı *</x-ui.label>
                                <x-ui.input id="title" name="title" value="{{ old('title', $page->title) }}" required placeholder="Örn: Hakkımızda" />
                            </div>
                            <div class="space-y-2">
                                <x-ui.label for="slug">URL Uzantısı (Slug)</x-ui.label>
                                <x-ui.input id="slug" name="slug" value="{{ old('slug', $page->slug) }}" placeholder="Otomatik oluşturmak için boş bırakın" />
                            </div>
                            <div class="space-y-2">
                                <x-ui.label for="group">Sayfa Grubu</x-ui.label>
                                <x-ui.select id="group" name="group">
                                    @foreach(['corporate' => 'Kurumsal', 'legal' => 'Yasal (Sözleşmeler)', 'support' => 'Destek M.'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('group', $page->group ?: 'corporate') === $value)>{{ $label }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="body">Sayfa İçeriği</x-ui.label>
                            <x-ui.textarea id="body" name="body" class="min-h-[300px]" placeholder="Sayfa içeriğinizi buraya yazın (HTML desteklenir)...">{{ old('body', $page->body) }}</x-ui.textarea>
                        </div>
                    </div>
                </x-ui.card>

                <!-- SEO Section -->
                <x-ui.card>
                    <div class="p-6 border-b flex flex-col space-y-1.5">
                        <h3 class="font-semibold tracking-tight">Arama Motoru Optimizasyonu (SEO)</h3>
                        <p class="text-sm text-muted-foreground">Sayfanızın Google'daki görünürlüğünü artırın.</p>
                    </div>
                    <div class="p-6 grid gap-6">
                        <div class="space-y-2">
                            <x-ui.label for="seo_title">SEO Başlığı</x-ui.label>
                            <x-ui.input id="seo_title" name="seo_title" value="{{ old('seo_title', $page->seo_title) }}" placeholder="Meta Title" />
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="seo_description">SEO Açıklaması</x-ui.label>
                            <x-ui.textarea id="seo_description" name="seo_description" placeholder="Meta Description">{{ old('seo_description', $page->seo_description) }}</x-ui.textarea>
                        </div>
                        <div class="space-y-2">
                            <x-ui.label for="meta_image_url">Meta Görsel URL</x-ui.label>
                            <x-ui.input id="meta_image_url" name="meta_image_url" type="url" value="{{ old('meta_image_url', $page->meta_image_url) }}" placeholder="https://..." />
                        </div>
                    </div>
                </x-ui.card>

                <!-- Actions -->
                <div class="flex items-center justify-between border-t pt-6 pb-12">
                    <div class="flex items-center space-x-2">
                        <x-ui.checkbox id="is_published" name="is_published" value="1" @checked(old('is_published', $page->is_published ?? true)) />
                        <x-ui.label for="is_published" class="cursor-pointer">Sayfa yayında ve görünür olsun</x-ui.label>
                    </div>
                    <div class="flex gap-4">
                        <x-ui.button type="button" variant="ghost" as="a" href="{{ route('admin.pages.index') }}">İptal</x-ui.button>
                        <x-ui.button type="submit">
                            <x-lucide-save class="mr-2 h-4 w-4" /> Sayfayı Kaydet
                        </x-ui.button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-layouts.admin>
