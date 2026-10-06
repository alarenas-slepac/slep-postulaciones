<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReemplazoDocumentosAcademicosExport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Support\IsolatedSecurityTestCase;
use ZipArchive;

class ReemplazoDocumentosAcademicosExportTest extends IsolatedSecurityTestCase
{
    protected function setUp(): void
    {
        parent::setUp(); // Antes del bootstrap: SQLite :memory:, sin cache real y discos fake.
        Schema::create('modules', function (Blueprint $table): void { $table->id(); $table->string('key'); });
        DB::table('modules')->insert(['key' => 'gestion.solicitudes-reemplazo']);
        Schema::create('establecimientos', function (Blueprint $table): void {
            $table->id(); $table->string('rbd'); $table->string('nombre_establecimiento');
        });
        Schema::create('areas_desempeno', function (Blueprint $table): void { $table->id(); $table->string('nombre'); });
        Schema::create('postulant_profiles', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('area_desempeno_id')->nullable();
            $table->string('estamento')->nullable(); $table->string('nivel_estudios')->nullable();
            $table->date('fecha_titulacion')->nullable(); $table->integer('semestres')->nullable(); $table->integer('horas_totales')->nullable();
        });
        Schema::create('solicitudes_reemplazo', function (Blueprint $table): void {
            $table->id(); $table->string('numero_solicitud')->nullable(); $table->integer('anio')->nullable(); $table->string('estado');
            foreach (['postulant_profile_id', 'contrato_trabajo_postulant_profile_id', 'establecimiento_id', 'area_desempeno_id'] as $field) {
                $table->unsignedBigInteger($field)->nullable();
            }
        });
        Schema::create('document_types', function (Blueprint $table): void { $table->id(); $table->string('slug'); });
        Schema::create('user_documents', function (Blueprint $table): void {
            $table->id(); $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('document_type_id');
            $table->string('path')->nullable(); $table->string('status'); $table->timestamps();
        });
        DB::table('establecimientos')->insert([
            ['id' => 1, 'rbd' => '99001', 'nombre_establecimiento' => 'Escuela de prueba Alfa'],
            ['id' => 2, 'rbd' => '99002', 'nombre_establecimiento' => 'Escuela de prueba Beta'],
        ]);
        DB::table('areas_desempeno')->insert([['id' => 1, 'nombre' => 'Área de prueba Alfa'], ['id' => 2, 'nombre' => 'Área de prueba Beta']]);
        foreach ([...array_keys(ReemplazoDocumentosAcademicosExport::DOCUMENTS), 'cedula'] as $slug) {
            DB::table('document_types')->insert(['slug' => $slug]);
        }
    }

    private function profile(int $id = 2, array $values = []): User
    {
        $user = $this->fixtureUser($id);
        $user->update(['nombres' => 'Reemplazante sintético', 'apellido_paterno' => 'Prueba', 'apellido_materno' => 'Ejemplo']);
        DB::table('postulant_profiles')->insert(array_merge([
            'id' => $id, 'user_id' => $id, 'area_desempeno_id' => 1, 'estamento' => 'docente',
            'nivel_estudios' => 'Profesional', 'fecha_titulacion' => '2020-03-15', 'semestres' => 10, 'horas_totales' => 2400,
        ], $values));

        return $user;
    }

    private function requestRow(int $id, ?int $profile = 2, string $state = 'aceptada', array $values = []): void
    {
        DB::table('solicitudes_reemplazo')->insert(array_merge([
            'id' => $id, 'numero_solicitud' => 'PRUEBA-'.$id, 'anio' => 2026, 'estado' => $state,
            'postulant_profile_id' => $profile, 'establecimiento_id' => 1, 'area_desempeno_id' => 1,
        ], $values));
    }

    private function document(int $user, string $slug, string $path, string $status = 'pending', bool $exists = true): void
    {
        DB::table('user_documents')->insert([
            'user_id' => $user, 'document_type_id' => DB::table('document_types')->where('slug', $slug)->value('id'),
            'path' => $path, 'status' => $status, 'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($exists) { Storage::disk('public')->put($path, '%PDF-1.4 Documento sintético '.$slug); }
    }

    private function admin(): void
    {
        $this->actingAs($this->fixtureUser(1, 3))->withSession(['active_role' => 'admin']);
    }

    private function fixtureUser(int $id, int $role = 1): User
    {
        $user = User::forceCreate([
            'id' => $id, 'rut' => '9900000'.$id.'K', 'email' => 'cuenta'.$id.'@example.test',
            'nombres' => 'Cuenta de prueba', 'password' => 'clave-de-prueba', 'email_verified_at' => now(),
        ]);
        DB::table('model_has_roles')->insert(['role_id' => $role, 'model_type' => User::class, 'model_id' => $id]);

        return $user;
    }

    /** @return array{0:array,1:array} */
    private function package(?string $path = null): array
    {
        $path ??= app(ReemplazoDocumentosAcademicosExport::class)->generate();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path));
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) { $entries[$zip->getNameIndex($i)] = $zip->getFromIndex($i); }
        $zip->close();
        $xlsx = Storage::disk('local')->path('nomina-lectura-prueba.xlsx');
        file_put_contents($xlsx, $entries['nomina_reemplazos.xlsx']);
        try {
            $book = IOFactory::load($xlsx);
            $sheets = [];
            foreach ($book->getAllSheets() as $sheet) { $sheets[$sheet->getTitle()] = $sheet->toArray(null, false, false, false); }
            $book->disconnectWorksheets();
        } finally {
            unlink($xlsx); unlink($path);
        }

        return [$entries, $sheets];
    }

    public function test_zip_contains_only_available_academic_documents_and_one_person_for_all_accepted_closed_requests(): void
    {
        $this->profile();
        $this->profile(3);
        $this->requestRow(1);
        $this->requestRow(2, 2, 'cerrado', ['establecimiento_id' => 2, 'area_desempeno_id' => 2, 'anio' => 2025]);
        $this->requestRow(3, 2, 'cerrada');
        foreach (['pendiente_gdp', 'derivada_slep', 'rechazada', 'anulada'] as $i => $state) { $this->requestRow(10 + $i, 3, $state); }
        foreach (array_keys(ReemplazoDocumentosAcademicosExport::DOCUMENTS) as $slug) { $this->document(2, $slug, 'documents/2/'.$slug.'.pdf'); }
        $this->document(2, 'cedula', 'documents/2/cedula.pdf');
        $this->document(3, 'titulo', 'documents/3/titulo.pdf');
        [$entries, $sheets] = $this->package();
        $this->assertCount(5, $entries);
        $this->assertCount(2, $sheets['Nómina']);
        $this->assertCount(4, $sheets['Solicitudes']);
        $this->assertCount(5, $sheets['Documentos']);
        $row = array_combine($sheets['Nómina'][0], $sheets['Nómina'][1]);
        $this->assertSame('Reemplazante sintético Prueba Ejemplo', $row['Nombre completo']);
        $this->assertSame('99000002-K', $row['RUT']);
        $this->assertSame('PRUEBA-1 | PRUEBA-2 | PRUEBA-3', $row['Solicitudes asociadas']);
        $this->assertSame('99001 | 99002', $row['RBD']);
        $this->assertSame('Área de prueba Alfa | Área de prueba Beta', $row['Área de desempeño']);
        $this->assertSame('Docente', $row['Estamento']);
        $this->assertSame('Profesional', $row['Nivel de estudios']);
        $this->assertSame('15-03-2020', $row['Fecha de titulación']);
        $this->assertEquals(10, $row['Semestres']); $this->assertEquals(2400, $row['Horas totales']);
        $this->assertSame('99002', $sheets['Solicitudes'][2][6]);
        $this->assertSame('Escuela de prueba Beta', $sheets['Solicitudes'][2][7]);
        $this->assertSame('Área de prueba Beta', $sheets['Solicitudes'][2][8]);
        foreach (array_slice($sheets['Documentos'], 1) as $doc) { $this->assertSame('Incluido', $doc[3]); $this->assertArrayHasKey($doc[5], $entries); }
        Storage::disk('public')->assertExists('documents/2/titulo.pdf');
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_missing_optional_fields_and_missing_files_are_reported_without_omitting_the_person(): void
    {
        $this->profile(2, ['fecha_titulacion' => null, 'semestres' => null, 'horas_totales' => 0, 'estamento' => 'asistente']);
        $this->requestRow(1);
        $this->document(2, 'titulo', 'documents/2/faltante.pdf', 'approved', false);
        $this->document(2, 'licencia_media', 'documents/2/historico.pdf', 'rejected');
        $this->requestRow(2, null, 'cerrado');
        [$entries, $sheets] = $this->package();
        $this->assertCount(2, $entries);
        $this->assertNull($sheets['Nómina'][1][8]); $this->assertNull($sheets['Nómina'][1][9]);
        $this->assertEquals(0, $sheets['Nómina'][1][10]);
        $this->assertSame('Asistente de la Educación', $sheets['Nómina'][1][6]);
        $this->assertSame('Archivo no disponible', $sheets['Documentos'][1][3]);
        $this->assertSame('No cargado', $sheets['Documentos'][2][3]);
        $this->assertSame('Incluido', $sheets['Documentos'][4][3]);
        $this->assertCount(2, $sheets['Sin perfil disponible']);
    }

    public function test_historical_contract_profile_fallback_includes_soft_deleted_users_but_not_the_old_proposal(): void
    {
        $user = $this->profile(); $this->profile(3);
        $this->requestRow(1, null, 'cerrado', ['contrato_trabajo_postulant_profile_id' => 2]);
        $this->requestRow(2, 999, 'cerrado', ['contrato_trabajo_postulant_profile_id' => 2]);
        $this->requestRow(3, 2, 'aceptada', ['contrato_trabajo_postulant_profile_id' => 3]);
        $this->document(2, 'titulo', 'documents/2/historico.pdf');
        $this->document(3, 'titulo', 'documents/3/propuesta-anterior.pdf');
        $user->delete();
        [$entries, $sheets] = $this->package();
        $this->assertCount(2, $entries); $this->assertCount(2, $sheets['Nómina']);
        $this->assertCount(4, $sheets['Solicitudes']);
        $this->assertStringNotContainsString('propuesta-anterior', implode('|', array_keys($entries)));
    }

    public function test_normalized_rut_deduplicates_historical_accounts_and_uses_latest_document_only(): void
    {
        $this->profile(); $this->profile(3);
        DB::table('users')->where('id', 3)->update(['rut' => ' 99.000.002-k ']);
        $this->requestRow(1); $this->requestRow(2, 3);
        $this->document(2, 'titulo', 'documents/2/anterior.pdf');
        $this->document(3, 'titulo', 'documents/3/actual.pdf');
        Storage::disk('public')->put('documents/2/anterior.pdf', '%PDF-1.4 Carga anterior sintética');
        Storage::disk('public')->put('documents/3/actual.pdf', '%PDF-1.4 Carga vigente sintética');
        [$entries, $sheets] = $this->package();
        $this->assertCount(2, $entries); $this->assertCount(2, $sheets['Nómina']);
        $this->assertCount(3, $sheets['Solicitudes']);
        $this->assertSame(1, app(ReemplazoDocumentosAcademicosExport::class)->peopleQuery()->get()->count());
        $this->assertSame('%PDF-1.4 Carga vigente sintética', $entries[$sheets['Documentos'][1][5]]);
        Storage::disk('public')->assertExists(['documents/2/anterior.pdf', 'documents/3/actual.pdf']);
    }

    public function test_distinct_people_without_rut_do_not_merge_and_formula_like_names_remain_text(): void
    {
        $this->profile(); $this->profile(3);
        DB::table('users')->whereIn('id', [2, 3])->update(['rut' => null, 'nombres' => '=SUM(1,2)']);
        $this->requestRow(1); $this->requestRow(2, 3);
        [$entries, $sheets] = $this->package();
        $this->assertCount(3, $sheets['Nómina']);
        $this->assertSame('=SUM(1,2) Prueba Ejemplo', $sheets['Nómina'][1][0]);
        $this->assertSame(2, app(ReemplazoDocumentosAcademicosExport::class)->peopleQuery()->get()->count());
    }

    public function test_documents_outside_public_disk_are_not_exported(): void
    {
        $this->profile(); $this->requestRow(1);
        Storage::disk('local')->put('privado-prueba.pdf', 'Documento ajeno sintético');
        $this->document(2, 'titulo', '../local/privado-prueba.pdf', 'pending', false);
        [$entries, $sheets] = $this->package();
        $this->assertCount(1, $entries);
        $this->assertSame('Archivo no disponible', $sheets['Documentos'][1][3]);
        Storage::disk('local')->assertExists('privado-prueba.pdf');
    }

    public function test_preview_and_download_are_only_available_in_admin_role_and_access_button_follows_same_rule(): void
    {
        $this->profile(); $this->requestRow(1);
        $this->document(2, 'titulo', 'documents/2/titulo.pdf');
        $this->admin();
        $this->get(route('gestion.solicitudes-reemplazo.documentos-academicos.index'))->assertOk()
            ->assertSee('Descargar ZIP y nómina Excel')->assertSee('Reemplazante sintético Prueba Ejemplo');
        $this->assertStringContainsString('Documentos académicos de reemplazantes', Blade::render("@include('gestion.solicitudes-reemplazo.partials._documentos-academicos-acceso')"));
        DB::table('model_has_roles')->insert(['role_id' => 1, 'model_type' => User::class, 'model_id' => 1]);
        auth()->user()->unsetRelation('roles');
        $this->withSession(['active_role' => 'postulante']);
        foreach (['index', 'download'] as $action) { $this->get(route('gestion.solicitudes-reemplazo.documentos-academicos.'.$action))->assertForbidden(); }
        $this->assertStringNotContainsString('Documentos académicos', Blade::render("@include('gestion.solicitudes-reemplazo.partials._documentos-academicos-acceso')"));
        $this->actingAs(User::find(2))->withSession(['active_role' => 'postulante']);
        foreach (['index', 'download'] as $action) { $this->get(route('gestion.solicitudes-reemplazo.documentos-academicos.'.$action))->assertForbidden(); }
    }

    public function test_download_has_private_headers_and_removes_temporary_zip_after_sending(): void
    {
        $this->profile(); $this->requestRow(1); $this->admin();
        $response = $this->get(route('gestion.solicitudes-reemplazo.documentos-academicos.download'))->assertOk()->assertHeader('Content-Type', 'application/zip');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertFileExists($path);
        ob_start();
        try { $response->baseResponse->sendContent(); } finally { ob_end_clean(); }
        $this->assertFileDoesNotExist($path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_empty_view_has_no_download_and_empty_download_returns_validation_error(): void
    {
        $this->admin();
        $url = route('gestion.solicitudes-reemplazo.documentos-academicos.index');
        $this->get($url)->assertOk()->assertDontSee('Descargar ZIP y nómina Excel');
        $this->from($url)->get(route('gestion.solicitudes-reemplazo.documentos-academicos.download'))->assertRedirect($url)->assertSessionHasErrors('documentos');
    }

    public function test_failure_cleans_export_temporaries_and_does_not_change_uploaded_documents(): void
    {
        $this->profile(); $this->requestRow(1); $this->document(2, 'titulo', 'documents/2/titulo.pdf');
        DB::listen(function ($query): void {
            if (str_contains($query->sql, 'user_documents')) { throw new \RuntimeException('Fallo sintético de lectura'); }
        });
        try { app(ReemplazoDocumentosAcademicosExport::class)->generate(); $this->fail('Debió fallar la exportación.'); }
        catch (\RuntimeException $error) { $this->assertSame('Fallo sintético de lectura', $error->getMessage()); }
        $this->assertSame([], Storage::disk('local')->allFiles());
        Storage::disk('public')->assertExists('documents/2/titulo.pdf');
    }

    public function test_person_with_requests_across_multiple_read_batches_is_exported_once(): void
    {
        $this->profile();
        for ($i = 1; $i <= 225; $i++) { $this->requestRow($i); }
        [$entries, $sheets] = $this->package();
        $this->assertCount(2, $sheets['Nómina']); $this->assertCount(226, $sheets['Solicitudes']);
        $this->assertStringContainsString('PRUEBA-225', $sheets['Nómina'][1][2]);
    }

    public function test_download_includes_people_outside_preview_page_and_ignores_management_filters(): void
    {
        for ($id = 2; $id <= 27; $id++) {
            $this->profile($id);
            $this->requestRow($id, $id, $id % 2 ? 'cerrado' : 'aceptada', ['anio' => 2025]);
        }
        $this->admin();
        $filters = ['page' => 2, 'anio' => 2026, 'o_estado' => 'rechazada', 'o_numero' => 'NO-EXISTE'];
        $this->get(route('gestion.solicitudes-reemplazo.documentos-academicos.index', $filters))
            ->assertOk()->assertViewHas('personas', fn ($people) => $people->total() === 26 && $people->count() === 1);
        $response = $this->get(route('gestion.solicitudes-reemplazo.documentos-academicos.download', $filters))->assertOk();
        [, $sheets] = $this->package($response->baseResponse->getFile()->getPathname());
        $this->assertCount(27, $sheets['Nómina']);
        $this->assertCount(27, $sheets['Solicitudes']);
    }

    public function test_invalid_historical_graduation_date_is_blank_and_zip_paths_cannot_escape_person_folder(): void
    {
        $this->profile(2, ['fecha_titulacion' => '0000-00-00']);
        DB::table('users')->where('id', 2)->update(['rut' => '../99000002K', 'nombres' => '../../Prueba']);
        $this->requestRow(1);
        $this->document(2, 'titulo', 'documents/2/titulo.pdf');
        [$entries, $sheets] = $this->package();
        $this->assertNull($sheets['Nómina'][1][8]);
        foreach (array_keys($entries) as $entry) {
            $this->assertStringNotContainsString('..', $entry);
            $this->assertFalse(str_starts_with($entry, '/'));
        }
    }
}
