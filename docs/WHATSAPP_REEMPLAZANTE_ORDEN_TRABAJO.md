# WhatsApp al reemplazante: aceptación y orden de trabajo

Versión 2026.10.11.590. Implementación local; despliegue y recepción real pendientes del operador.

## Evento y destinatario

`slepCrearOrdenTrabajo` guarda el PDF y cambia `derivada_slep` a `aceptada` dentro de una transacción con bloqueo de fila. `SolicitudReemplazoWhatsAppObserver` reconoce exclusivamente esa transición; el notifier prepara el aviso y el dispatcher espera el commit. Un rollback no deja aviso ni job. Regenerar una OT por modificación de término, guardar otros datos o regresar desde cierre no vuelve a notificar la aceptación.

Se usa el **reemplazante definitivo** en `postulant_profile_id`, con cuenta vigente, correo verificado y rol `postulante` o `funcionario`, como exige Mis reemplazos. El número es exclusivamente **`postulant_profiles.telefono1`**. No se utiliza `telefono2`, el contacto del establecimiento, la ficha AC ni un candidato anterior. La activación independiente responde a la instrucción del operador para este evento concreto; no crea una preferencia individual nueva ni genera envíos retrospectivos.

`WhatsAppPhone` añade `+56` a un móvil de nueve dígitos que comienza con `9`, añade sólo `+` a `56` seguido de nueve dígitos, conserva internacionales explícitos y convierte `00` en `+`. Retira separadores y exige E.164; Chile requiere nueve dígitos nacionales. Omite formatos incompletos, anexos o listas sin reescribir el perfil. Node consulta si el número tiene WhatsApp antes de reservar y enviar.

## Contenido y ejemplo ficticio

El inicio es **`fecha_inicio_trabajo`**, además de `fecha_termino`, número de solicitud, nombre y RBD. La dirección es **`establecimiento.admisionPerfil.direccion_publica`**: no hay una columna dirección en la tabla base. Si falta, se indica **No informada**; revisar y completar la ficha de Admisión antes de activar. No usar el domicilio personal del reemplazante.

Con `latitud`/`longitud` válidas, excluyendo `0,0`, se envía primero una **tarjeta de ubicación nativa** y luego el **PDF real de la OT** con la descripción siguiente. Se agrega un enlace Google Maps con las mismas coordenadas. Sin coordenadas válidas se omite tarjeta/enlace y se informa ubicación no registrada. No hay geocodificación ni coordenadas estimadas; WhatsApp no admite un iframe de Google Maps en el texto.

```text
📍 Tarjeta de ubicación: Escuela de Ejemplo
   Calle de Ejemplo 123

📄 ORDEN_TRABAJO_00001-2026.pdf

SGA SLEP Andalién Costa
Solicitud de reemplazo N° 00001-2026 — Aceptada
Tu asignación de reemplazo ha sido aceptada.
Establecimiento: Escuela de Ejemplo
RBD: 99999
Dirección: Calle de Ejemplo 123
Inicio de trabajo: 14-10-2026
Fecha de término: 30-10-2026
Orden de trabajo: PDF adjunto.
Ubicación: https://www.google.com/maps/search/?api=1&query=-36%2C-73
Revisa tu reemplazo en el SGA:
https://sga.example.test/postulante/mis-reemplazos/1

Esta es una notificación automática del SGA.
Por favor, no respondas a este mensaje.
```

Dominio y coordenadas son ilustrativos. En los avisos se utiliza `APP_URL` y las coordenadas guardadas. El enlace **Mis reemplazos** exige autenticación y propiedad del perfil; no usar el enlace de gestión de funcionarios. La apariencia final de la tarjeta/documento debe comprobarse en el teléfono. La plantilla del PDF existente se conserva.

## Privacidad, adjunto y reintentos

