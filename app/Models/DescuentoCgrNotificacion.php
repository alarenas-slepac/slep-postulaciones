<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class DescuentoCgrNotificacion extends Model
{
    protected $table = 'descuentos_cgr_notificaciones';

    protected $fillable = ['evento', 'correos_adicionales'];

    public static function destinatarios(string $evento, string $rol): array
    {
        $configurados = Schema::hasTable('descuentos_cgr_notificaciones')
            ? (string) static::query()->where('evento', $evento)->value('correos_adicionales')
            : '';
        $adicionales = preg_split('/[,;\s]+/', $configurados) ?: [];
        $usuarios = Schema::hasTable('users') && Schema::hasTable('model_has_roles')
            ? User::role($rol)->whereNotNull('email')->pluck('email')->all()
            : [];

        return collect(array_merge($usuarios, $adicionales))
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()->values()->all();
    }
}
