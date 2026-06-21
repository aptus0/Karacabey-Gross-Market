<?php

namespace App\Services\Paytr;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaytrClient
{
    public function getIframeToken(Order $order, string $userIp): array
    {
        $this->ensureConfigured();
        $order->loadMissing('items');

        $basket = base64_encode(json_encode($order->paytrBasket(), JSON_UNESCAPED_UNICODE));
        $paymentAmount = (string) $order->total_cents;
        $testMode = $this->testMode();
        $currency = $this->currency();
        $noInstallment = (string) config('paytr.no_installment', 0);
        $maxInstallment = (string) config('paytr.max_installment', 0);

        $hashStr = $this->merchantId()
            .$userIp
            .$order->merchant_oid
            .$order->customer_email
            .$paymentAmount
            .$basket
            .$noInstallment
            .$maxInstallment
            .$currency
            .$testMode;

        $payload = [
            'merchant_id' => $this->merchantId(),
            'user_ip' => $userIp,
            'merchant_oid' => $order->merchant_oid,
            'email' => $order->customer_email,
            'payment_amount' => $paymentAmount,
            'paytr_token' => $this->hmac($hashStr.$this->merchantSalt()),
            'user_basket' => $basket,
            'debug_on' => config('paytr.debug') ? '1' : '0',
            'no_installment' => $noInstallment,
            'max_installment' => $maxInstallment,
            'user_name' => $order->customer_name,
            'user_address' => $order->shipping_address,
            'user_phone' => $order->customer_phone,
            'merchant_ok_url' => config('paytr.ok_url'),
            'merchant_fail_url' => config('paytr.fail_url'),
            'timeout_limit' => (string) config('paytr.timeout_limit', 30),
            'currency' => $currency,
            'test_mode' => $testMode,
        ];

        $response = $this->postForm(config('paytr.endpoints.iframe_token'), $payload);

        if (($response['status'] ?? null) !== 'success' || empty($response['token'])) {
            throw new RuntimeException((string) ($response['reason'] ?? 'Odeme oturumu olusturulamadi.'));
        }

        return [
            'token' => $response['token'],
            'iframe_src' => rtrim(config('paytr.endpoints.iframe_secure'), '/').'/'.$response['token'],
            'raw' => $response,
        ];
    }

    public function getDirectPaymentParams(Order $order, string $userIp): array
    {
        $this->ensureConfigured();
        $order->loadMissing('items');

        $basket = json_encode($order->paytrBasket(), JSON_UNESCAPED_UNICODE);
        $paymentAmount = Money::centsToDecimal($order->total_cents);
        $installmentCount = '0';
        $testMode = $this->testMode();
        $non3d = '0';
        $currency = $this->currency();
        $paymentType = 'card';

        $hashStr = $this->merchantId()
            .$userIp
            .$order->merchant_oid
            .$order->customer_email
            .$paymentAmount
            .$paymentType
            .$installmentCount
            .$currency
            .$testMode
            .$non3d;

        return [
            'post_url' => config('paytr.endpoints.direct_payment'),
            'fields' => [
                'merchant_id' => $this->merchantId(),
                'user_ip' => $userIp,
                'merchant_oid' => $order->merchant_oid,
                'email' => $order->customer_email,
                'payment_type' => $paymentType,
                'payment_amount' => $paymentAmount,
                'installment_count' => $installmentCount,
                'currency' => $currency,
                'test_mode' => $testMode,
                'non_3d' => $non3d,
                'merchant_ok_url' => config('paytr.ok_url'),
                'merchant_fail_url' => config('paytr.fail_url'),
                'user_name' => $order->customer_name,
                'user_address' => $order->shipping_address,
                'user_phone' => $order->customer_phone,
                'user_basket' => $basket,
                'debug_on' => config('paytr.debug') ? '1' : '0',
                'client_lang' => 'tr',
                'paytr_token' => $this->hmac($hashStr.$this->merchantSalt()),
            ],
        ];
    }

    public function verifyCallback(array $payload): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        foreach (['merchant_oid', 'status', 'total_amount', 'hash'] as $key) {
            if (! isset($payload[$key])) {
                return false;
            }
        }

        $expected = $this->hmac(
            $payload['merchant_oid']
            .$this->merchantSalt()
            .$payload['status']
            .$payload['total_amount']
        );

