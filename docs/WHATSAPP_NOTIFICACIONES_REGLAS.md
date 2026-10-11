# Regla de notificaciones WhatsApp del SGA

## Regla obligatoria para incorporar otros módulos

1. Definir un catálogo cerrado de eventos de negocio y su siguiente responsable. Emitir desde la acción que confirma la transición, dentro de su transacción; no desde cada `save`, una consulta, una importación ni un cambio de archivos.
2. Exigir tanto `WHATSAPP_ENABLED=true` como la activación explícita del módulo. Cada nuevo módulo comienza desactivado y utiliza una preferencia individual propia, inicialmente falsa. **`enviar_notificacion` no activa WhatsApp.** Un flag de reemplazos no autoriza avisos de otro módulo.
3. Resolver una cuenta vigente, verificada, con el rol exacto del responsable y acceso al módulo. Usar su ficha en `funcionarios_ac_autorizados`, su `telefono` y su preferencia. Primero `registered_user_id`; sólo si está vacío, coincidencia única del RUT normalizado. Omitir vínculos contradictorios, cuentas eliminadas y registros ambiguos. No resolver por nombres, correos, teléfono del solicitante o destinatarios inventados.
4. Si existe una asignación, avisar sólo a la cuenta asignada que cumpla esas condiciones. Cuando la etapa tenga una bandeja por rol sin asignación individual, avisar a las cuentas de ese rol que el operador haya marcado. Deduplicar por cuenta y teléfono normalizado para cada ocurrencia.
5. Validar el teléfono antes de encolar y antes de enviar. Sólo inferir Chile para móviles de nueve dígitos que comienzan con `9`: `9XXXXXXXX` → `+569XXXXXXXX`; si ya tiene once dígitos y comienza con `56`, añadir sólo `+`. Conservar un internacional explícito, convertir `00` a `+` y retirar espacios, puntos, paréntesis y guiones. Exigir E.164 (8 a 15 dígitos totales, código de país sin cero inicial) y exactamente nueve dígitos nacionales para `+56`. Omitir anexos, listas, números cortos e internacionales sin prefijo explícito. La normalización se aplica al envío y no reescribe la ficha histórica. Node consulta `onWhatsApp` antes de `sendMessage`: sin cuenta WhatsApp devuelve `invalid_recipient`, fallo definitivo sin reenvío automático.
6. **Todo mensaje automático debe incluir un enlace HTTPS completo al detalle autorizado del SGA**, junto con evento, identificador y resumen mínimo útil. Construirlo desde `APP_URL` configurado y una ruta nombrada relativa, nunca desde el Host recibido, enlaces de descarga privados, tokens o un inicio de sesión que evite permisos. Si no se puede construir un enlace válido, omitir el envío. El usuario debe autenticarse normalmente al abrirlo.
7. Excluir nombres/RUT de titulares o reemplazantes, diagnósticos, razones de licencia, motivos de rechazo, documentos, claves y QR. Sanear saltos de línea en campos libres. Respetar 1000 caracteres y conservar el enlace íntegro.
8. Reutilizar `WhatsAppChannel`, `WhatsAppText`, `WhatsAppDispatcher` y la cola `whatsapp`. El job sólo lleva UUID; texto, teléfono y política de destinatario se guardan cifrados en `whatsapp_dispatches` y se eliminan al finalizar. No encolar otro job con el modelo o el texto en claro. La política interna no se remite a Node.
9. Preparar una clave estable por módulo + entidad + evento + ocurrencia + destinatario, sin teléfonos en claro. Conservarla y el UUID en reintentos; una reapertura real es una nueva ocurrencia. La clave única y el registro persistente Node evitan duplicar por repetir el mismo envío. Repetir una aprobación o asignar nuevamente a la misma persona no crea otro evento. Los cambios de etapa deben bloquear y revalidar la fila para evitar dos confirmaciones simultáneas.
10. Antes de cada intento, comprobar nuevamente activación, preferencia, autorización, rol, acceso, identidad y teléfono del destinatario, además de etapa y asignación. Cancelar si cambiaron; no reenviar a un nuevo teléfono automáticamente. Un resultado incierto requiere revisión, sin reenvío forzado. Esto no garantiza lectura ni entrega exactamente una vez.
11. Los fallos de WhatsApp no impiden confirmar el trámite. Conservar sus correos y permisos existentes; registrar sólo códigos de error conocidos. Probar con SQLite en memoria, HTTP/colas/sockets simulados y datos ficticios. Nunca enviar mensajes reales en pruebas automatizadas.

