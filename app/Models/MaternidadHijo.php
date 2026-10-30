<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaternidadHijo extends Model
{
    protected $table = 'maternidad_hijos';
    protected $guarded = ['id'];
    protected $casts = ['fecha_nacimiento' => 'date:Y-m-d'];
}
