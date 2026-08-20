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

    public function test_botones_comunales_siguen_habilitados_sin_establecimientos_georreferenciados(): void
    {
        $script = file_get_contents(resource_path('js/centro-operaciones.js'));

        foreach (['Lota', 'Coronel', 'San Pedro de la Paz', 'Santa Juana'] as $commune) {
            $this->assertStringContainsString("normalizeCommune('{$commune}')", $script);
        }

        $this->assertStringContainsString('communeMapFallbacks.has(commune)', $script);
        $this->assertStringContainsString('map.setView(fallback.center, fallback.zoom);', $script);
        $this->assertStringNotContainsString(
            "button.disabled = commune !== '' && !mapPointsByCommune.has(commune);",
            $script
        );
    }

    public function test_aliases_historicos_se_asocian_a_los_botones_comunales(): void
    {
        $script = file_get_contents(resource_path('js/centro-operaciones.js'));

        $this->assertStringContainsString(
            "[normalizeCommune('SAN PEDRO'), normalizeCommune('San Pedro de la Paz')]",
            $script
        );
        $this->assertStringContainsString(
            "[normalizeCommune('STA. JUANA'), normalizeCommune('Santa Juana')]",
            $script
        );
        $this->assertStringContainsString('const communeKey = (value)', $script);
        $this->assertStringContainsString('const commune = communeKey(item.comuna);', $script);
    }

    public function test_marcadores_muestran_puntaje_activo_y_ficha_al_pasar_el_mouse(): void
    {
        $view = file_get_contents(resource_path('views/centro-operaciones/panel.blade.php'));
        $script = file_get_contents(resource_path('js/centro-operaciones.js'));
        $styles = file_get_contents(resource_path('css/centro-operaciones.css'));
        $consolidado = file_get_contents(app_path('Services/CentroOperaciones/ConsolidadoService.php'));

        $this->assertStringContainsString("'puntaje' => \$riesgoActivo ? (int) \$riesgo->irte : 0", $consolidado);
        $this->assertStringContainsString("'activa' => \$riesgoActivo", $consolidado);
        $this->assertStringContainsString('const riskScore = Number(item.riesgo?.puntaje ?? 0);', $script);
        $this->assertStringContainsString('markerIcon(item.estado, activeRisk ? item.riesgo.categoria : null, riskScore)', $script);
        $this->assertStringContainsString("marker.on('mouseover'", $script);
        $this->assertStringContainsString('marker.openPopup();', $script);
        $this->assertStringContainsString('co-leaflet-popup-logo', $script);
        $this->assertStringContainsString('Puntaje de riesgo', $script);
        $this->assertStringContainsString('.co-map-marker b', $styles);
        $this->assertStringContainsString('Número: puntaje IRTE activo', $view);
    }
}
