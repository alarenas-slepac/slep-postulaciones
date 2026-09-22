<?php

namespace Tests\Unit;

use Tests\TestCase;

class DotacionAsignacionViewTest extends TestCase
{
    public function test_el_selector_de_personal_tiene_buscador_y_muestra_la_prelacion(): void
    {
        $source = file_get_contents(resource_path('views/admin/dotacion-establecimiento/partials/_asignacion.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('https://code.jquery.com/jquery-3.7.1.min.js', $source);
        $this->assertStringContainsString('minimumResultsForSearch: 0', $source);
        $this->assertStringContainsString('templateResult: templateResult', $source);
        $this->assertStringContainsString('Buscar por nombre o RUT...', $source);
        $this->assertStringContainsString('data-prioridad-label=', $source);
        $this->assertStringContainsString('data-titular-disponible=', $source);
        $this->assertStringContainsString('dotacion-selector-guide', $source);
        $this->assertStringContainsString('dotacion-assignment-form', $source);
    }
}
