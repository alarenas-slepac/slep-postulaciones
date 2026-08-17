<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudReemplazoAutorizacionDocente extends Model
{
    public const ESTADO_EN_TRAMITE = 'en_tramite';
    public const ESTADO_APROBADA = 'aprobada';
    public const ESTADO_RECHAZADA = 'rechazada';

    protected $table = 'solicitud_reemplazo_autorizaciones_docentes';

    protected $fillable = [
        'solicitud_reemplazo_id',
        'postulant_profile_id',
        'numero_autorizacion',
        'estado',
        'observacion_estado',
        'correo_destino',
        'documentos_enviados',
        'solicitado_por_user_id',
        'solicitado_at',
        'correo_enviado_at',
        'correo_error',
        'numero_registrado_por_user_id',
        'numero_registrado_at',
        'estado_actualizado_por_user_id',
        'estado_actualizado_at',
    ];

    protected $casts = [
        'documentos_enviados' => 'array',
        'solicitado_at' => 'datetime',
        'correo_enviado_at' => 'datetime',
        'numero_registrado_at' => 'datetime',
        'estado_actualizado_at' => 'datetime',
    ];

    public static function estados(): array
    {
        return [
            self::ESTADO_EN_TRAMITE => 'En trámite',
            self::ESTADO_APROBADA => 'Aprobada',
            self::ESTADO_RECHAZADA => 'Rechazada',
        ];
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::estados()[$this->estado] ?? ucfirst(str_replace('_', ' ', (string) $this->estado));
    }

    public function solicitud()
    {
        return $this->belongsTo(SolicitudReemplazo::class, 'solicitud_reemplazo_id');
    }

    public function postulante()
    {
        return $this->belongsTo(PostulantProfile::class, 'postulant_profile_id');
    }

    public function solicitadoPor()
    {
        return $this->belongsTo(User::class, 'solicitado_por_user_id');
    }

    public function numeroRegistradoPor()
    {
        return $this->belongsTo(User::class, 'numero_registrado_por_user_id');
    }

    public function estadoActualizadoPor()
    {
        return $this->belongsTo(User::class, 'estado_actualizado_por_user_id');
    }
}
