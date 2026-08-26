# Auditoría técnica del módulo Licencias Médicas

Fecha de revisión: 26 de agosto de 2026

Rama de trabajo: `codex/auditoria-licencias-medicas`

Fuente funcional: `LICENCIAS_MEDICAS_BACKLOG_CODEX.md`

Fuente técnica definitiva: código local basado en `origin/main` (`3009c3a`)

## Conclusión ejecutiva

El módulo es un MVP operativo con registro manual/digital, extracción desde PDF, asociación de funcionarios, cálculo de días, feriados e importación histórica de seguimiento. No está listo todavía como sistema integral de seguimiento COMPIN, recuperación financiera y auditoría.

La prioridad recomendada es estabilizar integridad, permisos, estados y trazabilidad antes de agregar costo, recuperación, alertas o reportes. Los riesgos más relevantes son:

- los cuatro roles especializados creados para Licencias Médicas no pueden entrar por las rutas actuales;
- se aceptan RUT con dígito verificador inválido porque se comprueba que exista cuerpo, pero no el indicador `valido`;
- el estado es texto libre y la base local ya contiene 16 variantes, incluidas formas inconsistentes;
- el importador puede quedar parcialmente aplicado y en estado `procesando` si falla;
- la importación actual no es reversible y sobrescribe el `importacion_id` de registros existentes;
- la interfaz declara historial por cada fila, pero el código actual desactiva expresamente ese registro;
- todos los usuarios habilitados tienen las mismas acciones sobre documentos y datos sensibles;
- no existen pruebas automatizadas del módulo.

Esta auditoría no modificó código de la aplicación, datos ni estructura de base de datos. Las cifras se obtuvieron únicamente mediante consultas agregadas sobre la base local anonimizada; no representan una validación de producción.

## Línea base observada

| Indicador local anonimizado | Resultado |
|---|---:|
| Licencias | 17.355 |
| Origen importación de seguimiento | 17.353 |
| Origen PDF digital | 2 |
| Historiales | 4.056 |
| Importaciones registradas | 27 |
| Importaciones procesadas | 26 |
| Importaciones atascadas en `procesando` | 1 |
| Feriados | 34 |
| Estados actuales distintos | 16 |
| Licencias sin asociación | 1.111 |
| Licencias asociadas a establecimientos | 16.112 |
| Licencias asociadas a Administración Central | 132 |

## 1. Archivos actuales del módulo

### Núcleo

- `app/Http/Controllers/Tramites/LicenciaMedicaController.php`
- `app/Http/Controllers/Tramites/LicenciaFeriadoController.php`
- `app/Models/LicenciaMedica.php`
- `app/Models/LicenciaMedicaHistorial.php`
- `app/Models/LicenciaMedicaImportacion.php`
- `app/Models/LicenciaFeriado.php`
- `app/Services/LicenciasMedicas/LicenciaFolio.php`
- `app/Services/LicenciasMedicas/RutNormalizer.php`
- `app/Services/LicenciasMedicas/LicenciaFuncionarioResolver.php`
- `app/Services/LicenciasMedicas/LicenciaDiasLaboralesService.php`
- `app/Services/LicenciasMedicas/LicenciaPdfExtractor.php`
- `app/Services/LicenciasMedicas/LicenciaSeguimientoImportService.php`

### Vistas

- `resources/views/tramites/licencias-medicas/index.blade.php`
- `resources/views/tramites/licencias-medicas/create.blade.php`
- `resources/views/tramites/licencias-medicas/show.blade.php`
- `resources/views/tramites/licencias-medicas/importar-seguimiento.blade.php`
- `resources/views/tramites/licencias-medicas/feriados/index.blade.php`

### Integración transversal

- `routes/web.php`
- `app/Support/SlepUiRegistry.php`
- `app/Support/ModuleRegistry.php`
- `app/Http/Middleware/EnsureRole.php`
- `app/Http/Middleware/EnsureModuleAccess.php`
- `resources/views/dashboard/admin.blade.php`
- `resources/views/dashboard/coordinador_gdp.blade.php`
- `resources/views/dashboard/funcionario_slep.blade.php`
- `config/filesystems.php`
- `config/changelog.php`

