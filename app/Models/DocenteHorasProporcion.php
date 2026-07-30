<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DocenteHorasProporcion extends Model
{
    protected $table = 'docente_horas_proporciones';

    protected $fillable = [
        'proporcion',
        'horas_contrato',
        'horas_aula_pedagogicas',
        'horas_aula_cronologicas_minutos',
        'recreo_minutos',
        'horas_no_lectivas_minutos',
        'vigente',
    ];

    protected $casts = [
        'horas_contrato' => 'integer',
        'horas_aula_pedagogicas' => 'integer',
        'horas_aula_cronologicas_minutos' => 'integer',
        'recreo_minutos' => 'integer',
        'horas_no_lectivas_minutos' => 'integer',
        'vigente' => 'boolean',
    ];
}
