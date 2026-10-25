# Exportación del borrador PAL 2027

En Dotación Establecimiento, seleccionar **2027 → Excel PAL 2027 consolidado**.
Se descarga **un único archivo con todos los establecimientos participantes**, sin
limitarlo al establecimiento abierto, la búsqueda, la comuna o la página visible.
El botón del detalle conduce al mismo consolidado. La pantalla muestra el listado
completo incluido, sin ejecutar cálculos de nóminas durante su carga.
Acceso por rol activo: administrador, coordinador UATP,
coordinador GDP y supervisor de Planificación, como los informes consolidados de
dotación. No habilita datos de matrícula para el rol directivo.

## Fuente y alcance

- Plantilla privada versionada: `resources/templates/dotacion/resumen_dotacion_pal_2027.xlsx`.
  Procede del archivo proporcionado por el usuario. Conserva sus nueve hojas y
  fórmulas, estilos, validaciones, objetos y tablas auxiliares. Se limpiaron los
  datos de ejemplo, las cachés calculadas y los nombres personales de las propiedades.
- `DotacionEstablecimientoCalculator::build($ee, 2027, false, false)` proporciona
  los mismos datos del proceso, sin calcular los consolidados por asignatura.
  La lectura se encapsula en `DotacionLecturaCache` y no modifica registros.
- Cursos y asignaciones: sólo 2027. El padrón puede corresponder al año previo,
  conforme al servicio de períodos y a las reglas de continuidad vigentes del SGA.
  Esta selección se aplica por establecimiento. No se supone que Git sincronice
  bases de datos ni que el ambiente local tenga lo ingresado en producción.
- Se completan **Info Establecimiento** e **Info Funcionario (DOCENTE)**.
  Asistentes y VTF conservan encabezados, pero quedan sin registros. No se presentan
  vacantes, plazas por asumir o cupos sin RUT real como funcionarios.
- Universo: los mismos establecimientos del listado de Dotación, con `sala_cuna`
  falso o nulo. Se procesan ordenados por RBD. Las salas cuna/VTF mantienen su
  exclusión anterior. Un establecimiento sin cursos 2027 aparece en
  **Info Establecimiento** con su RBD y la indicación de que faltan registros.
- **Resumen** obtiene los RBD desde **Info Establecimiento**, a solicitud del
  usuario, para incluir también los establecimientos sin funcionarios. Se conserva
  la lógica de conteo por RUT y RBD y de suma de horas. Las filas de resultados y
  los rangos de referencia se amplían para todo el consolidado; las sumas usan
  rangos finitos que abarcan todas las filas docentes.
- Las rutas individuales existentes se conservan por compatibilidad. Los accesos
  visibles del listado y del detalle generan el consolidado.

## Mapeo

| Hoja / columnas | Fuente y criterio |
| --- | --- |
| Establecimiento A–C | Nombre y RBD de establecimientos; nivel o nombre del grupo combinado con sus cursos integrantes |
| Establecimiento D–E | Matrícula y cantidad de cursos independientes del mismo plan; cada grupo combinado cuenta como una unidad de plan compartida |
| Establecimiento F | Horas semanales requeridas por el plan de cada curso independiente o por el plan consolidado del grupo combinado; no usa horas asignadas |
| Establecimiento G | Fórmula original `E × F`, sin reemplazar su cálculo |
| Establecimiento H | Horas de contrato necesarias para cubrir el plan del nivel; excluye PIE y funciones |
| Docentes A–C | RUT de la nómina del proceso, establecimiento y RBD |
| Docentes D–G | Nivel según clases asignadas y reglas de Especial/EPJA, estamento/título, cargo declarado y función asignada |
| Docentes H | Titular, Plazo Fijo u Otros, según las capacidades contractuales del proceso |
| Docentes I | «si» sólo si existe la situación anual explícita `horas_catedra_art_69` |
| Docentes J, N, O | Fórmulas originales de suma y búsqueda en las tablas auxiliares |
| Docentes K–L | Horas pedagógicas de aula financiadas por General, separadas por nivel, cuando la regla es representable |
| Docentes M | 65/35 o 60/40 de la asignación; las educadoras elegibles de Parvularia representan el contrato especial como 65/35 según la excepción siguiente |
| Docentes P–Q | Horas cronológicas asignadas y financiadas por PIE o SEP |
| Docentes R | Funciones efectivamente asignadas financiadas por General; nunca el saldo sin asignar |
| Docentes S | Contrato de referencia, tramo distribuido, situación 2027 con contrato completo y horas consideradas/no necesarias, pendientes, detalle e incompatibilidades |

