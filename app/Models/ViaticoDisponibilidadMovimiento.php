<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViaticoDisponibilidadMovimiento extends Model
{
    use HasFactory;

    protected $table = 'viaticos_disponibilidad_movimientos';

    protected $fillable = [
        'viatico_disponibilidad_presupuestaria_id',
        'cometido_funcionario_id',
        'tipo_movimiento',
        'monto',
        'saldo_anterior',
        'saldo_nuevo',
        'referencia',
        'observacion',
        'created_by',
    ];

    protected $casts = [
        'monto' => 'integer',
        'saldo_anterior' => 'integer',
        'saldo_nuevo' => 'integer',
    ];

    public const TIPO_COMPROMISO_CDP_VIATICO = 'compromiso_cdp_viatico';

    public function disponibilidad(): BelongsTo
    {
        return $this->belongsTo(ViaticoDisponibilidadPresupuestaria::class, 'viatico_disponibilidad_presupuestaria_id');
    }

    public function cometido(): BelongsTo
    {
        return $this->belongsTo(CometidoFuncionario::class, 'cometido_funcionario_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
