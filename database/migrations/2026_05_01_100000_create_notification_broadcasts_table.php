<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('audience', 24)->default('all');
            $table->string('type', 40)->default('general');
            $table->string('title', 160);
            $table->text('body');
            $table->string('action_url')->nullable();
            $table->string('image_url')->nullable();
            $table->json('payload')->nullable();
            $table->unsignedInteger('delivered_count')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['audience', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_broadcasts');
    }
};
