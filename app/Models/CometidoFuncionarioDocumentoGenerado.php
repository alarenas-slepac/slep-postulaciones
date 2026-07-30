<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CometidoFuncionarioDocumentoGenerado extends Model
{
    protected $table = 'cometido_funcionario_documentos_generados';

    protected $fillable = [
        'cometido_funcionario_id',
        'tipo_documento',
        'numero_documento',
        'codigo_validacion',
        'token_validacion',
        'documento_hash',
        'archivo_pdf_path',
        'estado',
        'emitido_por_user_id',
        'emitido_at',
    ];

    protected $casts = [
        'emitido_at' => 'datetime',
    ];

    public function cometido(): BelongsTo
    {
        return $this->belongsTo(CometidoFuncionario::class, 'cometido_funcionario_id');
    }

    public function firmas(): HasMany
    {
        return $this->hasMany(CometidoFuncionarioFirma::class, 'documento_generado_id');
    }
}
