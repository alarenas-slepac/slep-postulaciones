<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $seed = [
        1 => 'Profesional Apoyo PIE',
        2 => 'Profesional Ed. Especial',
        3 => 'Psicopedagogo/a',
        4 => 'Psicólogo/a',
        5 => 'Monitor Talleres Extraprogramáticos',
        6 => 'Monitor Talleres Curriculares',
        7 => 'Asistente de Aula PIE',
        8 => 'Técnico Ed. Parvularia',
        9 => 'Técnico Ed. Especial Diferencial',
        10 => 'Técnico Enfermero/a - Paramédico',
        11 => 'Paradocente - Inspector de Patio',
        12 => 'Paradocente - CRA',
        13 => 'Paradocente - Laboratorios',
        14 => 'Paradocente - Administrativo',
        15 => 'Secretario/a',
        16 => 'Contador/a',
        17 => 'Asistente Social',
        18 => 'Soporte Informático',
        19 => 'Asesoría Legal',
        20 => 'Enfermero/a',
        21 => 'Manipulador/a de Alimentos',
        22 => 'Servicios de Seguridad',
        23 => 'Chofer',
        24 => 'Auxiliar de Servicio',
        25 => 'Portería',
        26 => 'Aseo y Servicios Menores',
        27 => 'Otro',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('funciones_catalogo')) {
            Schema::create('funciones_catalogo', function (Blueprint $table) {
                $table->id();
                $table->string('nombre')->unique();
                $table->timestamps();
            });
        }

        foreach ($this->seed as $id => $nombre) {
            $existingById = DB::table('funciones_catalogo')->where('id', $id)->first();
            $existingByName = DB::table('funciones_catalogo')->where('nombre', $nombre)->first();

            if ($existingById) {
                DB::table('funciones_catalogo')->where('id', $id)->update([
                    'nombre' => $nombre,
                    'updated_at' => now(),
                ]);
                continue;
            }

            if ($existingByName) {
                continue;
            }

            DB::table('funciones_catalogo')->insert([
                'id' => $id,
                'nombre' => $nombre,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (Schema::hasTable('declaracion_sostenedores')) {
            Schema::table('declaracion_sostenedores', function (Blueprint $table) {
                if (!Schema::hasColumn('declaracion_sostenedores', 'funcion_catalogo_id')) {
                    $table->unsignedBigInteger('funcion_catalogo_id')->nullable()->after('titulo_catalogo_id');
                }
                if (!Schema::hasColumn('declaracion_sostenedores', 'nombre_funcion')) {
                    $table->string('nombre_funcion')->nullable()->after('funcion_catalogo_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('declaracion_sostenedores')) {
            Schema::table('declaracion_sostenedores', function (Blueprint $table) {
                if (Schema::hasColumn('declaracion_sostenedores', 'nombre_funcion')) {
                    $table->dropColumn('nombre_funcion');
                }
                if (Schema::hasColumn('declaracion_sostenedores', 'funcion_catalogo_id')) {
                    $table->dropColumn('funcion_catalogo_id');
                }
            });
        }

        Schema::dropIfExists('funciones_catalogo');
    }
};
