# WhatsApp por QR: instalación y operación en cPanel

## Alcance y estado

La integración está **desactivada por defecto**. Laravel ofrece un canal encolado y la pantalla **Administración → WhatsApp · Notificaciones**, en `/admin/whatsapp`. No se han conectado eventos automáticos ni inferido destinatarios a partir de teléfonos históricos. El envío de prueba requiere ingresar expresamente un número y un mensaje.

El puente separado en `services/whatsapp` usa **Baileys 7.0.0-rc14**, fijado en `package.json` y `package-lock.json`. Es una versión preliminar: actualizarla exige revisar cambios incompatibles y repetir las pruebas. No requiere Chromium ni WhatsApp Cloud API. La dependencia declara Node >=20, pero **esta implementación requiere Node >=22.13.0** por `node:sqlite`, `--env-file` y las APIs utilizadas. Preferir Node 22 actualizado y confirmar sus revisiones de seguridad con el proveedor. SQLite aún muestra una advertencia experimental en Node 22.

Se comprobó localmente en Windows con PHP 8.3.30 y Node 22.22.0: carga de Baileys, persistencia SQLite, autenticación HTTP, permisos, reconexiones y deduplicación con sockets simulados. **No hay acceso al hosting, no se modificó producción y no se comprobó una vinculación ni un envío real.** Las pruebas automatizadas no conectan cuentas de WhatsApp.

El número de notificaciones se vincula desde **WhatsApp Messenger en el iPhone → Configuración → Dispositivos vinculados → Vincular un dispositivo**. No hay pasos sobre el número personal ni sobre WhatsApp Business. La sesión QR es una credencial: no fotografiarla, publicarla ni compartirla en tickets.

## Requisitos que deben confirmarse antes de vincular

Tener cPanel o Application Manager no confirma la viabilidad del servicio. Completar esta tabla con el proveedor; todos los requisitos del hosting están pendientes.

| Requisito | Comprobación del usuario cPanel | Confirmación del proveedor |
| --- | --- | --- |
| Node >=22.13 y npm | Terminal: `command -v node`, `node --version`, `npm --version`; verificar también el binario absoluto suministrado | Instalar/habilitar Node compatible en la cuenta y CageFS si corresponde; indicar ruta y arquitectura |
| Terminal o SSH | Ver si existe Terminal y probar el acceso autorizado | Habilitar acceso shell; Terminal necesita que el proveedor lo permita |
| Dependencias nativas | Ejecutar `npm ci` e importar Baileys y `node:sqlite` como se indica abajo | Compatibilidad Linux/arquitectura de `whatsapp-rust-bridge`, espacio, límites de memoria/procesos y almacenamiento local |
| Proceso persistente | Consultar límites de ejecución y políticas de la cuenta | Autorizar explícitamente un proceso Node continuo y un supervisor; no terminarlo por inactividad ni al cerrar SSH |
| Salida a WhatsApp | `curl -I --max-time 15 https://web.whatsapp.com` comprueba sólo DNS/TLS/HTTP inicial | Permitir DNS, TLS y conexiones WebSocket salientes persistentes por 443 a los endpoints usados por WhatsApp; una respuesta de curl no prueba el enlace completo |
| Comunicación local | PHP y Node deben poder conectar por `127.0.0.1:3210` | Confirmar que ambos procesos comparten el acceso a loopback, incluido CageFS; no abrir ni publicar este puerto |
| Recuperación | Probar un reinicio controlado una vez instalado, sin envíos pendientes | Inicio tras reiniciar servidor, reinicio ante caída, límites de reinicio y monitoreo de fallos |
| Sesión persistente | Directorio privado escribible, propietario de la cuenta, permisos 700/600 | No usar almacenamiento efímero ni NFS para SQLite/bloqueos; preservar datos durante despliegues y restauraciones |
| Laravel | PHP 8.3, BD y tablas `jobs`/`failed_jobs`, permiso de cola y cron | Binario absoluto PHP 8.3, `flock` o supervisor de cola, `retry_after` mayor que el timeout del worker |

**Passenger por sí solo no basta.** Puede detener procesos inactivos y su ciclo depende de peticiones y configuración del servidor. Este puente es un consumidor permanente de WebSocket; el ejemplo suministrado usa systemd. No iniciar varias instancias, clusters de PM2 ni copias de la sesión.

