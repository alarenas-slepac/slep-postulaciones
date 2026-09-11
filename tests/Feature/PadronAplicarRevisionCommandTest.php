<?php

namespace Tests\Feature;

use App\Console\Commands\PadronAplicarRevision;
use App\Models\PadronRevision;
use App\Models\User;
use App\Services\Padron\PadronAplicacionService;
use App\Services\Padron\PadronConciliador;
use App\Services\Padron\PadronHistorialService;
use App\Services\Padron\PadronRevisionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PadronAplicarRevisionCommandTest extends TestCase
{
    private PadronRevision $revision;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Log::spy();
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->softDeletes(); });
        Schema::create('roles', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); });
        Schema::create('model_has_roles', function (Blueprint $t) { $t->integer('role_id'); $t->integer('model_id'); $t->string('model_type'); });
        DB::table('users')->insert(['id' => 7]);
        DB::table('roles')->insert(['id' => 1, 'name' => 'admin', 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => 1, 'model_id' => 7, 'model_type' => User::class]);
        Schema::create('establecimientos', function (Blueprint $t) { $t->id(); $t->integer('rbd'); });
        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99999]);
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('rbd');
            foreach (['rut', 'nombre', 'tipocontrato', 'estatuto', 'escalafon', 'financiamiento'] as $f) { $t->string($f); }
            foreach (['fecha_nacimiento', 'fecha_ingreso', 'fecha_termino', 'fecha_antiguedad'] as $f) { $t->date($f)->nullable(); }
            foreach (['anio', 'mes', 'jornada', 'jornada_basica', 'jornada_media', 'bienios'] as $f) { $t->integer($f)->nullable(); }
            $t->string('tramo')->nullable(); $t->boolean('vigente')->default(true); $t->string('row_hash')->unique();
            $t->string('source_filename')->nullable(); $t->integer('created_by')->nullable(); $t->timestamps();
        });
        foreach (PadronHistorialService::DOCUMENTOS as $table) {
            Schema::create($table, function (Blueprint $t) { $t->id(); $t->integer('reemplazo_personal_id'); $t->timestamps(); });
        }
        foreach (['2026_09_08_120000_create_padron_revisiones', '2026_09_08_130000_add_padron_aplicacion_segura',
            '2026_09_08_150000_add_padron_snapshot_to_documentos', '2026_09_08_160000_create_padron_periodo_versiones'] as $file) {
            (require base_path('database/migrations/'.$file.'.php'))->up();
        }
        $data = ['rut' => '111111111', 'nombre' => 'Persona sintética', 'rbd' => 99999,
            'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'tipocontrato' => 'PLANTA',
            'financiamiento' => 'SUB.GENERAL', 'anio' => 2026, 'mes' => 8, 'jornada' => 30,
            'jornada_basica' => 30, 'jornada_media' => 0, 'bienios' => 0,
            'fecha_nacimiento' => '1980-01-01', 'fecha_ingreso' => '2020-03-01', 'fecha_termino' => null];
        DB::table('reemplazos_personal')->insert(array_replace($data, ['id' => 101, 'establecimiento_id' => 1,
            'mes' => 7, 'row_hash' => 'sintetico', 'created_by' => 7]));
        $snapshot = (new \ReflectionMethod(PadronRevisionService::class, 'snapshot'))->invoke(app(PadronRevisionService::class), 202608);
        $report = app(PadronConciliador::class)->reconcile([['datos' => $data, 'fila_excel' => 2, 'observaciones' => []]],
            $snapshot['personal'], $snapshot['establecimientos'], $snapshot['asignaciones'], $snapshot['declaraciones']);
        $this->revision = PadronRevision::create(['archivo' => 'sintetico.xlsx', 'archivo_hash' => str_repeat('a', 64),
            'base_hash' => $snapshot['hash'], 'created_by' => 7, 'anio' => 2026, 'mes' => 8,
            'resumen' => $report['resumen'], 'errores' => $report['errores'], 'excesos' => $report['excesos']]);
        $this->revision->filas()->createMany($report['filas']);
    }

    private function command(bool $realPreflight = false): void
    {
        // Solo la inspección MariaDB se sustituye en SQLite. Se prueba el escritor real, sin mock.
        $command = $realPreflight ? new PadronAplicarRevision : new class extends PadronAplicarRevision {
            protected function verificarEntorno(): void
            {
                if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== ':memory:') {
                    throw new \LogicException('Solo SQLite efímero.');
                }
            }
        };
        Artisan::all(); // Inicializa el descubrimiento antes de registrar la instancia aislada.
        Artisan::registerCommand($command);
    }

    private function arguments(): array
    {
        return ['revision' => (string) $this->revision->id, '--usuario' => '7'];
    }

    private function confirmed(): array
    {
        return $this->arguments() + ['--aplicar' => true,
            '--confirmacion' => app(PadronAplicacionService::class)->plan($this->revision)['confirmacion_hash'],
            '--confirmar' => 'APLICAR:'.$this->revision->id.':2026:8', '--respaldo-sha256' => str_repeat('b', 64)];
    }

    public function test_default_consults_without_writes_and_web_stays_closed(): void
    {
        $this->command();
        $this->assertSame(0, Artisan::call('padron:aplicar-revision', $this->arguments()));
        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($report['solo_lectura']);
        $this->assertSame('lista_para_confirmar', $report['estado']);
        $this->assertSame(64, strlen($report['confirmacion']));
        $this->assertStringNotContainsString('111111111', Artisan::output());
        $this->assertUnchanged();
    }

    public function test_confirmed_application_audits_and_retry_is_idempotent(): void
    {
        $this->command();
        $options = $this->confirmed();
        $this->assertSame(0, Artisan::call('padron:aplicar-revision', $options), Artisan::output());
        $this->assertNotNull($this->revision->fresh()->aplicada_at);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'mes' => 8, 'row_hash' => 'sintetico']);
        $this->assertDatabaseHas('padron_personal_cambios', ['personal_id' => 101, 'usuario_id' => 7]);
        $versions = DB::table('padron_periodo_versiones')->count();
        Log::shouldHaveReceived('info')->with('padron.cli.aplicacion_completada', \Mockery::on(fn ($c) => $c['respaldo_sha256'] === str_repeat('b', 64)))->once();
        $this->assertSame(0, Artisan::call('padron:aplicar-revision', $options));
        $this->assertStringContainsString('ya_aplicada', Artisan::output());
        $this->assertDatabaseCount('padron_personal_cambios', 1);
        $this->assertDatabaseCount('padron_periodo_versiones', $versions);
        $this->assertFalse(app(PadronAplicacionService::class)->disponible());
    }

    public function test_missing_each_confirmation_rejects_without_writes(): void
    {
        $this->command();
        foreach (['--confirmacion', '--confirmar', '--respaldo-sha256'] as $option) {
            $args = $this->confirmed();
            unset($args[$option]);
            $this->assertSame(1, Artisan::call('padron:aplicar-revision', $args));
            $this->assertUnchanged();
        }
    }

    public function test_non_admin_missing_and_deleted_users_are_rejected(): void
    {
        $this->command();
        DB::table('users')->insert([['id' => 8], ['id' => 9]]);
        DB::table('users')->where('id', 9)->update(['deleted_at' => now()]);
        foreach (['8', '9', '1000', '0', '7x'] as $id) {
            $this->assertSame(1, Artisan::call('padron:aplicar-revision', array_replace($this->arguments(), ['--usuario' => $id])));
            $this->assertUnchanged();
        }
    }

    public function test_changed_plan_and_blocked_plan_cannot_be_applied(): void
    {
        $this->command();
        $args = $this->confirmed();
        $this->revision->update(['archivo' => 'otra-version.xlsx']);
        $this->assertSame(1, Artisan::call('padron:aplicar-revision', $args));
        $this->assertUnchanged();
        $this->revision->update(['errores' => ['Dato inválido del RUT 111111111']]);
        $this->assertSame(1, Artisan::call('padron:aplicar-revision', $this->arguments()));
        $this->assertStringNotContainsString('111111111', Artisan::output());
        $this->assertUnchanged();
    }

    public function test_stale_base_rejects_even_readiness(): void
    {
        $this->command();
        DB::table('reemplazos_personal')->where('id', 101)->update(['jornada' => 31]);
        $this->assertSame(1, Artisan::call('padron:aplicar-revision', $this->arguments()));
        $this->assertStringContainsString('base contractual cambió', Artisan::output());
        $this->assertUnchanged();
    }

    public function test_real_preflight_refuses_sqlite_without_application(): void
    {
        $this->command(true);
        $this->assertSame(1, Artisan::call('padron:aplicar-revision', $this->arguments()));
        $this->assertUnchanged();
    }

    public function test_mid_write_failure_rolls_back_through_cli(): void
    {
        $this->command();
        $args = $this->confirmed();
        DB::listen(function ($event): void {
            if (str_starts_with($event->sql, 'insert into "padron_personal_cambios"')) {
                throw new \RuntimeException('Fallo sintético sin datos reales.');
            }
        });
        $this->assertSame(1, Artisan::call('padron:aplicar-revision', $args));
        $this->assertUnchanged();
        $this->assertSame(0, DB::transactionLevel());
        Log::shouldNotHaveReceived('info', ['padron.cli.aplicacion_completada', \Mockery::any()]);
    }

    public function test_http_context_cannot_invoke_command_writer(): void
    {
        $this->command();
        $property = new \ReflectionProperty($this->app, 'isRunningInConsole');
        $previous = $property->getValue($this->app);
        try {
            $property->setValue($this->app, false);
            $this->assertSame(1, Artisan::call('padron:aplicar-revision', $this->arguments()));
            $this->assertStringContainsString('únicamente desde PHP CLI', Artisan::output());
            $this->assertUnchanged();
        } finally {
            $property->setValue($this->app, $previous);
        }
    }

    public function test_preflight_requires_finite_256_mb_process_limit(): void
    {
        $this->command(true);
        $previous = ini_get('memory_limit');
        try {
            foreach (['128M', '-1'] as $limit) {
                ini_set('memory_limit', $limit);
                $this->assertSame(1, Artisan::call('padron:aplicar-revision', $this->arguments()));
                $this->assertStringContainsString('memory_limit=256M', Artisan::output());
                $this->assertUnchanged();
            }
        } finally {
            ini_set('memory_limit', $previous);
        }
    }

    private function assertUnchanged(): void
    {
        $this->assertNull($this->revision->fresh()->aplicada_at);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'mes' => 7]);
        $this->assertDatabaseCount('padron_personal_cambios', 0);
        $this->assertDatabaseCount('padron_periodo_versiones', 0);
        $this->assertFalse(app(PadronAplicacionService::class)->disponible());
    }
}