### Migraciones

- `database/migrations/2026_07_03_220000_create_licencias_medicas_module_tables.php`
- `database/migrations/2026_07_03_221000_seed_licencias_medicas_module_roles.php`
- `database/migrations/2026_07_03_222000_enable_licencias_medicas_access_for_gdp_roles.php`
- `database/migrations/2026_07_03_225000_add_dependencia_ac_to_licencias_medicas_table.php`
- `database/migrations/2026_07_03_231000_create_licencias_feriados_and_add_dias_corridos.php`
- `database/migrations/2026_07_03_232000_add_salud_fields_to_licencias_medicas.php`
- `database/migrations/2026_07_03_233000_importacion_seguimiento_licencias_medicas.php`

No hay factories, seeders funcionales ni pruebas específicas para el módulo.

## 2. Rutas

Existen 14 rutas bajo `tramites/licencias-medicas`, todas con `web`, `auth`, `verified`, `ensure.module` y `ensure.role:admin|funcionario_slep|coordinador_gdp`.

| Operación | Método y ruta | Acción |
|---|---|---|
| Listado | `GET /tramites/licencias-medicas` | `LicenciaMedicaController@index` |
| Formulario | `GET /tramites/licencias-medicas/crear` | `create` |
| Registro | `POST /tramites/licencias-medicas` | `store` |
| Extracción | `POST /tramites/licencias-medicas/extraer-digital` | `extractDigital` |
| Descarte temporal | `POST /tramites/licencias-medicas/descartar-carga` | `descartarCarga` |
| Formulario importación | `GET /tramites/licencias-medicas/importar-seguimiento` | `importarSeguimientoForm` |
| Ejecutar importación | `POST /tramites/licencias-medicas/importar-seguimiento` | `importarSeguimiento` |
| Detalle | `GET /tramites/licencias-medicas/{licenciaMedica}` | `show` |
| Descargar respaldo | `GET /tramites/licencias-medicas/{licenciaMedica}/archivo` | `descargarArchivo` |
| Recalcular días | `POST /tramites/licencias-medicas/{licenciaMedica}/recalcular-dias` | `recalcularDias` |
| Listar feriados | `GET /tramites/licencias-medicas/feriados` | `LicenciaFeriadoController@index` |
| Crear feriado | `POST /tramites/licencias-medicas/feriados` | `store` |
| Actualizar feriado | `PUT /tramites/licencias-medicas/feriados/{feriado}` | `update` |
| Eliminar feriado | `DELETE /tramites/licencias-medicas/feriados/{feriado}` | `destroy` |

No hay rutas para edición controlada de licencias, cambio de estado, prevalidación, reintento, reversa, descarga de errores, reportes, alertas, recuperación o conciliación.

## 3. Controladores

### `LicenciaMedicaController`

Implementa listado, filtros básicos, métricas, selección digital/escaneada, extracción de PDF, creación, importación histórica, detalle, descarga privada y recálculo de días. Usa transacción sólo para crear la licencia manual y su historial; el archivo ya fue movido antes de iniciar esa transacción.

El controlador contiene además `resolverAsociacionReemplazos()`, método privado que ya no es usado y duplica parcialmente a `LicenciaFuncionarioResolver`. También conserva imports sin uso de `Establecimiento` y `ReemplazoPersonal` asociados a esa implementación anterior.

### `LicenciaFeriadoController`

Implementa CRUD de feriados. No registra historial de configuración. `destroy()` elimina físicamente el feriado y no deja trazabilidad. `store()` usa `updateOrCreate()` y, al actualizar una fecha existente, reemplaza también `created_by`, perdiendo quién lo creó originalmente.

## 4. Models

### `LicenciaMedica`