Laravel transmite por `POST /v1/messages/document`, autenticado, sólo referencia relativa `id/ORDEN_TRABAJO_*.pdf`, SHA-256 y ubicación opcional, conservando el límite JSON de 16 KiB. Un puente antiguo devuelve 404 en esa ruta: no se degrada a texto afirmando que adjuntó un PDF. Node lee desde una raíz privada configurada: máximo **5 MiB**, firma `%PDF-`, hash y MIME `application/pdf`. Rechaza rutas absolutas, URLs, traversal, symlinks y archivos fuera de la raíz. No publica enlaces de descarga, envía base64 en la API ni guarda el PDF en ledger/logs.

Número, texto, política y referencia se cifran en los despachos y se eliminan al finalizar; el job sólo lleva UUID. Cada intento revalida flag, perfil/cuenta/rol/verificación, teléfono, estado, fechas, establecimiento, generación y hash del PDF. Un cambio cancela el pendiente sin avisar a otro número/persona.

Una clave estable por solicitud evita otra notificación de aceptación. El ledger Node incluye PDF/ubicación en la huella y conserva huellas históricas de texto. Reserva antes de emitir tarjeta o documento; ambos componentes tienen IDs distintos y estables. **`unknown` puede significar que se recibió sólo la ubicación:** revisar el chat/PDF antes de decidir un envío manual. No reenviar automáticamente, borrar registros ni restaurar un ledger atrasado. `sent` confirma aceptación de los componentes solicitados, no lectura.

