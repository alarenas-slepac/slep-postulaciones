<?php

namespace App\Support;

use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DotacionInformePlanesNiveles extends DotacionInformePlanes
{
    public const NIVELES = [
        'parvularia' => 'Parvularia', 'basica' => 'Básica', 'media' => 'Media',
        'media_hc' => 'Media HC', 'media_tp' => 'Media TP', 'epja' => 'EPJA', 'especial' => 'Especial',
    ];

    public function fila(Establecimiento $establecimiento, int $anio): array
    {
        $datos = $this->datos($establecimiento, $anio);
        $ids = collect($datos['cursos']['rows'])->flatMap(fn ($row) => $row['detalles'] ?? [])
            ->pluck('establecimiento_curso_id')->merge(collect($datos['grupos'])
                ->where('activo', true)->flatMap(fn ($grupo) => collect($grupo['miembros'])->pluck('id')))->unique();
        // El catálogo conserva HC/TP, que el cuadro por grado agrupa como 3M/4M.
        $categorias = EstablecimientoCurso::with('curso')->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)->whereIn('id', $ids)->get()
            ->mapWithKeys(fn ($curso) => [(int) $curso->id => $this->clasificar($curso)])->all();
        $filas = $this->segregar($datos['cursos'], $categorias, $datos['grupos']);
        $identidad = ['rbd' => (string) $establecimiento->rbd, 'nombre' => (string) $establecimiento->nombre_establecimiento,
            'comuna' => (string) $establecimiento->comuna];
        $filas['global'] += $identidad;
        foreach ($filas['niveles'] as &$fila) { $fila += $identidad; }
        unset($fila);

        return $filas;
    }

    public function clasificar(EstablecimientoCurso $curso): ?string
    {
        $catalogo = $curso->curso;
        $codigo = Str::upper((string) $catalogo?->codigo);
        $texto = Str::of(implode(' ', [$codigo, $catalogo?->nombre, $catalogo?->nivel_educativo,
            $catalogo?->modalidad, $curso->nombre_seccion]))->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', ' ')->squish()->toString();

        return match (true) {
            str_contains($texto, 'EPJA'), str_contains($texto, 'ADULTO') => 'epja',
            str_contains($texto, 'ESPECIAL'), str_contains($texto, 'DIFERENCIAL'), str_contains($texto, 'LABORAL') => 'especial',
            in_array($codigo, ['NT1', 'NT2'], true), str_contains($texto, 'PARVUL') => 'parvularia',
            preg_match('/^[1-8]B$/', $codigo) === 1, str_contains($texto, 'BASIC') => 'basica',
            preg_match('/^[12]M(?:$|-)/', $codigo) === 1, preg_match('/\b[12] (?:MEDIO|MEDIA)\b/', $texto) === 1 => 'media',
            preg_match('/\bTP\b|TECNICO PROFESIONAL/', $texto) === 1 => 'media_tp',
            preg_match('/\bHC\b|HUMANISTIC/', $texto) === 1 => 'media_hc',
            default => null,
        };
    }

    /** Distribuye unidades ya consolidadas; nunca vuelve a convertir horas por nivel. */
    public function segregar(array $cursos, array $categorias, Collection|array $grupos = []): array
    {
        $resumen = $cursos['resumen_cursos_planes'];
        $detalles = collect($cursos['rows'] ?? [])->flatMap(fn ($row) => $row['detalles'] ?? [])
            ->keyBy(fn ($d) => (int) $d['establecimiento_curso_id']);
        $buckets = [];
        foreach (array_keys(self::NIVELES + ['sin_desglose' => 'Sin desglose']) as $key) {
            $buckets[$key] = ['totales' => array_fill_keys(array_keys($resumen['totales']), 0), 'detalles' => [], 'notas' => []];
        }
        // Las filas independientes del resumen ya excluyen cursos combinados y refuerzos.
        foreach ($resumen['rows'] as $row) {
            foreach ($row['detalles'] as $detalle) {
                $key = $categorias[(int) $detalle['establecimiento_curso_id']] ?? 'sin_desglose';
                $contrato = (float) $detalle['horas_contrato_equivalente_redondeado'];
                $pie = (float) ($detalle['trabajo_colaborativo_pie'] ?? 0);
                $this->acumular($buckets[$key], ['matricula' => (int) $detalle['matricula'], 'cursos' => 1,
                    'horas' => (float) $detalle['horas'], 'horas_contrato_equivalente' => $contrato,
                    'trabajo_colaborativo_pie' => $pie, 'contrato_mas_trabajo_colaborativo_pie' => $contrato + $pie], [$detalle]);
            }
        }
        $grupos = collect($grupos)->keyBy('id');
        foreach ($resumen['combinados'] as $combinado) {
            $miembros = collect(data_get($grupos->get($combinado['id']), 'miembros', []))->unique('id');
            if ($miembros->isEmpty()) {
                throw new \RuntimeException('No se encontraron los integrantes del curso combinado.');
            }
            $keys = $miembros->map(fn ($m) => $categorias[(int) $m['id']] ?? 'sin_desglose')->unique();
            $totales = ['matricula' => $combinado['matricula'], 'cursos' => $combinado['cursos'],
                'horas' => $combinado['total_horas'], 'horas_contrato_equivalente' => $combinado['total_horas_contrato_equivalente'],
                'trabajo_colaborativo_pie' => $combinado['total_trabajo_colaborativo_pie'],
                'contrato_mas_trabajo_colaborativo_pie' => $combinado['total_contrato_mas_trabajo_colaborativo_pie']];
            $metadata = $miembros->map(fn ($m) => $detalles->get((int) $m['id']))->filter()->all();
            if ($keys->count() === 1) {
                $this->acumular($buckets[$keys->first()], $totales, $metadata);
                continue;
            }
            // Un grupo entre niveles no tiene un reparto contractual definido.
            // Conserva sus horas una vez en Sin desglose; sus personas/cursos sí
            // pertenecen al nivel de cada integrante. No se inventa un prorrateo.
            $totales['matricula'] = $totales['cursos'] = 0;
            $this->acumular($buckets['sin_desglose'], $totales, $metadata);
            foreach ($miembros as $miembro) {
                $key = $categorias[(int) $miembro['id']] ?? 'sin_desglose';
                $this->acumular($buckets[$key], ['matricula' => (int) ($miembro['matricula'] ?? 0), 'cursos' => 1],
                    array_filter([$detalles->get((int) $miembro['id'])]));
                $buckets[$key]['notas'][] = 'Grupo entre niveles «'.$combinado['label'].'»: horas compartidas en la hoja Sin desglose.';
            }
            $buckets['sin_desglose']['notas'][] = 'Horas compartidas del grupo «'.$combinado['label'].'»; matrícula y cursos informados en sus niveles.';
        }
        // El refuerzo se identifica por el curso NT de origen, aunque su contrato
        // se financie en Plan General. No agrega matrícula, cursos ni PIE.
        foreach ($detalles as $id => $detalle) {
            $horas = (float) ($detalle['horas_plan_refuerzo_ld_otro_docente'] ?? 0);
            $contrato = (float) ($detalle['horas_contrato_refuerzo_ld_otro_docente'] ?? 0);
            if ($horas <= 0 && $contrato <= 0) { continue; }
            $key = $categorias[$id] ?? 'sin_desglose';
            $this->acumular($buckets[$key], ['horas' => $horas, 'horas_contrato_equivalente' => $contrato,
                'contrato_mas_trabajo_colaborativo_pie' => $contrato], [$detalle]);
            $buckets[$key]['notas'][] = 'Incluye libre disposición NT1/NT2 de otros docentes contabilizada en Plan General.';
        }
        $filas = [];
        foreach ($buckets as $key => $bucket) {
            if (! array_sum($bucket['totales']) && $bucket['notas'] === []) { continue; }
            $row = $this->resumir(['rows' => [['detalles' => array_values($bucket['detalles'])]],
                'resumen_cursos_planes' => ['totales' => $bucket['totales']]]);
            if ($key === 'sin_desglose' && (int) $bucket['totales']['cursos'] === 0) {
                $row['observaciones'] = str_replace('Sin cursos activos con matrícula en el año consultado.', '', $row['observaciones']);
            }
            if ($key === 'sin_desglose') { $bucket['notas'][] = 'Cursos sin clasificación en los niveles solicitados u horas de grupos entre niveles; incluidos una sola vez en el global.'; }
            $row['observaciones'] = trim($row['observaciones'].' '.implode(' ', array_unique($bucket['notas'])));
            $filas[$key] = $row;
        }
        foreach ($resumen['totales'] as $key => $total) {
            if (abs((float) collect($buckets)->sum('totales.'.$key) - (float) $total) > 0.01) {
                throw new \RuntimeException('El desglose por niveles no concilia con el total del establecimiento.');
            }
        }
        $global = $this->resumir($cursos);
        if (isset($filas['sin_desglose'])) {
            $global['observaciones'] = trim($global['observaciones'].' Revise la hoja Sin desglose: contiene cursos sin clasificación u horas compartidas entre niveles.');
        }

        return ['global' => $global, 'niveles' => $filas];
    }

    private function acumular(array &$bucket, array $totales, array $detalles): void
    {
        foreach ($totales as $key => $value) { $bucket['totales'][$key] = round($bucket['totales'][$key] + $value, 2); }
        foreach ($detalles as $detalle) { $bucket['detalles'][(int) $detalle['establecimiento_curso_id']] = $detalle; }
    }
}