Expone 84 campos en asignación masiva, casts para fechas, enteros, montos y JSON, y relaciones con establecimiento, creador, actualizador e historial. No tiene relación Eloquent con la importación, movimientos financieros, ausencias, reemplazos, alertas o notificaciones.

### `LicenciaMedicaHistorial`

Es una bitácora genérica con acción, descripción, JSON anterior/nuevo, usuario y fecha. Puede reutilizarse para historial de estados sin crear un segundo flujo, pero hoy no tiene origen tipificado, vínculo a importación ni documento asociado.

### `LicenciaMedicaImportacion`

Registra archivo, período, totales, resumen JSON, estado y usuario. No tiene relación con licencias, errores persistentes o movimientos de reversa.

### `LicenciaFeriado`

Registra fecha única, nombre, tipo, vigencia y usuarios creador/actualizador.

## 5. Migraciones

Las siete migraciones del módulo figuran ejecutadas en la base local anonimizada. Son compatibles con una evolución incremental, pero presentan estas limitaciones:

- `importacion_id` es sólo índice; no tiene Foreign Key hacia `licencias_medicas_importaciones`;
- el historial usa `cascadeOnDelete`, por lo que eliminar una licencia también elimina toda su auditoría;
- los `down()` de las migraciones eliminan tablas o columnas, comportamiento normal de rollback pero destructivo si se usara sin resguardo;
- la migración de roles crea cuatro perfiles especializados, pero las rutas no los autorizan;
- se registra un módulo `tramites.licencias-medicas`, aunque `ModuleRegistry` resuelve esas rutas al módulo padre `tramites`;
- no hay migraciones para catálogo de estados, errores por fila, movimientos financieros, alertas, relaciones con ausencias/reemplazos o notificaciones.

## 6. Tablas

### Existentes

- `licencias_medicas`: 86 columnas; concentra datos identificatorios, administrativos, clínicos básicos, seguimiento, cobro, montos, documento, extracción y auditoría.
- `licencias_medicas_historial`: bitácora genérica.
- `licencias_medicas_importaciones`: cabecera y resumen de cargas.
- `licencias_feriados`: calendario para días laborales.
- Tablas externas usadas: `funcionarios_ac_autorizados`, `reemplazos_personal`, `establecimientos`, `users`, `roles`, `modules` y pivotes de autorización.

### Índices relevantes

- único por `folio_licencia`;
- único por `tipo_ingreso_licencia + cuerpo_licencia + dv_licencia`;
- búsqueda COMPIN por `tipo_ingreso_licencia + cuerpo_licencia`;
- búsqueda de funcionario por `rut_normalizado + fecha_inicio`;
- índices simples para estados, origen, dependencia, tipo, comuna e importación.

### Ausentes para el backlog

No existen estructuras dedicadas para errores de importación, catálogo/transiciones de estados, movimientos de recuperación, fuentes remuneracionales por período, documentos de seguimiento, alertas, notificaciones, cierres o relación licencia-ausencia-reemplazo.

## 7. Relaciones

Relaciones efectivas:

- licencia pertenece a establecimiento, creador y actualizador;
- licencia tiene muchos historiales;
- historial pertenece a licencia y usuario;
- importación pertenece al usuario que la subió;
- feriado pertenece a creador y actualizador;
- el resolver consulta funcionarios AC y padrón de reemplazos, pero no persiste una FK hacia esos registros.

Relaciones faltantes o débiles:

- `licencias_medicas.importacion_id` no tiene FK ni relación Eloquent;
- no hay relación persistida con `funcionarios_ac_autorizados` o `reemplazos_personal`, sólo una copia de datos y texto de fuente;
- no existe vínculo con ausencias o solicitudes de reemplazo;
- no existen relaciones para movimientos financieros, alertas, notificaciones o documentos posteriores.

## 8. Roles y control de acceso

La migración crea:

- `digitador_licencias`;
- `analista_licencias`;
- `analista_smc`;
- `administrador_licencias`.

