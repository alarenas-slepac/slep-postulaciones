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
}
