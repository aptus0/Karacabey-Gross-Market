<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_auth_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 48)->index();
            $table->string('status', 32)->index();
            $table->string('guard_action', 32)->index();
            $table->string('route_name')->nullable()->index();
            $table->string('path', 512)->nullable();
            $table->string('method', 12)->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->unsignedTinyInteger('risk_score')->default(0)->index();
            $table->json('risk_reasons')->nullable();
            $table->timestamp('blocked_until')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['ip_address', 'event_type', 'created_at'], 'admin_auth_logs_ip_event_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_auth_logs');
    }
};
