<?php

namespace App\Console\Commands;

use App\Services\Erp12\Erp12ConnectionResolver;
use App\Services\Erp12\Erp12SnapshotImportService;
use App\Support\TenantResolver;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Throwable;

class SyncErp12AdminSnapshot extends Command
{
    protected $signature = 'erp12:sync-admin-snapshot {--tenant-id= : Tenant ID}';

    protected $description = 'ERP12 cari, fatura ve fatura satırlarını admin panel için yerel veritabanına aktarır.';

    public function handle(Erp12ConnectionResolver $resolver, Erp12SnapshotImportService $importer, TenantResolver $tenants): int
    {
        $tenantId = $this->option('tenant-id');
        if ($tenantId === null || $tenantId === '') {
            $tenantId = $tenants->resolve(Request::create('/admin', 'GET', [], [], [], [
                'HTTP_HOST' => 'panel.karacabeygrossmarket.com',
            ]))->id;
        }

        $tenantId = (int) $tenantId;
        $connection = $resolver->connection($tenantId);
        if (! $connection) {
            $this->error('ERP12 veri bağlantısı bulunamadı.');
            return self::FAILURE;
        }

        $this->info("ERP12 admin snapshot aktarımı başlıyor. Tenant: {$tenantId}, bağlantı: #{$connection->id} {$connection->name}");

        try {
            $pdo = $resolver->connect($tenantId);
        } catch (Throwable $e) {
            $connection->forceFill([
                'last_tested_at' => now(),
                'last_test_status' => 'fail',
                'last_test_message' => $e->getMessage(),
            ])->save();

            $this->error($e->getMessage());
            return self::FAILURE;
        }

        if (! $pdo) {
            $this->error('ERP12 bağlantısı kurulamadı.');
            return self::FAILURE;
        }

        $stats = $importer->import($pdo, $tenantId);

        $connection->forceFill([
            'last_tested_at' => now(),
            'last_test_status' => 'success',
            'last_test_message' => "ERP12 admin snapshot: {$stats['cariler']} cari, {$stats['faturalar']} fatura, {$stats['satirlar']} satır aktarıldı.",
        ])->save();

        $this->info("Aktarım tamamlandı: {$stats['cariler']} cari, {$stats['faturalar']} fatura, {$stats['satirlar']} fatura satırı.");

        return self::SUCCESS;
    }
}
