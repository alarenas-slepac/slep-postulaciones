# Padrón completo: actualización segura

## Previsualización y resolución manual implementadas

La ruta existente de carga masiva, restringida a `admin`, ahora recibe la acción
`previsualizar`. Exige confirmar que el archivo contiene el padrón completo,
valida la primera hoja y persiste una revisión separada del personal vigente.
El importador anterior se conserva como método privado, sin acceso por rutas.
El trabajo local de aplicación definitiva se conserva, pero está bloqueado en
`PadronAplicacionService` tanto para llamadas directas como para POST. La resolución
manual está habilitada por separado en `PadronResolucionService`. La existencia
de las tablas de aplicación no habilita escrituras. No cambiar este bloqueo hasta
completar las validaciones de compatibilidad histórica descritas más abajo.

- Campo nullable `reemplazos_personal.fecha_antiguedad`. Es opcional en Excel;
  si falta o está vacío, la propuesta conserva el valor existente.
- Coincidencias inequívocas por RUT y composición contractual conservan el ID
  como referencia. No se ejecutan inserciones, actualizaciones ni bajas de personal.
- La base de comparación usa el último período disponible por establecimiento
  hasta el período del archivo; busca candidatos históricos para reincorporaciones.
  Una carga anterior al período máximo de la base queda bloqueada para autorización.
- Cambios ambiguos muestran candidatos sin seleccionar un ID automáticamente.
- Asignaciones activas del año se informan por RUT o ID; no se trasladan ni eliminan.
- Filas inválidas, duplicadas, RBD desconocidos o períodos mezclados se muestran;
  cualquier error impide proponer bajas por ausencia como si el archivo fuera válido.
- Jornada total considera los registros seleccionados del RUT entre RBD y financiamientos
  cuando tiene al menos un contrato docente. Básica/Media no se suman nuevamente.
  Una persona exclusivamente asistente no queda sujeta a este control docente.
- Sobre 44 horas se permite autorización con justificación de 10–2.000 caracteres,
  usuario, fecha, revisión y jornada. No se sobrescribe una autorización existente.
  La autorización no implica aplicar el registro al padrón.
- Una huella de la base y dependencias detecta revisiones desactualizadas.
- Declaración de Sostenedores mantiene prioridad. Los contratos desconocidos se
  marcan para revisión.
- Dotación y el selector de titulares de Solicitudes de Reemplazo usan alcances
  explícitos de vigencia y excluyen contratos de reemplazo/suplencia, tanto de
  docentes como de asistentes. El POST de creación también valida esta condición.
  La edición que conserva el mismo titular y las relaciones históricas no reciben
  un filtro global. No se borran contratos, documentos ni asignaciones.
- Las autorizaciones vuelven a comprobar el estado persistido bajo bloqueo de la
  revisión; una instancia antigua no permite autorizar una revisión ya cerrada.

## Resolución manual y referencias históricas (2026.9.8.475)

- El administrador puede seleccionar un candidato, confirmar una nueva línea o
  confirmar una baja propuesta por ausencia ambigua. No cambia personal ni documentos.
- Cada decisión requiere justificación; las correcciones se anexan al historial
  sin sobrescribir decisiones anteriores. Los reenvíos idénticos no duplican auditoría.
- Un ID no se puede seleccionar en dos filas. Cuando se vincula una ausencia a una
  fila del archivo, se muestra como cubierta y no corresponde confirmar su baja.
- Un token de versión de la decisión rechaza formularios abiertos antes de otra
  corrección. El bloqueo de la revisión serializa decisiones de la misma carga.
- No se puede resolver un archivo inválido, una revisión cerrada/desactualizada
  ni clasificar arbitrariamente un tipo de contrato desconocido: se corrige el Excel.
- El resumen de resolución abarca todas las páginas y conserva las propuestas
  originales como referencia. La comparación muestra los datos del candidato elegido.
- `PadronDependenciasService` informa referencias por ID en solicitudes de reemplazo,
  cometidos, incumplimientos y bloqueos de personal. Muestra hasta 20 referencias
  por ID con el conteo total, sin copiar el contenido de los documentos al inventario.
- El análisis incorpora una huella de esas referencias, incluidos sus cambios.
  Las revisiones de la versión anterior deben regenerarse; no se migran sus decisiones.
- Este inventario no sustituye la protección histórica: las relaciones por RUT,
  los lectores indirectos y el uso de valores anteriores siguen pendientes de revisión.

