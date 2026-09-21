<?php

namespace App\Support;

use App\Models\DotacionEstablecimientoConfiguracion;
use App\Models\DotacionFuncionRegla;
use App\Models\Establecimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DotacionFuncionesCalculator
{
    public static function contexto(Establecimiento $establecimiento, int $anio): array
    {
        $matriculaTotal = (int) DB::table('establecimiento_cursos')
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->where('activo', true)
            ->sum('matricula');

        $cursosNee = (int) DB::table('establecimiento_curso_pie')
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->where('total_pie', '>', 0)
            ->distinct()
            ->count('establecimiento_curso_id');

        $config = DotacionEstablecimientoConfiguracion::query()
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->first();

        return [
            'matricula_total' => $matriculaTotal,
            'cursos_nee' => $cursosNee,
            'director_adp' => (bool) ($config?->director_adp ?? false),
            'config' => $config,
        ];
    }

    public static function sugerencias(Establecimiento $establecimiento, int $anio): Collection
    {
        $contexto = self::contexto($establecimiento, $anio);
        $rules = DotacionFuncionRegla::query()->where('vigente', true)->orderBy('categoria')->orderBy('id')->get();
        $items = collect();

        foreach ($rules as $rule) {
            if ($rule->declarable || ! self::reglaAplica($rule)) {
                continue;
            }

            $horas = self::calcularHorasRegla($rule, $contexto);
            if ($horas === null) {
                continue;
            }

            $items->push([
                'regla' => $rule,
                'codigo' => $rule->codigo,
                'categoria' => $rule->categoria,
                'nombre_funcion' => $rule->nombre,
                'horas_sugeridas' => $horas,
                'estado' => 'calculado',
                'detalle' => self::detalleRegla($rule, $contexto, $horas),
            ]);
        }

        return $items;
    }

    private static function reglaAplica(DotacionFuncionRegla $rule): bool
    {
        return $rule->codigo !== 'transicion_educativa';
    }

    public static function calcularHorasRegla(DotacionFuncionRegla $rule, array $contexto): ?int
    {
        if ($rule->codigo === 'transicion_educativa') {
            return null;
        }

        return match ($rule->tipo_regla) {
            'fija' => (int) ($rule->horas_fijas ?? 0),
            'director_adp' => (bool) ($contexto['director_adp'] ?? false)
                ? (int) ($rule->horas_fijas ?? 0)
                : null,
            'matricula', 'matricula_por_registro' => ((int) ($contexto['matricula_total'] ?? 0) > (int) ($rule->umbral_matricula ?? 300))
                ? (int) ($rule->horas_sobre_umbral ?? 0)
                : (int) ($rule->horas_bajo_umbral ?? 0),
            'cursos_nee' => (int) ($contexto['cursos_nee'] ?? 0) * 2,
            default => null,
        };
    }

    public static function detalleRegla(DotacionFuncionRegla $rule, array $contexto, int $horas): string
    {
        return match ($rule->codigo) {
            'director_adp' => 'Cargo directivo normativo habilitado para este establecimiento y año.',
            'inspector_general' => 'Inspector(a) General se considera cargo fijo con 44 horas, independiente de si Director(a) es ADP.',
            'coordinador_pie' => 'Cursos con estudiantes NEE: '.((int) ($contexto['cursos_nee'] ?? 0)).'. Regla: 2 horas por curso, sin tope máximo.',
            'coordinador_extraescolar', 'cra', 'coordinador_ciclo_tp_especialidad' => 'Matrícula total del establecimiento: '.number_format((int) ($contexto['matricula_total'] ?? 0), 0, ',', '.').'. Umbral: '.((int) ($rule->umbral_matricula ?? 300)).' estudiantes.',
            default => 'Horas sugeridas según catálogo base de dotación.',
        };
    }

    public static function distribucionCoordinacionPie(int $horas): array
    {
        if ($horas <= 0) {
            return [];
        }

        $pendiente = $horas;
        $items = [];
        $contador = 1;

        while ($pendiente > 0) {
            $asignadas = min(44, $pendiente);
            $items[] = [
                'docente' => $contador,
                'horas' => $asignadas,
            ];
            $pendiente -= $asignadas;
            $contador++;
        }

        return $items;
    }
}
