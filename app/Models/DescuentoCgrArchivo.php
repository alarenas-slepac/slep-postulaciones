<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DescuentoCgrArchivo extends Model
{
    protected $table = 'descuentos_cgr_archivos';

    protected $fillable = ['descuento_cgr_id', 'numero_cuota', 'tipo', 'grupo_archivo', 'path', 'nombre_original', 'tamano', 'folio', 'fecha_reintegro', 'monto_reintegro_pesos', 'cargado_por_id'];

    protected function casts(): array
    {
        return ['numero_cuota' => 'integer', 'fecha_reintegro' => 'date', 'monto_reintegro_pesos' => 'integer'];
    }

    public function descuentoCgr(): BelongsTo
    {
        return $this->belongsTo(DescuentoCgr::class, 'descuento_cgr_id');
    }

    public function cargadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cargado_por_id');
    }
}
