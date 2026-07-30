<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaeRegistroOtroDescuento extends Model
{
    use HasFactory;

    protected $table = 'mae_registro_otros_descuentos';

    protected $fillable = [
        'mae_registro_id',
        'columna_origen',
        'columna_normalizada',
        'valor',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
    ];

    public function registro(): BelongsTo
    {
        return $this->belongsTo(MaeRegistro::class, 'mae_registro_id');
    }
}
