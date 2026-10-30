<?php

namespace Tests\Feature;

use App\Exports\DotacionEstablecimientoAvanceExport;
use App\Models\DotacionProceso2027Configuracion;
use App\Models\DotacionSobredotacionJustificacion;
use App\Models\Establecimiento;
use App\Support\DotacionEstablecimientoAvanceCalculator;
use App\Support\DotacionJustificacionesAvanceCalculator;
use App\Support\DotacionProceso2027Calculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionEtapaJustificacionesTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('dotacion_sobredotacion_justificaciones', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('establecimiento_id'); $table->integer('anio');
            $table->string('docente_rut_normalizado'); $table->string('bloque'); $table->string('tipo_horas');
            $table->decimal('horas_detectadas', 8, 2); $table->text('justificacion'); $table->timestamps();
        });
        Schema::create('dotacion_proceso_2027_configuraciones', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('establecimiento_id'); $table->integer('anio');
            $table->string('decision_combinacion'); $table->json('funciones_normativas')->nullable();
            foreach ([1, 2, 3] as $bloque) $table->decimal('max_horas_bloque_'.$bloque, 8, 2)->nullable();
            $table->timestamps();
        });
        $this->app['request']->setLaravelSession($this->app['session.store']);
        $disk = Storage::disk('local');
        Vite::useHotFile($disk->path('etapas-views/nonexistent-hot'));
        $disk->put('etapas-views/layouts/app.blade.php', '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">@vite([\'resources/js/app.js\', \'resources/scss/app.scss\', \'resources/css/dotacion-establecimiento.css\'])@stack(\'styles\')</head><body><main class="p-3">@yield(\'content\')</main>@stack(\'scripts\')</body></html>');
        $this->app['view']->getFinder()->prependLocation($disk->path('etapas-views'));
    }

    public function test_suma_tres_bloques_y_solo_descuenta_fundamentos_vigentes_del_mismo_periodo_y_establecimiento(): void
    {
        $saldos = ['vacantes_por_bloque' => []];
        foreach (['plan_estudio' => [3, 2], 'parvularia' => [4, 1], 'pie' => [2, 6]] as $bloque => [$planta, $contrata]) {
            $saldos['vacantes_por_bloque'][$bloque] = ['items' => [[
                'rut' => '99.000.001-K', 'horas_sobredotacion_planta' => $planta,
                'horas_sobredotacion_contrata' => $contrata,
            ]]];
        }
        $this->justificar('plan_estudio', 'titular', 3);
        $this->justificar('pie', 'contrata', 6);
        $this->justificar('parvularia', 'contrata', 2); // El saldo actual es 1: requiere actualización.
        $this->justificar('plan_estudio', 'contrata', 2, ['establecimiento_id' => 2]);
        $this->justificar('parvularia', 'titular', 4, ['anio' => 2026]);
        $this->justificar('pie', 'titular', 2, ['justificacion' => '   ']);

        $resumen = DotacionJustificacionesAvanceCalculator::resumen(1, 2027, $saldos);
        $this->assertSame(18.0, $resumen['horas_total']);
        $this->assertSame(9.0, $resumen['horas_justificadas']);
        $this->assertSame(9.0, $resumen['horas_pendientes']);
        $this->assertFalse($resumen['completo']);
        $this->assertSame('9 de 18 horas por justificar.', $resumen['detalle']);
        $this->assertSame(6, DotacionSobredotacionJustificacion::count());
        $this->assertSame('2.00', DotacionSobredotacionJustificacion::where('bloque', 'parvularia')->where('tipo_horas', 'contrata')->first()->horas_detectadas);
    }

    public function test_sin_saldos_se_completa_y_sin_tabla_mantiene_las_horas_pendientes(): void
    {
        Schema::drop('dotacion_sobredotacion_justificaciones');
        $vacio = DotacionJustificacionesAvanceCalculator::resumen(1, 2027, []);
        $this->assertTrue($vacio['completo']);
        $this->assertSame('0 de 0 horas por justificar.', $vacio['detalle']);
        $pendiente = DotacionProceso2027Calculator::resumen($this->establecimiento(), 2027, $this->data());
        $this->assertFalse($pendiente['justificaciones']['disponible']);
        $this->assertSame(12.0, $pendiente['justificaciones']['horas_pendientes']);
        $this->assertFalse($pendiente['pasos']['justificaciones']['completo']);
    }

    public function test_la_etapa_siete_excluye_protegidos_y_cupos_y_recalcula_al_cambiar_el_saldo(): void
    {
        $this->configurar();
        $ee = $this->establecimiento();
        $data = $this->data();
        $avance = DotacionEstablecimientoAvanceCalculator::fromData($ee, 2027, $data);
        $this->assertCount(7, $avance['proceso_2027']['pasos']);
        $this->assertSame(12.0, $avance['proceso_2027']['justificaciones']['horas_total']);
        $this->assertSame(85.7, $avance['porcentaje_general']);

        $this->justificar('plan_estudio', 'titular', 7);
        $contrata = $this->justificar('plan_estudio', 'contrata', 5);
        $completo = DotacionEstablecimientoAvanceCalculator::fromData($ee, 2027, $data);
        $this->assertTrue($completo['proceso_2027']['pasos']['justificaciones']['completo']);
        $this->assertSame(100.0, $completo['porcentaje_general']);
        $this->assertSame('completo', $completo['estado']['key']);

        $data['docentes'][0]['horas_contrato'] = 13;
        $data['docentes'][0]['horas_contrata'] = 6;
        $data['resumen']['horas_contrato_docentes_aula'] = 35;
        $cambio = DotacionEstablecimientoAvanceCalculator::fromData($ee, 2027, $data);
        $this->assertSame(13.0, $cambio['proceso_2027']['justificaciones']['horas_total']);
        $this->assertSame(6.0, $cambio['proceso_2027']['justificaciones']['horas_pendientes']);
        $this->assertSame('5.00', $contrata->fresh()->horas_detectadas);
        $this->assertSame(85.7, $cambio['porcentaje_general']);
        $contrata->update(['horas_detectadas' => 6]);
        $this->assertSame(100.0, DotacionEstablecimientoAvanceCalculator::fromData($ee, 2027, $data)['porcentaje_general']);
    }

    public function test_resumen_global_incluye_las_siete_etapas_y_no_oculta_errores_ni_afecta_anios_historicos(): void
    {
        $this->configurar();
        $this->justificar('plan_estudio', 'titular', 7);
        $this->justificar('plan_estudio', 'contrata', 5);
        $completo = DotacionEstablecimientoAvanceCalculator::fromData($this->establecimiento(), 2027, $this->data());
        $this->configurar(2);
        $pendiente = DotacionEstablecimientoAvanceCalculator::fromData($this->establecimiento(2), 2027, $this->data());
        $global = DotacionEstablecimientoAvanceCalculator::resumenGlobal(collect([$completo, $pendiente]));
        $this->assertSame(92.9, $global['porcentaje_general']);
        $this->assertCount(7, $global['proceso_2027']['pasos']);
        $this->assertSame(50.0, $global['proceso_2027']['pasos']['justificaciones']['porcentaje']);
        $this->assertSame(24.0, $global['justificaciones']['horas_total']);
        $this->assertSame(12.0, $global['justificaciones']['horas_pendientes']);
        $error = DotacionEstablecimientoAvanceCalculator::error($this->establecimiento(3), 2027);
        $conError = DotacionEstablecimientoAvanceCalculator::resumenGlobal(collect([$completo, $pendiente, $error]));
        $this->assertSame(61.9, $conError['porcentaje_general']);
        $this->assertSame(1, $conError['justificaciones']['establecimientos_sin_calculo']);
        $this->assertSame(3, $conError['proceso_2027']['pasos']['justificaciones']['total']);

        $data = $this->data();
        $data['asignacion']['resumen'] = ['horas_aula_requeridas' => 10, 'horas_aula_asignadas' => 5];
        $historico = DotacionEstablecimientoAvanceCalculator::fromData($this->establecimiento(), 2026, $data);
        $this->assertFalse($historico['proceso_2027']['aplica']);
        $this->assertSame(75.0, $historico['porcentaje_general']);
        $this->assertFalse(DotacionEstablecimientoAvanceCalculator::resumenGlobal(collect([$historico]))['justificaciones']['aplica']);
        $this->assertFalse(DotacionEstablecimientoAvanceCalculator::resumenGlobal(collect())['proceso_2027']['aplica']);
    }

    public function test_tarjeta_y_reportes_web_pdf_y_excel_muestran_las_mismas_horas(): void
    {
        $this->configurar();
        $this->justificar('plan_estudio', 'titular', 7);
        $ee = $this->establecimiento();
        $avance = DotacionEstablecimientoAvanceCalculator::fromData($ee, 2027, $this->data());
        $html = view('admin.dotacion-establecimiento.partials._proceso_etapas', [
            'proceso2027' => $avance['proceso_2027'], 'establecimiento' => $ee, 'anio' => 2027, 'activeRole' => 'funcionario_directivo_estab',
        ])->render();
        $this->assertStringContainsString('Etapa 7', $html);
        $this->assertStringContainsString('6 de 7 etapas completas', $html);
        $this->assertStringContainsString('5 de 12 horas por justificar.', $html);
        $this->assertStringContainsString('tab=sobredotacion#vacantes-plan_estudio', $html);
        $this->assertStringContainsString('data-dotacion-etapa-pendientes="justificaciones"', $html);
        $restringido = view('admin.dotacion-establecimiento.partials._proceso_etapas', [
            'proceso2027' => $avance['proceso_2027'], 'establecimiento' => $ee, 'anio' => 2027, 'activeRole' => 'postulante',
        ])->render();
        $this->assertStringNotContainsString('tab=sobredotacion', $restringido);
        $ee->dotacion_avance = $avance;
        $avances = collect([$avance]);
        $resumen = DotacionEstablecimientoAvanceCalculator::resumen($avances);
        $global = DotacionEstablecimientoAvanceCalculator::resumenGlobal($avances);
        $web = view('admin.dotacion-establecimiento.avance', [
            'establecimientos' => new LengthAwarePaginator([$ee], 1, 20), 'opcionesEstablecimientos' => collect([$ee]),
            'comunas' => collect(), 'anio' => 2027, 'comuna' => '', 'establecimientoId' => 0, 'resumenPagina' => $resumen,
        ])->render();
        $pdf = view('admin.dotacion-establecimiento.avance-pdf', [
            'resumenGlobal' => $global, 'anio' => 2027, 'comuna' => '', 'establecimientoSeleccionado' => null,
            'generatedAt' => now(), 'generatedBy' => null,
        ])->render();
        foreach ([$web, $pdf] as $contenido) $this->assertStringContainsString('5 de 12 horas por justificar.', $contenido);
        $this->assertStringContainsString('7. Horas por justificar', $pdf);
        $this->assertStringContainsString('siete etapas', $pdf);

        $spreadsheet = new Spreadsheet;
        $export = new DotacionEstablecimientoAvanceExport;
        (new \ReflectionMethod($export, 'buildSummarySheet'))->invoke($export, $spreadsheet, collect([$ee]), $avances, $resumen, 2027, [], null);
        (new \ReflectionMethod($export, 'buildDetailSheet'))->invoke($export, $spreadsheet, $avances);
        $filas = $spreadsheet->getSheet(1)->toArray(null, false, false);
        $porColumna = array_combine($filas[0], $filas[1]);
        $this->assertSame(12.0, $porColumna['Etapa 7 - Total horas que requieren justificación']);
        $this->assertSame(7.0, $porColumna['Etapa 7 - Horas justificadas vigentes']);
        $this->assertSame(5.0, $porColumna['Etapa 7 - Horas pendientes de justificar']);
        $this->assertSame('Pendiente', $porColumna['Proceso 2027 - Horas por justificar']);
        $this->assertSame('Completada', $porColumna['Proceso 2027 - Docentes por asignatura']);
        $this->assertContains(['Horas pendientes de justificar', 5.0], $spreadsheet->getSheet(0)->rangeToArray('A24:B27', null, false, false));
        $archivo = Storage::disk('local')->path('avance.xlsx');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($archivo);
        $reabierto = (new \PhpOffice\PhpSpreadsheet\Reader\Xlsx)->load($archivo);
        $this->assertSame(5.0, $reabierto->getSheet(0)->getCell('B27')->getValue());
        $reabierto->disconnectWorksheets();
        $spreadsheet->disconnectWorksheets();

        if (getenv('DOTACION_ETAPA7_PREVIEW') === '1') {
            $dir = base_path('.codex_work/etapa7');
            if (! is_dir($dir)) mkdir($dir, 0777, true);
            file_put_contents($dir.'/avance.html', $web);
            $estilos = '<link rel="stylesheet" href="'.Vite::asset('resources/scss/app.scss').'">'
                .'<link rel="stylesheet" href="'.Vite::asset('resources/css/dotacion-establecimiento.css').'">';
            file_put_contents($dir.'/etapas.html', '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'.$estilos.'</head><body><main class="p-3 dotacion-workspace">'.$html.'</main></body></html>');
            file_put_contents($dir.'/avance.pdf', app('dompdf.wrapper')->loadHTML($pdf)->setPaper('a4', 'landscape')->output());
        }
    }

    public function test_excel_conserva_ceros_y_no_inventa_horas_para_errores_o_anios_historicos(): void
    {
        $this->configurar();
        $this->justificar('plan_estudio', 'titular', 7);
        $this->justificar('plan_estudio', 'contrata', 5);
        $ee = $this->establecimiento();
        $completo = DotacionEstablecimientoAvanceCalculator::fromData($ee, 2027, $this->data());
        $historico = DotacionEstablecimientoAvanceCalculator::fromData($ee, 2026, $this->data());
        $error = DotacionEstablecimientoAvanceCalculator::error($ee, 2027);
        $spreadsheet = new Spreadsheet;
        $export = new DotacionEstablecimientoAvanceExport;
        $avances = collect([$completo, $historico, $error]);
        (new \ReflectionMethod($export, 'buildDetailSheet'))->invoke($export, $spreadsheet, $avances);
        $filas = $spreadsheet->getSheet(1)->toArray(null, false, false);
        $completado = array_combine($filas[0], $filas[1]);
        $this->assertSame(0.0, $completado['Etapa 7 - Horas pendientes de justificar']);
        $this->assertSame('Completada', $completado['Proceso 2027 - Horas por justificar']);
        foreach ([2, 3] as $fila) {
            $valores = array_combine($filas[0], $filas[$fila]);
            $this->assertNull($valores['Etapa 7 - Horas pendientes de justificar']);
            $this->assertSame($fila === 2 ? 'No aplica' : 'Pendiente', $valores['Proceso 2027 - Horas por justificar']);
        }
        $spreadsheet->disconnectWorksheets();
        $html = view('admin.dotacion-establecimiento.partials._avance-justificaciones', ['avance' => $completo])->render();
        $this->assertStringContainsString('0 de 12 horas por justificar.', $html);
        $this->assertStringContainsString('Completada', $html);
        $this->assertSame('', trim(view('admin.dotacion-establecimiento.partials._avance-justificaciones', ['avance' => $historico])->render()));
    }

    private function justificar(string $bloque, string $tipo, float $horas, array $extra = []): DotacionSobredotacionJustificacion
    {
        return DotacionSobredotacionJustificacion::create(array_replace([
            'establecimiento_id' => 1, 'anio' => 2027, 'docente_rut_normalizado' => '99000001K',
            'bloque' => $bloque, 'tipo_horas' => $tipo, 'horas_detectadas' => $horas,
            'justificacion' => 'Fundamento sintético para revisar las horas.',
        ], $extra));
    }

    private function configurar(int $id = 1): void
    {
        DotacionProceso2027Configuracion::create([
            'establecimiento_id' => $id, 'anio' => 2027, 'decision_combinacion' => 'sin_combinacion',
            'max_horas_bloque_1' => 100, 'max_horas_bloque_2' => 100, 'max_horas_bloque_3' => 100,
        ]);
    }

    private function establecimiento(int $id = 1): Establecimiento
    {
        $ee = new Establecimiento(['nombre_establecimiento' => 'Establecimiento sintético '.$id, 'rbd' => '99999', 'comuna' => 'Comuna de prueba']);
        $ee->id = $id;

        return $ee;
    }

    private function data(): array
    {
        $base = ['rut' => '99.000.001-K', 'rut_normalizado' => '99000001K', 'nombre' => 'Docente sintético',
            'horas_contrato' => 12, 'horas_planta' => 7, 'horas_contrata' => 5, 'horas_asignadas_total' => 0];

        return [
            'docentes' => [
                $base,
                array_replace($base, ['rut' => '99000002-K', 'rut_normalizado' => '99000002K',
                    'horas_contrato' => 22, 'horas_planta' => 22, 'horas_contrata' => 0, 'exclusion_docente' => ['motivo' => 'horas_gremiales']]),
                array_replace($base, ['rut' => 'CUPO1', 'rut_normalizado' => 'CUPO1', 'cupo_contrata_id' => 1,
                    'horas_contrato' => 20, 'horas_planta' => 0, 'horas_contrata' => 20]),
            ],
            'resumen' => ['horas_contrato_docentes_aula' => 34],
            'cursos' => ['totales' => ['cursos' => 1, 'sin_horas_plan' => 0],
                'configuracion_planes' => ['completo' => true, 'total' => 1, 'configurados' => 1]],
            'asignacion' => ['necesidades' => [], 'asignaciones' => [], 'resumen' => []],
        ];
    }
}
