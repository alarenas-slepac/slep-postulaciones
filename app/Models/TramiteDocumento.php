<?php

namespace App\Models;

use App\Support\RutChile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class TramiteDocumento extends Model
{
    use HasFactory;

    protected $fillable = [
        'tramite_id',
        'uploaded_by',
        'tipo_documento',
        'formato',
        'path',
        'original_name',
        'mime',
        'size',
        'fecha_inicio',
        'fecha_termino',
        'estado_revision',
        'reviewed_by',
        'reviewed_at',
        'revision_observacion',
        'captura_estado',
        'captura_metodo',
        'captura_ejecutada_at',
        'captura_rut',
        'captura_periodos',
        'captura_rango_inicio',
        'captura_rango_termino',
        'captura_total_periodos',
        'captura_tiene_interrupciones',
        'captura_comparacion_periodo',
        'captura_observaciones',
        'captura_payload',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_termino' => 'date',
            'reviewed_at' => 'datetime',
            'size' => 'integer',
            'captura_ejecutada_at' => 'datetime',
            'captura_periodos' => 'array',
            'captura_rango_inicio' => 'date',
            'captura_rango_termino' => 'date',
            'captura_total_periodos' => 'integer',
            'captura_tiene_interrupciones' => 'boolean',
            'captura_payload' => 'array',
        ];
    }

    public function tramite(): BelongsTo
    {
        return $this->belongsTo(Tramite::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getEstadoRevisionLabelAttribute(): string
    {
        return match ((string) $this->estado_revision) {
            'aprobado' => 'Aprobado',
            'rechazado' => 'Rechazado',
            default => 'Pendiente',
        };
    }

    public function getEstadoRevisionBadgeClassAttribute(): string
    {
        return match ((string) $this->estado_revision) {
            'aprobado' => 'text-bg-success',
            'rechazado' => 'text-bg-danger',
            default => 'text-bg-warning',
        };
    }

    public function getTipoDocumentoLabelAttribute(): string
    {
        $tipo = $this->tramite?->tipo ?? 'reconocimiento_bienios';

        return (string) data_get(config('tramites.tipos'), $tipo . '.documentos.' . $this->tipo_documento . '.label', $this->tipo_documento);
    }

    public function supportsDataCapture(): bool
    {
        $tipo = $this->tramite?->tipo ?? 'reconocimiento_bienios';

        return (bool) data_get(config('tramites.tipos'), $tipo . '.documentos.' . $this->tipo_documento . '.capture_enabled', false);
    }

    public function requiresPeriod(): bool
    {
        $tipo = $this->tramite?->tipo ?? 'reconocimiento_bienios';

        return (bool) data_get(config('tramites.tipos'), $tipo . '.documentos.' . $this->tipo_documento . '.requires_period', false);
    }

    public function getRequiresPeriodAttribute(): bool
    {
        return $this->requiresPeriod();
    }

    public function getSupportsDataCaptureAttribute(): bool
    {
        return $this->supportsDataCapture();
    }

    public function canRunDataCapture(): bool
    {
        return $this->supportsDataCapture() && (string) $this->estado_revision === 'aprobado';
    }

    public function getCanRunDataCaptureAttribute(): bool
    {
        return $this->canRunDataCapture();
    }

    public function getCapturaEstadoLabelAttribute(): string
    {
        return match ((string) $this->captura_estado) {
            'procesado' => 'Procesado',
            'requiere_revision' => 'Requiere revisión',
            'sin_resultado' => 'Sin resultado',
            'error' => 'Error',
            'no_aplica' => 'No aplica',
            default => 'Pendiente',
        };
    }

    public function getCapturaEstadoBadgeClassAttribute(): string
    {
        return match ((string) $this->captura_estado) {
            'procesado' => 'text-bg-success',
            'requiere_revision' => 'text-bg-warning',
            'sin_resultado' => 'text-bg-secondary',
            'error' => 'text-bg-danger',
            'no_aplica' => 'text-bg-dark',
            default => 'text-bg-light text-dark border',
        };
    }

    public function getCapturaMetodoLabelAttribute(): string
    {
        if ((string) $this->captura_metodo === 'ocr') {
            $driver = (string) data_get($this->captura_payload, 'ocr_meta.driver', '');

            return match ($driver) {
                'python_easyocr' => 'OCR Python',
                'tesseract' => 'OCR local',
                default => 'OCR',
            };
        }

        return match ((string) $this->captura_metodo) {
            'pdf_texto' => 'PDF con texto',
            default => '—',
        };
    }

    public function getCapturaComparacionLabelAttribute(): string
    {
        return match ((string) $this->captura_comparacion_periodo) {
            'exacto' => 'Coincide exactamente',
            'contenido_continuo' => 'Cubre el período de forma continua',
            'rango_resumen_con_interrupciones' => 'Rango resumen con interrupciones',
            'coincidencia_parcial' => 'Coincidencia parcial',
            'no_coincide' => 'No coincide',
            'sin_periodos_detectados' => 'Sin períodos detectados',
            'sin_periodo_asociado' => 'Sin período asociado',
            default => '—',
        };
    }

    public function getCapturaPeriodosCollectionAttribute(): Collection
    {
        return collect($this->captura_periodos ?? []);
    }

    public function getCapturaRutCoincideConTramiteAttribute(): ?bool
    {
        if (!$this->captura_rut || !$this->tramite?->rut_snapshot) {
            return null;
        }

        $captura = RutChile::normalize((string) $this->captura_rut);
        $tramite = RutChile::normalize((string) $this->tramite->rut_snapshot);
        if (!$captura || !$tramite) {
            return null;
        }

        return strtoupper((string) $captura['rut']) === strtoupper((string) $tramite['rut']);
    }
}
