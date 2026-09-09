<?php

namespace Tests\Support;

use App\Models\PadronRevision;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

final class PadronMySqlWorker
{
    public static function emit(string $event, array $data = []): void
    {
        echo json_encode(['event' => $event] + $data, JSON_THROW_ON_ERROR)."\n";
        flush();
    }

    public static function barrier(string $stage): void
    {
        self::emit($stage);
        if (trim((string) fgets(STDIN)) !== 'go') { throw new \RuntimeException('Barrera cancelada.'); }
        self::emit('released', ['stage' => $stage]);
    }

    public static function run(array $job): void
    {
        $attempts = 0;
        $trace = [];
        try {
            PadronMySqlLab::boot($job['database'], $job['isolation']);
            PadronMySqlLab::guard();
            DB::statement('SET SESSION innodb_lock_wait_timeout = '.(int) ($job['timeout'] ?? 8));
            self::emit('started', ['connection' => (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id,
                'isolation' => PadronMySqlLab::server(DB::connection()->getPdo())['isolation_default']]);
            DB::connection()->getEventDispatcher()->listen(TransactionBeginning::class, function () use (&$attempts): void { $attempts++; });
            if ($job['mode'] === 'apply') {
                $fired = [];
                $audits = 0;
                DB::listen(function (QueryExecuted $event) use ($job, &$fired, &$audits, &$trace, &$attempts): void {
                    $sql = strtolower($event->sql);
                    if (($job['trace'] ?? false) && $attempts > 0 && count($trace) < 60) {
                        // Solo SQL parametrizado del fixture; nunca bindings.
                        $trace[] = ['attempt' => $attempts, 'sql' => $event->sql];
                    }
                    $stage = null;
                    if (str_contains($sql, 'for update') && str_contains($sql, '`padron_aplicacion_control`')) { $stage = 'control_locked'; }
                    if (str_contains($sql, 'for update') && str_contains($sql, '`reemplazos_personal_bloqueos`')) { $stage = 'dependencies_locked'; }
                    if (str_starts_with($sql, 'insert into `padron_personal_cambios`')) {
                        $audits++;
                        $stage = 'audit_'.$audits;
                        if (($job['fault'] ?? false) && $audits === 2) { throw new \RuntimeException('synthetic_mid_write_fault'); }
                    }
                    if ($stage === ($job['pause'] ?? '') && ! isset($fired[$stage])) {
                        $fired[$stage] = true;
                        self::barrier($stage);
                    }
                });
                PadronMySqlLab::writer()->aplicar(PadronRevision::findOrFail($job['revision']), 7, $job['token']);
            } elseif ($job['mode'] === 'coordinated') {
                app(\App\Services\Padron\PadronEscrituraService::class)->ejecutar(function () use ($job): void {
                    if (($job['pause'] ?? '') === 'coordinator_locked') { self::barrier('coordinator_locked'); }
                    $personal = DB::table('reemplazos_personal')->find(101);
                    self::emit('observed', ['jornada' => (int) $personal->jornada, 'mes' => (int) $personal->mes]);
                    // Validaciones representativas dentro del callback, no una
                    // certificación de todos los controladores del esquema real.
                    if ($job['operation'] === 'personal_insert' && (int) $personal->mes !== 8) {
                        throw ValidationException::withMessages(['padron' => 'El período cambió. Revise los datos actuales antes de guardar.']);
                    }
                    if ($job['operation'] === 'assignment_insert'
                        && ! app(\App\Services\Padron\PadronVigenciaService::class)->consultaActual()->whereKey(102)->exists()) {
                        throw ValidationException::withMessages(['padron' => 'El contrato ya no pertenece al padrón vigente.']);
                    }
                    if ($job['operation'] === 'exclusion_insert') {
                        $base = (float) DB::table('declaracion_sostenedores')->where('id', 1)->value('horas_contratadas');
                        $assigned = (float) DB::table('dotacion_docente_asignaciones')->where('estado', 'activa')->sum('horas_contrato');
                        if (25 > $base - $assigned) {
                            throw ValidationException::withMessages(['padron' => 'Las horas exceden el saldo sin asignación actual.']);
                        }
                    }
                    if ($job['operation'] === 'document_insert') {
                        \App\Models\SolicitudReemplazo::forceCreate(['id' => 3, 'reemplazo_personal_id' => 101]);
                    } else {
                        self::mutation($job['operation']);
                    }
                });
            } else {
                DB::beginTransaction();
                if ($job['mode'] === 'deadlock_actor') {
                    DB::table('padron_lab_ballast')->increment('n');
                    DB::table('reemplazos_personal')->where('id', 101)->lockForUpdate()->first();
                    self::barrier('actor_locked');
                    DB::table('padron_aplicacion_control')->where('id', 1)->lockForUpdate()->first();
                    self::barrier('cycle_broken');
                } else {
                    if (($job['pause'] ?? '') === 'before_sql') { self::barrier('before_sql'); }
                    self::mutation($job['operation']);
                    if (($job['pause'] ?? '') === 'after_sql') { self::barrier('after_sql'); }
                }
                DB::commit();
            }
            self::emit('result', ['status' => 'committed', 'attempts' => $attempts, 'trace' => $trace]);
        } catch (\Throwable $e) {
            while (isset($GLOBALS['padron_lab_app']) && DB::transactionLevel() > 0) { DB::rollBack(); }
            $previous = $e instanceof \Illuminate\Database\QueryException ? $e->getPrevious() : $e;
            self::emit('result', ['status' => 'rejected', 'type' => get_class($e), 'attempts' => $attempts,
                'sqlstate' => $previous instanceof \PDOException ? ($previous->errorInfo[0] ?? null) : null,
                'driver_code' => $previous instanceof \PDOException ? ($previous->errorInfo[1] ?? null) : null,
                // No emitir SQL, bindings, credenciales ni trazas del entorno.
                'reason' => $e instanceof ValidationException || str_starts_with($e->getFile(), __DIR__)
                    ? $e->getMessage() : 'worker_error',
                'origin' => basename($e->getFile()).':'.$e->getLine(), 'trace' => $trace]);
        }
    }

    private static function mutation(string $operation): void
    {
        match ($operation) {
            'control_lock' => DB::table('padron_aplicacion_control')->where('id', 1)->lockForUpdate()->first(),
            'personal_update' => DB::table('reemplazos_personal')->where('id', 101)->update(['jornada' => 43]),
            'document_update' => DB::table('solicitudes_reemplazo')->where('id', 1)->update(['estado' => 'aprobado']),
            'assignment_update' => DB::table('dotacion_docente_asignaciones')->where('id', 501)->update(['horas_contrato' => 21]),
            'declaration_update' => DB::table('declaracion_sostenedores')->where('id', 1)->update(['horas_contratadas' => 31]),
            'exclusion_update' => DB::table('dotacion_docente_exclusiones')->where('id', 1)->update(['horas' => 1]),
            'document_insert' => DB::table('solicitudes_reemplazo')->insert(['id' => 3, 'reemplazo_personal_id' => 101]),
            'assignment_insert' => DB::table('dotacion_docente_asignaciones')->insert(['id' => 502, 'anio' => 2026,
                'establecimiento_id' => 1, 'reemplazos_personal_id' => 102, 'docente_rut' => '222222222', 'estado' => 'activa', 'horas_contrato' => 2]),
            'declaration_insert' => DB::table('declaracion_sostenedores')->insert(['id' => 2, 'rut' => '111111111',
                'rbd' => 99999, 'estamento' => 'DOCENTE', 'horas_contratadas' => 10]),
            'exclusion_insert' => DB::table('dotacion_docente_exclusiones')->insert(['id' => 2, 'anio' => 2026,
                'establecimiento_id' => 1, 'docente_rut' => '111111111', 'horas' => 25]),
            'personal_insert' => DB::table('reemplazos_personal')->insert(['id' => 105, 'establecimiento_id' => 1,
                'row_hash' => 'sintetico-nuevo'] + PadronMySqlLab::data(['rut' => '555555555', 'mes' => 8])),
            default => throw new \RuntimeException('Operación no permitida.'),
        };
    }
}

/** Proceso PHP real, con barreras stdin/stdout y límite de duración. */
final class PadronMySqlProcess
{
    public Process $process;
    private InputStream $input;
    private string $buffer = '';
    public array $events = [];