La base local tiene al menos un usuario de prueba por cada rol. Sin embargo, las rutas y menús sólo habilitan `admin`, `funcionario_slep` y `coordinador_gdp`. Por tanto, los cuatro roles especializados existen y tienen asignaciones de módulo, pero reciben 403 en todas las rutas.

Tampoco existe separación de capacidades: cualquier rol que logra entrar puede importar, ver todos los RUT, descargar cualquier respaldo, recalcular días y crear/editar/eliminar feriados. No hay Policies ni permisos por acción, establecimiento, subdirección o propósito.

Además, `ModuleRegistry::moduleKeyFromRouteName('tramites.licencias-medicas.index')` devuelve `tramites`, por lo que la fila específica `tramites.licencias-medicas` no participa en `ensure.module`.

## 9. Vistas

### Listado

Incluye métricas, búsqueda por folio/RUT/nombre, año, mes, origen y paginación. El controlador acepta filtro `estado`, pero la vista no ofrece el campo. Sólo reconoce visualmente `digital_pdf`; cualquier otro origen —incluida una importación— se presenta como “Escaneada”. El filtro de origen tampoco ofrece importaciones.

### Creación

Permite digital o escaneada, revisión de extracción y carga de respaldo. El estado inicial es texto libre. No existe consulta AJAX de funcionario ni confirmación visual de la asociación resuelta antes de guardar. Aunque el extractor entrega apellidos y nombres separados, el controlador sólo persiste el nombre completo en este flujo.

### Detalle

Muestra datos principales, asociación, respaldo, recálculo e historial. No muestra todos los campos de seguimiento/cobro ya existentes en la tabla, no presenta una línea de tiempo funcional completa y no permite correcciones controladas.

### Importación

Permite cargar Excel y muestra resumen e inconsistencias limitadas. No hay previsualización, historial de importaciones, descarga de errores, reintento ni reversa. La ayuda afirma que cada creación/actualización registra historial, lo cual contradice el código actual.

### Feriados

Permite listar, filtrar y mantener feriados. No advierte qué licencias quedan potencialmente afectadas ni ofrece recálculo masivo controlado.

## 10. Importadores

Sólo existe `LicenciaSeguimientoImportService`. Procesa hojas fijas `2026`, `2025` y `datos`; para `.xlsx` intenta una ruta con `ZipArchive/XMLReader`, y para otros formatos usa PhpSpreadsheet.

Aspectos implementados:

- detección parcial de cabeceras;
- normalización de folio y RUT;
- asociación de funcionarios con caché en memoria;
- creación o actualización por tipo/cuerpo/DV;
- cálculo de fechas y días;
- resumen por hoja y muestra limitada de inconsistencias;
- respaldo privado del Excel original;
- optimizaciones de memoria y query log.

Brechas:

- proceso síncrono dentro de una petición HTTP;
- sin prevalidación ni previsualización;
- sin transacción por lote y con posibilidad de carga parcial;
- sin reversa;
- sin tabla de errores por fila;
- sin marca confiable de qué importación creó o modificó cada versión;
- hojas/años codificados de forma rígida;
- contador `duplicadas` nunca se incrementa porque `guardarLicencia()` sólo retorna `importadas` o `actualizadas`;
- `fecha_nuevo_cobro` se mapea al mismo encabezado `FECHACOBRO` que `fecha_cobro`;
- si ocurre una excepción, la cabecera puede quedar indefinidamente en `procesando`; hay un caso así en la base local;
- se muestra al usuario el mensaje técnico de la excepción;
- el supuesto streaming carga el XML completo de cada hoja mediante `ZipArchive::getFromName()` antes de pasarlo a `XMLReader`.

## 11. Jobs y colas

No existe Job, comando Artisan ni procesamiento en cola para Licencias Médicas. No hay control de progreso, reintentos, timeout por lote, recuperación tras caída o exclusión mutua de importaciones.

El servicio fuerza `memory_limit=1024M` y tiempo ilimitado dentro de la petición web. Esto reduce algunos errores, pero no sustituye un procesamiento desacoplado y sigue siendo riesgoso en cPanel.

