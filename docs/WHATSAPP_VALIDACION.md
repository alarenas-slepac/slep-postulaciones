# Validación local de WhatsApp por QR

Fecha: 10 de octubre de 2026. Rama: `feat/whatsapp-qr-notificaciones`.

Entorno: Windows, PHP 8.3.30, Laravel 12, Node 22.22.0, Baileys 7.0.0-rc14. Las pruebas de Laravel utilizan SQLite en memoria, HTTP/cola simulados y almacenamiento de pruebas aislado; las del puente utilizan sockets y credenciales sintéticos. No se enviaron mensajes reales ni se vinculó un teléfono.

| Comprobación | Resultado |
| --- | --- |
| `tests/Feature/WhatsAppIntegrationTest.php` | 18 pruebas, 153 aserciones, todas correctas |
| `AdminBulkRoleMailMenuTest.php` + `ChangeLogLazyTest.php` | 9 pruebas de regresión, 72 aserciones, todas correctas |
| `services/whatsapp`: `npm test` | 13 pruebas correctas |
| `node --test tests/js/whatsapp-admin.test.mjs` | 2 pruebas correctas |
| `php -l` | 16 archivos PHP/Blade nuevos o modificados, sin errores de sintaxis |
| `services/whatsapp`: `npm run check` | Sintaxis válida de los seis archivos del servicio/diagnóstico |
| `services/whatsapp`: `npm audit --omit=dev` | 0 vulnerabilidades reportadas en las dependencias instaladas |
| `php artisan route:list --path=admin/whatsapp -vv` | 5 rutas con auth, verified, rol admin y whatsapp.manage; límites de frecuencia independientes |
| `php artisan optimize:clear` con `CACHE_STORE=file` | Correcto; MySQL local no está disponible para limpiar la caché de BD |
| `npm run build` | Correcto; advertencias existentes de Sass y fuentes Century Gothic ajenas al módulo |
| Assets | Manifest actualizado y dos assets de WhatsApp; las entradas preexistentes del manifest conservan sus hashes |
| Comunicación local real | Arranque de `src/index.js`, consulta desde el cliente Laravel y `check-status.js`: `idle`; sin token y con token incorrecto se rechaza el acceso |
| Interfaz en Chrome | Vista Laravel renderizada con administrador sintético y API de pruebas local; QR sintético, respuesta de envío simulada, foco por teclado y columnas móviles verificados |
| Móvil | Ancho 390 px: cuatro paneles en una columna, 343 px cada uno; sin desbordamiento horizontal de la página |

Cobertura relevante: denegación por rol/permiso, protección CSRF, configuración desactivada, comunicación autenticada, URL restringida a loopback, rechazo de QR no PNG/expirado, cifrado del payload, job con UUID únicamente, cola persistente obligatoria, deduplicación Laravel/Node y después de reinicio, desconexión antes del envío, resultado incierto sin reenvío, cierre asíncrono del socket anterior, límites de reconexión, conflicto de dispositivo, restauración de credenciales QR, bloqueo de una segunda instancia y conservación del historial al desvincular/reaplicar la migración. También se comprueba que un rollback del trámite descarte el envío y que un fallo de cola al confirmar una transacción externa no interrumpa la operación principal.

El proceso real se inició sólo con una sesión nueva y un token sintético: permaneció `idle`, sin abrir un socket a WhatsApp. La API usada para la revisión visual es un servidor temporal de respuestas simuladas, no el servicio de envío. Ambos procesos de diagnóstico se detuvieron al terminar.

Las comprobaciones de integridad confirmaron que el `.env` y los tres PDF locales anteriores permanecen intactos. El parche excluye esos archivos, `vendor`, `node_modules`, logs y credenciales/sesiones. Se revisó el diff de los cambios y el contenido de los nuevos archivos.

## Actualización de la guía: 11 de octubre de 2026

Rama local: `docs/whatsapp-cpanel-node22-nvm`. Versión de documentación: `2026.10.11.585`. Se actualizan la guía paso a paso, la plantilla del supervisor y el changelog; el código de envío no cambia.

El operador informó que dispone de Terminal de cPanel, instaló Node **22.23.3** con npm **10.9.9** y comprobó la carga de `node:sqlite`. Ruta informada: `/home/slepac/.nvm/versions/node/v22.23.3/bin/node`. También informó que creó el archivo privado `service.env`. Esta información procede de su salida de Terminal; este agente sigue sin acceso directo al hosting.

Las comprobaciones siguientes se realizaron localmente con Node 22.22.0 y PHP 8.3.30:

