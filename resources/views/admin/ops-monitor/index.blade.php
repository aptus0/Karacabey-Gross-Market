@extends('admin.layout')

@section('title', 'Sistem Monitör')

@push('head')
    @if(!empty($autoRefresh) && $autoRefresh > 0)
        <meta http-equiv="refresh" content="{{ $autoRefresh }}">
    @endif
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        .ops-monitor { display: grid; gap: 18px; }
        .ops-monitor .panel { margin-top: 0; border-color: #e2e8f0; box-shadow: 0 12px 32px rgba(15, 23, 42, .06); }
        .ops-hero { position: relative; overflow: hidden; display: flex; align-items: flex-end; justify-content: space-between; gap: 24px; border-radius: 22px; padding: 26px; color: #fff; background: linear-gradient(135deg, #0f172a 0%, #1e293b 48%, #ea580c 150%); box-shadow: 0 20px 50px rgba(15, 23, 42, .18); }
        .ops-hero::after { content: ""; position: absolute; width: 260px; height: 260px; right: -90px; top: -130px; border-radius: 999px; background: rgba(249, 115, 22, .25); filter: blur(2px); }
        .ops-hero > * { position: relative; z-index: 1; }
        .ops-eyebrow { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; color: #fed7aa; font-size: 12px; font-weight: 900; letter-spacing: .1em; text-transform: uppercase; }
        .ops-live-dot { width: 9px; height: 9px; border-radius: 999px; background: #4ade80; box-shadow: 0 0 0 6px rgba(74, 222, 128, .14); animation: ops-pulse 1.8s infinite; }
        .ops-hero h1 { font-size: clamp(28px, 4vw, 42px); letter-spacing: -.05em; }
        .ops-hero-meta { margin-top: 8px; color: #cbd5e1; font-size: 13px; }
        .ops-controls { display: grid; justify-items: end; gap: 10px; color: #cbd5e1; font-size: 12px; }
        .ops-controls form { display: flex; align-items: center; gap: 8px; border: 1px solid rgba(255, 255, 255, .16); border-radius: 14px; background: rgba(255, 255, 255, .08); padding: 8px; backdrop-filter: blur(8px); }
        .ops-controls input { width: 78px; border: 1px solid rgba(255, 255, 255, .22); border-radius: 9px; background: rgba(15, 23, 42, .48); padding: 8px; color: #fff; }
        .ops-controls .btn { border-color: #fb923c; background: #f97316; color: #fff; }
        .ops-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 14px; }
        .ops-card { position: relative; overflow: hidden; min-height: 146px; border-color: #e2e8f0; border-radius: 18px; padding: 20px; box-shadow: 0 12px 32px rgba(15, 23, 42, .06); transition: transform .18s ease, box-shadow .18s ease; }
        .ops-card:hover { transform: translateY(-2px); box-shadow: 0 18px 38px rgba(15, 23, 42, .1); }
        .ops-card::after { content: ""; position: absolute; width: 72px; height: 72px; right: -26px; bottom: -30px; border-radius: 999px; background: #fff7ed; }
        .ops-card-label { color: #64748b; font-size: 11px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
        .ops-card-value { margin: 8px 0 4px; color: #0f172a; font-size: 34px; font-weight: 950; letter-spacing: -.05em; }
        .ops-card-hint { color: #64748b; font-size: 13px; line-height: 1.45; }
        .ops-monitor table { min-width: 720px; }
        @keyframes ops-pulse { 50% { opacity: .45; transform: scale(.86); } }
        @media (max-width: 760px) {
            .ops-hero { align-items: flex-start; flex-direction: column; padding: 22px; }
            .ops-controls { width: 100%; justify-items: stretch; }
            .ops-controls form { justify-content: space-between; }
        }
    </style>
@endpush

@section('content')
    <div class="ops-monitor">
    <div class="ops-hero">
        <div>
            <p class="ops-eyebrow"><span class="ops-live-dot"></span> Go API / ERP / Mobile · Canlı</p>
            <h1>Sistem Monitör</h1>
            @if($generatedAt)
                <p class="ops-hero-meta">Veri zamanı: {{ \Illuminate\Support\Carbon::parse($generatedAt)->setTimezone(config('app.timezone'))->format('d.m.Y H:i:s') }} · Sonraki yenileme: <span data-ops-countdown>{{ $autoRefresh ?: 'kapalı' }}</span>{{ $autoRefresh ? ' sn' : '' }}</p>
            @endif
        </div>
        <div class="ops-controls">
            <div>Internal API: {{ $baseUrl }}</div>
            <form method="get">
                <label>Yenileme (sn)</label>
                <input type="number" name="refresh" value="{{ $autoRefresh }}" min="0" max="600">
                <button type="submit" class="btn btn-sm">Uygula</button>
            </form>
        </div>
    </div>

    @if($error)
        <div class="panel" style="border-color:#fecaca;background:#fef2f2;color:#991b1b;">
            <strong>Bağlantı uyarısı:</strong> {{ $error }}
        </div>
    @endif

    @if($summary)
        <div class="ops-grid">
            @php
                $cards = [
                    ['Aktif Ürün', data_get($summary, 'catalog.active_products', 0), 'Katalogda aktif olan ürünler'],
                    ['Stok Yok', data_get($summary, 'catalog.out_of_stock', 0), 'Satışta ama stok sıfır'],
                    ['Bugünkü Sipariş', data_get($summary, 'orders.today', 0), 'Gün içinde oluşturulan sipariş'],
                    ['Bekleyen Ödeme', data_get($summary, 'payments.pending', 0), 'PayTR callback bekleyen ödeme'],
                    ['Callback Hash Hata', data_get($paymentRisk, 'callback_hash_failed', 0), 'Son 24 saat PayTR hash hatası'],
                    ['Kuyruk Bekleyen', data_get($queues, 'notification_pending', 0) + data_get($queues, 'cloudflare_purge_pending', 0), 'Bildirim + CDN purge bekleyen'],
                    ['Mobil Cihaz 24s', data_get($summary, 'mobile.active_devices_24h', 0), 'Son 24 saatte aktif cihaz'],
                    ['Mobil Event 24s', data_get($summary, 'mobile.events_24h', 0), 'Swift/web event akışı'],
                ];
            @endphp
            @foreach($cards as [$label, $value, $hint])
                <div class="card ops-card">
                    <div class="ops-card-label">{{ $label }}</div>
                    <div class="ops-card-value">{{ number_format((int) $value, 0, ',', '.') }}</div>
                    <div class="ops-card-hint">{{ $hint }}</div>
                </div>
            @endforeach
        </div>


        <div class="panel">
            <h2 style="font-size:1.15rem;margin-bottom:1rem;">Ödeme Risk / Idempotency</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
                <div><strong>Başarılı Ödeme 24s</strong><br>{{ number_format((int) data_get($paymentRisk, 'paid_24h', 0), 0, ',', '.') }}</div>
                <div><strong>Başarısız Ödeme 24s</strong><br>{{ number_format((int) data_get($paymentRisk, 'failed_24h', 0), 0, ',', '.') }}</div>
                <div><strong>Hash Hatası 24s</strong><br>{{ number_format((int) data_get($paymentRisk, 'callback_hash_failed', 0), 0, ',', '.') }}</div>
                <div><strong>Açık Idempotency</strong><br>{{ number_format((int) data_get($paymentRisk, 'idempotency_open', 0), 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="panel">
            <h2 style="font-size:1.15rem;margin-bottom:1rem;">Mobil / Web Senkronizasyon</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.25rem;">
                <div><strong>Aktif Cihaz 24s</strong><br>{{ number_format((int) data_get($mobile, 'active_devices_24h', 0), 0, ',', '.') }}</div>
                <div><strong>Event 24s</strong><br>{{ number_format((int) data_get($mobile, 'events_24h', 0), 0, ',', '.') }}</div>
                <div><strong>Crash 24s</strong><br>{{ number_format((int) data_get($mobile, 'crashes_24h', 0), 0, ',', '.') }}</div>
                <div><strong>Checkout Başlatma</strong><br>{{ number_format((int) data_get($mobile, 'checkout_started', 0), 0, ',', '.') }}</div>
            </div>

            @php
                $totals = data_get($devices, 'totals', []);
            @endphp
            <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;font-size:.85rem;color:var(--muted);">
                <span><strong style="color:var(--text,inherit);">{{ number_format((int) data_get($totals, 'total', 0), 0, ',', '.') }}</strong> toplam kayıtlı cihaz</span>
                <span>iOS: <strong style="color:var(--text,inherit);">{{ number_format((int) data_get($totals, 'ios', 0), 0, ',', '.') }}</strong></span>
                <span>Android: <strong style="color:var(--text,inherit);">{{ number_format((int) data_get($totals, 'android', 0), 0, ',', '.') }}</strong></span>
                <span>Web: <strong style="color:var(--text,inherit);">{{ number_format((int) data_get($totals, 'web', 0), 0, ',', '.') }}</strong></span>
            </div>

            <h3 style="font-size:.95rem;margin:.5rem 0;">Son Cihazlar</h3>
            <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                <thead><tr style="text-align:left;color:var(--muted);">
                    <th style="padding:.5rem;border-bottom:1px solid var(--border);">Platform</th>
                    <th style="padding:.5rem;border-bottom:1px solid var(--border);">Model / OS</th>
                    <th style="padding:.5rem;border-bottom:1px solid var(--border);">App</th>
                    <th style="padding:.5rem;border-bottom:1px solid var(--border);">Device ID</th>
                    <th style="padding:.5rem;border-bottom:1px solid var(--border);">Müşteri</th>
                    <th style="padding:.5rem;border-bottom:1px solid var(--border);">Son IP</th>
                    <th style="padding:.5rem;border-bottom:1px solid var(--border);">Son Görülme</th>
                </tr></thead>
                <tbody>
                @forelse(data_get($devices, 'devices', []) as $device)
                    @php
                        $lastSeen = data_get($device, 'last_seen_at');
                        $lastSeenLabel = $lastSeen ? \Illuminate\Support\Carbon::parse($lastSeen)->setTimezone(config('app.timezone'))->diffForHumans() : '-';
                        $plat = strtolower((string) data_get($device, 'platform', ''));
                        $platBadge = match($plat) {
                            'ios' => '#0a84ff', 'android' => '#34c759', 'web' => '#8e8e93', default => '#6b7280'
                        };
                    @endphp
                    <tr>
                        <td style="padding:.5rem;border-bottom:1px solid var(--border);"><span style="background:{{ $platBadge }};color:#fff;padding:.15rem .55rem;border-radius:1rem;font-size:.72rem;font-weight:700;text-transform:uppercase;">{{ $plat ?: '?' }}</span></td>
                        <td style="padding:.5rem;border-bottom:1px solid var(--border);">{{ data_get($device, 'device_model') ?: '-' }}<br><span style="color:var(--muted);font-size:.78rem;">{{ data_get($device, 'os_version') ?: '' }}</span></td>
                        <td style="padding:.5rem;border-bottom:1px solid var(--border);font-family:monospace;">{{ data_get($device, 'app_version') ?: '-' }}</td>
                        <td style="padding:.5rem;border-bottom:1px solid var(--border);font-family:monospace;font-size:.72rem;word-break:break-all;">{{ \Illuminate\Support\Str::limit((string) data_get($device, 'device_id'), 22) }}</td>
                        <td style="padding:.5rem;border-bottom:1px solid var(--border);font-family:monospace;font-size:.72rem;">{{ data_get($device, 'customer_uid') ? \Illuminate\Support\Str::limit((string) data_get($device, 'customer_uid'), 18) : '-' }}</td>
                        <td style="padding:.5rem;border-bottom:1px solid var(--border);font-family:monospace;font-size:.78rem;">{{ data_get($device, 'last_ip') ?: '-' }}</td>
                        <td style="padding:.5rem;border-bottom:1px solid var(--border);">{{ $lastSeenLabel }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="padding:.85rem;color:var(--muted);">Henüz kayıtlı mobil cihaz yok. iOS uygulaması açıldığında otomatik kayıt olacak.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h2 style="font-size:1.15rem;margin-bottom:1rem;">Son Mobil Eventler</h2>
            <table style="width:100%;border-collapse:collapse;font-size:.85rem;">
                <thead><tr style="text-align:left;color:var(--muted);">
                    <th style="padding:.5rem;border-bottom:1px solid var(--border);">Zaman</th>
                    <th style="padding:.5rem;border-bottom:1px solid var(--border);">Event</th>
                    <th style="padding:.5rem;border-bottom:1px solid var(--border);">Ekran</th>
                    <th style="padding:.5rem;border-bottom:1px solid var(--border);">Platform</th>
                    <th style="padding:.5rem;border-bottom:1px solid var(--border);">App</th>
                    <th style="padding:.5rem;border-bottom:1px solid var(--border);">Müşteri</th>
                </tr></thead>
                <tbody>
                @forelse(data_get($events, 'events', []) as $event)
                    @php
                        $when = data_get($event, 'created_at');
                        $whenLabel = $when ? \Illuminate\Support\Carbon::parse($when)->setTimezone(config('app.timezone'))->format('H:i:s') : '-';
                    @endphp
                    <tr>
                        <td style="padding:.5rem;border-bottom:1px solid var(--border);font-family:monospace;font-size:.78rem;">{{ $whenLabel }}</td>
                        <td style="padding:.5rem;border-bottom:1px solid var(--border);font-weight:600;">{{ data_get($event, 'event_name') }}</td>
                        <td style="padding:.5rem;border-bottom:1px solid var(--border);">{{ data_get($event, 'screen') ?: '-' }}</td>
                        <td style="padding:.5rem;border-bottom:1px solid var(--border);text-transform:uppercase;font-size:.78rem;color:var(--muted);">{{ data_get($event, 'platform') ?: '-' }}</td>
                        <td style="padding:.5rem;border-bottom:1px solid var(--border);font-family:monospace;">{{ data_get($event, 'app_version') ?: '-' }}</td>
                        <td style="padding:.5rem;border-bottom:1px solid var(--border);font-family:monospace;font-size:.72rem;">{{ data_get($event, 'customer_uid') ? \Illuminate\Support\Str::limit((string) data_get($event, 'customer_uid'), 16) : '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="padding:.85rem;color:var(--muted);">Henüz event yok. iOS uygulaması ekran değiştirdiğinde / açıldığında akış başlayacak.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div class="panel">
            <h2 style="font-size:1.15rem;margin-bottom:1rem;">Kuyruklar / CDN / Bildirim</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
                <div><strong>Bildirim Bekleyen</strong><br>{{ number_format((int) data_get($queues, 'notification_pending', 0), 0, ',', '.') }}</div>
                <div><strong>Bildirim Hatalı</strong><br>{{ number_format((int) data_get($queues, 'notification_failed', 0), 0, ',', '.') }}</div>
                <div><strong>CDN Purge Bekleyen</strong><br>{{ number_format((int) data_get($queues, 'cloudflare_purge_pending', 0), 0, ',', '.') }}</div>
                <div><strong>CDN Purge Hatalı</strong><br>{{ number_format((int) data_get($queues, 'cloudflare_purge_failed', 0), 0, ',', '.') }}</div>
            </div>
        </div>

        <div class="panel">
            <h2 style="font-size:1.15rem;margin-bottom:1rem;">ERP Senkronizasyon</h2>
            @php($erp = data_get($summary, 'erp.last_run'))
            @if($erp)
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
                    <div><strong>Durum:</strong><br>{{ data_get($erp, 'status') }}</div>
                    <div><strong>Gelen:</strong><br>{{ number_format((int) data_get($erp, 'received_count', 0), 0, ',', '.') }}</div>
                    <div><strong>Eklenen:</strong><br>{{ number_format((int) data_get($erp, 'inserted_count', 0), 0, ',', '.') }}</div>
                    <div><strong>Güncellenen:</strong><br>{{ number_format((int) data_get($erp, 'updated_count', 0), 0, ',', '.') }}</div>
                    <div><strong>Atlanan:</strong><br>{{ number_format((int) data_get($erp, 'skipped_count', 0), 0, ',', '.') }}</div>
                    <div><strong>Hatalı:</strong><br>{{ number_format((int) data_get($erp, 'failed_count', 0), 0, ',', '.') }}</div>
                </div>
            @else
                <p style="color:var(--muted);">Henüz ERP run kaydı yok.</p>
            @endif
        </div>

        <div class="panel">
            <h2 style="font-size:1.15rem;margin-bottom:1rem;">API Runtime</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.25rem;">
                <div><strong>Toplam İstek</strong><br>{{ number_format((int) data_get($runtime, 'total_requests', 0), 0, ',', '.') }}</div>
                <div><strong>Ortalama Latency</strong><br>{{ data_get($runtime, 'avg_latency_ms', 0) }} ms</div>
                <div><strong>Uptime</strong><br>{{ data_get($runtime, 'uptime_seconds', 0) }} sn</div>
                <div><strong>Catalog Version</strong><br>{{ data_get($summary, 'catalog.version', '-') }}</div>
            </div>

            <h3 style="font-size:.95rem;margin:1rem 0 .5rem;">En Yoğun Route'lar</h3>
            <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
                <thead><tr style="text-align:left;color:var(--muted);"><th style="padding:.5rem;border-bottom:1px solid var(--border);">Route</th><th style="padding:.5rem;border-bottom:1px solid var(--border);">İstek</th></tr></thead>
                <tbody>
                @forelse(data_get($runtime, 'top_routes', []) as $route)
                    <tr><td style="padding:.5rem;border-bottom:1px solid var(--border);font-family:monospace;">{{ data_get($route, 'key') }}</td><td style="padding:.5rem;border-bottom:1px solid var(--border);">{{ data_get($route, 'count') }}</td></tr>
                @empty
                    <tr><td colspan="2" style="padding:.75rem;color:var(--muted);">Henüz runtime route verisi yok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    @endif
    </div>
@endsection

@push('scripts')
    @if(!empty($autoRefresh) && $autoRefresh > 0)
        <script nonce="{{ request()->attributes->get('csp_nonce') }}">
            (() => {
                const target = document.querySelector('[data-ops-countdown]');
                if (!target) return;
                let remaining = {{ (int) $autoRefresh }};
                window.setInterval(() => {
                    remaining = Math.max(0, remaining - 1);
                    target.textContent = String(remaining);
                }, 1000);
            })();
        </script>
    @endif
@endpush
