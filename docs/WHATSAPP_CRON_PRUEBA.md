# Prueba temporal de Node mediante Cron Jobs en cPanel

Esta prueba responde a la cuenta `slepac`, con Node 22.23.3 instalado por nvm. No modifica producción, Laravel, `service.env`, la sesión de WhatsApp ni los números de Messenger/Business. El operador ejecuta los comandos en su hosting. El agente no tiene acceso directo al servidor.

Se ejecuta **un servidor de diagnóstico**, no el puente Baileys. No incluye rutas de QR ni envío. Usa exclusivamente `127.0.0.1:3211`, un token de prueba generado sin mostrarlo y la carpeta privada `/home/slepac/.local/share/sga-whatsapp-cron-test`. No abrir ese puerto al exterior. La prueba caduca 15 minutos después de prepararla y permite hasta cinco arranques; cron no renueva esos límites.

Permite comprobar inicio desde cron, supervivencia al cerrar Terminal, exclusión de instancias simultáneas y recuperación tras una salida simulada con código 1. No demuestra permiso contractual del hosting, recuperación tras reiniciar el servidor, conectividad WebSocket, funcionamiento del puente real, persistencia de la sesión ni entrega de mensajes.

## 1. Diferenciar HTTPS del sitio y salida hacia WhatsApp

El operador informó que el sitio del cPanel ya utiliza HTTPS en 443. Eso corresponde al acceso entrante. Node necesita DNS y conexiones **salientes** TLS/WebSocket por 443; ese requisito todavía no se ha comprobado en el hosting. Son condiciones distintas. No hay que abrir 3210 ni 3211 públicamente.

Comprobar DNS:

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node --input-type=module -e 'import {lookup} from "node:dns/promises"; try { await lookup("web.whatsapp.com"); console.log("DNS saliente: OK"); } catch { console.error("DNS saliente: FALLA"); process.exitCode=1; }'
~~~

Comprobar TLS/HTTPS saliente desde el mismo Node, sin credenciales ni sesión:

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node --input-type=module -e 'try { const r=await fetch("https://web.whatsapp.com/", {signal:AbortSignal.timeout(10000), redirect:"manual"}); console.log("HTTPS saliente: respuesta HTTP " + r.status); await r.body?.cancel(); } catch { console.error("HTTPS saliente: FALLA; revisar DNS, certificados y firewall con el proveedor"); process.exitCode=1; }'
~~~

Una respuesta HTTP demuestra que se completó DNS/TLS hacia ese destino, aunque un 403 exige revisar posibles restricciones. **No demuestra que el hosting permita el Upgrade a WebSocket ni que WhatsApp acepte una sesión**. Baileys utiliza `wss://web.whatsapp.com/ws/chat`; esto se comprobará después con la vinculación real y autorización operativa del hosting. No desactivar validación TLS ni usar `curl -k`.

## 2. Subir únicamente el script de prueba

Desde el Administrador de archivos de cPanel, subir `services/whatsapp/deploy/cron-probe.mjs` a:

~~~text
/home/slepac/apps/slep_postulaciones/services/whatsapp/deploy/cron-probe.mjs
~~~

No reemplazar `.env`, `service.env` ni otros archivos productivos. El script requiere `proper-lockfile`, que ya forma parte de las dependencias del puente. Comprobar que el despliegue y la instalación del paso 4 de [WHATSAPP_CPANEL.md](WHATSAPP_CPANEL.md) están completos:

~~~bash
cd /home/slepac/apps/slep_postulaciones/services/whatsapp &&
/home/slepac/.nvm/versions/node/v22.23.3/bin/node --input-type=module -e 'try { await import("proper-lockfile"); console.log("Dependencia de bloqueo: OK"); } catch { console.error("Falta instalar dependencias del servicio"); process.exitCode=1; }'
~~~

Si falla, completar `npm ci --omit=dev` usando Node 22 según la guía antes de continuar. No usar `--force` ni instalar dependencias en la carpeta de inicio.

## 3. Preparar la prueba una sola vez

Tener abierta la pantalla **cPanel → Cron Jobs** para añadir el cron inmediatamente después. Mantener `WHATSAPP_ENABLED=false`.

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node /home/slepac/apps/slep_postulaciones/services/whatsapp/deploy/cron-probe.mjs init
~~~

Esperado: `probe_initialized`. Este comando crea la configuración y el log privados, con carpeta 700 y archivos 600. El token queda dentro de `probe.json`; no es el token de Laravel y no se debe copiar, mostrar o enviar. **No repetir `init`: se niega a sobrescribir una prueba existente**. El límite de 15 minutos empieza aquí.

## 4. Añadir el cron temporal

En **cPanel → Cron Jobs**, elegir **una vez por minuto**: minuto, hora, día, mes y día de semana con `*`. En **Comando** pegar únicamente:

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node /home/slepac/apps/slep_postulaciones/services/whatsapp/deploy/cron-probe.mjs run >> /home/slepac/.local/share/sga-whatsapp-cron-test/events.log 2>&1
~~~

Crear **un solo cron** para esta prueba. La ruta absoluta evita depender de `nvm use` o del PATH de la Terminal. El bloqueo impide que las ejecuciones de los siguientes minutos abran otros servidores; si el proceso muere abruptamente, el bloqueo puede tardar algo más de 30 segundos en quedar disponible. Un cron solapado sale sin errores ni logs repetitivos.