Si el hosting compartido no autoriza el proceso persistente, no se puede mantener esta integración en él. La alternativa mínima es que el proveedor habilite un supervisor permitido en el mismo servidor, o mover SGA y puente a un VPS/servidor con ese control. Un puente remoto exigiría cambiar el requisito de mismo servidor y diseñar otro transporte seguro: la implementación actual rechaza URL remotas deliberadamente.

## 1. Instalación por el usuario cPanel

Desplegar únicamente los archivos de este cambio, conservar las rutas y el `.env` existente. No subir `node_modules`, sesiones, logs ni archivos de usuarios. Las dependencias de Composer del SGA no cambian. El document root del dominio debe apuntar al `public/` de Laravel; nunca al repositorio ni al servicio Node.

Las siguientes rutas son ejemplos adaptados a `/home/slepac/apps/slep_postulaciones`. **Verificar ambos binarios absolutos con el proveedor antes de ejecutar.** No depender de `$PHP83` ni del Composer predeterminado del servidor.

```bash
/opt/cpanel/ea-nodejs22/bin/node --version
/opt/cpanel/ea-php83/root/usr/bin/php -v
cd /home/slepac/apps/slep_postulaciones/services/whatsapp
export PATH=/opt/cpanel/ea-nodejs22/bin:$PATH
npm ci --omit=dev
node --input-type=module -e "await import('@whiskeysockets/baileys'); await import('node:sqlite'); console.log('Dependencias disponibles')"
```

Si falla la dependencia nativa, la versión o las restricciones del hosting, resolverlo con el proveedor antes de iniciar. No sustituir por instalaciones globales ni actualizar Baileys automáticamente. Los assets Vite de esta entrega están compilados; para reconstruirlos, hacerlo en desarrollo con las dependencias raíz y `npm run build`.

Crear directorios privados fuera del document root:

```bash
umask 077
mkdir -p /home/slepac/.config/sga-whatsapp /home/slepac/.local/share/sga-whatsapp
chmod 700 /home/slepac/.config/sga-whatsapp /home/slepac/.local/share/sga-whatsapp
```

Crear `/home/slepac/.config/sga-whatsapp/service.env`, permisos 600, con estas variables. Generar un token aleatorio de 32 bytes/64 caracteres hexadecimales, guardarlo mediante un editor privado o herramienta de secretos, y copiar el mismo valor a la configuración de Laravel. No dejarlo en historial, argumentos de comandos, Git o tickets. Los textos entre `<...>` son marcadores que se deben reemplazar.

```dotenv
WHATSAPP_PORT=3210
WHATSAPP_DATA_DIR=/home/slepac/.local/share/sga-whatsapp
WHATSAPP_SERVICE_TOKEN=<TOKEN_ALEATORIO_HEXADECIMAL_DE_64_CARACTERES>
```

Node escucha exclusivamente en `127.0.0.1`. La sesión y las claves de deduplicación se guardan en `state.sqlite` con sus archivos WAL/SHM, dentro del directorio privado. El proceso aplica umask 077, directorio 700 y BD 600. No crear proxies públicos, reglas de Apache, exposición de contenedores o túneles hacia 3210. El token limita el acceso incluso entre procesos locales; el proveedor debe aislar adecuadamente las cuentas del hosting.

Configurar en el entorno privado de Laravel, **sin reemplazar ni versionar su `.env`**, estos valores iniciales:

```dotenv
WHATSAPP_ENABLED=false
WHATSAPP_SERVICE_URL=http://127.0.0.1:3210
WHATSAPP_SERVICE_TOKEN=<EL_MISMO_TOKEN_DEL_SERVICIO>
WHATSAPP_CONNECT_TIMEOUT=3
WHATSAPP_TIMEOUT=15
WHATSAPP_QUEUE_CONNECTION=database
WHATSAPP_QUEUE=whatsapp
```

El cliente Laravel sólo permite HTTP sobre `127.0.0.1`, sin credenciales en URL ni redirecciones. Los tiempos se limitan internamente a 5 segundos para conectar y 20 para la respuesta. Mantener 15/35/90 segundos respectivamente para petición, worker y `retry_after`; si cambia la conexión de cola, verificar también su tiempo de visibilidad. Las conexiones `sync` y `null` se rechazan para evitar envíos en la operación principal.

