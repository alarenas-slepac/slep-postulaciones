# Usabilidad de Dotación establecimiento

Parche `2026.10.5.570`, rama `feat/dotacion-usabilidad-asignacion`.

## Cambios del flujo

| Zona | Comportamiento |
| --- | --- |
| Navegación | Pestañas antes de los paneles de configuración e indicadores; cada vista de trabajo conserva sus enlaces, año y permisos. |
| Proceso guiado | Seis etapas visibles, estado textual, siguiente acción y enlaces que abren la sección correspondiente aunque esté plegada. |
| Configuración | Etiquetas asociadas a los campos, mensajes locales y explicación del máximo faltante o insuficiente y de la cobertura pendiente. |
| Docentes por asignatura | Búsqueda por asignatura, curso, docente asociado, RUT y título; filtros de asociación; apertura del nivel encontrado o con errores. |
| Selectores | Tema Bootstrap 5, títulos y disponibilidad, nombres accesibles y activación al utilizar los niveles o editores. Se conserva el selector nativo si no carga Select2. |
| Asignación | Búsqueda y filtros de sección, curso y pendientes; avance por curso; formularios por necesidad; explicación de acciones deshabilitadas. |
| Formularios | Recuperación de datos en el formulario que falló, mensajes junto al campo, estado Guardando y recuperación del botón al volver con el navegador. |
| Revisión contractual | Resumen por bloque con vigente, asignado, reservado, saldo y máximo; explicación de redondeo Parvularia y cobertura AAEE. |
| Docentes y sobredotación | Filtros por persona, bloque y estado; enlaces al detalle, las asignaciones o la justificación; reservas separadas sin duplicarlas. |
| Funciones | Estado actual separado de Validar, Observar y Eliminar; recuperación independiente de cada formulario y bloqueo con enlace al proceso. |
| Cursos combinados | Etiquetas únicas de grupo, curso y asignatura, identificación de horas aula y feedback al guardar. |
| Cargas anuales | Búsqueda por RBD/nombre y estado. Cero se distingue de un máximo sin configurar; totales y plantillas mantienen su alcance completo. |
| Pantallas pequeñas | Necesidades como tarjetas; tablas de revisión con desplazamiento horizontal local; campos, títulos y acciones adaptados al ancho disponible. |

Las reglas de conversión, prelación, titularidad, límites, reservas, autorización y persistencia continúan en los servicios y controladores existentes. El parche no cambia el contrato del padrón ni requiere migraciones.

## Detalle de etapas pendientes · 2026.10.19.611

Las tarjetas incompletas incluyen **Qué falta para completar** usando exclusivamente el resumen y las necesidades ya calculadas. Identifican cursos con planes pendientes, asignaturas sin docentes asociados, decisiones y funciones sin definir, máximos ausentes o insuficientes y cobertura obligatoria pendiente por bloque. EPJA conserva la posibilidad de asociar asistentes habilitados.

En asignación, los saldos por bloque se expresan en horas de contrato y el detalle del plan en horas aula; no se suman ambas unidades. Las necesidades no normativas o descartadas no se incluyen. Los enlaces al detalle conservan establecimiento y año y aplican los filtros existentes de curso, sección, búsqueda y pendientes. Las listas muestran tres elementos y un desplegable nativo accesible con el resto. Las tarjetas completas no muestran avisos pendientes.

La explicación no cambia las reglas de completitud ni agrega consultas SQL. El detalle disponible de planes identifica los cursos pendientes y el número con libre disposición incompleta; los motivos de cada bloque se revisan en la configuración de planes. Pruebas: `DotacionEtapasPendientesTest` y `DotacionConfiguracionUsabilidadTest`.

## Cupo institucional y redondeo docente · 2026.10.19.613

En Educación Parvularia, el curso conserva su necesidad proporcional y el máximo autorizado. El contrato personal se redondea hacia arriba una vez por docente; ese redondeo consume su jornada, pero no agrega necesidad ni cupo al curso. La cobertura parcial también usa la base del curso, para que el redondeo personal no oculte horas aula pendientes.

El proceso 2027 calcula `asignadas_para_maximo` con las bases de cada curso o grupo combinado, conserva las fracciones hasta sumar y excluye el acompañamiento simultáneo. Mantiene el contrato docente redondeado en `asignadas`, en el padrón y en los controles de disponibilidad, titularidad y límite de aula. La creación y edición utilizan el mismo criterio institucional; al editar se libera el cupo proporcional anterior. PIE y funciones conservan su costo contractual y las reservas sus reglas existentes. Las necesidades, máximos configurados e históricos no se modifican.

