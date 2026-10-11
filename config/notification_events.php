<?php

// Registrar cada evento nuevo aquí: docs/NOTIFICACIONES_ADMINISTRACION.md.
return [
    'notifications' => [
        App\Notifications\Auth\CustomVerifyEmail::class => ['auth.email_verification', 'Acceso', 'Verificación de correo', 'Cuenta que solicita acceso', true],
        App\Notifications\Auth\CustomResetPassword::class => ['auth.password_reset', 'Acceso', 'Recuperación de contraseña', 'Cuenta que solicita acceso', true],
        App\Notifications\Auth\WelcomeUser::class => ['auth.welcome', 'Acceso', 'Bienvenida (sin invocaciones actuales)', 'Cuenta registrada', false],
    ],
    // Eventos sin clase propia (vistas directas o emisores con sga_event_key).
    // [clave => [módulo, nombre, destinatarios]]; aparecen sin insertar filas en la BD.
    'events' => [],
    'non_mail_notifications' => [App\Notifications\ReemplazoWhatsAppNotification::class],
    'mailables' => [
        App\Mail\SolicitudReemplazoCreada::class => ['solicitud_reemplazo.created', 'Reemplazos', 'Nueva solicitud', 'Contacto del establecimiento'],
        App\Mail\SolicitudReemplazoAprobadaUatpPendientePlani::class => ['solicitud_reemplazo.aprobada_uatp_pendiente_plani', 'Reemplazos', 'Aprobación UATP', 'Contacto del establecimiento'],
        App\Mail\SolicitudReemplazoValidadaPlani::class => ['solicitud_reemplazo.validada_plani', 'Reemplazos', 'Validación de Planificación', 'Contacto del establecimiento'],
        App\Mail\SolicitudReemplazoRechazadaUatp::class => ['solicitud_reemplazo.rechazada_uatp', 'Reemplazos', 'Rechazo UATP', 'Contacto del establecimiento'],
        App\Mail\SolicitudReemplazoRechazadaPlani::class => ['solicitud_reemplazo.rechazada_plani', 'Reemplazos', 'Rechazo de Planificación', 'Contacto del establecimiento'],
        App\Mail\SolicitudReemplazoObservacionSlep::class => ['solicitud_reemplazo.observacion_slep', 'Reemplazos', 'Observación SLEP', 'Contacto del establecimiento'],
        App\Mail\OrdenTrabajoCreada::class => ['solicitud_reemplazo.orden_trabajo_creada', 'Reemplazos', 'OT creada: establecimiento', 'Contacto del establecimiento'],
        App\Mail\OrdenTrabajoGenerada::class => ['solicitud_reemplazo.orden_trabajo_notificada_reemplazante', 'Reemplazos', 'OT generada: reemplazante', 'Reemplazante definitivo y destinatarios del flujo'],
        App\Mail\OrdenTrabajoCorreoInstitucionalSoporteTi::class => ['solicitud_reemplazo.orden_trabajo_soporte_ti_correo_institucional', 'Reemplazos', 'OT: correo institucional', 'Soporte TI'],
        App\Mail\ContratoTrabajoFirmadoEnviado::class => ['solicitud_reemplazo.contrato_trabajo_firmado_enviado', 'Reemplazos', 'Contrato firmado', 'Destinatarios seleccionados en el trámite'],
        App\Mail\ResolucionDocenteFirmadaEnviada::class => ['solicitud_reemplazo.resolucion_docente_firmada_enviada', 'Reemplazos', 'Resolución docente firmada', 'Destinatarios seleccionados en el trámite'],
        App\Mail\SolicitudAutorizacionDocenteMail::class => ['solicitud_reemplazo.autorizacion_docente.solicitada', 'Reemplazos', 'Autorización docente', 'Correo autorizado en la solicitud'],
        App\Mail\DeudaPensionAlimentosRemuneracionesMail::class => ['solicitud_reemplazo.deuda_pension.enviada_remuneraciones', 'Reemplazos', 'Deuda de pensión: remuneraciones', 'Encargado de remuneraciones del flujo'],
        App\Mail\DocumentStatusChangedMail::class => ['document.review.status_changed', 'Documentos', 'Resultado de revisión de documento', 'Propietario del documento'],
        App\Mail\TramiteDocumentoStatusChangedMail::class => ['tramite.documento.review.status_changed', 'Trámites y bienios', 'Resultado de revisión de documento de trámite', 'Solicitante del trámite'],
        App\Mail\TramiteBieniosSolicitudRecibidaMail::class => ['tramite.bienios.solicitud_recibida', 'Trámites y bienios', 'Solicitud de bienios recibida', 'Solicitante del trámite'],
        App\Mail\TramiteResultadoBieniosMail::class => ['tramite.bienios.resultado_enviado', 'Trámites y bienios', 'Resultado de bienios', 'Destinatarios seleccionados en el trámite'],
        App\Mail\TramiteCierreBieniosInformadoMail::class => ['tramite.bienios.cierre_informado', 'Trámites y bienios', 'Cierre de bienios', 'Solicitante del trámite'],
        App\Mail\TramiteManualApplicantNotificationMail::class => ['tramite.manual_applicant_notification', 'Trámites y bienios', 'Aviso manual al solicitante', 'Solicitante del trámite'],
        App\Mail\TramiteAnuladoMail::class => ['tramite.anulado', 'Trámites y bienios', 'Trámite anulado', 'Solicitante del trámite'],
        App\Mail\BolsaTrabajoDifusionRequestMail::class => ['gestion.bolsa-trabajo.difusion', 'Bolsa de Trabajo', 'Solicitud de difusión (creación o actualización)', 'Comunicaciones'],
        App\Mail\NewChatMessageMail::class => ['messages.new_message', 'Mensajería', 'Nuevo mensaje interno', 'Participantes de la conversación, excepto el autor'],
        App\Mail\BulkRoleMail::class => ['admin.bulk_role_mail', 'Administración', 'Correo masivo por rol', 'Usuarios verificados de los roles seleccionados'],
        App\Mail\InternalNotification::class => ['system.internal', 'Sistema', 'Aviso interno genérico (sin invocaciones actuales)', 'Destinatario indicado por el emisor'],
    ],
    'whatsapp' => [
        'solicitud_reemplazo.created' => ['Nueva solicitud', ['coordinador_uatp']],
        'solicitud_reemplazo.aprobada_uatp_pendiente_plani' => ['Aprobación UATP', ['supervisor_plani']],
        'solicitud_reemplazo.validada_plani' => ['Validación de Planificación', ['coordinador_gdp']],
        'solicitud_reemplazo.derivada_slep' => ['Asignación SLEP', ['funcionario_slep', 'coordinador_gdp']],
        'solicitud_reemplazo.reasignada_slep' => ['Reasignación SLEP', ['funcionario_slep', 'coordinador_gdp']],
        'solicitud_reemplazo.reabierta_uatp' => ['Reapertura UATP', ['coordinador_uatp']],
        'solicitud_reemplazo.reabierta_plani' => ['Reapertura de Planificación', ['supervisor_plani']],
        'solicitud_reemplazo.corregida_uatp' => ['Corrección para UATP', ['coordinador_uatp']],
        'solicitud_reemplazo.corregida_plani' => ['Corrección para Planificación', ['supervisor_plani']],
        'solicitud_reemplazo.aceptada_reemplazante' => ['Aceptación: OT y ubicación al reemplazante', []],
        'admin.test' => ['Prueba manual de WhatsApp', []],
    ],
];
