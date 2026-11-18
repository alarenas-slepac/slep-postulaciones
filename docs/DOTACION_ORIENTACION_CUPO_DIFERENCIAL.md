# Asignatura Orientación con un cupo de Educación Diferencial

Parche `2026.11.17.695`.

Un docente por contratar del bloque PIE con título de Educación Diferencial
puede cubrir la **asignatura Orientación de un curso**. La excepción se aplica
en la asociación de docentes y en el selector y guardado de horas del plan.

1. En **Docentes por asignatura del plan de estudio**, abrir el nivel del curso
   y asociar el cupo a **Orientación**.
2. En **Asignación de horas**, abrir Orientación del curso y seleccionar ese
   docente por contratar.
3. Ingresar sus horas aula. El sistema conserva la conversión contractual
   vigente y valida el contrato disponible antes de guardar.

La asignatura debe ser Orientación en el plan vigente. Se reconocen diferencias
de mayúsculas, tildes y espacios. Un nombre enviado desde el formulario no
habilita Matemática u otra asignatura como Orientación. Si existe un nombre
oficial de la asignatura, éste prevalece. La excepción requiere título
diferencial; un cupo PIE sin ese título no recibe esta habilitación.

Las horas se descuentan del mismo cupo que usa Educación Diferencial PIE.
Por ejemplo, un cupo de 12 h con 10 h PIE ya asignadas puede utilizar las
2 h de contrato restantes en Orientación, según la conversión de sus horas
aula. No puede utilizar esas mismas horas nuevamente en PIE.

Se conservan la asociación previa, prelación de docentes, horas pendientes del
plan, máximo de Plan General y bloque contractual original del cupo. Editar
restituye las horas de la asignación actual antes de validar; eliminar devuelve
el saldo. No se duplican cupos ni se redistribuye su contrato entre bloques.

Este cambio no habilita la función Orientador(a), otras asignaturas ni cursos
NT1/NT2. No modifica asignaciones históricas al consultar. No requiere
migraciones ni compilación de CSS o JavaScript.

Las pruebas de `DotacionOrientacionCupoDiferencialTest` usan SQLite en memoria y
datos sintéticos. Cubren asociación, opciones de ambos selectores, creación,
edición, eliminación, PIE existente, jornada completa, rechazo por contrato,
plan y máximo insuficientes, y manipulación del nombre de otra asignatura.
`DotacionAsignacionElegibilidad2027Test` comprueba la identificación del perfil
y la asignatura, sin ampliar la habilitación a otras funciones o títulos.

Validación local: 148 pruebas relacionadas aprobadas (1548 aserciones), sintaxis
PHP 8.3 y `optimize:clear` correctos. Se revisaron ambos selectores en Chrome a
1440 y 390 px, incluyendo búsqueda y selección con teclado, sin errores de
JavaScript ni desborde horizontal.
