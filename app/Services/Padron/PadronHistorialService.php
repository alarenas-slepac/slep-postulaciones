<?php

namespace App\Services\Padron;

use App\Models\ReemplazoPersonalHistorico;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class PadronHistorialService
{
    public const DOCUMENTOS = ['solicitudes_reemplazo', 'cometidos_funcionarios', 'incumplimientos_laborales'];

    private const CAMPOS = [
        'id', 'establecimiento_id', 'rbd', 'rut', 'nombre', 'fecha_nacimiento',
        'fecha_ingreso', 'fecha_termino', 'fecha_antiguedad', 'tipocontrato',
        'financiamiento', 'estatuto', 'escalafon', 'anio', 'mes', 'jornada',
        'jornada_basica', 'jornada_media', 'bienios', 'tramo', 'vigente',
    ];

    public function capturar(int $personalId, string $origen): array
    {
        $row = DB::table('reemplazos_personal')->where('id', $personalId)->lockForUpdate()->first();
        if (! $row) {
            throw ValidationException::withMessages(['reemplazo_personal_id' => 'No existe el registro contractual para conservar su copia histórica.']);
        }
        return [
            'version' => 1, 'capturado_at' => now()->toIso8601String(), 'origen' => $origen,
            'personal' => array_intersect_key((array) $row, array_flip(self::CAMPOS)),
        ];
    }

    public function leer(array $snapshot, int $personalId): ReemplazoPersonalHistorico
    {
        if (($snapshot['version'] ?? null) !== 1 || ! is_array($snapshot['personal'] ?? null)
            || (int) ($snapshot['personal']['id'] ?? 0) !== $personalId) {
            throw ValidationException::withMessages(['padron' => 'La copia contractual no corresponde al funcionario del documento. Requiere revisión; no se sustituye por datos actuales.']);
        }
        $personal = new ReemplazoPersonalHistorico;
        $personal->setRawAttributes($snapshot['personal'], true);
        $personal->exists = true;
        return $personal;
    }

    /** Debe ejecutarse dentro de la misma transacción y ANTES de modificar personal. */
    public function congelarReferencias(array $personalIds): int
    {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('La protección histórica debe compartir la transacción de actualización del padrón.');
        }
        $total = 0;
        foreach (self::DOCUMENTOS as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'padron_personal_snapshot')) {
                throw ValidationException::withMessages(['revision' => 'Falta la migración de protección histórica en '.$table.'.']);
            }
            foreach (array_chunk(array_values(array_unique($personalIds)), 300) as $ids) {
                $snapshots = [];
                $rows = DB::table($table)->whereIn('reemplazo_personal_id', $ids)
                    ->orderBy('id')->lockForUpdate()->get(['id', 'reemplazo_personal_id', 'padron_personal_snapshot']);
                foreach ($rows as $row) {
                    $id = (int) $row->reemplazo_personal_id;
                    if ($row->padron_personal_snapshot !== null) {
                        $this->leer(json_decode($row->padron_personal_snapshot, true, 512, JSON_THROW_ON_ERROR), $id);
                        continue;
                    }
                    $snapshots[$id] ??= $this->capturar($id, 'previo_actualizacion_padron');
                    // No cambia FK, estado, timestamps ni datos propios del documento.
                    $total += DB::table($table)->where('id', $row->id)->whereNull('padron_personal_snapshot')->update([
                        'padron_personal_snapshot' => json_encode($snapshots[$id], JSON_THROW_ON_ERROR),
                    ]);
                }
            }
        }
        return $total;
    }
}
