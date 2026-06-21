<?php

namespace App\Services\Cargo\Contracts;

use App\Models\Order;

interface CargoProvider
{
    /**
     * Kargo firmasında gönderi oluşturur.
     *
     * @return array{tracking_number: string, tracking_url: string|null, metadata: array<string,mixed>}
     */
    public function createShipment(Order $order): array;

    /**
     * Kargo takip numarasından güncel durumu sorgular.
     *
     * @return array{status: string, events: array<int,array<string,mixed>>, metadata: array<string,mixed>}
     */
    public function track(string $trackingNumber): array;

    /** Provider kodu: YURTICI | ARAS | PTT | MNG */
    public function code(): string;

    /** İnsan-okunur isim */
    public function displayName(): string;
}
