# Auditoría de consumidores del padrón — actualizada en 2026.9.8.486

Avance de esta etapa: [versiones por período](PADRON_VERSIONES_PERIODO.md).
Las lecturas principales mensuales y la base contractual anual ya usan copias
cuando existen; no están certificados todos los lectores indirectos ni los demás
insumos históricos de Dotación. El bloqueo anual y la aplicación cerrada se mantienen.
Los hallazgos de 483 descritos abajo documentan la causa original.
En 486 se adaptaron Centro de Operaciones, auto-registro y autocompletado de
trámites para distinguir vigencia de antecedentes históricos; ver alcance más abajo.

## Alcance y estado

Inspección estática de referencias a `ReemplazoPersonal`, `reemplazos_personal`
y relaciones de documentos en `app`, rutas y vistas. Pruebas con datos sintéticos
en SQLite en memoria. Sin consultas a producción ni importaciones de personas.
Una coincidencia de búsqueda identifica un consumidor, no acredita su compatibilidad.
Las referencias dinámicas e indirectas requieren continuar el seguimiento.

La aplicación definitiva continúa deshabilitada. No habilitarla por haber pasado
las pruebas de documentos: existe dependencia directa de períodos del padrón.

## Hallazgos reproducidos y protección implementada

### P0 — Sobrescritura del año contractual

`app/Support/DotacionEstablecimientoCalculator.php`, métodos `docentes` y
`asistentes`, usa `ReemplazoPersonal::padronVigente($anio)` y filtra establecimiento
y año sobre la tabla actual. El modelo también calcula el máximo período por
establecimiento/año y el piso de revisiones aplicadas.

Si el ID cambia de 2026 a 2027, deja de encontrarse en la consulta 2026. Una versión
anterior del mismo RUT puede reaparecer o puede desaparecer el funcionario; no basta
con que la fila siga existiendo. La declaración tiene prioridad para ciertos valores,
pero se aplica después de seleccionar las personas: no restaura la fila faltante.

Alcance derivado: resumen, docentes, asistentes, sobredotación y exportaciones que
reciben `DotacionEstablecimientoCalculator::build`, además de avances de Dotación.
Las asignaciones se conservan, pero los cálculos cruzan contra una base contractual
que habría cambiado. No se necesita una FK de asignación para que exista el riesgo.

Protección: `PadronAplicacionService::bloqueosHistoriaAnual` rechaza actualizaciones
de IDs entre años y bajas vigentes de otro año. El plan las muestra y la escritura
de prueba las revalida. No se habilita un botón para saltarlas. Esto **no implementa
versionado anual** ni corrige retrospectivamente datos ya sobrescritos.

Pruebas: `PadronAplicacionTransaccionalTest` reproduce la pérdida de la consulta
anual pese a una copia documental válida; verifica rechazo sin cambios, asistentes
sin referencias, valores anteriores manipulados y autorización/decisión manual.

### P1 — Identificación del ranking de solicitudes

`Gestion/EstadisticasController::buildTopFuncionarios` leía nombres/RUT de la tabla
actual por ID, eludiendo la copia de cada solicitud. Corregido: carga la relación
histórica y usa la copia de la solicitud de mayor ID disponible dentro del filtro.
La agrupación sigue siendo por ID contractual; dos IDs con igual nombre no se fusionan.
Sin copia conserva el comportamiento anterior; copias inválidas requieren revisión.

Pruebas: `PadronEstadisticasHistoricasTest`, filtro de establecimiento, múltiples
copias, documentos sin copia, fila actual ausente, migración no instalada y conteos.

## Inventario de trabajo