    public function __construct(array $job)
    {
        $this->input = new InputStream;
        $environment = [];
        foreach (['PADRON_MYSQL_LAB_RUN', 'PADRON_MYSQL_HOST', 'PADRON_MYSQL_PORT', 'PADRON_MYSQL_USER', 'PADRON_MYSQL_PASSWORD'] as $key) {
            $environment[$key] = getenv($key);
        }
        $this->process = new Process([PHP_BINARY, dirname(__DIR__).'/Integration/padron_mysql_concurrency.php', '--worker'], dirname(__DIR__, 2), $environment);
        $this->process->setInput($this->input);
        $this->process->setTimeout(35);
        $this->process->start();
        $this->input->write(json_encode($job, JSON_THROW_ON_ERROR)."\n");
    }

    public function poll(): void
    {
        $this->process->checkTimeout();
        $this->buffer .= $this->process->getIncrementalOutput();
        while (($pos = strpos($this->buffer, "\n")) !== false) {
            $line = substr($this->buffer, 0, $pos);
            $this->buffer = substr($this->buffer, $pos + 1);
            $data = json_decode($line, true);
            if (is_array($data) && isset($data['event'])) { $this->events[$data['event']] = $data; }
        }
    }

    public function wait(string $event, float $seconds = 15): array
    {
        $until = microtime(true) + $seconds;
        do {
            $this->poll();
            if (isset($this->events[$event])) { return $this->events[$event]; }
            if (! $this->process->isRunning() || isset($this->events['result'])) {
                throw new \RuntimeException('Worker terminó antes de '.$event.': '.json_encode($this->events['result'] ?? ['exit' => $this->process->getExitCode()]));
            }
            usleep(20000);
        } while (microtime(true) < $until);
        throw new \RuntimeException('Timeout de barrera '.$event);
    }

    public function go(): void
    {
        unset($this->events['released']);
        $this->input->write("go\n");
        // InputStream se vacía cuando Symfony bombea las tuberías. El padre
        // puede consultar MySQL enseguida, sin volver a esperar un evento PHP.
        $this->wait('released');
    }
    public function stop(): void { if ($this->process->isRunning()) { $this->process->stop(0.2); } }
}
