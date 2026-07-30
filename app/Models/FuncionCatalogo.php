<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuncionCatalogo extends Model
{
    protected $table = 'funciones_catalogo';

    protected $fillable = ['nombre'];
}
