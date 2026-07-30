<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establecimiento_planes_estudio_asignaturas', function (Blueprint $table) {
            if (! Schema::hasColumn('establecimiento_planes_estudio_asignaturas', 'asignatura_plan_comun_id')) {
                $table->unsignedBigInteger('asignatura_plan_comun_id')->nullable()->after('asignatura_id');
                $table->index('asignatura_plan_comun_id', 'epea_plan_comun_idx');
                $table->foreign('asignatura_plan_comun_id', 'fk_epea_plan_comun')
                    ->references('id')->on('asignaturas')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('establecimiento_planes_estudio_asignaturas', function (Blueprint $table) {
            if (Schema::hasColumn('establecimiento_planes_estudio_asignaturas', 'asignatura_plan_comun_id')) {
                $table->dropForeign('fk_epea_plan_comun');
                $table->dropIndex('epea_plan_comun_idx');
                $table->dropColumn('asignatura_plan_comun_id');
            }
        });
    }
};
