<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookie_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('anonymous_id', 100)->nullable();
            $table->string('session_id', 100)->nullable();
            $table->string('cart_token', 100)->nullable();
            $table->string('source', 40)->nullable();
            $table->boolean('necessary')->default(true);
            $table->boolean('analytics')->default(false);
            $table->boolean('marketing')->default(false);
            $table->boolean('personalization')->default(false);
            $table->boolean('performance')->default(false);
            $table->string('consent_version', 40)->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id', 80)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'anonymous_id', 'created_at'], 'cookie_consents_anon_created_idx');
            $table->index(['tenant_id', 'session_id', 'created_at'], 'cookie_consents_session_created_idx');
            $table->index(['tenant_id', 'created_at'], 'cookie_consents_created_idx');
        });

        Schema::create('tracking_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_id', 100)->nullable();
            $table->string('event_name', 100);
            $table->string('category', 40)->default('analytics');
            $table->string('anonymous_id', 100)->nullable();
            $table->string('session_id', 100)->nullable();
            $table->string('cart_token', 100)->nullable();
            $table->text('page_url')->nullable();
            $table->text('referrer')->nullable();
            $table->string('source', 120)->nullable();
            $table->string('medium', 120)->nullable();
            $table->string('campaign', 160)->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->bigInteger('value_cents')->nullable();
            $table->string('currency', 8)->default('TRY');
            $table->json('event_data')->nullable();
            $table->json('consent_snapshot')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['tenant_id', 'event_id'], 'tracking_events_tenant_event_uid');
            $table->index(['tenant_id', 'event_name', 'created_at'], 'tracking_events_name_created_idx');
            $table->index(['tenant_id', 'anonymous_id', 'created_at'], 'tracking_events_anon_created_idx');
            $table->index(['tenant_id', 'session_id', 'created_at'], 'tracking_events_session_created_idx');
            $table->index(['tenant_id', 'campaign', 'created_at'], 'tracking_events_campaign_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_events');
        Schema::dropIfExists('cookie_consents');
    }
};
