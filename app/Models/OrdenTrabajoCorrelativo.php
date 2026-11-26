<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrdenTrabajoCorrelativo extends Model
{
    protected $table = 'orden_trabajo_correlativos';

    public const UPDATED_AT = null;

    protected $fillable = ['origen', 'origen_id'];

    public const REEMPLAZO = 'reemplazo';

    public const PRORROGA = 'prorroga';

    public static function reservar(string $origen, int $id): self
    {
        // Las altas de solicitudes y las emisiones comparten la transacción coordinada del padrón.
        $reserva = self::query()->firstOrNew(['origen' => $origen, 'origen_id' => $id]);
        if (! $reserva->exists) {
            $reserva->id = max(
                (int) self::query()->max('id'),
                (int) SolicitudReemplazo::query()->max('correlativo'),
            ) + 1;
            $reserva->save();
        }

        return $reserva;
    }

    public static function siguienteSolicitud(int $anio): int
    {
        $siguiente = (int) SolicitudReemplazo::query()->where('anio', $anio)->lockForUpdate()->max('correlativo') + 1;
        foreach (self::query()->select('id')->where('id', '>=', $siguiente)->lazyById() as $reserva) {
            if ($reserva->id > $siguiente) {
                break;
            }
            $siguiente++;
        }

        return $siguiente;
    }
}
