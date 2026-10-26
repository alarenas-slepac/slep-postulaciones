<?php

namespace Tests\Feature;

use App\Exports\PadronVotacionExcel;
use App\Models\JornadaVotacion;
use App\Models\VotanteConsulta;
use App\Services\Votaciones\ActualizarVotantesExcel;
use App\Services\Votaciones\ActualizarVotantesService;
use App\Services\Votaciones\BitacoraVotacionService;
use App\Services\Votaciones\PadronConsultaService;
use App\Support\RutChile;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\IsolatedSecurityTestCase;

class ActualizarVotantesTest extends IsolatedSecurityTestCase
{
    private JornadaVotacion $jornada;
    private VotanteConsulta $votante;

    protected function setUp(): void
    {
        parent::setUp(); // Aísla configuración antes del bootstrap, SQLite :memory: y discos simulados.
        $this->withoutVite();
        Schema::create('modules', function (Blueprint $t) { $t->id(); $t->string('key'); });
        foreach (['model_has_permissions', 'role_has_permissions'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->unsignedBigInteger('permission_id'); $t->unsignedBigInteger('role_id')->nullable(); $t->unsignedBigInteger('model_id')->nullable(); $t->string('model_type')->nullable(); });
        }
        foreach (['2026_08_27_210000_create_votaciones_module_tables.php', '2026_10_21_210000_create_consulta_local_votacion.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        $this->actingAs($this->testUser(1, 3));
        $this->jornada = JornadaVotacion::create(['nombre' => 'Jornada sintética', 'slug' => 'prueba', 'fecha' => '2026-10-28']);
        $this->jornada->procesos()->attach(DB::table('procesos_votacion')->pluck('id'));
        $this->cargar($this->jornada);
        $this->votante = $this->jornada->padronConsulta()->where('rut', $this->rut())->firstOrFail();
        $this->jornada->forceFill(['consulta_publica' => true])->save();
        Storage::disk('local')->put('actualizar-views/layouts/app.blade.php', '<!doctype html><html><head>@stack(\'styles\')</head><body>@yield(\'content\')@stack(\'scripts\')</body></html>');
        $this->app['view']->getFinder()->prependLocation(Storage::disk('local')->path('actualizar-views'));
    }

    private function rut(int $n = 99000001): string { return $n.RutChile::dv($n); }

    private function cargar(JornadaVotacion $jornada): void
    {
        $locales = [];
        foreach ([9001, 9002] as $rbd) {
            $locales[$rbd.'|9501'] = ['rbd_origen' => $rbd, 'rbd_local' => 9501, 'establecimiento' => 'Origen '.$rbd,
                'lugar_votacion' => 'Local sintético', 'dia' => '2026-10-28', 'hora_inicio' => '09:00:00', 'fila_excel' => 2];
        }
        $padron = [];
        foreach ([99000001, 99000002] as $n) {
            $row = ['rut' => $this->rut($n), 'nombre' => 'Persona de prueba '.$n, 'rbd' => 9001,
                'establecimiento' => 'Origen 9001', 'tipo_contrato' => 'Contrata', 'grupo' => '1', 'local_key' => '9001|9501'];
            $padron[] = $row + ['fila_hash' => hash('sha256', json_encode($row, JSON_THROW_ON_ERROR))];
        }
        app(PadronConsultaService::class)->importar($jornada, ['padron' => $padron, 'locales' => $locales, 'resumen' => ['errores' => 0]], auth()->user(), false);
    }

    private function archivo(array $filas, array $campos, ?array $headers = null): UploadedFile
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet()->setTitle('ACTUALIZAR PADRON');
        $headers ??= ActualizarVotantesExcel::columnas($campos);
        $sheet->fromArray([$headers]);
        foreach ($filas as $i => $fila) {
            foreach ($headers as $j => $header) { $sheet->setCellValue([$j + 1, $i + 2], $fila[$header] ?? null); }
        }
        $path = Storage::disk('local')->path('actualizacion-'.bin2hex(random_bytes(6)).'.xlsx');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();
        return new UploadedFile($path, 'actualizacion.xlsx', null, null, true);
    }