| Consumidor / entrada | Lectura identificada | Estado y acción pendiente |
| --- | --- | --- |
| Dotación: `DotacionEstablecimientoCalculator`, controladores de Dotación y exportaciones/avance | Año + último período por establecimiento; declaración prioritaria | Base contractual versionada en 485; pruebas de docentes/asistentes y traslados entre años. Pendiente certificar lectores indirectos y otros insumos; bloqueo anual conservado. |
| `ReemplazosController::resolvePadronContext/buildPadronQuery` | Selector y consulta mensual, conteos y CSV | Adaptados en 485, con filtro por establecimiento y consulta SQL paginable. Meses archivados de solo lectura; traspaso de bloqueos históricos aún bloqueado. |
| `CentroOperaciones/DatosBaseService::dotacionesPara` | Último período por establecimiento, vigencia y piso de cargas completas | Adaptado en 486: no retrocede a un mes anterior si el último queda inactivo. Mantiene conteos únicos por RUT y reglas propias de Centro de Operaciones. |
| `FuncionarioRegisterLookupService`, `TramiteAutofillService` | Contratos vigentes por RUT y establecimiento | Adaptados en 486: antecedentes no acreditan vigencia; contempla ambigüedad entre RBD, huérfanos actuales y alternativa de solicitudes solo para RUT sin registros de padrón. |
| `LicenciasMedicas/LicenciaFuncionarioResolver`, `Tramites/LicenciaMedicaController` | Administración Central y búsqueda del padrón en el máximo período global | Pendiente: unificar criterio de vigencia y probar edición/importación sin alterar licencias previas. No extender aquí la exclusión de reemplazos de Dotación. |
| `Remuneraciones/ReemplazoPersonalRutService`, `DescuentoCgrController` | Identidad por RUT; prioriza Administración Central y último año/mes/id del RUT | Pendiente: diferenciar identificación histórica de elegibilidad actual. No excluir exfuncionarios de descuentos sin confirmar regla del módulo. |
| `IncumplimientoLaboralController` | Consultas directas en selector, validación y detalle; modelo con copia | Propiedad histórica protegida; pendiente revisar todo el ciclo de edición frente a bajas y traslados. |
| `Tramites/CometidoFuncionarioController` | Búsquedas directas de titulares y período por establecimiento | Rendición adaptada previamente; faltan pruebas completas de edición/selección con padrón actualizado. |
| `FuncionarioEstab/SolicitudReemplazoController`, `ReemplazoSolicitudReglaMinima` | Selector vigente y edición con contexto documental | Protecciones y pruebas anteriores; mantener revisión de rutas indirectas y máximos por financiamiento. |
| `Gestion/SolicitudReemplazoGestionController`, `Gestion/InformesController` | Nóminas, filtros históricos y estatuto | Adaptados previamente con pruebas; no confundir con certificación de todos los informes indirectos. |
| `Gestion/EstadisticasController` | Ranking por ID | Corregido y probado en esta etapa. |
| `Admin/EstablecimientoController::show`, `Establecimiento::personal` | Relación/listado directo de registros sin filtro temporal | Pendiente determinar presentación de archivo histórico versus nómina actual; no imponer filtro global. |
| `Admin/FuncionarioViaticoAnexoController`, `Admin/PermisoSinGoceExcepcionController` | Validación/selección directa de funcionario | Pendientes pruebas de vigencia y conservación de excepciones existentes por RUT/ID. |
| `System/GlobalSearchController` | Búsqueda directa del padrón | Pendiente distinguir resultados históricos/actuales sin romper acceso a documentos anteriores. |
| `ReemplazoPersonalBloqueo::personal` | Relación actual por ID | Pendiente decidir vigencia del bloqueo tras traslado/cambio contractual; no se borra ni traslada automáticamente. |
| `Auth/RegisterRutLookupController` y servicios de autocompletado | Consumidores indirectos de resolución por RUT | Respuesta de registro probada en 486, conservando confirmación por fecha de nacimiento. Pendiente certificación de concurrencia del registro completo y otros lectores indirectos. |
| Servicios de `Padron` y `PersonalImportController` | Conciliación, historial, decisiones, escrituras controladas | Aplicación cerrada; antiguo importador privado sin ruta pública. Nuevos bloqueos no habilitan escrituras. |

