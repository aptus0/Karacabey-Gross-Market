<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta property="csp-nonce" content="{{ request()->attributes->get('csp_nonce') }}">
    <title>{{ $header ?? 'Yönetim' }} — KGM Panel</title>
    @vite(['resources/css/app.css', 'resources/js/admin.js'])
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        /* Dock buton açık hâli */
        .dock-btn[aria-expanded="true"] { background-color: rgb(241 245 249); }
        .dock-btn[aria-expanded="true"] .dock-icon-wrap { transform: scale(1.1); }
        .dock-btn[aria-expanded="true"] .dock-chevron { transform: rotate(180deg); }
    </style>
    @stack('styles')
    {{ $head ?? '' }}
</head>
<body class="min-h-screen bg-slate-50 font-sans antialiased text-slate-900">

    @php
        $authUser = auth()->user();
        $initials = collect(explode(' ', trim((string) ($authUser?->name ?? 'Admin'))))
            ->filter()->take(2)
            ->map(fn (string $p) => strtoupper(substr($p, 0, 1)))
            ->implode('');
    @endphp

    {{-- ── TOP HEADER ────────────────────────────────────────────── --}}
    <header class="sticky top-0 z-30 h-14 border-b border-slate-200 bg-white shadow-sm">
        <div class="mx-auto flex h-full max-w-screen-2xl items-center gap-4 px-4 sm:px-6">

            {{-- Logo + Marka --}}
            <a href="{{ route('admin.dashboard') }}" class="flex shrink-0 items-center gap-2.5">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-orange-500 shadow-sm">
                    <img src="{{ asset('assets/kgm-logo-4k.png') }}" alt="KGM" class="h-5 w-auto">
                </div>
                <span class="hidden text-sm font-semibold text-slate-900 sm:block">Karacabey Gross Market</span>
            </a>

            {{-- Divider --}}
            <div class="hidden h-5 w-px bg-slate-200 sm:block"></div>

            {{-- Sayfa başlığı --}}
            <h1 class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-700 sm:text-base">
                {{ $header ?? 'Yönetim Paneli' }}
            </h1>

            {{-- Sağ aksiyon grubu --}}
            <div class="flex shrink-0 items-center gap-2">

                {{-- Mağazayı gör --}}
                <a href="{{ config('commerce.domains.storefront', '/') }}"
                   target="_blank" rel="noreferrer"
                   class="hidden items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:border-slate-300 hover:text-slate-900 sm:inline-flex">
                    <x-lucide-external-link class="h-3.5 w-3.5" />
                    Mağaza
                </a>

                {{-- Kullanıcı --}}
                <div class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5">
                    <div class="flex h-7 w-7 items-center justify-center rounded-md bg-orange-500 text-xs font-bold text-white">
                        {{ $initials ?: 'A' }}
                    </div>
                    <div class="hidden text-left sm:block">
                        <p class="text-xs font-semibold text-slate-900 leading-tight">{{ $authUser?->name ?? 'Admin' }}</p>
                        <p class="text-[10px] text-slate-400 leading-tight">Yönetici</p>
                    </div>
                </div>
            </div>
        </div>
    </header>

    {{-- ── MAIN CONTENT ──────────────────────────────────────────── --}}
    {{-- pb-16: bottom appbar için boşluk --}}
    <main class="mx-auto max-w-screen-2xl p-4 pb-20 sm:p-6 sm:pb-20 lg:p-8 lg:pb-20">
        {{ $slot }}
    </main>

    {{-- ── BOTTOM APPBAR ─────────────────────────────────────────── --}}
    <x-admin.appbar />

    {{-- Toast --}}
    <x-ui.toaster />

    @stack('scripts')
</body>
</html>
