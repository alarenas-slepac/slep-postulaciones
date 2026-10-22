<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RutaVotacion extends Model
{
    protected $table = 'rutas_votacion';

    protected $fillable = ['grupo_votacion_id', 'establecimiento_id', 'orden', 'activa', 'convocados_personalizados'];

    protected function casts(): array
    {
        return ['activa' => 'boolean', 'convocados_personalizados' => 'boolean'];
    }

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(GrupoVotacion::class, 'grupo_votacion_id');
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function visita(): HasOne
    {
        return $this->hasOne(VisitaVotacion::class);
    }

    public function convocadosConfigurados(): HasMany
    {
        return $this->hasMany(RutaVotacionConvocado::class, 'ruta_votacion_id')->orderBy('nombre_establecimiento');
    }
}
