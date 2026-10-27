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
  **Info Funcionario(AE)** e **Info Funcionario (VTF)** usan exclusivamente
  `reemplazos_personal` de **08/2026**, conforme al criterio detallado abajo. Los docentes
  por contratar y directivos por asumir se incorporan con identidades sintéticas
  y una indicación explícita de su condición pendiente.
- Universo: los mismos establecimientos del listado de Dotación, con `sala_cuna`
  falso o nulo, más los jardines con personal AAEE/JUNJI elegible de 08/2026.
  Se procesan ordenados por RBD. Los jardines aportan personal VTF y una fila
  identificativa sin cursos ni proyección escolar. Un establecimiento sin cursos 2027 aparece en
  **Info Establecimiento** con su RBD y la indicación de que faltan registros.
- **Resumen** obtiene los RBD desde **Info Establecimiento**, a solicitud del
  usuario, para incluir también los establecimientos sin funcionarios. Se conserva
  la lógica de conteo por RUT y RBD y de suma de horas. Las filas de resultados y
  los rangos de referencia se amplían para todo el consolidado; las sumas usan
  rangos finitos que abarcan todas las filas docentes.
- Las rutas individuales existentes se conservan por compatibilidad. Los accesos
  visibles del listado y del detalle generan el consolidado.

## Mapeo

### Asistentes y jardines VTF: referencia de agosto de 2026

Se selecciona `anio=2026`, `mes=8`, estatuto **AAEE o JUNJI**, sin usar el alcance
de padrón vigente: una carga posterior no reemplaza esta referencia histórica.
Se excluye cualquier tipo contractual que contenga `REEMPLAZ` o `SUPLEN`, sin
distinguir mayúsculas. No se cambia la fuente docente de 2027 ni su proyección.

La fuente histórica también identifica JUNJI mediante **financiamiento**, con
estatuto AAEE. Por eso cada línea con financiamiento JUNJI, estatuto JUNJI o de
un establecimiento `sala_cuna` se dirige sólo a VTF; el resto AAEE va a AE.
La asociación usa el RBD histórico, incluso si `establecimiento_id` es nulo.
Los jardines con personal elegible se incluyen en el consolidado y en Resumen.
No se fabrican establecimientos para RBD inexistentes en el catálogo.

Se normaliza el RUT y se agrupan las líneas por persona, RBD, hoja, cargo,
calidad contractual y nivel (este último sólo en AE). Se suman las jornadas de
los financiamientos sin repetir una misma línea contractual importada con
distinta representación del RUT. Calidades distintas y cargos distintos siguen
en filas separadas; el conteo nativo del resumen usa RUT únicos por RBD.
Se usa `jornada`; sólo si es nula se suman `jornada_basica` y `jornada_media`.

| Hoja | Mapeo |
| --- | --- |
| AE A–C | RUT normalizado, establecimiento y RBD |
| AE D | Nivel único del establecimiento, o nivel inequívoco del desglose Básica/Media. Se deja vacío si no se individualiza, especialmente con Parvularia y Básica combinadas |
| AE E–H | Asistente de la Educación; escalafón; función Administrativo, Auxiliares, Técnicos o Profesionales; calidad Indefinido, Plazo Fijo u Otros |
| AE I, K–L | Contrato total, horas de financiamiento PIE y SEP. PIE/SEP son componentes del contrato, no horas adicionales |
| VTF D–G | Estamento y cargo según escalafón; función Directiva, Educadora de Párvulos, Técnico de Educación Parvularia u Otros asistentes; calidad contractual |
| VTF H | Contrato total |
| AE J / VTF I | Vacías: la fuente no informa horas lectivas; no se deducen de la jornada contractual |
| AE M / VTF J | Nombre, origen 08/2026, cargo/tipo/financiamiento originales y datos pendientes de clasificación |

