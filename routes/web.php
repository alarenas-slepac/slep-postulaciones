<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AdmisionEscolarPublicController;
use App\Http\Controllers\RoleContextController;
use App\Http\Controllers\PostulanteProvisorioLookupController;
use App\Http\Controllers\CentroOperaciones\PanelController as CentroOperacionesPanelController;
use App\Http\Controllers\CentroOperaciones\ReporteController as CentroOperacionesReporteController;
use App\Http\Controllers\CentroOperaciones\TicketController as CentroOperacionesTicketController;
use App\Http\Controllers\CentroOperaciones\IncidenteConfiguracionController as CentroOperacionesIncidenteConfiguracionController;

use App\Http\Controllers\ReemplazosController;
use App\Http\Controllers\Reemplazos\PersonalImportController;

use App\Http\Controllers\PostulantProfileController;
use App\Http\Controllers\PostulantDocumentsController;
use App\Http\Controllers\Postulante\MisReemplazosController;
use App\Http\Controllers\Postulante\MisFiniquitosController;

use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\Admin\PostulacionAdminController;
use App\Http\Controllers\Admin\AdmisionEscolarController;
use App\Http\Controllers\Admin\AdmisionEscolarGaleriaController;
use App\Http\Controllers\Admin\EstablecimientoController;
use App\Http\Controllers\Admin\EstablecimientoAreaDesempenoController;
use App\Http\Controllers\Admin\SubsectorAdminController;
use App\Http\Controllers\Admin\MencionAdminController;
use App\Http\Controllers\Admin\TituloCatalogoController;
use App\Http\Controllers\Admin\FuncionCatalogoController;
use App\Http\Controllers\Admin\InstitucionCatalogoController;
use App\Http\Controllers\Admin\DocumentReviewController;
use App\Http\Controllers\Admin\AreaDesempenoController;
use App\Http\Controllers\Admin\AaeeValorHoraController;
use App\Http\Controllers\Admin\ViaticoReembolsoValorController;
use App\Http\Controllers\Admin\ViaticoDisponibilidadPresupuestariaController;
use App\Http\Controllers\Admin\CometidoNotificacionConfiguracionController;
use App\Http\Controllers\Admin\FuncionarioViaticoAnexoController;
use App\Http\Controllers\Admin\AlumnoPrioritarioPorcentajeController;
use App\Http\Controllers\Admin\CursoController;
use App\Http\Controllers\Admin\PlanEstudioController;
use App\Http\Controllers\Admin\AsignaturaController;
use App\Http\Controllers\Admin\EstablecimientoCursoController;
use App\Http\Controllers\Admin\EstablecimientoCursoPieController;
use App\Http\Controllers\Admin\DotacionFuncionesController;
use App\Http\Controllers\Admin\DotacionCursoCombinadoController;
use App\Http\Controllers\Admin\DotacionEstablecimientoController;
use App\Http\Controllers\Admin\DotacionProporcionExcepcionController;
use App\Http\Controllers\Admin\DotacionAsignacionController;
use App\Http\Controllers\Admin\EstablecimientoPlanEstudioController;
use App\Http\Controllers\Admin\AsignaturaPersonalizadaController;

use App\Http\Controllers\FuncionarioEstab\SolicitudReemplazoController;
use App\Http\Controllers\MessagingController;
use App\Http\Controllers\Gestion\SolicitudReemplazoGestionController;
use App\Http\Controllers\Reemplazos\BuscadorPostulantesController;
use App\Http\Controllers\Gestion\OrdenTrabajoPdfController;
use App\Http\Controllers\Gestion\EstadisticasController;
use App\Http\Controllers\Gestion\InformesController;
use App\Http\Controllers\Gestion\PostulanteTutorialController;
use App\Http\Controllers\Gestion\BolsaTrabajoController;
use App\Http\Controllers\Gestion\AgendamientoRecursoController;
use App\Http\Controllers\Postulante\OfertaLaboralController;
use App\Http\Controllers\Admin\RestrictedRutController;
use App\Http\Controllers\Admin\PermisoSinGoceExcepcionController;
use App\Http\Controllers\Admin\NotificationDispatchLogController;
use App\Http\Controllers\IncumplimientoLaboralController;
use App\Http\Controllers\ChangeLogController;
use App\Http\Controllers\Endeudamiento\MaeCargaController;
use App\Http\Controllers\Endeudamiento\MaeRegistroController;
use App\Http\Controllers\Endeudamiento\MaeTopesController;
use App\Http\Controllers\Endeudamiento\MaeNormativaController;
use App\Http\Controllers\Endeudamiento\MaeCuotasController;
use App\Http\Controllers\Liquidaciones\LiquidacionCargaController;
use App\Http\Controllers\Liquidaciones\MisLiquidacionesController;
use App\Http\Controllers\TramiteController;
use App\Http\Controllers\CargaFamiliarController;
use App\Http\Controllers\FuncionarioAcController;
use App\Http\Controllers\FuncionarioAcAutorizadoController;
use App\Http\Controllers\FuncionarioAcJefaturaDependenciaController;
use App\Http\Controllers\DeclaracionSostenedorController;
use App\Http\Controllers\Tramites\CometidoFuncionarioController;
use App\Http\Controllers\Tramites\CometidoFuncionarioRendicionController;
use App\Http\Controllers\Tramites\CometidoFuncionarioInformeController;
use App\Http\Controllers\Tramites\LicenciaMedicaController;
use App\Http\Controllers\Tramites\LicenciaFeriadoController;
use App\Http\Controllers\System\GlobalSearchController;
use App\Http\Controllers\Certificados\CertificadoImportacionController;
use App\Http\Controllers\Certificados\CertificadoLaboralController;
use App\Http\Controllers\Certificados\CertificadoVerificacionController;


Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

// Carga rutas de autenticación (login/registro/password/verify)
require __DIR__ . '/auth.php';

// Dashboard (decide vista según rol; NO lo restringimos por módulo)
Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::post('/role-context', [RoleContextController::class, 'update'])
    ->middleware(['auth'])
    ->name('role-context.update');

Route::post('/changelog/ack', [ChangeLogController::class, 'acknowledge'])
    ->middleware(['auth'])
    ->name('changelog.ack');

Route::get('/buscar', GlobalSearchController::class)
    ->middleware(['auth', 'verified'])
    ->name('global-search');

// Vista temporal de usuarios. Index e inicio requieren un operador autorizado.
// Finalizar queda solo con auth porque la cuenta visualizada puede tener cualquier rol.
Route::get('/gestion/postulantes/tutoriales', [PostulanteTutorialController::class, 'index'])
    ->middleware(['auth', 'verified', 'ensure.role:admin|coordinador_gdp|funcionario_slep'])
    ->name('gestion.postulante-tutorial.index');
Route::post('/gestion/postulantes/tutoriales/finalizar', [PostulanteTutorialController::class, 'stop'])
    ->middleware('auth')
    ->name('gestion.postulante-tutorial.stop');
Route::post('/gestion/postulantes/tutoriales/{user}/iniciar', [PostulanteTutorialController::class, 'start'])
    ->middleware(['auth', 'verified', 'ensure.role:admin|coordinador_gdp|funcionario_slep'])
    ->whereNumber('user')
    ->name('gestion.postulante-tutorial.start');

// Lookup público/guest
Route::get('/postulantes-provisorios/lookup', PostulanteProvisorioLookupController::class)
    ->middleware('guest');

Route::get('/ofertas-laborales-vigentes', [OfertaLaboralController::class, 'publicIndex'])
    ->name('public.ofertas-laborales.index');

// Vitrina pública de establecimientos para Admisión Escolar (sin login).
Route::get('/admision-escolar', [AdmisionEscolarPublicController::class, 'index'])
    ->name('public.admision-escolar.index');