## 12. Services y Support

- `LicenciaFolio`: normaliza tipo, cuerpo y DV, construye y divide folios. Valida forma, no una regla matemática del DV de licencia.
- `RutNormalizer`: normaliza, formatea y calcula validez del RUT. Sus consumidores no usan correctamente el resultado `valido`.
- `LicenciaFuncionarioResolver`: prioriza Administración Central y luego el último período global de `reemplazos_personal`.
- `LicenciaDiasLaboralesService`: centraliza días corridos/laborales y descuenta feriados activos.
- `LicenciaPdfExtractor`: extrae texto y aplica varios parsers/fallbacks para variantes FONASA/ISAPRE.
- `LicenciaSeguimientoImportService`: concentra lectura, normalización, cruce y persistencia de seguimiento.
- `SlepUiRegistry`: expone acceso visual sólo a los tres roles generales.
- `ModuleRegistry`: resuelve el módulo al padre `tramites`, no al submódulo específico.

No existe un servicio central de estados/transiciones, auditoría, duplicados, recuperación, costo, alertas o notificaciones.

## 13. Estados implementados

Campos existentes:

- `estado_actual`;
- `estado_compin`;
- `primer_estado`;
- `segundo_estado`;
- `estado_notificacion`;
- `estado_alerta`;
- `gestion_cobro` y otros textos de seguimiento.

No existe catálogo ni transición formal. El flujo manual acepta cualquier texto y el importador copia `primer_estado` a `estado_actual` y `estado_compin`.

La base local contiene 16 valores distintos en `estado_actual`. Los principales son `AUTORIZADA`, `RECHAZADA`, `Importada seguimiento`, `REDUCIDA`, `SIN INFORMACION` y `TRAMITE`, junto con variantes como singular/plural, “EN TRAMITE”, “RECHAZO” y errores tipográficos. Esto impide indicadores confiables y evidencia la necesidad de conservar el valor original de COMPIN separado de un estado canónico.

La alerta de importación sólo contempla algunas cadenas de rechazo/reducción y no cubre de forma consistente los valores reales observados.

## 14. Funcionalidades terminadas

Con el alcance actual, se consideran operativas:

- estructura base y navegación para tres roles generales;
- creación digital y escaneada con archivo obligatorio;
- almacenamiento en disco local privado;
- extracción de PDF con Smalot y fallbacks;
- normalización formal de folio;
- asociación AC/establecimiento con fuente registrada;
- listado, detalle y filtros básicos;
- cálculo centralizado de días corridos y laborales;
- mantenedor de feriados;
- recálculo individual con historial;
- importación histórica básica de hojas 2025/2026;
- resumen agregado de importación.

“Terminada” aquí significa utilizable dentro de su alcance actual, no que tenga toda la robustez del backlog.

## 15. Funcionalidades parciales

- roles: están creados, pero cuatro no pueden acceder;
- historial: existe tabla/vista, pero la importación actual no registra eventos por fila;
- estados: hay campos, pero no catálogo, normalización ni transiciones;
- duplicados: se evita folio exacto, pero no se clasifica conflicto por RUT/fecha/tipo;
- COMPIN: se importa una planilla histórica de seguimiento, no hay flujo completo de actualización COMPIN;
- seguridad documental: disco privado y ruta autenticada, pero sin autorización granular ni auditoría de descargas;
- extracción PDF: robusta por heurísticas, pero sin pruebas de regresión ni catálogo de plantillas;
- días laborales: centralizados, pero sin snapshot de reglas/feriados para reproducibilidad histórica;
- trazabilidad de importación: hay cabecera y resumen, pero no detalle reversible;
- campos financieros: existen columnas y datos históricos parciales, pero no modelo de movimientos ni cálculo institucional;
- corrección de errores: las inconsistencias se muestran como muestra limitada y no pueden corregirse/reprocesarse desde el módulo;
- filtros: existen sólo los básicos y el filtro de estado no está expuesto en la vista.

