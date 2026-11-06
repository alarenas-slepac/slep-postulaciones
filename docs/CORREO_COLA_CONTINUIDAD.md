# Correo en cola y continuidad del SGA

Parche 2026.11.6.684. La indisponibilidad del SMTP no debe revertir un trámite ni impedir crear una cuenta o solicitar su enlace de verificación.

## Comportamiento

El gestor de correo encola todos los mailables y las vistas directas, incluidos `send`, `sendNow`, `queue` y `later`. El canal de correo de las notificaciones también aplaza las llamadas síncronas y los trabajos Laravel anteriores. Los demás canales mantienen su transporte.

La conexión `sga_mail` usa la base de datos del SGA y la tabla `mail_jobs`, cola `sga-mail`, independientemente de `QUEUE_CONNECTION`. La inserción espera el commit externo: si se revierte la operación, el mensaje se descarta con ella. No hay conexión SMTP dentro de la petición HTTP. Los adjuntos y destinatarios permanecen en el payload cifrado con la clave de la aplicación; no cambiar `APP_KEY` mientras existan pendientes.

El worker aplica las preferencias actuales del catálogo antes de entregar, conserva To/CC/BCC y usa una ocurrencia estable para deduplicar los avisos WhatsApp derivados durante los reintentos. Estos avisos derivados se preparan al procesar el correo; una pausa de esta cola aplaza también esa preparación. Los avisos propios de reemplazos por WhatsApp conservan su cola y reglas independientes.

Una excepción del transporte SMTP conserva el trabajo y reprograma su disponibilidad a 1, 5, 15, 30 y luego 60 minutos. No se agotan intentos durante la incidencia. La columna de intentos usa un entero amplio para soportar pausas prolongadas. El timeout SMTP es de 10 segundos; el trabajo tiene 60 segundos y la reserva de cola 120 segundos. Otros errores se reintentan con espera y requieren revisar el registro del worker.

La auditoría registra `queued` al aceptar el aviso, `sent` sólo después del evento de entrega al transporte, y `suppressed` si la política cancela el envío. Un fallo SMTP deja `queued`, sin fecha de envío, y el código `smtp_temporarily_unavailable`. La aceptación SMTP no acredita recepción final ni lectura.

La verificación conserva el control de acceso: no verifica cuentas automáticamente. El enlace firmado se genera al procesar el aviso, y una cuenta verificada, eliminada o cuyo correo cambió no recibe el pendiente obsoleto. En recuperación de contraseña, el token conserva su vencimiento original: si caduca o se sustituye, se omite el mensaje y debe solicitarse otro enlace.

## Despliegue en cPanel

Aplicar la migración y reconstruir la configuración como parte del mismo despliegue, antes de iniciar el nuevo worker. No cambiar `.env`, SMTP ni la cola general. Usar siempre el binario absoluto de PHP 8.3.

```bash
cd /home/slepac/apps/slep_postulaciones
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --path=database/migrations/2026_11_06_120000_create_sga_mail_jobs_table.php --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan sga-mail:control pausar
/opt/cpanel/ea-php83/root/usr/bin/php artisan queue:restart
```

La migración sólo crea `mail_jobs` si no existe. No altera trámites, usuarios, preferencias ni otras colas. Su `down` conserva los correos; no elimina la tabla ni sus datos.

Configurar un worker dedicado o un cron cada minuto. Ejemplo de cron con exclusión de ejecuciones simultáneas; `flock` debe estar disponible en el hosting:

```bash
flock -n /home/slepac/.sga-mail-worker.lock /opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan queue:work sga_mail --queue=sga-mail --stop-when-empty --max-time=50 --sleep=1 --timeout=60 --tries=0 >> /home/slepac/apps/slep_postulaciones/storage/logs/mail-worker.log 2>&1
```

La cola seguirá recibiendo mensajes aunque esté pausada o el cron todavía no se ejecute. Los workers de las demás colas pueden continuar; no basta con un worker que sólo lea `default`. El marcador de pausa se guarda en el disco local privado y sobrevive a `optimize:clear`.

## Operación durante la incidencia

`estado` muestra si la entrega está pausada y la cantidad de trabajos pendientes, sin consultar el SMTP ni mostrar destinatarios.

```bash
/opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan sga-mail:control estado
/opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan sga-mail:control pausar
```

Una vez que el proveedor confirme el SMTP operativo:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan sga-mail:control reanudar
```

Reanudar habilita al worker; el comando no envía mensajes por sí mismo. Los pendientes con espera vuelven a estar disponibles según su reprogramación. Revisar que disminuya `mail_jobs` y que la auditoría pase de En cola a Enviado. Las pruebas automatizadas usan SQLite en memoria y transportes simulados; no se realizaron envíos reales.

Los registros anteriores en `failed_jobs` requieren una revisión separada y reintentar únicamente los IDs de correo que correspondan. No repetir trámites ni ejecutar reintentos masivos de trabajos de negocio para recuperar un aviso. La solución mantiene los mensajes futuros en cola; el proveedor debe reparar el DNS y el SMTP para entregarlos.
