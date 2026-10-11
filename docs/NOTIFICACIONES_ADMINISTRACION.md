# Administración de eventos de notificaciones

Parche **2026.10.11.591**, rama `feat/admin-eventos-notificaciones`. Implementación local; no se modificó producción ni se enviaron correos o WhatsApp reales.

## Catálogo y alcance

La pantalla **Administración → Eventos de notificaciones**, URL `/admin/eventos-notificaciones`, exige sesión, correo verificado, rol `admin` y permiso **`notifications.manage`**. La migración lo concede únicamente al rol administrador. Tener el permiso con otro rol no habilita el acceso. El menú respeta el permiso; todas las operaciones, incluida la vista previa, verifican la autorización en el servidor.

El [inventario](NOTIFICACIONES_INVENTARIO.md) contiene **90 entradas** de **12 módulos**. Se revisaron las **29 clases Mailable**, las notificaciones Laravel, los envíos directos de vistas, jobs, servicios, controladores, observadores y agenda de consola. Incluye **38 reglas de Cometidos**, sus asuntos alternativos, etapas de Bolsa de Trabajo, tickets y descuentos CGR. Hay **88 eventos con WhatsApp**: los 11 existentes conservan su transporte; los otros **77 canales nuevos comienzan desactivados**. Las clases sin invocaciones actuales están identificadas; configurar una de ellas no crea un disparador nuevo.

Por instrucción del operador, los eventos que sólo tenían correo ahora ofrecen también WhatsApp cuando el destinatario original tiene cuenta verificada y teléfono válido. Verificación y recuperación de contraseña siguen exclusivamente por correo y protegidas. Los mensajes dentro del chat, broadcasts y estados de los trámites continúan con su funcionamiento propio; se controlan sus avisos externos, no la existencia del mensaje.

## Configurar un evento

1. Buscar por nombre/clave, módulo o canal y abrir **Configurar**.
2. Habilitar o desactivar cada canal de forma independiente.
3. En correo, agregar hasta **20 direcciones de copia oculta**, separadas por coma, espacio o línea. Se validan y deduplican con To/CC/BCC de ese correo. Recibirán el mismo contenido y los mismos adjuntos: el administrador debe seleccionar destinatarios autorizados para esos datos. El propietario o responsable calculado por el trámite se conserva.
4. En avisos internos WhatsApp, elegir todos los destinatarios de la etapa o un subconjunto de usuarios. Sólo se ofrecen fichas AC vigentes, activadas para WhatsApp, con teléfono válido, cuenta verificada, rol y acceso al módulo. Seleccionar no agrega receptores ni cambia al funcionario asignado. Una ficha que pierde condiciones deja de ser elegible. El aviso de aceptación conserva al reemplazante definitivo; la prueba conserva el número expresamente ingresado.
   Para los **canales nuevos**, activar explícitamente el evento y elegir todos sus destinatarios válidos o una selección con buscador. Cada cuenta seleccionada debe estar también entre los destinatarios originales To/CC/BCC del aviso; seleccionarla no crea una copia ni le entrega otro expediente. Las copias adicionales configuradas aquí para correo no se convierten en destinatarios WhatsApp. Se busca la ficha AC mediante vínculo registrado o RUT único; debe estar activa, ser inequívoca y no contradecir el RUT. Si no hay ficha AC, se usa `postulantProfile.telefono1`. Una ficha vencida, duplicada o inválida no se sustituye por el perfil. No se usan teléfonos públicos de establecimientos, `contacto_fono`, `telefono2` ni correos externos sin cuenta registrada.
5. Editar asunto y presentación, revisar **Vista previa sin envío** y guardar cada canal. La vista previa usa datos ficticios; no consulta una solicitud real, genera PDFs, encola jobs ni envía mensajes. Muestra la presentación, no reproduce el PDF ni la apariencia exacta de WhatsApp.
6. **Restablecer configuración original** retira la personalización de ese canal y conserva la auditoría. No reenvía eventos anteriores ni altera las fichas.

Los roles y correos propios de Cometidos conservan su mantenedor actual, enlazado desde el evento. No se crean dos fuentes de roles. El evento central aplica una condición adicional: no sustituye la autorización ni reactiva un aviso bloqueado por el proceso o su configuración propia.

