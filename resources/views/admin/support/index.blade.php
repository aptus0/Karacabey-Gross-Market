<x-layouts.admin header="Canlı Destek">
    <div class="grid gap-5 xl:grid-cols-[380px_minmax(0,1fr)]">
        <section class="rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-black text-slate-950">Destek Konuşmaları</h2>
                        <p class="mt-1 text-xs font-semibold text-slate-500">Müşteri mesajları, AI yanıtları ve admin takibi.</p>
                    </div>
                    <form method="GET">
                        <select name="status" data-auto-submit class="h-9 rounded-md border border-slate-200 bg-white px-2 text-xs font-bold text-slate-700">
                            @foreach($statuses as $value => $label)
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>

            <div class="divide-y divide-slate-100">
                @forelse($conversations as $conversation)
                    <a href="{{ route('admin.support.index', ['status' => $status, 'conversation' => $conversation->id]) }}"
                       class="block p-4 transition hover:bg-orange-50/50 {{ $selected?->id === $conversation->id ? 'bg-orange-50' : '' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate text-sm font-black text-slate-900">
                                    {{ $conversation->customer_name ?: 'Misafir müşteri' }}
                                </div>
                                <div class="mt-1 truncate text-xs font-semibold text-slate-500">
                                    {{ $conversation->last_message_preview ?: $conversation->subject }}
                                </div>
                            </div>
                            <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-black uppercase {{ $conversation->status === 'closed' ? 'bg-slate-100 text-slate-600' : ($conversation->status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') }}">
                                {{ $statuses[$conversation->status] ?? $conversation->status }}
                            </span>
                        </div>
                        <div class="mt-2 flex items-center justify-between gap-2 text-[11px] font-semibold text-slate-400">
                            <span>{{ $conversation->assignedAdmin?->name ? 'Admin: '.$conversation->assignedAdmin->name : 'Atanmamış' }}</span>
                            <span>{{ $conversation->last_message_at?->diffForHumans() ?? '-' }}</span>
                        </div>
                    </a>
                @empty
                    <div class="p-6 text-center text-sm font-semibold text-slate-500">Henüz destek konuşması yok.</div>
                @endforelse
            </div>

            @if($conversations->hasPages())
                <div class="border-t border-slate-200 p-3">
                    {{ $conversations->links('pagination::tailwind') }}
                </div>
            @endif
        </section>

        <section class="min-h-[620px] rounded-md border border-slate-200 bg-white shadow-sm">
            @if($selected)
                @php($lastMessageId = (int) $selected->messages->max('id'))
                <div class="border-b border-slate-200 p-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div class="text-xs font-black uppercase tracking-wide text-orange-600">Konuşma #{{ $selected->id }}</div>
                            <h2 class="mt-1 text-xl font-black text-slate-950">{{ $selected->customer_name ?: 'Misafir müşteri' }}</h2>
                            <p class="mt-1 text-xs font-semibold text-slate-500">
                                {{ $selected->customer_phone ?: 'Telefon yok' }} · {{ $selected->customer_email ?: 'E-posta yok' }}
                            </p>
                        </div>

                        <form method="POST" action="{{ route('admin.support.update', $selected) }}" class="flex items-center gap-2">
                            @csrf
                            @method('PATCH')
                            <select name="status" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-bold text-slate-700">
                                <option value="open" @selected($selected->status === 'open')>Açık</option>
                                <option value="pending" @selected($selected->status === 'pending')>Beklemede</option>
                                <option value="closed" @selected($selected->status === 'closed')>Kapalı</option>
                            </select>
                            <x-ui.button type="submit" size="sm" class="rounded-md">Güncelle</x-ui.button>
                        </form>
                    </div>
                </div>

                <div data-support-admin
                     data-stream-url="{{ route('admin.support.stream', $selected) }}"
                     data-last-id="{{ $lastMessageId }}"
                     class="flex min-h-[520px] flex-col">
                    <div data-support-messages class="flex-1 space-y-3 overflow-y-auto bg-slate-50 p-4">
                        @foreach($selected->messages as $message)
                            <div data-message-id="{{ $message->id }}" class="flex {{ $message->sender_type === 'admin' ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[78%] rounded-md border px-3 py-2 text-sm shadow-sm {{ $message->sender_type === 'admin' ? 'border-orange-200 bg-orange-50 text-orange-950' : ($message->sender_type === 'ai' ? 'border-violet-200 bg-violet-50 text-violet-950' : 'border-slate-200 bg-white text-slate-800') }}">
                                    <div class="mb-1 text-[10px] font-black uppercase tracking-wide text-slate-400">{{ $message->sender_name ?: $message->sender_type }}</div>
                                    <div class="whitespace-pre-line leading-relaxed">{{ $message->body }}</div>
                                    <div class="mt-1 text-right text-[10px] font-semibold text-slate-400">{{ $message->created_at?->format('H:i') }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <form method="POST" action="{{ route('admin.support.messages.store', $selected) }}" class="border-t border-slate-200 p-4">
                        @csrf
                        <div class="flex gap-2">
                            <textarea name="message" rows="2" required maxlength="1500" placeholder="Müşteriye yanıt yaz..." class="min-h-12 flex-1 rounded-md border border-slate-200 px-3 py-2 text-sm outline-none focus:border-orange-400 focus:ring-2 focus:ring-orange-100"></textarea>
                            <x-ui.button type="submit" class="h-auto rounded-md">Gönder</x-ui.button>
                        </div>
                    </form>
                </div>
            @else
                <div class="flex min-h-[620px] items-center justify-center p-8 text-center">
                    <div>
                        <x-lucide-message-circle class="mx-auto h-10 w-10 text-slate-300" />
                        <p class="mt-3 text-sm font-bold text-slate-500">Bir konuşma seçildiğinde mesajlar burada görünecek.</p>
                    </div>
                </div>
            @endif
        </section>
    </div>
</x-layouts.admin>
