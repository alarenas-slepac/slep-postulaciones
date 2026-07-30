<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dotacion_docente_asignaciones')) {
            return;
        }

        Schema::create('dotacion_docente_asignaciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio')->index();
            $table->foreignId('establecimiento_id');
            $table->foreign('establecimiento_id', 'dda_est_fk')->references('id')->on('establecimientos')->cascadeOnDelete();
            $table->string('docente_rut', 32);
            $table->string('docente_rut_normalizado', 32)->index();
            $table->string('docente_nombre')->nullable();
            $table->unsignedBigInteger('reemplazos_personal_id')->nullable()->index();
            $table->unsignedBigInteger('declaracion_sostenedor_id')->nullable()->index();
            $table->string('tipo_asignacion', 64)->index();
            $table->string('subtipo_asignacion', 64)->nullable()->index();
            $table->string('subvencion', 80)->default('General')->index();
            $table->string('necesidad_key', 180)->nullable()->index();
            $table->unsignedBigInteger('establecimiento_curso_id')->nullable()->index();
            $table->unsignedBigInteger('plan_estudio_id')->nullable()->index();
            $table->unsignedBigInteger('plan_bloque_id')->nullable()->index();
            $table->unsignedBigInteger('asignatura_id')->nullable()->index();
            $table->string('asignatura_nombre')->nullable();
            $table->unsignedBigInteger('dotacion_funcion_id')->nullable()->index();
            $table->unsignedBigInteger('dotacion_funcion_regla_id')->nullable()->index();
            $table->decimal('horas_plan_pedagogicas', 8, 2)->nullable();
            $table->decimal('horas_contrato', 8, 2)->default(0);
            $table->decimal('horas_cronologicas_aula', 8, 2)->nullable();
            $table->string('proporcion_aplicada')->nullable();
            $table->string('fuente_calculo')->nullable();
            $table->text('observacion')->nullable();
            $table->string('estado', 32)->default('activa')->index();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dotacion_docente_asignaciones');
    }
};
