# Padrón: coordinación de escritores (2026.9.9.495)

Continuación 496: [consumidores, bloqueo por RUT y verificación de entorno](PADRON_CIERRE_CONSUMIDORES.md).
Se incorporan las entradas de anexos de viático y excepciones de permiso sin goce,
y el servicio de importación de establecimientos también desde CLI. No habilita
la aplicación definitiva ni elimina los límites de certificación descritos aquí.

## Alcance implementado

`PadronEscrituraService::ejecutar` inicia una transacción y adquiere la misma fila
de control (`padron_aplicacion_control`, ID 1) que la aplicación completa. El
callback comienza después de obtener el bloqueo: las lecturas y validaciones
deben estar dentro del callback, no realizadas antes y capturadas en variables.

El control es exclusivo: serializa las operaciones participantes, no solo los
INSERT. No cambia el aislamiento de sesión ni servidor. Mantiene un único
intento para no repetir correos, archivos u otros efectos externos. Los errores
MySQL 1205/1213 se comunican como error de validación, sin ejecutar automáticamente
otra vez la petición. No constituye atomicidad de archivos o notificaciones.

La instancia es scoped; importadores/servicios anidados comparten la transacción
coordinada. Una transacción externa que no entró por el coordinador se rechaza:
no se adquiere el control después de haber leído o bloqueado otros datos.

Sin la tabla de control se conserva el comportamiento previo a esa migración;
el aplicador completo no puede habilitarse sin ella. Con tabla pero sin la fila
ID 1 se rechaza la escritura. No se crea/repara automáticamente el control.

## Entradas HTTP

`CoordinarEscrituraPadron` se instala en el grupo web y tiene prioridad anterior
a `SubstituteBindings`. Así, los modelos de ruta y las lecturas del controlador
son posteriores a cualquier espera. No sustituye autorización, permisos,
validación de vigencia/cobertura ni la captura histórica de los modelos.

La lista exacta y auditable está en `CONTROLADORES`, no en una detección dinámica
de SQL. Incluye las operaciones no seguras de estas familias:

| Familia | Controladores identificados |
| --- | --- |
| Dotación | Asignaciones, exclusiones, funciones, cursos combinados, excepciones de proporción y establecimiento |
| Base de establecimiento/declaraciones | Establecimientos, declaración de sostenedores, catálogos de función, institución y título |
| Personal | ReemplazosController, incluidos sus bloqueos de personal |
| Solicitudes | SolicitudReemplazoController de establecimiento y SolicitudReemplazoGestionController |
| Documentos | IncumplimientoLaboralController y cometidos, informes y rendiciones |
| Excepción GET heredada | OrdenTrabajoPdfController: show/download con regenerar=true |

GET/HEAD ordinarios no adquieren el control. `PersonalImportController` queda
fuera: la previsualización y las decisiones manuales no toman este control, y
la aplicación completa ya debe iniciar su propia transacción. No hay cambios
de rutas ni permisos; sí un cambio de middleware de las entradas seleccionadas.

El pipeline puede devolver un error ya renderizado. Ante respuesta HTTP >=400 o
redirección con nuevos errores de validación se revierte SQL y se devuelve esa
respuesta, conservando los mensajes de sesión. Las descargas transmiten su archivo
fuera del callback: no debe añadirse SQL tardío a funciones de streaming.

## Importadores y servicios

- `SostenedoresImport::import`: coordina lectura/comparación/escritura del lote.
  Con control instalado un fallo revierte el lote SQL completo; conserva IDs al
  actualizar. No se ha añadido ni habilitado una ruta de importación nueva.
- `DotacionProporcionRecalculationService::recalculate`: coordina antes de leer
  porcentajes y asignaciones. Desde el controlador coordinado comparte su ámbito.
- El antiguo upsert de `PersonalImportController` sigue siendo privado y sin
  acceso por rutas. No se habilitó ni se presenta como escritor certificado.
- Nuevos comandos/jobs deben entrar por el servicio antes de leer datos; no se
  intercepta arbitrariamente todo Eloquent o todo SQL de la aplicación.

## Evidencia y límites

La matriz de 46 escenarios (`78dd0b311296fe88`) terminó con 36 aprobados y 10
observaciones de SQL externo, sin fallos de aserción. Los doce casos coordinados
(seis por aislamiento) comprobaron:

- El escritor espera al aplicador; no ejecuta su callback durante la espera.
- Tras el commit observa jornada/período nuevos. Las validaciones representativas
  rechazan el contrato ya inactivo, el período antiguo y una exclusión sin saldo.
- El modelo real `SolicitudReemplazo` captura el contrato actualizado al insertar
  después de esperar, usando su trait de historia dentro de la transacción.
- La declaración como fuente autoritativa puede guardarse después de esperar;
  no se inventó un bloqueo de declaración por falta de cobertura.
- Si el escritor obtiene primero el control, el aplicador espera y rechaza la
  confirmación anterior a ese cambio.

Las validaciones de las sondas de personal/asignación/exclusión son callbacks
representativos del laboratorio; **no son ejecuciones de todos los controladores
concurrentes sobre el esquema completo**. La prueba de pipeline HTTP valida
prioridad y binding mediante una ruta sintética. La importación real tiene pruebas
de actualización y rollback; el recálculo conserva las regresiones de Dotación.

La herramienta incorpora además `--case=coordinated_timeout`, que verifica una
espera real, rechazo sin repetir el callback y ausencia de cambios parciales.
La matriz completa pasa a 48 escenarios contando ambos aislamientos.
Los dos casos de timeout pasaron en la ejecución `9138fdd8848ae67d` (reporte local
`storage/app/testing/padron_mysql_9138fdd8848ae67d.json`). La matriz de 46 está en
`storage/app/testing/padron_mysql_78dd0b311296fe88.json`; se conservan ambos reportes.

Regresión final con el filtro
`Padron|Dotacion|SolicitudReemplazo|Sostenedores|Declaracion|Cometido|Incumplimiento`:
345 pruebas y 2.457 aserciones aprobadas. Incluye trece pruebas nuevas de servicio,
middleware, orden de binding HTTP, errores y redirecciones, compatibilidad previa
a migraciones, importador real y rollback. Son datos sintéticos y SQLite en memoria,
complementarios a las pruebas de concurrencia MySQL.

Los diez INSERT con SQL directo se conservan intencionalmente como controles
negativos. Ignoran la fila de control: no quedan protegidos por instalar el
middleware. No reemplazar esos resultados por éxitos artificiales ni interpretar
el código de salida 2 como aprobación para habilitar la aplicación.

## Pendiente antes de habilitar

1. Completar el inventario de escritores indirectos, cascadas, comandos/jobs,
   métodos invocados directamente y otros GET que puedan guardar al generar
   documentos. La lista HTTP implementada no es una certificación exhaustiva.
2. Probar cada flujo de negocio real con esquema completo, usuarios/permisos y
   configuración equivalente al hosting, incluyendo validaciones tras cambios
   de año, RBD, cobertura y conservación de historia documental.
3. Medir contención y duración de transacciones con volumen real anonimizado.
   Se serializan escrituras participantes; una importación o generación extensa
   puede hacer esperar a otros usuarios. No se aumentaron timeouts globales.
4. Evaluar efectos de archivos/notificaciones ante rollback y terminar lectores
   históricos. No hacer retries ciegos de operaciones con efectos externos.

La aplicación definitiva continúa deshabilitada. No se modificó producción, no
se importaron datos reales y no se borraron las bases sintéticas del laboratorio.
