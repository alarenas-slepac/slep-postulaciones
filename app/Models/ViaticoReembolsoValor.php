<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViaticoReembolsoValor extends Model
{
    protected $table = 'viaticos_reembolsos_valores';

    protected $fillable = [
        'estamento',
        'cargo_funcion',
        'vigente_desde',
        'vigente_hasta',
        'valor_100',
        'valor_60',
        'valor_40',
        'activo',
    ];

    protected $casts = [
        'vigente_desde' => 'date',
        'vigente_hasta' => 'date',
        'valor_100' => 'integer',
        'valor_60' => 'integer',
        'valor_40' => 'integer',
        'activo' => 'boolean',
    ];


    public function valor60Vigente(): int
    {
        if ($this->valor_60 !== null) {
            return (int) $this->valor_60;
        }

        return (int) round(((int) $this->valor_100) * 0.60);
    }

    public function getValor60CalculadoAttribute(): int
    {
        return $this->valor60Vigente();
    }

    public static function estamentos(): array
    {
        return ['Docente', 'AAEE', 'Código Administrativo'];
    }

    public static function cargosPorEstamento(): array
    {
        return [
            'Docente' => [
                'Director',
                'Docente Directivo',
                'Docentes',
            ],
            'AAEE' => [
                'Directora Junji',
                'Educadora de Párvulos',
                'Profesional',
                'Técnico',
                'Administrativo',
                'Auxiliar',
            ],
            'Código Administrativo' => [
                '1° al 4°',
                '5° al 10°',
                '11° al 21°',
                '22° al 31°',
            ],
        ];
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }
}
