<?php

namespace Tests\Unit;

use App\Models\DotacionDocenteAsignacion;
use App\Models\ReemplazoPersonal;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class DotacionDocenteDetalleHorasTest extends TestCase
{
    public function test_consolida_todas_las_lineas_del_ultimo_mes_y_separa_planta_contrata(): void
    {
        $personal = collect([
            $this->personal(1, '11111111-1', 2026, 6, 'PLANTA', 44, 'anterior-planta'),
            $this->personal(2, '22222222-2', 2026, 6, 'CONTRATA', 30, 'solo-mes-anterior'),
            $this->personal(3, '11111111-1', 2026, 7, 'PLANTA', 30, 'actual-planta'),
            $this->personal(4, '11111111-1', 2026, 7, 'CONTRATA', 14, 'actual-contrata'),
            $this->personal(5, '11111111-1', 2026, 7, 'CONTRATA', 14, 'actual-contrata'),
        ]);

        /** @var Collection $resultado */
        $resultado = $this->invokePrivate('consolidarPersonalUltimoPeriodo', [$personal]);

        $this->assertCount(1, $resultado);
        $grupo = $resultado->first();

        $this->assertSame(2026, $grupo['anio']);
        $this->assertSame(7, $grupo['mes']);
        $this->assertSame(2, $grupo['registros']);
        $this->assertSame(44.0, $grupo['jornada_total']);
        $this->assertSame(30.0, $grupo['jornada_planta_total']);
        $this->assertSame(14.0, $grupo['jornada_contrata_total']);
        $this->assertEqualsCanonicalizing(['PLANTA', 'CONTRATA'], explode(' / ', $grupo['tipos_contrato']));
    }

    public function test_detalla_y_agrupa_solo_otras_funciones_con_horas_positivas(): void
    {
        $asignaciones = collect([
            $this->asignacion('otra_funcion', 'Coordinación medioambiental', 3),
            $this->asignacion('otra_funcion', 'Coordinación medioambiental', 2),
            $this->asignacion('otra_funcion', 'Apoyo biblioteca', 4),
            $this->asignacion('otra_funcion', 'Sin horas', 0),
            $this->asignacion('funcion_directiva', 'Dirección', 44),
        ]);

        $resultado = $this->invokePrivate('detalleOtrasFunciones', [$asignaciones]);

        $this->assertSame([
            ['nombre' => 'Apoyo biblioteca', 'horas' => 4.0],
            ['nombre' => 'Coordinación medioambiental', 'horas' => 5.0],
        ], $resultado);
    }

    public function test_vista_omite_calidades_en_cero_y_muestra_detalle_de_otras_horas(): void
    {
        $html = view('admin.dotacion-establecimiento.partials._docentes', [
            'docentes' => collect([$this->docenteVista()]),
        ])->render();

        $this->assertStringContainsString('Planta 44 h', $html);
        $this->assertStringNotContainsString('Contrata 0 h', $html);
        $this->assertStringNotContainsString('Contrata: 0 h', $html);
        $this->assertStringContainsString('Detalle de otras horas', $html);
        $this->assertStringContainsString('Coordinación medioambiental', $html);
    }

    private function personal(int $id, string $rut, int $anio, int $mes, string $tipo, int $jornada, string $hash): ReemplazoPersonal
    {
        $personal = new ReemplazoPersonal([
            'rut' => $rut,
            'nombre' => 'Docente de prueba',
            'anio' => $anio,
            'mes' => $mes,
            'tipocontrato' => $tipo,
            'jornada' => $jornada,
            'estatuto' => 'DOCENTE',
            'escalafon' => 'DOCENTE AULA',
            'row_hash' => $hash,
        ]);
        $personal->id = $id;

        return $personal;
    }

    private function asignacion(string $tipo, string $nombre, float $horas): DotacionDocenteAsignacion
    {
        return new DotacionDocenteAsignacion([
            'tipo_asignacion' => $tipo,
            'asignatura_nombre' => $nombre,
            'horas_contrato' => $horas,
        ]);
    }

    private function docenteVista(): array
    {
        return [
            'rut' => '11111111-1',
            'nombre' => 'Docente de prueba',
            'niveles_declarados' => 'Básica',
            'funcion' => 'Docente aula',
            'titulo' => 'Profesor(a)',
            'estamento' => 'DOCENTE',
            'horas_contrato' => 44.0,
            'horas_planta' => 44.0,
            'horas_contrata' => 0.0,
            'horas_aula' => 30.0,
            'horas_aula_65_35' => 30.0,
            'horas_aula_60_40' => 0.0,
            'horas_contrato_65_35' => 44.0,
            'horas_contrato_60_40' => 0.0,
            'horas_contrato_especial' => 0.0,
            'horas_funciones_total' => 5.0,
            'horas_directivas' => 0.0,
            'horas_tecnico_pedagogicas' => 0.0,
            'horas_pie' => 0.0,
            'horas_planes' => 0.0,
            'horas_otras_funciones' => 5.0,
            'otras_funciones_detalle' => [
                ['nombre' => 'Coordinación medioambiental', 'horas' => 5.0],
            ],
            'horas_asignadas_total' => 49.0,
            'diferencia' => -5.0,
            'estado_cuadratura' => [
                'key' => 'sobrecarga',
                'label' => 'Sobrecarga',
                'class' => 'text-bg-danger',
                'detalle' => 'Las horas asignadas superan las horas contratadas.',
            ],
            'horas_contrato_detalle' => null,
            'registros_contrato' => 1,
            'tipo_contrato' => 'PLANTA',
            'financiamiento' => 'Regular',
            'fuente_contrato' => 'reemplazos_personal',
            'mes' => 7,
            'anio' => 2026,
        ];
    }

    private function invokePrivate(string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod(DotacionEstablecimientoCalculator::class, $method);

        return $reflection->invoke(null, ...$arguments);
    }
}
