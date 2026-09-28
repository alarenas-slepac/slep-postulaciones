<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dotacion_docente_asignaciones')
            || ! Schema::hasColumn('dotacion_docente_asignaciones', 'subvencion')) {
            return;
        }

        DB::table('dotacion_docente_asignaciones')
            ->where('tipo_asignacion', 'plan_estudio')
            ->where(function ($query): void {
                $query->whereNull('subvencion')->orWhere('subvencion', '!=', 'General');
            })
            ->update(['subvencion' => 'General']);
    }

    public function down(): void
    {
        // La clasificación anterior de cada asignación no se puede reconstruir con certeza.
    }
};
