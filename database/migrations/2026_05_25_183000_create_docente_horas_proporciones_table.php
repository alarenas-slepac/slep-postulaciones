<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('docente_horas_proporciones', function (Blueprint $table) {
            $table->id();
            $table->string('proporcion', 10);
            $table->unsignedTinyInteger('horas_contrato');
            $table->unsignedTinyInteger('horas_aula_pedagogicas');
            $table->unsignedSmallInteger('horas_aula_cronologicas_minutos');
            $table->unsignedSmallInteger('recreo_minutos');
            $table->unsignedSmallInteger('horas_no_lectivas_minutos');
            $table->boolean('vigente')->default(true);
            $table->timestamps();

            $table->unique(['proporcion', 'horas_contrato'], 'docente_horas_prop_contrato_unique');
            $table->index(['proporcion', 'vigente']);
        });

        $now = now();
        $rows = [];
        foreach (range(1, 44) as $horasContrato) {
            foreach ([
                '65_35' => 0.65,
                '60_40' => 0.60,
            ] as $proporcion => $ratioLectivo) {
                $recreo = (int) round($horasContrato * 45 / 11);
                $horasAulaPedagogicas = max(1, (int) floor(($horasContrato * 60 * $ratioLectivo) / 45));

                // Ajustes de borde visibles en la tabla ministerial para contratos mínimos.
                if ($proporcion === '65_35' && $horasContrato <= 3) {
                    $horasAulaPedagogicas = $horasContrato;
                }
                if ($proporcion === '60_40' && $horasContrato === 2) {
                    $horasAulaPedagogicas = 2;
                }

                $aulaCronologica = $horasAulaPedagogicas * 45;
                $noLectivas = max(0, ($horasContrato * 60) - $aulaCronologica - $recreo);

                // Filas de 44 horas fijadas explícitamente según Anexo 1 del documento de referencia.
                if ($horasContrato === 44 && $proporcion === '65_35') {
                    $horasAulaPedagogicas = 38;
                    $aulaCronologica = 1710;
                    $recreo = 180;
                    $noLectivas = 750;
                }
                if ($horasContrato === 44 && $proporcion === '60_40') {
                    $horasAulaPedagogicas = 35;
                    $aulaCronologica = 1575;
                    $recreo = 180;
                    $noLectivas = 885;
                }

                $rows[] = [
                    'proporcion' => $proporcion,
                    'horas_contrato' => $horasContrato,
                    'horas_aula_pedagogicas' => $horasAulaPedagogicas,
                    'horas_aula_cronologicas_minutos' => $aulaCronologica,
                    'recreo_minutos' => $recreo,
                    'horas_no_lectivas_minutos' => $noLectivas,
                    'vigente' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('docente_horas_proporciones')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('docente_horas_proporciones');
    }
};
