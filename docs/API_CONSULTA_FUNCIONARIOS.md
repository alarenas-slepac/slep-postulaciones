# Consulta de funcionarios por RUT

## Accesos

- **Trámites y operación → Consulta de funcionarios** (`/consultas/funcionarios`): rol activo `funcionario_estab` (también alias `funcionario_establecimiento`, si existe) o `admin`, correo verificado y permiso `consultar_funcionarios`.
- **Administración → Integraciones API de funcionarios** (`/admin/integraciones-funcionarios`): rol activo `admin`, correo verificado y permiso `funcionarios_api.manage`.
- API para servidores de otros sistemas: `POST /api/v1/funcionarios/consultar`, autenticada con Sanctum. Una identidad técnica por integración; no necesita una cuenta de persona ni recibe roles de administración. No habilita consultas anónimas.

La migración concede los permisos a los roles existentes indicados. No crea usuarios, destinatarios ni notificaciones. Se puede revocar el permiso de un rol mediante el mantenedor existente. El alcance es una consulta individual a nivel del SLEP, incluyendo otros establecimientos; no incorpora exportaciones ni listados de personas.

## Solicitud

```http
POST /api/v1/funcionarios/consultar
Authorization: Bearer <TOKEN_PRIVADO>
Content-Type: application/json
Accept: application/json

{"rut":"990000018"}
```

El RUT del ejemplo es sintético. El cuerpo debe contener un string con cuerpo numérico y DV (0–9/K), sin puntos ni guion. Se acepta `k` y se normalizan ceros iniciales. Laravel recorta espacios exteriores. El DV se valida por módulo 11: no se completa ni se corrige un DV ausente/incorrecto. No se consulta desde parámetros de URL, ni se admite un token en la URL. La pantalla puede recibir un RUT formateado y prepararlo antes del envío JSON.

Usar HTTPS. Un entorno Laravel identificado como producción rechaza la API por HTTP; si existe proxy inverso, el proveedor debe configurar HTTPS y los proxies confiables correctamente, sin confiar cabeceras arbitrarias del cliente. Los tokens sólo van entre servidores: no incrustarlos en sitios públicos ni JavaScript de terceros. La pantalla del SGA usa su sesión y CSRF.

## Contrato de respuesta

Todas las respuestas de consulta llevan `Cache-Control: no-store, private`, `Pragma: no-cache`, `Referrer-Policy: no-referrer` y una referencia `X-Request-ID`. El contrato completo está en [api-consulta-funcionarios.openapi.yaml](api-consulta-funcionarios.openapi.yaml).

Ejemplo de establecimiento, con datos sintéticos:

```json
{
  "encontrado": true,
  "clasificacion": "establecimiento",
  "estado": "padron_actual",
  "fuente": "reemplazos_personal",
  "funcionario": {
    "rut_normalizado": "990000018",
    "nombre": "PERSONA DE EJEMPLO",
    "vinculos": [
      {
        "rbd": 99999,
        "establecimiento": "ESTABLECIMIENTO DE EJEMPLO",
        "estatuto": "ESTATUTO DE EJEMPLO",
        "escalafon": "ESCALAFON DE EJEMPLO",
        "periodo": "2026-10"
      }
    ]
  },
  "advertencias": []
}
```

Los vínculos distinguen establecimiento, estatuto, escalafón y período; una misma persona puede tener varios. Se omiten repeticiones idénticas en estos campos. Se conservan los valores de estatuto/escalafón del padrón. No se devuelven nacimiento, correo, teléfono, remuneración, documentos ni atributos internos del modelo.

Ejemplo de Administración Central:

```json
{
  "encontrado": true,
  "clasificacion": "administracion_central",
  "estado": "autorizacion_activa",
  "fuente": "funcionarios_ac_autorizados",
  "funcionario": {
    "rut_normalizado": "990000018",
    "apellido_paterno": "APELLIDO DE EJEMPLO",
    "apellido_materno": null,
    "nombres": "PERSONA DE EJEMPLO",
    "unidad_departamento": "UNIDAD DE EJEMPLO",
    "subdireccion_dependencia": "SUBDIRECCION DE EJEMPLO",
    "jefatura": 1
  },
  "advertencias": ["La autorización del SGA no constituye una certificación de vigencia laboral."]
}
```

`jefatura` es entero `1`/`0`, no booleano ni texto. Los campos de texto vacíos se devuelven como `null`.

