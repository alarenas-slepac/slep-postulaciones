<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CometidoFuncionarioRendicion extends Model
{
    protected $table = 'cometido_funcionario_rendiciones';

    protected $guarded = [];

    protected $casts = [
        'monto_rendido' => 'integer',
        'monto_autorizado_daf' => 'integer',
        'monto_cdp_reembolso' => 'integer',
        'documentos_respaldo' => 'array',
        'fecha_envio_rendicion' => 'datetime',
        'fecha_revision_daf' => 'datetime',
        'fecha_revision_cdp' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function cometido()
    {
        return $this->belongsTo(CometidoFuncionario::class, 'cometido_funcionario_id');
    }

    public function resolucion()
    {
        return $this->hasOne(CometidoFuncionarioResolucionReembolso::class, 'rendicion_id');
    }
}
