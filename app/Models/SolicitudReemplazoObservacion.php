<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudReemplazoObservacion extends Model
{
    protected $table = 'solicitudes_reemplazo_observaciones';

    protected $fillable = [
        'solicitud_reemplazo_id',
        'etapa',
        'accion',
        'estado_origen',
        'estado_destino',
        'motivo',
        'observacion',
        'user_id',
    ];

    public function solicitud()
    {
        return $this->belongsTo(SolicitudReemplazo::class, 'solicitud_reemplazo_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
