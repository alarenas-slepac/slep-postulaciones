<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProcesoVotacion extends Model
{
    protected $table = 'procesos_votacion';

    protected $fillable = ['codigo', 'nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function jornadas(): BelongsToMany
    {
        return $this->belongsToMany(JornadaVotacion::class, 'jornada_votacion_proceso');
    }
}
