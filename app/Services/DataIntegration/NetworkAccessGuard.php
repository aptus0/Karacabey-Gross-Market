<?php

namespace App\Services\DataIntegration;

use App\Models\DataConnection;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpFoundation\IpUtils;

final class NetworkAccessGuard
{
    private const PRIVATE_RANGES = [
        '127.0.0.0/8',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '::1/128',
        'fc00::/7',
        'fe80::/10',
    ];

    public function assertRequestAllowed(Request $request, DataConnection $connection): void
    {
        $clientIp = (string) $request->ip();

        if ($this->isPrivateIp($clientIp) || $this->matchesConfiguredRanges($clientIp, $connection, 'allowed_client_ips')) {
            return;
        }

        throw new RuntimeException('Bu veri bağlantısı sadece yerel ağdan veya izinli dış IP adreslerinden çalıştırılabilir.');
    }

    public function assertSourceAllowed(DataConnection $connection): void
    {
        if ($connection->driver === 'sqlite') {
            return;
        }

        $ips = $this->resolveHostIps((string) $connection->host);
        foreach ($ips as $ip) {
            if ($this->isPrivateIp($ip) || $this->matchesConfiguredRanges($ip, $connection, 'allowed_source_ips')) {
                return;
            }
        }

        throw new RuntimeException('Kaynak sunucu yerel ağda değil. Public kaynaklar için izinli kaynak IP/CIDR tanımlayın.');
    }

    public function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false
            && IpUtils::checkIp($ip, self::PRIVATE_RANGES);
    }

    /**
     * @return array<int, string>
     */
    private function resolveHostIps(string $host): array
    {
        $host = trim($host);
        if ($host === '') {
            return [];
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];
        $ips = [];
        foreach ($records as $record) {
            if (! empty($record['ip'])) {
                $ips[] = $record['ip'];
            }
            if (! empty($record['ipv6'])) {
                $ips[] = $record['ipv6'];
            }
        }

        $fallback = @gethostbyname($host);
        if ($fallback && $fallback !== $host) {
            $ips[] = $fallback;
        }

        return array_values(array_unique(array_filter($ips)));
    }

    private function matchesConfiguredRanges(string $ip, DataConnection $connection, string $key): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        $ranges = $this->ranges($connection, $key);
        return $ranges !== [] && IpUtils::checkIp($ip, $ranges);
    }

    /**
     * @return array<int, string>
     */
    private function ranges(DataConnection $connection, string $key): array
    {
        $value = data_get($connection->extra ?? [], "product_sync.{$key}", '');
        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string) $value) ?: [])));
    }
}
