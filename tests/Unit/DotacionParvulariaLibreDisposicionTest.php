<?php

namespace Tests\Unit;

use App\Models\Curso;
use App\Models\DeclaracionSostenedor;
use App\Models\DotacionDocenteAsignacion;
use App\Models\EstablecimientoCurso;
use App\Support\DotacionEstablecimientoCalculator;
use ReflectionMethod;
use Tests\TestCase;

class DotacionParvulariaLibreDisposicionTest extends TestCase
{
    public function test_suma_solo_libre_disposicion_de_otro_docente_en_nt_con_jec_y_aplica_tope_por_curso(): void
    {
        $nt1Jec = $this->curso(10, 'NT1', 'Con JEC');
        $nt2SinJec = $this->curso(20, 'NT2', 'Sin JEC');
        $primeroJec = $this->curso(30, '1B', 'Con JEC');

        $asignaciones = collect([
            $this->asignacion(10, 4, 'Profesor de Educación General Básica'),
            $this->asignacion(10, 3, null),
            $this->asignacion(10, 6, 'Pedagogía en Educación de Párvulos'),
            $this->asignacion(10, 6, 'Profesor de Educación General Básica', 'asistente'),
            $this->asignacion(20, 6, 'Profesor de Educación General Básica'),
            $this->asignacion(30, 6, 'Profesor de Educación General Básica'),
        ]);

        $method = new ReflectionMethod(DotacionEstablecimientoCalculator::class, 'horasLibreDisposicionNtOtroDocente');
        $method->setAccessible(true);
        $resultado = $method->invoke(null, collect([$nt1Jec, $nt2SinJec, $primeroJec]), $asignaciones);

        $this->assertSame([
            10 => [
                'horas_plan' => 6.0,
                'asignaciones' => 2,
            ],
        ], $resultado);
    }

    public function test_calculador_y_vistas_declaran_conversion_65_35_sin_equivalencia_fija(): void
    {
        $calculator = file_get_contents(app_path('Support/DotacionEstablecimientoCalculator.php'));
        $resumen = file_get_contents(resource_path('views/admin/dotacion-establecimiento/partials/_resumen.blade.php'));
        $pdf = file_get_contents(resource_path('views/admin/dotacion-establecimiento/pdf.blade.php'));

        $this->assertIsString($calculator);
        $this->assertIsString($resumen);
        $this->assertIsString($pdf);
        $this->assertStringContainsString('DocenteHorasNoLectivasCalculator::PROPORCION_GENERAL', $calculator);
        $this->assertStringContainsString('HORAS_LIBRE_DISPOSICION_NT_OTRO_DOCENTE_MAX = 6.0', $calculator);
        $this->assertStringContainsString('su contrato equivalente se calcula mediante 65/35', $resumen);
        $this->assertStringContainsString('El contrato equivalente de esas horas se calcula mediante 65/35', $pdf);
        $this->assertStringNotContainsString('máximo de 8', $resumen);
    }

    private function curso(int $id, string $codigo, string $regimen): EstablecimientoCurso
    {
        $curso = new EstablecimientoCurso;
        $curso->id = $id;
        $curso->regimen_jec = $regimen;
        $curso->setRelation('curso', (new Curso)->forceFill([
            'codigo' => $codigo,
            'nombre' => $codigo,
        ]));
        $curso->setRelation('planEstudio', null);

        return $curso;
    }

    private function asignacion(
        int $cursoId,
        float $horas,
        ?string $titulo,
        ?string $estamento = 'docente'
    ): DotacionDocenteAsignacion {
        $asignacion = (new DotacionDocenteAsignacion)->forceFill([
            'establecimiento_curso_id' => $cursoId,
            'estamento_cobertura' => $estamento,
            'horas_plan_pedagogicas' => $horas,
        ]);
        $declaracion = $titulo === null
            ? null
            : (new DeclaracionSostenedor)->forceFill(['nombre_titulo' => $titulo]);
        $asignacion->setRelation('declaracionSostenedor', $declaracion);

        return $asignacion;
    }
}
