<?php

namespace App\Services\Padron;

use App\Models\PadronRevision;
use App\Models\ReemplazoPersonal;
use App\Models\ReemplazoPersonalHistorico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Versiones explícitas de consulta, sin un scope global sobre el personal actual. */
class PadronPeriodoService
{
    private const CAMPOS = [
        'establecimiento_id', 'rbd', 'rut', 'nombre', 'fecha_nacimiento', 'fecha_ingreso',
        'fecha_antiguedad', 'fecha_termino', 'tipocontrato', 'financiamiento', 'estatuto',
        'escalafon', 'anio', 'mes', 'jornada', 'jornada_basica', 'jornada_media', 'bienios',
        'tramo', 'row_hash', 'source_filename', 'created_by', 'created_at', 'updated_at', 'vigente',
    ];

    public function instalado(): bool
    {
        return Schema::hasTable('padron_periodo_versiones') && Schema::hasTable('padron_periodo_personal');
    }

    /** Dentro del escritor: base completa anterior y nueva copia del período abierto. */
    public function antesDeAplicar(PadronRevision $revision, int $usuario): void
    {
        $this->assertEscritura();
        $periodos = DB::table('reemplazos_personal')->select('anio', 'mes')->distinct()->get();
        $maximo = $this->periodoMaximo();
        foreach ($periodos as $p) {
            if ((int) $p->anio < 2000 || (int) $p->mes < 1 || (int) $p->mes > 12) {
                throw ValidationException::withMessages(['revision' => 'Existen contratos sin período válido. No se puede crear una base histórica completa.']);
            }
            $periodo = (int) $p->anio * 100 + (int) $p->mes;
            // No volver a capturar meses cerrados desde filas actuales ya mutadas.
            if (! $this->ultimaVersion($periodo) || $periodo >= $maximo) {
                $this->capturar($revision, $usuario, $periodo, 'previa');
            }
        }
    }

    public function despuesDeAplicar(PadronRevision $revision, int $usuario): void
    {
        $this->assertEscritura();
        $this->capturar($revision, $usuario, (int) $revision->anio * 100 + (int) $revision->mes, 'aplicada');
    }

    private function assertEscritura(): void
    {
        if (! $this->instalado() || DB::transactionLevel() === 0) {
            throw ValidationException::withMessages(['revision' => 'El historial por período requiere su migración y la transacción de aplicación.']);
        }
        DB::table('padron_aplicacion_control')->where('id', 1)->lockForUpdate()->firstOrFail();
    }

    private function capturar(PadronRevision $revision, int $usuario, int $periodo, string $origen): void
    {
        $versionId = DB::table('padron_periodo_versiones')->insertGetId([
            'periodo' => $periodo, 'padron_revision_id' => $revision->id, 'origen' => $origen,
            'usuario_id' => $usuario, 'created_at' => now(),
        ]);
        $huella = hash_init('sha256');
        $registros = 0;
        DB::table('reemplazos_personal')->where('anio', intdiv($periodo, 100))->where('mes', $periodo % 100)
            ->chunkById(100, function ($rows) use ($versionId, $huella, &$registros): void {
                $copias = [];
                foreach ($rows as $row) {
                    $datos = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    hash_update($huella, $datos."\n");
                    $campos = array_replace(array_fill_keys(self::CAMPOS, null), array_intersect_key((array) $row, array_flip(self::CAMPOS)));
                    $campos['vigente'] = $row->vigente ?? true;
                    $copias[] = $campos + ['version_id' => $versionId, 'personal_id' => $row->id, 'datos' => $datos];
                    $registros++;
                }
                DB::table('padron_periodo_personal')->insert($copias);
            });
        DB::table('padron_periodo_versiones')->where('id', $versionId)->update([
            'registros' => $registros, 'huella' => hash_final($huella), 'completada_at' => now(),
        ]);
    }

    public function periodoMaximo(): int
    {
        $maximo = (int) DB::table('reemplazos_personal')->selectRaw('MAX(anio * 100 + mes) as periodo')->value('periodo');
        if ($this->instalado()) {
            $maximo = max($maximo, (int) DB::table('padron_periodo_versiones')->whereNotNull('completada_at')->max('periodo'));
        }
        if (Schema::hasColumn('padron_revisiones', 'aplicada_at')) {
            $maximo = max($maximo, (int) DB::table('padron_revisiones')->whereNotNull('aplicada_at')
                ->selectRaw('MAX(anio * 100 + mes) as periodo')->value('periodo'));
        }
        return $maximo;
    }

