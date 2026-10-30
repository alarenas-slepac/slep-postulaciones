<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MaternidadCaso extends Model
{
    protected $table = 'maternidad_casos';
    protected $guarded = ['id'];

    protected function casts(): array
    {
        $casts = ['ley_dominga' => 'boolean', 'lactancia' => 'boolean', 'postnatal_parental' => 'boolean',
            'fuente_snapshot' => 'array', 'documentos_pendientes' => 'array', 'version' => 'integer'];
        foreach (['fecha_ingreso', 'fecha_termino', 'fecha_registro', 'fecha_certificado_embarazo', 'fpc', 'fpp', 'fnh',
            'fecha_ley_dominga', 'fecha_inicio_lactancia', 'fecha_inicio_postnatal', 'fecha_termino_postnatal',
            'fecha_rex_postnatal', 'fuero_estimado', 'fuero_nacimiento', 'fuero_dominga', 'fecha_termino_fuero', 'fecha_termino_lactancia'] as $campo) $casts[$campo] = 'date:Y-m-d';
        return $casts;
    }

    protected static function booted(): void
    {
        static::creating(fn (self $caso) => $caso->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string { return 'uuid'; }
    public function getNombreCompletoAttribute(): string { return trim($this->nombres.' '.$this->apellido_paterno.' '.$this->apellido_materno); }
    public function getCodigoAttribute(): string { return 'MAT-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT); }
    public function hijos(): HasMany { return $this->hasMany(MaternidadHijo::class)->orderBy('orden')->orderBy('id'); }
    public function documentos(): HasMany { return $this->hasMany(MaternidadDocumento::class)->orderByDesc('id'); }
    public function historial(): HasMany { return $this->hasMany(MaternidadHistorial::class)->orderByDesc('id'); }
}
