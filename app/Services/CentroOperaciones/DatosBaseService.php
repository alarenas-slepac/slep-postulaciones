<?php

namespace App\Services\CentroOperaciones;

use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use App\Models\ReemplazoPersonal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DatosBaseService
{
    /**
     * @param  Collection<int, Establecimiento>  $establecimientos
     * @return array<int, array{total:int,fuente:string}>
     */
    public function matriculasPara(Collection $establecimientos, int $anio): array
    {
        $ids = $establecimientos->pluck('id')->map(fn ($id) => (int) $id)->all();
        $cursos = empty($ids) || ! Schema::hasTable('establecimiento_cursos')
            ? collect()
            : EstablecimientoCurso::query()
                ->whereIn('establecimiento_id', $ids)
                ->where('anio', $anio)
                ->where('activo', true)
                ->groupBy('establecimiento_id')
                ->selectRaw('establecimiento_id, COALESCE(SUM(matricula), 0) as total')
                ->pluck('total', 'establecimiento_id');

        return $establecimientos->mapWithKeys(function (Establecimiento $establecimiento) use ($cursos) {
            if ((int) ($establecimiento->matricula_total ?? 0) > 0) {
                return [$establecimiento->id => [
                    'total' => (int) $establecimiento->matricula_total,
                    'fuente' => 'establecimientos.matricula_total',
                ]];
            }

            return [$establecimiento->id => [
                'total' => (int) ($cursos[$establecimiento->id] ?? 0),
                'fuente' => 'cursos_activos',
            ]];
        })->all();
    }

    /**
     * Obtiene el último período disponible de cada establecimiento y evita
     * duplicar personas que aparezcan más de una vez en el mismo padrón.
     *
     * @param  Collection<int, Establecimiento>  $establecimientos
     * @return array<int, array{docentes:int,asistentes:int,periodo:?string}>
     */
    public function dotacionesPara(Collection $establecimientos): array
    {
        $ids = $establecimientos->pluck('id')->map(fn ($id) => (int) $id)->all();
        $resultado = $establecimientos->mapWithKeys(fn (Establecimiento $establecimiento) => [
            $establecimiento->id => ['docentes' => 0, 'asistentes' => 0, 'periodo' => null],
        ])->all();

        if (empty($ids) || ! Schema::hasTable('reemplazos_personal')) {
            return $resultado;
        }

        $periodos = DB::table('reemplazos_personal')
            ->whereIn('establecimiento_id', $ids)
            ->when(
                Schema::hasColumn('reemplazos_personal', 'vigente'),
                fn ($query) => $query->where('vigente', true)
            )
            ->groupBy('establecimiento_id')
            ->selectRaw('establecimiento_id, MAX((anio * 100) + mes) as periodo');

        $filas = ReemplazoPersonal::query()
            ->from('reemplazos_personal as rp')
            ->joinSub($periodos, 'ultimo', function ($join) {
                $join->on('ultimo.establecimiento_id', '=', 'rp.establecimiento_id')
                    ->whereRaw('ultimo.periodo = ((rp.anio * 100) + rp.mes)');
            })
            ->when(
                Schema::hasColumn('reemplazos_personal', 'vigente'),
                fn ($query) => $query->where('rp.vigente', true)
            )
            ->get(['rp.id', 'rp.establecimiento_id', 'rp.rut', 'rp.estatuto', 'rp.anio', 'rp.mes']);

        foreach ($filas->groupBy('establecimiento_id') as $establecimientoId => $personas) {
            $unicas = $personas->groupBy(function ($fila) {
                $rut = preg_replace('/[^0-9kK]/', '', (string) $fila->rut);

                return $rut !== '' ? Str::lower($rut) : 'fila-'.$fila->getKey();
            });

            $docentes = $unicas->filter(function (Collection $registros) {
                return $registros->contains(function ($registro) {
                    return Str::lower(Str::ascii(trim((string) $registro->estatuto))) === 'docente';
                });
            })->count();

            $primera = $personas->first();
            $resultado[(int) $establecimientoId] = [
                'docentes' => $docentes,
                'asistentes' => $unicas->count() - $docentes,
                'periodo' => $primera
                    ? sprintf('%04d%02d', (int) $primera->anio, (int) $primera->mes)
                    : null,
            ];
        }

        return $resultado;
    }

    /**
     * @return array{matricula:array{total:int,fuente:string},dotacion:array{docentes:int,asistentes:int,periodo:?string}}
     */
    public function paraEstablecimiento(Establecimiento $establecimiento, int $anio): array
    {
        $coleccion = collect([$establecimiento]);

        return [
            'matricula' => $this->matriculasPara($coleccion, $anio)[$establecimiento->id],
            'dotacion' => $this->dotacionesPara($coleccion)[$establecimiento->id],
        ];
    }

    /**
     * Las unidades anexas del Centro de Operaciones no heredan matrícula ni
     * dotación del establecimiento principal, para evitar duplicarlas en el
     * consolidado. Sus totales pueden definirse en la configuración del módulo.
     *
     * @return array{matricula:array{total:int,fuente:string},dotacion:array{docentes:int,asistentes:int,periodo:?string}}
     */
    public function paraContexto(
        Establecimiento $establecimiento,
        int $anio,
        ?string $unidadCodigo = null
    ): array {
        if ($unidadCodigo === null || $unidadCodigo === '') {
            return $this->paraEstablecimiento($establecimiento, $anio);
        }

        $unidad = app(UnidadOperacionalService::class)->obtener($establecimiento, $unidadCodigo);

        return [
            'matricula' => [
                'total' => (int) ($unidad['matricula_total'] ?? 0),
                'fuente' => 'unidad_operacional',
            ],
            'dotacion' => [
                'docentes' => (int) ($unidad['docentes_total'] ?? 0),
                'asistentes' => (int) ($unidad['asistentes_total'] ?? 0),
                'periodo' => null,
            ],
        ];
    }
}
