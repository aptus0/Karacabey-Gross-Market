<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_coupons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('cart_token', 64)->nullable()->index();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id'], 'cart_coupons_user_unique');
            $table->unique(['tenant_id', 'cart_token'], 'cart_coupons_token_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_coupons');
    }
};
