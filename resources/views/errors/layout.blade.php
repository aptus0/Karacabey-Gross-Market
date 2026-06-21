@php
    $requestUid = request()->attributes->get('kgm_request_uid') ?: request()->headers->get('X-Request-ID') ?: 'kgm_req_' . strtolower((string) \Illuminate\Support\Str::ulid());
    $errorUid = request()->attributes->get('kgm_error_uid') ?: 'kgm_err_' . strtolower((string) \Illuminate\Support\Str::ulid());
    $statusCode = trim($__env->yieldContent('code', '500'));
@endphp
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Sistem Bildirimi') | Karacabey Gross Market</title>
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        :root {
            --bg: #f8fafc;
            --panel: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --soft: #fff7ed;
            --orange: #f97316;
            --orange-dark: #ea580c;
            --danger: #dc2626;
            --success: #059669;
            --radius: 8px;
        }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; margin: 0; }
        body {
            min-width: 320px;
            background:
                radial-gradient(circle at 14% 12%, rgba(249, 115, 22, 0.08), transparent 28%),
                linear-gradient(180deg, #fff 0%, var(--bg) 100%);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; text-decoration: none; }
        .error-shell { min-height: 100vh; display: grid; place-items: center; padding: 32px 18px; }
        .error-card {
            width: min(100%, 920px);
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) 330px;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--panel);
            box-shadow: 0 20px 56px rgba(17, 24, 39, 0.10);
        }
        .error-main { padding: 38px; }
        .brand { display: inline-flex; align-items: center; gap: 12px; margin-bottom: 30px; }
        .brand img { height: 42px; width: auto; object-fit: contain; }
        .brand span { font-weight: 800; letter-spacing: -0.02em; }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border: 1px solid #fed7aa;
            border-radius: 999px;
            background: var(--soft);
            color: var(--orange-dark);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .status-code { margin: 18px 0 8px; font-size: clamp(68px, 10vw, 118px); line-height: .9; font-weight: 900; letter-spacing: 0; }
        .headline { margin: 0; max-width: 14ch; font-size: clamp(28px, 3.5vw, 42px); line-height: 1.05; letter-spacing: 0; }
        .copy { margin: 18px 0 0; max-width: 64ch; color: var(--muted); font-size: 15px; line-height: 1.75; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 24px; }
        .button { display: inline-flex; align-items: center; justify-content: center; min-height: 44px; padding: 0 16px; border-radius: 8px; font-size: 14px; font-weight: 800; }
        .button-primary { background: var(--orange); color: #fff; }
        .button-primary:hover { background: var(--orange-dark); }
        .button-secondary { border: 1px solid var(--border); background: #fff; color: var(--text); }
        .error-side { display: grid; align-content: space-between; gap: 18px; padding: 30px; border-left: 1px solid var(--border); background: #fffaf5; }
        .meta { display: grid; gap: 10px; }
        .meta-card { border: 1px solid #fed7aa; border-radius: 8px; background: rgba(255,255,255,.82); padding: 14px; }
        .meta-label { display: block; margin-bottom: 5px; color: var(--muted); font-size: 10px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
        .meta-value { display: block; color: var(--text); font-size: 13px; line-height: 1.55; font-weight: 700; word-break: break-word; }
        .uid-value { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 12px; }
        .footer-note { margin: 0; color: var(--muted); font-size: 12px; line-height: 1.6; }
        @media (max-width: 780px) {
            .error-card { grid-template-columns: 1fr; }
            .error-main, .error-side { padding: 24px; }
            .error-side { border-left: 0; border-top: 1px solid var(--border); }
            .brand { margin-bottom: 22px; }
        }
    </style>
</head>
<body>
    <main class="error-shell">
        <section class="error-card" aria-labelledby="error-title">
            <div class="error-main">
                <a href="{{ url('/') }}" class="brand" aria-label="Karacabey Gross Market">
                    <img src="{{ asset('assets/kgm-logo.png') }}" alt="Karacabey Gross Market">
                    <span>Karacabey Gross Market</span>
                </a>

                <div class="eyebrow">@yield('eyebrow', 'Sistem Bildirimi')</div>

                <p class="status-code">@yield('code', '500')</p>
                <h1 class="headline" id="error-title">@yield('message', 'Beklenmeyen bir durum oluştu')</h1>

                <p class="copy">@yield('description', 'İstediğiniz işlem şu anda tamamlanamadı. Bağlantıyı, oturumunuzu veya sistem durumunu kontrol edip tekrar deneyebilirsiniz.')</p>

                <div class="actions">
                    @yield('actions')
                </div>
            </div>

            <aside class="error-side" aria-label="Teknik hata bilgileri">
                <div class="meta">
                    <div class="meta-card">
                        <span class="meta-label">Durum</span>
                        <span class="meta-value">@yield('status_text', 'İşlem tamamlanamadı')</span>
                    </div>
                    <div class="meta-card">
                        <span class="meta-label">Öneri</span>
                        <span class="meta-value">@yield('recommendation', 'Sayfayı yenileyin veya ana sayfadan yeniden ilerleyin.')</span>
                    </div>
                    <div class="meta-card">
                        <span class="meta-label">Hata UID</span>
                        <span class="meta-value uid-value">{{ $errorUid }}</span>
                    </div>
                    <div class="meta-card">
                        <span class="meta-label">İstek UID</span>
                        <span class="meta-value uid-value">{{ $requestUid }}</span>
                    </div>
                </div>

                <p class="footer-note">
                    Zaman: {{ now()->timezone(config('app.timezone', 'UTC'))->format('d.m.Y H:i:s') }}<br>
                    Kod: {{ $statusCode }} · Ortam: {{ app()->environment('production') ? 'Canlı' : app()->environment() }}
                </p>
            </aside>
        </section>
    </main>
</body>
</html>
