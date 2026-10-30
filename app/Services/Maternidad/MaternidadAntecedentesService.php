<?php

namespace App\Services\Maternidad;

use App\Models\LicenciaMedica;
use App\Models\MaternidadCaso;
use App\Models\SolicitudReemplazo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

/** Consulta de lectura por identidad y fechas; no vincula ni modifica documentos de origen. */
final class MaternidadAntecedentesService
{
    public function obtener(MaternidadCaso $caso): array
    {
        $inicio = $caso->fpc ?? $caso->fecha_certificado_embarazo ?? $caso->fnh ?? $caso->fecha_registro;
        $terminos = collect([$caso->fecha_termino_fuero, $caso->fecha_termino_lactancia,
            $caso->postnatal_parental ? $caso->fecha_termino_postnatal : null])->filter();
        $fin = $terminos->isEmpty() ? CarbonImmutable::today() : $terminos->sort()->last();
        $periodo = ['inicio' => $inicio->toDateString(), 'fin' => $fin->toDateString(),
            'fin_provisional' => $terminos->isEmpty(), 'valido' => $inicio->lte($fin)];
        $licencias = $this->vacio('licencias_page');
        $reemplazos = $this->vacio('reemplazos_page');
        $hayLicencias = Schema::hasTable('licencias_medicas');
        $hayReemplazos = Schema::hasTable('solicitudes_reemplazo');
        if ($periodo['valido']) {
            if ($hayLicencias) $licencias = $this->licencias($caso->rut, $periodo);
            if ($hayReemplazos) $reemplazos = $this->reemplazos($caso->rut, $periodo);
        }
        return compact('periodo', 'licencias', 'reemplazos', 'hayLicencias', 'hayReemplazos');
    }

    private function licencias(string $rut, array $periodo): LengthAwarePaginator
    {
        [$cuerpo, $dv] = explode('-', $rut);
        $clave = $cuerpo.$dv;
        $query = LicenciaMedica::query()->select(['id', 'folio_licencia', 'fecha_inicio', 'fecha_termino',
            'establecimiento_nombre', 'tipo_licencia', 'tipo_licencia_glosa', 'estado_actual', 'estado_administrativo_codigo'])
            ->where(function (Builder $q) use ($clave, $cuerpo, $dv) {
                $q->whereRaw($this->sqlRut('rut_normalizado').' = ?', [$clave])
                    ->orWhere(function (Builder $legacy) use ($clave, $cuerpo, $dv) {
                        $legacy->whereRaw($this->sqlRut('rut_normalizado')." = ''")->where(function (Builder $q) use ($clave, $cuerpo, $dv) {
                            $q->whereRaw($this->sqlRut('rut_formateado').' = ?', [$clave])
                                ->orWhere(function (Builder $q) use ($cuerpo, $dv) {
                                    $q->whereRaw($this->sqlRut('rut_funcionario').' = ?', [$cuerpo])->whereRaw("UPPER(TRIM(dv_funcionario)) = ?", [$dv]);
                                });
                        });
                    });
            });
        $this->solapadas($query, $periodo, 'fecha_inicio');
        return $query->orderByDesc('fecha_inicio')->orderByDesc('id')->paginate(10, ['*'], 'licencias_page')->withQueryString()->fragment('mat-licencias');
    }

    private function reemplazos(string $rut, array $periodo): LengthAwarePaginator
    {
        $base = SolicitudReemplazo::query();
        // Fechas efectivas de contrato/OT prevalecen sobre la fecha inicialmente solicitada.
        $inicio = 'COALESCE(contrato_trabajo_fecha_inicio_trabajo, fecha_inicio_trabajo, fecha_inicio)';
        $this->solapadas($base, $periodo, $inicio);
        $titulares = (clone $base)->titularRutComparable($rut);
        $reemplazantes = (clone $base)->where(fn (Builder $q) => $this->comoReemplazante($q, $rut));
        $pagina = (clone $base)->where(function (Builder $q) use ($titulares, $reemplazantes) {
            $q->whereIn('id', (clone $titulares)->select('id'))->orWhereIn('id', (clone $reemplazantes)->select('id'));
        })->with('establecimiento:id,nombre_establecimiento')->orderByDesc('fecha_inicio')->orderByDesc('id')
            ->paginate(10, ['id', 'establecimiento_id', 'numero_solicitud', 'fecha_inicio', 'fecha_inicio_trabajo',
                'contrato_trabajo_fecha_inicio_trabajo', 'fecha_termino', 'estado'], 'reemplazos_page')->withQueryString()->fragment('mat-reemplazos');
        $ids = $pagina->getCollection()->modelKeys();
        $idsTitular = (clone $titulares)->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $idsReemplazante = (clone $reemplazantes)->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $pagina->getCollection()->each(function ($solicitud) use ($idsTitular, $idsReemplazante) {
            $solicitud->participaciones_maternidad = array_values(array_filter([
                in_array($solicitud->id, $idsTitular, true) ? 'Titular' : null,
                in_array($solicitud->id, $idsReemplazante, true) ? 'Reemplazante' : null,
            ]));
            $inicio = $solicitud->contrato_trabajo_fecha_inicio_trabajo ?? $solicitud->fecha_inicio_trabajo ?? $solicitud->fecha_inicio;
            $solicitud->inicio_maternidad = $inicio ? CarbonImmutable::parse($inicio)->toDateString() : null;
        });
        return $pagina;
    }

    private function comoReemplazante(Builder $q, string $rut): void
    {
        $clave = str_replace('-', '', $rut);
        $persona = fn (Builder $u) => $u->withTrashed()->whereRaw($this->sqlRut('rut').' = ?', [$clave]);
        // No adjudicar la propuesta anterior a quien fue contratada en su lugar.
        $q->where(function (Builder $contratada) use ($persona) {
            $contratada->whereNotNull('contrato_trabajo_postulant_profile_id')->whereHas('contratoPostulante.user', $persona);
        })->orWhere(function (Builder $sinContrato) use ($persona, $clave) {
            $sinContrato->whereNull('contrato_trabajo_postulant_profile_id')->where(function (Builder $q) use ($persona, $clave) {
                $q->whereHas('postulante.user', $persona)->orWhere(function (Builder $legacy) use ($clave) {
                    $legacy->whereNull('postulant_profile_id')->whereRaw($this->sqlRut('rut_reemplazo_normalizado').' = ?', [$clave]);
                });
            });
        });
    }

    private function solapadas(Builder $query, array $periodo, string $inicio): void
    {
        $query->whereRaw($inicio.' <= ?', [$periodo['fin']])->whereRaw('COALESCE(fecha_termino, '.$inicio.') >= ?', [$periodo['inicio']]);
        // Excluir períodos contradictorios y no interpretar un término desconocido como vigencia indefinida.
        $query->whereRaw('(fecha_termino IS NULL OR fecha_termino >= '.$inicio.')');
    }

    private function sqlRut(string $columna): string
    {
        return "UPPER(REPLACE(REPLACE(REPLACE(COALESCE({$columna}, ''), '.', ''), '-', ''), ' ', ''))";
    }

    private function vacio(string $pagina): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 10, 1, ['pageName' => $pagina]);
    }
}
