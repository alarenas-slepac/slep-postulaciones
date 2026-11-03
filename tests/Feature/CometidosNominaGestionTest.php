<?php

namespace Tests\Feature;

use App\Exports\CometidosNominaGestionExport;
use App\Http\Middleware\CoordinarEscrituraPadron;
use App\Http\Middleware\EnsureModuleAccess;
use App\Models\CometidoFuncionario;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class CometidosNominaGestionTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([EnsureModuleAccess::class, CoordinarEscrituraPadron::class]);
        $this->withoutVite();
        DB::connection()->getPdo()->sqliteCreateFunction('CONCAT', fn (...$values) => implode('', $values));
        Schema::table('users', fn (Blueprint $t) => $t->integer('establecimiento_id')->nullable());
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->integer('rbd'); $t->string('nombre_establecimiento');
        });
        Schema::create('funcionarios_ac_autorizados', function (Blueprint $t) {
            $t->id();
            foreach (['rut_normalizado', 'run', 'dv', 'estado_autorizacion', 'cargo_funcion', 'subdireccion_dependencia', 'unidad_departamento'] as $field) {
                $t->string($field)->nullable();
            }
            $t->boolean('jefatura')->default(false);
        });
        Schema::create('cometidos_funcionarios', function (Blueprint $t) {
            $t->id();
            foreach (['user_id', 'establecimiento_id', 'rbd', 'funcionario_ac_autorizado_id', 'jefatura_autorizadora_ac_id'] as $field) {
                $t->integer($field)->nullable();
            }
            foreach (['estado', 'estado_viatico', 'estado_reembolso', 'origen_cometido', 'numero_cometido_interno',
                'funcionario_nombre', 'funcionario_rut', 'estamento', 'cargo_funcion', 'subdireccion_dependencia_ac',
                'unidad_departamento_ac', 'destino', 'comuna_destino_nombre'] as $field) {
                $t->string($field)->nullable();
            }
            foreach (['fecha_solicitud', 'fecha_desde', 'fecha_hasta'] as $field) { $t->date($field)->nullable(); }
            foreach (['solicita_viatico', 'solicita_reembolso', 'requiere_pasaje_aereo', 'cdp_aprobado'] as $field) {
                $t->boolean($field)->default(false);
            }
            $t->timestamps();
        });
        Schema::create('cometido_funcionario_pasajes_aereos', function (Blueprint $t) {
            $t->id(); $t->integer('cometido_funcionario_id'); $t->string('estado_pasaje');
        });
        foreach ([1, 2] as $id) {
            DB::table('establecimientos')->insert(['id' => $id, 'rbd' => 99000 + $id, 'nombre_establecimiento' => 'Escuela sintética '.$id]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function usuario(string $role = 'admin'): User
    {
        $roleId = DB::table('roles')->where('name', $role)->value('id');
        if (!$roleId) {
            $roleId = DB::table('roles')->insertGetId(['name' => $role, 'guard_name' => 'web']);
        }
        $user = $this->testUser(1, $roleId);
        $user->forceFill(['establecimiento_id' => 1])->save();
        $this->actingAs($user)->withSession(['active_role' => $role]);
        return $user;
    }

    protected function solicitud(int $id, array $data = []): void
    {
        DB::table('cometidos_funcionarios')->insert(array_replace([
            'id' => $id, 'user_id' => 1, 'establecimiento_id' => 1, 'rbd' => 99001,
            'origen_cometido' => 'establecimiento', 'estado' => 'en_revision_uatp',
            'fecha_solicitud' => '2026-10-15', 'fecha_desde' => '2026-12-01', 'fecha_hasta' => '2026-12-03',
            'funcionario_nombre' => 'Persona sintética '.$id, 'funcionario_rut' => '99.000.001-K',
            'cargo_funcion' => 'Cargo sintético', 'estamento' => 'DOCENTE', 'destino' => 'Destino sintético',
            'created_at' => '2026-10-15 12:00:00', 'updated_at' => '2026-10-15 12:00:00',
        ], $data));
    }

    protected function url(array $query = []): string
    {
        return route('tramites.cometidos-funcionarios.index', array_replace(['tab' => 'estab_por_autorizar'], $query));
    }

    private function exportIds(array $query = []): array
    {
        $response = $this->get($this->url(array_replace(['exportar' => 'excel'], $query)))->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));
        $payload = $response->streamedContent();
        $this->assertStringStartsWith('PK', $payload);
        Storage::disk('local')->put('nomina.xlsx', $payload);
        $book = IOFactory::load(Storage::disk('local')->path('nomina.xlsx'));
        try {
            $sheet = $book->getActiveSheet();
            $ids = [];
            for ($line = 7; $line < $sheet->getHighestRow(); $line++) {
                $value = $sheet->getCell('A'.$line)->getValue();
                if (is_numeric($value)) { $ids[] = (int) $value; }
            }
            return $ids;
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public function test_exporta_todas_las_paginas_y_combina_los_filtros_de_la_bandeja_activa(): void
    {
        $this->usuario('coordinador_uatp');
        foreach (range(1, 13) as $id) { $this->solicitud($id); }
        $this->solicitud(20, ['fecha_solicitud' => '2026-09-30']);
        $this->solicitud(21, ['establecimiento_id' => 2]);
        $this->solicitud(22, ['funcionario_nombre' => 'Otra coincidencia']);
        $this->solicitud(23, ['funcionario_rut' => '99.000.002-K']);
        $this->solicitud(24, ['estado' => 'en_revision_cdp']);
        $query = ['mes' => '2026-10', 'nombre' => 'sintética', 'rut' => '99000001', 'estado' => 'en_revision_uatp', 'establecimiento_id' => 1];
        $before = DB::table('cometidos_funcionarios')->orderBy('id')->get()->toJson();
        $response = $this->get($this->url($query))->assertOk();
        $this->assertSame(13, $response->viewData('cometidosEstabPorAutorizar')->total());
        $this->assertCount(10, $response->viewData('cometidosEstabPorAutorizar'));
        $response->assertSee('name="mes"', false)->assertSee('value="2026-10"', false)->assertSee('Descargar nómina Excel');
        $this->assertStringContainsString('mes=2026-10', $response->viewData('cometidosEstabPorAutorizar')->nextPageUrl());
        $this->assertSame(range(13, 1), $this->exportIds($query + ['estab_por_autorizar_page' => 2]));
        $this->assertSame($before, DB::table('cometidos_funcionarios')->orderBy('id')->get()->toJson());
        $this->get($this->url())->assertOk()->assertViewHas('cometidosEstabPorAutorizar', fn ($p) => $p->total() === 17);
    }

    public function test_separa_origen_ac_establecimientos_y_registros_historicos(): void
    {
        $this->usuario();
        $this->solicitud(1, ['origen_cometido' => 'administracion_central', 'establecimiento_id' => null, 'estado' => 'en_revision_jefatura_ac']);
        $this->solicitud(2);
        $this->solicitud(3, ['origen_cometido' => null]);
        $this->solicitud(4, ['estado' => 'cerrado']);
        $this->assertSame([1], $this->exportIds(['tab' => 'ac_por_autorizar']));
        $this->assertSame([3, 2], $this->exportIds());
        $this->get($this->url(['tab' => 'ac_por_autorizar', 'mes' => '2026-10']))->assertOk()
            ->assertViewHas('cometidosAcPorAutorizar', fn ($p) => $p->pluck('id')->all() === [1]);
    }

    public static function rolesGestion(): array
    {
        return [
            ['admin', 'en_revision_uatp', null, null],
            ['coordinador_uatp', 'en_revision_uatp', null, null],
            ['supervisor_plani', 'en_gestion_paralela', 'en_revision_cdp', null],
            ['coordinador_plani', 'en_revision_cdp_rendicion', null, null],
            ['coordinador_gdp', 'en_gdp_resolucion', null, null],
            ['funcionario_slep', 'en_gdp_rex_cgr', null, null],
            ['funcionario_daf', 'en_gestion_paralela', null, 'en_daf_contable_reembolso'],
            ['funcionario_daf_compra', 'en_gdp_resolucion', null, null],
            ['director_ejecutivo', 'pendiente_autorizacion_director_sin_disponibilidad', null, null],
            ['funcionario_juridica', 'en_gestion_paralela', null, 'en_juridica_resolucion_reembolso'],
            ['juridica', 'en_juridica_resolucion_reembolso', null, null],
            ['juridico', 'en_juridica_resolucion_reembolso', null, null],
            ['abogado_juridica', 'en_juridica_resolucion_reembolso', null, null],
            ['coordinador_juridica', 'en_juridica_resolucion_reembolso', null, null],
        ];
    }

    #[DataProvider('rolesGestion')]
    public function test_exportacion_respeta_el_pendiente_del_rol_activo(string $role, string $estado, ?string $viatico, ?string $reembolso): void
    {
        $this->usuario($role);
        $this->solicitud(1, ['estado' => $estado, 'estado_viatico' => $viatico, 'estado_reembolso' => $reembolso,
            'requiere_pasaje_aereo' => $role === 'funcionario_daf_compra']);
        if ($role === 'funcionario_daf_compra') {
            DB::table('cometido_funcionario_pasajes_aereos')->insert(['cometido_funcionario_id' => 1, 'estado_pasaje' => 'pendiente_compra']);
        }
        $this->solicitud(2, ['estado' => 'cerrado']);
        $this->assertSame([1], $this->exportIds());
        $this->get($this->url())->assertOk()->assertViewHas('cometidosEstabPorAutorizar', fn ($p) => $p->pluck('id')->all() === [1]);
    }

    public function test_el_rol_admin_inactivo_no_amplia_la_nomina(): void
    {
        $user = $this->usuario('coordinador_uatp');
        DB::table('model_has_roles')->insert(['role_id' => 3, 'model_type' => User::class, 'model_id' => $user->id]);
        $user->unsetRelation('roles');
        $this->solicitud(1);
        $this->solicitud(2, ['estado' => 'en_gdp_resolucion']);
        $this->assertSame([1], $this->exportIds());
    }

    public function test_director_exporta_solo_las_jefaturas_y_dependencias_ac_que_le_corresponden(): void
    {
        $user = $this->usuario('director_ejecutivo');
        DB::table('funcionarios_ac_autorizados')->insert([
            ['id' => 1, 'rut_normalizado' => $user->rut, 'cargo_funcion' => 'Director Ejecutivo', 'jefatura' => true],
            ['id' => 2, 'rut_normalizado' => '99000002K', 'cargo_funcion' => 'Cargo sintético', 'jefatura' => true],
            ['id' => 3, 'rut_normalizado' => '99000003K', 'cargo_funcion' => 'Cargo sintético', 'jefatura' => false],
        ]);
        foreach ([1, 2, 3] as $id) {
            $this->solicitud($id, ['origen_cometido' => 'administracion_central', 'establecimiento_id' => null,
                'estado' => 'en_revision_jefatura_ac', 'funcionario_ac_autorizado_id' => $id,
                'jefatura_autorizadora_ac_id' => $id === 3 ? 1 : null]);
        }
        $this->solicitud(4, ['origen_cometido' => 'administracion_central', 'estado' => 'en_revision_jefatura_ac']);
        $this->assertSame([3, 2], $this->exportIds(['tab' => 'ac_por_autorizar']));
    }

    public static function solicitudesNoExportables(): array
    {
        return [['funcionario_estab', 'estab_por_autorizar'], ['funcionario_ac', 'ac_por_autorizar'],
            ['postulante', 'estab_por_autorizar'], ['admin', 'ac_autorizados'], ['admin', 'estab_autorizados'], ['admin', 'otra']];
    }

    #[DataProvider('solicitudesNoExportables')]
    public function test_impide_exportacion_fuera_de_bandejas_y_roles_de_gestion(string $role, string $tab): void
    {
        $user = $this->usuario($role);
        DB::table('funcionarios_ac_autorizados')->insert(['id' => 1, 'rut_normalizado' => $user->rut]);
        $this->solicitud(1);
        $this->get($this->url(['tab' => $tab, 'exportar' => 'excel']))->assertForbidden();
    }

    public function test_exportacion_exige_autenticacion(): void
    {
        $this->get($this->url(['exportar' => 'excel']))->assertRedirect(route('login'));
    }

    public static function mesesLimite(): array
    {
        return [['2026-02', '2026-02-28', '2026-03-01'], ['2024-02', '2024-02-29', '2024-03-01'], ['2025-12', '2025-12-31', '2026-01-01']];
    }

    #[DataProvider('mesesLimite')]
    public function test_mes_usa_fecha_solicitud_con_anio_y_limites_correctos_aun_en_dia_31(string $mes, string $ultimo, string $siguiente): void
    {
        $this->usuario(); Carbon::setTestNow('2026-01-31 12:00:00');
        $this->solicitud(1, ['fecha_solicitud' => $mes.'-01']);
        $this->solicitud(2, ['fecha_solicitud' => $ultimo]);
        $this->solicitud(3, ['fecha_solicitud' => $siguiente]);
        $this->solicitud(4, ['fecha_solicitud' => '2023-'.substr($mes, 5).'-15']);
        $this->assertSame([2, 1], $this->exportIds(['mes' => $mes]));
        DB::table('cometidos_funcionarios')->update(['estado' => 'cerrado']);
        $this->get($this->url(['tab' => 'estab_autorizados', 'seguimiento_mes' => $mes]))->assertOk()
            ->assertViewHas('cometidosEstabAutorizados', fn ($p) => $p->pluck('id')->all() === [2, 1]);
        $this->get(route('tramites.cometidos-funcionarios.seguimiento.exportar-excel', ['seguimiento_mes' => $mes]))
            ->assertOk()->assertSee('Persona sintética 1')->assertSee('Persona sintética 2')
            ->assertDontSee('Persona sintética 3')->assertDontSee('Persona sintética 4');
    }

    public function test_mes_invalido_se_informa_sin_error_500_y_sin_exportar_datos(): void
    {
        $this->usuario();
        foreach (['2026-13', '2026-2', 'ayer', ['2026-10']] as $mes) {
            $this->getJson($this->url(['mes' => $mes]))->assertUnprocessable()->assertJsonValidationErrors('mes');
            $this->getJson($this->url(['mes' => $mes, 'exportar' => 'excel']))->assertUnprocessable()->assertJsonValidationErrors('mes');
        }
        $this->getJson($this->url(['tab' => 'estab_autorizados', 'seguimiento_mes' => '2026-13']))
            ->assertUnprocessable()->assertJsonValidationErrors('seguimiento_mes');
    }

    public function test_la_exportacion_rechaza_filtros_malformados_sin_error_500(): void
    {
        $this->usuario();
        foreach (['estado', 'nombre', 'rut', 'establecimiento_id'] as $campo) {
            $this->getJson($this->url(['exportar' => 'excel', $campo => ['valor']]))
                ->assertUnprocessable()->assertJsonValidationErrors($campo);
        }
    }

    public function test_mes_en_listado_solicitante_conserva_establecimiento_y_no_muestra_exportacion_de_gestion(): void
    {
        $this->usuario('funcionario_estab');
        $this->solicitud(1); $this->solicitud(2, ['establecimiento_id' => 2]);
        $this->solicitud(3, ['fecha_solicitud' => '2026-09-30']);
        $this->get($this->url(['mes' => '2026-10']))->assertOk()->assertDontSee('Descargar nómina Excel')
            ->assertViewHas('cometidos', fn ($p) => $p->pluck('id')->all() === [1]);
    }

    public function test_excel_conserva_campos_fechas_y_texto_seguro_y_no_duplica_por_pasajes(): void
    {
        $this->solicitud(1, ['numero_cometido_interno' => 'CF-AC-SINTETICO', 'origen_cometido' => 'administracion_central',
            'subdireccion_dependencia_ac' => 'Dependencia histórica', 'funcionario_nombre' => '=1+1',
            'estado' => 'en_gestion_paralela', 'estado_viatico' => 'en_revision_cdp', 'estado_reembolso' => 'en_juridica_resolucion_reembolso']);
        DB::table('cometido_funcionario_pasajes_aereos')->insert([
            ['cometido_funcionario_id' => 1, 'estado_pasaje' => 'pendiente_compra'],
            ['cometido_funcionario_id' => 1, 'estado_pasaje' => 'pendiente_compra'],
        ]);
        $book = (new CometidosNominaGestionExport)->workbook(CometidoFuncionario::with(['establecimiento', 'funcionarioAcAutorizado', 'pasajeAereo'])->get(), 'ac_por_autorizar', ['mes' => '2026-10']);
        try {
            $sheet = $book->getActiveSheet();
            $this->assertSame('=1+1', $sheet->getCell('E7')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('E7')->getDataType());
            $this->assertSame('CF-AC-SINTETICO', $sheet->getCell('B7')->getValue());
            $this->assertSame('Dependencia histórica', $sheet->getCell('I7')->getValue());
            $this->assertSame('', $sheet->getCell('J7')->getValue());
            $this->assertEquals(Date::PHPToExcel(new \DateTimeImmutable('2026-10-15')), $sheet->getCell('C7')->getValue());
            $this->assertSame('dd-mm-yyyy', $sheet->getStyle('C7')->getNumberFormat()->getFormatCode());
            $this->assertSame('En revisión CDP', $sheet->getCell('Q7')->getValue());
            $this->assertSame(CometidoFuncionario::ESTADOS['en_juridica_resolucion_reembolso'], $sheet->getCell('R7')->getValue());
            $this->assertSame('Pendiente de compra', $sheet->getCell('T7')->getValue());
            $this->assertSame('Total: 1 solicitud(es)', $sheet->getCell('A8')->getValue());
            $this->assertSame('F7', $sheet->getFreezePane()); $this->assertSame('A6:T7', $sheet->getAutoFilter()->getRange());
        } finally { $book->disconnectWorksheets(); }
        $this->usuario();
        $this->assertSame([], $this->exportIds(['mes' => '2020-01']));
    }
}
