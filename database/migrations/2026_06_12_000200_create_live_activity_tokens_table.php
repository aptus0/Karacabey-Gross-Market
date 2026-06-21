<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_activity_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('device_id', 160);
            $table->string('fcm_token', 700);
            $table->string('token', 512)->unique();
            $table->string('kind', 32);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id', 'kind', 'is_active'], 'live_activity_user_kind_idx');
            $table->index(['tenant_id', 'order_id', 'kind', 'is_active'], 'live_activity_order_kind_idx');
            $table->index(['tenant_id', 'device_id', 'kind'], 'live_activity_device_kind_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_activity_tokens');
    }
};
