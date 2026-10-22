<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\CarbonInterface;

class JornadaVotacion extends Model
{
    public const BORRADOR = 'borrador';

    public const PUBLICADA = 'publicada';

    public const EN_CURSO = 'en_curso';

    public const FINALIZADA = 'finalizada';

    public const SUSPENDIDA = 'suspendida';

    public const ESTADOS = [self::BORRADOR, self::PUBLICADA, self::EN_CURSO, self::FINALIZADA, self::SUSPENDIDA];

    protected $table = 'jornadas_votacion';

    protected $fillable = ['nombre', 'slug', 'fecha', 'fecha_fin', 'estado', 'publica', 'descripcion', 'publicada_at', 'iniciada_at', 'finalizada_at', 'creada_por', 'actualizada_por'];

    protected function casts(): array
    {
        return ['fecha' => 'date', 'fecha_fin' => 'date', 'publica' => 'boolean', 'consulta_publica' => 'boolean', 'publicada_at' => 'datetime', 'iniciada_at' => 'datetime', 'finalizada_at' => 'datetime'];
    }

    public function finFuncionamiento(): CarbonInterface
    {
        return $this->fecha_fin ?? $this->fecha;
    }

    public function getPeriodoFuncionamientoAttribute(): string
    {
        $inicio = $this->fecha->format('d-m-Y');

        return $this->fecha->isSameDay($this->finFuncionamiento()) ? $inicio : $inicio.' al '.$this->finFuncionamiento()->format('d-m-Y');
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

    public function padronConsulta(): HasMany
    {
        return $this->hasMany(VotanteConsulta::class, 'jornada_votacion_id');
    }

    public function localesConsulta(): HasMany
    {
        return $this->hasMany(LocalConsultaVotacion::class, 'jornada_votacion_id');
    }

    public function resultadosDiarios(): HasMany
    {
        return $this->hasMany(ResultadoDiarioVotacion::class, 'jornada_votacion_id');
    }

    public function resultadosConsolidados(): HasMany
    {
        return $this->hasMany(ResultadoConsolidadoVotacion::class, 'jornada_votacion_id');
    }
}