Baileys continúa fijado en **7.0.0-rc14**, Node **>=22.13.0**. Se verificaron los tipos instalados de documento, descripción, MIME y timeout de carga, y la ubicación en la [documentación oficial de Baileys](https://github.com/WhiskeySockets/Baileys#sending-messages). Los [enlaces universales de Google Maps](https://developers.google.com/maps/documentation/urls/get-started) no requieren clave API.

## Despliegue en cPanel — usuario de la cuenta

Conservar token, sesión y ledger; no volver a vincular ni modificar WhatsApp Business personal. Estos comandos no se ejecutaron contra producción.

1. Desplegar sólo archivos nuevos/modificados con el procedimiento habitual. Mantener `WHATSAPP_REEMPLAZANTES_ENABLED=false` durante la instalación. **No hay migración nueva ni cambio de dependencias**; deben estar instaladas la tabla de despachos y cola anteriores.
2. Obtener la raíz real de las órdenes y comprobar la cola, sin leer documentos ni secretos:

   ```bash
   /opt/cpanel/ea-php83/root/usr/bin/php <<'PHP'
   <?php
   require '/home/slepac/apps/slep_postulaciones/vendor/autoload.php';
   $app = require '/home/slepac/apps/slep_postulaciones/bootstrap/app.php';
   $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
   echo \Illuminate\Support\Facades\Storage::disk('local')->path('private/solicitudes-reemplazo').PHP_EOL;
   echo 'retry_after: '.config('queue.connections.database.retry_after').PHP_EOL;
   PHP
   ```

   Con el disco local actual se espera `/home/slepac/apps/slep_postulaciones/storage/app/private/private/solicitudes-reemplazo`: el primer `private` es la raíz del disco y el segundo corresponde a las rutas históricas. Usar el resultado efectivo; **no mover archivos ni quitar el segundo segmento**. `retry_after` debe ser mayor que **60**, normalmente **90**.
3. En el editor privado de `/home/slepac/.config/sga-whatsapp/service.env`, permiso 600, agregar una sola línea con la ruta comprobada:

   ```dotenv
   WHATSAPP_DOCUMENT_ROOT=/home/slepac/apps/slep_postulaciones/storage/app/private/private/solicitudes-reemplazo
   ```

   Node debe poder leer las órdenes creadas por PHP con la cuenta `slepac`. No ampliar permisos a todos ni usar storage público. Laravel crea la carpeta al generar la OT si aún no existe.
4. Sin envíos en curso, reiniciar con el **cron existente**, esperar su siguiente ejecución y comprobar recuperación sin QR:

   ```bash
   /home/slepac/.nvm/versions/node/v22.23.3/bin/node --env-file=/home/slepac/.config/sga-whatsapp/service.env /home/slepac/apps/slep_postulaciones/services/whatsapp/deploy/cron-service.mjs restart
   ```

   Esperar uno o dos minutos antes de ejecutar el diagnóstico:

   ```bash
   /home/slepac/.nvm/versions/node/v22.23.3/bin/node --env-file=/home/slepac/.config/sga-whatsapp/service.env /home/slepac/apps/slep_postulaciones/services/whatsapp/src/check-status.js
   ```

   Debe indicar `connected`. No iniciar otra instancia manual. Con systemd, el proveedor reinicia únicamente la unidad existente.
5. Actualizar **la entrada existente del worker PHP** a `--timeout=60`, conservando bloqueo, cola y frecuencia por minuto. No crear otro cron:

   ```bash
   /usr/bin/flock -n /home/slepac/.local/share/sga-whatsapp/queue-worker.lock /opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan queue:work database --queue=whatsapp --stop-when-empty --max-time=50 --timeout=60 --tries=5
   ```

   Si el worker está supervisado, pedir ese ajuste al proveedor. Referencia: texto **15 s**, PDF **45 s** (`WHATSAPP_MEDIA_TIMEOUT`, acotado a 50), job/worker **60 s**, `retry_after` **90 s**. Backoff y cinco intentos se conservan; `--max-time` se revisa entre jobs y `flock` evita solapamientos si uno se prolonga.
6. En el editor privado de la configuración Laravel, agregar:

   ```dotenv
   WHATSAPP_REEMPLAZANTES_ENABLED=true
   WHATSAPP_MEDIA_TIMEOUT=45
   ```

   Mantener `WHATSAPP_ENABLED=true`, URL/token existentes y `APP_URL` HTTPS real. El flag `WHATSAPP_REEMPLAZOS_ENABLED` y las preferencias AC controlan sólo avisos internos. Aplicar:

   ```bash
   cd /home/slepac/apps/slep_postulaciones
   /opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
   /opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
   /opt/cpanel/ea-php83/root/usr/bin/php artisan queue:restart
   ```

7. Validar primero en un entorno de pruebas con reemplazante/número autorizado: generar OT, recibir tarjeta/PDF, revisar fechas, pie y enlace, y comprobar que otra cuenta no puede abrir el detalle. Después el operador valida una aceptación legítima autorizada en producción y el historial administrativo. No crear solicitudes ficticias ni enviar documentos ajenos en pruebas automatizadas. **Recepción real y acceso a servidores multimedia siguen pendientes**.

## Cuándo interviene el proveedor

Si falla, comprobar lectura desde CageFS, políticas de procesos/recursos y DNS/TLS saliente por 443 a los servidores multimedia que utilice WhatsApp. Haber abierto `web.whatsapp.com` y su WebSocket **no demuestra la carga de un PDF**. Con systemd, permitir lectura de la raíz privada; Node no requiere escribir órdenes. No instalar un mecanismo que arranque otra sesión.

Para pausar sólo este aviso: `WHATSAPP_REEMPLAZANTES_ENABLED=false`, reconstruir caché y reiniciar worker. No borrar sesión, PDFs ni historial. Habilitar no reenvía aceptaciones anteriores.

## Validación local

SQLite en memoria, discos temporales, HTTP/colas y sockets simulados: aceptación/commit/rollback, destinatario/propiedad del PDF, número/flags/datos/pie, cancelaciones, autenticación, desconexión/UUID estable, deduplicación tras reinicio, rutas, symlinks, límites y resultado parcial incierto. **Sin mensajes reales.** Resultados y manifiesto: [WHATSAPP_VALIDACION.md](WHATSAPP_VALIDACION.md).
