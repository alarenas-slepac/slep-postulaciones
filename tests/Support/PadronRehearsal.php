<?php

namespace Tests\Support;

use App\Models\PadronRevision;
use App\Services\Padron\PadronAplicacionService;
use App\Services\Padron\PadronBloqueoService;
use App\Services\Padron\PadronConciliador;
use App\Services\Padron\PadronExcelReader;
use App\Services\Padron\PadronHistorialService;
use App\Services\Padron\PadronRevisionService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

/** Solo lo invoca el CLI privado; nunca se registra en el contenedor o las rutas. */
final class PadronRehearsal
{
    public static function run(string $root, int $revisionId, int $userId): array
    {
        $root = self::privateRoot($root);
        PadronMySqlLab::guard();
        self::check(PHP_SAPI === 'cli' && ! app(PadronAplicacionService::class)->disponible(), 'production_guard');
        self::check($revisionId > 0 && $userId > 0 && DB::table('users')->where('id', $userId)->exists(), 'explicit_actor_required');
        $source = DB::connection()->getDatabaseName();
        $sourceRun = getenv('PADRON_MYSQL_LAB_RUN');
        $revision = PadronRevision::findOrFail($revisionId);
        if ($revision->aplicada_at || app(PadronRevisionService::class)->stale($revision)) {
            return ['status' => 'blocked', 'reason' => 'review_closed_or_stale', 'source_unchanged' => true];
        }
        $baseline = self::state();
        $plan = PadronMySqlLab::writer()->plan($revision);
        if ($plan['errores']) {
            self::check($baseline === self::state(), 'preflight_changed_source');
            return ['status' => 'blocked', 'reason' => 'unresolved_review', 'plan_errors' => count($plan['errores']),
                'source_unchanged' => true, 'clone_created' => false];
        }
        $token = $plan['confirmacion_hash'];
        unset($plan);

        // No sobrescribir la copia original. CREATE DATABASE falla si ya existe.
        $cloneRun = bin2hex(random_bytes(8));
        $clone = 'padron_lab_'.$cloneRun.'_90';
        $directory = $root.'/rehearsal-'.$cloneRun;
        self::check(mkdir($directory, 0700), 'private_directory');
        $pdo = PadronMySqlLab::admin();
        self::check($pdo->query('SELECT VERSION()')->fetchColumn() === '10.11.18-MariaDB', 'exact_engine');
        self::check(realpath($pdo->query('SELECT @@datadir')->fetchColumn()) === realpath($root.'/data'), 'private_instance');
        // No omitir silenciosamente efectos SQL al copiar.
        $q = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ?');
        $q->execute([$source]);
        self::check((int) $q->fetchColumn() === 0, 'triggers_require_review');
        $q = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND (TABLE_TYPE <> 'BASE TABLE' OR ENGINE <> 'InnoDB')");
        $q->execute([$source]);
        self::check((int) $q->fetchColumn() === 0, 'tables_require_review');
        $credentials = PadronMySqlLab::credentials();
        $args = ['--no-defaults', '--host=127.0.0.1', '--port='.$credentials['port'], '--user='.$credentials['username']];
        $dump = $directory.'/baseline.sql';
        $process = new Process([$root.'/mariadb-10.11.18-winx64/bin/mariadb-dump.exe', ...$args,
            '--single-transaction', '--quick', '--skip-triggers', '--skip-routines', '--skip-events', '--skip-add-drop-table',
            '--hex-blob', '--result-file='.$dump, $source], $root, ['MYSQL_PWD' => $credentials['password']]);
        $process->setTimeout(1200);
        $process->run();
        self::check($process->isSuccessful(), 'clone_dump_failed');
        $pdo->exec('CREATE DATABASE `'.$clone.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $importUser = 'rehearse_'.$cloneRun;
        $importPass = bin2hex(random_bytes(32));
        $account = $pdo->quote($importUser)."@'localhost'";
        $pdo->exec('CREATE USER '.$account.' IDENTIFIED BY '.$pdo->quote($importPass));
        $stream = fopen($dump, 'rb');
        try {
            $pdo->exec('GRANT SELECT, INSERT, CREATE, ALTER, INDEX, REFERENCES, LOCK TABLES ON `'.$clone.'`.* TO '.$account);
            $process = new Process([$root.'/mariadb-10.11.18-winx64/bin/mariadb.exe', '--no-defaults',
                '--host=127.0.0.1', '--port='.$credentials['port'], '--user='.$importUser,
                '--binary-mode', '--local-infile=0', '--max-allowed-packet=128M', '--database='.$clone],
                $root, ['MYSQL_PWD' => $importPass], $stream, 1200);
            $process->run();
            self::check($process->isSuccessful(), 'clone_import_failed');
        } finally {
            fclose($stream);
            // Únicamente la cuenta efímera creada para esta copia; nunca una base.
            $pdo->exec('DROP USER '.$account);
        }
        self::check($baseline === self::state(), 'source_changed_during_clone');
        $q = $pdo->prepare('UPDATE `'.$clone.'`.padron_lab_guard SET run_id = ? WHERE id = 1');
        $q->execute([$cloneRun]);
        file_put_contents($directory.'/manifest.json', json_encode(['source' => $source, 'clone' => $clone,
            'revision' => $revisionId, 'user' => $userId, 'baseline' => $baseline], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));

        try {
            putenv('PADRON_MYSQL_LAB_RUN='.$cloneRun);
            PadronMySqlLab::boot($clone, 'REPEATABLE READ');
            PadronMySqlLab::guard();
            self::check($baseline === self::state(), 'clone_not_identical');
            $revision = PadronRevision::findOrFail($revisionId);
            $writer = PadronMySqlLab::writer();
            self::check(! app(PadronRevisionService::class)->stale($revision), 'clone_review_stale');
            $plan = $writer->plan($revision);
            self::check(! $plan['errores'] && hash_equals($token, $plan['confirmacion_hash']), 'clone_plan_changed');
            $expected = $directory.'/expected.jsonl';
            $file = fopen($expected, 'xb');
            $changes = [];
            try {
                foreach ($plan['destinos'] as $destination) {
                    $id = $destination['id'];
                    if ($id !== null) { $changes[$id] = true; }
                    fwrite($file, json_encode(['id' => $id, 'fila' => $destination['fila']->id,
                        'data' => $destination['fila']->datos], JSON_THROW_ON_ERROR)."\n");
                }
            } finally {
                fclose($file);
            }
            $bajas = $plan['bajas'];
            $releases = [];
            foreach ($plan['conflictos']['bajas_asignaciones'] ?? [] as $confirmation) {
                if (! $confirmation['confirmada']) { continue; }
                foreach ($confirmation['alcance']['asignaciones'] as $id => $hash) {
                    $releases[$id] = ['hash' => $hash, 'confirmation' => $confirmation['ultima_id']];
                }
            }
            foreach ($bajas as $id) { $changes[$id] = true; }
        $destinations = count($plan['destinos']);
            unset($plan);
            // Falla después de la primera auditoría: ya hubo congelación, versiones
            // y escritura contractual, todo dentro de la transacción real.
            $inject = true;
            $injected = false;
            DB::listen(static function (QueryExecuted $event) use (&$inject, &$injected): void {
                if ($inject && str_starts_with(strtolower($event->sql), 'insert into `padron_personal_cambios`')) {
                    $injected = true;
                    throw new \RuntimeException('rehearsal_injected_failure');
                }
            });
            try {
                $writer->aplicar($revision, $userId, $token);
                throw new \RuntimeException('rollback_probe_did_not_fail');
            } catch (\RuntimeException $e) {
                self::check($injected && $e->getMessage() === 'rehearsal_injected_failure', 'unexpected_rollback_error');
            } finally {
                $inject = false;
            }
            self::check(DB::transactionLevel() === 0 && $baseline === self::state(), 'rollback_not_exact');

            $preApplicationPeak = round(memory_get_peak_usage(true) / 1048576, 2);
            memory_reset_peak_usage();
            $started = microtime(true);
            $applied = $writer->aplicar($revision, $userId, $token);
            $seconds = round(microtime(true) - $started, 3);
            $peak = round(memory_get_peak_usage(true) / 1048576, 2);
            self::check($applied->aplicada_at !== null && (int) $applied->aplicada_por === $userId, 'completion_not_saved');
            $result = self::verify($pdo, $source, $revisionId, $expected, $changes, $bajas, $destinations, $releases, $userId);
            $after = self::state();
            $writer->aplicar($revision, $userId, $token);
            self::check($after === self::state(), 'retry_not_idempotent');
            return $result + ['status' => 'completed', 'clone_created' => true, 'clone' => $clone,
                'rollback_exact' => true, 'retry_idempotent' => true, 'application_seconds' => $seconds,
                'application_peak_memory_mb' => $peak, 'pre_application_peak_memory_mb' => $preApplicationPeak,
                'source_unchanged' => true];
        } finally {
            putenv('PADRON_MYSQL_LAB_RUN='.$sourceRun);
            PadronMySqlLab::boot($source, 'REPEATABLE READ');
            self::check($baseline === self::state(), 'original_copy_changed');
        }
    }

    public static function privateRoot(string $root): string
    {
        $allowed = realpath(getenv('LOCALAPPDATA').'/SlepPadronLab');
        $candidate = $root !== '' ? realpath($root) : false;
        self::check(PHP_SAPI === 'cli' && $allowed && $candidate
            && str_starts_with($candidate, $allowed.DIRECTORY_SEPARATOR), 'private_root_required');
        return $candidate;
    }

    private static function verify(\PDO $sourcePdo, string $source, int $revision, string $expected, array $changed, array $bajas, int $destinations, array $releases, int $userId): array
    {
        $person = $sourcePdo->prepare('SELECT * FROM `'.$source.'`.reemplazos_personal WHERE id = ?');
        $oldCount = 0;
        $deactivations = 0;
        foreach (self::sourceRows($sourcePdo, $source, 'reemplazos_personal') as $old) {
            $oldCount++;
            $current = (array) DB::table('reemplazos_personal')->find($old['id']);
            self::check($current !== [], 'old_id_missing');
            if (! isset($changed[$old['id']])) {
                self::check($old === $current, 'unplanned_personnel_change');
                continue;
            }
            foreach (['id', 'rut', 'row_hash', 'created_at', 'created_by'] as $key) {
                self::check($old[$key] === $current[$key], 'identity_or_metadata_changed');
            }
            if (in_array((int) $old['id'], $bajas, true)) {
                self::check(! $current['vigente'], 'absence_not_deactivated');
                $deactivations += (int) (bool) $old['vigente'];
                self::check(array_diff_key($old, array_flip(['vigente', 'updated_at']))
                    === array_diff_key($current, array_flip(['vigente', 'updated_at'])), 'absence_fields_changed');
                if (! $old['vigente']) { self::check($old === $current, 'inactive_absence_changed'); continue; }
            }
            $audit = DB::table('padron_personal_cambios')->where('padron_revision_id', $revision)->where('personal_id', $old['id'])->first();
            self::check($audit && json_decode($audit->antes, true, 512, JSON_THROW_ON_ERROR) === $old
                && json_decode($audit->despues, true, 512, JSON_THROW_ON_ERROR) === $current, 'audit_images_mismatch');
        }
        $newCount = 0;
        $updates = 0;
        $reactivations = 0;
        $file = fopen($expected, 'rb');
        try {
            while (($line = fgets($file)) !== false) {
                $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $current = (array) ($entry['id'] === null
                    ? DB::table('reemplazos_personal')->where('row_hash', hash('sha256', 'padron|'.$revision.'|'.$entry['fila']))->first()
                    : DB::table('reemplazos_personal')->find($entry['id']));
                self::check($current !== [] && (bool) $current['vigente'], 'planned_destination_missing');
                $data = array_intersect_key($entry['data'], array_flip([...PadronExcelReader::REQUIRED, 'tramo', 'fecha_antiguedad']));
                if (empty($data['fecha_antiguedad'])) { unset($data['fecha_antiguedad']); }
                foreach ($data as $key => $value) {
                    if ($key === 'rut') {
                        self::check(PadronConciliador::rut($current[$key]) === PadronConciliador::rut($value), 'planned_rut_mismatch');
                    } elseif (in_array($key, ['rbd', 'anio', 'mes', 'jornada', 'jornada_basica', 'jornada_media', 'bienios'], true)
                        && is_numeric($value) && is_numeric($current[$key])) {
                        self::check((float) $value === (float) $current[$key], 'planned_number_mismatch');
                    } else {
                        self::check(($value === null && $current[$key] === null)
                            || ($value !== null && $current[$key] !== null && (string) $value === (string) $current[$key]), 'planned_field_mismatch');
                    }
                }
                self::check((int) DB::table('establecimientos')->where('rbd', $current['rbd'])->value('id') === (int) $current['establecimiento_id'], 'planned_establishment_mismatch');
                $audit = DB::table('padron_personal_cambios')->where('padron_revision_id', $revision)->where('personal_id', $current['id'])->first();
                if ($entry['id'] === null) {
                    $newCount++;
                    self::check($audit && $audit->accion === 'incorporacion' && $audit->antes === null
                        && json_decode($audit->despues, true, 512, JSON_THROW_ON_ERROR) === $current, 'new_contract_audit_mismatch');
                } else {
                    $person->execute([$entry['id']]);
                    $old = $person->fetch(\PDO::FETCH_ASSOC);
                    $action = $old['vigente'] ? 'actualizacion' : 'reactivacion';
                    self::check($audit && $audit->accion === $action, 'contract_action_mismatch');
                    $updates += (int) (bool) $old['vigente'];
                    $reactivations += (int) ! $old['vigente'];
                    $allowed = array_fill_keys([...array_keys($data), 'establecimiento_id', 'vigente', 'source_filename', 'updated_at'], true);
                    self::check(array_diff_key($old, $allowed) === array_diff_key($current, $allowed), 'unrequested_fields_changed');
                }
            }
        } finally {
            fclose($file);
        }
        self::check(DB::table('reemplazos_personal')->count() === $oldCount + $newCount, 'personnel_count_mismatch');
        self::check(DB::table('padron_personal_cambios')->where('padron_revision_id', $revision)->count() === $destinations + $deactivations, 'audit_count_mismatch');
        $documents = 0;
        $released = 0;
        foreach (['dotacion_docente_asignaciones', 'declaracion_sostenedores', 'dotacion_docente_exclusiones',
            'reemplazos_personal_bloqueos', 'padron_revision_filas', 'padron_revision_decisiones', 'padron_revision_autorizaciones',
            ...PadronHistorialService::DOCUMENTOS] as $table) {
            $count = 0;
            foreach (self::sourceRows($sourcePdo, $source, $table) as $old) {
                $count++;
                $current = (array) DB::table($table)->find($old['id']);
                self::check($current !== [], 'related_row_missing');
                if ($table === 'dotacion_docente_asignaciones' && isset($releases[$old['id']])) {
                    $release = $releases[$old['id']];
                    self::check(hash_equals($release['hash'], hash('sha256', json_encode($old, JSON_THROW_ON_ERROR)))
                        && $old['estado'] === 'activa' && $current['estado'] === 'inactiva', 'release_scope_mismatch');
                    $audit = DB::table('padron_asignacion_cambios')->where('padron_revision_id', $revision)->where('asignacion_id', $old['id'])->first();
                    self::check($audit && (int) $audit->baja_asignaciones_id === $release['confirmation']
                        && (int) $audit->usuario_id === $userId
                        && json_decode($audit->antes, true, 512, JSON_THROW_ON_ERROR) === $old
                        && json_decode($audit->despues, true, 512, JSON_THROW_ON_ERROR) === $current, 'release_audit_mismatch');
                    $allowed = array_flip(['estado', 'updated_at', 'updated_by']);
                    self::check(array_diff_key($old, $allowed) === array_diff_key($current, $allowed), 'release_changed_assignment_fields');
                    $released++;
                    continue;
                }
                if (in_array($table, PadronHistorialService::DOCUMENTOS, true)) {
                    $id = (int) ($old['reemplazo_personal_id'] ?? 0);
                    if ($old['padron_personal_snapshot'] === null && isset($changed[$id])) {
                        $copy = json_decode($current['padron_personal_snapshot'] ?? 'null', true, 512, JSON_THROW_ON_ERROR);
                        self::check(is_array($copy), 'historical_snapshot_missing');
                        $historical = app(PadronHistorialService::class)->leer($copy, $id)->getAttributes();
                        $person->execute([$id]);
                        $before = $person->fetch(\PDO::FETCH_ASSOC);
                        self::check(array_intersect_key($before, $historical) === $historical, 'historical_contract_changed');
                        $current['padron_personal_snapshot'] = null;
                        $documents++;
                    }
                }
                self::check($old === $current, 'related_data_changed');
            }
            self::check(DB::table($table)->count() === $count, 'related_count_changed');
        }
        self::check($released === count($releases), 'planned_release_missing');
        foreach (['padron_bajas_asignaciones', 'padron_asignacion_cambios'] as $table) {
            if (! Schema::hasTable($table)) { continue; }
            $count = 0;
            foreach (self::sourceRows($sourcePdo, $source, $table) as $old) {
                $count++;
                self::check($old === (array) DB::table($table)->find($old['id']), 'previous_release_history_changed');
            }
            self::check(DB::table($table)->count() === $count + ($table === 'padron_asignacion_cambios' ? $released : 0), 'release_history_count_mismatch');
        }
        foreach (['padron_periodo_versiones', 'padron_periodo_personal'] as $table) {
            foreach (self::sourceRows($sourcePdo, $source, $table) as $old) {
                self::check($old === (array) DB::table($table)->find($old['id']), 'previous_period_history_changed');
            }
        }
        $versions = 0;
        foreach (DB::table('padron_periodo_versiones')->where('padron_revision_id', $revision)->lazyById(100) as $version) {
            $hash = hash_init('sha256');
            $count = 0;
            foreach (DB::table('padron_periodo_personal')->where('version_id', $version->id)->lazyById(100) as $row) {
                $copy = json_decode($row->datos, true, 512, JSON_THROW_ON_ERROR);
                if ($version->origen === 'previa') {
                    $person->execute([$row->personal_id]);
                    $original = $person->fetch(\PDO::FETCH_ASSOC);
                } else {
                    self::check($version->origen === 'aplicada', 'unexpected_period_origin');
                    $original = (array) DB::table('reemplazos_personal')->find($row->personal_id);
                }
                self::check($copy === $original, 'period_contract_mismatch');
                hash_update($hash, $row->datos."\n");
                $count++;
            }
            self::check($version->completada_at !== null && (int) $version->registros === $count
                && hash_equals($version->huella, hash_final($hash)), 'period_version_incomplete');
            $versions++;
        }
        self::check($versions > 0, 'period_versions_missing');
        $blockedRuts = [];
        foreach (self::sourceRows($sourcePdo, $source, 'reemplazos_personal_bloqueos') as $block) {
            if (! ($block['activo'] ?? false)) { continue; }
            $rut = PadronConciliador::rut($block['rut'] ?? '');
            if ($rut === '' && ! empty($block['reemplazo_personal_id'])) {
                $person->execute([$block['reemplazo_personal_id']]);
                $rut = PadronConciliador::rut($person->fetch(\PDO::FETCH_ASSOC)['rut'] ?? '');
            }
            if ($rut !== '') { $blockedRuts[$rut] = true; }
        }
        $blockedContracts = 0;
        foreach (\App\Models\ReemplazoPersonal::query()->lazyById(100) as $row) {
            if (isset($blockedRuts[PadronConciliador::rut($row->rut)])) {
                self::check(app(PadronBloqueoService::class)->bloqueado($row), 'personal_block_lost');
                $blockedContracts++;
            }
        }
        return ['destinations_verified' => $destinations, 'new_contracts_verified' => $newCount,
            'updates_verified' => $updates, 'reactivations_verified' => $reactivations,
            'deactivations_verified' => $deactivations, 'historical_documents_verified' => $documents,
            'period_versions_verified' => $versions, 'blocked_contracts_verified' => $blockedContracts,
            'assignments_released_verified' => $released,
            'assignments_and_blocks_unchanged' => $released === 0, 'unplanned_assignments_and_blocks_unchanged' => true];
    }

    private static function sourceRows(\PDO $pdo, string $source, string $table): \Generator
    {
        $last = 0;
        $query = $pdo->prepare('SELECT * FROM `'.$source.'`.`'.$table.'` WHERE id > ? ORDER BY id LIMIT 100');
        do {
            $query->execute([$last]);
            $rows = $query->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $row) { $last = $row['id']; yield $row; }
        } while (count($rows) === 100);
    }

    private static function state(): array
    {
        $state = PadronMySqlLab::state();
        foreach (['padron_revision_filas', 'padron_revision_decisiones', 'padron_revision_autorizaciones', 'padron_bajas_asignaciones', 'padron_asignacion_cambios'] as $table) {
            if (! Schema::hasTable($table)) { continue; }
            $hash = hash_init('sha256');
            foreach (DB::table($table)->lazyById(100) as $row) { hash_update($hash, json_encode($row, JSON_THROW_ON_ERROR)."\n"); }
            $state[$table] = hash_final($hash);
        }
        return $state;
    }

    private static function check(bool $condition, string $code): void
    {
        if (! $condition) { throw new \RuntimeException($code); }
    }
}
