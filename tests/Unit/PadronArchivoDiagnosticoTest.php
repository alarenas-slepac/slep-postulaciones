<?php

namespace Tests\Unit;

use App\Services\Padron\PadronArchivoDiagnostico;
use App\Services\Padron\PadronExcelReader;
use App\Support\RutChile;
use PHPUnit\Framework\TestCase;

// No arranca Laravel ni necesita una conexión a base de datos.
class PadronArchivoDiagnosticoTest extends TestCase
{
    private function row(array $changes = [], int $line = 2): array
    {
        return (new PadronExcelReader)->normalize(array_replace([
            'rut' => '111111111', 'nombre' => 'Persona de prueba privada',
            'fecha_nacimiento' => '1980-01-02', 'fecha_ingreso' => '2020-03-04',
            'fecha_termino' => '', 'fecha_antiguedad' => '2010-05-06',
            'tipocontrato' => 'CONTRATA', 'financiamiento' => 'REGULAR',
            'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE AULA',
            'anio' => 2026, 'mes' => 5, 'jornada' => 22, 'jornada_basica' => 22,
            'jornada_media' => 0, 'rbd' => 99999, 'bienios' => 3, 'tramo' => 'Inicial',
        ], $changes), $line);
    }

    public function test_reports_duplicates_and_excesses_without_identifying_people_or_mutating_rows(): void
    {
        $rows = [$this->row(['jornada' => 23]), $this->row(['jornada' => 23], 3)];
        $before = $rows;
        $report = (new PadronArchivoDiagnostico)->analizar($rows);
        $this->assertSame($before, $rows);
        $this->assertSame(2, $report['filas']);
        $this->assertSame(1, $report['identificadores_rut_distintos']);
        $this->assertSame(['2026-05' => 2], $report['periodos_validos']);
        $this->assertSame(0, $report['filas_con_errores_lector']);
        $this->assertSame(2, $report['filas_duplicadas']);
        $this->assertSame(2, $report['filas_invalidas_conciliacion']);
        $this->assertSame(1, $report['rut_docentes_sobre_44_horas']);
        $this->assertSame([['total' => 46.0, 'exceso' => 2.0, 'filas' => [2, 3]]], $report['excesos_primeros_20']);
        $json = json_encode($report, JSON_UNESCAPED_UNICODE);
        foreach (['111111111', 'Persona de prueba privada', '1980-01-02', '2020-03-04', '2010-05-06'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
        $this->assertStringNotContainsString('nueva_incorporacion', $json);
        $this->assertStringContainsString('Sin consultas ni escrituras', $json);
    }

    public function test_anonymization_preserves_differences_and_is_repeatable_without_exporting_the_key(): void
    {
        $rows = [$this->row(), $this->row(['nombre' => 'Otra variante sintética'], 3),
            $this->row(['fecha_ingreso' => '2021-03-04'], 4)];
        $diagnostic = new PadronArchivoDiagnostico;
        $first = $diagnostic->analizar($rows);
        $this->assertSame(0, $first['filas_duplicadas']);
        $this->assertSame(0, $first['filas_invalidas_conciliacion']);
        $this->assertSame($first, $diagnostic->analizar($rows));
    }

    public function test_unknown_contracts_invalid_fields_and_mixed_periods_remain_visible(): void
    {
        $report = (new PadronArchivoDiagnostico)->analizar([
            $this->row(['rut' => '12345678-9', 'fecha_antiguedad' => '31/02/2026']),
            $this->row(['tipocontrato' => 'DESCONOCIDO', 'mes' => 6], 3),
        ]);
        $this->assertSame(1, $report['filas_con_errores_lector']);
        $this->assertSame(2, $report['filas_con_columna_antiguedad']);
        $this->assertSame(1, $report['filas_con_antiguedad_valida']);
        $this->assertSame(1, $report['tipos']['por_clasificar']);
        $this->assertCount(2, $report['periodos_validos']);
        $this->assertNotEmpty($report['errores_globales']);
        $this->assertCount(3, $report['observaciones']);
    }

    public function test_44_hours_uses_all_lines_without_double_counting_basica_media_or_blocking_assistants_only(): void
    {
        $rows = [
            $this->row(['jornada' => 24, 'jornada_basica' => 12, 'jornada_media' => 12]),
            $this->row(['jornada' => 21, 'jornada_basica' => 21, 'rbd' => 99998, 'tipocontrato' => 'SUPLENCIA'], 3),
            $this->row(['rut' => '222222222', 'estatuto' => 'ASISTENTE EDUCACION', 'escalafon' => 'PARADOCENTE', 'jornada' => 45], 4),
        ];
        $report = (new PadronArchivoDiagnostico)->analizar($rows);
        $this->assertSame(2, $report['identificadores_rut_distintos']);
        $this->assertSame(2, $report['valores_rbd_distintos']);
        $this->assertSame(1, $report['tipos']['reemplazo_suplencia']);
        $this->assertSame(1, $report['rut_docentes_sobre_44_horas']);
        $this->assertSame([['total' => 45.0, 'exceso' => 1.0, 'filas' => [2, 3]]], $report['excesos_primeros_20']);
        $this->assertSame([], $report['observaciones']);
    }

    public function test_old_template_and_empty_seniority_are_not_invented(): void
    {
        $old = $this->row();
        unset($old['datos']['fecha_antiguedad']);
        $report = (new PadronArchivoDiagnostico)->analizar([$old, $this->row(['fecha_antiguedad' => ''], 3)]);
        $this->assertSame(1, $report['filas_con_columna_antiguedad']);
        $this->assertSame(0, $report['filas_con_antiguedad_valida']);
        $this->assertSame(0, $report['filas_con_errores_lector']);
    }

    public function test_private_date_mapping_preserves_chronology_and_excess_counts_before_after_selection(): void
    {
        $rows = [
            $this->row(['tipocontrato' => 'REEMPLAZO', 'jornada' => 44, 'fecha_ingreso' => '2026-03-01', 'fecha_termino' => '2026-03-31']),
            $this->row(['tipocontrato' => 'REEMPLAZO', 'jornada' => 44, 'fecha_ingreso' => '2026-05-01', 'fecha_termino' => '2026-05-31'], 3),
        ];
        $report = (new PadronArchivoDiagnostico)->analizar($rows);
        $this->assertSame(1, $report['rut_docentes_sobre_44_antes_seleccion']);
        $this->assertSame(2, $report['filas_de_rut_docentes_sobre_44_antes_seleccion']);
        $this->assertSame(0, $report['rut_docentes_sobre_44_horas']);
        $this->assertSame(1, $report['reemplazos_anteriores_omitidos']);
        $this->assertSame([2], array_values($report['observaciones'])[0]['primeras_filas']);
        $this->assertStringNotContainsString('2026-03-01', json_encode($report));
        $this->assertStringNotContainsString('2026-05-31', json_encode($report));
    }

    public function test_large_synthetic_batch_keeps_full_counts_with_bounded_detail(): void
    {
        $rows = [];
        for ($i = 0; $i < 6500; $i++) {
            $body = 90000000 + $i;
            $rows[] = $this->row(['rut' => $body.RutChile::dv($body), 'jornada' => 45, 'fecha_antiguedad' => '31/02/2026'], $i + 2);
        }
        $report = (new PadronArchivoDiagnostico)->analizar($rows);
        $this->assertSame(6500, $report['filas']);
        $this->assertSame(6500, $report['identificadores_rut_distintos']);
        $this->assertSame(6500, $report['filas_con_errores_lector']);
        $this->assertSame(6500, $report['rut_docentes_sobre_44_horas']);
        $this->assertSame(0, $report['filas_duplicadas']);
        $this->assertCount(20, $report['excesos_primeros_20']);
        $issue = array_values($report['observaciones'])[0];
        $this->assertSame(6500, $issue['cantidad']);
        $this->assertSame(range(2, 21), $issue['primeras_filas']);
    }
}
