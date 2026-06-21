<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Yönetim Paneli') | Karacabey Gross Market</title>
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        /* shadcn/ui Renk Paleti ve Değişkenleri */
        :root { 
            --background: #f8fafc; /* Açık gri ana arka plan */
            --sidebar-bg: #ffffff; /* Beyaz sidebar */
            --border: #e2e8f0; 
            --foreground: #0f172a; 
            --muted: #64748b; 
            --muted-foreground: #94a3b8;
            --accent: #f1f5f9; /* Hover rengi */
            --accent-foreground: #0f172a;
            --primary: #ea580c; /* Kurumsal Turuncu */
            --danger: #ef4444; 
            --danger-bg: #fef2f2;
            --radius: 0.375rem;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { min-width: 320px; background: var(--background); color: var(--foreground); font-family: ui-sans-serif, system-ui, -apple-system, sans-serif; -webkit-font-smoothing: antialiased; }
        a { color: inherit; text-decoration: none; }
        input, textarea, select, button { font: inherit; }
        
        /* Layout İskeleti */
        .shell { display: flex; min-height: 100vh; }
        
        /* SIDEBAR (shadcn/ui Tarzı) */
        .sidebar { width: 260px; flex-shrink: 0; border-right: 1px solid var(--border); background: var(--sidebar-bg); display: flex; flex-direction: column; }
        
        .sidebar-header { padding: 1.25rem 1.25rem 1rem 1.25rem; display: flex; align-items: center; border-bottom: 1px solid transparent; }
        .sidebar-logo { max-height: 45px; object-fit: contain; }

        .sidebar-content { flex: 1; overflow-y: auto; padding: 1rem 0.75rem; display: flex; flex-direction: column; gap: 1.5rem; }
        
        .nav-group { display: flex; flex-direction: column; gap: 0.25rem; }
        .nav-group-title { padding: 0 0.75rem 0.5rem 0.75rem; font-size: 0.75rem; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.05em; }
        
        .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0.75rem; font-size: 0.875rem; font-weight: 500; border-radius: var(--radius); color: var(--muted); transition: all 0.2s ease; cursor: pointer; }
        .nav-item:hover { background: var(--accent); color: var(--accent-foreground); }
        .nav-item svg { width: 1.125rem; height: 1.125rem; flex-shrink: 0; }
        
        /* Aktif Menü Sınıfı (Örn: class="nav-item active") */
        .nav-item.active { background: var(--accent); color: var(--foreground); font-weight: 600; }
        .nav-item.active svg { color: var(--primary); }

        .sidebar-footer { padding: 1rem; border-top: 1px solid var(--border); }
        .logout-btn { display: flex; width: 100%; align-items: center; gap: 0.75rem; padding: 0.5rem 0.75rem; font-size: 0.875rem; font-weight: 600; color: var(--danger); border-radius: var(--radius); border: none; background: transparent; cursor: pointer; transition: background 0.2s; }
        .logout-btn:hover { background: var(--danger-bg); }

        /* ANA İÇERİK ALANI */
        .main { flex: 1; min-width: 0; padding: 2rem; display: flex; flex-direction: column; }
        
        .top { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; }
        .top h1 { font-size: 1.875rem; font-weight: 700; letter-spacing: -0.025em; line-height: 1.2; color: var(--foreground); }
        
        /* Eski İçerik Stillerinin shadcn Uyarlaması */
        .status { display: flex; align-items: center; gap: 0.5rem; border: 1px solid #bbf7d0; border-radius: var(--radius); background: #f0fdf4; padding: 0.75rem 1rem; margin-bottom: 1.5rem; color: #166534; font-size: 0.875rem; font-weight: 500; }
        .card, .panel { border: 1px solid var(--border); border-radius: 0.5rem; background: var(--sidebar-bg); box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
        .card { padding: 1.5rem; }
        .panel { padding: 1.5rem; margin-top: 1.5rem; overflow-x: auto; }
        
        @media (max-width: 900px) {
            .shell { flex-direction: column; }
            .sidebar { width: 100%; border-right: none; border-bottom: 1px solid var(--border); }
            .sidebar-content { gap: 1rem; padding: 1rem; }
            .main { padding: 1rem; }
        }
    </style>
</head>
<body>
    <div class="shell">
        
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="{{ route('admin.dashboard') }}">
                    <img src="{{ asset('assets/kgm-logo-4k.png') }}" alt="Karacabey Gross" class="sidebar-logo">
                </a>
            </div>

            <nav class="sidebar-content" aria-label="Ana Menü">
                
                <div class="nav-group">
                    <h4 class="nav-group-title">Genel</h4>
                    <a href="{{ route('admin.dashboard') }}" class="nav-item">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                        Dashboard
                    </a>
                </div>

                <div class="nav-group">
                    <h4 class="nav-group-title">Katalog</h4>
                    <a href="{{ route('admin.products.index') }}" class="nav-item">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m7.5 4.27 9 5.15"/><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>
                        Ürünler
                    </a>
                    <a href="{{ route('admin.categories.index') }}" class="nav-item">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12.586 2.586A2 2 0 0 0 11.172 2H4a2 2 0 0 0-2 2v7.172a2 2 0 0 0 .586 1.414l8.704 8.704a2.426 2.426 0 0 0 3.42 0l6.58-6.58a2.426 2.426 0 0 0 0-3.42l-8.704-8.704z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/></svg>
                        Kategoriler
                    </a>
                    <a href="{{ route('admin.campaigns.index') }}" class="nav-item">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                        Kampanyalar
                    </a>
                </div>

                <div class="nav-group">
                    <h4 class="nav-group-title">E-Ticaret</h4>
                    <a href="{{ route('admin.orders.index') }}" class="nav-item">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                        Siparişler
                    </a>
                    <a href="{{ route('admin.payments.index') }}" class="nav-item">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/></svg>
                        Ödemeler
                    </a>
                    <a href="{{ route('admin.users.index') }}" class="nav-item">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        Müşteriler / Kullanıcılar
                    </a>
                </div>

                <div class="nav-group">
                    <h4 class="nav-group-title">İçerik & Ayarlar</h4>
                    <a href="{{ route('admin.pages.index') }}" class="nav-item">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/></svg>
                        Kurumsal Sayfalar
                    </a>
                    <a href="{{ route('admin.homepage-blocks.index') }}" class="nav-item">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        Ana Sayfa Vitrin
                    </a>
                    <a href="{{ route('admin.navigation.index') }}" class="nav-item">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                        Navigasyon
                    </a>
                    <a href="{{ route('admin.marketing.edit') }}" class="nav-item">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                        SEO / Meta Pixel
                    </a>
                </div>

            </nav>

            <div class="sidebar-footer">
                <form action="{{ route('admin.logout') }}" method="post">
                    @csrf
                    <button type="submit" class="logout-btn">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                        Oturumu Kapat
                    </button>
                </form>
            </div>
        </aside>

        <main class="main">
            @if(session('status'))
                <div class="status">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    {{ session('status') }}
                </div>
            @endif
            
            @yield('content')
            
        </main>
    </div>
</body>
</html>