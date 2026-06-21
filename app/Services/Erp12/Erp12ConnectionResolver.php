<?php

namespace App\Services\Erp12;

use App\Models\DataConnection;
use App\Services\DataIntegration\DataSourceBrowser;
use App\Services\DataIntegration\NetworkAccessGuard;
use PDO;

final class Erp12ConnectionResolver
{
    public function __construct(
        private readonly DataSourceBrowser $browser,
        private readonly NetworkAccessGuard $network,
    ) {
    }

    public function connection(?int $tenantId = null): ?DataConnection
    {
        return DataConnection::query()
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->whereIn('driver', ['dblib', 'sqlsrv'])
            ->where(function ($query): void {
                $query->where('database', 'ERP12')
                    ->orWhere('name', 'like', '%ERP12%')
                    ->orWhere('name', 'like', '%POS%');
            })
            ->orderByRaw("CASE WHEN last_test_status = 'success' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->first();
    }

    public function connect(?int $tenantId = null): ?PDO
    {
        $connection = $this->connection($tenantId);
        if (! $connection) {
            return null;
        }

        $this->network->assertSourceAllowed($connection);

        return $this->browser->connect($connection);
    }
}
