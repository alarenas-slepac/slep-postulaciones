<?php

namespace App\Support;

use App\Models\Establecimiento;
use App\Models\AreaDesempeno;
use App\Models\PostulantProfile;
use App\Models\ReemplazoPersonal;
use App\Models\SolicitudReemplazo;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class ReemplazoSolicitudReglaMinima
{
    public const REGLA_DOCENTE = 'docente_art_22j_ley_21040';
    public const REGLA_SALA_CUNA = 'sala_cuna_junji_glosa_02_e';
    public const REGLA_ASISTENTE = 'asistente_manual_reemplazos_rex_1379';
    public const EXCEPCION_CELADOR = 'celador';

    public const MENSAJE_DOCENTE = 'Contratar personal de reemplazo en aquellos casos en que profesionales de la educación pertenecientes a los establecimientos educacionales dependientes del Servicio Local se encuentren imposibilitados para desempeñar sus cargos por un lapso mayor a siete días corridos, previa solicitud motivada del director o directora del establecimiento respectivo. Esta facultad solo podrá ejercerse cuando exista disponibilidad presupuestaria en el Servicio Local.\n\nArt. 22 j) de la Ley 21.040, actualización 25-05-2026.';

    public const MENSAJE_SALA_CUNA = 'Ley de presupuesto Andalién Costa 2026: Glosa 02, letra e). Se podrá contratar personal de reemplazo en los Jardines Infantiles, en aquellos casos en que por cualquier razón, funcionarios de planta o contrata que se desempeñen en el Servicio Local de Educación se encuentren imposibilitados para desempeñar sus cargos por un periodo superior a 7 días.';

    public const MENSAJE_ASISTENTE = 'Las solicitudes de reemplazo de los asistentes de la educación serán aprobadas siempre y cuando la cantidad de días de reemplazo sean igual o superior a 7 días.\n\nEste criterio se encuentra definido en el Manual de procedimientos de reemplazos del Servicio Local Andalién Costa, aprobado por Resolución Exenta N° 1379 del 27-10-2025.';

    public const MENSAJE_CONTINUIDAD_NO_ENCONTRADA = 'No existe una solicitud anterior correlativa para esta continuidad. Verifique que exista una solicitud previa cuya fecha de término sea inmediatamente anterior a la nueva fecha de inicio, y que coincidan el RUT del titular y el RUT del reemplazante.';

    public function evaluar(
        Establecimiento $establecimiento,
        ReemplazoPersonal $titular,
        ?PostulantProfile $reemplazante,
        CarbonInterface $fechaInicio,
        CarbonInterface $fechaTermino,
        bool $continuidadSolicitada = false,
        ?int $excluirSolicitudId = null,
        ?AreaDesempeno $areaDesempeno = null
    ): array {
        $inicio = Carbon::parse($fechaInicio)->startOfDay();
        $termino = Carbon::parse($fechaTermino)->startOfDay();

        if ($termino->lt($inicio)) {
            return $this->respuesta(false, 0, null, null, null, null, 'La fecha término debe ser mayor o igual a la fecha inicio.');
        }

        $duracion = $inicio->diffInDays($termino) + 1;
        $rutTitular = self::normalizarRut($titular->rut ?? '');
        $rutReemplazo = self::normalizarRut($reemplazante?->user?->rut ?? '');
        $continuidadAnterior = null;

        if ($rutTitular !== '' && $rutReemplazo !== '') {
            $continuidadAnterior = $this->buscarSolicitudAnteriorContinuidad(
                $establecimiento,
                $rutTitular,
                $rutReemplazo,
                $inicio,
                $excluirSolicitudId
            );
        }

        if ($continuidadAnterior) {
            return $this->respuesta(true, $duracion, $continuidadAnterior->id, 'continuidad', 'continuidad', null, null, $rutTitular, $rutReemplazo);
        }

        if ($continuidadSolicitada) {
            return $this->respuesta(false, $duracion, null, 'continuidad_no_encontrada', null, null, self::MENSAJE_CONTINUIDAD_NO_ENCONTRADA, $rutTitular, $rutReemplazo);
        }

        $esSalaCuna = (bool) ($establecimiento->sala_cuna ?? false);
        $esDocente = $this->titularEsDocente($titular->estatuto ?? '');
        $esUnidocencia = (bool) ($establecimiento->unidocencia ?? false);

        if ($esSalaCuna) {
            if ($duracion >= 8) {
                return $this->respuesta(true, $duracion, null, self::REGLA_SALA_CUNA, null, null, null, $rutTitular, $rutReemplazo);
            }

            return $this->respuesta(false, $duracion, null, self::REGLA_SALA_CUNA, null, null, self::MENSAJE_SALA_CUNA, $rutTitular, $rutReemplazo);
        }

        if ($esDocente) {
            if ($esUnidocencia) {
                return $this->respuesta(true, $duracion, null, self::REGLA_DOCENTE, 'unidocencia', null, null, $rutTitular, $rutReemplazo);
            }

            if ($duracion >= 8) {
                return $this->respuesta(true, $duracion, null, self::REGLA_DOCENTE, null, null, null, $rutTitular, $rutReemplazo);
            }

            return $this->respuesta(false, $duracion, null, self::REGLA_DOCENTE, null, null, self::MENSAJE_DOCENTE, $rutTitular, $rutReemplazo);
        }

        if ($this->areaEsCelador($areaDesempeno)) {
            return $this->respuesta(true, $duracion, null, self::REGLA_ASISTENTE, self::EXCEPCION_CELADOR, null, null, $rutTitular, $rutReemplazo);
        }

        if ($duracion >= 7) {
            return $this->respuesta(true, $duracion, null, self::REGLA_ASISTENTE, null, null, null, $rutTitular, $rutReemplazo);
        }

        return $this->respuesta(false, $duracion, null, self::REGLA_ASISTENTE, null, null, self::MENSAJE_ASISTENTE, $rutTitular, $rutReemplazo);
    }

    public static function normalizarRut(?string $rut): string
    {
        return strtoupper(preg_replace('/[^0-9Kk]/', '', (string) $rut));
    }

    private function buscarSolicitudAnteriorContinuidad(
        Establecimiento $establecimiento,
        string $rutTitularNormalizado,
        string $rutReemplazoNormalizado,
        CarbonInterface $nuevaFechaInicio,
        ?int $excluirSolicitudId = null
    ): ?SolicitudReemplazo {
        $fechaTerminoAnterior = Carbon::parse($nuevaFechaInicio)->subDay()->toDateString();

        $candidatas = SolicitudReemplazo::query()
            ->with(['funcionarioTitular', 'postulante.user', 'contratoPostulante.user'])
            ->where('establecimiento_id', (int) $establecimiento->id)
            ->whereDate('fecha_termino', $fechaTerminoAnterior)
            ->when($excluirSolicitudId, fn($q) => $q->where('id', '<>', $excluirSolicitudId))
            ->whereNotIn('estado', ['anulada', 'anulado', 'rechazada', 'rechazada_uatp', 'rechazada_plani'])
            ->orderByDesc('fecha_termino')
            ->orderByDesc('id')
            ->get();

        foreach ($candidatas as $solicitud) {
            $rutTitularAnterior = self::normalizarRut($solicitud->funcionarioTitular?->rut ?? '');
            $rutReemplazoAnterior = self::normalizarRut($solicitud->postulante?->user?->rut ?? '');

            if ($rutReemplazoAnterior === '') {
                $rutReemplazoAnterior = self::normalizarRut($solicitud->contratoPostulante?->user?->rut ?? '');
            }

            if ($rutTitularAnterior === $rutTitularNormalizado && $rutReemplazoAnterior === $rutReemplazoNormalizado) {
                return $solicitud;
            }
        }

        return null;
    }

    private function titularEsDocente(?string $estatuto): bool
    {
        $e = strtoupper(trim((string) $estatuto));
        if ($e === '') {
            return false;
        }

        return in_array($e, ['DOCENTE', 'PROFESOR', 'PROFESORA'], true) || str_contains($e, 'DOC');
    }

    private function areaEsCelador(?AreaDesempeno $areaDesempeno): bool
    {
        if (!$areaDesempeno) {
            return false;
        }

        $slug = mb_strtolower(trim((string) $areaDesempeno->slug), 'UTF-8');
        $nombre = mb_strtolower(trim((string) $areaDesempeno->nombre), 'UTF-8');

        return $slug === 'celador'
            || $nombre === 'celador'
            || str_contains($slug, 'celador')
            || str_contains($nombre, 'celador');
    }

    private function respuesta(
        bool $permitido,
        int $duracionDias,
        ?int $solicitudAnteriorId,
        ?string $reglaAplicada,
        ?string $excepcion,
        ?string $observacion,
        ?string $mensaje,
        ?string $rutTitularNormalizado = null,
        ?string $rutReemplazoNormalizado = null
    ): array {
        return [
            'permitido' => $permitido,
            'duracion_dias' => $duracionDias,
            'es_continuidad' => $solicitudAnteriorId !== null,
            'solicitud_anterior_id' => $solicitudAnteriorId,
            'regla_minima_aplicada' => $reglaAplicada,
            'regla_minima_excepcion' => $excepcion,
            'observacion' => $observacion,
            'mensaje' => $mensaje,
            'rut_titular_normalizado' => $rutTitularNormalizado,
            'rut_reemplazo_normalizado' => $rutReemplazoNormalizado,
        ];
    }
}
