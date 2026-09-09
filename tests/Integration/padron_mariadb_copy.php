<?php

/** CLI local opt-in. Copia privada: nunca imprime registros, SQL ni excepciones con bindings. */
require dirname(__DIR__, 2).'/vendor/autoload.php';
require dirname(__DIR__).'/Support/PadronMySqlLab.php';
require dirname(__DIR__).'/Support/PadronRehearsal.php';

use App\Models\PadronRevision;
use App\Models\ReemplazoPersonal;
use App\Services\Padron\PadronAplicacionService;
use App\Services\Padron\PadronBloqueoService;
use App\Services\Padron\PadronRevisionService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\PadronMySqlLab as Lab;

$options = getopt('', ['root:', 'action:', 'source:', 'excel:', 'revision:', 'user:', 'synthetic-case:']);
$start = microtime(true);
$report = ['application_enabled' => false];
$root = null;
$reserve = str_repeat('x', 262144);
register_shutdown_function(static function () use (&$reserve, &$root, &$report, $start): void {
    $reserve = null;
    $error = error_get_last();
    if ($root && $error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $report['status'] = 'fatal';
        $report['error'] = str_contains($error['message'], 'memory') ? 'memory_limit_exceeded' : 'fatal_error';
        $report['origin'] = basename($error['file']).':'.$error['line'];
        $report['seconds'] = round(microtime(true) - $start, 3);
        file_put_contents($root.'/last-fatal.json', json_encode($report, JSON_PRETTY_PRINT));
        echo "Prueba interrumpida; diagnóstico sin datos personales en last-fatal.json.\n";
    }
});
// PHP tampoco debe imprimir errores que pudieran contener datos del respaldo.
ini_set('display_errors', '0');
ini_set('log_errors', '0');