Un docente puede tener varias filas por calidad contractual, función, nivel y
proporción. Se consumen primero sus horas titulares y después las de plazo fijo.
Los grupos se recorren en orden estable de sus primeras asignaciones por ID. El
reparto es sólo una representación para el informe y no actualiza asignaciones ni
la calidad jurídica del padrón. El conteo de personas se obtiene de la nómina, no
de la cantidad de filas. Las personas que trabajan en dos establecimientos se
mantienen en cada RBD, conforme al formato original.

### Nivel del docente en Info Funcionario (DOCENTE)

La columna **D, NIVEL**, usa las opciones originales de la plantilla. Se obtiene
de los cursos de las asignaciones activas con horas positivas de aula,
acompañamiento de Parvularia o docencia PIE. Las asignaciones anuladas, sin horas,
de asistentes, plazas por asumir y funciones administrativas no añaden niveles
de clases. Los cursos asignados tienen prioridad sobre los niveles declarados;
una bolsa PIE sin curso identificado tampoco añade por sí sola un nivel.
Si no hay clases asignadas se conserva la declaración como referencia y se
mantiene pendiente cuando no permite identificar una categoría.

- En establecimientos marcados como **Educación Especial** (`especial`), todos
  los docentes se informan como **Educación Básica**, incluso quienes sólo tienen
  funciones o todavía no tienen clases asignadas. Los cursos del catálogo
  Especial/Laboral también se representan como Básica.
- En **EPJA**, los niveles básicos **1, 2 y 3** se informan como **Educación
  Básica**. Los niveles medios **1 y 2** se informan como **Educación Media**.
  Se reconoce el código del catálogo y los nombres históricos con su modalidad;
  una asignación EPJA sin detalle del nivel permanece pendiente.
- Si el docente hace clases en Básica y Media, se selecciona **Ed. Básica/Media**
  en sus filas, tanto en enseñanza regular como en EPJA. Las horas siguen
  desglosadas por el nivel de cada asignación: K para Básica y L para Media,
  conservando las fórmulas y el total contractual, sin duplicar horas.
- Parvularia conserva su opción. Si se combina con otro nivel, el formato no
  ofrece una categoría mixta que la incluya: cada fila conserva entonces su
  nivel correspondiente. La excepción de presentación de escuelas especiales
  no cambia la regla contractual ni la conversión de Parvularia a 65/35.

Estas reglas sólo adaptan la representación del PAL, en la descarga individual
y en el consolidado. No cambian cursos, asignaciones, declaraciones ni matrícula.

### Situaciones docentes 2027

Los docentes con situación **Proceso BIR en 2027 se excluyen del PAL**, de sus
filas y del conteo de personas del informe, tanto individual como consolidado.
No se excluye a alguien sólo por tener BIR en un año anterior: la fuente consulta
la situación del año 2027. Esta exclusión no modifica la nómina del SGA ni las
necesidades del plan informadas en Info Establecimiento.

Para las demás situaciones 2027, Observaciones informa el **contrato completo**
del padrón (`horas_contrato_base`), el motivo, las **horas consideradas** para la
situación contractual (`horas_contrato`) y las **horas no necesarias** por la
situación (`horas_excluidas`). Por compatibilidad, si un DTO antiguo no trae la
base se obtiene sumando las horas consideradas y excluidas.

Por decisión del usuario, el contrato completo es una **referencia en S**: no
se carga la diferencia automáticamente en aula, PIE, SEP ni otras funciones.
J conserva su fórmula y las horas efectivamente distribuidas que el formato
puede representar. Ejemplo: contrato completo 44 h, consideradas 20 h y situación
24 h; con 20 h de funciones distribuidas, S informa los tres valores y J suma
20 h. También se mantiene el detalle de situaciones que dejan cero horas
consideradas, sin inventar asignaciones.

