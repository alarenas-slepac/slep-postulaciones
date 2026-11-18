# Cupo de Plan General asociado a Educación Parvularia

Parche `2026.11.18.696`, proceso de Dotación 2027.

Un **Docente por contratar** creado en **Plan General** podía asociarse a una
asignatura de Educación Parvularia, pero luego desaparecía del selector de horas
o recibía un rechazo por pertenecer a otro bloque. Ahora esa asociación permite
asignar horas del plan NT1/NT2 al mismo cupo, incluidos cursos combinados.

1. Asociar el cupo a la asignatura del nivel Educación Parvularia en
   **Docentes por asignatura del plan de estudio**.
2. Abrir la asignatura del curso en **Asignación de horas** y seleccionar el
   docente por contratar.
3. Ingresar las horas aula pendientes. El servidor comprueba la asignatura y
   el curso vigentes y valida el contrato disponible antes de guardar.

La conversión de aula a contrato se conserva según el título del cupo. Con JEC,
los perfiles sin título de Educación de Párvulos usan 65/35; el título de
Párvulos mantiene su base especial. Sin JEC sólo se permite el título de
Educación de Párvulos. Asociar el cupo no cambia su título ni su contrato.

Se mantienen la prelación de los docentes asociados, las horas pendientes y el
máximo autorizado del bloque Educación Parvularia. La asignación consume el
mismo contrato del cupo de Plan General: editar libera primero las horas
actuales y eliminar restituye ese saldo. No se crean cupos adicionales ni se
cambia su bloque contractual original.

La excepción se limita a asignaturas del plan NT1/NT2 de 2027. No habilita
automáticamente PIE ni acompañamiento, otros establecimientos o cursos, ni
otros años. Las asignaciones históricas no se reescriben al consultar.

`DotacionCupoGeneralParvulariaTest` utiliza SQLite en memoria y datos sintéticos.
Cubre NT1, NT2, combinación NT1+NT2, asociación, selector, guardado, edición,
eliminación, jornada completa, prioridad titular, límites y manipulación del
curso. También conserva la cobertura de un cupo propio de Parvularia y las
reglas de título sin JEC. No requiere migraciones ni compilación de recursos.

Validación local: 147 pruebas relacionadas aprobadas, con 1338 aserciones;
sintaxis PHP 8.3, `optimize:clear` y `git diff --check` correctos. Asociación y
asignación revisadas en Chrome a 1440 y 390 px, con búsqueda y selección por
teclado, sin errores de JavaScript ni desborde horizontal.
