<?php

namespace App\Support;

use App\Models\Establecimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Asociaciones de docentes por asignatura consolidada y nivel para dotación 2027. */
class DotacionDocentesSubsector
{
    public const NIVELES = [
        'parvularia' => 'Educación Parvularia',
        'basica' => 'Educación Básica',
        'media' => 'Educación Media',
        'especial' => 'Educación Especial',
        'epja' => 'EPJA',
        'otros' => 'Otros niveles',
    ];

    public static function disponible(): bool
    {
        return Schema::hasTable('dotacion_docente_subsectores');
    }

    public static function keyParaNecesidad(array $necesidad): ?string
    {
        $nombre = trim((string) ($necesidad['asignatura_nombre'] ?? $necesidad['titulo'] ?? ''));
        if ($nombre === '' || ! data_get($necesidad, 'curso.curso')) {
            return null;
        }

        $normalizado = Str::of($nombre)->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', ' ')->squish()->toString();

        return sha1(self::nivel($necesidad).'|'.$normalizado);
    }

    public static function nivel(array $necesidad): string
    {
        $curso = data_get($necesidad, 'curso.curso');
        $texto = Str::of(collect([
            data_get($curso, 'codigo'), data_get($curso, 'nombre'),
            data_get($curso, 'nivel_educativo'), data_get($curso, 'modalidad'),
        ])->filter()->implode(' '))->ascii()->upper()->toString();

        return match (true) {
            str_contains($texto, 'EPJA'), str_contains($texto, 'ADULTO') => 'epja',
            str_contains($texto, 'ESPECIAL'), str_contains($texto, 'DIFERENCIAL'), str_contains($texto, 'LABORAL') => 'especial',
            preg_match('/\bNT[12]\b|PARVUL/', $texto) === 1 => 'parvularia',
            preg_match('/\b[1-8]B\b|BASICA/', $texto) === 1 => 'basica',
            preg_match('/\b[1-4]M\b|MEDIA/', $texto) === 1 => 'media',
            default => 'otros',
        };
    }

    public static function docenteAdmisible(array $docente, string $nivel): bool
    {
        if (empty($docente['cupo_contrata_id'])) {
            return true;
        }

        return $nivel === 'parvularia' && ($docente['cupo_bloque'] ?? null) === 'parvularia';
    }

    /** @return array<string, mixed> */
    public static function resumen(Establecimiento $establecimiento, int $anio, Collection $necesidades, Collection $docentes): array
    {
        $disponible = self::disponible();
        $docentes = $docentes->values();
        $conocidos = $docentes->keyBy(fn (array $docente) =>
            DotacionEstablecimientoCalculator::normalizeRut((string) ($docente['rut_normalizado'] ?? $docente['rut'] ?? ''))
        );
        $guardados = $disponible
            ? DB::table('dotacion_docente_subsectores')
                ->where('establecimiento_id', $establecimiento->id)
                ->where('anio', $anio)
                ->get()->groupBy('asignatura_key')
            : collect();
        $asignaturas = $necesidades->filter(fn ($necesidad) => is_array($necesidad))
            ->groupBy(fn (array $necesidad) => self::keyParaNecesidad($necesidad) ?? '')
            ->except('')
            ->map(function (Collection $items, string $key) use ($guardados, $conocidos): array {
                $primera = $items->first();
                $historicos = $items->flatMap(fn (array $item) => collect($item['asignaciones'] ?? [])
                    ->concat($item['acompanamientos'] ?? [])
                    ->filter(fn ($asignacion) => data_get($asignacion, 'estamento_cobertura', 'docente') === 'docente')
                    ->map(fn ($asignacion) => DotacionEstablecimientoCalculator::normalizeRut(
                        (string) (data_get($asignacion, 'docente_rut_normalizado') ?: data_get($asignacion, 'docente_rut', ''))
                    )));
                $seleccionados = collect($guardados->get($key, []))
                    ->pluck('docente_rut_normalizado')->concat($historicos)
                    ->filter(fn ($rut) => $rut !== '' && $conocidos->has($rut)
                        && self::docenteAdmisible($conocidos->get($rut), self::nivel($primera)))
                    ->unique()->values()->all();

                return [
                    'key' => $key,
                    'nivel' => self::nivel($primera),
                    'nombre' => (string) ($primera['asignatura_nombre'] ?? $primera['titulo']),
                    'cursos' => $items->pluck('curso_label')->filter()->unique()->values()->all(),
                    'horas_aula' => round((float) $items->sum(fn ($item) => (float) ($item['horas_plan_requeridas'] ?? 0)), 2),
                    'docentes' => $seleccionados,
                    'completo' => count($seleccionados) > 0,
                ];
            })->values();
        $grupos = collect(self::NIVELES)->mapWithKeys(fn ($label, $nivel) => [
            $nivel => ['label' => $label, 'asignaturas' => $asignaturas->where('nivel', $nivel)
                ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE)->values()],
        ])->filter(fn ($grupo) => $grupo['asignaturas']->isNotEmpty());

        return [
            'disponible' => $disponible,
            'grupos' => $grupos,
            'asignaturas' => $asignaturas,
            'docentes' => $docentes,
            'total' => $asignaturas->count(),
            'completas' => $asignaturas->where('completo', true)->count(),
            'completo' => $asignaturas->isEmpty()
                || ($disponible && $asignaturas->every(fn ($item) => $item['completo'])),
        ];
    }
}
