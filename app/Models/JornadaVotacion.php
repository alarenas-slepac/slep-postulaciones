<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JornadaVotacion extends Model
{
    public const BORRADOR = 'borrador';

    public const PUBLICADA = 'publicada';

    public const EN_CURSO = 'en_curso';

    public const FINALIZADA = 'finalizada';

    public const SUSPENDIDA = 'suspendida';

    public const ESTADOS = [self::BORRADOR, self::PUBLICADA, self::EN_CURSO, self::FINALIZADA, self::SUSPENDIDA];

    protected $table = 'jornadas_votacion';

    protected $fillable = ['nombre', 'slug', 'fecha', 'estado', 'publica', 'descripcion', 'publicada_at', 'iniciada_at', 'finalizada_at', 'creada_por', 'actualizada_por'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'publica' => 'boolean', 'publicada_at' => 'datetime', 'iniciada_at' => 'datetime', 'finalizada_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function procesos(): BelongsToMany
    {
        return $this->belongsToMany(ProcesoVotacion::class, 'jornada_votacion_proceso');
    }

    public function grupos(): HasMany
    {
        return $this->hasMany(GrupoVotacion::class)->orderBy('numero');
    }

    public function incidencias(): HasMany
    {
        return $this->hasMany(IncidenciaVotacion::class)->latest();
    }

    public function bitacora(): HasMany
    {
        return $this->hasMany(BitacoraVotacion::class)->latest('created_at');
    }
}
