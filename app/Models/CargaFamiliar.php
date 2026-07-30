<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CargaFamiliar extends Model
{
    use HasFactory;

    protected $table = 'cargas_familiares';

    protected $fillable = [
        'user_id',
        'periodo_carga',
        'comuna_origen',
        'fuente_archivo',
        'beneficiario_run',
        'beneficiario_dv',
        'beneficiario_rut_completo',
        'beneficiario_run_normalizado',
        'beneficiario_apellido_paterno',
        'beneficiario_apellido_materno',
        'beneficiario_nombres',
        'beneficiario_email',
        'causante_run',
        'causante_dv',
        'causante_rut_completo',
        'causante_run_normalizado',
        'causante_apellido_paterno',
        'causante_apellido_materno',
        'causante_nombres',
        'sexo',
        'parentesco',
        'codigo_siagf',
        'tipo_beneficio',
        'codigo_tipo_causante',
        'fecha_nacimiento',
        'fecha_resolucion',
        'numero_resolucion',
        'fecha_inicio',
        'fecha_termino',
        'tipo',
        'tramo',
        'monto',
        'estado_carga',
        'observaciones',
        'raw_row',
        'imported_by',
        'imported_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_resolucion' => 'date',
            'fecha_inicio' => 'date',
            'fecha_termino' => 'date',
            'monto' => 'decimal:2',
            'raw_row' => 'array',
            'imported_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function causantesSolicitados(): HasMany
    {
        return $this->hasMany(CargaFamiliarCausante::class, 'carga_familiar_id');
    }

    public function getBeneficiarioNombreCompletoAttribute(): string
    {
        return trim(collect([
            $this->beneficiario_nombres,
            $this->beneficiario_apellido_paterno,
            $this->beneficiario_apellido_materno,
        ])->filter()->implode(' '));
    }

    public function getCausanteNombreCompletoAttribute(): string
    {
        return trim(collect([
            $this->causante_nombres,
            $this->causante_apellido_paterno,
            $this->causante_apellido_materno,
        ])->filter()->implode(' '));
    }

    public function getEdadAttribute(): ?int
    {
        return $this->fecha_nacimiento ? $this->fecha_nacimiento->age : null;
    }

    public function getEstadoCargaLabelAttribute(): string
    {
        return match ((string) $this->estado_carga) {
            'vigente' => 'Vigente',
            'extinguida' => 'Extinguida',
            'suspendida' => 'Suspendida',
            default => ucfirst((string) $this->estado_carga),
        };
    }

    public function getEstadoCargaBadgeClassAttribute(): string
    {
        return match ((string) $this->estado_carga) {
            'vigente' => 'text-bg-success',
            'suspendida' => 'text-bg-warning',
            'extinguida' => 'text-bg-secondary',
            default => 'text-bg-light text-dark border',
        };
    }
}
