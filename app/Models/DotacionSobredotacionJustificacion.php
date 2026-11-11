<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DotacionSobredotacionJustificacion extends Model
{
    public const NIVELES_ENSENANZA = [
        'basica' => 'Enseñanza Básica', 'media' => 'Enseñanza Media',
        'parvularia' => 'Educación de Párvulos', 'epja' => 'EPJA',
        'especial' => 'Educación Especial', 'pie' => 'PIE',
    ];

    protected $table = 'dotacion_sobredotacion_justificaciones';

    protected $fillable = [
        'establecimiento_id', 'anio', 'docente_rut_normalizado', 'bloque',
        'tipo_horas', 'horas_detectadas', 'justificacion', 'created_by', 'updated_by',
        'nivel_ensenanza', 'areas_ensenanza',
    ];

    protected $casts = [
        'horas_detectadas' => 'decimal:2',
        'areas_ensenanza' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public static function ensenanzaDisponible(): bool
    {
        return Schema::hasTable('dotacion_sobredotacion_justificaciones')
            && Schema::hasColumns('dotacion_sobredotacion_justificaciones', ['nivel_ensenanza', 'areas_ensenanza']);
    }

    public static function areasDocentesActivas(): Collection
    {
        return Schema::hasTable('areas_desempeno')
            ? AreaDesempeno::activos()->docente()->orderBy('nombre')->get(['id', 'nombre']) : collect();
    }

    public function nivelEnsenanzaNombre(): ?string
    {
        return self::NIVELES_ENSENANZA[$this->getAttribute('nivel_ensenanza') ?? ''] ?? null;
    }

    public function areasEnsenanzaIds(): array
    {
        return collect($this->getAttribute('areas_ensenanza') ?? [])->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    public function areasEnsenanzaNombres(): array
    {
        return collect($this->getAttribute('areas_ensenanza') ?? [])->pluck('nombre')
            ->filter(fn ($nombre) => is_string($nombre) && trim($nombre) !== '')->unique()->values()->all();
    }

    public function fundamentoCompleto(): string
    {
        $partes = [(string) $this->justificacion];
        if ($nivel = $this->nivelEnsenanzaNombre()) { $partes[] = 'Nivel de enseñanza: '.$nivel; }
        if ($areas = $this->areasEnsenanzaNombres()) { $partes[] = 'Área de enseñanza: '.implode(', ', $areas); }
        return implode("\n", $partes);
    }

    public static function clave(string $bloque, string $rut, string $tipo): string
    {
        return $bloque.'|'.\App\Support\DotacionEstablecimientoCalculator::normalizeRut($rut).'|'.$tipo;
    }

    public function vigentePara(float $horas): bool
    {
        return abs((float) $this->horas_detectadas - $horas) < 0.01;
    }

    public static function registrosPara(int $establecimientoId, int $anio): Collection
    {
        if (! Schema::hasTable('dotacion_sobredotacion_justificaciones')) {
            return collect();
        }

        return self::query()->where('establecimiento_id', $establecimientoId)->where('anio', $anio)->get();
    }

    /** Una justificación registrada retira al docente de otras funciones de ese año y establecimiento. */
    public static function rutsJustificados(int $establecimientoId, int $anio, ?Collection $registros = null): array
    {
        return ($registros ?? self::registrosPara($establecimientoId, $anio))
            ->where('establecimiento_id', $establecimientoId)->where('anio', $anio)
            ->whereIn('bloque', ['plan_estudio', 'parvularia', 'pie'])
            ->whereIn('tipo_horas', ['titular', 'contrata'])->where('horas_detectadas', '>', 0)
            ->filter(fn (self $registro) => trim((string) $registro->justificacion) !== '')
            ->map(fn (self $registro) => \App\Support\DotacionEstablecimientoCalculator::normalizeRut($registro->docente_rut_normalizado))
            ->filter()->unique()->values()->all();
    }

    public static function horasVacantes(array $sobredotacion, string $bloque, string $rut, string $tipo): float
    {
        if (! in_array($bloque, ['plan_estudio', 'parvularia', 'pie'], true)
            || ! in_array($tipo, ['titular', 'contrata'], true)) {
            return 0.0;
        }

        $rutNormalizado = \App\Support\DotacionEstablecimientoCalculator::normalizeRut($rut);
        if ($rutNormalizado === '') {
            return 0.0;
        }

        $docente = collect(data_get($sobredotacion, 'vacantes_por_bloque.'.$bloque.'.items', []))
            ->first(fn (array $item) => \App\Support\DotacionEstablecimientoCalculator::normalizeRut($item['rut']) === $rutNormalizado);
        $campo = $tipo === 'titular' ? 'horas_sobredotacion_planta' : 'horas_sobredotacion_contrata';

        return round((float) ($docente[$campo] ?? 0), 2);
    }
}
