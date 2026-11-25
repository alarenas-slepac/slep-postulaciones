<?php

namespace Tests\Feature;

use App\Jobs\ProcesarMaeSned;
use App\Models\SnedCuota;
use App\Support\RutChile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\IsolatedSecurityTestCase;

class SnedRemuneracionesTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Queue::fake([ProcesarMaeSned::class]);
        Storage::fake('local');
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('section');
            $table->string('icon')->nullable();
            $table->unsignedSmallInteger('sort');
            $table->timestamps();
        });
        Schema::create('module_role', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id');
            $table->unsignedBigInteger('role_id');
        });
        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('rbd');
            $table->string('nombre_establecimiento');
            $table->string('comuna');
        });
        DB::table('establecimientos')->insert([
            ['rbd' => 90001, 'nombre_establecimiento' => 'Escuela de Prueba A', 'comuna' => 'Santa Juana'],
            ['rbd' => 90002, 'nombre_establecimiento' => 'Escuela de Prueba B', 'comuna' => 'Santa Juana'],
            ['rbd' => 90003, 'nombre_establecimiento' => 'Escuela de otra comuna', 'comuna' => 'Comuna de prueba'],
        ]);
        $migracion = require database_path('migrations/2026_11_25_110000_create_sned_remuneraciones.php');
        $migracion->up();
        $migracion->up();
    }

    public function test_prorratea_con_tramos_separa_incentivos_y_exporta_por_comuna(): void
    {
        $this->actingAs($this->testUser(role: 3));
        $this->post(route('sned.store'), ['anio' => 2026, 'numero' => 3, 'comuna' => 'santa-juana'])->assertRedirect();
        $cuota = SnedCuota::sole();
        $this->assertSame('jornada_dias', $cuota->metodo);
        $this->get(route('sned.index'))->assertOk()->assertSee('Cuotas SNED');
        $this->get(route('sned.show', $cuota))->assertOk()->assertSee('MAE de Haberes por Contrato');
        $this->get(route('sned.exportar', [$cuota, 'csv']))->assertConflict();

        $principal = $this->rut(1);
        $ultimoDia = $this->rut(2);
        $anterior = $this->rut(3);
        $aaee = $this->rut(4);
        foreach ([7 => 34, 8 => 38, 9 => 30] as $mes => $jornada) {
            $filas = [
                [$principal, 'Docente de prueba titular', '01/07/2026', '', $jornada, 'Docente', 1, '90001 Escuela de Prueba A'],
                [$principal, 'Docente de prueba titular', '01/07/2026', '', 8, 'Docente', 2, '90002 Escuela de Prueba B'],
                [$anterior, 'Docente de prueba anterior', '01/07/2026', '31/08/2026', 33, 'Docente', 1, '90001 Escuela de Prueba A'],
                [$aaee, 'Asistente de prueba', '01/07/2026', '', 44, 'Asist.Técnico', 1, '90001 Escuela de Prueba A'],
            ];
            if ($mes === 9) {
                $filas[] = [$ultimoDia, 'Docente de prueba de septiembre', '30/09/2026', '30/09/2026', 20, 'Docente', 1, '90001 Escuela de Prueba A'];
                $filas[] = $filas[0];
            }
            $this->post(route('sned.cargar', $cuota), [
                'mes' => $mes, 'excel' => $this->mae($filas, sprintf('prueba_%02d_2026.xlsx', $mes)),
            ])->assertSessionHasNoErrors();
            $carga = $cuota->cargas()->where('mes', $mes)->sole();
            Queue::assertPushed(ProcesarMaeSned::class, fn ($job) => $job->cargaId === $carga->id);
            app()->call([new ProcesarMaeSned($carga->id), 'handle']);
            $this->assertSame('lista', $carga->refresh()->estado, $carga->error ?? '');
        }
        $this->assertSame(1, $cuota->cargas()->where('mes', 9)->sole()->lectura['duplicados']);
        $this->post(route('sned.aportes.store', $cuota), ['rbd' => 90001, 'docentes' => 10001, 'aaee' => 431])->assertSessionHasNoErrors();
        $this->post(route('sned.aportes.store', $cuota), ['rbd' => 90002, 'docentes' => 11, 'aaee' => 0])->assertSessionHasNoErrors();
        $this->post(route('sned.aportes.store', $cuota), ['rbd' => 90003, 'docentes' => 11, 'aaee' => 0])->assertSessionHasErrors('rbd');
        $this->patch(route('sned.configurar', $cuota), ['metodo' => 'jornada_dias', 'mes_pago' => '2026-10'])->assertSessionHasNoErrors();
        $this->post(route('sned.cerrar', $cuota))->assertSessionHasErrors('instruccion_calculo');
        $this->patch(route('sned.configurar', $cuota), [
            'metodo' => 'jornada_dias', 'mes_pago' => '2026-10', 'instruccion_calculo' => 'Instrucción de prueba',
        ])->assertSessionHasNoErrors();
        $this->post(route('sned.cerrar', $cuota))->assertSessionHasErrors('cierre');

        $aporteA = $cuota->aportes()->where('rbd', 90001)->sole();
        $aporteB = $cuota->aportes()->where('rbd', 90002)->sole();
        $incentivo = ['modo' => 'montos', 'alcance' => 'seleccion', 'referencia' => 'Acta de prueba'];
        $this->post(route('sned.incentivo', [$cuota, $aporteA]), $incentivo + [
            'ruts' => [$anterior], 'montos' => [$anterior => 1000],
        ])->assertSessionHasErrors('ruts');
        $this->post(route('sned.incentivo', [$cuota, $aporteA]), $incentivo + [
            'ruts' => [$ultimoDia], 'montos' => [$ultimoDia => 999],
        ])->assertSessionHasErrors('montos');
        $this->post(route('sned.incentivo', [$cuota, $aporteA]), $incentivo + [
            'ruts' => [$ultimoDia], 'montos' => [$ultimoDia => 1000],
        ])->assertSessionHasNoErrors();
        $this->post(route('sned.incentivo', [$cuota, $aporteB]), [
            'modo' => 'iguales', 'alcance' => 'todos', 'referencia' => 'Acta de prueba B',
        ])->assertSessionHasNoErrors();

        $this->get(route('sned.show', [$cuota, 'rbd' => 90001]))->assertOk()
            ->assertSee('Incentivo docente 10%')->assertSee('Docente de prueba de septiembre');
        $this->post(route('sned.cerrar', $cuota))->assertSessionHasNoErrors();
        $resultado = $cuota->refresh()->resultados[90001];
        $this->assertSame(['docentes' => 9001, 'incentivo' => 1000, 'aaee' => 431], $resultado['pagos']);
        $personas = collect($resultado['personas'])->keyBy('rut');
        $this->assertSame(8943, $personas[$principal]['general']);
        $this->assertSame(58, $personas[$ultimoDia]['general']);
        $this->assertSame([34, 38, 30], array_map(fn ($tramo) => (int) ($tramo['minutos'] / 60), $personas[$principal]['tramos']));
        $this->assertSame(1, $personas[$ultimoDia]['tramos'][0]['dias']);
        $this->assertSame(1000, $personas[$ultimoDia]['incentivo']);
        $this->assertFalse($personas->has($anterior));

        $csv = $this->get(route('sned.exportar', [$cuota, 'csv']))->assertOk();
        $this->assertStringContainsString('SNED_CUOTA_3_2026_SANTA_JUANA.csv', $csv->headers->get('Content-Disposition'));
        $lineas = array_map(fn ($linea) => str_getcsv($linea, escape: ''), explode("\n", trim($csv->streamedContent())));
        $this->assertSame(['RUT', 'MONTO'], array_shift($lineas));
        $consolidado = array_column($lineas, 1, 0);
        $this->assertCount(3, $consolidado);
        $this->assertSame(10443, array_sum($consolidado));
        $this->assertSame($personas[$principal]['general'] + 11, (int) $consolidado[$principal]);
        $txt = $this->get(route('sned.exportar', [$cuota, 'txt']))->assertOk();
        $this->assertStringContainsString('SNED_CUOTA_3_2026_SANTA_JUANA.txt', $txt->headers->get('Content-Disposition'));
        $this->assertStringStartsWith("RUT\tMONTO\n", $txt->streamedContent());

        $this->post(route('sned.aportes.store', $cuota), ['rbd' => 90001, 'docentes' => 25, 'aaee' => 0])->assertConflict();
        $this->post(route('sned.version', $cuota))->assertRedirect();
        $nueva = SnedCuota::query()->where('version', 2)->sole();
        $this->assertNull($nueva->resultados);
        $this->assertSame(3, $nueva->cargas()->count());
        $this->assertSame(2, $nueva->aportes()->count());
    }

    public function test_el_modulo_requiere_acceso_a_remuneraciones_y_valida_el_mes_en_el_nombre_del_mae(): void
    {
        $this->actingAs($this->testUser());
        $this->get(route('sned.index'))->assertForbidden();
        $this->actingAs($this->testUser(2, 3));
        $this->post(route('sned.store'), ['anio' => 2026, 'numero' => 4, 'comuna' => 'santa-juana'])->assertRedirect();
        $cuota = SnedCuota::sole();
        $this->assertSame([10, 11, 12], $cuota->meses());
        $this->post(route('sned.cargar', $cuota), [
            'mes' => 10, 'excel' => $this->mae([], 'prueba_09_2026.xlsx'),
        ])->assertSessionHasErrors('excel');
        Queue::assertNothingPushed();
    }

    public function test_la_migracion_recupera_un_indice_faltante_conservando_la_cuota_y_los_aportes(): void
    {
        $cuota = SnedCuota::create([
            'anio' => 2026, 'numero' => 3, 'comuna' => 'Santa Juana', 'comuna_clave' => 'santa-juana',
            'metodo' => 'jornada_corte', 'creado_por' => 3,
        ]);
        $aporte = $cuota->aportes()->create([
            'rbd' => 90001, 'establecimiento' => 'Escuela de Prueba A', 'docentes' => 10000, 'aaee' => 0, 'actualizado_por' => 3,
        ]);
        Schema::table('sned_aportes', fn (Blueprint $table) => $table->dropUnique('uq_sned_aporte_rbd'));
        $this->assertFalse(Schema::hasIndex('sned_aportes', 'uq_sned_aporte_rbd'));
        (require database_path('migrations/2026_11_25_110000_create_sned_remuneraciones.php'))->up();
        $this->assertTrue(Schema::hasIndex('sned_aportes', 'uq_sned_aporte_rbd', 'unique'));
        $this->assertSame($aporte->id, $cuota->aportes()->sole()->id);
    }

    private function rut(int $numero): string
    {
        $cuerpo = 99000000 + $numero;

        return $cuerpo.'-'.RutChile::dv($cuerpo);
    }

    private function mae(array $filas, string $nombre): UploadedFile
    {
        $libro = new Spreadsheet;
        $libro->getActiveSheet()->setTitle('Hoja1')->fromArray([
            ['rut', 'nombre', 'Fecha_Ingreso', 'Fecha_Termino', 'jornada', 'Planta', 'Lincontrato_C', 'departamento'],
            ...$filas,
        ]);
        $archivo = UploadedFile::fake()->create($nombre, 0, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        (new Xlsx($libro))->save($archivo->getRealPath());
        $libro->disconnectWorksheets();

        return $archivo;
    }
}
