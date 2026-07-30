<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CometidoFuncionarioCdpMonto extends Model
{
    protected $table = 'cometido_funcionario_cdp_montos';

    protected $fillable = [
        'cometido_funcionario_id',
        'tipo',
        'fecha',
        'dia_numero',
        'porcentaje',
        'valor_diario',
        'monto',
        'catalogo_valor_id',
        'created_by',
    ];

    protected $casts = [
        'fecha' => 'date',
        'dia_numero' => 'integer',
        'porcentaje' => 'integer',
        'valor_diario' => 'integer',
        'monto' => 'integer',
    ];

    public function cometido(): BelongsTo
    {
        return $this->belongsTo(CometidoFuncionario::class, 'cometido_funcionario_id');
    }

    public function catalogoValor(): BelongsTo
    {
        return $this->belongsTo(ViaticoReembolsoValor::class, 'catalogo_valor_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
