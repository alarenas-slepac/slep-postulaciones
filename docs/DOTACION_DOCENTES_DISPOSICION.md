# Docentes a disposición

Disponible desde la vista general de Dotación Establecimiento, para el año consultado
(2020–2100). Requiere rol asignado y activo `admin`, `coordinador_gdp`,
`coordinador_uatp` o `supervisor_plani`, además del acceso al módulo existente.

## Despliegue

La migración aditiva `2026_11_06_170000_create_dotacion_docente_distribuciones_table`
crea exclusivamente el registro auxiliar. No modifica contratos ni situaciones.
La reversión de código conserva esta tabla y su historial.

Ejecutar en cPanel, tras desplegar el código:

```bash
cd /home/slepac/apps/slep_postulaciones
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
```

## Nómina y saldo

En la vista general, los cuatro roles habilitados pueden marcar cada establecimiento
como **Revisado y finalizado** para el año seleccionado, con confirmación expresa.
La marca es manual, no se deduce del porcentaje de avance ni finaliza automáticamente
otras etapas. No modifica contratos ni bloquea la edición del módulo original.

Sólo los establecimientos con revisión finalizada de ese mismo año aportan docentes
y aparecen como destinos. Inicialmente ninguno queda marcado automáticamente.
La migración aditiva `2026_11_06_170100_create_dotacion_establecimiento_revisiones_table`
conserva responsable y fechas de cada revisión y reapertura. Para reabrir se exige
motivo. Una nueva finalización genera otra entrada y conserva las anteriores.

Reabrir retira el establecimiento del universo de nuevas distribuciones, sin borrar
registros previos. El detalle de sus distribuciones conserva consulta y anulación.
Los permisos y el cierre anual se verifican otra vez al guardar, bloqueando origen
y destino en orden de ID para coordinar las operaciones y las reaperturas.

- Reutiliza las vacantes revisables y las justificaciones del año y establecimiento,
  separadas en Planes de Estudio, Educación Parvularia y PIE, por horas titulares
  o a contrata. Conserva las exclusiones de contratos protegidos de la nómina original.
- Supresión total implica que los saldos de todos los bloques y calidades abarcan
  el contrato considerado de origen. Si sólo abarcan una parte, es parcial.
  Un contrato desconocido se informa como sin clasificar.
- Incorpora no renovaciones registradas vigentes, distribuidas según sus cupos por
  bloque; el retiro y sus cupos no suman dos veces. Retornos o anulaciones del origen
  no borran las distribuciones ya registradas.
- Ordena globalmente por el grupo de prelación del SGA, fecha de antigüedad
  (sin fecha al final) y nombre. Las no renovaciones recuperan los antecedentes de
  carrera del padrón anual cuando existen; sin ellos se presentan en el grupo a
  contrata, con tramo y fecha no informados.
- Saldo = horas actuales del origen menos distribuciones activas para la misma
  combinación año, establecimiento, RUT, bloque y calidad. Usa centésimas de hora.
- El guardado vuelve a consultar el origen después de bloquear su establecimiento
  dentro de una transacción. Comparte el orden de bloqueo de las operaciones
  existentes de asignación y no renovación; reintenta hasta tres veces ante deadlock.
- Una solicitud UUID impide repetir la misma distribución por doble envío.

## Filtros de consulta

Tipo de horas distingue **Supresión parcial**, **Supresión total**, **No renovación**
y **Supresión sin clasificar**. Los enlaces históricos `tipo=titular` siguen
consultando todas las supresiones. La clasificación total/parcial mantiene las
reglas de contrato considerado entre todos los bloques y calidades.

La nómina permite combinar título, nivel y área de enseñanza con los filtros
existentes. Nivel y área proceden de la justificación registrada; los antecedentes
históricos sin esos campos se pueden consultar como **Sin información**. El catálogo
de áreas incluye las copias históricas de las justificaciones. Los filtros se
conservan al cambiar de pestaña o página.

La asignación de destino incorpora los mismos tres filtros. Allí el título procede
del docente o cupo, el nivel del curso de la asignación (o su declaración cuando no
hay curso) y el área de la asignatura registrada. No se infiere una habilitación
profesional ni se modifica la justificación del origen. La suma se filtra por
asignación antes de contar cada contrata o cupo una vez por bloque.

## Referencia de cupos del destino

La referencia del selector suma **horas de contrato efectivamente asignadas**, no
el contrato completo ni la brecha teórica del establecimiento. Se consulta el mismo
año y bloque de la distribución:

1. Docentes por contratar con asignaciones activas.
2. Docentes reales con contrato positivo, cero horas de planta y todas sus horas
   a contrata, también con asignaciones activas.

Excluye Director, Inspector, UTP, Coordinación PIE y Convivencia Educativa/Escolar,
tanto por función principal como por función de la asignación. Excluye reservas,
asistentes, plazas por asumir, años u orígenes distintos y situaciones de fuero
maternal o gremial (`horas_gremiales`). La lactancia se excluye por fuero sólo
cuando éste consta expresamente.

