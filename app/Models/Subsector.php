<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subsector extends Model
{
    // 👇 fuerza el nombre real de la tabla
    protected $table = 'subsectores';

    protected $fillable = ['subsector'];

    public function menciones(): HasMany
    {
        return $this->hasMany(Mencion::class);
    }
}
