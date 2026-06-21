<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Paytr\PaytrClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RefundController extends Controller
{
    public function store(Request $request, Payment $payment, PaytrClient $paytr): JsonResponse
    {
        $validated = $request->validate([
            'amount_cents' => ['required', 'integer', 'min:1', 'max:'.$payment->amount_cents],
            'reference_no' => ['nullable', 'alpha_num', 'max:64'],
        ]);

        abort_unless($payment->status === PaymentStatus::Paid || $payment->status === PaymentStatus::PartiallyRefunded, 422, 'Yalnizca basarili odemeler iade edilebilir.');

        $alreadyRefunded = (int) $payment->refunds()->where('status', 'success')->sum('amount_cents');
        abort_if($alreadyRefunded + $validated['amount_cents'] > $payment->amount_cents, 422, 'Iade tutari odeme tutarini asamaz.');

        $referenceNo = $validated['reference_no'] ?? 'KGMREF'.Str::upper(Str::random(16));
        $refund = $payment->refunds()->create([
            'reference_no' => $referenceNo,
            'amount_cents' => $validated['amount_cents'],
            'status' => 'pending',
        ]);

        $response = $paytr->refund($payment->merchant_oid, $validated['amount_cents'], $referenceNo);
        $success = ($response['status'] ?? null) === 'success';

        $refund->update([
            'status' => $success ? 'success' : 'error',
            'provider_payload' => $response,
        ]);

        if ($success) {
            $totalRefunded = $alreadyRefunded + $validated['amount_cents'];
            $payment->update([
                'status' => $totalRefunded >= $payment->amount_cents
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded,
            ]);
        }

        return response()->json(['data' => $refund->fresh()]);
    }
}
