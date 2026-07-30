<?php

// app/Models/PostulanteProvisorio.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PostulanteProvisorio extends Model
{
    protected $table = 'postulantes_provisorios';

    protected $fillable = [
        'rut',
        'rut_body',
        'rut_dv',
        'raw_rut',
        'nombres',
        'apellidos',
        'email',
        'emails',
        'source_filename',
        'import_status',
    ];

    protected $casts = [
        'emails' => 'array',
    ];
}