`INDEFINID…` se representa como Indefinido; Plazo Fijo y Contrata como Plazo Fijo.
Otros tipos conservan su descripción en Observaciones. En VTF, directoras y
encargadas educadoras se clasifican en Función Directiva; educadoras en Educadora
de Párvulos; técnicos en Técnico de Educación Parvularia; los demás en Otros
asistentes. La validación de **VTF E** añade **PROFESIONAL** a sus opciones;
profesionales, fonoaudiólogos y terapeutas ocupacionales usan ese cargo y
**Otros asistentes de la educación** en F. Los cargos sin opción exacta quedan
vacíos con su origen en Observaciones.
No se inventan títulos ni fechas. Los encabezados y listas de la plantilla se
conservan y sus rangos, junto con los del resumen, se amplían a todas las filas.
El consolidado escribe las cuatro hojas por tramos, con un máximo explícito de
100.000 filas por hoja de funcionarios. La descarga individual admite 10.000.

### Establecimientos y docentes

| Hoja / columnas | Fuente y criterio |
| --- | --- |
| Establecimiento A–C | Nombre y RBD de establecimientos; nivel o nombre del grupo combinado con sus cursos integrantes |
| Establecimiento D–E | Matrícula y cantidad de cursos independientes del mismo plan; cada grupo combinado cuenta como una unidad de plan compartida |
| Establecimiento F | Horas semanales requeridas por el plan de cada curso independiente o por el plan consolidado del grupo combinado; no usa horas asignadas |
| Establecimiento G | Fórmula original `E × F`, sin reemplazar su cálculo |
| Establecimiento H | Horas de contrato necesarias para cubrir el plan del nivel; excluye PIE y funciones |
| Docentes A–C | RUT de la nómina o identificador sintético del cupo/plaza, establecimiento y RBD |
| Docentes D–G | Nivel según clases asignadas y reglas de Especial/EPJA, estamento/título, cargo declarado y función asignada |
| Docentes H | Titular, Plazo Fijo u Otros, según las capacidades contractuales del proceso |
| Docentes I | «si» sólo si existe la situación anual explícita `horas_catedra_art_69` |
| Docentes J, N, O | Fórmulas originales de suma y búsqueda en las tablas auxiliares |
| Docentes K–L | Horas pedagógicas de aula financiadas por General, separadas por nivel, cuando la regla es representable |
| Docentes M | 65/35 o 60/40 de la asignación; las educadoras elegibles de Parvularia representan el contrato especial como 65/35 según la excepción siguiente |
| Docentes P–Q | Horas cronológicas asignadas y financiadas por PIE o SEP |
| Docentes R | Funciones de General, situaciones 2027 y reservas/saldos titulares elegibles o a plazo fijo, clasificados como Otros |
| Docentes S | Contrato de referencia, tramo distribuido, situación 2027 con contrato completo y horas consideradas/no necesarias, pendientes, detalle e incompatibilidades |

Se consumen primero las horas titulares del docente y después las de plazo fijo.
Los grupos se recorren en orden estable de sus primeras asignaciones por ID.
Las reservas con origen contractual explícito apartan previamente su capacidad
titular o a plazo fijo; las demás asignaciones consumen el contrato restante. El
reparto es sólo una representación para el informe y no actualiza asignaciones ni
la calidad jurídica del padrón. El conteo de personas se obtiene de la nómina, no
de la cantidad de filas. Las personas que trabajan en dos establecimientos se
mantienen en cada RBD, conforme al formato original.

### Docentes por contratar y directivos por asumir

Se incluyen los cupos habilitados de 2027 en plan de estudio, Parvularia y PIE,
reutilizando **VACANTE-\<id\>**, la identidad virtual estable del SGA. El título,
cargo, bloque, horas y asignaciones provienen del cupo vigente. El saldo todavía
sin asignar se incorpora en Otros/R como plazo fijo. El mismo cupo no se repite
si aparece en ambas colecciones de la fuente.

Los **Docentes Directivos por asumir** usan la identidad separada
**VACANTE-ADP-\<establecimiento\>-\<regla\>** y sus horas se representan como
Función Directiva / Plazo Fijo. Se usan copias de las coberturas automáticas para
el informe; no se altera su estado ni su exclusión de las asignaciones a personas
en el resto del SGA. No se mezclan sus identidades con RUT reales o cupos.

