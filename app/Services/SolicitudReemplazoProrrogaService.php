<?php

namespace App\Services;

use App\Models\SolicitudReemplazo;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SolicitudReemplazoProrrogaService
{
    public function casos(int $anio, ?string $rut = null): Collection
    {
        $diciembre = CarbonImmutable::create($anio, 12, 1)->startOfDay();
        $inicioDiciembre = $diciembre->toDateString();
        $finDiciembre = $diciembre->endOfMonth()->toDateString();
        ['desde' => $desdeProrroga, 'hasta' => $hastaProrroga] = $this->vigencia($anio);

        $base = $this->solicitudes();
        $candidatos = (clone $base)
            ->where('fecha_inicio_trabajo', '<=', $finDiciembre)
            ->where('fecha_termino', '>=', $inicioDiciembre)
            ->lazyById(500)
            ->map(fn (SolicitudReemplazo $solicitud) => $this->periodo($solicitud))
            ->filter(fn (?array $periodo) => $periodo && ($rut === null || $rut === $periodo['rut']))
            ->mapWithKeys(fn (array $periodo) => [$this->claveContinuidad($periodo) => true])
            ->all();

        if ($candidatos === []) {
            return collect();
        }

        // La cadena puede comenzar antes del semestre evaluado.
        $periodos = $base->where('fecha_inicio_trabajo', '<=', $hastaProrroga)
            ->lazyById(500)
            ->map(fn (SolicitudReemplazo $solicitud) => $this->periodo($solicitud))
            ->filter(fn (?array $periodo) => $periodo && isset($candidatos[$this->claveContinuidad($periodo)]))
            ->collect()
            ->groupBy(fn (array $periodo) => $this->claveContinuidad($periodo));

        return $periodos->map(function (Collection $tramos) use ($anio, $inicioDiciembre, $finDiciembre): ?array {
            ['desde' => $desdeProrroga, 'hasta' => $hastaProrroga] = $this->vigencia($anio, $tramos->first()['estamento']);
            $cadenas = $this->unirPeriodos($tramos);
            $cobertura = collect($cadenas)->filter(fn (array $cadena) => $cadena['hasta'] >= $desdeProrroga && $cadena['desde'] <= $hastaProrroga)
                ->map(fn (array $cadena) => [
                    'desde' => max($desdeProrroga, $cadena['desde']),
                    'hasta' => min($hastaProrroga, $cadena['hasta']),
                ])->values()->all();

            foreach ($cadenas as $cadena) {
                if ($cadena['hasta'] < $inicioDiciembre || $cadena['desde'] > $finDiciembre) {
                    continue;
                }
                $corte = min($cadena['hasta'], $finDiciembre);
                if ($cadena['desde'] > CarbonImmutable::parse($corte)->subMonthsNoOverflow(6)->toDateString()) {
                    continue;
                }
                $antecedentes = collect($cadena['periodos'])->where('desde', '<=', $corte)->values();
                $ultimo = $this->ultimoAlCorte($antecedentes, $corte);

                return [
                    'rut' => $ultimo['rut'],
                    'nombre' => $ultimo['nombre'],
                    'estamento' => $ultimo['estamento'],
                    'inicio_continuidad' => $cadena['desde'],
                    'fecha_corte' => $corte,
                    'periodos' => $antecedentes->all(),
                    'ultimo_reemplazo' => $ultimo,
                    'cobertura' => $cobertura,
                ];
            }

            return null;
        })->filter()
            ->sortBy([['fecha_corte', 'desc'], ['ultimo_reemplazo.desde', 'desc'], ['ultimo_reemplazo.id', 'desc']])
            ->unique(fn (array $caso) => $caso['estamento'].':'.$caso['rut'])
            ->keyBy(fn (array $caso) => $caso['estamento'].':'.$caso['rut']);
    }

    public function vigencia(int $anio, string $estamento = 'docente'): array
    {
        $febrero = CarbonImmutable::create($anio + 1, 2, 1)->startOfDay();

        return [
            'desde' => ($estamento === 'junji' ? $febrero : $febrero->startOfYear())->toDateString(),
            'hasta' => $febrero->endOfMonth()->toDateString(),
        ];
    }

    public function ultimoReemplazo(array $caso): array
    {
        if (isset($caso['ultimo_reemplazo'])) {
            return $caso['ultimo_reemplazo'];
        }
        $anterior = $this->ultimoAlCorte(collect($caso['periodos']), $caso['fecha_corte']);
        $solicitud = $anterior ? $this->solicitudes()->find($anterior['id']) : null;

        return ($solicitud ? $this->periodo($solicitud) : null) ?? $anterior ?? [];
    }

    public function motivo(string $estamento): string
    {
        return match ($estamento) {
            'docente' => 'BENEFICIARIO PRORROGA ENERO/FEBRERO, ART. 41 BIS, LEY N° 19.070',
            'junji' => 'PRORROGA MES DE FEBRERO, DICTAMEN E-198383 C.G.R.',
            default => 'PRÓRROGA ENERO/FEBRERO',
        };
    }

    public function financiamiento(array $periodo): string
    {
        return collect($periodo['financiamiento'] ?? [])->map(fn (array $fila) =>
            $this->formatearHoras($fila['horas']).' h '.$fila['fuente'])->implode(' + ');
    }

    private function formatearHoras(float $horas): string
    {
        return rtrim(rtrim(number_format($horas, 2, ',', ''), '0'), ',');
    }

    private function claveContinuidad(array $periodo): string
    {
        if ($periodo['estamento'] === 'junji') {
            return 'junji:'.$periodo['estamento_origen'].':'.$periodo['establecimiento_id'].':'.$periodo['rut'];
        }
        return $periodo['estamento'].':'.$periodo['rut'];
    }

    private function ultimoAlCorte(Collection $periodos, string $corte): ?array
    {
        return $periodos->where('desde', '<=', $corte)->where('hasta', '>=', $corte)
            ->sortBy([['desde', 'desc'], ['hasta', 'desc'], ['id', 'desc']])->first();
    }

    private function solicitudes(): Builder
    {
        $columnas = [
            'id', 'numero_solicitud', 'establecimiento_id', 'reemplazo_personal_id',
            'postulant_profile_id', 'contrato_trabajo_postulant_profile_id', 'rut_reemplazo_normalizado',
            'fecha_inicio_trabajo', 'fecha_termino', 'area_desempeno_id', 'aaee_categoria', 'contrato_trabajo_docx_path',
            'resolucion_docente_docx_path', 'resolucion_docente_firmada_pdf_path',
        ];
        if (Schema::hasColumn('solicitudes_reemplazo', 'padron_personal_snapshot')) {
            $columnas[] = 'padron_personal_snapshot';
        }

        return SolicitudReemplazo::query()->select($columnas)
            ->whereIn('estado', ['aceptada', 'cerrado', 'cerrada'])
            ->whereNotNull('fecha_inicio_trabajo')
            ->whereColumn('fecha_termino', '>=', 'fecha_inicio_trabajo')
            ->with([
                'establecimiento:id,rbd,nombre_establecimiento,sala_cuna',
                'areaDesempeno:id,nombre,slug,estamento',
                'jornadas:id,solicitud_reemplazo_id,financiamiento,reemplazo_total,reemplazo_basica,reemplazo_media',
                'funcionarioTitular:id,estatuto',
                'contratoPostulante:id,user_id',
                'postulante:id,user_id',
                'contratoPostulante.user' => fn ($query) => $query->withTrashed()->select('id', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno'),
                'postulante.user' => fn ($query) => $query->withTrashed()->select('id', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno'),
            ]);
    }

    private function periodo(SolicitudReemplazo $solicitud): ?array
    {
        $persona = $solicitud->contratoPostulante?->user ?? $solicitud->postulante?->user;
        $rut = Rut::normalize($persona?->rut ?: $solicitud->rut_reemplazo_normalizado);
        if (! $rut || ! preg_match('/^0*[1-9][0-9]{0,7}[0-9K]$/D', $rut)) {
            return null;
        }

        $area = $solicitud->areaDesempeno;
        $jornadas = $solicitud->jornadas;
        $esJunji = $solicitud->establecimiento?->sala_cuna
            || preg_match('/(?:junji|sala_cuna|\bvtf\b)/i', $area?->slug ?? '')
            || $jornadas->contains(fn ($jornada) => preg_match('/\b(?:JUNJI|VTF)\b/i', (string) $jornada->financiamiento));
        $estamento = $this->estamento($solicitud);
        if ($esJunji) {
            $estamento ??= match ($area?->estamento) {
                'docente' => 'docente',
                'asistente', 'aaee' => 'aaee',
                default => null,
            };
        }
        if (! $estamento || ($esJunji && ! $solicitud->establecimiento_id)) {
            return null;
        }
        $financiamiento = $jornadas->filter(fn ($fila) => (float) $fila->reemplazo_total > 0)->map(fn ($fila) => [
            'fuente' => trim((string) $fila->financiamiento),
            'horas' => (float) $fila->reemplazo_total,
            'basica' => (float) $fila->reemplazo_basica,
            'media' => (float) $fila->reemplazo_media,
        ])->values()->all();

        return [
            'id' => $solicitud->id,
            'solicitud' => $solicitud->numero_solicitud ?: (string) $solicitud->id,
            'rut' => ltrim(substr($rut, 0, -1), '0').substr($rut, -1),
            'nombre' => $persona?->nombre_completo ?: 'Nombre no informado',
            'estamento' => $esJunji ? 'junji' : $estamento,
            'estamento_origen' => $estamento,
            'desde' => $solicitud->fecha_inicio_trabajo->toDateString(),
            'hasta' => $solicitud->fecha_termino->toDateString(),
            'establecimiento' => $solicitud->establecimiento?->nombre_establecimiento,
            'establecimiento_id' => $solicitud->establecimiento_id,
            'rbd' => $solicitud->establecimiento?->rbd,
            'area_id' => $area?->id,
            'cargo' => $area?->nombre,
            'horas' => $jornadas->whereNotNull('reemplazo_total')->isNotEmpty() ? $jornadas->sum('reemplazo_total') : null,
            'financiamiento' => $financiamiento,
        ];
    }

    private function estamento(SolicitudReemplazo $solicitud): ?string
    {
        $tieneResolucionDocente = $solicitud->resolucion_docente_firmada_pdf_path || $solicitud->resolucion_docente_docx_path;
        $tieneContratoAaee = $solicitud->contrato_trabajo_docx_path || $solicitud->aaee_categoria;
        if ($tieneResolucionDocente !== $tieneContratoAaee) {
            return $tieneResolucionDocente ? 'docente' : 'aaee';
        }

        $estatuto = Str::upper(Str::ascii(trim((string) $solicitud->funcionarioTitular?->estatuto)));

        return match (true) {
            str_contains(str_replace('.', '', $estatuto), 'AAEE'), str_contains($estatuto, 'ASIST') => 'aaee',
            str_contains($estatuto, 'DOC'), in_array($estatuto, ['PROFESOR', 'PROFESORA'], true) => 'docente',
            default => null,
        };
    }

    private function unirPeriodos(Collection $periodos): array
    {
        $cadenas = [];
        $cadena = null;
        foreach ($periodos->sortBy('desde') as $periodo) {
            if ($cadena && $periodo['desde'] <= CarbonImmutable::parse($cadena['hasta'])->addDay()->toDateString()) {
                $cadena['hasta'] = max($cadena['hasta'], $periodo['hasta']);
                $cadena['periodos'][] = $periodo;
                continue;
            }
            if ($cadena) {
                $cadenas[] = $cadena;
            }
            $cadena = ['desde' => $periodo['desde'], 'hasta' => $periodo['hasta'], 'periodos' => [$periodo]];
        }
        if ($cadena) {
            $cadenas[] = $cadena;
        }

        return $cadenas;
    }
}
