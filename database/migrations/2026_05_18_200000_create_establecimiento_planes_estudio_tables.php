<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Esta migración puede haber quedado parcialmente creada si falló por
        // nombres de llaves foráneas demasiado largos en MySQL. Como el módulo
        // aún no entra en operación, se limpian sólo estas tablas nuevas antes
        // de recrearlas con nombres de constraints cortos y explícitos.
        Schema::dropIfExists('establecimiento_planes_estudio_asignaturas');
        Schema::dropIfExists('establecimiento_planes_estudio');

        Schema::create('establecimiento_planes_estudio', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('establecimiento_id');
            $table->unsignedBigInteger('establecimiento_curso_id');
            $table->unsignedBigInteger('plan_estudio_id');
            $table->unsignedBigInteger('curso_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedSmallInteger('anio');
            $table->string('estado', 30)->default('borrador');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique('establecimiento_curso_id', 'est_plan_est_curso_uq');
            $table->index(['establecimiento_id', 'anio', 'estado'], 'est_plan_filtros_idx');

            $table->foreign('establecimiento_id', 'fk_epe_est')
                ->references('id')->on('establecimientos')
                ->cascadeOnDelete();
            $table->foreign('establecimiento_curso_id', 'fk_epe_est_curso')
                ->references('id')->on('establecimiento_cursos')
                ->cascadeOnDelete();
            $table->foreign('plan_estudio_id', 'fk_epe_plan')
                ->references('id')->on('planes_estudio')
                ->restrictOnDelete();
            $table->foreign('curso_id', 'fk_epe_curso')
                ->references('id')->on('cursos')
                ->restrictOnDelete();
            $table->foreign('created_by', 'fk_epe_user')
                ->references('id')->on('users')
                ->nullOnDelete();
        });

        Schema::create('establecimiento_planes_estudio_asignaturas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('establecimiento_plan_estudio_id');
            $table->unsignedBigInteger('plan_estudio_bloque_id');
            $table->unsignedBigInteger('asignatura_id')->nullable();
            $table->string('nombre_asignatura_personalizada')->nullable();
            $table->decimal('horas_semanales', 8, 2)->default(0);
            $table->decimal('horas_anuales', 8, 2)->nullable();
            $table->string('origen', 50)->default('oficial');
            $table->text('observacion')->nullable();
            $table->unsignedSmallInteger('orden')->default(1);
            $table->timestamps();

            $table->index(['establecimiento_plan_estudio_id', 'plan_estudio_bloque_id'], 'epea_bloque_idx');

            $table->foreign('establecimiento_plan_estudio_id', 'fk_epea_plan_ee')
                ->references('id')->on('establecimiento_planes_estudio')
                ->cascadeOnDelete();
            $table->foreign('plan_estudio_bloque_id', 'fk_epea_bloque')
                ->references('id')->on('planes_estudio_bloques')
                ->restrictOnDelete();
            $table->foreign('asignatura_id', 'fk_epea_asig')
                ->references('id')->on('asignaturas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('establecimiento_planes_estudio_asignaturas');
        Schema::dropIfExists('establecimiento_planes_estudio');
    }
};
