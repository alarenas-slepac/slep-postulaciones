<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CargaFamiliarCausante extends Model
{
    use HasFactory;

    protected $table = 'cargas_familiares_causantes';

    protected $fillable = [
        'solicitud_id',
        'carga_familiar_id',
        'accion',
        'run',
        'dv',
        'rut_completo',
        'run_normalizado',
        'apellido_paterno',
        'apellido_materno',
        'nombres',
        'sexo',
        'parentesco',
        'codigo_tipo_beneficio',
        'codigo_tipo_causante',
        'fecha_nacimiento',
        'edad_al_enviar',
        'fecha_inicio_beneficio',
        'observaciones',
        'estado_revision',
        'revision_observacion',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_inicio_beneficio' => 'date',
            'edad_al_enviar' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(CargaFamiliarSolicitud::class, 'solicitud_id');
    }

    public function cargaVigente(): BelongsTo
    {
        return $this->belongsTo(CargaFamiliar::class, 'carga_familiar_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(CargaFamiliarDocumento::class, 'causante_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim(collect([$this->nombres, $this->apellido_paterno, $this->apellido_materno])->filter()->implode(' '));
    }

    public function getEdadActualAttribute(): ?int
    {
        return $this->fecha_nacimiento ? $this->fecha_nacimiento->age : null;
    }

    public function getRequiereDocumentosMayorEdadAttribute(): bool
    {
        $edad = $this->edad_al_enviar ?? $this->edad_actual;
        return $edad !== null && $edad >= 18;
    }

    public function getEstadoRevisionLabelAttribute(): string
    {
        return match ((string) $this->estado_revision) {
            'aprobado' => 'Aprobado',
            'observado' => 'Observado',
            'rechazado' => 'Rechazado',
            default => 'Pendiente',
        };
    }

    public function getEstadoRevisionBadgeClassAttribute(): string
    {
        return match ((string) $this->estado_revision) {
            'aprobado' => 'text-bg-success',
            'observado' => 'text-bg-warning',
            'rechazado' => 'text-bg-danger',
            default => 'text-bg-light text-dark border',
        };
    }
}
