<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('planes_estudio')) {
            Schema::create('planes_estudio', function (Blueprint $table) {
                $table->id();
                $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
                $table->unsignedSmallInteger('anio')->index();
                $table->string('nombre_plan', 180);
                $table->string('nivel_educativo', 80)->nullable()->index();
                $table->string('modalidad', 80)->nullable()->index();
                $table->string('regimen_jec', 20)->index();
                $table->decimal('horas_semanales_subtotal', 6, 2)->nullable();
                $table->decimal('horas_semanales_libre_disposicion', 6, 2)->nullable();
                $table->decimal('horas_semanales_total', 6, 2)->nullable();
                $table->decimal('horas_anuales_total', 8, 2)->nullable();
                $table->string('decreto_referencia', 255)->nullable();
                $table->text('observacion')->nullable();
                $table->boolean('activo')->default(true)->index();
                $table->timestamps();

                $table->unique(['curso_id', 'anio', 'regimen_jec'], 'planes_estudio_curso_anio_regimen_unique');
            });
        }

        if (! Schema::hasTable('planes_estudio_asignaturas')) {
            Schema::create('planes_estudio_asignaturas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('plan_estudio_id')->constrained('planes_estudio')->cascadeOnDelete();
                $table->string('asignatura', 180);
                $table->decimal('horas_semanales', 6, 2)->nullable();
                $table->decimal('horas_anuales', 8, 2)->nullable();
                $table->string('tipo_bloque', 60)->default('asignatura')->index();
                $table->unsignedSmallInteger('orden')->default(1)->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('planes_estudio_asignaturas');
        Schema::dropIfExists('planes_estudio');
    }
};
