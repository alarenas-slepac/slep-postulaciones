<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PadronRevision extends Model
{
    protected $table = 'padron_revisiones';

    protected $guarded = ['id'];

    protected $casts = ['resumen' => 'array', 'errores' => 'array', 'excesos' => 'array'];

    public function filas(): HasMany
    {
        return $this->hasMany(PadronRevisionFila::class);
    }
}
