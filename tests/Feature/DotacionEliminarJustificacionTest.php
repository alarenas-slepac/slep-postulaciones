<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionSobredotacionJustificacionController;
use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionEliminarJustificacionTest extends IsolatedSecurityTestCase
{
    private Establecimiento $ee;

    private DotacionSobredotacionJustificacion $registro;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('modules', function (Blueprint $table): void {
            $table->id();
            $table->string('key');
        });
        DB::table('modules')->insert(['key' => 'admin.dotacion-establecimiento']);
        foreach (['2026_01_23_000001_create_establecimientos_table.php',
            '2026_09_29_190000_create_dotacion_sobredotacion_justificaciones_table.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->ee = Establecimiento::create(['rbd' => 99999, 'cod_estab' => 'SINTETICO', 'nombre_establecimiento' => 'EE sintético']);
        $this->registro = $this->justificar();
    }

    public function test_eliminar_ultima_justificacion_reincorpora_al_docente_con_25_h_al_selector_pae(): void
    {
        $this->withoutExceptionHandling();
        $this->assertSame(0, $this->opcionesPae());
        $this->actingAs($this->testUser(1, 3))->withSession(['active_role' => 'admin']);
        $this->delete(route('admin.dotacion-establecimiento.sobredotacion.justificaciones.destroy', [$this->ee, $this->registro->id]), ['anio' => 2027])
            ->assertRedirect(route('admin.dotacion-establecimiento.show', [$this->ee, 'anio' => 2027, 'tab' => 'sobredotacion']).'#vacantes-plan_estudio')
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('dotacion_sobredotacion_justificaciones', ['id' => $this->registro->id]);
        $this->assertSame([], DotacionSobredotacionJustificacion::rutsJustificados($this->ee->id, 2027));
        $this->assertSame(1, $this->opcionesPae());
    }

    public function test_elimina_solo_el_registro_elegido_y_avisa_si_quedan_otras_justificaciones(): void
    {
        $otra = $this->justificar(['bloque' => 'pie']);
        $response = $this->eliminar();
        $this->assertNull($this->registro->fresh());
        $this->assertNotNull($otra->fresh());
        $this->assertStringContainsString('otras justificaciones', $response->getSession()->get('success'));
        $this->assertSame(0, $this->opcionesPae());
    }

    public function test_puede_eliminar_una_justificacion_desactualizada_sin_recalcular_saldos(): void
    {
        $this->registro->update(['horas_detectadas' => 35]);
        $this->assertFalse($this->registro->vigentePara(25));
        $this->eliminar();
        $this->assertNull($this->registro->fresh());
        $this->assertSame(1, $this->opcionesPae());
    }

    public static function rolesAutorizados(): array
    {
        return [['admin'], ['supervisor_plani'], ['funcionario_directivo_estab']];
    }

    #[DataProvider('rolesAutorizados')]
    public function test_permite_eliminar_a_los_mismos_perfiles_que_guardan(string $rol): void
    {
        $this->eliminar($rol);
        $this->assertNull($this->registro->fresh());
    }

    public static function rolesRestringidos(): array
    {
        return [['coordinador_uatp'], ['coordinador_gdp'], ['funcionario_ac'], ['postulante']];
    }

    #[DataProvider('rolesRestringidos')]
    public function test_perfiles_de_consulta_no_pueden_eliminar(string $rol): void
    {
        try {
            $this->eliminar($rol);
            $this->fail('El perfil de consulta no puede eliminar justificaciones.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertNotNull($this->registro->fresh());
    }

    public function test_directivo_de_otro_establecimiento_no_puede_eliminar(): void
    {
        try {
            $this->eliminar('funcionario_directivo_estab', $this->ee->id + 1);
            $this->fail('El directivo no puede administrar justificaciones de otro establecimiento.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertNotNull($this->registro->fresh());
    }

    public function test_no_elimina_registros_de_otro_anio_ni_de_otro_establecimiento(): void
    {
        $otraEe = Establecimiento::create(['rbd' => 99998, 'cod_estab' => 'OTRO', 'nombre_establecimiento' => 'Otro EE sintético']);
        foreach (['anio' => 2026, 'establecimiento_id' => $otraEe->id] as $campo => $valor) {
            $this->registro->update([$campo => $valor]);
            $this->actingAs($this->httpUser());
            $this->withSession(['active_role' => 'admin']);
            $this->delete(route('admin.dotacion-establecimiento.sobredotacion.justificaciones.destroy', [$this->ee, $this->registro->id]), ['anio' => 2027])->assertNotFound();
            $this->assertNotNull($this->registro->fresh());
            $this->registro->update(['anio' => 2027, 'establecimiento_id' => $this->ee->id]);
        }
    }

    public function test_sin_anio_el_formulario_no_borra_y_get_no_es_una_operacion_de_eliminacion(): void
    {
        $this->actingAs($this->testUser(1, 3))->withSession(['active_role' => 'admin']);
        $url = route('admin.dotacion-establecimiento.sobredotacion.justificaciones.destroy', [$this->ee, $this->registro->id]);
        $this->delete($url)->assertRedirect()->assertSessionHasErrors('anio');
        $this->get($url)->assertNotFound();
        $this->assertSame(['DELETE'], app('router')->getRoutes()->getByName('admin.dotacion-establecimiento.sobredotacion.justificaciones.destroy')->methods());
        $this->assertNotNull($this->registro->fresh());
    }

    public function test_boton_visible_para_registros_guardados_y_oculto_en_consulta(): void
    {
        $data = $this->datosVista();
        $xpath = $this->xpath(view('admin.dotacion-establecimiento.partials._sobredotacion', $data)->render());
        $forms = $xpath->query('//form[input[@name="_method" and @value="DELETE"]]');
        $this->assertSame(1, $forms->length);
        $this->assertSame('2027', $xpath->query('.//input[@name="anio"]', $forms->item(0))->item(0)->getAttribute('value'));
        $this->assertSame(1, $xpath->query('.//input[@name="_token"]', $forms->item(0))->length);
        $this->assertNotEmpty($forms->item(0)->getAttribute('onsubmit'));
        $this->assertStringContainsString('Eliminar justificación', $forms->item(0)->textContent);
        $data['canManageJustificacionesSobredotacion'] = false;
        $readonly = $this->xpath(view('admin.dotacion-establecimiento.partials._sobredotacion', $data)->render());
        $this->assertSame(0, $readonly->query('//form[input[@name="_method" and @value="DELETE"]]')->length);
    }

    public function test_registro_de_un_bloque_que_ya_no_tiene_saldo_tambien_se_puede_eliminar(): void
    {
        $data = $this->datosVista();
        $data['sobredotacion']['vacantes_por_bloque'] = [];
        $html = view('admin.dotacion-establecimiento.partials._sobredotacion', $data)->render();
        $this->assertStringContainsString('Justificaciones fuera de la nómina de saldos', $html);
        $this->assertSame(1, $this->xpath($html)->query('//form[input[@name="_method" and @value="DELETE"]]')->length);
    }

    private function justificar(array $overrides = []): DotacionSobredotacionJustificacion
    {
        return DotacionSobredotacionJustificacion::create(array_merge(['establecimiento_id' => $this->ee->id,
            'anio' => 2027, 'bloque' => 'plan_estudio', 'tipo_horas' => 'titular', 'docente_rut_normalizado' => '99000001K',
            'horas_detectadas' => 25, 'justificacion' => 'Fundamento sintético guardado para esta prueba.'], $overrides));
    }

    private function eliminar(string $rol = 'admin', ?int $eeUsuario = null): \Illuminate\Http\RedirectResponse
    {
        $user = new class($rol, $eeUsuario ?? $this->ee->id)
        {
            public function __construct(private string $rol, public int $establecimiento_id) {}

            public function activeRoleName(): string
            {
                return $this->rol;
            }
        };
        $request = Request::create('/', 'DELETE', ['anio' => 2027]);
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession($this->app['session.store']);

        return app(DotacionSobredotacionJustificacionController::class)->destroy($request, $this->ee, $this->registro->id);
    }

    private function httpUser(): \App\Models\User
    {
        return \App\Models\User::first() ?? $this->testUser(1, 3);
    }

    private function datosVista(): array
    {
        $fila = ['rut' => '99000001-K', 'nombre' => 'Docente sintético', 'funcion' => 'Docente', 'tipo_contrato' => 'PLANTA',
            'horas_contrato_categoria' => 35, 'horas_sobredotacion_total' => 25, 'horas_sobredotacion_planta' => 25, 'horas_sobredotacion_contrata' => 0];

        return ['establecimiento' => $this->ee, 'anio' => 2027, 'errors' => new ViewErrorBag,
            'justificacionesSobredotacionTableReady' => true, 'canManageJustificacionesSobredotacion' => true,
            'justificacionesSobredotacion' => collect([DotacionSobredotacionJustificacion::clave('plan_estudio', $fila['rut'], 'titular') => $this->registro]),
            'sobredotacion' => ['aula' => [], 'protegidos' => collect(), 'vacantes_por_bloque' => ['plan_estudio' => ['items' => collect([$fila]), 'horas_total' => 25, 'horas_planta' => 25, 'horas_contrata' => 0]]]];
    }

    private function opcionesPae(): int
    {
        $docente = ['rut' => '99000001-K', 'rut_normalizado' => '99000001K', 'nombre' => 'Docente sintético',
            'titulo' => 'Pedagogía en Educación Física', 'horas_contrato' => 35, 'horas_asignadas_total' => 10,
            'horas_titulares_disponibles' => 25, 'horas_contrata_disponibles' => 0,
            'sobredotacion_justificada' => in_array('99000001K', DotacionSobredotacionJustificacion::rutsJustificados($this->ee->id, 2027), true)];
        $need = ['key' => 'pae-sintetico', 'tipo_asignacion' => 'plan_normativo', 'subtipo_asignacion' => 'planes_programas',
            'titulo' => 'Programa de Alimentación Escolar (PAE)', 'curso_label' => 'Planes', 'horas_plan_requeridas' => null,
            'horas_contrato_requeridas' => 6, 'horas_contrato_asignadas' => 0, 'horas_contrato_pendientes' => 6,
            'asignaciones' => collect()];
        $data = ['establecimiento' => $this->ee, 'anio' => 2027, 'errors' => new ViewErrorBag,
            'docentes' => collect([$docente]), 'asignacion' => ['docentes' => collect([$docente]), 'necesidades' => ['funciones' => collect([$need])]],
            'proceso2027' => ['aplica' => true, 'asignacion_habilitada' => true, 'docentes' => collect([$docente])]];
        $xpath = $this->xpath(view('admin.dotacion-establecimiento.partials._asignacion', $data)->render());
        $form = $xpath->query('//form[input[@name="necesidad_key" and @value="pae-sintetico"]]')->item(0);
        $this->assertNotNull($form);
        $count = 0;
        foreach ($xpath->query('.//optgroup[@data-personal-catalogo]', $form) as $reference) {
            $count += $xpath->query('//template[@id="'.$reference->getAttribute('data-personal-catalogo').'"]//option[@value="99000001-K"]')->length;
        }

        return $count;
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($dom);
    }
}
