<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <meta property="csp-nonce" nonce="{{ request()->attributes->get('csp_nonce') }}">
    <title>Auth2 Dogrulama - Karacabey Gross Market</title>
    @vite(['resources/css/app.css'])
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; }
        .auth2-shell {
            min-height: 100svh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            background:
                radial-gradient(circle at top left, rgba(249, 115, 22, 0.18), transparent 28%),
                linear-gradient(180deg, #fffaf5 0%, #fff7ed 48%, #f8fafc 100%);
        }
        .auth2-panel {
            width: min(100%, 440px);
            border: 1px solid rgba(254, 215, 170, 0.95);
            border-radius: 1rem;
            background: rgba(255, 255, 255, 0.94);
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.14);
            overflow: hidden;
        }
        .auth2-header {
            padding: 1.25rem 1.25rem 1rem;
            border-bottom: 1px solid rgba(254, 215, 170, 0.7);
            display: flex;
            align-items: center;
            gap: 0.875rem;
        }
        .auth2-icon {
            width: 3rem;
            height: 3rem;
            border-radius: 1rem;
            display: grid;
            place-items: center;
            background: #ffedd5;
            color: #c2410c;
            flex: 0 0 auto;
        }
        .auth2-header h1 {
            margin: 0;
            color: #111827;
            font-size: 1.125rem;
            font-weight: 800;
        }
        .auth2-header p {
            margin: 0.25rem 0 0;
            color: #64748b;
            font-size: 0.875rem;
        }
        .auth2-body {
            padding: 1.25rem;
            display: grid;
            gap: 1rem;
        }
        .auth2-meta {
            display: grid;
            gap: 0.5rem;
            border-radius: 0.875rem;
            background: #fff7ed;
            padding: 0.875rem;
            color: #475569;
            font-size: 0.8125rem;
        }
        .auth2-row {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
        }
        .auth2-row strong {
            color: #1f2937;
            font-weight: 700;
            text-align: right;
            word-break: break-word;
        }
        .auth2-alert {
            border-radius: 0.875rem;
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #b91c1c;
            padding: 0.875rem;
            font-size: 0.875rem;
            font-weight: 650;
        }
        .auth2-field {
            display: grid;
            gap: 0.4rem;
        }
        .auth2-field label {
            font-size: 0.8125rem;
            font-weight: 750;
            color: #334155;
        }
        .auth2-field input {
            height: 2.75rem;
            border-radius: 0.75rem;
            border: 1px solid #fed7aa;
            padding: 0 0.85rem;
            outline: none;
            color: #111827;
            background: white;
        }
        .auth2-field input:focus {
            border-color: #fb923c;
            box-shadow: 0 0 0 3px rgba(251, 146, 60, 0.18);
        }
        .auth2-button {
            height: 2.75rem;
            border: 0;
            border-radius: 0.75rem;
            background: #f97316;
            color: white;
            font-weight: 800;
            cursor: pointer;
        }
        .auth2-foot {
            padding: 0 1.25rem 1.25rem;
            color: #94a3b8;
            font-size: 0.75rem;
            text-align: center;
        }
    </style>
</head>
<body>
@php
    $mode = $mode ?? 'challenge';
    $challengeId = $challengeId ?? 'KGM-000000';
    $clientId = $clientId ?? 'kgm-admin-console';
    $scope = $scope ?? 'admin.access security.audit';
@endphp

<main class="auth2-shell">
    <section class="auth2-panel" aria-labelledby="auth2-title">
        <div class="auth2-header">
            <div class="auth2-icon">
                <x-lucide-shield-check class="h-6 w-6" />
            </div>
            <div>
                <h1 id="auth2-title">Auth2 Güvenlik Doğrulaması</h1>
                <p>Yönetim istemcisi için ek oturum kontrolü gerekiyor.</p>
            </div>
        </div>

        <div class="auth2-body">
            <div class="auth2-meta">
                <div class="auth2-row">
                    <span>Challenge</span>
                    <strong>{{ $challengeId }}</strong>
                </div>
                <div class="auth2-row">
                    <span>Client</span>
                    <strong>{{ $clientId }}</strong>
                </div>
                <div class="auth2-row">
                    <span>Scope</span>
                    <strong>{{ $scope }}</strong>
                </div>
            </div>

            @if ($mode === 'blocked')
                <div class="auth2-alert">
                    Bu IP icin dogrulama oturumu gecici olarak bekletiliyor.
                </div>
            @endif

            @if ($errors->has('identifier'))
                <div class="auth2-alert">
                    {{ $errors->first('identifier') }}
                </div>
            @endif

            <form method="POST" action="{{ route('admin.fake-auth2.store') }}" class="grid gap-4">
                @csrf
                <input type="hidden" name="client_id" value="{{ $clientId }}">
                <input type="hidden" name="scope" value="{{ $scope }}">

                <div class="auth2-field">
                    <label for="identifier">Kurumsal kimlik</label>
                    <input
                        id="identifier"
                        name="identifier"
                        type="text"
                        inputmode="email"
                        autocomplete="off"
                        value="{{ old('identifier') }}"
                        placeholder="yonetici@sirket.com"
                    >
                </div>

                <button type="submit" class="auth2-button">Oturumu Doğrula</button>
            </form>
        </div>

        <p class="auth2-foot">KGM Auth2 Gateway</p>
    </section>
</main>
</body>
</html>
