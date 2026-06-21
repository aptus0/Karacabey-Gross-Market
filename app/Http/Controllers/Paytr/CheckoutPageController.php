<?php

namespace App\Http\Controllers\Paytr;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Contracts\View\View;

class CheckoutPageController extends Controller
{
    public function show(Order $order): View
    {
        $order->loadMissing('payment');

        abort_unless($order->payment?->provider_token, 404);

        return view('checkout.session', [
            'order' => $order,
            'iframeSrc' => rtrim(config('paytr.endpoints.iframe_secure'), '/').'/'.$order->payment->provider_token,
        ]);
    }
}
