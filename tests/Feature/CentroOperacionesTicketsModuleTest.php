<?php

namespace Tests\Feature;

use App\Support\SlepUiRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CentroOperacionesTicketsModuleTest extends TestCase
{
    public function test_reparacion_incluye_todas_las_incidencias_y_conserva_plazo_por_defecto(): void
    {
        $migrationInicial = file_get_contents(database_path('migrations/2026_08_06_120000_create_centro_operaciones_tickets_tables.php'));
        $reparacion = file_get_contents(database_path('migrations/2026_08_10_090000_repair_centro_operaciones_tickets.php'));
        $servicio = file_get_contents(app_path('Services/CentroOperaciones/TicketService.php'));

        $this->assertStringContainsString("->default(4)", $migrationInicial);
        $this->assertStringContainsString('completarConfiguraciones()', $reparacion);
        $this->assertStringContainsString('crearTicketsFaltantes()', $reparacion);
        $this->assertStringNotContainsString("\$incidencia->tipo === 'otro'", $servicio);
    }

    public function test_clave_foranea_de_responsable_usa_un_nombre_compatible_con_mysql(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_06_120000_create_centro_operaciones_tickets_tables.php'));

        $this->assertStringContainsString("'co_inc_cfg_responsable_fk'", $migration);
        $this->assertLessThanOrEqual(64, strlen('co_inc_cfg_responsable_fk'));
        $this->assertStringNotContainsString(
            'centro_operaciones_incidente_configuraciones_responsable_funcionario_ac_id_foreign',
            $migration
        );
    }

    public function test_migracion_de_imagenes_crea_la_tabla_aditiva(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('centro_operaciones_tickets', function (Blueprint $table) {
            $table->id();
        });
        $migration = require database_path('migrations/2026_08_17_120000_create_centro_operaciones_ticket_imagenes_table.php');

        try {
            $migration->up();

            $this->assertTrue(Schema::hasTable('centro_operaciones_ticket_imagenes'));
            $this->assertTrue(Schema::hasColumns('centro_operaciones_ticket_imagenes', [
                'ticket_id',
                'path',
                'mime_type',
                'size_bytes',
                'subida_por_id',
            ]));
        } finally {
            $migration->down();
            Schema::dropIfExists('centro_operaciones_tickets');
            Schema::dropIfExists('users');
        }
    }

    public function test_rutas_de_tickets_mantenedor_y_resolucion_estan_registradas(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $this->assertStringContainsString("name('tickets.index')", $routes);
        $this->assertStringContainsString("name('configuraciones.index')", $routes);
        $this->assertStringContainsString("name('configuraciones.store')", $routes);
        $this->assertStringContainsString("name('tickets.resolver')", $routes);
        $this->assertStringContainsString("name('tickets.pdf')", $routes);
        $this->assertStringContainsString("name('tickets.imagenes.store')", $routes);
        $this->assertStringContainsString("name('tickets.imagenes.show')", $routes);
        $this->assertStringContainsString("name('centro-operaciones.tickets.verificar')", $routes);
    }

    public function test_mantenedor_permite_crear_y_asignar_por_subdireccion(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CentroOperaciones/IncidenteConfiguracionController.php'));
        $view = file_get_contents(resource_path('views/centro-operaciones/configuraciones/index.blade.php'));

        $this->assertStringContainsString('public function store(Request $request)', $controller);
        $this->assertStringContainsString("->where('subdireccion_dependencia', \$datos['subdireccion_dependencia'])", $controller);
        $this->assertStringContainsString('48 - strlen($terminacion)', $controller);
        $this->assertStringContainsString('Nueva incidencia', $view);
        $this->assertStringContainsString('<span class="co-step-number">1</span> Subdirección', $view);
        $this->assertStringContainsString('<span class="co-step-number">2</span> Responsable de subdirección', $view);
        $this->assertStringContainsString('Subdirector(a) (Jefatura)', $view);
        $this->assertStringContainsString('data-subdireccion', $view);
        $this->assertStringContainsString('segunda_subdireccion_responsable', $view);
        $this->assertStringContainsString('segundo_responsable_funcionario_ac_id', $view);
    }

    public function test_mantenedor_y_tickets_comparten_la_linea_visual_del_centro_de_operaciones(): void
    {
        $mantenedor = file_get_contents(resource_path('views/centro-operaciones/configuraciones/index.blade.php'));
        $tickets = file_get_contents(resource_path('views/centro-operaciones/tickets/index.blade.php'));
        $detalle = file_get_contents(resource_path('views/centro-operaciones/tickets/show.blade.php'));
        $estilos = file_get_contents(resource_path('css/centro-operaciones.css'));

        foreach ([$mantenedor, $tickets, $detalle] as $vista) {
            $this->assertStringContainsString("@vite('resources/css/centro-operaciones.css')", $vista);
            $this->assertStringContainsString('class="co-shell', $vista);
            $this->assertStringContainsString('class="co-hero', $vista);
            $this->assertStringContainsString('class="co-card', $vista);
        }

        $this->assertStringContainsString('co-config-grid', $mantenedor);
        $this->assertStringContainsString('co-ticket-status', $tickets);
        $this->assertStringContainsString('co-resolution-card', $detalle);
        $this->assertStringContainsString('.co-config-grid', $estilos);
        $this->assertStringContainsString('.co-ticket-status', $estilos);
    }

    public function test_catalogo_dinamico_conserva_nombre_severidad_y_estado(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_06_150000_add_catalog_fields_to_centro_operaciones_incidente_configuraciones.php'));
        $catalogo = file_get_contents(app_path('Services/CentroOperaciones/IncidenciaCatalogo.php'));

        $this->assertStringContainsString("->string('nombre', 120)", $migration);
        $this->assertStringContainsString("->string('severidad', 20)", $migration);
        $this->assertStringContainsString("'active' => \$configuracion->activo", $catalogo);
        $this->assertStringContainsString('public function activos(): Collection', $catalogo);
    }

    public function test_menu_de_tickets_considera_los_roles_asignados_al_usuario(): void
    {
        $navbar = file_get_contents(resource_path('views/partials/navbar.blade.php'));

        $this->assertStringContainsString(
            '$canAccessCentroOperacionesTickets = $u->hasAnyRole($ticketsRoles);',
            $navbar
        );
        $this->assertStringContainsString(
            '@if ($canAccessCentroOperacionesTickets && Route::has(\'centro-operaciones.tickets.index\'))',
            $navbar
        );
        $this->assertStringContainsString(
            '$canManageCentroOperacionesTickets = $u->hasAnyRole([\'admin\', \'gabinete_slep\']);',
            $navbar
        );
        $this->assertStringContainsString(
            '@if ($canManageCentroOperacionesTickets && Route::has(\'centro-operaciones.configuraciones.index\'))',
            $navbar
        );
        $this->assertStringNotContainsString(
            "in_array(\$activeRole, ['admin', 'director_ejecutivo', 'secretaria_direccion_ejecutiva', 'comunicaciones', 'funcionario_ac', 'funcionario_directivo_estab'], true) && Route::has('centro-operaciones.tickets.index')",
            $navbar
        );
    }

    public function test_barra_lateral_moderna_incluye_los_accesos_de_tickets(): void
    {
        $menuDirector = collect(SlepUiRegistry::menuGroups(null, 'director_ejecutivo'))
            ->flatten(1)
            ->pluck('label');
        $menuAdmin = collect(SlepUiRegistry::menuGroups(null, 'admin'))
            ->flatten(1)
            ->pluck('label');

        $this->assertContains('Tickets de incidencias', $menuDirector);
        $this->assertNotContains('Mantenedor de incidencias', $menuDirector);
        $this->assertContains('Tickets de incidencias', $menuAdmin);
        $this->assertContains('Mantenedor de incidencias', $menuAdmin);

        $menuGabinete = collect(SlepUiRegistry::menuGroups(null, 'gabinete_slep'))
            ->flatten(1)
            ->pluck('label');
        $this->assertContains('Tickets de incidencias', $menuGabinete);
        $this->assertContains('Mantenedor de incidencias', $menuGabinete);
    }

    public function test_reporte_resuelve_el_ticket_y_conserva_la_incidencia(): void
    {
        $servicio = file_get_contents(app_path('Services/CentroOperaciones/ReporteService.php'));
        $formulario = file_get_contents(resource_path('views/centro-operaciones/reportes/form.blade.php'));

        $this->assertStringContainsString('private function resolverIncidencias(', $servicio);
        $this->assertStringContainsString('CentroOperacionesTicket::query()', $servicio);
        $this->assertStringContainsString("'resolucion' => \$resolucion", $servicio);
        $this->assertStringNotContainsString("->whereNotIn('tipo', \$tiposIncidencia)\n            ->delete();", $servicio);
        $this->assertStringContainsString("->where('estado', 'activa')", $formulario);
        $this->assertStringContainsString('$this->sincronizarExtintores($reporte, $usuario, $ahora);', $servicio);
        $this->assertStringNotContainsString("where('tipo', '!=', 'otro')", $servicio);
    }

    public function test_segundo_responsable_usa_clave_foranea_y_no_rutas_inexistentes(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_10_090000_repair_centro_operaciones_tickets.php'));
        $modelo = file_get_contents(app_path('Models/CentroOperacionesTicket.php'));
        $controlador = file_get_contents(app_path('Http/Controllers/CentroOperaciones/TicketController.php'));
        $detalle = file_get_contents(resource_path('views/centro-operaciones/tickets/show.blade.php'));

        $this->assertStringContainsString('co_ticket_segundo_resp_fk', $migration);
        $this->assertStringContainsString('segundo_responsable_funcionario_ac_id', $modelo);
        $this->assertStringContainsString("orWhere('segundo_responsable_funcionario_ac_id'", $controlador);
        $this->assertStringNotContainsString('tickets.update-second-responsible', $detalle);
        $this->assertStringNotContainsString('/api/subdirecciones', $detalle);
        $this->assertStringNotContainsString('/api/responsables', $detalle);
    }

    public function test_escalamiento_esta_programado(): void
    {
        $console = file_get_contents(base_path('routes/console.php'));
        $this->assertStringContainsString("Schedule::command('incidencias:escalar-tickets')->hourly()", $console);
    }

    public function test_fotografias_de_ticket_tienen_persistencia_privada_y_alcance_por_establecimiento(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_17_120000_create_centro_operaciones_ticket_imagenes_table.php'));
        $request = file_get_contents(app_path('Http/Requests/CentroOperaciones/SubirTicketImagenesRequest.php'));
        $service = file_get_contents(app_path('Services/CentroOperaciones/TicketImagenService.php'));

        $this->assertStringContainsString("Schema::create('centro_operaciones_ticket_imagenes'", $migration);
        $this->assertStringContainsString('->cascadeOnDelete()', $migration);
        $this->assertStringContainsString("hasRole('funcionario_directivo_estab')", $request);
        $this->assertStringContainsString("hasRole('gabinete_slep')", $request);
        $this->assertStringContainsString('establecimiento_id', $request);
        $this->assertStringContainsString("'mimes:jpg,jpeg,png,webp'", $request);
        $this->assertStringContainsString("Storage::disk('local')", $service);
        $this->assertStringContainsString('lockForUpdate()', $service);
        $this->assertStringContainsString('toJpeg(', $service);
    }

    public function test_detalle_y_pdf_incorporan_hasta_diez_fotografias_de_veinte_mb(): void
    {
        $detalle = file_get_contents(resource_path('views/centro-operaciones/tickets/show.blade.php'));
        $pdf = file_get_contents(resource_path('views/centro-operaciones/tickets/pdf.blade.php'));
        $documentoService = file_get_contents(app_path('Services/CentroOperaciones/TicketDocumentoService.php'));

        $this->assertSame(10, config('centro_operaciones.ticket_imagenes.maximo'));
        $this->assertSame(20, config('centro_operaciones.ticket_imagenes.maximo_mb'));
        $this->assertStringContainsString('name="imagenes[]"', $detalle);
        $this->assertStringContainsString('multiple required', $detalle);
        $this->assertStringContainsString('20 MB por imagen', $detalle);
        $this->assertStringContainsString('Ver informe PDF', $detalle);
        $this->assertStringContainsString('$imagenesPdf as $imagen', $pdf);
        $this->assertStringContainsString('base64_encode($disco->get($imagen->path))', $documentoService);
        $this->assertStringContainsString("'imagenes' => \$ticket->imagenes->map", $documentoService);
    }

    public function test_informe_incluye_firma_y_verificacion_documental(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_10_160000_add_verification_and_signatures_to_centro_operaciones_tickets.php'));
        $servicio = file_get_contents(app_path('Services/CentroOperaciones/TicketDocumentoService.php'));
        $pdf = file_get_contents(resource_path('views/centro-operaciones/tickets/pdf.blade.php'));
        $detalleReporte = file_get_contents(resource_path('views/centro-operaciones/reportes/show.blade.php'));

        $this->assertStringContainsString('centro_operaciones_ticket_firmas', $migration);
        $this->assertStringContainsString('reportado_por_nombre', $migration);
        $this->assertStringContainsString('registrarFirmaResolucion', $servicio);
        $this->assertStringContainsString('verificarIntegridad', $servicio);
        $this->assertStringContainsString('Firma electrónica y verificación documental', $pdf);
        $this->assertStringContainsString('Huella de datos SHA-256', $pdf);
        $this->assertStringContainsString('$reporte->reportado_por_nombre_visible', $detalleReporte);
        $this->assertStringNotContainsString('Usuario no disponible', $detalleReporte);
    }
}