No requiere migraciones nuevas respecto de 2026.9.8.474; deben estar instaladas
las dos migraciones del padrón para registrar decisiones.

## Copia contractual por documento (2026.9.8.476)

- La migración `2026_09_08_150000_add_padron_snapshot_to_documentos` agrega una
  columna JSON nullable a solicitudes de reemplazo, cometidos e incumplimientos.
  No actualiza documentos existentes ni elimina información.
- Al guardar mediante esos modelos, se captura el contrato si todavía no tiene
  copia. Con el mismo titular se conserva; al cambiarlo se captura el nuevo y se
  conserva el anterior. Desvincular una referencia tampoco borra sus copias previas.
- Las propiedades de relación `funcionarioTitular`, `funcionarioPadron` y
  `reemplazoPersonal` leen la copia cuando existe, incluso mediante carga anticipada
  o selección parcial de columnas. La representación histórica no permite guardar
  ni eliminar el registro del padrón mediante sus métodos `save`/`delete`.
- El JSON no se incluye en la serialización habitual del documento. La copia conserva
  datos contractuales, no archivos adjuntos, y no cambia IDs ni asignaciones.
- `PadronHistorialService::congelarReferencias` exige una transacción; captura las
  referencias sin copia **antes** de modificar personal. No cambia FK, estado ni
  timestamps del documento, no sobrescribe copias existentes y falla si falta la
  migración o si una copia no corresponde al ID. Está conectado al flujo de aplicación
  todavía bloqueado; no se ha ejecutado sobre datos reales.
- Una captura previa a una actualización conserva los valores disponibles en ese
  momento: no reconstruye contratos que hubieran sido modificados anteriormente.
- Los documentos sin copia conservan su lectura anterior para permitir el despliegue
  gradual. Instalar esta migración no habilita la aplicación definitiva.

**Alcance pendiente:** una consulta `funcionarioPadron()->first()`, un `whereHas`
o un JOIN sigue consultando el padrón actual; no pasa por la propiedad histórica.
Los filtros identificados en `SolicitudReemplazoGestionController` e
`InformesController` se adaptaron explícitamente en 2026.9.8.478; sigue pendiente
completar el inventario de otras consultas indirectas antes de habilitar la carga
definitiva. La lectura de rendiciones y la edición de solicitudes se adaptaron
en la etapa siguiente.

## Edición y consultas contextuales de solicitudes (2026.9.8.477)

- Al mantener el mismo titular, `FuncionarioEstab/SolicitudReemplazoController`
  usa la propiedad histórica para validar estatuto y la distribución guardada en
  `SolicitudReemplazoJornada` para mostrar y validar máximos por financiamiento.
  No reconstruye esas horas desde las filas actuales del padrón.
- La pantalla de edición envía el ID de solicitud en la URL de detalle del titular.
  Tanto ese detalle como la consulta de regla mínima validan que la solicitud
  pertenezca al establecimiento del usuario antes de usar el contexto histórico.
  Un traslado posterior del titular no cambia la propiedad de la solicitud.
- Elegir otro titular conserva las restricciones de pertenencia, vigencia y tipo
  contractual en el guardado; utiliza su distribución vigente y conserva la copia
  anterior en el historial del documento. La creación no admite titulares antiguos
  por el hecho de existir solicitudes históricas.
- Si existe copia contractual pero faltan jornadas guardadas, se exige revisión:
  no se sustituyen silenciosamente por las horas actuales. Los registros anteriores
  sin copia ni jornadas mantienen la compatibilidad de lectura previa; no es posible
  reconstruir automáticamente información que nunca se guardó.
- `CometidoFuncionarioRendicionController` obtiene los antecedentes contractuales
  para categoría mediante `funcionarioPadron` y no mediante una consulta directa
  que eluda la copia. No cambia reglas ni montos de viático/reembolso.
- No se modificaron rutas, permisos, asignaciones ni datos productivos. La aplicación
  definitiva del padrón sigue bloqueada, incluidos POST y llamadas al servicio.

## Filtros históricos de nóminas e informes (2026.9.8.478)

- `FiltraTitularHistorico` agrega alcances SQL explícitos al modelo de solicitud;
  no altera globalmente las relaciones ni los permisos. La búsqueda se ejecuta
  antes de paginar, contar o limitar los resultados.
