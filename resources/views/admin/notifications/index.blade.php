<x-layouts.admin header="Bildirim Merkezi">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Bildirim Merkezi</h2>
                <p class="text-muted-foreground">Mobil ve web istemcilerinin kullanacağı kullanıcı bildirimlerini buradan gönderin.</p>
            </div>
        </div>

        @if(session('status'))
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[420px_minmax(0,1fr)]">
            <x-ui.card>
                <div class="border-b p-6">
                    <h3 class="font-semibold tracking-tight">Yeni Bildirim Gönder</h3>
                    <p class="mt-1 text-sm text-muted-foreground">Kampanya, ürün veya genel duyuru tipinde bildirim hazırlayabilirsiniz.</p>
                </div>

                <form action="{{ route('admin.notifications.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="space-y-4 p-6">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2">
                                <x-ui.label for="type">Tip</x-ui.label>
                                <x-ui.select id="type" name="type">
                                    <option value="general">Genel Duyuru</option>
                                    <option value="campaign">Kampanya</option>
                                    <option value="product">Ürün</option>
                                </x-ui.select>
                            </div>
                            <div class="space-y-2">
                                <x-ui.label for="audience">Hedef</x-ui.label>
                                <x-ui.select id="audience" name="audience">
                                    <option value="all">Tüm müşteriler</option>
                                    <option value="user">Tek müşteri</option>
                                </x-ui.select>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="target_user_id">Tek Müşteri Seçimi</x-ui.label>
                            <x-ui.select id="target_user_id" name="target_user_id">
                                <option value="">Tüm müşterilere gönder</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} @if($user->phone) · {{ $user->phone }} @endif @if($user->email) · {{ $user->email }} @endif</option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="title">Başlık *</x-ui.label>
                            <x-ui.input id="title" name="title" required placeholder="Yeni kampanya yayında" />
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="body">Mesaj *</x-ui.label>
                            <x-ui.textarea id="body" name="body" rows="5" required placeholder="Kullanıcıya görünecek mesajı yazın."></x-ui.textarea>
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="action_url">Tıklama URL</x-ui.label>
                            <x-ui.input id="action_url" name="action_url" placeholder="/kampanyalar veya /product/ornek-urun" />
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="cta_title">Buton Metni</x-ui.label>
                            <x-ui.input id="cta_title" name="cta_title" placeholder="Kampanyayı Gör" maxlength="80" />
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="image_file">Bildirim Görseli Yükle</x-ui.label>
                            <x-ui.input id="image_file" name="image_file" type="file" accept=".jpg,.jpeg,.png,.webp" />
                            <p class="text-xs text-muted-foreground">Önerilen: 1200×630, en fazla 2 MB. Dosya seçilirse URL'den önce kullanılır.</p>
                        </div>

                        <div class="space-y-2">
                            <x-ui.label for="image_url">Veya Görsel URL</x-ui.label>
                            <x-ui.input id="image_url" name="image_url" type="url" placeholder="https://..." />
                        </div>

                        <div id="notification-image-preview-wrap" class="hidden space-y-2">
                            <x-ui.label>Görsel Önizleme</x-ui.label>
                            <img id="notification-image-preview" alt="Bildirim görseli önizlemesi" class="h-36 w-full rounded-2xl border object-cover" />
                        </div>
                    </div>
                    <div class="border-t bg-muted/20 p-6">
                        <x-ui.button type="submit" class="w-full">Bildirimi Gönder</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card>
                <div class="border-b p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h3 class="font-semibold tracking-tight">Gönderim Geçmişi</h3>
                            <p class="mt-1 text-sm text-muted-foreground">Hangi bildirimin kaç kullanıcıya gittiğini buradan takip edebilirsiniz.</p>
                        </div>
                        <span class="rounded-full bg-orange-50 px-3 py-1 text-xs font-semibold text-orange-700">{{ $broadcasts->total() }} kayıt</span>
                    </div>
                </div>

                <div class="divide-y">
                    @forelse($broadcasts as $broadcast)
                        <div class="grid gap-4 p-5 lg:grid-cols-[minmax(0,1fr)_180px_140px]">
                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <strong class="text-base text-slate-900">{{ $broadcast->title }}</strong>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">{{ strtoupper($broadcast->type) }}</span>
                                    <span class="rounded-full bg-orange-50 px-2.5 py-1 text-xs font-semibold text-orange-700">
                                        {{ $broadcast->audience === 'all' ? 'Tum musteriler' : optional($broadcast->targetUser)->name ?? 'Tek musteri' }}
                                    </span>
                                </div>
                                <p class="text-sm leading-6 text-slate-600">{{ $broadcast->body }}</p>
                                <div class="flex flex-wrap gap-4 text-xs text-muted-foreground">
                                    <span>Olusturan: {{ $broadcast->creator?->name ?? 'Sistem' }}</span>
                                    <span>{{ $broadcast->created_at?->format('d.m.Y H:i') }}</span>
                                    @if($broadcast->action_url)
                                        <span>Yonlendirme: {{ $broadcast->action_url }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="space-y-2">
                                <div class="rounded-2xl border bg-slate-50 p-4">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Teslimat</div>
                                        <span class="rounded-full bg-white px-2 py-1 text-[10px] font-semibold uppercase text-slate-600">{{ $broadcast->status }}</span>
                                    </div>
                                    <div class="mt-3 grid grid-cols-2 gap-2 text-center">
                                        <div><div class="text-lg font-bold text-slate-900">{{ $broadcast->delivered_count }}</div><div class="text-[10px] text-slate-500">uygulama ici</div></div>
                                        <div><div class="text-lg font-bold text-emerald-700">{{ $broadcast->push_sent_count }}</div><div class="text-[10px] text-slate-500">push basarili</div></div>
                                        <div><div class="text-lg font-bold text-slate-700">{{ $broadcast->target_count }}</div><div class="text-[10px] text-slate-500">hedef</div></div>
                                        <div><div class="text-lg font-bold text-rose-700">{{ $broadcast->push_failed_count }}</div><div class="text-[10px] text-slate-500">push hatali</div></div>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                @if($broadcast->image_url)
                                    <img src="{{ $broadcast->image_url }}" alt="{{ $broadcast->title }}" class="h-24 w-full rounded-2xl object-cover" />
                                @else
                                    <div class="flex h-24 items-center justify-center rounded-2xl border bg-slate-50 text-xs font-semibold text-slate-400">
                                        Gorsel yok
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-10 text-center text-sm text-muted-foreground">Henüz bildirim gönderilmedi.</div>
                    @endforelse
                </div>

                @if($broadcasts->hasPages())
                    <div class="border-t bg-muted/20 p-4 px-6">
                        {{ $broadcasts->links('pagination::tailwind') }}
                    </div>
                @endif
            </x-ui.card>
        </div>
    </div>

    <script>
        (() => {
            const file = document.getElementById('image_file');
            const url = document.getElementById('image_url');
            const wrap = document.getElementById('notification-image-preview-wrap');
            const preview = document.getElementById('notification-image-preview');

            const show = (src) => {
                if (!src) {
                    wrap.classList.add('hidden');
                    preview.removeAttribute('src');
                    return;
                }
                preview.src = src;
                wrap.classList.remove('hidden');
            };

            file?.addEventListener('change', () => show(file.files?.[0] ? URL.createObjectURL(file.files[0]) : url.value));
            url?.addEventListener('input', () => {
                if (!file.files?.length) show(url.value);
            });
        })();
    </script>
</x-layouts.admin>
