<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidenciaVotacion extends Model
{
    public const ABIERTA = 'abierta';

    public const RESUELTA = 'resuelta';

    protected $table = 'incidencias_votacion';

    protected $fillable = ['jornada_votacion_id', 'grupo_votacion_id', 'ruta_votacion_id', 'tipo', 'detalle_interno', 'mensaje_publico', 'publica', 'estado', 'reportada_por', 'resuelta_por', 'resuelta_at', 'resolucion'];

    protected function casts(): array
    {
        return ['publica' => 'boolean', 'resuelta_at' => 'datetime'];
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(JornadaVotacion::class, 'jornada_votacion_id');
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoVotacion::class, 'grupo_votacion_id');
    }

    public function getDescripcionAttribute(): string
    {
        return (string) $this->detalle_interno;
    }
}