Estos identificadores son sintéticos, no RUT personales. Observaciones conserva
la referencia del cupo o cargo y su condición pendiente. Resumen cuenta estas
identidades y sus horas además de las del padrón; la previa mantiene separado
el número de personas identificadas y las cantidades de cupos y plazas. Descargar
no crea personas, contratos ni asignaciones. Los cupos o plazas incorporados no
vuelven a figurar como excluidos; sólo se detallan sus incompatibilidades restantes
con una base distinta del contrato del padrón.

### Consolidación de filas docentes

Después de distribuir titular y contrata, se consolidan las filas con el mismo
**RUT normalizado, RBD, función del PAL (G) y calidad contractual (H)**. No se
mezclan funciones distintas ni contratos titulares con plazo fijo. La primera
aparición de cada fila determina el orden del resultado; se conservan las notas
de los registros de origen y se indica cuántas filas se unieron.

Se suman K, L, P, Q y R, manteniendo el desglose por nivel y financiamiento. La
columna M debe admitir una sola proporción. La equivalencia de las horas de aula
se valida **después de intentar consolidar**, porque el SGA acumula la docencia
y reparte entre asignaciones los incrementos de contrato. Un incremento aislado
puede no coincidir con una búsqueda independiente en la tabla del PAL.

Si las entradas individuales ya son representables, se conservan sus sumas de
**horas lectivas (N)** y **no lectivas (O)**. Para tramos incrementales se conserva
el contrato informado por el SGA: las lectivas corresponden a 45 minutos por
hora pedagógica y las no lectivas son su diferencia con el contrato. La fila
conjunta debe coincidir tanto en contrato como en ambos componentes con la tabla
original. N y O hacen **una búsqueda de K+L**, no una búsqueda independiente por
cada nivel. J conserva su fórmula y su total, incluyendo PIE, SEP y otras funciones.
No se modifican fórmulas ni se reparten contratos de nuevo en el SGA.

Ejemplo: dos filas de Docente aula/Titular con 19 h pedagógicas en Básica y
19 h en Media, 22 h contractuales cada una, y otra fila de 3 h PIE quedan en una
fila con K=19, L=19, P=3, M=65/35 y J=47. Se conserva **Ed. Básica/Media**.

Ejemplo de incrementos con datos sintéticos: una fila de Religión de 4 h
pedagógicas y 4 h de contrato, junto con otra de 8 h pedagógicas y 10 h de
contrato, no se descarta por sus equivalencias individuales 4→5 y 8→9 del PAL.
Con mismo docente, establecimiento, función, calidad contractual y 65/35, se
valida primero el conjunto: **12 h pedagógicas → 14 h de contrato**. Si las
asignaciones corresponden a Básica y Media respectivamente, quedan K=4, L=8,
N=9, O=5 y J=14. Observaciones conserva las cantidades de origen y explica la
validación conjunta. La hora pendiente de un contrato a plazo fijo de 15 h se
incorpora en Otros/R conforme a la regla actual de saldos a plazo fijo.

Si el conjunto tampoco es representable, se intentan subconjuntos compatibles
y se valida individualmente lo que queda. Se aplica el redondeo y la conversión
a aula equivalente descritos más adelante; las incompatibilidades que persistan
se marcan y quedan sin entradas numéricas.
Los problemas de nivel sin identificar, proporción no admitida, financiamiento
o exceso contractual no se corrigen mediante una unión. La referencia interna
del contrato de aula se retira del resultado antes de escribir el Excel.

La unión de filas de **Educación Básica y Educación Media** requiere **65/35
en ambas**, además del mismo docente, establecimiento, función y calidad
contractual. Las filas generadas con **60/40 se excluyen de esta consolidación**,
incluso si ambas son 60/40 y su suma tendría equivalencia en la tabla. Tampoco se
les agrega una fila PIE u otra sin proporción. Se conservan su cálculo original
y una explicación en Observaciones; no se convierten a 65/35. Las filas sin
horas lectivas continúan consolidándose entre sí cuando son compatibles.

