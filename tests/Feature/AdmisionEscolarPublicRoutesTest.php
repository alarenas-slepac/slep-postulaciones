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
            ->assertSee('<a href="mailto:comunicaciones@slepandaliencosta.gob.cl">comunicaciones@slepandaliencosta.gob.cl</a>', false)
            ->assertSee('<span>© '.now()->year.' SLEP AC Admisión Escolar. Todos los derechos reservados.</span>', false);
    }

    public function test_public_layout_loads_the_provided_century_gothic_fonts(): void
    {
        config([
            'admision.publica_habilitada' => false,
            'admision.mostrar_proximamente' => true,
        ]);

        $regularFont = public_path('fonts/admision-escolar/century-gothic-regular.ttf');
        $boldFont = public_path('fonts/admision-escolar/century-gothic-bold.ttf');

        $this->assertFileExists($regularFont);
        $this->assertFileExists($boldFont);
        $this->assertSame('3a9cbb5d75b2a2b0d22dc94571608e4e9dc7b88e825374985880c5722c1c9e5f', hash_file('sha256', $regularFont));
        $this->assertSame('90cb613b492874a560c0ff18a3402b1d24fb7e846dff11295d5c4644d6c75e83', hash_file('sha256', $boldFont));

        $this->get('/admision-escolar')
            ->assertOk()
            ->assertSee(asset('fonts/admision-escolar/century-gothic-regular.ttf'), false)
            ->assertSee(asset('fonts/admision-escolar/century-gothic-bold.ttf'), false)
            ->assertSee('font-family:"Century Gothic",Arial,sans-serif', false);
    }

    public function test_footer_shows_whatsapp_contact_below_the_email(): void
    {
        config([
            'admision.publica_habilitada' => false,
            'admision.mostrar_proximamente' => true,
        ]);

        $this->get('/admision-escolar')
            ->assertOk()
            ->assertSeeInOrder([
                '<a href="mailto:comunicaciones@slepandaliencosta.gob.cl">comunicaciones@slepandaliencosta.gob.cl</a>',
                'href="https://wa.me/56926159707"',
                'WhatsApp +56 9 2615 9707',
                'Atención de lunes a viernes, de 08:00 a 17:00.',
                '<strong>Solo mensajes, no llamadas.</strong>',
            ], false)
            ->assertSee('aria-label="Contactar por WhatsApp al +56 9 2615 9707"', false)
            ->assertSee('<svg viewBox="0 0 32 32" aria-hidden="true" focusable="false">', false);
    }

    public function test_long_public_texts_use_the_justified_text_utility(): void
    {
        config([
            'admision.publica_habilitada' => false,
            'admision.mostrar_proximamente' => true,
        ]);

        $this->get('/admision-escolar')
            ->assertOk()
            ->assertSee('.ae-long-text{text-align:justify;text-justify:inter-word;hyphens:auto}', false)
            ->assertSee('<p class="ae-long-text">Muy pronto podrás explorar', false);
    }
}
