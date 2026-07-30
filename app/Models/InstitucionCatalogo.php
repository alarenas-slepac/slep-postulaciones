<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitucionCatalogo extends Model
{
    protected $table = 'instituciones_catalogo';

    protected $fillable = ['nombre'];
}