| Comprobación de las instrucciones | Resultado |
| --- | --- |
| Sintaxis de bloques Bash con Git Bash `bash -n` | 26 bloques válidos; no ejecuta los comandos de instalación |
| Bloques JavaScript de creación, token y coincidencia | Los tres compilan; 12 casos correctos con archivos simulados en memoria |
| Conservación de configuración/token | Creación sin sobrescribir, generación de 64 caracteres, repetición sin rotar, conservación de variables y token entre comillas/export; rechazo de duplicados y valores inválidos |
| Coincidencia con Laravel | Acepta el mismo token con integración desactivada; rechaza diferencia, duplicados, activación anticipada y URL distinta, sin escribir configuración real |
| Sintaxis PHP del diagnóstico y comando Tinker | Ambos válidos con PHP 8.3 |
| Diagnóstico Laravel del paso 13 | Cuatro escenarios correctos: estado autenticado, HTTP 401, desconexión y activación anticipada; HTTP simulado y salida sin token, QR ni detalle sensible |
| `ChangeLogLazyTest.php` | 5 pruebas y 53 aserciones, correctas; SQLite en memoria |
| `php -l config/changelog.php` | Sin errores |
| Changelog histórico | Los 319 registros antiguos y 602 entradas anteriores conservan su contenido; sólo se agrega la entrada actual y cambia la versión |
| `php artisan optimize:clear`, `CACHE_STORE=file` | Correcto; no comprueba limpieza de caché MySQL productiva |

Los permisos del bloque Node se comprobaron como operaciones previstas sobre archivos simulados; no constituyen una validación de permisos Linux. La plantilla systemd y sus rutas se revisaron, pero no se instaló ni ejecutó `systemd-analyze` desde Windows. No se modificaron CSS, JavaScript compilado ni rutas de Laravel en esta actualización, por lo que no corresponde repetir el build ni el listado de rutas. Las 42 pruebas de la implementación descritas arriba pertenecen a la validación del 10 de octubre; no se presenta ese total como una nueva ejecución.

## Pendientes del despliegue

Los resultados anteriores describen su fecha y versión; la evidencia posterior del hosting se recoge a continuación. No se ejecutó la suite completa del SGA ni se enviaron mensajes reales desde este agente.

## Inicio del puente real con cron: versión 2026.10.11.587

Rama local: `feat/whatsapp-cron-service`. El paso 11 de [WHATSAPP_CPANEL.md](WHATSAPP_CPANEL.md) ahora usa `deploy/cron-service.mjs`, con Node y `service.env` absolutos, bloqueo de instancia, controles `start`, `stop`, `restart` y `status`, y límite de cinco arranques en diez minutos. El puente corre dentro del mismo proceso del lanzador; no queda un hijo huérfano si el proceso muere. Conserva la sesión, el token y la deduplicación. Los logs contienen sólo eventos conocidos, rotan a dos archivos acotados y descartan los datos restantes de dependencias.

### Evidencia compartida por el operador

- Puente real en 3210: HTTP 401 sin token y estado autenticado `idle`.
- Salida desde Node del hosting: DNS correcto, HTTPS a WhatsApp con HTTP 200 y conexión WebSocket establecida.
- Diagnóstico de cron: `starts: 1`, recuperación tras `crash` con `starts: 2` y vencimiento original intacto; estado final `finished` y retirada del cron confirmada.
- Después de las instrucciones de vinculación, el operador informó que la sesión real funciona. No se presupone un resultado de recepción de mensajes ni recuperación tras reiniciar el puente a partir de ese informe.

El agente no tiene acceso directo al servidor y no instaló el lanzador permanente allí. El diagnóstico temporal anterior no ejecuta el nuevo mecanismo del puente real.

### Validación local del lanzador

Comprobaciones ejecutadas el 11 de octubre de 2026 en Windows, Node 22.22.0 y PHP 8.3.30:

| Comprobación | Resultado |
| --- | --- |
| `services/whatsapp`: `npm test` | 26 pruebas correctas: 13 del puente, 6 del diagnóstico y 7 del lanzador permanente |
| Lanzador permanente | Bloqueo de duplicados, detención persistente, reinicio, cinco arranques en diez minutos, reapertura manual y rotación de logs sin datos sensibles |
| CLI con el puente real | API autenticada en `idle`, duplicado descartado, reinicio y caída abrupta recuperables; conserva credenciales sintéticas, clave de deduplicación e historial SQLite |
| `services/whatsapp`: `npm run check` | Los siete archivos incluidos tienen sintaxis válida |
| `ChangeLogLazyTest.php` | 5 pruebas y 53 aserciones correctas; SQLite en memoria |
| `php -l config/changelog.php` | Sin errores de sintaxis |
| `php artisan optimize:clear`, `CACHE_STORE=file` | Correcto; MySQL local no está disponible |
| Sintaxis Bash de las dos guías, `bash -n` | 43 bloques válidos, sin ejecutar la instalación: 34 de cPanel y 9 del diagnóstico |
| Fragmentos Node de la guía | Los tres compilan, sin ejecutarlos ni cambiar configuración |
| Integridad del changelog | Los 319 registros históricos y las 604 entradas anteriores conservan su contenido |
| Integridad y alcance | `.env` y los tres PDF locales previos conservan sus hashes; diff revisado y parche limitado a siete archivos, sin credenciales, dependencias, logs ni archivos de usuarios |