| Estado | Interpretación |
| --- | --- |
| `padron_actual` | Vínculos conforme al alcance vigente existente de personal: última carga por establecimiento y piso de revisiones aplicadas. No sustituye un certificado laboral. |
| `autorizacion_activa` | Registro AC activo dentro de sus fechas de autorización, incluidas las fechas límite. Esa autorización del SGA no acredita por sí sola un contrato laboral vigente. |
| `sin_antecedentes` | No encontrado en las fuentes disponibles. `encontrado=false`, clasificación/fuente/funcionario nulos; no afirma que la persona no trabaje en el SLEP. |
| `sin_vinculo_actual_confirmado` | Hay antecedentes antiguos o autorizaciones inactivas, vencidas o futuras. `encontrado=true`, clasificación/fuente/funcionario nulos; no se divulgan esos datos como actuales. |
| `revision_requerida` | Coincidencias actuales en ambas fuentes, varios registros AC normalizados, establecimientos ausentes o nombres contradictorios. No se elige una dependencia arbitrariamente. |

En una coincidencia entre fuentes, `clasificacion=revision_requerida`, `funcionario=null` y `coincidencias` incluye `establecimiento` (objeto con vínculos o null) y `administracion_central` (array de objetos AC). En un vínculo escolar inconsistente se conserva `clasificacion=establecimiento`, con estado y advertencias de revisión. Un registro AC inactivo no oculta un vínculo escolar actual.

HTTP: `200` consulta completada, `401` token inválido/vencido/revocado, `403` falta de permiso, integración suspendida o transporte HTTP en producción, `422` RUT inválido, `429` límite alcanzado (incluye `Retry-After`), `503` fallo de alguna fuente o auditoría. Los errores llevan `error` y `message`; no contienen SQL, credenciales ni la petición original. Un fallo de fuente nunca se transforma en “no encontrado”.

## Tokens, límites y actividad

Crear una integración desde Administración con nombre, consultas/minuto y vigencia en días. Se muestra el token una sola vez en la respuesta POST, sin flash de sesión ni almacenamiento en claro; la base conserva el hash de Sanctum. Copiarlo a un editor/gestor privado del sistema consumidor, nunca a Git, tickets, comandos con el token como argumento o logs. Volver a la lista no permite recuperarlo.

Renovar invalida el token anterior y entrega uno nuevo; actualizarlo en el consumidor. Revocar elimina los tokens de esa integración. Suspender bloquea el acceso inmediatamente, conservando el token para una eventual reactivación mientras no venza. Crear/renovar tiene un límite adicional de 6 acciones/minuto por administrador. No se generan credenciales durante migraciones, pruebas o peticiones GET.

`config/funcionario_consulta.php` define 30 consultas/minuto para usuarios, valores predeterminados de integración, máximo configurable (300) y token de 90 días (máximo 365). Cada integración tiene su propio límite. Existe además un límite de entrada por IP de 60/minuto, incluyendo intentos sin autenticación; ajustar ese valor si varios consumidores comparten una IP. La caché de Laravel debe ser persistente entre peticiones (archivo, base o Redis); no usar `array` en despliegues reales. El almacenamiento de tokens y límites usa los mecanismos del Laravel actual, sin un proceso Node adicional.

Se auditan consultas de actores identificados, incluso errores y denegaciones: ID de actor, fecha, HTTP, estado, referencia UUID y HMAC del RUT válido con APP_KEY. No se guardan RUT ni nombres consultados en claro. La auditoría es de acceso restringido al administrador y requiere la política de conservación institucional. Si no puede escribirse, no se entrega información. Los intentos sin identidad se limitan por IP y no llenan la tabla de auditoría. Servidores, WAF, proxies y herramientas APM deben omitir `Authorization` y cuerpos de estas rutas; la aplicación no puede controlar sus registros externos.

## Instalación en cPanel

Este cambio se despliega junto con Laravel. Requiere PHP 8.3 y MySQL con columnas virtuales indexadas (verificar en el motor del hosting). Los índices se calculan automáticamente al importar o editar las fuentes y no alteran sus RUT originales. Su creación puede bloquear tablas grandes: respaldar y coordinar una ventana de despliegue.

