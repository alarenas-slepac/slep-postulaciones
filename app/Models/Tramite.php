<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class Tramite extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tipo',
        'bienios_flujo_externo',
        'estado',
        'rut_snapshot',
        'nombre_completo_snapshot',
        'email_snapshot',
        'estatuto_snapshot',
        'escalafon_snapshot',
        'establecimiento_id_snapshot',
        'establecimiento_nombre_snapshot',
        'enviado_at',
        'anulado_at',
        'anulado_por_user_id',
        'anulado_motivo',
        'calculo_periodos_habilitado_at',
        'calculo_periodos_data',
        'rex_generado_at',
        'rex_fecha_reconocimiento',
        'rex_docx_path',
        'resolucion_pdf_path',
        'resolucion_pdf_uploaded_at',
        'detalle_calculo_pdf_path',
        'detalle_calculo_pdf_uploaded_at',
        'detalle_calculo_pdf_uploaded_by_user_id',
        'resultado_enviado_at',
        'resuelto_at',
    ];

    protected function casts(): array
    {
        return [
            'bienios_flujo_externo' => 'boolean',
            'enviado_at' => 'datetime',
            'anulado_at' => 'datetime',
            'calculo_periodos_habilitado_at' => 'datetime',
            'calculo_periodos_data' => 'array',
            'rex_generado_at' => 'datetime',
            'rex_fecha_reconocimiento' => 'date',
            'resolucion_pdf_uploaded_at' => 'datetime',
            'detalle_calculo_pdf_uploaded_at' => 'datetime',
            'resultado_enviado_at' => 'datetime',
            'resuelto_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function anuladoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por_user_id');
    }

    public function establecimientoSnapshot(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class, 'establecimiento_id_snapshot');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(TramiteDocumento::class)->latest('id');
    }

    public function canBeEditedBy(User $user): bool
    {
        if ((bool) $this->bienios_flujo_externo
            && ($this->resolucion_pdf_path || $this->detalle_calculo_pdf_path)) {
            return false;
        }

        return (int) $this->user_id === (int) $user->id
            && in_array((string) $this->estado, ['enviado', 'en_revision'], true);
    }

    public function syncEstadoRevisionFromDocumentos(): bool
    {
        if (in_array((string) $this->estado, ['anulado', 'resuelto'], true)) {
            return false;
        }

        $hasReviewedDocuments = $this->documentos()
            ->whereIn('estado_revision', ['aprobado', 'rechazado'])
            ->exists();

        if ($hasReviewedDocuments && (string) $this->estado !== 'en_revision') {
            $this->forceFill(['estado' => 'en_revision'])->save();
            $this->refresh();
            return true;
        }

        return false;
    }

    public function getTipoLabelAttribute(): string
    {
        return (string) data_get(config('tramites.tipos'), $this->tipo . '.label', $this->tipo);
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ((string) $this->estado) {
            'enviado' => 'Enviado',
            'en_revision' => 'En revisión',
            'resuelto' => 'Resuelto',
            'anulado' => 'Anulado',
            default => ucfirst(str_replace('_', ' ', (string) $this->estado)),
        };
    }

    public function getEstadoBadgeClassAttribute(): string
    {
        return match ((string) $this->estado) {
            'anulado' => 'text-bg-danger',
            'resuelto' => 'text-bg-success',
            'en_revision' => 'text-bg-warning',
            default => 'text-bg-info',
        };
    }

    public function getCalculoPeriodosBlocksCollectionAttribute(): Collection
    {
        return collect($this->calculo_periodos_data ?? [])->values();
    }

    public function getHasCalculoPeriodosAttribute(): bool
    {
        return $this->calculo_periodos_blocks_collection->isNotEmpty();
    }

    public function getCalculoPeriodosConfirmadosCountAttribute(): int
    {
        return (int) $this->calculo_periodos_blocks_collection
            ->sum(fn ($block) => count((array) data_get($block, 'periodos', [])));
    }

    public function getCalculoPeriodosFlattenedCollectionAttribute(): Collection
    {
        return $this->calculo_periodos_blocks_collection
            ->flatMap(function ($block, $blockIndex) {
                return collect((array) data_get($block, 'periodos', []))
                    ->map(function ($periodo, $periodIndex) use ($block, $blockIndex) {
                        $inicio = (string) data_get($periodo, 'inicio', '');
                        $termino = (string) data_get($periodo, 'termino', '');

                        return [
                            'block_index' => $blockIndex,
                            'period_index' => $periodIndex,
                            'documento_id' => data_get($block, 'documento_id'),
                            'documento_tipo' => data_get($block, 'documento_tipo'),
                            'documento_label' => (string) data_get($block, 'documento_label', 'Documento'),
                            'documento_nombre' => (string) data_get($block, 'documento_nombre', '—'),
                            'inicio' => $inicio,
                            'termino' => $termino,
                            'dias' => self::calculatePeriodDays($inicio, $termino),
                            'referencia' => (string) data_get($periodo, 'referencia', ''),
                            'origen' => (string) data_get($periodo, 'origen', 'confirmado'),
                        ];
                    });
            })
            ->values();
    }

    public function getCalculoPeriodosResumenAttribute(): array
    {
        $periodos = $this->calculo_periodos_flattened_collection;
        $totalDays = (int) $periodos->sum(fn ($periodo) => (int) ($periodo['dias'] ?? 0));
        $bieniosSinTope = intdiv(max($totalDays, 0), 730);
        $bienios = min($bieniosSinTope, 15);
        $maxDays = 15 * 730;
        $remainingDays = $bienios >= 15 ? 0 : max((($bienios + 1) * 730) - $totalDays, 0);

        return [
            'total_periodos' => (int) $periodos->count(),
            'total_dias' => $totalDays,
            'duracion' => self::decomposeDaysToCalendarParts($totalDays),
            'bienios' => $bienios,
            'bienios_sin_tope' => $bieniosSinTope,
            'bienios_topados' => $bieniosSinTope > 15,
            'max_bienios' => 15,
            'max_dias_bienios' => $maxDays,
            'siguiente_bienio' => $bienios >= 15 ? 15 : ($bienios + 1),
            'dias_para_siguiente_bienio' => $remainingDays,
            'duracion_para_siguiente_bienio' => self::decomposeDaysToCalendarParts($remainingDays),
        ];
    }

    public function getHasResolucionReconocimientoAttribute(): bool
    {
        return !empty($this->rex_generado_at)
            || !empty($this->rex_docx_path)
            || !empty($this->resolucion_pdf_path)
            || !empty($this->detalle_calculo_pdf_path);
    }

    public static function calculatePeriodDays(?string $inicio, ?string $termino): ?int
    {
        $inicioDate = self::parsePeriodDate($inicio);
        $terminoDate = self::parsePeriodDate($termino);

        if (!$inicioDate || !$terminoDate || $terminoDate->lt($inicioDate)) {
            return null;
        }

        return $inicioDate->diffInDays($terminoDate) + 1;
    }

    public static function decomposeDaysToCalendarParts(int $days): array
    {
        $days = max($days, 0);
        $anchor = Carbon::create(2001, 1, 1, 0, 0, 0, 'UTC');
        $end = $anchor->copy()->addDays($days);
        $diff = $anchor->diff($end);

        return [
            'years' => (int) $diff->y,
            'months' => (int) $diff->m,
            'days' => (int) $diff->d,
        ];
    }

    protected static function parsePeriodDate(?string $value): ?Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
