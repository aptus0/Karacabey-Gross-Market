<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Services\Paytr\PaytrClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function index(Request $request, PaytrClient $paytr): JsonResponse
    {
        $methods = $request->user()
            ->paymentMethods()
            ->latest()
            ->get();

        $remote = null;
        $firstMethod = $methods->first();

        if ($firstMethod) {
            $remote = $paytr->cardList($firstMethod->utoken);
        }

        return response()->json([
            'data' => $methods,
            'gateway' => $remote,
        ]);
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod, PaytrClient $paytr): JsonResponse
    {
        abort_unless($paymentMethod->user_id === $request->user()->id, 403);

        $response = $paytr->deleteCard($paymentMethod->utoken, $paymentMethod->ctoken);

        if (($response['status'] ?? null) === 'success') {
            $paymentMethod->delete();
        }

        return response()->json(['data' => $response]);
    }
}