## 5. Consultar y cerrar Terminal

Esperar entre uno y dos minutos, luego ejecutar:

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node /home/slepac/apps/slep_postulaciones/services/whatsapp/deploy/cron-probe.mjs status
~~~

Esperado: `state: running`, `starts: 1`, `without_token: 401`, `with_token: 200`. El comando no imprime el token. El número de arranques debe seguir en 1 aunque pasen varios minutos.

Cerrar Terminal, esperar dos minutos, abrirla nuevamente y repetir `status`. Debe seguir `running`, con el mismo `starts: 1`. Si aparece `waiting_for_cron`, revisar que el cron se haya guardado, que el binario de nvm sea accesible dentro de CageFS y que el hosting no finalice procesos prolongados. Si aparece `finished`, ya venció el plazo, se detuvo la prueba o se alcanzaron los cinco arranques.

## 6. Simular una caída y comprobar recuperación

Mientras siga activa y no falten pocos segundos para el vencimiento:

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node /home/slepac/apps/slep_postulaciones/services/whatsapp/deploy/cron-probe.mjs crash
~~~

Este comando pide al proceso de diagnóstico que salga con código 1. No usa `kill`, no afecta otros Node y no toca el puente real. Esperar entre uno y dos minutos y repetir el comando `status` del paso 5. Esperado: `running`, `starts: 2`, HTTP 401/200. Esto demuestra recuperación por la próxima ejecución de cron, con una demora que un supervisor reduciría; no prueba recuperación después de un reinicio completo del servidor.

## 7. Detener y retirar el cron

Aunque el plazo venza automáticamente, detener explícitamente:

~~~bash
/home/slepac/.nvm/versions/node/v22.23.3/bin/node /home/slepac/apps/slep_postulaciones/services/whatsapp/deploy/cron-probe.mjs stop
~~~

Esperar dos segundos y consultar `status`: debe mostrar `finished`. En **cPanel → Cron Jobs**, eliminar **sólo el cron cuyo comando contiene `deploy/cron-probe.mjs run`**. No eliminar los cron existentes de Laravel o de las colas.

Consultar el log, que contiene sólo eventos de diagnóstico sin credenciales:

~~~bash
tail -n 20 /home/slepac/.local/share/sga-whatsapp-cron-test/events.log
~~~

Se pueden compartir las salidas de DNS, HTTPS, `status` y ese log. **No compartir `probe.json`, `.env`, `service.env`, QR ni credenciales**. Conservar la carpeta privada hasta terminar de revisar resultados; no hace falta borrarla para detener el proceso. Para otra prueba, usar una carpeta privada nueva mediante `--directory` en todos los comandos y cambiar también la redirección del log; no reutilizar ni borrar el directorio de sesión real.

Si la prueba funciona, queda demostrado únicamente el comportamiento observado durante esos 15 minutos. Antes de usar cron para el servicio real debe confirmarse con el proveedor que admite procesos continuos, conexiones salientes WebSocket y almacenamiento persistente. Si los mata por política, cron no elimina esa restricción. La alternativa mínima es que el proveedor habilite un supervisor permitido en el mismo servidor, como el del paso 11.

## Validación local del 11 de octubre de 2026

Rama: `test/whatsapp-cron-probe`. Versión: `2026.10.11.586`. Windows con Node 22.22.0 y PHP 8.3.30:

- `npm test` en `services/whatsapp`: **19 pruebas correctas**, incluidas seis nuevas de diagnóstico. Se verifican autenticación HTTP, bloqueo entre procesos, salida con código 1 y recuperación, detención explícita, vencimiento, límite de arranques y ausencia del token en la salida. Las 13 pruebas del puente usan sockets sintéticos, sin mensajes reales.
- `npm run check` y `node --check deploy/cron-probe.mjs`: sintaxis válida.
- `ChangeLogLazyTest.php`: **5 pruebas, 53 aserciones**, correctas. `php -l config/changelog.php`: sin errores. Se conservan los 319 registros históricos y las 603 entradas anteriores.
- `php artisan optimize:clear` con `CACHE_STORE=file`: correcto; MySQL local no está disponible. No hubo cambios de rutas, vistas, CSS o JavaScript compilado, por lo que no corresponde un build ni otro listado de rutas.
- Sintaxis de los comandos Bash y de los tres fragmentos Node comprobada sin ejecutar comandos de instalación ni conexiones externas. `.env` y los tres PDF locales previos conservan sus hashes; credenciales, dependencias, logs y archivos de usuarios quedan fuera del parche.

**Pendiente en el hosting:** permisos 700/600 efectivos, ejecución real de Cron Jobs, acceso al Node de nvm desde cron/CageFS, supervivencia al cerrar Terminal, recuperación desde cron, DNS/TLS saliente y autorización del proveedor para un proceso permanente. Los chequeos POSIX de permisos del test se omiten en Windows. Las pruebas locales no sustituyen los pasos anteriores ni demuestran el funcionamiento productivo.

## Fuentes

- [cPanel: Cron Jobs y rutas absolutas](https://docs.cpanel.net/cpanel/advanced/cron-jobs/).
- [proper-lockfile: bloqueo, stale y update](https://github.com/moxystudio/node-proper-lockfile#readme).
- [Baileys rc14: URL WebSocket y origen HTTPS](https://github.com/WhiskeySockets/Baileys/blob/v7.0.0-rc14/src/Defaults/index.ts).
