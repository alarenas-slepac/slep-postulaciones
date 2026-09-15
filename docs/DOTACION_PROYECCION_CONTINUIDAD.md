# Continuidad docente y proyección de dotación

Desde Dotación Establecimiento del año base, el botón **Proyección 2027**
(si el año base es 2026) abre una vista completa de solo lectura.

En **Docentes → Situación docente** hay dos decisiones independientes:

- **El docente continúa en dotación 2027**: desmarcar registra la salida de la persona.
- **Contemplar horas necesarias en dotación 2027**: conserva las horas necesarias
  de quien sale como vacantes por cubrir, sin reincorporarlo al contrato proyectado.

Ambas están marcadas por defecto. El motivo Proceso BIR no registra automáticamente
la salida; Fuero maternal y Horas gremiales tampoco implican que la persona salga.

## Efectos

- Las horas necesarias y no necesarias siguen sumando el contrato original.
- Desmarcar la continuidad retira el aporte contractual del docente y toda
  su cobertura asignada de la proyección. No elimina ni modifica sus registros.
- Se conservan las necesidades de cursos, plan de estudio, funciones normativas,
  funciones declaradas y PIE del año base, aunque el docente que las cubría salga.
- El indicador verde **Hrs vacantes por cubrir 2027** suma las horas necesarias
  conservadas de las personas que no continúan. Excluye sus horas no necesarias.
- Para evitar duplicaciones, se reconoce la necesidad de plan o función vinculada
  mediante las asignaciones del año base, hasta el total contractual requerido
  de esa necesidad. La cantidad de horas asignadas no limita la reserva manual.
  Una necesidad compartida se reconoce una sola vez entre los docentes que salen.
- Si parte de las horas conservadas no estaba incluida en una necesidad vinculada,
  se agrega como necesidad adicional de Aula, Parvularia o PIE según el docente.
  El detalle muestra cuánto ya estaba contemplado y cuánto se agrega a la brecha.
- Desmarcar la conservación evita generar una reserva por esa situación. No borra
  las necesidades propias de los planes y funciones configurados en el año base.
- La cobertura de otras personas permanece. Las horas pendientes se calculan
  para cada necesidad; los excedentes de otra función o curso no las compensan.
- El detalle curricular usa horas pedagógicas; las funciones usan horas de
  contrato. Los indicadores de brecha utilizan horas de contrato.
- Aula, Educación Parvularia y Docente PIE mantienen sus reglas de clasificación,
  incluida la excepción de escuelas especiales.
- Reincorporar el check de continuidad o eliminar la situación restablece la participación
  proyectada del docente según su contrato y asignaciones del año base.

La decisión se guarda por RUT, establecimiento y año base. La proyección siempre
corresponde al año siguiente: no modifica ni sustituye el padrón, los cursos o
las funciones que se configuren directamente para ese otro año.

### Ejemplo

Un Jefe UTP tiene contrato de 44 horas, 44 necesarias, 0 no necesarias y 44
asignadas a una función normativa. Al registrar Proceso BIR, desmarcar la continuidad
y marcar **Contemplar horas necesarias en dotación 2027**:

- En 2026 conserva 44 de contrato y 44 asignadas.
- En la proyección 2027 aporta 0 de contrato y 0 de cobertura.
- La función normativa continúa necesitando 44 horas. Sin otra cobertura,
  las 44 horas quedan pendientes. El mismo criterio se aplica al plan de estudio.
- El indicador de vacantes muestra 44 horas en verde. La necesidad normativa sigue
  siendo 44 horas, no 88; marcar conservación no reincorpora a la persona.

## Instalación

La migración `2026_09_14_160000_add_continuidad_to_dotacion_docente_exclusiones.php`
agrega `considerar_dotacion_siguiente` con valor predeterminado verdadero.
Conserva las situaciones existentes. Requiere que ya esté instalada la tabla de
exclusiones docentes. Antes de instalarla, la vista advierte que todos se
contemplan y no permite enviar una decisión que no pueda guardarse.

La migración `2026_09_14_170000_add_conservar_horas_to_dotacion_docente_exclusiones.php`
agrega la conservación independiente de horas, marcada por defecto. Conserva las
decisiones de continuidad ya guardadas. Hasta instalarla, las salidas mantienen
sus horas necesarias por defecto y la pantalla informa que falta habilitar la casilla.

En producción se debe ejecutar Artisan con la ruta absoluta del PHP 8.3 instalado
en el servidor. Este cambio no ejecuta migraciones ni escribe en producción.
