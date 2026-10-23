<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['reemplazos_personal' => 'rut', 'funcionarios_ac_autorizados' => 'rut_normalizado'] as $table => $source) {
            if (!Schema::hasColumn($table, 'rut_consulta_api')) {
                $clean = "UPPER(REPLACE(REPLACE(REPLACE(TRIM($source), '.', ''), '-', ''), ' ', ''))";
                $expression = DB::getDriverName() === 'sqlite' ? "LTRIM($clean, '0')" : "TRIM(LEADING '0' FROM $clean)";
                Schema::table($table, function (Blueprint $blueprint) use ($expression): void {
                    $blueprint->string('rut_consulta_api', 30)->virtualAs($expression);
                });
            }
            if (!Schema::hasIndex($table, $table.'_rut_consulta_api_idx')) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index('rut_consulta_api', $table.'_rut_consulta_api_idx'));
            }
        }
    }

    public function down(): void
    {
        // Índices y columnas derivados; conservarlos no modifica los datos de origen.
    }
};
