<?php

namespace App\Services\Cargo\Providers;

use App\Models\Order;
use App\Services\Cargo\Contracts\CargoProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * PTT Kargo — Kurumsal API
 *
 * Dokümantasyon: https://www.ptt.gov.tr/tr/kurumsal/kargo-api
 * Credentials: api_key + customer_id
 */
class PttCargoProvider implements CargoProvider
{
    private const BASE_URL   = 'https://apigw.ptt.gov.tr/kargo/v1';
    private const SHIP_PATH  = '/gonderi';
    private const TRACK_PATH = '/sorgula';

    private string $apiKey;
    private string $customerId;

    public function __construct(array $credentials)
    {
        $this->apiKey     = (string) ($credentials['api_key'] ?? '');
        $this->customerId = (string) ($credentials['customer_id'] ?? '');

        if ($this->apiKey === '' || $this->customerId === '') {
            throw new RuntimeException('PTT Kargo: api_key ve customer_id zorunludur.');
        }
    }

    public function code(): string { return 'PTT'; }
    public function displayName(): string { return 'PTT Kargo'; }

    public function createShipment(Order $order): array
    {
        $payload = [
            'musteriNo'       => $this->customerId,
            'aliciAd'         => $order->customer_name,
            'aliciTelefon'    => preg_replace('/\D/', '', $order->customer_phone),
            'aliciAdres'      => $order->shipping_address,
            'aliciIl'         => $order->shipping_city ?? '',
            'aliciIlce'       => $order->shipping_district ?? '',
            'gonderiRefNo'    => $order->merchant_oid,
            'agirlık'         => max(1, (int) ceil($order->total_cents / 50000)),
            'desi'            => max(1, (int) ceil($order->total_cents / 10000)),
            'icerik'          => 'E-ticaret gönderisi',
        ];

        $response = Http::withHeaders(['X-API-Key' => $this->apiKey])
            ->timeout(20)
            ->retry(2, 500)
            ->post(self::BASE_URL . self::SHIP_PATH, $payload)
            ->throw()
            ->json();

        if (empty($response['barkodNo'])) {
            throw new RuntimeException('PTT Kargo: Barkod alınamadı. ' . json_encode($response));
        }

        $barkod = (string) $response['barkodNo'];

        return [
            'tracking_number' => $barkod,
            'tracking_url'    => 'https://gonderitakip.ptt.gov.tr/' . $barkod,
            'metadata'        => $response,
        ];
    }

    public function track(string $trackingNumber): array
    {
        $response = Http::withHeaders(['X-API-Key' => $this->apiKey])
            ->timeout(15)
            ->get(self::BASE_URL . self::TRACK_PATH . '/' . $trackingNumber)
            ->throw()
            ->json();

        $status = $this->normalizeStatus((string) ($response['sonDurum'] ?? ''));
        $events = array_map(fn (array $e) => [
            'description' => $e['aciklama'] ?? '',
            'date'        => $e['tarih'] ?? '',
            'location'    => $e['konum'] ?? '',
        ], $response['hareketler'] ?? []);

        return [
            'status'   => $status,
            'events'   => $events,
            'metadata' => $response,
        ];
    }

    private function normalizeStatus(string $raw): string
    {
        $raw = mb_strtolower($raw);

        return match (true) {
            str_contains($raw, 'teslim edildi')                       => 'delivered',
            str_contains($raw, 'dağıtımda') || str_contains($raw, 'yolda') => 'out_for_delivery',
            str_contains($raw, 'aktarım') || str_contains($raw, 'transit')  => 'in_transit',
            str_contains($raw, 'iade')                                => 'returned',
            str_contains($raw, 'alındı') || str_contains($raw, 'kabul')     => 'pending',
            default => 'unknown',
        };
    }
}
