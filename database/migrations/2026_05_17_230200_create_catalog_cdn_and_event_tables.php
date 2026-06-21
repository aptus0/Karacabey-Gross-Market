<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 80)->default('global');
            $table->unsignedBigInteger('version')->default(1);
            $table->timestamp('last_changed_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'scope'], 'catalog_versions_tenant_scope_unique');
        });

        Schema::create('catalog_cache_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('cache_key', 220);
            $table->string('etag', 120)->nullable();
            $table->mediumText('payload_json');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'cache_key'], 'catalog_cache_tenant_key_unique');
            $table->index(['tenant_id', 'expires_at'], 'catalog_cache_expire_idx');
        });

        Schema::create('image_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('owner_type', 60);
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('source_url', 1000);
            $table->string('cdn_url', 1000)->nullable();
            $table->string('storage_key', 700)->nullable();
            $table->string('content_hash', 120)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->enum('status', ['pending', 'processing', 'ready', 'failed'])->default('pending')->index();
            $table->string('error_message', 1000)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'owner_type', 'owner_id'], 'image_assets_owner_idx');
            $table->index(['tenant_id', 'content_hash'], 'image_assets_hash_idx');
        });

        Schema::create('outbox_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 120);
            $table->string('aggregate_type', 80);
            $table->string('aggregate_id', 120);
            $table->unsignedBigInteger('version')->default(1);
            $table->json('payload')->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('last_error', 1000)->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'processed_at', 'available_at'], 'outbox_pending_idx');
            $table->index(['event_type', 'created_at'], 'outbox_type_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('image_assets');
        Schema::dropIfExists('catalog_cache_snapshots');
        Schema::dropIfExists('catalog_versions');
    }
};