Por decisión del usuario, las excepciones permanecen separadas y se explican en
Observaciones: filas 60/40, proporciones distintas, niveles sin opción común,
horas fuera del rango de la tabla, diferencias en lectivas/no lectivas que persisten
en el conjunto, incompatibilidades estructurales o datos de estamento, cargo y art. 69 que no coincidan.
Si no cabe todo el grupo, se intentan subconjuntos compatibles. No se fuerzan
redondeos ni nuevas fórmulas; una jornada de Parvularia que necesita varias filas
para representar su contrato conserva las que sean necesarias.

La consolidación se aplica al informe individual y al de todos los establecimientos.
No altera asignaciones, contratos, saldos del SGA, situaciones 2027 ni la exclusión
de BIR. Los recuentos de personas siguen siendo independientes de las filas.

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

Las horas excluidas por situaciones 2027 se incorporan al informe con **FUNCIÓN
(G) = Otros (Talleristas / Apoyo / etc.)** y como horas cronológicas no lectivas
en **R**. Se respeta su calidad contractual: las horas titulares y las de plazo
fijo se presentan separadas. Observaciones identifica el motivo, las horas por
situación, el contrato completo y la calidad correspondiente. También se cubren
las situaciones que excluyen todo el contrato; no se crea una asignación en el SGA.

Ejemplo: contrato completo 44 h, consideradas 20 h y situación 24 h; con 20 h
asignadas a función directiva, el PAL informa esa fila y las 24 h en función Otros.
La suma de J por docente es 44. Si el contrato es 32 h titular y 12 h a contrata,
las 20 h consideradas consumen primero titular y las 24 h de situación se
representan como 12 h Otros/Titular y 12 h Otros/Plazo Fijo.

### Horas sin asignar y Sobredotación

Además de las situaciones, se incorporan en **FUNCIÓN Otros / R** los saldos
**titulares y a plazo fijo sin asignar**, conservando separadas ambas calidades.
Las otras calidades contractuales sin correspondencia quedan excluidas y se
detallan en la hoja de exclusiones. Esta representación no crea asignaciones.

Del saldo titular se descuentan las horas con justificación registrada en
**Sobredotación**, para el mismo establecimiento, RUT normalizado y año **2027**,
en los bloques plan de estudio, Parvularia y PIE, de tipo `titular`, con cantidad
positiva y justificación no vacía. Se suman los importes registrados por bloque
y se limita el descuento al saldo titular actual: una justificación anterior
superior al saldo no resta horas ya asignadas ni vuelve negativos los totales.
No se usan justificaciones de otros años, establecimientos ni tipo contrata.
La lectura es una consulta por establecimiento, sin modificar las justificaciones.

### Reservas para otras funciones

Las reservas activas `reserva_no_normativa`, presentadas como **Horas para otras
funciones** y financiadas por **General**, se incorporan en **FUNCIÓN Otros / R**
por su porción **titular sin justificación en Sobredotación 2027** y por su
porción **a plazo fijo**, sin mezclar calidades.
No requieren equivalencia de aula en las tablas 65/35 o 60/40: son horas
cronológicas de reserva. Se conserva la fórmula J = N+O+P+Q+R y se explica su
clasificación en Observaciones. Las reservas de otras calidades quedan fuera
de las entradas numéricas y se detallan con su motivo en la hoja de exclusiones.

Se respeta el origen `titular` o `contrata` guardado en la reserva, aunque el
orden de sus ID difiera del de las clases o funciones. Se aparta su capacidad
antes de repartir el resto; una reserva no puede consumir horas de otra calidad
si supera la propia. Los registros históricos sin origen se reparten desde el
contrato disponible, primero titular, y Observaciones identifica la inferencia.
Un exceso de calidad o un financiamiento distinto de General conserva su
incompatibilidad y no se transforma en horas de otra columna.

Las justificaciones titulares se descuentan **una sola vez**: primero del saldo
titular sin asignar y luego, por el remanente, de las reservas titulares. El
descuento total queda limitado a la suma de ambos conceptos; no resta funciones
asignadas ni horas de situaciones. Ejemplo sintético: 44 h titulares, 28 h de
función, 10 h reservadas, 6 h pendientes y 8 h justificadas: se excluyen las 6 h
pendientes y 2 h de reserva. El PAL representa 28 + 8 = **36 h**, sin duplicar
el descuento. Las reservas no se suman de nuevo como saldo pendiente.

