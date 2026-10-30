<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaternidadImportacion extends Model
{
    protected $table = 'maternidad_importaciones';
    protected $guarded = ['id'];
    protected $casts = ['payload' => 'encrypted:array', 'expira_at' => 'datetime', 'procesada_at' => 'datetime'];
    public function getRouteKeyName(): string { return 'uuid'; }
}
