<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureModuleAccess;
use App\Mail\CometidoFuncionarioNotificationMail;
use App\Models\CometidoFuncionario;
use App\Models\CometidoFuncionarioDocumentoGenerado;
use App\Models\User;
use App\Services\Notifications\NotificationEventCatalog;
use App\Support\Cometidos\CometidoFuncionarioPdfService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\IsolatedSecurityTestCase;

class CometidoInformeRendicionAccessTest extends IsolatedSecurityTestCase
{
    private const RUTA = 'tramites.cometidos-funcionarios.';

    private int $rolEstablecimiento;

    private int $rolDirectivo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(EnsureModuleAccess::class);
        $this->withoutVite();
        Mail::fake();

        $this->rolEstablecimiento = DB::table('roles')->insertGetId([
            'name' => 'funcionario_estab', 'guard_name' => 'web',
        ]);
        $this->rolDirectivo = DB::table('roles')->insertGetId([
            'name' => 'funcionario_directivo_estab', 'guard_name' => 'web',
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('establecimiento_id')->nullable();
        });
        Schema::create('establecimientos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_establecimiento');
        });
        DB::table('establecimientos')->insert([
            ['id' => 10, 'nombre_establecimiento' => 'Establecimiento de prueba'],
            ['id' => 20, 'nombre_establecimiento' => 'Otro establecimiento de prueba'],
        ]);
        Schema::create('cometidos_funcionarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('establecimiento_id')->nullable();
            $table->string('origen_cometido');
            $table->string('estado');
            $table->string('estado_reembolso');
            $table->string('estado_viatico')->nullable();
            $table->boolean('solicita_reembolso')->default(true);
            $table->date('fecha_desde');
            $table->date('fecha_hasta');
            $table->time('hora_salida');
            $table->time('hora_regreso');
            $table->unsignedBigInteger('uatp_revisado_por')->nullable();
            $table->unsignedBigInteger('gdp_revisado_por')->nullable();
            $table->timestamp('gdp_revisado_at')->nullable();
            $table->string('numero_resolucion_cometido')->nullable();
            $table->date('fecha_resolucion_cometido')->nullable();
            $table->string('archivo_resolucion_cometido_path')->nullable();
            $table->timestamps();
        });

        foreach ([
            'cometido_funcionario_cdp_montos',
            'cometido_funcionario_documentos',
            'cometido_funcionario_documentos_generados',
            'cometido_funcionario_firmas',
            'cometido_funcionario_rendiciones',
            'cometido_funcionario_resoluciones_reembolso',
            'cometidos_funcionarios_informes',
            'cometido_funcionario_pasajes_aereos',
            'cometido_funcionario_historial',
        ] as $nombre) {
            Schema::create($nombre, function (Blueprint $table) use ($nombre) {
                $table->id();
                $table->unsignedBigInteger('cometido_funcionario_id');
                if ($nombre === 'cometido_funcionario_cdp_montos') {
                    $table->string('tipo');
                    $table->integer('dia_numero');
                } elseif ($nombre === 'cometido_funcionario_documentos') {
                    $table->string('tipo');
                    $table->string('path');
                    $table->string('nombre_original');
                    $table->string('mime_type')->nullable();
                    $table->integer('size')->nullable();
                    $table->unsignedBigInteger('uploaded_by');
                    $table->timestamps();
                } elseif ($nombre === 'cometido_funcionario_documentos_generados') {
                    $table->string('tipo_documento');
                    $table->string('numero_documento');
                    $table->string('codigo_validacion');
                    $table->string('archivo_pdf_path')->nullable();
                    $table->string('estado')->default('vigente');
                    $table->timestamps();
                } elseif ($nombre === 'cometido_funcionario_firmas') {
                    $table->unsignedBigInteger('documento_generado_id')->nullable();
                } elseif ($nombre === 'cometido_funcionario_rendiciones') {
                    $table->string('estado');
                    $table->unsignedInteger('monto_rendido');
                    $table->text('observacion_establecimiento')->nullable();
                    $table->json('documentos_respaldo');
                    $table->timestamp('fecha_envio_rendicion');
                    $table->unsignedBigInteger('created_by');
                    $table->unsignedBigInteger('updated_by');
                    $table->timestamps();
                } elseif ($nombre === 'cometido_funcionario_resoluciones_reembolso') {
                    $table->unsignedBigInteger('rendicion_id');
                } elseif ($nombre === 'cometidos_funcionarios_informes') {
                    $table->string('estado_informe');
                    $table->date('fecha_desde_real')->nullable();
                    $table->date('fecha_hasta_real')->nullable();
                    $table->time('hora_salida_real')->nullable();
                    $table->time('hora_regreso_real')->nullable();
                    foreach (['organismos_autoridades_relatores', 'descripcion_actividades_realizadas', 'resultados_obtenidos', 'opiniones_propuestas', 'observacion_sistema', 'decision_jefatura', 'observacion_jefatura'] as $campo) {
                        $table->text($campo)->nullable();
                    }
                    $table->boolean('requiere_nuevo_cometido_diferencia')->default(false);
                    $table->timestamp('fecha_envio')->nullable();
                    $table->timestamp('fecha_revision_jefatura')->nullable();
                    $table->unsignedBigInteger('user_id_envia')->nullable();
                    $table->unsignedBigInteger('jefatura_revisora_id')->nullable();
                    $table->timestamps();
                } elseif ($nombre === 'cometido_funcionario_historial') {
                    $table->unsignedBigInteger('user_id');
                    $table->string('estado_anterior');
                    $table->string('estado_nuevo');
                    $table->string('accion');
                    $table->text('observacion')->nullable();
                    $table->timestamps();
                }
            });
        }

        Schema::create('cometidos_notificaciones_configuracion', function (Blueprint $table) {
            $table->id();
            $table->string('clave');
            $table->boolean('activo');
        });
        Schema::create('viaticos_reembolsos_valores', function (Blueprint $table) {
            $table->id();
            $table->string('estamento');
            $table->string('cargo_funcion');
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->unsignedInteger('valor_100');
            $table->unsignedInteger('valor_60');
            $table->unsignedInteger('valor_40');
            $table->boolean('activo');
        });
    }

    public function test_funcionario_solicitante_ve_ambos_formularios_con_su_rol_predeterminado(): void
    {
        $usuario = $this->testUser(role: $this->rolEstablecimiento);
        $cometido = $this->cometido($usuario);
        $urlInforme = route(self::RUTA.'informe.create', $cometido);

        $this->actingAs($usuario)->get(route(self::RUTA.'rendicion.panel', $cometido))
            ->assertOk()
            ->assertSessionMissing('active_role')
            ->assertViewHas('puedeCompletarInformeCometido', true)
            ->assertViewHas('puedeRendir', true)
            ->assertSee('id="rendicionReembolsoForm"', false)
            ->assertSee('Completar informe')
            ->assertSee('href="'.$urlInforme.'"', false);

        $this->get($urlInforme)->assertOk()
            ->assertSee('action="'.route(self::RUTA.'informe.store', $cometido).'"', false);
    }

    public function test_otro_funcionario_no_ve_el_boton_aunque_la_sesion_conserve_un_rol_no_asignado(): void
    {
        $solicitante = $this->testUser(1, $this->rolEstablecimiento);
        $otroFuncionario = $this->testUser(2, $this->rolEstablecimiento);
        $solicitante->update(['establecimiento_id' => 10]);
        $otroFuncionario->update(['establecimiento_id' => 20]);
        $cometido = $this->cometido($solicitante);

        $this->actingAs($otroFuncionario)->withSession(['active_role' => 'admin'])
            ->get(route(self::RUTA.'rendicion.panel', $cometido))
            ->assertOk()
            ->assertViewHas('puedeCompletarInformeCometido', false)
            ->assertViewHas('puedeRendir', false)
            ->assertDontSee('Completar informe');

        $this->get(route(self::RUTA.'informe.create', $cometido))->assertForbidden();
        $this->post(route(self::RUTA.'rendicion.enviar', $cometido), ['monto_rendido' => 1000])->assertForbidden();
    }

    public function test_el_solicitante_no_ve_el_boton_antes_de_la_etapa_de_informe_o_durante_la_revision(): void
    {
        $usuario = $this->testUser(role: $this->rolEstablecimiento);
        $pendienteResolucion = $this->cometido($usuario, 'en_gdp_resolucion');
        $pendienteJefatura = $this->cometido($usuario, 'rendicion_enviada_pendiente_informe');
        DB::table('cometidos_funcionarios_informes')->insert([
            'cometido_funcionario_id' => $pendienteJefatura->id,
            'estado_informe' => 'enviado_pendiente_jefatura',
        ]);

        $this->actingAs($usuario)->withSession(['active_role' => 'funcionario_estab']);
        $this->get(route(self::RUTA.'rendicion.panel', $pendienteResolucion))->assertOk()
            ->assertViewHas('puedeRendir', false)
            ->assertDontSee('Completar informe');
        $this->post(route(self::RUTA.'rendicion.enviar', $pendienteResolucion), ['monto_rendido' => 1000])->assertForbidden();
        $this->get(route(self::RUTA.'informe.create', $pendienteResolucion))->assertForbidden();
        $this->get(route(self::RUTA.'rendicion.panel', $pendienteJefatura))->assertOk()->assertDontSee('Completar informe');
    }

    public function test_enviar_la_rendicion_primero_permite_completar_el_informe_y_mantiene_pendiente_la_rex(): void
    {
        $solicitante = $this->testUser(role: $this->rolEstablecimiento);
        $solicitante->update(['establecimiento_id' => 10]);
        $admin = $this->testUser(2, 3);
        $directivo = $this->testUser(3, $this->rolDirectivo);
        $directivo->update(['establecimiento_id' => 10]);
        $cometido = $this->cometido($solicitante);
        $urlPanel = route(self::RUTA.'rendicion.panel', $cometido);
        $urlInforme = route(self::RUTA.'informe.create', $cometido);
        $urlRendicion = route(self::RUTA.'rendicion.enviar', $cometido);
        $urlRevisionDaf = route(self::RUTA.'rendicion.daf.autorizar', $cometido);
        $pdf = new CometidoFuncionarioDocumentoGenerado(['numero_documento' => 'INF-PRUEBA']);
        $this->mock(CometidoFuncionarioPdfService::class, function ($mock) use ($pdf) {
            $mock->shouldReceive('generarInformeCometido')->twice()->andReturn($pdf);
            $mock->shouldReceive('firmarInformeCometidoJefatura')->once()->andReturn($pdf);
        });

        $this->actingAs($solicitante)->post($urlRendicion, $this->respaldoFiscal())->assertRedirect($urlPanel);
        $this->assertSame('en_gdp_rex_cgr', $cometido->refresh()->estado_reembolso);
        $this->assertDatabaseHas('cometido_funcionario_rendiciones', ['cometido_funcionario_id' => $cometido->id, 'monto_rendido' => 1000]);
        $documento = DB::table('cometido_funcionario_documentos')->where('cometido_funcionario_id', $cometido->id)->sole();
        Storage::disk('public')->assertExists($documento->path);
        $rendicionId = DB::table('cometido_funcionario_rendiciones')->where('cometido_funcionario_id', $cometido->id)->value('id');
        $this->get(route(self::RUTA.'rendicion.documentos.descargar', [$cometido, $rendicionId, 0]))->assertOk();
        $this->post($urlRendicion, ['monto_rendido' => 1000])->assertForbidden();
        $this->get(route(self::RUTA.'show', $cometido))->assertOk()->assertSee('href="'.$urlPanel.'"', false);
        $this->get($urlPanel)->assertOk()->assertViewHas('puedeCompletarInformeCometido', true)->assertViewHas('puedeRendir', false);
        $this->get($urlInforme)->assertOk();
        $this->post(route(self::RUTA.'informe.store', $cometido), $this->datosInforme())
            ->assertRedirect(route(self::RUTA.'show', $cometido));
        $this->assertSame('en_gdp_rex_cgr', $cometido->refresh()->estado_reembolso);
        $this->get($urlInforme)->assertForbidden();

        $this->actingAs($directivo)->patch(route(self::RUTA.'informe.jefatura.observar', $cometido), [
            'observacion_jefatura' => 'Detalle las actividades realizadas.',
        ])->assertRedirect(route(self::RUTA.'show', $cometido));
        $this->assertSame('en_gdp_rex_cgr', $cometido->refresh()->estado_reembolso);
        $this->actingAs($solicitante)->get($urlInforme)->assertOk();
        $this->post(route(self::RUTA.'informe.store', $cometido), $this->datosInforme())
            ->assertRedirect(route(self::RUTA.'show', $cometido));

        $this->actingAs($directivo)->patch(route(self::RUTA.'informe.jefatura.aprobar', $cometido))
            ->assertRedirect(route(self::RUTA.'show', $cometido));
        $this->assertDatabaseHas('cometidos_funcionarios_informes', [
            'cometido_funcionario_id' => $cometido->id,
            'estado_informe' => 'aprobado_jefatura',
            'jefatura_revisora_id' => $directivo->id,
        ]);
        $this->assertSame('en_gdp_rex_cgr', $cometido->refresh()->estado_reembolso);
        $this->actingAs($admin)->get($urlPanel)->assertViewHas('bloqueaDafPorRex', true)->assertDontSee($urlRevisionDaf);
        $this->patchJson($urlRevisionDaf, ['monto_autorizado_daf' => 1000])
            ->assertUnprocessable()->assertJsonValidationErrors('rex_cgr');

        $urlRex = route(self::RUTA.'gdp.resolucion', $cometido);
        $this->withoutExceptionHandling()->get(route(self::RUTA.'show', $cometido))->assertOk()
            ->assertSee('action="'.$urlRex.'"', false);
        $this->patch($urlRex, [
            'numero_resolucion_cometido' => 'REX-PRUEBA',
            'fecha_resolucion_cometido' => '2026-11-25',
        ])->assertSessionHas('success');
        $this->assertSame('en_revision_daf_rendicion', $cometido->refresh()->estado_reembolso);
        $this->get($urlPanel)->assertOk()->assertSee($urlRevisionDaf);
    }

    public function test_enviar_el_informe_primero_permite_rendir_desde_el_mismo_establecimiento(): void
    {
        $solicitante = $this->testUser(1, $this->rolEstablecimiento);
        $funcionario = $this->testUser(2, $this->rolEstablecimiento);
        $solicitante->update(['establecimiento_id' => 10]);
        $funcionario->update(['establecimiento_id' => 10]);
        $cometido = $this->cometido($solicitante, 'pendiente_rendicion');
        $urlPanel = route(self::RUTA.'rendicion.panel', $cometido);
        $this->mock(CometidoFuncionarioPdfService::class)->shouldReceive('generarInformeCometido')->once()
            ->andReturn(new CometidoFuncionarioDocumentoGenerado(['numero_documento' => 'INF-PRUEBA']));

        $this->withoutExceptionHandling()->actingAs($solicitante)->post(route(self::RUTA.'informe.store', $cometido), $this->datosInforme())
            ->assertRedirect(route(self::RUTA.'show', $cometido));
        $this->actingAs($funcionario)->get($urlPanel)->assertOk()
            ->assertViewHas('puedeRendir', true)->assertViewHas('puedeCompletarInformeCometido', false);
        $this->post(route(self::RUTA.'rendicion.enviar', $cometido), $this->respaldoFiscal())->assertRedirect($urlPanel);
        $this->get($urlPanel)->assertOk()->assertViewHas('bloqueaDafPorInforme', true);
    }

    public function test_el_detalle_con_informe_generado_permite_regenerar_el_pdf_al_solicitante(): void
    {
        $solicitante = $this->testUser(1, $this->rolEstablecimiento);
        $otroFuncionario = $this->testUser(2, $this->rolEstablecimiento);
        $solicitante->update(['establecimiento_id' => 10]);
        $otroFuncionario->update(['establecimiento_id' => 10]);
        $estado = 'rendicion_enviada_pendiente_informe';
        $cometido = $this->cometido($solicitante, $estado);
        $cometido->informesCometido()->create(['estado_informe' => 'enviado_pendiente_jefatura']);
        $documento = $cometido->documentosGenerados()->create([
            'tipo_documento' => 'informe_cometido',
            'numero_documento' => 'INF-PRUEBA',
            'codigo_validacion' => 'PRUEBA',
            'archivo_pdf_path' => 'cometidos/informe-prueba.pdf',
        ]);
        $this->mock(CometidoFuncionarioPdfService::class)->shouldReceive('generarInformeCometido')->once()
            ->andReturn($documento);

        $urlDetalle = route(self::RUTA.'show', $cometido);
        $respuestaDetalle = $this->actingAs($solicitante)->get($urlDetalle)->assertOk()->assertSee('Regenerar PDF');
        $urlRegenerar = route(self::RUTA.'informe.regenerar-pdf', $cometido);
        $respuestaDetalle->assertSee('action="'.$urlRegenerar.'"', false);
        $this->actingAs($otroFuncionario)->post($urlRegenerar)->assertForbidden();
        $this->actingAs($solicitante)->post($urlRegenerar)->assertRedirect($urlDetalle)->assertSessionHas('success');

        $this->assertDatabaseHas('cometido_funcionario_historial', [
            'cometido_funcionario_id' => $cometido->id,
            'user_id' => $solicitante->id,
            'accion' => 'PDF de informe de cometido regenerado',
            'estado_anterior' => $estado,
            'estado_nuevo' => $estado,
        ]);
    }

    public function test_solo_el_directivo_del_propio_establecimiento_puede_revisar_y_recibe_el_aviso(): void
    {
        $this->withMiddleware(EnsureModuleAccess::class);
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key');
        });
        Schema::create('module_role', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id');
            $table->unsignedBigInteger('role_id');
        });
        DB::table('modules')->insert(['key' => 'tramites']);

        $solicitante = $this->testUser(1, $this->rolEstablecimiento);
        $solicitante->assignRole('funcionario_directivo_estab');
        $funcionario = $this->testUser(2, $this->rolEstablecimiento);
        $directivo = $this->testUser(3, $this->rolDirectivo);
        $directivoAjeno = $this->testUser(4, $this->rolDirectivo);
        $admin = $this->testUser(5, 3);
        $rolUatp = DB::table('roles')->insertGetId(['name' => 'coordinador_uatp', 'guard_name' => 'web']);
        $uatp = $this->testUser(6, $rolUatp);
        foreach ([$solicitante, $funcionario, $directivo] as $usuario) {
            $usuario->update(['establecimiento_id' => 10]);
        }
        $directivoAjeno->update(['establecimiento_id' => 20]);

        $cometido = $this->cometido($solicitante);
        $cometido->update(['uatp_revisado_por' => $uatp->id]);
        $cometidoSinInforme = $this->cometido($solicitante);
        $urlDetalle = route(self::RUTA.'show', $cometido);
        $urlAprobar = route(self::RUTA.'informe.jefatura.aprobar', $cometido);
        $this->mock(CometidoFuncionarioPdfService::class)->shouldReceive('generarInformeCometido')->once()
            ->andReturn(new CometidoFuncionarioDocumentoGenerado(['numero_documento' => 'INF-PRUEBA']));

        $this->actingAs($solicitante)->post(route(self::RUTA.'informe.store', $cometido), $this->datosInforme())->assertRedirect($urlDetalle);
        $catalogo = app(NotificationEventCatalog::class);
        $clave = 'cometidos.informe_revision_directivo_estab';
        $this->assertFalse($catalogo->find($clave)['whatsapp_default']);
        Mail::assertSentCount(1);
        Mail::assertSent(CometidoFuncionarioNotificationMail::class, function ($aviso) use ($directivo, $urlDetalle, $catalogo, $clave) {
            return $aviso->hasTo($directivo->email)
                && $aviso->actionUrl === $urlDetalle
                && $aviso->attachmentPack === null
                && $catalogo->resolveMail($aviso->viewData, $aviso->title) === $clave;
        });

        $this->actingAs($directivo)->get(route(self::RUTA.'index'))->assertOk()
            ->assertSee($urlDetalle)->assertDontSee(route(self::RUTA.'show', $cometidoSinInforme));
        $this->get($urlDetalle)->assertOk()->assertSee('action="'.$urlAprobar.'"', false);
        $this->get(route(self::RUTA.'rendicion.panel', $cometido))->assertForbidden();

        $this->actingAs($directivoAjeno)->get($urlDetalle)->assertForbidden();
        foreach ([$directivoAjeno, $funcionario, $admin, $uatp] as $usuario) {
            $this->actingAs($usuario)->patch($urlAprobar)->assertForbidden();
        }
        $this->actingAs($solicitante)->withSession(['active_role' => 'funcionario_directivo_estab'])
            ->patch($urlAprobar)->assertForbidden();
    }

    private function cometido(User $solicitante, string $estado = 'pendiente_rendicion_informe'): CometidoFuncionario
    {
        return CometidoFuncionario::create([
            'user_id' => $solicitante->id,
            'establecimiento_id' => $solicitante->establecimiento_id,
            'origen_cometido' => 'establecimiento',
            'estado' => $estado,
            'estado_reembolso' => $estado,
            'solicita_reembolso' => true,
            'fecha_desde' => '2026-11-25',
            'fecha_hasta' => '2026-11-25',
            'hora_salida' => '09:00',
            'hora_regreso' => '12:00',
        ]);
    }

    private function respaldoFiscal(): array
    {
        return [
            'monto_rendido' => 1000,
            'comprobantes' => [[
                'archivo' => UploadedFile::fake()->create('comprobante.pdf', 10, 'application/pdf'),
                'fecha_documento' => '2026-11-25',
                'monto_documento' => 1000,
                'detalle_gasto' => 'Traslado de prueba',
            ]],
        ];
    }

    private function datosInforme(): array
    {
        return [
            'fecha_desde_real' => '2026-11-25',
            'fecha_hasta_real' => '2026-11-25',
            'hora_salida_real' => '09:00',
            'hora_regreso_real' => '12:00',
            'organismos_autoridades_relatores' => 'Organismo de prueba',
            'descripcion_actividades_realizadas' => 'Actividades de prueba',
            'resultados_obtenidos' => 'Resultados de prueba',
            'opiniones_propuestas' => 'Propuesta de prueba',
        ];
    }
}
