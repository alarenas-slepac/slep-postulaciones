<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('funcionarios_ac_jefaturas_dependencias')) {
            Schema::create('funcionarios_ac_jefaturas_dependencias', function (Blueprint $table) {
                $table->id();
                $table->string('subdireccion_dependencia');
                $table->unsignedBigInteger('jefatura_funcionario_ac_id')->nullable();
                $table->unsignedBigInteger('subrogante_1_funcionario_ac_id')->nullable();
                $table->unsignedBigInteger('subrogante_2_funcionario_ac_id')->nullable();
                $table->unsignedBigInteger('subrogante_3_funcionario_ac_id')->nullable();
                $table->boolean('subrogancia_activa')->default(false);
                $table->unsignedTinyInteger('subrogante_activo_nivel')->nullable();
                $table->date('subrogancia_desde')->nullable();
                $table->date('subrogancia_hasta')->nullable();
                $table->unsignedBigInteger('subrogancia_activada_por')->nullable();
                $table->text('motivo_subrogancia')->nullable();
                $table->boolean('activo')->default(true);
                $table->text('observaciones')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('funcionarios_ac_jefaturas_dependencias')) {
            try {
                $indexes = collect(DB::select("SHOW INDEX FROM `funcionarios_ac_jefaturas_dependencias`"))
                    ->pluck('Key_name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                if (! in_array('fac_jef_dep_unique', $indexes, true)) {
                    Schema::table('funcionarios_ac_jefaturas_dependencias', function (Blueprint $table) {
                        $table->unique('subdireccion_dependencia', 'fac_jef_dep_unique');
                    });
                }
            } catch (Throwable $e) {
                // Si la tabla quedó creada en una ejecución previa fallida o el índice ya existe con otro nombre,
                // no detenemos la migración: updateOrInsert mantiene la consistencia funcional por dependencia.
            }
        }

        $dependencias = [
            'Subdirección de Gestión y Desarrollo de las Personas',
            'Subdirección de Administración y Finanzas',
            'Subdirección de Planificación y Control de Gestión',
            'Subdirección de Apoyo Técnico Pedagógico',
            'Subdirección de Infraestructura y Mantenimiento',
            'Gabinete',
            'Unidad Jurídica',
            'Dirección Ejecutiva',
        ];

        foreach ($dependencias as $dependencia) {
            DB::table('funcionarios_ac_jefaturas_dependencias')->updateOrInsert(
                ['subdireccion_dependencia' => $dependencia],
                ['activo' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('funcionarios_ac_jefaturas_dependencias');
    }
};
