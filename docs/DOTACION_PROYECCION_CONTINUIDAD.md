# Continuidad docente y proyección de dotación

Desde Dotación Establecimiento del año base, el botón **Proyección 2027**
(si el año base es 2026) abre una vista completa de solo lectura.

En **Docentes → Situación docente**, el check **Contemplar a este docente y
sus horas en dotación 2027** determina si continuará en esa proyección.
Está marcado por defecto. El motivo Proceso BIR no lo desmarca automáticamente.

## Efectos

- Las horas necesarias y no necesarias siguen sumando el contrato original.
- Desmarcar la continuidad retira el aporte contractual del docente y toda
  su cobertura asignada de la proyección. No elimina ni modifica sus registros.
- Se conservan las necesidades de cursos, plan de estudio, funciones normativas,
  funciones declaradas y PIE del año base, aunque el docente que las cubría salga.
- La cobertura de otras personas permanece. Las horas pendientes se calculan
  para cada necesidad; los excedentes de otra función o curso no las compensan.
- El detalle curricular usa horas pedagógicas; las funciones usan horas de
  contrato. Los indicadores de brecha utilizan horas de contrato.
- Aula, Educación Parvularia y Docente PIE mantienen sus reglas de clasificación,
  incluida la excepción de escuelas especiales.
- Reincorporar el check o eliminar la situación restablece la participación
  proyectada del docente según su contrato y asignaciones del año base.

La decisión se guarda por RUT, establecimiento y año base. La proyección siempre
corresponde al año siguiente: no modifica ni sustituye el padrón, los cursos o
las funciones que se configuren directamente para ese otro año.

### Ejemplo

Un Jefe UTP tiene contrato de 44 horas, 44 necesarias, 0 no necesarias y 44
asignadas a una función normativa. Al registrar Proceso BIR y desmarcar 2027:

- En 2026 conserva 44 de contrato y 44 asignadas.
- En la proyección 2027 aporta 0 de contrato y 0 de cobertura.
- La función normativa continúa necesitando 44 horas. Sin otra cobertura,
  las 44 horas quedan pendientes. El mismo criterio se aplica al plan de estudio.

## Instalación

La migración `2026_09_14_160000_add_continuidad_to_dotacion_docente_exclusiones.php`
agrega `considerar_dotacion_siguiente` con valor predeterminado verdadero.
Conserva las situaciones existentes. Requiere que ya esté instalada la tabla de
exclusiones docentes. Antes de instalarla, la vista advierte que todos se
contemplan y no permite enviar una decisión que no pueda guardarse.

En producción se debe ejecutar Artisan con la ruta absoluta del PHP 8.3 instalado
en el servidor. Este cambio no ejecuta migraciones ni escribe en producción.