Para ampliar la implementación, documentar la matriz, sumar una política de validación al worker para el nuevo módulo y probar revocaciones, errores, rollback y deduplicación. No reutilizar la política `scope=reemplazos` para otra entidad.

## Reemplazos: eventos implementados

Los nombres reales de roles son `coordinador_uatp`, `supervisor_plani`, `coordinador_gdp` y **`funcionario_slep`**, en singular. No se crean roles con los nombres escritos por error `coordinador_uato` o `funcionarios_slep`.

| Acción confirmada | Estado destino | Recibe | Evento `solicitud_reemplazo.*` |
| --- | --- | --- | --- |
| Crear solicitud con documentos y jornadas | `pendiente_uatp` | Coordinadores UATP seleccionados | `created` |
| UATP aprueba | `pendiente_validacion` | Supervisores de Planificación seleccionados | `aprobada_uatp_pendiente_plani` |
| Planificación valida | `pendiente_gdp` | Coordinadores GDP seleccionados | `validada_plani` |
| GDP deriva | `derivada_slep` | Sólo `derivada_a_user_id`, funcionario SLEP o coordinador GDP seleccionado | `derivada_slep` |
| GDP cambia la persona asignada | `derivada_slep` | Sólo el nuevo asignado elegible | `reasignada_slep` |
| Reapertura administrativa de rechazo UATP | `pendiente_uatp` | Coordinadores UATP seleccionados | `reabierta_uatp` |
| Reapertura administrativa de rechazo de Planificación | `pendiente_validacion` | Supervisores de Planificación seleccionados | `reabierta_plani` |
| Establecimiento reenvía corrección hacia UATP | `pendiente_uatp` | Coordinadores UATP seleccionados | `corregida_uatp` |
| Establecimiento reenvía corrección hacia Planificación | `pendiente_validacion` | Supervisores de Planificación seleccionados | `corregida_plani` |

Las tres primeras bandejas no tienen asignación individual en el modelo. Si se marca más de una cuenta para el mismo rol, todas las elegibles reciben el aviso de su etapa. Después de GDP se exige la asignación concreta. Un usuario con varios roles recibe sólo los eventos que le corresponden; `admin`, `funcionario_ac`, `funcionario_estab`, `coordinador_plani` y `gdp_admin` por sí solos no son destinatarios.

Fuentes del flujo: `FuncionarioEstab/SolicitudReemplazoController::store/update` y `Gestion/SolicitudReemplazoGestionController::uatpAprobar/planiValidar/gdpDerivar/gdpReasignar/uatpReabrir/planiReabrir`. El servicio `ReemplazoWhatsAppNotifier` contiene el catálogo y `ReemplazoWhatsAppRecipients` resuelve autorizaciones. Los avisos se preparan antes del correo, pero la cola espera el commit externo: si se revierte el trámite, no queda aviso ni job.

Se cancela un pendiente si la solicitud avanzó de etapa, se eliminó o cambió su asignación; también si la preferencia, rol, autorización, acceso o teléfono ya no son válidos. La activación no genera mensajes retroactivos de solicitudes existentes: sólo nuevas acciones del catálogo.

## Contenido y ejemplo ficticio

Cada mensaje contiene `numero_solicitud`, `establecimiento.nombre_establecimiento`, fechas **solicitadas** `fecha_inicio` y `fecha_termino`, continuidad y enlace. No sustituir inicio por `fecha_inicio_trabajo`, que corresponde a la orden de trabajo.

Se usa `es_continuidad` para indicar continuidad validada. El dato histórico `continuidad=true` sin validación aparece como declarada; un dato ausente se informa como no informado. El mensaje no vuelve a calcular ni cambia las reglas de continuidad.

