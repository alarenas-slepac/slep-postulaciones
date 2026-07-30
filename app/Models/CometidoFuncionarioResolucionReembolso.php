<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CometidoFuncionarioResolucionReembolso extends Model
{
    protected $table = 'cometido_funcionario_resoluciones_reembolso';

    protected $guarded = [];

    protected $casts = [
        'monto_resolucion' => 'integer',
        'monto_pagado_reembolso' => 'integer',
        'fecha_resolucion' => 'date',
        'fecha_envio_juridica' => 'datetime',
        'fecha_emision_resolucion' => 'datetime',
        'fecha_pago_reembolso' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function cometido()
    {
        return $this->belongsTo(CometidoFuncionario::class, 'cometido_funcionario_id');
    }

    public function rendicion()
    {
        return $this->belongsTo(CometidoFuncionarioRendicion::class, 'rendicion_id');
    }
}
