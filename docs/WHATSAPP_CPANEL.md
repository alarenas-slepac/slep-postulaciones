# WhatsApp por QR en cPanel: instalación paso a paso

Actualizada el 11 de octubre de 2026 para el Node instalado mediante nvm y el inicio del puente real desde Cron Jobs.

Esta guía se guarda en el repositorio local. Los comandos de instalación son para **Terminal de cPanel / Linux**, no para PowerShell de Windows. Ejecutar un paso, comprobar su resultado y continuar. Si un comando falla, resolverlo antes de avanzar. Los comandos no se han ejecutado desde este equipo contra producción.

## Estado actual y rutas

Según las comprobaciones compartidas por el operador, ya están disponibles **Terminal, Node v22.23.3, npm 10.9.9 y la carga de node:sqlite**. También comprobó el puente autenticado en `idle`, DNS, HTTPS 200 y apertura WebSocket saliente a WhatsApp. La prueba temporal de cron inició una instancia, la recuperó tras `crash` con `starts: 2` y terminó a los quince minutos; el cron de diagnóstico fue retirado. Después informó que la vinculación real funciona. No se ha comprobado desde este equipo el servidor ni se ha informado aún la recuperación de esa sesión tras reiniciar el puente o el servidor.

Si la instalación **ya está vinculada**, continuar en los pasos **11 y 16** para cambiar el mecanismo de inicio: conservar el token, `service.env`, la configuración de Laravel y el directorio de sesión. No repetir migraciones, regenerar credenciales, desvincular ni forzar otro QR. Los pasos iniciales corresponden a una instalación nueva.

| Elemento | Valor para esta instalación |
| --- | --- |
| Proyecto Laravel | /home/slepac/apps/slep_postulaciones |
| Servicio separado | /home/slepac/apps/slep_postulaciones/services/whatsapp |
| Node instalado con nvm | /home/slepac/.nvm/versions/node/v22.23.3/bin/node |
| Directorio de binarios Node/npm | /home/slepac/.nvm/versions/node/v22.23.3/bin |
| PHP 8.3, ruta que debe verificarse | /opt/cpanel/ea-php83/root/usr/bin/php |
| Configuración privada de Node | /home/slepac/.config/sga-whatsapp/service.env |
| Sesión y registro de deduplicación | /home/slepac/.local/share/sga-whatsapp |
| Control privado del inicio por cron | /home/slepac/.local/share/sga-whatsapp/cron-control |
| API privada | http://127.0.0.1:3210 |
| Administración Laravel | /admin/whatsapp |

La implementación requiere **Node >=22.13.0**. Baileys está fijado en **7.0.0-rc14**, con lockfile; no actualizarlo automáticamente ni usar instalaciones globales. SQLite emite una advertencia experimental en Node 22: el mensaje `ExperimentalWarning` acompañado de `SQLite disponible` no indica que la comprobación haya fallado.

La integración permanece **desactivada por defecto**. Entrega el canal y el envío de prueba, sin eventos automáticos ni destinatarios inferidos. Sólo se vincula el número de notificaciones registrado en **WhatsApp Messenger**; el número personal de WhatsApp Business conserva su configuración.

## Requisitos y responsables

Tener Terminal y Node no confirma que el hosting admita una conexión permanente.

| Requisito | Estado conocido | Qué falta / responsable |
| --- | --- | --- |
| Terminal de cPanel | Confirmado por el operador | SSH no ha sido comprobado; Terminal sirve para estos pasos |
| Node y npm | Confirmados: 22.23.3 y 10.9.9 | Usar la ruta absoluta de nvm en cron o supervisor |
| Carga de node:sqlite | Confirmada por el operador | Escritura, permisos y persistencia Linux: pasos 5, 10 y proveedor |
| Dependencias nativas de Baileys | Arranque y vinculación informados por el operador | Mantener versiones fijadas; proveedor: límites de recursos y compatibilidad al actualizar |
| PHP 8.3 y BD | Pendiente de verificación en esta instalación | Usuario: pasos 2 y 9; proveedor: confirmar binario absoluto y acceso a MySQL |
| Proceso Node continuo | Prueba temporal de cron correcta | Usuario: instalar y comprobar el lanzador real del paso 11; proveedor: política de procesos continuos |
| Reinicio automático | Recuperación del diagnóstico comprobada con starts: 2 | Usuario: reinicio del puente real; proveedor: recuperación después de reiniciar el servidor |
| Salida a WhatsApp | DNS, HTTPS 200 y apertura WebSocket comprobados | Proveedor: restricciones y disponibilidad sostenida por 443; HTTPS entrante del sitio es una condición diferente |
| Loopback PHP–Node | API autenticada comprobada; SGA informado funcionando | Paso 13 si falta; verificar restricciones de PHP web/CageFS cuando cambie el hosting |
| Almacenamiento privado persistente | Pendiente | Proveedor: disco local, no efímero ni NFS; usuario: permisos 700/600 |
| Cola dedicada | Pendiente | Usuario/proveedor: tablas existentes, cron o worker supervisado, pasos 9 y 12 |
| QR / sesión real | Operador informó que funciona | Usuario: conservar sesión y comprobar recuperación sin QR en el paso 16 |
| Recepción de mensaje real | Sin resultado detallado compartido | Operador autorizado: prueba expresa del paso 15 y cola del paso 12 |

**Passenger por sí solo no garantiza este servicio permanente.** No iniciar clusters, varias instancias de Node ni copias activas de la misma sesión. El paso 11 usa un lanzador con bloqueo, control de detención y límite persistente de arranques. Cron comprobará una vez por minuto si debe iniciar el proceso; el proceso permanece activo entre ejecuciones. El mecanismo real requiere su propia validación, aunque la prueba temporal haya pasado.

Si el hosting no autoriza el proceso continuo, hace falta que el proveedor habilite un supervisor permitido **en el mismo servidor**, o trasladar SGA y puente a un servidor/VPS con ese control. Un puente remoto cambia el requisito de mismo servidor y exige otro transporte seguro; esta implementación sólo admite loopback.

