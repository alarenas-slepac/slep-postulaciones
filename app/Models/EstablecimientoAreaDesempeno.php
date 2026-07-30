<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EstablecimientoAreaDesempeno extends Model
{
    protected $table = 'establecimiento_area_desempeno';

    protected $fillable = [
        'establecimiento_id',
        'area_desempeno_id',
        'bloqueada',
    ];

    protected $casts = [
        'establecimiento_id' => 'integer',
        'area_desempeno_id' => 'integer',
        'bloqueada' => 'boolean',
    ];

    public function establecimiento()
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function area()
    {
        return $this->belongsTo(AreaDesempeno::class, 'area_desempeno_id');
    }
}
