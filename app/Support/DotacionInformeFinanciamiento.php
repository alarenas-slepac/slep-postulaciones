<?php

namespace App\Support;

use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use App\Models\ReemplazoPersonal;
use App\Services\Dotacion\ContratacionHabilitacionService;
use App\Services\Padron\PadronPeriodoService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DotacionInformeFinanciamiento
{
    public const FUENTES = [
        'general' => 'Sub. General',
        'pie' => 'PIE',
        'sep' => 'SEP',
        'pro_retencion' => 'Pro-Retención',
    ];

    public function __construct(
        private readonly PadronPeriodoService $padron,
        private readonly DotacionInformePlanes $planes,
        private readonly DotacionInformePlanesNiveles $niveles,
    ) {}

    /** @return array{anio: int, mes: int}|null */
    public function periodoAsistentes(): ?array
    {
        if (! Schema::hasTable('reemplazos_personal')) {
            return null;
        }

        $periodo = $this->padron->periodos()->first();

        return $periodo ? ['anio' => (int) $periodo->anio, 'mes' => (int) $periodo->mes] : null;
    }

    public function fila(Establecimiento $establecimiento, int $anio, ?array $periodoAsistentes): array
    {
        $id = (int) $establecimiento->id;
        $horas = array_fill_keys(array_keys(self::FUENTES), 0.0);
        $justificados = array_flip(DotacionSobredotacionJustificacion::rutsJustificados($id, $anio));
        $docentes = DotacionEstablecimientoCalculator::docentes($establecimiento, $anio, false)
            ->concat(app(ContratacionHabilitacionService::class)->docentesVirtuales($establecimiento, $anio, false));
        $ruts = $docentes->pluck('rut_normalizado')->filter()->flip()->all();

        foreach (DotacionAsignacionCalculator::assignmentsFor($establecimiento, $anio) as $asignacion) {
            $rut = DotacionEstablecimientoCalculator::normalizeRut($asignacion->docente_rut_normalizado ?: $asignacion->docente_rut);
            if (! isset($ruts[$rut]) || isset($justificados[$rut])
                || DotacionAsignacionCalculator::coverageEstamento($asignacion) !== 'docente'
                || $asignacion->tipo_asignacion === 'reserva_no_normativa') {
                continue;
            }
            $fuente = DotacionAsignacionCalculator::esAsignacionPie($asignacion)
                ? 'pie'
                : (self::fuente(self::normalizar($asignacion->subvencion)) ?? 'general');
            $horas[$fuente] += max(0.0, (float) $asignacion->horas_contrato);
        }

        $otras = [];
        if ($periodoAsistentes !== null && ! self::esJardinOSalaCuna($establecimiento)) {
            $lineas = $this->padron->consultaMensual($periodoAsistentes['anio'], $periodoAsistentes['mes'])
                ->where('reemplazos_personal.establecimiento_id', $id)->sinReemplazoSuplencia()
                ->when(Schema::hasColumn('reemplazos_personal', 'vigente'), fn ($q) => $q->where('reemplazos_personal.vigente', true))
                ->get()->unique(fn (ReemplazoPersonal $fila) => DotacionEstablecimientoCalculator::claveLineaContractual($fila));
            foreach ($lineas as $linea) {
                $estatuto = self::normalizar($linea->estatuto);
                $financiamiento = self::normalizar($linea->financiamiento);
                if (! preg_match('/^(?:AAEE|A A E E|ASISTENTES? (?:DE LA )?EDUCACION)(?: |$)/', $estatuto)
                    || preg_match('/\b(?:JUNJI|VTF)\b/', $estatuto.' '.$financiamiento)
                    || in_array($financiamiento, ['', '0', 'NA', 'N A', 'NO APLICA', 'NO INFORMADO', 'NO INFORMADA',
                        'SIN INFORMACION', 'SIN FINANCIAMIENTO', 'SIN FUENTE', 'SIN CLASIFICAR'], true)) {
                    continue;
                }

                $jornada = max(0.0, (float) ($linea->jornada ?? ((float) $linea->jornada_basica + (float) $linea->jornada_media)));
                if ($fuente = self::fuente($financiamiento)) {
                    $horas[$fuente] += $jornada;
                } elseif ($jornada > 0) {
                    $otras[$financiamiento] ??= ['fuente' => trim($linea->financiamiento), 'horas' => 0.0];
                    $otras[$financiamiento]['horas'] += $jornada;
                }
            }
        }

        ksort($otras, SORT_NATURAL);

        return [
            'rbd' => (string) $establecimiento->rbd,
            'nombre' => (string) $establecimiento->nombre_establecimiento,
            'horas' => array_map(fn ($cantidad) => round($cantidad, 2), $horas),
            'otras_aaee' => array_values($otras),
            'pie_proyectado' => DotacionEstablecimientoCalculator::horasContratoPieEstablecimiento($establecimiento, $anio, soloCursosActivos: true),
            'oferta_educativa' => $this->planes->ofertaEducativa($establecimiento, $anio),
            'niveles_modalidades' => $this->niveles->presenciaPorEstablecimiento($establecimiento, $anio),
        ];
    }

    private static function esJardinOSalaCuna(Establecimiento $establecimiento): bool
    {
        $tipo = self::normalizar($establecimiento->tipo_estab.' '.$establecimiento->clasificacion);
        $nombre = self::normalizar($establecimiento->nombre_establecimiento);

        return (bool) $establecimiento->sala_cuna
            || preg_match('/\b(?:JARDIN(?:ES)?|SALAS? CUNAS?|JUNJI|VTF)\b/', $tipo)
            || preg_match('/^(?:JARDIN(?:ES)?|SALAS? CUNAS?|JUNJI|VTF)\b/', $nombre);
    }

    private static function normalizar(?string $texto): string
    {
        return Str::of($texto ?? '')->ascii()->upper()->replaceMatches('/[^A-Z0-9]+/', ' ')->squish()->toString();
    }

    private static function fuente(string $texto): ?string
    {
        return match (true) {
            (bool) preg_match('/^(?:SUB(?:V(?:ENCION)?)? )?(?:GENERAL|GRAL|G)$|^SUBVENCION GENERAL DE EDUCACION$/', $texto) => 'general',
            in_array($texto, ['PIE', 'P I E', 'PROGRAMA DE INTEGRACION ESCOLAR', 'PROGRAMA INTEGRACION ESCOLAR'], true) => 'pie',
            in_array($texto, ['SEP', 'S E P', 'SUBV SEP', 'SUBVENCION SEP', 'SUBVENCION ESCOLAR PREFERENCIAL'], true) => 'sep',
            (bool) preg_match('/^(?:SUB(?:V(?:ENCION)?)? (?:ESCOLAR )?)?PRO ?RETENCION$/', $texto) => 'pro_retencion',
            default => null,
        };
    }
}
