<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentroOperacionesRiesgoDimension extends Model
{
    protected $table = 'centro_operaciones_riesgo_dimensiones';

    protected $fillable = ['modelo_id', 'codigo', 'nombre', 'pregunta', 'peso', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['peso' => 'integer', 'orden' => 'integer', 'activo' => 'boolean'];
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(CentroOperacionesRiesgoModelo::class, 'modelo_id');
    }

    public function opciones(): HasMany
    {
        return $this->hasMany(CentroOperacionesRiesgoOpcion::class, 'dimension_id')->orderBy('orden');
    }
}
