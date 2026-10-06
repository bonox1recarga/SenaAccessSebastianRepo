<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Anade tipo de documento y telefono de contacto a los usuarios. SQL crudo
// porque doctrine/dbal no esta instalado. Idempotente ante reintentos.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('usuarios', 'user_documento_tipo')) {
            DB::statement("ALTER TABLE usuarios ADD user_documento_tipo VARCHAR(5) NULL DEFAULT 'CC'");
        }
        if (!Schema::hasColumn('usuarios', 'user_telefono')) {
            DB::statement('ALTER TABLE usuarios ADD user_telefono VARCHAR(20) NULL');
        }
        DB::statement("UPDATE usuarios SET user_documento_tipo = 'CC' WHERE user_documento_tipo IS NULL");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE usuarios DROP COLUMN user_documento_tipo');
        DB::statement('ALTER TABLE usuarios DROP COLUMN user_telefono');
    }
};
