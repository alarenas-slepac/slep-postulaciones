<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionDocenteExclusionController;
use App\Models\DotacionDocenteExclusion;
use App\Models\Establecimiento;
use App\Models\User;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionProceso2027Calculator;
use App\Support\DotacionSituacionesAnuales;
use App\Support\DotacionSobredotacionCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionLactanciaFueroMaternalTest extends IsolatedSecurityTestCase
{
    private const MIGRACION = '2026_10_08_160000_add_fuero_maternal_to_dotacion_docente_exclusiones.php';

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetSchemaCaches();
        $this->app['request']->setLaravelSession($this->app['session.store']);
        DB::table('roles')->insert([
            ['id' => 4, 'name' => 'funcionario_directivo_estab', 'guard_name' => 'web'],
            ['id' => 5, 'name' => 'coordinador_uatp', 'guard_name' => 'web'],
            ['id' => 6, 'name' => 'coordinador_gdp', 'guard_name' => 'web'],
            ['id' => 7, 'name' => 'supervisor_plani', 'guard_name' => 'web'],
        ]);
        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id(); $table->integer('rbd'); $table->string('nombre_establecimiento');
            $table->boolean('sala_cuna')->default(false); $table->timestamps();
        });
        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => 99001, 'nombre_establecimiento' => 'Establecimiento de prueba']);
        foreach ([
            '2026_08_24_090000_create_dotacion_docente_exclusiones_table.php',
            '2026_09_14_160000_add_continuidad_to_dotacion_docente_exclusiones.php',
            '2026_09_14_170000_add_conservar_horas_to_dotacion_docente_exclusiones.php',
            self::MIGRACION,
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::create('reemplazos_personal', function (Blueprint $table): void {
            $table->id(); $table->integer('establecimiento_id'); $table->string('rut'); $table->string('nombre');
            $table->integer('anio'); $table->integer('mes'); $table->decimal('jornada', 8, 2);
            $table->decimal('jornada_basica', 8, 2)->nullable(); $table->decimal('jornada_media', 8, 2)->nullable();
            foreach (['tipocontrato', 'financiamiento', 'estatuto', 'escalafon', 'row_hash'] as $campo) {
                $table->string($campo)->nullable();
            }
            $table->timestamps();
        });
        DB::table('reemplazos_personal')->insert([
            'establecimiento_id' => 1, 'rut' => '99000001K', 'nombre' => 'Docente de prueba',
            'anio' => 2026, 'mes' => 8, 'jornada' => 44, 'tipocontrato' => 'PLANTA',
            'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => 'prueba-lactancia',
        ]);
    }

    protected function tearDown(): void
    {
        $this->resetSchemaCaches();
        parent::tearDown();
    }

    public static function prelaciones(): array
    {
        return [
            'lactancia titular avanzado' => [44, 'Avanzado', false, 2],
            'lactancia titular inicial' => [44, 'Inicial', false, 3],
            'lactancia contrata' => [0, 'Experto 2', false, 4],
            'fuero titular avanzado' => [44, 'Avanzado', true, 1],
            'fuero titular inicial' => [44, 'Inicial', true, 1],
            'fuero contrata' => [0, 'Experto 2', true, 1],
        ];
    }

    #[DataProvider('prelaciones')]
    public function test_lactancia_solo_otorga_prioridad_por_fuero_explicito(float $planta, string $tramo, bool $fuero, int $prioridad): void
    {
        $docente = $this->docente(['horas_planta' => $planta, 'horas_contrata' => 44 - $planta, 'tramo' => $tramo]);
        $docente['exclusion_docente']['posee_fuero_maternal'] = $fuero;
        $priorizado = DotacionProceso2027Calculator::docentesPriorizados(collect([$docente]))->sole();
        $this->assertSame($prioridad, $priorizado['prioridad_2027']);
        $this->assertSame(38.0, $priorizado['horas_disponibles']);
        $this->assertSame('horas_lactancia', $priorizado['exclusion_docente']['motivo']);
    }

    public function test_lactancia_contrata_no_bloquea_titular_y_no_se_presume_fuero_en_datos_historicos(): void
    {
        $lactancia = $this->docente(['rut_normalizado' => '99000002K', 'horas_planta' => 0, 'horas_contrata' => 44]);
        unset($lactancia['exclusion_docente']['posee_fuero_maternal']);
        $titular = $this->docente(['exclusion_docente' => null]);
        $priorizados = DotacionProceso2027Calculator::docentesPriorizados(collect([$lactancia, $titular]));
        $this->assertSame([2, 4], $priorizados->pluck('prioridad_2027')->all());
        $this->assertFalse(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible($priorizados, $priorizados->first(), 2));
        $lactancia['exclusion_docente']['posee_fuero_maternal'] = true;
        $conFuero = DotacionProceso2027Calculator::docentesPriorizados(collect([$lactancia, $titular]));
        $this->assertSame([1, 2], $conFuero->pluck('prioridad_2027')->all());
        $this->assertTrue(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible($conFuero, $conFuero->last(), 2));
        $this->assertFalse(DotacionDocenteExclusion::tieneFueroMaternal(['motivo' => 'traslado', 'posee_fuero_maternal' => true]));
        $this->assertTrue(DotacionDocenteExclusion::tieneFueroMaternal(['motivo' => 'fuero_maternal']));
    }

    public static function rolesGestion(): array
    {
        return [[3, 'admin'], [5, 'coordinador_uatp'], [6, 'coordinador_gdp'], [7, 'supervisor_plani']];
    }

    #[DataProvider('rolesGestion')]
    public function test_gestion_puede_marcar_y_desmarcar_fuero_sin_perder_lactancia_ni_cambiar_horas(int $roleId, string $role): void
    {
        $user = $this->testUser(1, $roleId);
        session(['active_role' => $role]);
        $request = $this->request($user);
        $controller = app(DotacionDocenteExclusionController::class);
        $ee = Establecimiento::findOrFail(1);
        $this->assertTrue($controller->store($request, $ee)->isRedirect());
        $this->assertTrue(DotacionDocenteExclusion::query()->sole()->posee_fuero_maternal);
        $docente = DotacionEstablecimientoCalculator::docentes($ee, 2027)->sole();
        $this->assertSame(42.0, $docente['horas_contrato']);
        $this->assertTrue($docente['exclusion_docente']['posee_fuero_maternal']);
        $this->assertSame(1, DotacionProceso2027Calculator::docentesPriorizados(collect([$docente]))->sole()['prioridad_2027']);
        $this->assertDatabaseHas('dotacion_docente_exclusiones', ['motivo' => 'horas_lactancia', 'horas' => 2]);
        // Un formulario anterior que no envía la bandera conserva la decisión.
        $controller->store($this->request($user, [], false), $ee);
        $this->assertTrue(DotacionDocenteExclusion::query()->sole()->posee_fuero_maternal);
        $request->merge(['posee_fuero_maternal' => 0]);
        $controller->store($request, $ee);
        $this->assertFalse(DotacionDocenteExclusion::query()->sole()->posee_fuero_maternal);
        $docente = DotacionEstablecimientoCalculator::docentes($ee, 2027)->sole();
        $this->assertSame(42.0, $docente['horas_contrato']);
        $this->assertSame(3, DotacionProceso2027Calculator::docentesPriorizados(collect([$docente]))->sole()['prioridad_2027']);
    }

    public function test_al_cambiar_motivo_se_limpia_fuero_y_no_se_reactiva_al_volver_a_lactancia(): void
    {
        $user = $this->testUser(1, 3);
        $controller = app(DotacionDocenteExclusionController::class);
        $ee = Establecimiento::findOrFail(1);
        $controller->store($this->request($user), $ee);
        $controller->store($this->request($user, ['motivo' => 'traslado'], false), $ee);
        $this->assertFalse(DotacionDocenteExclusion::query()->sole()->posee_fuero_maternal);
        $controller->store($this->request($user, [], false), $ee);
        $this->assertFalse(DotacionDocenteExclusion::query()->sole()->posee_fuero_maternal);
    }

    public static function banderasInvalidas(): array
    {
        return [['horas_lactancia', 'sí'], ['traslado', 1], ['fuero_maternal', 1]];
    }

    #[DataProvider('banderasInvalidas')]
    public function test_rechaza_bandera_invalida_o_fuero_adicional_fuera_de_lactancia(string $motivo, mixed $bandera): void
    {
        $user = $this->testUser(1, 3);
        try {
            app(DotacionDocenteExclusionController::class)->store($this->request($user, [
                'motivo' => $motivo, 'posee_fuero_maternal' => $bandera,
            ]), Establecimiento::findOrFail(1));
            $this->fail('Se esperaba rechazar la bandera.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('posee_fuero_maternal', $exception->errors());
        }
        $this->assertDatabaseCount('dotacion_docente_exclusiones', 0);
    }

    public function test_directivo_no_puede_marcar_fuero_y_su_consulta_es_solo_lectura(): void
    {
        $situacion = DotacionDocenteExclusion::create($this->situacion(['anio' => 2027]));
        $user = $this->testUser(1, 4);
        session(['active_role' => 'funcionario_directivo_estab']);
        try {
            app(DotacionDocenteExclusionController::class)->store($this->request($user), Establecimiento::findOrFail(1));
            $this->fail('Se esperaba rechazar al directivo.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertTrue($situacion->fresh()->posee_fuero_maternal);
        $html = $this->vista('funcionario_directivo_estab', $situacion);
        $this->assertStringContainsString('Fuero maternal: <strong>Sí</strong>', $html);
        $this->assertStringNotContainsString('<form', $html);
        $this->assertStringNotContainsString('name="posee_fuero_maternal"', $html);
    }

    public function test_vista_ofrece_checkbox_en_lactancia_y_consulta_ambos_anios(): void
    {
        $situacion = DotacionDocenteExclusion::create($this->situacion(['anio' => 2027]));
        $html = $this->vista('admin', $situacion);
        $this->assertStringContainsString('Posee fuero maternal', $html);
        $this->assertSame(2, substr_count($html, 'Fuero maternal: <strong>Sí</strong>'));
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//input[@type="checkbox" and @name="posee_fuero_maternal" and @checked and not(@disabled)]')->length);
        $this->assertSame(0, $xpath->query('//div[@data-lactancia-fuero and contains(@class,"d-none")]')->length);
    }

    public function test_migracion_no_infiere_fuero_historico_y_es_reintentable(): void
    {
        Schema::table('dotacion_docente_exclusiones', fn (Blueprint $table) => $table->dropColumn('posee_fuero_maternal'));
        $datos = $this->situacion();
        unset($datos['posee_fuero_maternal']);
        $id = DB::table('dotacion_docente_exclusiones')->insertGetId($datos);
        foreach ([1, 2] as $intento) {
            (require database_path('migrations/'.self::MIGRACION))->up();
        }
        $situacion = DotacionDocenteExclusion::findOrFail($id);
        $this->assertFalse($situacion->posee_fuero_maternal);
        $this->assertSame('horas_lactancia', $situacion->motivo);
        $this->assertSame('2.00', $situacion->horas);
        $this->assertDatabaseCount('dotacion_docente_exclusiones', 1);
    }

    public function test_sin_migracion_admite_formulario_antiguo_y_rechaza_nueva_bandera_sin_perder_situacion(): void
    {
        Schema::table('dotacion_docente_exclusiones', fn (Blueprint $table) => $table->dropColumn('posee_fuero_maternal'));
        $user = $this->testUser(1, 3);
        $ee = Establecimiento::findOrFail(1);
        $controller = app(DotacionDocenteExclusionController::class);
        $controller->store($this->request($user, [], false), $ee);
        $antes = DB::table('dotacion_docente_exclusiones')->sole();
        try {
            $controller->store($this->request($user), $ee);
            $this->fail('Se esperaba exigir la migración.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('posee_fuero_maternal', $exception->errors());
        }
        $this->assertEquals($antes, DB::table('dotacion_docente_exclusiones')->sole());
        $this->assertFalse(DotacionEstablecimientoCalculator::docentes($ee, 2027)->sole()['exclusion_docente']['posee_fuero_maternal']);
    }

    public function test_copia_fuero_al_anio_siguiente_sin_sobrescribir_decision_del_destino(): void
    {
        (require database_path('migrations/2026_10_05_210000_create_dotacion_situacion_traspasos_table.php'))->up();
        DotacionDocenteExclusion::create($this->situacion());
        $destino = DotacionDocenteExclusion::query()->where('anio', 2027)->sole();
        $this->assertTrue($destino->posee_fuero_maternal);
        $this->assertSame('horas_lactancia', $destino->motivo);
        $this->assertSame('2.00', $destino->horas);
        DB::table('dotacion_docente_exclusiones')->where('id', $destino->id)->update(['posee_fuero_maternal' => false]);
        app(DotacionSituacionesAnuales::class)->copiarAlAnioSiguiente(1, 2026);
        $this->assertFalse($destino->fresh()->posee_fuero_maternal);
        $this->assertDatabaseCount('dotacion_docente_exclusiones', 2);
    }

    public function test_fuero_explicito_protege_en_sobredotacion_y_lactancia_sola_no(): void
    {
        $docente = $this->docente();
        $sinFuero = DotacionSobredotacionCalculator::build([$docente], []);
        $this->assertTrue($sinFuero['protegidos']->isEmpty());
        $this->assertCount(1, $sinFuero['aula']['items']);
        $docente['exclusion_docente']['posee_fuero_maternal'] = true;
        $conFuero = DotacionSobredotacionCalculator::build([$docente], []);
        $this->assertTrue($conFuero['aula']['items']->isEmpty());
        $this->assertSame('Fuero maternal', $conFuero['protegidos']->sole()['motivo_proteccion']);
        $this->assertSame($sinFuero['aula']['resumen']['horas_dotacion_total'], $conFuero['aula']['resumen']['horas_dotacion_total']);
        $this->assertSame($sinFuero['aula']['resumen']['horas_asignadas_total'], $conFuero['aula']['resumen']['horas_asignadas_total']);
    }

    private function request(User $user, array $cambios = [], bool $incluirFuero = true): Request
    {
        $datos = array_replace(['anio' => 2027, 'docente_rut' => '99.000.001-k',
            'motivo' => 'horas_lactancia', 'horas_necesarias' => 42, 'horas' => 2], $cambios);
        if ($incluirFuero && ! array_key_exists('posee_fuero_maternal', $datos)) {
            $datos['posee_fuero_maternal'] = 1;
        }
        $request = Request::create('/situacion', 'POST', $datos);
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    private function situacion(array $cambios = []): array
    {
        return array_replace(['establecimiento_id' => 1, 'anio' => 2026, 'docente_rut' => '99.000.001-k',
            'docente_rut_normalizado' => '99000001K', 'docente_nombre' => 'Docente de prueba',
            'motivo' => 'horas_lactancia', 'horas' => 2, 'posee_fuero_maternal' => true,
            'considerar_dotacion_siguiente' => true, 'conservar_horas_necesarias' => true], $cambios);
    }

    private function docente(array $cambios = []): array
    {
        return array_replace(['rut' => '99000001-K', 'rut_normalizado' => '99000001K', 'nombre' => 'Docente de prueba',
            'titulo' => 'Pedagogía en Educación Básica', 'funcion' => 'DOCENTE', 'estamento' => 'DOCENTE',
            'horas_contrato' => 44, 'horas_contrato_base' => 44, 'horas_planta' => 44, 'horas_contrata' => 0,
            'horas_asignadas_total' => 6, 'horas_aula' => 0, 'diferencia' => 38, 'tipo_contrato' => 'PLANTA',
            'niveles_declarados' => 'Básica', 'financiamiento' => 'General', 'mes' => 8, 'anio' => 2026,
            'tramo' => 'Avanzado', 'fecha_antiguedad' => '2020-01-01',
            'exclusion_docente' => ['motivo' => 'horas_lactancia', 'posee_fuero_maternal' => false]], $cambios);
    }

    private function vista(string $role, DotacionDocenteExclusion $situacion): string
    {
        return view('admin.dotacion-establecimiento.partials._docentes', [
            'anio' => 2027, 'establecimiento' => Establecimiento::findOrFail(1), 'activeRole' => $role,
            'canManageDocenteExclusiones' => true, 'docenteExclusionesTableReady' => true,
            'fueroMaternalDisponible' => true, 'motivosExclusionDocente' => DotacionDocenteExclusion::MOTIVOS,
            'situacionesDocentesAnteriores' => ['99000001K' => new DotacionDocenteExclusion($this->situacion())],
            'docentes' => collect([$this->docente(['exclusion_docente' => array_merge($situacion->toArray(), ['motivo_label' => $situacion->motivo_label])])]),
        ])->render();
    }

    private function resetSchemaCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $nombre) {
                (new ReflectionProperty($class, $nombre))->setValue(null, []);
            }
        }
    }
}
