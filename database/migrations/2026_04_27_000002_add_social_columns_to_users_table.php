<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('google_id')->nullable()->unique()->after('email');
            $table->string('google_email')->nullable()->after('google_id');
            $table->string('github_id')->nullable()->unique()->after('google_email');
            $table->string('github_email')->nullable()->after('github_id');
            $table->string('facebook_id')->nullable()->unique()->after('github_email');
            $table->string('facebook_email')->nullable()->after('facebook_id');
            $table->string('avatar_url')->nullable()->after('facebook_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['google_id']);
            $table->dropUnique(['github_id']);
            $table->dropUnique(['facebook_id']);
            $table->dropColumn([
                'google_id',
                'google_email',
                'github_id',
                'github_email',
                'facebook_id',
                'facebook_email',
                'avatar_url'
            ]);
        });
    }
};
