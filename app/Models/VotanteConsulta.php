<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VotanteConsulta extends Model
{
    protected $table = 'votacion_padron_consulta';

    protected $guarded = ['id'];

    // No serializar identificadores o datos del padrón accidentalmente.
    protected $hidden = ['rut', 'nombre', 'tipo_contrato', 'fila_hash'];

    public function local(): BelongsTo
    {
        return $this->belongsTo(LocalConsultaVotacion::class, 'local_consulta_id');
    }

    public function establecimientoOrigen(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'rbd', 'rbd');
    }
}
