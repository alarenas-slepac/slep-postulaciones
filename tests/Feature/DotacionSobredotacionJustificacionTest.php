<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionSobredotacionJustificacionController;
use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DotacionSobredotacionJustificacionTest extends TestCase
{
    public function test_vista_ofrece_justificacion_independiente_para_ambas_calidades_en_tres_bloques(): void
    {
        $establecimiento = new Establecimiento(['nombre_establecimiento' => 'Establecimiento de prueba']);
        $establecimiento->id = 1;
        $fila = [
            'rut' => '11111111-1', 'nombre' => 'Docente de prueba', 'funcion' => 'Docente',
            'tipo_contrato' => 'PLANTA / CONTRATA', 'horas_contrato_categoria' => 10,
            'horas_sobredotacion_total' => 5, 'horas_sobredotacion_planta' => 3,
            'horas_sobredotacion_contrata' => 2,
        ];
        $bloque = ['items' => collect([$fila]), 'horas_total' => 5, 'horas_planta' => 3, 'horas_contrata' => 2];
        $registro = new DotacionSobredotacionJustificacion([
            'bloque' => 'plan_estudio', 'docente_rut_normalizado' => '111111111',
            'tipo_horas' => 'titular', 'horas_detectadas' => 3,
            'justificacion' => 'Fundamento registrado para la prueba.',
        ]);

        $html = view('admin.dotacion-establecimiento.partials._sobredotacion', [
            'sobredotacion' => [
                'aula' => ['items' => collect(), 'ajustes' => collect(), 'resumen' => []],
                'protegidos' => collect(),
                'vacantes_por_bloque' => ['plan_estudio' => $bloque, 'parvularia' => $bloque, 'pie' => $bloque],
            ],
            'sobredotacionTipo' => 'aula', 'establecimiento' => $establecimiento, 'anio' => 2027,
            'justificacionesSobredotacionTableReady' => true,
            'justificacionesSobredotacion' => collect([
                DotacionSobredotacionJustificacion::clave('plan_estudio', $fila['rut'], 'titular') => $registro,
            ]),
            'canManageJustificacionesSobredotacion' => true,
        ])->render();

        $this->assertSame(6, substr_count($html, 'name="justificacion"'));
        $this->assertSame(3, substr_count($html, 'Supresión de horas titulares'));
        $this->assertSame(3, substr_count($html, 'No renovación de horas a contrata'));
        $this->assertStringContainsString('Fundamento registrado para la prueba.', $html);
        $this->assertStringContainsString('1 pendiente(s)', $html);
        $this->assertStringContainsString('2 pendiente(s)', $html);

        $this->assertTrue($registro->vigentePara(3));
        $this->assertFalse($registro->vigentePara(4));
    }

    public function test_solo_admite_horas_calculadas_para_el_docente_bloque_y_calidad(): void
    {
        $sobredotacion = ['vacantes_por_bloque' => [
            'parvularia' => ['items' => collect([[
                'rut' => '11111111-1', 'horas_sobredotacion_planta' => 3.0,
                'horas_sobredotacion_contrata' => 2.0,
            ]])],
        ]];

        $this->assertSame(3.0, DotacionSobredotacionJustificacion::horasVacantes($sobredotacion, 'parvularia', '111111111', 'titular'));
        $this->assertSame(2.0, DotacionSobredotacionJustificacion::horasVacantes($sobredotacion, 'parvularia', '11111111-1', 'contrata'));
        $this->assertSame(0.0, DotacionSobredotacionJustificacion::horasVacantes($sobredotacion, 'pie', '11111111-1', 'contrata'));
        $this->assertSame(0.0, DotacionSobredotacionJustificacion::horasVacantes($sobredotacion, 'parvularia', '22222222-2', 'titular'));
    }

    public function test_no_permite_escribir_desde_otro_rol_o_establecimiento(): void
    {
        $establecimiento = new Establecimiento();
        $establecimiento->id = 10;
        $controller = app(DotacionSobredotacionJustificacionController::class);

        foreach ([['admin', 10], ['funcionario_directivo_estab', 11]] as [$rol, $idEstablecimiento]) {
            $usuario = new class($rol, $idEstablecimiento)
            {
                public function __construct(private string $rol, public int $establecimiento_id) {}
                public function activeRoleName(): string { return $this->rol; }
            };
            $request = Request::create('/', 'POST');
            $request->setUserResolver(fn () => $usuario);
            try {
                $controller->store($request, $establecimiento);
                $this->fail('La escritura debió ser rechazada.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }
}
