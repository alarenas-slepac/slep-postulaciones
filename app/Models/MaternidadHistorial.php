<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaternidadHistorial extends Model
{
    protected $table = 'maternidad_historial';
    protected $guarded = ['id'];
    public $timestamps = false;
    protected $casts = ['cambios' => 'array', 'created_at' => 'datetime'];
    public function autor() { return $this->belongsTo(User::class, 'created_by'); }
}
