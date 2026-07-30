<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CometidoFuncionarioHistorial extends Model
{
    protected $table = 'cometido_funcionario_historial';

    protected $fillable = [
        'cometido_funcionario_id',
        'user_id',
        'estado_anterior',
        'estado_nuevo',
        'accion',
        'observacion',
    ];

    public function cometido(): BelongsTo
    {
        return $this->belongsTo(CometidoFuncionario::class, 'cometido_funcionario_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
