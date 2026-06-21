<?php

namespace App\Jobs;

use App\Services\Erp12\Erp12ConnectionResolver;
use App\Services\Erp12\Erp12ProductImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncErp12ProductsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public ?int $tenantId = null)
    {
        $this->onQueue('default');
    }

    public function handle(Erp12ConnectionResolver $resolver, Erp12ProductImportService $importer): void
    {
        $connection = $resolver->connection($this->tenantId);
        if (! $connection || ! data_get($connection->extra ?? [], 'erp12_sync.enabled', false)) {
            return;
        }

        try {
            $source = $resolver->connect((int) $connection->tenant_id);
            if (! $source) {
                return;
            }

            $stats = $importer->import(
                $source,
                (int) $connection->tenant_id,
                (int) data_get($connection->extra ?? [], 'erp12_sync.price_list_id', 1016),
                (int) data_get($connection->extra ?? [], 'erp12_sync.limit', 50000),
            );

            $connection->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => $stats['failed'] > 0 ? 'fail' : 'success',
                'last_test_message' => "ERP12 ürün sync: {$stats['created']} yeni, {$stats['updated']} güncel, {$stats['skipped']} aynı, {$stats['failed']} hata.",
            ])->save();
        } catch (\Throwable $e) {
            $connection->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => 'fail',
                'last_test_message' => $e->getMessage(),
            ])->save();

            Log::warning('ERP12 product sync skipped or failed', [
                'connection_id' => $connection->id,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
