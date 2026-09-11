# Bajas anteriores con continuidad como reemplazo/suplencia

El funcionario puede seguir en el Excel con un nuevo reemplazo aunque sus
contratos anteriores terminen. No corresponde tratarlo como ausente del archivo
ni como traslado a un destino regular. El reemplazo no aporta cobertura en Dotación.

## Revisión

1. Resolver las nuevas filas como nuevas líneas de reemplazo/suplencia.
2. Resolver las ausencias de los contratos anteriores como bajas.
3. En Conflictos con asignaciones de Dotación, revisar el bloque
   **Baja de contratos anteriores con continuidad como reemplazo/suplencia**.
4. Revisar IDs, año, establecimientos, cantidad de asignaciones y horas. Abrir
   **Confirmar baja y liberar asignaciones al aplicar**, ingresar justificación
   y aceptar expresamente el alcance.

La confirmación no modifica contratos ni asignaciones durante la revisión. Puede
retirarse. No requiere volver a cargar el archivo ni borra decisiones anteriores.

## Protecciones

- Todas las líneas efectivas deben ser nuevas líneas resueltas de reemplazo o
  suplencia, con identidad, período, establecimiento y jornada válidos.
- No aplica a propuestas regulares, conservaciones, IDs reutilizados, líneas
  pendientes, tipos desconocidos o reemplazos omitidos por vigencia.
- Todas las bajas deben estar resueltas. Los IDs explícitos de las asignaciones
  deben pertenecer a esas bajas; también admite vínculos solo por RUT.
- Se mantienen los controles de identidad, establecimiento contractual, año y
  horas de las asignaciones, y el resto de bloqueos del plan de aplicación.
- La huella incorpora las propuestas de reemplazo: sus cambios requieren una
  nueva confirmación. Las huellas de bajas completas anteriores no cambian.

## Aplicación

El escritor existente, dentro de su transacción, incorpora las nuevas líneas,
inactiva los contratos anteriores y libera únicamente las asignaciones confirmadas.
Registra imágenes antes/después. No borra documentos ni necesidades, no mueve
referencias históricas y no crea asignaciones para el reemplazo. Ante un fallo,
revierte toda la aplicación.

Se reutilizan las tablas de bajas y auditoría existentes; el alcance JSON
identifica la continuidad como reemplazo. No hay nuevas migraciones y este parche
no habilita la aplicación definitiva en producción.