    private function fila(array $extra = []): array
    {
        return $extra + ['ID_REGISTRO' => $this->votante->id, 'RUT' => $this->rut(), 'NOMBRE' => 'Nombre corregido',
            'TIPO_DE_CONTRATO' => 'Titular', 'GRUPO' => '2', 'RBD' => 9002, 'RBD_LUGAR_DE_VOTACION' => 9501];
    }

    private function aplicar(array $filas, array $campos = ['nombre'], array $extra = [])
    {
        return $this->post(route('votaciones.admin.padrones.actualizar', $this->jornada), $extra + [
            'archivo_actualizacion' => $this->archivo($filas, $campos), 'campos' => $campos, 'confirmar_actualizacion' => 1, 'agregar' => 1]);
    }

    private function libroRespuesta($response): Spreadsheet
    {
        $path = Storage::disk('local')->path('respuesta-'.bin2hex(random_bytes(6)).'.xlsx');
        file_put_contents($path, $response->streamedContent());
        return IOFactory::load($path);
    }

    public function test_plantilla_completa_tiene_hojas_encabezados_y_locales_sin_votantes(): void
    {
        $response = $this->get(route('votaciones.admin.padrones.plantilla', [$this->jornada, 'modo' => 'completo']))
            ->assertOk()->assertDownload()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $book = $this->libroRespuesta($response);
        try {
            $this->assertSame(['PADRON VOTACIÓN', 'LOCALES VOTACION', 'INSTRUCCIONES'], $book->getSheetNames());
            $this->assertSame(1, $book->getSheet(0)->getHighestDataRow());
            $this->assertSame('RUT', $book->getSheet(0)->getCell('A1')->getValue());
            $this->assertSame('2026-10-28', $book->getSheet(1)->getCell('F2')->getValue());
        } finally { $book->disconnectWorksheets(); }
        $this->assertSame(2, $this->jornada->padronConsulta()->count());
        $this->assertTrue($this->jornada->refresh()->consulta_publica);
    }

    public function test_plantilla_dinamica_precarga_textos_como_texto_y_respeta_campos(): void
    {
        $this->votante->update(['nombre' => '=Nombre sintético']);
        $response = $this->post(route('votaciones.admin.padrones.plantilla', $this->jornada),
            ['modo' => 'actualizacion', 'campos' => ['nombre', 'grupo'], 'precargar' => 1])->assertOk()->assertDownload();
        $book = $this->libroRespuesta($response);
        try {
            $sheet = $book->getSheet(0);
            $this->assertSame('ACTUALIZAR PADRON', $sheet->getTitle());
            $this->assertSame(['ID_REGISTRO', 'RUT', 'NOMBRE', 'GRUPO'], $sheet->rangeToArray('A1:D1')[0]);
            $this->assertSame($this->rut(), $sheet->getCell('B2')->getValue());
            $this->assertSame('s', $sheet->getCell('C2')->getDataType());
            $this->assertSame('=Nombre sintético', $sheet->getCell('C2')->getValue());
        } finally { $book->disconnectWorksheets(); }
    }

    public function test_actualizacion_parcial_preserva_otros_campos_personas_locales_y_jornadas(): void
    {
        $otra = JornadaVotacion::create(['nombre' => 'Otra', 'slug' => 'otra', 'fecha' => '2026-10-29']);
        $this->cargar($otra);
        $otrosAntes = $otra->padronConsulta()->get()->toJson();
        $localesAntes = DB::table('votacion_locales_consulta')->get()->toJson();
        $sinTocar = $this->jornada->padronConsulta()->where('id', '!=', $this->votante->id)->first()->getAttributes();
        $response = $this->aplicar([$this->fila()])->assertOk()->assertDownload()->assertSessionHas('resumen_actualizacion_padron.actualizados', 1);
        $book = $this->libroRespuesta($response);
        try { $this->assertSame('Actualizado', $book->getSheet(0)->getCell('D2')->getValue()); }
        finally { $book->disconnectWorksheets(); }
        $this->assertSame('Nombre corregido', $this->votante->fresh()->nombre);
        $this->assertSame('Contrata', $this->votante->fresh()->tipo_contrato);
        $this->assertSame('1', $this->votante->fresh()->grupo);
        $this->assertSame($otrosAntes, $otra->padronConsulta()->get()->toJson());
        $this->assertSame($localesAntes, DB::table('votacion_locales_consulta')->get()->toJson());
        $this->assertSame($sinTocar, VotanteConsulta::find($sinTocar['id'])->getAttributes());
        $this->assertFalse($this->jornada->refresh()->consulta_publica);
        $meta = DB::table('bitacora_votacion')->where('evento', 'padron_votantes_actualizado')->value('metadata');
        $this->assertStringNotContainsString($this->rut(), $meta);
        $this->assertStringNotContainsString('Nombre corregido', $meta);
    }

