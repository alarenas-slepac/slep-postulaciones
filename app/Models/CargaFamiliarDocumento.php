<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CargaFamiliarDocumento extends Model
{
    use HasFactory;

    protected $table = 'cargas_familiares_documentos';

    protected $fillable = [
        'solicitud_id',
        'causante_id',
        'nivel',
        'tipo_documento',
        'path',
        'original_name',
        'mime',
        'size',
        'estado_revision',
        'revision_observacion',
        'uploaded_by',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function solicitud(): BelongsTo
    {
        return $this->belongsTo(CargaFamiliarSolicitud::class, 'solicitud_id');
    }

    public function causante(): BelongsTo
    {
        return $this->belongsTo(CargaFamiliarCausante::class, 'causante_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getTipoDocumentoLabelAttribute(): string
    {
        return self::labels()[(string) $this->tipo_documento] ?? (string) $this->tipo_documento;
    }

    public function getEstadoRevisionLabelAttribute(): string
    {
        return match ((string) $this->estado_revision) {
            'aprobado' => 'Aprobado',
            'observado' => 'Observado',
            'rechazado' => 'Rechazado',
            default => 'Pendiente',
        };
    }

    public function getEstadoRevisionBadgeClassAttribute(): string
    {
        return match ((string) $this->estado_revision) {
            'aprobado' => 'text-bg-success',
            'observado' => 'text-bg-warning',
            'rechazado' => 'text-bg-danger',
            default => 'text-bg-light text-dark border',
        };
    }

    public static function labels(): array
    {
        return (array) config('cargas_familiares.documentos', []);
    }

    public static function causanteDocumentTypes(): array
    {
        $tipos = [];

        foreach ((array) config('cargas_familiares.codigos_causante', []) as $codigo) {
            foreach ((array) ($codigo['documentos_obligatorios'] ?? []) as $documento) {
                $tipos[$documento] = $documento;
            }

            foreach ((array) ($codigo['documentos_condicionales'] ?? []) as $condicional) {
                $documento = (string) ($condicional['documento'] ?? '');
                if ($documento !== '') {
                    $tipos[$documento] = $documento;
                }
            }
        }

        return array_values($tipos);
    }
}
