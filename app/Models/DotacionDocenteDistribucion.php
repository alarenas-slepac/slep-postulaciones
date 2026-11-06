<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DotacionDocenteDistribucion extends Model
{
    protected $table = 'dotacion_docente_distribuciones';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['horas' => 'decimal:2', 'antecedente' => 'array', 'anulada_at' => 'datetime',
            'created_by' => 'integer', 'anulada_by' => 'integer', 'reemplaza_id' => 'integer'];
    }

    public function destino(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'destino_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulada_by');
    }
}
