# Jornada de aula de Educadoras de Párvulos · 2026.11.17.694

En Dotación 2027, la Educadora de Párvulos puede asignar al aula toda su jornada
contractual disponible. Se retiran para este proceso los topes fijos de 35 horas
pedagógicas y 41 horas contractuales de aula, y la obligación implícita de dejar
3 h del contrato para trabajo colaborativo PIE. La misma regla rige al crear y
editar y para identificar otras educadoras disponibles antes de un cierre residual.

Ejemplo: cuatro educadoras de 44 h ya tienen 41 h contractuales de aula y 3 h PIE
cada una. Una quinta educadora de 44 h puede recibir las 44 h contractuales en
aula si el plan tiene horas pendientes y el bloque conserva capacidad autorizada.
No necesita tener PIE asignado ni reservar automáticamente esas horas.

El límite sigue siendo el contrato individual menos todas las asignaciones
vigentes. Si esa misma educadora ya tiene 3 h PIE, sólo dispone de las 41 h
restantes; no se permite sumar 44 h aula y además 3 h PIE a una jornada de 44 h.
Los contratos menores mantienen su propio límite.

Las horas de aula ingresadas continúan siendo pedagógicas y se convierten a
contrato con la base especial del curso y el redondeo acumulado por educadora.
En un curso Con JEC con plan de 38 h y base de 55 h, por ejemplo, 30 h pedagógicas
consumen 44 h de contrato. No se incrementa la necesidad del curso ni el máximo
institucional. Se conservan asociaciones por asignatura, prelación, perfil,
permisos, planes pendientes y bloqueos del proceso.

El cambio se limita al proceso 2027, donde el servidor valida contrato disponible
y máximo del bloque dentro de la transacción de guardado. Los otros años
conservan su comportamiento y no se reescriben asignaciones históricas. No
requiere migraciones ni compilación de recursos.

Las pruebas relacionadas usan SQLite en memoria y datos sintéticos:
`DotacionParvulariaCupoInstitucionalTest`, `DotacionParvulariaReglaEspecial2027Test`
y `DotacionCierreParvulariaTest`. Cubren la quinta educadora con 44 h de aula,
edición con jornada completa, descuento de PIE existente, rechazo de horas que
superan la jornada y el cierre residual con otra educadora que ya tiene 41 h aula.

Validación local: 42 pruebas de contrato, cupo institucional y cierre residual,
además de 127 pruebas adicionales de bases, libre disposición, vista de asignación,
PIE combinado y cálculo contractual, aprobadas. La ayuda se revisó a 1440 y
390 px, con teclado y sin JavaScript, sin desborde horizontal.

La revisión ampliada también detectó 14 casos existentes de
`DotacionLibreDisposicionParvulariaTest` cuyos datos de justificación omiten
campos obligatorios de enseñanza. Los 14 errores se reprodujeron cargando el
controlador de la versión anterior (`7a3bb0e`), sin el cambio de jornada. Su
corrección queda fuera de este parche.
