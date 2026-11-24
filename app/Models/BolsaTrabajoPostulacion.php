<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BolsaTrabajoPostulacion extends Model
{
    protected $table = 'bolsa_trabajo_postulaciones';

    protected $fillable = [
        'bolsa_trabajo_oferta_id',
        'user_id',
        'postulant_profile_id',
        'estado',
        'observacion',
        'avanza_etapa',
        'cargo_presentado',
    ];

    protected function casts(): array
    {
        return [
            'avanza_etapa' => 'boolean',
            'cargo_presentado' => 'array',
        ];
    }

    public function oferta(): BelongsTo
    {
        return $this->belongsTo(BolsaTrabajoOferta::class, 'bolsa_trabajo_oferta_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function postulantProfile(): BelongsTo
    {
        return $this->belongsTo(PostulantProfile::class, 'postulant_profile_id');
    }

    public function submittedDocuments(): BelongsToMany
    {
        return $this->belongsToMany(UserDocument::class, 'bolsa_trabajo_documentos_presentados', 'postulacion_id', 'user_document_id');
    }

    public function canParticipateInStageSelection(): bool
    {
        return !in_array((string) $this->estado, ['no_avanza', 'seleccionado', 'cerrado_no_seleccionado', 'proceso_desierto'], true);
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ((string) $this->estado) {
            'postulado' => 'Postulado',
            'en_proceso' => 'En proceso',
            'no_avanza' => 'No avanza',
            'seleccionado' => 'Seleccionado/a',
            'cerrado_no_seleccionado' => 'Proceso cerrado',
            'proceso_desierto' => 'Proceso desierto',
            default => ucfirst(str_replace('_', ' ', (string) $this->estado)),
        };
    }
}