- Gestión de solicitudes (UATP, validación y otras), finiquitos y exportaciones
  buscan nombre y RUT en la copia contractual cuando existe. Los informes BRP y
  DIPRES usan el estatuto histórico para seleccionar titulares docentes.
- Las solicitudes anteriores relacionadas comparan el RUT histórico, sin sumar
  coincidencias contradictorias del padrón actual o del RUT alternativo. Para
  documentos sin copia se mantiene la consulta actual y su alternativa anterior.
- Las ramas de consulta están agrupadas: una coincidencia histórica no elude
  filtros de establecimiento, estado, fechas ni las restricciones de cada nómina.
- Sin copia, o antes de instalar su columna, se conserva el filtro del padrón
  actual. Una copia con versión o ID incompatibles no habilita esa alternativa.
- Se conserva el formato de las exportaciones y los datos contractuales leídos.
  No hay migraciones nuevas, cambios de rutas ni habilitación de la aplicación.

## Conflictos con asignaciones (2026.9.8.479)

- `PadronConflictosAsignacionService` revisa todas las asignaciones activas del año
  contra las propuestas y decisiones efectivas de la carga. Es de solo lectura y
  alimenta tanto la pantalla como los bloqueos del plan de aplicación.
- Cada asignación afectada muestra ID, RUT, RBD, horas, total asignado por RUT y
  establecimiento, cobertura, fuente, exclusiones y motivos. Los conteos abarcan
  toda la carga; la tabla tiene paginación independiente de 20 asignaciones.
- Bloquea IDs sin destino, traslados a otro RBD, RUT contradictorio, contratos
  de reemplazo/suplencia, incompatibilidad de estamento y horas sin cobertura.
  Conservar otro contrato del mismo RUT no sustituye un vínculo por ID existente.
- Suma componentes docentes de Jornada del mismo RUT y establecimiento, sin volver
  a sumar Básica/Media. Una declaración compatible con horas positivas reemplaza
  esa base, no se suma. Sigue la selección actual de Dotación por RBD o RUT
  almacenado normalizado y última declaración; aplica prioridad del estamento
  declarado y descuenta exclusiones docentes del año y establecimiento.
- La declaración no crea una incorporación ni permite cubrir desde otro RBD si
  no hay contrato regular propuesto en el establecimiento. Estamentos mixtos o
  contradictorios se marcan para revisión. Asistentes con múltiples líneas y sin
  horas declaradas quedan bloqueados: el consumidor actual usa una línea
  representativa, por lo que no se presume la suma de todas sus jornadas.
- Cambios de contrato regular, financiamiento, estatuto o reducción de jornada
  se informan; solo se bloquean cuando existe incompatibilidad o falta de cobertura.
  Una misma asignación se cuenta una vez aunque tenga varios motivos. El total
  del RUT/establecimiento repetido en la tabla es referencial, no sumable por fila.
- Resolución: corregir la correspondencia mediante las decisiones existentes o
  revisar manualmente las asignaciones en Dotación, con los permisos habituales,
  y analizar nuevamente el archivo completo. No hay botón de omitir conflictos
  ni traslado/borrado automático. La excepción de más de 44 horas no los levanta.
- La huella pasa a versión 3 e incluye asignaciones completas, declaraciones y
  exclusiones. Las revisiones anteriores quedan obsoletas y requieren nuevo análisis.
  Cambios externos posteriores también invalidan decisiones/autorizaciones; los
  diagnósticos de revisiones obsoletas o archivos inválidos se etiquetan orientativos.
- No requiere nuevas migraciones, rutas ni permisos. La aplicación definitiva
  continúa bloqueada: este diagnóstico no sustituye las pruebas transaccionales,
  la revisión de cambios de año y el cierre del inventario de consumidores.

## Escritura transaccional bajo pruebas (2026.9.8.480)

- La aplicación continúa deshabilitada en el servicio real. Las pruebas usan una
  instancia derivada, exclusiva de `testing` y SQLite `:memory:`, sin cambiar la
  constante ni los bindings de rutas. No se ha aplicado ningún padrón real.
- El plan enumera las bajas: únicamente ausencias propuestas o ambiguas resueltas,
  excluyendo IDs reutilizados en filas del archivo. Se elimina el barrido general
  de registros vigentes del año. Versiones anteriores que no figuran en las bajas
  mantienen sus valores; los alcances de vigencia existentes evitan reapariciones
  anteriores al período de la carga completa aplicada.
