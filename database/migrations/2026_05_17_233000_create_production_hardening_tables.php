<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('idempotency_keys')) {
            Schema::create('idempotency_keys', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->string('scope', 80);
                $table->string('idempotency_key', 96);
                $table->char('request_hash', 64);
                $table->string('status', 24)->default('processing')->index();
                $table->unsignedSmallInteger('response_code')->nullable();
                $table->longText('response_body')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('locked_until')->nullable()->index();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'scope', 'idempotency_key'], 'idem_tenant_scope_key_unique');
                $table->index(['tenant_id', 'scope', 'status', 'updated_at'], 'idem_monitor_idx');
            });
        }

        if (! Schema::hasTable('cloudflare_purge_jobs')) {
            Schema::create('cloudflare_purge_jobs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->string('entity_type', 64)->nullable()->index();
                $table->string('entity_ref', 160)->nullable();
                $table->json('urls')->nullable();
                $table->json('tags')->nullable();
                $table->string('status', 24)->default('pending')->index();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('scheduled_at')->nullable()->index();
                $table->timestamp('processed_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status', 'scheduled_at'], 'cf_purge_queue_idx');
            });
        }

        if (! Schema::hasTable('notification_jobs')) {
            Schema::create('notification_jobs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->string('audience_type', 32)->default('customer')->index();
                $table->string('channel', 32)->default('in_app')->index();
                $table->string('template_key', 96)->nullable()->index();
                $table->string('customer_uid', 96)->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('recipient', 190)->nullable();
                $table->string('title', 190);
                $table->text('body')->nullable();
                $table->json('payload')->nullable();
                $table->string('status', 24)->default('pending')->index();
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->timestamp('scheduled_at')->nullable()->index();
                $table->timestamp('sent_at')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status', 'scheduled_at'], 'notification_queue_idx');
            });
        }

        if (! Schema::hasTable('api_security_events')) {
            Schema::create('api_security_events', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->string('event_type', 80)->index();
                $table->string('severity', 16)->default('info')->index();
                $table->string('customer_uid', 96)->nullable()->index();
                $table->string('session_uid', 96)->nullable()->index();
                $table->string('ip', 64)->nullable()->index();
                $table->string('route', 190)->nullable();
                $table->string('request_id', 64)->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();

                $table->index(['tenant_id', 'event_type', 'created_at'], 'api_security_event_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_security_events');
        Schema::dropIfExists('notification_jobs');
        Schema::dropIfExists('cloudflare_purge_jobs');
        Schema::dropIfExists('idempotency_keys');
    }
};
