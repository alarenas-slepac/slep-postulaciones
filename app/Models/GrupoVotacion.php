<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrupoVotacion extends Model
{
    public const PENDIENTE = 'pendiente';

    public const EN_TRASLADO = 'en_traslado';

    public const EN_VOTACION = 'en_votacion';

    public const FINALIZADO = 'finalizado';

    public const SUSPENDIDO = 'suspendido';

    public const ESTADOS = [self::PENDIENTE, self::EN_TRASLADO, self::EN_VOTACION, self::FINALIZADO, self::SUSPENDIDO];

    protected $table = 'grupos_votacion';

    protected $fillable = ['jornada_votacion_id', 'numero', 'nombre', 'encargado_id', 'estado', 'observacion', 'iniciado_at', 'finalizado_at'];

    protected function casts(): array
    {
        return ['iniciado_at' => 'datetime', 'finalizado_at' => 'datetime'];
    }

    public function jornada(): BelongsTo
    {
        return $this->belongsTo(JornadaVotacion::class, 'jornada_votacion_id');
    }

    public function encargado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encargado_id');
    }

    public function miembros(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'grupo_votacion_miembros')->withPivot('rol')->withTimestamps();
    }

    public function rutas(): HasMany
    {
        return $this->hasMany(RutaVotacion::class)->orderBy('orden');
    }
}
