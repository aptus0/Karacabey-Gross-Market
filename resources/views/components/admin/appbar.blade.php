@php
$nav = [
    [
        'group'  => 'Genel',
        'icon'   => 'layout-dashboard',
        'direct' => 'admin.dashboard',
        'active' => request()->routeIs('admin.dashboard'),
        'items'  => [],
    ],
    [
        'group'  => 'Ticaret',
        'icon'   => 'shopping-cart',
        'direct' => null,
        'active' => request()->routeIs('admin.orders.*', 'admin.payments.*', 'admin.support.*'),
        'items'  => [
            ['label' => 'Siparişler', 'route' => 'admin.orders.index',   'icon' => 'shopping-bag', 'active' => request()->routeIs('admin.orders.*')],
            ['label' => 'Ödemeler',   'route' => 'admin.payments.index', 'icon' => 'credit-card',  'active' => request()->routeIs('admin.payments.*')],
            ['label' => 'Destek',     'route' => 'admin.support.index',  'icon' => 'message-circle','active' => request()->routeIs('admin.support.*')],
        ],
    ],
    [
        'group'  => 'Katalog',
        'icon'   => 'boxes',
        'direct' => null,
        'active' => request()->routeIs('admin.products.*', 'admin.categories.*', 'admin.catalog-images.*'),
        'items'  => [
            ['label' => 'Ürünler',    'route' => 'admin.products.index',   'icon' => 'package', 'active' => request()->routeIs('admin.products.*')],
            ['label' => 'Görsel Atölyesi', 'route' => 'admin.catalog-images.index', 'icon' => 'image-plus', 'active' => request()->routeIs('admin.catalog-images.*')],
            ['label' => 'Kategoriler','route' => 'admin.categories.index', 'icon' => 'tags',    'active' => request()->routeIs('admin.categories.*')],
        ],
    ],
    [
        'group'  => 'Pazarlama',
        'icon'   => 'megaphone',
        'direct' => null,
        'active' => request()->routeIs('admin.users.*', 'admin.notifications.*', 'admin.campaigns.*'),
        'items'  => [
            ['label' => 'Müşteriler', 'route' => 'admin.users.index',         'icon' => 'users',     'active' => request()->routeIs('admin.users.*')],
            ['label' => 'Bildirimler','route' => 'admin.notifications.index', 'icon' => 'bell-ring', 'active' => request()->routeIs('admin.notifications.*')],
            ['label' => 'Kampanyalar','route' => 'admin.campaigns.index',     'icon' => 'ticket',    'active' => request()->routeIs('admin.campaigns.*', 'admin.coupons.*')],
        ],
    ],
    [
        'group'  => 'İçerik',
        'icon'   => 'square-pen',
        'direct' => null,
        'active' => request()->routeIs('admin.pages.*', 'admin.homepage-blocks.*', 'admin.navigation.*', 'admin.stories.*'),
        'items'  => [
            ['label' => 'Sayfalar',  'route' => 'admin.pages.index',           'icon' => 'file-text',       'active' => request()->routeIs('admin.pages.*')],
            ['label' => 'Vitrin',    'route' => 'admin.homepage-blocks.index', 'icon' => 'layout-template', 'active' => request()->routeIs('admin.homepage-blocks.*')],
            ['label' => 'Menü',      'route' => 'admin.navigation.index',      'icon' => 'menu-square',     'active' => request()->routeIs('admin.navigation.*')],
            ['label' => 'Hikayeler', 'route' => 'admin.stories.index',         'icon' => 'image',           'active' => request()->routeIs('admin.stories.*')],
        ],
    ],
    [
        'group'  => 'ERP',
        'icon'   => 'database',
        'direct' => null,
        'active' => request()->routeIs('admin.erp.*'),
        'items'  => [
            ['label' => 'Fatura',   'route' => 'admin.erp.fatura', 'icon' => 'receipt',        'active' => request()->routeIs('admin.erp.fatura*')],
            ['label' => 'POS/Z',    'route' => 'admin.erp.pos',    'icon' => 'receipt-text',   'active' => request()->routeIs('admin.erp.pos*')],
            ['label' => 'Cari',     'route' => 'admin.erp.cari',   'icon' => 'users-2',        'active' => request()->routeIs('admin.erp.cari*')],
            ['label' => 'Sayım',    'route' => 'admin.erp.sayim',  'icon' => 'clipboard-list', 'active' => request()->routeIs('admin.erp.sayim*')],
        ],
    ],
    [
        'group'  => 'Ayarlar',
        'icon'   => 'settings-2',
        'direct' => null,
        'active' => request()->routeIs('admin.cargo.*', 'admin.marketing.*', 'admin.auth-logs.*', 'admin.ops-monitor.*', 'admin.data-pull.*'),
        'items'  => [
            ['label' => 'Kargo',       'route' => 'admin.cargo.index',      'icon' => 'truck',        'active' => request()->routeIs('admin.cargo.*')],
            ['label' => 'Pazarlama',   'route' => 'admin.marketing.edit',   'icon' => 'bar-chart-2',  'active' => request()->routeIs('admin.marketing.*')],
            ['label' => 'Veri Çekme',  'route' => 'admin.data-pull.index',  'icon' => 'database',     'active' => request()->routeIs('admin.data-pull.index', 'admin.data-pull.create', 'admin.data-pull.store', 'admin.data-pull.edit', 'admin.data-pull.update', 'admin.data-pull.browse', 'admin.data-pull.preview', 'admin.data-pull.export')],
            ['label' => 'Ürün Veri',   'route' => 'admin.data-pull.products','icon' => 'package-plus', 'active' => request()->routeIs('admin.data-pull.products*')],
            ['label' => 'Auth Log',    'route' => 'admin.auth-logs.index',  'icon' => 'shield-alert', 'active' => request()->routeIs('admin.auth-logs.*')],
            ['label' => 'Ops',         'route' => 'admin.ops-monitor.index','icon' => 'activity',     'active' => request()->routeIs('admin.ops-monitor.*')],
        ],
    ],
];
@endphp

