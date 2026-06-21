<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('subtitle', 240)->nullable();
            $table->string('image_path')->nullable();
            $table->string('category_slug', 120)->nullable();
            $table->string('custom_url', 500)->nullable();
            $table->string('gradient_start', 7)->default('#FF7A00');
            $table->string('gradient_end', 7)->default('#FF3300');
            $table->string('icon', 80)->default('tag.fill');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stories');
    }
};