    public function test_agrega_rut_nuevo_con_local_existente_y_conserva_anteriores(): void
    {
        $this->aplicar([$this->fila(['ID_REGISTRO' => '', 'RUT' => $this->rut(99000003)])], array_keys(ActualizarVotantesExcel::CAMPOS))
            ->assertOk()->assertSessionHas('resumen_actualizacion_padron.agregados', 1);
        $this->assertSame(3, $this->jornada->padronConsulta()->count());
        $nuevo = $this->jornada->padronConsulta()->where('rut', $this->rut(99000003))->sole();
        $this->assertSame(9002, (int) $nuevo->rbd);
        $this->assertSame('Origen 9002', $nuevo->establecimiento);
        $this->assertSame('09:00:00', $nuevo->local->hora_inicio);
        $this->assertSame('Persona de prueba 99000001', $this->votante->fresh()->nombre);
    }

    public function test_grupo_vacio_se_limpia_y_carga_identica_conserva_publicacion(): void
    {
        $this->aplicar([$this->fila(['GRUPO' => '1'])], ['grupo'])->assertSessionHas('resumen_actualizacion_padron.sin_cambios', 1);
        $this->assertTrue($this->jornada->refresh()->consulta_publica);
        $this->aplicar([$this->fila(['GRUPO' => ''])], ['grupo'])->assertSessionHas('resumen_actualizacion_padron.actualizados', 1);
        $this->assertNull($this->votante->fresh()->grupo);
        $this->assertFalse($this->jornada->refresh()->consulta_publica);
    }

    public function test_cambia_origen_local_del_votante_sin_modificar_horarios_compartidos(): void
    {
        $locales = DB::table('votacion_locales_consulta')->get()->toJson();
        $this->aplicar([$this->fila()], ['asignacion'])->assertSessionHas('resumen_actualizacion_padron.actualizados', 1);
        $actual = $this->votante->fresh();
        $this->assertSame(9002, (int) $actual->rbd);
        $this->assertSame('Origen 9002', $actual->establecimiento);
        $this->assertSame('Persona de prueba 99000001', $actual->nombre);
        $this->assertSame($locales, DB::table('votacion_locales_consulta')->get()->toJson());
    }

    public function test_sin_id_normaliza_rut_y_no_elige_entre_asignaciones_multiples(): void
    {
        $fila = $this->fila(['ID_REGISTRO' => '', 'RUT' => RutChile::format($this->rut())]);
        $this->aplicar([$fila])->assertSessionHas('resumen_actualizacion_padron.actualizados', 1);
        $copy = $this->votante->fresh()->replicate();
        $copy->fill(['grupo' => 'Otro', 'fila_hash' => hash('sha256', 'otra-asignacion')])->save();
        $this->aplicar([$fila])->assertSessionHas('resumen_actualizacion_padron.omitidos', 1);
        $this->aplicar([$this->fila(['ID_REGISTRO' => $copy->id, 'GRUPO' => 'Elegido'])], ['grupo'])->assertSessionHas('resumen_actualizacion_padron.actualizados', 1);
        $this->assertSame('Elegido', $copy->fresh()->grupo);
        $this->assertSame('1', $this->votante->fresh()->grupo);
    }

    public static function omitidas(): array
    {
        return [[['ID_REGISTRO' => 99999]], [['RUT' => 'invalido']], [['NOMBRE' => '']],
            [['RBD' => 12345]], [['RBD_LUGAR_DE_VOTACION' => 99999]], [['GRUPO' => 'grupo'.str_repeat('x', 100)]]];
    }

