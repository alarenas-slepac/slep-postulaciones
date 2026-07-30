<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AgendamientoRecursoCatalogo extends Model
{
    use HasFactory;

    protected $table = 'agendamiento_recursos_catalogo';

    protected $fillable = [
        'nombre',
        'slug',
        'tipo',
        'ubicacion',
        'descripcion',
        'requiere_aprobacion',
        'activo',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'requiere_aprobacion' => 'boolean',
        'activo' => 'boolean',
    ];

    public const TIPO_SALA = 'sala';
    public const TIPO_PROYECTOR = 'proyector';

    public static function tipos(): array
    {
        return [
            self::TIPO_SALA => 'Sala de reuniones',
            self::TIPO_PROYECTOR => 'Proyector',
        ];
    }

    public static function recursosDireccionEjecutiva(): array
    {
        return [
            'sala_direccion_ejecutiva_gabinete',
            'sala_1_4to_piso',
            'sala_2_4to_piso',
        ];
    }

    public static function recursosGdp(): array
    {
        return [
            'sala_gdp',
            'proyector',
        ];
    }

    public function agendamientos(): HasMany
    {
        return $this->hasMany(AgendamientoRecurso::class, 'recurso_catalogo_id');
    }

    public function administradores(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'agendamiento_recurso_administradores', 'recurso_id', 'user_id')
            ->withPivot(['created_by'])
            ->withTimestamps();
    }

    public function getTipoLabelAttribute(): string
    {
        return self::tipos()[$this->tipo] ?? ucfirst((string) $this->tipo);
    }
}
