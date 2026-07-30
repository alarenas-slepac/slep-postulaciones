<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuncionarioViaticoAnexo extends Model
{
    use HasFactory;

    protected $table = 'funcionarios_viatico_anexo';

    protected $fillable = [
        'rut',
        'rut_body',
        'rut_dv',
        'nombre_completo',
        'establecimiento_id',
        'establecimiento_nombre',
        'estamento',
        'cargo_funcion',
        'activo',
        'observacion',
        'registrado_por',
        'validado_at',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'validado_at' => 'datetime',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
