<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'loyalty_points')) {
                $table->unsignedInteger('loyalty_points')->default(0)->after('sync_version');
            }
            if (! Schema::hasColumn('users', 'loyalty_points_lifetime')) {
                $table->unsignedInteger('loyalty_points_lifetime')->default(0)->after('loyalty_points');
            }
            if (! Schema::hasColumn('users', 'is_vip')) {
                $table->boolean('is_vip')->default(false)->after('loyalty_points_lifetime');
            }
            if (! Schema::hasColumn('users', 'vip_started_at')) {
                $table->timestamp('vip_started_at')->nullable()->after('is_vip');
            }
            if (! Schema::hasColumn('users', 'vip_expires_at')) {
                $table->timestamp('vip_expires_at')->nullable()->after('vip_started_at');
            }
            if (! Schema::hasColumn('users', 'vip_note')) {
                $table->string('vip_note', 255)->nullable()->after('vip_expires_at');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            try { $table->index(['is_vip', 'vip_expires_at'], 'users_vip_status_idx'); } catch (Throwable $e) {}
        });

        Schema::table('customer_reward_events', function (Blueprint $table): void {
            try { $table->unique(['order_id', 'event_type'], 'customer_reward_events_order_event_unique'); } catch (Throwable $e) {}
        });

        Schema::table('marketing_settings', function (Blueprint $table): void {
            if (! Schema::hasColumn('marketing_settings', 'google_admob_app_id')) {
                $table->string('google_admob_app_id', 120)->nullable()->after('google_maps_api_key');
            }
            if (! Schema::hasColumn('marketing_settings', 'google_admob_ios_banner_unit_id')) {
                $table->string('google_admob_ios_banner_unit_id', 120)->nullable()->after('google_admob_app_id');
            }
            if (! Schema::hasColumn('marketing_settings', 'google_admob_ios_interstitial_unit_id')) {
                $table->string('google_admob_ios_interstitial_unit_id', 120)->nullable()->after('google_admob_ios_banner_unit_id');
            }
            if (! Schema::hasColumn('marketing_settings', 'google_admob_android_banner_unit_id')) {
                $table->string('google_admob_android_banner_unit_id', 120)->nullable()->after('google_admob_ios_interstitial_unit_id');
            }
            if (! Schema::hasColumn('marketing_settings', 'google_admob_android_interstitial_unit_id')) {
                $table->string('google_admob_android_interstitial_unit_id', 120)->nullable()->after('google_admob_android_banner_unit_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketing_settings', function (Blueprint $table): void {
            foreach ([
                'google_admob_android_interstitial_unit_id',
                'google_admob_android_banner_unit_id',
                'google_admob_ios_interstitial_unit_id',
                'google_admob_ios_banner_unit_id',
                'google_admob_app_id',
            ] as $column) {
                if (Schema::hasColumn('marketing_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('customer_reward_events', function (Blueprint $table): void {
            try { $table->dropUnique('customer_reward_events_order_event_unique'); } catch (Throwable $e) {}
        });

        Schema::table('users', function (Blueprint $table): void {
            try { $table->dropIndex('users_vip_status_idx'); } catch (Throwable $e) {}

            foreach ([
                'vip_note',
                'vip_expires_at',
                'vip_started_at',
                'is_vip',
                'loyalty_points_lifetime',
                'loyalty_points',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
