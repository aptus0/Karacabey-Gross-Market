<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_stock_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->index();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status', 32)->default('waiting')->index();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'product_id', 'user_id'], 'stock_alerts_tenant_product_user_unique');
            $table->index(['tenant_id', 'product_id', 'status'], 'stock_alerts_product_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_stock_alerts');
    }
};
