<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DotacionDocenteAsignacion extends Model
{
    protected $table = 'dotacion_docente_asignaciones';

    protected $fillable = [
        'anio',
        'establecimiento_id',
        'docente_rut',
        'docente_rut_normalizado',
        'docente_nombre',
        'reemplazos_personal_id',
        'declaracion_sostenedor_id',
        'estamento_cobertura',
        'tipo_asignacion',
        'subtipo_asignacion',
        'subvencion',
        'necesidad_key',
        'establecimiento_curso_id',
        'dotacion_curso_combinado_id',
        'dotacion_curso_combinado_asignatura_id',
        'plan_estudio_id',
        'plan_bloque_id',
        'asignatura_id',
        'asignatura_nombre',
        'dotacion_funcion_id',
        'dotacion_funcion_regla_id',
        'horas_plan_pedagogicas',
        'horas_contrato',
        'horas_cronologicas_aula',
        'proporcion_aplicada',
        'fuente_calculo',
        'observacion',
        'estado',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'anio' => 'integer',
        'establecimiento_id' => 'integer',
        'reemplazos_personal_id' => 'integer',
        'declaracion_sostenedor_id' => 'integer',
        'establecimiento_curso_id' => 'integer',
        'dotacion_curso_combinado_id' => 'integer',
        'dotacion_curso_combinado_asignatura_id' => 'integer',
        'plan_estudio_id' => 'integer',
        'plan_bloque_id' => 'integer',
        'asignatura_id' => 'integer',
        'dotacion_funcion_id' => 'integer',
        'dotacion_funcion_regla_id' => 'integer',
        'horas_plan_pedagogicas' => 'decimal:2',
        'horas_contrato' => 'decimal:2',
        'horas_cronologicas_aula' => 'decimal:2',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public const ESTAMENTOS_COBERTURA = [
        'docente' => 'Docente',
        'asistente' => 'Asistente de la educación',
    ];

    public const TIPOS = [
        'plan_estudio' => 'Plan de estudio',
        'pie_colaborativo' => 'Trabajo colaborativo PIE',
        'pie_educadora_diferencial' => 'Educadoras diferenciales PIE',
        'funcion_directiva' => 'Función directiva',
        'funcion_tecnico_pedagogica' => 'Función técnico-pedagógica',
        'plan_normativo' => 'Plan normativo',
        'otra_funcion' => 'Otra función',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function establecimientoCurso(): BelongsTo
    {
        return $this->belongsTo(EstablecimientoCurso::class, 'establecimiento_curso_id');
    }

    public function declaracionSostenedor(): BelongsTo
    {
        return $this->belongsTo(DeclaracionSostenedor::class, 'declaracion_sostenedor_id');
    }

    public function cursoCombinado(): BelongsTo
    {
        return $this->belongsTo(DotacionCursoCombinado::class, 'dotacion_curso_combinado_id');
    }

    public function cursoCombinadoAsignatura(): BelongsTo
    {
        return $this->belongsTo(DotacionCursoCombinadoAsignatura::class, 'dotacion_curso_combinado_asignatura_id');
    }

    public function getTipoLabelAttribute(): string
    {
        return self::TIPOS[$this->tipo_asignacion] ?? ucfirst(str_replace('_', ' ', (string) $this->tipo_asignacion));
    }

    public function getEstamentoCoberturaLabelAttribute(): string
    {
        return self::ESTAMENTOS_COBERTURA[$this->estamento_cobertura ?: 'docente']
            ?? ucfirst(str_replace('_', ' ', (string) $this->estamento_cobertura));
    }
}
