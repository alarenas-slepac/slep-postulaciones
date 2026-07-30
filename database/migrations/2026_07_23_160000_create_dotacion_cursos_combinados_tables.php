<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dotacion_cursos_combinados')) {
            Schema::create('dotacion_cursos_combinados', function (Blueprint $table) {
                $table->id();
                $table->foreignId('establecimiento_id');
                $table->unsignedSmallInteger('anio')->index();
                $table->string('nombre', 180);
                $table->string('proporcion', 16)->default('auto');
                $table->text('observacion')->nullable();
                $table->boolean('activo')->default(true)->index();
                $table->unsignedBigInteger('created_by')->nullable()->index();
                $table->unsignedBigInteger('updated_by')->nullable()->index();
                $table->timestamps();

                $table->foreign('establecimiento_id', 'dcc_est_fk')
                    ->references('id')->on('establecimientos')->cascadeOnDelete();
                $table->index(['establecimiento_id', 'anio', 'activo'], 'dcc_est_anio_act_idx');
            });
        }

        if (! Schema::hasTable('dotacion_curso_combinado_miembros')) {
            Schema::create('dotacion_curso_combinado_miembros', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dotacion_curso_combinado_id');
                $table->unsignedBigInteger('establecimiento_curso_id')->index();
                $table->timestamps();

                $table->foreign('dotacion_curso_combinado_id', 'dccm_grupo_fk')
                    ->references('id')->on('dotacion_cursos_combinados')->cascadeOnDelete();
                $table->foreign('establecimiento_curso_id', 'dccm_curso_fk')
                    ->references('id')->on('establecimiento_cursos')->cascadeOnDelete();
                $table->unique(
                    ['dotacion_curso_combinado_id', 'establecimiento_curso_id'],
                    'dccm_grupo_curso_uk'
                );
            });
        }

        if (! Schema::hasTable('dotacion_curso_combinado_asignaturas')) {
            Schema::create('dotacion_curso_combinado_asignaturas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dotacion_curso_combinado_id');
                $table->string('asignatura_key', 120);
                $table->string('asignatura_nombre', 255);
                $table->string('modalidad', 24)->default('conjunta');
                $table->decimal('horas_conjuntas', 8, 2)->nullable();
                $table->decimal('horas_personalizadas', 8, 2)->nullable();
                $table->json('horas_exclusivas')->nullable();
                $table->text('observacion')->nullable();
                $table->timestamps();

                $table->foreign('dotacion_curso_combinado_id', 'dcca_grupo_fk')
                    ->references('id')->on('dotacion_cursos_combinados')->cascadeOnDelete();
                $table->unique(
                    ['dotacion_curso_combinado_id', 'asignatura_key'],
                    'dcca_grupo_asignatura_uk'
                );
            });
        }

        if (Schema::hasTable('dotacion_docente_asignaciones')) {
            $addGroupColumn = ! Schema::hasColumn('dotacion_docente_asignaciones', 'dotacion_curso_combinado_id');
            $addSubjectColumn = ! Schema::hasColumn('dotacion_docente_asignaciones', 'dotacion_curso_combinado_asignatura_id');

            if ($addGroupColumn || $addSubjectColumn) {
                Schema::table('dotacion_docente_asignaciones', function (Blueprint $table) use ($addGroupColumn, $addSubjectColumn) {
                    if ($addGroupColumn) {
                        $table->unsignedBigInteger('dotacion_curso_combinado_id')
                            ->nullable()
                            ->after('establecimiento_curso_id')
                            ->index('dda_curso_comb_idx');
                    }
                    if ($addSubjectColumn) {
                        $table->unsignedBigInteger('dotacion_curso_combinado_asignatura_id')
                            ->nullable()
                            ->after('dotacion_curso_combinado_id')
                            ->index('dda_comb_asig_idx');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dotacion_docente_asignaciones')) {
            $dropSubjectColumn = Schema::hasColumn('dotacion_docente_asignaciones', 'dotacion_curso_combinado_asignatura_id');
            $dropGroupColumn = Schema::hasColumn('dotacion_docente_asignaciones', 'dotacion_curso_combinado_id');

            if ($dropSubjectColumn || $dropGroupColumn) {
                Schema::table('dotacion_docente_asignaciones', function (Blueprint $table) use ($dropSubjectColumn, $dropGroupColumn) {
                    if ($dropSubjectColumn) {
                        $table->dropIndex('dda_comb_asig_idx');
                        $table->dropColumn('dotacion_curso_combinado_asignatura_id');
                    }
                    if ($dropGroupColumn) {
                        $table->dropIndex('dda_curso_comb_idx');
                        $table->dropColumn('dotacion_curso_combinado_id');
                    }
                });
            }
        }

        Schema::dropIfExists('dotacion_curso_combinado_asignaturas');
        Schema::dropIfExists('dotacion_curso_combinado_miembros');
        Schema::dropIfExists('dotacion_cursos_combinados');
    }
};
