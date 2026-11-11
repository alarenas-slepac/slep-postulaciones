<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tabla = 'dotacion_sobredotacion_justificaciones';
        if (! Schema::hasTable($tabla)) { return; }
        // Cada columna se comprueba por separado: MySQL puede confirmar un DDL
        // antes de fallar el siguiente. Un reintento conserva los datos existentes.
        if (! Schema::hasColumn($tabla, 'nivel_ensenanza')) {
            Schema::table($tabla, fn (Blueprint $table) => $table->string('nivel_ensenanza', 24)->nullable());
        }
        if (! Schema::hasColumn($tabla, 'areas_ensenanza')) {
            Schema::table($tabla, fn (Blueprint $table) => $table->json('areas_ensenanza')->nullable());
        }
    }

    public function down(): void
    {
        // Conserva el nivel y las áreas que forman parte de justificaciones históricas.
    }
};
