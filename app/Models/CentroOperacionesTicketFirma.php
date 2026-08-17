<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroOperacionesTicketFirma extends Model
{
    protected $table = 'centro_operaciones_ticket_firmas';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'funcionario_ac_autorizado_id',
        'tipo_firma',
        'rol_firmante',
        'nombre_firmante',
        'rut_firmante',
        'cargo_firmante',
        'dependencia_firmante',
        'ip_firma',
        'user_agent',
        'fecha_firma',
        'token_firma',
        'hash_firma',
    ];

    protected function casts(): array
    {
        return ['fecha_firma' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(CentroOperacionesTicket::class, 'ticket_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function funcionarioAc(): BelongsTo
    {
        return $this->belongsTo(FuncionarioAcAutorizado::class, 'funcionario_ac_autorizado_id');
    }
}
