<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use DateTimeInterface;

class BolsaTrabajoOferta extends Model
{
    public const ETAPA_RECEPCION_ANTECEDENTES = 'recepcion_antecedentes';
    public const ETAPA_EVALUACION_ANTECEDENTES = 'evaluacion_antecedentes';
    public const ETAPA_ENTREVISTA_PSICOLABORAL = 'entrevista_psicolaboral';
    public const ETAPA_ENTREVISTA_FINAL = 'entrevista_final';
    public const ETAPA_CERRADO = 'cerrado';
    public const ETAPA_DESIERTO = 'desierto';

    protected $table = 'bolsa_trabajo_ofertas';

    protected $fillable = [
        'establecimiento_id',
        'establecimientos_ids',
        'comuna',
        'estamento',
        'area_desempeno_id',
        'calidad_contractual',
        'cantidad_horas',
        'remuneracion_bruta',
        'inicio_trabajo_aproximado',
        'fecha_inicio_postulaciones',
        'hora_inicio_postulaciones',
        'fecha_termino_postulaciones',
        'hora_termino_postulaciones',
        'correo_contacto',
        'bases_pdf_path',
        'bases_pdf_original_name',
        'etapa_estado',
        'selected_postulacion_id',
        'etapa_changed_at',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'establecimiento_id' => 'integer',
            'establecimientos_ids' => 'array',
            'area_desempeno_id' => 'integer',
            'cantidad_horas' => 'integer',
            'remuneracion_bruta' => 'integer',
            'inicio_trabajo_aproximado' => 'date',
            'fecha_inicio_postulaciones' => 'date',
            'fecha_termino_postulaciones' => 'date',
            'selected_postulacion_id' => 'integer',
            'etapa_changed_at' => 'datetime',
        ];
    }

    public static function etapaOptions(): array
    {
        return [
            self::ETAPA_RECEPCION_ANTECEDENTES => 'Recepción de antecedentes',
            self::ETAPA_EVALUACION_ANTECEDENTES => 'Evaluación de antecedentes',
            self::ETAPA_ENTREVISTA_PSICOLABORAL => 'Entrevista psicolaboral',
            self::ETAPA_ENTREVISTA_FINAL => 'Entrevista final',
            self::ETAPA_CERRADO => 'Cerrado',
            self::ETAPA_DESIERTO => 'Desierto',
        ];
    }

    public static function etapasEvaluables(): array
    {
        return [
            self::ETAPA_EVALUACION_ANTECEDENTES,
            self::ETAPA_ENTREVISTA_PSICOLABORAL,
            self::ETAPA_ENTREVISTA_FINAL,
        ];
    }

    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id');
    }


    public function getEstablecimientosSeleccionadosIdsAttribute(): array
    {
        $ids = collect($this->establecimientos_ids ?: [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (!empty($ids)) {
            return $ids;
        }

        return $this->establecimiento_id ? [(int) $this->establecimiento_id] : [];
    }

    public function getEstablecimientosSeleccionadosAttribute()
    {
        $ids = $this->establecimientos_seleccionados_ids;
        if (empty($ids)) {
            return collect();
        }

        $establecimientos = Establecimiento::query()
            ->whereIn('id', $ids)
            ->get(['id', 'rbd', 'nombre_establecimiento', 'comuna'])
            ->keyBy('id');

        return collect($ids)
            ->map(fn ($id) => $establecimientos->get((int) $id))
            ->filter()
            ->values();
    }

    public function getEstablecimientosDisplayAttribute(): string
    {
        $labels = $this->establecimientos_seleccionados
            ->map(function ($establecimiento) {
                $rbd = trim((string) ($establecimiento->rbd ?? ''));
                $nombre = trim((string) ($establecimiento->nombre_establecimiento ?? ''));

                return trim(($rbd !== '' ? $rbd . ' - ' : '') . $nombre);
            })
            ->filter()
            ->values();

        if ($labels->isNotEmpty()) {
            return $labels->implode(', ');
        }

        $fallback = trim((string) (optional($this->establecimiento)->rbd ?: ''));
        $fallbackName = trim((string) (optional($this->establecimiento)->nombre_establecimiento ?: ''));

        return trim(($fallback !== '' ? $fallback . ' - ' : '') . $fallbackName);
    }

    public function getRbdsDisplayAttribute(): string
    {
        $rbds = $this->establecimientos_seleccionados
            ->map(fn ($establecimiento) => preg_replace('/[^0-9A-Za-z]/', '', (string) ($establecimiento->rbd ?? '')))
            ->filter()
            ->values();

        if ($rbds->isNotEmpty()) {
            return $rbds->implode('_');
        }

        return preg_replace('/[^0-9A-Za-z]/', '', (string) (optional($this->establecimiento)->rbd ?: 'sin-rbd')) ?: 'sin-rbd';
    }

    public function areaDesempeno(): BelongsTo
    {
        return $this->belongsTo(AreaDesempeno::class, 'area_desempeno_id');
    }

    public function creador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function actualizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function postulaciones(): HasMany
    {
        return $this->hasMany(BolsaTrabajoPostulacion::class, 'bolsa_trabajo_oferta_id');
    }

    public function selectedPostulacion(): BelongsTo
    {
        return $this->belongsTo(BolsaTrabajoPostulacion::class, 'selected_postulacion_id');
    }

    public static function portalTimezone(): string
    {
        return (string) (config('app.display_timezone') ?: config('app.timezone') ?: 'America/Santiago');
    }

    public static function portalNow(?DateTimeInterface $now = null): Carbon
    {
        if ($now instanceof Carbon) {
            return $now->copy()->setTimezone(static::portalTimezone());
        }

        if ($now instanceof DateTimeInterface) {
            return Carbon::instance($now)->setTimezone(static::portalTimezone());
        }

        return now(static::portalTimezone());
    }

    public function scopeVisibleEnPortal(Builder $query, ?Carbon $now = null): Builder
    {
        $now = static::portalNow($now);

        return $query
            ->where(function (Builder $stage) {
                $stage->whereNull('etapa_estado')
                    ->orWhere('etapa_estado', self::ETAPA_RECEPCION_ANTECEDENTES);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereDate('fecha_inicio_postulaciones', '<', $now->toDateString())
                    ->orWhere(function (Builder $sameDay) use ($now) {
                        $sameDay->whereDate('fecha_inicio_postulaciones', '=', $now->toDateString())
                            ->whereTime('hora_inicio_postulaciones', '<=', $now->format('H:i:s'));
                    });
            });
    }

    public function currentEtapaKey(): string
    {
        $value = (string) ($this->etapa_estado ?: self::ETAPA_RECEPCION_ANTECEDENTES);
        return array_key_exists($value, self::etapaOptions()) ? $value : self::ETAPA_RECEPCION_ANTECEDENTES;
    }

    public function isVisibleEnPortal(?Carbon $now = null): bool
    {
        if ($this->currentEtapaKey() !== self::ETAPA_RECEPCION_ANTECEDENTES) {
            return false;
        }

        $inicio = $this->ventana_inicio;
        if (!$inicio) {
            return true;
        }

        $now = static::portalNow($now);

        return $inicio->lessThanOrEqualTo($now);
    }

    public function isPostulacionAbierta(?Carbon $now = null): bool
    {
        if ($this->currentEtapaKey() !== self::ETAPA_RECEPCION_ANTECEDENTES) {
            return false;
        }

        $now = static::portalNow($now);
        $inicio = $this->ventana_inicio;
        $termino = $this->ventana_termino;

        if ($inicio && $inicio->greaterThan($now)) {
            return false;
        }

        if ($termino && $termino->lessThan($now)) {
            return false;
        }

        return true;
    }

    public function getEstamentoLabelAttribute(): string
    {
        return match ((string) $this->estamento) {
            'docente' => 'Docente',
            'asistente' => 'Asistente',
            default => ucfirst((string) $this->estamento),
        };
    }

    public function getRemuneracionBrutaFormattedAttribute(): string
    {
        $monto = (int) ($this->remuneracion_bruta ?? 0);
        if ($monto <= 0) {
            return '—';
        }

        return '$' . number_format($monto, 0, ',', '.');
    }

    public function getCalidadContractualLabelAttribute(): string
    {
        return match ((string) $this->calidad_contractual) {
            'reemplazo' => 'Reemplazo',
            'contrata' => 'Contrata',
            'plazo_fijo' => 'Plazo Fijo',
            default => ucfirst(str_replace('_', ' ', (string) $this->calidad_contractual)),
        };
    }

    public function getEtapaLabelAttribute(): string
    {
        return self::etapaOptions()[$this->currentEtapaKey()] ?? 'Recepción de antecedentes';
    }

    public function getSelectedPostulanteNameAttribute(): ?string
    {
        return $this->selectedPostulacion?->user?->display_name
            ?? $this->selectedPostulacion?->user?->full_name
            ?? null;
    }

    public function getVentanaInicioAttribute(): ?Carbon
    {
        if (!$this->fecha_inicio_postulaciones || !$this->hora_inicio_postulaciones) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d H:i:s', $this->fecha_inicio_postulaciones->format('Y-m-d') . ' ' . $this->hora_inicio_postulaciones, static::portalTimezone());
    }

    public function getVentanaTerminoAttribute(): ?Carbon
    {
        if (!$this->fecha_termino_postulaciones || !$this->hora_termino_postulaciones) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d H:i:s', $this->fecha_termino_postulaciones->format('Y-m-d') . ' ' . $this->hora_termino_postulaciones, static::portalTimezone());
    }
}
