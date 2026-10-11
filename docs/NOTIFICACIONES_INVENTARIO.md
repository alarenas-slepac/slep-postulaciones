# Inventario de eventos de notificaciones

Revisión de código: 2026-10-11. Catálogo de `NotificationEventCatalog`, sin consultar destinatarios ni datos reales.

90 entradas, 12 módulos, 29 clases Mailable, 38 reglas propias de Cometidos y 88 eventos con WhatsApp. Los 11 existentes (diez automáticos de reemplazos y una prueba manual) conservan sus reglas; los 77 nuevos, derivados de correo, comienzan desactivados y requieren un destinatario original con cuenta verificada y teléfono válido. Una entrada no implica un disparador activo. Los avisos sin invocaciones actuales se indican en el nombre.

Fuentes revisadas por mecanismo:

- `app/Mail`, `app/Notifications`, `app/Models/User.php` y controladores de Auth: mailables, verificación, recuperación y bienvenida.
- `app/Support/NotificationAudit.php`, jobs de mensajes/correos masivos y `app/Exceptions/Handler.php`: auditoría, mensajería, selección por roles y alertas de fallos.
- Controladores de reemplazos, `TramiteController`, revisión de documentos, Bolsa de Trabajo, servicios de autorización docente y deuda de pensión: eventos y destinatarios calculados por el flujo.
- `CometidoNotificacionConfiguracion` y los tres controladores de Cometidos: solicitud, autorización, pasajes, informe, rendición, CDP, Jurídica, contabilidad, pagos y cierre. Incluye asuntos alternativos y el cierre cuyo asunto agrega el nombre del funcionario.
- `AgendamientoRecursoController`: los tres envíos directos de vistas y sus cinco variantes de evento.
- `TicketService`, `DescuentoCgrWorkflowService`, `EscalarTicketsIncidencia` y `routes/console.php`: asignación/escalamiento y etapas de remuneraciones, incluyendo la ejecución programada.
- `services/whatsapp`, notifiers, observer, dispatcher y worker: QR, mensajes por etapa, OT, prueba y deduplicación. Se conserva su transporte; el nuevo control está en Laravel.

La búsqueda global de emisores no identifica otros transportes activos por correo/WhatsApp en los restantes módulos. `SmsSender` existe como servicio Vonage, sin invocaciones encontradas; SMS queda fuera de los dos canales solicitados. Los avisos y broadcasts dentro del chat continúan como parte de la conversación; se configuran sus avisos externos. Los eventos de correo agregan WhatsApp por autorización del operador; no se copian cuerpos ni adjuntos y los avisos de acceso permanecen protegidos sólo por correo.

Configuración, reglas de extensión, archivos y despliegue: [NOTIFICACIONES_ADMINISTRACION.md](NOTIFICACIONES_ADMINISTRACION.md).