## Paso 1. Comprobar el Node que ya instalaste — usuario cPanel

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node --version
export PATH="/home/slepac/.nvm/versions/node/v22.23.3/bin:$PATH"
npm --version
/home/slepac/.nvm/versions/node/v22.23.3/bin/node -e "require('node:sqlite'); console.log('SQLite disponible')"
~~~

Resultados ya informados: `v22.23.3`, `10.9.9` y `SQLite disponible`. Puede aparecer la advertencia experimental.

El Node administrado por cPanel en `/opt/cpanel/ea-nodejs20/bin/node` seguirá siendo Node 20. La instalación de nvm convive con él. Cron y systemd no cargan `nvm use` automáticamente: esta guía y la plantilla usan el binario absoluto de Node 22.23.3.

## Paso 2. Verificar PHP 8.3 — usuario cPanel / proveedor

~~~bash
/opt/cpanel/ea-php83/root/usr/bin/php -v
~~~

Debe mostrar **PHP 8.3.x**. Si no existe o muestra otra versión, pedir al proveedor la ruta correcta y sustituirla en todos los comandos PHP de esta guía y en el cron. No depender de `$PHP83`, de `php` sin ruta ni del Composer predeterminado del servidor.

## Paso 3. Comprobar los archivos desplegados — usuario cPanel

Aplicar el despliegue habitual del SGA con respaldo de BD. Conservar la configuración privada y los archivos de usuarios; desplegar solamente los archivos nuevos/modificados de la integración, manteniendo sus rutas. No subir `node_modules`, credenciales de sesión, logs ni reemplazar el `.env` del servidor por uno local.

~~~bash
ls -l /home/slepac/apps/slep_postulaciones/services/whatsapp/package.json
ls -l /home/slepac/apps/slep_postulaciones/services/whatsapp/package-lock.json
ls -l /home/slepac/apps/slep_postulaciones/services/whatsapp/src/index.js
ls -l /home/slepac/apps/slep_postulaciones/database/migrations/2026_10_10_120000_create_whatsapp_dispatches.php
~~~

Si una ruta no existe, completar el despliegue antes de instalar. El lanzador de cron está en `services/whatsapp/deploy/cron-service.mjs`; la alternativa systemd del proveedor sigue disponible en `services/whatsapp/deploy/sga-whatsapp.service`.

