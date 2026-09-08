<?php

namespace Tests\Support;

use App\Models\PadronRevision;
use App\Services\Padron\PadronAplicacionService;
use App\Services\Padron\PadronConciliador;
use App\Services\Padron\PadronRevisionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Laboratorio CLI opt-in. Nunca utiliza DB_* ni la configuración de .env. */
final class PadronMySqlLab
{
    public static function credentials(): array
    {
        $host = getenv('PADRON_MYSQL_HOST') ?: '127.0.0.1';
        $port = getenv('PADRON_MYSQL_PORT') ?: '3306';
        $user = getenv('PADRON_MYSQL_USER');
        if (PHP_SAPI !== 'cli' || $host !== '127.0.0.1' || ! ctype_digit($port) || ! $user) {
            throw new \RuntimeException('Solo CLI, TCP loopback y usuario PADRON_MYSQL_USER explícito.');
        }
        return ['host' => $host, 'port' => $port, 'username' => $user, 'password' => getenv('PADRON_MYSQL_PASSWORD') ?: ''];
    }

    public static function admin(): \PDO
    {
        $c = self::credentials();
        return new \PDO("mysql:host={$c['host']};port={$c['port']};charset=utf8mb4", $c['username'], $c['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION, \PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    public static function assertName(string $database): void
    {
        $run = getenv('PADRON_MYSQL_LAB_RUN') ?: '';
        if (! preg_match('/^[a-f0-9]{16}$/D', $run)
            || ! preg_match('/^padron_lab_'.preg_quote($run, '/').'_[0-9]{2}$/D', $database)) {
            throw new \RuntimeException('Base fuera del espacio aislado de esta ejecución.');
        }
    }

    public static function boot(string $database, string $isolation): void
    {
        self::assertName($database);
        self::credentials();
        if (! in_array($isolation, ['REPEATABLE READ', 'READ COMMITTED'], true)) {
            throw new \RuntimeException('Aislamiento no permitido.');
        }
        if (! isset($GLOBALS['padron_lab_app'])) {
            // No cargar .env ni un config cache de la aplicación real.
            putenv('APP_ENV=testing');
            putenv('APP_CONFIG_CACHE='.sys_get_temp_dir().'/padron_lab_'.getenv('PADRON_MYSQL_LAB_RUN').'_no_config.php');
            $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
            $app->loadEnvironmentFrom('__padron_lab_no_env__');
            $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
                static function ($app) use ($database): void {
                    $app['config']->set([
                        'app.env' => 'testing', 'database.default' => 'padron_lab',
                        'database.connections.padron_lab' => self::connection($database),
                        'cache.default' => 'array', 'session.driver' => 'array',
                        'queue.default' => 'sync', 'mail.default' => 'array',
                        'logging.default' => 'null',
                        'logging.channels.null' => ['driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class],
                    ]);
                    $app->instance('env', 'testing');
                });
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            $GLOBALS['padron_lab_app'] = $app;
        }
        DB::purge('padron_lab');
        config(['database.default' => 'padron_lab', 'database.connections.padron_lab' => self::connection($database)]);
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL '.$isolation);
        DB::statement('SET SESSION innodb_lock_wait_timeout = 8');
        DB::disableQueryLog();
        if (DB::selectOne('SELECT DATABASE() AS db')->db !== $database || ! app()->environment('testing')) {
            throw new \RuntimeException('Conexión de laboratorio no verificada.');
        }
    }

    private static function connection(string $database): array
    {
        return self::credentials() + ['driver' => 'mysql', 'database' => $database,
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '',
            'strict' => true, 'engine' => 'InnoDB', 'url' => null, 'unix_socket' => '',
            'options' => [\PDO::ATTR_EMULATE_PREPARES => false]];
    }

    public static function guard(): void
    {
        self::assertName(DB::connection()->getDatabaseName());
        if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'mysql'
            || DB::table('padron_lab_guard')->where('id', 1)->value('run_id') !== getenv('PADRON_MYSQL_LAB_RUN')) {
            throw new \RuntimeException('Falta la marca de propiedad del laboratorio.');
        }
    }

    public static function writer(): PadronAplicacionService
    {
        self::guard();
        // No se cambia la constante ni el binding usado por rutas productivas.
        return new class(app(PadronRevisionService::class)) extends PadronAplicacionService {
            public function disponible(): bool
            {
                PadronMySqlLab::guard();
                return true;
            }
        };
    }

    public static function data(array $changes = []): array
    {
        return array_replace(['rut' => '111111111', 'nombre' => 'Persona sintetica', 'rbd' => 99999,
            'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE AULA', 'tipocontrato' => 'CONTRATA',
            'financiamiento' => 'REGULAR', 'anio' => 2026, 'mes' => 9, 'jornada' => 30,
            'jornada_basica' => 30, 'jornada_media' => 0, 'bienios' => 3,
            'fecha_nacimiento' => '1980-01-01', 'fecha_ingreso' => '2020-03-01',
            'fecha_termino' => null, 'fecha_antiguedad' => null], $changes);
    }

    public static function fixture(): void
    {
        self::assertName(DB::connection()->getDatabaseName());
        if (DB::selectOne('SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = DATABASE()')->n != 0) {
            throw new \RuntimeException('El fixture solo se crea en una base nueva y vacía.');
        }
        Schema::create('padron_lab_guard', function (Blueprint $t) { $t->id(); $t->string('run_id'); });
        DB::table('padron_lab_guard')->insert(['id' => 1, 'run_id' => getenv('PADRON_MYSQL_LAB_RUN')]);
        self::guard();
        Schema::create('establecimientos', function (Blueprint $t) { $t->id(); $t->unsignedInteger('rbd'); });
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('establecimiento_id'); $t->unsignedInteger('rbd');
            foreach (['rut', 'nombre', 'tipocontrato', 'estatuto', 'escalafon', 'financiamiento'] as $field) { $t->string($field); }
            foreach (['fecha_nacimiento', 'fecha_ingreso', 'fecha_termino', 'fecha_antiguedad'] as $field) { $t->date($field)->nullable(); }
            foreach (['anio', 'mes', 'jornada', 'jornada_basica', 'jornada_media', 'bienios'] as $field) { $t->integer($field)->nullable(); }
            $t->string('tramo')->nullable(); $t->boolean('vigente')->default(true);
            $t->string('row_hash')->unique(); $t->string('source_filename')->nullable();
            $t->unsignedBigInteger('created_by')->nullable(); $t->timestamps();
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t) {
            $t->id(); $t->integer('anio'); $t->unsignedBigInteger('establecimiento_id');
            // La migración real utiliza índice SIN FK contractual, también acepta vínculo por RUT.
            $t->unsignedBigInteger('reemplazos_personal_id')->nullable()->index();
            $t->string('docente_rut'); $t->string('estado'); $t->decimal('horas_contrato', 10, 2);
        });
        Schema::create('declaracion_sostenedores', function (Blueprint $t) {
            $t->id(); $t->string('rut'); $t->unsignedInteger('rbd'); $t->string('estamento'); $t->decimal('horas_contratadas', 10, 2);
        });
        Schema::create('dotacion_docente_exclusiones', function (Blueprint $t) {
            $t->id(); $t->integer('anio'); $t->unsignedBigInteger('establecimiento_id'); $t->string('docente_rut'); $t->decimal('horas', 10, 2);
        });
        foreach (['solicitudes_reemplazo', 'cometidos_funcionarios', 'incumplimientos_laborales', 'reemplazos_personal_bloqueos'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id(); $t->foreignId('reemplazo_personal_id')->nullable()->constrained('reemplazos_personal');
                $t->string('estado')->default('cerrado'); $t->timestamps();
            });
        }
        Schema::create('padron_lab_ballast', function (Blueprint $t) { $t->id(); $t->integer('n')->default(0); });
        DB::table('padron_lab_ballast')->insert(array_map(fn ($id) => ['id' => $id], range(1, 100)));
        foreach (['2026_09_08_120000_create_padron_revisiones.php', '2026_09_08_130000_add_padron_aplicacion_segura.php',
            '2026_09_08_150000_add_padron_snapshot_to_documentos.php', '2026_09_08_160000_create_padron_periodo_versiones.php'] as $file) {
            (require dirname(__DIR__, 2).'/database/migrations/'.$file)->up();
        }
        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99999]);
        foreach ([101 => '111111111', 102 => '222222222', 103 => '333333333'] as $id => $rut) {
            DB::table('reemplazos_personal')->insert(['id' => $id, 'establecimiento_id' => 1, 'row_hash' => 'sintetico-'.$id,
                'vigente' => $id !== 103, 'created_by' => 1, 'created_at' => '2026-08-01 00:00:00', 'updated_at' => '2026-08-01 00:00:00']
                + self::data(['rut' => $rut, 'mes' => 8, 'jornada' => 44, 'jornada_basica' => 44]));
        }
        DB::table('dotacion_docente_asignaciones')->insert(['id' => 501, 'anio' => 2026, 'establecimiento_id' => 1,
            'reemplazos_personal_id' => 101, 'docente_rut' => '111111111', 'estado' => 'activa', 'horas_contrato' => 20]);
        DB::table('declaracion_sostenedores')->insert(['id' => 1, 'rut' => '111111111', 'rbd' => 99999, 'estamento' => 'DOCENTE', 'horas_contratadas' => 30]);
        DB::table('dotacion_docente_exclusiones')->insert(['id' => 1, 'anio' => 2026, 'establecimiento_id' => 1, 'docente_rut' => '111111111', 'horas' => 0]);
        foreach (\App\Services\Padron\PadronHistorialService::DOCUMENTOS as $table) {
            DB::table($table)->insert(['id' => 1, 'reemplazo_personal_id' => 101]);
            DB::table($table)->insert(['id' => 2, 'reemplazo_personal_id' => 102]);
        }
        DB::table('reemplazos_personal_bloqueos')->insert(['id' => 1, 'reemplazo_personal_id' => 102]);
        $bad = DB::selectOne("SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = DATABASE() AND engine <> 'InnoDB'");
        if ($bad->n != 0 || app(PadronAplicacionService::class)->disponible()) { throw new \RuntimeException('Guardas o motores inválidos.'); }
    }

    public static function revision(): array
    {
        self::guard();
        $service = app(PadronRevisionService::class);
        $base = (new \ReflectionMethod($service, 'snapshot'))->invoke($service, 202609);
        $incoming = [self::data(), self::data(['rut' => '333333333']), self::data(['rut' => '444444444'])];
        $report = app(PadronConciliador::class)->reconcile(array_map(fn ($d, $i) => ['datos' => $d, 'fila_excel' => $i + 2, 'observaciones' => []], $incoming, array_keys($incoming)),
            $base['personal'], $base['establecimientos'], $base['asignaciones'], $base['declaraciones']);
        $r = PadronRevision::create(['archivo' => 'sintetico.xlsx', 'archivo_hash' => str_repeat('a', 64), 'base_hash' => $base['hash'],
            'created_by' => 1, 'anio' => 2026, 'mes' => 9, 'resumen' => $report['resumen'], 'errores' => $report['errores'], 'excesos' => $report['excesos']]);
        $r->filas()->createMany($report['filas']);
        $plan = self::writer()->plan($r);
        if ($plan['errores']) { throw new \RuntimeException('Fixture con bloqueos: '.implode(' ', $plan['errores'])); }
        return ['revision' => $r->id, 'token' => $plan['confirmacion_hash']];
    }

    public static function state(): array
    {
        $tables = ['reemplazos_personal', 'dotacion_docente_asignaciones', 'declaracion_sostenedores', 'dotacion_docente_exclusiones',
            'padron_personal_cambios', 'padron_periodo_versiones', 'padron_periodo_personal', 'padron_revisiones',
            ...\App\Services\Padron\PadronHistorialService::DOCUMENTOS];
        $out = [];
        foreach ($tables as $table) { $out[$table] = hash('sha256', json_encode(DB::table($table)->orderBy('id')->get(), JSON_THROW_ON_ERROR)); }
        return $out;
    }
}
