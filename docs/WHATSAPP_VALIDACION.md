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