## 16. Funcionalidades inexistentes

- catálogo central y reglas de transición de estados;
- línea de tiempo completa y documentos por gestión;
- prevalidación/previsualización de importaciones;
- tabla y descarga de errores por fila;
- reversa segura de importaciones;
- procesamiento mediante Jobs/colas;
- cálculo de costo con tres meses de monto imponible;
- integración formal con fuente institucional de remuneraciones;
- movimientos de recuperación/recaudación y saldo;
- conciliación y cierre;
- vínculo licencia-ausencia-reemplazo;
- alertas por plazos y días hábiles;
- notificaciones por eventos;
- dashboard del módulo;
- reportes y exportaciones;
- edición/corrección controlada;
- gestión de documentos posteriores;
- catálogos de previsión, ISAPRE, diagnósticos, tipos y motivos;
- indicadores por funcionario;
- pruebas unitarias, de integración y autorización.

## 17. Errores técnicos detectados

### Prioridad crítica

1. **RUT inválido aceptado.** `RutNormalizer` calcula `valido`, pero `store()` y `parsearFila()` sólo verifican que exista `rut`; un DV incorrecto puede persistirse.
2. **Roles especializados bloqueados.** Las migraciones y la base crean/asignan cuatro roles que las rutas excluyen.
3. **Importaciones parciales sin recuperación.** No hay transacción por lote ni estado `fallido` en el `catch`; se confirmó una importación antigua atascada en `procesando`.
4. **Trazabilidad de importación insuficiente.** El método `registrarHistorialLiviano()` retorna inmediatamente y no registra nada. La pantalla indica lo contrario.
5. **Reversa imposible con el modelo actual.** Al actualizar una licencia se reemplaza `importacion_id`, sin guardar versión anterior ni distinguir creación de actualización.

### Prioridad alta

6. **Estado libre e inconsistente.** El formulario permite texto arbitrario y los importadores copian valores sin normalización.
7. **Contador de duplicados muerto.** Ningún camino devuelve `duplicadas`; el reporte siempre queda en cero.
8. **Mapeo de nuevo cobro incorrecto.** `fecha_nuevo_cobro` busca `FECHACOBRO`, igual que la primera fecha de cobro.
9. **Carga “streaming” incompleta.** `getFromName()` materializa el XML completo de la hoja en memoria.
10. **Acceso excesivamente amplio.** No hay Policies ni separación entre lectura, digitación, análisis, importación, configuración y administración.
11. **Importación puede sobrescribir datos vigentes.** `fill(array_filter(...))` actualiza todo valor no nulo del archivo sobre una licencia ya existente sin revisión de conflictos ni whitelist por origen.
12. **Archivo fuera de transacción.** El respaldo definitivo se mueve antes de crear la licencia; un fallo de base puede dejar un archivo huérfano.

### Prioridad media

13. El listado y detalle presentan cualquier origen no digital como “Escaneada”, incluidas importaciones.
14. El filtro de estado existe en el controlador, pero no en el formulario de búsqueda.
15. Al usar `updateOrCreate()` para feriados se reemplaza `created_by` en una actualización.
16. La eliminación de feriados es física, sin historial, y puede alterar recálculos futuros.
17. El error de importación expone el detalle técnico de la excepción al usuario.
18. No hay limpieza programada de archivos temporales abandonados ni política de retención de documentos/importaciones.
19. La búsqueda AC puede devolver un registro inactivo si no encuentra uno activo; sólo ordena por estado y no exige vigencia ni fechas efectivas.
20. No hay pruebas para folios, RUT, días, parsers PDF, cabeceras Excel, duplicados, permisos ni rutas.

## 18. Duplicación de lógica

