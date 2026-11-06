<?php

namespace Tests\Unit;

use App\Support\DotacionDisposicionDatos;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionDisposicionCuposTest extends IsolatedSecurityTestCase
{
    private function docente(array $datos = [], array $asignaciones = []): array
    {
        return array_replace(['rut' => '99000001K', 'funcion' => 'DOCENTE', 'titulo' => 'Pedagogía en Educación Básica', 'horas_contrato' => 44,
            'horas_planta' => 0, 'horas_contrata' => 44, 'asignaciones' => $asignaciones ?: [$this->asignacion()]], $datos);
    }

    private function asignacion(array $datos = []): array
    {
        return array_replace(['id' => 1, 'establecimiento_id' => 1, 'anio' => 2028, 'estado' => 'activa', 'tipo_asignacion' => 'otra_funcion',
            'docente_rut_normalizado' => '99000001K', 'horas_contrato' => 12, 'asignatura_nombre' => 'Apoyo docente'], $datos);
    }

    public function test_suma_solo_asignadas_de_cupos_y_contrata_completa_por_bloque(): void
    {
        $real = $this->docente([], [$this->asignacion(), $this->asignacion(['id' => 2, 'tipo_asignacion' => 'pie_educadora_diferencial', 'horas_contrato' => 4])]);
        $virtual = $this->docente(['rut' => 'VACANTE-1', 'cupo_contrata_id' => 1], [$this->asignacion(['horas_contrato' => 7])]);
        $pie = $this->docente(['rut' => '99000002K', 'titulo' => 'Pedagogía en Educación Diferencial']);
        $r = DotacionDisposicionDatos::cuposDestino([$real, $virtual, $pie], 1, 2028);
        $this->assertSame(12.0, $r['plan_estudio']['contrata']); $this->assertSame(7.0, $r['plan_estudio']['cupos']);
        $this->assertSame(19.0, $r['plan_estudio']['horas']); $this->assertSame(16.0, $r['pie']['horas']);
        $this->assertSame(0.0, $r['parvularia']['horas']);
    }

    public function test_excluye_planta_mixtos_sin_contrato_asistentes_y_cupos_sin_asignaciones(): void
    {
        foreach ([['horas_planta' => 1, 'horas_contrata' => 43], ['horas_planta' => 44, 'horas_contrata' => 0],
            ['horas_contrato' => null], ['estamento_cobertura' => 'asistente'], ['asignaciones' => []],
            ['cupo_contrata_id' => 1, 'asignaciones' => []]] as $datos) {
            $this->assertSame(0.0, DotacionDisposicionDatos::cuposDestino([$this->docente($datos)], 1, 2028)['plan_estudio']['horas']);
        }
    }

    public static function funciones(): array
    {
        return array_map(fn ($f) => [$f], ['Director', 'Directora ADP', 'Inspector General', 'Inspectora general', 'Jefe UTP', 'Jefa U.T.P.',
            'Jefe Unidad Técnico Pedagógica', 'Coordinador(a) PIE', 'COORD. PIE', 'Encargada de Convivencia Educativa', 'Convivencia Escolar']);
    }

    #[DataProvider('funciones')]
    public function test_excluye_cargo_del_docente_y_funcion_de_asignacion(string $funcion): void
    {
        $this->assertSame(0.0, DotacionDisposicionDatos::cuposDestino([$this->docente(['funcion' => $funcion])], 1, 2028)['plan_estudio']['horas']);
        $this->assertSame(0.0, DotacionDisposicionDatos::cuposDestino([$this->docente([], [$this->asignacion(['asignatura_nombre' => $funcion])])], 1, 2028)['plan_estudio']['horas']);
    }

    public function test_no_cuenta_fuero_maternal_gremial_y_lactancia_con_fuero(): void
    {
        foreach ([['motivo' => 'fuero_maternal'], ['motivo' => 'horas_gremiales'], ['motivo' => 'fuero_gremial'],
            ['motivo' => 'horas_lactancia', 'posee_fuero_maternal' => true]] as $situacion) {
            $this->assertSame(0.0, DotacionDisposicionDatos::cuposDestino([$this->docente(['exclusion_docente' => $situacion])], 1, 2028)['plan_estudio']['horas']);
            $this->assertSame(0.0, DotacionDisposicionDatos::cuposDestino([$this->docente()], 1, 2028, ['99000001K' => $situacion])['plan_estudio']['horas']);
        }
        $this->assertSame(12.0, DotacionDisposicionDatos::cuposDestino([$this->docente(['exclusion_docente' => ['motivo' => 'horas_lactancia', 'posee_fuero_maternal' => false]])], 1, 2028)['plan_estudio']['horas']);
    }

    public function test_solo_asignaciones_activas_del_ano_y_destino_sin_reservas_o_directivas(): void
    {
        $asignaciones = [$this->asignacion()];
        foreach ([['anio' => 2027], ['establecimiento_id' => 2], ['estado' => 'no_renovada'], ['tipo_asignacion' => 'reserva_no_normativa'],
            ['tipo_asignacion' => 'funcion_directiva'], ['estamento_cobertura' => 'asistente'],
            ['tipo_asignacion' => 'funcion_tecnico_pedagogica', 'subtipo_asignacion' => 'pie']] as $c) {
            $asignaciones[] = $this->asignacion(['id' => count($asignaciones) + 1] + $c);
        }
        $this->assertSame(12.0, DotacionDisposicionDatos::cuposDestino([$this->docente([], $asignaciones)], 1, 2028)['plan_estudio']['horas']);
    }
}