La tabla muestra **Asignadas para el máximo** cuando esa cantidad difiere del contrato individual. La prueba sintética reproduce 33 de 38 horas aula cubiertas, 21 horas personales disponibles, 110 contractuales asignadas y máximo/necesidad 116. Completar las cinco horas puede llevar los contratos personales a 118, mientras el curso y PIE permanecen en 116. Pruebas: `DotacionParvulariaCupoInstitucionalTest` y `DotacionParvulariaCupoCalculatorTest`.

## Planes TP y libre disposición restante · 2026.10.19.614

La etapa de planes 2027 toma las horas requeridas de los bloques activos configurables que muestra el formulario. En TP, la cabecera puede registrar seis horas que se distribuyen en dos de electivo y cuatro de libre disposición restante; exigir seis de libre disposición además del electivo generaba un falso faltante de dos horas. Los bloques completos de formación general 14, electivo 2, especialidad TP 22 y libre disposición 4 conservan el total de 42 horas.

Se valida cada bloque flexible por separado y se sigue exigiendo un estado enviado, aprobado o cerrado. La cabecera respalda la libre disposición sólo si no existe un bloque activo explícito; un bloque activo de cero horas no hereda otro valor de la cabecera. La consulta no modifica detalles, estados ni planes oficiales. `DotacionProceso2027TpPlanesTest` reproduce ambos grados TP y el caso de 27 cursos con 13 TP completos que aparecían pendientes.

## Libre disposición en cursos combinados · 2026.10.19.615

La combinación conserva las actividades y bloques configurados. Un NT1 y NT2 con el mismo plan de 32 horas comunes y 6 de libre disposición muestran Lenguaje Verbal 6, Corporalidad y Movimiento 2, Inglés 3 y Educación Física 3, junto con los demás núcleos: 38 horas conjuntas, 76 brutas y 38 de reducción. El vínculo curricular de Inglés con Lenguaje Verbal y de Educación Física con Corporalidad se informa sin sumar ambas actividades en una sola fila.

Las claves separan bloque y nombre configurado; cada actividad tiene su modalidad, saldo y asignaciones. La libre disposición NT con JEC conserva la selección libre entre docentes asociados y los controles de contrato, cupo y cobertura. Los núcleos comunes mantienen la prelación. El redondeo contractual no agrega horas al plan.

Las claves curriculares anteriores con asignaciones activas o modalidades explícitas se conservan: no se puede inferir cómo repartir una carga conjunta ya registrada entre núcleo y taller. Esas filas muestran los nombres de sus componentes y mantienen sus horas y acompañamientos. Las reglas conjuntas sin valores manuales no impiden el nuevo desglose. La consulta no escribe ni migra datos históricos.

`DotacionCombinadoPlanConfiguradoTest` reproduce el plan compartido, talleres vinculados al mismo núcleo, nombres iguales en distintos bloques, modalidades independientes, guardado de 3 horas con un especialista a contrata frente a educadoras titulares, límites de cobertura, conservación histórica y vistas de edición/consulta.

## Eliminar justificaciones de Sobredotación · 2026.10.19.616

El detalle de cada justificación guardada ofrece **Eliminar justificación**, con confirmación, para administrador, supervisor de Planificación y directivo del propio establecimiento. La eliminación usa DELETE con CSRF y comprueba en el servidor el establecimiento, el año y el identificador del registro. Los perfiles de consulta conservan acceso de lectura sin botón ni permiso de eliminación.

Se elimina sólo la justificación elegida; no se modifican contratos, asignaciones, reservas ni máximos. Al retirar la última justificación que causaba la exclusión, el docente vuelve a los candidatos de otras funciones si tiene saldo y cumple los requisitos habituales. Si conserva otra justificación del mismo establecimiento y año, el mensaje indica que la exclusión continúa.

Las justificaciones que quedaron fuera de la nómina por cambios de bloque o calidad se muestran en una sección propia, con la misma acción y filtros, para poder retirar también registros antiguos sin saldo visible. No se exige que las horas originales coincidan con el saldo actual para eliminar. `DotacionEliminarJustificacionTest` verifica la solicitud HTTP, la reaparición en el catálogo PAE con contrato 35, asignadas 10 y saldo 25, los permisos, el aislamiento anual y por establecimiento, las justificaciones múltiples/desactualizadas y las vistas de edición/consulta.

## Bloques HC y electivo sin duplicados · 2026.10.20.617

En la asignación de 3.º y 4.º Medio HC, el desglose configurado sustituye al bloque oficial correspondiente. Tres materias diferenciadas de seis horas suman 18; Artes de dos horas cubre el electivo. Los totales oficiales de 18 y 2 horas dejan de aparecer como necesidades adicionales cuando existe el desglose de cada bloque. Un plan de 42 horas conserva 42, sin generar otras 20 pendientes.

