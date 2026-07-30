<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CometidoFuncionarioPasajeAereo extends Model
{
    protected $table = 'cometido_funcionario_pasajes_aereos';

    protected $fillable = [
        'cometido_funcionario_id',
        'numero_solicitud_pedido',
        'solicitud_pedido_pdf_path',
        'estado_pasaje',
        'reserva_usuario_id',
        'reserva_archivo_path',
        'reserva_nombre_original',
        'reserva_fecha',
        'reserva_observacion',
        'cdp_usuario_id',
        'cdp_referencia',
        'cdp_fecha',
        'cdp_archivo_path',
        'cdp_nombre_original',
        'cdp_observacion',
        'compra_usuario_id',
        'proveedor',
        'monto',
        'fecha_compra',
        'numero_oc',
        'compra_archivo_path',
        'compra_nombre_original',
        'compra_observacion',
        'boleto_disponible_at',
        'notificado_funcionario_at',
    ];

    protected $casts = [
        'reserva_fecha' => 'datetime',
        'cdp_fecha' => 'date',
        'fecha_compra' => 'date',
        'boleto_disponible_at' => 'datetime',
        'notificado_funcionario_at' => 'datetime',
        'monto' => 'integer',
    ];

    public function cometido(): BelongsTo
    {
        return $this->belongsTo(CometidoFuncionario::class, 'cometido_funcionario_id');
    }
}