try {
    $candidateRoot = realpath($options['root'] ?? '');
    $allowed = realpath(getenv('LOCALAPPDATA').'/SlepPadronLab');
    if (PHP_SAPI !== 'cli' || ! $candidateRoot || ! $allowed || ! str_starts_with($candidateRoot, $allowed.DIRECTORY_SEPARATOR)) {
        throw new RuntimeException('Use un directorio privado bajo LOCALAPPDATA/SlepPadronLab.');
    }
    $root = $candidateRoot;
    $action = $options['action'] ?? '';
    if (! in_array($action, ['init', 'import', 'inspect', 'preview', 'plan', 'reject', 'blocks', 'transfer', 'history', 'matrix', 'verify-fatal', 'rehearse', 'rehearse-synthetic'], true)) {
        throw new RuntimeException('Acción no permitida.');
    }
    $configPath = $root.'/private-runtime.json';
    if ($action === 'init') {
        if (file_exists($configPath)) { throw new RuntimeException('El laboratorio ya está inicializado.'); }
        $pdo = new PDO('mysql:host=127.0.0.1;port=33118;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        if ($pdo->query('SELECT VERSION()')->fetchColumn() !== '10.11.18-MariaDB') { throw new RuntimeException('Versión inesperada.'); }
        $actualData = realpath($pdo->query('SELECT @@datadir')->fetchColumn());
        if ($actualData !== realpath($root.'/data')) { throw new RuntimeException('El servidor no pertenece al directorio aislado.'); }
        $config = ['run' => bin2hex(random_bytes(8)), 'port' => 33118, 'password' => bin2hex(random_bytes(32))];
        // Guardar antes del cambio para poder recuperar la credencial si se interrumpe.
        file_put_contents($configPath, json_encode($config, JSON_THROW_ON_ERROR));
        $pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY ".$pdo->quote($config['password']));
        echo "Instancia privada inicializada; credencial no mostrada.\n";
        exit(0);
    }
    $config = json_decode(file_get_contents($configPath), true, 512, JSON_THROW_ON_ERROR);
    putenv('PADRON_MYSQL_USER=root');
    putenv('PADRON_MYSQL_PASSWORD='.$config['password']);
    putenv('PADRON_MYSQL_PORT='.$config['port']);
    putenv('PADRON_MYSQL_LAB_RUN='.$config['run']);
    $pdo = Lab::admin();
    if (realpath($pdo->query('SELECT @@datadir')->fetchColumn()) !== realpath($root.'/data')
        || $pdo->query('SELECT VERSION()')->fetchColumn() !== '10.11.18-MariaDB'
        || $pdo->query('SELECT @@event_scheduler')->fetchColumn() !== 'DISABLED') {
        throw new RuntimeException('Instancia o aislamiento no verificados.');
    }
    $database = 'padron_lab_'.$config['run'].'_90';
    Lab::assertName($database);
    $report += ['action' => $action, 'server' => Lab::server($pdo), 'memory_limit' => ini_get('memory_limit')];
    if ($action === 'matrix') {
        $environment = [];
        foreach (['PADRON_MYSQL_USER', 'PADRON_MYSQL_PASSWORD', 'PADRON_MYSQL_PORT'] as $key) {
            $environment[$key] = getenv($key);
        }
        $process = new Process([PHP_BINARY, __DIR__.'/padron_mysql_concurrency.php', '--run'], dirname(__DIR__, 2), $environment);
        $process->setTimeout(900);
        $code = $process->run(static function ($type, $out): void { echo $out; });
        exit($code);
    }
    if ($action === 'import') {
        $source = realpath($options['source'] ?? '');
        if (! $source || strtolower(pathinfo($source, PATHINFO_EXTENSION)) !== 'sql') { throw new RuntimeException('Respaldo SQL requerido.'); }
        // Cuenta efímera con permisos SOLO sobre una base nueva; sin FILE, EVENT,
        // TRIGGER, rutinas, cuentas, privilegios globales ni acceso a otras bases.
        $pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $user = 'import_'.$config['run'];
        $pass = bin2hex(random_bytes(32));
        $account = $pdo->quote($user)."@'localhost'";
        $pdo->exec('CREATE USER '.$account.' IDENTIFIED BY '.$pdo->quote($pass));
        $pdo->exec('GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, REFERENCES, LOCK TABLES ON `'.$database.'`.* TO '.$account);
        try {
            $stream = fopen($source, 'rb');
            $process = new Process([$root.'/mariadb-10.11.18-winx64/bin/mariadb.exe', '--no-defaults',
                '--host=127.0.0.1', '--port='.$config['port'], '--user='.$user, '--database='.$database,
                '--binary-mode', '--local-infile=0', '--default-character-set=utf8mb4', '--max-allowed-packet=128M'],
                $root, ['MYSQL_PWD' => $pass], $stream, 1200);
            $process->run();
            fclose($stream);
            if (! $process->isSuccessful()) {
                preg_match('/ERROR (\d+) \(([A-Z0-9]+)\) at line (\d+)/', $process->getErrorOutput(), $match);
                $report['import_error'] = ['driver_code' => $match[1] ?? null, 'sqlstate' => $match[2] ?? null, 'line' => $match[3] ?? null];
                throw new RuntimeException('Importación detenida. Base parcial conservada, sin reintentar ni vaciar.');
            }
        } finally {
            // Solo la cuenta efímera creada arriba. No elimina ninguna base.
            $pdo->exec('DROP USER '.$account);
        }
        $pdo->exec('CREATE TABLE `'.$database.'`.padron_lab_guard (id BIGINT PRIMARY KEY, run_id VARCHAR(32) NOT NULL) ENGINE=InnoDB');
        $q = $pdo->prepare('INSERT INTO `'.$database.'`.padron_lab_guard VALUES (1, ?)');
        $q->execute([$config['run']]);
        $report['source_sha256'] = hash_file('sha256', $source);
        $report['source_bytes'] = filesize($source);
        $report['database'] = $database;
    } else {
        if ($action === 'rehearse-synthetic') {
            $syntheticCase = $options['synthetic-case'] ?? 'success';
            if (! in_array($syntheticCase, ['success', 'unresolved', 'stale', 'missing-user'], true)) {
                throw new RuntimeException('Caso sintético no permitido.');
            }
            // Fixture separado: no usa ni altera funcionarios de la copia real.
            $syntheticRun = bin2hex(random_bytes(8));
            putenv('PADRON_MYSQL_LAB_RUN='.$syntheticRun);
            $database = 'padron_lab_'.$syntheticRun.'_90';
            Lab::assertName($database);
            $pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            Lab::boot($database, 'REPEATABLE READ');
            Lab::fixture();
            \Illuminate\Support\Facades\Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table) { $table->id(); });
            DB::table('users')->insert(['id' => 7]);
            \Illuminate\Support\Facades\Schema::table('reemplazos_personal_bloqueos', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->string('rut')->nullable(); $table->boolean('activo')->default(true);
            });
            DB::table('reemplazos_personal_bloqueos')->where('id', 1)->update(['rut' => '222222222']);
            $options['revision'] = Lab::revision()['revision'];
            $options['user'] = 7;
            $report['synthetic_only'] = true;
            $report['synthetic_case'] = $syntheticCase;
            if ($syntheticCase === 'unresolved') {
                DB::table('padron_revision_filas')->where('padron_revision_id', $options['revision'])->where('fila_excel', 2)
                    ->update(['accion' => 'revision_manual', 'personal_id' => null]);
            } elseif ($syntheticCase === 'stale') {
                DB::table('reemplazos_personal')->where('id', 101)->update(['jornada' => 43]);
            } elseif ($syntheticCase === 'missing-user') {
                $options['user'] = 0;
            }
        }
        Lab::boot($database, 'REPEATABLE READ');
        Lab::guard();
        // Sin servidor web, workers ni scheduler. Interceptar además servicios externos.
        \Illuminate\Support\Facades\Http::preventStrayRequests();
        \Illuminate\Support\Facades\Mail::fake();
        \Illuminate\Support\Facades\Notification::fake();
        \Illuminate\Support\Facades\Queue::fake();
        \Illuminate\Support\Facades\Bus::fake();
        $report['external_services_disabled'] = true;
        if (in_array($action, ['rehearse', 'rehearse-synthetic'], true)) {
            $report += \Tests\Support\PadronRehearsal::run($root, (int) ($options['revision'] ?? 0), (int) ($options['user'] ?? 0));
        } elseif ($action === 'inspect') {
            Artisan::call('padron:verificar-entorno', ['--json' => true]);
            $report['preflight'] = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
            $report['table_count'] = (int) DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = DATABASE()')->n;
            foreach (['reemplazos_personal', 'dotacion_docente_asignaciones', 'reemplazos_personal_bloqueos', 'padron_revisiones', 'solicitudes_reemplazo', 'cometidos_funcionarios', 'incumplimientos_laborales'] as $table) {
                $report['counts'][$table] = DB::table($table)->count();
            }
            $report['periods'] = DB::table('reemplazos_personal')->selectRaw('anio, mes, COUNT(*) AS registros')->groupBy('anio', 'mes')->orderBy('anio')->orderBy('mes')->get()->all();
        } elseif ($action === 'preview') {
            $excel = realpath($options['excel'] ?? '');
            if (! $excel || strtolower(pathinfo($excel, PATHINFO_EXTENSION)) !== 'xlsx') { throw new RuntimeException('Excel de prueba requerido.'); }
            $before = Lab::state();
            $user = (int) DB::table('users')->min('id');
            $revision = app(PadronRevisionService::class)->create($excel, 'prueba-local.xlsx', $user);
            $report['revision'] = $revision->id;
            $report['period'] = [$revision->anio, $revision->mes];
            $report['actions'] = $revision->resumen;
            $report['file_error_count'] = count($revision->errores ?? []);
            $report['excess_people'] = count($revision->excesos ?? []);
            $after = Lab::state();
            unset($before['padron_revisiones'], $after['padron_revisiones']);
            if ($before !== $after) { throw new RuntimeException('La previsualización alteró datos operativos.'); }
            $report['operational_state_unchanged'] = true;
        } elseif (in_array($action, ['plan', 'reject'], true)) {
            $revision = PadronRevision::findOrFail((int) ($options['revision'] ?? 0));
            $before = Lab::state();
            $report['baseline_hashes'] = $before;
            $plan = Lab::writer()->plan($revision);
            $report['revision'] = $revision->id;
            $report['plan_errors'] = count($plan['errores']);
            $report['destinations'] = count($plan['destinos']);
            $report['deactivations'] = count($plan['bajas']);
            $report['conflicts'] = array_intersect_key($plan['conflictos'], array_flip(['grupos_revisados', 'grupos_bloqueantes', 'grupos_preexistentes', 'asignaciones_revisadas', 'bloqueantes', 'avisos']));
            $report['reason_counts'] = [];
            foreach ($plan['conflictos']['items'] as $item) {
                foreach (array_keys($item['motivos']) as $reason) { $report['reason_counts'][$reason] = ($report['reason_counts'][$reason] ?? 0) + 1; }
            }
            if ($action === 'reject') {
                if (! $plan['errores']) { throw new RuntimeException('La prueba de rechazo requiere bloqueos; no se autoriza una aplicación real.'); }
                $token = $plan['confirmacion_hash'];
                unset($plan);
                try {
                    Lab::writer()->aplicar($revision, (int) DB::table('users')->min('id'), $token);
                    throw new RuntimeException('Aceptó inesperadamente una revisión bloqueada.');
                } catch (\Illuminate\Validation\ValidationException) {
                    $report['blocked_application_rejected'] = true;
                }
            }
            if ($before !== Lab::state()) { throw new RuntimeException('La validación/rechazo dejó modificaciones.'); }
            $report['operational_state_unchanged'] = true;
        } elseif ($action === 'verify-fatal') {
            $fatal = json_decode(file_get_contents($root.'/last-fatal.json'), true, 512, JSON_THROW_ON_ERROR);
            if (empty($fatal['baseline_hashes']) || $fatal['baseline_hashes'] !== Lab::state()) {
                throw new RuntimeException('No se acredita igualdad después del fallo fatal.');
            }
            $report['operational_state_unchanged'] = true;
        } elseif (in_array($action, ['transfer', 'history'], true)) {
            $before = Lab::state();
            $report['baseline_hashes'] = $before;
            DB::beginTransaction();
            try {
                DB::table('padron_aplicacion_control')->where('id', 1)->lockForUpdate()->firstOrFail();
                if ($action === 'transfer') {
                    $service = app(PadronBloqueoService::class);
                    $person = $service->filtrarBloqueados(ReemplazoPersonal::query())->orderBy('id')->firstOrFail();
                    $target = DB::table('establecimientos')->where('id', '<>', $person->establecimiento_id)->orderBy('id')->firstOrFail();
                    $service->cargar(collect([$person]));
                    $blockId = $person->getRelation('bloqueoFuncionario')->id;
                    $block = DB::table('reemplazos_personal_bloqueos')->find($blockId);
                    DB::table('reemplazos_personal')->where('id', $person->id)->update(['establecimiento_id' => $target->id, 'rbd' => $target->rbd]);
                    $moved = $person->fresh();
                    if (! $service->bloqueado($moved)) { throw new RuntimeException('El traslado perdió el bloqueo.'); }
                    $data = $moved->getAttributes();
                    unset($data['id']);
                    $data['row_hash'] = hash('sha256', random_bytes(32));
                    $data['source_filename'] = 'escenario-local-no-aplicar.xlsx';
                    $newId = DB::table('reemplazos_personal')->insertGetId($data);
                    $new = ReemplazoPersonal::findOrFail($newId);
                    if (! $service->bloqueado($new)
                        || ! $service->filtrarBloqueados(ReemplazoPersonal::query()->whereKey($newId))->exists()
                        || $block != DB::table('reemplazos_personal_bloqueos')->find($blockId)) {
                        throw new RuntimeException('Nuevo ID o traslado no conservaron el bloqueo original.');
                    }
                    $report['same_id_transfer_blocked'] = true;
                    $report['new_id_same_person_blocked'] = true;
                    $report['original_block_unchanged'] = true;
                } else {
                    $ids = DB::table('reemplazos_personal')->orderBy('id')->pluck('id')->all();
                    $history = app(\App\Services\Padron\PadronHistorialService::class);
                    $report['documents_frozen'] = $history->congelarReferencias($ids);
                    $revision = PadronRevision::findOrFail((int) ($options['revision'] ?? 0));
                    app(\App\Services\Padron\PadronPeriodoService::class)->antesDeAplicar($revision, (int) DB::table('users')->min('id'));
                    $report['period_snapshots'] = DB::table('padron_periodo_versiones')->count();
                    $report['contract_snapshots'] = DB::table('padron_periodo_personal')->count();
                    $checked = 0;
                    foreach (\App\Services\Padron\PadronHistorialService::DOCUMENTOS as $table) {
                        foreach (DB::table($table)->whereNotNull('reemplazo_personal_id')->lazyById(100) as $doc) {
                            if ($doc->padron_personal_snapshot === null) { throw new RuntimeException('Documento sin copia contractual.'); }
                            $history->leer(json_decode($doc->padron_personal_snapshot, true, 512, JSON_THROW_ON_ERROR), (int) $doc->reemplazo_personal_id);
                            $checked++;
                        }
                    }
                    $report['document_snapshots_validated'] = $checked;
                }
            } finally {
                DB::rollBack();
            }
            if ($before !== Lab::state()) { throw new RuntimeException('La prueba reversible dejó modificaciones.'); }
            $report['rollback_exact'] = true;
        } elseif ($action === 'blocks') {
            $service = app(PadronBloqueoService::class);
            $before = Lab::state();
            $count = 0;
            // Ejecuta el conteo correlacionado y la carga paginada contra datos reales.
            $report['blocked_contracts'] = $service->filtrarBloqueados(ReemplazoPersonal::query())->count();
            foreach ($service->filtrarBloqueados(ReemplazoPersonal::query())->orderBy('id')->lazyById(50) as $row) {
                $service->cargar(collect([$row]));
                if (! $service->bloqueado($row)) { throw new RuntimeException('Conteo y detalle de bloqueos no coinciden.'); }
                $count++;
            }
            $report['verified_contracts'] = $count;
            if ($count !== $report['blocked_contracts'] || $before !== Lab::state()) { throw new RuntimeException('Comprobación de bloqueos inconsistente.'); }
            $report['operational_state_unchanged'] = true;
        }
        if (app(PadronAplicacionService::class)->disponible()) { throw new RuntimeException('El servicio productivo no debe habilitarse.'); }
    }
    $report['status'] ??= 'completed';
    unset($report['baseline_hashes']);
} catch (Throwable $e) {
    $report['status'] = 'failed';
    $report['exception'] = get_class($e);
    $report['origin'] = basename($e->getFile()).':'.$e->getLine();
    // Solo mensajes propios de este ejecutor; nunca detalles SQL/validaciones con RUT.
    $report['error'] = $e->getFile() === __FILE__ ? $e->getMessage() : 'test_error_details_suppressed';
    if ($e instanceof RuntimeException
        && $e->getFile() === realpath(dirname(__DIR__).'/Support/PadronRehearsal.php')
        && preg_match('/^[a-z_]+$/D', $e->getMessage())) {
        // Códigos estáticos de las verificaciones; no contienen SQL ni datos.
        $report['error'] = $e->getMessage();
    }
    $previous = $e instanceof \Illuminate\Database\QueryException ? $e->getPrevious() : $e;
    if ($previous instanceof PDOException) { $report['driver_code'] = $previous->errorInfo[1] ?? null; }
}
$report['seconds'] = round(microtime(true) - $start, 3);
$report['peak_memory_mb'] = max($report['pre_application_peak_memory_mb'] ?? 0, round(memory_get_peak_usage(true) / 1048576, 2));
if ($root) { file_put_contents($root.'/report-'.($options['action'] ?? 'invalid').'-'.gmdate('YmdHis').'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); }
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n";
exit($report['status'] === 'completed' ? 0 : 1);
