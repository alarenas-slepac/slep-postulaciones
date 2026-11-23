<?php

namespace Tests\Feature;

use App\Exports\DotacionInformeFinanciamientoExport;
use App\Jobs\GenerarExcelFinanciamiento;
use App\Models\DotacionFinanciamientoExportacion;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\IsolatedSecurityTestCase;
use Tests\Support\PreparaExcelFinanciamiento;

class DotacionFinanciamientoExportacionTest extends IsolatedSecurityTestCase
{
    use PreparaExcelFinanciamiento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        (require database_path('migrations/2026_01_23_000001_create_establecimientos_table.php'))->up();
        Schema::create('modules', fn (Blueprint $table) => $table->string('key'));
        $this->instalarExportacionFinanciamiento();
        $this->actingAs($this->testUser(role: 3))->withSession(['active_role' => 'admin']);
        $disk = Storage::disk('local');
        $disk->put('vistas/layouts/app.blade.php', '<html lang="es"><body>@yield("content")@stack("scripts")</body></html>');
        $this->app['view']->getFinder()->prependLocation($disk->path('vistas'));
    }

    public function test_solicita_sin_generar_en_http_y_reutiliza_pendiente_en_doble_envio(): void
    {
        $this->mock(DotacionInformeFinanciamientoExport::class)->shouldNotReceive('guardar');
        $filtros = ['anio' => 2027, 'q' => '  99999  ', 'comuna' => 'Comuna sintética'];
        $legacy = route('admin.dotacion-establecimiento.index', ['informe_funciones' => 1, 'export_financiamiento' => 1] + $filtros);
        $confirmacion = route('admin.dotacion-establecimiento.financiamiento.create', ['anio' => 2027, 'q' => '99999', 'comuna' => $filtros['comuna']]);
        $this->get($legacy)->assertRedirect($confirmacion);
        $this->get($confirmacion)->assertOk()->assertSee('Preparar Excel');
        $this->assertSame(0, DB::table('jobs')->count());

        $store = route('admin.dotacion-establecimiento.financiamiento.store');
        $this->post($store, $filtros)->assertStatus(303);
        $solicitud = DotacionFinanciamientoExportacion::sole();
        $show = route('admin.dotacion-establecimiento.financiamiento.show', $solicitud->id);
        $this->post($store, $filtros)->assertRedirect($show);
        $this->post($store, ['anio' => 2026])->assertRedirect($show)->assertSessionHas('financiamiento_aviso');
        $this->get($show)->assertOk()->assertSee('En cola')->assertSee('Actualizar estado');
        $this->getJson($show)->assertOk()->assertExactJson(['estado' => 'pendiente']);
        $this->assertSame(['q' => '99999', 'comuna' => $filtros['comuna']], $solicitud->filtros);
        $this->assertSame(1, DB::table('jobs')->where('queue', GenerarExcelFinanciamiento::COLA)->count());
        Storage::disk('local')->assertMissing($solicitud->ruta());
    }

    public function test_descarga_exige_propietario_rol_activo_y_archivo_listo_vigente(): void
    {
        $owner = auth()->user();
        $this->post(route('admin.dotacion-establecimiento.financiamiento.store'), ['anio' => 2027])->assertStatus(303);
        $solicitud = DotacionFinanciamientoExportacion::sole();
        $show = route('admin.dotacion-establecimiento.financiamiento.show', $solicitud->id);
        $download = route('admin.dotacion-establecimiento.financiamiento.download', $solicitud->id);
        $this->get($download)->assertRedirect($show);

        $this->actingAs($this->testUser(2, 3));
        $this->getJson($show)->assertNotFound();
        $this->get($download)->assertNotFound();
        $this->actingAs($this->testUser(3, 1));
        $this->get($download)->assertForbidden();
        $owner->assignRole('postulante');
        $this->actingAs($owner)->withSession(['active_role' => 'postulante']);
        $this->get($download)->assertForbidden();
        $this->actingAs($owner)->withSession(['active_role' => 'admin']);

        $solicitud->update(['estado' => 'listo']);
        Storage::disk('local')->put($solicitud->ruta(), 'archivo sintético');
        $this->get($download)->assertOk()->assertDownload($solicitud->nombreDescarga());
        $solicitud->update(['expires_at' => now()->subSecond()]);
        $this->get($download)->assertRedirect($show);
        $this->getJson($show)->assertExactJson(['estado' => 'vencido']);
        $this->assertSame(1, (new DotacionFinanciamientoExportacion)->pruneAll());
        Storage::disk('local')->assertMissing($solicitud->ruta());
        $this->get($show)->assertNotFound();
    }

    public function test_falla_del_generador_no_publica_archivo_parcial_y_permite_reintentar(): void
    {
        $store = route('admin.dotacion-establecimiento.financiamiento.store');
        $this->post($store, ['anio' => 2027])->assertStatus(303);
        $solicitud = DotacionFinanciamientoExportacion::sole();
        $exportador = $this->mock(DotacionInformeFinanciamientoExport::class);
        $exportador->shouldReceive('guardar')->once()->andReturnUsing(function ($builder, $anio, $path): void {
            file_put_contents($path, 'incompleto');
            throw new RuntimeException('Fallo sintético.');
        });

        try {
            (new GenerarExcelFinanciamiento($solicitud->id))->handle($exportador);
            $this->fail('El worker debe recibir el fallo de generación.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo sintético.', $exception->getMessage());
        }

        Storage::disk('local')->assertMissing([$solicitud->ruta(), $solicitud->ruta().'.tmp']);
        $this->getJson(route('admin.dotacion-establecimiento.financiamiento.show', $solicitud->id))
            ->assertExactJson(['estado' => 'error']);
        $this->post($store, ['anio' => 2027])->assertStatus(303);
        $this->assertSame(2, DotacionFinanciamientoExportacion::count());
    }

    public function test_falla_al_encolar_revierte_la_solicitud(): void
    {
        Schema::drop('jobs');
        $this->post(route('admin.dotacion-establecimiento.financiamiento.store'), ['anio' => 2027])
            ->assertSessionHasErrors('financiamiento');
        $this->assertSame(0, DotacionFinanciamientoExportacion::count());
    }
}
