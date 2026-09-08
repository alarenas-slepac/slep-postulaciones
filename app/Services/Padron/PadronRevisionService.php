<?php

namespace App\Services\Padron;

use App\Models\PadronRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;

class PadronRevisionService
{
    public function __construct(private PadronExcelReader $reader, private PadronConciliador $conciliador, private PadronDependenciasService $dependencias) {}

    public function create(string $path, string $filename, int $userId): PadronRevision
    {
        $this->assertInstalled();
        try {
            $incoming = $this->reader->read($path);
        } catch (ReaderException $exception) {
            throw ValidationException::withMessages(['excel' => 'No se pudo leer el Excel. Verifique que no esté dañado ni protegido con contraseña.']);
        }
        $validPeriods = collect($incoming)->filter(fn ($r) => ($r['datos']['anio'] ?? 0) >= 2000 && ($r['datos']['mes'] ?? 0) >= 1 && ($r['datos']['mes'] ?? 0) <= 12)
            ->map(fn ($r) => $r['datos']['anio'] * 100 + $r['datos']['mes'])->unique();
        $period = $validPeriods->count() === 1 ? (int) $validPeriods->first() : null;
        $base = $this->snapshot($period);
        $report = $this->conciliador->reconcile($incoming, $base['personal'], $base['establecimientos'], $base['asignaciones'], $base['declaraciones']);
        if ($period !== null && $base['periodo_maximo'] > $period) {
            $report['errores'][] = 'La base contiene un período posterior al archivo. Carga histórica: no puede reemplazar el padrón actual.';
            foreach ($report['filas'] as &$row) {
                if ($row['accion'] === 'baja_propuesta') {
                    $row['accion'] = 'ausencia_por_revisar';
                }
            }
            unset($row);
            $report['resumen'] = collect($report['filas'])->countBy('accion')->all();
        }
        $baseHash = $base['hash'];
        unset($incoming, $base);
        return DB::transaction(function () use ($filename, $path, $userId, $period, $baseHash, $report): PadronRevision {
            $revision = PadronRevision::create([
                'created_by' => $userId, 'archivo' => mb_substr(basename($filename), 0, 255),
                'archivo_hash' => hash_file('sha256', $path), 'base_hash' => $baseHash,
                'anio' => $period ? intdiv($period, 100) : null, 'mes' => $period ? $period % 100 : null,
                'resumen' => $report['resumen'], 'errores' => $report['errores'], 'excesos' => $report['excesos'],
            ]);
            foreach (array_chunk($report['filas'], 250) as $chunk) {
                $rows = array_map(function ($row) use ($revision) {
                    $row['padron_revision_id'] = $revision->id;
                    foreach (['datos', 'anterior', 'candidatos', 'asignaciones', 'observaciones'] as $key) {
                        $row[$key] = $row[$key] === null ? null : json_encode($row[$key], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
                    }
                    return $row;
                }, $chunk);
                DB::table('padron_revision_filas')->insert($rows);
            }
            return $revision;
        });
    }

    public function stale(PadronRevision $revision): bool
    {
        $period = $revision->anio && $revision->mes ? $revision->anio * 100 + $revision->mes : null;
        return ! hash_equals($revision->base_hash, $this->snapshot($period)['hash']);
    }

    public function authorize(PadronRevision $revision, string $rut, string $justification, int $userId): void
    {
        if ($revision->aplicada_at) {
            throw ValidationException::withMessages(['revision' => 'La revisión ya fue aplicada.']);
        }
        if (mb_strlen(trim($justification)) < 10 || mb_strlen($justification) > 2000) {
            throw ValidationException::withMessages(['justificacion' => 'Ingrese una justificación de entre 10 y 2.000 caracteres.']);
        }
        if ($this->stale($revision)) {
            throw ValidationException::withMessages(['revision' => 'El padrón o sus dependencias cambiaron. Genere una nueva revisión antes de autorizar.']);
        }
        if ($revision->errores || ! isset($revision->excesos[$rut])) {
            throw ValidationException::withMessages(['revision' => 'La revisión contiene errores o el RUT no tiene exceso de jornada. Corrija el archivo y vuelva a analizar.']);
        }
        DB::transaction(function () use ($revision, $rut, $justification, $userId): void {
            // Serializa autorizaciones de esta carga. No modifica el padrón.
            $revision = PadronRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();
            // Revalidar el estado persistido tras adquirir el bloqueo: la instancia
            // recibida podría ser anterior al cierre o a cambios de dependencias.
            if ($revision->aplicada_at || $this->stale($revision)
                || $revision->errores || ! isset($revision->excesos[$rut])) {
                throw ValidationException::withMessages(['revision' => 'La revisión fue cerrada, contiene errores o la base cambió. Genere un nuevo análisis.']);
            }
            if (DB::table('padron_revision_autorizaciones')->where('padron_revision_id', $revision->id)->where('rut', $rut)->exists()) {
                throw ValidationException::withMessages(['revision' => 'Este RUT ya fue autorizado para esta carga. La autorización original se conserva.']);
            }
            DB::table('padron_revision_autorizaciones')->insert([
                'padron_revision_id' => $revision->id, 'rut' => $rut, 'jornada_total' => $revision->excesos[$rut]['total'],
                'justificacion' => trim($justification), 'autorizado_por' => $userId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function assertInstalled(): void
    {
        if (! Schema::hasTable('padron_revisiones') || ! Schema::hasTable('padron_revision_filas') || ! Schema::hasTable('padron_revision_autorizaciones')) {
            throw ValidationException::withMessages(['excel' => 'La previsualización requiere ejecutar las migraciones del padrón con PHP 8.3.']);
        }
    }

    private function snapshot(?int $period): array
    {
        $maxPeriod = (int) DB::table('reemplazos_personal')->selectRaw('MAX(anio * 100 + mes) as periodo')->value('periodo');
        $aplicaciones = Schema::hasColumn('padron_revisiones', 'aplicada_at')
            ? DB::table('padron_revisiones')->whereNotNull('aplicada_at')->orderBy('id')->get(['id', 'anio', 'mes', 'aplicada_at'])->all() : [];
        foreach ($aplicaciones as $aplicacion) {
            $maxPeriod = max($maxPeriod, (int) $aplicacion->anio * 100 + (int) $aplicacion->mes);
        }
        $personal = [];
        $latestByEst = [];
        $historical = [];
        $historicalPeriods = [];
        $fingerprint = hash_init('sha256');
        // Un período base por establecimiento. No desaparece un RBD porque otro
        // tenga una carga más reciente. Los IDs nunca se reescriben aquí.
        $query = DB::table('reemplazos_personal')->when($period, fn ($q) => $q->whereRaw('anio * 100 + mes <= ?', [$period]))
            ->orderByDesc('anio')->orderByDesc('mes')->orderBy('id');
        foreach ($query->lazy(250) as $row) {
            hash_update($fingerprint, json_encode($row, JSON_THROW_ON_ERROR));
            $key = $row->establecimiento_id ?? 'rbd_'.$row->rbd;
            $p = $row->anio * 100 + $row->mes;
            $latestByEst[$key] ??= $p;
            $record = (array) $row;
            if ($latestByEst[$key] === $p) {
                $personal[] = $record;
            }
            $rut = PadronConciliador::rut($row->rut);
            $historicalPeriods[$rut] ??= $p;
            if ($historicalPeriods[$rut] === $p) {
                $historical[$rut][] = $record;
            }
        }
        // Una reincorporación puede tener IDs históricos aunque ya no figure
        // en el último mes de su establecimiento. Nunca se presume persona nueva.
        $currentRuts = array_fill_keys(array_map(fn ($r) => PadronConciliador::rut($r['rut']), $personal), true);
        foreach ($historical as $rut => $records) {
            if (! isset($currentRuts[$rut])) {
                foreach ($records as $record) {
                    $record['_historico'] = true;
                    $record['vigente'] = false;
                    $personal[] = $record;
                }
            }
        }
        unset($historical, $historicalPeriods, $currentRuts, $records, $record);
        $establishments = DB::table('establecimientos')->orderBy('id')->get(['id', 'rbd'])->keyBy('rbd')->map(fn ($r) => $r->id)->all();
        $cobertura = app(PadronConflictosAsignacionService::class)->snapshot($period ? intdiv($period, 100) : null);
        // El hash usa la dependencia completa; la revisión solo copia el detalle
        // necesario, no observaciones ni otras columnas de la asignación.
        $assignments = $cobertura['asignaciones'];
        $declarations = [];
        // Usar exactamente las filas ya incluidas en la huella, en orden ID descendente.
        foreach (array_reverse($cobertura['declaraciones']) as $r) {
            $rut = PadronConciliador::rut($r['rut']);
            if (! array_key_exists($rut, $declarations)) {
                $declarations[$rut] = $r['horas_contratadas'];
            }
        }
        $dependencias = $this->dependencias->snapshot();
        return ['personal' => $personal, 'establecimientos' => $establishments, 'asignaciones' => $assignments,
            'declaraciones' => $declarations, 'periodo_maximo' => $maxPeriod,
            // Versión 11: propuestas explícitas de redistribución entre financiamientos.
            // Las revisiones previas requieren analizar nuevamente el archivo.
            'hash' => hash('sha256', json_encode(['v11', hash_final($fingerprint), $establishments, $maxPeriod, $dependencias['hash'], $cobertura['hash'], $aplicaciones, app(PadronPeriodoService::class)->huella()], JSON_THROW_ON_ERROR))];
    }
}
