<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PieHorasApoyoMinimo extends Model
{
    protected $table = 'pie_horas_apoyo_minimo';

    protected $fillable = [
        'regimen_jec',
        'neet_cantidad_base',
        'neet_horas_base_minutos',
        'neep_cantidad',
        'neep_horas_minutos',
        'total_crono_minutos',
        'prof_educ_dif_minutos',
        'pae_minutos',
        'vigente',
    ];

    protected $casts = [
        'neet_cantidad_base' => 'integer',
        'neet_horas_base_minutos' => 'integer',
        'neep_cantidad' => 'integer',
        'neep_horas_minutos' => 'integer',
        'total_crono_minutos' => 'integer',
        'prof_educ_dif_minutos' => 'integer',
        'pae_minutos' => 'integer',
        'vigente' => 'boolean',
    ];
}
