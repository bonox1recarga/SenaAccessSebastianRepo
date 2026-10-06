<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Idempotente: si la columna ya existe (reintento de deploy), no hace nada.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ingresos', 'ingreso_type')) {
            Schema::table('ingresos', function (Blueprint $table) {
                $table->string('ingreso_type', 20)->default('Entrada')->after('ingreso_place');
            });
        }
    }

    public function down(): void
    {
        Schema::table('ingresos', function (Blueprint $table) {
            $table->dropColumn('ingreso_type');
        });
    }
};