El dominio debe apuntar al **public/** de Laravel, nunca al repositorio completo ni al servicio Node. Las dependencias de Composer del SGA no cambian. Los assets Vite de la integración ya están compilados; cualquier reconstrucción se hace en desarrollo.

## Paso 4. Instalar dependencias y comprobar Baileys — usuario cPanel

Copiar el bloque completo: los `&&` evitan instalar en otra carpeta si falla el cambio de directorio.

~~~bash
cd /home/slepac/apps/slep_postulaciones/services/whatsapp &&
export PATH="/home/slepac/.nvm/versions/node/v22.23.3/bin:$PATH" &&
npm ci --omit=dev &&
/home/slepac/.nvm/versions/node/v22.23.3/bin/node --input-type=module -e "await import('@whiskeysockets/baileys'); await import('node:sqlite'); console.log('Dependencias disponibles')"
~~~

Esperado: **Dependencias disponibles**. Esta importación no vincula un teléfono ni envía mensajes. Si falla un módulo nativo, faltan recursos o el hosting impide ejecutarlo, resolverlo con el proveedor. No usar `--force` para omitir el requisito de Node ni cambiar la versión de Baileys como solución automática.

## Paso 5. Preparar directorios privados — usuario cPanel

~~~bash
umask 077
mkdir -p /home/slepac/.config/sga-whatsapp /home/slepac/.local/share/sga-whatsapp
chmod 700 /home/slepac/.config/sga-whatsapp /home/slepac/.local/share/sga-whatsapp
~~~

Estos directorios quedan fuera del document root y pertenecen a la cuenta. El proveedor debe confirmar que el segundo usa almacenamiento local persistente.

## Paso 6. Crear service.env sin sobrescribirlo — usuario cPanel

Si ya lo creaste, el bloque lo conserva. Si no existe, lo crea con el puerto, el directorio privado y un marcador de token pendiente.

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node <<'NODE'
const fs = require('node:fs');
const file = '/home/slepac/.config/sga-whatsapp/service.env';
try {
    fs.writeFileSync(file,
        'WHATSAPP_PORT=3210\n' +
        'WHATSAPP_DATA_DIR=/home/slepac/.local/share/sga-whatsapp\n' +
        'WHATSAPP_SERVICE_TOKEN=<TOKEN_PENDIENTE>\n',
        { mode: 0o600, flag: 'wx' });
} catch (error) {
    if (error.code !== 'EEXIST') throw error;
}
fs.chmodSync(file, 0o600);
console.log('Archivo privado preparado; si existia, se conservo.');
NODE
~~~

Usar **Administrador de archivos de cPanel → Configuración → Mostrar archivos ocultos** y su editor privado para comprobar las dos primeras variables. No publicar el archivo ni mostrar su contenido completo en Terminal.

~~~dotenv
WHATSAPP_PORT=3210
WHATSAPP_DATA_DIR=/home/slepac/.local/share/sga-whatsapp
WHATSAPP_SERVICE_TOKEN=<TOKEN_PENDIENTE>
~~~

El paso siguiente reemplaza el marcador: no introducir literalmente `<TOKEN_PENDIENTE>` como credencial definitiva.

## Paso 7. Generar y guardar el token sin mostrarlo — usuario cPanel

El bloque genera **32 bytes / 64 caracteres hexadecimales** dentro del proceso. El valor no aparece en la salida, el historial ni los argumentos del comando. Conserva un token hexadecimal válido si repites el paso y mantiene las demás variables.

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node <<'NODE'
const fs = require('node:fs');
const { randomBytes } = require('node:crypto');
const { parseEnv } = require('node:util');
const file = '/home/slepac/.config/sga-whatsapp/service.env';

fs.chmodSync('/home/slepac/.config/sga-whatsapp', 0o700);
fs.chmodSync(file, 0o600);
const text = fs.readFileSync(file, 'utf8');
const key = /^[ \t]*(?:export[ \t]+)?WHATSAPP_SERVICE_TOKEN[ \t]*=.*\r?$/gm;
if ([...text.matchAll(key)].length > 1) {
    throw new Error('Hay varias lineas de token; deja una sola mediante el editor privado.');
}
const previous = parseEnv(text).WHATSAPP_SERVICE_TOKEN ?? '';
const valid = /^[a-f0-9]{64}$/i.test(previous);
if (previous && !valid && !/^<[^>]+>$/.test(previous)) {
    throw new Error('El token existente tiene otro formato; revisalo sin compartirlo.');
}
const token = valid ? previous : randomBytes(32).toString('hex');
const cleaned = text.replace(key, '');
fs.writeFileSync(file, cleaned.trimEnd() + '\nWHATSAPP_SERVICE_TOKEN=' + token + '\n');
console.log('Listo: token guardado y permisos 600. El valor no se muestra.');
NODE
~~~

Esperado: **Listo: token guardado y permisos 600. El valor no se muestra.**

Si indica líneas duplicadas o un formato distinto, corregir esa línea mediante el editor privado y repetir. No pegar el token en esta guía, comandos, tickets, Git ni mensajes de soporte. No ejecutar comandos que lo impriman, como `cat service.env`.

Comprobar sólo los permisos:

~~~bash
stat -c '%a %n' /home/slepac/.config/sga-whatsapp
stat -c '%a %n' /home/slepac/.config/sga-whatsapp/service.env
stat -c '%a %n' /home/slepac/.local/share/sga-whatsapp
~~~

Esperado: **700**, **600**, **700** respectivamente.

## Paso 8. Configurar Laravel y comprobar que comparte el token — usuario cPanel

En el editor privado de cPanel, abrir `/home/slepac/.config/sga-whatsapp/service.env` y copiar **únicamente los 64 caracteres** después de `WHATSAPP_SERVICE_TOKEN=`.

Abrir el `.env` productivo existente en `/home/slepac/apps/slep_postulaciones/.env`. El operador agrega o actualiza sólo las variables siguientes, dejando una línea por variable y preservando el resto de la configuración. No reemplazar el archivo ni versionarlo.

~~~dotenv
WHATSAPP_ENABLED=false
WHATSAPP_SERVICE_URL=http://127.0.0.1:3210
WHATSAPP_SERVICE_TOKEN=<PEGAR_AQUI_EL_MISMO_TOKEN_DE_64_CARACTERES>
WHATSAPP_CONNECT_TIMEOUT=3
WHATSAPP_TIMEOUT=15
WHATSAPP_QUEUE_CONNECTION=database
WHATSAPP_QUEUE=whatsapp
~~~

Reemplazar únicamente el marcador del token por su valor real, guardar y cerrar los editores. Mantener **WHATSAPP_ENABLED=false** hasta el paso 14. No cambiar `APP_KEY`, SMTP, la cola predeterminada ni otras variables del SGA.

Este comando **lee** ambos archivos y comprueba el token sin imprimirlo; no modifica el `.env`:

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node <<'NODE'
const fs = require('node:fs');
const { parseEnv } = require('node:util');
const bridge = parseEnv(fs.readFileSync('/home/slepac/.config/sga-whatsapp/service.env', 'utf8'));
const laravelText = fs.readFileSync('/home/slepac/apps/slep_postulaciones/.env', 'utf8');
const laravel = parseEnv(laravelText);
const lines = laravelText.match(/^[ \t]*(?:export[ \t]+)?WHATSAPP_SERVICE_TOKEN[ \t]*=.*$/gm) ?? [];
if (lines.length !== 1 || !/^[a-f0-9]{64}$/i.test(bridge.WHATSAPP_SERVICE_TOKEN ?? '') ||
    bridge.WHATSAPP_SERVICE_TOKEN !== laravel.WHATSAPP_SERVICE_TOKEN) {
    throw new Error('Los tokens no coinciden o hay lineas duplicadas; corregir en el editor privado.');
}
if (laravel.WHATSAPP_ENABLED !== 'false' || laravel.WHATSAPP_SERVICE_URL !== 'http://127.0.0.1:3210') {
    throw new Error('Mantener WhatsApp desactivado y configurar la URL privada indicada.');
}
console.log('Token coincidente. Integracion desactivada. No se muestran credenciales.');
NODE
~~~

## Paso 9. Migración aditiva, permisos y configuración — usuario cPanel

Con respaldo de BD y dentro del despliegue habitual, ejecutar **sólo la migración de WhatsApp**:

~~~bash
/opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan migrate --path=database/migrations/2026_10_10_120000_create_whatsapp_dispatches.php --force
~~~

La migración crea `whatsapp_dispatches`, registra el módulo y crea `whatsapp.manage`, asignándolo al rol `admin` existente. Conserva el historial al revertir. No ejecutar `migrate:fresh`, `db:wipe`, rollback ni todas las migraciones pendientes como parte de estos pasos.

~~~bash
/opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan route:list --path=admin/whatsapp -vv
~~~

Esperado: migración correcta y **cinco rutas** protegidas por `auth`, `verified`, `ensure.role:admin` y `permission:whatsapp.manage`.

La pantalla exige simultáneamente usuario verificado, rol **admin** y permiso **whatsapp.manage**. Administrar ese permiso con las herramientas existentes si se debe restringir el acceso entre administradores. No habilitar el módulo a otros perfiles para resolver un error 403.

Comprobar las tablas de cola sin modificar datos:

~~~bash
/opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan tinker --execute='foreach (["jobs", "failed_jobs", "whatsapp_dispatches"] as $table) { echo $table.": ".(\Illuminate\Support\Facades\Schema::hasTable($table) ? "OK" : "FALTA").PHP_EOL; } echo "retry_after: ".config("queue.connections.database.retry_after").PHP_EOL;'
~~~

Las tres tablas deben mostrar **OK** y `retry_after` debe ser **mayor que 35**; el valor predeterminado es 90. Si falta una tabla, revisar con el responsable del SGA su migración existente antes de crearla. La migración `0001_01_01_000002_create_jobs_table.php` crea varias tablas: no reaplicarla a ciegas si alguna ya existe.

El canal usa conexión persistente `database` y cola `whatsapp`. No usar `sync` ni `null`.

## Paso 10. Prueba breve de arranque y autenticación — usuario cPanel

Hacerla con el mecanismo de inicio detenido y **sin otra instancia del puente**. Si se usa cron, aplicar `stop` del paso 16; si se usa systemd, coordinar su detención con el proveedor. El bloqueo de sesión rechaza una segunda instancia. Omitir el arranque manual si ya hay una instalación vinculada y sólo se cambia al lanzador del paso 11.

En una Terminal A:

~~~bash
cd /home/slepac/apps/slep_postulaciones/services/whatsapp &&
/home/slepac/.nvm/versions/node/v22.23.3/bin/node --env-file=/home/slepac/.config/sga-whatsapp/service.env src/index.js
~~~

Esperado: un evento fijo **service_started**. La primera sesión queda `idle`; no conecta con WhatsApp hasta generar el QR. Si existe una sesión vinculada anterior, el servicio intentará recuperarla.

Mantener esa Terminal abierta y, en otra Terminal B, comprobar el rechazo sin token:

~~~bash
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:3210/v1/status
~~~

Esperado: **401**. Después consultar de forma autenticada:

~~~bash
cd /home/slepac/apps/slep_postulaciones/services/whatsapp &&
/home/slepac/.nvm/versions/node/v22.23.3/bin/node --env-file=/home/slepac/.config/sga-whatsapp/service.env src/check-status.js
~~~

Esperado para una sesión nueva: **Estado: idle**. Este diagnóstico no imprime el token, QR ni mensajes.

Cuando ambas comprobaciones pasen, detener la Terminal A con **Ctrl+C** antes del inicio del paso 11. Si no puedes abrir una segunda Terminal, realizar los diagnósticos después de guardar el cron. Esta prueba breve no valida persistencia al cerrar Terminal ni después de reiniciar el servidor.

## Paso 11. Instalar el inicio mediante Cron Jobs — usuario cPanel

La cuenta ya comprobó que Cron Jobs puede iniciar y recuperar el proceso de diagnóstico. Ahora se instala **el lanzador del puente real**, sin el vencimiento de quince minutos de aquella prueba. Usa el mismo `service.env`, puerto **3210** y sesión persistente; no usa `cron-probe.mjs` ni crea otra sesión.

### 11.1. Desplegar el lanzador y detener el arranque anterior

Subir únicamente los archivos nuevos/modificados del parche, conservando sus rutas. Comprobar:

~~~bash
ls -l /home/slepac/apps/slep_postulaciones/services/whatsapp/deploy/cron-service.mjs
~~~

Las dependencias no cambian: el lanzador reutiliza `proper-lockfile`, ya comprobado. No requiere un `flock` externo para Node. Si el puente está ejecutándose en Terminal, detener **esa instancia** con **Ctrl+C** y esperar su cierre antes de guardar el cron. Si existe systemd, Passenger u otro mecanismo que arranque el puente, detenerlo y deshabilitar su inicio con su responsable. Usar **un solo mecanismo de inicio**. No usar `pkill node` ni borrar bloqueos de una instancia activa.

La sesión y el token se conservan. En una instalación ya vinculada, no deshabilitar ni modificar el `.env` como parte de este cambio de arranque. El cron temporal de `cron-probe.mjs` ya debe estar eliminado.

### 11.2. Habilitar el control de cron

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node --env-file=/home/slepac/.config/sga-whatsapp/service.env /home/slepac/apps/slep_postulaciones/services/whatsapp/deploy/cron-service.mjs start
~~~

Esperado: `enabled: true`, `blocked: false`, `runner_running: false`, `recent_starts: 0`. **start prepara el control; el siguiente cron inicia Node**. Se crea el subdirectorio privado `cron-control` dentro de la sesión, con permiso 700. Su configuración contiene sólo marcadores e intentos; el token sigue en `service.env`.

Si indica que existe una instancia activa del lanzador, consultar `status`; no forzar la eliminación de bloqueos. Este control no detiene un puente iniciado manualmente o por systemd: aplicar primero el apartado 11.1. `start` se usa con el lanzador detenido y, después de corregir la causa, para reabrir su límite de arranques.

### 11.3. Crear el cron del puente

En **cPanel → Cron Jobs**, seleccionar **una vez por minuto**: los cinco campos de tiempo son `*`. Pegar **sólo este comando** en Comando:

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node --env-file=/home/slepac/.config/sga-whatsapp/service.env /home/slepac/apps/slep_postulaciones/services/whatsapp/deploy/cron-service.mjs run >/dev/null 2>&1
~~~

Crear una sola entrada. El comando usa rutas absolutas; cron no necesita cargar `nvm use` ni mantener una Terminal abierta. El lanzador mantiene el bloqueo y carga el puente **en el mismo proceso Node**. Las ejecuciones siguientes salen sin iniciar otra instancia ni abrir otra sesión. Si ese proceso cae, la siguiente ejecución de cron vuelve a iniciarlo; una caída abrupta puede dejar el bloqueo fresco durante algo más de 30 segundos, por lo que la recuperación puede tardar uno o dos minutos.

No usar `&`, `nohup`, `timeout`, el puerto 3211 ni el archivo del diagnóstico. No iniciar `src/index.js` manualmente mientras este cron esté activo.

**Límite de reinicios:** se admiten como máximo cinco arranques dentro de diez minutos. Al intentar exceder ese límite se guarda `blocked: true` y cron deja de arrancar el puente hasta que el operador corrija la causa y ejecute `start`. Los arranques fallidos y los reinicios solicitados cuentan; un proceso que permanece estable no se vuelve a iniciar cada minuto. La pausa de reconexión de WhatsApp es un control separado que se resuelve desde la pantalla administrativa.

### 11.4. Verificar el puente real y cerrar Terminal

Esperar uno o dos minutos y consultar ambos estados:

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node --env-file=/home/slepac/.config/sga-whatsapp/service.env /home/slepac/apps/slep_postulaciones/services/whatsapp/deploy/cron-service.mjs status
/home/slepac/.nvm/versions/node/v22.23.3/bin/node --env-file=/home/slepac/.config/sga-whatsapp/service.env /home/slepac/apps/slep_postulaciones/services/whatsapp/src/check-status.js
~~~

Esperado: `enabled: true`, `blocked: false`, `runner_running: true`; una sesión ya vinculada debe llegar a **Estado: connected** sin otro QR. En una instalación nueva será `idle`. `runner_running` indica un bloqueo reciente del lanzador, no autentica WhatsApp: comprobar siempre el segundo comando y la pantalla del SGA.

Cerrar Terminal, esperar dos minutos, volver a abrirla y repetir ambos comandos. El puente debe seguir conectado y no debe haber arranques adicionales dentro de ese intervalo. Confirmar además rechazo sin token, como en el paso 10. No probar recuperación con mensajes pendientes; seguir el `restart` del paso 16.

### 11.5. Logs, límites del hosting y alternativa del proveedor

~~~bash
tail -n 30 /home/slepac/.local/share/sga-whatsapp/cron-control/events.log
~~~

El lanzador registra sólo nombres de eventos conocidos y su fecha; descarta credenciales, QR, cuerpos y errores sin filtrar de dependencias. Mantiene `events.log` y `events.previous.log` de unos 64 KiB cada uno, con permiso 600. La salida restante se descarta para evitar correos de cron con datos sensibles. `service_started` confirma el arranque de la API; el estado autenticado confirma la conexión. Si no se creó el log, comprobar rutas, permisos, `service.env`, dependencias y registro del cron.

El usuario cPanel puede instalar, detener, habilitar y reiniciar este mecanismo sin privilegios administrativos. **Siguen correspondiendo al proveedor** las políticas de procesos continuos, restricciones de recursos/CageFS, almacenamiento local persistente y recuperación después de reiniciar el servidor. Cuando el daemon de cron vuelva a funcionar tras un reinicio, intentará arrancar el puente en el siguiente minuto, salvo que esté detenido o bloqueado; aún debe comprobarse en este hosting. No hace falta agregar un segundo cron `@reboot`.

Si el proveedor mata procesos prolongados por política, cron no elimina esa restricción: debe autorizar el proceso o instalar un supervisor permitido en el mismo servidor. Se conserva la alternativa `services/whatsapp/deploy/sga-whatsapp.service`. Para usarla, detener el lanzador, retirar **sólo su cron** y coordinar con el proveedor; no mantener ambos activos. Los comandos siguientes son exclusivamente del proveedor, después de revisar usuario, Node, rutas y permisos:

~~~bash
install -m 644 /home/slepac/apps/slep_postulaciones/services/whatsapp/deploy/sga-whatsapp.service /etc/systemd/system/sga-whatsapp.service
systemd-analyze verify /etc/systemd/system/sga-whatsapp.service
systemctl daemon-reload
systemctl enable --now sga-whatsapp
systemctl status sga-whatsapp --no-pager
~~~

Solicitud sin credenciales, sólo si hace falta esa alternativa:

> La cuenta slepac ya comprobó DNS/HTTPS/WebSocket saliente y Cron Jobs con Node /home/slepac/.nvm/versions/node/v22.23.3/bin/node. Necesitamos mantener un único puente continuo, con sesión privada persistente en /home/slepac/.local/share/sga-whatsapp y API sólo en 127.0.0.1:3210. ¿Confirman que permiten el proceso, sus recursos y almacenamiento local, y que cron vuelve a ejecutarse tras reiniciar el servidor? Si su política impide mantenerlo mediante cron, ¿pueden instalar la unidad sga-whatsapp.service en este mismo servidor, usando la configuración privada existente sin compartir sus credenciales?

## Paso 12. Configurar la cola de WhatsApp — usuario cPanel / proveedor

Verificar `flock`:

~~~bash
/usr/bin/flock --version
~~~

Antes de generar mensajes, probar que el worker arranca y sale al quedar vacío:

~~~bash
/usr/bin/flock -n /home/slepac/.local/share/sga-whatsapp/queue-worker.lock /opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan queue:work database --queue=whatsapp --stop-when-empty --max-time=50 --timeout=35 --tries=5
~~~

El worker procesa cualquier job existente en esa cola: esta comprobación corresponde a una instalación nueva, sin envíos pendientes y con `WHATSAPP_ENABLED=false`.

En **cPanel → Cron Jobs**, elegir **una vez por minuto**: los cinco campos de tiempo son `*`. Pegar **sólo este comando** en el campo Comando:

~~~bash
/usr/bin/flock -n /home/slepac/.local/share/sga-whatsapp/queue-worker.lock /opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan queue:work database --queue=whatsapp --stop-when-empty --max-time=50 --timeout=35 --tries=5
~~~

No crear un segundo cron para el mismo worker. El bloqueo evita que los procesos de este comando se solapen. No cambiar las colas de correo existentes. Si el proveedor instala un worker permanente dedicado a `whatsapp`, usar ese mecanismo en lugar de este cron.

Conservar **15 / 35 / 90 segundos** como referencia para petición HTTP / timeout del worker / `retry_after`. Si se elige otra conexión persistente, ajustar tanto Laravel como el worker y su tiempo de visibilidad.

Después de modificar código o configuración:

~~~bash
/opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan queue:restart
~~~

## Paso 13. Comprobar comunicación desde Laravel — diagnóstico de sólo lectura

El diagnóstico de Node no prueba el acceso desde PHP. Este bloque carga la configuración efectiva de Laravel y consulta sólo el estado autenticado. Funciona con la integración habilitada o desactivada, no modifica configuración, no genera QR ni envía mensajes. Informa la activación y el uso de caché, y distingue errores sin imprimir credenciales, QR, cuerpos HTTP ni excepciones sin filtrar.

El bloque anterior exigía `WHATSAPP_ENABLED=false`: si se repetía después de vincular y habilitar el módulo, fallaba antes de consultar Node. No desactivar una instalación vinculada para diagnosticarla; usar este bloque:

~~~bash
/opt/cpanel/ea-php83/root/usr/bin/php <<'PHP'
<?php
$failure = 'ARRANQUE: no se pudo cargar Laravel; revisar rutas, dependencias y PHP 8.3.';
try {
    require '/home/slepac/apps/slep_postulaciones/vendor/autoload.php';
    $app = require '/home/slepac/apps/slep_postulaciones/bootstrap/app.php';
    $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    echo 'Integracion: '.(config('whatsapp.enabled') ? 'habilitada' : 'desactivada').PHP_EOL;
    echo 'Cache de configuracion: '.($app->configurationIsCached() ? 'si' : 'no').PHP_EOL;
    $failure = 'URL: configurar WHATSAPP_SERVICE_URL=http://127.0.0.1:3210 y revisar la cache de Laravel.';
    if (config('whatsapp.url') !== 'http://127.0.0.1:3210') {
        throw new \RuntimeException('configuration');
    }
    $token = (string) config('whatsapp.token');
    $failure = 'TOKEN: falta un token valido en la configuracion efectiva de Laravel; revisar el editor privado y la cache.';
    if (!preg_match('/^[A-Za-z0-9_-]{32,256}$/D', $token)) {
        throw new \RuntimeException('configuration');
    }
    $failure = 'CONEXION: PHP no pudo consultar Node; revisar cron, puerto 3210 y acceso loopback/CageFS.';
    $response = \Illuminate\Support\Facades\Http::withToken($token)
        ->connectTimeout(3)->timeout(15)->withoutRedirecting()
        ->get('http://127.0.0.1:3210/v1/status');
    $http = $response->status();
    if (in_array($http, [401, 403], true)) {
        $failure = 'AUTENTICACION: HTTP '.$http.'; revisar coincidencia del token efectivo de Laravel con el servicio Node y su cache.';
        throw new \RuntimeException('authentication');
    }
    if (!$response->successful()) {
        $failure = 'HTTP: respuesta '.$http.'; revisar servicio y ruta privada, sin publicar el puerto.';
        throw new \RuntimeException('communication');
    }
    $failure = 'RESPUESTA: HTTP '.$http.' sin un estado reconocido; revisar que responda el puente correcto.';
    $state = $response->json('state');
    $allowed = ['idle', 'connecting', 'qr', 'connected', 'reconnecting', 'paused', 'logged_out', 'unlinked'];
    if (!in_array($state, $allowed, true)) {
        throw new \RuntimeException('communication');
    }
    echo 'Laravel conecta autenticado. Estado: '.$state.PHP_EOL;
} catch (\Throwable $exception) {
    file_put_contents('php://stderr', $failure.PHP_EOL);
    exit(1);
}
PHP
~~~

Esperado para una sesión nueva: **Laravel conecta autenticado. Estado: idle**; para la sesión vinculada, normalmente **Estado: connected**. Un estado válido como `reconnecting` confirma comunicación autenticada, pero no conexión estable con WhatsApp.

Si falla, compartir únicamente estas líneas de salida. `AUTENTICACION` indica un rechazo HTTP 401/403; `CONEXION` indica que PHP no obtuvo respuesta; `URL` o `TOKEN` señalan configuración efectiva inválida. Si se corrigió el archivo privado de Laravel y muestra que usa caché, reconstruirla con el comando `config:cache` del paso 14; no basta con editar el archivo. Si Node responde pero PHP no, revisar CageFS/aislamiento con el proveedor. No publicar el puerto, rotar tokens, borrar la sesión ni generar un QR para resolver un error del diagnóstico.

La pantalla web sólo consultará el puente cuando la integración se habilite. Si PHP CLI y PHP web están aislados de forma diferente, comprobar también el estado de la pantalla en el siguiente paso antes de escanear.

## Paso 14. Habilitar únicamente el módulo administrativo — usuario autorizado

Para una instalación nueva, continuar cuando dependencias, autenticación, inicio del puente real, almacenamiento, cola y conexiones salientes estén comprobados. Mantener presentes las condiciones del proveedor indicadas en 11.5. Si el operador ya vinculó la cuenta y el módulo funciona, conservar su configuración al aplicar este parche.

En el editor privado del `.env` existente, cambiar **sólo**:

~~~dotenv
WHATSAPP_ENABLED=true
~~~

~~~bash
/opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan queue:restart
~~~

Entrar al SGA con usuario verificado, rol **admin** y permiso **whatsapp.manage**. Abrir **Administración → WhatsApp · Notificaciones**, o la ruta `/admin/whatsapp` bajo el dominio del SGA.

Debe mostrar el estado del servicio; una sesión nueva está `idle`. Si aparece **Servicio no disponible**, corregir proceso/token/cache/loopback antes de generar el QR. Si aparece un error de permisos, revisar el paso 9. Habilitar esta configuración **no activa eventos automáticos**.

## Paso 15. Vincular Messenger y enviar una prueba manual — operador autorizado

1. En la pantalla del SGA, pulsar **Generar / renovar QR**.
2. En el iPhone, abrir **WhatsApp Messenger del número de notificaciones**.
3. Ir a **Configuración → Dispositivos vinculados → Vincular un dispositivo**.
4. Escanear el QR mostrado en el SGA y esperar **Conectado**.
5. Si el QR expira, generar uno nuevo desde la misma pantalla.
6. Ingresar expresamente un destinatario de prueba que haya aceptado recibirla, con `+` y código de país, sin espacios. Escribir un texto breve sin datos sensibles.
7. Pulsar enviar una vez. Revisar el UUID y estado del envío en el SGA; con cron, el procesamiento puede comenzar en el siguiente minuto.
8. Comprobar recepción en el teléfono destinatario. **Enviado** confirma la aceptación del envío por el puente, no recepción ni lectura.
9. Para otra prueba, comprobar primero el resultado anterior y recargar la página para obtener una nueva clave.

La pantalla conserva la misma clave ante doble clic o pérdida de respuesta. Si aparece **Resultado incierto**, comprobar el teléfono antes de una nueva prueba: no borrar el UUID ni el registro persistente para forzar un reenvío.

No hacer cambios en WhatsApp Business del número personal. No fotografiar, compartir ni publicar el QR.

## Paso 16. Comprobar recuperación y mantener el servicio — usuario / proveedor

Con el mecanismo de cron del paso 11 y sin mensajes pendientes, solicitar un reinicio del único proceso:

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node --env-file=/home/slepac/.config/sga-whatsapp/service.env /home/slepac/apps/slep_postulaciones/services/whatsapp/deploy/cron-service.mjs restart
~~~

El lanzador detecta el marcador y envía SIGTERM **a su propio proceso**, dejando que el puente cierre sesión y SQLite; el siguiente cron lo inicia de nuevo. La orden no se ejecuta contra un PID externo ni contra un Node iniciado manualmente. Esperar uno o dos minutos y consultar:

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node --env-file=/home/slepac/.config/sga-whatsapp/service.env /home/slepac/apps/slep_postulaciones/services/whatsapp/deploy/cron-service.mjs status
/home/slepac/.nvm/versions/node/v22.23.3/bin/node --env-file=/home/slepac/.config/sga-whatsapp/service.env /home/slepac/apps/slep_postulaciones/services/whatsapp/src/check-status.js
~~~

Esperado: control habilitado, sin bloqueo y activo; **Estado: connected** sin otro QR. Comprobar también la pantalla y el dispositivo vinculado en Messenger. Este resultado confirma la recuperación de sesión tras reiniciar el proceso. La recuperación después de reiniciar todo el servidor requiere una comprobación posterior del proveedor; no reiniciar el servidor ni otros servicios desde estos pasos.

Para detener el puente y evitar que cron lo vuelva a iniciar:

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node --env-file=/home/slepac/.config/sga-whatsapp/service.env /home/slepac/apps/slep_postulaciones/services/whatsapp/deploy/cron-service.mjs stop
~~~

Consultar `status` hasta ver `enabled: false` y `runner_running: false`. El cierre normal empieza en aproximadamente un segundo; si el puente no termina de cerrar, el lanzador limita la espera a 30 segundos. Un bloqueo abandonado tras un cierre abrupto puede seguir apareciendo fresco durante unos 30 segundos. No copiar ni respaldar SQLite hasta que el proceso haya terminado. **Eliminar el cron no detiene un proceso ya activo**: aplicar `stop` antes de retirar exclusivamente la entrada de `cron-service.mjs run`.

Para habilitarlo nuevamente, con el lanzador detenido y después de corregir cualquier causa de `blocked: true`:

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node --env-file=/home/slepac/.config/sga-whatsapp/service.env /home/slepac/apps/slep_postulaciones/services/whatsapp/deploy/cron-service.mjs start
~~~

El siguiente cron lo inicia. Si se eliminó la entrada, volver a guardar exactamente la del paso 11.3. No ejecutar `run` en Terminal para mantenerlo permanente. Para revisar eventos:

~~~bash
tail -n 30 /home/slepac/.local/share/sga-whatsapp/cron-control/events.log
~~~

No eliminar bloqueos mientras otra instancia esté activa. El lanzador pausa nuevos arranques al exceder cinco dentro de diez minutos y requiere `start` para reabrirlos. El puente limita por separado a seis los intentos de reconexión a WhatsApp; desconexiones definitivas o conflictos suspenden sus intentos hasta una acción administrativa. `start` no borra ese estado, no desvincula la cuenta ni recrea credenciales. Cierres breves e inestables no reinician el contador de conexión.

Si se eligió la alternativa systemd, **usar únicamente el mecanismo del proveedor**, con el cron del puente retirado:

~~~bash
systemctl restart sga-whatsapp
systemctl status sga-whatsapp --no-pager
~~~

El proveedor administra `stop`, `start`, el journal y `reset-failed` de esa unidad. El comando de control de cron no detiene una unidad systemd ni un proceso manual.

**Actualizaciones de Node:** si nvm instala otra revisión, `nvm which 22` puede dar una nueva ruta. Con cron, detener el lanzador con el binario todavía vigente, actualizar la ruta del comando de cron y de los controles, y habilitarlo con el nuevo binario antes de retirar Node 22.23.3. Con systemd, el proveedor adapta `ExecStart`, ejecuta `systemctl daemon-reload` y reinicia la unidad. No depender del alias interactivo de nvm.

**Rotación del token:** detener el puente con su mecanismo vigente, confirmar que terminó, cambiar el token en ambos entornos privados, reconstruir caché de Laravel, reiniciar worker, habilitar el lanzador y repetir la autenticación. El paso 7 conserva el token; no es un comando de rotación.

**Respaldo:** detener el puente y respaldar el directorio privado completo, incluido `state.sqlite` y sus WAL/SHM, en almacenamiento privado/cifrado. Conservar su coherencia con `whatsapp_dispatches`. No borrar ni recrear la base: también guarda la deduplicación, incluso tras desvincular. No restaurar un registro atrasado ni purgarlo sin evaluar mensajes pendientes. Conservar `APP_KEY`: los pendientes de Laravel dependen de ella.

**Desactivar:** el operador cambia `WHATSAPP_ENABLED=false` en el editor privado, reconstruye caché, reinicia worker y ejecuta `stop` del mecanismo de cron; si se usa systemd, el proveedor detiene la unidad. No usar rollback ni borrar datos para desactivar. Al desvincular sin conexión, eliminar además el dispositivo del SGA en Messenger: el olvido local no garantiza cerrar la sesión remota.

## Diagnóstico

| Síntoma | Acción |
| --- | --- |
| npm usa Node 20 / error de versión | Repetir el PATH del paso 4; no usar --force |
| ExperimentalWarning de SQLite | Esperado en Node 22; comprobar el mensaje de éxito y el código de salida |
| Error del módulo nativo / GLIBC / arquitectura | Resolver compatibilidad Linux y restricciones con el proveedor |
| No existe package.json | Completar despliegue; no instalar en la carpeta de inicio |
| Node no carga service.env | Revisar ruta, propietario, permisos 700/600 y variables con el editor privado |
| Token duplicado o con marcador | Pasos 7 y 8; dejar una línea y el mismo valor en ambos archivos |
| startup_failed / proceso detenido | Dependencias, token, puerto, permisos, segunda instancia o límite del supervisor |
| blocked: true / runner_limit | Revisar events.log y causa de los arranques; con el lanzador detenido, corregir y ejecutar start |
| runner_running: false | Revisar cron, binario absoluto, service.env, permisos, enabled y blocked; no iniciar otra instancia manual |
| Control activo pero API no responde | Puede estar iniciando; comprobar eventos, bloqueo de sesión, puerto y dependencias; no borrar state.sqlite |
| Diagnóstico sin token devuelve 200 | Detener el despliegue y revisar autenticación; debe ser 401 |
| Node funciona, Laravel no conecta | Token efectivo, config cache y acceso loopback/CageFS desde PHP |
| Pantalla desactivada | Esperado hasta el paso 14; no habilitar antes de completar requisitos |
| Error 403 | Usuario verificado, rol admin y permiso whatsapp.manage |
| QR no aparece / conexión cae | Supervisor, DNS/TLS/WebSocket saliente, versión fijada y estado pausado |
| En cola sin avanzar | Cron/worker dedicado, PHP 8.3, tablas, conexión database y cola whatsapp |
| Resultado incierto | Comprobar recepción manualmente; no forzar reenvíos ni borrar historial |

Los jobs reintentan fallos de desconexión hasta cinco veces, con esperas de 30, 90, 300 y 900 segundos. Llevan sólo UUID; teléfono y mensaje se cifran en BD y se eliminan al terminar. Un UUID reservado antes de enviar impide duplicados en Node. Si se pierde la respuesta o el envío queda incierto, no se reenvía automáticamente. Esto no garantiza entrega exactamente una vez.

Node escucha sólo en **127.0.0.1**. No crear un proxy público, túnel, regla de Apache ni apertura del puerto 3210. No habilitar depuración de Baileys/libsignal ni registrar credenciales, QR, objetos de sesión o cuerpos de mensajes.

## Eventos automáticos

Las solicitudes de reemplazo incorporan avisos al responsable de la siguiente etapa, desactivados por defecto mediante `WHATSAPP_REEMPLAZOS_ENABLED=false`, y selección individual mediante `funcionarios_ac_autorizados.notificar_whatsapp_reemplazos`. La matriz de eventos, regla reutilizable y activación paso a paso están en [WHATSAPP_NOTIFICACIONES_REGLAS.md](WHATSAPP_NOTIFICACIONES_REGLAS.md). Instalar la migración nueva y seleccionar las fichas antes de habilitar ese flag. No interpretar teléfonos históricos ni el campo genérico `enviar_notificacion` como consentimiento para WhatsApp. Los demás módulos requieren definir sus eventos y preferencias.

Para incorporar después un evento, conservar sus correos/trámites y agregar `App\Notifications\Channels\WhatsAppChannel` a sus canales. El notificable debe tener una ruta explícita `routeNotificationForWhatsApp($notification)` que valide su preferencia; la notificación debe implementar `toWhatsApp($notifiable)` y devolver `App\Notifications\Messages\WhatsAppText`.

La clave debe identificar evento + instancia histórica + destinatario, ser estable durante reintentos y tener como máximo 128 caracteres, sin teléfonos ni datos personales. Texto máximo: 1000 caracteres; evento máximo: 100. El canal encola su propio job. Si la notificación se encola por separado y contiene datos personales, aplicar también el cifrado de jobs de Laravel.

## Validación y límites

Resultados de implementación y comprobaciones de esta actualización: [WHATSAPP_VALIDACION.md](WHATSAPP_VALIDACION.md).

Las pruebas automatizadas son para desarrollo, con SQLite en memoria, HTTP/cola y sockets sintéticos; **no ejecutarlas contra producción ni conectar teléfonos en ellas**:

~~~bash
php vendor/phpunit/phpunit/phpunit tests/Feature/WhatsAppIntegrationTest.php --do-not-cache-result
cd services/whatsapp
npm test
npm run check
~~~

Desde la raíz en desarrollo: `node --test tests/js/whatsapp-admin.test.mjs`, `php artisan route:list --path=admin/whatsapp -vv`, `php artisan optimize:clear` y `npm run build`. Los PHP requieren PHP 8.3 y revisión con `php -l`; revisar diff y exclusión de credenciales, dependencias y archivos de usuarios.

No hay acceso directo de este agente al servidor. Las comprobaciones de Node, autenticación, DNS/HTTPS/WebSocket y cron proceden de las salidas compartidas por el operador; la vinculación real fue informada como funcionando. Esta actualización no ejecuta el nuevo lanzador contra producción: aún deben verificarse su instalación, supervivencia al cerrar Terminal, recuperación de la sesión y reinicio completo del servidor, política de procesos, almacenamiento y cola. No se presume recepción de mensajes a partir de `connected`. La conexión no oficial depende de cambios de WhatsApp y puede perder compatibilidad o provocar restricciones de cuenta.

## Referencias oficiales

- [nvm: instalación por usuario y uso por shell](https://github.com/nvm-sh/nvm/blob/v0.40.3/README.md).
- [Node 22: generador criptográfico randomBytes](https://nodejs.org/download/release/v22.22.0/docs/api/crypto.html#cryptorandombytessize-callback), [parseEnv](https://nodejs.org/download/release/v22.22.0/docs/api/util.html#utilparseenvcontent) y [SQLite](https://nodejs.org/download/release/v22.22.0/docs/api/sqlite.html).
- [Baileys 7.0.0-rc14](https://github.com/WhiskeySockets/Baileys/releases/tag/v7.0.0-rc14), [README](https://github.com/WhiskeySockets/Baileys/blob/v7.0.0-rc14/README.md) y [requisitos](https://github.com/WhiskeySockets/Baileys/blob/v7.0.0-rc14/package.json).
- [cPanel: Node.js](https://docs.cpanel.net/knowledge-base/general-server-administration/guide-to-node-js-installations/), [Terminal](https://docs.cpanel.net/cpanel/advanced/terminal-in-cpanel/) y [Application Manager](https://docs.cpanel.net/cpanel/software/application-manager/).
- [cPanel: Cron Jobs](https://docs.cpanel.net/cpanel/advanced/cron-jobs/) y [proper-lockfile: bloqueo, actualización y recuperación](https://github.com/moxystudio/node-proper-lockfile#readme).
- [Passenger: límite de inactividad](https://www.phusionpassenger.com/library/config/apache/reference/#passengerpoolidletime).