```text
SGA · Solicitud de reemplazo N° 00001-2026
UATP aprobó la solicitud. Pendiente de validación de Planificación.
Establecimiento: Establecimiento de prueba
Fecha de inicio: 12-10-2026
Fecha de término: 30-10-2026
Continuidad: Sí (validada)
Ingresa al SGA para revisar y gestionar:
https://sga.example.test/gestion/solicitudes-reemplazo/1
```

## Eventos analizados que necesitan otra decisión

Los rechazos UATP/Planificación y la observación SLEP mantienen el correo al establecimiento. No se envían a `contacto_fono`, porque ese destinatario está fuera de los cuatro roles elegidos. Guardar un borrador, editar sin reenviar, ajustar jornadas o cambiar documentos no avisa.

La aceptación con orden de trabajo avisa ahora al reemplazante bajo la política específica descrita al final. Contrato firmado, resolución docente, cierre, anulación, finiquito, modificación de término y retorno desde cierre no disparan WhatsApp. Tampoco `derivarCasoGestion` (SLEP devuelve a GDP/administrador): sus posibles destinatarios incluyen roles fuera de lo autorizado. Antes de ampliarlos hay que elegir evento y siguiente responsable elegible; no basta con activar el flag de reemplazos.

## Activación paso a paso en cPanel

Prerequisito: integración instalada según [WHATSAPP_CPANEL.md](WHATSAPP_CPANEL.md), sesión conectada y cola dedicada funcionando. El operador ya informó conexión autenticada y estado `connected`; estos cambios no se instalaron ni probaron en producción desde el entorno local.

1. Desplegar los archivos de este parche. Mantener el módulo automático desactivado mientras se instala. El parche 589 de avisos internos no cambia Node; el parche 590 de aceptación al reemplazante amplía el puente y requiere los ajustes de la guía específica enlazada al final. Conservar dependencias, token y sesión.
2. Ejecutar únicamente la migración aditiva nueva, desde Terminal:

   ```bash
   cd /home/slepac/apps/slep_postulaciones
   /opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --path=database/migrations/2026_10_11_150000_add_whatsapp_reemplazos_preference.php --force
   /opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
   /opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
   /opt/cpanel/ea-php83/root/usr/bin/php artisan queue:restart
   ```

   La columna `notificar_whatsapp_reemplazos` queda falsa para todas las fichas. El rollback conserva preferencias; para desactivar usar configuración, sin eliminar la columna ni el historial.
3. Con una cuenta que ya pueda administrar la nómina, abrir **Trámites → Cargas familiares → Administración de funcionarios AC autorizados → Editar**. El mantenedor actual permite `admin`, `funcionario_slep` y `coordinador_gdp`, además del acceso al módulo existente. No se amplían esos permisos. Si el nombre del menú varía, la ruta es `/tramites/cargas-familiares/administracion/funcionarios-ac`.
4. Revisar en cada ficha seleccionada el teléfono, la autorización y su vínculo con la cuenta correcta. La cuenta debe tener correo verificado, acceso a **Gestión Solicitudes** y el rol de la etapa. Marcar **Recibir WhatsApp de solicitudes de reemplazo** y guardar. La validación indica si faltan condiciones; se puede desmarcar sin esas condiciones. No marcar toda la nómina. Una importación Excel posterior conserva el flag, aunque cambios de teléfono/autorización pueden cancelar envíos pendientes.
5. Confirmar que `APP_URL` en la configuración privada de Laravel sea el origen HTTPS real del SGA. En el mismo editor privado agregar:

   ```dotenv
   WHATSAPP_REEMPLAZOS_ENABLED=true
   ```

   Conservar las variables de la instalación, con estos valores de referencia:

   ```dotenv
   WHATSAPP_ENABLED=true
   WHATSAPP_SERVICE_URL=http://127.0.0.1:3210
   WHATSAPP_SERVICE_TOKEN=<el mismo token privado ya instalado>
   WHATSAPP_QUEUE_CONNECTION=database
   WHATSAPP_QUEUE=whatsapp
   WHATSAPP_CONNECT_TIMEOUT=3
   WHATSAPP_TIMEOUT=15
   APP_URL=https://<dominio-real-del-SGA>
   ```

   No pegar el token en comandos, historial, Git o tickets. Estas líneas son ejemplos, no modifican `.env` local ni regeneran secretos.
