<?php

namespace Tests\Unit;

use App\Models\DeclaracionSostenedor;
use App\Support\DotacionAsignacionElegibilidad2027;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DotacionAsignacionElegibilidad2027Test extends TestCase
{
    public static function titles(): array
    {
        return [
            'diferencial' => ['Pedagogía en Educación Diferencial', true],
            'educadora' => ['Educadora Diferencial', true],
            'profesor' => ['Profesor de Educación Diferencial', true],
            'parvularia' => ['Pedagogía en Educación de Párvulos', false],
            'inglés' => ['Pedagogía en Inglés', false],
            'sin título' => ['', true],
            'marcador del SGA' => ['Sin título declarado', true],
            'marcador con espacios' => ['  SIN TÍTULO  ', true],
            'título por revisar' => ['Título pendiente de validación', false],
        ];
    }

    #[DataProvider('titles')]
    public function test_bolsa_se_limita_a_diferencial_y_ausencia_de_titulo(string $title, bool $expected): void
    {
        $this->assertSame($expected, DotacionAsignacionElegibilidad2027::puedeRecibirBolsaDiferencial(['titulo' => $title]));
    }

    public function test_titulo_de_declaracion_no_puede_suplantarse_y_filtrado_conserva_orden(): void
    {
        $declaration = new DeclaracionSostenedor(['nombre_titulo' => 'Pedagogía en Inglés']);
        $this->assertFalse(DotacionAsignacionElegibilidad2027::puedeRecibirBolsaDiferencial([
            'declaracion' => $declaration, 'titulo' => 'Educación Diferencial',
        ]));
        $candidates = collect([
            ['rut' => '99000001K', 'titulo' => 'Pedagogía en Inglés'],
            ['rut' => '99000002K', 'titulo' => ''],
            ['rut' => '99000003K', 'titulo' => 'Educación Diferencial'],
        ]);
        $filtered = DotacionAsignacionElegibilidad2027::candidatosParaPrelacion($candidates, ['tipo_asignacion' => 'pie_educadora_diferencial']);
        $this->assertSame(['99000002K', '99000003K'], $filtered->pluck('rut')->all());
        $this->assertSame($candidates, DotacionAsignacionElegibilidad2027::candidatosParaPrelacion($candidates, ['tipo_asignacion' => 'plan_estudio']));
    }

    public function test_solo_los_tipos_de_funcion_tienen_orden_informativo(): void
    {
        foreach (['funcion_directiva', 'funcion_tecnico_pedagogica', 'plan_normativo', 'otra_funcion'] as $type) {
            $this->assertTrue(DotacionAsignacionElegibilidad2027::esFuncionSinPrelacion(['tipo_asignacion' => $type]));
        }
        foreach (['plan_estudio', 'pie_colaborativo', 'pie_educadora_diferencial', 'acompanamiento_parvularia', 'reserva_no_normativa'] as $type) {
            $this->assertFalse(DotacionAsignacionElegibilidad2027::esFuncionSinPrelacion(['tipo_asignacion' => $type]));
        }
    }
}