Observaciones distingue las horas por situación, las titulares sin asignación
incorporadas, las reservas incorporadas/excluidas y las titulares justificadas
que se excluyen del informe. Si ya
existe una fila Otros de igual función y calidad, se consolida con las nuevas
horas cuando es compatible, sin duplicar los valores. El personal BIR continúa
excluido. Si las horas consideradas y excluidas no coinciden con el contrato
completo, se cancela el informe para revisar los datos antes de informar cifras.

## Fórmulas originales y borradores

El usuario solicitó conservar estrictamente los criterios de cálculo originales.
Las instrucciones de la plantilla dicen informar el contrato completo en J, pero
J en realidad suma N+O+P+Q+R. Se conserva esta fórmula y se completa R con las
horas de situaciones, reservas y saldos elegibles descritos antes. Un docente
con contrato titular de 44 h, 30 distribuidas y sin justificación de Sobredotación
incluye las otras 14 h en FUNCIÓN Otros y suma 44 h en el PAL. El saldo a plazo
fijo también se incorpora; las horas titulares justificadas quedan excluidas.

Cada fila lectiva resultante, consolidada cuando corresponde, se contrasta con
el mismo rango exacto de BUSCARV que usa el Excel (`A4:D47` en cada tabla). Si
no existe una equivalencia, o no coincide con el contrato consolidado del SGA,
se redondea primero el contrato de aula al entero: fracción **0,5 o superior sube**;
fracción **menor de 0,5 se trunca**. Por ejemplo, 10,75 → 11 h y 24,25 → 24 h.
Después se invierte esa misma tabla para representar el contrato redondeado con
**horas aula equivalentes**, manteniendo la proporción original 65/35 o 60/40.
Se usa el menor número de bloques exactos para ese entero. Un tramo inferior a
0,5 h se representa como cero, conservando su referencia en Observaciones. El aula
real del SGA, el desglose por nivel, el contrato de referencia y cada parte de
la conversión, el contrato SGA original y la diferencia por redondeo se conservan
en S. Las fórmulas originales N/O/J no cambian. Este ajuste puede cambiar el total
representado por calidad respecto del contrato SGA y sólo afecta al informe.
Las equivalencias son una adaptación del informe y no representan el aula real.
Las filas 60/40 siguen separadas; las horas PIE, SEP u Otras de una fila dividida
se informan una sola vez. Si ni siquiera se puede invertir la tabla exactamente,
la fila queda **INCOMPATIBLE**, sin entradas numéricas, y en la hoja de excluidos.

### Hoja Excluidos PAL 2027

Ambas descargas añaden esta pestaña con RBD, establecimiento, RUT, nombre,
calidad contractual, motivo, horas excluidas, detalle, base de referencia y año
base. Incluye no continuidad para 2027 según la situación registrada en el
último padrón anterior (2026 mientras sea la fuente vigente), no renovación
activa de 2027, BIR 2027, horas titulares justificadas en Sobredotación y las
incompatibilidades que permanezcan. La no renovación tiene prioridad si una
persona también está marcada sin continuidad, evitando duplicar su contrato.
El padrón propio de 2027 prevalece sobre decisiones de continuidad de 2026.

La base **Contrato docente** corresponde al padrón. Los motivos restantes de
**Cupo por contratar**, **Plaza por asumir** y **Asignación excedente** tienen
otra base y no se descuentan nuevamente del padrón nominal. El redondeo de aula
se concilia aparte de las exclusiones usando el ajuste informado en S. No se
reincorporan las personas retiradas a la nómina ni se modifican situaciones,
asignaciones o justificaciones. El consolidado escribe por establecimiento,
sin acumular todos los excluidos en memoria; supera 100.000 filas sólo mediante
un error explícito que cancela la descarga completa.

Esto puede ocurrir con reglas especiales sin la equivalencia autorizada siguiente, fracciones producidas al
separar contratos, niveles sin desglose y financiamientos
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
es 40 h antes del saldo. Si las 4 h pendientes son titulares y no están justificadas
en Sobredotación, se incorporan en otra fila con FUNCIÓN Otros, sumando 44 h.

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