<div class="fixed bottom-0 left-0 right-0 z-40 flex justify-center pb-3 pointer-events-none">
    <nav aria-label="Ana navigasyon"
         data-dock
         class="pointer-events-auto flex items-stretch gap-px rounded-2xl border border-slate-200/80 bg-white/95 px-2 py-2 shadow-[0_8px_32px_rgba(0,0,0,0.14),0_2px_8px_rgba(0,0,0,0.06)] backdrop-blur-xl">

        @foreach($nav as $gIdx => $group)

            @if($group['direct'])
                {{-- Doğrudan bağlantı --}}
                <a href="{{ route($group['direct']) }}"
                   class="relative flex flex-col items-center justify-center gap-1 rounded-xl px-4 py-2 transition-colors duration-150
                          {{ $group['active'] ? 'bg-orange-50 text-orange-600' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800' }}">
                    <x-dynamic-component :component="'lucide-' . $group['icon']" class="h-5 w-5 shrink-0" />
                    <span class="text-[10px] font-semibold leading-none tracking-wide">{{ $group['group'] }}</span>
                    @if($group['active'])
                        <span class="absolute bottom-0.5 left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-orange-500"></span>
                    @endif
                </a>

            @else
                {{-- Dropdown grubu --}}
                <div class="relative flex items-stretch">

                    {{-- Dropdown panel — varsayılan: gizli --}}
                    <div class="dock-dropdown absolute bottom-full left-1/2 z-50 mb-3 -translate-x-1/2 origin-bottom"
                         data-dock-panel="{{ $gIdx }}"
                         hidden>

                        <div class="min-w-46 overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-[0_8px_32px_rgba(0,0,0,0.13),0_2px_8px_rgba(0,0,0,0.07)]">
                            <div class="border-b border-slate-100 px-3 py-2">
                                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">{{ $group['group'] }}</p>
                            </div>
                            <div class="p-1.5">
                                @foreach($group['items'] as $item)
                                    <a href="{{ route($item['route']) }}"
                                       class="flex items-center gap-3 rounded-lg px-3 py-2 transition-colors duration-100
                                              {{ $item['active'] ? 'bg-orange-50 text-orange-700' : 'text-slate-700 hover:bg-slate-50 hover:text-slate-900' }}">
                                        <x-dynamic-component :component="'lucide-' . $item['icon']"
                                            class="h-4 w-4 shrink-0 {{ $item['active'] ? 'text-orange-500' : 'text-slate-400' }}" />
                                        <span class="flex-1 text-[13px] font-medium leading-none">{{ $item['label'] }}</span>
                                        @if($item['active'])
                                            <x-lucide-check class="h-3.5 w-3.5 shrink-0 text-orange-500" />
                                        @endif
                                    </a>
                                @endforeach
                            </div>
                        </div>

                        {{-- Caret --}}
                        <div class="absolute -bottom-1.5 left-1/2 h-3 w-3 -translate-x-1/2 rotate-45 border-b border-r border-slate-200/80 bg-white"></div>
                    </div>

                    {{-- Dock butonu --}}
                    <button type="button"
                            data-dock-btn="{{ $gIdx }}"
                            aria-expanded="false"
                            class="dock-btn relative flex flex-col items-center justify-center gap-1 rounded-xl px-4 py-2 transition-colors duration-150
                                   {{ $group['active'] ? 'text-orange-600' : 'text-slate-500' }}
                                   hover:bg-slate-100 hover:text-slate-800">

                        <span class="dock-icon-wrap inline-flex h-5 w-5 shrink-0 items-center justify-center transition-transform duration-200">
                            <x-dynamic-component :component="'lucide-' . $group['icon']"
                                class="h-5 w-5 {{ $group['active'] ? 'text-orange-500' : '' }}" />
                        </span>

                        <span class="flex items-center gap-0.5 text-[10px] font-semibold leading-none tracking-wide
                                     {{ $group['active'] ? 'text-orange-600' : '' }}">
                            {{ $group['group'] }}
                            <span class="dock-chevron inline-flex opacity-40 transition-transform duration-200">
                                <x-lucide-chevron-up class="h-2.5 w-2.5" />
                            </span>
                        </span>

                        @if($group['active'])
                            <span class="absolute bottom-0.5 left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-orange-500"></span>
                        @endif
                    </button>

                </div>
            @endif

        @endforeach

        {{-- Ayraç --}}
        <div class="mx-1.5 my-1 w-px self-stretch rounded-full bg-slate-200"></div>

        {{-- Çıkış --}}
        <form action="{{ route('admin.logout') }}" method="POST" class="flex items-stretch">
            @csrf
            <button type="submit"
                    class="flex flex-col items-center justify-center gap-1 rounded-xl px-4 py-2 text-slate-400 transition-colors duration-150 hover:bg-rose-50 hover:text-rose-500">
                <x-lucide-log-out class="h-5 w-5 shrink-0" />
                <span class="text-[10px] font-semibold leading-none tracking-wide">Çıkış</span>
            </button>
        </form>

    </nav>
</div>
