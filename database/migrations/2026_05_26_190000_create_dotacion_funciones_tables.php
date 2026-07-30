<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dotacion_funciones_reglas')) {
            Schema::create('dotacion_funciones_reglas', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 100)->unique();
                $table->string('categoria', 60)->index();
                $table->string('nombre', 180);
                $table->string('tipo_regla', 60)->index();
                $table->unsignedSmallInteger('horas_fijas')->nullable();
                $table->unsignedSmallInteger('horas_minimas')->nullable();
                $table->unsignedSmallInteger('horas_maximas')->nullable();
                $table->unsignedSmallInteger('umbral_matricula')->nullable();
                $table->unsignedSmallInteger('horas_bajo_umbral')->nullable();
                $table->unsignedSmallInteger('horas_sobre_umbral')->nullable();
                $table->boolean('permite_multiples')->default(false)->index();
                $table->boolean('declarable')->default(false)->index();
                $table->boolean('obligatoria')->default(false)->index();
                $table->boolean('requiere_validacion')->default(true);
                $table->text('fundamento')->nullable();
                $table->boolean('vigente')->default(true)->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dotacion_establecimiento_configuraciones')) {
            Schema::create('dotacion_establecimiento_configuraciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('establecimiento_id');
                $table->foreign('establecimiento_id', 'dot_cfg_estab_fk')->references('id')->on('establecimientos')->cascadeOnDelete();
                $table->unsignedSmallInteger('anio')->index();
                $table->boolean('director_adp')->default(false)->index();
                $table->text('observacion')->nullable();
                $table->foreignId('created_by')->nullable();
                $table->foreignId('updated_by')->nullable();
                $table->foreign('created_by', 'dot_cfg_created_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by', 'dot_cfg_updated_fk')->references('id')->on('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['establecimiento_id', 'anio'], 'dotacion_config_estab_anio_unique');
            });
        }

        if (! Schema::hasTable('dotacion_funciones_establecimiento')) {
            Schema::create('dotacion_funciones_establecimiento', function (Blueprint $table) {
                $table->id();
                $table->foreignId('establecimiento_id');
                $table->foreign('establecimiento_id', 'dot_func_estab_fk')->references('id')->on('establecimientos')->cascadeOnDelete();
                $table->foreignId('regla_id')->nullable();
                $table->foreign('regla_id', 'dot_func_regla_fk')->references('id')->on('dotacion_funciones_reglas')->nullOnDelete();
                $table->unsignedSmallInteger('anio')->index();
                $table->string('categoria', 60)->index();
                $table->string('nombre_funcion', 180);
                $table->string('tipo_coordinacion', 80)->nullable();
                $table->text('descripcion_funcion')->nullable();
                $table->string('origen', 60)->default('manual_establecimiento')->index();
                $table->string('tipo_regla', 60)->nullable();
                $table->unsignedSmallInteger('horas_sugeridas')->nullable();
                $table->unsignedSmallInteger('horas_declaradas')->nullable();
                $table->unsignedSmallInteger('horas_aprobadas')->nullable();
                $table->text('fundamento')->nullable();
                $table->text('observacion')->nullable();
                $table->string('estado', 40)->default('borrador')->index();
                $table->foreignId('created_by')->nullable();
                $table->foreignId('updated_by')->nullable();
                $table->foreignId('validated_by')->nullable();
                $table->foreign('created_by', 'dot_func_created_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by', 'dot_func_updated_fk')->references('id')->on('users')->nullOnDelete();
                $table->foreign('validated_by', 'dot_func_validated_fk')->references('id')->on('users')->nullOnDelete();
                $table->timestamp('validated_at')->nullable();
                $table->timestamps();

                $table->index(['establecimiento_id', 'anio', 'categoria'], 'dotacion_func_estab_anio_cat_idx');
            });
        }

        $this->seedRules();
        $this->registerModule();
    }

    public function down(): void
    {
        Schema::dropIfExists('dotacion_funciones_establecimiento');
        Schema::dropIfExists('dotacion_establecimiento_configuraciones');
        Schema::dropIfExists('dotacion_funciones_reglas');

        if (Schema::hasTable('modules')) {
            $moduleId = DB::table('modules')->where('key', 'admin.dotacion-funciones')->value('id');
            if ($moduleId) {
                if (Schema::hasTable('module_role')) {
                    DB::table('module_role')->where('module_id', $moduleId)->delete();
                }
                DB::table('modules')->where('id', $moduleId)->delete();
            }
        }
    }

    private function seedRules(): void
    {
        if (! Schema::hasTable('dotacion_funciones_reglas')) {
            return;
        }

        $now = now();
        $rules = [
            ['director', 'directiva', 'Director(a)', 'fija', 44, null, null, null, null, null, false, false, true, 'Ley N° 19.070 - Estatuto Docente. Cargo de dirección escolar obligatorio.'],
            ['jefe_utp', 'directiva', 'Jefe(a) UTP', 'fija', 44, null, null, null, null, null, false, false, true, 'Ley N° 19.070 - Estatuto Docente. Función directiva y técnico-pedagógica.'],
            ['inspector_general', 'directiva', 'Inspector(a) General', 'fija', 44, null, null, null, null, null, false, false, true, 'Cargo fijo de la dotación directiva: 44 horas, independiente de si Director(a) es ADP.'],
            ['coordinador_pie', 'tecnico_pedagogica', 'Coordinador(a) PIE', 'cursos_nee', null, null, null, null, null, null, false, false, true, '2 horas por curso con estudiantes NEE. Si supera 44 horas, el excedente debe asignarse a otro/a docente diferencial.'],
            ['coordinador_ciclo_tp_especialidad', 'tecnico_pedagogica', 'Coordinador de Ciclo, TP, Especialidad u otro', 'matricula_por_registro', null, null, null, 300, 3, 5, true, true, false, 'Cada establecimiento declara cada coordinación. 3 horas si matrícula <= 300; 5 horas si matrícula > 300.'],
            ['encargado_convivencia', 'tecnico_pedagogica', 'Encargado(a) de Convivencia Escolar', 'fija', 44, null, null, null, null, null, false, false, true, 'Función considerada para todos los establecimientos educacionales.'],
            ['orientador', 'tecnico_pedagogica', 'Orientador(a)', 'manual', null, 22, 44, null, null, null, false, true, false, 'No obligatorio. Actualmente se declara sólo cuando existe la función; referencia 22/44 horas según validación.'],
            ['coordinador_extraescolar', 'tecnico_pedagogica', 'Coordinador(a) extraescolar', 'matricula', null, null, null, 300, 4, 8, false, false, false, 'Hasta 300 estudiantes: 4 horas; más de 300 estudiantes: 8 horas.'],
            ['plan_afectividad', 'planes_programas', 'Plan de Afectividad, Sexualidad y Género', 'fija', 3, null, null, null, null, null, false, false, true, 'Horas para coordinar acciones, seguimiento y evaluación del plan.'],
            ['plan_formacion_ciudadana', 'planes_programas', 'Plan de Formación Ciudadana', 'fija', 3, null, null, null, null, null, false, false, true, 'Ley N° 20.911. Todo establecimiento debe contar con plan de formación ciudadana.'],
            ['pise', 'planes_programas', 'Plan Integral de Seguridad Escolar (PISE)', 'fija', 3, null, null, null, null, null, false, false, true, 'Horas para diseño, implementación de simulacros, protocolos y reporte.'],
            ['cra', 'planes_programas', 'Centro de Recursos para el Aprendizaje (CRA)', 'matricula', null, null, null, 300, 4, 8, false, false, false, 'Hasta 300 estudiantes: 4 horas; más de 300 estudiantes: 8 horas.'],
            ['pae_programa', 'planes_programas', 'Programa de Alimentación Escolar (PAE)', 'fija', 6, null, null, null, null, null, false, false, true, 'Coordinación del programa, registro y seguimiento de alimentación escolar.'],
            ['centro_estudiantes', 'planes_programas', 'Centro de Estudiantes', 'fija', 2, null, null, null, null, null, false, false, true, 'Profesor/a asesor/a del centro de estudiantes.'],
            ['centro_padres', 'planes_programas', 'Centro de Padres, Madres y/o Apoderados', 'fija', 2, null, null, null, null, null, false, false, true, 'Profesor/a asesor/a del centro de padres, madres y/o apoderados.'],
            ['transicion_educativa', 'planes_programas', 'Transición educativa', 'nt1_nt2', null, null, null, 40, 20, 44, false, false, false, 'Matrícula NT1 y NT2 menor a 40: 20 horas; 40 o más: 44 horas.'],
            ['otra_funcion_docente', 'otras_funciones_docentes', 'Otra función docente declarada', 'manual', null, null, null, null, null, null, true, true, false, 'Funciones docentes de apoyo directivo o técnico-pedagógico no declaradas en reglas base.'],
        ];

        foreach ($rules as $rule) {
            [$codigo, $categoria, $nombre, $tipoRegla, $horasFijas, $horasMinimas, $horasMaximas, $umbralMatricula, $horasBajo, $horasSobre, $permiteMultiples, $declarable, $obligatoria, $fundamento] = $rule;
            DB::table('dotacion_funciones_reglas')->updateOrInsert(
                ['codigo' => $codigo],
                [
                    'categoria' => $categoria,
                    'nombre' => $nombre,
                    'tipo_regla' => $tipoRegla,
                    'horas_fijas' => $horasFijas,
                    'horas_minimas' => $horasMinimas,
                    'horas_maximas' => $horasMaximas,
                    'umbral_matricula' => $umbralMatricula,
                    'horas_bajo_umbral' => $horasBajo,
                    'horas_sobre_umbral' => $horasSobre,
                    'permite_multiples' => $permiteMultiples,
                    'declarable' => $declarable,
                    'obligatoria' => $obligatoria,
                    'requiere_validacion' => true,
                    'fundamento' => $fundamento,
                    'vigente' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function registerModule(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        $now = now();
        $moduleId = DB::table('modules')->where('key', 'admin.dotacion-funciones')->value('id');
        $payload = [
            'name' => 'Dotación funciones y planes',
            'section' => 'Catálogos',
            'icon' => null,
            'sort' => 32,
            'updated_at' => $now,
        ];

        if ($moduleId) {
            DB::table('modules')->where('id', $moduleId)->update($payload);
        } else {
            $payload['key'] = 'admin.dotacion-funciones';
            $payload['created_at'] = $now;
            $moduleId = DB::table('modules')->insertGetId($payload);
        }

        if (! Schema::hasTable('roles') || ! Schema::hasTable('module_role')) {
            return;
        }

        $roles = DB::table('roles')
            ->whereIn('name', ['admin', 'funcionario_directivo_estab', 'coordinador_uatp', 'coordinador_gdp'])
            ->pluck('id');

        $hasTimestamps = Schema::hasColumn('module_role', 'created_at') && Schema::hasColumn('module_role', 'updated_at');

        foreach ($roles as $roleId) {
            $exists = DB::table('module_role')
                ->where('module_id', $moduleId)
                ->where('role_id', $roleId)
                ->exists();

            if (! $exists) {
                $row = ['module_id' => $moduleId, 'role_id' => $roleId];
                if ($hasTimestamps) {
                    $row['created_at'] = $now;
                    $row['updated_at'] = $now;
                }
                DB::table('module_role')->insert($row);
            }
        }
    }
};
