<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('centro_operaciones_reportes', function (Blueprint $table) {
            $table->string('unidad_codigo', 48)->nullable()->after('establecimiento_id');
            $table->date('fecha_control_plagas')->nullable()->after('funcionamiento');
            $table->index(
                ['establecimiento_id', 'unidad_codigo', 'fecha_reporte'],
                'co_reportes_contexto_fecha_idx'
            );
        });

        Schema::table('centro_operaciones_incidencias', function (Blueprint $table) {
            $table->string('unidad_codigo', 48)->nullable()->after('establecimiento_id');
            $table->string('modalidad', 32)->nullable()->after('tipo');
            $table->index(
                ['establecimiento_id', 'unidad_codigo', 'estado'],
                'co_incidencias_contexto_estado_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('centro_operaciones_incidencias', function (Blueprint $table) {
            $table->dropIndex('co_incidencias_contexto_estado_idx');
            $table->dropColumn(['unidad_codigo', 'modalidad']);
        });

        Schema::table('centro_operaciones_reportes', function (Blueprint $table) {
            $table->dropIndex('co_reportes_contexto_fecha_idx');
            $table->dropColumn(['unidad_codigo', 'fecha_control_plagas']);
        });
    }
};
