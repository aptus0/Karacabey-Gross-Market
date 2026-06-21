<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('paytr');
            $table->string('merchant_oid', 64)->unique();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('amount_cents');
            $table->unsignedInteger('captured_amount_cents')->nullable();
            $table->string('currency', 8)->default('TL');
            $table->text('provider_token')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('failed_reason_code')->nullable();
            $table->string('failed_reason_msg')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('paytr');
            $table->string('event_type')->index();
            $table->string('merchant_oid', 64)->nullable()->index();
            $table->string('hash_status')->default('unchecked');
            $table->json('payload');
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('reference_no', 64)->nullable()->unique();
            $table->unsignedInteger('amount_cents');
            $table->string('status')->default('pending')->index();
            $table->json('provider_payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payments');
    }
};
