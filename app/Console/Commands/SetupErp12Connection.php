<?php

namespace App\Console\Commands;

use App\Models\DataConnection;
use Illuminate\Console\Command;

class SetupErp12Connection extends Command
{
    protected $signature = 'erp12:setup-connection
        {--tenant=1 : Local tenant id}
        {--name=ERP12 POS Canli : Admin connection name}
        {--host=192.168.1.17 : SQL Server host}
        {--port=6066 : SQL Server TCP port}
        {--database=ERP12 : SQL Server database}
        {--username=sa : SQL Server username}
        {--password= : SQL Server password. Falls back to ERP12_DB_PASSWORD}
        {--price-list=1016 : STOK_FIYAT_AD id for product sync}
        {--disable-sync : Create connection but disable automatic product sync}';

    protected $description = 'ERP12 POS SQL Server bağlantısını admin veri bağlantılarına güvenli şekilde kaydeder';

    public function handle(): int
    {
        $tenantId = (int) $this->option('tenant');
        $password = (string) ($this->option('password') ?: env('ERP12_DB_PASSWORD', ''));
        if ($password === '') {
            $this->error('SQL Server parolası gerekli. --password veya ERP12_DB_PASSWORD kullanın.');

            return self::FAILURE;
        }

        $connection = DataConnection::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'name' => (string) $this->option('name'),
            ],
            [
                'driver' => in_array('dblib', \PDO::getAvailableDrivers(), true) ? 'dblib' : 'sqlsrv',
                'host' => (string) $this->option('host'),
                'port' => (int) $this->option('port'),
                'database' => (string) $this->option('database'),
                'username' => (string) $this->option('username'),
                'password' => $password,
                'extra' => [
                    'trust_server_certificate' => 1,
                    'encrypt' => 0,
                    'erp12_sync' => [
                        'enabled' => ! (bool) $this->option('disable-sync'),
                        'price_list_id' => (int) $this->option('price-list'),
                        'limit' => 50000,
                    ],
                    'product_sync' => [
                        'allowed_source_ips' => '192.168.0.0/16,10.0.0.0/8,172.16.0.0/12',
                    ],
                ],
            ],
        );

        $this->info("ERP12 bağlantısı kaydedildi: #{$connection->id} {$connection->name}");
        $this->line('Otomatik ürün sync: '.(data_get($connection->extra, 'erp12_sync.enabled') ? 'aktif' : 'pasif'));

        return self::SUCCESS;
    }
}
