<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IdoneidadPsicologicaSolicitud extends Model
{
    public const ESTADOS = [
        'solicitada' => 'Solicitada',
        'resuelta' => 'Resultados registrados',
    ];

    protected $table = 'idoneidad_psicologica_solicitudes';

    protected $fillable = [
        'fecha_inicio',
        'fecha_termino',
        'estado',
        'observacion',
        'solicitada_por',
        'solicitada_at',
        'actualizada_por',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_termino' => 'date',
        'solicitada_at' => 'datetime',
    ];

    public function funcionarios(): HasMany
    {
        return $this->hasMany(IdoneidadPsicologicaFuncionario::class, 'solicitud_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitada_por');
    }

    public function actualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizada_por');
    }
}
