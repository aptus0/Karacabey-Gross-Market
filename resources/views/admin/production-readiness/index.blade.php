@extends('admin.layout')

@section('title', 'Canlı Kontrol')

@push('head')
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        .prod-grid { display:grid; gap:16px; }
        .prod-hero { display:flex; justify-content:space-between; gap:20px; align-items:flex-end; border-radius:22px; padding:26px; background:linear-gradient(135deg,#111827,#1f2937 55%,#f97316 145%); color:#fff; box-shadow:0 20px 50px rgba(15,23,42,.18); }
        .prod-hero h1 { font-size:clamp(28px,4vw,42px); letter-spacing:-.05em; margin:0; }
        .prod-hero p { color:#cbd5e1; margin-top:8px; }
        .prod-actions { display:flex; flex-wrap:wrap; gap:8px; justify-content:flex-end; }
        .prod-actions a, .prod-actions code { display:inline-flex; align-items:center; min-height:36px; border:1px solid rgba(255,255,255,.2); border-radius:12px; padding:0 12px; background:rgba(255,255,255,.08); color:#fff; font-size:12px; font-weight:800; }
        .prod-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:14px; }
        .prod-card { border:1px solid var(--border); border-radius:18px; background:#fff; padding:18px; box-shadow:0 10px 28px rgba(15,23,42,.06); }
        .prod-card small { display:block; color:#64748b; font-size:11px; font-weight:900; letter-spacing:.08em; text-transform:uppercase; }
        .prod-card strong { display:block; margin-top:7px; color:#0f172a; font-size:31px; line-height:1; letter-spacing:-.05em; }
        .prod-card span { display:block; margin-top:7px; color:#64748b; font-size:13px; }
        .prod-panel { border:1px solid var(--border); border-radius:18px; background:#fff; padding:18px; box-shadow:0 10px 28px rgba(15,23,42,.05); overflow:auto; }
        .prod-panel h2 { font-size:18px; margin-bottom:12px; }
        .prod-check { display:grid; gap:10px; }
        .prod-check li { display:flex; gap:10px; align-items:flex-start; list-style:none; border:1px solid #e5e7eb; border-radius:14px; padding:12px; }
        .prod-dot { width:10px; height:10px; border-radius:999px; margin-top:5px; background:#22c55e; box-shadow:0 0 0 5px #dcfce7; flex:none; }
        .prod-dot.warning { background:#f59e0b; box-shadow:0 0 0 5px #fef3c7; }
        .prod-dot.critical { background:#ef4444; box-shadow:0 0 0 5px #fee2e2; }
        .prod-check b { color:#111827; }
        .prod-check p { color:#64748b; font-size:13px; margin-top:2px; }
        .prod-table { width:100%; min-width:720px; border-collapse:collapse; font-size:13px; }
        .prod-table th, .prod-table td { text-align:left; border-bottom:1px solid #e5e7eb; padding:10px 8px; }
        .prod-table th { color:#64748b; font-size:11px; text-transform:uppercase; letter-spacing:.08em; }
        .prod-badge { display:inline-flex; border-radius:999px; padding:4px 9px; font-size:11px; font-weight:900; }
        .prod-badge.ok { background:#dcfce7; color:#166534; }
        .prod-badge.warning { background:#fef3c7; color:#92400e; }
        .prod-badge.critical { background:#fee2e2; color:#991b1b; }
        .prod-two { display:grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap:16px; }
        @media (max-width: 900px) { .prod-hero { align-items:flex-start; flex-direction:column; } .prod-actions { justify-content:flex-start; } .prod-two { grid-template-columns:1fr; } }
    </style>
@endpush

@section('content')
<div class="prod-grid">
    <section class="prod-hero">
        <div>
            <div style="font-size:12px;font-weight:900;letter-spacing:.12em;text-transform:uppercase;color:#fed7aa;">Release Candidate · Canlıya Hazırlık</div>
            <h1>Canlı Kontrol Merkezi</h1>
            <p>Web, panel, SEO, mail DNS, kuyruk ve sipariş operasyonu için son kapı kontrolü. Son güncelleme: {{ $generatedAt->format('d.m.Y H:i') }}</p>
        </div>
        <div class="prod-actions">
            <a href="{{ route('admin.maintenance.edit') }}">Bakım Modu</a>
            <a href="{{ route('admin.orders.index') }}">Siparişler</a>
            <a href="{{ route('admin.ops-monitor.index') }}">Sistem Monitör</a>
            <code>php artisan kgm:production-health</code>
        </div>
    </section>

    @if(!empty($criticalWarnings))
        <section class="prod-panel" style="border-color:#fecaca;background:#fff7f7;">
            <h2 style="color:#991b1b;">Canlı yayın öncesi kritik uyarılar</h2>
            <ul class="prod-check">
                @foreach($criticalWarnings as $warning)
                    <li>
                        <span class="prod-dot critical"></span>
                        <div><b>{{ $warning['title'] }}</b><p>{{ $warning['message'] }}</p></div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="prod-cards">
        <div class="prod-card"><small>Aktif Ürün</small><strong>{{ number_format($catalog['active'], 0, ',', '.') }}</strong><span>Canlı vitrinde listelenecek ürün.</span></div>
        <div class="prod-card"><small>Görsel Eksik</small><strong>{{ number_format($catalog['missing_images'], 0, ',', '.') }}</strong><span>Logo fallback SEO'ya gönderilmez.</span></div>
        <div class="prod-card"><small>SEO Eksik</small><strong>{{ number_format($catalog['missing_seo'], 0, ',', '.') }}</strong><span>Başlık/açıklama üretimi gereken ürün.</span></div>
        <div class="prod-card"><small>Kuyruk Hatası</small><strong>{{ number_format($queues['failed_jobs'] + $queues['failed_notifications'], 0, ',', '.') }}</strong><span>Deploy öncesi temizlenmeli.</span></div>
    </section>

    <div class="prod-two">
        <section class="prod-panel">
            <h2>Konfigürasyon Kontrolü</h2>
            <ul class="prod-check">
                @foreach($configChecks as $check)
                    <li>
                        <span class="prod-dot {{ $check['level'] }}"></span>
                        <div><b>{{ $check['title'] }}</b><p>{{ $check['message'] }}</p></div>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="prod-panel">
            <h2>Bakım Modu Durumu</h2>
            <ul class="prod-check">
                <li>
                    <span class="prod-dot {{ $maintenance['active'] ? 'warning' : 'ok' }}"></span>
                    <div>
                        <b>{{ $maintenance['active'] ? 'Aktif' : 'Kapalı' }}</b>
                        <p>{{ $maintenance['title'] }} · {{ $maintenance['message'] }}</p>
                    </div>
                </li>
                <li><span class="prod-dot ok"></span><div><b>Kanallar</b><p>Web: {{ $maintenance['channels']['storefront'] ? 'kilitlenebilir' : 'açık' }} · Checkout: {{ $maintenance['channels']['checkout'] ? 'kilitlenebilir' : 'açık' }} · Mobil: {{ $maintenance['channels']['mobile'] ? 'kilitlenebilir' : 'açık' }}</p></div></li>
            </ul>
        </section>
    </div>

    <div class="prod-two">
        <section class="prod-panel">
            <h2>Sipariş Operasyonu</h2>
            <table class="prod-table">
                <thead><tr><th>Durum</th><th>Adet</th><th>Operasyon</th></tr></thead>
                <tbody>
                    @php($labels = \App\Http\Controllers\Admin\OrderController::statusLabels())
                    @foreach(['awaiting_payment','reviewing','paid','approved','preparing','shipped','delivered','cancelled'] as $status)
                        <tr>
                            <td>{{ $labels[$status] ?? $status }}</td>
                            <td><strong>{{ number_format((int) ($orderStatusCounts[$status] ?? 0), 0, ',', '.') }}</strong></td>
                            <td><a href="{{ route('admin.orders.index', ['status' => $status]) }}" class="prod-badge ok">Listele</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section class="prod-panel">
            <h2>SEO Dosyaları</h2>
            <table class="prod-table">
                <thead><tr><th>Dosya</th><th>Durum</th><th>Kaynak</th><th>Boyut</th><th>Güncelleme</th></tr></thead>
                <tbody>
                    @foreach($seoFiles as $file)
                        <tr>
                            <td>{{ $file['name'] }}</td>
                            <td><span class="prod-badge {{ $file['exists'] ? 'ok' : 'critical' }}">{{ $file['exists'] ? 'Var' : 'Eksik' }}</span></td>
                            <td>{{ $file['source'] ?? '-' }}</td>
                            <td>{{ number_format($file['size'] / 1024, 1, ',', '.') }} KB</td>
                            <td>{{ $file['updated_at'] ?? '-' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    </div>

    <section class="prod-panel">
        <h2>Mail DNS / Webmail Kayıtları</h2>
        <table class="prod-table">
            <thead><tr><th>Tip</th><th>Ad</th><th>Değer</th><th>Mod</th></tr></thead>
            <tbody>
                @foreach($mailRecords as $record)
                    <tr>
                        <td>{{ $record['type'] }}</td>
                        <td><code>{{ $record['name'] }}</code></td>
                        <td><code style="word-break:break-all;">{{ $record['value'] }}</code></td>
                        <td>{{ $record['mode'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p style="margin-top:12px;color:#64748b;font-size:13px;">Not: <strong>mail</strong> SMTP/IMAP için DNS only kalmalı. Web arayüzü için <strong>webmail</strong> Cloudflare Tunnel/proxy kullanmalı.</p>
    </section>
</div>
@endsection
