<?php

namespace App\Support;

use App\Models\DotacionDocenteAsignacion;
use Illuminate\Support\Collection;

class DotacionAsignacionPorCursoBloque
{
    /** @return Collection<int, array<string, mixed>> */
    public static function necesidades(array $necesidades, string $grupo, string $cursoLabel, ?string $bloque): Collection
    {
        return collect($necesidades[$grupo] ?? [])->filter(fn ($item) => is_array($item)
            && (string) ($item['curso_label'] ?? 'Curso sin identificar') === $cursoLabel
            && ($grupo !== 'plan_estudio' || $bloque === null || (string) ($item['bloque'] ?? 'Sin bloque') === $bloque)
        )->values();
    }

    /** @param Collection<int, array<string, mixed>> $necesidades
     *  @return Collection<int, DotacionDocenteAsignacion>
     */
    public static function asignaciones(Collection $necesidades): Collection
    {
        return $necesidades->flatMap(fn (array $item) => collect($item['asignaciones'] ?? [])
            ->concat($item['acompanamientos'] ?? []))
            ->filter(fn ($row) => $row instanceof DotacionDocenteAsignacion
                && (int) $row->id > 0
                && ! (bool) data_get($row, 'asignacion_automatica', false))
            ->unique('id')
            ->values();
    }
}