    #[DataProvider('omitidas')]
    public function test_filas_invalidas_se_informan_sin_alterar_el_padron(array $overrides): void
    {
        $antes = DB::table('votacion_padron_consulta')->get()->toJson();
        $this->aplicar([$this->fila($overrides)], array_keys(ActualizarVotantesExcel::CAMPOS))->assertOk()->assertSessionHas('resumen_actualizacion_padron.omitidos', 1);
        $this->assertSame($antes, DB::table('votacion_padron_consulta')->get()->toJson());
        $this->assertTrue($this->jornada->refresh()->consulta_publica);
    }

    public function test_id_de_otra_jornada_o_rut_distinto_no_actualiza_ni_inserta(): void
    {
        $otra = JornadaVotacion::create(['nombre' => 'Otra', 'slug' => 'otra', 'fecha' => '2026-10-29']);
        $this->cargar($otra);
        $this->aplicar([$this->fila(['ID_REGISTRO' => $otra->padronConsulta()->first()->id])])->assertSessionHas('resumen_actualizacion_padron.omitidos', 1);
        $this->aplicar([$this->fila(['RUT' => $this->rut(99000003)])])->assertSessionHas('resumen_actualizacion_padron.omitidos', 1);
        $this->assertSame(4, VotanteConsulta::count());
    }

    public function test_nuevos_exigen_campos_completos_y_pueden_desactivarse(): void
    {
        $nueva = $this->fila(['ID_REGISTRO' => '', 'RUT' => $this->rut(99000003)]);
        $this->aplicar([$nueva], ['grupo'])->assertSessionHas('resumen_actualizacion_padron.omitidos', 1);
        $this->aplicar([$nueva], array_keys(ActualizarVotantesExcel::CAMPOS), ['agregar' => 0])->assertSessionHas('resumen_actualizacion_padron.omitidos', 1);
        $this->assertSame(2, VotanteConsulta::count());
    }

    public function test_identificacion_repetida_omite_ambas_filas_y_no_duplica_personas(): void
    {
        $this->aplicar([$this->fila(), $this->fila(['NOMBRE' => 'Otro valor'])])->assertSessionHas('resumen_actualizacion_padron.omitidos', 2);
        $this->assertSame('Persona de prueba 99000001', $this->votante->fresh()->nombre);
    }

    public function test_duplicar_otra_asignacion_se_omite_y_el_hash_es_compatible_con_importacion(): void
    {
        $copy = $this->votante->replicate()->fill(['grupo' => '2']);
        $copy->fila_hash = hash('sha256', json_encode(['rut' => $copy->rut, 'nombre' => $copy->nombre, 'rbd' => 9001,
            'establecimiento' => $copy->establecimiento, 'tipo_contrato' => $copy->tipo_contrato, 'grupo' => '2', 'local_key' => '9001|9501'], JSON_THROW_ON_ERROR));
        $copy->save();
        $this->aplicar([$this->fila()], ['grupo'])->assertSessionHas('resumen_actualizacion_padron.omitidos', 1);
        $this->assertSame('1', $this->votante->fresh()->grupo);
    }

    public function test_error_de_bitacora_revierte_actualizados_nuevos_y_publicacion(): void
    {
        $antes = DB::table('votacion_padron_consulta')->get()->toJson();
        $this->mock(BitacoraVotacionService::class)->shouldReceive('registrar')->once()->andThrow(new \RuntimeException('Fallo simulado'));
        $this->aplicar([$this->fila(), $this->fila(['ID_REGISTRO' => '', 'RUT' => $this->rut(99000003)])], array_keys(ActualizarVotantesExcel::CAMPOS))
            ->assertSessionHasErrors('archivo_actualizacion');
        $this->assertSame($antes, DB::table('votacion_padron_consulta')->get()->toJson());
        $this->assertTrue($this->jornada->refresh()->consulta_publica);
        Log::shouldHaveReceived('error')->once()->with('Falló la actualización masiva de votantes.', ['jornada_id' => $this->jornada->id, 'tipo' => \RuntimeException::class]);
    }

