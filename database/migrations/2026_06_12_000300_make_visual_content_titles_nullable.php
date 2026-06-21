<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_blocks', function (Blueprint $table): void {
            $table->string('title')->nullable()->change();
        });

        Schema::table('stories', function (Blueprint $table): void {
            $table->string('title', 120)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('homepage_blocks', function (Blueprint $table): void {
            $table->string('title')->nullable(false)->change();
        });

        Schema::table('stories', function (Blueprint $table): void {
            $table->string('title', 120)->nullable(false)->change();
        });
    }
};
