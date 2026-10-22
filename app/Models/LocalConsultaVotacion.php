<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocalConsultaVotacion extends Model
{
    protected $table = 'votacion_locales_consulta';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['dia' => 'date'];
    }

    public function establecimientoDestino(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'rbd_local', 'rbd');
    }
}
