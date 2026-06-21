@extends('admin.layout')

@section('title', 'Bakım Modu')

@section('content')
@php
    $fmt = fn ($value) => $value ? \Carbon\Carbon::parse($value)->format('Y-m-d\TH:i') : '';
@endphp

<div class="top">
    <div>
        <h1>Bakım Modu</h1>
        <p class="muted">Web, checkout, API yazma işlemleri ve mobil kanal için kontrollü bakım yönetin.</p>
    </div>
    <a class="btn secondary" href="{{ config('commerce.domains.storefront', 'https://karacabeygrossmarket.com') }}" target="_blank" rel="noreferrer">Siteyi Aç</a>
</div>

@if(session('status'))
    <div class="status">{{ session('status') }}</div>
@endif

<div class="maintenance-grid">
    <section class="card maintenance-card {{ $status['active'] ? 'maintenance-card--active' : '' }}">
        <span class="maintenance-state">{{ $status['active'] ? 'Aktif' : ($status['enabled'] ? 'Planlandı' : 'Kapalı') }}</span>
        <h2>{{ $status['title'] }}</h2>
        <p>{{ $status['message'] }}</p>
        <dl class="maintenance-meta">
            <div><dt>Başlangıç</dt><dd>{{ $status['starts_at'] ? \Carbon\Carbon::parse($status['starts_at'])->format('d.m.Y H:i') : 'Hemen' }}</dd></div>
            <div><dt>Bitiş</dt><dd>{{ $status['ends_at'] ? \Carbon\Carbon::parse($status['ends_at'])->format('d.m.Y H:i') : 'Manuel kapatılacak' }}</dd></div>
            <div><dt>Güncelleyen</dt><dd>{{ $status['updated_by'] ?: '—' }}</dd></div>
        </dl>
    </section>

    <form class="card maintenance-form" method="POST" action="{{ route('admin.maintenance.update') }}">
        @csrf
        @method('PUT')

        <label class="switch-row">
            <span>
                <strong>Bakım modunu aç</strong>
                <small>Müşteriye bakım ekranı gösterilir. Panel açık kalır.</small>
            </span>
            <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $status['enabled']))>
        </label>

        <div class="form-grid two">
            <label>
                <span>Başlık</span>
                <input name="title" value="{{ old('title', $status['title']) }}" maxlength="120" required>
                @error('title') <small class="error">{{ $message }}</small> @enderror
            </label>
            <label>
                <span>Başlangıç</span>
                <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $fmt($status['starts_at'])) }}">
                @error('starts_at') <small class="error">{{ $message }}</small> @enderror
            </label>
            <label class="span-2">
                <span>Mesaj</span>
                <textarea name="message" rows="4" maxlength="500" required>{{ old('message', $status['message']) }}</textarea>
                @error('message') <small class="error">{{ $message }}</small> @enderror
            </label>
            <label>
                <span>Bitiş</span>
                <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $fmt($status['ends_at'])) }}">
                @error('ends_at') <small class="error">{{ $message }}</small> @enderror
            </label>
        </div>

        <div class="maintenance-toggles">
            @foreach([
                'storefront' => ['Web vitrini kapat', 'karacabeygrossmarket.com bakım sayfasına düşer.'],
                'checkout' => ['Sepet/checkout kilitle', 'Sepet yazma ve ödeme başlatma geçici kapatılır.'],
                'api_writes' => ['API yazma işlemlerini kapat', 'POST/PATCH/DELETE isteklerine 503 döner.'],
                'mobile' => ['Mobil uygulamaya bildir', 'Mobil durum endpointinden bakım bilgisi alır.'],
            ] as $key => [$title, $desc])
                <label class="toggle-card">
                    <input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $status['channels'][$key] ?? false))>
                    <span><strong>{{ $title }}</strong><small>{{ $desc }}</small></span>
                </label>
            @endforeach
        </div>

        <div class="actions">
            <button class="btn" type="submit">Bakım Ayarlarını Kaydet</button>
            <a class="btn secondary" href="/api/v1/system/status" target="_blank" rel="noreferrer">Status API</a>
        </div>
    </form>
</div>

<style nonce="{{ request()->attributes->get('csp_nonce') }}">
    .muted{color:var(--muted);margin-top:6px;font-size:13px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:40px;border:0;border-radius:10px;background:var(--orange);color:#fff;padding:0 16px;font-weight:900}.btn.secondary{border:1px solid var(--border);background:#fff;color:var(--text)}.maintenance-grid{display:grid;grid-template-columns:minmax(280px,.85fr) minmax(0,1.4fr);gap:18px}.maintenance-card{position:sticky;top:86px;align-self:start;background:linear-gradient(180deg,#fff,#fff7ed)}.maintenance-card--active{border-color:#fdba74;box-shadow:0 10px 35px rgba(249,115,22,.13)}.maintenance-state{display:inline-flex;border-radius:999px;background:#111827;color:#fff;padding:7px 12px;font-size:12px;font-weight:900}.maintenance-card--active .maintenance-state{background:#dc2626}.maintenance-card h2{margin-top:18px;font-size:24px;letter-spacing:-.04em}.maintenance-card p{margin-top:10px;color:var(--muted);line-height:1.7}.maintenance-meta{display:grid;gap:10px;margin-top:18px}.maintenance-meta div{border:1px solid var(--border);border-radius:10px;background:#fff;padding:12px}.maintenance-meta dt{font-size:11px;font-weight:900;text-transform:uppercase;color:var(--muted);letter-spacing:.08em}.maintenance-meta dd{margin-top:4px;font-size:13px;font-weight:900}.maintenance-form{display:grid;gap:18px}.switch-row{display:flex;align-items:center;justify-content:space-between;gap:16px;border:1px solid #fed7aa;border-radius:12px;background:#fff7ed;padding:16px}.switch-row strong,.toggle-card strong{display:block;font-weight:900}.switch-row small,.toggle-card small{display:block;color:var(--muted);font-size:12px;line-height:1.5}.switch-row input{width:24px;height:24px;accent-color:var(--orange)}.form-grid{display:grid;gap:14px}.form-grid.two{grid-template-columns:repeat(2,minmax(0,1fr))}.form-grid label{display:grid;gap:7px}.form-grid label span{font-size:12px;font-weight:900;text-transform:uppercase;color:var(--muted);letter-spacing:.08em}.form-grid input,.form-grid textarea{width:100%;border:1px solid var(--border);border-radius:10px;background:#fff;padding:12px;font-weight:700}.form-grid textarea{resize:vertical;line-height:1.6}.span-2{grid-column:1/-1}.error{color:var(--danger);font-weight:800}.maintenance-toggles{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.toggle-card{display:flex;gap:12px;border:1px solid var(--border);border-radius:12px;background:#fff;padding:14px}.toggle-card input{margin-top:3px;width:18px;height:18px;accent-color:var(--orange)}.actions{display:flex;gap:10px;flex-wrap:wrap}@media(max-width:900px){.maintenance-grid,.form-grid.two,.maintenance-toggles{grid-template-columns:1fr}.maintenance-card{position:static}.span-2{grid-column:auto}}
</style>
@endsection
