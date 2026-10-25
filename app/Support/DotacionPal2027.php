<?php

namespace App\Support;

use App\Models\Establecimiento;

/** Adaptación de sólo lectura. Las limitaciones del formato PAL nunca alteran el SGA. */
class DotacionPal2027
{
    public const ROLES = ['admin', 'coordinador_uatp', 'coordinador_gdp', 'supervisor_plani'];

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
        $cantidadBir = $docentes->where('exclusion_docente.motivo', 'proceso_bir')->count();
        $docentes = $docentes->reject(fn ($d) => data_get($d, 'exclusion_docente.motivo') === 'proceso_bir')->values();
        if ($cantidadBir > 0) { $advertencias[] = 'Se excluyen del PAL '.$cantidadBir.' docente(s) con situación Proceso BIR 2027.'; }
        foreach ($docentes as $docente) {
            array_push($filasDocentes, ...$this->docente($ee, $docente, $tablas));
        }
        $incompatibles = count(array_filter($filasDocentes, fn ($row) => str_contains($row['S'], 'INCOMPATIBLE:')));
        if ($incompatibles) { $advertencias[] = $incompatibles.' fila(s) docentes incompatibles: sus horas se describen en Observaciones y no se fuerzan a otra regla.'; }
        if ($docentes->isEmpty()) { $advertencias[] = 'No hay docentes del padrón incluidos en el PAL 2027.'; }
        $virtuales = collect(data_get($data, 'asignacion.docentes', []))->filter(fn ($d) => isset($d['cupo_contrata_id']))->count();
        if ($virtuales || count(data_get($data, 'asignacion.plazas_por_asumir', []))) {
            $advertencias[] = 'Las plazas por asumir y cupos sin persona identificada no se exportan como funcionarios.';
        }
        return ['cursos' => $filasCursos, 'docentes' => $filasDocentes, 'advertencias' => array_values(array_unique($advertencias)),
            'personas' => $docentes->count(), 'incompatibles' => $incompatibles,
            'anio_padron' => data_get($data, 'resumen.anio_padron'),
            'horas_plan_sga' => (float) data_get($data, 'cursos.resumen_cursos_planes.totales.horas', 0),
            'contrato_plan_sga' => (float) data_get($data, 'cursos.resumen_cursos_planes.totales.horas_contrato_equivalente', 0)];
    }

    private function docente(Establecimiento $ee, array $docente, array $tablas): array
    {
        $base = max(0.0, (float) ($docente['horas_contrato'] ?? 0));
        $titular = min($base, max(0.0, (float) ($docente['horas_planta'] ?? 0)));
        $contrata = min(max(0, $base - $titular), max(0.0, (float) ($docente['horas_contrata'] ?? 0)));
        $capacidades = [];
        foreach (['Titular' => $titular, 'Plazo Fijo' => $contrata, 'Otros' => max(0, $base - $titular - $contrata)] as $calidad => $horas) {
            if ($horas > 0) { $capacidades[] = ['calidad' => $calidad, 'horas' => round($horas, 2), 'usadas' => 0.0]; }
        }
        if ($capacidades === []) { $capacidades[] = ['calidad' => 'Otros', 'horas' => 0.0, 'usadas' => 0.0]; }
        $grupos = $this->grupos($docente);
        $asignadas = round((float) collect($grupos)->sum('contrato'), 2);
        $pendientes = max(0, round($base - $asignadas, 2));
        $filas = [];
        $indice = 0;
        foreach ($grupos as $grupo) {
            $restante = $grupo['contrato'];
            while ($restante > 0.001) {
                while ($indice < count($capacidades) && $capacidades[$indice]['horas'] - $capacidades[$indice]['usadas'] < 0.001) { $indice++; }
                $exceso = $indice >= count($capacidades);
                $parte = $exceso ? $restante : min($restante, $capacidades[$indice]['horas'] - $capacidades[$indice]['usadas']);
                $capacidad = $exceso ? ['calidad' => 'Otros', 'horas' => 0] : $capacidades[$indice];
                $convertidas = ! $exceso && ($grupo['convertir_parvularia'] ?? false)
                    ? $this->filasParvularia($ee, $docente, $grupo, round($parte, 2), $capacidad, $pendientes, $tablas)
                    : null;
                if ($convertidas !== null) { array_push($filas, ...$convertidas); }
                else { $filas[] = $this->fila($ee, $docente, $grupo, round($parte, 2), $capacidad, $pendientes, $tablas, $exceso); }
                if (! $exceso) { $capacidades[$indice]['usadas'] += $parte; }
                $restante = round($restante - $parte, 4);
            }
        }
        foreach ($capacidades as $capacidad) {
            if ($capacidad['usadas'] > 0) { continue; }
            $filas[] = $this->fila($ee, $docente, null, 0, $capacidad, $pendientes, $tablas);
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
            $proporcion = (string) data_get($a, 'proporcion_aplicada', '');
            $columna = match (true) {
                strcasecmp($fondos, 'PIE') === 0 => 'P', strcasecmp($fondos, 'SEP') === 0 => 'Q',
                strcasecmp($fondos, 'General') === 0 && ! $plan && $tipo !== 'reserva_no_normativa' => 'R',
                strcasecmp($fondos, 'General') === 0 && $plan => 'aula', default => 'incompatible',
            };
            $nivel = $this->nivel($a, $docente);
            $funcion = match ($tipo) {
                'funcion_directiva' => 'Función Directiva', 'funcion_tecnico_pedagogica' => 'Función Técnico Pedagógica',
                'plan_estudio', 'acompanamiento_parvularia', 'pie_colaborativo', 'pie_educadora_diferencial' => 'Docente aula',
                default => 'Otros (Talleristas / Apoyo / etc.)',
            };
            $convertir = $esParvulos && $columna === 'aula' && $nivel === 'Educación Parvularia'
                && DotacionProfesionDocenteResolver::esAsignacionParvularia(['proporcion_aplicada' => $proporcion]);
            $key = implode('|', [$columna, $funcion, $columna === 'aula' ? ($convertir ? 'PAL_PARVULARIA_65_35' : $proporcion) : '', $nivel]);
            $grupos[$key] ??= ['columna' => $columna, 'funcion' => $funcion, 'nivel' => $nivel,
                'proporcion' => $proporcion, 'contrato' => 0.0, 'pedagogicas' => 0.0, 'detalle' => [],
                'convertir_parvularia' => $convertir, 'reglas_originales' => []];
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

    private function nivel(object|array $a, array $docente): string
    {
        $curso = data_get($a, 'establecimientoCurso.curso');
        $texto = mb_strtoupper(\Illuminate\Support\Str::ascii(implode(' ', [(string) data_get($curso, 'codigo'), (string) data_get($curso, 'nivel_educativo')])));
        if (str_contains($texto, 'EPJA') || str_contains($texto, 'ADULTO') || str_contains($texto, 'ESPECIAL') || str_contains($texto, 'LABORAL')) { return ''; }
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
            'C' => (string) $ee->rbd, 'D' => $grupo['nivel'] ?? $this->nivel([], $docente),
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
                .' El contrato completo es una referencia; J conserva las horas distribuidas.';
        }
        if ($pendientes > 0) { $notas[] = 'Pendientes de distribución del docente: '.$pendientes.' h; no se asignan automáticamente a otras funciones.'; }
        $problemas = [];
        if ($grupo) {
            $factor = $grupo['contrato'] > 0 ? $parte / $grupo['contrato'] : 0;
            $pedagogicas = round($grupo['pedagogicas'] * $factor, 4);
            if ($exceso) { $problemas[] = 'asignaciones exceden el contrato del padrón'; }
            if ($grupo['columna'] === 'aula') {
                $proporcion = in_array($grupo['proporcion'], ['65/35','60/40'], true) ? $grupo['proporcion'] : null;
                $regla = $proporcion ? ($tablas[$proporcion][number_format($pedagogicas, 4, '.', '')] ?? null) : null;
                if ($regla === null || abs($regla['contrato'] - $parte) > 0.01) { $problemas[] = 'la fórmula original no representa '.$pedagogicas.' h pedagógicas / '.$parte.' h de contrato SGA ('.$grupo['proporcion'].')'; }
                if ($fila['D'] === '' || $fila['D'] === 'Ed. Básica/Media') { $problemas[] = 'falta desglose del nivel de las horas lectivas'; }
                if ($problemas === []) {
                    $fila[$fila['D'] === 'Educación Media' ? 'L' : 'K'] = $pedagogicas;
                    $fila['M'] = $proporcion;
                }
            } elseif ($grupo['columna'] === 'incompatible') {
                $problemas[] = 'financiamiento o reserva sin equivalencia en el formato';
            } elseif (! $exceso) { $fila[$grupo['columna']] = $parte; }
            $notas[] = 'Asignación SGA: '.$parte.' h cronológicas. '.implode('; ', array_unique($grupo['detalle'])).'.';
        }
        if ($fila['D'] === '') { $notas[] = 'Nivel pendiente de identificar en las categorías del formato PAL.'; }
        if ($problemas !== []) { $notas[] = 'INCOMPATIBLE: '.implode('; ', $problemas).'. Estas horas no se cargan en las entradas numéricas; J/N/O no representan este tramo.'; }
        $fila['S'] = implode(' ', $notas);
        return $fila;
    }
}
