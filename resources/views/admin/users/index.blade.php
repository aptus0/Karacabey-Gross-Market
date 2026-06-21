<x-layouts.admin header="Müşteriler & Yetkililer">
    <div class="flex flex-col gap-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Kullanıcılar</h2>
                <p class="text-muted-foreground">Kayıtlı müşterileri, giriş bilgilerini ve konumlarını görüntüleyin.</p>
            </div>
            @if(session('status'))
                <div class="inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1.5 text-sm font-medium text-emerald-700 ring-1 ring-emerald-200">
                    <x-lucide-check-circle-2 class="h-4 w-4" />
                    {{ session('status') }}
                </div>
            @endif
        </div>

        <x-ui.card>
            <div class="p-6 pb-6 border-b flex items-center gap-4">
                <div class="relative flex-1 max-w-md">
                    <x-lucide-search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                    <x-ui.input type="search" name="q" placeholder="İsim veya telefon ile arayın..." class="pl-9 bg-muted/50" />
                </div>
            </div>

            <x-ui.table>
                <x-slot name="header">
                    <tr>
                        <th scope="col" class="h-12 px-4 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Kullanıcı</th>
                        <th scope="col" class="h-12 px-4 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Telefon</th>
                        <th scope="col" class="h-12 px-4 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Son IP / Konum</th>
                        <th scope="col" class="h-12 px-4 text-center align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Sipariş</th>
                        <th scope="col" class="h-12 px-4 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Rol</th>
                        <th scope="col" class="h-12 px-4 text-left align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Puan / VIP</th>
                        <th scope="col" class="h-12 px-4 text-right align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Son Giriş</th>
                        <th scope="col" class="h-12 px-4 text-right align-middle font-medium text-muted-foreground text-xs uppercase tracking-wider">Aksiyon</th>
                    </tr>
                </x-slot>

                @forelse($users as $user)
                    <tr class="border-b transition-colors hover:bg-muted/30">
                        {{-- Kullanıcı adı + e-posta --}}
                        <td class="p-3 px-4 align-middle">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-orange-100 text-orange-700 text-sm font-black">
                                    {{ mb_substr($user->name, 0, 1) }}
                                </div>
                                <div>
                                    <p class="font-semibold text-sm leading-tight">{{ $user->name }}</p>
                                    @if($user->email)
                                        <p class="text-xs text-muted-foreground">{{ $user->email }}</p>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- Telefon --}}
                        <td class="p-3 px-4 align-middle">
                            @if($user->phone)
                                <span class="inline-flex items-center gap-1.5 text-sm font-mono font-semibold">
                                    <x-lucide-phone class="h-3.5 w-3.5 text-muted-foreground" />
                                    {{ $user->phone }}
                                </span>
                            @else
                                <span class="text-muted-foreground text-xs">—</span>
                            @endif
                        </td>

                        {{-- IP + Konum --}}
                        <td class="p-3 px-4 align-middle">
                            <div class="flex flex-col gap-0.5">
                                @if($user->last_ip)
                                    <span class="inline-flex items-center gap-1.5 text-xs font-mono text-muted-foreground">
                                        <x-lucide-monitor class="h-3 w-3" />
                                        {{ $user->last_ip }}
                                    </span>
                                @endif
                                @if($user->last_location)
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-foreground">
                                        <x-lucide-map-pin class="h-3 w-3 text-orange-500" />
                                        {{ $user->last_location }}
                                    </span>
                                @endif
                                @if(!$user->last_ip && !$user->last_location)
                                    <span class="text-muted-foreground text-xs">—</span>
                                @endif
                            </div>
                        </td>

                        {{-- Sipariş sayısı --}}
                        <td class="p-3 px-4 align-middle text-center">
                            <span class="inline-flex items-center justify-center h-6 min-w-[24px] rounded-full bg-muted text-xs font-bold px-2">
                                {{ $user->orders_count ?? 0 }}
                            </span>
                        </td>

                        {{-- Rol --}}
                        <td class="p-3 px-4 align-middle">
                            @if($user->is_admin)
                                <x-ui.badge variant="default" class="bg-primary/20 text-primary hover:bg-primary/30">Yönetici</x-ui.badge>
                            @else
                                <x-ui.badge variant="secondary">Müşteri</x-ui.badge>
                            @endif
                        </td>

                        {{-- Puan / VIP --}}
                        <td class="p-3 px-4 align-middle">
                            <div class="flex flex-col gap-1">
                                <span class="text-xs font-bold text-slate-900">{{ (int) $user->loyalty_points }} puan</span>
                                @if($user->isVipActive())
                                    <span class="inline-flex w-fit items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-black text-amber-700 ring-1 ring-amber-200">
                                        <x-lucide-crown class="h-3 w-3" />
                                        VIP · reklamsız
                                    </span>
                                @else
                                    <span class="text-[11px] font-semibold text-slate-400">Standart müşteri</span>
                                @endif
                            </div>
                        </td>

                        {{-- Son giriş --}}
                        <td class="p-3 px-4 align-middle text-right">
                            <div class="flex flex-col items-end gap-0.5">
                                @if($user->last_login_at)
                                    <span class="text-xs font-semibold text-foreground">
                                        {{ $user->last_login_at->format('d.m.Y') }}
                                    </span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ $user->last_login_at->format('H:i') }}
                                    </span>
                                @else
                                    <span class="text-xs text-muted-foreground">
                                        {{ $user->created_at?->format('d.m.Y') }}
                                    </span>
                                @endif
                            </div>
                        </td>

                        {{-- VIP aksiyonu --}}
                        <td class="p-3 px-4 align-middle text-right">
                            @if(! $user->is_admin)
                                <form action="{{ route('admin.users.vip.update', $user) }}" method="POST" class="inline-flex items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="is_vip" value="{{ $user->isVipActive() ? '0' : '1' }}">
                                    @if(! $user->isVipActive())
                                        <input type="hidden" name="vip_note" value="Admin panelinden VIP yapıldı">
                                    @endif
                                    <button type="submit" class="inline-flex h-8 items-center gap-1.5 rounded-md border border-slate-200 px-2.5 text-xs font-bold {{ $user->isVipActive() ? 'text-slate-600 hover:bg-slate-50' : 'bg-amber-50 text-amber-700 hover:bg-amber-100' }}">
                                        <x-lucide-crown class="h-3.5 w-3.5" />
                                        {{ $user->isVipActive() ? 'VIP kapat' : 'VIP yap' }}
                                    </button>
                                </form>
                            @else
                                <span class="text-xs text-muted-foreground">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-10 text-center text-muted-foreground">Kullanıcı bulunamadı.</td>
                    </tr>
                @endforelse
            </x-ui.table>

            @if($users->hasPages())
                <div class="p-4 px-6 border-t bg-muted/20">
                    {{ $users->links('pagination::tailwind') }}
                </div>
            @endif
        </x-ui.card>
    </div>
</x-layouts.admin>
