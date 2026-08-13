<?php

namespace App\Models;

use App\Models\AreaDesempeno;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PostulantProfile extends Model
{
    protected $fillable = [
        'user_id',
        'email_contacto',
        'fecha_nacimiento',
        'direccion',
        'region_code',
        'comuna_id',
        'nacionalidad',
        'telefono1',
        'telefono2',
        'genero',
        'pronombres',
        'foto_path',
        'foto_thumb_path',
        'estamento',
        'area_desempeno_id',
        'mencion',
        'especialidad_tp',
        'nivel_estudios',
        'institucion_titulo',
        'fecha_titulacion',
        'semestres',
        'horas_totales',
        'anios_experiencia',
        'cargos_funcion',
        'prevision_afp',
        'salud_institucion',
        'banco',
        'tipo_cuenta',
        'numero_cuenta',
        'deudor_pension_alimentos',
        'deudor_pension_alimentos_marcado_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_titulacion' => 'date',
            'semestres' => 'integer',
            'horas_totales' => 'integer',
            'anios_experiencia' => 'integer',
            'deudor_pension_alimentos' => 'boolean',
            'deudor_pension_alimentos_marcado_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relación correcta para la comuna seleccionada en el perfil
    public function comuna(): BelongsTo
    {
        return $this->belongsTo(Commune::class, 'comuna_id');
    }

    public function areaDesempeno(): BelongsTo
    {
        return $this->belongsTo(AreaDesempeno::class, 'area_desempeno_id');
    }

    public function contratosLaborales(): HasMany
    {
        return $this->hasMany(PostulantProfileContrato::class, 'postulant_profile_id')
            ->latest();
    }

    public function contratosLaboralesActivos(): HasMany
    {
        return $this->hasMany(PostulantProfileContrato::class, 'postulant_profile_id')
            ->where('activo', true)
            ->where(function ($query) {
                $query->whereNull('fecha_termino')
                    ->orWhereDate('fecha_termino', '>=', now()->toDateString());
            })
            ->with('establecimiento')
            ->orderBy('fecha_termino')
            ->orderByDesc('created_at');
    }

    /**
     * Relación conservada por compatibilidad: devuelve una vinculación activa representativa.
     */
    public function contratoLaboralActivo(): HasOne
    {
        return $this->hasOne(PostulantProfileContrato::class, 'postulant_profile_id')
            ->where('activo', true)
            ->where(function ($query) {
                $query->whereNull('fecha_termino')
                    ->orWhereDate('fecha_termino', '>=', now()->toDateString());
            })
            ->latestOfMany();
    }

    public function ultimaVinculacionLaboral(): HasOne
    {
        return $this->hasOne(PostulantProfileContrato::class, 'postulant_profile_id')
            ->latestOfMany();
    }

    public function deudasPensionAlimentos(): HasMany
    {
        return $this->hasMany(SolicitudReemplazoDeudaPension::class, 'postulant_profile_id');
    }

    // Para no romper lo antiguo:
    public function getAreaDesempenoNombreAttribute(): ?string
    {
        return $this->areaDesempeno?->nombre ?? ($this->area_desempeno ?? null);
    }
}
