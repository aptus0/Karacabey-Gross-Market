<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PrepareLiveCommerce extends Command
{
    protected $signature = 'kgm:prepare-live-commerce
        {--execute : Temizliği gerçekten uygula}
        {--scope=test : test veya all. Varsayılan sadece test verisini hedefler}
        {--confirm= : scope=all için KGM_LIVE_RESET yazılmalı}
        {--include-test-products : Deneme/test ürünlerini de temizle}
        {--keep-admin-logs : Yönetici giriş kayıtlarını koru}';

    protected $description = 'Canlıya hazırlık için test siparişi, test müşteri ve çalışma loglarını güvenli şekilde temizler.';

    private const EPHEMERAL_TABLES = [
        'mobile_events',
        'tracking_events',
        'cookie_consents',
        'idempotency_keys',
        'api_security_events',
        'api_daily_metrics',
        'outbox_events',
        'cloudflare_purge_jobs',
        'failed_jobs',
        'job_batches',
        'jobs',
        'sessions',
        'password_reset_tokens',
    ];

    public function handle(): int
    {
        $scope = strtolower((string) $this->option('scope'));
        if (! in_array($scope, ['test', 'all'], true)) {
            $this->error('--scope sadece test veya all olabilir.');

            return self::FAILURE;
        }

        if ($scope === 'all' && $this->option('confirm') !== 'KGM_LIVE_RESET') {
            $this->error('scope=all çok riskli. Devam etmek için --confirm=KGM_LIVE_RESET gerekir.');

            return self::FAILURE;
        }

        $plan = $scope === 'all' ? $this->buildAllResetPlan() : $this->buildTestOnlyPlan();
        $this->table(['Temizlenecek alan', 'Kayıt', 'Kapsam'], $plan['rows']);

        if (! $this->option('execute')) {
            $this->warn('Dry-run tamamlandı. Uygulamak için --execute kullanın. Varsayılan kapsam sadece test verisidir.');
            $this->line('Tüm müşteri/sipariş temizliği gerekiyorsa: php artisan kgm:prepare-live-commerce --scope=all --confirm=KGM_LIVE_RESET --execute');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($scope, $plan): void {
            if ($scope === 'all') {
                $this->executeAllReset($plan);
            } else {
                $this->executeTestOnlyReset($plan);
            }
        });

        Cache::flush();
        $this->info('Canlı hazırlığı tamamlandı. Ürün/kategori/admin verisi korunur; test verisi kapsamlı şekilde temizlendi.');

        return self::SUCCESS;
    }

    /** @return array<string, mixed> */
    private function buildTestOnlyPlan(): array
    {
        $testOrderIds = $this->testOrderIds();
        $testUserIds = $this->testUserIds();
        $testProductIds = $this->option('include-test-products') ? $this->testProductIds() : [];

        $rows = [
            ['orders/test', count($testOrderIds), 'test sipariş'],
            ['users/test non-admin', count($testUserIds), 'test müşteri'],
        ];

        foreach ($this->dependentOrderTables() as [$table, $column]) {
            $rows[] = [$table, $this->countWhereIn($table, $column, $testOrderIds), 'test sipariş ilişkili'];
        }
        $paymentIds = $this->paymentIdsForOrders($testOrderIds);
        $supportConversationIds = $this->supportConversationIds($testOrderIds, $testUserIds);
        $rows[] = ['payment_events', $this->countWhereIn('payment_events', 'payment_id', $paymentIds), 'test ödeme ilişkili'];
        $rows[] = ['refunds', $this->countWhereIn('refunds', 'payment_id', $paymentIds), 'test ödeme ilişkili'];
        $rows[] = ['support_messages', $this->countWhereIn('support_messages', 'support_conversation_id', $supportConversationIds), 'test destek ilişkili'];

        foreach ($this->dependentUserTables() as [$table, $column]) {
            $rows[] = [$table, $this->countWhereIn($table, $column, $testUserIds), 'test müşteri ilişkili'];
        }

        foreach (self::EPHEMERAL_TABLES as $table) {
            if (Schema::hasTable($table)) {
                $rows[] = [$table, DB::table($table)->count(), 'geçici çalışma verisi'];
            }
        }

        if (! $this->option('keep-admin-logs') && Schema::hasTable('admin_auth_logs')) {
            $rows[] = ['admin_auth_logs', DB::table('admin_auth_logs')->count(), 'opsiyonel panel giriş logu'];
        }

        if ($this->option('include-test-products')) {
            $rows[] = ['products/test', count($testProductIds), 'test ürün'];
        }

        return compact('rows', 'testOrderIds', 'testUserIds', 'testProductIds');
    }

    /** @return array<string, mixed> */
    private function buildAllResetPlan(): array
    {
        $tables = array_values(array_filter([
            'support_messages',
            'support_conversations',
            'refunds',
            'payment_events',
            'shipments',
            'order_status_events',
            'order_items',
            'payments',
            'orders',
            'cart_coupons',
            'cart_items',
            'customer_active_carts',
            'favorites',
            'addresses',
            'payment_methods',
            'product_reviews',
            'device_tokens',
            'notifications',
            'notification_jobs',
            'notification_broadcasts',
            'customer_identity_events',
            'customer_sync_versions',
            'customer_identities',
            ...self::EPHEMERAL_TABLES,
        ], fn (string $table): bool => Schema::hasTable($table)));

        if (! $this->option('keep-admin-logs') && Schema::hasTable('admin_auth_logs')) {
            $tables[] = 'admin_auth_logs';
        }

        $rows = [];
        foreach ($tables as $table) {
            $rows[] = [$table, DB::table($table)->count(), 'all reset'];
        }
        $rows[] = ['users (non-admin)', Schema::hasTable('users') ? DB::table('users')->where('is_admin', false)->count() : 0, 'all reset'];

        return compact('rows', 'tables');
    }

    private function executeTestOnlyReset(array $plan): void
    {
        $orderIds = $plan['testOrderIds'];
        $userIds = $plan['testUserIds'];
        $productIds = $plan['testProductIds'];

        $paymentIds = $this->paymentIdsForOrders($orderIds);
        $supportConversationIds = $this->supportConversationIds($orderIds, $userIds);

        $this->deleteWhereIn('payment_events', 'payment_id', $paymentIds);
        $this->deleteWhereIn('refunds', 'payment_id', $paymentIds);
        $this->deleteWhereIn('support_messages', 'support_conversation_id', $supportConversationIds);

        foreach ($this->dependentOrderTables() as [$table, $column]) {
            $this->deleteWhereIn($table, $column, $orderIds);
        }
        $this->deleteWhereIn('orders', 'id', $orderIds);

        foreach ($this->dependentUserTables() as [$table, $column]) {
            $this->deleteWhereIn($table, $column, $userIds);
        }
        $this->deleteWhereIn('users', 'id', $userIds);

        foreach (self::EPHEMERAL_TABLES as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->delete();
            }
        }

        if (! $this->option('keep-admin-logs') && Schema::hasTable('admin_auth_logs')) {
            DB::table('admin_auth_logs')->delete();
        }

        if ($this->option('include-test-products')) {
            foreach (['cart_items', 'favorites', 'order_items'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'product_id')) {
                    $this->deleteWhereIn($table, 'product_id', $productIds);
                }
            }
            $this->deleteWhereIn('products', 'id', $productIds);
        }
    }

    private function executeAllReset(array $plan): void
    {
        foreach ($plan['tables'] as $table) {
            DB::table($table)->delete();
        }
        if (Schema::hasTable('users')) {
            DB::table('users')->where('is_admin', false)->delete();
        }
    }

    /** @return array<int> */
    private function testOrderIds(): array
    {
        if (! Schema::hasTable('orders')) {
            return [];
        }

        return DB::table('orders')
            ->where(function (Builder $query): void {
                foreach (['merchant_oid', 'checkout_ref', 'customer_name', 'customer_email', 'customer_phone', 'metadata'] as $column) {
                    if (! Schema::hasColumn('orders', $column)) {
                        continue;
                    }
                    $query->orWhere($column, 'like', '%test%')
                        ->orWhere($column, 'like', '%deneme%')
                        ->orWhere($column, 'like', '%demo%')
                        ->orWhere($column, 'like', '%example%');
                }
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @return array<int> */
    private function testUserIds(): array
    {
        if (! Schema::hasTable('users')) {
            return [];
        }

        return DB::table('users')
            ->where('is_admin', false)
            ->where(function (Builder $query): void {
                foreach (['name', 'email', 'phone'] as $column) {
                    if (! Schema::hasColumn('users', $column)) {
                        continue;
                    }
                    $query->orWhere($column, 'like', '%test%')
                        ->orWhere($column, 'like', '%deneme%')
                        ->orWhere($column, 'like', '%demo%')
                        ->orWhere($column, 'like', '%example%');
                }
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @return array<int> */
    private function testProductIds(): array
    {
        if (! Schema::hasTable('products')) {
            return [];
        }

        return DB::table('products')
            ->where(function (Builder $query): void {
                foreach (['name', 'slug', 'barcode', 'metadata'] as $column) {
                    if (! Schema::hasColumn('products', $column)) {
                        continue;
                    }
                    $query->orWhere($column, 'like', '%test%')
                        ->orWhere($column, 'like', '%deneme%')
                        ->orWhere($column, 'like', '%demo%');
                }
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** @return array<int, array{0:string,1:string}> */
    private function dependentOrderTables(): array
    {
        return array_values(array_filter([
            ['order_items', 'order_id'],
            ['payments', 'order_id'],
            ['shipments', 'order_id'],
            ['order_status_events', 'order_id'],
            ['notifications', 'order_id'],
            ['support_conversations', 'order_id'],
        ], fn (array $entry): bool => Schema::hasTable($entry[0]) && Schema::hasColumn($entry[0], $entry[1])));
    }

    /** @return array<int, array{0:string,1:string}> */
    private function dependentUserTables(): array
    {
        return array_values(array_filter([
            ['addresses', 'user_id'],
            ['api_tokens', 'user_id'],
            ['cart_items', 'user_id'],
            ['cart_coupons', 'user_id'],
            ['customer_active_carts', 'user_id'],
            ['device_tokens', 'user_id'],
            ['favorites', 'user_id'],
            ['notifications', 'user_id'],
            ['payment_methods', 'user_id'],
            ['product_reviews', 'user_id'],
            ['support_conversations', 'user_id'],
            ['support_messages', 'user_id'],
        ], fn (array $entry): bool => Schema::hasTable($entry[0]) && Schema::hasColumn($entry[0], $entry[1])));
    }

    /** @param array<int> $orderIds @return array<int> */
    private function paymentIdsForOrders(array $orderIds): array
    {
        if ($orderIds === [] || ! Schema::hasTable('payments') || ! Schema::hasColumn('payments', 'order_id')) {
            return [];
        }

        return DB::table('payments')->whereIn('order_id', $orderIds)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @param array<int> $orderIds @param array<int> $userIds @return array<int> */
    private function supportConversationIds(array $orderIds, array $userIds): array
    {
        if (! Schema::hasTable('support_conversations')) {
            return [];
        }

        $query = DB::table('support_conversations')->select('id');
        $query->where(function (Builder $query) use ($orderIds, $userIds): void {
            if ($orderIds !== [] && Schema::hasColumn('support_conversations', 'order_id')) {
                $query->orWhereIn('order_id', $orderIds);
            }
            if ($userIds !== [] && Schema::hasColumn('support_conversations', 'user_id')) {
                $query->orWhereIn('user_id', $userIds);
            }
            foreach (['customer_email', 'customer_name', 'customer_phone', 'metadata'] as $column) {
                if (Schema::hasColumn('support_conversations', $column)) {
                    $query->orWhere($column, 'like', '%test%')
                        ->orWhere($column, 'like', '%deneme%')
                        ->orWhere($column, 'like', '%demo%')
                        ->orWhere($column, 'like', '%example%');
                }
            }
        });

        return $query->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @param array<int> $ids */
    private function countWhereIn(string $table, string $column, array $ids): int
    {
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return 0;
        }

        return (int) DB::table($table)->whereIn($column, $ids)->count();
    }

    /** @param array<int> $ids */
    private function deleteWhereIn(string $table, string $column, array $ids): void
    {
        if ($ids === [] || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        DB::table($table)->whereIn($column, $ids)->delete();
    }
}