## Fórmulas originales y borradores

El usuario solicitó conservar estrictamente los criterios de cálculo originales.
Las instrucciones de la plantilla dicen informar el contrato completo en J, pero
J en realidad suma N+O+P+Q+R. Se conserva esta fórmula: un docente de 44 horas con
30 distribuidas muestra el total representado y **14 horas pendientes** en S.
No se inventa una función para completar la diferencia.

Cada entrada lectiva se contrasta con el mismo rango exacto de BUSCARV que usa el
Excel (`A4:D47` en cada tabla). Si no existe una equivalencia, o no coincide con el
contrato consolidado del SGA, la fila queda marcada **INCOMPATIBLE** en S y sus
entradas numéricas incompatibles quedan vacías. S conserva las horas pedagógicas
y cronológicas que motivan el problema. J/N/O pueden calcular cero: ese cero **no
representa ausencia de horas**. No usar esas filas como dotación definitiva.

Esto puede ocurrir con reglas especiales sin la equivalencia autorizada siguiente, fracciones producidas al
separar contratos, niveles sin desglose, reservas sin función y financiamientos
sin equivalencia. Los excesos de asignación se marcan y no se transforman en otro
contrato real.

### Educadoras de Párvulos: contrato especial a horas aula equivalentes 65/35

El usuario solicitó conservar en el PAL las horas de contrato calculadas por la
regla especial y obtener las horas aula equivalentes para que las fórmulas del
formato devuelvan ese mismo contrato. Esta excepción se aplica sólo cuando:

- El título es reconocido como **Pedagogía en Educación de Párvulos** por el
  resolver de profesión del SGA, dando prioridad a la declaración de sostenedor.
- La asignación corresponde a plan de estudio o acompañamiento en aula de
  **Educación Parvularia**, financiada por General.
- La proporción guardada identifica la regla especial NT con/sin JEC o su
  antecedente histórico NT JEC CPEIP.

Se suman las asignaciones elegibles del docente antes de separar la calidad
contractual. Se conserva su contrato especial y se invierte la tabla **efectiva**
que usa BUSCARV en el formato, respetando la primera coincidencia de aulas
repetidas. K recibe horas aula **equivalentes**, M recibe `65/35`, y las fórmulas
originales N/O/J calculan el contrato. S conserva las horas aula reales del SGA,
el contrato especial, las reglas originales y el número de parte cuando hay
varias filas. Los registros del SGA no se modifican.

Ejemplo de comprobación con datos sintéticos: **25 h aula reales → 37 h contrato
especial → 32 h aula equivalentes 65/35 → 37 h contrato PAL**. La plantilla relaciona
34 h aula con 39 h contrato, por lo que no se usan 34 para representar 37. Si hay
además 3 h PIE y un contrato base de 44, PIE queda aparte, el total representado
es 40 h y se siguen informando 4 h pendientes.

Cuando el contrato supera la fila máxima de la tabla o no tiene una equivalencia
directa por aulas repetidas, se usan las mínimas filas necesarias cuya suma
devuelve exactamente el contrato. Esto no crea nuevos funcionarios: las filas
conservan el mismo RUT y RBD. No se aplican redondeos a fracciones sin equivalencia
exacta; esos casos y los excesos sobre la base contractual siguen marcados para
revisión. Las horas PIE, SEP, funciones y asignaciones de otros niveles mantienen
su tratamiento anterior. La columna H de **Info Establecimiento** conserva la
necesidad contractual calculada por el SGA.

### Planes y cursos combinados en Info Establecimiento

Las horas de esta hoja corresponden a la **necesidad del plan**, incluso cuando
todavía no hay docentes asignados. Para cursos independientes se utiliza el plan
base y su contrato necesario, sin sumar refuerzos de libre disposición que
dependen de asignaciones a otro docente. Los cursos de un nivel con planes
diferentes se separan en filas con sus secciones y sus horas propias, sin promedios.

