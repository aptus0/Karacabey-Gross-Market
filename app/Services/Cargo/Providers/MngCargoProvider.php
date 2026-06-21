<?php

namespace App\Services\Cargo\Providers;

use App\Models\Order;
use App\Services\Cargo\Contracts\CargoProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * MNG Kargo — Kurumsal API
 *
 * Dokümantasyon: https://api.mngkargo.com.tr/docs
 * Credentials: api_key + merchant_code
 */
class MngCargoProvider implements CargoProvider
{
    private const BASE_URL   = 'https://api.mngkargo.com.tr/api/v1';
    private const TOKEN_PATH = '/auth/login';
    private const SHIP_PATH  = '/shipment/create';
    private const TRACK_PATH = '/shipment/query';

    private string $apiKey;
    private string $merchantCode;
    private ?string $tokenCache = null;

    public function __construct(array $credentials)
    {
        $this->apiKey       = (string) ($credentials['api_key'] ?? '');
        $this->merchantCode = (string) ($credentials['merchant_code'] ?? '');

        if ($this->apiKey === '' || $this->merchantCode === '') {
            throw new RuntimeException('MNG Kargo: api_key ve merchant_code zorunludur.');
        }
    }

    public function code(): string { return 'MNG'; }
    public function displayName(): string { return 'MNG Kargo'; }

    public function createShipment(Order $order): array
    {
        $token = $this->getToken();

        $payload = [
            'merchantCode'   => $this->merchantCode,
            'receiverName'   => $order->customer_name,
            'receiverPhone'  => preg_replace('/\D/', '', $order->customer_phone),
            'receiverAddress'=> $order->shipping_address,
            'receiverCity'   => $order->shipping_city ?? '',
            'receiverDistrict'=> $order->shipping_district ?? '',
            'referenceCode'  => $order->merchant_oid,
            'weight'         => max(0.5, $order->total_cents / 100000),
            'quantity'       => 1,
            'description'    => 'KGM Sipariş #' . $order->merchant_oid,
        ];

        $response = Http::withToken($token)
            ->timeout(20)
            ->retry(2, 500)
            ->post(self::BASE_URL . self::SHIP_PATH, $payload)
            ->throw()
            ->json();

        if (empty($response['data']['trackingNo'])) {
            throw new RuntimeException('MNG Kargo: Takip numarası alınamadı. ' . json_encode($response));
        }

        $tracking = (string) $response['data']['trackingNo'];

        return [
            'tracking_number' => $tracking,
            'tracking_url'    => 'https://www.mngkargo.com.tr/tr/gonidtakibi?no=' . $tracking,
            'metadata'        => $response['data'] ?? [],
        ];
    }

    public function track(string $trackingNumber): array
    {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->timeout(15)
            ->post(self::BASE_URL . self::TRACK_PATH, [
                'trackingNo' => $trackingNumber,
            ])
            ->throw()
            ->json();

        $data   = $response['data'] ?? [];
        $status = $this->normalizeStatus((string) ($data['statusCode'] ?? ''));
        $events = array_map(fn (array $e) => [
            'description' => $e['description'] ?? '',
            'date'        => $e['date'] ?? '',
            'location'    => $e['location'] ?? '',
        ], $data['events'] ?? []);

        return [
            'status'   => $status,
            'events'   => $events,
            'metadata' => $data,
        ];
    }

    private function getToken(): string
    {
        if ($this->tokenCache !== null) {
            return $this->tokenCache;
        }

        $response = Http::timeout(10)
            ->post(self::BASE_URL . self::TOKEN_PATH, [
                'apiKey'       => $this->apiKey,
                'merchantCode' => $this->merchantCode,
            ])
            ->throw()
            ->json();

        if (empty($response['data']['token'])) {
            throw new RuntimeException('MNG Kargo: Token alınamadı.');
        }

        return $this->tokenCache = (string) $response['data']['token'];
    }

    private function normalizeStatus(string $code): string
    {
        return match ($code) {
            'DELIVERED'        => 'delivered',
            'OUT_FOR_DELIVERY' => 'out_for_delivery',
            'IN_TRANSIT'       => 'in_transit',
            'RETURNED'         => 'returned',
            'EXCEPTION'        => 'exception',
            default            => 'pending',
        };
    }
}
