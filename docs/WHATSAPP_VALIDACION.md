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

## Pendientes del despliegue

No se comprobaron acceso SSH/Terminal, Node Linux y dependencia nativa en cPanel, autorización de proceso persistente y WebSocket saliente, supervisor/reinicio de servidor, almacenamiento/permisos Linux, conectividad PHP–Node bajo CageFS, migración en MySQL, worker de cola productivo, escaneo real ni recepción del mensaje. No se ejecutó la suite completa del SGA; se ejecutaron las pruebas de la integración y las regresiones relacionadas.

Seguir [WHATSAPP_CPANEL.md](WHATSAPP_CPANEL.md) para completar estas condiciones antes de escanear. La selección de eventos, destinatarios y preferencias de WhatsApp permanece pendiente: no hay envíos automáticos activados.