- `resolverAsociacionReemplazos()` en el controlador duplica una versión anterior de `LicenciaFuncionarioResolver` y no se usa.
- `LicenciaPdfExtractor::formatRut()` duplica parte de `RutNormalizer`.
- `LicenciaSeguimientoImportService::calcularDiasLaborales()` duplica un cálculo anterior y no se usa; el flujo activo usa `LicenciaDiasLaboralesService`.
- Las rutas PhpSpreadsheet y XMLReader duplican bucles de hoja, estadísticas y persistencia.
- La normalización de sistema/institución de salud aparece en controlador, extractor e importador.
- La construcción de fecha de término desde inicio+días aparece en controlador, extractor e importador.
- La asignación de estado se realiza directamente en formulario e importador, sin servicio común.

La corrección debe consolidar estas responsabilidades gradualmente, sin reconstruir los archivos ni eliminar comportamiento existente en un solo cambio.

## 19. Riesgos de datos y seguridad

- La tabla principal concentra RUT, nombre, contacto, dirección de reposo, salud y datos financieros; requiere mínimo privilegio y auditoría de acceso.
- `extraccion_pdf_json` conserva hasta 12.000 caracteres de texto y estructura extraída, potencialmente más información médica que la necesaria.
- Los archivos se guardan correctamente en `storage/app/private`, pero el nombre original y el RUT forman parte de metadatos/ruta; no hay cifrado de aplicación ni política de retención.
- No se auditan descargas de respaldos.
- No existe alcance por establecimiento o subdirección.
- Cambiar/eliminar feriados y recalcular puede alterar un resultado histórico sin conservar la versión del calendario utilizada originalmente.
- Los valores financieros se guardan como columnas finales; no hay movimientos inmutables para explicar pagos parciales o correcciones.
- Una nueva importación puede reemplazar procedencia y valores de una licencia existente.
- `licencias_medicas_historial` desaparece si se elimina la licencia por el `cascadeOnDelete`.
- La muestra local no contiene rutas de respaldo, incluso para los dos registros digitales. Esto puede deberse al proceso de anonimización/importación y no permite certificar disponibilidad de archivos con esta base.

## 20. Diferencias entre el backlog y el código real

| Afirmación o pendiente del backlog | Código/base actual |
|---|---|
| P1: acceso por roles implementado | Parcial: tres roles generales acceden; cuatro roles especializados creados no acceden. |
| P1: estructura inicial de estados | Existe como campos de texto; no hay catálogo ni transiciones. |
| P2: respaldo documental | Implementado en código sobre disco privado; no verificable con los archivos de la base anonimizada. |
| P3: lectura PDF FONASA/ISAPRE | Implementada con varias heurísticas, sin suite de regresión. |
| P4: aproximadamente 17.343 registros y 11 errores | La base local actual tiene 17.355 licencias; 17.353 provienen del importador. El acumulado de 26 importaciones procesadas registra 211 inconsistencias y existe una carga atascada. Las cifras incluyen cargas posteriores/de prueba y no contradicen necesariamente el hito original. |
| P5: importación COMPIN | Existe importación de seguimiento histórico 2025/2026; no hay el flujo robusto completo de prevalidación, actualización, errores y reversa COMPIN. |
| Pendiente: cálculo de días laborales | Ya está implementado y centralizado, con feriados y recálculo individual; queda robustecer reproducibilidad y pruebas. |
| Pendiente: historial de seguimiento | Existe una bitácora genérica y se muestra en detalle, pero está incompleta y el importador actual la omite. |
| Pendiente: control de duplicados | Hay dos índices únicos y consulta por partes de folio; falta clasificación robusta usando también RUT, fecha y origen, más reporte correcto. |
| Pendiente: archivos de importación | El original ya se guarda en disco privado y se registra su ruta; faltan retención, descarga controlada y seguridad operacional. |
| Pendiente: rendimiento | Hay optimizaciones y una ruta XMLReader, pero sigue siendo petición síncrona y no es streaming real de extremo a extremo. |
| Pendiente: seguridad y datos sensibles | El disco privado está resuelto; faltan permisos finos, minimización, auditoría y retención. |
| Costos, recuperación, alertas, dashboard y reportes | No implementados funcionalmente, aunque existen algunas columnas preparatorias. |

