<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // phone-based auth
            $table->string('phone', 20)->nullable()->unique()->after('name');

            // email artık zorunlu değil (telefon birincil tanımlayıcı)
            $table->string('email')->nullable()->change();

            // session meta — IP, coğrafi konum, son giriş
            $table->string('last_ip', 45)->nullable()->after('avatar_url');
            $table->string('last_location', 160)->nullable()->after('last_ip');
            $table->timestamp('last_login_at')->nullable()->after('last_location');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['phone', 'last_ip', 'last_location', 'last_login_at']);
            $table->string('email')->nullable(false)->change();
        });
    }
};
