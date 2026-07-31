<?php

namespace Tests\Feature;

use App\Http\Controllers\Certificados\CertificadoLaboralController;
use App\Models\CertificadoEmitido;
use App\Models\User;
use App\Services\Certificados\CertificadoVigenciaLaboralService;
use App\Services\Certificados\CertificadoVigenciaPdfService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class CertificadoLaboralControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->crearTablas();
        config(['certificados.roles_emision_general' => ['admin']]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('certificados_emitidos');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_emitir_vuelve_al_listado_sin_abrir_el_pdf(): void
    {
        $usuario = Mockery::mock(User::class)->makePartial();
        $usuario->id = 15;
        $usuario->shouldReceive('activeRoleName')->andReturn('admin');

        $resultado = [
            'rut_normalizado' => '123456785',
            'nombre' => 'FUNCIONARIA DE PRUEBA',
            'fecha_antiguedad' => CarbonImmutable::parse('2023-04-11'),
            'calidad_juridica' => 'CONTRATA',
            'regimen_juridico' => 'ESTATUTO DOCENTE',
            'establecimientos' => [
                [
                    'establecimiento' => 'ESCUELA DE PRUEBA',
                    'comuna' => 'COMUNA DE PRUEBA',
                ],
            ],
            'contratos' => [
                [
                    'fecha_ingreso' => '2023-04-11',
                    'calidad_juridica' => 'CONTRATA',
                    'regimen_juridico' => 'ESTATUTO DOCENTE',
                ],
            ],
            'es_funcionario_ac' => true,
            'importacion' => (object) ['id' => 8],
        ];

        $vigenciaService = Mockery::mock(CertificadoVigenciaLaboralService::class);
        $vigenciaService
            ->shouldReceive('resolver')
            ->once()
            ->with('123456785')
            ->andReturn($resultado);

        $pdfService = Mockery::mock(CertificadoVigenciaPdfService::class);
        $pdfService
            ->shouldReceive('generar')
            ->once()
            ->andReturnUsing(function (CertificadoEmitido $certificado) {
                $certificado->update([
                    'archivo_pdf_path' => 'certificados/vigencia/prueba.pdf',
                    'documento_hash' => str_repeat('a', 64),
                ]);

                return $certificado->fresh();
            });

        $request = Request::create(
            '/certificados/emitir',
            'POST',
            [
                'rut' => '123456785',
                'fecha_antiguedad' => '2024-01-15',
                'calidad_juridica' => 'PLAZO FIJO',
                'regimen_juridico' => 'CÓDIGO DEL TRABAJO',
            ]
        );
        $request->setUserResolver(fn () => $usuario);

        $controller = new CertificadoLaboralController(
            $vigenciaService,
            $pdfService
        );
        $response = $controller->emitir($request);

        self::assertSame(
            route('certificados.index', ['rut' => '123456785'])
                .'#certificados-emitidos',
            $response->getTargetUrl()
        );
        self::assertStringNotContainsString(
            '/certificados/1',
            $response->getTargetUrl()
        );
        self::assertDatabaseHas('certificados_emitidos', [
            'rut_normalizado' => '123456785',
            'calidad_juridica_snapshot' => 'PLAZO FIJO',
            'regimen_juridico_snapshot' => 'CÓDIGO DEL TRABAJO',
            'es_funcionario_ac_snapshot' => true,
            'estado' => 'vigente',
            'archivo_pdf_path' => 'certificados/vigencia/prueba.pdf',
        ]);

        $certificado = CertificadoEmitido::query()->firstOrFail();
        self::assertSame(
            '2024-01-15',
            $certificado->fecha_antiguedad->format('Y-m-d')
        );
        self::assertSame(
            'CONTRATA',
            $certificado->contratos_snapshot[0]['calidad_juridica']
        );
        self::assertSame(
            'ESTATUTO DOCENTE',
            $certificado->contratos_snapshot[0]['regimen_juridico']
        );
    }

    public function test_funcionario_ac_emite_su_certificado_sin_poder_ajustarlo(): void
    {
        $usuario = Mockery::mock(User::class)->makePartial();
        $usuario->id = 16;
        $usuario->rut = '123456785';
        $usuario->shouldReceive('activeRoleName')->andReturn('funcionario_ac');

        $resultado = [
            'rut_normalizado' => '123456785',
            'nombre' => 'FUNCIONARIA DE PRUEBA',
            'fecha_antiguedad' => CarbonImmutable::parse('2023-04-11'),
            'calidad_juridica' => 'CONTRATA',
            'regimen_juridico' => 'ESTATUTO DOCENTE',
            'establecimientos' => [
                [
                    'establecimiento' => 'ESCUELA DE PRUEBA',
                    'comuna' => 'COMUNA DE PRUEBA',
                ],
            ],
            'contratos' => [],
            'es_funcionario_ac' => true,
            'importacion' => (object) ['id' => 8],
        ];

        $vigenciaService = Mockery::mock(CertificadoVigenciaLaboralService::class);
        $vigenciaService
            ->shouldReceive('resolver')
            ->once()
            ->with('123456785')
            ->andReturn($resultado);

        $pdfService = Mockery::mock(CertificadoVigenciaPdfService::class);
        $pdfService
            ->shouldReceive('generar')
            ->once()
            ->andReturnUsing(function (CertificadoEmitido $certificado) {
                return $certificado->fresh();
            });

        $request = Request::create(
            '/certificados/emitir',
            'POST',
            [
                'fecha_antiguedad' => '2024-01-15',
                'calidad_juridica' => 'PLAZO FIJO',
                'regimen_juridico' => 'CÓDIGO DEL TRABAJO',
            ]
        );
        $request->setUserResolver(fn () => $usuario);

        $controller = new CertificadoLaboralController(
            $vigenciaService,
            $pdfService
        );
        $controller->emitir($request);

        $certificado = CertificadoEmitido::query()->firstOrFail();
        self::assertSame(
            '2023-04-11',
            $certificado->fecha_antiguedad->format('Y-m-d')
        );
        self::assertSame('CONTRATA', $certificado->calidad_juridica_snapshot);
        self::assertSame(
            'ESTATUTO DOCENTE',
            $certificado->regimen_juridico_snapshot
        );
    }

    private function crearTablas(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('rut')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('certificados_emitidos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo');
            $table->string('numero')->nullable();
            $table->char('codigo_validacion', 32);
            $table->string('rut_normalizado');
            $table->string('nombre_snapshot');
            $table->date('fecha_antiguedad');
            $table->string('calidad_juridica_snapshot')->nullable();
            $table->string('regimen_juridico_snapshot');
            $table->json('establecimientos_snapshot');
            $table->json('contratos_snapshot');
            $table->boolean('es_funcionario_ac_snapshot')->default(false);
            $table->unsignedBigInteger('importacion_id')->nullable();
            $table->unsignedBigInteger('usuario_beneficiario_id')->nullable();
            $table->unsignedBigInteger('emitido_por_user_id')->nullable();
            $table->string('rol_emisor')->nullable();
            $table->string('estado');
            $table->string('archivo_pdf_path')->nullable();
            $table->char('documento_hash', 64)->nullable();
            $table->timestamp('emitido_at');
            $table->timestamp('anulado_at')->nullable();
            $table->unsignedBigInteger('anulado_por_user_id')->nullable();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();
        });
    }
}