Se muestran por separado las horas asignadas a cupos por contratar y las horas
renovables de docentes reales íntegramente a contrata. No se incluyen las horas
habilitadas de cupos que aún no tienen asignaciones. La selección presenta una
tabla con los tres bloques (Planes de Estudio, Educación Parvularia y PIE) y la
cantidad de cupos y contratas de cada uno. Una persona puede participar en más
de un bloque; el total del selector no la cuenta dos veces.

Los destinos con más horas detectadas en el bloque de origen aparecen primero.
Sin filtros se conservan los destinos con cero. Al aplicar filtros se muestran
las coincidencias de cualquiera de los bloques; el destino de una distribución
en corrección se conserva aunque no coincida. **La referencia nunca actúa como
máximo:** el límite es el saldo disponible del docente de origen.

## Selección y reserva de cupos · 2026.11.16.692

Registrar destino permite elegir un **cupo concreto** del establecimiento, por
persona y bloque, tanto por contratar como a contrata por renovar. El selector y
el cuadro muestran requerimiento, horas ya destinadas, saldo disponible y las
destinaciones activas vinculadas. Los cupos cubiertos siguen visibles como
**Ya destinado**, deshabilitados para nuevas vinculaciones. Los parcialmente
destinados admiten usar el saldo restante.

Se permite destinar más horas que las disponibles en el cupo: por ejemplo,
24 h a un cupo PIE de 19 h, si el origen dispone de 24 h. Las 24 h quedan
vinculadas y el cupo no puede volver a seleccionarse. El exceso se informa en
el formulario y queda visible en el historial. El saldo de origen conserva
su límite acumulado.

La ocupación se calcula sumando distribuciones activas del mismo año,
establecimiento y cupo; se consulta siempre de nuevo, fuera de la caché de
fuentes. El guardado valida la selección y su disponibilidad después de bloquear
origen y destino, rechazando formularios antiguos que intenten reutilizar un
cupo cubierto. No acepta horas de disponibilidad enviadas desde el navegador.
Los filtros seleccionan cupos coincidentes, pero conservan su requerimiento
completo y descuentan todas sus vinculaciones, evitando liberar horas al filtrar.

Una corrección excluye su versión anterior del cómputo y registra la nueva
vinculación en la misma transacción. Cambiar de cupo libera el anterior; anular
libera las horas vinculadas. Un fallo conserva la versión y ocupación anteriores.
La clave del cupo y sus antecedentes quedan en `antecedente.destino_cupo`, sin
migraciones. Los registros históricos sin cupo siguen visibles y se pueden
anular o corregir en su destino original; no se les atribuye un cupo automáticamente.
Una vinculación cuyo cupo dejó de existir se puede regularizar conservando su
antecedente, sin ofrecerla como cupo para nuevas destinaciones.

Sin JavaScript, el selector nativo agrupa los cupos por establecimiento y el
cuadro completo se puede consultar bajo **Consultar referencias por bloque**.

## Rendimiento y vigencia

Las fuentes calculadas se reutilizan durante 120 segundos, por establecimiento y
año. La nómina y el detalle comparten esa lectura; las referencias de destinos
incluyen los tres bloques para no reconstruirlas al filtrar. El cálculo de varios
establecimientos comparte sus consultas de períodos del padrón en una memoria
que se descarta al terminar.

`DisposicionLecturaServiceProvider` detecta escrituras del SGA, incluidas las de
Query Builder, en las tablas que alimentan el cálculo. Cambia la generación de
la caché **después del commit** y no publica una generación por transacciones
revertidas. Las distribuciones no invalidan la fuente: el historial, sus cantidades,
los cierres anuales y los permisos se consultan siempre de nuevo. Las alteraciones
externas al SGA se reflejan como máximo al vencer los 120 segundos o al limpiar
la caché con el procedimiento habitual de despliegue.

Guardar una distribución nunca utiliza esa caché: vuelve a calcular el origen
después de tomar sus bloqueos y valida el saldo actual. El cambio no requiere
migraciones ni genera notificaciones. La caché puede deshabilitarse con
`dotacion_disposicion.cache_seconds = 0`; no se modifica `.env`.

## Historial

El registro auxiliar guarda destino, horas, observación, responsable y una copia
de los antecedentes y justificación de origen al distribuir. Una corrección anula
la versión anterior y crea otra, vinculadas entre sí y dentro de la misma
transacción. Anular libera las horas y conserva responsable, fecha y motivo.

Si baja el saldo de origen, la vista marca el exceso y exige corregir o anular
antes de agregar horas. Si desaparece el origen, permite consultar y anular el
historial sin recrear disponibilidad.

Esta distribución reserva las horas del cupo en el registro auxiliar de
destinaciones. Conserva el padrón, los contratos y las asignaciones existentes;
no asigna cursos automáticamente ni genera notificaciones nuevas.
