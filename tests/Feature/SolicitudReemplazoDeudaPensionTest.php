<?php

namespace Tests\Feature;

use App\Models\SolicitudReemplazoDeudaPension;
use App\Models\UserDocument;
use App\Support\SlepUiRegistry;
use App\Support\ModuleRegistry;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SolicitudReemplazoDeudaPensionTest extends TestCase
{
    public function test_estado_se_desbloquea_solo_despues_del_envio_con_documentos_vigentes(): void
    {
        $deuda = new SolicitudReemplazoDeudaPension([
            'certificado_deuda_path' => 'certificado.pdf',
            'resolucion_path' => 'resolucion.pdf',
            'valor_cuota_alimentaria' => 150000,
        ]);
        $deuda->certificado_subido_at = Carbon::parse('2026-08-13 09:00:00');
        $deuda->resolucion_subida_at = Carbon::parse('2026-08-13 09:30:00');

        $declaracion = new UserDocument(['path' => 'declaracion.pdf']);
        $declaracion->updated_at = Carbon::parse('2026-08-13 10:00:00');

        $this->assertSame(
            SolicitudReemplazoDeudaPension::ESTADO_LISTO_ENVIO,
            $deuda->estadoFlujo($declaracion)
        );

        $deuda->enviado_at = Carbon::parse('2026-08-13 10:30:00');
        $this->assertSame(
            SolicitudReemplazoDeudaPension::ESTADO_ENVIADO,
            $deuda->estadoFlujo($declaracion)
        );

        $declaracion->updated_at = Carbon::parse('2026-08-13 11:00:00');
        $this->assertSame(
            SolicitudReemplazoDeudaPension::ESTADO_LISTO_ENVIO,
            $deuda->estadoFlujo($declaracion)
        );
    }

    public function test_rutas_separan_gestion_slep_y_expediente_del_postulante(): void
    {
        $gestion = app('router')->getRoutes()->getByName('gestion.deudas-pension-alimentos.index');
        $activacion = app('router')->getRoutes()->getByName('gestion.solicitudes-reemplazo.deuda-pension-alimentos.activar');
        $postulante = app('router')->getRoutes()->getByName('postulant.deudas-pension-alimentos.index');

        $this->assertNotNull($gestion);
        $this->assertContains('ensure.role:admin|funcionario_slep', $gestion->gatherMiddleware());
        $this->assertContains('ensure.active-role:admin|funcionario_slep', $activacion->gatherMiddleware());
        $this->assertContains('ensure.role:postulante|funcionario', $postulante->gatherMiddleware());
        $this->assertSame('gestion.solicitudes-reemplazo', ModuleRegistry::moduleKeyFromRouteName($gestion->getName()));
        $this->assertSame('postulant.reemplazos', ModuleRegistry::moduleKeyFromRouteName($postulante->getName()));
    }

    public function test_menu_muestra_bandejas_solo_a_los_roles_correspondientes(): void
    {
        $slep = collect(SlepUiRegistry::menuGroups(null, 'funcionario_slep'))->flatten(1)->pluck('label');
        $postulante = collect(SlepUiRegistry::menuGroups(null, 'postulante'))->flatten(1)->pluck('label');
        $gdp = collect(SlepUiRegistry::menuGroups(null, 'coordinador_gdp'))->flatten(1)->pluck('label');

        $this->assertContains('Deuda pensión de alimentos', $slep);
        $this->assertContains('Mi deuda de pensión', $postulante);
        $this->assertNotContains('Deuda pensión de alimentos', $gdp);
    }

    public function test_backend_bloquea_ot_y_contrato_mientras_el_expediente_no_este_enviado(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Gestion/SolicitudReemplazoGestionController.php'));
        $gestion = file_get_contents(app_path('Http/Controllers/Gestion/DeudaPensionAlimentosController.php'));
        $modelo = file_get_contents(app_path('Models/SolicitudReemplazoDeudaPension.php'));

        $this->assertGreaterThanOrEqual(4, substr_count($controller, 'deudaPensionBloqueaFlujo'));
        $this->assertStringContainsString("abort_unless(\$solicitud->derivada_a_user_id", $gestion);
        $this->assertStringContainsString("\$solicitud->estado === 'derivada_slep'", $gestion);
        $this->assertStringContainsString('ESTADO_ENVIADO', $modelo);
        $this->assertStringContainsString("'estado' => SolicitudReemplazoDeudaPension::ESTADO_ENVIADO", file_get_contents(app_path('Services/SolicitudReemplazoDeudaPensionService.php')));
    }

    public function test_declaracion_jurada_se_resuelve_dinamicamente_por_slug(): void
    {
        $modelo = file_get_contents(app_path('Models/SolicitudReemplazoDeudaPension.php'));
        $mailable = file_get_contents(app_path('Mail/DeudaPensionAlimentosRemuneracionesMail.php'));

        $this->assertStringContainsString("'declaracion_cargo_publico'", $modelo);
        $this->assertStringContainsString("['public', \$this->declaracion->path", $mailable);
    }
}
