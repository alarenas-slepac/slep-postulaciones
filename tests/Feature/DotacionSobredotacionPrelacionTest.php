<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Support\DotacionEstablecimientoCalculator;
use App\Support\DotacionSobredotacionCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionSobredotacionPrelacionTest extends IsolatedSecurityTestCase
{
    public static function bloques(): array
    {
        return ['Plan de estudio' => ['plan_estudio'], 'Parvularia' => ['parvularia'], 'PIE' => ['pie']];
    }

    #[DataProvider('bloques')]
    public function test_nomina_y_filas_visibles_respetan_prelacion_en_cada_bloque(string $bloque): void
    {
        $docentes = $this->docentes($bloque);
        $original = $docentes;
        $resultado = DotacionSobredotacionCalculator::build($docentes, $this->resumen($docentes, $bloque));
        $items = $resultado['vacantes_por_bloque'][$bloque]['items'];
        $esperado = $this->ordenEsperado();

        $this->assertSame($esperado, $items->pluck('rut')->all());
        $this->assertSame($original, $docentes);
        $this->assertSame(101.0, $resultado['vacantes_por_bloque'][$bloque]['horas_total']);
        $this->assertSame(47.0, $resultado['vacantes_por_bloque'][$bloque]['horas_planta']);
        $this->assertSame(54.0, $resultado['vacantes_por_bloque'][$bloque]['horas_contrata']);
        $this->assertCount(3, $resultado['protegidos']);
        foreach (array_diff(array_keys($resultado['vacantes_por_bloque']), [$bloque]) as $otro) {
            $this->assertTrue($resultado['vacantes_por_bloque'][$otro]['items']->isEmpty());
        }

        $establecimiento = new Establecimiento(['nombre_establecimiento' => 'Establecimiento sintético']);
        $establecimiento->id = 99999;
        $html = view('admin.dotacion-establecimiento.partials._sobredotacion', [
            'sobredotacion' => $resultado, 'docentes' => $docentes,
            'establecimiento' => $establecimiento, 'anio' => 2027,
            'justificacionesSobredotacionTableReady' => true,
            'canManageJustificacionesSobredotacion' => true,
        ])->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $visibles = [];
        foreach ($xpath->query('//section[@aria-labelledby="vacantes-'.$bloque.'"]//tbody/tr[@data-revision-row]/td[1]') as $celda) {
            $visibles[] = trim($celda->textContent);
        }
        $this->assertSame($esperado, $visibles);
        $this->assertSame(9, $xpath->query('//section[@aria-labelledby="vacantes-'.$bloque.'"]//button[contains(@data-bs-target,"justificacion-")]')->length);
    }

    public function test_ordenar_no_modifica_distribucion_pie_saldos_ni_resumenes(): void
    {
        $docentes = $this->docentes('pie');
        $resumen = $this->resumen($docentes, 'pie');
        $resumen['horas_contrato_pie_necesarias'] = 20;
        $resultado = DotacionSobredotacionCalculator::build($docentes, $resumen);
        foreach ($docentes as &$docente) {
            $docente['tramo'] = 'Acceso';
            $docente['fecha_antiguedad'] = null;
        }
        unset($docente);
        $comparacion = DotacionSobredotacionCalculator::build($docentes, $resumen);

        foreach (['aula', 'pie', 'protegidos'] as $key) {
            $this->assertEquals($comparacion[$key], $resultado[$key]);
        }
        foreach ($resultado['vacantes_por_bloque'] as $bloque => $datos) {
            $this->assertSame($comparacion['vacantes_por_bloque'][$bloque]['horas_total'], $datos['horas_total']);
            $this->assertSame(
                $comparacion['vacantes_por_bloque'][$bloque]['items']->keyBy('rut')->sortKeys()->all(),
                $datos['items']->keyBy('rut')->sortKeys()->all()
            );
        }
    }

    public function test_admite_iteradores_y_contratos_historicos_sin_horas_por_calidad(): void
    {
        $docentes = $this->docentes('plan_estudio');
        $indice = array_search('99000003-K', array_column($docentes, 'rut'), true);
        unset($docentes[$indice]['horas_planta'], $docentes[$indice]['horas_contrata']);
        $docentes[$indice]['rut'] = '99.000.003-k';
        $iterador = (function () use ($docentes): \Generator {
            yield from $docentes;
        })();
        $resultado = DotacionSobredotacionCalculator::build($iterador, $this->resumen($docentes, 'plan_estudio'));
        $items = $resultado['vacantes_por_bloque']['plan_estudio']['items'];

        $this->assertSame('99.000.003-k', $items->first()['rut']);
        $this->assertSame(4.0, $items->first()['horas_sobredotacion_planta']);
        $this->assertSame(
            array_map(DotacionEstablecimientoCalculator::normalizeRut(...), $this->ordenEsperado()),
            $items->map(fn ($item) => DotacionEstablecimientoCalculator::normalizeRut($item['rut']))->all()
        );
    }

    private function ordenEsperado(): array
    {
        return array_map(fn ($id) => sprintf('990000%02d-K', $id), [3, 8, 4, 9, 6, 2, 5, 7, 1]);
    }

    private function docentes(string $bloque): array
    {
        $datos = [
            [1, 'A Contrata sintética', 0, 44, 'Experto II', '1980-01-01'],
            [2, 'A Titular inicial sintético', 6, 0, 'Inicial', '1990-01-01'],
            [9, 'Z Avanzado mismo ingreso sintético', 3, 0, 'Avanzado', '2010-01-01'],
            [4, 'A Experto nuevo sintético', 8, 0, 'Experto I', '2010-01-01'],
            [6, 'Z Experto sin antigüedad sintético', 5, 0, 'Experto II', null],
            [3, 'Z Avanzado antiguo sintético', 4, 0, 'Avanzado', '2005-01-01'],
            [8, 'X Mixto sintético', 2, 10, 'Experto 1', '2008-01-01'],
            [7, 'A Lactancia sin fuero sintética', 9, 0, 'Inicial', '2020-01-01'],
            [5, 'B Temprano sintético', 10, 0, 'Temprano', '2000-01-01'],
            [10, 'Fuero maternal sintético', 0, 10, 'Avanzado', '1970-01-01'],
            [11, 'Horas gremiales sintético', 11, 0, 'Avanzado', '1970-01-01'],
            [12, 'Lactancia con fuero sintética', 12, 0, 'Avanzado', '1970-01-01'],
        ];
        return array_map(function ($datos) use ($bloque): array {
            [$id, $nombre, $planta, $contrata, $tramo, $fecha] = $datos;
            return [
                'rut' => sprintf('990000%02d-K', $id), 'nombre' => $nombre,
                'tipo_contrato' => $planta > 0 ? 'PLANTA / CONTRATA' : 'CONTRATA',
                'es_titular' => $planta > 0, 'horas_contrato' => $planta + $contrata,
                'horas_planta' => $planta, 'horas_contrata' => $contrata,
                'tramo' => $tramo, 'fecha_antiguedad' => $fecha,
                'titulo' => match ($bloque) {
                    'parvularia' => 'Pedagogía en Educación de Párvulos',
                    'pie' => 'Pedagogía en Educación Diferencial',
                    default => 'Pedagogía en Educación Básica',
                },
                'funcion' => $bloque === 'pie' ? 'PIE' : 'Docente', 'asignaciones' => [],
                'horas_asignadas_protegidas' => 0, 'horas_declaradas_ajustables' => 0,
                'horas_contrato_pie' => $bloque === 'pie' ? $planta + $contrata : 0,
                'exclusion_docente' => match ($id) {
                    7 => ['motivo' => 'horas_lactancia', 'posee_fuero_maternal' => false],
                    10 => ['motivo' => 'fuero_maternal'],
                    11 => ['motivo' => 'horas_gremiales'],
                    12 => ['motivo' => 'horas_lactancia', 'posee_fuero_maternal' => true],
                    default => null,
                },
            ];
        }, $datos);
    }

    private function resumen(array $docentes, string $bloque): array
    {
        $contrato = (float) array_sum(array_column($docentes, 'horas_contrato'));
        return [
            'horas_contrato_docentes_aula' => $bloque === 'pie' ? 0 : $contrato,
            'horas_contrato_docentes_parvularia' => $bloque === 'parvularia' ? $contrato : 0,
            'horas_contrato_docente_pie' => $bloque === 'pie' ? $contrato : 0,
        ];
    }
}
