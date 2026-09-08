<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PadronRevisionFila extends Model
{
    protected $table = 'padron_revision_filas';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'datos' => 'array', 'anterior' => 'array', 'candidatos' => 'array',
        'asignaciones' => 'array', 'observaciones' => 'array',
    ];
}
