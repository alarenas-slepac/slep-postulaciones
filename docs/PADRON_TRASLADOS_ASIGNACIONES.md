# Padrón: liberación de asignaciones por traslado

Cuando un funcionario aparece resuelto en un RBD nuevo, sus asignaciones activas
del RBD anterior no se mueven automáticamente. El flujo de traslado detecta el
caso cuando todas las asignaciones activas del grupo tienen el mismo RUT, sus IDs
fueron resueltos en un único RBD de destino y el año de la revisión coincide.

La opción **Liberar asignaciones por traslado** exige una justificación y una
confirmación del alcance. Durante la previsualización no modifica Dotación. Al
aplicar el padrón, inactiva únicamente las asignaciones del RBD de origen que
fueron verificadas; registra las imágenes antes/después en
`padron_asignacion_cambios` y conserva contratos, documentos, necesidades e
historial. No crea asignaciones en el destino.

Destinos múltiples, IDs sin correspondencia, RUT incompatibles, asignaciones de
otro año o cambios concurrentes mantienen el bloqueo. La liberación solo puede
aplicarse dentro de la transacción de la carga y la aplicación definitiva sigue
deshabilitada hasta completar la validación operativa.
