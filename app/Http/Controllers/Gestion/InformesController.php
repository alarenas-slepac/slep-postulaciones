<?php

namespace App\Http\Controllers\Gestion;

use App\Http\Controllers\Controller;
use App\Models\Establecimiento;
use App\Models\SolicitudReemplazo;
use App\Support\Rut;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class InformesController extends Controller
{
    private const TIPO_PLANILLA_BRP = 'brp';
    private const TIPO_PLANILLA_DIPRES = 'dipres';
    private const TIPO_PLANILLA_MATRIZ_S_DIPRES = 'matriz_s_dipres';
    private const TIPO_PLANILLA_MATRIZ_C_DIPRES = 'matriz_c_dipres';

    public function index(Request $request)
    {
        $tiposPlanilla = $this->tiposPlanilla();
        $opcionesTiposReemplazo = $this->tiposReemplazoOptions();
        $opcionesTrimestres = $this->trimestresMatrizSOptions();
        $tipoPlanilla = $request->input('tipo_planilla', self::TIPO_PLANILLA_BRP);
        $anioMatrizS = (int) $request->input('matriz_s_anio', 2026);
        $trimestreMatrizS = $request->input('matriz_s_trimestre', '1');
        $rangoMatrizS = $this->rangoTrimestreMatrizS($trimestreMatrizS, $anioMatrizS);
        $isMatrizDipresSeleccionada = $this->esMatrizDipres($tipoPlanilla);
        $filtros = [
            'tipo_planilla' => $tipoPlanilla,
            'fecha_inicio' => $isMatrizDipresSeleccionada ? ($rangoMatrizS['inicio'] ?? null) : $request->input('fecha_inicio'),
            'fecha_termino' => $isMatrizDipresSeleccionada ? ($rangoMatrizS['termino'] ?? null) : $request->input('fecha_termino'),
            'tipos_reemplazo' => array_values(array_filter((array) $request->input('tipos_reemplazo', []), fn ($v) => is_string($v) && trim($v) !== '')),
            'matriz_s_trimestre' => $trimestreMatrizS,
            'matriz_s_anio' => $anioMatrizS ?: 2026,
        ];

        $searched = $request->boolean('buscar');
        $rows = collect();

        if ($searched) {
            $validated = $this->validateFilters($request);
            $isMatrizS = $validated['tipo_planilla'] === self::TIPO_PLANILLA_MATRIZ_S_DIPRES;
            $isMatrizC = $validated['tipo_planilla'] === self::TIPO_PLANILLA_MATRIZ_C_DIPRES;
            $filtros = [
                'tipo_planilla' => $validated['tipo_planilla'],
                'fecha_inicio' => $validated['fecha_inicio'],
                'fecha_termino' => $validated['fecha_termino'],
                'tipos_reemplazo' => array_values($validated['tipos_reemplazo'] ?? []),
                'matriz_s_trimestre' => $validated['matriz_s_trimestre'] ?? '1',
                'matriz_s_anio' => $validated['matriz_s_anio'] ?? 2026,
            ];

            $rows = match (true) {
                $isMatrizS => $this->buildMatrizSRows($validated['fecha_inicio'], $validated['fecha_termino']),
                $isMatrizC => $this->buildMatrizCRows($validated['fecha_inicio'], $validated['fecha_termino']),
                default => $this->buildRows(
                    $validated['fecha_inicio'],
                    $validated['fecha_termino'],
                    $validated['tipo_planilla'] === self::TIPO_PLANILLA_DIPRES ? $filtros['tipos_reemplazo'] : []
                ),
            };
        }

        return view('gestion.informes.index', [
            'tiposPlanilla' => $tiposPlanilla,
            'opcionesTiposReemplazo' => $opcionesTiposReemplazo,
            'opcionesTrimestres' => $opcionesTrimestres,
            'filtros' => $filtros,
            'searched' => $searched,
            'rows' => $rows,
            'totalRows' => $rows->count(),
            'isDipres' => ($filtros['tipo_planilla'] ?? self::TIPO_PLANILLA_BRP) === self::TIPO_PLANILLA_DIPRES,
            'isMatrizS' => ($filtros['tipo_planilla'] ?? self::TIPO_PLANILLA_BRP) === self::TIPO_PLANILLA_MATRIZ_S_DIPRES,
            'isMatrizC' => ($filtros['tipo_planilla'] ?? self::TIPO_PLANILLA_BRP) === self::TIPO_PLANILLA_MATRIZ_C_DIPRES,
            'isMatrizDipres' => $this->esMatrizDipres($filtros['tipo_planilla'] ?? self::TIPO_PLANILLA_BRP),
            'matrizSRango' => $this->rangoTrimestreMatrizS($filtros['matriz_s_trimestre'] ?? '1', (int) ($filtros['matriz_s_anio'] ?? 2026)),
            'matrizCRangosCese' => $this->rangosCeseMatrizC($filtros['matriz_s_trimestre'] ?? '1', (int) ($filtros['matriz_s_anio'] ?? 2026)),
            'selectedPlanillaLabel' => $tiposPlanilla[$filtros['tipo_planilla'] ?? self::TIPO_PLANILLA_BRP] ?? 'Planilla BRP',
        ]);
    }

    public function export(Request $request)
    {
        $validated = $this->validateFilters($request);
        $tiposSeleccionados = array_values($validated['tipos_reemplazo'] ?? []);
        $isDipres = $validated['tipo_planilla'] === self::TIPO_PLANILLA_DIPRES;

        if ($validated['tipo_planilla'] === self::TIPO_PLANILLA_MATRIZ_C_DIPRES) {
            $rows = $this->buildMatrizCRows($validated['fecha_inicio'], $validated['fecha_termino']);

            if ($rows->isEmpty()) {
                return redirect()
                    ->route('gestion.informes.index', [
                        'buscar' => 1,
                        'tipo_planilla' => $validated['tipo_planilla'],
                        'matriz_s_trimestre' => $validated['matriz_s_trimestre'] ?? '1',
                        'matriz_s_anio' => $validated['matriz_s_anio'] ?? 2026,
                    ])
                    ->with('warning', 'No existen ceses de reemplazo para exportar en el trimestre seleccionado.');
            }

            $filename = sprintf(
                'matriz-c-dipres-T%s-%s.xls',
                $validated['matriz_s_trimestre'] ?? '1',
                $validated['matriz_s_anio'] ?? 2026
            );

            return response()
                ->view('gestion.informes.exports.matriz_c_dipres', [
                    'rows' => $rows,
                    'columns' => $this->matrizCColumns(),
                    'fechaInicio' => $validated['fecha_inicio'],
                    'fechaTermino' => $validated['fecha_termino'],
                    'trimestre' => $validated['matriz_s_trimestre'] ?? '1',
                    'anio' => $validated['matriz_s_anio'] ?? 2026,
                    'trimestreLabel' => $this->trimestresMatrizSOptions()[$validated['matriz_s_trimestre'] ?? '1'] ?? '1er Trimestre',
                    'rangosCese' => $this->rangosCeseMatrizC($validated['matriz_s_trimestre'] ?? '1', (int) ($validated['matriz_s_anio'] ?? 2026)),
                    'generatedAt' => now(),
                ])
                ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Cache-Control', 'max-age=0, no-cache, no-store, must-revalidate');
        }

        if ($validated['tipo_planilla'] === self::TIPO_PLANILLA_MATRIZ_S_DIPRES) {
            $rows = $this->buildMatrizSRows($validated['fecha_inicio'], $validated['fecha_termino']);

            if ($rows->isEmpty()) {
                return redirect()
                    ->route('gestion.informes.index', [
                        'buscar' => 1,
                        'tipo_planilla' => $validated['tipo_planilla'],
                        'matriz_s_trimestre' => $validated['matriz_s_trimestre'] ?? '1',
                        'matriz_s_anio' => $validated['matriz_s_anio'] ?? 2026,
                    ])
                    ->with('warning', 'No existen cadenas de reemplazo para exportar en el trimestre seleccionado.');
            }

            $filename = sprintf(
                'matriz-s-dipres-T%s-%s.xls',
                $validated['matriz_s_trimestre'] ?? '1',
                $validated['matriz_s_anio'] ?? 2026
            );

            return response()
                ->view('gestion.informes.exports.matriz_s_dipres', [
                    'rows' => $rows,
                    'columns' => $this->matrizSColumns(),
                    'fechaInicio' => $validated['fecha_inicio'],
                    'fechaTermino' => $validated['fecha_termino'],
                    'trimestre' => $validated['matriz_s_trimestre'] ?? '1',
                    'anio' => $validated['matriz_s_anio'] ?? 2026,
                    'trimestreLabel' => $this->trimestresMatrizSOptions()[$validated['matriz_s_trimestre'] ?? '1'] ?? '1er Trimestre',
                    'generatedAt' => now(),
                ])
                ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                ->header('Cache-Control', 'max-age=0, no-cache, no-store, must-revalidate');
        }

        $rows = $this->buildRows(
            $validated['fecha_inicio'],
            $validated['fecha_termino'],
            $isDipres ? $tiposSeleccionados : []
        );

        if ($rows->isEmpty()) {
            return redirect()
                ->route('gestion.informes.index', [
                    'buscar' => 1,
                    'tipo_planilla' => $validated['tipo_planilla'],
                    'fecha_inicio' => $validated['fecha_inicio'],
                    'fecha_termino' => $validated['fecha_termino'],
                    'matriz_s_trimestre' => $validated['matriz_s_trimestre'] ?? null,
                    'matriz_s_anio' => $validated['matriz_s_anio'] ?? null,
                    'tipos_reemplazo' => $tiposSeleccionados,
                ])
                ->with('warning', 'No existen registros para exportar con el criterio seleccionado.');
        }

        $filename = sprintf(
            '%s-%s-a-%s.xls',
            $isDipres ? 'informe-reemplazo-maternidad-dipres' : 'planilla-brp',
            str_replace('-', '', $validated['fecha_inicio']),
            str_replace('-', '', $validated['fecha_termino'])
        );

        $view = $isDipres
            ? 'gestion.informes.exports.informe_reemplazo_maternidad_dipres'
            : 'gestion.informes.exports.planilla_brp';

        return response()
            ->view($view, [
                'rows' => $rows,
                'fechaInicio' => $validated['fecha_inicio'],
                'fechaTermino' => $validated['fecha_termino'],
                'generatedAt' => now(),
                'tiposSeleccionados' => $tiposSeleccionados,
            ])
            ->header('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'max-age=0, no-cache, no-store, must-revalidate');
    }

    protected function validateFilters(Request $request): array
    {
        $esMatrizDipresRequest = $this->esMatrizDipres($request->input('tipo_planilla'));

        $validated = $request->validate([
            'tipo_planilla' => ['required', Rule::in(array_keys($this->tiposPlanilla()))],
            'fecha_inicio' => [Rule::requiredIf(!$esMatrizDipresRequest), 'nullable', 'date'],
            'fecha_termino' => [Rule::requiredIf(!$esMatrizDipresRequest), 'nullable', 'date', 'after_or_equal:fecha_inicio'],
            'tipos_reemplazo' => ['required_if:tipo_planilla,' . self::TIPO_PLANILLA_DIPRES, 'array', 'min:1'],
            'tipos_reemplazo.*' => ['string', Rule::in($this->tiposReemplazoOptions())],
            'matriz_s_trimestre' => [Rule::requiredIf($esMatrizDipresRequest), Rule::in(array_keys($this->trimestresMatrizSOptions()))],
            'matriz_s_anio' => [Rule::requiredIf($esMatrizDipresRequest), 'integer', 'between:2020,2100'],
        ], [
            'tipo_planilla.required' => 'Debes seleccionar un tipo de planilla.',
            'tipo_planilla.in' => 'El tipo de planilla seleccionado no es válido.',
            'fecha_inicio.required' => 'Debes ingresar la fecha de inicio.',
            'fecha_termino.required' => 'Debes ingresar la fecha de término.',
            'fecha_termino.after_or_equal' => 'La fecha de término debe ser igual o posterior a la fecha de inicio.',
            'tipos_reemplazo.required_if' => 'Debes seleccionar al menos un tipo de reemplazo para el informe DIPRES.',
            'tipos_reemplazo.array' => 'La selección de tipos de reemplazo no es válida.',
            'tipos_reemplazo.min' => 'Debes seleccionar al menos un tipo de reemplazo para el informe DIPRES.',
            'tipos_reemplazo.*.in' => 'Uno de los tipos de reemplazo seleccionados no es válido.',
            'matriz_s_trimestre.required' => 'Debes seleccionar el trimestre para la matriz DIPRES.',
            'matriz_s_trimestre.in' => 'El trimestre seleccionado no es válido.',
            'matriz_s_anio.required' => 'Debes ingresar el año para la matriz DIPRES.',
            'matriz_s_anio.integer' => 'El año debe ser numérico.',
            'matriz_s_anio.between' => 'El año seleccionado no es válido.',
        ]);

        if ($this->esMatrizDipres($validated['tipo_planilla'] ?? null)) {
            $rango = $this->rangoTrimestreMatrizS($validated['matriz_s_trimestre'] ?? '1', (int) ($validated['matriz_s_anio'] ?? 2026));
            $validated['fecha_inicio'] = $rango['inicio'];
            $validated['fecha_termino'] = $rango['termino'];
            $validated['tipos_reemplazo'] = [];
        }

        return $validated;
    }

    protected function buildMatrizSRows(string $fechaInicio, string $fechaTermino): Collection
    {
        $inicioPeriodo = Carbon::parse($fechaInicio)->startOfDay();
        $terminoPeriodo = Carbon::parse($fechaTermino)->endOfDay();

        $solicitudes = SolicitudReemplazo::query()
            ->with([
                'postulante.user:id,rut,nombres,apellido_paterno,apellido_materno,email',
                'postulante.areaDesempeno:id,nombre,estamento',
                'contratoPostulante.user:id,rut,nombres,apellido_paterno,apellido_materno,email',
                'contratoPostulante.areaDesempeno:id,nombre,estamento',
                'establecimiento:id,rbd,nombre_establecimiento,comuna,sala_cuna',
                'funcionarioTitular:id,rut,nombre,estatuto',
                'jornadas:id,solicitud_reemplazo_id,reemplazo_total',
            ])
            ->whereIn('estado', ['aceptada', 'cerrado', 'cerrada'])
            ->whereNotNull('fecha_inicio_trabajo')
            ->whereNotNull('fecha_termino')
            ->orderBy('fecha_inicio_trabajo')
            ->orderBy('fecha_termino')
            ->orderBy('id')
            ->get([
                'id',
                'estado',
                'tipo_reemplazo',
                'tipo_reemplazo_otro',
                'fecha_inicio_trabajo',
                'fecha_termino',
                'postulant_profile_id',
                'contrato_trabajo_postulant_profile_id',
                'establecimiento_id',
                'reemplazo_personal_id',
                'rut_titular_normalizado',
                'rut_reemplazo_normalizado',
                'solicitud_anterior_id',
                'observaciones',
            ]);

        if ($solicitudes->isEmpty()) {
            return collect();
        }

        $ids = $solicitudes->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $parent = array_combine($ids, $ids);
        $byId = $solicitudes->keyBy('id');

        $find = function (int $id) use (&$parent, &$find): int {
            if (!isset($parent[$id])) {
                $parent[$id] = $id;
            }

            if ($parent[$id] !== $id) {
                $parent[$id] = $find($parent[$id]);
            }

            return $parent[$id];
        };

        $union = function (int $a, int $b) use (&$parent, $find): void {
            $ra = $find($a);
            $rb = $find($b);

            if ($ra !== $rb) {
                $parent[$rb] = $ra;
            }
        };

        foreach ($solicitudes as $solicitud) {
            $anteriorId = (int) ($solicitud->solicitud_anterior_id ?? 0);
            if ($anteriorId > 0 && $byId->has($anteriorId)) {
                $union((int) $solicitud->id, $anteriorId);
            }
        }

        $porLlave = $solicitudes
            ->groupBy(function (SolicitudReemplazo $solicitud) {
                return implode('|', [
                    $this->postulanteContinuidadId($solicitud),
                    $this->rutTitularNormalizado($solicitud),
                ]);
            })
            ->filter(function (Collection $grupo, string $llave) {
                return !str_starts_with($llave, '|') && !str_ends_with($llave, '|');
            });

        foreach ($porLlave as $grupo) {
            $ordenadas = $grupo
                ->sortBy([
                    ['fecha_inicio_trabajo', 'asc'],
                    ['fecha_termino', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();

            $anterior = null;
            foreach ($ordenadas as $actual) {
                if ($anterior && $this->fechasSonContinuas($anterior, $actual)) {
                    $union((int) $anterior->id, (int) $actual->id);
                }
                $anterior = $actual;
            }
        }

        $anioPeriodo = (int) $inicioPeriodo->format('Y');
        $ingresoServicioPorPostulante = $solicitudes
            ->filter(function (SolicitudReemplazo $solicitud) use ($anioPeriodo): bool {
                if (!$solicitud->fecha_inicio_trabajo) {
                    return false;
                }

                return Carbon::parse($solicitud->fecha_inicio_trabajo)->year === $anioPeriodo;
            })
            ->groupBy(fn (SolicitudReemplazo $solicitud) => $this->postulanteContinuidadId($solicitud))
            ->filter(fn (Collection $grupo, string $postulanteId): bool => trim($postulanteId) !== '')
            ->map(function (Collection $grupo): string {
                $fecha = $grupo
                    ->pluck('fecha_inicio_trabajo')
                    ->filter()
                    ->map(fn ($fecha) => Carbon::parse($fecha)->startOfDay())
                    ->sort()
                    ->first();

                return $fecha ? $fecha->format('d-m-Y') : '';
            });

        $cadenas = $solicitudes->groupBy(fn (SolicitudReemplazo $solicitud) => $find((int) $solicitud->id));

        return $cadenas
            ->map(function (Collection $cadena) use ($inicioPeriodo, $terminoPeriodo, $ingresoServicioPorPostulante) {
                $ordenada = $cadena
                    ->sortBy([
                        ['fecha_inicio_trabajo', 'asc'],
                        ['fecha_termino', 'asc'],
                        ['id', 'asc'],
                    ])
                    ->values();

                $primera = $ordenada->first();
                $ultima = $ordenada
                    ->sortByDesc(fn (SolicitudReemplazo $solicitud) => sprintf('%s-%010d', optional($solicitud->fecha_termino)->format('Ymd') ?? '00000000', (int) $solicitud->id))
                    ->first();

                if (!$primera || !$ultima || !$primera->fecha_inicio_trabajo || !$ultima->fecha_termino) {
                    return null;
                }

                $inicioCadena = Carbon::parse($primera->fecha_inicio_trabajo)->startOfDay();
                $terminoCadena = Carbon::parse($ultima->fecha_termino)->endOfDay();

                if ($inicioCadena->gt($terminoPeriodo) || $terminoCadena->lt($terminoPeriodo)) {
                    return null;
                }

                $postulante = $ultima->contratoPostulante ?: $ultima->postulante;
                $user = $postulante?->user;
                $establecimiento = $ultima->establecimiento;
                $funcionario = $ultima->funcionarioTitular;
                $jornada = (float) $ultima->jornadas->sum(function ($jornada) {
                    return (float) ($jornada->reemplazo_total ?? 0);
                });

                $rutTitularPartes = $this->splitRutDipres($funcionario?->rut ?? $ultima->rut_titular_normalizado);
                $rutReemplazoPartes = $this->splitRutDipres($user?->rut ?? $ultima->rut_reemplazo_normalizado);
                $fechaNacimiento = $postulante?->fecha_nacimiento;
                $edad = $fechaNacimiento ? Carbon::parse($fechaNacimiento)->age : '';
                $sexo = strtoupper(trim((string) ($postulante?->genero ?? '')));
                $estamento = strtoupper(trim((string) ($postulante?->estamento ?? $postulante?->areaDesempeno?->estamento ?? '')));
                $descNivel = '';
                $educacion = trim((string) ($postulante?->nivel_estudios ?? ''));
                $titulo = trim((string) ($postulante?->institucion_titulo ?? ''));
                $areaDesempenoNombre = trim((string) ($postulante?->areaDesempeno?->nombre ?? ''));
                $especialidad = trim(collect([
                    $postulante?->mencion,
                    $postulante?->especialidad_tp,
                    $areaDesempenoNombre,
                ])->filter()->unique()->implode(' / '));
                $tipoReemplazo = (string) ($ultima->tipo_reemplazo ?: $ultima->tipo_reemplazo_otro ?: '');
                $rbdEstablecimiento = trim((string) ($establecimiento?->rbd ?? ''));
                $ingresoServicio = (string) ($ingresoServicioPorPostulante->get($this->postulanteContinuidadId($ultima), '') ?: '');
                $sistRem = $this->sistemaRemuneracionMatrizS($estamento, $establecimiento, $rbdEstablecimiento);
                $jornadaEntera = $jornada > 0 ? (string) (int) round($jornada, 0) : '';
                $observacionContinuidad = $ordenada->count() > 1
                    ? 'Continuidad reconstruida con solicitudes: ' . $ordenada->pluck('id')->implode(', ')
                    : 'Solicitud sin continuidad asociada.';

                $datosFaltantes = $this->missingMatrizSFields([
                    'RUN_TIT' => $rutTitularPartes['run'],
                    'DV_TIT' => $rutTitularPartes['dv'],
                    'RUN' => $rutReemplazoPartes['run'],
                    'DV' => $rutReemplazoPartes['dv'],
                    'APELLIDO_1' => trim((string) ($user?->apellido_paterno ?? '')),
                    'NOMBRES' => trim((string) ($user?->nombres ?? '')),
                    'FECHA_NAC' => $fechaNacimiento ? Carbon::parse($fechaNacimiento)->format('d-m-Y') : '',
                    'SEXO' => $sexo,
                    'INGRESO_SERV' => $ingresoServicio,
                    'PREVISION' => trim((string) ($postulante?->prevision_afp ?? '')),
                    'SALUD' => trim((string) ($postulante?->salud_institucion ?? '')),
                    'ESTAMENTO' => $estamento,
                    'JORNADA' => $jornadaEntera,
                    'INICIO_NOM' => $inicioCadena->format('d-m-Y'),
                    'TERMINO_NOM' => $terminoCadena->format('d-m-Y'),
                    'ESTAB' => $rbdEstablecimiento,
                ]);

                $observaciones = $observacionContinuidad;
                if (!empty($datosFaltantes)) {
                    $observaciones .= ' Datos faltantes: ' . implode(', ', $datosFaltantes) . '.';
                }

                return [
                    'RUN_TIT' => $rutTitularPartes['run'],
                    'DV_TIT' => $rutTitularPartes['dv'],
                    'CAUSAL' => $tipoReemplazo,
                    'RUN' => $rutReemplazoPartes['run'],
                    'DV' => $rutReemplazoPartes['dv'],
                    'APELLIDO_1' => trim((string) ($user?->apellido_paterno ?? '')),
                    'APELLIDO_2' => trim((string) ($user?->apellido_materno ?? '')),
                    'NOMBRES' => trim((string) ($user?->nombres ?? '')),
                    'FECHA_NAC' => $fechaNacimiento ? Carbon::parse($fechaNacimiento)->format('d-m-Y') : '',
                    'EDAD' => $edad,
                    'SEXO' => $sexo,
                    'INGRESO_SERV' => $ingresoServicio,
                    'ANTIG_SERV' => '',
                    'ANTIG_AP' => '',
                    'PREVISION' => trim((string) ($postulante?->prevision_afp ?? '')),
                    'SALUD' => trim((string) ($postulante?->salud_institucion ?? '')),
                    'SIST_REM' => $sistRem,
                    'REGION' => '',
                    'ESTAMENTO' => $estamento,
                    'GRADO' => '',
                    'DESC_NIVEL' => '',
                    'C_DESEMPEÑO' => '',
                    'JORNADA' => $jornadaEntera,
                    'FUNCION_DIRECTIVA' => '',
                    'INICIO_NOM' => $inicioCadena->format('d-m-Y'),
                    'TERMINO_NOM' => $terminoCadena->format('d-m-Y'),
                    'ASIGPROF' => '',
                    'ZONA' => '',
                    'BI' => '',
                    'TRI' => '',
                    'PAIS' => trim((string) ($postulante?->nacionalidad ?? '')),
                    'EDU' => $educacion,
                    'TITULO' => $titulo,
                    'OTROS_EDU' => '',
                    'ESPECIALIDAD' => $especialidad,
                    'UNIDAD' => '',
                    'ESTAB' => $rbdEstablecimiento,
                    'RENTA' => '',
                    'MODALIDAD' => '',
                    'PORCENTAJE' => '',
                    'ORIGEN' => '',
                    'CR' => 'SCR',
                    'OBSERVACIONES' => $observaciones,
                    'solicitud_final_id' => $ultima->id,
                    'solicitudes_ids' => $ordenada->pluck('id')->values()->all(),
                    'cantidad_solicitudes' => $ordenada->count(),
                    'rut_reemplazo' => $this->formatRutChile($user?->rut ?? $ultima->rut_reemplazo_normalizado),
                    'nombre_completo_reemplazo' => trim((string) ($user?->nombre_completo ?? $user?->full_name ?? $user?->email ?? '')),
                    'rut_titular' => $this->formatRutChile($funcionario?->rut ?? $ultima->rut_titular_normalizado),
                    'nombre_titular' => trim((string) ($funcionario?->nombre ?? '')),
                    'rbd_establecimiento' => (string) ($establecimiento?->rbd ?? ''),
                    'nombre_establecimiento' => (string) ($establecimiento?->nombre_establecimiento ?? ''),
                    'comuna' => (string) ($establecimiento?->comuna ?? ''),
                    'tipo_reemplazo' => $tipoReemplazo,
                    'fecha_inicio_cadena' => $inicioCadena->format('d-m-Y'),
                    'fecha_termino_cadena' => $terminoCadena->format('d-m-Y'),
                    'jornada' => $jornadaEntera,
                    'estado' => (string) $ultima->estado,
                    'observaciones' => $observaciones,
                    'datos_faltantes' => $datosFaltantes,
                    'cantidad_datos_faltantes' => count($datosFaltantes),
                    'tiene_datos_faltantes' => !empty($datosFaltantes),
                ];
            })
            ->filter()
            ->sortBy([
                ['fecha_inicio_cadena', 'asc'],
                ['nombre_establecimiento', 'asc'],
                ['nombre_completo_reemplazo', 'asc'],
            ])
            ->values();
    }

    protected function postulanteContinuidadId(SolicitudReemplazo $solicitud): string
    {
        return (string) ($solicitud->postulant_profile_id ?: $solicitud->contrato_trabajo_postulant_profile_id ?: '');
    }

    protected function rutTitularNormalizado(SolicitudReemplazo $solicitud): string
    {
        return (string) (Rut::normalize($solicitud->funcionarioTitular?->rut ?? $solicitud->rut_titular_normalizado) ?? '');
    }

    protected function fechasSonContinuas(SolicitudReemplazo $anterior, SolicitudReemplazo $actual): bool
    {
        if (!$anterior->fecha_termino || !$actual->fecha_inicio_trabajo) {
            return false;
        }

        $terminoAnterior = Carbon::parse($anterior->fecha_termino)->startOfDay();
        $inicioActual = Carbon::parse($actual->fecha_inicio_trabajo)->startOfDay();
        $diferencia = $terminoAnterior->diffInDays($inicioActual, false);

        return $diferencia >= 0 && $diferencia <= 1;
    }


    protected function buildMatrizCRows(string $fechaInicio, string $fechaTermino): Collection
    {
        $inicioPeriodo = Carbon::parse($fechaInicio)->startOfDay();
        $terminoPeriodo = Carbon::parse($fechaTermino)->startOfDay();
        $diaAnteriorTerminoPeriodo = $terminoPeriodo->copy()->subDay();
        $ultimoDiaTrimestreAnterior = $inicioPeriodo->copy()->subDay();

        $solicitudes = SolicitudReemplazo::query()
            ->with([
                'postulante.user:id,rut,nombres,apellido_paterno,apellido_materno,email',
                'postulante.areaDesempeno:id,nombre,estamento',
                'contratoPostulante.user:id,rut,nombres,apellido_paterno,apellido_materno,email',
                'contratoPostulante.areaDesempeno:id,nombre,estamento',
                'establecimiento:id,rbd,nombre_establecimiento,comuna,sala_cuna',
                'funcionarioTitular:id,rut,nombre,estatuto',
                'jornadas:id,solicitud_reemplazo_id,reemplazo_total',
            ])
            ->whereIn('estado', ['aceptada', 'cerrado', 'cerrada'])
            ->whereNotNull('fecha_inicio_trabajo')
            ->whereNotNull('fecha_termino')
            ->orderBy('fecha_inicio_trabajo')
            ->orderBy('fecha_termino')
            ->orderBy('id')
            ->get([
                'id',
                'estado',
                'tipo_reemplazo',
                'tipo_reemplazo_otro',
                'fecha_inicio_trabajo',
                'fecha_termino',
                'postulant_profile_id',
                'contrato_trabajo_postulant_profile_id',
                'establecimiento_id',
                'reemplazo_personal_id',
                'rut_titular_normalizado',
                'rut_reemplazo_normalizado',
                'solicitud_anterior_id',
                'observaciones',
            ]);

        if ($solicitudes->isEmpty()) {
            return collect();
        }

        $ids = $solicitudes->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $parent = array_combine($ids, $ids);
        $byId = $solicitudes->keyBy('id');

        $find = function (int $id) use (&$parent, &$find): int {
            if (!isset($parent[$id])) {
                $parent[$id] = $id;
            }

            if ($parent[$id] !== $id) {
                $parent[$id] = $find($parent[$id]);
            }

            return $parent[$id];
        };

        $union = function (int $a, int $b) use (&$parent, $find): void {
            $ra = $find($a);
            $rb = $find($b);

            if ($ra !== $rb) {
                $parent[$rb] = $ra;
            }
        };

        foreach ($solicitudes as $solicitud) {
            $anteriorId = (int) ($solicitud->solicitud_anterior_id ?? 0);
            if ($anteriorId > 0 && $byId->has($anteriorId)) {
                $union((int) $solicitud->id, $anteriorId);
            }
        }

        $porLlave = $solicitudes
            ->groupBy(function (SolicitudReemplazo $solicitud) {
                return implode('|', [
                    $this->postulanteContinuidadId($solicitud),
                    $this->rutTitularNormalizado($solicitud),
                ]);
            })
            ->filter(function (Collection $grupo, string $llave) {
                return !str_starts_with($llave, '|') && !str_ends_with($llave, '|');
            });

        foreach ($porLlave as $grupo) {
            $ordenadas = $grupo
                ->sortBy([
                    ['fecha_inicio_trabajo', 'asc'],
                    ['fecha_termino', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();

            $anterior = null;
            foreach ($ordenadas as $actual) {
                if ($anterior && $this->fechasSonContinuas($anterior, $actual)) {
                    $union((int) $anterior->id, (int) $actual->id);
                }
                $anterior = $actual;
            }
        }

        $anioPeriodo = (int) $inicioPeriodo->format('Y');
        $ingresoServicioPorPostulante = $solicitudes
            ->filter(function (SolicitudReemplazo $solicitud) use ($anioPeriodo): bool {
                if (!$solicitud->fecha_inicio_trabajo) {
                    return false;
                }

                return Carbon::parse($solicitud->fecha_inicio_trabajo)->year === $anioPeriodo;
            })
            ->groupBy(fn (SolicitudReemplazo $solicitud) => $this->postulanteContinuidadId($solicitud))
            ->filter(fn (Collection $grupo, string $postulanteId): bool => trim($postulanteId) !== '')
            ->map(function (Collection $grupo): string {
                $fecha = $grupo
                    ->pluck('fecha_inicio_trabajo')
                    ->filter()
                    ->map(fn ($fecha) => Carbon::parse($fecha)->startOfDay())
                    ->sort()
                    ->first();

                return $fecha ? $fecha->format('d-m-Y') : '';
            });

        $cadenas = $solicitudes->groupBy(fn (SolicitudReemplazo $solicitud) => $find((int) $solicitud->id));

        return $cadenas
            ->map(function (Collection $cadena) use ($inicioPeriodo, $terminoPeriodo, $diaAnteriorTerminoPeriodo, $ultimoDiaTrimestreAnterior, $ingresoServicioPorPostulante) {
                $ordenada = $cadena
                    ->sortBy([
                        ['fecha_inicio_trabajo', 'asc'],
                        ['fecha_termino', 'asc'],
                        ['id', 'asc'],
                    ])
                    ->values();

                $primera = $ordenada->first();
                $ultima = $ordenada
                    ->sortByDesc(fn (SolicitudReemplazo $solicitud) => sprintf('%s-%010d', optional($solicitud->fecha_termino)->format('Ymd') ?? '00000000', (int) $solicitud->id))
                    ->first();

                if (!$primera || !$ultima || !$primera->fecha_inicio_trabajo || !$ultima->fecha_termino) {
                    return null;
                }

                $inicioCadena = Carbon::parse($primera->fecha_inicio_trabajo)->startOfDay();
                $terminoCadena = Carbon::parse($ultima->fecha_termino)->startOfDay();

                $terminaDuranteTrimestreAntesDelCierre = $inicioCadena->lte($terminoPeriodo)
                    && $terminoCadena->gte($inicioPeriodo)
                    && $terminoCadena->lt($terminoPeriodo);
                $terminaUltimoDiaTrimestreAnteriorSinContinuidad = $terminoCadena->equalTo($ultimoDiaTrimestreAnterior);

                if (!$terminaDuranteTrimestreAntesDelCierre && !$terminaUltimoDiaTrimestreAnteriorSinContinuidad) {
                    return null;
                }

                $motivoCese = $terminaDuranteTrimestreAntesDelCierre
                    ? 'Cese dentro del trimestre seleccionado antes del cierre trimestral.'
                    : 'Cese el ultimo dia del trimestre anterior sin continuidad posterior.';

                $fechaAlejamiento = $terminoCadena->copy()->addDay();

                $postulante = $ultima->contratoPostulante ?: $ultima->postulante;
                $user = $postulante?->user;
                $establecimiento = $ultima->establecimiento;
                $funcionario = $ultima->funcionarioTitular;
                $jornada = (float) $ultima->jornadas->sum(function ($jornada) {
                    return (float) ($jornada->reemplazo_total ?? 0);
                });

                $rutReemplazoPartes = $this->splitRutDipres($user?->rut ?? $ultima->rut_reemplazo_normalizado);
                $fechaNacimiento = $postulante?->fecha_nacimiento;
                $edad = $fechaNacimiento ? Carbon::parse($fechaNacimiento)->age : '';
                $sexo = strtoupper(trim((string) ($postulante?->genero ?? '')));
                $estamento = strtoupper(trim((string) ($postulante?->estamento ?? $postulante?->areaDesempeno?->estamento ?? '')));
                $tipoReemplazo = (string) ($ultima->tipo_reemplazo ?: $ultima->tipo_reemplazo_otro ?: '');
                $rbdEstablecimiento = trim((string) ($establecimiento?->rbd ?? ''));
                $ingresoServicio = (string) ($ingresoServicioPorPostulante->get($this->postulanteContinuidadId($ultima), '') ?: '');
                $sistRem = $this->sistemaRemuneracionMatrizS($estamento, $establecimiento, $rbdEstablecimiento);
                $jornadaEntera = $jornada > 0 ? (string) (int) round($jornada, 0) : '';
                $observacionContinuidad = $ordenada->count() > 1
                    ? 'Continuidad reconstruida con solicitudes: ' . $ordenada->pluck('id')->implode(', ')
                    : 'Solicitud sin continuidad asociada.';

                $datosFaltantes = $this->missingMatrizSFields([
                    'RUN' => $rutReemplazoPartes['run'],
                    'DV' => $rutReemplazoPartes['dv'],
                    'APELLIDO_1' => trim((string) ($user?->apellido_paterno ?? '')),
                    'NOMBRES' => trim((string) ($user?->nombres ?? '')),
                    'FECHA_NAC' => $fechaNacimiento ? Carbon::parse($fechaNacimiento)->format('d-m-Y') : '',
                    'SEXO' => $sexo,
                    'INGRESO_SERV' => $ingresoServicio,
                    'PREVISION' => trim((string) ($postulante?->prevision_afp ?? '')),
                    'SALUD' => trim((string) ($postulante?->salud_institucion ?? '')),
                    'ESTAMENTO' => $estamento,
                    'JORNADA' => $jornadaEntera,
                    'INICIO_NOM' => $inicioCadena->format('d-m-Y'),
                    'TERMINO_NOM' => $terminoCadena->format('d-m-Y'),
                    'FECHA_ALEJAMIENTO' => $fechaAlejamiento->format('d-m-Y'),
                    'ESTAB' => $rbdEstablecimiento,
                ]);

                $observaciones = $motivoCese . ' ' . $observacionContinuidad;
                if (!empty($datosFaltantes)) {
                    $observaciones .= ' Datos faltantes: ' . implode(', ', $datosFaltantes) . '.';
                }

                return [
                    'TIPO_INFO' => '',
                    'ID_SERV' => '',
                    'RUN' => $rutReemplazoPartes['run'],
                    'DV' => $rutReemplazoPartes['dv'],
                    'APELLIDO_1' => trim((string) ($user?->apellido_paterno ?? '')),
                    'APELLIDO_2' => trim((string) ($user?->apellido_materno ?? '')),
                    'NOMBRES' => trim((string) ($user?->nombres ?? '')),
                    'FECHA_NAC' => $fechaNacimiento ? Carbon::parse($fechaNacimiento)->format('d-m-Y') : '',
                    'EDAD' => $edad,
                    'SEXO' => $sexo,
                    'INGRESO_SERV' => $ingresoServicio,
                    'ANTIG_SERV' => '',
                    'PREVISION' => trim((string) ($postulante?->prevision_afp ?? '')),
                    'SALUD' => trim((string) ($postulante?->salud_institucion ?? '')),
                    'SIST_REM' => $sistRem,
                    'REGION' => '',
                    'DOTACION' => '',
                    'ESTAMENTO' => $estamento,
                    'GRADO' => '',
                    'DESC_NIVEL' => '',
                    'C_JURIDICA' => '',
                    'C_DESEMPENO' => '',
                    'SUBT' => '',
                    'RENTA' => '',
                    'JORNADA' => $jornadaEntera,
                    'FUNCION_DIRECTIVA' => '',
                    'INICIO_NOM' => $inicioCadena->format('d-m-Y'),
                    'TERMINO_NOM' => $terminoCadena->format('d-m-Y'),
                    'FECHA_ALEJAMIENTO' => $fechaAlejamiento->format('d-m-Y'),
                    'CAUSAL_ALEJAMIENTO' => $tipoReemplazo,
                    'ASIGPROF' => '',
                    'PAIS' => trim((string) ($postulante?->nacionalidad ?? '')),
                    'EDU' => trim((string) ($postulante?->nivel_estudios ?? '')),
                    'TITULO' => trim((string) ($postulante?->institucion_titulo ?? '')),
                    'OTROS_EDU' => '',
                    'ESPECIALIDAD' => trim(collect([
                        $postulante?->mencion,
                        $postulante?->especialidad_tp,
                        $postulante?->areaDesempeno?->nombre,
                    ])->filter()->unique()->implode(' / ')),
                    'UNIDAD' => '',
                    'ESTAB' => $rbdEstablecimiento,
                    'CR' => 'SCR',
                    'EC' => '',
                    'OBSERVACIONES' => $observaciones,
                    'solicitud_final_id' => $ultima->id,
                    'solicitudes_ids' => $ordenada->pluck('id')->values()->all(),
                    'cantidad_solicitudes' => $ordenada->count(),
                    'rut_reemplazo' => $this->formatRutChile($user?->rut ?? $ultima->rut_reemplazo_normalizado),
                    'nombre_completo_reemplazo' => trim((string) ($user?->nombre_completo ?? $user?->full_name ?? $user?->email ?? '')),
                    'rut_titular' => $this->formatRutChile($funcionario?->rut ?? $ultima->rut_titular_normalizado),
                    'nombre_titular' => trim((string) ($funcionario?->nombre ?? '')),
                    'rbd_establecimiento' => (string) ($establecimiento?->rbd ?? ''),
                    'nombre_establecimiento' => (string) ($establecimiento?->nombre_establecimiento ?? ''),
                    'comuna' => (string) ($establecimiento?->comuna ?? ''),
                    'tipo_reemplazo' => $tipoReemplazo,
                    'fecha_inicio_cadena' => $inicioCadena->format('d-m-Y'),
                    'fecha_termino_cadena' => $terminoCadena->format('d-m-Y'),
                    'fecha_alejamiento' => $fechaAlejamiento->format('d-m-Y'),
                    'causal_alejamiento' => $tipoReemplazo,
                    'jornada' => $jornadaEntera,
                    'estado' => (string) $ultima->estado,
                    'motivo_cese_matriz_c' => $motivoCese,
                    'observaciones' => $observaciones,
                    'datos_faltantes' => $datosFaltantes,
                    'cantidad_datos_faltantes' => count($datosFaltantes),
                    'tiene_datos_faltantes' => !empty($datosFaltantes),
                ];
            })
            ->filter()
            ->sortBy([
                ['fecha_termino_cadena', 'asc'],
                ['nombre_establecimiento', 'asc'],
                ['nombre_completo_reemplazo', 'asc'],
            ])
            ->values();
    }

    protected function buildRows(string $fechaInicio, string $fechaTermino, array $tiposReemplazo = []): Collection
    {
        return SolicitudReemplazo::query()
            ->with([
                'postulante.user:id,rut,nombres,apellido_paterno,apellido_materno,email',
                'establecimiento:id,rbd,nombre_establecimiento,sala_cuna',
                'funcionarioTitular:id,rut,nombre,estatuto',
                'jornadas:id,solicitud_reemplazo_id,reemplazo_total',
            ])
            ->whereIn('estado', ['aceptada', 'cerrado'])
            ->whereNotNull('fecha_inicio_trabajo')
            ->whereNotNull('fecha_termino')
            ->when(!empty($tiposReemplazo), function ($query) use ($tiposReemplazo) {
                $query->whereIn('tipo_reemplazo', $tiposReemplazo);
            })
            ->whereHas('funcionarioTitular', function ($query) {
                $query->where(function ($docente) {
                    $docente
                        ->whereIn('estatuto', ['DOCENTE', 'PROFESOR', 'PROFESORA'])
                        ->orWhere('estatuto', 'like', '%DOC%');
                });
            })
            ->whereDate('fecha_inicio_trabajo', '<=', $fechaTermino)
            ->whereDate('fecha_termino', '>=', $fechaInicio)
            ->orderBy('fecha_inicio_trabajo')
            ->orderBy('establecimiento_id')
            ->orderBy('id')
            ->get([
                'id',
                'estado',
                'tipo_reemplazo',
                'fecha_inicio_trabajo',
                'fecha_termino',
                'postulant_profile_id',
                'establecimiento_id',
                'reemplazo_personal_id',
            ])
            ->filter(function (SolicitudReemplazo $solicitud) {
                return $this->funcionarioTitularEsDocente($solicitud->funcionarioTitular?->estatuto);
            })
            ->map(function (SolicitudReemplazo $solicitud) {
                $postulante = $solicitud->postulante;
                $user = $postulante?->user;
                $establecimiento = $solicitud->establecimiento;
                $funcionario = $solicitud->funcionarioTitular;

                return [
                    'solicitud_id' => $solicitud->id,
                    'rut_reemplazo' => $this->formatRutChile($user?->rut),
                    'nombre_completo_reemplazo' => trim((string) ($user?->nombre_completo ?? $user?->full_name ?? $user?->email ?? '')),
                    'rbd_establecimiento' => (string) ($establecimiento?->rbd ?? ''),
                    'nombre_establecimiento' => (string) ($establecimiento?->nombre_establecimiento ?? ''),
                    'rut_funcionario_a_reemplazar' => $this->formatRutChile($funcionario?->rut),
                    'nombre_funcionario_a_reemplazar' => trim((string) ($funcionario?->nombre ?? '')),
                    'tipo_reemplazo' => (string) ($solicitud->tipo_reemplazo ?? ''),
                    'fecha_inicio_trabajo' => optional($solicitud->fecha_inicio_trabajo)->format('d-m-Y') ?? '',
                    'fecha_termino' => optional($solicitud->fecha_termino)->format('d-m-Y') ?? '',
                    'horas_efectivamente_reemplazadas' => number_format((float) $solicitud->jornadas->sum(function ($j) {
                        return (float) ($j->reemplazo_total ?? 0);
                    }), 2, ',', '.'),
                    'estado' => (string) $solicitud->estado,
                ];
            })
            ->values();
    }

    protected function funcionarioTitularEsDocente(?string $estatuto): bool
    {
        $e = strtoupper(trim((string) $estatuto));

        if ($e === '') {
            return false;
        }

        if (in_array($e, ['DOCENTE', 'PROFESOR', 'PROFESORA'], true)) {
            return true;
        }

        return str_contains($e, 'DOC');
    }

    protected function splitRutDipres(?string $rut): array
    {
        $normalizado = Rut::normalize($rut);

        if (!$normalizado || strlen($normalizado) < 2) {
            return ['run' => '', 'dv' => ''];
        }

        return [
            'run' => substr($normalizado, 0, -1),
            'dv' => substr($normalizado, -1),
        ];
    }

    protected function establecimientoEsSalaCuna(mixed $establecimiento, ?string $rbdRegistro = null): bool
    {
        $valorDirecto = $establecimiento?->sala_cuna ?? $establecimiento?->es_sala_cuna ?? null;

        if (is_bool($valorDirecto) && $valorDirecto === true) {
            return true;
        }

        if (is_numeric($valorDirecto) && (int) $valorDirecto === 1) {
            return true;
        }

        $rbd = trim((string) ($rbdRegistro ?: ($establecimiento?->rbd ?? $establecimiento?->RBD ?? '')));
        $rbdNumerico = preg_replace('/\D+/', '', $rbd);

        if ($rbdNumerico !== '') {
            $valorPorRbd = Establecimiento::query()
                ->where(function ($query) use ($rbdNumerico) {
                    $query->where('rbd', (int) $rbdNumerico)
                        ->orWhere('cod_estab', (int) $rbdNumerico);
                })
                ->value('sala_cuna');

            if ($valorPorRbd !== null) {
                return (int) $valorPorRbd === 1;
            }
        }

        if (is_bool($valorDirecto)) {
            return $valorDirecto;
        }

        if (is_numeric($valorDirecto)) {
            return (int) $valorDirecto === 1;
        }

        $normalizado = $this->normalizeTextMatrizS((string) $valorDirecto);

        return in_array($normalizado, ['SI', 'SALA CUNA', 'SALA_CUNA', 'TRUE', 'VERDADERO'], true);
    }

    protected function normalizeTextMatrizS(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $replacements = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U', 'ü' => 'U', 'ñ' => 'N',
        ];

        return strtoupper(strtr($value, $replacements));
    }

    protected function sistemaRemuneracionMatrizS(string $estamento, mixed $establecimiento, ?string $rbdRegistro = null): string
    {
        if ($this->establecimientoEsSalaCuna($establecimiento, $rbdRegistro)) {
            return '15';
        }

        $estamentoNormalizado = $this->normalizeTextMatrizS($estamento);

        if (str_contains($estamentoNormalizado, 'DOCENTE')) {
            return '13';
        }

        if (str_contains($estamentoNormalizado, 'ASISTENTE')) {
            return '14';
        }

        return '';
    }

    protected function missingMatrizSFields(array $values): array
    {
        return collect($values)
            ->filter(function ($value): bool {
                if (is_string($value)) {
                    return trim($value) === '';
                }

                return $value === null || $value === '';
            })
            ->keys()
            ->values()
            ->all();
    }

    protected function matrizSColumns(): array
    {
        return [
            'RUN_TIT',
            'DV_TIT',
            'CAUSAL',
            'RUN',
            'DV',
            'APELLIDO_1',
            'APELLIDO_2',
            'NOMBRES',
            'FECHA_NAC',
            'EDAD',
            'SEXO',
            'INGRESO_SERV',
            'ANTIG_SERV',
            'ANTIG_AP',
            'PREVISION',
            'SALUD',
            'SIST_REM',
            'REGION',
            'ESTAMENTO',
            'GRADO',
            'DESC_NIVEL',
            'C_DESEMPEÑO',
            'JORNADA',
            'FUNCION_DIRECTIVA',
            'INICIO_NOM',
            'TERMINO_NOM',
            'ASIGPROF',
            'ZONA',
            'BI',
            'TRI',
            'PAIS',
            'EDU',
            'TITULO',
            'OTROS_EDU',
            'ESPECIALIDAD',
            'UNIDAD',
            'ESTAB',
            'RENTA',
            'MODALIDAD',
            'PORCENTAJE',
            'ORIGEN',
            'CR',
            'OBSERVACIONES',
        ];
    }

    protected function matrizCColumns(): array
    {
        return [
            'TIPO_INFO',
            'ID_SERV',
            'RUN',
            'DV',
            'APELLIDO_1',
            'APELLIDO_2',
            'NOMBRES',
            'FECHA_NAC',
            'EDAD',
            'SEXO',
            'INGRESO_SERV',
            'ANTIG_SERV',
            'PREVISION',
            'SALUD',
            'SIST_REM',
            'REGION',
            'DOTACION',
            'ESTAMENTO',
            'GRADO',
            'DESC_NIVEL',
            'C_JURIDICA',
            'C_DESEMPENO',
            'SUBT',
            'RENTA',
            'JORNADA',
            'FUNCION_DIRECTIVA',
            'INICIO_NOM',
            'TERMINO_NOM',
            'FECHA_ALEJAMIENTO',
            'CAUSAL_ALEJAMIENTO',
            'ASIGPROF',
            'PAIS',
            'EDU',
            'TITULO',
            'OTROS_EDU',
            'ESPECIALIDAD',
            'UNIDAD',
            'ESTAB',
            'CR',
            'EC',
            'OBSERVACIONES',
        ];
    }

    protected function formatRutChile(?string $rut): string
    {
        return trim((string) (Rut::format($rut) ?? $rut ?? ''));
    }

    protected function tiposPlanilla(): array
    {
        return [
            self::TIPO_PLANILLA_BRP => 'Planilla BRP',
            self::TIPO_PLANILLA_DIPRES => 'Informe Reemplazo Maternidad (DIPRES)',
            self::TIPO_PLANILLA_MATRIZ_S_DIPRES => 'Informe Matriz S DIPRES',
            self::TIPO_PLANILLA_MATRIZ_C_DIPRES => 'Informe Matriz C DIPRES',
        ];
    }


    protected function esMatrizDipres(?string $tipoPlanilla): bool
    {
        return in_array($tipoPlanilla, [
            self::TIPO_PLANILLA_MATRIZ_S_DIPRES,
            self::TIPO_PLANILLA_MATRIZ_C_DIPRES,
        ], true);
    }

    protected function rangosCeseMatrizC(?string $trimestre, int $anio): array
    {
        $rango = $this->rangoTrimestreMatrizS($trimestre, $anio);
        $inicio = Carbon::parse($rango['inicio'])->startOfDay();
        $termino = Carbon::parse($rango['termino'])->startOfDay();

        return [
            'inicio_trimestre' => $inicio->format('Y-m-d'),
            'termino_trimestre' => $termino->format('Y-m-d'),
            'dia_anterior_termino_trimestre' => $termino->copy()->subDay()->format('Y-m-d'),
            'ultimo_dia_trimestre_anterior' => $inicio->copy()->subDay()->format('Y-m-d'),
        ];
    }


    protected function trimestresMatrizSOptions(): array
    {
        return [
            '1' => '1er Trimestre (01 de enero al 31 de marzo)',
            '2' => '2do Trimestre (01 de abril al 30 de junio)',
            '3' => '3er Trimestre (01 de julio al 30 de septiembre)',
            '4' => '4to Trimestre (01 de octubre al 31 de diciembre)',
        ];
    }

    protected function rangoTrimestreMatrizS(?string $trimestre, int $anio): array
    {
        $anio = $anio > 0 ? $anio : 2026;

        return match ((string) $trimestre) {
            '2' => ['inicio' => sprintf('%04d-04-01', $anio), 'termino' => sprintf('%04d-06-30', $anio)],
            '3' => ['inicio' => sprintf('%04d-07-01', $anio), 'termino' => sprintf('%04d-09-30', $anio)],
            '4' => ['inicio' => sprintf('%04d-10-01', $anio), 'termino' => sprintf('%04d-12-31', $anio)],
            default => ['inicio' => sprintf('%04d-01-01', $anio), 'termino' => sprintf('%04d-03-31', $anio)],
        };
    }

    protected function tiposReemplazoOptions(): array
    {
        return [
            'Licencia Médica (General)',
            'Licencia Médica (Pre y/o Post Natal y/o Parental)',
            'Permiso Postnatal Parental',
            'Permiso sin goce de sueldo',
            'Permiso Horas de Lactancia',
            'Permiso especial para deportistas (Art 74, Ley 19.712)',
            'Sumario Administrativo',
            'Otras',
        ];
    }
}
