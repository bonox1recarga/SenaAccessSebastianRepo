<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Ensancha token_code a 255 para guardar hashes bcrypt. SQL crudo porque
// doctrine/dbal no está instalado. MODIFY es idempotente por naturaleza.
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE token_recovery MODIFY token_code VARCHAR(255)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE token_recovery MODIFY token_code VARCHAR(10)');
    }
};
