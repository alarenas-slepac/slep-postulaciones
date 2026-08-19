<?php

namespace Tests\Feature;

use Tests\TestCase;

class CentroOperacionesMapControlsTest extends TestCase
{
    public function test_mapa_territorial_incluye_enfoque_por_comuna_y_alturas_ampliadas(): void
    {
        $view = file_get_contents(resource_path('views/centro-operaciones/panel.blade.php'));
        $script = file_get_contents(resource_path('js/centro-operaciones.js'));
        $styles = file_get_contents(resource_path('css/centro-operaciones.css'));

        $this->assertStringContainsString('data-map-commune=""', $view);
        foreach (['Lota', 'Coronel', 'San Pedro de la Paz', 'Santa Juana'] as $commune) {
            $this->assertStringContainsString("'{$commune}'", $view);
        }

        $this->assertStringContainsString('normalizeCommune', $script);
        $this->assertStringContainsString("root.querySelectorAll('[data-map-commune]')", $script);
        $this->assertStringContainsString('map.fitBounds(points, { padding: [35, 35], maxZoom });', $script);
        $this->assertStringContainsString("button.setAttribute('aria-pressed'", $script);

        $this->assertStringContainsString('#co-map { height: 512px;', $styles);
        $this->assertStringContainsString('body.co-tv-mode #co-map { height: 408px;', $styles);
        $this->assertStringContainsString('#co-map { height: 396px;', $styles);
    }
}
