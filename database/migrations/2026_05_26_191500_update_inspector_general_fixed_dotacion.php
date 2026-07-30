<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dotacion_funciones_reglas')) {
            return;
        }

        DB::table('dotacion_funciones_reglas')->updateOrInsert(
            ['codigo' => 'inspector_general'],
            [
                'categoria' => 'directiva',
                'nombre' => 'Inspector(a) General',
                'tipo_regla' => 'fija',
                'horas_fijas' => 44,
                'horas_minimas' => null,
                'horas_maximas' => null,
                'umbral_matricula' => null,
                'horas_bajo_umbral' => null,
                'horas_sobre_umbral' => null,
                'permite_multiples' => false,
                'declarable' => false,
                'obligatoria' => true,
                'requiere_validacion' => true,
                'fundamento' => 'Cargo fijo de la dotación directiva: 44 horas, independiente de si Director(a) es ADP.',
                'vigente' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('dotacion_funciones_reglas')) {
            return;
        }

        DB::table('dotacion_funciones_reglas')
            ->where('codigo', 'inspector_general')
            ->update([
                'tipo_regla' => 'director_adp_o_manual',
                'horas_fijas' => null,
                'declarable' => true,
                'obligatoria' => false,
                'fundamento' => '44 horas cuando Director(a) es ADP; si no es ADP, cargo opcional y horas de declaración manual.',
                'updated_at' => now(),
            ]);
    }
};