| Clave | Módulo | Evento | Canales |
| --- | --- | --- | --- |
| auth.welcome | Acceso | Bienvenida (sin invocaciones actuales) | mail, whatsapp (WhatsApp nuevo: desactivado) |
| auth.password_reset | Acceso | Recuperación de contraseña | mail (protegido) |
| auth.email_verification | Acceso | Verificación de correo | mail (protegido) |
| admin.bulk_role_mail | Administración | Correo masivo por rol | mail, whatsapp (WhatsApp nuevo: desactivado) |
| admin.test | Administración | Prueba manual de WhatsApp | whatsapp |
| agendamientos.edicion_horario | Agendamientos | Cambio de horario | mail, whatsapp (WhatsApp nuevo: desactivado) |
| agendamientos.anulacion | Agendamientos | Sala liberada por anulación | mail, whatsapp (WhatsApp nuevo: desactivado) |
| agendamientos.aprobada | Agendamientos | Solicitud aprobada | mail, whatsapp (WhatsApp nuevo: desactivado) |
| agendamientos.pendiente | Agendamientos | Solicitud pendiente | mail, whatsapp (WhatsApp nuevo: desactivado) |
| agendamientos.rechazada | Agendamientos | Solicitud rechazada | mail, whatsapp (WhatsApp nuevo: desactivado) |
| gestion.bolsa-trabajo.etapas.avance | Bolsa de Trabajo | Avanza de etapa | mail, whatsapp (WhatsApp nuevo: desactivado) |
| gestion.bolsa-trabajo.etapas.no_avanza | Bolsa de Trabajo | No avanza de etapa | mail, whatsapp (WhatsApp nuevo: desactivado) |
| gestion.bolsa-trabajo.etapas.cerrado | Bolsa de Trabajo | Proceso cerrado | mail, whatsapp (WhatsApp nuevo: desactivado) |
| gestion.bolsa-trabajo.etapas.desierto | Bolsa de Trabajo | Proceso desierto | mail, whatsapp (WhatsApp nuevo: desactivado) |
| gestion.bolsa-trabajo.difusion | Bolsa de Trabajo | Solicitud de difusión (creación o actualización) | mail, whatsapp (WhatsApp nuevo: desactivado) |
| centro_operaciones.asignacion | Centro de Operaciones | Ticket asignado o reasignado | mail, whatsapp (WhatsApp nuevo: desactivado) |
| centro_operaciones.escalamiento | Centro de Operaciones | Ticket vencido: escalamiento | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.solicitud_ac_pendiente_jefatura | Cometidos | AC - nueva solicitud pendiente de autorización | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.solicitud_ac_actualizada_jefatura | Cometidos | AC - solicitud actualizada para autorización | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.daf_rendicion_rex_emitida | Cometidos | DAF - REX CGR emitida y rendición disponible | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.daf_contable_reembolso | Cometidos | DAF - compromiso y devengo de reembolso | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.daf_contable_viatico | Cometidos | DAF - compromiso y devengo de viático | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.daf_pago_reembolso | Cometidos | DAF - pago de reembolso habilitado | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.daf_pago_viatico | Cometidos | DAF - pago de viático habilitado | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.daf_rendicion_lista_informe | Cometidos | DAF - rendición e informe listos | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.daf_compra_cdp_pasaje_emitido | Cometidos | DAF Compra - CDP pasaje emitido | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.daf_compra_reserva_pasaje_ac | Cometidos | DAF Compra - reserva pasaje aéreo | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.director_ejecutivo_decision_sin_disponibilidad | Cometidos | Director Ejecutivo - decisión por falta de disponibilidad | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.establecimiento_cierre_cometido | Cometidos | Establecimiento - cierre de cometido | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.funcionario_rex_rendicion_habilitada | Cometidos | Funcionario - REX CGR emitida y rendición habilitada | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.funcionario_boleto_aereo_disponible | Cometidos | Funcionario - boleto aéreo disponible | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.funcionario_cometido_autorizado | Cometidos | Funcionario - cometido autorizado por jefatura | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.funcionario_rechazo_sin_disponibilidad | Cometidos | Funcionario - cometido rechazado por falta de disponibilidad | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.funcionario_reconversion_reembolso | Cometidos | Funcionario - cometido reconvertido a reembolso | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.funcionario_informe_pendiente | Cometidos | Funcionario - debe completar informe | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.funcionario_informe_aprobado | Cometidos | Funcionario - informe aprobado | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.funcionario_informe_observado | Cometidos | Funcionario - informe observado | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.funcionario_informe_rechazado | Cometidos | Funcionario - informe rechazado | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.funcionario_pago_reembolso_registrado | Cometidos | Funcionario - pago de reembolso registrado | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.funcionario_pago_viatico_registrado | Cometidos | Funcionario - pago de viático registrado | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.funcionario_rendicion_monto_cero | Cometidos | Funcionario - rendición autorizada con monto $0 | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.funcionario_rendicion_informe_disponibles | Cometidos | Funcionario - rendición e informe disponibles | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.funcionario_rendicion_observada | Cometidos | Funcionario - rendición observada por DAF | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.funcionario_rendicion_rechazada | Cometidos | Funcionario - rendición rechazada por DAF | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.gdp_rex_cgr_ac_autorizado | Cometidos | GDP - REX cometido CGR AC autorizado | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.gdp_reconversion_reembolso_emitir_rex | Cometidos | GDP - reconversión aprobada para emitir REX CGR | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.gdp_rex_rendicion_ac | Cometidos | GDP - rendición AC requiere REX CGR | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.gdp_rex_rendicion_establecimiento | Cometidos | GDP - rendición establecimiento requiere REX CGR | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.informe_revision_jefatura_respaldo | Cometidos | Informe - revisión de jefatura respaldo | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.juridica_resolucion_pago_reembolso | Cometidos | Jurídica - resolución de pago de reembolso | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.otros | Cometidos | Otros avisos de etapas de Cometidos | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.planificacion_cdp_reembolso | Cometidos | Planificación - CDP de reembolso | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.planificacion_cdp_pasaje_aereo | Cometidos | Planificación - CDP pasaje aéreo | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.planificacion_cdp_viatico_ac | Cometidos | Planificación - CDP viático AC | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.planificacion_rechazo_director_sin_disponibilidad | Cometidos | Planificación - rechazo Director por falta de disponibilidad | mail, whatsapp (WhatsApp nuevo: desactivado) |
| cometidos.servicios_generales_vehiculo_institucional | Cometidos | Servicios Generales - vehículo institucional | mail, whatsapp (WhatsApp nuevo: desactivado) |
| document.review.status_changed | Documentos | Resultado de revisión de documento | mail, whatsapp (WhatsApp nuevo: desactivado) |
| messages.new_message | Mensajería | Nuevo mensaje interno | mail, whatsapp (WhatsApp nuevo: desactivado) |
| solicitud_reemplazo.aceptada_reemplazante | Reemplazos | Aceptación: OT y ubicación al reemplazante | whatsapp |
| solicitud_reemplazo.aprobada_uatp_pendiente_plani | Reemplazos | Aprobación UATP | mail, whatsapp |
| solicitud_reemplazo.derivada_slep | Reemplazos | Asignación SLEP | whatsapp |
| solicitud_reemplazo.autorizacion_docente.solicitada | Reemplazos | Autorización docente | mail, whatsapp (WhatsApp nuevo: desactivado) |
| solicitud_reemplazo.contrato_trabajo_firmado_enviado | Reemplazos | Contrato firmado | mail, whatsapp (WhatsApp nuevo: desactivado) |
| solicitud_reemplazo.corregida_plani | Reemplazos | Corrección para Planificación | whatsapp |
| solicitud_reemplazo.corregida_uatp | Reemplazos | Corrección para UATP | whatsapp |
| solicitud_reemplazo.deuda_pension.enviada_remuneraciones | Reemplazos | Deuda de pensión: remuneraciones | mail, whatsapp (WhatsApp nuevo: desactivado) |
| solicitud_reemplazo.created | Reemplazos | Nueva solicitud | mail, whatsapp |
| solicitud_reemplazo.orden_trabajo_creada | Reemplazos | OT creada: establecimiento | mail, whatsapp (WhatsApp nuevo: desactivado) |
| solicitud_reemplazo.orden_trabajo_notificada_reemplazante | Reemplazos | OT generada: reemplazante | mail, whatsapp (WhatsApp nuevo: desactivado) |
| solicitud_reemplazo.orden_trabajo_soporte_ti_correo_institucional | Reemplazos | OT: correo institucional | mail, whatsapp (WhatsApp nuevo: desactivado) |
| solicitud_reemplazo.observacion_slep | Reemplazos | Observación SLEP | mail, whatsapp (WhatsApp nuevo: desactivado) |
| solicitud_reemplazo.reabierta_uatp | Reemplazos | Reapertura UATP | whatsapp |
| solicitud_reemplazo.reabierta_plani | Reemplazos | Reapertura de Planificación | whatsapp |
| solicitud_reemplazo.reasignada_slep | Reemplazos | Reasignación SLEP | whatsapp |
| solicitud_reemplazo.rechazada_uatp | Reemplazos | Rechazo UATP | mail, whatsapp (WhatsApp nuevo: desactivado) |
| solicitud_reemplazo.rechazada_plani | Reemplazos | Rechazo de Planificación | mail, whatsapp (WhatsApp nuevo: desactivado) |
| solicitud_reemplazo.resolucion_docente_firmada_enviada | Reemplazos | Resolución docente firmada | mail, whatsapp (WhatsApp nuevo: desactivado) |
| solicitud_reemplazo.validada_plani | Reemplazos | Validación de Planificación | mail, whatsapp |
| descuentos_cgr.auditoria | Remuneraciones | Descuento CGR: Auditoría | mail, whatsapp (WhatsApp nuevo: desactivado) |
| descuentos_cgr.finanzas | Remuneraciones | Descuento CGR: Finanzas | mail, whatsapp (WhatsApp nuevo: desactivado) |
| system.internal | Sistema | Aviso interno genérico (sin invocaciones actuales) | mail, whatsapp (WhatsApp nuevo: desactivado) |
| system.generic_mail | Sistema | Correo base genérico | mail, whatsapp (WhatsApp nuevo: desactivado) |
| critical_exception | Sistema | Excepción crítica | mail, whatsapp (WhatsApp nuevo: desactivado) |
| failed_job | Sistema | Trabajo de cola fallido | mail, whatsapp (WhatsApp nuevo: desactivado) |
| tramite.manual_applicant_notification | Trámites y bienios | Aviso manual al solicitante | mail, whatsapp (WhatsApp nuevo: desactivado) |
| tramite.bienios.cierre_informado | Trámites y bienios | Cierre de bienios | mail, whatsapp (WhatsApp nuevo: desactivado) |
| tramite.bienios.resultado_enviado | Trámites y bienios | Resultado de bienios | mail, whatsapp (WhatsApp nuevo: desactivado) |
| tramite.documento.review.status_changed | Trámites y bienios | Resultado de revisión de documento de trámite | mail, whatsapp (WhatsApp nuevo: desactivado) |
| tramite.bienios.solicitud_recibida | Trámites y bienios | Solicitud de bienios recibida | mail, whatsapp (WhatsApp nuevo: desactivado) |
| tramite.anulado | Trámites y bienios | Trámite anulado | mail, whatsapp (WhatsApp nuevo: desactivado) |
