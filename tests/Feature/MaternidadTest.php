<?php

namespace Tests\Feature;

use App\Models\MaternidadCaso;
use App\Models\MaternidadImportacion;
use App\Services\Maternidad\MaternidadFechas;
use App\Services\Maternidad\MaternidadImportador;
use App\Services\Maternidad\MaternidadPersonalResolver;
use App\Services\Maternidad\MaternidadRegistroService;
use App\Services\Maternidad\MaternidadRut;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\IsolatedSecurityTestCase;

class MaternidadTest extends IsolatedSecurityTestCase
{
    private const RUT = '12345678-5'; // Identidad sintética, sin datos personales reales.
    private $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-30 12:00:00'));
        Schema::create('establecimientos', function (Blueprint $t) {
            $t->id(); $t->string('rbd'); $t->string('nombre_establecimiento'); $t->string('comuna');
        });
        Schema::create('reemplazos_personal', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('establecimiento_id'); $t->string('rut'); $t->string('nombre');
            $t->string('nombres')->nullable(); $t->string('apellido_paterno')->nullable(); $t->string('apellido_materno')->nullable();
            $t->string('estatuto'); $t->string('escalafon'); $t->string('tipocontrato'); $t->date('fecha_ingreso');
            $t->date('fecha_termino')->nullable(); $t->integer('anio'); $t->integer('mes'); $t->timestamps();
        });
        Schema::create('postulant_profiles', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('user_id'); $t->string('estamento'); });
        Schema::create('solicitudes_reemplazo', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('establecimiento_id'); $t->unsignedBigInteger('postulant_profile_id')->nullable();
            $t->unsignedBigInteger('contrato_trabajo_postulant_profile_id')->nullable(); $t->string('rut_reemplazo_normalizado')->nullable();
            $t->string('estado'); $t->string('numero_solicitud'); $t->date('fecha_inicio'); $t->date('fecha_termino')->nullable();
            $t->date('fecha_inicio_trabajo')->nullable(); $t->date('contrato_trabajo_fecha_inicio_trabajo')->nullable();
        });
        (require database_path('migrations/2026_10_30_180000_create_maternidad_tables.php'))->up();
        DB::table('establecimientos')->insert(['id' => 1, 'rbd' => '99001', 'nombre_establecimiento' => 'Establecimiento de prueba', 'comuna' => 'Comuna de prueba']);
        $this->padron();
        foreach (array_slice(config('maternidad.roles'), 1) as $i => $role) DB::table('roles')->insert(['id' => 10 + $i, 'name' => $role, 'guard_name' => 'web']);
        $this->actor = $this->testUser(1, 3);
        // Se aísla sólo el layout para no consultar módulos ajenos a esta prueba.
        $views = Storage::disk('local')->path('test-views');
        if (!is_dir($views.'/layouts')) mkdir($views.'/layouts', 0777, true);
        file_put_contents($views.'/layouts/app.blade.php', '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">@vite(["resources/scss/app.scss", "resources/js/app.js"])@stack("styles")</head><body>@yield("content")@stack("scripts")</body></html>');
        View::prependLocation($views);
        app(\Illuminate\Foundation\Vite::class)->useHotFile($views.'/no-hot-file');
        $this->actingAs($this->actor)->withSession(['active_role' => 'admin']);
    }

    private function padron(array $overrides = []): int
    {
        return DB::table('reemplazos_personal')->insertGetId(array_replace(['establecimiento_id' => 1, 'rut' => self::RUT,
            'nombre' => 'APELLIDOAPELLIDO MATERNO NOMBRES PRUEBA', 'estatuto' => 'DOCENTE', 'escalafon' => 'DOCENTE',
            'tipocontrato' => 'TITULAR', 'fecha_ingreso' => '2025-03-01', 'anio' => 2026, 'mes' => 10], $overrides));
    }

    private function entrada(array $extra = []): array
    {
        return array_replace(['rut' => self::RUT, 'fuente' => 'padron:1', 'referencia_caso' => 'CASO-PRUEBA',
            'fecha_registro' => '2026-10-30', 'fpp' => '2026-12-01'], $extra);
    }

    private function caso(array $extra = [], array $files = [], bool $masivo = false): MaternidadCaso
    {
        if (!$masivo) { $files += ['embarazo' => $this->pdf('embarazo.pdf')]; $extra += ['fecha_certificado_embarazo' => '2026-10-01']; }
        $service = app(MaternidadRegistroService::class);
        return $service->guardar($service->preparar($this->entrada($extra), $files, null, $masivo), $files, $this->actor->id, null, null, $masivo);
    }

    private function excel(array $rows, array $children = []): UploadedFile
    {
        $book = app(MaternidadImportador::class)->plantilla();
        foreach ($rows as $i => $row) foreach (MaternidadImportador::COLUMNAS as $col => $name) {
            if (array_key_exists($name, $row)) $book->getSheet(0)->setCellValueExplicit([$col + 1, $i + 2], $row[$name], is_numeric($row[$name]) ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
        }
        foreach ($children as $i => $row) foreach (MaternidadImportador::COLUMNAS_HIJOS as $col => $name) {
            if (isset($row[$name])) $book->getSheet(1)->setCellValue([$col + 1, $i + 2], $row[$name]);
        }
        $file = Storage::disk('local')->path('carga-'.uniqid().'.xlsx'); (new Xlsx($book))->save($file); $book->disconnectWorksheets();
        return new UploadedFile($file, 'carga.xlsx', null, null, true);
    }

    private function pdf(string $name = 'certificado.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF");
    }

    public function test_fechas_respetan_respaldos_prioridad_y_anios_bisiestos(): void
    {
        $service = app(MaternidadFechas::class);
        $datos = ['fpp' => '2024-02-29', 'fnh' => '2024-02-28', 'ley_dominga' => true, 'fecha_ley_dominga' => '2024-03-01', 'lactancia' => true];
        $sin = $service->calcular($datos, []);
        $this->assertSame('2025-05-23', $sin['fecha_termino_fuero']);
        $this->assertNull($sin['fuero_nacimiento']); $this->assertNull($sin['fuero_dominga']); $this->assertNull($sin['fecha_termino_lactancia']);
        $con = $service->calcular($datos, ['nacimiento', 'lactancia', 'ley_dominga']);
        $this->assertSame('2024-03-31', $con['fecha_termino_fuero']);
        $this->assertSame('ley_dominga', $con['base_fuero']); $this->assertSame('2026-02-28', $con['fecha_termino_lactancia']);
        $this->assertSame('2025-05-24', $service->calcular($datos + ['regla_fuero_dominga' => 'anio_84_dias'], ['ley_dominga'])['fecha_termino_fuero']);
    }

    public function test_roles_autorizados_y_contexto_activo_en_todas_las_rutas(): void
    {
        foreach ([3,10,11,12,13] as $i => $role) {
            $user = $i === 0 ? $this->actor : $this->testUser(10 + $i, $role);
            $name = DB::table('roles')->where('id', $role)->value('name');
            $this->actingAs($user)->withSession(['active_role' => $name])->get(route('tramites.maternidad.buscar', ['rut' => self::RUT]))->assertOk();
        }
        $forbidden = $this->testUser(30, 1);
        $this->actingAs($forbidden)->withSession(['active_role' => 'postulante']);
        foreach (['index','create','importar','plantilla','buscar'] as $name) $this->getJson(route('tramites.maternidad.'.$name))->assertForbidden();
        $this->postJson(route('tramites.maternidad.store'), $this->entrada())->assertForbidden();
        DB::table('model_has_roles')->insert(['role_id' => 1, 'model_type' => \App\Models\User::class, 'model_id' => $this->actor->id]);
        $this->actor->unsetRelation('roles');
        $this->actingAs($this->actor)->withSession(['active_role' => 'postulante'])->getJson(route('tramites.maternidad.index'))->assertForbidden();
    }

    public function test_lookup_separa_nombres_sin_modificar_padron_y_prefiere_campos_separados(): void
    {
        $resolver = app(MaternidadPersonalResolver::class);
        $candidate = $resolver->buscar('12.345.678-5')[0];
        $this->assertSame('NOMBRES PRUEBA', $candidate['nombres']); $this->assertSame('APELLIDOAPELLIDO', $candidate['apellido_paterno']);
        $this->assertTrue($candidate['nombre_requiere_revision']);
        DB::table('reemplazos_personal')->where('id', 1)->update(['nombres' => 'Nombres separados', 'apellido_paterno' => 'Apellido compuesto', 'apellido_materno' => 'Materno']);
        $this->assertSame('Nombres separados', $resolver->buscar(self::RUT)[0]['nombres']);
        $this->assertSame('APELLIDOAPELLIDO MATERNO NOMBRES PRUEBA', DB::table('reemplazos_personal')->value('nombre'));
    }

    public function test_lookup_toma_reemplazante_contratada_y_fecha_sin_cast(): void
    {
        $propuesta = $this->testUser(60, 1); $actual = $this->testUser(61, 1);
        DB::table('users')->where('id', $propuesta->id)->update(['rut' => '11111111-1']);
        DB::table('users')->where('id', $actual->id)->update(['rut' => self::RUT]);
        $actual->update(['nombres' => 'Nombre de reemplazo', 'apellido_paterno' => 'Apellido']);
        DB::table('postulant_profiles')->insert([['id' => 1, 'user_id' => $propuesta->id, 'estamento' => 'docente'], ['id' => 2, 'user_id' => $actual->id, 'estamento' => 'asistente']]);
        DB::table('solicitudes_reemplazo')->insert(['id' => 1, 'establecimiento_id' => 1, 'rut_reemplazo_normalizado' => '111111111', 'postulant_profile_id' => 1,
            'contrato_trabajo_postulant_profile_id' => 2, 'estado' => 'aceptada', 'numero_solicitud' => 'PRUEBA', 'fecha_inicio' => '2026-01-01', 'contrato_trabajo_fecha_inicio_trabajo' => '2026-02-01']);
        $resolver = app(MaternidadPersonalResolver::class);
        $this->assertCount(0, $resolver->buscar('11111111-1'));
        $candidate = collect($resolver->buscar(self::RUT))->firstWhere('fuente', 'reemplazo:1');
        $this->assertSame('Nombre de reemplazo', $candidate['nombres']); $this->assertSame('2026-02-01', $candidate['fecha_ingreso']); $this->assertSame('asistente', $candidate['estamento']);
    }

    public function test_varios_establecimientos_requieren_seleccion_y_no_se_acepta_rut_invalido(): void
    {
        DB::table('establecimientos')->insert(['id' => 2, 'rbd' => '99002', 'nombre_establecimiento' => 'Otro establecimiento de prueba', 'comuna' => 'Prueba']);
        $this->padron(['establecimiento_id' => 2]);
        $this->assertSame(2, app(MaternidadPersonalResolver::class)->resolver(self::RUT, null, '99002')['establecimiento_id']);
        $this->getJson(route('tramites.maternidad.buscar', ['rut' => '12345678-0']))->assertUnprocessable();
        $this->expectException(ValidationException::class); app(MaternidadPersonalResolver::class)->resolver(self::RUT, null);
    }

    public function test_crea_varios_casos_por_rut_y_conserva_copia_laboral(): void
    {
        $one = $this->caso(); $two = $this->caso(['referencia_caso' => 'OTRO-CASO']);
        $this->assertNotSame($one->uuid, $two->uuid); $this->assertFalse($one->ley_dominga); $this->assertFalse($one->postnatal_parental);
        $this->assertSame([], $one->documentos_pendientes);
        DB::table('establecimientos')->where('id', 1)->update(['nombre_establecimiento' => 'Nombre cambiado']);
        $this->assertSame('Establecimiento de prueba', $one->fresh()->establecimiento_nombre);
        $this->expectException(ValidationException::class); $this->caso();
    }

    public function test_beneficios_individuales_exigen_documentos_y_fechas_validas(): void
    {
        $this->post(route('tramites.maternidad.store'), $this->entrada())->assertSessionHasErrors('documentos.embarazo');
        $this->post(route('tramites.maternidad.store'), $this->entrada(['ley_dominga' => true, 'fecha_ley_dominga' => '2026-10-01']))->assertSessionHasErrors('documentos.ley_dominga');
        $this->post(route('tramites.maternidad.store'), $this->entrada(['fnh' => '2026-09-01']))->assertSessionHasErrors('documentos.nacimiento');
        $this->post(route('tramites.maternidad.store'), $this->entrada(['fecha_termino' => '2024-01-01']))->assertSessionHasErrors('fecha_termino');
        $this->post(route('tramites.maternidad.store'), $this->entrada(['postnatal_parental' => true]))->assertSessionHasErrors(['fecha_inicio_postnatal', 'modalidad_postnatal']);
        $this->assertDatabaseCount('maternidad_casos', 0);
    }

    public function test_dominga_30_dias_y_ampliacion_gdp_con_causal_fundamento_y_respaldo(): void
    {
        $caso = $this->caso(['ley_dominga' => true, 'fecha_ley_dominga' => '2026-10-01'], ['ley_dominga' => $this->pdf('dominga.pdf')]);
        $this->assertSame('2026-10-31', $caso->fecha_termino_fuero->toDateString());
        $gdp = $this->testUser(80, 11); $this->actingAs($gdp)->withSession(['active_role' => 'coordinador_gdp']);
        $data = $caso->toArray() + ['hijos' => []]; $data['regla_fuero_dominga'] = 'anio_84_dias';
        $service = app(MaternidadRegistroService::class);
        try { $service->preparar($data, [], $caso); $this->fail('Debe exigir causal y fundamento'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('causal_ampliacion_dominga', $e->errors()); $this->assertArrayHasKey('motivo_ampliacion_dominga', $e->errors()); }
        $data += ['causal_ampliacion_dominga' => 'parto_prematuro', 'motivo_ampliacion_dominga' => 'Fundamento de prueba GDP'];
        // toArray contiene claves nulas: se reemplazan para informar la decisión.
        $data['causal_ampliacion_dominga'] = 'parto_prematuro'; $data['motivo_ampliacion_dominga'] = 'Fundamento de prueba GDP';
        $actual = $service->guardar($service->preparar($data, [], $caso), [], $gdp->id, $caso, 1);
        $this->assertSame('2027-12-24', $actual->fecha_termino_fuero->toDateString());
        $audit = $actual->historial()->first(); $this->assertSame($gdp->id, $audit->created_by);
        $this->assertSame('anio_84_dias', $audit->cambios['regla_fuero_dominga']['despues']);
        $slep = $this->testUser(81, 10); $this->actingAs($slep)->withSession(['active_role' => 'funcionario_slep']);
        $this->get(route('tramites.maternidad.edit', $actual))->assertOk()->assertDontSee('id="mat-regla-dominga"', false);
        $data['regla_fuero_dominga'] = '30_dias';
        $this->expectException(ValidationException::class); $service->preparar($data, [], $actual);
    }

    public function test_rechaza_documentos_de_tipo_tamano_o_estructura_invalidos(): void
    {
        $this->post(route('tramites.maternidad.store'), $this->entrada(['documentos' => ['embarazo' => UploadedFile::fake()->create('ejecutable.exe', 1, 'application/octet-stream')]]))->assertSessionHasErrors('documentos.embarazo');
        $this->post(route('tramites.maternidad.store'), $this->entrada(['documentos' => ['orden_trabajo' => UploadedFile::fake()->image('orden.png')]]))->assertSessionHasErrors('documentos.orden_trabajo');
        $this->post(route('tramites.maternidad.store'), $this->entrada(['documentos' => ['embarazo' => UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf')]]))->assertSessionHasErrors('documentos.embarazo');
        $this->postJson(route('tramites.maternidad.store'), $this->entrada(['rut' => ['arreglo']]))->assertUnprocessable();
        $this->assertDatabaseCount('maternidad_documentos', 0);
    }

    public function test_documentos_privados_con_versiones_y_actualizacion_optimista(): void
    {
        $caso = $this->caso(['fnh' => '2026-09-01'], ['nacimiento' => $this->pdf()]);
        $doc = $caso->documentos()->where('tipo', 'nacimiento')->first(); Storage::disk('local')->assertExists($doc->path); Storage::disk('public')->assertMissing($doc->path);
        $this->get(route('tramites.maternidad.documento', [$caso, $doc]))->assertOk()->assertDownload('certificado.pdf')->assertHeader('X-Content-Type-Options', 'nosniff');
        $other = $this->caso(['referencia_caso' => 'OTRO']);
        $this->get(route('tramites.maternidad.documento', [$other, $doc]))->assertNotFound();
        $s = app(MaternidadRegistroService::class); $files = ['nacimiento' => $this->pdf('nuevo.pdf')];
        $p = $s->preparar($this->entrada(['fnh' => '2026-09-01', 'nombres' => 'Nombres corregidos', 'apellido_paterno' => 'Apellido', 'estamento' => 'docente', 'calidad_juridica' => 'TITULAR']), $files, $caso);
        $s->guardar($p, $files, $this->actor->id, $caso, 1);
        $this->assertSame(2, $caso->documentos()->where('tipo', 'nacimiento')->count()); Storage::disk('local')->assertExists($doc->path);
        $this->expectException(ValidationException::class); $s->guardar($p, [], $this->actor->id, $caso, 1);
    }

    public function test_formulario_guarda_actualiza_y_conserva_errores_y_vinculo(): void
    {
        $this->post(route('tramites.maternidad.store'), $this->entrada(['fecha_certificado_embarazo' => '2026-10-01', 'documentos' => ['embarazo' => $this->pdf()]]))->assertSessionHasNoErrors()->assertRedirect();
        $caso = MaternidadCaso::firstOrFail();
        $this->assertSame('docente', $caso->estamento);
        $data = $caso->toArray(); $data['version'] = 1; $data['nombres'] = 'Nombre corregido en formulario';
        $this->put(route('tramites.maternidad.update', $caso), $data)->assertSessionHasNoErrors()->assertRedirect(route('tramites.maternidad.show', $caso));
        $this->assertSame('Nombre corregido en formulario', $caso->fresh()->nombres);
        $this->withSession(['_old_input' => ['rut' => self::RUT, 'fuente' => 'padron:1', 'nombres' => 'Nombre conservado']])->get(route('tramites.maternidad.create'))->assertOk()->assertSee('Nombre conservado')->assertSee('Establecimiento de prueba');
    }

    public function test_observaciones_son_append_only_y_hijos_quedan_en_historial(): void
    {
        $caso = $this->caso(['observacion' => '<script>PRUEBA</script>', 'hijos' => [['nombre_completo' => 'Hijo de prueba', 'rut' => '11111111-1']]]);
        $this->post(route('tramites.maternidad.observar', $caso), ['observacion' => 'Segunda observación'])->assertRedirect();
        $this->assertSame(2, $caso->historial()->count()); $this->assertSame(2, $caso->fresh()->version);
        $response = $this->get(route('tramites.maternidad.show', $caso))->assertOk()->assertSee('&lt;script&gt;PRUEBA&lt;/script&gt;', false);
        $this->assertSame('Hijo de prueba', $caso->historial()->reorder('id')->first()->cambios['hijos']['despues'][0]['nombre_completo']);
    }

    public function test_rollback_elimina_solo_archivos_nuevos_si_falla_auditoria(): void
    {
        \App\Models\MaternidadHistorial::creating(fn () => throw new \RuntimeException('Fallo simulado'));
        try { $this->caso(['fecha_certificado_embarazo' => '2026-10-01'], ['embarazo' => $this->pdf()]); $this->fail('Debía fallar'); }
        catch (\RuntimeException $e) { $this->assertSame('Fallo simulado', $e->getMessage()); }
        finally { \App\Models\MaternidadHistorial::flushEventListeners(); }
        $this->assertDatabaseCount('maternidad_casos', 0); $this->assertSame([], Storage::disk('local')->allFiles('maternidad'));
    }

    public function test_excel_previsualiza_sin_crear_confirma_una_vez_y_documentos_se_completan_despues(): void
    {
        $rows = [$this->entrada(['referencia_caso' => 'EXCEL-1', 'fnh' => '2026-09-01', 'lactancia' => 'SI', 'fecha_inicio_lactancia' => '2026-10-01']), $this->entrada(['referencia_caso' => 'EXCEL-2'])];
        $file = $this->excel($rows, [['rut_funcionaria' => self::RUT, 'referencia_caso' => 'EXCEL-1', 'nombre_completo' => 'Hijo de prueba', 'fecha_nacimiento' => '2026-09-01']]);
        $s = app(MaternidadImportador::class); $batch = $s->previsualizar($file, $this->actor->id);
        $this->assertDatabaseCount('maternidad_casos', 0); $this->assertStringNotContainsString(self::RUT, DB::table('maternidad_importaciones')->value('payload'));
        $this->get(route('tramites.maternidad.importacion', $batch))->assertOk()->assertSee('EXCEL-1');
        $this->assertSame(2, $s->confirmar($batch, $this->actor->id)); $this->assertSame(2, $s->confirmar($batch, $this->actor->id));
        $this->assertDatabaseCount('maternidad_casos', 2); $this->assertNull($batch->fresh()->payload);
        $caso = MaternidadCaso::where('referencia_caso', 'EXCEL-1')->firstOrFail();
        $this->assertSame(['embarazo','nacimiento','lactancia'], $caso->documentos_pendientes); $this->assertNull($caso->fuero_nacimiento); $this->assertNull($caso->fecha_termino_lactancia);
        $reg = app(MaternidadRegistroService::class); $files = ['nacimiento' => $this->pdf(), 'lactancia' => $this->pdf('solicitud.pdf')];
        $data = $caso->toArray(); $data['hijos'] = $caso->hijos->toArray();
        $updated = $reg->guardar($reg->preparar($data, $files, $caso), $files, $this->actor->id, $caso, 1);
        $this->assertSame('2028-09-01', $updated->fecha_termino_lactancia->toDateString()); $this->assertSame('nacimiento_certificado', $updated->base_fuero);
    }

    public function test_excel_error_en_cualquier_fila_impide_crear_y_detecta_hijos_huerfanos(): void
    {
        $file = $this->excel([$this->entrada(), $this->entrada(['referencia_caso' => 'INVALIDO', 'rut' => '12345678-0'])]);
        try { app(MaternidadImportador::class)->previsualizar($file, $this->actor->id); $this->fail('Debía rechazar'); }
        catch (ValidationException $e) { $this->assertStringContainsString('fila 3', implode(' ', $e->errors()['archivo'])); }
        $this->assertDatabaseCount('maternidad_importaciones', 0); $this->assertDatabaseCount('maternidad_casos', 0);
        $this->expectException(ValidationException::class);
        app(MaternidadImportador::class)->previsualizar($this->excel([$this->entrada()], [['rut_funcionaria' => self::RUT, 'referencia_caso' => 'SIN-CASO', 'nombre_completo' => 'Prueba']]), $this->actor->id);
    }

    public function test_excel_rechaza_formulas_y_respeta_expiracion_y_propietario(): void
    {
        $s = app(MaternidadImportador::class); $batch = $s->previsualizar($this->excel([$this->entrada()]), $this->actor->id);
        $other = $this->testUser(70, 3); $this->actingAs($other)->withSession(['active_role' => 'admin']);
        $this->get(route('tramites.maternidad.importacion', $batch))->assertForbidden(); $this->post(route('tramites.maternidad.confirmar', $batch))->assertForbidden();
        $batch->update(['expira_at' => now()->subMinute()]);
        try { $s->confirmar($batch, $this->actor->id); $this->fail('Debía expirar'); } catch (ValidationException) { $this->assertDatabaseCount('maternidad_casos', 0); }
        $book = $s->plantilla(); $book->getSheet(0)->setCellValue('A2', '=1+1');
        $path = Storage::disk('local')->path('formula.xlsx'); (new Xlsx($book))->save($path); $book->disconnectWorksheets();
        $this->expectException(ValidationException::class); $s->previsualizar(new UploadedFile($path, 'formula.xlsx', null, null, true), $this->actor->id);
    }

    public function test_conflicto_al_confirmar_revierte_lote_completo(): void
    {
        $s = app(MaternidadImportador::class); $batch = $s->previsualizar($this->excel([$this->entrada(['referencia_caso' => 'PRIMERO']), $this->entrada()]), $this->actor->id);
        $this->caso();
        try { $s->confirmar($batch, $this->actor->id); $this->fail('Debía rechazar duplicado'); } catch (ValidationException) { }
        $this->assertDatabaseCount('maternidad_casos', 1); $this->assertNull($batch->fresh()->procesada_at);
    }

    public function test_vistas_y_plantilla_usan_el_flujo_y_dos_estamentos(): void
    {
        $this->withoutExceptionHandling();
        $caso = $this->caso([], [], true);
        foreach (['index','create','importar'] as $name) $this->get(route('tramites.maternidad.'.$name))->assertOk();
        $this->get(route('tramites.maternidad.create', ['rut' => self::RUT]))->assertOk()->assertSee('NOMBRES PRUEBA');
        $this->get(route('tramites.maternidad.edit', $caso))->assertOk();
        $this->get(route('tramites.maternidad.index', ['estamento' => 'docente', 'estado' => 'pendiente']))->assertOk()->assertSee($caso->codigo)->assertSee('Asistentes de la educación')->assertSee('Docentes');
        $this->get(route('tramites.maternidad.plantilla'))->assertOk()->assertDownload('plantilla_maternidad.xlsx');
        if (getenv('MATERNIDAD_UI_PREVIEW')) {
            $dir = base_path('.codex_work/maternidad'); if (!is_dir($dir)) mkdir($dir, 0777, true);
            foreach (['index' => route('tramites.maternidad.index', ['estamento' => 'docente']), 'form' => route('tramites.maternidad.create', ['rut' => self::RUT]), 'show' => route('tramites.maternidad.show', $caso), 'importar' => route('tramites.maternidad.importar')] as $file => $url) file_put_contents($dir.'/'.$file.'.html', $this->get($url)->getContent());
        }
    }
}
