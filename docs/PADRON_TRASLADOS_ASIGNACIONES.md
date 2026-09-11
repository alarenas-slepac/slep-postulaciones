# Padrón: liberación de asignaciones por traslado

Cuando un funcionario aparece resuelto en un RBD nuevo, sus asignaciones activas
del RBD anterior no se mueven automáticamente. El flujo de traslado detecta el
caso cuando todas las asignaciones activas del grupo tienen el mismo RUT, todas
las correspondencias y ausencias del RUT están resueltas y existe un único RBD
de destino con contratos regulares del período. No debe quedar contrato propuesto
en el origen. También reconoce asignaciones vinculadas solo por RUT, aunque los
contratos ya estuvieran registrados en el destino antes de analizar el archivo.

Si una asignación tiene ID contractual, este debe estar seleccionado en el destino.
Si solo tiene RUT, admite propuestas resueltas como nuevas líneas; la pantalla
advierte que se deben seleccionar los IDs existentes cuando corresponde conservar
los contratos. Dar de baja y crear nuevas líneas no libera asignaciones por sí solo.
Las decisiones no se cambian automáticamente.

La opción **Liberar asignaciones por traslado** exige una justificación y una
confirmación del alcance. Durante la previsualización no modifica Dotación. Al
aplicar el padrón, inactiva únicamente las asignaciones del RBD de origen que
fueron verificadas; registra las imágenes antes/después en
`padron_asignacion_cambios` y conserva documentos, necesidades e historial.
Los contratos se actualizan, incorporan o inactivan según las correspondencias
registradas, no por la autorización de liberación. No crea asignaciones en el destino.

La corrección funciona al recargar la misma revisión, sin volver a cargar el Excel.
Las confirmaciones anteriores de traslado deben revalidarse porque ahora su alcance
incluye las propuestas efectivas del destino. Las demás decisiones se conservan.

Destinos múltiples, IDs sin correspondencia, RUT incompatibles, asignaciones de
otro año o cambios concurrentes mantienen el bloqueo. La liberación solo puede
aplicarse dentro de la transacción de la carga y la aplicación definitiva sigue
deshabilitada hasta completar la validación operativa.
