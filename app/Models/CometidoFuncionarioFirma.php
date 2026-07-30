<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CometidoFuncionarioFirma extends Model
{
    protected $table = 'cometido_funcionario_firmas';

    protected $fillable = [
        'cometido_funcionario_id',
        'documento_generado_id',
        'user_id',
        'funcionario_ac_autorizado_id',
        'tipo_firma',
        'rol_firmante',
        'nombre_firmante',
        'rut_firmante',
        'cargo_firmante',
        'dependencia_firmante',
        'es_subrogante',
        'ip_firma',
        'user_agent',
        'fecha_firma',
        'token_firma',
        'hash_firma',
    ];

    protected $casts = [
        'es_subrogante' => 'boolean',
        'fecha_firma' => 'datetime',
    ];

    public function cometido(): BelongsTo
    {
        return $this->belongsTo(CometidoFuncionario::class, 'cometido_funcionario_id');
    }

    public function documento(): BelongsTo
    {
        return $this->belongsTo(CometidoFuncionarioDocumentoGenerado::class, 'documento_generado_id');
    }
}
