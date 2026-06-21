<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_product_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_uid', 80)->nullable()->index();
            $table->string('session_uid', 80)->nullable()->index();
            $table->string('source', 32)->default('ios')->index();
            $table->timestamp('viewed_at')->index();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'viewed_at'], 'customer_product_views_user_idx');
            $table->index(['tenant_id', 'customer_uid', 'viewed_at'], 'customer_product_views_customer_idx');
            $table->index(['tenant_id', 'product_id', 'viewed_at'], 'customer_product_views_product_idx');
        });

        Schema::create('customer_reward_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('customer_uid', 80)->nullable()->index();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type', 40)->index();
            $table->integer('points_delta')->default(0);
            $table->integer('balance_after')->nullable();
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'created_at'], 'customer_reward_events_user_idx');
            $table->index(['tenant_id', 'customer_uid', 'created_at'], 'customer_reward_events_customer_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_reward_events');
        Schema::dropIfExists('customer_product_views');
    }
};
