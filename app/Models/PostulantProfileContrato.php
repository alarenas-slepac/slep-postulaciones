<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostulantProfileContrato extends Model
{
    protected $table = 'postulant_profile_contratos';

    protected $fillable = [
        'postulant_profile_id',
        'tipo_contrato',
        'cantidad_horas',
        'fecha_termino',
        'establecimiento_id',
        'registrado_por',
        'activo',
        'motivo_desactivacion',
        'desactivado_por',
        'desactivado_at',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_horas' => 'integer',
            'fecha_termino' => 'date',
            'activo' => 'boolean',
            'desactivado_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(PostulantProfile::class, 'postulant_profile_id');
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function desactivadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'desactivado_por');
    }

    public function getEstaVigenteAttribute(): bool
    {
        return (bool) $this->activo
            && ($this->fecha_termino === null || $this->fecha_termino->isToday() || $this->fecha_termino->isFuture());
    }

    public function getResumenAttribute(): string
    {
        $partes = [];
        $partes[] = $this->tipo_contrato ?: 'Contrato no informado';

        if ($this->cantidad_horas) {
            $partes[] = $this->cantidad_horas . ' hrs.';
        }

        if ($this->establecimiento) {
            $partes[] = $this->establecimiento->nombre_establecimiento ?: ('RBD ' . $this->establecimiento->rbd);
        }

        if ($this->fecha_termino) {
            $partes[] = 'hasta ' . $this->fecha_termino->format('d-m-Y');
        }

        return implode(' · ', array_filter($partes));
    }
}