        return hash_equals($expected, (string) $payload['hash']);
    }

    public function refund(string $merchantOid, int $amountCents, ?string $referenceNo = null): array
    {
        $this->ensureConfigured();
        $returnAmount = Money::centsToDecimal($amountCents);
        $hashStr = $this->merchantId().$merchantOid.$returnAmount.$this->merchantSalt();

        $payload = [
            'merchant_id' => $this->merchantId(),
            'merchant_oid' => $merchantOid,
            'return_amount' => $returnAmount,
            'paytr_token' => $this->hmac($hashStr),
        ];

        if ($referenceNo) {
            $payload['reference_no'] = $referenceNo;
        }

        return $this->postForm(config('paytr.endpoints.refund'), $payload, timeout: 90);
    }

    public function status(string $merchantOid): array
    {
        $this->ensureConfigured();
        $payload = [
            'merchant_id' => $this->merchantId(),
            'merchant_oid' => $merchantOid,
            'paytr_token' => $this->hmac($this->merchantId().$merchantOid.$this->merchantSalt()),
        ];

        return $this->postForm(config('paytr.endpoints.status'), $payload, timeout: 90);
    }

    public function cardList(string $utoken): array
    {
        $this->ensureConfigured();

        return $this->postForm(config('paytr.endpoints.card_list'), [
            'merchant_id' => $this->merchantId(),
            'utoken' => $utoken,
            'paytr_token' => $this->hmac($utoken.$this->merchantSalt()),
        ]);
    }

    public function deleteCard(string $utoken, string $ctoken): array
    {
        $this->ensureConfigured();

        return $this->postForm(config('paytr.endpoints.card_delete'), [
            'merchant_id' => $this->merchantId(),
            'utoken' => $utoken,
            'ctoken' => $ctoken,
            'paytr_token' => $this->hmac($ctoken.$utoken.$this->merchantSalt()),
        ]);
    }

    public function storedCardForm(Order $order, string $userIp, string $utoken, string $ctoken, bool $requiresCvv = false): array
    {
        $this->ensureConfigured();
        $order->loadMissing('items');

        $paymentAmount = Money::centsToDecimal($order->total_cents);
        $installmentCount = '0';
        $testMode = $this->testMode();
        $non3d = '0';
        $currency = $this->currency();
        $paymentType = 'card';

        $hashStr = $this->merchantId()
            .$userIp
            .$order->merchant_oid
            .$order->customer_email
            .$paymentAmount
            .$paymentType
            .$installmentCount
            .$currency
            .$testMode
            .$non3d;

        return [
            'post_url' => config('paytr.endpoints.direct_payment'),
            'requires_cvv' => $requiresCvv,
            'fields' => [
                'merchant_id' => $this->merchantId(),
                'user_ip' => $userIp,
                'merchant_oid' => $order->merchant_oid,
                'email' => $order->customer_email,
                'payment_type' => $paymentType,
                'payment_amount' => $paymentAmount,
                'installment_count' => $installmentCount,
                'currency' => $currency,
                'test_mode' => $testMode,
                'non_3d' => $non3d,
                'merchant_ok_url' => config('paytr.ok_url'),
                'merchant_fail_url' => config('paytr.fail_url'),
                'user_name' => $order->customer_name,
                'user_address' => $order->shipping_address,
                'user_phone' => $order->customer_phone,
                'user_basket' => json_encode($order->paytrBasket(), JSON_UNESCAPED_UNICODE),
                'debug_on' => config('paytr.debug') ? '1' : '0',
                'client_lang' => 'tr',
                'paytr_token' => $this->hmac($hashStr.$this->merchantSalt()),
                'utoken' => $utoken,
                'ctoken' => $ctoken,
            ],
        ];
    }


    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('PayTR ayarlari eksik. PAYTR_MERCHANT_ID, PAYTR_MERCHANT_KEY ve PAYTR_MERCHANT_SALT degerlerini .env icinde tanimlayin.');
        }
    }

    private function isConfigured(): bool
    {
        return $this->merchantId() !== ''
            && $this->merchantKey() !== ''
            && $this->merchantSalt() !== '';
    }

    private function postForm(string $url, array $payload, int $timeout = 20): array
    {
        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout($timeout)
                ->connectTimeout($timeout)
                ->retry(2, 250, function ($exception): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if ($exception instanceof RequestException) {
                        return $exception->response?->serverError() ?? false;
                    }

                    return false;
                })
                ->post($url, $payload)
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            throw new RuntimeException('Odeme servisi baglanti hatasi: '.$exception->getMessage(), previous: $exception);
        }

        if (! is_array($response)) {
            throw new RuntimeException('Odeme servisi gecersiz yanit dondurdu.');
        }

        return $response;
    }

    private function hmac(string $value): string
    {
        return base64_encode(hash_hmac('sha256', $value, $this->merchantKey(), true));
    }

    private function merchantId(): string
    {
        return (string) config('paytr.merchant_id');
    }

    private function merchantKey(): string
    {
        return (string) config('paytr.merchant_key');
    }

    private function merchantSalt(): string
    {
        return (string) config('paytr.merchant_salt');
    }

    private function testMode(): string
    {
        return config('paytr.test_mode') ? '1' : '0';
    }

    private function currency(): string
    {
        return (string) config('paytr.currency', 'TL');
    }
}
