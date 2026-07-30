<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CargaFamiliarSolicitud extends Model
{
    use HasFactory;

    protected $table = 'cargas_familiares_solicitudes';

    protected $fillable = [
        'user_id',
        'tipo_solicitud',
        'estado',
        'beneficiario_snapshot',
        'solicitante_distinto',
        'solicitante_snapshot',
        'solicita_pago_directo',
        'declaracion_aceptada',
        'declaracion_ingresos',
        'fecha_envio',
        'fecha_revision',
        'revisado_por',
        'observacion_revision',
    ];

    protected function casts(): array
    {
        return [
            'beneficiario_snapshot' => 'array',
            'solicitante_distinto' => 'boolean',
            'solicitante_snapshot' => 'array',
            'solicita_pago_directo' => 'boolean',
            'declaracion_aceptada' => 'boolean',
            'declaracion_ingresos' => 'array',
            'fecha_envio' => 'datetime',
            'fecha_revision' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }

    public function causantes(): HasMany
    {
        return $this->hasMany(CargaFamiliarCausante::class, 'solicitud_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(CargaFamiliarDocumento::class, 'solicitud_id');
    }

    public function documentosSolicitud(): HasMany
    {
        return $this->documentos()->whereNull('causante_id');
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ((string) $this->estado) {
            'enviado' => 'Enviado',
            'en_revision' => 'En revisión',
            'observado' => 'Observado',
            'aprobado' => 'Aprobado',
            'rechazado' => 'Rechazado',
            'anulado' => 'Anulado',
            default => ucfirst((string) $this->estado),
        };
    }

    public function getEstadoBadgeClassAttribute(): string
    {
        return match ((string) $this->estado) {
            'aprobado' => 'text-bg-success',
            'rechazado', 'anulado' => 'text-bg-danger',
            'observado' => 'text-bg-warning',
            'en_revision' => 'text-bg-info',
            default => 'text-bg-primary',
        };
    }

    public function getTipoSolicitudLabelAttribute(): string
    {
        return match ((string) $this->tipo_solicitud) {
            'actualizacion' => 'Actualización de carga existente',
            default => 'Inscripción de nuevo causante',
        };
    }

    public function getCanBeEditedByApplicantAttribute(): bool
    {
        return in_array((string) $this->estado, ['enviado', 'observado'], true);
    }
}