6. Aplicar la configuración con PHP 8.3:

   ```bash
   cd /home/slepac/apps/slep_postulaciones
   /opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
   /opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
   /opt/cpanel/ea-php83/root/usr/bin/php artisan queue:restart
   ```

   `queue:restart` comunica la salida al worker; el mecanismo existente debe volver a iniciarlo. Mantener el cron/worker del paso 12 de la guía, con cola `whatsapp`; el cron de Node por sí solo no procesa jobs PHP. Si usa un worker persistente, su `retry_after` debe superar el timeout de 60 segundos y respetar los cinco intentos y backoff. No ejecutar dos mecanismos distintos para iniciar el puente.
7. Primero validar el flujo en un entorno de pruebas con un número ingresado y autorizado expresamente. Con el operador, aprobar una solicitud de prueba en UATP y comprobar que recibe sólo el supervisor de Planificación marcado; validar en Planificación y derivar en GDP para comprobar la siguiente etapa y el enlace. No crear ni aprobar solicitudes ficticias en producción como parte de pruebas automatizadas. La recepción real y apertura del enlace requieren comprobación manual del operador.
8. Revisar **Administración → WhatsApp → Últimos envíos** y, en Terminal, `queue:failed` sin publicar contenidos de jobs. `sent` significa aceptación por WhatsApp, no lectura. `cancelled` puede indicar revocación o cambio de etapa; `failed/invalid_recipient` indica que WhatsApp no reconoce el número. `unknown` requiere comprobar recepción antes de cualquier decisión manual.

Para pausar sólo reemplazos, poner `WHATSAPP_REEMPLAZOS_ENABLED=false`, reconstruir caché y reiniciar workers como en el paso 6. La sesión y la prueba manual siguen disponibles. Rehabilitar no recrea avisos cancelados ni genera un envío masivo retrospectivo.

## Regla específica: aceptación al reemplazante con PDF

Por instrucción expresa del operador, `derivada_slep → aceptada` después de guardar la OT es el evento `solicitud_reemplazo.aceptada_reemplazante`. El observer reconoce únicamente esa transición y encola dentro de la transacción, esperando el commit. Regeneraciones, retornos desde cierre y consultas no avisan.

Esta política `scope=reemplazante_ot` es una excepción autorizada a las reglas generales de preferencia individual y destinatarios AC: resuelve directamente `postulant_profile_id → user` y **`telefono1`**, con cuenta verificada, no eliminada y rol permitido en Mis reemplazos. No reutiliza roles ni flags de la nómina ni usa `telefono2` como fallback. La activación independiente comienza falsa: `WHATSAPP_REEMPLAZANTES_ENABLED`.

Excepción explícita a la exclusión general de documentos: adjuntar **sólo la orden de trabajo propia del reemplazante definitivo**, desde almacenamiento privado, con hash y límite de tamaño. No extenderla a otros PDFs, expedientes o avisos por rol. La tarjeta de ubicación acompaña al documento del mismo evento; su descripción siempre incluye el enlace HTTPS a **Mis reemplazos** y termina indicando que es automática y que no debe responder. Usar `fecha_inicio_trabajo`, dirección pública y coordenadas registradas del establecimiento, sin inferir ubicaciones ni usar domicilios personales.

La aceptación se deduplica por solicitud y se revalida el destinatario y la versión del PDF antes de cada intento. Cambios de perfil, número, estado, fechas o PDF cancelan el pendiente. Un resultado incierto puede corresponder sólo a la tarjeta de ubicación; no reenviar automáticamente. Instalación y ejemplo: [WHATSAPP_REEMPLAZANTE_ORDEN_TRABAJO.md](WHATSAPP_REEMPLAZANTE_ORDEN_TRABAJO.md).
