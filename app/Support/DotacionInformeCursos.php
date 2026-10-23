<?php

namespace App\Support;

use App\Models\Curso;
use App\Models\DotacionCursoCombinado;
use App\Models\EstablecimientoCurso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/** Cuenta cursos físicos: un grupo combinado activo constituye una unidad. */
class DotacionInformeCursos
{
    public function niveles(int $anio): Collection
    {
        // Incluye catálogos inactivos que aún tienen cursos históricos del año.
        return Curso::query()->where(fn ($query) => $query->where('activo', true)
            ->orWhereIn('id', EstablecimientoCurso::query()->select('curso_id')
                ->where('anio', $anio)->where('activo', true)->where('matricula', '>', 0)))
            ->orderBy('orden')->orderBy('nombre')->orderBy('id')
            ->get(['id', 'codigo', 'nombre', 'nivel_educativo', 'modalidad', 'orden']);
    }

    public function construir(Collection $establecimientos, int $anio, Collection $catalogo, array $seleccionados): array
    {
        $catalogo = $catalogo->keyBy('id');
        $seleccion = array_fill_keys(array_map('intval', $seleccionados), true);
        $columnas = [];
        foreach ($catalogo as $id => $curso) {
            if (isset($seleccion[$id])) {
                $columnas['nivel_'.$id] = ['label' => $curso->nombre, 'combinado' => false];
            }
        }
        $cursos = EstablecimientoCurso::query()->whereIn('establecimiento_id', $establecimientos->modelKeys())
            ->where('anio', $anio)->where('activo', true)->where('matricula', '>', 0)
            ->get(['id', 'establecimiento_id', 'curso_id'])->groupBy('establecimiento_id');
        $tieneGrupos = Schema::hasTable('dotacion_cursos_combinados');
        $tieneMiembros = Schema::hasTable('dotacion_curso_combinado_miembros');
        if ($tieneGrupos !== $tieneMiembros) {
            throw new \RuntimeException('La estructura de cursos combinados está incompleta.');
        }
        $grupos = $tieneGrupos ? DotacionCursoCombinado::query()->with('miembros')
            ->whereIn('establecimiento_id', $establecimientos->modelKeys())
            ->where('anio', $anio)->where('activo', true)->orderBy('id')->get()->groupBy('establecimiento_id') : collect();
        $filas = [];
        $combinadas = [];
        foreach ($establecimientos as $ee) {
            $cursosEe = $cursos->get($ee->id, collect())->keyBy('id');
            $consumidos = [];
            $conteos = [];
            $detalle = [];
            $regulares = $combinados = 0;
            foreach ($grupos->get($ee->id, collect()) as $grupo) {
                // Nunca incorpora miembros de otro establecimiento, año o sin matrícula.
                $miembros = $grupo->miembros->pluck('establecimiento_curso_id')->unique()
                    ->map(fn ($id) => $cursosEe->get($id))->filter();
                if ($miembros->isEmpty()) { continue; }
                foreach ($miembros as $miembro) {
                    if (isset($consumidos[$miembro->id])) {
                        throw new \RuntimeException('Un curso pertenece a más de un grupo combinado activo.');
                    }
                    $consumidos[$miembro->id] = true;
                }
                $ids = $catalogo->keys()->filter(fn ($id) => $miembros->contains('curso_id', $id))->values();
                if (! $ids->contains(fn ($id) => isset($seleccion[$id]))) { continue; }
                $key = 'combinado_'.$ids->implode('_');
                $label = $ids->map(fn ($id) => $catalogo->get($id)->nombre)->implode(' + ').' (combinado)';
                $combinadas[$key] = ['label' => $label, 'combinado' => true];
                $conteos[$key] = ($conteos[$key] ?? 0) + 1;
                $combinados++;
                $detalle[] = $grupo->nombre.': '.$label;
            }
            foreach ($cursosEe as $curso) {
                if (isset($consumidos[$curso->id]) || ! isset($seleccion[$curso->curso_id])) { continue; }
                $key = 'nivel_'.$curso->curso_id;
                $conteos[$key] = ($conteos[$key] ?? 0) + 1;
                $regulares++;
            }
            $filas[] = ['rbd' => (string) $ee->rbd, 'nombre' => (string) $ee->nombre_establecimiento,
                'comuna' => (string) $ee->comuna, 'conteos' => $conteos,
                'regulares' => $regulares, 'combinados' => $combinados, 'total' => $regulares + $combinados,
                'detalle_combinados' => implode(' | ', $detalle)];
        }
        uasort($combinadas, fn ($a, $b) => strnatcasecmp($a['label'], $b['label']));
        $columnas += $combinadas;
        $totales = array_fill_keys(array_keys($columnas), 0);
        foreach ($filas as &$fila) {
            $fila['conteos'] = array_replace(array_fill_keys(array_keys($columnas), 0), $fila['conteos']);
            foreach ($fila['conteos'] as $key => $cantidad) { $totales[$key] += $cantidad; }
        }
        unset($fila);

        return ['columnas' => $columnas, 'filas' => $filas, 'totales' => $totales,
            'regulares' => array_sum(array_column($filas, 'regulares')),
            'combinados' => array_sum(array_column($filas, 'combinados')),
            'total' => array_sum(array_column($filas, 'total'))];
    }
}
