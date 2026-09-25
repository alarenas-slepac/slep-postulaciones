<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DescuentoCgr extends Model
{
    protected $table = 'descuentos_cgr';

    protected $fillable = [
        'rut',
        'nombre',
        'origen_funcionario',
        'estado',
        'institucion_reintegro',
        'estamento_funcionario',
        'numero_resolucion',
        'numero_resolucion_clave',
        'fecha_resolucion',
        'deuda_definitiva_pesos',
        'deuda_equivalente_utm',
        'cuota_utm',
        'numero_cuotas',
        'tasa_interes_anual',
        'tasa_interes_mensual',
        'fecha_primer_descuento',
        'resolucion_pdf_path',
        'resolucion_pdf_nombre',
        'resolucion_pdf_tamano',
        'observaciones',
        'codigo_verificacion',
        'documento_hash',
        'documento_emitido_en',
        'creado_por_id',
        'actualizado_por_id',
        'enviado_finanzas_en',
        'enviado_auditoria_en',
        'cerrado_en',
        'certificado_generado_en',
        'certificado_generado_por_id',
        'certificado_firmado_path',
        'certificado_firmado_nombre',
        'certificado_firmado_en',
        'certificado_firmado_por_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_resolucion' => 'date',
            'fecha_primer_descuento' => 'date',
            'deuda_definitiva_pesos' => 'integer',
            'deuda_equivalente_utm' => 'decimal:4',
            'cuota_utm' => 'decimal:4',
            'numero_cuotas' => 'integer',
            'tasa_interes_anual' => 'decimal:4',
            'tasa_interes_mensual' => 'decimal:4',
            'resolucion_pdf_tamano' => 'integer',
            'documento_emitido_en' => 'datetime',
            'enviado_finanzas_en' => 'datetime',
            'enviado_auditoria_en' => 'datetime',
            'cerrado_en' => 'datetime',
            'certificado_generado_en' => 'datetime',
            'certificado_firmado_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $descuento): void {
            $claveNueva = self::normalizarNumeroResolucion((string) $descuento->numero_resolucion);
            $claveOriginal = self::normalizarNumeroResolucion((string) $descuento->getOriginal('numero_resolucion'));

            if (! $descuento->exists || $claveOriginal !== $claveNueva || $descuento->numero_resolucion_clave !== null) {
                $descuento->numero_resolucion_clave = $claveNueva;
            }
        });
    }

    public static function normalizarNumeroResolucion(string $numero): string
    {
        return Str::upper(Str::squish($numero));
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por_id');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por_id');
    }

    public function documentosMensuales(): HasMany
    {
        return $this->hasMany(DescuentoCgrDocumentoMensual::class, 'descuento_cgr_id');
    }

    public function archivos(): HasMany
    {
        return $this->hasMany(DescuentoCgrArchivo::class, 'descuento_cgr_id');
    }

    public function estadoActual(): string
    {
        return $this->estado ?: 'ingresado';
    }

    public function etiquetaEstado(): string
    {
        return match ($this->estadoActual()) {
            'descuentos_realizados' => 'Descuentos realizados',
            'en_auditoria' => 'En proceso de Auditoría',
            'cerrado' => 'Cerrado',
            default => 'Ingresado',
        };
    }
}
