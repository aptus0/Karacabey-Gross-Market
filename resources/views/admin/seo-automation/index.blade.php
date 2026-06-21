<x-layouts.admin header="SEO XML Otomasyonu">
    <x-slot:head>
        <style nonce="{{ request()->attributes->get('csp_nonce') }}">
            .seo-grid { display: grid; gap: 14px; grid-template-columns: repeat(1, minmax(0, 1fr)); }
            .seo-actions { display: flex; flex-wrap: wrap; gap: 10px; }
            .seo-card { border: 1px solid #e5e7eb; border-radius: 12px; background: #fff; padding: 18px; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
            .seo-stat-label { color: #64748b; font-size: 12px; font-weight: 900; letter-spacing: .08em; text-transform: uppercase; }
            .seo-stat-value { margin-top: 8px; color: #0f172a; font-size: 28px; font-weight: 950; letter-spacing: -.03em; }
            .seo-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 42px; border-radius: 10px; border: 1px solid #d1d5db; background: #fff; padding: 0 14px; color: #1f2937; font-size: 14px; font-weight: 900; }
            .seo-btn-primary { border-color: #fb923c; background: #f97316; color: #fff; }
            .seo-btn-green { border-color: #86efac; background: #f0fdf4; color: #166534; }
            .seo-btn-blue { border-color: #bfdbfe; background: #eff6ff; color: #1d4ed8; }
            .seo-link { color: #ea580c; font-weight: 900; word-break: break-all; }
            .seo-log { max-height: 260px; overflow: auto; border-radius: 10px; background: #0f172a; padding: 14px; color: #dbeafe; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 12px; line-height: 1.6; }
            .seo-form-row { display: flex; flex-wrap: wrap; gap: 10px; align-items: end; }
            .seo-input { width: 140px; min-height: 42px; border: 1px solid #d1d5db; border-radius: 10px; padding: 0 12px; font-weight: 800; }
            @media (min-width: 768px) { .seo-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
            @media (min-width: 1180px) { .seo-grid { grid-template-columns: repeat(5, minmax(0, 1fr)); } }
        </style>
    </x-slot:head>

    <div class="space-y-5">
        @php($storefrontUrl = rtrim((string) config('commerce.domains.storefront'), '/'))

        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-slate-950">SEO ve Metadata XML</h2>
                <p class="text-sm text-slate-500">Aktif ürünler için AI açıklama, zengin meta alanları, ürün sitemap ve metadata XML yönetimi.</p>
            </div>
            <div class="seo-actions">
                <a class="seo-btn" href="{{ $storefrontUrl }}/sitemap.xml" target="_blank" rel="noopener">Sitemap</a>
                <a class="seo-btn" href="{{ $storefrontUrl }}/google-merchant.xml" target="_blank" rel="noopener">Merchant XML</a>
            </div>
        </div>

        <div class="seo-grid">
            <div class="seo-card">
                <div class="seo-stat-label">Aktif Ürün</div>
                <div class="seo-stat-value">{{ number_format($stats['active']) }}</div>
            </div>
            <div class="seo-card">
                <div class="seo-stat-label">AI SEO Hazır</div>
                <div class="seo-stat-value text-emerald-700">{{ number_format($stats['ai']) }}</div>
            </div>
            <div class="seo-card">
                <div class="seo-stat-label">AI Bekleyen</div>
                <div class="seo-stat-value text-orange-700">{{ number_format($stats['missing_ai']) }}</div>
            </div>
            <div class="seo-card">
                <div class="seo-stat-label">Açıklaması Olan</div>
                <div class="seo-stat-value">{{ number_format($stats['with_description']) }}</div>
            </div>
            <div class="seo-card">
                <div class="seo-stat-label">Görselli Ürün</div>
                <div class="seo-stat-value">{{ number_format($stats['with_image']) }}</div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="seo-card space-y-4">
                <div>
                    <h3 class="text-lg font-black text-slate-950">AI SEO Otomasyonu</h3>
                    <p class="text-sm text-slate-500">Gemini aktif ürünlerin açıklama, meta description, keyword, schema ve görsel alt metinlerini doldurur.</p>
                </div>

                <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm font-bold text-slate-700">
                    Durum:
                    @if($aiJob['running'])
                        <span class="text-emerald-700">Çalışıyor @if($aiJob['pid']) · PID {{ $aiJob['pid'] }} @endif</span>
                    @else
                        <span class="text-slate-600">Beklemede</span>
                    @endif
                </div>

                <form method="POST" action="{{ route('admin.seo-automation.ai') }}" class="seo-form-row">
                    @csrf
                    <label class="grid gap-1 text-xs font-black text-slate-500 uppercase">
                        Limit
                        <input class="seo-input" type="number" name="limit" min="1" max="5000" placeholder="Boş = tümü">
                    </label>
                    <label class="grid gap-1 text-xs font-black text-slate-500 uppercase">
                        Batch
                        <input class="seo-input" type="number" name="chunk" min="5" max="25" value="25">
                    </label>
                    <label class="inline-flex items-center gap-2 pb-3 text-sm font-bold text-slate-600">
                        <input type="checkbox" name="force" value="1">
                        Yeniden yaz
                    </label>
                    <button class="seo-btn seo-btn-primary" type="submit">AI SEO Başlat</button>
                </form>
            </div>

            <div class="seo-card space-y-4">
                <div>
                    <h3 class="text-lg font-black text-slate-950">XML Dosyaları</h3>
                    <p class="text-sm text-slate-500">Arama motorları ve ürün keşfi için public XML dosyalarını üretir.</p>
                </div>

                <div class="grid gap-3 text-sm">
                    <div>
                        <div class="font-black text-slate-700">Ürün Sitemap</div>
                        <a class="seo-link" href="{{ $xml['sitemap_url'] }}" target="_blank" rel="noopener">{{ $xml['sitemap_url'] }}</a>
                        <div class="text-xs text-slate-500">{{ $xml['sitemap_exists'] ? 'Güncel: '.$xml['sitemap_updated'].' · '.number_format($xml['sitemap_size']).' byte' : 'Henüz oluşturulmadı' }}</div>
                    </div>
                    <div>
                        <div class="font-black text-slate-700">Ürün Metadata XML</div>
                        <a class="seo-link" href="{{ $xml['metadata_url'] }}" target="_blank" rel="noopener">{{ $xml['metadata_url'] }}</a>
                        <div class="text-xs text-slate-500">{{ $xml['metadata_exists'] ? 'Güncel: '.$xml['metadata_updated'].' · '.number_format($xml['metadata_size']).' byte' : 'Henüz oluşturulmadı' }}</div>
                    </div>
                </div>

                <div class="seo-actions">
                    <form method="POST" action="{{ route('admin.seo-automation.xml') }}">
                        @csrf
                        <button class="seo-btn seo-btn-green" type="submit">XML Oluştur</button>
                    </form>
                    <form method="POST" action="{{ route('admin.seo-automation.base-seo') }}">
                        @csrf
                        <button class="seo-btn seo-btn-blue" type="submit">Template SEO + XML</button>
                    </form>
                    <form method="POST" action="{{ route('admin.seo-automation.cache') }}">
                        @csrf
                        <button class="seo-btn" type="submit">Cache Temizle</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="seo-card space-y-3">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-black text-slate-950">AI SEO Log</h3>
                    <p class="text-sm text-slate-500">Son arka plan işlem satırları.</p>
                </div>
            </div>
            <pre class="seo-log">@forelse($logLines as $line){{ $line }}
@empty Log henüz yok.
@endforelse</pre>
        </div>
    </div>
</x-layouts.admin>
