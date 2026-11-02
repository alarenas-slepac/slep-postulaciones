<?php

namespace Tests\Feature;

use App\Models\SolicitudReemplazo;
use App\Models\SolicitudReemplazoModificacionTermino;
use App\Models\SolicitudReemplazoObservacion;
use App\Support\TipoReemplazo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\IsolatedSecurityTestCase;

class SolicitudReemplazoReduccionLicenciaTest extends IsolatedSecurityTestCase
{
    private $actor;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('modules', function (Blueprint $table) { $table->id(); $table->string('key'); });
        Schema::create('reemplazos_personal', function (Blueprint $table) { $table->id(); $table->string('estatuto'); });
        Schema::create('solicitudes_reemplazo', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('reemplazo_personal_id'); $table->string('numero_solicitud')->default('PRUEBA');
            $table->string('tipo_reemplazo'); $table->string('estado'); $table->date('fecha_inicio'); $table->date('fecha_termino');
            $table->unsignedBigInteger('derivada_a_user_id')->nullable(); $table->unsignedBigInteger('cerrado_por_user_id')->nullable(); $table->timestamp('cerrado_at')->nullable();
            $table->string('orden_trabajo_pdf_path')->nullable(); $table->timestamp('orden_trabajo_creada_at')->nullable();
            $table->string('resolucion_docente_docx_path')->nullable(); $table->string('resolucion_docente_firmada_pdf_path')->nullable(); $table->timestamps();
        });
        Schema::create('solicitudes_reemplazo_observaciones', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('solicitud_reemplazo_id'); $table->string('etapa'); $table->string('accion');
            $table->string('estado_origen'); $table->string('estado_destino'); $table->text('motivo'); $table->text('observacion'); $table->unsignedBigInteger('user_id'); $table->timestamps();
        });
        (require database_path('migrations/2026_09_23_130000_create_solicitud_reemplazo_modificaciones_termino_table.php'))->up();
        (require database_path('migrations/2026_11_02_100000_add_respaldo_licencia_to_modificaciones_termino.php'))->up();
        DB::table('reemplazos_personal')->insert(['id' => 1, 'estatuto' => 'DOCENTE']);
        DB::table('roles')->insert([
            ['id' => 10, 'name' => 'coordinador_gdp', 'guard_name' => 'web'],
            ['id' => 11, 'name' => 'funcionario_slep', 'guard_name' => 'web'],
            ['id' => 12, 'name' => 'coordinador_uatp', 'guard_name' => 'web'],
        ]);
        $this->actor = $this->testUser(1, 3);
        $this->actingAs($this->actor)->withSession(['active_role' => 'admin']);
    }

    private function solicitud(array $extra = []): SolicitudReemplazo
    {
        $id = DB::table('solicitudes_reemplazo')->insertGetId(array_replace(['reemplazo_personal_id' => 1,
            'tipo_reemplazo' => TipoReemplazo::ENFERMEDAD_ACCIDENTE_COMUN, 'estado' => 'cerrado',
            'fecha_inicio' => '2026-11-01', 'fecha_termino' => '2026-11-30', 'cerrado_por_user_id' => $this->actor->id,
            'cerrado_at' => now(), 'orden_trabajo_pdf_path' => 'reemplazos/prueba/ot-anterior.pdf',
            'orden_trabajo_creada_at' => now(), 'resolucion_docente_docx_path' => 'reemplazos/prueba/resolucion-anterior.docx'], $extra));
        return SolicitudReemplazo::findOrFail($id);
    }

    private function datos(array $extra = []): array
    {
        return array_replace(['causal_modificacion_termino' => SolicitudReemplazoModificacionTermino::REDUCCION_LICENCIA_REPOSO,
            'fecha_termino_nueva' => '2026-11-15', 'motivo_modificacion_termino' => 'Reducción de período documentada en respaldo de prueba.'], $extra);
    }

    private function pdf(string $name = 'respaldo.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
    }

    private function reabrir(SolicitudReemplazo $solicitud, array $datos)
    {
        return $this->from('/solicitud-prueba')->post(route('gestion.solicitudes-reemplazo.reabrir-modificar-termino', $solicitud), $datos);
    }

    public function test_nueva_causal_exige_solo_respaldo_y_conserva_documentos_y_auditoria(): void
    {
        foreach ([TipoReemplazo::ENFERMEDAD_ACCIDENTE_COMUN, TipoReemplazo::LICENCIA_MATERNAL, TipoReemplazo::REPOSO_MUTUALIDAD] as $tipo) {
            $solicitud = $this->solicitud(['tipo_reemplazo' => $tipo]);
            $this->reabrir($solicitud, $this->datos(['respaldo_licencia_pdf' => $this->pdf()]))->assertRedirect('/solicitud-prueba')->assertSessionHasNoErrors();
            $actual = $solicitud->fresh();
            $this->assertSame('aceptada', $actual->estado); $this->assertSame('2026-11-15', $actual->fecha_termino->toDateString());
            $this->assertNull($actual->cerrado_at); $this->assertNull($actual->cerrado_por_user_id);
            $modificacion = $actual->modificacionesTermino()->firstOrFail();
            $this->assertSame(SolicitudReemplazoModificacionTermino::REDUCCION_LICENCIA_REPOSO, $modificacion->causal);
            $this->assertNull($modificacion->carta_renuncia_path); $this->assertNull($modificacion->resolucion_renuncia_path);
            $this->assertSame('2026-11-30', $modificacion->fecha_termino_anterior->toDateString());
            $this->assertSame($actual->orden_trabajo_pdf_path, $modificacion->orden_trabajo_anterior_path);
            $this->assertSame($actual->resolucion_docente_docx_path, $modificacion->resolucion_docente_docx_anterior_path);
            $this->assertTrue($modificacion->orden_trabajo_requiere_regeneracion); $this->assertTrue($modificacion->resolucion_docente_requiere_regeneracion);
            Storage::disk('local')->assertExists($modificacion->respaldo_licencia_path); Storage::disk('public')->assertMissing($modificacion->respaldo_licencia_path);
            $this->assertDatabaseHas('solicitudes_reemplazo_observaciones', ['solicitud_reemplazo_id' => $solicitud->id, 'accion' => 'reapertura_modificacion_termino', 'user_id' => $this->actor->id]);
        }
    }

    public function test_servidor_rechaza_respaldo_ausente_formato_y_tamano_incorrectos(): void
    {
        $s = $this->solicitud();
        $this->reabrir($s, $this->datos())->assertSessionHasErrors('respaldo_licencia_pdf')->assertSessionDoesntHaveErrors(['carta_renuncia_pdf', 'resolucion_renuncia_pdf']);
        $this->reabrir($s, $this->datos(['respaldo_licencia_pdf' => UploadedFile::fake()->image('imagen.png')]))->assertSessionHasErrors('respaldo_licencia_pdf');
        $this->reabrir($s, $this->datos(['respaldo_licencia_pdf' => UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf')]))->assertSessionHasErrors('respaldo_licencia_pdf');
        $this->assertSame('2026-11-30', $s->fresh()->fecha_termino->toDateString()); $this->assertDatabaseCount('solicitudes_reemplazo_modificaciones_termino', 0);
    }

    public function test_nueva_causal_ignora_documentos_de_renuncia_incluso_si_se_envian(): void
    {
        $s = $this->solicitud();
        $this->reabrir($s, $this->datos(['respaldo_licencia_pdf' => $this->pdf(), 'carta_renuncia_pdf' => UploadedFile::fake()->image('carta.png'),
            'resolucion_renuncia_pdf' => UploadedFile::fake()->create('resolucion.txt', 1, 'text/plain')]))->assertSessionHasNoErrors();
        $m = $s->modificacionesTermino()->firstOrFail();
        $this->assertNull($m->carta_renuncia_path); $this->assertNull($m->resolucion_renuncia_path);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_causales_anteriores_conservan_sus_requisitos(): void
    {
        $s = $this->solicitud();
        $renuncia = $this->datos(['causal_modificacion_termino' => 'renuncia_voluntaria']);
        $this->reabrir($s, $renuncia)->assertSessionHasErrors(['carta_renuncia_pdf', 'resolucion_renuncia_pdf'])->assertSessionDoesntHaveErrors('respaldo_licencia_pdf');
        $this->reabrir($s, $renuncia + ['carta_renuncia_pdf' => $this->pdf('carta.pdf'), 'resolucion_renuncia_pdf' => $this->pdf('resolucion.pdf')])->assertSessionHasNoErrors();
        $this->assertNull($s->modificacionesTermino()->firstOrFail()->respaldo_licencia_path);
        $mutual = $this->solicitud(['tipo_reemplazo' => TipoReemplazo::REPOSO_MUTUALIDAD, 'orden_trabajo_pdf_path' => null, 'resolucion_docente_docx_path' => null]);
        $this->reabrir($mutual, $this->datos(['causal_modificacion_termino' => 'reduccion_reposo_mutualidad']))->assertSessionHasNoErrors();
        $this->assertNotNull($mutual->modificacionesTermino()->firstOrFail()->finalizada_at);
        $otro = $this->solicitud();
        $this->reabrir($otro, $this->datos(['causal_modificacion_termino' => 'reduccion_reposo_mutualidad']))->assertSessionHasErrors('causal_modificacion_termino');
    }

    public function test_se_conservan_limites_de_fechas_y_estado(): void
    {
        $s = $this->solicitud();
        foreach (['2026-10-31', '2026-11-30', '2026-12-01'] as $fecha) {
            $this->reabrir($s, $this->datos(['fecha_termino_nueva' => $fecha, 'respaldo_licencia_pdf' => $this->pdf()]))->assertSessionHasErrors('fecha_termino_nueva');
        }
        $pendiente = $this->solicitud(['estado' => 'pendiente_uatp']);
        $this->postJson(route('gestion.solicitudes-reemplazo.reabrir-modificar-termino', $pendiente), $this->datos())->assertForbidden();
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_permisos_y_derivacion_de_funcionario_slep_se_conservan(): void
    {
        $s = $this->solicitud(); $gdp = $this->testUser(2, 10);
        $this->actingAs($gdp)->withSession(['active_role' => 'coordinador_gdp']);
        $this->reabrir($s, $this->datos(['respaldo_licencia_pdf' => $this->pdf()]))->assertSessionHasNoErrors();
        $slep = $this->testUser(3, 11); $s2 = $this->solicitud(); $this->actingAs($slep)->withSession(['active_role' => 'funcionario_slep']);
        $this->postJson(route('gestion.solicitudes-reemplazo.reabrir-modificar-termino', $s2), $this->datos())->assertForbidden();
        $s2->forceFill(['derivada_a_user_id' => $slep->id])->save();
        $this->reabrir($s2, $this->datos(['respaldo_licencia_pdf' => $this->pdf()]))->assertSessionHasNoErrors();
        $uatp = $this->testUser(4, 12); $this->actingAs($uatp)->withSession(['active_role' => 'coordinador_uatp']);
        $this->postJson(route('gestion.solicitudes-reemplazo.reabrir-modificar-termino', $this->solicitud()), $this->datos())->assertForbidden();
    }

    public function test_descarga_respeta_solicitud_y_archivo_privado(): void
    {
        $s = $this->solicitud(); $this->reabrir($s, $this->datos(['respaldo_licencia_pdf' => $this->pdf()]));
        $m = $s->modificacionesTermino()->firstOrFail();
        $this->get(route('gestion.solicitudes-reemplazo.modificacion-termino.documento', [$s, $m, 'respaldo_licencia']))->assertOk()->assertHeader('Content-Disposition', 'inline; filename="RESPALDO_LICENCIA.pdf"');
        $this->getJson(route('gestion.solicitudes-reemplazo.modificacion-termino.documento', [$this->solicitud(), $m, 'respaldo_licencia']))->assertNotFound();
        $this->getJson(route('gestion.solicitudes-reemplazo.modificacion-termino.documento', [$s, $m, 'campo_arbitrario']))->assertNotFound();
        $this->assertSame('Término anticipado por reducción de licencia médica o reposo mutualidad', $m->causal_label);
    }

    public function test_fallo_de_auditoria_revierte_periodo_y_documento_nuevo(): void
    {
        $s = $this->solicitud(); Storage::disk('local')->put($s->orden_trabajo_pdf_path, 'Documento anterior de prueba');
        SolicitudReemplazoObservacion::creating(fn () => throw new \RuntimeException('Auditoría de prueba'));
        $this->withoutExceptionHandling();
        try { $this->reabrir($s, $this->datos(['respaldo_licencia_pdf' => $this->pdf()])); $this->fail('Debió fallar la auditoría'); }
        catch (\RuntimeException $error) { $this->assertSame('Auditoría de prueba', $error->getMessage()); }
        finally { SolicitudReemplazoObservacion::flushEventListeners(); }
        $this->assertDatabaseCount('solicitudes_reemplazo_modificaciones_termino', 0);
        $this->assertSame('2026-11-30', $s->fresh()->fecha_termino->toDateString());
        $this->assertSame([$s->orden_trabajo_pdf_path], Storage::disk('local')->allFiles());
    }

    public function test_formulario_presenta_nueva_causal_y_conserva_valores_tras_error(): void
    {
        $s = $this->solicitud(['tipo_reemplazo' => TipoReemplazo::REPOSO_MUTUALIDAD]);
        View::share('errors', new ViewErrorBag);
        app(\Illuminate\Foundation\Vite::class)->useHotFile(Storage::disk('local')->path('sin-hot'));
        $html = view('gestion.solicitudes-reemplazo.partials._reapertura-termino', compact('s'))->render();
        $this->assertStringContainsString('Término anticipado por reducción de licencia médica o reposo mutualidad', $html);
        $this->assertStringContainsString('Respaldo Licencia (PDF)', $html); $this->assertStringContainsString('reduccion_reposo_mutualidad', $html);
        $this->withSession(['_old_input' => $this->datos(['motivo_modificacion_termino' => 'Motivo conservado'])]);
        $this->app['request']->setLaravelSession($this->app['session']->driver());
        $html = view('gestion.solicitudes-reemplazo.partials._reapertura-termino', compact('s'))->render();
        $this->assertStringContainsString('Motivo conservado', $html); $this->assertStringContainsString('value="reduccion_licencia_reposo" selected', $html);
        if (getenv('REAPERTURA_UI_PREVIEW')) {
            $dir = base_path('.codex_work/reapertura-termino'); if (!is_dir($dir)) mkdir($dir, 0777, true);
            $assets = app(\Illuminate\Foundation\Vite::class)(['resources/scss/app.scss', 'resources/js/reemplazo-modificacion-termino.js']);
            file_put_contents($dir.'/fixture.html', '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'.$assets.'</head><body class="p-3">'.$html.'</body></html>');
        }
    }
}