Las pruebas usan directorios temporales y una sesión nueva en `idle`, sin QR ni conexiones a WhatsApp. La prueba de caída abrupta modifica únicamente los mtimes de sus bloqueos después de confirmar el cierre del proceso para simular la espera de caducidad; no sustituye observar los 30 segundos reales en Linux. Las aserciones POSIX de permisos 700/600 no se ejecutan en Windows. No se instaló el cron ni se comprobó systemd en el hosting. No hay cambios de rutas, vistas, CSS o JavaScript compilado: no corresponde otro listado de rutas ni build. No se enviaron mensajes reales.

### Condiciones aún pendientes

Instalar y comprobar el cron **del puente real**, su supervivencia al cerrar Terminal, recuperación `connected` sin QR después de `restart`, recuperación tras reiniciar el servidor, política del proveedor para procesos continuos, persistencia y permisos efectivos del disco, acceso SSH si se necesita y cola productiva. La entrega real de mensajes requiere su prueba expresa del paso 15. La selección de eventos, destinatarios y preferencias permanece pendiente: no hay envíos automáticos activados.

## Diagnóstico Laravel con el módulo habilitado: versión 2026.10.11.588

Rama local: `docs/whatsapp-diagnostico-laravel`. El operador compartió el fallo genérico del paso 13. El bloque anterior rechazaba la configuración cuando `whatsapp.enabled` era verdadero, antes de consultar Node; la salida recibida no permite determinar si ésa fue la causa en el hosting. Se corrige el diagnóstico para admitir ambos estados de activación, conservar el acceso exclusivo a loopback y clasificar errores sin mostrar token, QR, cuerpo de respuesta ni detalles de excepciones. La consulta sigue siendo únicamente `GET /v1/status`.

Validación local el 11 de octubre de 2026 con PHP 8.3.30 y Node 22.22.0:

- El bloque PHP extraído de la guía pasó **13 escenarios** con Laravel real y HTTP simulado: desactivado/idle, habilitado/connected, habilitado/reconnecting, URL inválida, token ausente o inválido, HTTP 401 y 403, conexión fallida, HTTP 500, estado inválido, JSON inválido y fallo de arranque. Se comprueba la petición GET autenticada, su ausencia ante configuración inválida y que no se impriman valores sensibles sintéticos.
- El diagnóstico PHP y `config/changelog.php` tienen sintaxis válida. Los **34 bloques Bash** de la guía pasan `bash -n`, sin ejecutar instalación ni conexiones al hosting.
- `ChangeLogLazyTest.php`: **5 pruebas y 53 aserciones** correctas con SQLite en memoria. `php artisan optimize:clear` con `CACHE_STORE=file`: correcto; MySQL local no está disponible.
- Se conserva el contenido de los **319 registros históricos y las 605 entradas anteriores**. Diff revisado; `.env` y los tres PDF locales previos conservan sus hashes y quedan fuera del cambio, al igual que credenciales, dependencias, logs y archivos de usuarios.

No cambian el código de envío, las rutas ni los assets compilados; no corresponde repetir suites Node, listado de rutas o build. El resultado del nuevo diagnóstico en el hosting sigue pendiente de la ejecución del operador. No se cambió configuración productiva, reiniciaron servicios, generaron QR ni enviaron mensajes.

## Actualización 2026.10.11.589: notificaciones automáticas de reemplazos

El operador informó `Integracion: habilitada`, caché de configuración activa y `Laravel conecta autenticado. Estado: connected`. Después autorizó implementar avisos de reemplazos al responsable de la siguiente etapa y una preferencia individual para los cuatro roles existentes. Esto acredita lo informado sobre la conexión previa; la instalación y recepción de los nuevos eventos todavía necesitan validación manual en el hosting.

Validación local con PHP 8.3.30 y Node 22.22.0, sin mensajes reales:

- `ReemplazoWhatsAppNotificationsTest`, `WhatsAppIntegrationTest`, `SolicitudReemplazoTipoValidationTest`, `FuncionarioAcDirectoryRolesTest` y `ChangeLogLazyTest`: **112 pruebas, 875 aserciones, todas correctas**. SQLite en memoria, almacenamiento ficticio, HTTP bloqueado salvo respuestas simuladas y cola simulada. Se ejercitan las rutas reales de aprobación, validación, derivación, reasignación, reapertura, creación, corrección y edición de preferencias.
- Se verifican los flags inicialmente falsos, independencia de `enviar_notificacion`, cuatro roles exactos, autorización vigente, correo verificado, acceso al módulo, identidad histórica única, conflictos de vínculo, deduplicación por teléfono, resumen con enlace HTTPS, cifrado del payload y job con sólo UUID.
- Doce escenarios de formato telefónico comprueban añadir `+56` o sólo `+`, conservar internacionales explícitos, retirar separadores y rechazar números incompletos, anexos y listas. Un rechazo `invalid_recipient` del servicio es definitivo. La suite Node comprueba además que `onWhatsApp` impide enviar a un número inexistente.
- Se comprueban rollback sin job, repetición de acciones sin segundo aviso, reintento con el mismo UUID/Bearer, ausencia de política interna en HTTP, fallos sin impedir el trámite y cancelaciones al cambiar flag, teléfono, autorización, rol, acceso, cuenta, etapa o asignación.
- `npm test` en `services/whatsapp`: **26 pruebas correctas**, con sockets sintéticos; incluye deduplicación, recuperación y bloqueo del proceso. No se modifican dependencias, sesión ni código Node.
- `php -l`: **18 archivos PHP/Blade nuevos o modificados correctos**. El editor se renderiza en una prueba HTTP; se comprueban etiqueta asociada, ayuda accesible y casilla inicialmente desmarcada. No se realizó revisión visual con navegador en escritorio/móvil.
- `php artisan route:list --except-vendor`: correcto, **766 rutas**. No se agregan ni eliminan rutas ni permisos. `php artisan optimize:clear` con `CACHE_STORE=file`: correcto. No se modifican assets compilados; no corresponde `npm run build`.
- `git diff --check`: correcto. Se conservan los **319 registros históricos y las 606 entradas anteriores** del changelog, incluida la documentación local previa 588. La comparación del contenido histórico mantiene SHA-256 `ae0753dd97f30139391e45bc4a3505d81acc62ab80f5aa6fd14f2da10e4cb0db`.
- Los **36 bloques Bash** de las dos guías pasan `bash -n`, sin ejecutar comandos de despliegue. La comparación estructural de los dos controladores de reemplazos verifica 146 métodos: sólo cambian los ocho puntos de integración del catálogo; no se elimina ningún método ni se modifica el cuerpo de los restantes.
- `.env` y los tres PDF locales previos conservan sus hashes. No se incluyen datos de usuarios, credenciales, logs, `vendor` ni `node_modules` en el parche. Rama: `feat/whatsapp-reemplazos-notificaciones`.

No se pudo ejecutar contra MySQL local porque no está disponible, ni comprobar bloqueos concurrentes con MySQL, el worker productivo, la migración en cPanel, los nuevos mensajes reales o la apertura del enlace desde el iPhone. No se modificó producción ni se regeneró QR. El operador conserva la sesión ya vinculada; la guía [WHATSAPP_NOTIFICACIONES_REGLAS.md](WHATSAPP_NOTIFICACIONES_REGLAS.md) indica la migración, selección por ficha y activación del módulo.

### Archivos del parche

Nuevos:

- `database/migrations/2026_10_11_150000_add_whatsapp_reemplazos_preference.php`
- `app/Services/WhatsApp/ReemplazoWhatsAppRecipients.php`
- `app/Services/WhatsApp/ReemplazoWhatsAppNotifier.php`
- `app/Notifications/ReemplazoWhatsAppNotification.php`
- `tests/Feature/ReemplazoWhatsAppNotificationsTest.php`
- `docs/WHATSAPP_NOTIFICACIONES_REGLAS.md`

Modificados, incluyendo las instrucciones de diagnóstico local previas conservadas:

- `app/Models/FuncionarioAcAutorizado.php`
- `app/Http/Controllers/FuncionarioAcAutorizadoController.php`
- `app/Http/Controllers/FuncionarioEstab/SolicitudReemplazoController.php`
- `app/Http/Controllers/Gestion/SolicitudReemplazoGestionController.php`
- `app/Notifications/Channels/WhatsAppChannel.php`
- `app/Notifications/Messages/WhatsAppText.php`
- `app/Services/WhatsApp/WhatsAppDispatcher.php`
- `app/Jobs/SendWhatsAppMessage.php`
- `config/whatsapp.php`
- `config/changelog.php`
- `resources/views/tramites/cargas-familiares/funcionarios-ac/edit.blade.php`
- `resources/views/admin/whatsapp/index.blade.php`
- `tests/Feature/SolicitudReemplazoTipoValidationTest.php`
- `docs/WHATSAPP_CPANEL.md`
- `docs/WHATSAPP_VALIDACION.md`