- La escritura conserva ID, `row_hash`, creación y creador de registros existentes.
  Las incorporaciones reciben un hash por revisión/fila. La antigüedad omitida o
  vacía conserva el valor anterior. Solo se aceptan campos de la plantilla; IDs,
  hashes y otras columnas internas no se copian del contenido de la fila.
- Captura documentos antes de modificar los IDs afectados, dentro de la misma
  transacción. Audita actualización, reactivación, incorporación y desactivación
  con valores anteriores/nuevos y usuario. No modifica asignaciones ni declaraciones.
- Una huella de confirmación comprende revisión, filas, decisiones y autorizaciones.
  Se compara antes/después de calcular el plan, al preparar la pantalla y de nuevo
  bajo bloqueo al aplicar. Un cambio posterior exige revisar y confirmar otra vez.
- Se bloquean la fila global de control, la revisión, sus decisiones/autorizaciones,
  personal y dependencias presentes antes de revalidar y escribir. Se rechaza entrar
  desde una transacción externa que pudiera contener una lectura antigua.
- La huella de base pasa a versión 4 e incluye aplicaciones anteriores. Incluso
  si no cambian horas, una carga aplicada invalida otra revisión de la base anterior.
  Las revisiones previas a este parche deben regenerarse.
- Las pruebas comprueban rollback tras un fallo entre escrituras y ante protección
  histórica incompleta, repetición sin duplicados y rechazo de una segunda revisión
  después del commit de la primera. Esa secuencia **no es una prueba de dos sesiones
  MySQL ejecutándose simultáneamente**.
- La prueba inicial de cambio de año verificaba las copias de documentos, pero no
  la lectura anual de Dotación. La auditoría 2026.9.8.483 confirmó pérdida de esa
  lectura y reemplaza esa prueba por un rechazo antes de escribir, descrito abajo.

### Validación MySQL: laboratorio y pendientes (entorno aislado)

La ejecución local de 2026.9.8.493 y sus límites se documentan en
[PADRON_MYSQL_CONCURRENCIA.md](PADRON_MYSQL_CONCURRENCIA.md). Se verificó recuperación
e idempotencia con procesos independientes, pero se detectaron confirmaciones
obsoletas aceptadas bajo REPEATABLE READ y falta de certificación del protocolo de
escritores externos. Estos hallazgos impiden cerrar la validación y habilitar la
aplicación. El esquema de prueba es reducido y sintético; no equivale a producción.

Antes de habilitar, usar una base MySQL desechable con datos sintéticos y la misma
versión/configuración de producción, nunca la base productiva. Verificar motores
transaccionales de todas las tablas participantes y el aislamiento efectivo.
Con dos conexiones independientes comprobar: misma revisión simultánea, revisiones
distintas de la misma base, cambios/altas concurrentes en personal, documentos,
asignaciones, declaraciones y exclusiones, además de timeout/deadlock y reintento.
Debe verificarse que no haya escrituras parciales, pérdida de actualizaciones ni
lecturas antiguas tras esperar un bloqueo. Los bloqueos de filas añadidos no se
presentan como garantía contra inserciones concurrentes bajo cualquier aislamiento.
Guardar los resultados de esta prueba antes de decidir la habilitación.

## Reemplazos más recientes hasta 44 horas (2026.9.8.481)

- Solo se selecciona automáticamente el tipo exacto `REEMPLAZO` (normalizando
  espacios y mayúsculas), cuando el total del RUT supera 44 h. No afecta `SUPLENCIA`
  ni variantes textuales de otros contratos. Comprende reemplazos docentes y asistentes.
- Se conservan intactos los demás contratos; sus jornadas consumen primero el
  margen de 44 h. Se ordenan reemplazos por Fecha_Ingreso descendente y, ante igual
  ingreso, Fecha_Termino descendente. Componentes con ambas fechas iguales se
  consideran juntos, entre todos los RBD y financiamientos del RUT.
- Se conserva un conjunto de los más recientes que quepa completo. Al llegar a
  un conjunto más antiguo que no cabe, se omite ese conjunto y los posteriores:
  no se fraccionan líneas ni se buscan combinaciones antiguas para llenar cupo.
- Si faltan fechas o el conjunto más reciente ya supera el margen, no se omite
  ninguna línea del RUT: se informa un error para corregir el archivo. La autorización
  de exceso no levanta estos errores. No se ocultan filas inválidas o duplicadas;
  con períodos mezclados no se ejecuta la selección automática.
