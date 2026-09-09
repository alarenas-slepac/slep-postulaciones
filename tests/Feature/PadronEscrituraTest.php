<?php

namespace Tests\Feature;

use App\Http\Middleware\CoordinarEscrituraPadron;
use App\Services\Padron\PadronEscrituraService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PadronEscrituraTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('padron_aplicacion_control', fn (Blueprint $t) => $t->id());
        Schema::create('padron_prueba_escritura', function (Blueprint $t) { $t->id(); $t->integer('valor'); });
        DB::table('padron_aplicacion_control')->insert(['id' => 1]);
    }

    public function test_control_is_first_read_and_nested_operation_shares_transaction(): void
    {
        $queries = [];
        DB::listen(function (QueryExecuted $event) use (&$queries) {
            if (DB::transactionLevel() > 0) { $queries[] = $event->sql; }
        });
        $result = app(PadronEscrituraService::class)->ejecutar(function () {
            $this->assertSame(1, DB::transactionLevel());
            return app(PadronEscrituraService::class)->ejecutar(function () {
                $this->assertSame(1, DB::transactionLevel());
                DB::table('padron_prueba_escritura')->insert(['valor' => 1]);
                return 7;
            });
        });
        $this->assertSame(7, $result);
        $this->assertStringContainsString('padron_aplicacion_control', $queries[0]);
        $this->assertDatabaseCount('padron_prueba_escritura', 1);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_failure_rolls_back_without_repeating_callback_and_releases_scope(): void
    {
        $calls = 0;
        try {
            app(PadronEscrituraService::class)->ejecutar(function () use (&$calls) {
                $calls++;
                DB::table('padron_prueba_escritura')->insert(['valor' => 1]);
                throw new \RuntimeException('fallo sintético');
            });
            $this->fail('Debe propagar el fallo.');
        } catch (\RuntimeException $e) { $this->assertSame('fallo sintético', $e->getMessage()); }
        $this->assertSame(1, $calls);
        $this->assertDatabaseCount('padron_prueba_escritura', 0);
        app(PadronEscrituraService::class)->ejecutar(fn () => DB::table('padron_prueba_escritura')->insert(['valor' => 2]));
        $this->assertDatabaseCount('padron_prueba_escritura', 1);
    }

    public function test_preexisting_transaction_is_rejected_before_callback(): void
    {
        DB::beginTransaction();
        try {
            $this->expectException(\LogicException::class);
            app(PadronEscrituraService::class)->ejecutar(fn () => $this->fail('No ejecutar desde una lectura antigua.'));
        } finally { DB::rollBack(); }
    }

    public function test_missing_control_row_fails_closed(): void
    {
        DB::table('padron_aplicacion_control')->where('id', 1)->delete();
        $this->expectException(ValidationException::class);
        app(PadronEscrituraService::class)->ejecutar(fn () => $this->fail('No guardar sin control.'));
    }

    public function test_legacy_schema_without_control_table_keeps_original_path(): void
    {
        Schema::drop('padron_aplicacion_control'); // Solo SQLite :memory:.
        $this->assertSame(0, app(PadronEscrituraService::class)->ejecutar(fn () => DB::transactionLevel()));
        $this->assertFalse(app(\App\Services\Padron\PadronAplicacionService::class)->disponible());
    }

    private function request(string $method = 'POST', string $controller = 'DeclaracionSostenedorController'): Request
    {
        $request = Request::create('/prueba', $method);
        $route = new \Illuminate\Routing\Route([$method], '/prueba', [
            'uses' => fn () => null, 'controller' => 'App\\Http\\Controllers\\'.$controller.'@store',
        ]);
        $request->setRouteResolver(fn () => $route);
        return $request;
    }

    public function test_http_error_response_rolls_back_even_if_pipeline_already_rendered_it(): void
    {
        $response = app(CoordinarEscrituraPadron::class)->handle($this->request(), function () {
            DB::table('padron_prueba_escritura')->insert(['valor' => 1]);
            return response('error sintético', 422);
        });
        $this->assertSame(422, $response->getStatusCode());
        $this->assertDatabaseCount('padron_prueba_escritura', 0);
    }

    public function test_read_requests_and_manual_review_are_not_wrapped(): void
    {
        foreach ([$this->request('GET'), $this->request('POST', 'Reemplazos\\PersonalImportController')] as $request) {
            app(CoordinarEscrituraPadron::class)->handle($request, function () {
                $this->assertSame(0, DB::transactionLevel());
                return response('ok');
            });
        }
    }

    public function test_real_web_pipeline_acquires_control_before_route_binding(): void
    {
        $bound = false;
        Route::bind('padronProbe', function ($value) use (&$bound) {
            $bound = true;
            $this->assertSame(1, DB::transactionLevel());
            return $value;
        });
        $route = Route::post('/padron-probe/{padronProbe}', fn () => response('ok'))->middleware('web');
        $route->setAction($route->getAction() + ['controller' => 'App\\Http\\Controllers\\DeclaracionSostenedorController@store']);
        $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
        $middleware = app('router')->gatherRouteMiddleware($route);
        $this->assertLessThan(array_search(SubstituteBindings::class, $middleware, true), array_search(CoordinarEscrituraPadron::class, $middleware, true));
        $this->post('/padron-probe/1')->assertOk();
        $this->assertTrue($bound);
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_legacy_get_regeneration_is_coordinated_but_download_is_not(): void
    {
        $request = $this->request('GET', 'Gestion\\OrdenTrabajoPdfController');
        $request->route()->setAction(['uses' => fn () => null, 'controller' => 'App\\Http\\Controllers\\Gestion\\OrdenTrabajoPdfController@download']);
        foreach ([false, true] as $regenerate) {
            $request->query->set('regenerar', $regenerate);
            app(CoordinarEscrituraPadron::class)->handle($request, function () use ($regenerate) {
                $this->assertSame($regenerate ? 1 : 0, DB::transactionLevel());
                return response('ok');
            });
        }
    }

    public function test_lock_timeout_is_reported_without_automatic_retry(): void
    {
        $calls = 0;
        try {
            app(PadronEscrituraService::class)->ejecutar(function () use (&$calls) {
                $calls++;
                DB::table('padron_prueba_escritura')->insert(['valor' => 1]);
                $cause = new \PDOException('timeout sintético');
                $cause->errorInfo = ['HY000', 1205, 'timeout sintético'];
                throw new \Illuminate\Database\QueryException('sqlite', 'select 1', [], $cause);
            });
            $this->fail('Debe informar el bloqueo.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('bloqueo concurrente', $e->getMessage());
        }
        $this->assertSame(1, $calls);
        $this->assertDatabaseCount('padron_prueba_escritura', 0);
    }

    public function test_validation_redirect_preserves_errors_but_rolls_back_sql(): void
    {
        $request = $this->request();
        $request->setLaravelSession(app('session')->driver());
        $response = app(CoordinarEscrituraPadron::class)->handle($request, function () use ($request) {
            DB::table('padron_prueba_escritura')->insert(['valor' => 1]);
            $request->session()->flash('errors', new \Illuminate\Support\ViewErrorBag);
            return redirect('/prueba');
        });
        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue($request->session()->has('errors'));
        $this->assertDatabaseCount('padron_prueba_escritura', 0);
    }

    private function declaracionFixture(): string
    {
        Schema::create('declaracion_sostenedores', function (Blueprint $t) {
            $t->id();
            foreach (['rbd', 'rut', 'nombres', 'apellido_paterno', 'apellido_materno', 'horas_contratadas',
                'educacion_parvularia', 'ensenanza_basica', 'ensenanza_media', 'nombre_funcion', 'tipo_titulo',
                'nombre_titulo', 'institucion_educacional', 'fecha_titulacion', 'pais_titulo', 'estamento',
                'funcion_catalogo_id', 'titulo_catalogo_id', 'institucion_catalogo_id'] as $field) { $t->string($field)->nullable(); }
            $t->timestamps();
        });
        $file = tempnam(sys_get_temp_dir(), 'padron_sintetico_');
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['RBD', 'RUT', 'Nombres'], [99999, '111111111', 'Persona sintética A'], [99999, '222222222', 'Persona sintética B'],
        ]);
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($file);
        $book->disconnectWorksheets();
        return $file;
    }

    public function test_real_declaration_import_uses_control_and_preserves_ids_on_update(): void
    {
        $file = $this->declaracionFixture();
        try {
            $reads = 0;
            $watch = true;
            DB::listen(function (QueryExecuted $event) use (&$reads, &$watch) {
                if ($watch && str_contains($event->sql, 'from "declaracion_sostenedores"')) {
                    $this->assertSame(1, DB::transactionLevel());
                    $reads++;
                }
            });
            $importer = new \App\Imports\SostenedoresImport;
            $this->assertSame(2, $importer->import($file)['inserted']);
            $this->assertSame(2, $importer->import($file)['updated']);
            $this->assertSame(4, $reads);
            $watch = false;
            $this->assertSame([1, 2], DB::table('declaracion_sostenedores')->orderBy('id')->pluck('id')->all());
        } finally { unlink($file); }
    }

    public function test_real_declaration_import_rolls_back_partial_batch(): void
    {
        $file = $this->declaracionFixture();
        $inserts = 0;
        DB::listen(function (QueryExecuted $event) use (&$inserts) {
            if (str_starts_with($event->sql, 'insert into "declaracion_sostenedores"') && ++$inserts === 2) {
                throw new \RuntimeException('fallo de importación sintético');
            }
        });
        try {
            (new \App\Imports\SostenedoresImport)->import($file);
            $this->fail('Debe revertir el lote.');
        } catch (\RuntimeException $e) {
            $this->assertSame('fallo de importación sintético', $e->getMessage());
            $this->assertDatabaseCount('declaracion_sostenedores', 0);
        } finally { unlink($file); }
    }

    private function establecimientosFixture(): string
    {
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('cod_estab'); $t->integer('rbd');
            foreach (['dv', 'nombre_establecimiento', 'clasificacion', 'tipo_estab', 'comuna', 'latitud', 'longitud'] as $field) { $t->string($field)->nullable(); }
            foreach (['sala_cuna', 'pre_escolar', 'basica', 'media', 'tecnico_profesional', 'adultos', 'especial', 'asignacion_zona'] as $field) { $t->integer($field)->default(0); }
            $t->timestamps();
        });
        $headers = (new \App\Services\EstablecimientoImportService)->requiredHeaders();
        $file = tempnam(sys_get_temp_dir(), 'establecimientos_sinteticos_');
        $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $book->getActiveSheet()->fromArray([
            $headers, array_fill(0, count($headers), null),
            [99999, 99999, '1', 'Escuela sintética A', 'RURAL', 'ESCUELA', 'N', 'N', 'S', 'N', 'N', 'N', 'N', 'Comuna sintética', 0, -37, -73],
            [99998, 99998, '2', 'Escuela sintética B', 'RURAL', 'ESCUELA', 'N', 'N', 'S', 'N', 'N', 'N', 'N', 'Comuna sintética', 0, -37, -73],
        ]);
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save($file);
        $book->disconnectWorksheets();
        return $file;
    }

    public function test_establishment_import_direct_service_entry_coordinates_before_lookup(): void
    {
        $file = $this->establecimientosFixture();
        try {
            $reads = 0; $watch = true;
            DB::listen(function (QueryExecuted $event) use (&$reads, &$watch) {
                if ($watch && str_contains($event->sql, 'from "establecimientos"')) {
                    $this->assertSame(1, DB::transactionLevel()); $reads++;
                }
            });
            $importer = new \App\Services\EstablecimientoImportService;
            $this->assertSame(2, $importer->importFromPath($file)['created']);
            $this->assertSame(2, $importer->importFromPath($file)['updated']);
            $this->assertSame(4, $reads); $watch = false;
            $this->assertSame([1, 2], DB::table('establecimientos')->orderBy('id')->pluck('id')->all());
        } finally { unlink($file); }
    }

    public function test_establishment_import_rolls_back_on_second_insert_failure(): void
    {
        $file = $this->establecimientosFixture(); $inserts = 0;
        DB::listen(function (QueryExecuted $event) use (&$inserts) {
            if (str_starts_with($event->sql, 'insert into "establecimientos"') && ++$inserts === 2) {
                throw new \RuntimeException('fallo sintético de establecimientos');
            }
        });
        try {
            (new \App\Services\EstablecimientoImportService)->importFromPath($file);
            $this->fail('Debe revertir el lote.');
        } catch (\RuntimeException $e) {
            $this->assertSame('fallo sintético de establecimientos', $e->getMessage());
            $this->assertDatabaseCount('establecimientos', 0);
        } finally { unlink($file); }
    }
}
