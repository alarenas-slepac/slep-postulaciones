<?php

namespace Tests\Feature;

use App\Http\Controllers\Postulante\OfertaLaboralController;
use App\Models\AreaDesempeno;
use App\Models\BolsaTrabajoOferta;
use App\Models\BolsaTrabajoPostulacion;
use App\Models\DocumentType;
use App\Models\PostulantProfile;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\BolsaTrabajoPostulacionService;
use App\Services\UserDocumentReplacement;
use App\Support\PostulantJobProfiles;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Support\IsolatedSecurityTestCase;

class PostulacionesMultiplesTest extends IsolatedSecurityTestCase
{
    private const MIGRATION = '2026_11_24_180000_add_perfiles_laborales_y_biblioteca_postulantes';

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('areas_desempeno', function (Blueprint $table) {
            $table->id();
            $table->string('estamento');
            $table->string('nombre');
            $table->string('slug');
            $table->boolean('activo');
            $table->timestamps();
        });
        Schema::create('postulant_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->unsignedBigInteger('area_desempeno_id');
            $table->string('estamento');
            $table->string('nivel_estudios');
            $table->string('genero')->nullable();
            $table->timestamps();
        });
        Schema::create('document_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('label');
            $table->string('required_for');
            $table->json('conditions')->nullable();
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();
        });
        Schema::create('establecimientos', fn (Blueprint $table) => $table->id());
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('name');
            $table->string('section');
            $table->string('icon');
            $table->integer('sort');
            $table->timestamps();
        });
        Schema::create('module_role', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id');
            $table->unsignedBigInteger('role_id');
        });
        $this->migrate('2025_09_22_160346_create_user_documents_table');
        $this->migrate('2026_04_13_235500_create_bolsa_trabajo_tables_and_modules');
        $this->migrate('2026_04_14_011500_add_etapas_to_bolsa_trabajo');
    }

    public function test_una_cuenta_reutiliza_documentos_en_varios_cargos_y_conserva_el_pdf_presentado(): void
    {
        $user = $this->testUser();
        $assistant = $this->area('asistente', 'Administración');
        $teacher = $this->area('docente', 'Matemática');
        $profile = $this->profile($user, $assistant);
        $identity = $this->type('cedula');
        $degree = $this->type('titulo');
        $transcript = $this->type('licencia_media');
        $identityFile = $this->document($user, $identity, 'cedula.pdf');
        $this->migrate(self::MIGRATION);

        $doc = $this->document($user, $degree, 'titulo-docente.pdf');
        $secondDegree = app(UserDocumentReplacement::class)->replace($user, $degree, UploadedFile::fake()->create('otro.pdf'), 'otro.pdf', credentialLabel: 'Otro título de prueba', additional: true);
        $this->assertNotSame($doc->id, $secondDegree->id);
        $this->document($user, $transcript, 'licencia.pdf');

        $this->actingAs($user)->post(route('postulant.profile.cargos.store'), [
            'area_desempeno_id' => $teacher->id, 'nivel_estudios' => 'Universitaria',
            'titulo' => 'Pedagogía en Matemática', 'anios_experiencia' => 4, 'activo' => '1',
        ])->assertRedirect(route('postulant.profile.cargos.index'));
        $job = $profile->jobProfiles()->sole();
        $this->assertSame($job->id, PostulantJobProfiles::forReplacement($profile, 'docente', $teacher->id)->id);
        $this->assertSame($profile->id, PostulantProfile::matchingCareer(fn ($career) => $career->where('estamento', 'docente'))->sole()->id);
        $first = $this->offer($assistant);
        $second = $this->offer($teacher);
        $payload = ['perfil_laboral' => 'cargo-'.$job->id, 'documentos' => [$degree->id => $doc->id]];
        $this->from(route('postulant.ofertas-laborales.show', $second))
            ->post(route('postulant.ofertas-laborales.store', $second), ['perfil_laboral' => 'principal'])
            ->assertSessionHasErrors('perfil_laboral');
        $this->post(route('postulant.ofertas-laborales.store', $first), ['perfil_laboral' => 'principal'])->assertRedirect();
        $this->post(route('postulant.ofertas-laborales.store', $second), $payload)->assertRedirect();
        $this->post(route('postulant.ofertas-laborales.store', $second), $payload)->assertSessionHas('status', 'Ya registraste tu postulación en esta oferta laboral.');

        $this->assertSame(2, BolsaTrabajoPostulacion::count());
        $this->assertSame(2, $identityFile->submissions()->count());
        $application = $second->postulaciones()->sole();
        $this->assertSame([$identityFile->id, $doc->id], $application->submittedDocuments()->get()->pluck('id')->sort()->values()->all());
        $this->assertSame('Pedagogía en Matemática', $application->cargo_presentado['titulo']);
        $job->update(['activo' => false, 'titulo' => 'Otra descripción']);
        $this->assertSame('Pedagogía en Matemática', $application->fresh()->cargo_presentado['titulo']);
        $this->assertFalse(PostulantProfile::matchingCareer(fn ($career) => $career->where('estamento', 'docente'))->exists());

        $replacement = app(UserDocumentReplacement::class)->replace($user, $identity, UploadedFile::fake()->create('nueva.pdf'), 'nueva.pdf', $identityFile->id);
        Storage::disk('public')->assertExists($identityFile->path);
        Storage::disk('public')->assertExists($replacement->path);
        $this->assertSame($replacement->id, $user->documents()->where('document_type_id', $identity->id)->sole()->id);
        $this->assertSame($identityFile->id, $application->submittedDocuments()->where('document_type_id', $identity->id)->sole()->id);

        $second->update(['etapa_estado' => BolsaTrabajoOferta::ETAPA_ENTREVISTA_FINAL]);
        $view = app(OfertaLaboralController::class)->show(Request::create('/oferta'), $second->fresh(), app(BolsaTrabajoPostulacionService::class));
        $this->assertSame($application->id, $view->getData()['application']->id);
    }

    public function test_requisitos_verifican_propiedad_tipo_estado_vigencia_y_existencia_del_archivo(): void
    {
        $this->migrate(self::MIGRATION);
        $user = $this->testUser();
        $other = $this->testUser(2);
        $area = $this->area('docente', 'Ciencias');
        $this->profile($user, $area);
        $type = $this->type('certificado_especial', 'asistente');
        $today = BolsaTrabajoOferta::portalNow();
        $offer = $this->offer($area, [['document_type_id' => $type->id, 'aprobado' => true, 'vigencia_dias' => 30]]);
        $foreign = $this->document($other, $type, 'otra-cuenta.pdf', 'approved', $today->toDateString());
        $doc = $this->document($user, $type, 'mi-certificado.pdf', 'pending');
        $url = route('postulant.ofertas-laborales.store', $offer);
        $this->actingAs($user)->from(route('postulant.ofertas-laborales.show', $offer));
        $this->post($url, ['perfil_laboral' => 'principal', 'documentos' => [$type->id => $foreign->id]])->assertSessionHasErrors('documentos');
        $this->post($url, ['perfil_laboral' => 'principal'])->assertSessionHasErrors('documentos');
        $this->patch(route('postulant.documents.issue-date', $foreign), ['issued_on' => $today->toDateString()])->assertForbidden();
        $this->patch(route('postulant.documents.issue-date', $doc), ['issued_on' => $today->copy()->subDays(31)->toDateString()])
            ->assertSessionHas('status', 'Fecha de emisión registrada.');
        $this->assertSame($doc->path, $doc->fresh()->path);
        $doc->update(['status' => 'approved']);
        $this->post($url, ['perfil_laboral' => 'principal'])->assertSessionHasErrors('documentos');
        $doc->update(['issued_on' => $today->copy()->subDays(30)->toDateString()]);
        Storage::disk('public')->delete($doc->path);
        $this->post($url, ['perfil_laboral' => 'principal'])->assertSessionHasErrors('documentos');
        Storage::disk('public')->put($doc->path, 'PDF de prueba');
        $this->post($url, ['perfil_laboral' => 'principal'])->assertRedirect(route('postulant.ofertas-laborales.show', $offer));
        $this->assertSame($doc->id, $offer->postulaciones()->sole()->submittedDocuments()->sole()->id);
    }

    public function test_migracion_se_puede_reintentar_con_indices_y_columnas_ya_creados(): void
    {
        $user = $this->testUser();
        $type = $this->type('curriculum');
        $original = $this->document($user, $type, 'cv-historico.pdf');
        Schema::table('user_documents', function (Blueprint $table) {
            $table->string('library_slot', 48)->default('');
            $table->unique(['user_id', 'document_type_id', 'library_slot'], 'ud_library_slot_unique');
        });
        $this->migrate(self::MIGRATION);
        $this->migrate(self::MIGRATION);
        $this->assertSame($original->path, $original->fresh()->path);
        $this->assertFalse(Schema::hasIndex('user_documents', ['user_id', 'document_type_id'], 'unique'));
        $this->assertTrue(Schema::hasIndex('user_documents', 'ud_library_slot_unique', 'unique'));
        $this->assertCount(2, Schema::getForeignKeys('postulant_job_profiles'));
        $this->assertCount(2, Schema::getForeignKeys('bolsa_trabajo_documentos_presentados'));
    }

    public function test_las_vistas_muestran_cargo_requisitos_y_archivos_propios(): void
    {
        $this->withoutExceptionHandling();
        $this->migrate(self::MIGRATION);
        $user = $this->testUser();
        $area = $this->area('asistente', 'Administración');
        $profile = $this->profile($user, $area);
        $profile->forceFill([
            'email_contacto' => 'cuenta@example.test', 'fecha_nacimiento' => '1990-01-01',
            'direccion' => 'Dirección de prueba', 'region_code' => '01', 'comuna_id' => 1,
            'nacionalidad' => 'CL', 'telefono1' => '000000000', 'genero' => 'otro',
            'anios_experiencia' => 0, 'prevision_afp' => 'Prueba', 'salud_institucion' => 'Prueba',
            'banco' => 'Prueba', 'tipo_cuenta' => 'Prueba', 'numero_cuenta' => '0000',
        ]);
        $user->setRelation('postulantProfile', $profile);
        Schema::create('communes', fn (Blueprint $table) => $table->id());
        Schema::create('commune_user', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('commune_id');
        });
        DB::table('commune_user')->insert(['user_id' => $user->id, 'commune_id' => DB::table('communes')->insertGetId([])]);
        $type = $this->type('cedula');
        $file = $this->document($user, $type, 'cedula-de-prueba.pdf');
        $this->type('licencia_media');
        $offer = $this->offer($area);
        $this->actingAs($user);
        $this->get(route('postulant.profile.cargos.index'))->assertOk()->assertSee('Cargos a los que quiero postular')->assertSee('Administración');
        $this->get(route('postulant.profile.cargos.create'))->assertOk()->assertSee('Guardar cargo');
        $this->get(route('postulant.ofertas-laborales.index'))->assertOk()->assertSee('Ver oferta y requisitos');
        $this->get(route('postulant.ofertas-laborales.history'))->assertOk()->assertSee('Aún no has postulado a una oferta');
        $this->get(route('postulant.ofertas-laborales.show', $offer))->assertOk()->assertSee('Se reutiliza')->assertSee('Falta cargar');
        $this->get(route('postulant.documents.index', ['tipo' => $type->id]))->assertOk()->assertSee('cedula-de-prueba.pdf');
        $this->get(route('postulant.documents.download', $file))->assertDownload('cedula-de-prueba.pdf');
    }

    public function test_ddl_compila_para_mysql_con_nombres_cortos_y_reemplaza_el_indice_historico_despues_de_crear_el_nuevo(): void
    {
        $pdo = Mockery::mock(\PDO::class);
        $pdo->shouldReceive('getAttribute')->with(\PDO::ATTR_SERVER_VERSION)->andReturn('8.4.3');
        $mysql = new MySqlConnection($pdo, 'sga_pruebas', 'sga_', ['prefix_indexes' => true]);
        $mysql->useDefaultSchemaGrammar();
        $sql = [];
        $collectStatements = function (string $table, \Closure $callback, bool $create = false) use ($mysql, &$sql) {
            $blueprint = new Blueprint($mysql, $table, function (Blueprint $blueprint) use ($callback, $create) {
                if ($create) {
                    $blueprint->create();
                }
                $callback($blueprint);
            });
            array_push($sql, ...$blueprint->toSql());
        };
        Schema::shouldReceive('hasTable', 'hasColumn', 'hasIndex')->andReturn(false);
        Schema::shouldReceive('getForeignKeys')->andReturn([]);
        Schema::shouldReceive('getIndexes')->with('user_documents')->andReturn([
            ['unique' => true, 'columns' => ['user_id', 'document_type_id'], 'name' => 'sga_user_documents_user_id_document_type_id_unique'],
        ]);
        Schema::shouldReceive('create')->andReturnUsing(fn ($table, $callback) => $collectStatements($table, $callback, true));
        Schema::shouldReceive('table')->andReturnUsing($collectStatements);
        $this->migrate(self::MIGRATION);
        $ddl = implode("\n", $sql);
        preg_match_all('/(?:constraint|index|unique) `([^`]+)`/i', $ddl, $names);
        $this->assertCount(8, $names[1]); // Cuatro claves foráneas, tres índices nuevos y un índice retirado.
        foreach ($names[1] as $name) {
            $this->assertLessThanOrEqual(64, strlen($mysql->getTablePrefix().$name));
        }
        $this->assertLessThan(strpos($ddl, 'drop index'), strpos($ddl, 'add unique `ud_library_slot_unique`'));
    }

    private function area(string $estamento, string $name): AreaDesempeno
    {
        return AreaDesempeno::create(['estamento' => $estamento, 'nombre' => $name, 'activo' => true]);
    }

    private function profile(User $user, AreaDesempeno $area): PostulantProfile
    {
        return $user->postulantProfile()->create([
            'estamento' => $area->estamento, 'area_desempeno_id' => $area->id,
            'nivel_estudios' => $area->estamento === 'docente' ? 'Universitaria' : 'Enseñanza Media',
        ]);
    }

    private function offer(AreaDesempeno $area, array $requirements = []): BolsaTrabajoOferta
    {
        $today = BolsaTrabajoOferta::portalNow();

        return BolsaTrabajoOferta::create([
            'estamento' => $area->estamento, 'area_desempeno_id' => $area->id, 'comuna' => 'Comuna de prueba',
            'calidad_contractual' => 'contrata', 'cantidad_horas' => 20, 'inicio_trabajo_aproximado' => $today->copy()->addDays(7),
            'fecha_inicio_postulaciones' => $today->copy()->subDay(),
            'hora_inicio_postulaciones' => '08:00:00', 'fecha_termino_postulaciones' => $today->copy()->addDay(),
            'hora_termino_postulaciones' => '22:00:00', 'correo_contacto' => 'ofertas@example.test',
            'requisitos_documentales' => $requirements,
        ]);
    }

    private function type(string $slug, string $for = 'both'): DocumentType
    {
        return DocumentType::create(['slug' => $slug, 'label' => $slug, 'required_for' => $for]);
    }

    private function document(User $user, DocumentType $type, string $name, string $status = 'approved', ?string $date = null): UserDocument
    {
        $path = 'documentos-prueba/'.$user->id.'/'.$name;
        Storage::disk('public')->put($path, 'PDF de prueba');
        $record = [
            'document_type_id' => $type->id, 'path' => $path, 'original_name' => $name,
            'mime' => 'application/pdf', 'size' => 13, 'status' => $status,
        ];
        if ($date !== null) {
            $record['issued_on'] = $date;
        }

        return $user->documents()->create($record);
    }

    private function migrate(string $file): void
    {
        (require database_path('migrations/'.$file.'.php'))->up();
    }
}