Route::get('/admision-escolar/establecimientos/{slug}', [AdmisionEscolarPublicController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->name('public.admision-escolar.show');

Route::get('/validar-documento/{codigo}', [CometidoFuncionarioController::class, 'validarDocumentoPublico'])
    ->middleware('throttle:60,1')
    ->name('documentos.validar');

Route::get('/certificados/verificar/{codigo}', CertificadoVerificacionController::class)
    ->middleware('throttle:60,1')
    ->where('codigo', '[A-Fa-f0-9]{32}')
    ->name('certificados.verificar');

// =========================
//  RUTAS PROTEGIDAS POR MÓDULO
// =========================
Route::middleware(['auth', 'verified', 'ensure.module'])->group(function () {

    Route::prefix('centro-operaciones')->name('centro-operaciones.')->group(function () {
        Route::middleware('ensure.role:admin|director_ejecutivo|secretaria_direccion_ejecutiva|comunicaciones|funcionario_ac|funcionario_directivo_estab')->group(function () {
            Route::get('/tickets', [CentroOperacionesTicketController::class, 'index'])->name('tickets.index');
            Route::get('/tickets/{ticket}', [CentroOperacionesTicketController::class, 'show'])->whereNumber('ticket')->name('tickets.show');
            Route::patch('/tickets/{ticket}/resolver', [CentroOperacionesTicketController::class, 'resolver'])->whereNumber('ticket')->name('tickets.resolver');
        });
        Route::middleware('ensure.role:admin')->group(function () {
            Route::get('/mantenedor-incidencias', [CentroOperacionesIncidenteConfiguracionController::class, 'index'])->name('configuraciones.index');
            Route::post('/mantenedor-incidencias', [CentroOperacionesIncidenteConfiguracionController::class, 'store'])->name('configuraciones.store');
            Route::put('/mantenedor-incidencias/{configuracion}', [CentroOperacionesIncidenteConfiguracionController::class, 'update'])->whereNumber('configuracion')->name('configuraciones.update');
        });
        Route::middleware('ensure.role:admin|director_ejecutivo|funcionario_slep|coordinador_gdp|coordinador_uatp|comunicaciones|gabinete_slep|secretaria_direccion_ejecutiva')
            ->group(function () {
                Route::get('/', [CentroOperacionesPanelController::class, 'index'])->name('index');
                Route::get('/datos', [CentroOperacionesPanelController::class, 'datos'])->name('datos');
                Route::get('/tv', [CentroOperacionesPanelController::class, 'tv'])->name('tv');
            });

        Route::get('/reporte-diario', [CentroOperacionesReporteController::class, 'create'])
            ->middleware('ensure.role:funcionario_directivo_estab')
            ->name('reportes.create');
        Route::post('/reportes', [CentroOperacionesReporteController::class, 'store'])
            ->middleware('ensure.role:funcionario_directivo_estab')
            ->name('reportes.store');
        Route::get('/historial', [CentroOperacionesReporteController::class, 'history'])
            ->middleware('ensure.role:admin|director_ejecutivo|funcionario_slep|coordinador_gdp|coordinador_uatp|comunicaciones|gabinete_slep|secretaria_direccion_ejecutiva|funcionario_directivo_estab')
            ->name('reportes.history');
        Route::get('/reportes/{reporte}', [CentroOperacionesReporteController::class, 'show'])
            ->middleware('ensure.role:admin|director_ejecutivo|funcionario_slep|coordinador_gdp|coordinador_uatp|comunicaciones|gabinete_slep|secretaria_direccion_ejecutiva|funcionario_directivo_estab')
            ->whereNumber('reporte')
            ->name('reportes.show');
        Route::get('/reportes/{reporte}/editar', [CentroOperacionesReporteController::class, 'edit'])
            ->middleware('ensure.role:funcionario_directivo_estab')
            ->whereNumber('reporte')
            ->name('reportes.edit');
        Route::put('/reportes/{reporte}', [CentroOperacionesReporteController::class, 'update'])
            ->middleware('ensure.role:funcionario_directivo_estab')
            ->whereNumber('reporte')
            ->name('reportes.update');
    });

    Route::prefix('certificados')
        ->name('certificados.')
        ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep|funcionario|funcionario_ac')
        ->group(function () {
            Route::get('/', [CertificadoLaboralController::class, 'index'])
                ->name('index');
            Route::post('/emitir', [CertificadoLaboralController::class, 'emitir'])
                ->name('emitir');

            Route::prefix('importaciones')
                ->name('importaciones.')
                ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
                ->group(function () {
                    Route::get('/', [CertificadoImportacionController::class, 'index'])
                        ->name('index');
                    Route::get('/crear', [CertificadoImportacionController::class, 'create'])
                        ->name('create');
                    Route::post('/', [CertificadoImportacionController::class, 'store'])
                        ->name('store');
                    Route::get('/{importacion}', [CertificadoImportacionController::class, 'show'])
                        ->whereNumber('importacion')
                        ->name('show');
                    Route::post('/{importacion}/activar', [CertificadoImportacionController::class, 'activar'])
                        ->whereNumber('importacion')
                        ->name('activar');
                });

            Route::get('/{certificado}', [CertificadoLaboralController::class, 'ver'])
                ->whereNumber('certificado')
                ->name('ver');
            Route::get('/{certificado}/descargar', [CertificadoLaboralController::class, 'descargar'])
                ->whereNumber('certificado')
                ->name('descargar');
            Route::patch('/{certificado}/anular', [CertificadoLaboralController::class, 'anular'])
                ->whereNumber('certificado')
                ->name('anular');
        });

    // -------------------------
    // Declaración de Sostenedores
    // -------------------------
    Route::prefix('declaracion')->name('declaracion.')->group(function () {
        Route::get('/', [DeclaracionSostenedorController::class, 'index'])->name('index');
        Route::post('/importar', [DeclaracionSostenedorController::class, 'importar'])->name('importar');
        Route::get('/exportar', [DeclaracionSostenedorController::class, 'exportar'])->name('exportar');
        Route::get('/exportar-reporte-establecimientos', [DeclaracionSostenedorController::class, 'exportarReporteEstablecimientos'])->name('exportarReporteEstablecimientos');
        Route::get('/exportar-pendientes-documentos', [DeclaracionSostenedorController::class, 'exportarPendientesDocumentos'])->name('exportarPendientesDocumentos');
        Route::post('/exportar-documentos', [DeclaracionSostenedorController::class, 'exportarDocumentos'])->name('exportarDocumentos');
        Route::get('/exportaciones-documentos/{id}/descargar', [DeclaracionSostenedorController::class, 'descargarExportacionDocumentos'])->name('descargarExportacionDocumentos');
        Route::get('/instructivo-pdf', [DeclaracionSostenedorController::class, 'instructivoPdf'])->name('instructivo.pdf');
        Route::delete('/{id}', [DeclaracionSostenedorController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/informar', [DeclaracionSostenedorController::class, 'informar'])->name('informar');
        Route::get('/{id}/certificado/{tipo}', [DeclaracionSostenedorController::class, 'verCertificado'])->where('tipo', 'titulo|antecedentes')->name('certificado.ver');
        Route::post('/{id}/certificado/{tipo}', [DeclaracionSostenedorController::class, 'subirCertificado'])->where('tipo', 'titulo|antecedentes')->name('certificado.subir');
        Route::put('/{id}/fecha', [DeclaracionSostenedorController::class, 'actualizarFecha'])->name('actualizarFecha');
        Route::put('/{id}/titulo', [DeclaracionSostenedorController::class, 'actualizarTitulo'])->name('actualizarTitulo');
        Route::put('/{id}/institucion', [DeclaracionSostenedorController::class, 'actualizarInstitucion'])->name('actualizarInstitucion');
        Route::put('/{id}/pais', [DeclaracionSostenedorController::class, 'actualizarPais'])->name('actualizarPais');
        Route::put('/{id}/funcion', [DeclaracionSostenedorController::class, 'actualizarFuncion'])->name('actualizarFuncion');
        Route::put('/{id}/tipo-titulo', [DeclaracionSostenedorController::class, 'actualizarTipoTitulo'])->name('actualizarTipoTitulo');
        Route::put('/{id}/datos-laborales', [DeclaracionSostenedorController::class, 'actualizarDatosLaborales'])->name('actualizarDatosLaborales');
        Route::put('/{id}/rbd', [DeclaracionSostenedorController::class, 'actualizarRbd'])->name('actualizarRbd');
        Route::put('/{id}/confirmar', [DeclaracionSostenedorController::class, 'confirmarRegistro'])->name('confirmarRegistro');
    });


    // -------------------------
    // Operación: Reemplazos
    // -------------------------
    Route::get('/reemplazos', [ReemplazosController::class, 'index'])
        ->name('reemplazos.index');

    Route::get('/reemplazos/padron/export', [ReemplazosController::class, 'export'])
        ->name('reemplazos.export');

    Route::post('/reemplazos/padron/traspasar-bloqueos', [ReemplazosController::class, 'traspasarBloqueosPersonal'])
        ->middleware('ensure.role:admin|funcionario_slep|coordinador_uatp|supervisor_plani|coordinador_plani')
        ->name('reemplazos.personal.traspasar-bloqueos');

    Route::get('/reemplazos/padron/{reemplazoPersonal}/edit', [ReemplazosController::class, 'editPersonal'])
        ->name('reemplazos.personal.edit');

    Route::put('/reemplazos/padron/{reemplazoPersonal}', [ReemplazosController::class, 'updatePersonal'])
        ->name('reemplazos.personal.update');

    Route::post('/reemplazos/padron/{reemplazoPersonal}/bloquear', [ReemplazosController::class, 'bloquearPersonal'])
        ->middleware('ensure.role:admin|funcionario_slep|coordinador_uatp|supervisor_plani|coordinador_plani')
        ->name('reemplazos.personal.bloquear');

    Route::delete('/reemplazos/padron/{reemplazoPersonal}/desbloquear', [ReemplazosController::class, 'desbloquearPersonal'])
        ->middleware('ensure.role:admin|funcionario_slep|coordinador_uatp|supervisor_plani|coordinador_plani')
        ->name('reemplazos.personal.desbloquear');

    // Carga masiva personal (módulo: reemplazos)
    Route::get('/reemplazos/personal/import', [PersonalImportController::class, 'create'])
        ->name('reemplazos.personal.import');

    Route::post('/reemplazos/personal/import', [PersonalImportController::class, 'store'])
        ->name('reemplazos.personal.import.store');

    // -------------------------
    // Operación: Incumplimiento Laboral
    // -------------------------
    Route::get('/incumplimiento-laboral', [IncumplimientoLaboralController::class, 'index'])
        ->name('incumplimientos.index');

    Route::get('/incumplimiento-laboral/create', [IncumplimientoLaboralController::class, 'create'])
        ->name('incumplimientos.create');

    Route::post('/incumplimiento-laboral', [IncumplimientoLaboralController::class, 'store'])
        ->name('incumplimientos.store');

    Route::get('/incumplimiento-laboral/export', [IncumplimientoLaboralController::class, 'export'])
        ->name('incumplimientos.export');

    Route::get('/incumplimiento-laboral/ajax/funcionarios', [IncumplimientoLaboralController::class, 'ajaxFuncionarios'])
        ->name('incumplimientos.ajax.funcionarios');

    Route::get('/incumplimiento-laboral/{incumplimientoLaboral}', [IncumplimientoLaboralController::class, 'show'])
        ->name('incumplimientos.show');

    Route::get('/incumplimiento-laboral/{incumplimientoLaboral}/constancia', [IncumplimientoLaboralController::class, 'constancia'])
        ->name('incumplimientos.constancia');

    Route::get('/incumplimiento-laboral/{incumplimientoLaboral}/edit', [IncumplimientoLaboralController::class, 'edit'])
        ->name('incumplimientos.edit');

    Route::put('/incumplimiento-laboral/{incumplimientoLaboral}', [IncumplimientoLaboralController::class, 'update'])
        ->name('incumplimientos.update');

    Route::delete('/incumplimiento-laboral/{incumplimientoLaboral}', [IncumplimientoLaboralController::class, 'destroy'])
        ->name('incumplimientos.destroy');

    // Buscador de postulantes (módulo: reemplazos)
    Route::get('/reemplazos/buscador-postulantes', [BuscadorPostulantesController::class, 'index'])
        ->name('reemplazos.buscador-postulantes.index');

    Route::get('/reemplazos/buscador-postulantes/{postulantProfile}', [BuscadorPostulantesController::class, 'show'])
        ->name('reemplazos.buscador-postulantes.show');

    Route::post('/reemplazos/buscador-postulantes/{postulantProfile}/vinculacion-laboral', [BuscadorPostulantesController::class, 'storeContratoLaboral'])
        ->middleware('ensure.role:admin|funcionario_slep')
        ->name('reemplazos.buscador-postulantes.contrato.store');

    Route::post('/reemplazos/buscador-postulantes/{postulantProfile}/trabajo-externo', [BuscadorPostulantesController::class, 'updateTrabajoExterno'])
        ->middleware('ensure.role:admin|funcionario_slep|coordinador_gdp')
        ->name('reemplazos.buscador-postulantes.trabajo-externo.update');

    Route::delete('/reemplazos/buscador-postulantes/{postulantProfile}/vinculacion-laboral/{contrato}', [BuscadorPostulantesController::class, 'destroyContratoLaboral'])
        ->middleware('ensure.role:admin|funcionario_slep')
        ->name('reemplazos.buscador-postulantes.contrato.destroy');

    // Documentos del postulante (solo lectura) desde Reemplazos/Buscador
    Route::get('/reemplazos/documentos/postulante/{user}', [\App\Http\Controllers\Admin\DocumentReviewController::class, 'forUserReadOnly'])
        ->name('reemplazos.documents.forUser');

    // Vista de revisión (detalle) del documento desde Reemplazos/Buscador
    Route::get('/reemplazos/documentos/{document}', [\App\Http\Controllers\Admin\DocumentReviewController::class, 'show'])
        ->name('reemplazos.documents.show');

    Route::get('/reemplazos/documentos/{document}/download', [\App\Http\Controllers\Admin\DocumentReviewController::class, 'downloadView'])
        ->name('reemplazos.documents.download');

    Route::get('/reemplazos/documentos/{document}/preview', [\App\Http\Controllers\Admin\DocumentReviewController::class, 'previewView'])
        ->name('reemplazos.documents.preview');

    
    Route::put('/reemplazos/documentos/{document}', [\App\Http\Controllers\Admin\DocumentReviewController::class, 'update'])
        ->name('reemplazos.documents.update');

    Route::get('/reemplazos/documentos/postulante/{user}/download-approved', [\App\Http\Controllers\Admin\DocumentReviewController::class, 'downloadApprovedZipView'])
        ->name('reemplazos.documents.downloadApproved');

    Route::get('/reemplazos/documentos/usuario/{user}/perfil.pdf', [\App\Http\Controllers\Admin\DocumentReviewController::class, 'exportProfilePdfView'])
        ->name('reemplazos.documents.user.profile.pdf');

    Route::get('/reemplazos/documentos/usuario/{user}/perfil/view', [\App\Http\Controllers\Admin\DocumentReviewController::class, 'exportProfileInlineView'])
        ->name('reemplazos.documents.user.profile.view');

    Route::get('/reemplazos/buscador-postulantes/{postulantProfile}/perfil/view', [BuscadorPostulantesController::class, 'perfilView'])
        ->name('reemplazos.buscador-postulantes.perfil.view');

    Route::get('/reemplazos/buscador-postulantes/{postulantProfile}/perfil.pdf', [BuscadorPostulantesController::class, 'perfilPdf'])
        ->name('reemplazos.buscador-postulantes.perfil.pdf');

    // -------------------------
    // Admin: Usuarios
    // -------------------------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::get('/users/export', [UserManagementController::class, 'export'])->name('users.export');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}', [UserManagementController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');

        Route::get('/postulaciones', [PostulacionAdminController::class, 'index'])->name('postulaciones.index');
        Route::get('/postulaciones/{postulacione}', [PostulacionAdminController::class, 'show'])->name('postulaciones.show');

        Route::get('/restricted-ruts', [RestrictedRutController::class, 'index'])->name('restricted-ruts.index');
        Route::get('/restricted-ruts/import', [RestrictedRutController::class, 'importForm'])->name('restricted-ruts.import');
        Route::get('/restricted-ruts/template', [RestrictedRutController::class, 'downloadTemplate'])->name('restricted-ruts.template');
        Route::post('/restricted-ruts/import', [RestrictedRutController::class, 'importStore'])->name('restricted-ruts.import.store');
        Route::get('/restricted-ruts/manual/create', [RestrictedRutController::class, 'manualCreate'])->name('restricted-ruts.manual.create');
        Route::post('/restricted-ruts/manual', [RestrictedRutController::class, 'manualStore'])->name('restricted-ruts.manual.store');
        Route::get('/restricted-ruts/{restrictedRut}', [RestrictedRutController::class, 'show'])->name('restricted-ruts.show');
        Route::get('/notification-logs', [NotificationDispatchLogController::class, 'index'])->name('notification-logs.index');
        Route::get('/notification-logs/{notificationLog}', [NotificationDispatchLogController::class, 'show'])->name('notification-logs.show');
        Route::get('/restricted-ruts/manual/{manualRecord}/edit', [RestrictedRutController::class, 'manualEdit'])->name('restricted-ruts.manual.edit');
        Route::put('/restricted-ruts/manual/{manualRecord}', [RestrictedRutController::class, 'manualUpdate'])->name('restricted-ruts.manual.update');
        Route::post('/restricted-ruts/manual/{manualRecord}/toggle', [RestrictedRutController::class, 'manualToggle'])->name('restricted-ruts.manual.toggle');
        Route::post('/restricted-ruts/court/{courtRecord}/toggle', [RestrictedRutController::class, 'courtToggle'])->name('restricted-ruts.court.toggle');

        Route::get('/permiso-sin-goce-excepciones', [PermisoSinGoceExcepcionController::class, 'index'])->name('permiso-sin-goce-excepciones.index');
        Route::get('/permiso-sin-goce-excepciones/create', [PermisoSinGoceExcepcionController::class, 'create'])->name('permiso-sin-goce-excepciones.create');
        Route::post('/permiso-sin-goce-excepciones', [PermisoSinGoceExcepcionController::class, 'store'])->name('permiso-sin-goce-excepciones.store');
        Route::get('/permiso-sin-goce-excepciones/{permisoSinGoceExcepcion}/edit', [PermisoSinGoceExcepcionController::class, 'edit'])->name('permiso-sin-goce-excepciones.edit');
        Route::put('/permiso-sin-goce-excepciones/{permisoSinGoceExcepcion}', [PermisoSinGoceExcepcionController::class, 'update'])->name('permiso-sin-goce-excepciones.update');
        Route::post('/permiso-sin-goce-excepciones/{permisoSinGoceExcepcion}/toggle', [PermisoSinGoceExcepcionController::class, 'toggle'])->name('permiso-sin-goce-excepciones.toggle');
    });

    // -------------------------
    // Admin: Catálogos
    // -------------------------
    Route::prefix('admin')->name('admin.')->group(function () {

        Route::prefix('admision-escolar')
            ->name('admision-escolar.')
            ->middleware([
                'ensure.role:admin|coordinador_uatp|comunicaciones',
                'ensure.active-role:admin|coordinador_uatp|comunicaciones',
            ])
            ->group(function () {
                Route::get('/', [AdmisionEscolarController::class, 'index'])->name('index');
                Route::get('/{establecimiento}/editar', [AdmisionEscolarController::class, 'edit'])
                    ->whereNumber('establecimiento')
                    ->name('edit');
                Route::put('/{establecimiento}', [AdmisionEscolarController::class, 'update'])
                    ->whereNumber('establecimiento')
                    ->name('update');
                Route::get('/{establecimiento}/previsualizar', [AdmisionEscolarController::class, 'preview'])
                    ->whereNumber('establecimiento')
                    ->name('preview');
                Route::post('/{establecimiento}/publicar', [AdmisionEscolarController::class, 'publish'])
                    ->whereNumber('establecimiento')
                    ->name('publish');
                Route::post('/{establecimiento}/despublicar', [AdmisionEscolarController::class, 'unpublish'])
                    ->whereNumber('establecimiento')
                    ->name('unpublish');
                Route::post('/{establecimiento}/galeria', [AdmisionEscolarGaleriaController::class, 'store'])
                    ->whereNumber('establecimiento')
                    ->name('gallery.store');
                Route::patch('/{establecimiento}/galeria/{imagen}', [AdmisionEscolarGaleriaController::class, 'update'])
                    ->whereNumber('establecimiento')
                    ->whereNumber('imagen')
                    ->name('gallery.update');
                Route::patch('/{establecimiento}/galeria/{imagen}/portada', [AdmisionEscolarGaleriaController::class, 'cover'])
                    ->whereNumber('establecimiento')
                    ->whereNumber('imagen')
                    ->name('gallery.cover');
                Route::delete('/{establecimiento}/galeria/{imagen}', [AdmisionEscolarGaleriaController::class, 'destroy'])
                    ->whereNumber('establecimiento')
                    ->whereNumber('imagen')
                    ->name('gallery.destroy');
            });

        Route::get('establecimientos/import', [EstablecimientoController::class, 'importForm'])
            ->name('establecimientos.import');
        Route::get('establecimientos/template', [EstablecimientoController::class, 'downloadTemplate'])
            ->name('establecimientos.template');
        Route::post('establecimientos/import', [EstablecimientoController::class, 'importStore'])
            ->name('establecimientos.import.store');

        Route::resource('establecimientos', EstablecimientoController::class)
            ->parameters(['establecimientos' => 'establecimiento']);

        Route::get('alumnos-prioritarios/import', [AlumnoPrioritarioPorcentajeController::class, 'importForm'])
            ->middleware('ensure.role:admin')
            ->name('alumnos-prioritarios.import');
        Route::get('alumnos-prioritarios/template', [AlumnoPrioritarioPorcentajeController::class, 'downloadTemplate'])
            ->middleware('ensure.role:admin')
            ->name('alumnos-prioritarios.template');
        Route::post('alumnos-prioritarios/import', [AlumnoPrioritarioPorcentajeController::class, 'importStore'])
            ->middleware('ensure.role:admin')
            ->name('alumnos-prioritarios.import.store');

        Route::resource('alumnos-prioritarios', AlumnoPrioritarioPorcentajeController::class)
            ->middleware('ensure.role:admin')
            ->parameters(['alumnos-prioritarios' => 'alumnos_prioritario']);

        Route::resource('cursos', CursoController::class)
            ->middleware('ensure.role:admin');


        Route::get('planes-estudio/import', [PlanEstudioController::class, 'importForm'])
            ->middleware('ensure.role:admin')
            ->name('planes-estudio.import');
        Route::get('planes-estudio/template', [PlanEstudioController::class, 'downloadTemplate'])
            ->middleware('ensure.role:admin')
            ->name('planes-estudio.template');
        Route::post('planes-estudio/import', [PlanEstudioController::class, 'importStore'])
            ->middleware('ensure.role:admin')
            ->name('planes-estudio.import.store');

        Route::resource('planes-estudio', PlanEstudioController::class)
            ->middleware('ensure.role:admin')
            ->parameters(['planes-estudio' => 'planes_estudio']);


        Route::get('asignaturas/import', [AsignaturaController::class, 'importForm'])
            ->middleware('ensure.role:admin')
            ->name('asignaturas.import');
        Route::get('asignaturas/template', [AsignaturaController::class, 'downloadTemplate'])
            ->middleware('ensure.role:admin')
            ->name('asignaturas.template');
        Route::post('asignaturas/import', [AsignaturaController::class, 'importStore'])
            ->middleware('ensure.role:admin')
            ->name('asignaturas.import.store');

        Route::resource('asignaturas', AsignaturaController::class)
            ->middleware('ensure.role:admin');

        Route::get('asignaturas-personalizadas', [AsignaturaPersonalizadaController::class, 'index'])
            ->middleware('ensure.role:admin,coordinador_gdp,coordinador_uatp')
            ->name('asignaturas-personalizadas.index');

        Route::get('establecimiento-cursos/import', [EstablecimientoCursoController::class, 'importForm'])
            ->middleware('ensure.role:admin')
            ->name('establecimiento-cursos.import');
        Route::get('establecimiento-cursos/template', [EstablecimientoCursoController::class, 'downloadTemplate'])
            ->middleware('ensure.role:admin')
            ->name('establecimiento-cursos.template');
        Route::post('establecimiento-cursos/import', [EstablecimientoCursoController::class, 'importStore'])
            ->middleware('ensure.role:admin')
            ->name('establecimiento-cursos.import.store');

        Route::resource('establecimiento-cursos', EstablecimientoCursoController::class)
            ->middleware('ensure.role:admin')
            ->parameters(['establecimiento-cursos' => 'establecimiento_curso']);

        Route::get('establecimiento-curso-pie/import', [EstablecimientoCursoPieController::class, 'importForm'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp')
            ->name('establecimiento-curso-pie.import');
        Route::get('establecimiento-curso-pie/template', [EstablecimientoCursoPieController::class, 'downloadTemplate'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp')
            ->name('establecimiento-curso-pie.template');
        Route::post('establecimiento-curso-pie/import', [EstablecimientoCursoPieController::class, 'importStore'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp')
            ->name('establecimiento-curso-pie.import.store');

        Route::resource('establecimiento-curso-pie', EstablecimientoCursoPieController::class)
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp|coordinador_gdp')
            ->parameters(['establecimiento-curso-pie' => 'establecimiento_curso_pie']);


        Route::get('dotacion-establecimiento', [DotacionEstablecimientoController::class, 'index'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp|coordinador_gdp')
            ->name('dotacion-establecimiento.index');
        Route::get('dotacion-establecimiento/{establecimiento}/pdf', [DotacionEstablecimientoController::class, 'pdf'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp|coordinador_gdp')
            ->whereNumber('establecimiento')
            ->name('dotacion-establecimiento.pdf');
        Route::post('dotacion-establecimiento/{establecimiento}/asignaciones', [DotacionAsignacionController::class, 'store'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp|coordinador_gdp')
            ->whereNumber('establecimiento')
            ->name('dotacion-establecimiento.asignaciones.store');
        Route::put('dotacion-establecimiento/{establecimiento}/asignaciones/{asignacion}', [DotacionAsignacionController::class, 'update'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp|coordinador_gdp')
            ->whereNumber('establecimiento')
            ->whereNumber('asignacion')
            ->name('dotacion-establecimiento.asignaciones.update');
        Route::delete('dotacion-establecimiento/{establecimiento}/asignaciones/{asignacion}', [DotacionAsignacionController::class, 'destroy'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp|coordinador_gdp')
            ->whereNumber('establecimiento')
            ->whereNumber('asignacion')
            ->name('dotacion-establecimiento.asignaciones.destroy');
        Route::post('dotacion-establecimiento/{establecimiento}/cursos-combinados', [DotacionCursoCombinadoController::class, 'store'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp|coordinador_gdp')
            ->whereNumber('establecimiento')
            ->name('dotacion-establecimiento.cursos-combinados.store');
        Route::put('dotacion-establecimiento/{establecimiento}/cursos-combinados/{cursoCombinado}', [DotacionCursoCombinadoController::class, 'update'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp|coordinador_gdp')
            ->whereNumber('establecimiento')
            ->whereNumber('cursoCombinado')
            ->name('dotacion-establecimiento.cursos-combinados.update');
        Route::delete('dotacion-establecimiento/{establecimiento}/cursos-combinados/{cursoCombinado}', [DotacionCursoCombinadoController::class, 'destroy'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp|coordinador_gdp')
            ->whereNumber('establecimiento')
            ->whereNumber('cursoCombinado')
            ->name('dotacion-establecimiento.cursos-combinados.destroy');

        Route::post('dotacion-establecimiento/{establecimiento}/proporcion-excepcion', [DotacionProporcionExcepcionController::class, 'store'])
            ->middleware('ensure.role:admin|coordinador_uatp')
            ->whereNumber('establecimiento')
            ->name('dotacion-establecimiento.proporcion-excepcion.store');
        Route::post('dotacion-establecimiento/{establecimiento}/proporcion-excepcion/recalcular', [DotacionProporcionExcepcionController::class, 'recalculate'])
            ->middleware('ensure.role:admin|coordinador_uatp')
            ->whereNumber('establecimiento')
            ->name('dotacion-establecimiento.proporcion-excepcion.recalculate');
        Route::delete('dotacion-establecimiento/{establecimiento}/proporcion-excepcion', [DotacionProporcionExcepcionController::class, 'destroy'])
            ->middleware('ensure.role:admin|coordinador_uatp')
            ->whereNumber('establecimiento')
            ->name('dotacion-establecimiento.proporcion-excepcion.destroy');
        Route::get('dotacion-establecimiento/{establecimiento}', [DotacionEstablecimientoController::class, 'show'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp|coordinador_gdp')
            ->whereNumber('establecimiento')
            ->name('dotacion-establecimiento.show');

        Route::get('dotacion-funciones', [DotacionFuncionesController::class, 'index'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp|coordinador_gdp')
            ->name('dotacion-funciones.index');
        Route::get('dotacion-funciones/{establecimiento}', [DotacionFuncionesController::class, 'show'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp|coordinador_gdp')
            ->name('dotacion-funciones.show');
        Route::post('dotacion-funciones/{establecimiento}/config', [DotacionFuncionesController::class, 'updateConfig'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp')
            ->name('dotacion-funciones.config');
        Route::post('dotacion-funciones/{establecimiento}/manual', [DotacionFuncionesController::class, 'storeManual'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp')
            ->name('dotacion-funciones.manual.store');
        Route::put('dotacion-funciones/{establecimiento}/manual/{funcion}', [DotacionFuncionesController::class, 'updateManual'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp')
            ->name('dotacion-funciones.manual.update');
        Route::delete('dotacion-funciones/{establecimiento}/manual/{funcion}', [DotacionFuncionesController::class, 'destroyManual'])
            ->middleware('ensure.role:admin|funcionario_directivo_estab|coordinador_uatp')
            ->name('dotacion-funciones.manual.destroy');
        Route::post('dotacion-funciones/{establecimiento}/manual/{funcion}/validar', [DotacionFuncionesController::class, 'validarManual'])
            ->middleware('ensure.role:admin|coordinador_uatp')
            ->name('dotacion-funciones.manual.validar');
        Route::post('dotacion-funciones/{establecimiento}/manual/{funcion}/observar', [DotacionFuncionesController::class, 'observarManual'])
            ->middleware('ensure.role:admin|coordinador_uatp')
            ->name('dotacion-funciones.manual.observar');


        Route::get('establecimiento-planes/{establecimiento_curso}/configurar', [EstablecimientoPlanEstudioController::class, 'configure'])
            ->middleware(['ensure.role:admin|funcionario_directivo_estab', \App\Http\Middleware\RestrictPlanesEeDirectivoEstablecimiento::class])
            ->name('establecimiento-planes.configure');

        Route::resource('establecimiento-planes', EstablecimientoPlanEstudioController::class)
            ->only(['index', 'show', 'edit', 'update', 'destroy'])
            ->middleware(['ensure.role:admin|funcionario_directivo_estab', \App\Http\Middleware\RestrictPlanesEeDirectivoEstablecimiento::class])
            ->parameters(['establecimiento-planes' => 'establecimiento_plan']);


        // Mantenedor: áreas bloqueadas por sobredotación (por establecimiento)
        Route::get('establecimientos/{establecimiento}/areas-desempeno-bloqueadas', [EstablecimientoAreaDesempenoController::class, 'edit'])
            ->name('establecimientos.areas-desempeno-bloqueadas.edit');

        Route::put('establecimientos/{establecimiento}/areas-desempeno-bloqueadas', [EstablecimientoAreaDesempenoController::class, 'update'])
            ->name('establecimientos.areas-desempeno-bloqueadas.update');

        Route::resource('areas-desempeno', AreaDesempenoController::class)
            ->except(['show']);

        Route::resource('aaee-valores-hora', AaeeValorHoraController::class)
            ->parameters(['aaee-valores-hora' => 'aaee_valor_hora'])
            ->except(['show']);



        Route::resource('cometidos-notificaciones', CometidoNotificacionConfiguracionController::class)
            ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
            ->parameters(['cometidos-notificaciones' => 'cometidos_notificacione'])
            ->only(['index', 'edit', 'update']);

        Route::resource('viaticos-disponibilidad', ViaticoDisponibilidadPresupuestariaController::class)
            ->middleware('ensure.role:admin|supervisor_plani|coordinador_plani')
            ->parameters(['viaticos-disponibilidad' => 'viaticos_disponibilidad'])
            ->except(['show']);

        Route::post('viaticos-reembolsos/activar-vigentes', [ViaticoReembolsoValorController::class, 'activarVigentes'])
            ->name('viaticos-reembolsos.activar-vigentes');

        Route::resource('viaticos-reembolsos', ViaticoReembolsoValorController::class)
            ->parameters(['viaticos-reembolsos' => 'viaticos_reembolso'])
            ->except(['show']);

        Route::post('funcionarios-viatico-anexo/{funcionarios_viatico_anexo}/toggle', [FuncionarioViaticoAnexoController::class, 'toggle'])
            ->middleware('ensure.role:admin|supervisor_plani|coordinador_plani')
            ->name('funcionarios-viatico-anexo.toggle');
        Route::resource('funcionarios-viatico-anexo', FuncionarioViaticoAnexoController::class)
            ->middleware('ensure.role:admin|supervisor_plani|coordinador_plani')
            ->parameters(['funcionarios-viatico-anexo' => 'funcionarios_viatico_anexo'])
            ->except(['show']);

        Route::resource('subsectores', SubsectorAdminController::class)
            ->parameters(['subsectores' => 'subsector']);

        Route::resource('menciones', MencionAdminController::class);

        Route::get('titulos-catalogo/import', [TituloCatalogoController::class, 'importForm'])->name('titulos-catalogo.import');
        Route::get('titulos-catalogo/template', [TituloCatalogoController::class, 'downloadTemplate'])->name('titulos-catalogo.template');
        Route::post('titulos-catalogo/import', [TituloCatalogoController::class, 'importStore'])->name('titulos-catalogo.import.store');
        Route::resource('titulos-catalogo', TituloCatalogoController::class)
            ->parameters(['titulos-catalogo' => 'titulos_catalogo']);

        Route::get('funciones-catalogo/import', [FuncionCatalogoController::class, 'importForm'])->name('funciones-catalogo.import');
        Route::get('funciones-catalogo/template', [FuncionCatalogoController::class, 'downloadTemplate'])->name('funciones-catalogo.template');
        Route::post('funciones-catalogo/import', [FuncionCatalogoController::class, 'importStore'])->name('funciones-catalogo.import.store');
        Route::resource('funciones-catalogo', FuncionCatalogoController::class)
            ->parameters(['funciones-catalogo' => 'funciones_catalogo']);

        Route::get('instituciones-catalogo/import', [InstitucionCatalogoController::class, 'importForm'])->name('instituciones-catalogo.import');
        Route::get('instituciones-catalogo/template', [InstitucionCatalogoController::class, 'downloadTemplate'])->name('instituciones-catalogo.template');
        Route::post('instituciones-catalogo/import', [InstitucionCatalogoController::class, 'importStore'])->name('instituciones-catalogo.import.store');
        Route::resource('instituciones-catalogo', InstitucionCatalogoController::class)
            ->parameters(['instituciones-catalogo' => 'instituciones_catalogo']);
    });

    // -------------------------
    // Admin: Revisión Documentos
    // -------------------------
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/documentos', [DocumentReviewController::class, 'index'])->name('documents.index');
        Route::get('/documentos/{document}', [DocumentReviewController::class, 'show'])->name('documents.show');
        Route::get('/documentos/{document}/download', [DocumentReviewController::class, 'download'])->name('documents.download');
        Route::put('/documentos/{document}', [DocumentReviewController::class, 'update'])->name('documents.update');

        Route::get('/documentos/{document}/preview', [DocumentReviewController::class, 'preview'])
            ->name('documents.preview');

        Route::get('/documentos/postulante/{user}', [DocumentReviewController::class, 'forUser'])
            ->name('documents.forUser');

        Route::get('/documentos/postulante/{user}/download-approved', [DocumentReviewController::class, 'downloadApprovedZip'])
            ->name('documents.downloadApproved');

        Route::get('/documentos/usuario/{user}/perfil.pdf', [DocumentReviewController::class, 'exportProfile'])
            ->name('documents.user.profile.pdf');

        Route::get('/documentos/usuario/{user}/perfil/view', [DocumentReviewController::class, 'exportProfileInline'])
            ->name('documents.user.profile.view');
    });

    // -------------------------
    // Postulante: Perfil + Documentos
    // -------------------------
    Route::get('/postulante/perfil', [PostulantProfileController::class, 'edit'])
        ->name('postulant.profile.edit');

    Route::put('/postulante/perfil/{user}', [PostulantProfileController::class, 'update'])
        ->name('postulant.profile.update');

    Route::get('/postulante/perfil.pdf', [PostulantProfileController::class, 'exportPdf'])
        ->name('postulant.profile.pdf');

    Route::get('/mis-documentos', [PostulantDocumentsController::class, 'index'])
        ->name('postulant.documents.index');

    Route::post('/mis-documentos/{type}', [PostulantDocumentsController::class, 'store'])
        ->name('postulant.documents.store');

    Route::get('/ofertas-laborales', [OfertaLaboralController::class, 'index'])
        ->middleware('ensure.role:postulante|funcionario')
        ->name('postulant.ofertas-laborales.index');

    Route::get('/ofertas-laborales/{oferta}/bases-pdf', [OfertaLaboralController::class, 'downloadBasesPdf'])
        ->middleware('ensure.role:postulante|funcionario')
        ->name('postulant.ofertas-laborales.bases');

    Route::post('/ofertas-laborales/{oferta}', [OfertaLaboralController::class, 'store'])
        ->middleware('ensure.role:postulante|funcionario')
        ->name('postulant.ofertas-laborales.store');

    Route::get('/mis-documentos/template/{type}', [PostulantDocumentsController::class, 'downloadTemplate'])
        ->name('postulant.documents.template');

    Route::get('/postulante/mis-reemplazos', [MisReemplazosController::class, 'index'])
        ->middleware('ensure.role:postulante|funcionario')
        ->name('postulant.reemplazos.index');

    Route::get('/postulante/mis-reemplazos/{solicitud}', [MisReemplazosController::class, 'show'])
        ->middleware('ensure.role:postulante|funcionario')
        ->name('postulant.reemplazos.show');

    Route::get('/postulante/mis-reemplazos/{solicitud}/orden-trabajo', [MisReemplazosController::class, 'ordenTrabajo'])
        ->middleware('ensure.role:postulante|funcionario')
        ->name('postulant.reemplazos.ot');

    Route::get('/postulante/mis-reemplazos/{solicitud}/contrato-firmado', [MisReemplazosController::class, 'contratoFirmado'])
        ->middleware('ensure.role:postulante|funcionario')
        ->name('postulant.reemplazos.contrato-firmado');

    Route::get('/postulante/mis-reemplazos/{solicitud}/horario-titular', [MisReemplazosController::class, 'horarioTitular'])
        ->middleware('ensure.role:postulante|funcionario')
        ->name('postulant.reemplazos.horario-titular');

    Route::get('/postulante/mis-finiquitos', [MisFiniquitosController::class, 'index'])
        ->middleware('ensure.role:postulante|funcionario')
        ->name('postulant.finiquitos.index');

    Route::get('/postulante/mis-finiquitos/{solicitud}/descargar', [MisFiniquitosController::class, 'descargar'])
        ->middleware('ensure.role:postulante|funcionario')
        ->name('postulant.finiquitos.descargar');


    // -------------------------
    // Trámites: Licencias Médicas
    // -------------------------
    Route::prefix('tramites/licencias-medicas')
        ->middleware('ensure.role:admin|funcionario_slep|coordinador_gdp')
        ->name('tramites.licencias-medicas.')
        ->group(function () {
            Route::get('/', [LicenciaMedicaController::class, 'index'])->name('index');
            Route::get('/crear', [LicenciaMedicaController::class, 'create'])->name('create');
            Route::post('/extraer-digital', [LicenciaMedicaController::class, 'extractDigital'])->name('extraer-digital');
            Route::post('/descartar-carga', [LicenciaMedicaController::class, 'descartarCarga'])->name('descartar-carga');
            Route::get('/importar-seguimiento', [LicenciaMedicaController::class, 'importarSeguimientoForm'])->name('importar-seguimiento');
            Route::post('/importar-seguimiento', [LicenciaMedicaController::class, 'importarSeguimiento'])->name('importar-seguimiento.store');
            Route::get('/feriados', [LicenciaFeriadoController::class, 'index'])->name('feriados.index');
            Route::post('/feriados', [LicenciaFeriadoController::class, 'store'])->name('feriados.store');
            Route::put('/feriados/{feriado}', [LicenciaFeriadoController::class, 'update'])->name('feriados.update');
            Route::delete('/feriados/{feriado}', [LicenciaFeriadoController::class, 'destroy'])->name('feriados.destroy');
            Route::post('/', [LicenciaMedicaController::class, 'store'])->name('store');
            Route::get('/{licenciaMedica}', [LicenciaMedicaController::class, 'show'])->name('show');
            Route::post('/{licenciaMedica}/recalcular-dias', [LicenciaMedicaController::class, 'recalcularDias'])->name('recalcular-dias');
            Route::get('/{licenciaMedica}/archivo', [LicenciaMedicaController::class, 'descargarArchivo'])->name('archivo');
        });


    // -------------------------
    // Gestión interna: Agendamiento Proyector y Salas de Reuniones
    // -------------------------
    Route::prefix('gestion/agendamientos-recursos')
        ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep|funcionario_ac|secretaria_direccion_ejecutiva')
        ->name('gestion.agendamientos-recursos.')
        ->group(function () {
            Route::get('/', [AgendamientoRecursoController::class, 'index'])->name('index');
            Route::get('/crear', [AgendamientoRecursoController::class, 'create'])->name('create');
            Route::post('/', [AgendamientoRecursoController::class, 'store'])->name('store');

            Route::get('/recursos', [AgendamientoRecursoController::class, 'recursosIndex'])->name('recursos.index');
            Route::get('/recursos/crear', [AgendamientoRecursoController::class, 'recursosCreate'])->name('recursos.create');
            Route::post('/recursos', [AgendamientoRecursoController::class, 'recursosStore'])->name('recursos.store');
            Route::get('/recursos/{recurso}/editar', [AgendamientoRecursoController::class, 'recursosEdit'])->name('recursos.edit');
            Route::put('/recursos/{recurso}', [AgendamientoRecursoController::class, 'recursosUpdate'])->name('recursos.update');

            Route::get('/{agendamiento}', [AgendamientoRecursoController::class, 'show'])->name('show');
            Route::get('/{agendamiento}/editar', [AgendamientoRecursoController::class, 'edit'])->name('edit');
            Route::put('/{agendamiento}', [AgendamientoRecursoController::class, 'update'])->name('update');
            Route::post('/{agendamiento}/aprobar', [AgendamientoRecursoController::class, 'aprobar'])->name('aprobar');
            Route::post('/{agendamiento}/rechazar', [AgendamientoRecursoController::class, 'rechazar'])->name('rechazar');
            Route::post('/{agendamiento}/anular', [AgendamientoRecursoController::class, 'anular'])->name('anular');
        });

    // -------------------------
    // Trámites: Cometido Funcionario
    // -------------------------
    Route::prefix('tramites/cometidos-funcionarios')
        ->middleware('ensure.role:funcionario_estab|funcionario_ac|coordinador_uatp|admin|funcionario_slep|coordinador_gdp|supervisor_plani|coordinador_plani|funcionario_daf|funcionario_daf_compra|director_ejecutivo|juridica|juridico|abogado_juridica|coordinador_juridica|funcionario_juridica')
        ->name('tramites.cometidos-funcionarios.')
        ->group(function () {
            Route::get('/', [CometidoFuncionarioController::class, 'index'])->name('index');
            Route::get('/seguimiento/exportar-excel', [CometidoFuncionarioController::class, 'exportarSeguimientoExcel'])
                ->middleware('ensure.role:coordinador_uatp|admin|funcionario_slep|coordinador_gdp|supervisor_plani|coordinador_plani|funcionario_daf|juridica|juridico|abogado_juridica|coordinador_juridica|funcionario_juridica')
                ->name('seguimiento.exportar-excel');
            Route::get('/seguimiento/documentos-zip', [CometidoFuncionarioController::class, 'descargarDocumentosSeguimientoZip'])
                ->middleware('ensure.role:coordinador_uatp|admin|funcionario_slep|coordinador_gdp|supervisor_plani|coordinador_plani|funcionario_daf|juridica|juridico|abogado_juridica|coordinador_juridica|funcionario_juridica')
                ->name('seguimiento.documentos-zip');
            Route::get('/crear', [CometidoFuncionarioController::class, 'create'])->middleware('ensure.role:funcionario_estab|funcionario_ac')->name('create');
            Route::post('/', [CometidoFuncionarioController::class, 'store'])->middleware('ensure.role:funcionario_estab|funcionario_ac')->name('store');
            Route::get('/plantilla/formulario-cometido', [CometidoFuncionarioController::class, 'descargarPlantillaFormulario'])->name('plantilla-formulario');
            Route::get('/{cometido}/editar', [CometidoFuncionarioController::class, 'edit'])->middleware('ensure.role:funcionario_estab|funcionario_ac')->name('edit');
            Route::put('/{cometido}', [CometidoFuncionarioController::class, 'update'])->middleware('ensure.role:funcionario_estab|funcionario_ac')->name('update');
            Route::delete('/{cometido}', [CometidoFuncionarioController::class, 'destroy'])->middleware('ensure.role:funcionario_estab|funcionario_ac')->name('destroy');
            Route::get('/{cometido}/documentos/{documento}/ver', [CometidoFuncionarioController::class, 'verDocumento'])->name('documentos.ver');
            Route::get('/{cometido}/documentos-generados/{documento}/ver', [CometidoFuncionarioController::class, 'verDocumentoGenerado'])->name('documentos-generados.ver');
            Route::post('/{cometido}/documentos-generados/regenerar-solicitud', [CometidoFuncionarioController::class, 'regenerarSolicitudCometidoPdf'])->middleware('ensure.role:funcionario_ac|admin')->name('documentos-generados.regenerar-solicitud');
            Route::patch('/{cometido}/jefatura-ac/aprobar', [CometidoFuncionarioController::class, 'aprobarJefaturaAc'])->middleware('ensure.role:funcionario_ac|director_ejecutivo|admin')->name('jefatura-ac.aprobar');
            Route::patch('/{cometido}/jefatura-ac/observar', [CometidoFuncionarioController::class, 'observarJefaturaAc'])->middleware('ensure.role:funcionario_ac|director_ejecutivo|admin')->name('jefatura-ac.observar');
            Route::patch('/{cometido}/jefatura-ac/rechazar', [CometidoFuncionarioController::class, 'rechazarJefaturaAc'])->middleware('ensure.role:funcionario_ac|director_ejecutivo|admin')->name('jefatura-ac.rechazar');
            Route::post('/{cometido}/pasaje-aereo/reserva', [CometidoFuncionarioController::class, 'cargarReservaPasaje'])->middleware('ensure.role:funcionario_daf_compra|admin')->name('pasaje.reserva');
            Route::post('/{cometido}/pasaje-aereo/cdp', [CometidoFuncionarioController::class, 'cargarCdpPasaje'])->middleware('ensure.role:supervisor_plani|coordinador_plani|admin')->name('pasaje.cdp');
            Route::post('/{cometido}/pasaje-aereo/compra', [CometidoFuncionarioController::class, 'cargarCompraPasaje'])->middleware('ensure.role:funcionario_daf_compra|admin')->name('pasaje.compra');
            Route::get('/{cometido}/pasaje-aereo/boleto', [CometidoFuncionarioController::class, 'verBoletoPasaje'])->name('pasaje.boleto');
            Route::patch('/{cometido}/uatp/aprobar', [CometidoFuncionarioController::class, 'aprobarUatp'])->middleware('ensure.role:coordinador_uatp|admin')->name('uatp.aprobar');
            Route::patch('/{cometido}/uatp/observar', [CometidoFuncionarioController::class, 'observarUatp'])->middleware('ensure.role:coordinador_uatp|admin')->name('uatp.observar');
            Route::patch('/{cometido}/uatp/rechazar', [CometidoFuncionarioController::class, 'rechazarUatp'])->middleware('ensure.role:coordinador_uatp|admin')->name('uatp.rechazar');
            Route::patch('/{cometido}/cdp/aprobar', [CometidoFuncionarioController::class, 'aprobarCdp'])->middleware('ensure.role:supervisor_plani|coordinador_plani|admin')->name('cdp.aprobar');
            Route::patch('/{cometido}/cdp/rechazar', [CometidoFuncionarioController::class, 'rechazarCdp'])->middleware('ensure.role:supervisor_plani|coordinador_plani|admin')->name('cdp.rechazar');
            Route::patch('/{cometido}/director-sin-disponibilidad/aprobar-reconversion', [CometidoFuncionarioController::class, 'aprobarReconversionDirector'])->middleware('ensure.role:director_ejecutivo|admin')->name('director-sin-disponibilidad.aprobar-reconversion');
            Route::patch('/{cometido}/director-sin-disponibilidad/rechazar', [CometidoFuncionarioController::class, 'rechazarReconversionDirector'])->middleware('ensure.role:director_ejecutivo|admin')->name('director-sin-disponibilidad.rechazar');
            Route::patch('/{cometido}/gdp/resolucion', [CometidoFuncionarioController::class, 'emitirResolucionGdp'])->middleware('ensure.role:coordinador_gdp|funcionario_slep|admin')->name('gdp.resolucion');
            Route::get('/{cometido}/informe-cometido', [CometidoFuncionarioInformeController::class, 'create'])->middleware('ensure.role:funcionario_estab|funcionario_ac|admin')->name('informe.create');
            Route::post('/{cometido}/informe-cometido', [CometidoFuncionarioInformeController::class, 'store'])->middleware('ensure.role:funcionario_estab|funcionario_ac|admin')->name('informe.store');
            Route::patch('/{cometido}/informe-cometido/jefatura/aprobar', [CometidoFuncionarioInformeController::class, 'aprobarJefatura'])->middleware('ensure.role:funcionario_estab|funcionario_ac|admin')->name('informe.jefatura.aprobar');
            Route::patch('/{cometido}/informe-cometido/jefatura/observar', [CometidoFuncionarioInformeController::class, 'observarJefatura'])->middleware('ensure.role:funcionario_estab|funcionario_ac|admin')->name('informe.jefatura.observar');
            Route::patch('/{cometido}/informe-cometido/jefatura/rechazar', [CometidoFuncionarioInformeController::class, 'rechazarJefatura'])->middleware('ensure.role:funcionario_estab|funcionario_ac|admin')->name('informe.jefatura.rechazar');
            Route::patch('/{cometido}/daf/contabilidad-viatico', [CometidoFuncionarioController::class, 'registrarContabilidadViatico'])->middleware('ensure.role:funcionario_daf|admin')->name('daf.contabilidad-viatico');
            Route::patch('/{cometido}/daf/pago-viatico', [CometidoFuncionarioController::class, 'registrarPagoViatico'])->middleware('ensure.role:funcionario_daf|admin')->name('daf.pago-viatico');
            Route::patch('/{cometido}/cerrar', [CometidoFuncionarioController::class, 'cerrar'])->middleware('ensure.role:coordinador_gdp|funcionario_slep|funcionario_daf|admin')->name('cerrar');

            // Rendición, Jurídica y pago de reembolso
            Route::get('/{cometido}/rendicion-reembolso', [CometidoFuncionarioRendicionController::class, 'panel'])
                ->whereNumber('cometido')
                ->name('rendicion.panel');
            Route::get('/{cometido}/rendicion-reembolso/{rendicion}/documentos/{index}/ver', [CometidoFuncionarioRendicionController::class, 'verDocumentoRespaldo'])
                ->whereNumber(['cometido', 'rendicion', 'index'])
                ->name('rendicion.documentos.ver');
            Route::get('/{cometido}/rendicion-reembolso/{rendicion}/documentos/{index}/descargar', [CometidoFuncionarioRendicionController::class, 'descargarDocumentoRespaldo'])
                ->whereNumber(['cometido', 'rendicion', 'index'])
                ->name('rendicion.documentos.descargar');
            Route::post('/{cometido}/rendicion-reembolso/enviar', [CometidoFuncionarioRendicionController::class, 'enviarRendicion'])
                ->middleware('ensure.role:funcionario_estab|funcionario_ac|admin')
                ->whereNumber('cometido')
                ->name('rendicion.enviar');
            Route::post('/{cometido}/rendicion-reembolso/rectificar', [CometidoFuncionarioRendicionController::class, 'rectificarRendicion'])
                ->middleware('ensure.role:funcionario_estab|funcionario_ac|admin')
                ->whereNumber('cometido')
                ->name('rendicion.rectificar');
            Route::patch('/{cometido}/rendicion-reembolso/daf/observar', [CometidoFuncionarioRendicionController::class, 'observarDaf'])
                ->middleware('ensure.role:funcionario_daf|admin')
                ->whereNumber('cometido')
                ->name('rendicion.daf.observar');
            Route::patch('/{cometido}/rendicion-reembolso/daf/autorizar', [CometidoFuncionarioRendicionController::class, 'autorizarDaf'])
                ->middleware('ensure.role:funcionario_daf|admin')
                ->whereNumber('cometido')
                ->name('rendicion.daf.autorizar');
            Route::patch('/{cometido}/rendicion-reembolso/daf/rechazar', [CometidoFuncionarioRendicionController::class, 'rechazarDaf'])
                ->middleware('ensure.role:funcionario_daf|admin')
                ->whereNumber('cometido')
                ->name('rendicion.daf.rechazar');
            Route::patch('/{cometido}/rendicion-reembolso/cdp/observar', [CometidoFuncionarioRendicionController::class, 'observarCdp'])
                ->middleware('ensure.role:supervisor_plani|coordinador_plani|admin')
                ->whereNumber('cometido')
                ->name('rendicion.cdp.observar');
            Route::patch('/{cometido}/rendicion-reembolso/cdp/autorizar', [CometidoFuncionarioRendicionController::class, 'autorizarCdp'])
                ->middleware('ensure.role:supervisor_plani|coordinador_plani|admin')
                ->whereNumber('cometido')
                ->name('rendicion.cdp.autorizar');
            Route::patch('/{cometido}/rendicion-reembolso/cdp/rechazar', [CometidoFuncionarioRendicionController::class, 'rechazarCdp'])
                ->middleware('ensure.role:supervisor_plani|coordinador_plani|admin')
                ->whereNumber('cometido')
                ->name('rendicion.cdp.rechazar');
            Route::patch('/{cometido}/rendicion-reembolso/juridica/observar', [CometidoFuncionarioRendicionController::class, 'observarJuridica'])
                ->middleware('ensure.role:juridica|juridico|abogado_juridica|coordinador_juridica|funcionario_juridica|admin')
                ->whereNumber('cometido')
                ->name('juridica.observar');
            Route::patch('/{cometido}/rendicion-reembolso/juridica/emitir-resolucion', [CometidoFuncionarioRendicionController::class, 'emitirResolucion'])
                ->middleware('ensure.role:juridica|juridico|abogado_juridica|coordinador_juridica|funcionario_juridica|admin')
                ->whereNumber('cometido')
                ->name('juridica.emitir-resolucion');
            Route::patch('/{cometido}/rendicion-reembolso/daf/contabilidad', [CometidoFuncionarioRendicionController::class, 'registrarContabilidad'])->middleware('ensure.role:funcionario_daf|admin')->whereNumber('cometido')->name('rendicion.daf.contabilidad');
            Route::patch('/{cometido}/rendicion-reembolso/pago/registrar', [CometidoFuncionarioRendicionController::class, 'registrarPago'])
                ->middleware('ensure.role:funcionario_daf|coordinador_gdp|admin')
                ->whereNumber('cometido')
                ->name('pago.registrar');
            Route::patch('/{cometido}/rendicion-reembolso/cerrar', [CometidoFuncionarioRendicionController::class, 'cerrar'])
                ->middleware('ensure.role:coordinador_gdp|funcionario_daf|admin')
                ->whereNumber('cometido')
                ->name('cierre.cerrar');

            Route::get('/ajax/funcionarios/{reemplazoPersonal}/detalle', [CometidoFuncionarioController::class, 'funcionarioDetalle'])->middleware('ensure.role:funcionario_estab')->name('ajax.funcionario.detalle');
            Route::get('/{cometido}', [CometidoFuncionarioController::class, 'show'])->whereNumber('cometido')->name('show');
        });

    // -------------------------
    // Trámites: Mis Cargas Familiares
    // -------------------------
    Route::prefix('tramites/cargas-familiares')->middleware('ensure.role:postulante|funcionario|funcionario_ac|coordinador_gdp|funcionario_slep|admin')->name('tramites.cargas-familiares.')->group(function () {
        Route::get('/', [CargaFamiliarController::class, 'index'])->middleware('ensure.role:postulante|funcionario|funcionario_ac')->name('index');
        Route::get('/crear', [CargaFamiliarController::class, 'create'])->middleware('ensure.role:postulante|funcionario|funcionario_ac')->name('create');
        Route::post('/', [CargaFamiliarController::class, 'store'])->middleware('ensure.role:postulante|funcionario|funcionario_ac')->name('store');

        Route::get('/administracion', [CargaFamiliarController::class, 'adminIndex'])->middleware('ensure.role:admin|funcionario_slep|coordinador_gdp')->name('admin.index');
        Route::get('/administracion/cargas/{cargaFamiliar}', [CargaFamiliarController::class, 'adminCargaShow'])->middleware('ensure.role:admin|funcionario_slep|coordinador_gdp')->name('admin.cargas.show');
        Route::get('/administracion/funcionarios-ac', [FuncionarioAcController::class, 'adminImportForm'])->middleware('ensure.role:admin|funcionario_slep|coordinador_gdp')->name('admin.funcionarios-ac.import');
        Route::post('/administracion/funcionarios-ac', [FuncionarioAcController::class, 'adminImportStore'])->middleware('ensure.role:admin|funcionario_slep|coordinador_gdp')->name('admin.funcionarios-ac.import.store');
        Route::get('/administracion/funcionarios-ac/plantilla', [FuncionarioAcController::class, 'adminDownloadTemplate'])->middleware('ensure.role:admin|funcionario_slep|coordinador_gdp')->name('admin.funcionarios-ac.template');
        Route::get('/administracion/funcionarios-ac/exportar', [FuncionarioAcController::class, 'adminExport'])->middleware('ensure.role:admin|funcionario_slep|coordinador_gdp')->name('admin.funcionarios-ac.export');
        Route::get('/administracion/funcionarios-ac/crear', [FuncionarioAcAutorizadoController::class, 'create'])->middleware('ensure.role:admin|funcionario_slep|coordinador_gdp')->name('admin.funcionarios-ac.create');
        Route::post('/administracion/funcionarios-ac/manual', [FuncionarioAcAutorizadoController::class, 'store'])->middleware('ensure.role:admin|funcionario_slep|coordinador_gdp')->name('admin.funcionarios-ac.store');
        Route::get('/administracion/funcionarios-ac/jefaturas', [FuncionarioAcJefaturaDependenciaController::class, 'index'])->middleware('ensure.role:admin')->name('admin.funcionarios-ac.jefaturas.index');
        Route::patch('/administracion/funcionarios-ac/jefaturas/{jefaturaDependencia}', [FuncionarioAcJefaturaDependenciaController::class, 'update'])->middleware('ensure.role:admin')->whereNumber('jefaturaDependencia')->name('admin.funcionarios-ac.jefaturas.update');
        Route::get('/administracion/funcionarios-ac/{funcionarioAc}/editar', [FuncionarioAcAutorizadoController::class, 'edit'])->middleware('ensure.role:admin|funcionario_slep|coordinador_gdp')->whereNumber('funcionarioAc')->name('admin.funcionarios-ac.edit');
        Route::patch('/administracion/funcionarios-ac/{funcionarioAc}', [FuncionarioAcAutorizadoController::class, 'update'])->middleware('ensure.role:admin|funcionario_slep|coordinador_gdp')->whereNumber('funcionarioAc')->name('admin.funcionarios-ac.update');
        Route::get('/carga-masiva', [CargaFamiliarController::class, 'importForm'])->middleware('ensure.role:admin|funcionario_slep')->name('import');
        Route::post('/carga-masiva', [CargaFamiliarController::class, 'importStore'])->middleware('ensure.role:admin|funcionario_slep')->name('import.store');
        Route::get('/carga-masiva/plantilla', [CargaFamiliarController::class, 'downloadTemplate'])->middleware('ensure.role:admin|funcionario_slep')->name('template');
        Route::get('/plantillas-documentos/{template}', [CargaFamiliarController::class, 'downloadDocumentTemplate'])->name('document-template');

        Route::get('/revision', [CargaFamiliarController::class, 'reviewIndex'])->middleware('ensure.role:coordinador_gdp|funcionario_slep|admin')->name('review.index');
        Route::get('/revision/{solicitud}', [CargaFamiliarController::class, 'show'])->middleware('ensure.role:coordinador_gdp|funcionario_slep|admin')->name('review.show');
        Route::patch('/revision/{solicitud}/resolver', [CargaFamiliarController::class, 'resolve'])->middleware('ensure.role:coordinador_gdp|funcionario_slep|admin')->name('review.resolve');
        Route::patch('/revision/{solicitud}/documentos/{documento}', [CargaFamiliarController::class, 'reviewDocumento'])->middleware('ensure.role:coordinador_gdp|funcionario_slep|admin')->name('documentos.review');

        Route::get('/{solicitud}/documentos/{documento}', [CargaFamiliarController::class, 'downloadDocumento'])->name('documentos.download');
        Route::get('/{solicitud}/documentos/{documento}/ver', [CargaFamiliarController::class, 'viewDocumento'])->name('documentos.view');
        Route::get('/{solicitud}', [CargaFamiliarController::class, 'show'])->name('show');
    });

    // -------------------------
    // Trámites: bandeja y revisión
    // -------------------------
    Route::prefix('tramites')->middleware('ensure.role:postulante|funcionario|coordinador_gdp|funcionario_slep|admin')->name('tramites.')->group(function () {
        Route::get('/', [TramiteController::class, 'index'])->name('index');
        Route::get('/export/review-excel', [TramiteController::class, 'exportReviewExcel'])->name('export.review-excel');
    });

    Route::prefix('tramites')->middleware('ensure.role:postulante|funcionario')->name('tramites.')->group(function () {
        Route::get('/crear', [TramiteController::class, 'create'])->name('create');
        Route::post('/', [TramiteController::class, 'store'])->name('store');
        Route::get('/plantilla/{tipo}', [TramiteController::class, 'downloadTemplate'])->name('template.download');
        Route::get('/{tramite}/editar', [TramiteController::class, 'edit'])->name('edit');
        Route::put('/{tramite}', [TramiteController::class, 'update'])->name('update');
        Route::patch('/{tramite}/anular', [TramiteController::class, 'anular'])->name('anular');
    });

    Route::prefix('tramites')->middleware('ensure.role:coordinador_gdp|funcionario_slep|admin')->name('tramites.')->group(function () {
        Route::get('/{tramite}/documentos-aprobados/download', [TramiteController::class, 'downloadApprovedZip'])->name('documentos.downloadApproved');
        Route::patch('/{tramite}/documentos/{documento}/revision', [TramiteController::class, 'reviewDocumento'])->name('documentos.review');
        Route::post('/{tramite}/documentos/{documento}/captura', [TramiteController::class, 'captureDocumento'])->name('documentos.capture');
        Route::post('/{tramite}/documentos/{documento}/captura/fechas-manuales', [TramiteController::class, 'updateCaptureManualDates'])->name('documentos.capture.manual-dates');
        Route::post('/{tramite}/documentos/{documento}/captura/periodo-manual', [TramiteController::class, 'storeManualCapturePeriod'])->name('documentos.capture.manual-period');
        Route::post('/{tramite}/documentos/{documento}/captura/confirmar-periodos', [TramiteController::class, 'confirmCapturePeriods'])->name('documentos.capture.confirm-periods');
        Route::post('/{tramite}/calculo-periodos/manual', [TramiteController::class, 'storeManualCalculationPeriod'])->name('calculo-periodos.manual.store');
        Route::delete('/{tramite}/calculo-periodos/{blockIndex}/{periodIndex}', [TramiteController::class, 'deleteCalculationPeriod'])
            ->whereNumber('blockIndex')
            ->whereNumber('periodIndex')
            ->name('calculo-periodos.periodo.destroy');
        Route::post('/{tramite}/calculo-periodos/documento', [TramiteController::class, 'uploadResolucionPdf'])->name('calculo-periodos.documento.store');
        Route::post('/{tramite}/calculo-periodos/generar-rex', [TramiteController::class, 'generateRex'])->name('calculo-periodos.generar-rex');
        Route::post('/{tramite}/calculo-periodos/generar-rex', [TramiteController::class, 'generateRex'])->name('rex.generate');
        Route::post('/{tramite}/resolucion/upload-pdf', [TramiteController::class, 'uploadResolucionPdf'])->name('resolucion.upload-pdf');
        Route::post('/{tramite}/resolucion/enviar-resultado', [TramiteController::class, 'sendResultado'])->name('resolucion.enviar-resultado');

        // Alias de compatibilidad para vistas del flujo de Bienios que invocan nombres historicos.
        Route::post('/{tramite}/bienios/informar-cierre', [TramiteController::class, 'sendResultado'])->name('bienios.informar-cierre');
        Route::post('/{tramite}/bienios/generar-rex', [TramiteController::class, 'generateRex'])->name('bienios.generar-rex');
        Route::post('/{tramite}/bienios/resolucion/upload-pdf', [TramiteController::class, 'uploadResolucionPdf'])->name('bienios.resolucion.upload-pdf');
        Route::post('/{tramite}/bienios/documento', [TramiteController::class, 'uploadResolucionPdf'])->name('bienios.documento.store');

        Route::post('/{tramite}/notificar-solicitante', [TramiteController::class, 'notifyApplicant'])->name('notify-applicant');
        Route::post('/{tramite}/anular-gestion', [TramiteController::class, 'anularGestion'])->name('anular.gestion');
    });

    Route::prefix('tramites')->middleware('ensure.role:postulante|funcionario|coordinador_gdp|funcionario_slep|admin')->name('tramites.')->group(function () {
        Route::get('/{tramite}/documentos/{documento}', [TramiteController::class, 'downloadDocumento'])->name('documentos.download');
        Route::get('/{tramite}/documentos/{documento}/ver', [TramiteController::class, 'viewDocumento'])->name('documentos.view');
        Route::get('/{tramite}/resolucion/docx', [TramiteController::class, 'downloadRexDocx'])->name('resolucion.download-docx');
        Route::get('/{tramite}/resolucion/pdf', [TramiteController::class, 'downloadResolucionPdf'])->name('resolucion.download-pdf');
        Route::get('/{tramite}/bienios/resolucion/docx', [TramiteController::class, 'downloadRexDocx'])->name('bienios.resolucion.download-docx');
        Route::get('/{tramite}/bienios/resolucion/pdf', [TramiteController::class, 'downloadResolucionPdf'])->name('bienios.resolucion.download-pdf');
        Route::get('/{tramite}', [TramiteController::class, 'show'])->name('show');
    });

    // ✅ IMPORTANTE: renombrado para que caiga dentro del módulo postulant.profile
    Route::get('/ajax/areas-desempeno', [PostulantProfileController::class, 'ajaxAreasDesempeno'])
        ->name('postulant.profile.ajax.areas-desempeno');

    // -------------------------
    // Funcionario Establecimiento: Solicitudes de reemplazo
    // -------------------------
    Route::prefix('funcionario')->name('funcionario.')->group(function () {

        Route::get('/solicitudes-reemplazo', [SolicitudReemplazoController::class, 'index'])
            ->name('solicitudes-reemplazo.index');

        Route::get('/solicitudes-reemplazo/crear', [SolicitudReemplazoController::class, 'create'])
            ->name('solicitudes-reemplazo.create');

        Route::post('/solicitudes-reemplazo', [SolicitudReemplazoController::class, 'store'])
            ->name('solicitudes-reemplazo.store');
        Route::get('/funcionario/ajax/funcionarios/{reemplazoPersonal}/detalle', [SolicitudReemplazoController::class, 'ajaxFuncionarioDetalle'])
            ->name('funcionario.solicitudes-reemplazo.ajax.funcionario.detalle');

        // ✅ IMPORTANTE: renombrados para que caigan dentro del módulo funcionario.solicitudes-reemplazo
        Route::get('/ajax/funcionarios', [SolicitudReemplazoController::class, 'ajaxFuncionarios'])
            ->name('solicitudes-reemplazo.ajax.funcionarios');

        Route::get('/ajax/funcionarios/{reemplazoPersonal}/detalle', [SolicitudReemplazoController::class, 'ajaxFuncionarioDetalle'])
            ->name('solicitudes-reemplazo.ajax.funcionario.detalle');

        Route::get('/ajax/postulantes', [SolicitudReemplazoController::class, 'ajaxPostulantes'])
            ->name('solicitudes-reemplazo.ajax.postulantes');
        Route::get('/ajax/areas-desempeno', [SolicitudReemplazoController::class, 'ajaxAreasDesempeno'])
            ->name('solicitudes-reemplazo.ajax.areas-desempeno');

        Route::get('/ajax/regla-minima-reemplazo', [SolicitudReemplazoController::class, 'ajaxReglaMinima'])
            ->name('solicitudes-reemplazo.ajax.regla-minima');

        Route::get(
            '/solicitudes-reemplazo/postulantes/{postulantProfile}/perfil/view',
            [SolicitudReemplazoController::class, 'postulantePerfilView']
        )->name('solicitudes-reemplazo.postulante.perfil.view');

        Route::get(
            '/solicitudes-reemplazo/postulantes/{postulantProfile}/perfil.pdf',
            [SolicitudReemplazoController::class, 'postulantePerfilPdf']
        )->name('solicitudes-reemplazo.postulante.perfil.pdf');

        Route::get(
            '/solicitudes-reemplazo/postulantes/{postulantProfile}/cv/view',
            [SolicitudReemplazoController::class, 'postulanteCvView']
        )->name('solicitudes-reemplazo.postulante.cv.view');
        Route::get('/solicitudes-reemplazo/{solicitud}/editar', [SolicitudReemplazoController::class, 'edit'])
            ->name('solicitudes-reemplazo.edit');

        Route::put('/solicitudes-reemplazo/{solicitud}', [SolicitudReemplazoController::class, 'update'])
            ->name('solicitudes-reemplazo.update');
    });

    // -------------------------
    // Operación: Endeudamiento
    // -------------------------
    Route::get('/endeudamiento/cargas', [MaeCargaController::class, 'index'])
        ->name('endeudamiento.cargas.index');

    Route::get('/endeudamiento/cargas/create', [MaeCargaController::class, 'create'])
        ->name('endeudamiento.cargas.create');

    Route::post('/endeudamiento/cargas', [MaeCargaController::class, 'store'])
        ->name('endeudamiento.cargas.store');

    Route::get('/endeudamiento/cargas/{maeCarga}', [MaeCargaController::class, 'show'])
        ->name('endeudamiento.cargas.show');

    Route::post('/endeudamiento/cargas/{maeCarga}/activar', [MaeCargaController::class, 'activarVersion'])
        ->name('endeudamiento.cargas.activar');

    Route::get('/endeudamiento/cuotas', [MaeCuotasController::class, 'index'])
        ->name('endeudamiento.cuotas.index');

    Route::get('/endeudamiento/cuotas/create', [MaeCuotasController::class, 'create'])
        ->name('endeudamiento.cuotas.create');

    Route::get('/endeudamiento/cuotas/plantilla', [MaeCuotasController::class, 'plantilla'])
        ->name('endeudamiento.cuotas.plantilla');

    Route::post('/endeudamiento/cuotas', [MaeCuotasController::class, 'store'])
        ->name('endeudamiento.cuotas.store');

    Route::get('/endeudamiento/cuotas/{maeCuotasImportacion}', [MaeCuotasController::class, 'show'])
        ->name('endeudamiento.cuotas.show');

    Route::get('/endeudamiento/registros', [MaeRegistroController::class, 'index'])
        ->name('endeudamiento.registros.index');

    Route::get('/endeudamiento/registros/{maeRegistro}', [MaeRegistroController::class, 'show'])
        ->name('endeudamiento.registros.show');

    Route::get('/endeudamiento/topes', [MaeTopesController::class, 'index'])
        ->name('endeudamiento.topes.index');

    Route::get('/endeudamiento/topes/export', [MaeTopesController::class, 'export'])
        ->name('endeudamiento.topes.export');

    Route::get('/endeudamiento/topes/{maeRegistro}', [MaeTopesController::class, 'show'])
        ->name('endeudamiento.topes.show');

    Route::get('/endeudamiento/topes/{maeRegistro}/pdf', [MaeTopesController::class, 'exportPdf'])
        ->name('endeudamiento.topes.export-pdf');

    Route::get('/endeudamiento/topes-normativos', [MaeNormativaController::class, 'index'])
        ->name('endeudamiento.normativa.index');

    Route::put('/endeudamiento/topes-normativos/{homologacion}', [MaeNormativaController::class, 'update'])
        ->name('endeudamiento.normativa.update');


    // -------------------------
    // Remuneraciones: Liquidaciones de sueldo
    // -------------------------
    Route::prefix('liquidaciones')->name('liquidaciones.')->middleware('ensure.role:admin|funcionario_slep')->group(function () {
        Route::get('/cargas', [LiquidacionCargaController::class, 'index'])->name('cargas.index');
        Route::get('/cargas/create', [LiquidacionCargaController::class, 'create'])->name('cargas.create');
        Route::post('/cargas', [LiquidacionCargaController::class, 'store'])->name('cargas.store');
        Route::post('/cargas/paquete', [LiquidacionCargaController::class, 'storePaquete'])->name('cargas.paquete.store');
        Route::get('/cargas/{liquidacionCarga}', [LiquidacionCargaController::class, 'show'])->name('cargas.show');
        Route::get('/liquidaciones/{liquidacion}/descargar', [LiquidacionCargaController::class, 'descargar'])->name('cargas.liquidaciones.descargar');
    });

    Route::prefix('mis-liquidaciones')->name('liquidaciones.mis.')->middleware('ensure.role:postulante|funcionario')->group(function () {
        Route::get('/', [MisLiquidacionesController::class, 'index'])->name('index');
        Route::get('/{liquidacion}/ver', [MisLiquidacionesController::class, 'ver'])->name('ver');
        Route::get('/{liquidacion}/descargar', [MisLiquidacionesController::class, 'descargar'])->name('descargar');
    });

    // -------------------------
    // Mensajes (módulo: messages)
    // -------------------------
    Route::prefix('mensajes')->name('messages.')->group(function () {
        Route::get('/', [MessagingController::class, 'index'])->name('index');
        Route::get('/unread-summary', [MessagingController::class, 'unreadSummary'])->name('unread-summary');
        Route::get('/search-users', [MessagingController::class, 'searchUsers'])->name('search-users');
        Route::post('/start', [MessagingController::class, 'start'])->name('start');
        Route::get('{conversation}/attachments/{attachment}', [MessagingController::class, 'attachment'])->name('attachments.show');
        Route::get('{conversation}', [MessagingController::class, 'show'])->name('show');
        Route::get('{conversation}/poll', [MessagingController::class, 'poll'])->name('poll');
        Route::post('{conversation}/send', [MessagingController::class, 'send'])->name('send');
    });
    // -------------------------
    // GESTION: REEMPLAZOS
    // -------------------------
    Route::prefix('gestion')->name('gestion.')->group(function () {

        Route::get('/solicitudes-reemplazo', [SolicitudReemplazoGestionController::class, 'index'])
            ->middleware('ensure.role:admin|coordinador_uatp|coordinador_gdp|funcionario_slep|supervisor_plani')
            ->name('solicitudes-reemplazo.index');

        Route::get('/solicitudes-reemplazo/exportar', [SolicitudReemplazoGestionController::class, 'exportar'])
            ->middleware('ensure.role:admin|coordinador_gdp')
            ->name('solicitudes-reemplazo.exportar');


        Route::get('/estadisticas', [EstadisticasController::class, 'index'])
            ->middleware('ensure.role:admin|coordinador_uatp|coordinador_gdp')
            ->name('estadisticas.index');

        Route::get('/informes', [InformesController::class, 'index'])
            ->middleware('ensure.role:admin|coordinador_uatp|coordinador_gdp')
            ->name('informes.index');

        Route::get('/informes/export', [InformesController::class, 'export'])
            ->middleware('ensure.role:admin|coordinador_uatp|coordinador_gdp')
            ->name('informes.export');

        Route::get('bolsa-trabajo/{bolsa_trabajo}/bases-pdf', [BolsaTrabajoController::class, 'downloadBasesPdf'])
            ->middleware('ensure.role:admin|funcionario_slep')
            ->name('bolsa-trabajo.bases');

        Route::get('bolsa-trabajo/{bolsa_trabajo}/documentos-aprobados-zip', [BolsaTrabajoController::class, 'downloadApprovedDocumentsZip'])
            ->middleware('ensure.role:admin|funcionario_slep')
            ->name('bolsa-trabajo.documentos-aprobados-zip');

        Route::get('bolsa-trabajo/{bolsa_trabajo}/cvs-zip', [BolsaTrabajoController::class, 'downloadCurriculumZip'])
            ->middleware('ensure.role:admin|funcionario_slep')
            ->name('bolsa-trabajo.cvs-zip');

        Route::patch('bolsa-trabajo/{bolsa_trabajo}/etapa', [BolsaTrabajoController::class, 'updateEtapa'])
            ->middleware('ensure.role:admin|funcionario_slep')
            ->name('bolsa-trabajo.update-etapa');

        Route::resource('bolsa-trabajo', BolsaTrabajoController::class)
            ->middleware('ensure.role:admin|funcionario_slep')
            ->parameters(['bolsa-trabajo' => 'bolsa_trabajo']);

        // UATP: aprobar / rechazar
        Route::post('/solicitudes-reemplazo/{solicitud}/uatp/aprobar', [SolicitudReemplazoGestionController::class, 'uatpAprobar'])
            ->middleware('ensure.role:admin|coordinador_uatp')
            ->name('solicitudes-reemplazo.uatp.aprobar');

        Route::post('/solicitudes-reemplazo/{solicitud}/uatp/rechazar', [SolicitudReemplazoGestionController::class, 'uatpRechazar'])
            ->middleware('ensure.role:admin|coordinador_uatp')
            ->name('solicitudes-reemplazo.uatp.rechazar');

        Route::post('/solicitudes-reemplazo/{solicitud}/uatp/reabrir', [SolicitudReemplazoGestionController::class, 'uatpReabrir'])
            ->middleware('ensure.role:admin|funcionario_slep|supervisor_plani|coordinador_gdp')
            ->name('solicitudes-reemplazo.uatp.reabrir');

        Route::post('/solicitudes-reemplazo/{solicitud}/plani/validar', [SolicitudReemplazoGestionController::class, 'planiValidar'])
            ->middleware('ensure.role:admin|supervisor_plani')
            ->name('solicitudes-reemplazo.plani.validar');

        Route::post('/solicitudes-reemplazo/{solicitud}/plani/rechazar', [SolicitudReemplazoGestionController::class, 'planiRechazar'])
            ->middleware('ensure.role:admin|supervisor_plani')
            ->name('solicitudes-reemplazo.plani.rechazar');

        Route::post('/solicitudes-reemplazo/{solicitud}/plani/reabrir', [SolicitudReemplazoGestionController::class, 'planiReabrir'])
            ->middleware('ensure.role:admin|funcionario_slep|supervisor_plani|coordinador_gdp')
            ->name('solicitudes-reemplazo.plani.reabrir');

        Route::post('/solicitudes-reemplazo/{solicitud}/ajuste-reemplazo', [SolicitudReemplazoGestionController::class, 'actualizarJornadaReemplazo'])
            ->middleware('ensure.role:admin|coordinador_uatp|supervisor_plani')
            ->name('solicitudes-reemplazo.ajuste-reemplazo.update');

        // GDP: derivar masivo
        Route::post('/solicitudes-reemplazo/gdp/derivar', [SolicitudReemplazoGestionController::class, 'gdpDerivar'])
            ->middleware('ensure.role:admin|coordinador_gdp')
            ->name('solicitudes-reemplazo.gdp.derivar');
        // GDP: reasignar derivación (estado derivada_slep)
        Route::post('/solicitudes-reemplazo/gdp/reasignar/{solicitud}', [SolicitudReemplazoGestionController::class, 'gdpReasignar'])
            ->middleware('ensure.role:admin|coordinador_gdp')
            ->name('solicitudes-reemplazo.gdp.reasignar');
        // Funcionario SLEP: bandeja de finiquitos de reemplazos terminados
        Route::get('/solicitudes-reemplazo/finiquitos', [SolicitudReemplazoGestionController::class, 'finiquitos'])
            ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
            ->name('solicitudes-reemplazo.finiquitos.index');

        Route::get('/solicitudes-reemplazo/finiquitos/exportar-excel', [SolicitudReemplazoGestionController::class, 'exportarFiniquitosExcel'])
            ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
            ->name('solicitudes-reemplazo.finiquitos.exportar-excel');

        Route::post('/solicitudes-reemplazo/finiquitos/{solicitud}/generar-pdf', [SolicitudReemplazoGestionController::class, 'generarFiniquitoPdf'])
            ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
            ->name('solicitudes-reemplazo.finiquitos.generar-pdf');

        Route::get('/solicitudes-reemplazo/finiquitos/{solicitud}/descargar-pdf', [SolicitudReemplazoGestionController::class, 'descargarFiniquitoPdf'])
            ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
            ->name('solicitudes-reemplazo.finiquitos.descargar-pdf');

        Route::post('/solicitudes-reemplazo/finiquitos/{solicitud}/cargar-firmado', [SolicitudReemplazoGestionController::class, 'cargarFiniquitoFirmado'])
            ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
            ->name('solicitudes-reemplazo.finiquitos.cargar-firmado');

        Route::get('/solicitudes-reemplazo/finiquitos/{solicitud}/descargar-firmado', [SolicitudReemplazoGestionController::class, 'descargarFiniquitoFirmado'])
            ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
            ->name('solicitudes-reemplazo.finiquitos.descargar-firmado');

        Route::delete('/solicitudes-reemplazo/finiquitos/{solicitud}/eliminar-firmado', [SolicitudReemplazoGestionController::class, 'eliminarFiniquitoFirmado'])
            ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
            ->name('solicitudes-reemplazo.finiquitos.eliminar-firmado');

        // ver solicitud
        Route::get('/solicitudes-reemplazo/{solicitud}', [SolicitudReemplazoGestionController::class, 'show'])
            ->middleware('ensure.role:admin|coordinador_uatp|coordinador_gdp|funcionario_slep|supervisor_plani|funcionario_estab')
            ->name('solicitudes-reemplazo.show');

        // AJAX: buscar postulantes para Orden de Trabajo (mismo comportamiento que funcionario_estab)
        Route::get('/solicitudes-reemplazo/{solicitud}/ajax/postulantes', [SolicitudReemplazoGestionController::class, 'ajaxPostulantesOt'])
            ->middleware('ensure.role:admin|coordinador_uatp|coordinador_gdp|funcionario_slep')
            ->name('solicitudes-reemplazo.ajax.postulantes');

        // Acciones SLEP (solo estado derivada_slep): anular / reasignar postulante
        Route::post('/solicitudes-reemplazo/{solicitud}/anular', [SolicitudReemplazoGestionController::class, 'slepAnular'])
            ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
            ->name('solicitudes-reemplazo.slep.anular');

        Route::post('/solicitudes-reemplazo/{solicitud}/cerrar-docente', [SolicitudReemplazoGestionController::class, 'cerrarSolicitudDocente'])
            ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
            ->name('solicitudes-reemplazo.slep.cerrar-docente');

        Route::post('/solicitudes-reemplazo/{solicitud}/retornar-derivada-slep', [SolicitudReemplazoGestionController::class, 'retornarDerivadaSlep'])
            ->middleware('ensure.role:admin|coordinador_gdp')
            ->name('solicitudes-reemplazo.slep.retornar-derivada');

        Route::post('/solicitudes-reemplazo/{solicitud}/reasignar-postulante', [SolicitudReemplazoGestionController::class, 'slepReasignarPostulante'])
            ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
            ->name('solicitudes-reemplazo.slep.reasignar-postulante');

        Route::post('/solicitudes-reemplazo/{solicitud}/observacion', [SolicitudReemplazoGestionController::class, 'informarObservacion'])
            ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
            ->name('solicitudes-reemplazo.observacion.store');

        // Funcionario SLEP: crear orden de trabajo
        Route::post('/solicitudes-reemplazo/{solicitud}/orden-trabajo', [SolicitudReemplazoGestionController::class, 'slepCrearOrdenTrabajo'])
            ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
            ->name('solicitudes-reemplazo.orden-trabajo.store');

        // Contrato de trabajo (AAEE): generar / subir / descargar (previo a crear OT)
        Route::post('/solicitudes-reemplazo/{solicitud}/contrato-trabajo/generar', [SolicitudReemplazoGestionController::class, 'slepGenerarContratoTrabajo'])
            ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
            ->name('solicitudes-reemplazo.contrato-trabajo.generar');

        Route::post('/solicitudes-reemplazo/{solicitud}/contrato-trabajo/upload', [SolicitudReemplazoGestionController::class, 'slepSubirContratoTrabajo'])
            ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
            ->name('solicitudes-reemplazo.contrato-trabajo.upload');

        Route::get('/solicitudes-reemplazo/{solicitud}/contrato-trabajo/download', [SolicitudReemplazoGestionController::class, 'downloadContratoTrabajo'])
            ->middleware('ensure.role:admin|coordinador_uatp|coordinador_gdp|funcionario_slep|supervisor_plani|funcionario_estab')
            ->name('solicitudes-reemplazo.contrato-trabajo.download');

        Route::post('/solicitudes-reemplazo/{solicitud}/contrato-trabajo-firmado/enviar', [SolicitudReemplazoGestionController::class, 'slepEnviarContratoFirmado'])
            ->middleware('ensure.role:admin|coordinador_gdp|funcionario_slep')
            ->name('solicitudes-reemplazo.contrato-trabajo-firmado.enviar');

        Route::get('/solicitudes-reemplazo/{solicitud}/contrato-trabajo-firmado/download', [SolicitudReemplazoGestionController::class, 'downloadContratoFirmado'])
            ->middleware('ensure.role:admin|coordinador_uatp|coordinador_gdp|funcionario_slep|supervisor_plani|funcionario_estab')
            ->name('solicitudes-reemplazo.contrato-trabajo-firmado.download');

        // editar solicitud de reemplazo
        Route::get('/solicitudes-reemplazo/{solicitud}/editar', [SolicitudReemplazoController::class, 'edit'])
            ->name('solicitudes-reemplazo.edit');
        // actualizar solicitu de reemplazo
        Route::put('/solicitudes-reemplazo/{solicitud}', [SolicitudReemplazoController::class, 'update'])
            ->name('solicitudes-reemplazo.update');
        // ver documentos de la solicitud de reemplazo Oficio y Respaldo
        Route::get('/solicitudes-reemplazo/{solicitud}/oficio', [SolicitudReemplazoGestionController::class, 'oficioPdf'])
            ->middleware('ensure.role:admin|coordinador_uatp|coordinador_gdp|funcionario_slep|supervisor_plani|funcionario_estab')
            ->name('solicitudes-reemplazo.oficio');

        Route::get('/solicitudes-reemplazo/{solicitud}/respaldo', [SolicitudReemplazoGestionController::class, 'respaldoPdf'])
            ->middleware('ensure.role:admin|coordinador_uatp|coordinador_gdp|funcionario_slep|supervisor_plani|funcionario_estab')
            ->name('solicitudes-reemplazo.respaldo');

        Route::get('/solicitudes-reemplazo/{solicitud}/horario-titular', [SolicitudReemplazoGestionController::class, 'horarioTitularPdf'])
            ->middleware('ensure.role:admin|coordinador_uatp|coordinador_gdp|funcionario_slep|supervisor_plani|funcionario_estab')
            ->name('solicitudes-reemplazo.horario-titular');

        // Ver Orden de Trabajo (PDF) - inline
        Route::get('/solicitudes-reemplazo/{solicitud}/orden-trabajo', [OrdenTrabajoPdfController::class, 'show'])
            ->middleware('ensure.role:admin|coordinador_uatp|coordinador_gdp|funcionario_slep|funcionario_estab')
            ->name('solicitudes-reemplazo.ot');

        // Descargar Orden de Trabajo (PDF)
        Route::get('/solicitudes-reemplazo/{solicitud}/orden-trabajo/download', [OrdenTrabajoPdfController::class, 'download'])
            ->middleware('ensure.role:admin|coordinador_uatp|coordinador_gdp|funcionario_slep|funcionario_estab')
            ->name('solicitudes-reemplazo.ot.download');
    });
});

Route::middleware(['auth', 'ensure.role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('roles', \App\Http\Controllers\Admin\RoleController::class);
    });

// SÓLO PARA SABER SI EL CACHE SE PIERDE
/* Route::get('/__diag', function () {
    return response()->json([
        'base_path'     => base_path(),
        'app_env'       => config('app.env'),
        'app_key_set'   => !empty(config('app.key')),
        'app_key_start' => substr((string) config('app.key'), 0, 12),
        'config_cached' => app()->configurationIsCached(),
    ]);
}); */

// -------------------------
// Endpoints auxiliares (no UI)
// -------------------------
Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::get('/api/menciones', [MencionAdminController::class, 'search'])
        ->name('api.menciones.search');
});

Route::fallback(function () {
    return response()->view('errors.404', [], 404);
});
