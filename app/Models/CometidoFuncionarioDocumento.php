<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CometidoFuncionarioDocumento extends Model
{
    protected $table = 'cometido_funcionario_documentos';

    protected $fillable = [
        'cometido_funcionario_id',
        'tipo',
        'nombre_original',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
    ];

    public function cometido(): BelongsTo
    {
        return $this->belongsTo(CometidoFuncionario::class, 'cometido_funcionario_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