## Plantillas seguras

Se edita **texto**, sin HTML, Blade ni PHP. Variables admitidas:

| Variable | Significado |
| --- | --- |
| `{{contenido_original}}` | Datos, enlaces y referencias producidos por el trámite. Obligatoria una sola vez en una presentación personalizada. |
| `{{asunto_original}}` | Asunto vigente del correo; vacío en WhatsApp. |
| `{{evento}}` | Nombre del evento del catálogo. |

Ejemplo de asunto: `SGA · {{asunto_original}}`.

Ejemplo de presentación:

```text
Información del SGA para tu gestión:

{{contenido_original}}

Revisa el detalle en el enlace indicado.
```

Vacío conserva la plantilla original. Esta edición permite personalizar el texto que la presenta; **no reemplaza el cuerpo específico ni sus datos obligatorios**. Los diseños de correo y sus adjuntos se mantienen. El contenido añadido se escapa al incorporarse al HTML. El PDF no se edita desde este mantenedor.

WhatsApp conserva el enlace autorizado y limita el resultado a **1000 caracteres**. Se reserva margen al validar la presentación; si los datos reales superan el límite, no se envía un mensaje truncado. En los avisos nuevos y la aceptación al reemplazante, el pie automático sin respuesta queda siempre al final. El administrador no puede sustituir al reemplazante ni dirigir su OT a otro teléfono.

Los canales nuevos usan un resumen común, sin copiar cuerpos de correo, diagnósticos ni adjuntos. Ejemplo ficticio:

```text
SGA SLEP Andalién Costa
Solicitud de bienios recibida
Tienes una actualización. Consulta el detalle y los documentos disponibles en el SGA:
https://sga.example.test/

Esta es una notificación automática del SGA.
Por favor, no respondas a este mensaje.
```

Se utiliza `actionUrl`, `detailUrl` o `url` explícito del emisor si es HTTPS, del mismo origen y sin credenciales ni parámetros de acceso. Si no existe, el enlace es la página principal HTTPS del SGA. El destinatario inicia sesión y conserva los permisos normales. La vista previa usa datos sintéticos ilustrativos; no renderiza el detalle de un correo real. Los WhatsApp de reemplazos ya existentes mantienen sus datos específicos y la OT conserva su PDF y ubicación.

**Verificación de correo y recuperación de contraseña** se muestran protegidas y no aceptan cambios de canal, contenido ni destinatario. Sus enlaces personales no se agregan a copias.

## Aplicación, colas y auditoría

Las preferencias se guardan en `notification_event_settings`, por clave/canal. La ausencia de una fila o de la tabla conserva correo y WhatsApp anteriores, y deja apagados los canales WhatsApp nuevos. Restablecer un canal nuevo también lo desactiva. La migración no siembra activaciones. No hay variables nuevas en `.env` para este mantenedor.

Todos los WhatsApp requieren `WHATSAPP_ENABLED`, sesión conectada y cola. Los avisos previos de reemplazos mantienen además su flag de módulo y preferencias individuales. Los 77 nuevos se autorizan por el interruptor de cada evento en la base de datos; no dependen del flag de reemplazos ni reutilizan su opt-in. Habilitar aquí no inicia Node; el listado indica cuando servicio o módulo están desactivados.

Un listener de `MessageSending` prepara el WhatsApp del evento y aplica la preferencia de correo **antes del transporte**, también en mailables/notificaciones en cola y vistas directas de agendamientos. Se puede desactivar correo y mantener WhatsApp. No crea notificaciones si el emisor original no se ejecuta o no calcula destinatarios. Los eventos con WhatsApp previo no se vuelven a enviar desde el correo. Las claves de auditoría se conservan y viajan con los mailables; las clases compartidas se distinguen por su etapa. `NotificationAudit` registra **`suppressed`** y no marca como enviado un correo omitido. Los envíos directos históricos no adquieren auditoría de correo nueva.