- Las filas omitidas permanecen almacenadas en la revisión con datos originales,
  motivo y referencias a las filas seleccionadas. Tienen etiqueta, conteo, filtro
  y estado propios. No suman en exceso docente, correspondencias ni destinos de
  escritura y no se pueden reactivar mediante una decisión de correspondencia.
- Omitir una fila entrante no borra ni da de baja automáticamente un ID existente.
  Las ausencias del mismo RUT mantienen revisión explícita. Las asignaciones cuyo
  ID queda sin destino siguen bloqueando; no se trasladan a otro contrato.
- El plan de aplicación vuelve a calcular la selección y bloquea omisiones
  incompatibles con las fechas/jornadas. La huella de base pasa a versión 5:
  analizar nuevamente las cargas anteriores, sin reutilizar sus autorizaciones.
- No agrega migraciones, rutas ni permisos. La escritura definitiva continúa
  bloqueada; las pruebas de aplicación solo usan SQLite en memoria y datos sintéticos.

## Transición de reemplazo a contrato regular (2026.9.8.482)

- Antes de seleccionar reemplazos hasta 44 h, se identifica por RUT si un
  `REEMPLAZO` terminó estrictamente antes del ingreso a un contrato regular
  posterior con jornada positiva. Se omite el reemplazo de la propuesta vigente,
  incluso si la suma no supera 44 h o cambia el RBD/financiamiento. Comprende
  docentes y asistentes y no depende del orden de las filas del archivo.
- La fecha de término es inclusiva: terminar e ingresar el mismo día no acredita
  sucesión. Fechas incompletas, errores o duplicados no se ocultan. `SUPLENCIA`,
  tipos desconocidos y contratos sin jornada positiva no acreditan un sucesor regular.
- El resto de los reemplazos mantiene la selección por fechas y margen de 44 h.
  Los contratos regulares no se recortan; sus excesos conservan revisión y
  autorización justificada. Esta regla no es un cálculo general de intervalos
  para todos los tipos contractuales.
- La revisión conserva la fila anterior y explica a qué filas posteriores
  corresponde la transición. No elimina IDs, referencias ni asignaciones; el
  plan vuelve a validar las omisiones. Las pruebas en memoria verifican conservar
  el ID al actualizar el contrato y la copia contractual previa de los documentos.
- La huella pasa a versión 6: las revisiones anteriores deben regenerarse, sin
  reutilizar decisiones ni autorizaciones. No agrega rutas o migraciones ni
  habilita la aplicación definitiva del padrón.

## Protección anual y estadísticas históricas (2026.9.8.483)

- Auditoría y pendientes detallados en [PADRON_AUDITORIA_CONSUMIDORES.md](PADRON_AUDITORIA_CONSUMIDORES.md).
  No se presenta el inventario como certificación de todos los módulos.
- `DotacionEstablecimientoCalculator::docentes/asistentes` lee el padrón actual
  filtrado por año. Reutilizar un ID con otro año hace desaparecer su contrato de
  esa consulta; congelar documentos no lo evita. Se reproduce con datos sintéticos.
- El plan bloquea reutilizar un ID cuyo año difiere del de la carga y desactivar
  una versión vigente de otro año. Lee el año real por ID, no la copia anterior
  enviada en una fila. Abarca docentes/asistentes, con o sin documentos/asignaciones.
  Las bajas ya inactivas no generan un nuevo cambio ni ese bloqueo.
- Los motivos aparecen en los bloqueos existentes de la revisión y se recalculan
  dentro de la aplicación transaccional de prueba, antes de congelar o escribir.
  Autorizar horas o confirmar una baja no los levanta. No duplicar registros ni
  cambiar el año de la planilla como solución: falta implementar lectura histórica.
- Las actualizaciones del mismo año siguen sujetas a los controles previos; esta
  protección no congela meses ni resuelve por sí sola el historial de traslados.
  La aplicación real permanece bloqueada por los pendientes del inventario.
- Estadísticas obtiene la identidad del ranking por ID desde la solicitud de
  mayor ID con copia histórica en el establecimiento filtrado. No cambia conteos,
  agrupaciones ni filtros. Sin copia usa la relación actual, incluso antes de la
  migración; una copia inválida requiere revisión y no se sustituye silenciosamente.
