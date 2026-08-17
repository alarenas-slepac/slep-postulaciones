<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentroOperacionesTicketImagen extends Model
{
    protected $table = 'centro_operaciones_ticket_imagenes';

    protected $fillable = [
        'ticket_id',
        'path',
        'mime_type',
        'size_bytes',
        'subida_por_id',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(CentroOperacionesTicket::class, 'ticket_id');
    }

    public function subidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subida_por_id');
    }
}
