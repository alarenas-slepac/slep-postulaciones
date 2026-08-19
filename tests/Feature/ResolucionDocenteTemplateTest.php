<?php

namespace Tests\Feature;

use App\Services\ResolucionDocenteDocxService;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

class ResolucionDocenteTemplateTest extends TestCase
{
    public function test_template_has_consecutive_considerandos_after_removing_the_obsolete_sixth(): void
    {
        $path = resource_path('templates/resolucion_docente_reemplazo.docx');
        $zip = new \ZipArchive();

        $this->assertTrue($zip->open($path) === true);

        try {
            $xml = $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }

        $this->assertIsString($xml);

        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadXML($xml));

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $insideConsiderandos = false;
        $numbers = [];
        $texts = [];

        foreach ($xpath->query('//w:body/w:p') as $paragraph) {
            $plainText = '';

            foreach ($xpath->query('.//w:t', $paragraph) as $textNode) {
                $plainText .= $textNode->textContent;
            }

            if (trim($plainText) === 'CONSIDERANDO') {
                $insideConsiderandos = true;
                continue;
            }

            if (trim($plainText) === 'RESUELVO') {
                break;
            }

            if ($insideConsiderandos && preg_match('/^\s*(\d+)°/u', $plainText, $matches) === 1) {
                $numbers[] = (int) $matches[1];
                $texts[] = $plainText;
            }
        }

        $this->assertSame(range(1, 14), $numbers);
        $this->assertStringNotContainsString(
            'es del caso indicar que, este Servicio Local de Educación Pública',
            implode("\n", $texts)
        );
    }

    public function test_versioned_template_refreshes_an_outdated_stored_copy(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('templates/resolucion_docente_reemplazo.docx', 'plantilla-anterior');

        $method = new ReflectionMethod(ResolucionDocenteDocxService::class, 'templatePath');
        $method->setAccessible(true);
        $resolvedPath = $method->invoke(new ResolucionDocenteDocxService());

        $this->assertSame(
            Storage::disk('local')->path('templates/resolucion_docente_reemplazo.docx'),
            $resolvedPath
        );
        $this->assertSame(
            hash_file('sha256', resource_path('templates/resolucion_docente_reemplazo.docx')),
            hash_file('sha256', $resolvedPath)
        );
    }
}
