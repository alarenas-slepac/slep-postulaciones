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
use Tests\TestCase;

class DotacionContrataHabilitacionTest extends TestCase
{
    public function test_habilita_cupos_sin_superar_brecha_ni_44_horas_y_permite_retirarlos(): void
    {
        $this->createTables();

        try {
            $establecimiento = Establecimiento::query()->create(['nombre_establecimiento' => 'Escuela de prueba']);
            $otro = Establecimiento::query()->create(['nombre_establecimiento' => 'Otra escuela']);
            $service = new class extends ContratacionHabilitacionService
            {
                protected function resumen(Establecimiento $establecimiento, int $anio): array
                {
                    return [
                        'contrato_educacion_parvularia_mas_trabajo_colaborativo_pie' => 116,
                        'horas_contrato_docentes_parvularia' => 86,
                        'horas_contrato_pie_necesarias' => 224,
                        'horas_contrato_docente_pie' => 180,
                    ];
                }
            };

            $service->habilitar($establecimiento, 2027, 'parvularia', 2, 15, 7);
            $this->assertSame(2, DB::table('dotacion_contrata_habilitaciones')->where('bloque', 'parvularia')->count());
            $this->assertSame(30.0, (float) DB::table('dotacion_contrata_habilitaciones')->where('bloque', 'parvularia')->sum('horas'));

            try {
                $service->habilitar($establecimiento, 2027, 'parvularia', 1, 0.01, 7);
                $this->fail('No se debe superar la brecha de Parvularia.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('horas', $exception->errors());
            }

            $service->habilitar($establecimiento, 2027, 'pie', 1, 44, 7);
            $this->assertSame(3, DB::table('dotacion_contrata_habilitaciones')->count());
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
            $this->assertSame(2, DB::table('dotacion_contrata_habilitaciones')->count());
        } finally {
            Schema::dropIfExists('dotacion_contrata_habilitaciones');
            Schema::dropIfExists('establecimientos');
        }
    }

    public function test_no_habilita_cupos_en_bloque_con_sobredotacion(): void
    {
        $this->createTables();

        try {
            $establecimiento = Establecimiento::query()->create(['nombre_establecimiento' => 'Escuela de prueba']);
            $service = new class extends ContratacionHabilitacionService
            {
                protected function resumen(Establecimiento $establecimiento, int $anio): array
                {
                    return ['horas_contrato_pie_necesarias' => 224, 'horas_contrato_docente_pie' => 251];
                }
            };

            $this->assertSame(-27.0, ContratacionHabilitacionService::brecha([
                'horas_contrato_pie_necesarias' => 224,
                'horas_contrato_docente_pie' => 251,
            ], 'pie'));

            $this->expectException(ValidationException::class);
            $service->habilitar($establecimiento, 2027, 'pie', 1, 27, 7);
        } finally {
            Schema::dropIfExists('dotacion_contrata_habilitaciones');
            Schema::dropIfExists('establecimientos');
        }
    }

    public function test_la_interfaz_ofrece_habilitar_solo_con_brecha_y_rol_autorizado(): void
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
        $this->assertStringNotContainsString('value="pie"', $html);
        $this->assertStringContainsString('max="30"', $html);

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
