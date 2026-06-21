<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta http-equiv="refresh" content="{{ (int) ($redirectDelay ?? 1) }};url={{ $redirectUrl }}">
    <title>Korunan API Alani | Karacabey Gross Market</title>
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        :root {
            --bg: #fff7ed;
            --panel: rgba(255, 255, 255, 0.96);
            --text: #1f2937;
            --muted: #6b7280;
            --danger: #b42318;
            --accent: #ff7a00;
            --border: rgba(180, 35, 24, 0.12);
            --shadow: 0 28px 70px rgba(15, 23, 42, 0.14);
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at top left, rgba(255, 122, 0, 0.24), transparent 32%),
                radial-gradient(circle at bottom right, rgba(180, 35, 24, 0.14), transparent 28%),
                linear-gradient(180deg, #fffaf5 0%, var(--bg) 100%);
        }

        .card {
            width: min(100%, 760px);
            padding: 36px 32px;
            border-radius: 26px;
            border: 1px solid var(--border);
            background: var(--panel);
            box-shadow: var(--shadow);
        }

        .eyebrow {
            display: inline-flex;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(180, 35, 24, 0.08);
            color: var(--danger);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 18px 0 12px;
            font-size: clamp(30px, 5vw, 46px);
            line-height: 1.02;
            letter-spacing: -0.04em;
        }

        p {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.8;
        }

        .meta {
            margin-top: 24px;
            padding: 16px 18px;
            border-radius: 18px;
            border: 1px solid rgba(255, 122, 0, 0.16);
            background: rgba(255, 247, 237, 0.85);
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-top: 26px;
            min-height: 48px;
            padding: 0 18px;
            border-radius: 14px;
            background: var(--accent);
            color: #fff;
            font-size: 14px;
            font-weight: 800;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <main class="card">
        <div class="eyebrow">Korunan API Alani</div>
        <h1>Bu uc nokta dogrudan erisime kapali.</h1>
        <p>
            Istek guvenlik politikasi nedeniyle engellendi. Oturum bir saniye icinde guvenli alana yonlendirilecek.
        </p>

        <div class="meta">
            <strong>Durum:</strong> Engellendi<br>
            <strong>Neden:</strong> {{ str_replace('_', ' ', (string) $reason) }}<br>
            <strong>Zaman:</strong> {{ now()->timezone(config('app.timezone', 'UTC'))->format('d.m.Y H:i:s') }}
        </div>

        <a class="button" href="{{ $redirectUrl }}">Guvenli alana don</a>
    </main>
</body>
</html>