## Siguiente incremento recomendado

### Estado actual

El sistema puede registrar y consultar licencias, pero no tiene una fuente única de verdad para el estado ni una auditoría completa. Construir costo, recuperación o alertas sobre los textos actuales produciría indicadores inestables.

### Solución propuesta

Implementar un primer parche de estabilización enfocado en:

1. validar realmente el DV del RUT en ingreso e importación;
2. definir catálogo canónico de estados y aliases de valores históricos/COMPIN, conservando siempre el texto oficial original;
3. reutilizar `licencias_medicas_historial` para registrar cambios de estado con anterior, nuevo, origen, usuario/importación y observación;
4. hacer que el importador marque `procesado` o `fallido`, registre conflictos y no muestre excepciones técnicas al usuario;
5. agregar pruebas unitarias y de integración antes de migrar datos históricos;
6. resolver la matriz de acceso de los roles especializados antes de modificar rutas.

La normalización histórica debe hacerse con una migración/comando incremental, idempotente y no destructivo. No se deben reemplazar los valores originales de COMPIN; debe añadirse o derivarse un código canónico.

### Riesgos del incremento

- mapear automáticamente un estado ambiguo puede cambiar indicadores;
- mezclar estado administrativo, resolución COMPIN y recuperación financiera en una sola columna generaría transiciones contradictorias;
- producir historial retroactivo para 17.355 registros requiere procesamiento por lotes;
- habilitar roles especializados sin definir capacidades podría ampliar acceso a información sensible.

### Archivos que previsiblemente sería necesario modificar o crear

- `config/licencias_medicas.php` (nuevo catálogo/aliases si se aprueba esta estrategia);
- `app/Services/LicenciasMedicas/LicenciaEstadoService.php` (nuevo);
- `app/Services/LicenciasMedicas/RutNormalizer.php`;
- `app/Services/LicenciasMedicas/LicenciaSeguimientoImportService.php`;
- `app/Http/Controllers/Tramites/LicenciaMedicaController.php`;
- `app/Models/LicenciaMedica.php`;
- `app/Models/LicenciaMedicaHistorial.php`;
- una migración incremental nueva, sin editar migraciones ya ejecutadas;
- `resources/views/tramites/licencias-medicas/create.blade.php`;
- `resources/views/tramites/licencias-medicas/index.blade.php`;
- `resources/views/tramites/licencias-medicas/show.blade.php`;
- pruebas nuevas en `tests/Unit` y `tests/Feature`;
- `config/changelog.php`.

Las rutas y menús sólo deberían modificarse después de confirmar la matriz de roles.

## Decisiones requeridas antes de programar el siguiente parche

1. Confirmar si `digitador_licencias`, `analista_licencias`, `analista_smc` y `administrador_licencias` deben tener acceso real y qué acciones corresponden a cada uno.
2. Confirmar que se separarán tres dimensiones: estado administrativo interno, resolución/estado oficial COMPIN y estado financiero de recuperación. Es la opción recomendada para evitar que “autorizada”, “pendiente de cobro” y “recuperada” compitan en una sola columna.

## Decisiones confirmadas e incremento ejecutado

El 26 de agosto de 2026 se confirmaron ambas decisiones:

1. habilitar los cuatro roles especializados con capacidades diferenciadas;
2. separar estado administrativo, resolución COMPIN y recuperación financiera.

El primer parche de estabilización implementa esas decisiones mediante catálogos canónicos, una migración aditiva compatible con los textos históricos, trazabilidad de cambios, validación del DV del RUT, control de importaciones fallidas y pruebas automatizadas. Los riesgos y pendientes restantes de esta auditoría continúan vigentes para los incrementos posteriores.

El segundo incremento agrega actualización masiva de la resolución COMPIN mediante Excel, con prevalidación de folio y RUT, detección de duplicados y conflictos, confirmación transaccional, historial por licencia y reversa completa condicionada a que no existan cambios posteriores.
