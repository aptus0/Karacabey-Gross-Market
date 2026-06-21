<?php

namespace App\Services\Cargo\Providers;

use App\Models\Order;
use App\Services\Cargo\Contracts\CargoProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Yurtiçi Kargo — Müşteri Entegrasyon Servisi
 *
 * Dokümantasyon: https://customerintegration.yurticikargo.com
 * Credentials: clientNumber + password
 */
class YurticiCargoProvider implements CargoProvider
{
    private const BASE_URL   = 'https://customerintegration.yurticikargo.com/integration';
    private const TOKEN_PATH = '/token';
    private const SHIP_PATH  = '/v1/shipments';
    private const TRACK_PATH = '/v1/shipments';

    private string $clientNumber;
    private string $password;
    private ?string $tokenCache = null;

    public function __construct(array $credentials)
    {
        $this->clientNumber = (string) ($credentials['client_number'] ?? '');
        $this->password     = (string) ($credentials['password'] ?? '');

        if ($this->clientNumber === '' || $this->password === '') {
            throw new RuntimeException('Yurtiçi Kargo: client_number ve password zorunludur.');
        }
    }

    public function code(): string { return 'YURTICI'; }
    public function displayName(): string { return 'Yurtiçi Kargo'; }

    public function createShipment(Order $order): array
    {
        $token = $this->getToken();

        $payload = [
            'clientNumber'    => $this->clientNumber,
            'senderName'      => config('app.name', 'Karacabey Gross Market'),
            'receiverName'    => $order->customer_name,
            'receiverPhone'   => $order->customer_phone,
            'receiverAddress' => $order->shipping_address,
            'receiverCity'    => $order->shipping_city ?? '',
            'receiverDistrict'=> $order->shipping_district ?? '',
            'desi'            => $this->estimateDesi($order),
            'quantity'        => 1,
            'orderNo'         => $order->merchant_oid,
            'description'     => 'KGM Sipariş #' . $order->merchant_oid,
        ];

        $response = Http::withToken($token)
            ->timeout(20)
            ->retry(2, 500)
            ->post(self::BASE_URL . self::SHIP_PATH, $payload)
            ->throw()
            ->json();

        if (empty($response['barcode'])) {
            throw new RuntimeException('Yurtiçi Kargo: Barkod alınamadı. ' . json_encode($response));
        }

        $barcode = (string) $response['barcode'];

        return [
            'tracking_number' => $barcode,
            'tracking_url'    => 'https://www.yurticikargo.com/tr/online-islemler/gonderi-sorgula?code=' . $barcode,
            'metadata'        => $response,
        ];
    }

    public function track(string $trackingNumber): array
    {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->timeout(15)
            ->get(self::BASE_URL . self::TRACK_PATH . '/' . $trackingNumber . '/events')
            ->throw()
            ->json();

        $events = $response['events'] ?? [];
        $status = $this->normalizeStatus($response['lastStatus'] ?? '');

        return [
            'status'   => $status,
            'events'   => $events,
            'metadata' => $response,
        ];
    }

    private function getToken(): string
    {
        if ($this->tokenCache !== null) {
            return $this->tokenCache;
        }

        $response = Http::asForm()
            ->timeout(10)
            ->post(self::BASE_URL . self::TOKEN_PATH, [
                'grant_type' => 'password',
                'username'   => $this->clientNumber,
                'password'   => $this->password,
            ])
            ->throw()
            ->json();

        if (empty($response['access_token'])) {
            throw new RuntimeException('Yurtiçi Kargo: Token alınamadı.');
        }

        return $this->tokenCache = (string) $response['access_token'];
    }

    private function estimateDesi(Order $order): int
    {
        // Ürün ağırlığı/desi bilgisi yoksa varsayılan 1 desi
        return max(1, (int) ceil($order->total_cents / 10000));
    }

    private function normalizeStatus(string $raw): string
    {
        $raw = mb_strtolower($raw);

        return match (true) {
            str_contains($raw, 'teslim') && !str_contains($raw, 'şube') => 'delivered',
            str_contains($raw, 'yolda') || str_contains($raw, 'transit') => 'in_transit',
            str_contains($raw, 'şube') || str_contains($raw, 'dağıtım') => 'out_for_delivery',
            str_contains($raw, 'iade')                                   => 'returned',
            str_contains($raw, 'bekle') || str_contains($raw, 'kabul')   => 'pending',
            default => 'unknown',
        };
    }
}
