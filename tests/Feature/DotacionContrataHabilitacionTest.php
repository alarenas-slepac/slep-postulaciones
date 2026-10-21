<?php

namespace Tests\Feature;

use App\Models\Establecimiento;
use App\Services\Dotacion\ContratacionHabilitacionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\IsolatedSecurityTestCase;

class DotacionContrataHabilitacionTest extends IsolatedSecurityTestCase
{
    public function test_habilita_cupos_sin_limite_de_brecha_y_conserva_maximo_individual_y_retiro(): void
    {
        $this->createTables();

        try {
            $establecimiento = Establecimiento::query()->create(['nombre_establecimiento' => 'Escuela de prueba']);
            $otro = Establecimiento::query()->create(['nombre_establecimiento' => 'Otra escuela']);
            $service = new ContratacionHabilitacionService;

            $service->habilitar($establecimiento, 2027, 'parvularia', 2, 15, 7);
            $this->assertSame(2, DB::table('dotacion_contrata_habilitaciones')->where('bloque', 'parvularia')->count());
            $this->assertSame(30.0, (float) DB::table('dotacion_contrata_habilitaciones')->where('bloque', 'parvularia')->sum('horas'));

            $service->habilitar($establecimiento, 2027, 'parvularia', 1, 0.01, 7);
            $this->assertSame(30.01, (float) DB::table('dotacion_contrata_habilitaciones')->where('bloque', 'parvularia')->sum('horas'));

            $service->habilitar($establecimiento, 2027, 'pie', 1, 44, 7);
            $this->assertSame(4, DB::table('dotacion_contrata_habilitaciones')->count());
            $this->assertSame(44.0, (float) DB::table('dotacion_contrata_habilitaciones')->where('bloque', 'pie')->value('horas'));

            try {
                $service->habilitar($establecimiento, 2027, 'pie', 1, 44.01, 7);
                $this->fail('Cada funcionario debe tener como máximo 44 horas.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('horas', $exception->errors());
            }

            $id = (int) DB::table('dotacion_contrata_habilitaciones')->where('bloque', 'pie')->value('id');
            $this->assertFalse($service->revocar($otro, 2027, $id));
            $this->assertFalse($service->revocar($establecimiento, 2026, $id));
            $this->assertTrue($service->revocar($establecimiento, 2027, $id));
            $this->assertSame(3, DB::table('dotacion_contrata_habilitaciones')->count());
        } finally {
            Schema::dropIfExists('dotacion_contrata_habilitaciones');
            Schema::dropIfExists('establecimientos');
        }
    }

    public function test_crea_en_los_tres_bloques_sin_consultar_brecha_y_sin_modificar_otros_anos(): void
    {
        $this->createTables();

        try {
            $establecimiento = Establecimiento::query()->create(['nombre_establecimiento' => 'Escuela de prueba']);
            $service = new class extends ContratacionHabilitacionService
            {
                protected function resumen(Establecimiento $establecimiento, int $anio): array
                {
                    throw new \LogicException('Crear un cupo no debe exigir un cálculo de brecha.');
                }
            };

            $this->assertSame(-27.0, ContratacionHabilitacionService::brecha([
                'horas_contrato_pie_necesarias' => 224,
                'horas_contrato_docente_pie' => 251,
            ], 'pie'));

            foreach (ContratacionHabilitacionService::BLOQUES as $bloque) {
                $service->habilitar($establecimiento, 2027, $bloque, 2, 44, 7);
            }
            $this->assertSame(6, DB::table('dotacion_contrata_habilitaciones')->where('anio', 2027)->count());
            $this->assertSame(264.0, (float) DB::table('dotacion_contrata_habilitaciones')->sum('horas'));
            $this->assertSame(0, DB::table('dotacion_contrata_habilitaciones')->where('anio', 2026)->count());
        } finally {
            Schema::dropIfExists('dotacion_contrata_habilitaciones');
            Schema::dropIfExists('establecimientos');
        }
    }

    public function test_la_interfaz_ofrece_todos_los_bloques_con_o_sin_sobredotacion_solo_a_roles_autorizados(): void
    {
        $vars = [
            'establecimiento' => new Establecimiento(['nombre_establecimiento' => 'Escuela de prueba']),
            'anio' => 2027,
            'resumen' => ['brecha_dotacion_parvularia' => 30, 'brecha_dotacion_pie' => -27],
            'contrataHabilitacionesTableReady' => true,
            'contrataHabilitaciones' => collect(),
            'canManageContrataHabilitaciones' => true,
            'fmt' => fn ($value) => (string) $value,
            'errors' => new \Illuminate\Support\ViewErrorBag,
        ];
        $vars['establecimiento']->id = 1;

        $html = \Illuminate\Support\Facades\Blade::render("@include('admin.dotacion-establecimiento.partials._contrata_habilitaciones')", $vars);
        $this->assertStringContainsString('value="parvularia"', $html);
        $this->assertStringContainsString('value="pie"', $html);
        $this->assertStringContainsString('value="plan_estudio"', $html);
        $this->assertSame(3, substr_count($html, 'name="cantidad"'));
        $this->assertSame(3, substr_count($html, 'max="44"'));
        $this->assertStringContainsString('con o sin sobredotación', $html);
        $this->assertStringContainsString('para 2027, desde su creación', $html);

        $htmlSoloLectura = \Illuminate\Support\Facades\Blade::render("@include('admin.dotacion-establecimiento.partials._contrata_habilitaciones')", [
            ...$vars,
            'canManageContrataHabilitaciones' => false,
        ]);
        $this->assertStringNotContainsString('name="cantidad"', $htmlSoloLectura);
    }

    public function test_otros_roles_no_pueden_habilitar_cupos_aunque_envien_el_formulario(): void
    {
        $request = Request::create('/dotacion/contrata', 'POST', ['anio' => 2027, 'bloque' => 'parvularia', 'cantidad' => 1, 'horas' => 30]);
        $request->setUserResolver(fn () => new class
        {
            public function activeRoleName(): string
            {
                return 'funcionario_directivo_estab';
            }
        });

        $this->expectException(HttpException::class);
        app(\App\Http\Controllers\Admin\DotacionContrataHabilitacionController::class)
            ->store($request, new Establecimiento, app(ContratacionHabilitacionService::class));
    }

    public function test_rechaza_anos_bloques_cantidades_y_horas_invalidas_sin_crear_cupos(): void
    {
        $this->createTables();
        $ee = Establecimiento::create(['nombre_establecimiento' => 'Escuela sintética']);
        $service = new ContratacionHabilitacionService;
        foreach ([
            [2019, 'pie', 1, 44, 'anio'], [2101, 'pie', 1, 44, 'anio'],
            [2027, 'inexistente', 1, 44, 'bloque'], [2027, 'pie', 0, 44, 'horas'],
            [2027, 'pie', 101, 44, 'horas'], [2027, 'pie', 1, 0, 'horas'],
            [2027, 'pie', 1, 44.01, 'horas'],
        ] as [$anio, $bloque, $cantidad, $horas, $campo]) {
            try {
                $service->habilitar($ee, $anio, $bloque, $cantidad, $horas, null);
                $this->fail('Los datos inválidos deben rechazarse.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey($campo, $error->errors());
            }
        }
        $this->assertSame(0, DB::table('dotacion_contrata_habilitaciones')->count());
    }

    private function createTables(): void
    {
        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_establecimiento');
            $table->timestamps();
        });
        Schema::create('dotacion_contrata_habilitaciones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('establecimiento_id');
            $table->unsignedSmallInteger('anio');
            $table->string('bloque', 20);
            $table->decimal('horas', 5, 2);
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
        });
    }
}
