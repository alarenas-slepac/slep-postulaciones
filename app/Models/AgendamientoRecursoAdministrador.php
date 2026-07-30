<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgendamientoRecursoAdministrador extends Model
{
    use HasFactory;

    protected $table = 'agendamiento_recurso_administradores';

    protected $fillable = [
        'recurso_id',
        'user_id',
        'created_by',
    ];

    public function recurso(): BelongsTo
    {
        return $this->belongsTo(AgendamientoRecursoCatalogo::class, 'recurso_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
