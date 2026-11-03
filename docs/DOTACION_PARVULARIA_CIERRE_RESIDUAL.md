# Cierre residual del plan de Educación Parvularia

Desde el proceso 2027, se permite una excepción operativa al asignar las últimas **hasta 2 horas aula** pendientes del plan del curso NT1/NT2 o grupo combinado seleccionado. El saldo se mide sobre todas sus asignaturas, como el total que muestra la tabla de asignación del curso. Se aplica tanto a Educadoras de Párvulos reales como a cupos **Docente por contratar** de ese bloque, dentro del establecimiento y año seleccionados.

La asignación debe pertenecer al plan vigente, usar la base especial de Parvularia y tener una educadora asociada con saldo contractual suficiente para cubrir **una hora de contrato por cada hora aula solicitada**. La conversión habitual debe superar ese saldo y no debe existir otra educadora real asociada que pueda cubrir la asignación con su saldo y los topes habituales. Si la conversión normal ya cabe, se conserva. El residuo puede repartirse entre asignaciones hasta completar el plan.

Ejemplo: quedan 2 h aula y el docente o cupo dispone de 2 h de contrato. Si se cumplen las condiciones, las 2 h aula consumen 2 h de contrato y el plan queda cubierto, sin sobreasignar a la persona. El cálculo institucional del máximo mantiene la base proporcional del curso (por ejemplo 55 h en NT con JEC); no se reduce esa necesidad ni se aumenta el cupo o máximo autorizado.

Se conservan los permisos y bloqueos existentes, asociación por asignatura, prelación cuando corresponda, límites de aula y contrato de la educadora, y máximos del bloque. No se permite usar la excepción si el curso o grupo mantiene más de 2 h pendientes entre sus asignaturas, asignar más aula que la pendiente, cubrir aula superior al saldo individual, ni cubrir PIE, acompañamiento u otras funciones. Los pendientes de otros cursos o grupos se mantienen independientes. Tampoco habilita libre disposición en NT sin JEC.

La fuente de cálculo identifica **Cierre residual de plan Parvularia**, las horas usadas y la conversión habitual. El consolidado individual conserva ese contrato aprobado, mientras el consolidado institucional usa la base original. Consultar no escribe cambios históricos. Una edición de observación conserva el cierre; al cambiar docente u horas se vuelve a evaluar la regla y al eliminar se recupera el saldo y se reabre la cobertura correspondiente.

El guardado vuelve a consultar aula y saldos bajo el bloqueo transaccional del establecimiento para evitar duplicar el residuo por solicitudes concurrentes. No se requieren migraciones ni cambios en archivos de entorno.

Pruebas: `tests/Feature/DotacionCierreParvulariaTest.php` y `tests/Unit/DotacionParvulariaCupoCalculatorTest.php`.
