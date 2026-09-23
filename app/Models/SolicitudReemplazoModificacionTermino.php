<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudReemplazoModificacionTermino extends Model
{
    protected $table = 'solicitudes_reemplazo_modificaciones_termino';

    protected $fillable = [
        'solicitud_reemplazo_id',
        'causal',
        'estado_origen',
        'fecha_termino_anterior',
        'fecha_termino_nueva',
        'motivo',
        'carta_renuncia_path',
        'resolucion_renuncia_path',
        'orden_trabajo_anterior_path',
        'orden_trabajo_anterior_creada_at',
        'resolucion_docente_docx_anterior_path',
        'resolucion_docente_firmada_anterior_path',
        'orden_trabajo_requiere_regeneracion',
        'orden_trabajo_regenerada_at',
        'resolucion_docente_requiere_regeneracion',
        'resolucion_docente_regenerada_at',
        'finalizada_at',
        'reabierta_por_user_id',
    ];

    protected $casts = [
        'fecha_termino_anterior' => 'date',
        'fecha_termino_nueva' => 'date',
        'orden_trabajo_anterior_creada_at' => 'datetime',
        'orden_trabajo_requiere_regeneracion' => 'boolean',
        'orden_trabajo_regenerada_at' => 'datetime',
        'resolucion_docente_requiere_regeneracion' => 'boolean',
        'resolucion_docente_regenerada_at' => 'datetime',
        'finalizada_at' => 'datetime',
    ];

    public function solicitud()
    {
        return $this->belongsTo(SolicitudReemplazo::class, 'solicitud_reemplazo_id');
    }

    public function reabiertaPor()
    {
        return $this->belongsTo(User::class, 'reabierta_por_user_id');
    }
}
