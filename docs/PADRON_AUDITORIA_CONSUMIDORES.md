# Auditoría de consumidores del padrón — actualizada en 2026.9.8.485

Avance de esta etapa: [versiones por período](PADRON_VERSIONES_PERIODO.md).
Las lecturas principales mensuales y la base contractual anual ya usan copias
cuando existen; no están certificados todos los lectores indirectos ni los demás
insumos históricos de Dotación. El bloqueo anual y la aplicación cerrada se mantienen.
Los hallazgos de 483 descritos abajo documentan la causa original.

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
| `CentroOperaciones/DatosBaseService::dotacionesPara` | Máximo período entre filas vigentes por establecimiento | P0 pendiente: si se desactiva el mes más reciente puede regresar a un mes anterior; no usa el piso de cargas completas aplicadas. |
| `FuncionarioRegisterLookupService`, `TramiteAutofillService` | Último período del propio RUT sin filtro de vigencia en la consulta inicial | P0 pendiente: distinguir antecedente histórico de vínculo actual al dar de baja/trasladar. El autocompletado también tiene alternativa desde solicitudes aceptadas/cerradas; no quitarla sin revisar su finalidad. |
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
| `Auth/RegisterRutLookupController` y servicios de autocompletado | Consumidores indirectos de resolución por RUT | Incluir al probar registro y trámites; conservar permisos y respuestas existentes. |
| Servicios de `Padron` y `PersonalImportController` | Conciliación, historial, decisiones, escrituras controladas | Aplicación cerrada; antiguo importador privado sin ruta pública. Nuevos bloqueos no habilitan escrituras. |

Los modelos `SolicitudReemplazo`, `CometidoFuncionario` e `IncumplimientoLaboral`
usan `ConservaPadronHistorico`. Sus propiedades de relación están protegidas;
consultar el método de relación o hacer un JOIN no equivale a leer la copia.

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