- La huella pasa a v7: regenerar revisiones previas. No se crean migraciones ni
  se modifican rutas, permisos, asignaciones o datos productivos.

## Memoria de carga masiva (2026.9.8.484)

- Se elimina la lectura completa y posterior `json_encode` conjunto de asignaciones,
  declaraciones y exclusiones. Las filas se leen en lotes de 100: todas sus columnas
  alimentan una huella incremental, pero solo se retienen los campos usados por la
  conciliación/cobertura. No se ignoran cambios en observaciones o metadatos.
- El inventario documental también usa lotes de 100, evitando que PDO MySQL almacene
  el resultado completo de un cursor. Conserva conteos y hasta 20 referencias por ID.
- El padrón base se recorre en lotes de 250 respetando año/mes/ID; comparte las filas
  seleccionadas y libera los índices históricos antes de analizar dependencias.
  La huella de base usa las huellas completas de origen, sin volver a serializar
  todas las estructuras derivadas. Las declaraciones se reutilizan desde esa lectura.
- La confirmación de pantalla/escritura recorre filas, decisiones y autorizaciones
  por lotes y mantiene la comprobación de cambios posteriores. El plan y el diagnóstico
  solo cargan las columnas necesarias de las filas de revisión, no copias anteriores,
  candidatos ni observaciones que ya fueron persistidos y siguen visibles en pantalla.
- Huella base v8 y confirmación v2: generar una nueva previsualización. No se cambian
  límites de PHP, rutas, migraciones, permisos, datos productivos ni el bloqueo global.

Validación de volumen: `php -d memory_limit=128M vendor/phpunit/phpunit/phpunit --filter PadronMemoriaTest`.
Usa 6.500 filas sintéticas y columnas de texto extensas en asignaciones/declaraciones;
ejercita creación/persistencia de revisión, comprobación de vigencia y preparación
de pantalla. Solo sustituye el lector Excel para aislar la memoria de conciliación
y BD. No es una importación del archivo real ni una reproducción de la BD productiva;
no garantiza el mismo pico para cualquier volumen de candidatos/documentos. La prueba
pesada se ejecuta en proceso separado para no heredar el pico de otras pruebas.

## Diagnóstico local de un Excel (herramienta de pruebas)

`php scripts/padron_diagnosticar_archivo.php "ruta/al/archivo.xlsx"` ejecuta el lector
real y la conciliación de contenido sin arrancar Laravel, leer `.env`, consultar
la base ni guardar registros. No cambia el original ni genera otro Excel.
Devuelve únicamente conteos, motivos y números de fila; sustituye RUT, nombres y
fechas personales por identificadores efímeros en memoria antes de conciliar.
Las fechas se sustituyen por rangos ordenados para conservar la cronología de
selección sin exponer los valores originales.
La clave aleatoria y esos identificadores no se guardan ni aparecen en la salida.
Los mensajes de excepciones de librerías no se vuelcan a terminal ni logs.

Comprueba períodos, antigüedad, errores de campos, duplicados y la suma de jornadas
por RUT con las reglas existentes. Muestra hasta 20 ejemplos por motivo/exceso,
pero los conteos comprenden todo el archivo. Los excesos incluyen líneas inválidas
o duplicadas: corregirlas antes de evaluar autorizaciones; no se deduplican ni
se autorizan automáticamente. Se informa el conteo conjunto de reemplazo/suplencia,
los reemplazos omitidos y los RUT docentes con exceso antes y después de la selección.
El número de filas de esos RUT se presenta separado del número de personas.

**Límite:** el catálogo de RBD es provisional y proviene del propio archivo.
Este diagnóstico no verifica que existan en la base, ni calcula altas, bajas,
traslados, conservación de IDs o cobertura de asignaciones reales. Tampoco
certifica escritura, concurrencia MySQL o compatibilidad histórica. No habilita
la aplicación definitiva ni cambia las reglas de la pantalla de previsualización.
Las pruebas unitarias de la herramienta usan exclusivamente datos sintéticos,
incluido un lote de 6.500 filas; no se versionan planillas ni registros reales.

## Instalación

