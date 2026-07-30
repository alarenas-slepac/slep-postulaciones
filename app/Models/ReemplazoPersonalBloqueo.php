<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReemplazoPersonalBloqueo extends Model
{
    protected $table = 'reemplazos_personal_bloqueos';

    protected $fillable = [
        'reemplazo_personal_id',
        'establecimiento_id',
        'rbd',
        'rut',
        'nombre',
        'motivo',
        'observacion',
        'activo',
        'bloqueado_por',
        'desbloqueado_por',
        'desbloqueado_at',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'rbd' => 'integer',
        'desbloqueado_at' => 'datetime',
    ];

    public function personal(): BelongsTo
    {
        return $this->belongsTo(ReemplazoPersonal::class, 'reemplazo_personal_id');
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function bloqueadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'bloqueado_por');
    }

    public function desbloqueadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'desbloqueado_por');
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
