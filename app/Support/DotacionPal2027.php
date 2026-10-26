<?php

namespace App\Support;

use App\Models\Establecimiento;

/** Adaptación de sólo lectura. Las limitaciones del formato PAL nunca alteran el SGA. */
class DotacionPal2027
{
    public const ROLES = ['admin', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'];
    private const FUNCION_OTROS = 'Otros (Talleristas / Apoyo / etc.)';

    public static function canView(?string $role): bool
    {
        return in_array($role, self::ROLES, true);
    }

    public function datos(Establecimiento $ee): array
    {
        return DotacionLecturaCache::ejecutar(fn () => DotacionEstablecimientoCalculator::build($ee, 2027, false, false));
    }

    public function construir(Establecimiento $ee, array $data, array $tablas): array
    {
        $cursosPal = (new DotacionPalCursos2027)->construir($ee, $data);
        $advertencias = $cursosPal['advertencias'];
        $filasCursos = $cursosPal['filas'];
        $filasDocentes = [];
        // La fuente ya consulta las situaciones exclusivamente del año 2027.
        $docentes = collect($data['docentes'] ?? []);
        $catalogo = new DotacionPalExclusiones2027;
        $excluidos = $catalogo->proyeccion($ee);
        foreach ($docentes->where('exclusion_docente.motivo', 'proceso_bir') as $docente) {
            $horas = (float) ($docente['horas_contrato_base'] ?? $docente['horas_contrato'] ?? 0);
            $excluidos[] = $catalogo->fila($ee, $docente, 'Proceso BIR 2027', $horas,
                'Persona excluida del PAL por su situación Proceso BIR 2027. Contrato de referencia: '.$horas.' h.');
        }
        $cantidadBir = $docentes->where('exclusion_docente.motivo', 'proceso_bir')->count();
        $docentes = $docentes->reject(fn ($d) => data_get($d, 'exclusion_docente.motivo') === 'proceso_bir')->values();
        $justificadas = $this->titularesJustificados($ee);
        $detalleJustificaciones = $justificadas === [] ? [] : $catalogo->justificaciones($ee);
        if ($cantidadBir > 0) { $advertencias[] = 'Se excluyen del PAL '.$cantidadBir.' docente(s) con situación Proceso BIR 2027.'; }
        foreach ($docentes as $docente) {
            $docente['pal_titulares_justificadas'] = $justificadas[DotacionEstablecimientoCalculator::normalizeRut($docente['rut'] ?? null)] ?? 0.0;
            $docente['pal_detalle_justificaciones'] = $detalleJustificaciones[DotacionEstablecimientoCalculator::normalizeRut($docente['rut'] ?? null)] ?? '';
            $filas = $this->docente($ee, $docente, $tablas);
            $this->registrarExclusiones($ee, $filas, [$docente], $excluidos, $catalogo);
            array_push($filasDocentes, ...$filas);
        }
        $filasDocentes = (new DotacionPalFilas2027)->consolidar($filasDocentes, $tablas);
        $this->registrarExclusiones($ee, $filasDocentes, $docentes->all(), $excluidos, $catalogo);
        $incompatibles = count(array_filter($filasDocentes, fn ($row) => str_contains($row['S'], 'INCOMPATIBLE:')));
        if ($incompatibles) { $advertencias[] = $incompatibles.' fila(s) docentes incompatibles: sus horas se describen en Observaciones y no se fuerzan a otra regla.'; }
        if ($docentes->isEmpty()) { $advertencias[] = 'No hay docentes del padrón incluidos en el PAL 2027.'; }
        $virtuales = collect(data_get($data, 'asignacion.docentes', []))->filter(fn ($d) => isset($d['cupo_contrata_id']))->count();
        if ($virtuales || count(data_get($data, 'asignacion.plazas_por_asumir', []))) {
            $advertencias[] = 'Las plazas por asumir y cupos sin persona identificada no se exportan como funcionarios.';
        }
        foreach (collect(data_get($data, 'asignacion.docentes', []))->filter(fn ($d) => isset($d['cupo_contrata_id']))
            ->concat(data_get($data, 'asignacion.plazas_por_asumir', [])) as $cupo) {
            $cupo['rut'] = '';
            $horas = (float) ($cupo['horas_contrato'] ?? 0);
            $excluidos[] = $catalogo->fila($ee, $cupo, isset($cupo['cupo_contrata_id']) ? 'Cupo por contratar sin persona' : 'Plaza por asumir sin persona',
                $horas, 'Sin persona identificada para informar en la nómina PAL. '.($cupo['nombre'] ?? ''),
                'Plazo Fijo', 'Cupo o plaza sin persona', 2027);
        }
        return ['cursos' => $filasCursos, 'docentes' => $filasDocentes, 'advertencias' => array_values(array_unique($advertencias)),
            'excluidos' => $excluidos,
            'personas' => $docentes->count(), 'incompatibles' => $incompatibles,
            'anio_padron' => data_get($data, 'resumen.anio_padron'),
            'horas_plan_sga' => (float) data_get($data, 'cursos.resumen_cursos_planes.totales.horas', 0),
            'contrato_plan_sga' => (float) data_get($data, 'cursos.resumen_cursos_planes.totales.horas_contrato_equivalente', 0)];
    }

    private function registrarExclusiones(Establecimiento $ee, array &$filas, array $docentes, array &$excluidos, DotacionPalExclusiones2027 $catalogo): void
    {
        $nomina = collect($docentes)->keyBy(fn ($d) => DotacionEstablecimientoCalculator::normalizeRut($d['rut'] ?? null));
        foreach ($filas as &$fila) {
            foreach ($fila['_pal_exclusiones'] ?? [] as $exclusion) {
                $docente = $nomina->get(DotacionEstablecimientoCalculator::normalizeRut($fila['A'] ?? null), []);
                $excluidos[] = $catalogo->fila($ee, $docente, $exclusion['motivo'], $exclusion['horas'], $exclusion['detalle'],
                    $exclusion['calidad'] ?? $fila['H'], $exclusion['base'] ?? 'Contrato docente');
            }
            unset($fila['_pal_exclusiones']);
        }
        unset($fila);
    }

    private function docente(Establecimiento $ee, array $docente, array $tablas): array
    {
        $docente['pal_nivel'] = $this->nivelDocente($ee, $docente);
        $base = max(0.0, (float) ($docente['horas_contrato'] ?? 0));
        $capacidades = $this->capacidadesContrato($base, $docente);
        $grupos = $this->grupos($docente);
        // Una reserva con origen explícito conserva su calidad aun si fue
        // creada antes o después de las asignaciones de aula.
        foreach ($capacidades as &$capacidad) {
            $capacidad['reservadas'] = min($capacidad['horas'], (float) collect($grupos)
                ->where('reserva_origen', $capacidad['calidad'])->sum('contrato'));
        }
        unset($capacidad);
        $asignadas = round((float) collect($grupos)->sum('contrato'), 2);
        $pendientes = max(0, round($base - $asignadas, 2));
        $filas = [];
        foreach ($grupos as $grupo) {
            $indices = array_keys(array_filter($capacidades, fn ($c) =>
                $grupo['reserva_origen'] === null || $c['calidad'] === $grupo['reserva_origen']));
            $indice = 0;
            $restante = $grupo['contrato'];
            while ($restante > 0.001) {
                while ($indice < count($indices) && $this->disponible($capacidades[$indices[$indice]], $grupo) < 0.001) { $indice++; }
                $exceso = $indice >= count($indices);
                $capacidad = $exceso ? ['calidad' => 'Otros', 'horas' => 0] : $capacidades[$indices[$indice]];
                $parte = $exceso ? $restante : min($restante, $this->disponible($capacidad, $grupo));
                $convertidas = ! $exceso && ($grupo['convertir_parvularia'] ?? false)
                    ? $this->filasParvularia($ee, $docente, $grupo, round($parte, 2), $capacidad, $pendientes, $tablas)
                    : null;
                if ($convertidas !== null) { array_push($filas, ...$convertidas); }
                else { $filas[] = $this->fila($ee, $docente, $grupo, round($parte, 2), $capacidad, $pendientes, $tablas, $exceso); }
                if (! $exceso) {
                    $capacidades[$indices[$indice]]['usadas'] += $parte;
                    if ($grupo['reserva_origen'] !== null) {
                        $capacidades[$indices[$indice]]['reservadas'] = max(0.0, $capacidad['reservadas'] - $parte);
                    }
                }
                $restante = round($restante - $parte, 4);
            }
        }
        $titular = collect($capacidades)->firstWhere('calidad', 'Titular');
        $docente['pal_sobredotacion_excluida'] = min((float) $docente['pal_titulares_justificadas'],
            max(0.0, round(($titular['horas'] ?? 0) - ($titular['usadas'] ?? 0), 2)));
        [$filas, $reservasExcluidas] = $this->incorporarReservas($filas,
            (float) $docente['pal_titulares_justificadas'] - $docente['pal_sobredotacion_excluida']);
        array_push($filas, ...$this->filasOtrasHoras($ee, $docente, $capacidades, $pendientes, $tablas));
        if ($filas === []) { $filas[] = $this->fila($ee, $docente, null, 0, $capacidades[0], $pendientes, $tablas); }
        foreach ($capacidades as $capacidad) {
            $saldo = max(0.0, round($capacidad['horas'] - $capacidad['usadas'], 2));
            if ($capacidad['calidad'] === 'Otros' && $saldo > 0) {
                $filas[0]['_pal_exclusiones'][] = ['motivo' => 'Saldo con calidad contractual sin correspondencia',
                    'calidad' => 'Otros', 'horas' => $saldo,
                    'detalle' => 'Saldo sin asignar que no corresponde a horas titulares o a plazo fijo del padrón.'];
            }
        }
        $justificadasExcluidas = round($docente['pal_sobredotacion_excluida'] + $reservasExcluidas, 2);
        if ($justificadasExcluidas > 0) {
            $filas[0]['_pal_exclusiones'][] = ['motivo' => 'Horas titulares justificadas en Sobredotación 2027',
                'horas' => $justificadasExcluidas, 'calidad' => 'Titular',
                'detalle' => 'Descuento aplicado una sola vez entre saldo y reservas titulares: '.$justificadasExcluidas.' h. '
                    .($docente['pal_detalle_justificaciones'] ?? '')];
            foreach ($filas as &$fila) {
                $fila['S'] .= ' Sobredotación 2027: '.$justificadasExcluidas.' h titulares justificadas excluidas de la incorporación del saldo y las reservas en FUNCIÓN '.self::FUNCION_OTROS.'.';
            }
            unset($fila);
        }
        return $filas;
    }

    private function disponible(array $capacidad, array $grupo): float
    {
        return max(0.0, round($capacidad['horas'] - $capacidad['usadas']
            - ($grupo['reserva_origen'] === null ? $capacidad['reservadas'] : 0), 4));
    }

    /** La misma justificación se descuenta una vez entre saldo y reservas. */
    private function incorporarReservas(array $filas, float $justificadas): array
    {
        $excluidas = 0.0;
        foreach ($filas as &$fila) {
            if (! array_key_exists('_pal_reserva_titular', $fila)) { continue; }
            $reserva = (float) $fila['_pal_reserva_titular'];
            $descuento = min($reserva, max(0.0, round($justificadas - $excluidas, 2)));
            $incorporadas = round($reserva - $descuento, 2);
            if ($incorporadas > 0) { $fila['R'] = $incorporadas; }
            $fila['S'] .= ' Clasificación para el informe PAL: '.$incorporadas.' h de reserva titular en FUNCIÓN '.self::FUNCION_OTROS.' y columna R.';
            if ($descuento > 0) { $fila['S'] .= ' De esta reserva se excluyen '.$descuento.' h titulares justificadas en Sobredotación 2027.'; }
            $excluidas = round($excluidas + $descuento, 2);
            unset($fila['_pal_reserva_titular']);
        }
        unset($fila);
        return [$filas, $excluidas];
    }

    /** Una lectura acotada al establecimiento/año, nunca una consulta por docente. */
    private function titularesJustificados(Establecimiento $ee): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('dotacion_sobredotacion_justificaciones')) { return []; }
        $horas = [];
        $registros = \App\Models\DotacionSobredotacionJustificacion::query()
            ->where('establecimiento_id', $ee->id)->where('anio', 2027)->where('tipo_horas', 'titular')
            ->whereIn('bloque', ['plan_estudio', 'parvularia', 'pie'])->where('horas_detectadas', '>', 0)
            ->get(['docente_rut_normalizado', 'horas_detectadas', 'justificacion']);
        foreach ($registros as $registro) {
            if (trim((string) $registro->justificacion) === '') { continue; }
            $rut = DotacionEstablecimientoCalculator::normalizeRut($registro->docente_rut_normalizado);
            if ($rut !== '') { $horas[$rut] = round(($horas[$rut] ?? 0) + (float) $registro->horas_detectadas, 2); }
        }
        return $horas;
    }

    private function capacidadesContrato(float $base, array $docente): array
    {
        $titular = min($base, max(0.0, (float) ($docente['horas_planta'] ?? 0)));
        $contrata = min(max(0, $base - $titular), max(0.0, (float) ($docente['horas_contrata'] ?? 0)));
        $capacidades = [];
        foreach (['Titular' => $titular, 'Plazo Fijo' => $contrata, 'Otros' => max(0, $base - $titular - $contrata)] as $calidad => $horas) {
            if ($horas > 0) { $capacidades[] = ['calidad' => $calidad, 'horas' => round($horas, 2), 'usadas' => 0.0]; }
        }
        return $capacidades ?: [['calidad' => 'Otros', 'horas' => 0.0, 'usadas' => 0.0]];
    }

    /** Completa sólo el informe: conserva por separado situación y saldo sin asignar. */
    private function filasOtrasHoras(Establecimiento $ee, array $docente, array $capacidades, float $pendientes, array $tablas): array
    {
        $base = max(0.0, (float) ($docente['horas_contrato'] ?? 0));
        $situacion = $docente['exclusion_docente'] ?? null;
        $excluidas = ! empty($situacion['motivo'])
            ? max(0.0, (float) ($docente['horas_excluidas'] ?? $situacion['horas'] ?? 0)) : 0.0;
        $completo = max(0.0, (float) ($docente['horas_contrato_base'] ?? ($base + $excluidas)));
        if (abs(round($completo - $base - $excluidas, 2)) > 0.0001) {
            throw \Illuminate\Validation\ValidationException::withMessages(['pal' => 'Las horas consideradas y excluidas de un docente no coinciden con su contrato completo. Revise la situación 2027 antes de descargar el PAL.']);
        }
        $motivo = $situacion['motivo_label'] ?? (\App\Models\DotacionDocenteExclusion::MOTIVOS[$situacion['motivo'] ?? ''] ?? 'Situación 2027');
        $consideradas = array_column($capacidades, null, 'calidad');
        $filas = [];
        foreach ($this->capacidadesContrato($completo, $docente) as $capacidad) {
            $actual = $consideradas[$capacidad['calidad']] ?? ['horas' => 0.0, 'usadas' => 0.0];
            $porSituacion = max(0.0, round($capacidad['horas'] - $actual['horas'], 2));
            $sinAsignar = in_array($capacidad['calidad'], ['Titular', 'Plazo Fijo'], true)
                ? max(0.0, round($actual['horas'] - $actual['usadas']
                    - ($capacidad['calidad'] === 'Titular' ? $docente['pal_sobredotacion_excluida'] : 0), 2)) : 0.0;
            $total = round($porSituacion + $sinAsignar, 2);
            if ($total <= 0) { continue; }
            $fila = $this->fila($ee, $docente, null, 0, $capacidad, $pendientes, $tablas);
            $fila['G'] = self::FUNCION_OTROS;
            $fila['R'] = $total;
            $detalle = [];
            if ($porSituacion > 0) { $detalle[] = $porSituacion.' h por situación 2027: '.$motivo; }
            if ($sinAsignar > 0) { $detalle[] = $sinAsignar.($capacidad['calidad'] === 'Titular'
                ? ' h titulares sin asignación, sin justificación en Sobredotación' : ' h a plazo fijo sin asignación'); }
            $fila['S'] .= ' Clasificación para el informe PAL: '.$total.' h cronológicas no lectivas en FUNCIÓN '.self::FUNCION_OTROS
                .' ('.implode('; ', $detalle).'). Calidad contractual: '.$capacidad['calidad'].'.';
            $filas[] = $fila;
        }
        return $filas;
    }

    private function grupos(array $docente): array
    {
        $grupos = [];
        $esParvulos = DotacionProfesionDocenteResolver::perfilTitulo($docente)['es_educacion_parvulos'];
        foreach (collect($docente['asignaciones'] ?? [])->sortBy(fn ($a) => (int) data_get($a, 'id', 0)) as $a) {
            if ((data_get($a, 'estado') ?? 'activa') !== 'activa' || ! DotacionAsignacionCalculator::esAsignacionDocenteReal($a)) { continue; }
            $tipo = (string) data_get($a, 'tipo_asignacion');
            $fondos = trim((string) data_get($a, 'subvencion', ''));
            $plan = in_array($tipo, ['plan_estudio', 'acompanamiento_parvularia'], true);
            $reserva = $tipo === 'reserva_no_normativa';
            $reservaOrigen = $reserva ? match (data_get($a, 'subtipo_asignacion')) {
                'titular' => 'Titular', 'contrata' => 'Plazo Fijo', default => null,
            } : null;
            $proporcion = (string) data_get($a, 'proporcion_aplicada', '');
            $columna = match (true) {
                $reserva && strcasecmp($fondos, 'General') === 0 => 'reserva',
                $reserva => 'incompatible',
                strcasecmp($fondos, 'PIE') === 0 => 'P', strcasecmp($fondos, 'SEP') === 0 => 'Q',
                strcasecmp($fondos, 'General') === 0 && ! $plan => 'R',
                strcasecmp($fondos, 'General') === 0 && $plan => 'aula', default => 'incompatible',
            };
            $nivel = $this->nivel($a, $docente);
            $funcion = match ($tipo) {
                'funcion_directiva' => 'Función Directiva', 'funcion_tecnico_pedagogica' => 'Función Técnico Pedagógica',
                'plan_estudio', 'acompanamiento_parvularia', 'pie_colaborativo', 'pie_educadora_diferencial' => 'Docente aula',
                default => self::FUNCION_OTROS,
            };
            $convertir = $esParvulos && $columna === 'aula' && $nivel === 'Educación Parvularia'
                && DotacionProfesionDocenteResolver::esAsignacionParvularia(['proporcion_aplicada' => $proporcion]);
            $key = implode('|', [$columna, $funcion, $columna === 'aula' ? ($convertir ? 'PAL_PARVULARIA_65_35' : $proporcion) : '', $nivel,
                $reserva ? 'reserva:'.($reservaOrigen ?? 'sin_origen') : '']);
            $grupos[$key] ??= ['columna' => $columna, 'funcion' => $funcion, 'nivel' => $nivel,
                'proporcion' => $proporcion, 'contrato' => 0.0, 'pedagogicas' => 0.0, 'detalle' => [],
                'convertir_parvularia' => $convertir, 'reglas_originales' => [], 'reserva_origen' => $reservaOrigen];
            $grupos[$key]['reglas_originales'][] = $proporcion;
            $grupos[$key]['contrato'] += max(0, (float) data_get($a, 'horas_contrato', 0));
            $grupos[$key]['pedagogicas'] += max(0, (float) data_get($a, 'horas_plan_pedagogicas', 0));
            $grupos[$key]['detalle'][] = (string) (data_get($a, 'asignatura_nombre') ?: data_get($a, 'observacion') ?: (\App\Models\DotacionDocenteAsignacion::TIPOS[$tipo] ?? $tipo));
        }
        return array_values($grupos);
    }

    private function filasParvularia(Establecimiento $ee, array $docente, array $grupo, float $parte, array $capacidad, float $pendientes, array $tablas): ?array
    {
        $bloques = DotacionPalParvularia65::convertir($parte, $tablas['65/35'] ?? []);
        if ($bloques === null) { return null; }
        $aulaOriginal = round($grupo['pedagogicas'] * $parte / $grupo['contrato'], 4);
        $filas = [];
        foreach ($bloques as $indice => $bloque) {
            $representacion = array_replace($grupo, ['proporcion' => '65/35', 'contrato' => $bloque['contrato'], 'pedagogicas' => $bloque['aula']]);
            $fila = $this->fila($ee, $docente, $representacion, $bloque['contrato'], $capacidad, $pendientes, $tablas);
            $fila['S'] .= ' Conversión PAL de Parvularia a 65/35: '.$bloque['aula'].' h aula equivalentes = '.$bloque['contrato'].' h contrato.'
                .' Referencia SGA: '.$aulaOriginal.' h aula real y '.$parte.' h contrato de regla especial.'
                .' Parte '.($indice + 1).' de '.count($bloques).' para esta calidad contractual. Regla original: '
                .implode(' / ', array_unique($grupo['reglas_originales'])).'.';
            $filas[] = $fila;
        }
        return $filas;
    }

    /** Una opción de la plantilla por docente, conservando el nivel de cálculo de cada grupo. */
    private function nivelDocente(Establecimiento $ee, array $docente): ?string
    {
        if ($ee->especial) { return 'Educación Básica'; }
        $clases = collect($docente['asignaciones'] ?? [])->filter(fn ($a) =>
            (data_get($a, 'estado') ?? 'activa') === 'activa'
            && DotacionAsignacionCalculator::esAsignacionDocenteReal($a)
            && in_array(data_get($a, 'tipo_asignacion'), ['plan_estudio', 'acompanamiento_parvularia', 'pie_colaborativo', 'pie_educadora_diferencial'], true)
            && (in_array(data_get($a, 'tipo_asignacion'), ['plan_estudio', 'acompanamiento_parvularia'], true)
                || data_get($a, 'establecimientoCurso.curso') !== null)
            && ((float) data_get($a, 'horas_contrato', 0) > 0 || (float) data_get($a, 'horas_plan_pedagogicas', 0) > 0));
        if ($clases->isEmpty()) { return $this->nivel([], $docente); }
        $niveles = $clases->map(fn ($a) => $this->nivel($a, []))->unique()->values();
        if ($niveles->contains('')) { return null; }
        if ($niveles->count() === 1) { return $niveles->first(); }
        if ($niveles->count() === 2 && $niveles->contains('Educación Básica') && $niveles->contains('Educación Media')) {
            return 'Ed. Básica/Media';
        }
        // La plantilla no ofrece una opción mixta que incluya Parvularia.
        // Se conserva entonces el nivel de cada fila, sin inventar categorías.
        return null;
    }

    private function nivel(object|array $a, array $docente): string
    {
        $curso = data_get($a, 'establecimientoCurso.curso');
        $texto = mb_strtoupper(\Illuminate\Support\Str::ascii(implode(' ', [(string) data_get($curso, 'codigo'),
            (string) data_get($curso, 'nivel_educativo'), (string) data_get($curso, 'nombre'), (string) data_get($curso, 'modalidad')])));
        if (str_contains($texto, 'EPJA') || str_contains($texto, 'ADULTO')) {
            if (str_contains($texto, 'BASIC')) { return 'Educación Básica'; }
            if (str_contains($texto, 'MEDIA') || str_contains($texto, 'MEDIO')) { return 'Educación Media'; }
            return '';
        }
        if (str_contains($texto, 'ESPECIAL') || str_contains($texto, 'LABORAL') || str_contains($texto, 'DIFERENCIAL')) { return 'Educación Básica'; }
        if (str_contains($texto, 'NT1') || str_contains($texto, 'NT2') || str_contains($texto, 'PARVUL')) { return 'Educación Parvularia'; }
        if (preg_match('/^[1-8]B\b/', $texto) || str_contains($texto, 'BASIC')) { return 'Educación Básica'; }
        if (preg_match('/^[1-4]M\b/', $texto) || str_contains($texto, 'MEDIA')) { return 'Educación Media'; }
        $niveles = collect($docente['niveles_declarados'] ?? []);
        $basica = $niveles->contains(fn ($n) => str_contains(mb_strtoupper(\Illuminate\Support\Str::ascii((string) $n)), 'BASIC'));
        $media = $niveles->contains(fn ($n) => str_contains(mb_strtoupper(\Illuminate\Support\Str::ascii((string) $n)), 'MEDIA'));
        if ($basica && $media) { return 'Ed. Básica/Media'; }
        if ($basica) { return 'Educación Básica'; }
        if ($media) { return 'Educación Media'; }
        return $niveles->contains(fn ($n) => str_contains(mb_strtoupper(\Illuminate\Support\Str::ascii((string) $n)), 'PARVUL')) ? 'Educación Parvularia' : '';
    }

    private function fila(Establecimiento $ee, array $docente, ?array $grupo, float $parte, array $capacidad, float $pendientes, array $tablas, bool $exceso = false): array
    {
        $fila = array_fill_keys(['A','B','C','D','E','F','G','H','I','K','L','M','P','Q','R','S'], null);
        $perfil = DotacionProfesionDocenteResolver::perfilTitulo($docente);
        $fila = array_replace($fila, ['A' => (string) ($docente['rut'] ?? ''), 'B' => (string) $ee->nombre_establecimiento,
            'C' => (string) $ee->rbd, 'D' => $docente['pal_nivel'] ?? ($grupo['nivel'] ?? $this->nivel([], $docente)),
            'E' => $perfil['es_educacion_parvulos'] ? 'Educadora de Parvulos' : 'Docente',
            'F' => (string) ($docente['funcion'] ?? ''), 'G' => $grupo['funcion'] ?? '', 'H' => $capacidad['calidad'],
            'I' => data_get($docente, 'exclusion_docente.motivo') === 'horas_catedra_art_69' ? 'si' : null]);
        $notas = ['Borrador PAL 2027. Contrato '.$capacidad['calidad'].': '.$capacidad['horas'].' h.'];
        $situacion = $docente['exclusion_docente'] ?? null;
        if (! empty($situacion['motivo'])) {
            $consideradas = max(0.0, (float) ($docente['horas_contrato'] ?? 0));
            $noNecesarias = max(0.0, (float) ($docente['horas_excluidas'] ?? $situacion['horas'] ?? 0));
            $completo = max(0.0, (float) ($docente['horas_contrato_base'] ?? ($consideradas + $noNecesarias)));
            $motivo = $situacion['motivo_label'] ?? (\App\Models\DotacionDocenteExclusion::MOTIVOS[$situacion['motivo']] ?? $situacion['motivo']);
            $notas[] = 'Situación 2027: '.$motivo.'. Contrato completo del docente: '.$completo.' h.'
                .' Horas consideradas para la situación contractual: '.$consideradas.' h.'
                .' Horas no necesarias por la situación: '.$noNecesarias.' h.'
                .' Las horas excluidas se incorporan en el PAL con FUNCIÓN '.self::FUNCION_OTROS.'.';
        }
        if ($pendientes > 0) { $notas[] = 'Pendientes de distribución del docente: '.$pendientes.' h. Los saldos titulares y a plazo fijo se incorporan en el PAL con FUNCIÓN '.self::FUNCION_OTROS.'; del saldo titular se descuentan las horas justificadas en Sobredotación.'; }
        $problemas = [];
        if ($grupo) {
            $factor = $grupo['contrato'] > 0 ? $parte / $grupo['contrato'] : 0;
            $pedagogicas = round($grupo['pedagogicas'] * $factor, 4);
            if ($exceso) { $problemas[] = $grupo['reserva_origen'] !== null
                ? 'la reserva excede las horas de su calidad contractual en el padrón' : 'asignaciones exceden el contrato del padrón'; }
            if ($grupo['columna'] === 'aula') {
                $proporcion = in_array($grupo['proporcion'], ['65/35','60/40'], true) ? $grupo['proporcion'] : null;
                if ($proporcion === null || ! isset($tablas[$proporcion])) { $problemas[] = 'la fórmula original no representa '.$pedagogicas.' h pedagógicas / '.$parte.' h de contrato SGA ('.$grupo['proporcion'].')'; }
                if ($grupo['nivel'] === '' || $grupo['nivel'] === 'Ed. Básica/Media') { $problemas[] = 'falta desglose del nivel de las horas lectivas'; }
                if ($problemas === []) {
                    $fila[$grupo['nivel'] === 'Educación Media' ? 'L' : 'K'] = $pedagogicas;
                    $fila['M'] = $proporcion;
                    // Validar la equivalencia después de intentar consolidar;
                    // cada tramo del SGA puede contener sólo un incremento del contrato.
                    $fila['_pal_contrato_aula'] = $parte;
                }
            } elseif ($grupo['columna'] === 'reserva') {
                if (! $exceso && $capacidad['calidad'] === 'Titular') { $fila['_pal_reserva_titular'] = $parte; }
                elseif (! $exceso && $capacidad['calidad'] === 'Plazo Fijo') {
                    $fila['R'] = $parte;
                    $notas[] = 'Reserva a plazo fijo incorporada en FUNCIÓN '.self::FUNCION_OTROS.' y columna R.';
                } elseif (! $exceso) {
                    $notas[] = 'Reserva '.$capacidad['calidad'].' excluida: calidad contractual sin correspondencia en la incorporación de reservas del PAL.';
                    $fila['_pal_exclusiones'][] = ['motivo' => 'Reserva con calidad contractual sin correspondencia', 'horas' => $parte,
                        'detalle' => 'La reserva no corresponde a una capacidad titular o a plazo fijo del padrón.'];
                }
                if ($grupo['reserva_origen'] === null) { $notas[] = 'Reserva histórica sin origen contractual: calidad inferida del contrato disponible, primero titular.'; }
            } elseif ($grupo['columna'] === 'incompatible') {
                $problemas[] = 'financiamiento sin equivalencia en el formato';
            } elseif (! $exceso) { $fila[$grupo['columna']] = $parte; }
            $notas[] = 'Asignación SGA: '.$parte.' h cronológicas. '.implode('; ', array_unique($grupo['detalle'])).'.';
        }
        if ($fila['D'] === '') { $notas[] = 'Nivel pendiente de identificar en las categorías del formato PAL.'; }
        if ($problemas !== []) {
            $detalleProblema = implode('; ', $problemas);
            $notas[] = 'INCOMPATIBLE: '.$detalleProblema.'. Estas horas no se cargan en las entradas numéricas; J/N/O no representan este tramo.';
            $fila['_pal_exclusiones'][] = ['motivo' => $exceso ? 'Asignación excede capacidad contractual' : 'Asignación sin representación PAL',
                'horas' => $parte, 'base' => $exceso ? 'Asignación excedente' : 'Contrato docente',
                'detalle' => $detalleProblema.'. '.implode('; ', array_unique($grupo['detalle'])).'.'];
        }
        $fila['S'] = implode(' ', $notas);
        return $fila;
    }
}
