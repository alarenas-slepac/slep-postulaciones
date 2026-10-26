<?php

namespace App\Services\Votaciones;

use App\Models\JornadaVotacion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ActualizarVotantesService
{
    public function aplicar(JornadaVotacion $jornada, array $filas, array $campos, bool $agregar, User $user): array
    {
        return DB::transaction(function () use ($jornada, $filas, $campos, $agregar, $user) {
            $locked = JornadaVotacion::query()->lockForUpdate()->findOrFail($jornada->id);
            $actuales = DB::table('votacion_padron_consulta')->where('jornada_votacion_id', $locked->id)->get()->keyBy('id');
            $porRut = $actuales->groupBy('rut');
            $locales = $locked->localesConsulta()->get()->keyBy(fn ($l) => $l->rbd_origen.'|'.$l->rbd_local);
            $localesPorId = $locales->keyBy('id');
            $hashes = $actuales->mapWithKeys(fn ($v) => [$v->fila_hash => (int) $v->id])->all();
            $tocados = [];
            $updates = $inserts = $reporte = [];
            $resumen = ['actualizados' => 0, 'agregados' => 0, 'sin_cambios' => 0, 'omitidos' => 0];
            $now = now();
            foreach ($filas as $fila) {
                $resultado = ['fila' => $fila['fila'], 'id' => $fila['id'], 'rut' => $fila['rut'], 'resultado' => 'Omitido', 'detalle' => $fila['error']];
                $actual = null;
                if (! $resultado['detalle']) {
                    $matches = $porRut->get($fila['rut'], collect());
                    $actual = $fila['id'] ? $actuales->get($fila['id']) : ($matches->count() === 1 ? $matches->first() : null);
                    if ($fila['id'] && (! $actual || $actual->rut !== $fila['rut'])) {
                        $resultado['detalle'] = 'ID y RUT no corresponden a un registro de esta jornada. Descargue una plantilla actualizada.';
                    } elseif (! $fila['id'] && $matches->count() > 1) {
                        $resultado['detalle'] = 'El RUT tiene varias asignaciones. Use el ID_REGISTRO de la plantilla precargada.';
                    } elseif (! $actual && (! $agregar || array_diff(['nombre', 'tipo_contrato', 'asignacion'], $campos))) {
                        $resultado['detalle'] = $agregar ? 'Para agregar un RUT nuevo seleccione Nombre, Tipo de contrato y Establecimiento/local.' : 'RUT no encontrado; la incorporación de nuevos votantes está desactivada.';
                    } elseif ($actual && isset($tocados[$actual->id])) {
                        $resultado['detalle'] = 'El registro ya fue tratado en otra fila del archivo.';
                    }
                }
                if (! $resultado['detalle']) {
                    $values = $fila['valores'];
                    $local = isset($values['rbd']) ? $locales->get($values['rbd'].'|'.$values['rbd_local']) : null;
                    if (in_array('asignacion', $campos, true) && ! $local) {
                        $resultado['detalle'] = 'No existe la combinación RBD de origen y local en esta jornada. Cargue primero los locales.';
                    } else {
                        $payload = [
                            'rut' => $fila['rut'], 'nombre' => $values['nombre'] ?? $actual?->nombre,
                            'rbd' => $local ? (int) $local->rbd_origen : (int) $actual->rbd,
                            'establecimiento' => $local ? $local->establecimiento : $actual->establecimiento,
                            'tipo_contrato' => $values['tipo_contrato'] ?? $actual?->tipo_contrato,
                            'grupo' => array_key_exists('grupo', $values) ? $values['grupo'] : $actual?->grupo,
                            'local_key' => $local ? $local->rbd_origen.'|'.$local->rbd_local : $actual->rbd.'|'.$actual->local_consulta_id,
                        ];
                        // El hash conserva la misma composición y orden que la importación completa.
                        if (! $local) {
                            $localActual = $localesPorId->get($actual->local_consulta_id);
                            if (! $localActual) { throw new \RuntimeException('Asignación de local inconsistente.'); }
                            $payload['local_key'] = $actual->rbd.'|'.$localActual->rbd_local;
                        }
                        $hash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
                        if (isset($hashes[$hash]) && $hashes[$hash] !== (int) ($actual?->id ?? 0)) {
                            $resultado['detalle'] = 'El cambio duplicaría otra asignación del padrón; se conservó el registro original.';
                        } else {
                            $localId = $local?->id ?? $actual->local_consulta_id;
                            unset($payload['local_key']);
                            $changed = ! $actual || (int) $actual->local_consulta_id !== (int) $localId;
                            if ($actual) {
                                foreach ($payload as $key => $value) { $changed = $changed || (string) $actual->$key !== (string) $value; }
                                $tocados[$actual->id] = true;
                            }
                            if (! $changed) {
                                $resumen['sin_cambios']++;
                                $resultado['resultado'] = 'Sin cambios';
                                $resultado['detalle'] = 'Los valores seleccionados coinciden con el padrón.';
                            } else {
                                $record = $payload + ['local_consulta_id' => $localId, 'fila_hash' => $hash,
                                    'jornada_votacion_id' => $locked->id, 'created_at' => $actual?->created_at ?? $now, 'updated_at' => $now];
                                if ($actual) {
                                    $updates[] = $record + ['id' => $actual->id];
                                    $resumen['actualizados']++;
                                    $resultado['resultado'] = 'Actualizado';
                                } else {
                                    $inserts[] = $record;
                                    $resumen['agregados']++;
                                    $resultado['resultado'] = 'Agregado';
                                }
                                $hashes[$hash] = $actual ? (int) $actual->id : -1;
                                $resultado['detalle'] = 'Registro procesado. Revise y vuelva a publicar la consulta.';
                            }
                        }
                    }
                }
                if ($resultado['resultado'] === 'Omitido') { $resumen['omitidos']++; }
                $reporte[] = $resultado;
            }
            // No se eliminan votantes, locales ni datos de otras jornadas.
            foreach (array_chunk($updates, 100) as $chunk) {
                DB::table('votacion_padron_consulta')->upsert($chunk, ['id'], ['local_consulta_id', 'nombre', 'rbd', 'establecimiento', 'tipo_contrato', 'grupo', 'fila_hash', 'updated_at']);
            }
            foreach (array_chunk($inserts, 100) as $chunk) { DB::table('votacion_padron_consulta')->insert($chunk); }
            if ($resumen['actualizados'] + $resumen['agregados'] > 0) {
                $locked->forceFill(['consulta_publica' => false])->save();
                app(BitacoraVotacionService::class)->registrar($locked, 'padron_votantes_actualizado', 'Se actualizaron o agregaron votantes mediante Excel.', $user,
                    metadata: $resumen + ['campos' => $campos]);
            }
            return compact('resumen', 'reporte');
        }, 3);
    }
}