Con respaldo de BD y siguiendo el despliegue habitual, ejecutar **sólo esta migración aditiva**:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan migrate --path=database/migrations/2026_10_10_120000_create_whatsapp_dispatches.php --force
/opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan config:cache
```

La migración crea `whatsapp_dispatches`, registra el módulo y crea `whatsapp.manage`, asignándolo al rol `admin` existente. Todas las rutas exigen simultáneamente usuario verificado, rol admin y ese permiso. Para restringir administradores, administrar `whatsapp.manage` con las herramientas existentes de roles/permisos. Un usuario de otro rol con el permiso sigue sin tener acceso. La reversión conserva tabla e historial; para retirar el servicio usar `WHATSAPP_ENABLED=false`, no borrar datos ni ejecutar migraciones destructivas. La migración admite volver a ejecutarse tras esa reversión conservadora.

## 2. Inicio y recuperación: tarea del proveedor

`services/whatsapp/deploy/sga-whatsapp.service` es una **plantilla**, no una instalación realizada. El proveedor debe comprobar usuario/grupo, las rutas y el sandbox de su servidor, copiarla a `/etc/systemd/system/`, y ejecutar con privilegios administrativos:

```bash
systemctl daemon-reload
systemctl enable --now sga-whatsapp
systemctl status sga-whatsapp --no-pager
```

La plantilla limita escrituras al directorio persistente, reinicia ante caída cada 15 segundos y limita arranques repetidos. Para despliegue o rotación del token: `systemctl restart sga-whatsapp`. Para diagnóstico: `journalctl -u sga-whatsapp -n 50 --no-pager`. Registrar sólo eventos fijos; no habilitar depuración de Baileys/libsignal ni imprimir objetos de sesión. Si un fallo repetido activa el límite, corregirlo y usar `systemctl reset-failed sga-whatsapp` antes de iniciar. El supervisor puede tardar más de 30 segundos en recuperar un bloqueo tras una caída abrupta.

Si systemd no está disponible, el proveedor debe ofrecer un mecanismo equivalente: una instancia, inicio en boot, reinicio ante caída, usuario de la cuenta, umask 077, rutas privadas y logs limitados. Una ventana SSH, `nohup`, un cron de arranque o Passenger sin estas garantías no constituyen recuperación comprobada.

Para una comprobación breve en primer plano, con el supervisor detenido y sin otra instancia:

```bash
cd /home/slepac/apps/slep_postulaciones/services/whatsapp
/opt/cpanel/ea-nodejs22/bin/node --env-file=/home/slepac/.config/sga-whatsapp/service.env src/index.js
```

No usarlo como mecanismo permanente. El bloqueo de la sesión rechaza una segunda instancia. Una sesión nueva queda en `idle`; no inicia vinculación hasta la acción administrativa. Una sesión ya vinculada se recupera después del reinicio. Un cierre de sesión, conflicto de dispositivo o más de seis reconexiones suspende los intentos hasta una acción manual. Conexiones breves e inestables no reinician el contador.

## 3. Cola: usuario cPanel o proveedor

Comprobar que las tablas de cola existentes estén instaladas. No crear otra cola síncrona ni cambiar las colas de correo. El job lleva únicamente el UUID: teléfono y texto se cifran con `APP_KEY` en la tabla y se eliminan al terminar. Conservar `APP_KEY` durante despliegues; los pendientes dependen de ella. No activar listeners que registren contenido ni capturen cuerpos HTTP de estas rutas.

Si el usuario tiene Cron Jobs y `/usr/bin/flock`, agregar cada minuto esta tarea con las rutas verificadas:

```cron
* * * * * /usr/bin/flock -n /home/slepac/.local/share/sga-whatsapp/queue-worker.lock /opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan queue:work database --queue=whatsapp --stop-when-empty --max-time=50 --timeout=35 --tries=5
```

No solapar workers. Si se usa Redis/SQS/otra conexión admitida, ajustar el nombre de conexión tanto en configuración como en el comando. Alternativamente, el proveedor puede supervisar un worker permanente dedicado a `whatsapp`. Después de cambiar código/configuración:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan queue:restart
```

