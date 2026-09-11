<?php

namespace Tests\Feature;

use App\Http\Controllers\Reemplazos\PersonalImportController;
use App\Services\Padron\PadronAplicacionService;
use App\Services\Padron\PadronConflictosAsignacionService;
use App\Services\Padron\PadronExcelReader;
use App\Services\Padron\PadronRevisionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PadronMemoriaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        DB::disableQueryLog();
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd');
        });
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('rbd');
            foreach (['rut', 'nombre', 'tipocontrato', 'estatuto', 'escalafon', 'financiamiento'] as $c) {
                $t->string($c);
            }
            foreach (['anio', 'mes', 'jornada', 'jornada_basica', 'jornada_media'] as $c) {
                $t->integer($c);
            }
            $t->boolean('vigente')->default(true);
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t) {
            $t->id(); $t->integer('anio'); $t->integer('establecimiento_id');
            $t->integer('reemplazos_personal_id')->nullable(); $t->string('docente_rut');
            $t->string('estado'); $t->integer('horas_contrato'); $t->text('observaciones');
        });
        Schema::create('declaracion_sostenedores', function (Blueprint $t) {
            $t->id(); $t->string('rut'); $t->integer('rbd'); $t->string('estamento');
            $t->integer('horas_contratadas'); $t->text('antecedentes');
        });
        (require base_path('database/migrations/2026_09_08_120000_create_padron_revisiones.php'))->up();
        (require base_path('database/migrations/2026_09_08_130000_add_padron_aplicacion_segura.php'))->up();
        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99999]);
    }

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    #[\PHPUnit\Framework\Attributes\DataProvider('assignmentHours')]
    public function test_6500_rows_with_large_dependencies_can_create_and_open_a_review_under_128_mb(int $horas): void
    {
        // Solo datos sintéticos. Ejecutar aisladamente con php -d memory_limit=128M.
        ini_set('memory_limit', '128M');
        $incoming = [];
        $payload = str_repeat('Antecedente sintético. ', 400);
        for ($start = 1; $start <= 6500; $start += 100) {
            $personas = $asignaciones = $declaraciones = [];
            for ($id = $start; $id < $start + 100; $id++) {
                $rut = (string) (10000000 + $id);
                $rut .= \App\Support\RutChile::dv((int) $rut);
                $data = ['rut' => $rut, 'nombre' => 'Persona sintética '.$id, 'rbd' => 99999,
                    'tipocontrato' => 'CONTRATA', 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE AULA',
                    'financiamiento' => 'REGULAR', 'anio' => 2026, 'mes' => 8,
                    'jornada' => 44, 'jornada_basica' => 44, 'jornada_media' => 0];
                $personas[] = ['id' => $id, 'establecimiento_id' => 1] + $data;
                $incoming[] = ['fila_excel' => $id + 1, 'observaciones' => [], 'datos' => array_replace($data, ['mes' => 9])];
                $asignaciones[] = ['id' => $id, 'anio' => 2026, 'establecimiento_id' => 1,
                    'reemplazos_personal_id' => $id, 'docente_rut' => $rut, 'estado' => 'activa',
                    'horas_contrato' => $horas, 'observaciones' => $payload];
                $declaraciones[] = ['id' => $id, 'rut' => $rut, 'rbd' => 99999,
                    'estamento' => 'DOCENTE', 'horas_contratadas' => 44, 'antecedentes' => $payload];
            }
            DB::table('reemplazos_personal')->insert($personas);
            DB::table('dotacion_docente_asignaciones')->insert($asignaciones);
            DB::table('declaracion_sostenedores')->insert($declaraciones);
        }
        unset($personas, $asignaciones, $declaraciones, $payload);
        // Se sustituye solo el lector: se ejercitan los servicios reales de BD,
        // conciliación, persistencia, huellas y preparación de pantalla.
        $reader = $this->mock(PadronExcelReader::class);
        $reader->shouldReceive('read')->once()->andReturn($incoming);
        unset($incoming);
        $revision = app(PadronRevisionService::class)->create(__FILE__, 'padron-sintetico.xlsx', 1);
        $this->assertSame([], $revision->errores);
        $this->assertSame(6500, $revision->filas()->count());
        $this->assertFalse(app(PadronRevisionService::class)->stale($revision));
        $view = app(PersonalImportController::class)->create(Request::create('/prueba', 'GET', ['revision' => $revision->id]));
        $data = $view->getData();
        $this->assertSame(0, $data['filas']->total());
        $this->assertFalse($data['mostrarFilas']);
        $this->assertSame([], $data['bloqueos']);
        $this->assertSame($horas > 44 ? 6500 : 0, $data['conflictos']['grupos_avisos']);
        $this->assertSame(0, $data['conflictos']['grupos_bloqueantes']);
        $this->assertFalse($data['obsoleta']);
        $this->assertFalse(app(PadronAplicacionService::class)->disponible());
        $this->assertSame(0, DB::table('padron_personal_cambios')->count());
        // Render real con usuario administrador y el composer web: antes solo
        // se preparaba la vista y se omitía precisamente el modal que agotaba memoria.
        $user = \Mockery::mock(\App\Models\User::class)->makePartial();
        $user->forceFill(['id' => 999, 'nombres' => 'Administrador sintético']);
        $user->shouldReceive('hasRole')->with('admin')->andReturnTrue();
        $user->shouldReceive('activeRoleName')->andReturn('admin');
        $user->shouldReceive('availableRoleContexts')->andReturn(collect(['admin']));
        $user->shouldReceive('canModule')->andReturnFalse();
        $this->actingAs($user);
        $this->app->getProvider(\App\Providers\AppServiceProvider::class)->registerChangeLogViews();
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $html = $view->render();
        $this->assertStringContainsString('id="changeLogModal"', $html);
        $this->assertStringContainsString('data-history-url=', $html);
        $this->assertStringNotContainsString('id="changeLogHistoryAccordion"', $html);
        $this->assertLessThan(512 * 1024, strlen($html));
        unset($html);
        unset($view, $data);
        $response = app(PersonalImportController::class)->create(Request::create('/prueba', 'GET', [
            'revision' => $revision->id, 'q' => $rut, 'solo_filas' => 1,
        ]));
        $this->assertSame('1', $response->headers->get('X-Padron-Filas'));
        $this->assertStringContainsString('Persona sintética 6500', $response->getContent());
        $this->assertLessThan(128 * 1024 * 1024, memory_get_peak_usage(true));
    }

    public static function assignmentHours(): array
    {
        return ['sin conflictos' => [20], '6500 excesos preexistentes' => [45]];
    }

    public function test_trimmed_columns_still_change_hash_and_year_state_filters_are_preserved(): void
    {
        $service = app(PadronConflictosAsignacionService::class);
        $empty = $service->snapshot(2026)['hash'];
        DB::table('dotacion_docente_asignaciones')->insert([
            'id' => 1, 'anio' => 2026, 'establecimiento_id' => 1, 'docente_rut' => '111111111',
            'estado' => 'activa', 'horas_contrato' => 20, 'observaciones' => 'Antes',
        ]);
        $first = $service->snapshot(2026);
        $this->assertNotSame($empty, $first['hash']);
        $this->assertArrayNotHasKey('observaciones', $first['asignaciones'][0]);
        $this->assertSame($first['hash'], $service->snapshot(2026)['hash']);
        DB::table('dotacion_docente_asignaciones')->where('id', 1)->update(['observaciones' => 'Después']);
        $this->assertNotSame($first['hash'], $service->snapshot(2026)['hash']);
        $this->assertSame([], $service->snapshot(2027)['asignaciones']);
        DB::table('dotacion_docente_asignaciones')->where('id', 1)->update(['estado' => 'inactiva']);
        $this->assertSame($empty, $service->snapshot(2026)['hash']);
        DB::table('declaracion_sostenedores')->insert(['id' => 1, 'rut' => '111111111', 'rbd' => 99999,
            'estamento' => 'DOCENTE', 'horas_contratadas' => 44, 'antecedentes' => 'Antes']);
        $first = $service->snapshot(2026);
        $this->assertArrayNotHasKey('antecedentes', $first['declaraciones'][0]);
        DB::table('declaracion_sostenedores')->where('id', 1)->update(['antecedentes' => 'Después']);
        $this->assertNotSame($first['hash'], $service->snapshot(2026)['hash']);
    }
}
