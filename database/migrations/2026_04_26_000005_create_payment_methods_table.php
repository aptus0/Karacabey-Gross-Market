<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->default('paytr');
            $table->text('utoken');
            $table->text('ctoken');
            $table->string('card_last_four', 4);
            $table->string('card_schema')->nullable();
            $table->string('card_brand')->nullable();
            $table->string('card_type')->nullable();
            $table->string('card_bank')->nullable();
            $table->unsignedTinyInteger('expiry_month')->nullable();
            $table->unsignedSmallInteger('expiry_year')->nullable();
            $table->boolean('requires_cvv')->default(false);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