Ejecutar las migraciones pendientes con PHP 8.3 antes de usar la previsualización.
La primera migración añade una columna y tres tablas de revisión. La segunda
añade las tablas y marcas reservadas para decisiones, auditoría y control de
aplicación. Ninguna borra ni reconstruye personal existente ni habilita el botón
de aplicación. Su reversión está bloqueada para evitar pérdida de auditoría;
requiere una migración específica autorizada. No usar `migrate:fresh` ni `db:wipe`.
La tercera migración agrega las copias contractuales descritas arriba y también
bloquea una reversión que elimine historial.

No se han ejecutado estas migraciones sobre la base habitual ni sobre producción.
Además de SQLite en memoria, el laboratorio MySQL ejecuta cuatro migraciones
existentes en bases nuevas, aisladas y exclusivamente sintéticas; nunca sobre el
padrón real. No hay migraciones nuevas en este parche.

## Etapas pendientes antes de habilitar aplicación definitiva

1. Validar operativamente la resolución de conflictos de asignaciones ya expuesta:
   las correcciones se realizan en correspondencias o Dotación, seguidas de un nuevo
   análisis. La decisión del ID no mueve asignaciones ni autoriza pérdidas de cobertura.
2. Resolver los hallazgos del laboratorio MySQL y repetir el protocolo con esquema
   completo y configuración equivalente a producción. La recuperación local ya
   se probó; la validación de concurrencia todavía no está aprobada.
3. Completar la auditoría de consumidores ante cambios de año y traslados, especialmente
   lectores indirectos de Dotación histórica, antes de habilitar la escritura.
4. Adaptar consumidores de vigencia sin perder referencias históricas de trámites,
   solicitudes, licencias y asignaciones existentes.
5. Completar los lectores históricos: están implementadas la copia por documento,
   las propiedades de relación, edición contextual y los filtros identificados
   de nóminas/informes; falta finalizar la revisión de otras consultas indirectas.
6. Verificar prioridad de Declaración de Sostenedores y regresiones en cálculos,
   exportadores y módulos dependientes antes de habilitar el botón de aplicación.

El trabajo de aplicación debe validar transaccionalmente las reglas de cobertura
anteriores y resolver la selección de líneas representativas de asistentes cuando
no es inequívoca. También debe revisar ausencias y cambios de año, sin desactivar
versiones históricas indiscriminadamente. No se presenta la aplicación definitiva
como funcionalidad terminada ni lista para producción.

Pruebas específicas: `php vendor/phpunit/phpunit/phpunit --filter Padron`.
Regresión relacionada: `php vendor/phpunit/phpunit/phpunit --filter "Padron|Dotacion|SolicitudReemplazo"`.
Incluye bloqueo de aplicación con ambas migraciones instaladas, POST directo,
previsualización sin cambios de personal/asignaciones, prioridad de declaración,
selector de titulares y lectura de relación/distribución histórica; además decisiones
manuales, correcciones auditadas, doble selección, reenvíos, versiones de pantalla,
referencias nuevas/modificadas y mantenimiento del bloqueo de escritura definitiva.
`PadronHistorialTest` verifica capturas, conservación de IDs/fechas/estados, rollback,
lecturas parciales y anticipadas, copias diferentes para el mismo ID, cambio o
desvinculación del titular, ausencia de migración y protección de solo lectura.
`PadronConsumidoresTest` incluye edición después de traslado y reducción de jornada,
máximos históricos por financiamiento, regla mínima por estatuto anterior, rechazo
de contextos de otro RBD, cambio válido de titular y exclusiones contractuales.
`PadronFiltrosHistoricosTest` verifica búsquedas por nombre/RUT, estatuto anterior,
paginación, aislamiento de filtros, documentos sin copia/columna, copias distintas
por ID, parámetros SQL enlazados, filas de informes, filtros de exportación por
etapa y solicitudes anteriores relacionadas. La ejecución funcional usa SQLite
en memoria; no constituye una validación de despliegue en MySQL productivo.
`PadronConflictosAsignacionTest` verifica cobertura por RUT/RBD, prioridad de la
declaración, exclusiones, traslados, ausencias, estamentos, componentes docentes,
composiciones ambiguas, decisiones que recalculan el diagnóstico, revisiones
obsoletas, paginación y ausencia de escrituras sobre personal/asignaciones.
`PadronAplicacionTransaccionalTest` alcanza el flujo de escritura solo en memoria:
comprueba bajas explícitas, IDs, campos internos, reactivaciones, copias históricas,
rollback intermedio, reintentos, revisiones competidoras secuenciales y versiones de
confirmación, incluyendo cambios intercalados durante el cálculo del plan.
