<?php

namespace Tests\Feature;

use App\Http\Controllers\Gestion\OrdenTrabajoPdfController;
use App\Models\SolicitudReemplazo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrdenTrabajoSupervisorPlanificacionAccessTest extends TestCase
{
    public function test_supervisor_planificacion_puede_descargar_ot_existente_sin_regenerarla(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ordenes/ot-100.pdf', '%PDF-1.4 prueba');

        $solicitud = new SolicitudReemplazo();
        $solicitud->id = 100;
        $solicitud->numero_solicitud = '00100-2026';
        $solicitud->orden_trabajo_pdf_path = 'ordenes/ot-100.pdf';

        $user = new class {
            public function hasAnyRole(array $roles): bool
            {
                return count(array_intersect($roles, ['supervisor_plani', 'coordinador_gdp'])) > 0;
            }

            public function hasRole(string $role): bool
            {
                return $role === 'supervisor_plani';
            }

            public function activeRoleName(): string
            {
                return 'supervisor_plani';
            }
        };

        $request = Request::create('/gestion/solicitudes-reemplazo/100/orden-trabajo/download', 'GET', [
            'regenerar' => 1,
        ]);
        $request->setUserResolver(fn () => $user);

        $response = app(OrdenTrabajoPdfController::class)->download($request, $solicitud);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ordenes/ot-100.pdf', $solicitud->orden_trabajo_pdf_path);
        Storage::disk('local')->assertExists('ordenes/ot-100.pdf');
    }
}
