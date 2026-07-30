<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Establecimiento;

class ReemplazoPersonal extends Model
{
    protected $table = 'reemplazos_personal';

    protected $fillable = [
        'establecimiento_id',
        'rbd',
        'rut',
        'nombre',
        'fecha_nacimiento',
        'fecha_ingreso',
        'fecha_termino',
        'tipocontrato',
        'financiamiento',
        'estatuto',
        'escalafon',
        'anio',
        'mes',
        'jornada',
        'jornada_basica',
        'jornada_media',
        'bienios',
        'tramo',
        'row_hash',
        'source_filename',
        'created_by',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'fecha_ingreso' => 'date',
        'fecha_termino' => 'date',
        'anio' => 'integer',
        'mes' => 'integer',
        'rbd' => 'integer',
        'jornada' => 'integer',
        'jornada_basica' => 'integer',
        'jornada_media' => 'integer',
        'bienios' => 'integer',
        'vigente' => 'boolean',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    public function bloqueoActivo(): HasOne
    {
        return $this->hasOne(ReemplazoPersonalBloqueo::class, 'reemplazo_personal_id')
            ->where('activo', true)
            ->latestOfMany();
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
    public function scopeDelEstablecimiento($q, int $establecimientoId)
    {
        return $q->where('establecimiento_id', $establecimientoId);
    }

    public function scopeFuncionarios($q)
    {
        return $q->where('tipo', 'FUNCIONARIO'); // ajusta
    }

    public function scopeReemplazosActivos($q)
    {
        // ejemplo: ajusta a tus columnas reales
        return $q->where('tipo', 'REEMPLAZO')
            ->where('activo', 1);
    }
}
