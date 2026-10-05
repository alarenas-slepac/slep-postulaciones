<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DotacionDocenteExclusionController;
use App\Models\DotacionDocenteExclusion;
use App\Models\Establecimiento;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionProceso2027Calculator;
use App\Support\DotacionSituacionesAnuales;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionSituacionesAnualesTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetSchemaCaches();
        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id(); $table->integer('rbd'); $table->string('nombre_establecimiento');
            $table->boolean('sala_cuna')->default(false); $table->timestamps();
        });
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99001, 'nombre_establecimiento' => 'Establecimiento de prueba A'],
            ['id' => 2, 'rbd' => 99002, 'nombre_establecimiento' => 'Establecimiento de prueba B'],
        ]);
        foreach ([
            '2026_08_24_090000_create_dotacion_docente_exclusiones_table.php',
            '2026_09_14_160000_add_continuidad_to_dotacion_docente_exclusiones.php',
            '2026_09_14_170000_add_conservar_horas_to_dotacion_docente_exclusiones.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    protected function tearDown(): void
    {
        $this->resetSchemaCaches();
        parent::tearDown();
    }

    public static function motivos(): array
    {
        return array_combine(array_keys(DotacionDocenteExclusion::MOTIVOS),
            array_map(fn (string $motivo) => [$motivo], array_keys(DotacionDocenteExclusion::MOTIVOS)));
    }

    #[DataProvider('motivos')]
    public function test_migracion_copia_todas_las_situaciones_y_sus_decisiones_sin_alterar_el_origen(string $motivo): void
    {
        $id = DB::table('dotacion_docente_exclusiones')->insertGetId($this->situacion([
            'motivo' => $motivo, 'horas' => 2.75, 'conservar_horas_necesarias' => false,
        ]));
        $origen = DB::table('dotacion_docente_exclusiones')->find($id);

        $this->migrarTraspasos();
        $this->migrarTraspasos(); // Reintento de despliegue: no duplica ni sobrescribe.

        $destino = DotacionDocenteExclusion::query()->where('anio', 2027)->sole();
        $this->assertSame($motivo, $destino->motivo);
        $this->assertSame('2.75', $destino->horas);
        $this->assertTrue($destino->considerar_dotacion_siguiente);
        $this->assertFalse($destino->conservar_horas_necesarias);
        $this->assertSame('99000001K', $destino->docente_rut_normalizado);
        $this->assertSame((array) $origen, (array) DB::table('dotacion_docente_exclusiones')->find($id));
        $this->assertDatabaseCount('dotacion_situacion_traspasos', 1);
        $this->assertDatabaseHas('dotacion_situacion_traspasos', [
            'anio_origen' => 2026, 'anio_destino' => 2027, 'situacion_origen_id' => $id, 'copiada' => true,
        ]);
        $this->assertSame(0, DB::table('dotacion_docente_exclusiones')->where('anio', 2028)->count());
    }

    public function test_no_continua_no_se_copia_y_una_decision_posterior_de_continuar_si_se_copia(): void
    {
        $id = DB::table('dotacion_docente_exclusiones')->insertGetId($this->situacion([
            'considerar_dotacion_siguiente' => false,
        ]));
        $this->migrarTraspasos();
        $this->assertSame(0, DB::table('dotacion_docente_exclusiones')->where('anio', 2027)->count());
        $this->assertDatabaseCount('dotacion_situacion_traspasos', 0);

        DotacionDocenteExclusion::findOrFail($id)->update(['considerar_dotacion_siguiente' => true]);
        $this->assertDatabaseHas('dotacion_docente_exclusiones', ['anio' => 2027, 'docente_rut_normalizado' => '99000001K']);
    }

    public function test_guarda_situaciones_nuevas_despues_de_migrar_y_copia_solo_un_anio(): void
    {
        $this->migrarTraspasos();
        $situacion = DotacionDocenteExclusion::create($this->situacion());
        $situacion->update(['horas' => 5, 'motivo' => 'horas_gremiales']);

        $this->assertDatabaseHas('dotacion_docente_exclusiones', ['anio' => 2027, 'motivo' => 'fuero_maternal', 'horas' => 2]);
        $this->assertSame(0, DB::table('dotacion_docente_exclusiones')->where('anio', 2028)->count());
        $destino = DotacionDocenteExclusion::query()->where('anio', 2027)->sole();
        $destino->update(['motivo' => 'horas_lactancia', 'horas' => 3]);
        $this->assertDatabaseHas('dotacion_docente_exclusiones', ['anio' => 2028, 'motivo' => 'horas_lactancia', 'horas' => 3]);
        $this->assertSame(0, DB::table('dotacion_docente_exclusiones')->where('anio', 2029)->count());
    }

    public function test_respeta_destino_existente_incluso_con_rut_historico_formateado(): void
    {
        DB::table('dotacion_docente_exclusiones')->insert($this->situacion());
        $id = DB::table('dotacion_docente_exclusiones')->insertGetId($this->situacion([
            'anio' => 2027, 'docente_rut_normalizado' => '99.000.001-k',
            'motivo' => 'traslado', 'horas' => 9, 'considerar_dotacion_siguiente' => false,
        ]));
        $antes = DB::table('dotacion_docente_exclusiones')->find($id);
        $this->migrarTraspasos();

        $this->assertSame((array) $antes, (array) DB::table('dotacion_docente_exclusiones')->find($id));
        $this->assertSame(1, DB::table('dotacion_docente_exclusiones')->where('anio', 2027)->count());
        $this->assertDatabaseHas('dotacion_situacion_traspasos', ['copiada' => false, 'docente_rut_normalizado' => '99000001K']);
    }

    public function test_no_recrea_situacion_eliminada_en_destino(): void
    {
        $this->migrarTraspasos();
        $origen = DotacionDocenteExclusion::create($this->situacion());
        DotacionDocenteExclusion::query()->where('anio', 2027)->sole()->delete();
        $origen->update(['horas' => 4]);
        $this->migrarTraspasos();

        $this->assertSame(0, DB::table('dotacion_docente_exclusiones')->where('anio', 2027)->count());
        $this->assertDatabaseCount('dotacion_situacion_traspasos', 1);
    }

    public function test_separa_establecimientos_y_no_rellena_anios_historicos(): void
    {
        DB::table('dotacion_docente_exclusiones')->insert([
            $this->situacion(),
            $this->situacion(['establecimiento_id' => 2, 'motivo' => 'horas_gremiales', 'horas' => 6]),
            $this->situacion(['anio' => 2024]),
        ]);
        $this->migrarTraspasos();

        $this->assertDatabaseHas('dotacion_docente_exclusiones', ['establecimiento_id' => 1, 'anio' => 2027, 'horas' => 2]);
        $this->assertDatabaseHas('dotacion_docente_exclusiones', ['establecimiento_id' => 2, 'anio' => 2027, 'horas' => 6]);
        $this->assertSame(0, DB::table('dotacion_docente_exclusiones')->where('anio', 2025)->count());
        $this->assertSame(0, app(DotacionSituacionesAnuales::class)->copiarAlAnioSiguiente(1, 2100));
    }

    public function test_es_compatible_con_schema_anterior_sin_tabla_de_traspasos(): void
    {
        DotacionDocenteExclusion::create($this->situacion());
        $this->assertDatabaseCount('dotacion_docente_exclusiones', 1);
        $this->assertFalse(Schema::hasTable('dotacion_situacion_traspasos'));
    }

    public function test_traspaso_es_compatible_con_situaciones_sin_columnas_de_decisiones(): void
    {
        // Sólo modifica el esquema SQLite aislado de esta prueba.
        Schema::table('dotacion_docente_exclusiones', fn (Blueprint $table) =>
            $table->dropColumn(['considerar_dotacion_siguiente', 'conservar_horas_necesarias']));
        $datos = $this->situacion();
        unset($datos['considerar_dotacion_siguiente'], $datos['conservar_horas_necesarias']);
        DB::table('dotacion_docente_exclusiones')->insert($datos);
        $this->migrarTraspasos();
        $this->assertDatabaseHas('dotacion_docente_exclusiones', ['anio' => 2027, 'motivo' => 'fuero_maternal', 'horas' => 2]);
    }

    public function test_nomina_del_anio_siguiente_refleja_situacion_y_prioridad_sin_heredar_asignaciones(): void
    {
        $this->crearPadron();
        $this->migrarTraspasos();
        DotacionDocenteExclusion::create($this->situacion(['horas' => 10]));
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $table): void {
            $table->id(); $table->integer('establecimiento_id'); $table->integer('anio');
            $table->string('docente_rut'); $table->string('docente_rut_normalizado');
            $table->string('tipo_asignacion'); $table->string('estado');
            $table->string('estamento_cobertura'); $table->decimal('horas_contrato', 8, 2);
        });
        DB::table('dotacion_docente_asignaciones')->insert([
            'establecimiento_id' => 1, 'anio' => 2026, 'docente_rut' => '99000001K',
            'docente_rut_normalizado' => '99000001K', 'tipo_asignacion' => 'otra_funcion',
            'estado' => 'activa', 'estamento_cobertura' => 'docente', 'horas_contrato' => 6,
        ]);
        $this->resetSchemaCaches();
        $docente = DotacionProceso2027Calculator::docentesPriorizados(
            DotacionEstablecimientoCalculator::docentes(Establecimiento::findOrFail(1), 2027)
        )->sole();

        $this->assertSame('fuero_maternal', $docente['exclusion_docente']['motivo']);
        $this->assertSame(1, $docente['prioridad_2027']);
        $this->assertSame(44.0, $docente['horas_contrato_base']);
        $this->assertSame(34.0, $docente['horas_contrato']);
        $this->assertSame(0.0, $docente['horas_asignadas_total']);
        $this->assertCount(0, $docente['asignaciones']);
        $this->assertDatabaseCount('dotacion_docente_asignaciones', 1);
        $this->assertDatabaseHas('reemplazos_personal', ['anio' => 2026, 'jornada' => 44]);
    }

    public function test_controller_guarda_y_copia_atomicamente_y_revierte_origen_si_falla_el_traspaso(): void
    {
        $this->crearPadron();
        $this->migrarTraspasos();
        $user = $this->testUser(1, 3);
        $request = Request::create('/situacion', 'POST', [
            'anio' => 2026, 'docente_rut' => '99.000.001-k', 'motivo' => 'horas_gremiales',
            'horas_necesarias' => 40, 'horas' => 4, 'considerar_dotacion_siguiente' => 1,
        ]);
        $request->setUserResolver(fn () => $user);
        $controller = app(DotacionDocenteExclusionController::class);
        $establecimiento = Establecimiento::findOrFail(1);
        $this->assertTrue($controller->store($request, $establecimiento)->isRedirect());
        $this->assertDatabaseHas('dotacion_docente_exclusiones', ['anio' => 2027, 'motivo' => 'horas_gremiales', 'horas' => 4]);
        $antes = DB::table('dotacion_docente_exclusiones')->orderBy('id')->get()->toArray();
        $this->mock(DotacionSituacionesAnuales::class)->shouldReceive('copiarAlAnioSiguiente')
            ->once()->with(1, 2026)->andThrow(new \RuntimeException('Fallo de traspaso simulado'));
        $request->merge(['motivo' => 'horas_lactancia', 'horas_necesarias' => 42, 'horas' => 2]);
        try {
            $controller->store($request, $establecimiento);
            $this->fail('Se esperaba el fallo simulado del traspaso.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fallo de traspaso simulado', $exception->getMessage());
        }
        $this->assertEquals($antes, DB::table('dotacion_docente_exclusiones')->orderBy('id')->get()->toArray());
    }

    public function test_fallo_del_historial_revierte_copia_parcial_y_permite_reintentar(): void
    {
        $this->migrarTraspasos();
        DB::table('dotacion_docente_exclusiones')->insert($this->situacion());
        $fallar = true;
        DB::listen(function ($query) use (&$fallar): void {
            if ($fallar && str_starts_with($query->sql, 'insert')
                && str_contains($query->sql, 'dotacion_situacion_traspasos')) {
                throw new \RuntimeException('Fallo de historial simulado');
            }
        });

        try {
            app(DotacionSituacionesAnuales::class)->copiarAlAnioSiguiente(1, 2026);
            $this->fail('Se esperaba el fallo simulado al registrar el historial.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fallo de historial simulado', $exception->getMessage());
        }
        $this->assertSame(0, DB::table('dotacion_docente_exclusiones')->where('anio', 2027)->count());
        $this->assertDatabaseCount('dotacion_situacion_traspasos', 0);
        $this->assertDatabaseHas('dotacion_docente_exclusiones', ['anio' => 2026, 'motivo' => 'fuero_maternal', 'horas' => 2]);
        $fallar = false;
        $this->assertSame(1, app(DotacionSituacionesAnuales::class)->copiarAlAnioSiguiente(1, 2026));
        $this->assertSame(0, app(DotacionSituacionesAnuales::class)->copiarAlAnioSiguiente(1, 2026));
        $this->assertDatabaseCount('dotacion_situacion_traspasos', 1);
    }

    public function test_las_tres_situaciones_tienen_primera_prioridad_y_el_resto_conserva_tramo_antiguedad_y_saldo(): void
    {
        $filas = collect([
            $this->docente('Avanzado reciente', null, 'Avanzado', '2020-01-01'),
            $this->docente('Contrata', null, null, '1990-01-01', 0),
            $this->docente('Maternal', 'fuero_maternal', 'Inicial', '2024-01-01'),
            $this->docente('Lactancia', 'horas_lactancia', 'Temprano', '2025-01-01'),
            $this->docente('Gremial', 'horas_gremiales', null, '2023-01-01', 0),
            $this->docente('Experto antiguo', null, 'Experto 1', '2010-01-01'),
            $this->docente('Titular inicial', 'sumario_administrativo', 'Inicial', '2000-01-01'),
        ]);
        $priorizados = DotacionProceso2027Calculator::docentesPriorizados($filas);
        $this->assertSame([
            'Gremial', 'Maternal', 'Lactancia', 'Experto antiguo', 'Avanzado reciente', 'Titular inicial', 'Contrata',
        ], $priorizados->pluck('nombre')->all());
        $this->assertSame([1, 1, 1, 2, 2, 3, 4], $priorizados->pluck('prioridad_2027')->all());
        foreach ($priorizados->take(3) as $docente) {
            $this->assertSame('1. Fuero maternal, gremiales o lactancia', $docente['prioridad_2027_label']);
            $this->assertSame(38.0, $docente['horas_disponibles']);
        }
        $this->assertTrue(DotacionProceso2027Calculator::hayPrelacionAnteriorDisponible(
            $priorizados, $priorizados->firstWhere('nombre', 'Experto antiguo'), 2));
        foreach (array_diff(array_keys(DotacionDocenteExclusion::MOTIVOS), ['fuero_maternal', 'horas_gremiales', 'horas_lactancia']) as $motivo) {
            $this->assertSame(3, DotacionProceso2027Calculator::docentesPriorizados(
                collect([$this->docente('Otra situación', $motivo, 'Inicial', '2000-01-01')])
            )->sole()['prioridad_2027']);
        }
    }

    private function situacion(array $cambios = []): array
    {
        return array_replace([
            'establecimiento_id' => 1, 'anio' => 2026, 'docente_rut' => '99.000.001-k',
            'docente_rut_normalizado' => '99000001K', 'docente_nombre' => 'Docente de prueba',
            'motivo' => 'fuero_maternal', 'horas' => 2, 'considerar_dotacion_siguiente' => true,
            'conservar_horas_necesarias' => true, 'created_by' => null, 'updated_by' => null,
            'created_at' => now(), 'updated_at' => now(),
        ], $cambios);
    }

    private function migrarTraspasos(): void
    {
        (require database_path('migrations/2026_10_05_210000_create_dotacion_situacion_traspasos_table.php'))->up();
    }

    private function crearPadron(): void
    {
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
            'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => 'situacion-sintetica',
        ]);
        $this->resetSchemaCaches();
    }

    private function docente(string $nombre, ?string $motivo, ?string $tramo, string $fecha, float $planta = 44): array
    {
        return [
            'nombre' => $nombre, 'rut_normalizado' => $nombre, 'horas_planta' => $planta,
            'horas_contrata' => 44 - $planta, 'horas_contrato' => 44, 'horas_asignadas_total' => 6,
            'tramo' => $tramo, 'fecha_antiguedad' => $fecha, 'exclusion_docente' => ['motivo' => $motivo],
        ];
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
