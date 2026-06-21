<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'public_uid')) {
                $table->string('public_uid', 64)->nullable()->after('id');
            }
            if (! Schema::hasColumn('users', 'customer_uid')) {
                $table->string('customer_uid', 64)->nullable()->after('public_uid');
            }
            if (! Schema::hasColumn('users', 'sync_version')) {
                $table->unsignedBigInteger('sync_version')->default(0)->after('customer_uid');
            }
        });

        DB::table('users')
            ->select('id')
            ->whereNull('public_uid')
            ->orWhere('public_uid', '')
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')->where('id', $user->id)->update([
                        'public_uid' => 'usr_'.str_pad((string) $user->id, 18, '0', STR_PAD_LEFT),
                    ]);
                }
            });
        DB::table('users')
            ->select('id')
            ->whereNull('customer_uid')
            ->orWhere('customer_uid', '')
            ->orderBy('id')
            ->chunkById(500, function ($users): void {
                foreach ($users as $user) {
                    DB::table('users')->where('id', $user->id)->update([
                        'customer_uid' => 'cus_'.str_pad((string) $user->id, 18, '0', STR_PAD_LEFT),
                    ]);
                }
            });
        DB::table('users')
            ->whereNull('sync_version')
            ->orWhere('sync_version', 0)
            ->update(['sync_version' => (int) floor(microtime(true) * 1_000_000)]);

        Schema::table('users', function (Blueprint $table): void {
            try { $table->unique('public_uid', 'users_public_uid_unique'); } catch (Throwable $e) {}
            try { $table->index('customer_uid', 'users_customer_uid_idx'); } catch (Throwable $e) {}
            try { $table->index('sync_version', 'users_sync_version_idx'); } catch (Throwable $e) {}
        });

        Schema::create('customer_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('customer_uid', 64);
            $table->string('session_uid', 64)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->ipAddress('first_ip')->nullable();
            $table->ipAddress('last_ip')->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_uid'], 'customer_identities_tenant_uid_unique');
            $table->index(['tenant_id', 'user_id'], 'customer_identities_user_idx');
            $table->index(['tenant_id', 'last_seen_at'], 'customer_identities_last_seen_idx');
        });

        Schema::create('customer_active_carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('customer_uid', 64);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cart_token', 80);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_uid'], 'customer_active_carts_tenant_uid_unique');
            $table->index(['tenant_id', 'user_id'], 'customer_active_carts_user_idx');
            $table->index(['tenant_id', 'cart_token'], 'customer_active_carts_token_idx');
        });

        Schema::create('customer_sync_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_uid', 64)->nullable();
            $table->string('scope', 40)->default('customer');
            $table->unsignedBigInteger('version');
            $table->string('reason', 120)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'scope'], 'customer_sync_user_scope_unique');
            $table->unique(['tenant_id', 'customer_uid', 'scope'], 'customer_sync_uid_scope_unique');
            $table->index(['tenant_id', 'version'], 'customer_sync_version_idx');
        });

        Schema::create('customer_identity_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('customer_uid', 64)->nullable();
            $table->string('session_uid', 64)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_name', 120);
            $table->string('cart_token', 80)->nullable();
            $table->string('request_id', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'customer_uid', 'created_at'], 'identity_events_customer_created_idx');
            $table->index(['tenant_id', 'user_id', 'created_at'], 'identity_events_user_created_idx');
            $table->index(['tenant_id', 'event_name', 'created_at'], 'identity_events_name_created_idx');
        });

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'customer_uid')) {
                $table->string('customer_uid', 64)->nullable()->after('user_id')->index('orders_customer_uid_idx');
            }
            if (! Schema::hasColumn('orders', 'session_uid')) {
                $table->string('session_uid', 64)->nullable()->after('customer_uid');
            }
            if (! Schema::hasColumn('orders', 'checkout_uid')) {
                $table->string('checkout_uid', 64)->nullable()->after('session_uid')->index('orders_checkout_uid_idx');
            }
            if (! Schema::hasColumn('orders', 'payment_uid')) {
                $table->string('payment_uid', 64)->nullable()->after('checkout_uid')->index('orders_payment_uid_idx');
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'payment_uid')) {
                $table->string('payment_uid', 64)->nullable()->after('merchant_oid');
            }
            if (! Schema::hasColumn('payments', 'customer_uid')) {
                $table->string('customer_uid', 64)->nullable()->after('payment_uid');
            }
            if (! Schema::hasColumn('payments', 'checkout_uid')) {
                $table->string('checkout_uid', 64)->nullable()->after('customer_uid');
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            try { $table->unique('payment_uid', 'payments_payment_uid_unique'); } catch (Throwable $e) {}
            try { $table->index(['customer_uid', 'status'], 'payments_customer_status_idx'); } catch (Throwable $e) {}
            try { $table->index('checkout_uid', 'payments_checkout_uid_idx'); } catch (Throwable $e) {}
        });

        if (Schema::hasTable('mobile_devices')) {
            Schema::table('mobile_devices', function (Blueprint $table): void {
                if (! Schema::hasColumn('mobile_devices', 'customer_uid')) {
                    $table->string('customer_uid', 64)->nullable()->after('tenant_id')->index('mobile_devices_customer_uid_idx');
                }
            });
        }

        if (Schema::hasTable('mobile_events')) {
            Schema::table('mobile_events', function (Blueprint $table): void {
                if (! Schema::hasColumn('mobile_events', 'customer_uid')) {
                    $table->string('customer_uid', 64)->nullable()->after('tenant_id');
                }
                if (! Schema::hasColumn('mobile_events', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->after('customer_uid')->constrained()->nullOnDelete();
                }
                $table->index(['tenant_id', 'customer_uid', 'created_at'], 'mobile_events_customer_created_idx');
                $table->index(['tenant_id', 'user_id', 'created_at'], 'mobile_events_user_created_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mobile_events')) {
            Schema::table('mobile_events', function (Blueprint $table): void {
                try { $table->dropIndex('mobile_events_customer_created_idx'); } catch (Throwable $e) {}
                try { $table->dropIndex('mobile_events_user_created_idx'); } catch (Throwable $e) {}
                if (Schema::hasColumn('mobile_events', 'user_id')) {
                    $table->dropConstrainedForeignId('user_id');
                }
                if (Schema::hasColumn('mobile_events', 'customer_uid')) {
                    $table->dropColumn('customer_uid');
                }
            });
        }

        if (Schema::hasTable('mobile_devices')) {
            Schema::table('mobile_devices', function (Blueprint $table): void {
                if (Schema::hasColumn('mobile_devices', 'customer_uid')) {
                    $table->dropColumn('customer_uid');
                }
            });
        }

        Schema::table('payments', function (Blueprint $table): void {
            foreach (['payment_uid', 'customer_uid', 'checkout_uid'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('orders', function (Blueprint $table): void {
            foreach (['customer_uid', 'session_uid', 'checkout_uid', 'payment_uid'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('customer_identity_events');
        Schema::dropIfExists('customer_sync_versions');
        Schema::dropIfExists('customer_active_carts');
        Schema::dropIfExists('customer_identities');

        Schema::table('users', function (Blueprint $table): void {
            foreach (['public_uid', 'customer_uid', 'sync_version'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
