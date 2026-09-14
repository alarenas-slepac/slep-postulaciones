<?php

namespace Tests\Feature;

use App\Exports\DotacionResumenSobredotacionExport;
use App\Models\Establecimiento;
use App\Support\DotacionAsignacionCalculator;
use App\Support\DotacionEstablecimientoCalculator;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Tests\TestCase;

class DotacionContratoPadronTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $this->resetSchemaCaches();

        Schema::create('establecimientos', function (Blueprint $t): void {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento');
        });
        Schema::create('reemplazos_personal', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->string('rut'); $t->string('nombre');
            $t->integer('anio'); $t->integer('mes'); $t->boolean('vigente')->default(true);
            foreach (['jornada', 'jornada_basica', 'jornada_media'] as $field) {
                $t->integer($field)->nullable();
            }
            foreach (['tipocontrato', 'financiamiento', 'estatuto', 'escalafon', 'row_hash'] as $field) {
                $t->string($field)->nullable();
            }
            $t->timestamps();
        });
        Schema::create('declaracion_sostenedores', function (Blueprint $t): void {
            $t->id(); $t->string('rbd'); $t->string('rut'); $t->integer('horas_contratadas')->nullable();
            foreach (['nombres', 'apellido_paterno', 'apellido_materno', 'nombre_titulo', 'nombre_funcion', 'estamento'] as $field) {
                $t->string($field)->nullable();
            }
        });
        Schema::create('dotacion_docente_exclusiones', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio');
            $t->string('docente_rut'); $t->string('docente_rut_normalizado');
            $t->string('motivo'); $t->integer('horas');
        });
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t): void {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio');
            $t->integer('reemplazos_personal_id')->nullable();
            $t->integer('declaracion_sostenedor_id')->nullable();
            $t->string('docente_rut'); $t->string('docente_rut_normalizado');
            $t->string('docente_nombre'); $t->string('tipo_asignacion');
            $t->string('subtipo_asignacion')->nullable(); $t->string('asignatura_nombre')->nullable();
            $t->string('estamento_cobertura')->default('docente');
            $t->integer('horas_contrato'); $t->string('estado')->default('activa');
        });
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Establecimiento sintético A'],
            ['id' => 2, 'rbd' => 99998, 'nombre_establecimiento' => 'Establecimiento sintético B'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->resetSchemaCaches();
        parent::tearDown();
    }

    public static function horas(): array
    {
        return [
            'padrón menor que declaración' => [38, 44, 38.0],
            'padrón mayor que declaración' => [44, 38, 44.0],
            'cero no recupera declaración' => [0, 44, 0.0],
            'sin jornada no recupera declaración' => [null, 44, 0.0],
            'declaración sin horas' => [38, null, 38.0],
        ];
    }

    #[DataProvider('horas')]
    public function test_horas_docentes_provienen_solo_del_padron_sin_perder_declaracion(?int $padron, ?int $declaradas, float $esperadas): void
    {
        $this->personal(101, ['jornada' => $padron]);
        $this->declaracion(201, ['horas_contratadas' => $declaradas]);
        $before = $this->snapshot();

        $docente = $this->docentes()->sole();

        $this->assertSame($esperadas, $docente['horas_contrato_base']);
        $this->assertSame($esperadas, $docente['horas_contrato']);
        $this->assertSame('reemplazos_personal', $docente['fuente_contrato']);
        $this->assertSame($esperadas > 0 ? [$esperadas] : [], $docente['horas_contrato_componentes']);
        $this->assertSame('Persona Sintética Declarada', $docente['nombre']);
        $this->assertSame('Pedagogía en Educación de Párvulos', $docente['titulo']);
        $this->assertSame('Docente aula', $docente['funcion']);
        $this->assertSame(201, $docente['declaracion']->id);
        $this->assertTrue($docente['tiene_declaracion']);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_consolida_lineas_vigentes_por_rut_y_establecimiento_sin_duplicar_basica_media(): void
    {
        $this->personal(101, ['rut' => '11.111.111-1', 'jornada' => 30, 'jornada_basica' => 20, 'jornada_media' => 10]);
        $this->personal(102, ['jornada' => 8, 'jornada_basica' => 8, 'financiamiento' => 'SEP', 'tipocontrato' => 'CONTRATA']);
        $this->personal(103, ['mes' => 7, 'jornada' => 44]);
        $this->personal(104, ['vigente' => false]);
        $this->personal(105, ['tipocontrato' => 'REEMPLAZO']);
        $this->personal(106, ['tipocontrato' => 'SUPLENCIA']);
        $this->personal(107, ['establecimiento_id' => 2, 'jornada' => 6]);
        $this->personal(108, ['anio' => 2025]);
        $this->declaracion();

        $docente = $this->docentes()->sole();
        $this->assertSame(38.0, $docente['horas_contrato']);
        $this->assertSame([30.0, 8.0], $docente['horas_contrato_componentes']);
        $this->assertSame('30 + 8 = 38 h', $docente['horas_contrato_detalle']);
        $this->assertSame(2, $docente['registros_contrato']);
        $this->assertSame(30.0, $docente['horas_planta']);
        $this->assertSame(8.0, $docente['horas_contrata']);
        $this->assertSame(28.0, $docente['horas_basica_declarada']);
        $this->assertSame(10.0, $docente['horas_media_declarada']);
        $this->assertSame(6.0, DotacionEstablecimientoCalculator::docentes(Establecimiento::findOrFail(2), 2026)->sole()['horas_contrato']);

        $html = view('admin.dotacion-establecimiento.partials._docentes', ['docentes' => collect([$docente])])->render();
        $this->assertStringContainsString('30 + 8 = 38 h', $html);
        $this->assertStringContainsString('Reemplazos personal', $html);
    }

    public function test_mantiene_exclusiones_y_asignaciones_y_recalcula_diferencia_con_padron(): void
    {
        $this->personal();
        $this->declaracion();
        DB::table('dotacion_docente_exclusiones')->insert([
            'id' => 301, 'establecimiento_id' => 1, 'anio' => 2026,
            'docente_rut' => '111111111', 'docente_rut_normalizado' => '111111111',
            'motivo' => 'sumario_administrativo', 'horas' => 10,
        ]);
        DB::table('dotacion_docente_asignaciones')->insert([
            'id' => 401, 'establecimiento_id' => 1, 'anio' => 2026,
            'reemplazos_personal_id' => 101, 'declaracion_sostenedor_id' => 201,
            'docente_rut' => '111111111', 'docente_rut_normalizado' => '111111111',
            'docente_nombre' => 'Persona sintética', 'tipo_asignacion' => 'otra_funcion',
            'asignatura_nombre' => 'Función sintética', 'horas_contrato' => 30,
        ]);
        $before = $this->snapshot();
        $docente = $this->docentes()->sole();

        $this->assertSame(38.0, $docente['horas_contrato_base']);
        $this->assertSame(10.0, $docente['horas_excluidas']);
        $this->assertSame(28.0, $docente['horas_contrato']);
        $this->assertSame(30.0, $docente['horas_asignadas_total']);
        $this->assertSame(-2.0, $docente['diferencia']);
        $this->assertSame('sobrecarga', $docente['estado_cuadratura']['key']);
        $this->assertSame(401, $docente['asignaciones']->sole()->id);
        $this->assertSame(101, $docente['asignaciones']->sole()->reemplazos_personal_id);
        $this->assertSame(201, $docente['asignaciones']->sole()->declaracion_sostenedor_id);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_docente_sin_declaracion_conserva_horas_del_padron(): void
    {
        $this->personal();
        $docente = $this->docentes()->sole();
        $this->assertSame(38.0, $docente['horas_contrato']);
        $this->assertFalse($docente['tiene_declaracion']);
        $this->assertSame('Sin título declarado', $docente['titulo']);
    }

    public function test_declaracion_no_incorpora_un_docente_sin_contrato_vigente(): void
    {
        $this->personal(101, ['vigente' => false]);
        $this->declaracion();
        $this->assertCount(0, $this->docentes());
    }

    public function test_no_modifica_la_prioridad_contractual_de_asistentes(): void
    {
        $this->personal(101, ['estatuto' => 'AAEE', 'escalafon' => 'ASISTENTE']);
        $this->declaracion(201, ['estamento' => 'ASISTENTE']);
        $asistente = DotacionEstablecimientoCalculator::asistentes(Establecimiento::findOrFail(1), 2026)->sole();
        $this->assertSame(44.0, $asistente['horas_contrato']);
        $this->assertCount(0, $this->docentes());
    }

    public function test_contrato_pie_parvularia_y_filas_excel_consumen_la_nueva_base(): void
    {
        $this->personal(101, ['jornada' => 38]);
        $this->declaracion();
        $this->personal(102, ['rut' => '222222222', 'jornada' => 30]);
        $this->declaracion(202, ['rut' => '222222222', 'nombre_titulo' => 'Educación Diferencial']);
        $this->personal(103, ['rut' => '333333333', 'jornada' => 24]);
        $this->declaracion(203, ['rut' => '333333333', 'nombre_titulo' => 'Pedagogía en Matemática']);
        $docentes = $this->docentes();
        $total = (float) $docentes->sum('horas_contrato');
        $pieMethod = new ReflectionMethod(DotacionAsignacionCalculator::class, 'resumenContratoDocentePie');
        $pie = $pieMethod->invoke(null, collect(), $docentes, false);
        $parvularia = DotacionEstablecimientoCalculator::contratoParvularia($docentes, $total - $pie['total'], 60);

        $this->assertSame(92.0, $total);
        $this->assertSame(30.0, $pie['total']);
        $this->assertSame(38.0, $parvularia['horas_contrato_docentes_parvularia']);
        $this->assertSame(24.0, $parvularia['horas_contrato_docentes_aula_general']);
        $especial = $pieMethod->invoke(null, collect(), $docentes, true);
        $this->assertSame(0.0, $especial['total']);
        $this->assertSame(30.0, DotacionAsignacionCalculator::contratoPiePorDocente($docentes->firstWhere('rut', '222222222')));

        $export = new DotacionResumenSobredotacionExport;
        $row = $export->row(Establecimiento::findOrFail(1), $parvularia + [
            'docentes_total' => $docentes->count(), 'horas_contrato_docentes' => $total,
            'horas_contrato_docente_pie' => $pie['total'],
            'contrato_educacion_parvularia_mas_trabajo_colaborativo_pie' => 60,
            'contrato_plan_general_mas_trabajo_colaborativo_pie' => 20,
            'horas_contrato_pie_necesarias' => 20,
        ]);
        $this->assertSame([92.0, 24.0, 38.0, 30.0, -4.0, 22.0, -10.0], array_slice($row, 10));
        $book = $export->workbook(collect([$row]), 2026);
        try {
            $this->assertSame(92.0, $book->getActiveSheet()->getCell('K7')->getValue());
            $this->assertSame(38.0, $book->getActiveSheet()->getCell('M7')->getValue());
            $this->assertSame(30.0, $book->getActiveSheet()->getCell('N7')->getValue());
        } finally {
            $book->disconnectWorksheets();
        }
    }

    private function personal(int $id = 101, array $changes = []): void
    {
        DB::table('reemplazos_personal')->insert(array_replace([
            'id' => $id, 'establecimiento_id' => 1, 'rut' => '111111111', 'nombre' => 'Persona sintética padrón',
            'anio' => 2026, 'mes' => 8, 'jornada' => 38, 'jornada_basica' => 0, 'jornada_media' => 0,
            'tipocontrato' => 'PLANTA', 'financiamiento' => 'SUB.GENERAL',
            'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'row_hash' => 'sintetico-'.$id,
        ], $changes));
    }

    private function declaracion(int $id = 201, array $changes = []): void
    {
        DB::table('declaracion_sostenedores')->insert(array_replace([
            'id' => $id, 'rbd' => '99999', 'rut' => '111111111', 'horas_contratadas' => 44,
            'nombres' => 'Persona', 'apellido_paterno' => 'Sintética', 'apellido_materno' => 'Declarada',
            'nombre_titulo' => 'Pedagogía en Educación de Párvulos',
            'nombre_funcion' => 'Docente aula', 'estamento' => 'DOCENTE',
        ], $changes));
    }

    private function docentes(): \Illuminate\Support\Collection
    {
        return DotacionEstablecimientoCalculator::docentes(Establecimiento::findOrFail(1), 2026);
    }

    private function snapshot(): array
    {
        return collect(['reemplazos_personal', 'declaracion_sostenedores', 'dotacion_docente_asignaciones', 'dotacion_docente_exclusiones'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    private function resetSchemaCaches(): void
    {
        foreach ([DotacionEstablecimientoCalculator::class, DotacionAsignacionCalculator::class] as $class) {
            foreach (['schemaTableCache', 'schemaColumnCache'] as $name) {
                (new ReflectionProperty($class, $name))->setValue(null, []);
            }
        }
    }
}
