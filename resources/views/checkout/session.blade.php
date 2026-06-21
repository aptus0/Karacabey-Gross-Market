<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Karacabey Gross Market Güvenli Ödeme</title>
    <link rel="stylesheet" href="{{ asset('assets/kgm-secure-pay.css') }}">
</head>
<body class="k0">
    <main class="k1" aria-label="Güvenli ödeme">
        <section class="k2">
            <div class="k3">
                <p class="k4">Karacabey Gross Market</p>
                <h1 class="k5">Güvenli Ödeme</h1>
                <p class="k6">Sipariş No: {{ $order->merchant_oid }}</p>
                <p class="k6">Kart bilgileriniz PayTR güvenli ödeme ekranında işlenir.</p>
            </div>
            <strong class="k7">{{ number_format($order->total_cents / 100, 2, ',', '.') }} {{ $order->currency }}</strong>
        </section>

        <section class="k8">
            <iframe
                src="{{ $iframeSrc }}"
                id="securepayframe"
                title="PayTR güvenli ödeme formu"
                frameborder="0"
                scrolling="no"
                class="k9"
                allow="payment"
            ></iframe>
        </section>
    </main>

    <script src="https://www.paytr.com/js/iframeResizer.min.js" defer></script>
    <script src="{{ asset('assets/kgm-secure-pay.js') }}" defer></script>
</body>
</html>