1. Desplegar la rama aprobada con los recursos compilados, `composer.json` y `composer.lock`. No sustituir `.env`, archivos de usuarios ni datos de producción.
2. Instalar dependencias con PHP 8.3 y el Composer del servidor. Por ejemplo, sustituyendo únicamente la ruta de Composer por la verificada en el hosting:

   ```bash
   cd /home/slepac/apps/slep_postulaciones
   /opt/cpanel/ea-php83/root/usr/bin/php /ruta/verificada/composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   ```

3. Revisar las migraciones pendientes con el mismo PHP. Aplicar las dos de esta integración tras el respaldo:

   ```bash
   /opt/cpanel/ea-php83/root/usr/bin/php artisan migrate:status
   /opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --path=database/migrations/2026_10_22_220000_create_funcionario_consulta_api.php --force
   /opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --path=database/migrations/2026_10_22_221000_index_rut_for_funcionario_consulta.php --force
   /opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
   /opt/cpanel/ea-php83/root/usr/bin/php artisan permission:cache-reset
   /opt/cpanel/ea-php83/root/usr/bin/php artisan route:list --path=funcionarios
   ```

4. Verificar que HTTPS llega correctamente a Laravel y que Apache/cPanel entrega la cabecera `Authorization` al PHP. No desactivar la autenticación para resolver un `401`. Esta API no requiere un token global ni variables nuevas en `.env`.
5. Ingresar como administrador, verificar ambas vistas y crear expresamente cada integración. Para comprobar desde otro sistema, usar su backend y leer el token desde su configuración privada; consultar sólo un RUT autorizado para la prueba. No registrar la respuesta en logs.
6. Verificar `401` sin token, `422` con un RUT sintético inválido y la revocación/suspensión con una integración de prueba. La consulta externa real y el comportamiento de proxy/WAF deben comprobarse en ese hosting; las pruebas locales no acreditan funcionamiento productivo.

Los `down()` son conservadores: preservan historial, permisos, tokens y columnas derivadas. Para detener el acceso, suspender integraciones/revocar tokens y retirar permisos. No ejecutar rollback como sustituto de la revocación. Cualquier eliminación de datos o tablas requiere revisión independiente.

## Validación local

Pruebas aisladas: `php vendor/phpunit/phpunit/phpunit tests/Feature/FuncionarioConsultaApiTest.php`. Usan SQLite en memoria, credenciales y personas sintéticas, correo/colas falsos y bloqueo de HTTP externo. No enviar mensajes ni llamar a sistemas productivos.

Interfaz: `node --test tests/js/funcionario-consulta.test.mjs`; compilación: `npm run build`. Revisar escritorio/móvil, teclado, mensajes de error, estados sin coincidencias y contratos múltiples. El estado de comprobaciones efectivamente realizadas se informa en la entrega.

### Comprobaciones realizadas el 22-10-2026

- Suite relacionada: **127 pruebas PHP, 898 aserciones**, incluyendo API, permisos, tokens, CSRF, errores, límites, migraciones reanudables y regresiones de padrón, licencias y directorio AC. Todas aprobadas, con SQLite en memoria y datos sintéticos.
- **2 pruebas JavaScript** aprobadas. `npm run build`, sintaxis de los **21 PHP nuevos/modificados**, `composer validate`, `route:list`, `optimize:clear` y revisión de diferencias completados.
- Contrato OpenAPI validado. Revisión de navegador con datos sintéticos a **1440 × 1080** y **390 × 844**: consulta, vínculos, errores de DV y administración, sin errores JavaScript ni desborde horizontal de página. Se comprobó también la vista de entrega única de token.
- Las dos migraciones se aplicaron exclusivamente en **MySQL local 8.4.3**. Se compararon huellas de todas las columnas originales antes/después: no cambiaron los registros de ambas fuentes. `EXPLAIN` confirmó el uso del índice derivado en ambas tablas. No quedaron migraciones pendientes, ni se crearon integraciones/tokens de uso real.
- Se preservaron `.env`, las cinco eliminaciones de recursos compilados y los tres archivos privados que ya existían localmente. Se añadió Sanctum **4.3.3**, sin actualizar otros paquetes de Composer.
- El build informa de deprecaciones Sass y fuentes ausentes preexistentes. Composer informa de avisos de seguridad en **16 dependencias existentes**; Sanctum no figura entre las afectadas. Su corrección requiere un trabajo de actualización de dependencias separado.
- No se modificó producción ni se enviaron mensajes reales. Queda pendiente verificar en cPanel las columnas virtuales indexadas, HTTPS/proxy, entrega de `Authorization` y una consulta externa expresamente autorizada tras crear su integración.