Los fallos de red/desconexión se reintentan hasta cinco veces con esperas de 30, 90, 300 y 900 segundos. Los jobs nunca impiden el trámite o correo original. El puente reserva durablemente el UUID **antes** de enviar: peticiones repetidas devuelven el resultado guardado. Si se pierde la respuesta después de comenzar el envío, se marca **Resultado incierto** y no se reenvía automáticamente. Esto prioriza evitar duplicados; no garantiza entrega exactamente una vez. “Enviado” no confirma recepción ni lectura del destinatario. Un agotamiento de reintentos también queda incierto de forma conservadora.

No borrar ni recrear `state.sqlite`: también conserva las claves de deduplicación, incluso al desvincular. Para respaldarlo, detener el proceso y copiar el directorio completo en un almacenamiento privado/cifrado, preservando permisos y su coherencia con `whatsapp_dispatches`. No restaurar copias atrasadas ni purgar ese historial sin evaluar los mensajes pendientes, porque se perdería protección frente a duplicados. Cualquier política futura de retención debe coordinar ambos registros.

## 4. Diagnóstico, activación y prueba real

Sin token, este comando debe devolver **401**, sin QR ni estado:

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3210/v1/status
```

Para consultar autenticado sin poner el token en argumentos ni mostrar QR:

```bash
cd /home/slepac/apps/slep_postulaciones/services/whatsapp
/opt/cpanel/ea-nodejs22/bin/node --env-file=/home/slepac/.config/sga-whatsapp/service.env src/check-status.js
```

| Síntoma | Revisar |
| --- | --- |
| `startup_failed` o proceso detenido | Node, token y puerto válidos, dependencia nativa, propietario/permisos, límite del supervisor, segunda instancia o bloqueo aún vigente |
| “Servicio no disponible” | Proceso activo, mismo token en ambos entornos, config cache Laravel, puerto y acceso loopback desde PHP; no publicar el puerto para resolverlo |
| QR no aparece/conexión cae | Egreso DNS/TLS/WebSocket, versión compatible, restricciones de cuenta, estado pausado; nunca incluir QR/credenciales en diagnósticos |
| En cola sin avanzar | Cron/worker dedicado, conexión y nombre `whatsapp`, tablas de cola, PHP 8.3, `retry_after` |
| Resultado incierto | Revisar el teléfono destinatario antes de una prueba nueva; no borrar UUID/ledger ni forzar reenvíos |
| Desvinculación estando desconectado | El olvido local no garantiza cerrar el dispositivo remoto: eliminar además el dispositivo del SGA en Messenger |

Tras comprobar dependencias, supervisor, autenticación y cola, el usuario autorizado puede cambiar **sólo `WHATSAPP_ENABLED=true`**, reconstruir config cache y reiniciar el worker. Entrar como administrador con `whatsapp.manage`, abrir la pantalla, pulsar **Generar / renovar QR**, escanear desde Messenger y esperar **Conectado**. Esto aún no activa eventos automáticos.

Ingresar expresamente el número de un destinatario de prueba que haya aceptado recibirlo, en formato internacional `+` y código de país, y un texto breve sin información sensible. Pulsar enviar una vez; verificar el estado del UUID y la recepción en el teléfono. La pantalla conserva la misma clave ante doble clic, reenvío o pérdida de respuesta. Para una prueba diferente, recargar la página genera otra clave; hacerlo sólo tras revisar el resultado anterior.

La integración por QR no oficial depende de cambios de WhatsApp y puede perder compatibilidad o provocar restricciones de cuenta. El operador debe aceptar esa limitación del método elegido y monitorear la sesión. No se ofrece ni se afirma un servicio de producción validado.

## Eventos automáticos: decisión pendiente

Actualmente se entrega **el canal y el envío de prueba**, sin nuevos envíos automáticos. Falta definir eventos concretos, destinatarios autorizados, preferencias/consentimiento, formato del contenido y tratamiento de datos. No se interpreta `telefono1`, `telefono2`, `telefono` ni `enviar_notificacion` genérico como consentimiento para WhatsApp.

Para incorporar después un evento, conservar sus correos/trámites y agregar `App\Notifications\Channels\WhatsAppChannel` a los canales apropiados. El notificable debe tener una ruta explícita `routeNotificationForWhatsApp($notification)` que valide su preferencia. La notificación debe implementar `toWhatsApp($notifiable)` y devolver `new WhatsAppText($texto, $claveEstable, $evento)`, de `App\Notifications\Messages`. La clave debe identificar **evento + instancia histórica + destinatario**, ser estable en todos los reintentos y tener hasta 128 caracteres; no incluir teléfonos ni datos personales en ella. Texto: máximo 1000 caracteres; evento: máximo 100. El canal encola su propio job, sin asumir que la notificación ya implemente `ShouldQueue`. Si la notificación se encola por separado y contiene datos personales, aplicar el cifrado de jobs de Laravel también a esa notificación.

## Validación disponible y archivos

Pruebas: `tests/Feature/WhatsAppIntegrationTest.php`, `services/whatsapp/test/bridge.test.js` y `tests/js/whatsapp-admin.test.mjs`. Laravel usa SQLite en memoria y fakes HTTP/cola; Node usa credenciales sintéticas y sockets simulados. No usar estas pruebas contra producción.

Resultados locales, alcance de la comprobación HTTP real y verificaciones pendientes: [WHATSAPP_VALIDACION.md](WHATSAPP_VALIDACION.md).

```bash
php vendor/phpunit/phpunit/phpunit tests/Feature/WhatsAppIntegrationTest.php --do-not-cache-result
cd services/whatsapp
npm test
npm run check
```

Desde la raíz: `node --test tests/js/whatsapp-admin.test.mjs`, `php artisan route:list --path=admin/whatsapp -vv`, `php artisan optimize:clear` y `npm run build`. Ejecutar `php -l` sobre los PHP nuevos/modificados y revisar `git diff`. En el entorno local no está disponible MySQL: la limpieza de caché se verifica usando `CACHE_STORE=file`; la limpieza de la caché real y la migración MySQL quedan para el despliegue autorizado. No se han ejecutado migraciones sobre la BD local real.

| Grupo | Archivos nuevos/modificados |
| --- | --- |
| Laravel | `config/whatsapp.php`, `routes/whatsapp.php`, `bootstrap/app.php`, `app/Http/Controllers/Admin/WhatsAppController.php`, `app/Services/WhatsApp/{WhatsAppClient,WhatsAppDispatcher,WhatsAppException}.php`, `app/Jobs/SendWhatsAppMessage.php`, `app/Models/WhatsAppDispatch.php`, `app/Notifications/Channels/WhatsAppChannel.php`, `app/Notifications/Messages/WhatsAppText.php`, `database/migrations/2026_10_10_120000_create_whatsapp_dispatches.php` |
| Interfaz | `app/Support/SlepUiRegistry.php`, `resources/views/admin/whatsapp/index.blade.php`, `resources/css/whatsapp-admin.css`, `resources/js/whatsapp-admin.js`, `vite.config.js`, `public/build/manifest.json` y los dos assets compilados `whatsapp-admin-*` |
| Puente | `services/whatsapp/package.json`, `package-lock.json`, `.npmrc`, `src/{config,index,session,store,server,check-status}.js`, `deploy/sga-whatsapp.service`, `test/bridge.test.js` |
| Entrega | `.gitignore`, `config/changelog.php`, esta guía, `docs/WHATSAPP_VALIDACION.md`, `tests/Feature/WhatsAppIntegrationTest.php`, `tests/js/whatsapp-admin.test.mjs` |

Condiciones pendientes para escanear y enviar de verdad: confirmar toda la tabla del hosting, instalar/iniciar servicio y cola con el proveedor, migrar en MySQL, comprobar aislamiento/permisos Linux, configurar secretos, activar sólo el módulo, vincular Messenger y comprobar recepción de la prueba autorizada. La selección de eventos automáticos es una decisión posterior.

## Referencias oficiales consultadas

- [Baileys 7.0.0-rc14: release](https://github.com/WhiskeySockets/Baileys/releases/tag/v7.0.0-rc14), [README de esa versión](https://github.com/WhiskeySockets/Baileys/blob/v7.0.0-rc14/README.md) y [requisitos del paquete](https://github.com/WhiskeySockets/Baileys/blob/v7.0.0-rc14/package.json).
- [cPanel: instalación de Node.js](https://docs.cpanel.net/knowledge-base/general-server-administration/guide-to-node-js-installations/), [Terminal](https://docs.cpanel.net/cpanel/advanced/terminal-in-cpanel/) y [Application Manager](https://docs.cpanel.net/cpanel/software/application-manager/).
- [Passenger: límite de inactividad](https://www.phusionpassenger.com/library/config/apache/reference/#passengerpoolidletime).