    private function ultimaVersion(int $periodo): ?object
    {
        return $this->instalado() ? DB::table('padron_periodo_versiones')->where('periodo', $periodo)
            ->whereNotNull('completada_at')->orderByDesc('id')->first() : null;
    }

    public function esHistorico(?int $anio, ?int $mes): bool
    {
        $periodo = (int) $anio * 100 + (int) $mes;
        return $this->instalado() && $periodo < $this->periodoMaximo() && $this->ultimaVersion($periodo) !== null;
    }

    public function consultaMensual(?int $anio, ?int $mes): Builder
    {
        $periodo = (int) $anio * 100 + (int) $mes;
        if ($this->esHistorico($anio, $mes)) {
            return $this->consultaVersion((int) $this->ultimaVersion($periodo)->id);
        }
        return ReemplazoPersonal::query()->where('reemplazos_personal.anio', $anio)->where('reemplazos_personal.mes', $mes);
    }

    /** SQL paginable: proyecta el ID original y no el de la fila de historial. */
    public function consultaVersion(int $versionId): Builder
    {
        $version = DB::table('padron_periodo_versiones')->where('id', $versionId)->whereNotNull('completada_at')->firstOrFail();
        $base = DB::table('padron_periodo_personal')->where('version_id', $version->id)
            ->select(['personal_id as id', ...self::CAMPOS]);
        return ReemplazoPersonalHistorico::query()->fromSub($base, 'reemplazos_personal');
    }

    public function periodos(): Collection
    {
        $periodos = DB::table('reemplazos_personal')->select('anio', 'mes')->distinct()->get();
        if ($this->instalado()) {
            foreach (DB::table('padron_periodo_versiones')->whereNotNull('completada_at')->distinct()->pluck('periodo') as $periodo) {
                $periodos->push((object) ['anio' => intdiv($periodo, 100), 'mes' => $periodo % 100]);
            }
        }
        return $periodos->unique(fn ($p) => $p->anio * 100 + $p->mes)
            ->sortByDesc(fn ($p) => $p->anio * 100 + $p->mes)->values();
    }

    /** Último período por establecimiento/año, respetando el piso de cargas completas. */
    public function consultaAnual(int $establecimientoId, int $anio): Builder
    {
        if (! $this->instalado()) {
            return ReemplazoPersonal::query()->padronVigente($anio)->where('establecimiento_id', $establecimientoId);
        }
        $periodo = (int) DB::table('reemplazos_personal')->where('establecimiento_id', $establecimientoId)->where('anio', $anio)
            ->selectRaw('MAX(anio * 100 + mes) as periodo')->value('periodo');
        $versiones = DB::table('padron_periodo_versiones')->whereBetween('periodo', [$anio * 100 + 1, $anio * 100 + 12])
            ->whereNotNull('completada_at');
        // Una carga completa es autoritativa incluso si dejó al RBD sin filas.
        $piso = (int) (clone $versiones)->where('origen', 'aplicada')->max('periodo');
        $ultimas = (clone $versiones)->selectRaw('MAX(id)')->groupBy('periodo');
        $historico = DB::table('padron_periodo_versiones as v')
            ->join('padron_periodo_personal as p', 'p.version_id', '=', 'v.id')
            ->whereIn('v.id', $ultimas)->where('p.establecimiento_id', $establecimientoId)->max('v.periodo');
        $periodo = max($periodo, (int) $historico);
        if (Schema::hasColumn('padron_revisiones', 'aplicada_at')) {
            $piso = max($piso, (int) DB::table('padron_revisiones')->where('anio', $anio)->whereNotNull('aplicada_at')
                ->selectRaw('MAX(anio * 100 + mes) as periodo')->value('periodo'));
        }
        if ($periodo === 0 || $periodo < $piso) {
            return ReemplazoPersonal::query()->whereRaw('1 = 0');
        }
        $query = $this->consultaMensual($anio, $periodo % 100)->where('establecimiento_id', $establecimientoId);
        if (Schema::hasColumn('reemplazos_personal', 'vigente')) {
            $query->where('vigente', true);
        }
        return $query;
    }

    /** La huella de revisión incluye el catálogo de versiones, sin cargar sus filas. */
    public function huella(): string
    {
        if (! $this->instalado()) {
            return 'no-instalado';
        }
        $hash = hash_init('sha256');
        foreach (DB::table('padron_periodo_versiones')->lazyById(100) as $version) {
            hash_update($hash, json_encode($version, JSON_THROW_ON_ERROR)."\n");
        }
        return hash_final($hash);
    }
}
