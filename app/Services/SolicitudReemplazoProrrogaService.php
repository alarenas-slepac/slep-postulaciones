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
            ->mapWithKeys(fn (array $periodo) => [$periodo['estamento'].':'.$periodo['rut'] => true])
            ->all();

        if ($candidatos === []) {
            return collect();
        }

        // La cadena puede comenzar antes del semestre evaluado.
        $periodos = $base->where('fecha_inicio_trabajo', '<=', $hastaProrroga)
            ->with('jornadas:id,solicitud_reemplazo_id,reemplazo_total')
            ->lazyById(500)
            ->map(fn (SolicitudReemplazo $solicitud) => $this->periodo($solicitud))
            ->filter(fn (?array $periodo) => $periodo && isset($candidatos[$periodo['estamento'].':'.$periodo['rut']]))
            ->collect()
            ->groupBy(fn (array $periodo) => $periodo['estamento'].':'.$periodo['rut']);

        return $periodos->map(function (Collection $tramos) use ($inicioDiciembre, $finDiciembre, $desdeProrroga, $hastaProrroga): ?array {
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
                $ultimo = $antecedentes->sortBy([['hasta', 'desc'], ['desde', 'desc'], ['id', 'desc']])->first();

                return [
                    'rut' => $ultimo['rut'],
                    'nombre' => $ultimo['nombre'],
                    'estamento' => $ultimo['estamento'],
                    'inicio_continuidad' => $cadena['desde'],
                    'fecha_corte' => $corte,
                    'periodos' => $antecedentes->all(),
                    'cobertura' => $cobertura,
                ];
            }

            return null;
        })->filter();
    }

    public function vigencia(int $anio): array
    {
        $siguiente = $anio + 1;

        return ['desde' => "{$siguiente}-01-01", 'hasta' => "{$siguiente}-02-28"];
    }

    private function solicitudes(): Builder
    {
        $columnas = [
            'id', 'numero_solicitud', 'establecimiento_id', 'reemplazo_personal_id',
            'postulant_profile_id', 'contrato_trabajo_postulant_profile_id', 'rut_reemplazo_normalizado',
            'fecha_inicio_trabajo', 'fecha_termino', 'aaee_categoria', 'contrato_trabajo_docx_path',
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
                'establecimiento:id,rbd,nombre_establecimiento',
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
        $estamento = $this->estamento($solicitud);
        if (! $rut || ! preg_match('/^0*[1-9][0-9]{0,7}[0-9K]$/D', $rut) || ! $estamento) {
            return null;
        }

        $jornadas = $solicitud->getRelations()['jornadas'] ?? collect();

        return [
            'id' => $solicitud->id,
            'solicitud' => $solicitud->numero_solicitud ?: (string) $solicitud->id,
            'rut' => ltrim(substr($rut, 0, -1), '0').substr($rut, -1),
            'nombre' => $persona?->nombre_completo ?: 'Nombre no informado',
            'estamento' => $estamento,
            'desde' => $solicitud->fecha_inicio_trabajo->toDateString(),
            'hasta' => $solicitud->fecha_termino->toDateString(),
            'establecimiento' => $solicitud->establecimiento?->nombre_establecimiento,
            'rbd' => $solicitud->establecimiento?->rbd,
            'horas' => $jornadas->whereNotNull('reemplazo_total')->isNotEmpty() ? $jornadas->sum('reemplazo_total') : null,
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
