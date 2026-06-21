<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('driver', 20); // mysql, pgsql, sqlsrv, sqlite
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('database');
            $table->string('username')->nullable();
            $table->text('password')->nullable(); // encrypted
            $table->json('extra')->nullable(); // ssl, schema, encrypt, trust_server_cert vs.
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 20)->nullable(); // success | fail
            $table->text('last_test_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'driver']);
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_connections');
    }
};
