# Cupos por contratar en trabajo colaborativo PIE combinado

Parche `2026.11.17.693`.

En el grupo NT1 + NT2, la necesidad de trabajo colaborativo PIE conservaba el
identificador del curso representante, pero no su relación con el nivel educativo.
El selector de cupos clasificaba esa fila como Plan General y omitía al docente
por contratar de Educación Parvularia. La validación del servidor sí consultaba
el curso real, por lo que selección y guardado no estaban alineados.

La consolidación ahora incorpora el curso representante desde los miembros del
grupo, cuyas relaciones ya se consultan en conjunto. Conserva el mismo ID, incluso
cuando sólo NT2 tiene estudiantes NEE. El selector identifica el bloque correcto
sin añadir consultas por fila ni inferirlo del nombre del grupo.

Se mantienen los requisitos de cupo habilitado, saldo positivo, perfil, año y
establecimiento. Las horas suman al bloque de Parvularia y se financian con PIE.
La necesidad sigue siendo de 3 h por grupo combinado. En Básica se conserva la
clasificación de Plan General. Los contratos y las asignaciones existentes no
se reescriben; no requiere migraciones ni compilación de CSS o JavaScript.

La regresión en `tests/Feature/DotacionPieCombinadoCupoParvulariaTest.php` reproduce
los cursos, el grupo, las necesidades y el catálogo real del selector. Cubre NT
con/sin JEC, NEE sólo en NT2, 2026, Básica, generación del payload, guardado de
las horas y rechazo de exceso contractual o cupos de otro bloque. Usa datos
sintéticos y SQLite en memoria.

Después de desplegar, limpiar las cachés con PHP 8.3 mediante el procedimiento
habitual del proyecto. No se necesitan cambios en `.env`.
