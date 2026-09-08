<?php

// Ejecución explícita fuera de phpunit.xml; no se ejecuta con las pruebas SQLite.
require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__).'/Support/PadronMySqlLab.php';
require dirname(__DIR__).'/Support/PadronMySqlWorker.php';

use App\Models\PadronRevision;
use App\Services\Padron\PadronAplicacionService;
use Illuminate\Support\Facades\DB;
use Tests\Support\PadronMySqlLab as Lab;
use Tests\Support\PadronMySqlProcess as Child;
use Tests\Support\PadronMySqlWorker as Worker;

if (in_array('--worker', $argv, true)) {
    Worker::run(json_decode((string) fgets(STDIN), true, 512, JSON_THROW_ON_ERROR));
    exit;
}
if (! in_array('--run', $argv, true)) {
    echo "Uso: PADRON_MYSQL_USER explícito; php tests/Integration/padron_mysql_concurrency.php --run [--case=baseline] [--isolation=RR|RC]\n";
    exit(1);
}

function check(bool $condition, string $message): void
{
    if (! $condition) { throw new RuntimeException($message); }
}

function lockWait(PDO $admin, int $waiting, int $blocking, float $timeout = 5): bool
{
    $query = $admin->prepare('SELECT COUNT(*) FROM performance_schema.data_lock_waits w
        JOIN performance_schema.threads r ON r.THREAD_ID = w.REQUESTING_THREAD_ID
        JOIN performance_schema.threads b ON b.THREAD_ID = w.BLOCKING_THREAD_ID
        WHERE r.PROCESSLIST_ID = ? AND b.PROCESSLIST_ID = ?');
    $until = microtime(true) + $timeout;
    do {
        $query->execute([$waiting, $blocking]);
        if ($query->fetchColumn() > 0) { return true; }
        usleep(20000);
    } while (microtime(true) < $until);
    return false;
}

function appliedOnce(): void
{
    check(DB::table('padron_personal_cambios')->count() === 4, 'Auditoría duplicada o incompleta.');
    check(DB::table('reemplazos_personal')->count() === 4, 'IDs duplicados o eliminados.');
    check((int) DB::table('reemplazos_personal')->where('id', 101)->value('jornada') === 30, 'No actualizó el ID receptor.');
    check(! DB::table('reemplazos_personal')->where('id', 102)->value('vigente'), 'No aplicó la baja prevista.');
    check(DB::table('reemplazos_personal')->where('id', 101)->value('row_hash') === 'sintetico-101', 'Perdió clave contractual.');
    check(DB::table('padron_periodo_versiones')->count() === 2, 'Versiones mensuales duplicadas o faltantes.');
    foreach (\App\Services\Padron\PadronHistorialService::DOCUMENTOS as $table) {
        $copy = json_decode(DB::table($table)->where('id', 1)->value('padron_personal_snapshot'), true);
        check((int) ($copy['personal']['jornada'] ?? 0) === 44, 'Historia documental no preservada.');
    }
}

try {
    $run = bin2hex(random_bytes(8));
    putenv('PADRON_MYSQL_LAB_RUN='.$run);
    $admin = Lab::admin();
    $server = $admin->query('SELECT VERSION() AS version, @@transaction_isolation AS isolation_default,
        @@default_storage_engine AS engine_default, @@innodb_lock_wait_timeout AS lock_wait_timeout_default')->fetch(PDO::FETCH_ASSOC);
    check(str_starts_with($server['version'], '8.'), 'Este protocolo requiere MySQL 8 y performance_schema.data_lock_waits.');
    $report = ['run' => $run, 'started_at' => gmdate(DATE_ATOM), 'server' => $server,
        'production_equivalence' => 'No verificada: no se consultó producción.',
        'schema' => 'Fixture sintético reducido; migraciones reales de revisión e historia. Dotación SIN FK contractual, documentos CON FK.',
        'application_enabled' => false, 'cases' => []];
    $only = null;
    $isolations = ['RR' => 'REPEATABLE READ', 'RC' => 'READ COMMITTED'];
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--case=')) { $only = substr($arg, 7); }
        if (str_starts_with($arg, '--isolation=')) { $isolations = array_intersect_key($isolations, [substr($arg, 12) => true]); }
    }
    $cases = ['baseline', 'same_review', 'different_reviews', 'rollback_fault', 'disconnect', 'timeout', 'deadlock',
        'personal_update', 'document_update', 'assignment_update', 'declaration_update', 'exclusion_update',
        'personal_insert', 'document_insert', 'assignment_insert', 'declaration_insert', 'exclusion_insert'];
    if ($only !== null) { $cases = array_values(array_intersect($cases, [$only])); }
    check((bool) $cases && (bool) $isolations, 'Filtro de casos o aislamiento vacío.');
    $index = 0;
    foreach ($isolations as $label => $isolation) {
        foreach ($cases as $case) {
            $database = 'padron_lab_'.$run.'_'.str_pad((string) ++$index, 2, '0', STR_PAD_LEFT);
            Lab::assertName($database);
            // CREATE sin IF NOT EXISTS: nunca reutiliza ni vacía una base existente.
            $admin->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $children = [];
            $entry = ['case' => $case, 'isolation' => $isolation, 'database' => $database];
            $start = microtime(true);
            try {
                Lab::boot($database, $isolation);
                Lab::fixture();
                $entry['engines'] = DB::select('SELECT table_name, engine FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY table_name');
                $r = Lab::revision();
                $job = ['database' => $database, 'isolation' => $isolation, 'mode' => 'apply'] + $r;
                $spawn = function (array $data) use (&$children): Child { $child = new Child($data); $children[] = $child; return $child; };
                $before = Lab::state();
                if ($case === 'baseline') {
                    $a = $spawn($job);
                    $entry['a'] = $a->wait('result');
                    check($entry['a']['status'] === 'committed', 'Aplicación básica rechazada.');
                    appliedOnce();
                } elseif (in_array($case, ['same_review', 'different_reviews'], true)) {
                    $second = $case === 'same_review' ? $r : Lab::revision();
                    $a = $spawn($job + ['pause' => 'control_locked']);
                    $aId = $a->wait('started')['connection']; $a->wait('control_locked');
                    $b = $spawn(array_replace($job, $second));
                    $bId = $b->wait('started')['connection'];
                    $entry['lock_wait_verified'] = lockWait($admin, $bId, $aId);
                    check($entry['lock_wait_verified'], 'No se observó concurrencia real sobre el control global.');
                    $a->go(); $entry['a'] = $a->wait('result'); $entry['b'] = $b->wait('result');
                    check($entry['a']['status'] === 'committed', 'Primer aplicador falló.');
                    check($entry['b']['status'] === ($case === 'same_review' ? 'committed' : 'rejected'), 'Segunda aplicación no respetó idempotencia/base.');
                    appliedOnce();
                    check(DB::table('padron_revisiones')->whereNotNull('aplicada_at')->count() === 1, 'Aplicó dos revisiones de una misma base.');
                } elseif (in_array($case, ['rollback_fault', 'disconnect'], true)) {
                    $a = $spawn($job + ($case === 'disconnect' ? ['pause' => 'audit_1'] : ['fault' => true]));
                    if ($case === 'disconnect') { $a->wait('audit_1'); $a->stop(); $entry['a'] = ['status' => 'process_terminated_after_first_audit']; }
                    else { $entry['a'] = $a->wait('result'); check($entry['a']['status'] === 'rejected', 'No se inyectó el fallo.'); }
                    check($before === Lab::state(), 'Rollback dejó modificaciones parciales.');
                    $entry['rollback_exact'] = true;
                    $entry['retry'] = $spawn($job)->wait('result');
                    check($entry['retry']['status'] === 'committed', 'No se pudo reintentar después del rollback.'); appliedOnce();
                } elseif ($case === 'timeout') {
                    $b = $spawn(array_replace($job, ['mode' => 'edit', 'operation' => 'control_lock', 'pause' => 'after_sql']));
                    $bId = $b->wait('started')['connection']; $b->wait('after_sql');
                    $a = $spawn($job + ['timeout' => 1]); $aId = $a->wait('started')['connection'];
                    $entry['lock_wait_verified'] = lockWait($admin, $aId, $bId);
                    $entry['a'] = $a->wait('result');
                    check($entry['a']['driver_code'] === 1205 && $entry['a']['attempts'] === 3, 'No agotó los tres reintentos de timeout.');
                    $b->go(); $b->wait('result');
                    check($before === Lab::state(), 'Timeout dejó escrituras parciales.');
                    $entry['retry'] = $spawn($job)->wait('result'); check($entry['retry']['status'] === 'committed', 'Reintento tras liberar bloqueo falló.'); appliedOnce();
                } elseif ($case === 'deadlock') {
                    $a = $spawn($job + ['pause' => 'control_locked']); $aId = $a->wait('started')['connection']; $a->wait('control_locked');
                    $b = $spawn(array_replace($job, ['mode' => 'deadlock_actor'])); $bId = $b->wait('started')['connection']; $b->wait('actor_locked');
                    $a->go();
                    $entry['first_wait_verified'] = lockWait($admin, $aId, $bId);
                    if (! $entry['first_wait_verified']) {
                        $a->poll(); $b->poll();
                        $entry['a_events'] = $a->events; $entry['b_events'] = $b->events;
                    }
                    check($entry['first_wait_verified'], 'No se estableció la primera arista del deadlock.');
                    $b->go(); $b->wait('cycle_broken');
                    $entry['retry_wait_verified'] = lockWait($admin, $aId, $bId);
                    $b->go(); $entry['b'] = $b->wait('result'); $entry['a'] = $a->wait('result');
                    check($entry['a']['status'] === 'committed' && $entry['a']['attempts'] >= 2, 'No recuperó el deadlock real.'); appliedOnce();
                } elseif (str_ends_with($case, '_update')) {
                    $b = $spawn(array_replace($job, ['mode' => 'edit', 'operation' => $case, 'pause' => 'after_sql']));
                    $bId = $b->wait('started')['connection']; $b->wait('after_sql');
                    $a = $spawn($job + ['trace' => true, 'pause' => 'dependencies_locked']); $aId = $a->wait('started')['connection'];
                    $entry['lock_wait_verified'] = lockWait($admin, $aId, $bId);
                    check($entry['lock_wait_verified'], 'Aplicador no esperó al escritor concurrente.');
                    $b->go(); $entry['b'] = $b->wait('result'); $a->wait('dependencies_locked');
                    $entry['token_invalid_in_fresh_connection'] = ! Lab::writer()->confirmacionVigente(PadronRevision::findOrFail($r['revision']), $r['token']);
                    if ($case !== 'personal_update') {
                        check($entry['token_invalid_in_fresh_connection'], 'El cambio externo no alteró la confirmación de control.');
                    }
                    $a->go(); $entry['a'] = $a->wait('result');
                    check($entry['a']['status'] === 'rejected', 'No rechazó la confirmación obsoleta tras el cambio externo.');
                    check(DB::table('padron_personal_cambios')->count() === 0 && DB::table('padron_periodo_versiones')->count() === 0,
                        'Rechazo concurrente dejó auditoría o versiones parciales.');
                    check(DB::table('padron_revisiones')->whereNotNull('aplicada_at')->count() === 0, 'Cerró una carga rechazada.');
                } else {
                    // Escritor externo sin protocolo del padrón. Determina si un INSERT
                    // posterior al chequeo final espera o genera un fantasma concurrente.
                    $a = $spawn($job + ['pause' => 'audit_1']); $aId = $a->wait('started')['connection']; $a->wait('audit_1');
                    $b = $spawn(array_replace($job, ['mode' => 'edit', 'operation' => $case])); $bId = $b->wait('started')['connection'];
                    $entry['insert_wait_verified'] = lockWait($admin, $bId, $aId, 0.8);
                    if (! $entry['insert_wait_verified']) {
                        $entry['b'] = $b->wait('result');
                        check($entry['b']['status'] === 'committed', 'Inserción externa falló antes de observar el riesgo.');
                    }
                    $a->go(); $entry['a'] = $a->wait('result'); $entry['b'] ??= $b->wait('result');
                    check($entry['a']['status'] === 'committed' && $entry['b']['status'] === 'committed', 'No completó la intercalación de inserciones.');
                    $entry['finding'] = $entry['insert_wait_verified']
                        ? 'El INSERT espera y ocurre después del commit. El escritor externo debe revalidar vigencia/cobertura antes de guardar; no participa en el protocolo del padrón.'
                        : 'INSERT confirmado después de la validación final y antes del commit del padrón: falta excluir/revalidar fantasmas de escritores externos.';
                    if ($case === 'assignment_insert') {
                        $entry['active_link_to_inactive_contract'] = ! (bool) DB::table('reemplazos_personal')->where('id', 102)->value('vigente');
                    }
                    if ($case === 'document_insert') {
                        $entry['external_document_without_snapshot'] = DB::table('solicitudes_reemplazo')->where('id', 3)->value('padron_personal_snapshot') === null;
                    }
                    $entry['status'] = 'requires_writer_protocol';
                }
                check(! app(PadronAplicacionService::class)->disponible(), 'El servicio real quedó habilitado.');
                $entry['status'] ??= 'passed';
            } catch (Throwable $e) {
                $entry['status'] = 'failed';
                // Solo mensajes propios/fixture; las excepciones SQL no se imprimen con bindings.
                $entry['error'] = $e instanceof \Illuminate\Database\QueryException || $e instanceof PDOException ? get_class($e).': database_error' : $e->getMessage();
            } finally {
                foreach ($children as $child) { $child->stop(); }
            }
            $entry['seconds'] = round(microtime(true) - $start, 3);
            $report['cases'][] = $entry;
            echo $label.' '.$case.' '.$entry['status'].(isset($entry['error']) ? ' '.$entry['error'] : '')."\n";
            flush();
        }
    }
    $report['finished_at'] = gmdate(DATE_ATOM);
    $report['summary'] = array_count_values(array_column($report['cases'], 'status'));
    $directory = dirname(__DIR__, 2).'/storage/app/testing';
    if (! is_dir($directory)) { mkdir($directory, 0700, true); }
    $path = $directory.'/padron_mysql_'.$run.'.json';
    file_put_contents($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    echo 'REPORT '.$path."\n".json_encode($report['summary'])."\n";
    echo "Las bases sintéticas se conservan para inspección. No se ejecuta DROP ni se modifica producción.\n";
    exit(isset($report['summary']['failed']) ? 1 : (isset($report['summary']['requires_writer_protocol']) ? 2 : 0));
} catch (Throwable $e) {
    fwrite(STDERR, 'Laboratorio detenido: '.($e instanceof PDOException ? 'no se pudo verificar el servidor local' : $e->getMessage())."\n");
    exit(1);
}