WhatsApp aplica plantilla y selección antes de cifrar y encolar, y revalida habilitación, selección, cuenta, email, ficha y teléfono en cada intento. Desactivar, desverificar, eliminar o cambiar el teléfono/email cancela el pendiente. Cambiar la presentación no reescribe un mensaje ya encolado ni modifica su clave. La ocurrencia usa el UUID del job de correo cuando existe; el contexto se limpia al terminar o fallar el job. `NotificationAudit` conserva una ocurrencia explícita en el mailable; una nueva acción síncrona genera otra. Para vistas directas reintentadas por el emisor, pasar `sga_occurrence_id` estable: repetir una llamada síncrona sin esa clave representa otra acción. El worker conserva el UUID ante desconexión y no repite resultados inciertos.

La auditoría de configuración está en `notification_event_changes`: actor, evento, canal, fecha y valores anterior/nuevo **cifrados**. Las copias también se cifran en su configuración. No se registran textos ni correos en logs nuevos. Una revisión de formulario y transacción evitan sobrescribir cambios guardados por otro administrador. El historial de la pantalla muestra sólo actor, canal y fecha.

Referencias del mecanismo Laravel: [eventos de correo](https://laravel.com/docs/12.x/mail#events) y código instalado de `Illuminate\Mail\Mailer`/`Mailable`. No se modificó `vendor`.

## Instalar en cPanel

Ejecutar como usuario de la cuenta, tras publicar y desplegar este parche con el procedimiento habitual. No aplicar estos comandos contra otro repositorio o antes de tener los archivos nuevos.

1. Desplegar los PHP/Blade/config/rutas y los assets: `public/build/manifest.json` y el CSS nuevo referenciado por éste. Se entregan compilados; si se reconstruyen, usar Node de nvm y `npm ci && npm run build` en el repositorio. Conservar los assets históricos del servidor al copiar sólo el parche.
2. Instalar únicamente la migración nueva, no destructiva:

   ```bash
   cd /home/slepac/apps/slep_postulaciones
   /opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --path=database/migrations/2026_10_11_170000_create_notification_event_settings.php --force
   ```

3. Aplicar configuración y reiniciar workers Laravel:

   ```bash
   /opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
   /opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
   /opt/cpanel/ea-php83/root/usr/bin/php artisan queue:restart
   /opt/cpanel/ea-php83/root/usr/bin/php artisan route:list --path=admin/eventos-notificaciones
   ```

4. Entrar con un administrador verificado y comprobar menú, filtros, configuración y vista previa. No es necesario reiniciar Node, renovar QR, generar otro token ni cambiar el cron existente. Los avisos mantienen los flags y condiciones ya instalados.
5. Validar una configuración y un envío legítimo autorizado en un entorno de pruebas antes de aplicar preferencias operativas. Revisar un pendiente desactivado, permiso de un no administrador, enlace y destinatario. No realizar envíos masivos de prueba ni crear trámites ficticios en producción.

No se necesita una tarea nueva del proveedor para este mantenedor. Las restricciones de proceso, red y lectura privada del puente WhatsApp siguen con sus diagnósticos anteriores.

## Regla obligatoria: cada evento nuevo debe aparecer en el mantenedor

Esta regla también está en **AGENTS.md, punto 14**. Toda nueva notificación debe quedar configurable al desplegar el código, antes de enviar un mensaje real y sin una migración que siembre filas por evento:

1. Registrar Mailable en `notification_events.mailables` o Notification de correo en `notification_events.notifications`: clase, clave estable, módulo, nombre y regla de destinatarios. La entrada de Notification incluye además el booleano `protected` para avisos de acceso que no se pueden editar. Los eventos de vistas directas se registran en `notification_events.events` y entregan `sga_event_key` al envío. No usar nombres/RUT/correos ni IDs de personas en la clave.
2. Si una clase cubre varias etapas, registrar cada clave y ampliar `resolveMail` con un discriminador estable. No deducir nuevas etapas únicamente por un asunto dinámico. Una transición sin clase nueva requiere registro explícito; no se descubre inspeccionando cambios de estado en producción.
3. El catálogo se construye desde código: el evento aparece sin una fila en `notification_event_settings`. Las nuevas clases concretas bajo `app/Mail` y `app/Notifications` se detectan también en subdirectorios y aparecen como **Pendientes de catalogar** si falta el registro. Completar sus metadatos antes de activar envíos; no usar esa clave provisional como clave de negocio. Las Notification sin correo deben declararse en `non_mail_notifications` y registrar por separado sus canales implementados.
4. Correo conserva el comportamiento existente; todo WhatsApp nuevo comienza **desactivado**, con destinatarios originales identificados y teléfonos normalizados. Si el evento necesita otro contenido, destinatario, adjunto o política, implementar esa política antes de activarlo; no reutilizar `scope=reemplazos`.
5. Probar que aparece en listado/edición, que la clase está registrada, la resolución de cada etapa y su desactivación antes del envío y después de encolar. La prueba de cobertura recorre recursivamente clases Mailable y Notification: **falla si una clase nueva no se registra**. Agregar cobertura explícita para vistas y etapas compartidas.
6. Actualizar inventario y changelog. Desplegar código/configuración y reconstruir `config:cache`/reiniciar workers; no modificar `.env`, producción, destinatarios históricos ni activar todos los eventos en un seeder.

Conservar permisos del flujo, preferencias aplicables, enlaces al SGA, documentos privados y deduplicación. Mantener contenido original y avisos obligatorios en plantillas de presentación. Nunca enviar mensajes reales en pruebas automatizadas.

## Validación local y límites

**184 pruebas PHP, 1604 aserciones correctas**: configuración, autorización, copias cifradas, plantillas, vista previa, revisión concurrente, correo `array`, WhatsApp independiente del correo, destinatarios de ficha/perfil, errores/reintentos y aparición automática/cobertura de nuevas clases. La suite nueva aporta **41 casos**. Sin SMTP real ni mensajes WhatsApp reales.

`php -l` correcto en **21 PHP/Blade**, `npm run build` correcto (advertencias Sass/fuentes preexistentes), cinco rutas nuevas con sus middleware y `optimize:clear` comprobados. Los dos bloques Bash de instalación pasan `bash -n` y el JavaScript de Select2 pasa la comprobación de sintaxis. Se conservaron los assets históricos que Vite limpia al reconstruir; el manifest sólo agrega el CSS nuevo. `git diff --check`, whitespace de archivos nuevos, comparación completa del changelog e inventario de los **29 archivos** correctos. `.env` y los tres PDF locales anteriores conservan sus hashes y quedan fuera del parche.

No se comprobó MySQL/concurrencia real ni el despliegue cPanel. El navegador automatizado no está disponible en esta sesión: se renderizó el HTML con el layout real en pruebas HTTP y se compiló el CSS; quedan pendientes inspección visual en escritorio/móvil, teclado y recepción manual en el hosting.

## Archivos del parche

Nuevos:

- `app/Http/Controllers/Admin/NotificationEventController.php`
- `app/Listeners/ConfigureNotificationMail.php`
- `app/Models/NotificationEventSetting.php`
- `app/Services/Notifications/NotificationEventCatalog.php`
- `app/Services/Notifications/NotificationEventPolicy.php`
- `app/Services/Notifications/NotificationEventOccurrence.php`
- `app/Services/Notifications/NotificationWhatsAppRecipients.php`
- `app/Services/Notifications/MailWhatsAppNotifier.php`
- `config/notification_events.php`
- `database/migrations/2026_10_11_170000_create_notification_event_settings.php`
- `routes/notification_events.php`
- `resources/views/admin/notification-events/index.blade.php`
- `resources/views/admin/notification-events/edit.blade.php`
- `resources/views/admin/notification-events/preview.blade.php`
- `resources/css/notification-events.css`
- `public/build/assets/notification-events-9hnmAjNk.css`
- `tests/Feature/NotificationEventConfigurationTest.php`
- `docs/NOTIFICACIONES_ADMINISTRACION.md`
- `docs/NOTIFICACIONES_INVENTARIO.md`

Modificados:

- `AGENTS.md`
- `docs/WHATSAPP_NOTIFICACIONES_REGLAS.md`
- `app/Jobs/SendWhatsAppMessage.php`
- `app/Providers/AppServiceProvider.php`
- `app/Services/WhatsApp/WhatsAppDispatcher.php`
- `app/Support/NotificationAudit.php`
- `app/Support/SlepUiRegistry.php`
- `config/changelog.php`
- `public/build/manifest.json`
- `vite.config.js`
