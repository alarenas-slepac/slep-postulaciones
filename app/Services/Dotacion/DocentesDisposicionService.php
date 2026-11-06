<?php

namespace App\Services\Dotacion;

use App\Models\DotacionDocenteDistribucion;
use App\Models\Establecimiento;
use App\Support\DotacionDisposicionDatos;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocentesDisposicionService
{
    public const ROLES = ['admin', 'coordinador_gdp', 'coordinador_uatp', 'supervisor_plani'];

    public function __construct(private DotacionDisposicionDatos $datos) {}

    public static function clave(array $fila): string
    {
        return implode('|', [$fila['origen_id'], $fila['anio'], DotacionEstablecimientoCalculator::normalizeRut($fila['rut']), $fila['bloque'], $fila['tipo_horas']]);
    }

    public static function establecimientos(?int $anio = null)
    {
        $query = Establecimiento::query()->where(fn ($q) => $q->whereNull('sala_cuna')->orWhere('sala_cuna', false));
        if ($anio !== null) {
            $query->whereIn('id', DotacionRevisionService::vigentes($anio)->select('establecimiento_id'));
        }
        return $query;
    }

    public function listado(int $anio): Collection
    {
        $filas = collect();
        self::establecimientos($anio)->orderBy('id')->chunkById(8, function ($ees) use ($anio, &$filas): void {
            foreach ($ees as $ee) { $filas = $filas->concat($this->datos->datos($ee, $anio)['filas']); }
        });
        $filas = $filas->keyBy(fn ($f) => self::clave($f));
        $historial = DotacionDocenteDistribucion::query()->where('anio', $anio)->whereIn('origen_id', self::establecimientos($anio)->select('id'))->orderByDesc('id')->get();
        return $this->completar($filas, $historial);
    }

    public function detalle(array $datos): ?array
    {
        $ee = self::establecimientos()->findOrFail($datos['origen_id']);
        $key = self::clave($datos);
        $filas = $this->datos->datos($ee, (int) $datos['anio'])['filas']->filter(fn ($f) => self::clave($f) === $key)->keyBy(fn ($f) => self::clave($f));
        $historial = DotacionDocenteDistribucion::query()->where('anio', $datos['anio'])->where('origen_id', $ee->id)
            ->where('docente_rut_normalizado', DotacionEstablecimientoCalculator::normalizeRut($datos['rut']))
            ->where('bloque', $datos['bloque'])->where('tipo_horas', $datos['tipo_horas'])->orderByDesc('id')->get();
        $fila = $this->completar($filas, $historial)->first();
        if ($fila) { $fila['finalizado'] = DotacionRevisionService::finalizado((int) $ee->id, (int) $datos['anio']); }
        return $fila;
    }

    private function completar(Collection $filas, Collection $historial): Collection
    {
        $porClave = $historial->groupBy(fn ($r) => self::clave($r->antecedente));
        foreach ($porClave as $key => $registros) {
            if (! $filas->has($key)) {
                $filas->put($key, array_replace($registros->first()->antecedente, ['horas' => 0, 'situacion' => 'Origen sin saldo actual', 'sin_origen' => true]));
            }
        }
        return $filas->map(function ($fila, $key) use ($porClave): array {
            $fila['clave'] = $key;
            $fila['historial'] = $porClave->get($key, collect());
            $fila['distribuidas'] = round((float) $fila['historial']->whereNull('anulada_at')->sum('horas'), 2);
            $saldo = (int) round($fila['horas'] * 100) - (int) round($fila['distribuidas'] * 100);
            $fila['saldo'] = max(0, $saldo) / 100;
            $fila['exceso'] = max(0, -$saldo) / 100;
            $fila['estado'] = $saldo < 0 ? 'regularizar' : ($fila['distribuidas'] > 0 ? ($saldo === 0 ? 'asignado' : 'parcial') : 'pendiente');
            return $fila;
        })->sort(fn ($a, $b) => (($a['prioridad'] ?? 6) <=> ($b['prioridad'] ?? 6))
            ?: ((trim((string) ($a['fecha_antiguedad'] ?? '')) ?: '9999-12-31') <=> (trim((string) ($b['fecha_antiguedad'] ?? '')) ?: '9999-12-31'))
            ?: strcmp($a['nombre'], $b['nombre']) ?: strcmp($a['clave'], $b['clave']))->values();
    }

    public function destinos(int $anio, string $bloque, int $origen): Collection
    {
        return self::establecimientos($anio)->whereKeyNot($origen)->get()->map(function ($ee) use ($anio, $bloque): array {
            $necesidad = $this->datos->referenciasDestino($ee, $anio)[$bloque];
            return ['id' => $ee->id, 'rbd' => $ee->rbd, 'nombre' => $ee->nombre_establecimiento] + $necesidad;
        })->sort(function ($a, $b): int {
            return ($b['horas'] ?? -1) <=> ($a['horas'] ?? -1) ?: strnatcasecmp($a['nombre'], $b['nombre']);
        })->values();
    }

    public function guardar(array $datos, int $userId, ?int $corregirId = null): DotacionDocenteDistribucion
    {
        return DB::transaction(function () use ($datos, $userId, $corregirId): DotacionDocenteDistribucion {
            // Orden común para dos establecimientos; evita ciclos entre redistribuciones cruzadas.
            $bloqueados = self::establecimientos()->whereIn('id', [$datos['origen_id'], $datos['destino_id']])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $ee = $bloqueados->get((int) $datos['origen_id']);
            abort_unless($ee, 404);
            $rut = DotacionEstablecimientoCalculator::normalizeRut($datos['rut']);
            $clave = ['origen_id' => (int) $ee->id, 'anio' => (int) $datos['anio'], 'rut' => $rut,
                'bloque' => $datos['bloque'], 'tipo_horas' => $datos['tipo_horas']];
            $repetido = DotacionDocenteDistribucion::where('solicitud_uuid', $datos['solicitud_uuid'])->first();
            if ($repetido) {
                abort_unless($repetido->created_by === $userId && self::clave($repetido->antecedente) === self::clave($clave)
                    && (int) $repetido->destino_id === (int) $datos['destino_id']
                    && (int) round((float) $repetido->horas * 100) === (int) round((float) $datos['horas'] * 100)
                    && $repetido->reemplaza_id === $corregirId, 409, 'La solicitud ya fue utilizada con otros datos.');
                return $repetido;
            }
            if (! DotacionRevisionService::finalizado((int) $ee->id, $clave['anio'])) {
                throw ValidationException::withMessages(['origen_id' => 'El establecimiento de origen debe estar revisado y finalizado para este año.']);
            }
            $anteriores = DotacionDocenteDistribucion::query()->where('origen_id', $ee->id)->where('anio', $clave['anio'])
                ->where('docente_rut_normalizado', $rut)->where('bloque', $clave['bloque'])->where('tipo_horas', $clave['tipo_horas'])
                ->lockForUpdate()->get();
            $anterior = $corregirId ? $anteriores->firstWhere('id', $corregirId) : null;
            abort_if($corregirId && (! $anterior || $anterior->anulada_at), 409, 'La distribución ya cambió. Actualice el detalle.');
            $fuente = $this->datos->datos($ee, $clave['anio'])['filas']->first(fn ($f) => self::clave($f) === self::clave($clave));
            $horas = (int) round((float) $datos['horas'] * 100);
            $usadas = (int) round((float) $anteriores->whereNull('anulada_at')->reject(fn ($r) => $r->id === $corregirId)->sum('horas') * 100);
            $saldo = (int) round((float) ($fuente['horas'] ?? 0) * 100) - $usadas;
            if (! $fuente || $horas <= 0 || $horas > $saldo) {
                throw ValidationException::withMessages(['horas' => 'Las horas superan el saldo actual del docente: '.DotacionEstablecimientoCalculator::formatHoras(max(0, $saldo) / 100).' h. Revise o anule las distribuciones anteriores.']);
            }
            $destino = self::establecimientos()->whereKey($datos['destino_id'])->first();
            if (! $destino || $destino->id === $ee->id || ! DotacionRevisionService::finalizado((int) $destino->id, $clave['anio'])) {
                throw ValidationException::withMessages(['destino_id' => 'Seleccione un establecimiento de destino distinto del origen y participante de dotación.']);
            }
            // La necesidad del destino es informativa y deliberadamente no participa del límite.
            if ($anterior) {
                $anterior->forceFill(['anulada_at' => now(), 'anulada_by' => $userId, 'motivo_anulacion' => $datos['motivo_correccion']])->save();
            }
            return DotacionDocenteDistribucion::create([
                'solicitud_uuid' => $datos['solicitud_uuid'], 'anio' => $clave['anio'], 'origen_id' => $ee->id,
                'destino_id' => $destino->id, 'docente_rut_normalizado' => $rut, 'bloque' => $clave['bloque'], 'tipo_horas' => $clave['tipo_horas'],
                'horas' => $horas / 100, 'antecedente' => $fuente, 'observacion' => $datos['observacion'] ?? null,
                'created_by' => $userId, 'reemplaza_id' => $corregirId,
            ]);
        }, 3);
    }

    public function anular(int $id, int $anio, string $motivo, int $userId): void
    {
        $origen = DotacionDocenteDistribucion::query()->where('anio', $anio)->findOrFail($id)->origen_id;
        DB::transaction(function () use ($id, $anio, $motivo, $userId, $origen): void {
            Establecimiento::whereKey($origen)->lockForUpdate()->firstOrFail();
            $registro = DotacionDocenteDistribucion::where('anio', $anio)->lockForUpdate()->findOrFail($id);
            if (! $registro->anulada_at) {
                $registro->forceFill(['anulada_at' => now(), 'anulada_by' => $userId, 'motivo_anulacion' => $motivo])->save();
            }
        }, 3);
    }
}
