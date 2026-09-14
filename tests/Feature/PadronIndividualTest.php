<?php

namespace Tests\Feature;

use App\Http\Controllers\PadronIndividualController;
use App\Models\ReemplazoPersonal;
use App\Models\User;
use App\Services\Padron\PadronIndividualService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PadronIndividualTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        Schema::create('users', fn (Blueprint $t) => $t->id());
        DB::table('users')->insert(['id' => 1]);
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento');
        });
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => 99999, 'nombre_establecimiento' => 'Escuela sintética A'],
            ['id' => 2, 'rbd' => 99998, 'nombre_establecimiento' => 'Escuela sintética B'],
        ]);
        (require base_path('database/migrations/2026_01_29_000001_create_reemplazos_personal_table.php'))->up();
        Schema::table('reemplazos_personal', function (Blueprint $t) {
            $t->integer('bienios')->nullable(); $t->string('tramo')->nullable(); $t->boolean('vigente')->default(true);
        });
        (require base_path('database/migrations/2026_09_08_120000_create_padron_revisiones.php'))->up();
        (require base_path('database/migrations/2026_09_08_130000_add_padron_aplicacion_segura.php'))->up();
        (require base_path('database/migrations/2026_09_08_160000_create_padron_periodo_versiones.php'))->up();
        (require base_path('database/migrations/2026_09_14_140000_create_padron_individual_cambios.php'))->up();
        $this->personal();
    }

    private function service(): PadronIndividualService { return app(PadronIndividualService::class); }

    private function usuario(bool $admin = true): User
    {
        $user = new class extends User {
            public bool $administrador = true;
            public function hasRole($roles, ?string $guard = null): bool { return $this->administrador && $roles === 'admin'; }
            public function hasAnyRole(...$roles): bool { return $this->administrador; }
        };
        $user->id = 1;
        $user->email_verified_at = now();
        $user->administrador = $admin;
        return $user;
    }

    private function personal(int $id = 101, array $changes = []): void
    {
        DB::table('reemplazos_personal')->insert(array_replace([
            'id' => $id, 'rut' => '111111111', 'nombre' => 'Persona sintética A',
            'establecimiento_id' => 1, 'rbd' => 99999, 'anio' => 2026, 'mes' => 8,
            'jornada' => 30, 'jornada_basica' => 30, 'jornada_media' => 0,
            'tipocontrato' => 'PLANTA', 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE',
            'financiamiento' => 'SUB.GENERAL', 'fecha_ingreso' => '2026-03-01',
            'row_hash' => hash('sha256', 'sintetico-'.$id), 'created_by' => 1,
        ], $changes));
    }

    private function entrada(array $changes = []): array
    {
        $data = array_replace([
            'rut' => '111111111', 'personal_id' => 101, 'periodo' => 202608,
            'establecimiento_id' => 1, 'nombre' => 'Persona sintética A', 'tipocontrato' => 'PLANTA',
            'fecha_nacimiento' => '1980-01-01', 'fecha_ingreso' => '2026-03-01', 'fecha_termino' => null,
            'fecha_antiguedad' => '2005-03-01', 'financiamiento' => 'SUB.GENERAL',
            'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE', 'jornada' => 30,
            'jornada_basica' => 30, 'jornada_media' => 0, 'bienios' => 4, 'tramo' => 'Temprano',
            'justificacion' => 'Regularización contractual sintética',
        ], $changes);
        $data['huella'] ??= $this->service()->huella($this->service()->registros($this->service()->rut($data['rut'])), $data['periodo']);
        return $data;
    }

    private function rechaza(array $data, string $campo): void
    {
        $before = DB::table('reemplazos_personal')->orderBy('id')->get()->toJson();
        $audits = DB::table('padron_individual_cambios')->count();
        try {
            $this->service()->guardar($data, $this->usuario());
            $this->fail('Debe rechazar sin escrituras.');
        } catch (ValidationException $e) { $this->assertArrayHasKey($campo, $e->errors()); }
        $this->assertSame($before, DB::table('reemplazos_personal')->orderBy('id')->get()->toJson());
        $this->assertDatabaseCount('padron_individual_cambios', $audits);
    }

    public function test_crea_primer_contrato_y_audita_sin_modificar_otros_ruts(): void
    {
        $row = $this->service()->guardar($this->entrada(['rut' => '222222222', 'personal_id' => null]), $this->usuario());
        $this->assertNotSame(101, $row->id);
        $this->assertSame('222222222', $row->rut);
        $this->assertSame(2026, $row->anio);
        $this->assertSame(8, $row->mes);
        $this->assertDatabaseCount('reemplazos_personal', 2);
        $this->assertDatabaseHas('padron_individual_cambios', ['personal_id' => $row->id, 'accion' => 'incorporacion', 'usuario_id' => 1]);
    }

    public static function contratos(): array
    {
        return array_map(fn ($tipo) => [$tipo], ['PLANTA', 'CONTRATA', 'PLAZO FIJO', 'INDEFINIDO', 'PLANTA PIE']);
    }

    #[DataProvider('contratos')]
    public function test_actualiza_regular_con_mismo_id_hash_y_datos_completos(string $tipo): void
    {
        DB::table('reemplazos_personal')->where('id', 101)->update(['tipocontrato' => $tipo]);
        $row = $this->service()->guardar($this->entrada([
            'tipocontrato' => 'CONTRATA', 'establecimiento_id' => 2, 'jornada' => 40,
            'jornada_basica' => 22, 'jornada_media' => 18, 'fecha_termino' => '2026-12-31',
            'bienios' => 8, 'tramo' => 'Avanzado',
        ]), $this->usuario());
        $this->assertSame(101, $row->id);
        $this->assertSame(hash('sha256', 'sintetico-101'), $row->row_hash);
        $this->assertSame(99998, $row->rbd);
        $this->assertSame(40, $row->jornada);
        $this->assertSame(8, $row->bienios);
        $this->assertSame('Avanzado', $row->tramo);
        $this->assertDatabaseCount('reemplazos_personal', 1);
        $audit = DB::table('padron_individual_cambios')->first();
        $this->assertSame(30, json_decode($audit->antes, true)['jornada']);
        $this->assertSame(40, json_decode($audit->despues, true)['jornada']);
    }

    public function test_reemplazo_nuevo_no_sobrescribe_regular_ni_reemplazo_anterior(): void
    {
        $this->personal(102, ['tipocontrato' => 'REEMPLAZO', 'jornada' => 10, 'fecha_termino' => '2026-07-31']);
        $row = $this->service()->guardar($this->entrada([
            'personal_id' => null, 'tipocontrato' => 'REEMPLAZO', 'jornada' => 10,
            'jornada_basica' => 10, 'fecha_ingreso' => '2026-08-01', 'fecha_termino' => '2026-08-31',
        ]), $this->usuario());
        $this->assertSame(103, $row->id);
        $this->assertDatabaseCount('reemplazos_personal', 3);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'tipocontrato' => 'PLANTA', 'jornada' => 30]);
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 102, 'fecha_termino' => '2026-07-31']);
    }

    public function test_impide_duplicar_regular_y_exige_seleccion_si_hay_varios(): void
    {
        $this->personal(102, ['jornada' => 4, 'financiamiento' => 'PIE']);
        $this->rechaza($this->entrada(['personal_id' => null]), 'personal_id');
        $this->service()->guardar($this->entrada(['jornada' => 32]), $this->usuario());
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 102, 'jornada' => 4]);
    }

    public function test_no_permite_convertir_regular_en_reemplazo_ni_editar_otro_rut(): void
    {
        $this->rechaza($this->entrada(['tipocontrato' => 'REEMPLAZO', 'fecha_termino' => '2026-08-31']), 'personal_id');
        $this->rechaza($this->entrada(['rut' => '222222222']), 'personal_id');
        DB::table('reemplazos_personal')->where('id', 101)->update(['tipocontrato' => 'REEMPLAZO']);
        $this->rechaza($this->entrada(), 'personal_id');
    }

    public function test_rechaza_doble_envio_y_duplicado_exacto_con_consulta_nueva(): void
    {
        $data = $this->entrada(['rut' => '222222222', 'personal_id' => null, 'tipocontrato' => 'REEMPLAZO', 'fecha_termino' => '2026-08-31']);
        $this->service()->guardar($data, $this->usuario());
        $this->rechaza($data, 'padron');
        unset($data['huella']);
        $this->rechaza($this->entrada($data), 'padron');
    }

    public function test_control_44_horas_suma_rbd_y_requiere_autorizacion_justificada(): void
    {
        $this->personal(102, ['establecimiento_id' => 2, 'rbd' => 99998, 'jornada' => 14]);
        $data = $this->entrada(['jornada' => 32]);
        $this->rechaza($data, 'autorizar_exceso');
        $this->rechaza($data + ['autorizar_exceso' => true, 'justificacion_exceso' => 'corta'], 'autorizar_exceso');
        $this->service()->guardar($data + ['autorizar_exceso' => true, 'justificacion_exceso' => 'Excepción sintética autorizada'], $this->usuario());
        $control = json_decode(DB::table('padron_individual_cambios')->first()->controles, true);
        $this->assertSame(46, $control['maximo_horas_docentes']);
        $this->assertTrue($control['autorizar_exceso']);
    }

    public function test_44_horas_no_suma_reemplazos_consecutivos_pero_si_dia_compartido(): void
    {
        DB::table('reemplazos_personal')->where('id', 101)->update(['tipocontrato' => 'REEMPLAZO', 'jornada' => 44, 'fecha_termino' => '2026-08-15']);
        $data = $this->entrada(['personal_id' => null, 'tipocontrato' => 'REEMPLAZO', 'jornada' => 44,
            'fecha_ingreso' => '2026-08-15', 'fecha_termino' => '2026-08-31']);
        $this->rechaza($data, 'autorizar_exceso');
        $data['fecha_ingreso'] = '2026-08-16';
        $this->service()->guardar($data, $this->usuario());
        $control = json_decode(DB::table('padron_individual_cambios')->first()->controles, true);
        $this->assertSame(44, $control['maximo_horas_docentes']);
    }

    public function test_historicos_no_se_editan_ni_se_duplican_regulares(): void
    {
        $this->personal(102, ['mes' => 7]);
        $this->rechaza($this->entrada(['personal_id' => 102]), 'personal_id');
        $this->rechaza($this->entrada(['periodo' => 202609]), 'padron');
        DB::table('reemplazos_personal')->where('id', 101)->update(['rut' => '222222222']);
        $this->rechaza($this->entrada(['personal_id' => null]), 'personal_id');
    }

    public function test_reactiva_mismo_id_del_periodo_abierto(): void
    {
        DB::table('reemplazos_personal')->where('id', 101)->update(['vigente' => false]);
        $this->rechaza($this->entrada(['personal_id' => null]), 'personal_id');
        $row = $this->service()->guardar($this->entrada(), $this->usuario());
        $this->assertSame(101, $row->id);
        $this->assertTrue($row->vigente);
        $this->assertDatabaseHas('padron_individual_cambios', ['personal_id' => 101, 'accion' => 'reactivacion']);
    }

    public function test_conserva_documento_y_asignaciones_en_traslado_con_confirmacion(): void
    {
        Schema::create('solicitudes_reemplazo', function (Blueprint $t) {
            $t->id(); $t->integer('reemplazo_personal_id'); $t->json('padron_personal_snapshot')->nullable();
        });
        DB::table('solicitudes_reemplazo')->insert(['id' => 201, 'reemplazo_personal_id' => 101]);
        Schema::create('dotacion_docente_asignaciones', function (Blueprint $t) {
            $t->id(); $t->integer('establecimiento_id'); $t->integer('anio'); $t->string('estado');
            $t->integer('reemplazos_personal_id')->nullable(); $t->string('docente_rut'); $t->integer('horas_contrato');
        });
        DB::table('dotacion_docente_asignaciones')->insert(['id' => 301, 'establecimiento_id' => 1, 'anio' => 2026,
            'estado' => 'activa', 'reemplazos_personal_id' => null, 'docente_rut' => '11.111.111-1', 'horas_contrato' => 30]);
        $this->rechaza($this->entrada(['establecimiento_id' => 2]), 'confirmar_asignaciones');
        $this->assertNull(DB::table('solicitudes_reemplazo')->value('padron_personal_snapshot'));
        $this->service()->guardar($this->entrada(['establecimiento_id' => 2, 'confirmar_asignaciones' => true]), $this->usuario());
        $doc = DB::table('solicitudes_reemplazo')->first();
        $this->assertSame(101, $doc->reemplazo_personal_id);
        $this->assertSame(1, json_decode($doc->padron_personal_snapshot, true)['personal']['establecimiento_id']);
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 301, 'establecimiento_id' => 1, 'estado' => 'activa']);
    }

    public function test_rut_dv_validado_y_normalizacion_no_cambia_rut_guardado(): void
    {
        $this->assertSame('111111111', $this->service()->rut('11.111.111-1'));
        DB::table('reemplazos_personal')->where('id', 101)->update(['rut' => '11.111.111-1']);
        $row = $this->service()->guardar($this->entrada(), $this->usuario());
        $this->assertSame('11.111.111-1', $row->rut);
        foreach (['111111112', 'abc111111111', '11111111', '0-0'] as $invalid) {
            try { $this->service()->rut($invalid); $this->fail('Debe rechazar el RUT.'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('rut', $e->errors()); }
        }
    }

    public function test_fechas_jornadas_y_datos_internos(): void
    {
        $this->rechaza($this->entrada(['fecha_termino' => '2025-01-01']), 'fecha_termino');
        $this->rechaza($this->entrada(['jornada_media' => 10]), 'jornada');
        $this->rechaza($this->entrada(['tipocontrato' => 'REEMPLAZO', 'personal_id' => null]), 'fecha_termino');
        $this->service()->guardar($this->entrada(['id' => 999, 'rbd' => 88888, 'vigente' => false,
            'created_by' => 999, 'row_hash' => 'alterado']), $this->usuario());
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'rbd' => 99999, 'vigente' => true, 'created_by' => 1]);
    }

    public function test_error_de_auditoria_revierte_contrato(): void
    {
        DB::listen(function (QueryExecuted $e) {
            if (str_starts_with($e->sql, 'insert into "padron_individual_cambios"')) { throw new \RuntimeException('Fallo sintético'); }
        });
        try { $this->service()->guardar($this->entrada(['jornada' => 32]), $this->usuario()); $this->fail('Debe revertir.'); }
        catch (\RuntimeException $e) { $this->assertSame('Fallo sintético', $e->getMessage()); }
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'jornada' => 30]);
        $this->assertDatabaseCount('padron_individual_cambios', 0);
    }

    public function test_control_compartido_es_primera_lectura_transaccional(): void
    {
        $queries = [];
        DB::listen(function (QueryExecuted $e) use (&$queries) { if (DB::transactionLevel() > 0) { $queries[] = $e->sql; } });
        $this->service()->guardar($this->entrada(), $this->usuario());
        $this->assertStringContainsString('padron_aplicacion_control', $queries[0]);
    }

    public function test_bloqueo_sigue_rut_en_traslado_y_nuevo_reemplazo(): void
    {
        (require base_path('database/migrations/2026_04_22_180000_create_reemplazos_personal_bloqueos_table.php'))->up();
        DB::table('reemplazos_personal_bloqueos')->insert(['id' => 401, 'reemplazo_personal_id' => 101,
            'establecimiento_id' => 1, 'rut' => '11.111.111-1', 'motivo' => 'Bloqueo sintético', 'activo' => true]);
        $this->service()->guardar($this->entrada(['establecimiento_id' => 2]), $this->usuario());
        $nuevo = $this->service()->guardar($this->entrada(['personal_id' => null, 'tipocontrato' => 'REEMPLAZO',
            'jornada' => 10, 'jornada_basica' => 10, 'fecha_termino' => '2026-08-31']), $this->usuario());
        $this->assertTrue(app(\App\Services\Padron\PadronBloqueoService::class)->bloqueado($nuevo));
        $this->assertDatabaseHas('reemplazos_personal_bloqueos', ['id' => 401, 'establecimiento_id' => 1, 'reemplazo_personal_id' => 101, 'activo' => true]);
        $this->assertDatabaseCount('reemplazos_personal_bloqueos', 1);
    }

    public function test_exige_migracion_sin_degradar_a_escritura_no_auditada(): void
    {
        Schema::drop('padron_individual_cambios'); // Solo SQLite :memory:.
        try { $this->service()->guardar($this->entrada(), $this->usuario()); $this->fail('No guardar sin auditoría.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('padron', $e->errors()); }
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'jornada' => 30]);
    }

    public static function consultasSinRegistros(): array
    {
        return [
            'apertura inicial' => [[], false],
            'rut vacío' => [['rut' => ''], false],
            'rut nulo' => [['rut' => null], false],
            'rut válido sin coincidencias' => [['rut' => '222222222'], true],
        ];
    }

    #[DataProvider('consultasSinRegistros')]
    public function test_apertura_y_busqueda_sin_registros_renderizan_sin_escrituras(array $query, bool $busqueda): void
    {
        $service = $this->service();
        if (! $busqueda) {
            $service = \Mockery::mock(PadronIndividualService::class)->makePartial();
            $service->shouldNotReceive('registros');
        }
        $antes = DB::table('reemplazos_personal')->get()->toJson();
        $view = app(PadronIndividualController::class)->index(Request::create('/', 'GET', $query), $service);
        $datos = $view->getData();
        $this->assertCount(0, $datos['registros']);
        $this->assertCount(0, $datos['editables']);
        $this->assertNull($datos['item']);
        $source = file_get_contents(resource_path('views/reemplazos/personal/individual.blade.php'));
        $html = Blade::render(str_replace(["@extends('layouts.app')", "@section('content')", '@endsection'], '', $source), $datos + ['errors' => new \Illuminate\Support\ViewErrorBag]);
        $this->assertStringContainsString('Validar RUT y consultar registros', $html);
        if ($busqueda) {
            $this->assertStringContainsString('Este RUT no tiene registros', $html);
            $this->assertStringContainsString('Crear nuevo registro', $html);
        } else {
            $this->assertStringNotContainsString('Crear nuevo registro', $html);
        }
        $this->assertSame($antes, DB::table('reemplazos_personal')->get()->toJson());
        $this->assertDatabaseCount('padron_individual_cambios', 0);
    }

    public function test_vista_busqueda_y_formulario_sin_escrituras(): void
    {
        $request = Request::create('/', 'GET', ['rut' => '11.111.111-1', 'personal_id' => 101]);
        $view = app(PadronIndividualController::class)->index($request, $this->service());
        $this->assertSame(101, $view->getData()['item']->id);
        $this->assertSame(202608, $view->getData()['periodo']);
        $source = file_get_contents(resource_path('views/reemplazos/personal/individual.blade.php'));
        $this->assertNotEmpty(token_get_all(app('blade.compiler')->compileString($source), TOKEN_PARSE));
        $html = Blade::render(str_replace(["@extends('layouts.app')", "@section('content')", '@endsection'], '', $source), $view->getData() + ['errors' => new \Illuminate\Support\ViewErrorBag]);
        $this->assertStringContainsString('Guardar cambios conservando ID 101', $html);
        $this->assertStringContainsString('fecha_antiguedad', $html);
        $this->assertStringNotContainsString('value="REEMPLAZO"', $html);
        $this->assertDatabaseCount('padron_individual_cambios', 0);
    }

    public function test_rutas_exigen_admin_y_post_guarda_usando_grupo_web(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\EnsureModuleAccess::class, \App\Http\Middleware\TouchLastSeen::class]);
        $this->actingAs($this->usuario(false));
        $this->get(route('reemplazos.individual.index'))->assertForbidden();
        $this->post(route('reemplazos.individual.store'), $this->entrada())->assertForbidden();
        $this->actingAs($this->usuario());
        $this->post(route('reemplazos.individual.store'), $this->entrada())->assertRedirect(route('reemplazos.individual.index', ['rut' => '111111111']));
        $this->assertDatabaseCount('padron_individual_cambios', 1);
        $route = app('router')->getRoutes()->getByName('reemplazos.individual.store');
        $this->assertContains('web', $route->gatherMiddleware());
        $this->assertContains('ensure.role:admin', $route->gatherMiddleware());
    }

    private function prepararAsignacionesTraslado(): void
    {
        (require base_path('database/migrations/2026_05_28_210000_create_dotacion_docente_asignaciones_table.php'))->up();
        foreach ([301 => 1, 302 => 1, 303 => 2] as $id => $est) {
            DB::table('dotacion_docente_asignaciones')->insert([
                'id' => $id, 'establecimiento_id' => $est, 'anio' => 2026,
                'docente_rut' => '11.111.111-1', 'docente_rut_normalizado' => '111111111',
                'reemplazos_personal_id' => $id === 301 ? 101 : null,
                'tipo_asignacion' => 'plan_estudio', 'asignatura_nombre' => 'Asignatura sintética',
                'necesidad_key' => 'necesidad-sintetica-'.$id, 'horas_contrato' => 2,
            ]);
        }
    }

    private function traslado(array $ids = [301], array $changes = []): array
    {
        $liberador = app(\App\Services\Padron\PadronIndividualAsignacionesService::class);
        $huellas = $this->service()->asignaciones(ReemplazoPersonal::findOrFail(101))
            ->mapWithKeys(fn ($a) => [$a->id => $liberador->huella($a)])->all();
        return $this->entrada(array_replace([
            'establecimiento_id' => 2, 'liberar_asignaciones' => $ids, 'huellas_asignaciones' => $huellas,
            'confirmar_liberacion' => true, 'justificacion_liberacion' => 'Traslado sintético autorizado',
            'confirmar_asignaciones' => true,
        ], $changes));
    }

    public function test_traslado_libera_solo_seleccionadas_y_audita_antes_despues(): void
    {
        $this->prepararAsignacionesTraslado();
        $antes = (array) DB::table('dotacion_docente_asignaciones')->find(301);
        $this->service()->guardar($this->traslado(), $this->usuario());
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'establecimiento_id' => 2]);
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 301, 'estado' => 'inactiva', 'updated_by' => 1]);
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 302, 'estado' => 'activa']);
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 303, 'estado' => 'activa']);
        $this->assertDatabaseCount('dotacion_docente_asignaciones', 3);
        $control = json_decode(DB::table('padron_individual_cambios')->first()->controles, true);
        $this->assertSame($antes, $control['liberaciones'][0]['antes']);
        $despues = $control['liberaciones'][0]['despues'];
        foreach (array_diff(array_keys($antes), ['estado', 'updated_by', 'updated_at']) as $campo) {
            $this->assertSame($antes[$campo], $despues[$campo], $campo.' debe conservarse');
        }
        $this->assertSame('inactiva', $despues['estado']);
        $this->assertSame('Traslado sintético autorizado', $control['justificacion_liberacion']);
        $this->assertSame(4.0, (float) DB::table('dotacion_docente_asignaciones')->where('estado', 'activa')->sum('horas_contrato'));
    }

    public function test_traslado_no_libera_destino_ni_requiere_cambiar_de_nuevo_un_traslado_previo(): void
    {
        $this->prepararAsignacionesTraslado();
        $this->rechaza($this->traslado([303]), 'liberar_asignaciones');
        DB::table('reemplazos_personal')->where('id', 101)->update(['establecimiento_id' => 2, 'rbd' => 99998]);
        $this->service()->guardar($this->traslado([301, 302]), $this->usuario());
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 301, 'estado' => 'inactiva']);
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 302, 'estado' => 'inactiva']);
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 303, 'estado' => 'activa']);
    }

    public function test_traslado_exige_seleccion_confirmacion_y_justificacion(): void
    {
        $this->prepararAsignacionesTraslado();
        $this->rechaza($this->traslado([301], ['confirmar_liberacion' => false]), 'liberar_asignaciones');
        $this->rechaza($this->traslado([301], ['justificacion_liberacion' => '']), 'liberar_asignaciones');
        $this->rechaza($this->traslado([301, 301]), 'liberar_asignaciones.0');
        $this->service()->guardar($this->traslado([]), $this->usuario());
        $this->assertSame(3, DB::table('dotacion_docente_asignaciones')->where('estado', 'activa')->count());
    }

    public function test_traslado_rechaza_otro_rut_ano_inactivas_y_vinculos_inconsistentes(): void
    {
        $this->prepararAsignacionesTraslado();
        foreach ([['anio' => 2025], ['estado' => 'inactiva'], ['docente_rut' => '222222222', 'reemplazos_personal_id' => null],
            ['docente_rut' => '222222222', 'reemplazos_personal_id' => 101]] as $cambio) {
            $original = (array) DB::table('dotacion_docente_asignaciones')->find(301);
            DB::table('dotacion_docente_asignaciones')->where('id', 301)->update($cambio);
            $this->rechaza($this->traslado([301]), 'liberar_asignaciones');
            DB::table('dotacion_docente_asignaciones')->where('id', 301)->update($original);
        }
        $this->assertSame(3, DB::table('dotacion_docente_asignaciones')->where('estado', 'activa')->count());
    }

    public function test_traslado_revalida_horas_solo_de_seleccionadas_sin_escrituras_parciales(): void
    {
        $this->prepararAsignacionesTraslado();
        $data = $this->traslado([301, 302]);
        DB::table('dotacion_docente_asignaciones')->where('id', 302)->update(['horas_contrato' => 4]);
        $this->rechaza($data, 'liberar_asignaciones');
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 301, 'estado' => 'activa']);
        $data = $this->traslado([301]);
        DB::table('dotacion_docente_asignaciones')->where('id', 302)->update(['horas_contrato' => 5]);
        $this->service()->guardar($data, $this->usuario());
        $this->assertDatabaseHas('dotacion_docente_asignaciones', ['id' => 302, 'horas_contrato' => 5, 'estado' => 'activa']);
    }

    public function test_traslado_fallo_auditoria_revierte_contrato_y_liberaciones(): void
    {
        $this->prepararAsignacionesTraslado();
        DB::listen(function (QueryExecuted $e) {
            if (str_starts_with($e->sql, 'insert into "padron_individual_cambios"')) { throw new \RuntimeException('Fallo sintético de liberación'); }
        });
        try { $this->service()->guardar($this->traslado([301, 302]), $this->usuario()); $this->fail('Debe revertir todo.'); }
        catch (\RuntimeException $e) { $this->assertSame('Fallo sintético de liberación', $e->getMessage()); }
        $this->assertDatabaseHas('reemplazos_personal', ['id' => 101, 'establecimiento_id' => 1]);
        $this->assertSame(3, DB::table('dotacion_docente_asignaciones')->where('estado', 'activa')->count());
        $this->assertDatabaseCount('padron_individual_cambios', 0);
    }

    public function test_traslado_muestra_ubicacion_detalle_y_seleccion_no_premarcada(): void
    {
        $this->prepararAsignacionesTraslado();
        $view = app(PadronIndividualController::class)->index(Request::create('/', 'GET', ['rut' => '111111111', 'personal_id' => 101]), $this->service());
        $source = file_get_contents(resource_path('views/reemplazos/personal/individual.blade.php'));
        $html = Blade::render(str_replace(["@extends('layouts.app')", "@section('content')", '@endsection'], '', $source), $view->getData() + ['errors' => new \Illuminate\Support\ViewErrorBag]);
        $this->assertStringContainsString('RBD 99999', $html);
        $this->assertStringContainsString('Escuela sintética A', $html);
        $this->assertStringContainsString('RBD 99998', $html);
        $this->assertStringContainsString('Asignatura sintética', $html);
        $this->assertSame(3, substr_count($html, 'name="liberar_asignaciones[]"'));
        $this->assertStringContainsString('name="huellas_asignaciones[301]"', $html);
        $this->assertDoesNotMatchRegularExpression('/name="liberar_asignaciones\[\]"[^>]*checked/', $html);
        $this->assertSame(3, DB::table('dotacion_docente_asignaciones')->where('estado', 'activa')->count());
    }
}
