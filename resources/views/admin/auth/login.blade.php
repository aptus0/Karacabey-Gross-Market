<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta property="csp-nonce" nonce="{{ request()->attributes->get('csp_nonce') }}">
    <title>Güvenli Erişim Portalı — KGM Teknik Servis</title>
    @vite(['resources/css/app.css'])
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100svh;
            background: #0f172a;
            color: #e2e8f0;
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .shell {
            min-height: 100svh;
            display: flex;
        }

        /* ── Sol Panel ── */
        .side {
            display: none;
            flex-direction: column;
            justify-content: space-between;
            width: 44%;
            min-height: 100svh;
            padding: 2.5rem;
            background: #0f172a;
            border-right: 1px solid #1e293b;
            position: sticky;
            top: 0;
        }
        @media (min-width: 1024px) { .side { display: flex; } }

        .side-brand {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #94a3b8;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .side-brand svg { width: 20px; height: 20px; color: #ea580c; flex-shrink: 0; }

        .side-main { flex: 1; display: flex; flex-direction: column; justify-content: center; gap: 2rem; }

        .side-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.875rem;
            background: rgba(234,88,12,0.12);
            border: 1px solid rgba(234,88,12,0.3);
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            color: #fb923c;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            width: fit-content;
        }
        .side-badge svg { width: 12px; height: 12px; }

        .side-title {
            font-size: 2rem;
            font-weight: 800;
            color: #f1f5f9;
            line-height: 1.2;
            letter-spacing: -0.025em;
        }
        .side-title span { color: #ea580c; }

        .side-desc {
            font-size: 0.9375rem;
            color: #64748b;
            line-height: 1.7;
        }

        .info-list {
            display: flex;
            flex-direction: column;
            gap: 0.875rem;
        }
        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.8125rem;
            color: #64748b;
            line-height: 1.5;
        }
        .info-icon {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: #1e293b;
            border: 1px solid #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 1px;
        }
        .info-icon svg { width: 14px; height: 14px; color: #ea580c; }
        .info-item strong { color: #94a3b8; display: block; font-weight: 600; font-size: 0.75rem; }

        .side-footer {
            font-size: 0.75rem;
            color: #334155;
            line-height: 1.6;
            border-top: 1px solid #1e293b;
            padding-top: 1.5rem;
        }

        /* ── Sağ Panel (Form) ── */
        .form-area {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1.25rem;
            background: #0f172a;
        }
        .form-box {
            width: 100%;
            max-width: 420px;
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
        }

        .form-header { text-align: center; }
        .shield-icon {
            width: 56px; height: 56px;
            border-radius: 14px;
            background: rgba(234,88,12,0.1);
            border: 1px solid rgba(234,88,12,0.25);
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem;
        }
        .shield-icon svg { width: 28px; height: 28px; color: #ea580c; }
        .form-title { font-size: 1.375rem; font-weight: 700; color: #f1f5f9; margin-bottom: 0.375rem; }
        .form-sub { font-size: 0.8125rem; color: #475569; }

        /* Card */
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .alert-error {
            border-radius: 8px;
            border: 1px solid rgba(239,68,68,0.25);
            background: rgba(239,68,68,0.08);
            padding: 0.75rem 1rem;
            color: #fca5a5;
            font-size: 0.8125rem;
        }
        .alert-success {
            border-radius: 8px;
            border: 1px solid rgba(16,185,129,0.25);
            background: rgba(16,185,129,0.08);
            padding: 0.75rem 1rem;
            color: #6ee7b7;
            font-size: 0.8125rem;
        }

        .field { display: flex; flex-direction: column; gap: 0.375rem; }
        label { font-size: 0.8125rem; font-weight: 500; color: #94a3b8; }
        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 0.625rem 0.875rem;
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            color: #f1f5f9;
            font-size: 0.9375rem;
            outline: none;
            transition: border-color 0.2s;
        }
        input[type="email"]:focus, input[type="password"]:focus {
            border-color: #ea580c;
        }
        input::placeholder { color: #475569; }

        .remember {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.8125rem;
            color: #64748b;
        }
        input[type="checkbox"] { accent-color: #ea580c; width: 15px; height: 15px; }

        .btn-submit {
            width: 100%;
            padding: 0.75rem;
            background: #ea580c;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            letter-spacing: 0.01em;
        }
        .btn-submit:hover { background: #c2410c; }
        .btn-submit:active { transform: scale(0.99); }
        .btn-submit svg { width: 16px; height: 16px; }

        .form-footer {
            text-align: center;
            font-size: 0.75rem;
            color: #334155;
        }

        .log-notice {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 0.875rem;
            background: rgba(15,23,42,0.8);
            border: 1px solid #1e293b;
            border-radius: 8px;
            font-size: 0.6875rem;
            color: #475569;
            line-height: 1.4;
        }
        .log-notice svg { width: 13px; height: 13px; color: #ea580c; flex-shrink: 0; }
    </style>
</head>
<body>
<div class="shell">

    {{-- ── Sol Panel ── --}}
    <aside class="side">
        <div class="side-brand">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
            KGM · Teknik Servis Sistemi
        </div>

        <div class="side-main">
            <div class="side-badge">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Erişim Denetim Sistemi
            </div>

            <div>
                <p class="side-title">Yalnızca <span>yetkili</span><br>personel erişebilir.</p>
            </div>

            <p class="side-desc">
                Bu portal, operasyonel yönetim işlemleri için kurum içi personele ayrılmıştır.
                Tüm bağlantılar şifreli kanal üzerinden iletilmekte olup erişim logları tutulmaktadır.
            </p>

            <div class="info-list">
                <div class="info-item">
                    <div class="info-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </div>
                    <div>
                        <strong>TLS 1.3 Şifreli Bağlantı</strong>
                        Kimlik bilgileri uçtan uca şifreli protokol ile iletilir.
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/></svg>
                    </div>
                    <div>
                        <strong>Erişim Günlüğü (Log)</strong>
                        IP adresi, tarayıcı bilgisi ve zaman damgası kayıt altına alınır.
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <div>
                        <strong>Yetkili Kullanıcı Doğrulama</strong>
                        T.C. Kimlik Numarası ile eşleştirilmiş kurumsal hesap zorunludur.
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
                    </div>
                    <div>
                        <strong>KVKK Uyumlu Sistem</strong>
                        6698 Sayılı Kanun kapsamında kişisel veriler mevzuata uygun işlenir.
                    </div>
                </div>
            </div>
        </div>

        <div class="side-footer">
            Bu sisteme yetkisiz erişim girişimleri Türk Ceza Kanunu'nun 243. maddesi kapsamında suç teşkil eder
            ve yasal işlem başlatılır. Tüm log kayıtları 90 gün boyunca saklanmaktadır.
        </div>
    </aside>

    {{-- ── Sağ Panel (Form) ── --}}
    <main class="form-area">
        <div class="form-box">

            <div class="form-header">
                <div class="shield-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="m9 12 2 2 4-4"/>
                    </svg>
                </div>
                <p class="form-title">Güvenli Erişim Portalı</p>
                <p class="form-sub">Yetkili personel kimlik doğrulama sistemi</p>
            </div>

            <div class="card">

                @if (session('status'))
                    <div class="alert-success">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="alert-error">
                        <div style="font-weight:600; margin-bottom:0.25rem;">Kimlik doğrulama başarısız</div>
                        @foreach ($errors->all() as $error)
                            <div>· {{ $error }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login.store') }}">
                    @csrf

                    <div style="display:flex; flex-direction:column; gap:1rem;">
                        <div class="field">
                            <label for="email">Kurumsal E-posta</label>
                            <input type="email" id="email" name="email"
                                   placeholder="personel@karacabeymarket.com"
                                   value="{{ old('email') }}"
                                   required autofocus autocomplete="email">
                        </div>

                        <div class="field">
                            <label for="password">Erişim Parolası</label>
                            <input type="password" id="password" name="password"
                                   placeholder="••••••••••••"
                                   required autocomplete="current-password">
                        </div>

                        <div class="remember">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember" style="cursor:pointer; color:#64748b;">Bu cihazı güvenilir olarak işaretle</label>
                        </div>

                        <button type="submit" class="btn-submit">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                                <polyline points="16 17 21 12 16 7"/>
                                <line x1="21" x2="9" y1="12" y2="12"/>
                            </svg>
                            Sisteme Giriş Yap
                        </button>
                    </div>
                </form>
            </div>

            <div class="log-notice">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                Bu giriş işlemi, {{ now()->format('d.m.Y H:i') }} tarihinde IP adresiniz ile birlikte kayıt altına alınmaktadır.
                Yetkisiz erişim girişimlerinde yasal süreç başlatılır.
            </div>

            <p class="form-footer">
                Giriş sorunu mu yaşıyorsunuz?
                <a href="#" style="color:#ea580c; text-decoration:underline;">Teknik destek hattı ile iletişime geçin.</a>
            </p>
        </div>
    </main>
</div>
</body>
</html>
