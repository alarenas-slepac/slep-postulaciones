<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RutaVotacion extends Model
{
    protected $table = 'rutas_votacion';

    protected $fillable = ['grupo_votacion_id', 'establecimiento_id', 'orden', 'activa'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
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
}
