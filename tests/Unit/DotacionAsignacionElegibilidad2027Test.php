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
        $this->assertSame($candidates->all(), DotacionAsignacionElegibilidad2027::candidatosParaPrelacion($candidates, ['tipo_asignacion' => 'plan_estudio'])->all());
    }

    public static function tiposAsignacion(): array
    {
        $types = [
            'plan_estudio', 'acompanamiento_parvularia', 'pie_colaborativo', 'pie_educadora_diferencial',
            'funcion_directiva', 'funcion_tecnico_pedagogica', 'plan_normativo', 'otra_funcion', 'reserva_no_normativa',
        ];

        return array_combine($types, array_map(fn (string $type) => [$type], $types));
    }

    #[DataProvider('tiposAsignacion')]
    public function test_docente_justificado_queda_fuera_de_los_candidatos_de_asignacion(string $type): void
    {
        $candidates = collect([
            ['rut' => '99000001K', 'titulo' => 'Educación Diferencial', 'sobredotacion_justificada' => true],
            ['rut' => '99000002K', 'titulo' => 'Educación Diferencial', 'sobredotacion_justificada' => false],
            ['rut' => '99000003K', 'titulo' => ''],
        ]);
        $assignment = ['tipo_asignacion' => $type];

        $this->assertTrue(DotacionAsignacionElegibilidad2027::excluidoDeAsignacion($candidates[0], $assignment));
        $this->assertSame(['99000002K', '99000003K'], DotacionAsignacionElegibilidad2027::candidatosParaPrelacion($candidates, $assignment)->pluck('rut')->all());
        $this->assertCount(3, $candidates);
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

    public function test_religion_usa_la_asignatura_real_y_pie_colaborativo_no_exime_bolsa_diferencial(): void
    {
        foreach (['Religión', 'RELIGION CATÓLICA', 'Religión Evangélica'] as $name) {
            $this->assertTrue(DotacionAsignacionElegibilidad2027::esReligion(['tipo_asignacion' => 'plan_estudio', 'titulo' => $name]));
        }
        $this->assertFalse(DotacionAsignacionElegibilidad2027::esReligion(['tipo_asignacion' => 'plan_estudio', 'titulo' => 'Religión', 'asignatura_oficial' => 'Matemática']));
        $this->assertFalse(DotacionAsignacionElegibilidad2027::esReligion(['tipo_asignacion' => 'otra_funcion', 'titulo' => 'Religión']));
        $this->assertTrue(DotacionAsignacionElegibilidad2027::esTrabajoColaborativoSinPrelacion(['tipo_asignacion' => 'pie_colaborativo']));
        $this->assertFalse(DotacionAsignacionElegibilidad2027::esTrabajoColaborativoSinPrelacion(['tipo_asignacion' => 'pie_educadora_diferencial']));
    }
}