La equivalencia reconoce los títulos genéricos Humanístico-Científico/Científica y los tipos históricos o importados de esos bloques. Conserva los nombres personalizados y las asignaciones a sus claves. Cuando una materia oficial y una configurada coinciden inequívocamente, ambas claves continúan localizables y sus asignaciones se cuentan una sola vez.

Si un bloque no tiene configuración, se mantiene su necesidad oficial; una configuración incompleta conserva las horas sin desglose pendientes. Los bloques de otras modalidades no se identifican como HC por compartir el tipo genérico `plan_diferenciado`. Una asignación antigua a un total de 18 horas no se distribuye automáticamente entre tres materias: permanece guardada y visible entre las asignaciones sin necesidad vigente para su revisión. La consulta no escribe ni migra datos y mantiene las reglas de redondeo individual y cupo del curso.

`DotacionHcBloquesConfiguradosTest` verifica ambos niveles, importaciones con tipos distintos, asignaciones existentes, bloques sin configuración, faltantes reales y compatibilidad de claves históricas. No se requieren migraciones ni compilación de recursos.

## Archivos principales

- `resources/views/admin/dotacion-establecimiento/show.blade.php` e `index.blade.php`: estructura y navegación.
- Parciales `_proceso_etapas`, `_proceso_2027`, `_docentes_subsector`, `_catalogo_filtros`, `_campo_error`: proceso, asociación, filtros y errores.
- Parciales `_asignacion`, `_asignacion_editor`, `_asignacion_errores`, `_asignacion_filtros`, `_restore_context`: edición y recuperación del contexto.
- Parciales `_docentes`, `_sobredotacion`, `_resumen_contractual_bloques`, `_revision_filtros`, `_revision_sobrecargas`: revisión por docente y bloque.
- Parciales `_cursos_combinados`, `_asignaturas`: etiquetas y acciones de configuración/revisión.
- `resources/views/admin/dotacion-funciones/{show,convivencia-carga,maximos-carga}.blade.php`: funciones y cargas anuales.
- `resources/css/dotacion-establecimiento.css`, `resources/js/dotacion-asignacion.js`, `vite.config.js` y sus entradas en `public/build`: comportamiento y presentación compartidos.
- `config/changelog.php`: relación exacta de archivos y versión.

## Validación reproducible

Antes de Artisan, usar una configuración de pruebas que no pueda seleccionar la base real:

```powershell
$env:APP_CONFIG_CACHE = Join-Path $env:TEMP ('dotacion-tests-' + [guid]::NewGuid() + '.php')
$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'sqlite'
$env:DB_DATABASE = ':memory:'
$env:DB_URL = 'null'
$env:CACHE_STORE = 'array'
$env:SESSION_DRIVER = 'array'
$env:MAIL_MAILER = 'array'

php -d memory_limit=256M artisan test --filter='Dotacion|DirectorAdpNormativaAccessTest'
node --test tests/js/dotacion-asignacion.test.mjs
php artisan optimize:clear
npm run build
git diff --check
```

Ejecutar además `php -l` para cada PHP/Blade nuevo o modificado. Las pruebas nuevas usan `IsolatedSecurityTestCase`: verifican SQLite en memoria antes del bootstrap, impiden una configuración cacheada real y emplean almacenamiento simulado. Los datos de prueba son sintéticos.

Cobertura nueva: recuperación de formularios y contexto, filtros combinados, unidades aula/contrato, reservas y redondeo, alcance anual, permisos de presentación, enlaces por etapa, funciones y cargas, identificadores accesibles y arranque de JavaScript sin fragmento en la URL. Las pruebas existentes verifican los cálculos y las operaciones autorizadas del flujo.

Resultado local del 5 de octubre de 2026: **402 pruebas PHP / 4488 comprobaciones**, **8 pruebas JavaScript**, sin fallos. El comando PHP principal se inició con `memory_limit=256M`; el conjunto específico de las vistas contiene 24 pruebas y 352 comprobaciones. Vite compila correctamente, con avisos previos de deprecación Sass/Bootstrap y fuentes de admisión que se resuelven al ejecutar la aplicación.

## Revisión visual y límites

Previsualizaciones sintéticas con recursos compilados; anchos de escritorio, tablet y móvil. Se comprueba navegación por etapa, apertura de niveles, búsqueda, teclado, presentación de títulos y opciones de los selectores y desplazamiento horizontal local. El catálogo amplio contiene 100 asignaturas y 60 docentes (6000 opciones nativas).

Estas comprobaciones no equivalen a un flujo manual completo con usuarios reales ni a una medición del servidor cPanel. No se utilizó la base SQL descargada, no se modificó `.env`, no se guardaron archivos reales de usuarios y no se ejecutaron migraciones. La publicación y la verificación posterior en el ambiente de destino son pasos separados.