Los modelos `SolicitudReemplazo`, `CometidoFuncionario` e `IncumplimientoLaboral`
usan `ConservaPadronHistorico`. Sus propiedades de relación están protegidas;
consultar el método de relación o hacer un JOIN no equivale a leer la copia.

## Vigencia en consumidores (2026.9.8.486)

- `PadronVigenciaService` reutiliza el alcance explícito `padronVigente`, sin
  cambiar globalmente el modelo ni las relaciones históricas. El máximo período
  se calcula entre todas las filas del establecimiento y luego se filtra vigencia.
  Una revisión completa aplicada impone un piso global; una previsualización no.
- Las cargas parciales anteriores conservan el último período propio de cada RBD;
  un mes más reciente de otro establecimiento no basta para declarar una baja.
- Centro de Operaciones conserva el conteo único por RUT, clasificación existente
  y participación de reemplazos/suplencias. No se le impone la exclusión propia de
  Dotación. No cambia totales guardados en reportes ni configuración de anexos.
- Registro y autocompletado consideran todas las líneas vigentes del RUT, incluso
  si existen en RBD con meses distintos. Dos establecimientos vigentes requieren
  regularización: no se selecciona uno solo por tener el mes más reciente.
- Un registro huérfano histórico no invalida el contrato actual correctamente
  asociado. Una fila sin establecimiento en el período global actual, o un ID
  de establecimiento vigente inexistente en el catálogo, requiere regularización.
- Un RUT conocido pero sin contrato actual no se registra como funcionario por su
  antecedente anterior; puede continuar como postulante sujeto a las validaciones
  existentes. No se modifican cuentas, roles o establecimientos de usuarios creados.
- En nuevos trámites ese antecedente devuelve un motivo de falta de vigencia.
  La alternativa original de solicitudes aceptadas/cerradas permanece para personas
  sin filas de padrón; no se amplía a personas cuyo padrón solo conserva antecedentes.
  No se cambia la regla de fechas/estados de esa alternativa en esta etapa.
- Los trámites existentes siguen mostrando sus campos snapshot en edición;
  el update documental no reescribe esa identidad desde el autocompletado actual.
  No se migra ni elimina información histórica.
- Sin columna `vigente` o tabla de revisiones se conserva el criterio temporal
  disponible, sin inventar bajas ni generar errores de columna ausente.

Pruebas: `PadronVigenciaConsumidoresTest` usa exclusivamente SQLite en memoria y
datos sintéticos. Cubre bajas, períodos vacíos, traslados/reincorporaciones con ID
estable, múltiples RBD, normalización y deduplicación, huérfanos, compatibilidad,
confirmación de identidad del endpoint de registro y la alternativa por solicitudes.
La consulta no escribe personal, usuarios ni documentos. No se agregan migraciones,
rutas ni permisos, y no se habilita la aplicación definitiva ni los cambios entre años.

Pendiente inmediato: revisar licencias, cometidos/incumplimientos y el ciclo de
bloqueos según el inventario. La certificación de concurrencia MySQL debe incluir
las escrituras de registro/autocompletado que pueden competir con una carga.

## Orden de cierre

1. Diseñar e implementar una fuente de contratos por período que conserve el ID
   contractual estable, diferenciándolo de la versión histórica. Tomar una base
   anterior completa antes de la primera aplicación; no reconstruir valores que
   ya se perdieron ni tratar la auditoría JSON como lectura histórica terminada.
2. Adaptar lecturas anuales de Dotación y mensuales del padrón. Verificar docentes,
   asistentes, PIE/Parvularia, traslados, bajas, declaración y exportaciones con
   escenarios antes/después del mismo mes, nuevo mes y nuevo año.
3. Corregir consumidores de vigencia y probar los selectores según la finalidad
   de cada módulo. El último registro encontrado no equivale necesariamente a activo.
4. Cerrar lectores indirectos restantes y la validación MySQL concurrente descrita
   en `PADRON_ACTUALIZACION_SEGURA.md`; solo entonces evaluar habilitación.

No se necesita cambiar datos del Excel para resolver estos pendientes técnicos.
