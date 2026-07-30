<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\ReemplazoPersonal;
use App\Models\AreaDesempeno;
use App\Models\EstablecimientoAreaDesempeno;
use App\Models\AlumnoPrioritarioPorcentaje;
use App\Models\DotacionProporcionExcepcion;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Establecimiento extends Model
{
    protected $table = 'establecimientos';

    protected $fillable = [
        'cod_estab',
        'rbd',
        'dv',
        'nombre_establecimiento',
        'clasificacion',
        'tipo_estab',
        'sala_cuna',
        'unidocencia',
        'pre_escolar',
        'basica',
        'media',
        'tecnico_profesional',
        'adultos',
        'especial',
        'comuna',
        'asignacion_zona',
        'latitud',
        'longitud',
    ];

    protected $casts = [
        'cod_estab' => 'integer',
        'rbd' => 'integer',
        'sala_cuna' => 'boolean',
        'unidocencia' => 'boolean',
        'pre_escolar' => 'boolean',
        'basica' => 'boolean',
        'media' => 'boolean',
        'tecnico_profesional' => 'boolean',
        'adultos' => 'boolean',
        'especial' => 'boolean',
        'asignacion_zona' => 'integer',
        'latitud' => 'decimal:7',
        'longitud' => 'decimal:7',
    ];

    /**
     * Perfil editorial utilizado por el módulo de Admisión Escolar.
     */
    public function admisionPerfil(): HasOne
    {
        return $this->hasOne(AdmisionEstablecimiento::class, 'establecimiento_id');
    }

    /**
     * Etiquetas de niveles educativos disponibles para la vitrina pública.
     * Los valores provienen exclusivamente de la tabla establecimientos.
     *
     * @return array<int, string>
     */
    public function nivelesEducativos(): array
    {
        return collect([
            'Sala cuna' => (bool) $this->sala_cuna,
            'Educación parvularia' => (bool) $this->pre_escolar,
            'Educación básica' => (bool) $this->basica,
            'Educación media' => (bool) $this->media,
            'Técnico profesional' => (bool) $this->tecnico_profesional,
            'Educación de adultos' => (bool) $this->adultos,
            'Educación especial' => (bool) $this->especial,
        ])->filter()->keys()->values()->all();
    }

    public function alumnosPrioritariosPorcentajes()
    {
        return $this->hasMany(AlumnoPrioritarioPorcentaje::class, 'establecimiento_id');
    }

    public function dotacionProporcionExcepciones()
    {
        return $this->hasMany(DotacionProporcionExcepcion::class, 'establecimiento_id');
    }

    public function dotacionCursosCombinados()
    {
        return $this->hasMany(DotacionCursoCombinado::class, 'establecimiento_id');
    }

    public function personal()
    {
        // ajusta 'establecimiento_id' si tu FK tiene otro nombre
        return $this->hasMany(ReemplazoPersonal::class, 'establecimiento_id');
    }

    /**
     * Configuración por establecimiento (1 fila por área).
     */
    public function areasDesempenoConfig()
    {
        return $this->hasMany(EstablecimientoAreaDesempeno::class, 'establecimiento_id');
    }

    /**
     * Áreas asociadas al establecimiento (pivot con flag bloqueada).
     */
    public function areasDesempeno()
    {
        return $this->belongsToMany(
            AreaDesempeno::class,
            'establecimiento_area_desempeno',
            'establecimiento_id',
            'area_desempeno_id'
        )->withPivot(['bloqueada'])->withTimestamps();
    }

    public function areasDesempenoBloqueadas()
    {
        return $this->areasDesempeno()->wherePivot('bloqueada', true);
    }
}
