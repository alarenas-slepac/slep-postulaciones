<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DotacionFuncionEstablecimiento extends Model
{
    protected $table = 'dotacion_funciones_establecimiento';

    protected $fillable = [
        'establecimiento_id',
        'regla_id',
        'anio',
        'categoria',
        'nombre_funcion',
        'tipo_coordinacion',
        'descripcion_funcion',
        'origen',
        'tipo_regla',
        'horas_sugeridas',
        'horas_declaradas',
        'horas_aprobadas',
        'fundamento',
        'observacion',
        'estado',
        'created_by',
        'updated_by',
        'validated_by',
        'validated_at',
    ];

    protected $casts = [
        'establecimiento_id' => 'integer',
        'regla_id' => 'integer',
        'anio' => 'integer',
        'horas_sugeridas' => 'integer',
        'horas_declaradas' => 'integer',
        'horas_aprobadas' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'validated_by' => 'integer',
        'validated_at' => 'datetime',
    ];

    public const ESTADOS = [
        'borrador' => 'Borrador',
        'en_revision' => 'En revisión',
        'observado' => 'Observado',
        'validado_uatp' => 'Validado UATP',
        'rechazado' => 'Rechazado',
    ];

    public const CATEGORIAS = [
        'directiva' => 'Funciones directivas',
        'tecnico_pedagogica' => 'Funciones técnico-pedagógicas',
        'planes_programas' => 'Planes normativos y programas',
        'otras_funciones_docentes' => 'Otras funciones docentes declaradas',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function regla(): BelongsTo
    {
        return $this->belongsTo(DotacionFuncionRegla::class, 'regla_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function actualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function validador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }

    public function estadoLabel(): string
    {
        return self::ESTADOS[$this->estado] ?? ucfirst((string) $this->estado);
    }

    public function categoriaLabel(): string
    {
        return self::CATEGORIAS[$this->categoria] ?? ucfirst(str_replace('_', ' ', (string) $this->categoria));
    }

    public function horasFinales(): int
    {
        if ($this->horas_aprobadas !== null) {
            return (int) $this->horas_aprobadas;
        }
        if ($this->horas_declaradas !== null) {
            return (int) $this->horas_declaradas;
        }
        return (int) ($this->horas_sugeridas ?? 0);
    }
}
