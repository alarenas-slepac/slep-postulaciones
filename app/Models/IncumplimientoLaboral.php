<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncumplimientoLaboral extends Model
{
    protected $table = 'incumplimientos_laborales';

    protected $fillable = [
        'establecimiento_id',
        'reemplazo_personal_id',
        'funcionario_rut',
        'funcionario_nombre',
        'funcionario_rbd',
        'fecha_desde',
        'fecha_hasta',
        'dias',
        'horas',
        'minutos',
        'informado_por_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'fecha_desde' => 'date',
        'fecha_hasta' => 'date',
        'dias' => 'integer',
        'horas' => 'integer',
        'minutos' => 'integer',
        'funcionario_rbd' => 'integer',
        'establecimiento_id' => 'integer',
        'reemplazo_personal_id' => 'integer',
        'informado_por_user_id' => 'integer',
        'updated_by_user_id' => 'integer',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function reemplazoPersonal(): BelongsTo
    {
        return $this->belongsTo(ReemplazoPersonal::class);
    }

    public function informadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'informado_por_user_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function historial(): HasMany
    {
        return $this->hasMany(IncumplimientoLaboralHistorial::class, 'incumplimiento_laboral_id');
    }

}
