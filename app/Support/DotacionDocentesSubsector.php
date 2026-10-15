<?php

namespace App\Support;

use App\Models\Establecimiento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Asociaciones por asignatura y nivel: docentes 2027 y cobertura EPJA 2026/2027. */
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
        if (($docente['estamento_cobertura'] ?? 'docente') === 'asistente') {
            return $nivel === 'epja';
        }
        if (empty($docente['cupo_contrata_id'])) {
            return true;
        }

        return ($docente['cupo_bloque'] ?? null) === 'plan_estudio'
            || ($nivel === 'parvularia' && ($docente['cupo_bloque'] ?? null) === 'parvularia');
    }

    public static function admiteAsistentes(int $anio, array $necesidad): bool
    {
        return in_array($anio, [2026, 2027], true) && self::nivel($necesidad) === 'epja';
    }

    public static function resumenEpja2026(Establecimiento $establecimiento, array $data): array
    {
        return self::resumen($establecimiento, 2026,
            collect(data_get($data, 'asignacion.necesidades.plan_estudio', []))
                ->filter(fn ($item) => is_array($item) && self::nivel($item) === 'epja'),
            collect(data_get($data, 'asignacion.docentes', $data['docentes'] ?? [])),
            collect(data_get($data, 'asignacion.asistentes', []))
        );
    }

    /** La misma asociación se valida al guardar horas, incluso desde formularios antiguos. */
    public static function rutsParaNecesidad(Establecimiento $establecimiento, int $anio, array $necesidad): array
    {
        $guardados = self::disponible() ? DB::table('dotacion_docente_subsectores')
            ->where('establecimiento_id', $establecimiento->id)->where('anio', $anio)
            ->where('asignatura_key', self::keyParaNecesidad($necesidad))->get() : collect();

        // La asociación es consolidada: las cargas históricas de otros cursos
        // de la misma asignatura y nivel cuentan igual que en el selector.
        $items = collect(DotacionAsignacionCalculator::necesidades(
            $establecimiento, $anio, [], [], DotacionAsignacionCalculator::assignmentsFor($establecimiento, $anio)
        )['plan_estudio'])->filter(fn ($item) => self::keyParaNecesidad($item) === self::keyParaNecesidad($necesidad));

        return self::rutsSeleccionados($guardados, $items, $anio)->all();
    }

    private static function rutsSeleccionados(Collection $guardados, Collection $items, int $anio): Collection
    {
        $historicos = $items->flatMap(fn (array $item) => collect($item['asignaciones'] ?? [])
            ->concat($item['acompanamientos'] ?? [])
            ->filter(fn ($asignacion) => data_get($asignacion, 'estamento_cobertura', 'docente') === 'docente'
                || (self::admiteAsistentes($anio, $item) && data_get($asignacion, 'estamento_cobertura') === 'asistente'))
            ->map(fn ($asignacion) => data_get($asignacion, 'docente_rut_normalizado') ?: data_get($asignacion, 'docente_rut', '')));

        return $guardados->pluck('docente_rut_normalizado')->concat($historicos)
            ->map(fn ($rut) => DotacionEstablecimientoCalculator::normalizeRut((string) $rut))
            ->filter()->unique()->values();
    }

    /** @return array<string, mixed> */
    public static function resumen(Establecimiento $establecimiento, int $anio, Collection $necesidades, Collection $docentes, ?Collection $asistentes = null): array
    {
        $disponible = self::disponible();
        $docentes = $docentes->values();
        if ($necesidades->contains(fn ($item) => is_array($item) && self::admiteAsistentes($anio, $item))) {
            $asistentes ??= DotacionEstablecimientoCalculator::asistentes($establecimiento, $anio);
            $docentes = $docentes->concat($asistentes->map(fn (array $persona) =>
                array_replace($persona, ['estamento_cobertura' => 'asistente'])
            ))->values();
        }
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
            ->map(function (Collection $items, string $key) use ($guardados, $conocidos, $anio): array {
                $primera = $items->first();
                $seleccionados = self::rutsSeleccionados(collect($guardados->get($key, [])), $items, $anio)
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
