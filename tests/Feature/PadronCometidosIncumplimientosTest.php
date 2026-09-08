<?php

namespace Tests\Feature;

use App\Http\Controllers\IncumplimientoLaboralController;
use App\Http\Controllers\Tramites\CometidoFuncionarioController;
use App\Models\CometidoFuncionario;
use App\Models\Establecimiento;
use App\Models\IncumplimientoLaboral;
use App\Models\ReemplazoPersonal;
use App\Models\User;
use App\Services\Padron\PadronDocumentoFuncionarioService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PadronCometidosIncumplimientosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento'); $t->string('comuna')->nullable();
        });
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('rbd');
            foreach (['rut', 'nombre', 'tipocontrato', 'estatuto', 'escalafon'] as $campo) { $t->string($campo)->nullable(); }
            $t->integer('anio'); $t->integer('mes'); $t->integer('jornada'); $t->boolean('vigente')->default(true); $t->timestamps();
        });
        foreach ([CometidoFuncionario::class, IncumplimientoLaboral::class] as $class) {
            Schema::create((new $class)->getTable(), function (Blueprint $t) use ($class) {
                $t->id(); $t->integer('reemplazo_personal_id'); $t->integer('establecimiento_id');
                $t->string('funcionario_rut')->nullable(); $t->string('funcionario_nombre')->nullable();
                $t->string('fecha_desde')->nullable(); $t->string('fecha_hasta')->nullable();
                $t->json('padron_personal_snapshot')->nullable(); $t->timestamps();
                if ($class === IncumplimientoLaboral::class) {
                    foreach (['funcionario_rbd', 'dias', 'horas', 'minutos', 'created_by_user_id', 'updated_by_user_id'] as $campo) { $t->integer($campo)->nullable(); }
                } else {
                    foreach (['rbd', 'calidad_juridica', 'estamento', 'cargo_funcion', 'estado', 'uatp_decision', 'uatp_observacion',
                        'region_destino', 'comuna_destino_id', 'comuna_destino_nombre', 'institucion_destino', 'destino',
                        'hora_salida', 'hora_regreso', 'medios_transporte', 'motivo', 'descripcion_actividades',
                        'existe_citacion_invitacion', 'solicita_viatico', 'solicita_reembolso', 'contempla_alojamiento',
                        'servicio_contempla_colacion', 'solicita_anticipo_viatico', 'porcentaje_anticipo_viatico',
                        'monto_anticipo_viatico', 'monto_saldo_viatico', 'banco_pago', 'tipo_cuenta_pago', 'numero_cuenta_pago',
                        'declaracion_aceptada', 'declaracion_aceptada_at', 'declaracion_texto'] as $campo) { $t->text($campo)->nullable(); }
                }
            });
        }
        Schema::create('incumplimientos_laborales_historial', function (Blueprint $t) {
            $t->id(); $t->integer('incumplimiento_laboral_id'); $t->integer('user_id')->nullable();
            foreach (['action', 'old_values', 'new_values', 'changed_fields'] as $campo) { $t->text($campo)->nullable(); }
            $t->timestamps();
        });
        Schema::create('cometido_funcionario_historial', function (Blueprint $t) {
            $t->id(); $t->integer('cometido_funcionario_id'); $t->integer('user_id')->nullable();
            foreach (['estado_anterior', 'estado_nuevo', 'accion', 'observacion'] as $campo) { $t->text($campo)->nullable(); }
            $t->timestamps();
        });
        Schema::create('communes', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('region_code'); });
        Schema::create('funcionarios_viatico_anexo', function (Blueprint $t) { $t->id(); $t->string('rut_body'); $t->boolean('activo'); });
        DB::table('communes')->insert(['id' => 1, 'name' => 'Comuna sintética', 'region_code' => '99']);
        foreach ([1, 2] as $id) {
            DB::table('establecimientos')->insert(['id' => $id, 'rbd' => 99000 + $id, 'nombre_establecimiento' => 'Escuela sintética '.$id]);
        }
        foreach ([
            [101, 1, 9, true, 'CONTRATA'], [102, 1, 9, true, 'REEMPLAZO'],
            [103, 1, 9, true, 'SUPLENCIA'], [104, 1, 9, false, 'CONTRATA'],
            [105, 1, 8, true, 'TITULAR'], [106, 2, 8, true, 'TITULAR'],
        ] as [$id, $est, $mes, $vigente, $tipo]) {
            DB::table('reemplazos_personal')->insert([
                'id' => $id, 'establecimiento_id' => $est, 'rbd' => 99000 + $est,
                'rut' => '99999'.$id.'K', 'nombre' => 'Persona sintética '.$id,
                'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'tipocontrato' => $tipo,
                'anio' => 2026, 'mes' => $mes, 'vigente' => $vigente, 'jornada' => 44,
            ]);
        }
    }

    private function invoke(string $controller, string $method, mixed ...$args): mixed
    {
        return (new \ReflectionMethod($controller, $method))->invoke(app($controller), ...$args);
    }

    private function request(array $data = [], string $role = 'admin', string $method = 'POST'): Request
    {
        $user = new class extends User {
            public string $testRole = 'admin';
            public function hasRole($roles, ?string $guard = null): bool { return in_array($this->testRole, (array) $roles, true); }
            public function hasAnyRole(...$roles): bool { return $this->hasRole(is_array($roles[0] ?? null) ? $roles[0] : $roles); }
            public function activeRoleName(): ?string { return $this->testRole; }
        };
        $user->testRole = $role; $user->id = 1; $user->establecimiento_id = 1;
        $user->setRelation('establecimiento', Establecimiento::findOrFail(1));
        $this->actingAs($user);
        $request = Request::create('/', $method, $data);
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    private function lista(int $est = 1, ?CometidoFuncionario $documento = null): array
    {
        return $this->invoke(CometidoFuncionarioController::class, 'funcionariosUltimoPadron', Establecimiento::findOrFail($est), $documento);
    }

    private function documento(string $class, bool $legacy = false): CometidoFuncionario|IncumplimientoLaboral
    {
        $data = ['reemplazo_personal_id' => 101, 'establecimiento_id' => 1,
            'funcionario_rut' => '99999101K', 'funcionario_nombre' => 'Nombre del documento sintético',
            'fecha_desde' => '2026-09-01', 'fecha_hasta' => '2026-09-01'];
        $data += $class === CometidoFuncionario::class
            ? ['rbd' => 99001, 'calidad_juridica' => 'CONTRATA', 'estamento' => 'DOCENTE', 'cargo_funcion' => null, 'estado' => 'borrador']
            : ['funcionario_rbd' => 99001, 'dias' => 0, 'horas' => 1, 'minutos' => 0];
        if ($legacy) {
            $id = DB::table((new $class)->getTable())->insertGetId($data);
            return $class::findOrFail($id);
        }
        return $class::create($data);
    }

    private function traslado(): void
    {
        DB::table('reemplazos_personal')->where('id', 101)->update([
            'establecimiento_id' => 2, 'rbd' => 99002, 'nombre' => 'Nuevo nombre sintético',
            'rut' => '99999888K', 'estatuto' => 'ASISTENTE', 'tipocontrato' => 'TITULAR',
            'escalafon' => 'ADMINISTRATIVO', 'jornada' => 30, 'vigente' => false,
        ]);
    }

    private function incumplimientoRequest(int $id = 101, int $est = 1): Request
    {
        return $this->request(['reemplazo_personal_id' => $id, 'establecimiento_id' => $est,
            'fecha_desde' => '2026-09-02', 'fecha_hasta' => '2026-09-02', 'dias' => 0, 'horas' => 2, 'minutos' => 15]);
    }

    private function cometidoRequest(int $id = 101): Request
    {
        return $this->request(['reemplazo_personal_id' => $id, 'region_destino' => '99', 'comuna_destino_id' => 1,
            'institucion_destino' => 'Institución sintética', 'destino' => 'Destino sintético',
            'fecha_desde' => now()->addDay()->toDateString(), 'fecha_hasta' => now()->addDay()->toDateString(),
            'hora_salida' => '08:00', 'hora_regreso' => '17:00', 'medios_transporte' => ['Microbús'],
            'motivo' => 'Concurrir a citación', 'descripcion_actividades' => 'Descripción sintética de actividades para prueba.',
            'existe_citacion_invitacion' => false, 'solicita_reembolso' => false, 'declaracion_aceptada' => true, 'accion' => 'guardar'], 'funcionario_estab');
    }

    public function test_selectors_only_offer_current_rows_including_replacements_and_substitutes(): void
    {
        [$period, $rows] = $this->lista();
        $this->assertSame(['anio' => 2026, 'mes' => 9], $period);
        $this->assertSame([101, 102, 103], $rows->pluck('id')->all());
        $response = app(IncumplimientoLaboralController::class)->ajaxFuncionarios($this->request(['establecimiento_id' => 1], 'admin', 'GET'));
        $this->assertSame(['101', '102', '103'], array_column($response->getData(true)['results'], 'id'));
        $this->assertSame([106], $this->lista(2)[1]->pluck('id')->all());
    }

    public function test_selectors_do_not_resurrect_older_rows_after_inactivation_or_complete_load(): void
    {
        DB::table('reemplazos_personal')->where('establecimiento_id', 1)->where('mes', 9)->update(['vigente' => false]);
        $this->assertCount(0, $this->lista()[1]);
        $this->assertSame([], app(IncumplimientoLaboralController::class)->ajaxFuncionarios($this->request(['establecimiento_id' => 1], 'admin', 'GET'))->getData(true)['results']);
        Schema::create('padron_revisiones', function (Blueprint $t) { $t->id(); $t->integer('anio'); $t->integer('mes'); $t->timestamp('aplicada_at')->nullable(); });
        DB::table('padron_revisiones')->insert(['anio' => 2026, 'mes' => 9, 'aplicada_at' => now()]);
        $this->assertCount(0, $this->lista(2)[1]);
    }

    public function test_incumplimiento_selection_cannot_override_establishment_or_use_stale_ids(): void
    {
        $response = app(IncumplimientoLaboralController::class)->ajaxFuncionarios($this->request(['establecimiento_id' => 2], 'funcionario_estab', 'GET'));
        $this->assertSame(['101', '102', '103'], array_column($response->getData(true)['results'], 'id'));
        foreach ([104, 105, 106, 999] as $id) {
            try {
                $this->invoke(IncumplimientoLaboralController::class, 'validateAndResolvePayload', $this->incumplimientoRequest($id), null);
                $this->fail('Debe rechazar un ID no vigente o ajeno.');
            } catch (ValidationException $e) { $this->assertArrayHasKey('reemplazo_personal_id', $e->errors()); }
        }
    }

    public function test_cometido_selection_revalidates_even_if_id_was_in_original_list(): void
    {
        $rows = $this->lista()[1];
        DB::table('reemplazos_personal')->where('id', 101)->update(['vigente' => false]);
        $this->expectException(ValidationException::class);
        $this->invoke(CometidoFuncionarioController::class, 'funcionarioPadronSeleccionado', $this->request(['reemplazo_personal_id' => 101]), Establecimiento::findOrFail(1), $rows);
    }

    public function test_original_selection_uses_document_identity_after_transfer(): void
    {
        $doc = $this->documento(CometidoFuncionario::class);
        $this->traslado();
        $original = $this->lista(1, $doc)[1]->firstWhere('id', 101);
        $this->assertSame('Nombre del documento sintético', $original->nombre);
        $this->assertSame('CONTRATA', $original->tipocontrato);
        $this->assertNull($original->escalafon);
        $this->assertSame(1, $original->establecimiento_id);
        $this->assertFalse($this->lista()[1]->contains('id', 101));
        $this->assertFalse($this->lista(2, $doc)[1]->contains('id', 101));
    }

    public function test_incumplimiento_update_preserves_document_and_snapshot_after_transfer(): void
    {
        foreach ([false, true] as $legacy) {
            $doc = $this->documento(IncumplimientoLaboral::class, $legacy);
            $before = $doc->padron_personal_snapshot;
            $this->traslado();
            $response = app(IncumplimientoLaboralController::class)->update($this->incumplimientoRequest(), $doc);
            $this->assertSame(302, $response->getStatusCode());
            $fresh = $doc->fresh();
            $this->assertSame(101, $fresh->reemplazo_personal_id);
            $this->assertSame(1, $fresh->establecimiento_id);
            $this->assertSame('Nombre del documento sintético', $fresh->funcionario_nombre);
            $this->assertSame('99999101K', $fresh->funcionario_rut);
            $this->assertSame(99001, $fresh->funcionario_rbd);
            $this->assertSame(2, $fresh->horas);
            if ($legacy) {
                $this->assertSame('antecedentes_documento_sin_copia', $fresh->padron_personal_snapshot['origen']);
                $this->assertArrayNotHasKey('jornada', $fresh->padron_personal_snapshot['personal']);
                $this->assertArrayNotHasKey('tipocontrato', $fresh->padron_personal_snapshot['personal']);
            } else { $this->assertSame($before, $fresh->padron_personal_snapshot); }
        }
        $this->assertDatabaseCount('incumplimientos_laborales_historial', 2);
        $this->assertSame('Nuevo nombre sintético', ReemplazoPersonal::findOrFail(101)->nombre);
    }

    public function test_cometido_update_preserves_saved_identity_and_contract_including_null_fields(): void
    {
        foreach ([false, true] as $legacy) {
            $doc = $this->documento(CometidoFuncionario::class, $legacy);
            $snapshot = $doc->padron_personal_snapshot;
            $this->traslado();
            $response = app(CometidoFuncionarioController::class)->update($this->cometidoRequest(), $doc);
            $this->assertSame(302, $response->getStatusCode());
            $fresh = $doc->fresh();
            $this->assertSame(101, (int) $fresh->reemplazo_personal_id);
            $this->assertSame('Nombre del documento sintético', $fresh->funcionario_nombre);
            $this->assertSame('99999101K', $fresh->funcionario_rut);
            $this->assertSame('CONTRATA', $fresh->calidad_juridica);
            $this->assertSame('DOCENTE', $fresh->estamento);
            $this->assertNull($fresh->cargo_funcion);
            $this->assertSame('borrador', $fresh->estado);
            if ($legacy) {
                $this->assertSame('antecedentes_documento_sin_copia', $fresh->padron_personal_snapshot['origen']);
                $this->assertArrayNotHasKey('jornada', $fresh->padron_personal_snapshot['personal']);
            } else { $this->assertSame($snapshot, $fresh->padron_personal_snapshot); }
        }
        $this->assertDatabaseCount('cometido_funcionario_historial', 2);
    }

    public function test_explicit_change_captures_new_contract_and_retains_legacy_document_copy(): void
    {
        foreach ([IncumplimientoLaboral::class, CometidoFuncionario::class] as $class) {
            $doc = $this->documento($class, true);
            $this->traslado();
            if ($class === IncumplimientoLaboral::class) {
                app(IncumplimientoLaboralController::class)->update($this->incumplimientoRequest(102), $doc);
            } else {
                app(CometidoFuncionarioController::class)->update($this->cometidoRequest(102), $doc);
            }
            $fresh = $doc->fresh();
            $this->assertSame(102, (int) $fresh->reemplazo_personal_id);
            $this->assertSame('Persona sintética 102', $fresh->funcionario_nombre);
            $this->assertSame('REEMPLAZO', $fresh->padron_personal_snapshot['personal']['tipocontrato']);
            $this->assertSame('Nombre del documento sintético', $fresh->padron_personal_snapshot['anteriores'][0]['personal']['nombre']);
            $this->assertSame(101, $fresh->padron_personal_snapshot['anteriores'][0]['personal']['id']);
        }
    }

    public function test_edit_option_is_document_scoped_and_not_available_for_new_incumplimientos(): void
    {
        $doc = $this->documento(IncumplimientoLaboral::class, true);
        $this->traslado();
        $option = $this->invoke(IncumplimientoLaboralController::class, 'buildFuncionarioOption', 101, 1, $doc);
        $this->assertStringContainsString('Nombre del documento sintético', $option['text']);
        $this->assertStringContainsString('Antecedente del documento', $option['text']);
        $this->assertNull($this->invoke(IncumplimientoLaboralController::class, 'buildFuncionarioOption', 101, 1));
        $this->expectException(ValidationException::class);
        $this->invoke(IncumplimientoLaboralController::class, 'validateAndResolvePayload', $this->incumplimientoRequest(101, 2), $doc);
    }

    public function test_invalid_snapshot_is_rejected_before_saving_either_document(): void
    {
        foreach ([IncumplimientoLaboral::class, CometidoFuncionario::class] as $class) {
            $doc = $this->documento($class, true);
            $doc->padron_personal_snapshot = ['version' => 1, 'personal' => ['id' => 102]];
            try {
                app(PadronDocumentoFuncionarioService::class)->conservar($doc);
                $this->fail('No debe reemplazar una copia inválida por el padrón actual.');
            } catch (ValidationException $e) { $this->assertArrayHasKey('padron', $e->errors()); }
            $this->assertNull($doc->fresh()->padron_personal_snapshot);
        }
    }

    public function test_legacy_schema_without_snapshot_column_preserves_document_fields(): void
    {
        Schema::table('incumplimientos_laborales', fn (Blueprint $t) => $t->dropColumn('padron_personal_snapshot'));
        $doc = $this->documento(IncumplimientoLaboral::class, true);
        $this->traslado();
        app(IncumplimientoLaboralController::class)->update($this->incumplimientoRequest(), $doc);
        $this->assertSame('Nombre del documento sintético', $doc->fresh()->funcionario_nombre);
        $this->assertArrayNotHasKey('padron_personal_snapshot', $doc->fresh()->getAttributes());
    }

    public function test_cometido_detail_does_not_expose_inactive_personnel(): void
    {
        $request = $this->request([], 'funcionario_estab', 'GET');
        try {
            app(CometidoFuncionarioController::class)->funcionarioDetalle($request, ReemplazoPersonal::findOrFail(104));
            $this->fail('El detalle debe exigir vigencia.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
        try {
            app(CometidoFuncionarioController::class)->funcionarioDetalle($request, ReemplazoPersonal::findOrFail(106));
            $this->fail('El detalle debe respetar el establecimiento.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
    }

    public function test_both_forms_render_saved_identity_after_transfer(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('views/layouts/app.blade.php', '@yield("content")');
        View::getFinder()->prependLocation(Storage::disk('local')->path('views'));
        View::share('errors', new ViewErrorBag);
        $this->request();
        $cometido = $this->documento(CometidoFuncionario::class, true);
        $item = $this->documento(IncumplimientoLaboral::class, true);
        $this->traslado();
        $cometido->setRelation('documentos', collect());
        [$periodo, $funcionarios] = $this->lista(1, $cometido);
        $data = $this->invoke(CometidoFuncionarioController::class, 'formData', $cometido, Establecimiento::findOrFail(1), $funcionarios, $periodo);
        $html = view('tramites.cometidos-funcionarios.form', $data)->render();
        $this->assertStringContainsString('Nombre del documento sintético', $html);
        $this->assertStringNotContainsString('Nuevo nombre sintético', $html);
        $this->assertStringContainsString('se conservan los antecedentes del documento', $html);
        $html = view('incumplimientos._form', [
            'item' => $item, 'isAdmin' => true, 'establecimientos' => Establecimiento::all(),
            'forcedEstablecimiento' => null, 'selectedEstablecimientoId' => 1,
            'selectedFuncionarioOption' => $this->invoke(IncumplimientoLaboralController::class, 'buildFuncionarioOption', 101, 1, $item),
        ])->render();
        $this->assertStringContainsString('Nombre del documento sintético', $html);
        $this->assertStringNotContainsString('Nuevo nombre sintético', $html);
        $this->assertStringContainsString('Antecedente del documento', $html);
    }

    public function test_cometido_cannot_select_stale_or_foreign_ids_without_document_context(): void
    {
        foreach ([104, 105, 106, 999] as $id) {
            try {
                $this->invoke(CometidoFuncionarioController::class, 'funcionarioPadronSeleccionado',
                    $this->request(['reemplazo_personal_id' => $id]), Establecimiento::findOrFail(1), $this->lista()[1]);
                $this->fail('Debe rechazar un funcionario fuera del padrón vigente.');
            } catch (ValidationException $e) { $this->assertArrayHasKey('reemplazo_personal_id', $e->errors()); }
        }
    }

    public function test_legacy_schema_without_vigencia_keeps_period_filter(): void
    {
        Schema::table('reemplazos_personal', fn (Blueprint $t) => $t->dropColumn('vigente'));
        $this->assertSame([101, 102, 103, 104], $this->lista()[1]->pluck('id')->all());
        $response = app(IncumplimientoLaboralController::class)->ajaxFuncionarios($this->request(['establecimiento_id' => 1], 'admin', 'GET'));
        $this->assertSame(['101', '102', '103', '104'], array_column($response->getData(true)['results'], 'id'));
    }

    public function test_preview_does_not_change_eligibility_and_form_deduplicates_normalized_rut(): void
    {
        Schema::create('padron_revisiones', function (Blueprint $t) { $t->id(); $t->integer('anio'); $t->integer('mes'); $t->timestamp('aplicada_at')->nullable(); });
        DB::table('padron_revisiones')->insert(['anio' => 2027, 'mes' => 1]);
        DB::table('reemplazos_personal')->where('id', 103)->update(['rut' => '99.999.102-K']);
        $this->assertCount(2, $this->lista()[1]);
        $response = app(IncumplimientoLaboralController::class)->ajaxFuncionarios($this->request(['establecimiento_id' => 1], 'admin', 'GET'));
        $this->assertCount(2, $response->getData(true)['results']);
        $this->assertSame([106], $this->lista(2)[1]->pluck('id')->all());
    }

    public function test_updates_keep_existing_document_authorization(): void
    {
        $cometido = $this->documento(CometidoFuncionario::class, true);
        $cometido->establecimiento_id = 2;
        try {
            app(CometidoFuncionarioController::class)->update($this->cometidoRequest(), $cometido);
            $this->fail('No puede editar un cometido de otro establecimiento.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $item = $this->documento(IncumplimientoLaboral::class, true);
        try {
            app(IncumplimientoLaboralController::class)->update($this->request([], 'funcionario_estab'), $item);
            $this->fail('Solo administrador puede editar incumplimientos.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $this->assertDatabaseCount('cometido_funcionario_historial', 0);
        $this->assertDatabaseCount('incumplimientos_laborales_historial', 0);
    }
}
