<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

use App\Models\Establecimiento;

class AreaDesempeno extends Model
{
    protected $table = 'areas_desempeno';

    protected $fillable = [
        'estamento',
        'nombre',
        'slug',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected static function booted()
    {
        static::saving(function ($m) {
            if (blank($m->slug) && filled($m->nombre)) {
                $m->slug = \Illuminate\Support\Str::slug($m->nombre, '_');
            }
        });
    }

    // Scopes útiles
    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    public function scopeDocente(Builder $q): Builder
    {
        return $q->where('estamento', 'docente');
    }

    public function scopeAsistente(Builder $q): Builder
    {
        return $q->where('estamento', 'asistente');
    }

    public function establecimientos()
    {
        return $this->belongsToMany(
            Establecimiento::class,
            'establecimiento_area_desempeno',
            'area_desempeno_id',
            'establecimiento_id'
        )->withPivot(['bloqueada'])->withTimestamps();
    }
}
