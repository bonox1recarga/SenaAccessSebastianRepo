<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Idempotente: si las columnas ya existen (reintento de deploy), no hace nada.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('usuarios', 'trusted_device_id')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->string('trusted_device_id', 64)->nullable()->after('two_factor_enabled');
            });
        }

        if (Schema::hasTable('two_factor_challenges') && !Schema::hasColumn('two_factor_challenges', 'device_id')) {
            Schema::table('two_factor_challenges', function (Blueprint $table) {
                $table->string('device_id', 64)->nullable()->after('user_agent');
            });
        }
    }

    public function down(): void
    {
        Schema::table('two_factor_challenges', function (Blueprint $table) {
            $table->dropColumn('device_id');
        });
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn('trusted_device_id');
        });
    }
};
