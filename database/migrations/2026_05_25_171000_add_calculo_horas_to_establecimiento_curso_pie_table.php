<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establecimiento_curso_pie', function (Blueprint $table) {
            if (! Schema::hasColumn('establecimiento_curso_pie', 'regimen_calculo')) {
                $table->string('regimen_calculo', 20)->nullable()->index();
            }
            if (! Schema::hasColumn('establecimiento_curso_pie', 'neet_calculo')) {
                $table->unsignedSmallInteger('neet_calculo')->default(0);
            }
            if (! Schema::hasColumn('establecimiento_curso_pie', 'neep_calculo')) {
                $table->unsignedSmallInteger('neep_calculo')->default(0);
            }
            if (! Schema::hasColumn('establecimiento_curso_pie', 'total_crono_minutos')) {
                $table->unsignedInteger('total_crono_minutos')->nullable();
            }
            if (! Schema::hasColumn('establecimiento_curso_pie', 'prof_educ_dif_minutos')) {
                $table->unsignedInteger('prof_educ_dif_minutos')->nullable();
            }
            if (! Schema::hasColumn('establecimiento_curso_pie', 'pae_minutos')) {
                $table->unsignedInteger('pae_minutos')->nullable();
            }
            if (! Schema::hasColumn('establecimiento_curso_pie', 'calculo_observacion')) {
                $table->text('calculo_observacion')->nullable();
            }
            if (! Schema::hasColumn('establecimiento_curso_pie', 'calculado_at')) {
                $table->timestamp('calculado_at')->nullable();
            }
        });

        $this->backfillCalculos();
    }

    public function down(): void
    {
        Schema::table('establecimiento_curso_pie', function (Blueprint $table) {
            foreach (['regimen_calculo', 'neet_calculo', 'neep_calculo', 'total_crono_minutos', 'prof_educ_dif_minutos', 'pae_minutos', 'calculo_observacion', 'calculado_at'] as $column) {
                if (Schema::hasColumn('establecimiento_curso_pie', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillCalculos(): void
    {
        if (! Schema::hasTable('establecimiento_curso_pie') || ! Schema::hasTable('establecimiento_cursos')) {
            return;
        }

        $rows = DB::table('establecimiento_curso_pie as pie')
            ->join('establecimiento_cursos as ec', 'ec.id', '=', 'pie.establecimiento_curso_id')
            ->leftJoin('cursos as c', 'c.id', '=', 'ec.curso_id')
            ->leftJoin('planes_estudio as pe', 'pe.id', '=', 'ec.plan_estudio_id')
            ->select([
                'pie.id',
                'pie.necesidades_transitorias',
                'pie.necesidades_permanentes',
                'ec.regimen_jec',
                'ec.nombre_seccion',
                'c.nombre as curso_nombre',
                'c.codigo as curso_codigo',
                'c.nivel_educativo as curso_nivel',
                'c.modalidad as curso_modalidad',
                'pe.nombre_plan',
                'pe.nivel_educativo as plan_nivel',
                'pe.modalidad as plan_modalidad',
            ])
            ->get();

        $now = now();
        foreach ($rows as $row) {
            $calc = $this->calculateRow($row);
            $calc['calculado_at'] = $now;
            DB::table('establecimiento_curso_pie')->where('id', $row->id)->update($calc);
        }
    }

    private function calculateRow(object $row): array
    {
        $neet = (int) ($row->necesidades_transitorias ?? 0);
        $neep = (int) ($row->necesidades_permanentes ?? 0);
        $isEpja = $this->isEpja($row);
        $regimen = $isEpja ? 'sin_jec' : $this->normalizeRegimen((string) ($row->regimen_jec ?? ''));
        $observaciones = [];

        if ($isEpja) {
            $observaciones[] = 'EPJA aplica regla SIN JEC.';
        }
        if ($neet > 0 && $neet < 5) {
            $observaciones[] = 'NEET menor a 5 calculado como mínimo 5.';
        }

        if ($neet === 0 && $neep === 0) {
            return $this->payload($regimen, 0, $neep, 0, 0, 0, $observaciones ?: ['Sin estudiantes PIE registrados.']);
        }
        if ($neep > 31) {
            $observaciones[] = 'Cantidad NEEP excede tabla de referencia vigente (máximo 31); cálculo automático no aplicado.';
            return $this->payload($regimen, $neet > 0 ? 5 : 0, $neep, null, null, null, $observaciones);
        }
        if ($neet === 0 && $neep > 0) {
            $total = $neep * 180;
            $observaciones[] = 'Sin NEET registrados: se calculan sólo horas NEEP, asignadas a Profesional Educador Diferencial.';
            return $this->payload($regimen, 0, $neep, $total, $total, 0, $observaciones);
        }
        if ($neep === 0) {
            $base = $regimen === 'con_jec' ? 600 : 420;
            return $this->payload($regimen, 5, 0, $base, $base, 0, $observaciones);
        }

        $rule = DB::table('pie_horas_apoyo_minimo')
            ->where('regimen_jec', $regimen)
            ->where('neep_cantidad', $neep)
            ->where('vigente', true)
            ->first();

        if (! $rule) {
            $observaciones[] = 'No existe regla de catálogo para el régimen y cantidad NEEP indicada.';
            return $this->payload($regimen, 5, $neep, null, null, null, $observaciones);
        }

        return $this->payload($regimen, 5, $neep, (int) $rule->total_crono_minutos, (int) $rule->prof_educ_dif_minutos, (int) $rule->pae_minutos, $observaciones);
    }

    private function payload(string $regimen, int $neetCalculo, int $neepCalculo, ?int $total, ?int $educDif, ?int $pae, array $observaciones): array
    {
        return [
            'regimen_calculo' => $regimen,
            'neet_calculo' => $neetCalculo,
            'neep_calculo' => $neepCalculo,
            'total_crono_minutos' => $total,
            'prof_educ_dif_minutos' => $educDif,
            'pae_minutos' => $pae,
            'calculo_observacion' => implode(' ', array_values(array_filter($observaciones))),
        ];
    }

    private function normalizeRegimen(string $value): string
    {
        $text = $this->normalize($value);
        return (str_contains($text, 'CON JEC') || $text === 'CON' || $text === 'JEC') ? 'con_jec' : 'sin_jec';
    }

    private function isEpja(object $row): bool
    {
        $text = $this->normalize(implode(' ', [
            $row->nombre_seccion ?? '',
            $row->curso_nombre ?? '',
            $row->curso_codigo ?? '',
            $row->curso_nivel ?? '',
            $row->curso_modalidad ?? '',
            $row->nombre_plan ?? '',
            $row->plan_nivel ?? '',
            $row->plan_modalidad ?? '',
        ]));

        return str_contains($text, 'EPJA')
            || str_contains($text, 'ADULTO')
            || str_contains($text, 'ADULTA')
            || str_contains($text, 'NIVEL BASICO')
            || str_contains($text, 'NIVEL MEDIO');
    }

    private function normalize(string $text): string
    {
        $text = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
            '°' => '', 'º' => '', '/' => ' ', '-' => ' ', '_' => ' ', '(' => ' ', ')' => ' ', '.' => ' ',
        ]);
        $text = preg_replace('/\s+/', ' ', $text);
        return mb_strtoupper(trim($text), 'UTF-8');
    }
};
