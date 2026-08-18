<?php

namespace Tests\Feature;

use App\Http\Controllers\Postulante\MisFiniquitosController;
use App\Support\ModuleRegistry;
use App\Support\SlepUiRegistry;
use ReflectionMethod;
use Tests\TestCase;

class PostulanteFiniquitosAccessTest extends TestCase
{
    public function test_routes_use_the_existing_postulant_replacements_module(): void
    {
        $index = app('router')->getRoutes()->getByName('postulant.finiquitos.index');
        $download = app('router')->getRoutes()->getByName('postulant.finiquitos.descargar');

        $this->assertNotNull($index);
        $this->assertNotNull($download);
        $this->assertContains('ensure.role:postulante|funcionario', $index->gatherMiddleware());
        $this->assertSame('postulant.reemplazos', ModuleRegistry::moduleKeyFromRouteName($index->getName()));
        $this->assertSame('postulant.reemplazos', ModuleRegistry::moduleKeyFromRouteName($download->getName()));
    }

    public function test_menu_uses_the_same_authorized_module(): void
    {
        $entry = collect(SlepUiRegistry::menuGroups(null, 'postulante'))
            ->flatten(1)
            ->firstWhere('route', 'postulant.finiquitos.index');

        $this->assertNotNull($entry);
        $this->assertSame('postulant.reemplazos', $entry['module']);
    }

    public function test_download_normalizes_a_lowercase_rut_check_digit(): void
    {
        $method = new ReflectionMethod(MisFiniquitosController::class, 'rutComparable');
        $method->setAccessible(true);

        $this->assertSame('12345678K', $method->invoke(new MisFiniquitosController(), '12.345.678-k'));
    }
}
