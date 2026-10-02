<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\DocumentReviewController;
use App\Http\Controllers\PostulantDocumentsController;
use App\Models\DocumentType;
use App\Models\PostulantProfile;
use App\Models\UserDocument;
use App\Services\UserDocumentReplacement;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\IsolatedSecurityTestCase;

class UserDocumentReplacementTest extends IsolatedSecurityTestCase
{
    private $user;
    private DocumentType $type;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('modules', function (Blueprint $table) { $table->id(); $table->string('key'); });
        Schema::create('document_types', function (Blueprint $table) {
            $table->id(); $table->string('slug'); $table->string('label');
            $table->string('required_for'); $table->text('conditions')->nullable(); $table->timestamps();
        });
        // Solo esta migración de creación, en SQLite :memory:. No se ejecuta
        // ningún comando de reset ni el conjunto de migraciones productivas.
        (require base_path('database/migrations/2025_09_22_160346_create_user_documents_table.php'))->up();
        Schema::create('communes', fn (Blueprint $table) => $table->id());
        Schema::create('commune_user', function (Blueprint $table) { $table->integer('user_id'); $table->integer('commune_id'); });
        DB::table('communes')->insert(['id' => 1]);
        $this->user = $this->testUser();
        DB::table('commune_user')->insert(['user_id' => $this->user->id, 'commune_id' => 1]);
        $profile = new PostulantProfile;
        $profile->forceFill([
            'email_contacto' => 'perfil@example.test', 'fecha_nacimiento' => '1990-01-01',
            'direccion' => 'Dirección de prueba', 'region_code' => '01', 'comuna_id' => 1,
            'nacionalidad' => 'prueba', 'telefono1' => '000000000', 'genero' => 'prueba',
            'nivel_estudios' => 'Enseñanza Media', 'anios_experiencia' => 0, 'estamento' => 'asistente',
            'area_desempeno' => 'Área de prueba', 'prevision_afp' => 'prueba', 'salud_institucion' => 'prueba',
            'banco' => 'prueba', 'tipo_cuenta' => 'prueba', 'numero_cuenta' => '0000',
        ])->setRelation('areaDesempeno', null);
        $this->user->setRelation('postulantProfile', $profile);
        $this->type = DocumentType::create(['slug' => 'identidad', 'label' => 'Identidad', 'required_for' => 'both']);
    }

    private function pdf(string $name = 'nuevo.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\nPDF de prueba sin datos personales\n%%EOF");
    }

    private function replace(): UserDocument
    {
        return app(UserDocumentReplacement::class)->replace($this->user, $this->type, $this->pdf(), 'nombre-descriptivo.pdf');
    }

    private function previous(): UserDocument
    {
        Storage::disk('public')->put('documents/historico.pdf', 'Archivo histórico de prueba');
        return UserDocument::create([
            'user_id' => $this->user->id, 'document_type_id' => $this->type->id,
            'path' => 'documents/historico.pdf', 'original_name' => 'anterior.pdf',
            'mime' => 'application/pdf', 'size' => 123, 'status' => 'approved',
            'reviewer_comment' => 'Revisión anterior', 'reviewed_by' => $this->user->id, 'reviewed_at' => now(),
        ])->fresh();
    }

    private function assertFailurePreserves(UserDocument $previous, array $metadata): void
    {
        try { $this->replace(); $this->fail('Debió rechazar el reemplazo'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('file', $exception->errors()); }
        $this->assertSame($metadata, $previous->fresh()->getRawOriginal());
        $this->assertSame('Archivo histórico de prueba', Storage::disk('public')->get($previous->path));
        $this->assertSame(['documents/historico.pdf'], Storage::disk('public')->allFiles('documents'));
    }

    public function test_first_upload_uses_unique_directory_and_descriptive_downloads(): void
    {
        $this->withoutExceptionHandling();
        $this->actingAs($this->user)->from('/mis-documentos')->post(route('postulant.documents.store', $this->type), ['file' => $this->pdf()])
            ->assertRedirect('/mis-documentos')->assertSessionHasNoErrors();
        $document = UserDocument::sole();
        $this->assertSame('pending', $document->status);
        $this->assertNull($document->reviewed_at);
        $this->assertMatchesRegularExpression('#^documents/\d+/\d+/[a-f0-9-]{36}/.+\.pdf$#', $document->path);
        Storage::disk('public')->assertExists($document->path);
        $filename = basename($document->path);
        $this->assertStringContainsString('_identidad.pdf', $filename);
        $response = app(PostulantDocumentsController::class)->download($document);
        $this->assertStringContainsString($filename, $response->headers->get('Content-Disposition'));
        $response = app(DocumentReviewController::class)->downloadView($document);
        $this->assertStringContainsString($filename, $response->headers->get('Content-Disposition'));
    }

    public function test_successful_replacement_commits_before_deleting_and_resets_review(): void
    {
        $old = $this->previous();
        UserDocument::updating(function ($document) use ($old) {
            $this->assertGreaterThan(0, DB::transactionLevel());
            Storage::disk('public')->assertExists($old->path);
            Storage::disk('public')->assertExists($document->path);
        });
        $new = $this->replace();
        $this->assertSame($old->id, $new->id);
        $this->assertNotSame($old->path, $new->path);
        $this->assertSame(1, UserDocument::count());
        $this->assertSame('pending', $new->status);
        foreach (['reviewer_comment', 'reviewed_by', 'reviewed_at'] as $field) { $this->assertNull($new->$field); }
        $this->assertSame('nuevo.pdf', $new->original_name);
        $this->assertSame('application/pdf', $new->mime);
        Storage::disk('public')->assertExists($new->path);
        Storage::disk('public')->assertMissing($old->path);
    }

    public function test_storage_false_removes_partial_file_and_preserves_all_previous_metadata(): void
    {
        $old = $this->previous(); $metadata = $old->getRawOriginal(); $disk = Storage::disk('public');
        $fault = Mockery::mock($disk);
        $fault->shouldReceive('putFileAs')->once()->andReturnUsing(function ($directory, $file, $name) use ($disk) {
            $disk->putFileAs($directory, $file, $name); return false;
        });
        Storage::shouldReceive('disk')->with('public')->andReturn($fault);
        $this->assertFailurePreserves($old, $metadata);
    }

    public function test_storage_exception_removes_partial_file_and_preserves_previous(): void
    {
        $old = $this->previous(); $metadata = $old->getRawOriginal(); $disk = Storage::disk('public');
        $fault = Mockery::mock($disk);
        $fault->shouldReceive('putFileAs')->once()->andReturnUsing(function ($directory, $file, $name) use ($disk) {
            $disk->putFileAs($directory, $file, $name); throw new \RuntimeException('Fallo simulado de almacenamiento');
        });
        Storage::shouldReceive('disk')->with('public')->andReturn($fault);
        $this->assertFailurePreserves($old, $metadata);
    }

    public function test_success_return_without_existing_file_does_not_update_metadata(): void
    {
        $old = $this->previous(); $metadata = $old->getRawOriginal(); $disk = Storage::disk('public');
        $fault = Mockery::mock($disk);
        $fault->shouldReceive('putFileAs')->once()->andReturnUsing(fn ($dir, $file, $name) => $dir.'/'.$name);
        Storage::shouldReceive('disk')->with('public')->andReturn($fault);
        $this->assertFailurePreserves($old, $metadata);
    }

    public function test_database_failure_rolls_back_changes_and_retires_new_file(): void
    {
        $old = $this->previous(); $metadata = $old->getRawOriginal();
        UserDocument::updating(function ($document) {
            DB::table('user_documents')->where('id', $document->id)->update(['status' => 'rejected']);
            // Excepción SQL real, sin referirse a conexiones o datos externos.
            DB::statement('INSERT INTO nonexistent_document_test_table (id) VALUES (1)');
        });
        $this->assertFailurePreserves($old, $metadata);
    }

    public function test_save_cancelled_without_exception_is_also_a_failed_replacement(): void
    {
        $old = $this->previous(); $metadata = $old->getRawOriginal();
        UserDocument::updating(fn () => false);
        $this->assertFailurePreserves($old, $metadata);
    }

    public function test_database_failure_during_first_upload_leaves_no_record_or_file(): void
    {
        UserDocument::creating(function () {
            DB::statement('INSERT INTO nonexistent_document_test_table (id) VALUES (1)');
        });
        try { $this->replace(); $this->fail('Debió rechazar la primera carga'); }
        catch (ValidationException $exception) { $this->assertArrayHasKey('file', $exception->errors()); }
        $this->assertSame(0, UserDocument::count());
        $this->assertSame([], Storage::disk('public')->allFiles('documents'));
    }

    public function test_delete_failure_after_commit_keeps_new_valid_and_logs_cleanup_reference(): void
    {
        $old = $this->previous(); $disk = Storage::disk('public');
        $fault = Mockery::mock($disk);
        $fault->shouldReceive('delete')->with($old->path)->once()->andReturnUsing(function () {
            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame('pending', UserDocument::sole()->status);
            return false;
        });
        Storage::shouldReceive('disk')->with('public')->andReturn($fault);
        $new = $this->replace();
        $this->assertSame($new->path, UserDocument::sole()->path);
        Storage::disk('public')->assertExists($new->path);
        Storage::disk('public')->assertExists($old->path);
        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) =>
            $message === 'Documento pendiente de limpieza de almacenamiento.'
            && $context['path_sha256'] === hash('sha256', $old->path) && $context['phase'] === 'superseded');
    }

    public function test_another_users_document_cannot_be_downloaded_or_replaced_by_supplied_id(): void
    {
        $old = $this->previous(); $metadata = $old->getRawOriginal(); $other = $this->testUser(2);
        $other->setRelation('postulantProfile', $this->user->postulantProfile);
        DB::table('commune_user')->insert(['user_id' => $other->id, 'commune_id' => 1]);
        $this->actingAs($other)->from('/mis-documentos')->post(route('postulant.documents.store', $this->type), [
            'file' => $this->pdf(), 'user_id' => $this->user->id, 'document_id' => $old->id,
        ])->assertSessionHasNoErrors();
        $this->assertSame($metadata, $old->fresh()->getRawOriginal());
        Storage::disk('public')->assertExists($old->path);
        $this->assertSame(2, UserDocument::count());
        try { app(PostulantDocumentsController::class)->download($old); $this->fail('Debió rechazar la descarga'); }
        catch (\Illuminate\Auth\Access\AuthorizationException $exception) { $this->assertTrue(true); }
    }

    public function test_shared_historical_file_is_not_deleted_while_another_document_references_it(): void
    {
        $old = $this->previous(); $other = $this->testUser(2);
        UserDocument::create(array_replace($old->getAttributes(), ['id' => null, 'user_id' => $other->id]));
        $new = $this->replace();
        Storage::disk('public')->assertExists($new->path);
        Storage::disk('public')->assertExists($old->path);
    }

    public function test_overlapping_first_upload_is_rejected_then_retry_replaces_without_duplicates(): void
    {
        $service = app(UserDocumentReplacement::class); $overlapped = false;
        UserDocument::creating(function () use ($service, &$overlapped) {
            $overlapped = true;
            try {
                $service->replace($this->user, $this->type, $this->pdf('segunda.pdf'), 'nombre-descriptivo.pdf');
                $this->fail('No debe entrar una segunda carga mientras se mantiene el bloqueo');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('otra carga en curso', $exception->errors()['file'][0]);
                $this->assertCount(1, Storage::disk('public')->allFiles('documents'));
            }
        });
        $first = $this->replace();
        $this->assertTrue($overlapped);
        $second = $service->replace($this->user, $this->type, $this->pdf('segunda.pdf'), 'nombre-descriptivo.pdf');
        $this->assertSame($first->id, $second->id);
        $this->assertNotSame($first->path, $second->path);
        $this->assertSame(1, UserDocument::count());
        Storage::disk('public')->assertMissing($first->path);
        Storage::disk('public')->assertExists($second->path);
        $this->assertCount(1, Storage::disk('public')->allFiles('documents'));
    }

    public function test_non_pdf_and_oversized_uploads_leave_previous_untouched(): void
    {
        $old = $this->previous(); $metadata = $old->getRawOriginal();
        $this->actingAs($this->user)->from('/mis-documentos');
        foreach ([UploadedFile::fake()->create('invalido.txt', 1, 'text/plain'), UploadedFile::fake()->create('grande.pdf', 10241, 'application/pdf')] as $file) {
            $this->post(route('postulant.documents.store', $this->type), ['file' => $file])->assertSessionHasErrors('file');
            $this->assertSame($metadata, $old->fresh()->getRawOriginal());
            Storage::disk('public')->assertExists($old->path);
        }
    }
}
