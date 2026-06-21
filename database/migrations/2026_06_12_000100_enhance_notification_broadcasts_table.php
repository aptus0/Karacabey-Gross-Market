<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_broadcasts', function (Blueprint $table): void {
            $table->string('cta_title', 80)->nullable()->after('action_url');
            $table->string('status', 24)->default('queued')->index()->after('payload');
            $table->unsignedInteger('target_count')->default(0)->after('status');
            $table->unsignedInteger('push_sent_count')->default(0)->after('delivered_count');
            $table->unsignedInteger('push_failed_count')->default(0)->after('push_sent_count');
            $table->timestamp('processed_at')->nullable()->after('push_failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('notification_broadcasts', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'cta_title',
                'status',
                'target_count',
                'push_sent_count',
                'push_failed_count',
                'processed_at',
            ]);
        });
    }
};
