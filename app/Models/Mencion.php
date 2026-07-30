<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mencion extends Model
{
    // 👇 fuerza el nombre real de la tabla
    protected $table = 'menciones';

    protected $fillable = ['nombre', 'anio', 'subsector_id', 'universidad'];

    public function subsector(): BelongsTo
    {
        return $this->belongsTo(Subsector::class);
    }

    public function getEtiquetaAttribute(): string
    {
        $partes = array_filter([
            $this->nombre,
            $this->universidad ? ' - ' . $this->universidad : null,
            $this->anio ? ' - ' . $this->anio : null,
        ]);
        return implode('', $partes);
    }
}
