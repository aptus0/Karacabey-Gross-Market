<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('carrier')->index(); // 'ARAS', 'MNG', 'FAKE' vb.
            $table->string('tracking_number')->nullable()->index();
            $table->string('status')->default('pending')->index(); // 'pending', 'shipped', 'in_transit', 'delivered', 'returned', 'exception'
            $table->string('tracking_url')->nullable();
            $table->json('metadata')->nullable(); // Kargo servisinden dönen raw veriler
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
