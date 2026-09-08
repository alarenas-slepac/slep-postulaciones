# Versiones del padrón por período — 2026.9.8.485

## Estado

Implementación y pruebas sintéticas en SQLite en memoria. La aplicación definitiva
sigue deshabilitada, incluidos POST y llamadas directas al servicio. Tampoco se
levanta todavía el bloqueo de actualización/baja entre años: falta certificar
lectores indirectos y escrituras ajenas a la carga, además de concurrencia MySQL.
No se importaron registros reales ni se ejecutaron migraciones del entorno.

## Identidad y captura

- `reemplazos_personal.id` sigue siendo la identidad contractual. Las copias tienen
  su propio ID y guardan `personal_id` con el ID original; no sustituyen relaciones.
- La nueva migración `2026_09_08_160000_create_padron_periodo_versiones` solo crea
  estructura. No hace un backfill ni escribe personal. Su reversión está bloqueada
  para no borrar historial sin una migración específica autorizada.
- Antes de la primera aplicación se capturan todos los períodos válidos que todavía
  existen en la base. Un período inválido aborta la operación. Se conserva lo que
  existe en ese momento; no se reconstruyen valores ya perdidos anteriormente.
- Antes de aplicaciones posteriores se captura nuevamente el período abierto y
  cualquier período sin copia. No se recapturan meses cerrados desde filas actuales
  que pudieron haber cambiado de mes, establecimiento o vigencia.
- Después se captura el período de la revisión, aunque no tenga filas. Cada copia
  tiene revisión, origen, usuario, cantidad, huella incremental y marca de finalización.
  Las correcciones del mismo mes anexan versiones, no sobrescriben las anteriores.
- Las filas incluyen columnas consultables y el JSON contractual original. Se leen
  e insertan en lotes de 100, sin acumular el padrón completo para generar la huella.
- Capturas, cambios de personal, auditoría, copias documentales y cierre de revisión
  comparten la transacción del aplicador y su bloqueo global. Un fallo revierte todo.
  El reintento de una revisión aplicada no genera nuevas versiones.

## Lecturas

- El período actual continúa consultando la tabla operativa: las correcciones
  manuales existentes siguen visibles y se congelan antes del siguiente avance.
- Para un mes anterior con copia finalizada se lee su última versión; el modelo
  proyecta `personal_id` como `id` y sus métodos `save/delete` rechazan escrituras.
  Sin copia se conserva la lectura anterior, compatible con el despliegue gradual.
- El selector mensual conserva períodos ya ausentes de la tabla operativa. Búsqueda,
  conteos, filtros de establecimiento, paginación y CSV comparten la fuente temporal.
- La nómina archivada no permite editar, bloquear o desbloquear el ID actual desde
  sus acciones. Los endpoints también rechazan un contexto archivado. El traspaso
  de bloqueos que involucra períodos archivados se rechaza hasta adaptar ese ciclo.
  Los bloqueos mostrados siguen siendo los actuales y se advierte explícitamente.
- Dotación usa la última base contractual por establecimiento y año. Una revisión
  completa impone un piso temporal: si un establecimiento queda sin personal,
  no reaparecen sus filas de meses anteriores. Se mantienen los filtros de vigencia
  y exclusión de reemplazo/suplencia. Los exportadores que usan el mismo calculador
  reciben esa base, sin duplicar contratos.
- No se introduce un filtro global al modelo ni se modifica el criterio de identidad
  histórica/elegibilidad de licencias, descuentos u otros trámites.
- La huella de revisión pasa a v9 e incluye el catálogo de versiones. Regenerar
  previsualizaciones anteriores; no reutilizar sus decisiones o autorizaciones.

## Límites y siguiente etapa

Esta es una versión del **padrón contractual**, no una fotografía de todos los
insumos de Dotación. Declaraciones de Sostenedores mantienen su prioridad y lectura
existente; planes, títulos declarados, asignaciones, exclusiones, establecimientos
y bloqueos no se versionan aquí. Sus cambios independientes pueden afectar un
informe anual. No se afirma que todos los informes queden congelados.

La lectura por año se prueba simulando el movimiento del ID exclusivamente en la
base efímera. Esas pruebas no levantan el bloqueo anual del aplicador real.

Quedan pendientes los consumidores de vigencia y los lectores indirectos del
[inventario](PADRON_AUDITORIA_CONSUMIDORES.md), la adaptación del ciclo de bloqueos,
las escrituras manuales concurrentes y las pruebas de despliegue/rollback MySQL
aislado. El escritor también conserva lecturas globales para sus bloqueos: esta
etapa no certifica su pico de memoria con volúmenes reales.

## Validaciones

- `PadronPeriodoTest`: captura, ID estable, traslado, bajas, cambio de año,
  período vacío, correcciones del mismo mes, lectura operativa actual, lotes,
  rollback, migración ausente, versiones incompletas, CSV, paginación/búsqueda,
  aislamiento por establecimiento y rechazo de edición histórica.
- `PadronAplicacionTransaccionalTest`: integra la nueva captura en el escritor
  de prueba; verifica rollback e idempotencia incluyendo las tablas históricas.
- Regresión: `php vendor/phpunit/phpunit/phpunit --filter "Padron|Dotacion|SolicitudReemplazo"`.

La instalación de esta migración no crea versiones visibles por sí sola. Las copias
se generarán al aplicar una revisión cuando se habilite ese flujo tras su validación.
