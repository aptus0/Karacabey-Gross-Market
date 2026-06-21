<?php

namespace App\Services\Cargo\Providers;

use App\Models\Order;
use App\Services\Cargo\Contracts\CargoProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Aras Kargo — REST API v2
 *
 * Dokümantasyon: https://api.araskargo.com.tr/docs
 * Credentials: username + password + customer_code
 */
class ArasCargoProvider implements CargoProvider
{
    private const BASE_URL  = 'https://api.araskargo.com.tr/api/v2';
    private const AUTH_PATH = '/auth/token';
    private const SHIP_PATH = '/shipment';
    private const TRACK_PATH= '/shipment/query';

    private string $username;
    private string $password;
    private string $customerCode;
    private ?string $tokenCache = null;

    public function __construct(array $credentials)
    {
        $this->username     = (string) ($credentials['username'] ?? '');
        $this->password     = (string) ($credentials['password'] ?? '');
        $this->customerCode = (string) ($credentials['customer_code'] ?? '');

        if ($this->username === '' || $this->password === '') {
            throw new RuntimeException('Aras Kargo: username ve password zorunludur.');
        }
    }

    public function code(): string { return 'ARAS'; }
    public function displayName(): string { return 'Aras Kargo'; }

    public function createShipment(Order $order): array
    {
        $token = $this->getToken();

        $payload = [
            'CUSTOMER_CODE'    => $this->customerCode,
            'RECEIVER_NAME'    => $order->customer_name,
            'RECEIVER_PHONE'   => preg_replace('/\D/', '', $order->customer_phone),
            'RECEIVER_ADDRESS' => $order->shipping_address,
            'RECEIVER_CITY'    => $order->shipping_city ?? '',
            'RECEIVER_DISTRICT'=> $order->shipping_district ?? '',
            'SENDER_REFERENCE' => $order->merchant_oid,
            'PIECE'            => 1,
            'WEIGHT'           => max(1.0, $order->total_cents / 100000),
            'PRODUCT_TYPE'     => 'K', // Koli
        ];

        $response = Http::withToken($token)
            ->timeout(20)
            ->retry(2, 500)
            ->post(self::BASE_URL . self::SHIP_PATH, $payload)
            ->throw()
            ->json();

        if (empty($response['CARGO_KEY'])) {
            throw new RuntimeException('Aras Kargo: Kargo anahtarı alınamadı. ' . json_encode($response));
        }

        $cargoKey = (string) $response['CARGO_KEY'];

        return [
            'tracking_number' => $cargoKey,
            'tracking_url'    => 'https://kargotakip.araskargo.com.tr/' . $cargoKey,
            'metadata'        => $response,
        ];
    }

    public function track(string $trackingNumber): array
    {
        $token = $this->getToken();

        $response = Http::withToken($token)
            ->timeout(15)
            ->post(self::BASE_URL . self::TRACK_PATH, [
                'CARGO_KEY' => $trackingNumber,
            ])
            ->throw()
            ->json();

        $status = $this->normalizeStatus((string) ($response['LAST_STATUS_CODE'] ?? ''));
        $events = array_map(fn (array $e) => [
            'description' => $e['STATUS_DESCRIPTION'] ?? '',
            'date'        => $e['OPERATION_DATE'] ?? '',
            'location'    => $e['BRANCH_NAME'] ?? '',
        ], $response['EVENTS'] ?? []);

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

        $response = Http::timeout(10)
            ->post(self::BASE_URL . self::AUTH_PATH, [
                'UserName' => $this->username,
                'Password' => $this->password,
            ])
            ->throw()
            ->json();

        if (empty($response['Data'])) {
            throw new RuntimeException('Aras Kargo: Token alınamadı.');
        }

        return $this->tokenCache = (string) $response['Data'];
    }

    private function normalizeStatus(string $code): string
    {
        return match ($code) {
            '04' => 'delivered',
            '03' => 'out_for_delivery',
            '02' => 'in_transit',
            '13' => 'returned',
            '99' => 'exception',
            default => 'pending',
        };
    }
}
