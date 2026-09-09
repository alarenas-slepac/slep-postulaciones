<?php

namespace Tests\Unit;

use App\Models\Establecimiento;
use App\Models\ReemplazoPersonal;
use App\Support\ReemplazoSolicitudReglaMinima;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReemplazoSolicitudReglaMinimaTest extends TestCase
{
    #[DataProvider('rangosCalendario')]
    public function test_cuenta_fechas_inclusivas_sin_alterar_las_fechas_recibidas(string $desde, string $hasta, int $dias): void
    {
        $inicio = Carbon::parse($desde, 'America/Santiago');
        $termino = Carbon::parse($hasta, 'America/Santiago');
        $inicioOriginal = $inicio->copy();
        $terminoOriginal = $termino->copy();

        $resultado = (new ReemplazoSolicitudReglaMinima)->evaluar(
            new Establecimiento,
            new ReemplazoPersonal,
            null,
            $inicio,
            $termino,
        );

        $this->assertSame($dias, $resultado['duracion_dias']);
        $this->assertEquals($inicioOriginal, $inicio);
        $this->assertEquals($terminoOriginal, $termino);
    }

    public static function rangosCalendario(): array
    {
        return [
            'caso informado' => ['2026-09-06', '2026-10-03', 28],
            'mismo dia con salto horario' => ['2026-09-06', '2026-09-06', 1],
            'dia siguiente al salto horario' => ['2026-09-06', '2026-09-07', 2],
            'cruza inicio horario verano' => ['2026-09-05', '2026-09-07', 3],
            'cruza termino horario verano' => ['2026-04-04', '2026-04-06', 3],
            'cambio de mes' => ['2026-01-31', '2026-02-01', 2],
            'cambio de anio' => ['2026-12-31', '2027-01-01', 2],
            'anio bisiesto' => ['2028-02-28', '2028-03-01', 3],
            'horas distintas no cambian las fechas' => ['2026-09-06 23:45:00', '2026-10-03 00:15:00', 28],
            'rango inverso' => ['2026-09-07', '2026-09-06', 0],
        ];
    }

    #[DataProvider('limitesMinimos')]
    public function test_respeta_los_minimos_en_el_cambio_de_horario(string $estatuto, bool $salaCuna, int $dias, bool $permitido): void
    {
        $resultado = (new ReemplazoSolicitudReglaMinima)->evaluar(
            (new Establecimiento)->forceFill(['sala_cuna' => $salaCuna]),
            (new ReemplazoPersonal)->forceFill(['estatuto' => $estatuto]),
            null,
            Carbon::parse('2026-09-06', 'America/Santiago'),
            Carbon::parse('2026-09-06', 'America/Santiago')->addDays($dias - 1),
        );

        $this->assertSame($dias, $resultado['duracion_dias']);
        $this->assertSame($permitido, $resultado['permitido']);
    }

    public static function limitesMinimos(): array
    {
        return [
            'docente siete' => ['DOCENTE', false, 7, false],
            'docente ocho' => ['DOCENTE', false, 8, true],
            'sala cuna siete' => ['ASISTENTE', true, 7, false],
            'sala cuna ocho' => ['ASISTENTE', true, 8, true],
            'asistente seis' => ['ASISTENTE', false, 6, false],
            'asistente siete' => ['ASISTENTE', false, 7, true],
        ];
    }

    public function test_continuidad_no_acreditada_conserva_el_rechazo_y_la_duracion_correcta(): void
    {
        $resultado = (new ReemplazoSolicitudReglaMinima)->evaluar(
            new Establecimiento,
            new ReemplazoPersonal,
            null,
            Carbon::parse('2026-09-06', 'America/Santiago'),
            Carbon::parse('2026-10-03', 'America/Santiago'),
            continuidadSolicitada: true,
        );

        $this->assertSame(28, $resultado['duracion_dias']);
        $this->assertFalse($resultado['permitido']);
        $this->assertFalse($resultado['es_continuidad']);
        $this->assertSame('continuidad_no_encontrada', $resultado['regla_minima_aplicada']);
    }
}
