<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Paytr\PaytrClient;
use Illuminate\Http\JsonResponse;

class PaymentStatusController extends Controller
{
    public function show(Payment $payment, PaytrClient $paytr): JsonResponse
    {
        return response()->json([
            'data' => [
                'local' => $payment->load('order', 'refunds'),
                'gateway' => $paytr->status($payment->merchant_oid),
            ],
        ]);
    }
}
