<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class GrupoVotacion extends Model
{
    public const PENDIENTE = 'pendiente';

    public const EN_TRASLADO = 'en_traslado';

    public const EN_VOTACION = 'en_votacion';

    public const FINALIZADO = 'finalizado';

    public const SUSPENDIDO = 'suspendido';

    public const ESTADOS = [self::PENDIENTE, self::EN_TRASLADO, self::EN_VOTACION, self::FINALIZADO, self::SUSPENDIDO];

    protected $table = 'grupos_votacion';

    protected $fillable = ['jornada_votacion_id', 'grupo_origen_id', 'fecha_inicio', 'fecha_fin', 'numero', 'nombre', 'encargado_id', 'estado', 'observacion', 'iniciado_at', 'finalizado_at'];

    protected function casts(): array
    {
        return ['fecha_inicio' => 'date', 'fecha_fin' => 'date', 'iniciado_at' => 'datetime', 'finalizado_at' => 'datetime'];
    }

    public function inicioFuncionamiento(): CarbonInterface
    {
        return $this->fecha_inicio ?? $this->jornada->fecha;
    }

    public function finFuncionamiento(): CarbonInterface
    {
        return $this->fecha_fin ?? $this->jornada->finFuncionamiento();
    }

    public function getPeriodoFuncionamientoAttribute(): string
    {
        $inicio = $this->inicioFuncionamiento()->format('d-m-Y');

        return $this->inicioFuncionamiento()->isSameDay($this->finFuncionamiento()) ? $inicio : $inicio.' al '.$this->finFuncionamiento()->format('d-m-Y');
    }

    public function dentroDelPeriodo(?CarbonInterface $momento = null): bool
    {
        $dia = CarbonImmutable::instance($momento ?? CarbonImmutable::now(config('votaciones.timezone')))
            ->timezone(config('votaciones.timezone'))->toDateString();

        return $dia >= $this->inicioFuncionamiento()->toDateString()
            && $dia <= $this->finFuncionamiento()->toDateString()
            && $dia >= $this->jornada->fecha->toDateString()
            && $dia <= $this->jornada->finFuncionamiento()->toDateString();
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

    public function incidencias(): HasMany
    {
        return $this->hasMany(IncidenciaVotacion::class, 'grupo_votacion_id');
    }
}
