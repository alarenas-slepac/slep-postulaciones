<?php

namespace App\Services\Dotacion;

use App\Models\DotacionDocenteDistribucion;
use App\Models\Establecimiento;
use App\Support\DotacionDisposicionDatos;
use App\Support\DotacionDisposicionCupos;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionLecturaCache;
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

    private function fuentes(Establecimiento $ee, int $anio, bool $cache): array
    {
        $calcular = fn () => $this->datos->datos($ee, $anio);
        return $cache ? app(DisposicionLecturaCache::class)->recordar('fuente:'.$ee->id.':'.$anio, $calcular) : $calcular();
    }

    public function listado(int $anio, bool $cache = false): Collection
    {
        $calcular = fn () => DotacionLecturaCache::ejecutar(function () use ($anio, $cache): Collection {
            $filas = collect();
            self::establecimientos($anio)->orderBy('id')->chunkById(50, function ($ees) use ($anio, $cache, &$filas): void {
                foreach ($ees as $ee) { $filas = $filas->concat($this->fuentes($ee, $anio, $cache)['filas']); }
            });
            return $filas;
        });
        $filas = $cache ? app(DisposicionLecturaCache::class)->recordar('nomina:'.$anio, $calcular) : $calcular();
        // El cierre anual se verifica de nuevo, incluso con una fuente reutilizada.
        $vigentes = self::establecimientos($anio)->pluck('id')->all();
        $filas = collect($filas)->whereIn('origen_id', $vigentes);
        $filas = $filas->keyBy(fn ($f) => self::clave($f));
        $historial = DotacionDocenteDistribucion::query()->where('anio', $anio)->whereIn('origen_id', self::establecimientos($anio)->select('id'))->orderByDesc('id')->get();
        return $this->completar($filas, $historial);
    }

    public function detalle(array $datos, bool $cache = false): ?array
    {
        $ee = self::establecimientos()->findOrFail($datos['origen_id']);
        $key = self::clave($datos);
        $filas = collect($this->fuentes($ee, (int) $datos['anio'], $cache)['filas'])->filter(fn ($f) => self::clave($f) === $key)->keyBy(fn ($f) => self::clave($f));
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

    public function destinos(int $anio, string $bloque, int $origen, bool $cache = false, ?int $corregirId = null): Collection
    {
        $ocupacion = DotacionDocenteDistribucion::where('anio', $anio)->whereNull('anulada_at')
            ->when($corregirId, fn ($q) => $q->whereKeyNot($corregirId))->get()->groupBy('destino_id');
        return DotacionLecturaCache::ejecutar(fn () => self::establecimientos($anio)->whereKeyNot($origen)->get()->map(function ($ee) use ($anio, $bloque, $cache, $ocupacion): array {
            $calcular = fn () => $this->datos->referenciasDestino($ee, $anio);
            $referencias = $cache ? app(DisposicionLecturaCache::class)->recordar('destino:cupos:'.$ee->id.':'.$anio, $calcular) : $calcular();
            $necesidad = $referencias[$bloque];
            return ['id' => $ee->id, 'rbd' => $ee->rbd, 'nombre' => $ee->nombre_establecimiento, 'bloques' => $referencias,
                'cupos_destino' => DotacionDisposicionCupos::opciones($referencias, $ocupacion->get($ee->id, collect()))->all()] + $necesidad;
        }))->sort(function ($a, $b): int {
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
                    && ($repetido->antecedente['destino_cupo']['clave'] ?? null) === ($datos['destino_cupo'] ?? null)
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
            $cupo = $this->cupoAlGuardar($destino, $clave['anio'], $datos, $anterior);
            if ($cupo) { $fuente['destino_cupo'] = $cupo; }
            // Se permite superar la necesidad del cupo; el límite sigue siendo el origen.
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

    private function cupoAlGuardar(Establecimiento $destino, int $anio, array $datos, ?DotacionDocenteDistribucion $anterior): ?array
    {
        $clave = $datos['destino_cupo'] ?? null;
        $previo = $anterior?->antecedente['destino_cupo'] ?? null;
        $mismoDestino = $anterior && (int) $anterior->destino_id === (int) $destino->id;
        // Los registros históricos sin cupo se pueden corregir en su destino original.
        if (! $clave && $mismoDestino && ! $previo) { return null; }
        $ocupacion = DotacionDocenteDistribucion::where('anio', $anio)->where('destino_id', $destino->id)->whereNull('anulada_at')
            ->when($anterior, fn ($q) => $q->whereKeyNot($anterior->id))->lockForUpdate()->get();
        $cupos = DotacionDisposicionCupos::opciones($this->datos->referenciasDestino($destino, $anio), $ocupacion);
        $cupo = $cupos->firstWhere('clave', $clave);
        if (! $cupo && $mismoDestino && $previo && $previo['clave'] === $clave) {
            // Permite regularizar una vinculación histórica si cambió la fuente del cupo.
            return $previo;
        }
        if (! $clave || ! $cupo || $cupo['disponibles'] <= 0) {
            throw ValidationException::withMessages(['destino_cupo' => 'Seleccione un cupo disponible del destino. Sus horas pueden haber sido destinadas por otro usuario; actualice la selección.']);
        }

        return array_diff_key($cupo, array_flip(['destinaciones', 'label', 'estado']));
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
