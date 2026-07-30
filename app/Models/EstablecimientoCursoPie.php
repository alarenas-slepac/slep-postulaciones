<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\PieHorasCalculator;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstablecimientoCursoPie extends Model
{
    protected $table = 'establecimiento_curso_pie';

    protected $fillable = [
        'establecimiento_id',
        'establecimiento_curso_id',
        'curso_id',
        'plan_estudio_id',
        'anio',
        'rbd',
        'necesidades_transitorias',
        'necesidades_permanentes',
        'total_pie',
        'observacion',
        'estado',
        'regimen_calculo',
        'neet_calculo',
        'neep_calculo',
        'total_crono_minutos',
        'prof_educ_dif_minutos',
        'pae_minutos',
        'calculo_observacion',
        'calculado_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'establecimiento_id' => 'integer',
        'establecimiento_curso_id' => 'integer',
        'curso_id' => 'integer',
        'plan_estudio_id' => 'integer',
        'anio' => 'integer',
        'rbd' => 'integer',
        'necesidades_transitorias' => 'integer',
        'necesidades_permanentes' => 'integer',
        'total_pie' => 'integer',
        'neet_calculo' => 'integer',
        'neep_calculo' => 'integer',
        'total_crono_minutos' => 'integer',
        'prof_educ_dif_minutos' => 'integer',
        'pae_minutos' => 'integer',
        'calculado_at' => 'datetime',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];

    public const ESTADOS = [
        'borrador' => 'Borrador',
        'en_revision' => 'En revisión',
        'validado' => 'Validado',
        'observado' => 'Observado',
    ];

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }

    public function establecimientoCurso(): BelongsTo
    {
        return $this->belongsTo(EstablecimientoCurso::class, 'establecimiento_curso_id');
    }

    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    public function planEstudio(): BelongsTo
    {
        return $this->belongsTo(PlanEstudio::class, 'plan_estudio_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function actualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function estadoLabel(): string
    {
        return self::ESTADOS[$this->estado] ?? ucfirst((string) $this->estado);
    }

    public function regimenCalculoLabel(): string
    {
        return match ($this->regimen_calculo) {
            'con_jec' => 'CON JEC',
            'sin_jec' => 'SIN JEC',
            default => 'Sin cálculo',
        };
    }

    public function totalCronoLabel(): string
    {
        return PieHorasCalculator::formatMinutes($this->total_crono_minutos);
    }

    public function profEducDifLabel(): string
    {
        return PieHorasCalculator::formatMinutes($this->prof_educ_dif_minutos);
    }

    public function educadoresDiferencialesNecesariosLabel(): string
    {
        return PieHorasCalculator::formatEducadoresDiferenciales($this->prof_educ_dif_minutos);
    }

    public function educadoresDiferencialesRedondeados(): int
    {
        return PieHorasCalculator::educadoresDiferencialesRedondeados($this->prof_educ_dif_minutos);
    }

    public function paeLabel(): string
    {
        return PieHorasCalculator::formatMinutes($this->pae_minutos);
    }
}

