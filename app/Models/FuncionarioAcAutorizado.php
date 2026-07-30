<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuncionarioAcAutorizado extends Model
{
    use HasFactory;

    protected $table = 'funcionarios_ac_autorizados';

    protected $fillable = [
        'periodo_nomina',
        'accion_sistema',
        'run',
        'dv',
        'rut_normalizado',
        'apellido_paterno',
        'apellido_materno',
        'nombres',
        'email',
        'telefono',
        'unidad_departamento',
        'cargo_funcion',
        'comuna',
        'calidad_juridica',
        'estado_autorizacion',
        'fecha_inicio_autorizacion',
        'fecha_fin_autorizacion',
        'enviar_notificacion',
        'grado',
        'escalafon',
        'subdireccion_dependencia',
        'jefatura',
        'observaciones',
        'registered_user_id',
        'registered_at',
        'imported_by',
        'imported_at',
        'last_import_message',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio_autorizacion' => 'date',
            'fecha_fin_autorizacion' => 'date',
            'enviar_notificacion' => 'boolean',
            'jefatura' => 'boolean',
            'registered_at' => 'datetime',
            'imported_at' => 'datetime',
        ];
    }

    public function registeredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_user_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim(collect([
            $this->nombres,
            $this->apellido_paterno,
            $this->apellido_materno,
        ])->filter()->implode(' '));
    }

    public function getRutCompletoAttribute(): string
    {
        return trim(($this->run ?? '') . '-' . ($this->dv ?? ''), '-');
    }

    public function estaActivo(): bool
    {
        if ((string) $this->estado_autorizacion !== 'activo') {
            return false;
        }

        $today = now()->toDateString();

        if ($this->fecha_inicio_autorizacion && $this->fecha_inicio_autorizacion->toDateString() > $today) {
            return false;
        }

        if ($this->fecha_fin_autorizacion && $this->fecha_fin_autorizacion->toDateString() < $today) {
            return false;
        }

        return true;
    }
}
