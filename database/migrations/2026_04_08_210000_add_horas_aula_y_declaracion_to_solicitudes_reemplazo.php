<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            if (!Schema::hasColumn('solicitudes_reemplazo', 'horas_aula_cronologicas_titular')) {
                $table->decimal('horas_aula_cronologicas_titular', 8, 2)
                    ->default(0)
                    ->after('observaciones');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'horas_aula_pedagogicas_titular')) {
                $table->decimal('horas_aula_pedagogicas_titular', 8, 2)
                    ->default(0)
                    ->after('horas_aula_cronologicas_titular');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'horas_aula_cronologicas_reemplazo')) {
                $table->decimal('horas_aula_cronologicas_reemplazo', 8, 2)
                    ->default(0)
                    ->after('horas_aula_pedagogicas_titular');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'horas_aula_pedagogicas_reemplazo')) {
                $table->decimal('horas_aula_pedagogicas_reemplazo', 8, 2)
                    ->default(0)
                    ->after('horas_aula_cronologicas_reemplazo');
            }

            if (!Schema::hasColumn('solicitudes_reemplazo', 'declaracion_responsabilidad_aceptada')) {
                $table->boolean('declaracion_responsabilidad_aceptada')
                    ->default(false)
                    ->after('horas_aula_pedagogicas_reemplazo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_reemplazo', function (Blueprint $table) {
            foreach ([
                'declaracion_responsabilidad_aceptada',
                'horas_aula_pedagogicas_reemplazo',
                'horas_aula_cronologicas_reemplazo',
                'horas_aula_pedagogicas_titular',
                'horas_aula_cronologicas_titular',
            ] as $column) {
                if (Schema::hasColumn('solicitudes_reemplazo', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
