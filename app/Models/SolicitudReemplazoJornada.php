<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudReemplazoJornada extends Model
{
    protected $fillable = [
        'solicitud_reemplazo_id',
        'financiamiento',
        'titular_basica',
        'titular_media',
        'titular_total',
        'reemplazo_basica',
        'reemplazo_media',
        'reemplazo_total',
    ];

    protected $casts = [
        'titular_basica' => 'decimal:2',
        'titular_media' => 'decimal:2',
        'titular_total' => 'decimal:2',
        'reemplazo_basica' => 'decimal:2',
        'reemplazo_media' => 'decimal:2',
        'reemplazo_total' => 'decimal:2',
    ];
}
