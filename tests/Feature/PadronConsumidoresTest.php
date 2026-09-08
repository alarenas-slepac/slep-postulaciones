<?php

namespace Tests\Feature;

use App\Http\Controllers\FuncionarioEstab\SolicitudReemplazoController;
use App\Models\Establecimiento;
use App\Models\ReemplazoPersonal;
use App\Models\SolicitudReemplazo;
use App\Models\User;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PadronConsumidoresTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->resetSchemaCaches();
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento');
        });
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id');
            foreach (['rut', 'nombre', 'tipocontrato', 'financiamiento', 'estatuto', 'escalafon'] as $column) {
                $t->string($column)->nullable();
            }
            foreach (['rbd', 'anio', 'mes', 'jornada', 'jornada_basica', 'jornada_media'] as $column) {
                $t->integer($column)->nullable();
            }
            $t->boolean('vigente')->default(true);
            $t->timestamps();
        });
        Schema::create('declaracion_sostenedores', function (Blueprint $t) {
            $t->id(); $t->string('rut'); $t->integer('rbd');
            $t->string('estamento'); $t->integer('horas_contratadas');
        });
        Schema::create('reemplazos_personal_bloqueos', function (Blueprint $t) {
            $t->id(); $t->integer('reemplazo_personal_id'); $t->boolean('activo');
        });
        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento sintético']);
        foreach ([
            [101, '111111111', 'CONTRATA', 'DOCENTE', true, 9],
            [102, '222222222', 'REEMPLAZO', 'DOCENTE', true, 9],
            [103, '333333333', 'SUPLENCIA', 'ASISTENTE EDUCACION', true, 9],
            [104, '444444444', 'TITULAR', 'DOCENTE', false, 9],
            [105, '555555555', 'CONTRATA', 'ASISTENTE EDUCACION', true, 9],
            [106, '222222222', 'TITULAR', 'DOCENTE', true, 8],
        ] as [$id, $rut, $tipo, $estatuto, $vigente, $mes]) {
            DB::table('reemplazos_personal')->insert([
                'id' => $id, 'rut' => $rut, 'establecimiento_id' => 1, 'rbd' => 99999,
                'nombre' => 'Persona sintética '.$id, 'tipocontrato' => $tipo,
                'estatuto' => $estatuto, 'escalafon' => $estatuto, 'financiamiento' => 'REGULAR',
                'vigente' => $vigente, 'mes' => $mes, 'anio' => 2026,
                'jornada' => 44, 'jornada_basica' => 44, 'jornada_media' => 0,
            ]);
        }
        DB::table('declaracion_sostenedores')->insert([
            'rut' => '111111111', 'rbd' => 99999, 'estamento' => 'DOCENTE', 'horas_contratadas' => 40,
        ]);
    }

    protected function tearDown(): void
    {
        $this->resetSchemaCaches();
        parent::tearDown();
    }

    private function resetSchemaCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $property) {
                (new \ReflectionProperty($class, $property))->setValue(null, []);
            }
        }
    }

    private function loginAtEstablishment(): void
    {
        $user = new User;
        $user->id = 1;
        $user->setRelation('establecimiento', Establecimiento::findOrFail(1));
        $this->actingAs($user);
    }

    public function test_dotacion_filters_contracts_without_changing_declaration_priority(): void
    {
        $establishment = Establecimiento::findOrFail(1);
        $teachers = DotacionEstablecimientoCalculator::docentes($establishment, 2026);
        $assistants = DotacionEstablecimientoCalculator::asistentes($establishment, 2026);
        $this->assertSame(['111111111'], $teachers->pluck('rut_normalizado')->all());
        $this->assertSame(40.0, $teachers->first()['horas_contrato']);
        $this->assertSame('declaracion_sostenedor', $teachers->first()['fuente_contrato']);
        $this->assertSame(['555555555'], $assistants->pluck('rut_normalizado')->all());
        $this->assertSame(44.0, $assistants->first()['horas_contrato']);
        $this->assertDatabaseCount('reemplazos_personal', 6);
    }

    public function test_titular_selector_excludes_docente_and_asistente_replacements_and_old_periods(): void
    {
        $this->loginAtEstablishment();
        $response = app(SolicitudReemplazoController::class)->ajaxFuncionarios(Request::create('/', 'GET'));
        $this->assertSame([101, 105], array_column($response->getData(true)['results'], 'id'));
        $response = app(SolicitudReemplazoController::class)->ajaxFuncionarios(Request::create('/', 'GET', ['term' => '22.222.222-2']));
        $this->assertSame([], $response->getData(true)['results']);
    }

    public function test_direct_request_cannot_use_replacement_inactive_or_old_titular(): void
    {
        $this->loginAtEstablishment();
        foreach ([102, 103, 104, 106] as $id) {
            try {
                app(SolicitudReemplazoController::class)->store(Request::create('/', 'POST', ['reemplazo_personal_id' => $id]));
                $this->fail('Debe rechazar al titular no seleccionable.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('No se admiten contratos de reemplazo o suplencia', $exception->errors()['reemplazo_personal_id'][0]);
            }
        }
    }

    public function test_historical_relationship_and_schedule_still_read_original_titular(): void
    {
        $request = new SolicitudReemplazo(['reemplazo_personal_id' => 106]);
        $this->assertSame(106, $request->funcionarioTitular->id);
        $this->assertSame(8, $request->funcionarioTitular->mes);
        $method = new \ReflectionMethod(SolicitudReemplazoController::class, 'distribucionJornadaTitular');
        $schedule = $method->invoke(app(SolicitudReemplazoController::class), 1, ReemplazoPersonal::findOrFail(106));
        $this->assertSame(44.0, $schedule[0]['total']);
        $this->assertDatabaseCount('reemplazos_personal', 6);
    }
}
