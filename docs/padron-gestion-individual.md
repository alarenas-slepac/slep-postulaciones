# Gestión individual del padrón

Acceso: **Reemplazos → Gestionar registro individual**, solo administrador.

1. Ingresar el RUT con dígito verificador y consultar. Se valida el DV y se buscan datos internos; no se consulta una fuente externa de identidad.
2. Si el RUT no tiene contratos, completar su primer registro.
3. Para PLANTA, CONTRATA, PLAZO FIJO o INDEFINIDO existentes, seleccionar explícitamente el ID del período abierto. Si tiene varias líneas, se modifica solo la seleccionada. Un contrato inactivo del período abierto se reactiva con el mismo ID al guardar.
4. Para un nuevo REEMPLAZO, usar el formulario de ingreso sin seleccionar ID. Se conserva todo registro anterior; un duplicado exacto se rechaza.
5. Completar establecimiento, nombre, fechas, jornada total y componentes básica/media, financiamiento, estatuto, escalafón, bienios, tramo y justificación. El término es obligatorio para REEMPLAZO y PLAZO FIJO.
6. En un traslado, revisar las asignaciones agrupadas por RBD/establecimiento. Marcar únicamente las de origen que se desea liberar, confirmar su liberación y justificarla. Las del destino no pueden liberarse desde este flujo.
7. Guardar y verificar el ID informado, la cantidad de asignaciones liberadas y la auditoría al pie de la consulta.

## Protecciones

- El período es el último abierto del padrón, no el mes calendario. Un ingreso individual no adelanta el padrón completo a otro mes. Los contratos regulares de períodos anteriores se concilian mediante la carga completa para conservar el historial mensual; no se duplican aquí.
- El RUT, ID y hash de contratos existentes se conservan. No se actualizan masivamente las demás líneas del funcionario.
- Se controlan hasta 44 horas docentes simultáneas sumando los establecimientos del RUT. Los intervalos incluyen inicio y término; contratos consecutivos sin superposición no se suman. El exceso exige autorización explícita del administrador y justificación adicional, que quedan auditadas.
- Las asignaciones muestran ID, establecimiento, tipo/detalle, curso/necesidad, vínculo contractual y horas. La selección es individual y ninguna viene marcada por defecto.
- En un traslado se pueden liberar asignaciones de establecimientos distintos al destino, incluso de un origen anterior cuando el contrato ya fue trasladado. Se inactivan, sin borrar filas, horas, necesidades, documentos ni referencias y sin crear asignaciones en destino. Las no seleccionadas se conservan; si se trasladan o reducen horas y quedan asignaciones, se exige confirmar su revisión en Dotación.
- La liberación exige confirmación y justificación adicionales. Se revalida identidad, año, estado, origen y la huella de cada asignación seleccionada. Los cambios concurrentes en filas seleccionadas cancelan toda la operación. Contrato, liberaciones y auditoría comparten una transacción; cualquier fallo revierte todos esos cambios.
- Los bloqueos siguen al RUT. Se protegen las copias contractuales de solicitudes, cometidos e incumplimientos antes de actualizar el contrato.
- La operación comparte el control transaccional de la carga masiva. Una falla revierte la escritura. Si cambian los contratos del RUT o el período desde la consulta, se solicita consultar nuevamente; reenviar un formulario no duplica registros.
- Una modificación contractual puede invalidar una revisión de carga masiva basada en datos anteriores. No altera las decisiones guardadas ni aplica cargas pendientes.

## Despliegue

Se requiere la migración aditiva `2026_09_14_140000_create_padron_individual_cambios.php`. No modifica las tablas contractuales ni borra historial. No ejecutar rollback destructivo.

Después de actualizar el código, desde `/home/slepac/apps/slep_postulaciones`, y con respaldo y el procedimiento habitual de despliegue:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --path=database/migrations/2026_09_14_140000_create_padron_individual_cambios.php --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
```

No se necesita volver a cargar el Excel ni aplicar nuevamente una revisión ya aplicada.
