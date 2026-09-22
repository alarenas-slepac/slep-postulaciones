<?php

namespace App\Support;

use App\Models\DotacionFuncionEstablecimiento;
use App\Models\DotacionProceso2027Configuracion;
use App\Models\Establecimiento;
use App\Models\EstablecimientoCurso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Reglas operativas exclusivas del proceso de dotación 2027.
 * Los cálculos históricos siguen usando sus reglas habituales.
 */
class DotacionProceso2027Calculator
{
    public const ANIO = 2027;

    private const ESTADOS_PLAN_CONFIGURADO = ['enviado', 'aprobado', 'cerrado'];

    public const BLOQUES = [
        'bloque_1' => 'Plan general, trabajo colaborativo PIE y funciones normativas',
        'bloque_2' => 'Educación Parvularia y trabajo colaborativo PIE NT1/NT2',
        'bloque_3' => 'PIE especializado: coordinación PIE y educadoras diferenciales',
    ];

    public static function aplica(int $anio): bool
    {
        return $anio === self::ANIO;
    }

    /** @return array<string, mixed> */
    public static function resumen(Establecimiento $establecimiento, int $anio, ?array $data = null): array
    {
        if (! self::aplica($anio)) {
            return ['aplica' => false];
        }

        $data ??= DotacionEstablecimientoCalculator::build($establecimiento, $anio);
        $config = self::configuracion($establecimiento, $anio);
        $necesidades = collect(data_get($data, 'asignacion.necesidades', []));
        $cursosNt = self::cursosNt($data);
        $needBlocks = [];
        $needKeysObligatorias = [];
        $seleccionNormativas = (array) ($config?->funciones_normativas ?? []);
        $funcionesNormativas = collect();
        $resumenContractual = (array) data_get($data, 'resumen', []);
        $usarResumenContractualPorComponente = array_key_exists('contrato_plan_general_mas_trabajo_colaborativo_pie', $resumenContractual)
            && array_key_exists('contrato_educacion_parvularia_mas_trabajo_colaborativo_pie', $resumenContractual);
        $bloques = collect(self::BLOQUES)->mapWithKeys(fn ($label, $key) => [$key => [
            'key' => $key,
            'label' => $label,
            'requeridas' => 0.0,
            'asignadas' => 0.0,
            'asignadas_obligatorias' => 0.0,
            'titulares_asignadas' => 0.0,
            'contrata_asignadas' => 0.0,
            'horas_normativas_potenciales' => 0.0,
            'horas_normativas_definidas' => 0.0,
            'maximo' => $config ? self::numero($config->{'max_horas_'.$key}) : null,
        ]])->all();

        foreach ($necesidades as $groupKey => $items) {
            foreach (collect($items) as $item) {
                $bloque = self::bloqueNecesidad($groupKey, $item, $cursosNt);
                if (! $bloque) {
                    continue;
                }
                $key = trim((string) data_get($item, 'key', ''));
                if ($key !== '') {
                    $needBlocks[$key] = $bloque;
                }
                $esNormativaDefinible = self::esFuncionNormativaDefinible($groupKey, $item);
                if ($esNormativaDefinible) {
                    $horasPotenciales = max(0.0, (float) data_get($item, 'horas_contrato_requeridas', 0));
                    $tieneAsignacion = (float) data_get($item, 'horas_contrato_asignadas', 0) > 0.01
                        || (bool) data_get($item, 'asignacion_automatica', false);
                    $definida = array_key_exists($key, $seleccionNormativas) || $tieneAsignacion;
                    $seUtilizara = $tieneAsignacion || (bool) ($seleccionNormativas[$key] ?? false);
                    $funcionesNormativas->push([
                        'key' => $key,
                        'titulo' => (string) data_get($item, 'titulo', 'Función normativa'),
                        'subtipo' => (string) data_get($item, 'subtipo_asignacion', ''),
                        'horas' => round($horasPotenciales, 2),
                        'definida' => $definida,
                        'se_utilizara' => $seUtilizara,
                        'asignacion_existente' => $tieneAsignacion,
                    ]);
                    $bloques['bloque_1']['horas_normativas_potenciales'] += $horasPotenciales;
                    if (! $seUtilizara) {
                        continue;
                    }
                    $bloques['bloque_1']['horas_normativas_definidas'] += $horasPotenciales;
                }
                if (! self::esNecesidadObligatoria($groupKey, $item)) {
                    continue;
                }
                if ($key !== '') {
                    $needKeysObligatorias[$key] = true;
                }
                if ($usarResumenContractualPorComponente
                    && in_array($groupKey, ['plan_estudio', 'pie_colaborativo'], true)) {
                    continue;
                }
                $bloques[$bloque]['requeridas'] += $esNormativaDefinible
                    ? max(0.0, (float) data_get($item, 'horas_contrato_requeridas', 0))
                    : DotacionAsignacionCalculator::horasContratoRequeridasParaCalculo($item);
            }
        }

        if ($usarResumenContractualPorComponente) {
            // Esta base ya aplica la consolidación y el redondeo contractual de
            // cursos combinados. Evita que el proceso guiado sume conversiones
            // por asignatura que la tarjeta contractual ya redujo correctamente.
            $bloques['bloque_1']['requeridas'] += max(0.0, (float) $resumenContractual['contrato_plan_general_mas_trabajo_colaborativo_pie']);
            $bloques['bloque_2']['requeridas'] += max(0.0, (float) $resumenContractual['contrato_educacion_parvularia_mas_trabajo_colaborativo_pie']);
        }

        $docentes = self::docentesPriorizados(collect($data['docentes'] ?? []));
        $docentesPorRut = $docentes->keyBy('rut_normalizado');
        $asignaciones = collect(data_get($data, 'asignacion.asignaciones', []))
            ->filter(fn ($row) => DotacionAsignacionCalculator::esAsignacionDocenteReal($row));
        foreach ($asignaciones as $asignacion) {
            $bloque = $needBlocks[(string) data_get($asignacion, 'necesidad_key', '')]
                ?? self::bloqueParaAsignacion($asignacion);
            if (! $bloque || ! isset($bloques[$bloque])) {
                continue;
            }
            $horas = max(0.0, (float) data_get($asignacion, 'horas_contrato', 0));
            $bloques[$bloque]['asignadas'] += $horas;
            if (isset($needKeysObligatorias[(string) data_get($asignacion, 'necesidad_key', '')])) {
                $bloques[$bloque]['asignadas_obligatorias'] += $horas;
            }
            $rut = DotacionEstablecimientoCalculator::normalizeRut((string) (data_get($asignacion, 'docente_rut_normalizado') ?: data_get($asignacion, 'docente_rut', '')));
            $docente = $docentesPorRut->get($rut);
            if ($docente) {
                $titularDisponible = max(0.0, (float) $docente['horas_planta'] - (float) $docente['horas_asignadas_previas']);
                $titular = min($horas, $titularDisponible);
                $bloques[$bloque]['titulares_asignadas'] += $titular;
                $bloques[$bloque]['contrata_asignadas'] += max(0.0, $horas - $titular);
                $docente['horas_asignadas_previas'] += $horas;
                $docentesPorRut->put($rut, $docente);
            } else {
                $bloques[$bloque]['contrata_asignadas'] += $horas;
            }
        }

        foreach ($bloques as &$bloque) {
            $bloque['requeridas'] = round((float) $bloque['requeridas'], 2);
            $bloque['asignadas'] = round((float) $bloque['asignadas'], 2);
            $bloque['asignadas_obligatorias'] = round((float) $bloque['asignadas_obligatorias'], 2);
            $bloque['titulares_asignadas'] = round((float) $bloque['titulares_asignadas'], 2);
            $bloque['contrata_asignadas'] = round((float) $bloque['contrata_asignadas'], 2);
            $bloque['horas_normativas_potenciales'] = round((float) $bloque['horas_normativas_potenciales'], 2);
            $bloque['horas_normativas_definidas'] = round((float) $bloque['horas_normativas_definidas'], 2);
            $bloque['pendientes'] = max(0.0, round($bloque['requeridas'] - $bloque['asignadas_obligatorias'], 2));
            $bloque['saldo_maximo'] = $bloque['maximo'] === null ? null : round(max(0.0, $bloque['maximo'] - $bloque['asignadas']), 2);
            $bloque['maximo_insuficiente'] = $bloque['maximo'] !== null && $bloque['maximo'] + 0.01 < $bloque['requeridas'];
        }
        unset($bloque);

        $estadoPlanes = self::estadoPlanesEstudio($establecimiento, $anio, $data);
        $planesCompletos = $estadoPlanes['completo'];
        $gruposActivos = (int) data_get($data, 'cursos_combinados.resumen.grupos_activos', 0);
        $combinacionDeclarada = $config && in_array($config->decision_combinacion, array_keys(DotacionProceso2027Configuracion::COMBINACIONES), true)
            && ($config->decision_combinacion !== 'combinaciones_configuradas' || $gruposActivos > 0);
        $maximosConfigurados = $config
            && collect($bloques)->every(fn ($bloque) => $bloque['maximo'] !== null);
        $funcionesNormativasDefinidas = $funcionesNormativas->every(fn ($funcion) => $funcion['definida']);
        $necesidadesCubiertas = collect($bloques)->every(fn ($bloque) => $bloque['pendientes'] <= 0.01);
        $topesSuficientes = collect($bloques)->every(fn ($bloque) => ! $bloque['maximo_insuficiente']);
        $asignacionHabilitada = $planesCompletos && $combinacionDeclarada && $funcionesNormativasDefinidas && $maximosConfigurados && $topesSuficientes;
        $horasDisponiblesDocentes = round((float) $docentes->sum('horas_disponibles'), 2);
        $capacidadNoNormativas = min(
            max(0.0, (float) data_get($bloques, 'bloque_1.saldo_maximo', 0)),
            $horasDisponiblesDocentes
        );

        return [
            'aplica' => true,
            'configuracion' => $config,
            'estado_planes' => $estadoPlanes,
            'bloques' => $bloques,
            'need_blocks' => $needBlocks,
            'funciones_normativas' => $funcionesNormativas->values(),
            'docentes' => $docentes->values(),
            'pasos' => [
                'planes' => [
                    'label' => 'Planes de estudio',
                    'completo' => $planesCompletos,
                    'detalle' => $estadoPlanes['detalle'],
                ],
                'combinaciones' => ['label' => 'Combinación de cursos', 'completo' => $combinacionDeclarada],
                'normativas' => ['label' => 'Definición de funciones normativas', 'completo' => $funcionesNormativasDefinidas],
                'maximos' => ['label' => 'Máximos por bloque', 'completo' => $maximosConfigurados && $topesSuficientes],
                'asignacion' => ['label' => 'Asignación obligatoria', 'completo' => $necesidadesCubiertas],
            ],
            'asignacion_habilitada' => $asignacionHabilitada,
            'funciones_no_normativas_habilitadas' => $asignacionHabilitada && $necesidadesCubiertas,
            'capacidad_no_normativas' => round($capacidadNoNormativas, 2),
            'horas_disponibles_docentes' => $horasDisponiblesDocentes,
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    public static function docentesPriorizados(Collection $docentes): Collection
    {
        return $docentes->map(function (array $docente): array {
            $motivo = (string) data_get($docente, 'exclusion_docente.motivo', '');
            $tramo = self::normalizar((string) ($docente['tramo'] ?? ''));
            $planta = max(0.0, (float) ($docente['horas_planta'] ?? 0));
            $contrata = max(0.0, (float) ($docente['horas_contrata'] ?? 0));
            $asignadas = max(0.0, (float) ($docente['horas_asignadas_total'] ?? 0));
            $prioridad = match (true) {
                in_array($motivo, ['horas_gremiales', 'horas_lactancia'], true) => 1,
                $planta > 0 && str_contains($tramo, 'EXPERTO II') => 2,
                $planta > 0 && str_contains($tramo, 'EXPERTO I') => 3,
                $planta > 0 && str_contains($tramo, 'AVANZADO') => 4,
                $planta > 0 => 5,
                default => 6,
            };
            $label = match ($prioridad) {
                1 => '1. Fuero: gremiales o lactancia',
                2 => '2. Titular · Experto II',
                3 => '3. Titular · Experto I',
                4 => '4. Titular · Avanzado',
                5 => '5. Resto titular',
                default => '6. Horas a contrata',
            };

            $docente['prioridad_2027'] = $prioridad;
            $docente['prioridad_2027_label'] = $label;
            $docente['tramo'] = $docente['tramo'] ?? null;
            $docente['fecha_antiguedad'] = $docente['fecha_antiguedad'] ?? null;
            $docente['horas_asignadas_previas'] = $asignadas;
            $docente['horas_disponibles'] = max(0.0, round((float) ($docente['horas_contrato'] ?? 0) - $asignadas, 2));
            $docente['horas_titulares_disponibles'] = max(0.0, round($planta - min($planta, $asignadas), 2));
            $docente['horas_contrata_disponibles'] = max(0.0, round($contrata - max(0.0, $asignadas - $planta), 2));

            return $docente;
        })->sortBy([
            ['prioridad_2027', 'asc'],
            ['fecha_antiguedad', 'asc'],
            ['nombre', 'asc'],
        ])->values();
    }

    /**
     * El plan oficial asociado permite calcular horas, pero no acredita que el
     * establecimiento haya completado su propia configuración. Para avanzar en
     * 2027 se exige una configuración enviada por cada curso con matrícula y
     * las horas completas en los bloques flexibles, especialmente Libre
     * disposición.
     *
     * @return array{completo: bool, total: int, configurados: int, pendientes: array<int, string>, libre_disposicion_pendiente: int, detalle: string}
     */
    private static function estadoPlanesEstudio(Establecimiento $establecimiento, int $anio, array $data): array
    {
        $inyectado = (array) data_get($data, 'cursos.configuracion_planes', []);
        if (array_key_exists('completo', $inyectado)) {
            $total = (int) ($inyectado['total'] ?? data_get($data, 'cursos.totales.cursos', 0));
            $configurados = (int) ($inyectado['configurados'] ?? 0);
            $pendientes = array_values((array) ($inyectado['pendientes'] ?? []));
            $librePendiente = (int) ($inyectado['libre_disposicion_pendiente'] ?? 0);

            return [
                'completo' => (bool) $inyectado['completo'],
                'total' => $total,
                'configurados' => $configurados,
                'pendientes' => $pendientes,
                'libre_disposicion_pendiente' => $librePendiente,
                'detalle' => (string) ($inyectado['detalle'] ?? self::detallePlanes($configurados, $total, $pendientes, $librePendiente)),
            ];
        }

        if (! Schema::hasTable('establecimiento_planes_estudio')
            || ! Schema::hasTable('establecimiento_planes_estudio_asignaturas')
            || ! Schema::hasTable('planes_estudio_bloques')) {
            $total = (int) data_get($data, 'cursos.totales.cursos', 0);
            $sinHoras = (int) data_get($data, 'cursos.totales.sin_horas_plan', 0);
            $completo = $total > 0 && $sinHoras === 0;
            $pendientes = $completo ? [] : ['Cursos sin horas de plan'];

            return [
                'completo' => $completo,
                'total' => $total,
                'configurados' => $completo ? $total : max(0, $total - $sinHoras),
                'pendientes' => $pendientes,
                'libre_disposicion_pendiente' => 0,
                'detalle' => self::detallePlanes($completo ? $total : max(0, $total - $sinHoras), $total, $pendientes, 0),
            ];
        }

        $cursos = EstablecimientoCurso::query()
            ->with(['curso', 'planEstudio.bloques'])
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->where('activo', true)
            ->where('matricula', '>', 0)
            ->orderBy('curso_id')
            ->orderBy('letra')
            ->get();
        $total = $cursos->count();
        if ($total === 0) {
            return [
                'completo' => false,
                'total' => 0,
                'configurados' => 0,
                'pendientes' => ['No hay cursos con matrícula'],
                'libre_disposicion_pendiente' => 0,
                'detalle' => 'No hay cursos con matrícula para configurar.',
            ];
        }

        $configuraciones = DB::table('establecimiento_planes_estudio')
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->whereIn('establecimiento_curso_id', $cursos->pluck('id'))
            ->get(['id', 'establecimiento_curso_id', 'estado'])
            ->keyBy('establecimiento_curso_id');
        $configuracionIds = $configuraciones->pluck('id')->filter()->values();
        $horasPorBloque = [];

        if ($configuracionIds->isNotEmpty()) {
            foreach (DB::table('establecimiento_planes_estudio_asignaturas as detalle')
                ->join('planes_estudio_bloques as bloque', 'bloque.id', '=', 'detalle.plan_estudio_bloque_id')
                ->whereIn('detalle.establecimiento_plan_estudio_id', $configuracionIds)
                ->groupBy('detalle.establecimiento_plan_estudio_id', 'bloque.tipo_bloque')
                ->selectRaw('detalle.establecimiento_plan_estudio_id, bloque.tipo_bloque, SUM(detalle.horas_semanales) as horas')
                ->get() as $detalle) {
                $horasPorBloque[(int) $detalle->establecimiento_plan_estudio_id][(string) $detalle->tipo_bloque] = (float) $detalle->horas;
            }
        }

        $configurados = 0;
        $pendientes = [];
        $librePendiente = 0;
        foreach ($cursos as $curso) {
            $label = trim((string) ($curso->nombre_seccion ?: (($curso->curso?->nombre ?? 'Curso').' '.($curso->letra ?? ''))));
            $plan = $curso->planEstudio;
            $configuracion = $configuraciones->get($curso->id);
            if (! $plan || ! $configuracion || ! in_array((string) $configuracion->estado, self::ESTADOS_PLAN_CONFIGURADO, true)) {
                $pendientes[] = $label;
                continue;
            }

            $requeridosPorBloque = collect($plan->bloques ?? [])
                ->filter(fn ($bloque) => (bool) $bloque->activo
                    && ($bloque->permite_asignaturas_establecimiento || $bloque->permite_asignaturas_personalizadas)
                    && (float) $bloque->horas_semanales > 0)
                ->mapWithKeys(fn ($bloque) => [(string) $bloque->tipo_bloque => (float) $bloque->horas_semanales])
                ->all();
            $libreRequerida = max(0.0, (float) ($plan->horas_semanales_libre_disposicion ?? 0));
            if ($libreRequerida > 0) {
                $requeridosPorBloque['libre_disposicion'] = max(
                    $libreRequerida,
                    (float) ($requeridosPorBloque['libre_disposicion'] ?? 0)
                );
            }

            $bloquesIncompletos = collect($requeridosPorBloque)
                ->filter(fn (float $horasRequeridas, string $tipo) => (float) data_get($horasPorBloque, $configuracion->id.'.'.$tipo, 0) + 0.01 < $horasRequeridas)
                ->keys()
                ->all();
            if ($bloquesIncompletos !== []) {
                $pendientes[] = $label;
                if (in_array('libre_disposicion', $bloquesIncompletos, true)) {
                    $librePendiente++;
                }
                continue;
            }

            $configurados++;
        }

        return [
            'completo' => $configurados === $total,
            'total' => $total,
            'configurados' => $configurados,
            'pendientes' => $pendientes,
            'libre_disposicion_pendiente' => $librePendiente,
            'detalle' => self::detallePlanes($configurados, $total, $pendientes, $librePendiente),
        ];
    }

    /** @param array<int, string> $pendientes */
    private static function detallePlanes(int $configurados, int $total, array $pendientes, int $librePendiente): string
    {
        if ($total === 0) {
            return 'No hay cursos con matrícula para configurar.';
        }
        if ($pendientes === []) {
            return "{$configurados} de {$total} curso(s) con plan enviado y bloques flexibles completos.";
        }

        $detalle = "{$configurados} de {$total} curso(s) listos; ".count($pendientes).' pendiente(s).';
        if ($librePendiente > 0) {
            $detalle .= " {$librePendiente} pendiente(s) incluyen horas de libre disposición.";
        }

        return $detalle;
    }

    private static function configuracion(Establecimiento $establecimiento, int $anio): ?DotacionProceso2027Configuracion
    {
        if (! Schema::hasTable('dotacion_proceso_2027_configuraciones')) {
            return null;
        }

        return DotacionProceso2027Configuracion::query()
            ->where('establecimiento_id', $establecimiento->id)
            ->where('anio', $anio)
            ->first();
    }

    /** @param array<int, bool> $cursosNt */
    private static function bloqueNecesidad(string $groupKey, mixed $item, array $cursosNt): ?string
    {
        if ($groupKey === 'plan_estudio' || $groupKey === 'pie_colaborativo') {
            $cursoId = (int) data_get($item, 'establecimiento_curso_id', 0);

            return ($cursosNt[$cursoId] ?? false) || self::esNt(data_get($item, 'curso'))
                ? 'bloque_2'
                : 'bloque_1';
        }
        if ($groupKey === 'pie_educadora_diferencial') {
            return 'bloque_3';
        }
        if ($groupKey === 'funciones') {
            return (string) data_get($item, 'subtipo_asignacion') === 'pie' ? 'bloque_3' : 'bloque_1';
        }

        return null;
    }

    private static function esNecesidadObligatoria(string $groupKey, mixed $item): bool
    {
        return $groupKey !== 'funciones' || (int) data_get($item, 'dotacion_funcion_id', 0) <= 0;
    }

    private static function esFuncionNormativaDefinible(string $groupKey, mixed $item): bool
    {
        return $groupKey === 'funciones'
            && (bool) data_get($item, 'necesidad_condicionada_por_asignacion_docente', false)
            && trim((string) data_get($item, 'key', '')) !== '';
    }

    public static function bloqueParaAsignacion(object|array $asignacion): ?string
    {
        return match ((string) data_get($asignacion, 'tipo_asignacion')) {
            'plan_estudio', 'pie_colaborativo' => self::esNt(data_get($asignacion, 'establecimientoCurso')) ? 'bloque_2' : 'bloque_1',
            'pie_educadora_diferencial' => 'bloque_3',
            'funcion_tecnico_pedagogica' => DotacionAsignacionCalculator::esAsignacionCoordinacionPie($asignacion) ? 'bloque_3' : 'bloque_1',
            'funcion_directiva', 'plan_normativo', 'otra_funcion' => 'bloque_1',
            default => null,
        };
    }

    private static function esNt(mixed $curso): bool
    {
        return $curso instanceof \App\Models\EstablecimientoCurso
            && DotacionProfesionDocenteResolver::esCursoNt($curso);
    }

    /**
     * Las necesidades de trabajo colaborativo guardan el identificador del curso,
     * no su relación Eloquent. Se conserva este índice desde la estructura ya
     * calculada de cursos para no consultar cada fila y clasificar NT1/NT2 bien.
     *
     * @return array<int, bool>
     */
    private static function cursosNt(array $data): array
    {
        $cursosNt = [];

        foreach ((array) data_get($data, 'cursos.rows', []) as $nivelKey => $nivel) {
            $esNt = in_array((string) $nivelKey, ['NT1', 'NT2'], true);
            foreach ((array) data_get($nivel, 'detalles', []) as $detalle) {
                $cursoId = (int) data_get($detalle, 'establecimiento_curso_id', 0);
                if ($cursoId > 0) {
                    $cursosNt[$cursoId] = $esNt;
                }
            }
        }

        return $cursosNt;
    }

    private static function normalizar(string $value): string
    {
        return Str::of($value)->ascii()->upper()->replaceMatches('/\s+/', ' ')->trim()->toString();
    }

    private static function numero(mixed $value): ?float
    {
        return $value === null ? null : max(0.0, round((float) $value, 2));
    }
}