Cada grupo combinado activo aparece una sola vez. **Nivel** identifica el nombre
del grupo y todos sus cursos, por ejemplo `Curso combinado «Multigrado A»:
1° Básico A + 2° Básico A`. D suma la matrícula de sus integrantes; E vale **1**
porque representa una unidad curricular compartida, no la cantidad de cursos
administrativos que la componen. Estos integrantes no vuelven a aparecer como
cursos independientes. F informa `totales.horas_requeridas` del plan consolidado
del grupo y H su necesidad contractual `totales.horas_contrato`; no se utiliza
`totales.asignadas`. La fórmula original G = E × F queda intacta y cuenta una sola
vez las horas compartidas. Por ejemplo, un grupo con plan de 46 h y 9 h asignadas
muestra F = 46 y G = 46, independientemente del avance de la asignación.

Un curso sin plan queda identificado como **plan pendiente de configurar**, con
las horas faltantes vacías, sin llamarlo INCOMPATIBLE. Si un curso pertenece a
dos grupos activos se cancela la descarga para evitar duplicaciones y se solicita
revisar sus combinaciones. Los grupos inactivos no alteran los cursos independientes.
Estas reglas se aplican tanto al consolidado como a la descarga individual
conservada por compatibilidad y no modifican registros del SGA.

Las filas se amplían copiando las fórmulas y estilos originales. Se extienden la
tabla docente, las validaciones y los rangos finitos de Resumen cuando corresponde,
sin cambiar sus funciones ni criterios. Sólo se ajustan el alto de las filas
completadas y el ajuste de texto de nombre/nivel para que sean legibles, manteniendo
los estilos originales y el orden de columnas. Límite defensivo del consolidado:
100.000 filas docentes, 10.000 niveles y 10.000 establecimientos. No se truncan
datos si se supera: se cancela el archivo. Los textos se escriben como texto, incluso si comienzan
con `=`. No se conserva un Excel con datos de usuarios en el repositorio.

## Instalación y verificación

Desplegar los archivos de este cambio incluyendo la plantilla privada y ejecutar,
con PHP 8.3, `php artisan optimize:clear` y las regeneraciones de caché utilizadas
por el despliegue existente. No requiere migraciones ni nuevas variables `.env`.
Usa PhpSpreadsheet ya instalado y las extensiones ZIP/DOM de PHP. El directorio
temporal de PHP debe permitir archivos privados y escritura. La descarga se
prepara completamente antes de enviarse y elimina su temporal al terminar o al
cerrarse la petición.

La lectura se hace por lotes de 10 establecimientos, con la caché de cálculo
acotada a cada uno. Las filas se escriben progresivamente en archivos XML privados:
no se retienen todas las nóminas ni todas las filas Excel en memoria. Primero se
completa el ZIP y después se envía la descarga. Un fallo de cálculo cancela todo
el consolidado e identifica el RBD que debe revisarse; los temporales se eliminan
también ante errores. El límite solicitado para la petición de descarga es de
240 segundos, sujeto a lo que permita el proveedor; la previa no calcula nóminas.

La conservación de fórmulas y objetos se realiza editando sólo las partes XML
necesarias del XLSX, para evitar que un motor de cálculo diferente cambie las
fórmulas matriciales de Excel. `Resumen` requiere una versión de Excel compatible
con `FILTER`, `UNIQUE`, `VSTACK` y `LET`. El archivo solicita recálculo automático
completo al abrir. Las cachés de los ejemplos se eliminaron para no mostrar totales
anteriores. Un visor sin recálculo puede mostrar celdas vacías o no representar
estas fórmulas; abrir en Excel compatible y revisar antes de presentar el PAL.

Pruebas: `php vendor/phpunit/phpunit/phpunit tests/Feature/DotacionPal2027Test.php tests/Feature/DotacionPal2027ConsolidadoTest.php`.
Usan SQLite en memoria y datos sintéticos: permisos por rol activo, validación del
año, privacidad, contratos, pendientes, incompatibilidades, conservación de
fórmulas/hojas/estilos, previa sin cálculos de personal, lotes, catálogo sin datos,
cancelación y limpieza ante fallos, detalle y deduplicación de cursos combinados,
planes diferentes, independencia de las horas asignadas y expansión a 110 establecimientos, 1.540 niveles
y 11.000 filas docentes. El recálculo nativo de Excel y un
archivo con datos reales de 2027 requieren validación adicional; no se da por
comprobado el resultado en producción.
