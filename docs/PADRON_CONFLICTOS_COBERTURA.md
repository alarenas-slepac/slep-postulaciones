# Padrón: comparación de cobertura y conflictos agrupados

Actualizado en el parche 2026.9.10.508. Diagnóstico de solo lectura; no aplica la carga, modifica contratos ni reasigna horas.

## Unidad de revisión

Cada caso corresponde a un RUT normalizado y establecimiento. La pantalla pagina casos,
no asignaciones: muestra una sola vez el total asignado, los motivos y los avisos.
El detalle desplegable conserva todas las asignaciones activas del grupo, sus horas,
ID contractual (o vínculo por RUT), tipo, asignatura y fila Excel cuando corresponde.
Si no se puede identificar el RUT, las asignaciones se mantienen separadas.
Los contadores de asignaciones se conservan, pero no representan funcionarios distintos.

## Comparación

Se usa el mismo total de horas contractuales asignadas en ambas columnas:

- Exceso actual = máximo entre cero y total asignado menos cobertura actual.
- Exceso propuesto = máximo entre cero y total asignado menos cobertura propuesta.

La cobertura actual consulta `PadronPeriodoService::consultaAnual`, igual que la base
temporal de Dotación: último período del establecimiento/año, vigencia, versiones
históricas y piso de cargas completas. No recupera una fila antigua solo porque
una asignación conserve su ID. Solo considera contratos regulares.

La propuesta usa las filas seleccionadas en la revisión y sus correspondencias
resueltas. En ambos lados se conserva la selección existente de Declaración de
Sostenedores: sus horas positivas tienen prioridad, no se suman al padrón.
Sin horas declaradas positivas se usa la jornada del padrón. Se descuentan las
exclusiones docentes existentes. Jornada Básica/Media no se suman nuevamente.
Una declaración sin contrato regular propuesto no acredita pertenencia al padrón.

| Situación | Tratamiento del exceso |
| --- | --- |
| Ya existía y la propuesta lo mantiene o reduce | Aviso; no bloquea por cobertura |
| No existía y la propuesta lo genera | Bloqueo |
| Existía y la propuesta lo aumenta | Bloqueo |
| No hay base anterior comparable, pero existe cobertura regular propuesta positiva y no ambigua | Aviso; no se presume preexistencia ni se bloquea solo por ese exceso |
| No hay contrato regular propuesto, la cobertura es cero o la propuesta tiene ambigüedad de estamento/composición | Mantiene el bloqueo |
| La propuesta elimina el exceso | No bloquea por cobertura |

Se mantiene la tolerancia existente de 0,01 h. La clasificación considera las
coberturas efectivas; por eso una declaración prioritaria puede mantenerlas
iguales aunque cambie la jornada del archivo. Ambas jornadas y la fuente se
muestran para revisión, sin alterar la regla de prioridad de Dotación.

Ejemplo sintético: 34 h asignadas, cobertura actual 33 h y propuesta 33 h producen
un solo aviso de exceso preexistente de 1 h. No son 1 h de exceso por asignación.
Si la cobertura actual era 44 h y la propuesta baja a 33 h, ese exceso de 1 h es
nuevo y bloquea. Si baja de 33 a 32 h, el exceso aumenta de 1 a 2 h y bloquea.

Ejemplo sin base comparable: 49 h asignadas y cobertura propuesta de 44 h muestran
5 h de exceso por revisar como aviso. El exceso actual sigue figurando como
«No comparable»; no se inventa una cobertura anterior ni se acredita preexistencia.
Las 49 h asignadas y las 44 h contractuales no se modifican ni se autorizan 5 h nuevas.

## Protecciones que no cambian

Un aviso de exceso preexistente o sin base comparable no anula los bloqueos por pérdida del ID contractual,
traslado, identidad incompatible, ausencia de contrato regular, reemplazo/suplencia,
cambio de estamento, horas inválidas o correspondencias sin resolver. Por ello un
caso puede tener un aviso de exceso y continuar bloqueado por otro motivo.
La autorización de jornadas superiores a 44 h tampoco sustituye estos controles.

Se conserva el detalle plano `items` para consumidores existentes. La pantalla usa
`grupos`; el plan conserva los errores, ahora deduplicados por RUT/establecimiento.
Las lecturas se hacen por lotes y no cargan observaciones ni adjuntos en el diagnóstico.

## Después de instalar

La huella de cobertura cambia a `cobertura-v6-aviso-sin-base-comparable`.
Recargar la misma revisión recalcula el diagnóstico y la confirmación final;
este cambio no invalida la base contractual ni reescribe decisiones o autorizaciones.
Una confirmación final anterior debe renovarse, conservando la revisión manual.

No requiere migraciones nuevas, cambios de rutas o compilación de CSS/JavaScript.
No habilita la aplicación definitiva del padrón ni elimina su protección anual.
Las pruebas utilizan únicamente SQLite efímero y personas sintéticas; no se modifica producción.
