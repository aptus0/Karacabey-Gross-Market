<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('driver', 40)->default('http_json');
            $table->string('base_url', 700)->nullable();
            $table->string('token_ref', 160)->nullable()->comment('Secret value env/vault içinde tutulur; burada gerçek token tutulmaz.');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('batch_size')->default(1000);
            $table->unsignedInteger('sync_interval_seconds')->default(300);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'name'], 'erp_sources_tenant_name_unique');
            $table->index(['tenant_id', 'is_active', 'sync_interval_seconds'], 'erp_sources_active_interval_idx');
        });

        Schema::create('erp_import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('erp_source_id')->constrained('erp_sources')->cascadeOnDelete();
            $table->string('run_key', 80)->unique();
            $table->enum('mode', ['full', 'incremental'])->default('incremental');
            $table->enum('status', ['queued', 'running', 'success', 'partial_failed', 'failed'])->default('queued')->index();
            $table->unsignedInteger('received_count')->default(0);
            $table->unsignedInteger('inserted_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->string('error_message', 1200)->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at'], 'erp_runs_tenant_status_created_idx');
            $table->index(['erp_source_id', 'created_at'], 'erp_runs_source_created_idx');
        });

        Schema::create('erp_product_staging', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('erp_source_id')->constrained('erp_sources')->cascadeOnDelete();
            $table->foreignId('erp_import_run_id')->nullable()->constrained('erp_import_runs')->nullOnDelete();
            $table->string('external_ref', 120);
            $table->string('sku', 120)->nullable();
            $table->string('barcode', 120)->nullable();
            $table->string('name', 500);
            $table->string('brand', 180)->nullable();
            $table->string('category_path', 700)->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->unsignedInteger('compare_at_price_cents')->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->string('unit_name', 32)->default('adet');
            $table->unsignedSmallInteger('vat_rate_basis_points')->default(1000);
            $table->string('image_url', 1000)->nullable();
            $table->char('feed_hash', 64);
            $table->json('raw_payload')->nullable();
            $table->timestamp('erp_updated_at')->nullable();
            $table->enum('apply_status', ['pending', 'applied', 'skipped', 'failed'])->default('pending')->index();
            $table->string('apply_error', 1000)->nullable();
            $table->timestamps();

            $table->unique(['erp_source_id', 'external_ref'], 'erp_staging_source_external_unique');
            $table->index(['tenant_id', 'apply_status', 'updated_at'], 'erp_staging_status_updated_idx');
            $table->index(['tenant_id', 'barcode'], 'erp_staging_tenant_barcode_idx');
            $table->index(['tenant_id', 'sku'], 'erp_staging_tenant_sku_idx');
        });

        Schema::create('erp_sync_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('erp_source_id')->constrained('erp_sources')->cascadeOnDelete();
            $table->string('checkpoint_key', 120);
            $table->text('checkpoint_value')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['erp_source_id', 'checkpoint_key'], 'erp_checkpoint_source_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_sync_checkpoints');
        Schema::dropIfExists('erp_product_staging');
        Schema::dropIfExists('erp_import_runs');
        Schema::dropIfExists('erp_sources');
    }
};
