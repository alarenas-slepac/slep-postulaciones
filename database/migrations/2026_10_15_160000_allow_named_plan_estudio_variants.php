<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('planes_estudio')) {
            return;
        }
        if (! Schema::hasIndex('planes_estudio', 'planes_estudio_curso_anio_regimen_nombre_unique')) {
            Schema::table('planes_estudio', function (Blueprint $table): void {
                $table->unique(['curso_id', 'anio', 'regimen_jec', 'nombre_plan'], 'planes_estudio_curso_anio_regimen_nombre_unique');
            });
        }
        if (Schema::hasIndex('planes_estudio', 'planes_estudio_curso_anio_regimen_unique')) {
            Schema::table('planes_estudio', function (Blueprint $table): void {
                $table->dropUnique('planes_estudio_curso_anio_regimen_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('planes_estudio')) {
            return;
        }
        // Nunca eliminar variantes ni sus asociaciones para restaurar el índice anterior.
        if (DB::table('planes_estudio')->groupBy('curso_id', 'anio', 'regimen_jec')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Hay variantes de planes para el mismo curso, año y régimen. Resuelva sus asociaciones antes de revertir esta migración.');
        }
        if (! Schema::hasIndex('planes_estudio', 'planes_estudio_curso_anio_regimen_unique')) {
            Schema::table('planes_estudio', function (Blueprint $table): void {
                $table->unique(['curso_id', 'anio', 'regimen_jec'], 'planes_estudio_curso_anio_regimen_unique');
            });
        }
        if (Schema::hasIndex('planes_estudio', 'planes_estudio_curso_anio_regimen_nombre_unique')) {
            Schema::table('planes_estudio', function (Blueprint $table): void {
                $table->dropUnique('planes_estudio_curso_anio_regimen_nombre_unique');
            });
        }
    }
};
