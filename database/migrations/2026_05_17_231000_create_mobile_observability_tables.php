<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 160);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('platform', ['ios', 'android', 'web'])->default('ios');
            $table->string('app_version', 40)->nullable();
            $table->string('os_version', 80)->nullable();
            $table->string('device_model', 160)->nullable();
            $table->string('push_token', 700)->nullable();
            $table->string('locale', 30)->nullable();
            $table->string('timezone', 80)->nullable();
            $table->ipAddress('last_ip')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_blocked')->default(false)->index();
            $table->timestamps();

            $table->unique(['tenant_id', 'device_id'], 'mobile_devices_tenant_device_unique');
            $table->index(['tenant_id', 'platform', 'app_version'], 'mobile_devices_platform_version_idx');
            $table->index(['tenant_id', 'last_seen_at'], 'mobile_devices_last_seen_idx');
            $table->index(['tenant_id', 'user_id'], 'mobile_devices_user_idx');
        });

        Schema::create('mobile_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 160)->nullable();
            $table->string('session_id', 120)->nullable();
            $table->string('event_name', 120);
            $table->string('screen', 160)->nullable();
            $table->string('platform', 30)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->json('payload')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 600)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'event_name', 'created_at'], 'mobile_events_name_created_idx');
            $table->index(['tenant_id', 'device_id', 'created_at'], 'mobile_events_device_created_idx');
            $table->index(['tenant_id', 'session_id', 'created_at'], 'mobile_events_session_created_idx');
            $table->index(['tenant_id', 'platform', 'app_version', 'created_at'], 'mobile_events_platform_version_idx');
        });

        Schema::create('api_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('metric_date');
            $table->string('metric_key', 120);
            $table->unsignedBigInteger('counter')->default(0);
            $table->decimal('value', 16, 4)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'metric_date', 'metric_key'], 'api_daily_metrics_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_daily_metrics');
        Schema::dropIfExists('mobile_events');
        Schema::dropIfExists('mobile_devices');
    }
};
