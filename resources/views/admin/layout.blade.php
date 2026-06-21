@php
    $adminNav = [
        'Genel' => [
            ['label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'grid'],
            ['label' => 'Sistem', 'route' => 'admin.ops-monitor.index', 'icon' => 'activity'],
            ['label' => 'Canlı Kontrol', 'route' => 'admin.production-readiness.index', 'icon' => 'rocket'],
            ['label' => 'Bakım Modu', 'route' => 'admin.maintenance.edit', 'icon' => 'wrench'],
            ['label' => 'Güvenlik', 'route' => 'admin.auth-logs.index', 'icon' => 'shield'],
        ],
        'Katalog' => [
            ['label' => 'Ürünler', 'route' => 'admin.products.index', 'icon' => 'box'],
            ['label' => 'Kategoriler', 'route' => 'admin.categories.index', 'icon' => 'tag'],
            ['label' => 'Kampanyalar', 'route' => 'admin.campaigns.index', 'icon' => 'megaphone'],
            ['label' => 'Story', 'route' => 'admin.stories.index', 'icon' => 'spark'],
        ],
        'Satış' => [
            ['label' => 'Siparişler', 'route' => 'admin.orders.index', 'icon' => 'cart'],
            ['label' => 'Ödemeler', 'route' => 'admin.payments.index', 'icon' => 'card'],
            ['label' => 'Müşteriler', 'route' => 'admin.users.index', 'icon' => 'users'],
            ['label' => 'Bildirimler', 'route' => 'admin.notifications.index', 'icon' => 'bell'],
            ['label' => 'Destek', 'route' => 'admin.support.index', 'icon' => 'message'],
        ],
        'İçerik' => [
            ['label' => 'Sayfalar', 'route' => 'admin.pages.index', 'icon' => 'file'],
            ['label' => 'Ana Sayfa', 'route' => 'admin.homepage-blocks.index', 'icon' => 'home'],
            ['label' => 'Navigasyon', 'route' => 'admin.navigation.index', 'icon' => 'menu'],
            ['label' => 'SEO / Pixel', 'route' => 'admin.marketing.edit', 'icon' => 'trend'],
            ['label' => 'SEO XML', 'route' => 'admin.seo-automation.index', 'icon' => 'globe'],
        ],
        'ERP' => [
            ['label' => 'Fatura', 'route' => 'admin.erp.fatura', 'icon' => 'receipt'],
            ['label' => 'POS/Z', 'route' => 'admin.erp.pos', 'icon' => 'receipt'],
            ['label' => 'Cari', 'route' => 'admin.erp.cari', 'icon' => 'building'],
            ['label' => 'Sayım', 'route' => 'admin.erp.sayim', 'icon' => 'scan'],
        ],
    ];
    $quickNav = [
        ['label' => 'Panel', 'route' => 'admin.dashboard', 'icon' => 'grid'],
        ['label' => 'Ürün', 'route' => 'admin.products.index', 'icon' => 'box'],
        ['label' => 'Sipariş', 'route' => 'admin.orders.index', 'icon' => 'cart'],
        ['label' => 'Ödeme', 'route' => 'admin.payments.index', 'icon' => 'card'],
        ['label' => 'Destek', 'route' => 'admin.support.index', 'icon' => 'message'],
    ];
    $iconSvg = function (string $icon): string {
        $icons = [
            'grid' => '<rect width="7" height="7" x="3" y="3" rx="1.5"/><rect width="7" height="7" x="14" y="3" rx="1.5"/><rect width="7" height="7" x="14" y="14" rx="1.5"/><rect width="7" height="7" x="3" y="14" rx="1.5"/>',
            'activity' => '<path d="M3 12h4l3-7 4 14 3-7h4"/>',
            'shield' => '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/>',
            'box' => '<path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
            'tag' => '<path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5"/>',
            'megaphone' => '<path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
            'spark' => '<path d="M9.9 10.8 12 3l2.1 7.8L22 12l-7.9 1.2L12 21l-2.1-7.8L2 12z"/>',
            'cart' => '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
            'card' => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'bell' => '<path d="M10.268 21a2 2 0 0 0 3.464 0"/><path d="M3.262 15.326A1 1 0 0 0 4 17h16a1 1 0 0 0 .74-1.673C19.41 13.956 18 12.499 18 8a6 6 0 0 0-12 0c0 4.499-1.411 5.956-2.738 7.326"/>',
            'message' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>',
            'file' => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5z"/><polyline points="14 2 14 8 20 8"/>',
            'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
            'menu' => '<line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>',
            'trend' => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
            'globe' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
            'upload' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/>',
            'receipt' => '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2z"/><path d="M16 8H8"/><path d="M16 12H8"/><path d="M10 16H8"/>',
            'building' => '<rect width="16" height="20" x="4" y="2" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/><path d="M12 10h.01"/><path d="M12 14h.01"/><path d="M16 10h.01"/><path d="M16 14h.01"/><path d="M8 10h.01"/><path d="M8 14h.01"/>',
            'scan' => '<path d="M3 7V5a2 2 0 0 1 2-2h2"/><path d="M17 3h2a2 2 0 0 1 2 2v2"/><path d="M21 17v2a2 2 0 0 1-2 2h-2"/><path d="M7 21H5a2 2 0 0 1-2-2v-2"/><path d="M7 12h10"/>',
            'wrench' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.7-3.7a6 6 0 0 1-7.9 7.9l-6.2 6.2a2.1 2.1 0 0 1-3-3l6.2-6.2a6 6 0 0 1 7.9-7.9z"/>',
            'rocket' => '<path d="M4.5 16.5c-1.5 1.26-2 3.75-2 3.75s2.49-.5 3.75-2c.71-.84.7-2.13-.09-2.91a2.18 2.18 0 0 0-2.91-.09z"/><path d="M12 15l-3-3a22 22 0 0 1 2-5.7A12 12 0 0 1 22 2c0 2.76-.78 7.43-4.3 10.7A22 22 0 0 1 12 15z"/><path d="M9 12H4s.55-3.03 2-4c1.62-1.08 5 0 5 0"/><path d="M12 15v5s3.03-.55 4-2c1.08-1.62 0-5 0-5"/>',
        ];
        return '<svg class="admin-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . ($icons[$icon] ?? $icons['grid']) . '</svg>';
    };
    $requestUid = request()->attributes->get('kgm_request_uid') ?: request()->headers->get('X-Request-ID') ?: 'kgm_req_' . strtolower((string) \Illuminate\Support\Str::ulid());
@endphp
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Operasyon') | KGM Yönetim</title>
    @vite(['resources/js/admin.js'])
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        :root {
            --bg: #f8fafc;
            --surface: #ffffff;
            --soft: #fff7ed;
            --border: #e5e7eb;
            --text: #111827;
            --muted: #6b7280;
            --orange: #f97316;
            --orange-dark: #ea580c;
            --danger: #dc2626;
            --success: #059669;
            --radius: 12px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { min-height: 100%; }
        body { min-width: 320px; min-height: 100%; background: var(--bg); color: var(--text); font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; -webkit-font-smoothing: antialiased; padding-bottom: calc(92px + env(safe-area-inset-bottom)); }
        a { color: inherit; text-decoration: none; }
        input, textarea, select, button { font: inherit; }
        button { cursor: pointer; }
        .admin-shell { min-height: 100vh; }
        .admin-header { position: sticky; top: 0; z-index: 30; border-bottom: 1px solid var(--border); background: rgba(255,255,255,.94); backdrop-filter: blur(10px); }
        .admin-header-inner { max-width: 1440px; margin: 0 auto; padding: 12px 22px; display: flex; align-items: center; justify-content: space-between; gap: 18px; }
        .admin-brand { display: flex; align-items: center; gap: 12px; min-width: 0; }
        .admin-brand img { width: auto; height: 38px; object-fit: contain; }
        .admin-brand-copy { display: grid; line-height: 1.1; }
        .admin-brand-title { font-size: 14px; font-weight: 900; letter-spacing: -.02em; }
        .admin-brand-subtitle { color: var(--muted); font-size: 11px; font-weight: 700; }
        .admin-header-actions { display: flex; align-items: center; gap: 10px; }
        .admin-chip { display: inline-flex; align-items: center; min-height: 34px; padding: 0 12px; border: 1px solid var(--border); border-radius: 999px; background: #fff; color: var(--muted); font-size: 12px; font-weight: 800; }
        .admin-chip strong { margin-left: 6px; color: var(--text); font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; }
        .admin-logout { display: inline-flex; align-items: center; min-height: 34px; border: 1px solid #fecaca; border-radius: 999px; background: #fff; padding: 0 12px; color: var(--danger); font-size: 12px; font-weight: 900; }
        .main { width: min(100%, 1440px); margin: 0 auto; padding: 22px; }
        .status { display: flex; align-items: center; gap: 8px; border: 1px solid #bbf7d0; border-radius: var(--radius); background: #f0fdf4; padding: 12px 14px; margin-bottom: 16px; color: #166534; font-size: 13px; font-weight: 800; }
        .card, .panel { border: 1px solid var(--border); border-radius: var(--radius); background: var(--surface); box-shadow: 0 1px 2px rgba(17, 24, 39, 0.04); }
        .card { padding: 18px; }
        .panel { padding: 18px; margin-top: 18px; overflow-x: auto; }
        .top { display: flex; justify-content: space-between; align-items: flex-end; gap: 16px; margin-bottom: 18px; }
        .top h1 { font-size: 26px; line-height: 1.1; letter-spacing: -.04em; }
        .bottom-dock { position: fixed; left: 0; right: 0; bottom: 0; z-index: 50; padding: 10px 14px calc(10px + env(safe-area-inset-bottom)); pointer-events: none; }
        .bottom-dock-inner { max-width: 860px; margin: 0 auto; display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: end; pointer-events: auto; }
        .quick-nav { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 6px; border: 1px solid var(--border); border-radius: 18px; background: rgba(255,255,255,.96); box-shadow: 0 16px 40px rgba(17,24,39,.12); padding: 8px; backdrop-filter: blur(10px); }
        .dock-link, .dock-menu-btn { display: flex; min-height: 54px; flex-direction: column; align-items: center; justify-content: center; gap: 4px; border-radius: 12px; color: var(--muted); font-size: 11px; font-weight: 900; border: 0; background: transparent; }
        .dock-link:hover, .dock-link.active { background: var(--soft); color: var(--orange-dark); }
        .dock-link svg, .dock-menu-btn svg { width: 18px; height: 18px; }
        .dock-menu-btn { width: 70px; border: 1px solid var(--orange); background: var(--orange); color: #fff; box-shadow: 0 10px 22px rgba(249,115,22,.24); }
        .nav-sheet-backdrop { position: fixed; inset: 0; z-index: 40; background: rgba(15,23,42,.34); opacity: 0; pointer-events: none; transition: opacity .18s ease; }
        .nav-sheet { position: fixed; left: 50%; bottom: calc(86px + env(safe-area-inset-bottom)); z-index: 55; width: min(960px, calc(100% - 24px)); max-height: min(72vh, 620px); overflow: auto; transform: translate(-50%, 24px); opacity: 0; pointer-events: none; border: 1px solid var(--border); border-radius: 18px; background: rgba(255,255,255,.98); box-shadow: 0 24px 70px rgba(15,23,42,.22); transition: transform .18s ease, opacity .18s ease; }
        .nav-open .nav-sheet-backdrop { opacity: 1; pointer-events: auto; }
        .nav-open .nav-sheet { opacity: 1; transform: translate(-50%, 0); pointer-events: auto; }
        .nav-sheet-head { position: sticky; top: 0; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px; border-bottom: 1px solid var(--border); background: #fff; }
        .nav-sheet-title { font-weight: 900; letter-spacing: -.02em; }
        .nav-sheet-close { width: 36px; height: 36px; border: 1px solid var(--border); border-radius: 10px; background: #fff; color: var(--text); font-size: 20px; }
        .nav-groups { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 12px; padding: 16px; }
        .nav-group { display: grid; gap: 6px; }
        .nav-group-title { color: var(--orange-dark); font-size: 11px; font-weight: 900; letter-spacing: .1em; text-transform: uppercase; }
        .nav-item { display: flex; align-items: center; gap: 9px; min-height: 40px; border: 1px solid var(--border); border-radius: 10px; background: #fff; padding: 0 10px; color: var(--text); font-size: 13px; font-weight: 800; }
        .nav-item:hover, .nav-item.active { border-color: #fdba74; background: var(--soft); color: var(--orange-dark); }
        .admin-icon { width: 16px; height: 16px; flex: none; }
        @media (max-width: 860px) {
            .admin-brand-copy { display: none; }
            .admin-header-inner { padding: 10px 14px; }
            .admin-chip { display: none; }
            .main { padding: 14px; }
            .quick-nav { grid-template-columns: repeat(5, minmax(0, 1fr)); }
            .dock-menu-btn { width: 58px; }
            .nav-groups { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .top { align-items: flex-start; flex-direction: column; }
        }
        @media (max-width: 520px) {
            .quick-nav { grid-template-columns: repeat(4, minmax(0, 1fr)); }
            .quick-nav .dock-link:nth-child(5) { display: none; }
            .nav-groups { grid-template-columns: 1fr; }
        }
    </style>
    @stack('head')
</head>
<body>
    <div class="admin-shell" id="admin-shell">
        <header class="admin-header">
            <div class="admin-header-inner">
                <a href="{{ route('admin.dashboard') }}" class="admin-brand" aria-label="Karacabey Gross Yönetim">
                    <img src="{{ asset('assets/kgm-logo.png') }}" alt="Karacabey Gross">
                    <span class="admin-brand-copy">
                        <span class="admin-brand-title">KGM Yönetim</span>
                        <span class="admin-brand-subtitle">Sipariş · Katalog · Operasyon</span>
                    </span>
                </a>
                <div class="admin-header-actions">
                    <span class="admin-chip">UID <strong>{{ $requestUid }}</strong></span>
                    <form action="{{ route('admin.logout') }}" method="post">
                        @csrf
                        <button type="submit" class="admin-logout">Çıkış</button>
                    </form>
                </div>
            </div>
        </header>

        <main class="main">
            @if(session('status'))
                <div class="status">
                    <svg class="admin-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    {{ session('status') }}
                </div>
            @endif
            @yield('content')
        </main>
    </div>

    <div class="nav-sheet-backdrop" data-admin-nav-close></div>
    <section class="nav-sheet" id="admin-nav-sheet" aria-label="Yönetim menüsü" aria-hidden="true">
        <div class="nav-sheet-head">
            <div>
                <div class="nav-sheet-title">Yönetim Menüsü</div>
                <div class="admin-brand-subtitle">Sidebar yok; hızlı alt menü sistemi</div>
            </div>
            <button type="button" class="nav-sheet-close" data-admin-nav-close aria-label="Menüyü kapat">×</button>
        </div>
        <div class="nav-groups">
            @foreach($adminNav as $group => $items)
                <div class="nav-group">
                    <div class="nav-group-title">{{ $group }}</div>
                    @foreach($items as $item)
                        <a href="{{ route($item['route']) }}" class="nav-item {{ request()->routeIs($item['route']) || request()->routeIs(str_replace('.index', '.*', $item['route'])) ? 'active' : '' }}">
                            {!! $iconSvg($item['icon']) !!}
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </section>

    <nav class="bottom-dock" aria-label="Hızlı yönetim menüsü">
        <div class="bottom-dock-inner">
            <div class="quick-nav">
                @foreach($quickNav as $item)
                    <a href="{{ route($item['route']) }}" class="dock-link {{ request()->routeIs($item['route']) || request()->routeIs(str_replace('.index', '.*', $item['route'])) ? 'active' : '' }}">
                        {!! $iconSvg($item['icon']) !!}
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
            <button type="button" class="dock-menu-btn" data-admin-nav-toggle aria-expanded="false" aria-controls="admin-nav-sheet">
                <svg class="admin-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                <span>Menü</span>
            </button>
        </div>
    </nav>

    @stack('scripts')
</body>
</html>
