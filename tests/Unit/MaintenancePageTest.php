<?php

namespace Tests\Unit;

use Illuminate\Foundation\Exceptions\RegisterErrorViewPaths;
use Tests\TestCase;

class MaintenancePageTest extends TestCase
{
    public function test_renderiza_la_pagina_de_mantenimiento_con_identidad_sga_y_reintento(): void
    {
        (new RegisterErrorViewPaths)();
        $html = view('errors::503', ['retryAfter' => 60])->render();

        $this->assertStringContainsString('Estamos realizando mejoras.', $html);
        $this->assertStringContainsString('Mantenimiento programado', $html);
        $this->assertStringContainsString('Actualización segura en curso', $html);
        $this->assertStringContainsString('aproximadamente 60 segundos', $html);
        $this->assertStringContainsString('branding/06_logo_login.png', $html);
    }
}