    public function test_no_admite_formulas_estructura_invalida_o_exceso_de_filas(): void
    {
        foreach ([$this->archivo([$this->fila(['NOMBRE' => '=1+1'])], ['nombre']), $this->archivo([$this->fila()], ['nombre'], ['RUT'])] as $archivo) {
            $this->post(route('votaciones.admin.padrones.actualizar', $this->jornada), ['campos' => ['nombre'], 'confirmar_actualizacion' => 1, 'archivo_actualizacion' => $archivo])->assertSessionHasErrors('archivo_actualizacion');
        }
        $file = $this->archivo([$this->fila()], ['nombre']);
        $book = IOFactory::load($file->getRealPath());
        $book->getSheet(0)->setCellValue('A20002', '1');
        (new Xlsx($book))->save($file->getRealPath());
        $book->disconnectWorksheets();
        $this->post(route('votaciones.admin.padrones.actualizar', $this->jornada), ['campos' => ['nombre'], 'confirmar_actualizacion' => 1, 'archivo_actualizacion' => $file])->assertSessionHasErrors('archivo_actualizacion');
        $this->assertTrue($this->jornada->refresh()->consulta_publica);
    }

    public function test_actualizaciones_se_escriben_en_lotes_y_la_repeticion_es_idempotente(): void
    {
        $filas = [];
        for ($i = 0; $i < 250; $i++) {
            $filas[] = $this->fila(['ID_REGISTRO' => '', 'RUT' => $this->rut(99000100 + $i)]);
        }
        $campos = array_keys(ActualizarVotantesExcel::CAMPOS);
        $archivo = $this->archivo($filas, $campos);
        $datos = app(ActualizarVotantesExcel::class)->leerActualizacion($archivo->getRealPath(), $campos);
        DB::enableQueryLog();
        try {
            $resultado = app(ActualizarVotantesService::class)->aplicar($this->jornada, $datos, $campos, true, auth()->user());
            $inserts = collect(DB::getQueryLog())->filter(fn ($q) => str_starts_with(strtolower($q['query']), 'insert into "votacion_padron_consulta"'));
        } finally { DB::disableQueryLog(); DB::flushQueryLog(); }
        $this->assertSame(250, $resultado['resumen']['agregados']);
        $this->assertCount(3, $inserts);
        $this->assertSame(252, $this->jornada->padronConsulta()->count());
        $repetido = app(ActualizarVotantesService::class)->aplicar($this->jornada, $datos, $campos, true, auth()->user());
        $this->assertSame(250, $repetido['resumen']['sin_cambios']);
        $this->assertSame(252, $this->jornada->padronConsulta()->count());
    }

    public function test_login_administrador_csrf_y_seleccion_validan_antes_de_escribir(): void
    {
        auth()->logout();
        $url = route('votaciones.admin.padrones.plantilla', [$this->jornada, 'modo' => 'completo']);
        $this->get($url)->assertRedirect(route('login'));
        $admin = $this->testUser(2, 3);
        $this->actingAs($this->testUser(3, 2))->get($url)->assertForbidden();
        $this->aplicar([$this->fila()])->assertForbidden();
        $this->actingAs($admin);
        $this->post(route('votaciones.admin.padrones.actualizar', $this->jornada), ['campos' => ['rut']])->assertSessionHasErrors('campos.0');
        $this->app['env'] = 'local';
        $this->post(route('votaciones.admin.padrones.actualizar', $this->jornada))->assertStatus(419);
        $this->post($url)->assertStatus(419);
        $this->assertSame(2, VotanteConsulta::count());
    }

    public function test_vista_presenta_plantillas_y_actualizacion_sin_listar_personas(): void
    {
        $this->get(route('votaciones.admin.padrones.index', ['jornada' => $this->jornada->slug]))->assertOk()
            ->assertSee('Descargar plantilla de padrón y locales')->assertSee('Descargar plantilla de actualización')
            ->assertSee('Aplicar cambios y descargar reporte')->assertDontSee('Persona de prueba 99000001');
    }
}
