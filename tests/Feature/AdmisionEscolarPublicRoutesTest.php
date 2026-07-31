<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdmisionEscolarPublicRoutesTest extends TestCase
{
    public function test_public_landing_shows_coming_soon_while_feature_is_disabled(): void
    {
        config([
            'admision.publica_habilitada' => false,
            'admision.mostrar_proximamente' => true,
        ]);

        $this->get('/admision-escolar')
            ->assertOk()
            ->assertSee('Estamos preparando una nueva forma de conocer nuestros establecimientos');
    }

    public function test_public_detail_is_not_exposed_while_feature_is_disabled(): void
    {
        config(['admision.publica_habilitada' => false]);

        $this->get('/admision-escolar/establecimientos/escuela-de-prueba-5000')
            ->assertNotFound();
    }

    public function test_header_and_footer_use_the_official_institutional_url(): void
    {
        config([
            'admision.publica_habilitada' => false,
            'admision.mostrar_proximamente' => true,
            'brand.org_url' => 'https://direccion-incorrecta.example/',
            'brand.org_name' => 'SLEP AC Postulaciones',
        ]);

        $this->get('/admision-escolar')
            ->assertOk()
            ->assertSee('<a href="https://slepandaliencosta.gob.cl/" target="_blank" rel="noopener noreferrer">Sitio institucional</a>', false)
            ->assertSee('<a href="https://slepandaliencosta.gob.cl/" target="_blank" rel="noopener noreferrer">SLEP Andalién Costa</a>', false)
            ->assertDontSee('<small>SLEP AC Postulaciones</small>', false)
            ->assertSee('<a href="mailto:comunicaciones@slepandaliencosta.gob.cl">comunicaciones@slepandaliencosta.gob.cl</a>', false);
    }
}
