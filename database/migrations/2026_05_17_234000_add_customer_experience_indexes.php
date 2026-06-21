<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                try { $table->index(['user_id', 'status', 'created_at'], 'orders_user_status_created_idx'); } catch (Throwable $e) {}
                try { $table->index(['user_id', 'created_at'], 'orders_user_created_idx'); } catch (Throwable $e) {}
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                try { $table->index(['tenant_id', 'user_id', 'read_at', 'created_at'], 'notifications_customer_inbox_idx'); } catch (Throwable $e) {}
            });
        }

        if (Schema::hasTable('favorites')) {
            Schema::table('favorites', function (Blueprint $table) {
                try { $table->index(['user_id', 'created_at'], 'favorites_user_created_idx'); } catch (Throwable $e) {}
            });
        }

        if (Schema::hasTable('addresses')) {
            Schema::table('addresses', function (Blueprint $table) {
                try { $table->index(['user_id', 'is_default', 'updated_at'], 'addresses_user_default_updated_idx'); } catch (Throwable $e) {}
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('addresses')) {
            Schema::table('addresses', function (Blueprint $table) {
                try { $table->dropIndex('addresses_user_default_updated_idx'); } catch (Throwable $e) {}
            });
        }

        if (Schema::hasTable('favorites')) {
            Schema::table('favorites', function (Blueprint $table) {
                try { $table->dropIndex('favorites_user_created_idx'); } catch (Throwable $e) {}
            });
        }

        if (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                try { $table->dropIndex('notifications_customer_inbox_idx'); } catch (Throwable $e) {}
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                try { $table->dropIndex('orders_user_status_created_idx'); } catch (Throwable $e) {}
                try { $table->dropIndex('orders_user_created_idx'); } catch (Throwable $e) {}
            });
        }
    }
};
