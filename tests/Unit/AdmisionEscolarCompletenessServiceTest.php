<?php

namespace Tests\Unit;

use App\Models\AdmisionEstablecimiento;
use App\Models\AdmisionEstablecimientoImagen;
use App\Models\Establecimiento;
use App\Services\AdmisionEscolarCompletenessService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Tests\TestCase;

class AdmisionEscolarCompletenessServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['admision.min_imagenes_publicacion' => 1]);
    }

    public function test_complete_profile_scores_one_hundred_and_is_publishable(): void
    {
        $establecimiento = $this->establecimientoBase();
        $perfil = new AdmisionEstablecimiento([
            'sello_educativo' => 'Inclusión, participación y desarrollo integral.',
            'descripcion_corta' => 'Una comunidad educativa abierta al territorio.',
            'director_nombre' => 'María González',
            'director_foto_path' => 'admision/director.webp',
            'logo_path' => 'admision/logo.webp',
            'sitio_web_url' => 'https://example.test',
            'facebook_url' => 'https://facebook.com/example',
        ]);
        $perfil->setRelation('imagenes', new EloquentCollection([
            new AdmisionEstablecimientoImagen([
                'imagen_path' => 'admision/portada.webp',
                'texto_alternativo' => 'Fachada del establecimiento',
                'es_portada' => true,
            ]),
        ]));

        $result = app(AdmisionEscolarCompletenessService::class)->calculate($establecimiento, $perfil);

        $this->assertSame(100, $result['score']);
        $this->assertTrue($result['publishable']);
        $this->assertSame([], $result['missing']);
    }

    public function test_optional_links_do_not_block_publication(): void
    {
        $establecimiento = $this->establecimientoBase();
        $perfil = new AdmisionEstablecimiento([
            'sello_educativo' => 'Aprendizaje con identidad territorial.',
            'director_nombre' => 'Juan Pérez',
            'director_foto_path' => 'admision/director.webp',
            'logo_path' => 'admision/logo.webp',
        ]);
        $perfil->setRelation('imagenes', new EloquentCollection([
            new AdmisionEstablecimientoImagen([
                'imagen_path' => 'admision/portada.webp',
                'texto_alternativo' => 'Patio del establecimiento',
                'es_portada' => true,
            ]),
        ]));

        $service = app(AdmisionEscolarCompletenessService::class);
        $result = $service->calculate($establecimiento, $perfil);

        $this->assertTrue($result['publishable']);
        $this->assertSame([], $service->publicationMissing($establecimiento, $perfil));
        $this->assertLessThan(100, $result['score']);
    }

    public function test_missing_required_media_blocks_publication(): void
    {
        $establecimiento = $this->establecimientoBase();
        $perfil = new AdmisionEstablecimiento([
            'sello_educativo' => 'Formación integral.',
            'director_nombre' => 'María González',
        ]);
        $perfil->setRelation('imagenes', new EloquentCollection());

        $missing = app(AdmisionEscolarCompletenessService::class)
            ->publicationMissing($establecimiento, $perfil);

        $this->assertContains('Fotografía del director o directora', $missing);
        $this->assertContains('Logo del establecimiento', $missing);
        $this->assertContains('Imagen de portada', $missing);
        $this->assertNotEmpty($missing);
    }

    private function establecimientoBase(): Establecimiento
    {
        return new Establecimiento([
            'cod_estab' => 5000,
            'rbd' => 5000,
            'dv' => '1',
            'nombre_establecimiento' => 'Escuela de Prueba',
            'comuna' => 'Coronel',
            'basica' => true,
        ]);
    }
}
