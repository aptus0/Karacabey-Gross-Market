<?php

namespace App\Jobs;

use App\Models\DataConnection;
use App\Services\DataIntegration\NetworkAccessGuard;
use App\Services\DataIntegration\ProductDataImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncDataConnectionProductsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ?int $connectionId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(NetworkAccessGuard $network, ProductDataImportService $importer): void
    {
        $query = DataConnection::query()
            ->where('extra->product_sync->enabled', true);

        if ($this->connectionId !== null) {
            $query->whereKey($this->connectionId);
        }

        foreach ($query->get() as $connection) {
            $settings = data_get($connection->extra ?? [], 'product_sync', []);

            if (empty($settings['table']) || empty($settings['mapping']['name'])) {
                continue;
            }

            try {
                $network->assertSourceAllowed($connection);
                $stats = $importer->import($connection, (int) $connection->tenant_id, $settings);
                $connection->forceFill([
                    'last_tested_at' => now(),
                    'last_test_status' => 'success',
                    'last_test_message' => "Otomatik ürün senkronu: {$stats['created']} yeni, {$stats['updated']} güncel.",
                ])->save();
            } catch (\Throwable $e) {
                $connection->forceFill([
                    'last_tested_at' => now(),
                    'last_test_status' => 'fail',
                    'last_test_message' => $e->getMessage(),
                ])->save();

                Log::warning('Product data sync skipped or failed', [
                    'connection_id' => $connection->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
