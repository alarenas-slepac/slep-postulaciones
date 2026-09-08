<?php

namespace App\Services\Padron;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PadronDependenciasService
{
    private const TABLAS = [
        'solicitudes_reemplazo' => 'Solicitud de reemplazo',
        'cometidos_funcionarios' => 'Cometido',
        'incumplimientos_laborales' => 'Incumplimiento laboral',
        'reemplazos_personal_bloqueos' => 'Bloqueo de personal',
    ];

    /** Inventario de referencias por ID. No modifica documentos ni presume que estén protegidos. */
    public function snapshot(): array
    {
        $hash = hash_init('sha256');
        $porPersonal = [];
        foreach (self::TABLAS as $tabla => $modulo) {
            hash_update($hash, $tabla);
            if (! Schema::hasTable($tabla)) {
                hash_update($hash, 'ausente');
                continue;
            }
            // Leer por cursor permite detectar cambios incluso en documentos sin
            // updated_at, sin guardar su contenido sensible en el inventario.
            foreach (DB::table($tabla)->whereNotNull('reemplazo_personal_id')->orderBy('id')->cursor() as $row) {
                hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR));
                $id = (int) $row->reemplazo_personal_id;
                $porPersonal[$id] ??= ['total' => 0, 'referencias' => []];
                $porPersonal[$id]['total']++;
                if (count($porPersonal[$id]['referencias']) < 20) {
                    $porPersonal[$id]['referencias'][] = ['modulo' => $modulo, 'id' => (int) $row->id];
                }
            }
        }
        return ['hash' => hash_final($hash), 'por_personal' => $porPersonal];
    }
}
