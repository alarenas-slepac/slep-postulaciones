<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LocalConsultaVotacion extends Model
{
    protected $table = 'votacion_locales_consulta';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['dia' => 'date'];
    }
}
