<?php

namespace Tests\Feature;

use App\Http\Controllers\PostulantDocumentsController;
use App\Models\DocumentType;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class PostulantDocumentsTemplateDownloadTest extends TestCase
{
    public function test_usa_la_plantilla_versionada_y_desactiva_la_cache(): void
    {
        $type = new DocumentType([
            'slug' => 'declaracion_cargo_publico',
            'template_path' => 'templates/declaracion_cargo_publico.pdf',
        ]);

        $response = app(PostulantDocumentsController::class)->downloadTemplate($type);

        $this->assertInstanceOf(BinaryFileResponse::class, $response);
        $this->assertSame(
            realpath(resource_path('templates/declaracion_cargo_publico.pdf')),
            $response->getFile()->getRealPath()
        );
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
        $this->assertSame('0', $response->headers->get('Expires'));
    }
}
