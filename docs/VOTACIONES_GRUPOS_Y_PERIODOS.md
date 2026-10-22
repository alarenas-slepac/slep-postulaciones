# Grupos y períodos de Votaciones CCAF y Mutualidades

## Configurar una jornada

1. Entre en **Votaciones CCAF y Mutualidades → Jornadas** y pulse **Nueva jornada**, o abra una jornada en borrador y pulse **Editar**.
2. Ingrese **Fecha de inicio** y **Fecha de término**, además de nombre, procesos y slug. Para una jornada de un solo día, use la misma fecha en ambos campos.
3. Pulse **Guardar jornada** y desplácese a **Gestión de grupos**.

Las fechas incluyen ambos días completos, según `America/Santiago`. Las jornadas históricas conservan `fecha` como inicio y término cuando no tienen `fecha_fin`. No se cambia el significado de la fecha de referencia usada por padrones y resultados diarios; esos procesos mantienen sus reglas propias.

## Crear o incorporar grupos

- **Nuevo:** pulse **Crear grupo**, complete número, nombre, encargado, integrantes y fechas del grupo; pulse **Crear grupo**. Queda asociado automáticamente a la jornada abierta.
- **Existente:** en **Asociar grupo existente**, busque el grupo y su jornada de origen. Indique un número libre en la jornada destino y sus fechas; pulse **Asociar a esta jornada**.

La incorporación copia el nombre, responsable, integrantes con su rol y la configuración de la ruta. El grupo de origen conserva sus datos. La nueva participación empieza pendiente, sin visitas, hitos, incidencias ni bitácoras del origen. Puede ajustar el equipo o la ruta de destino sin alterar otras jornadas. No se permite incorporar dos veces el mismo grupo de origen a la misma jornada ni repetir números dentro de ella; un número puede repetirse en jornadas distintas.

El campo **GRUPO** del Excel de consulta por RUT sigue siendo una etiqueta del padrón: no crea equipos operativos, no asigna responsables ni rutas. La configuración descrita aquí corresponde al seguimiento territorial.

## Fechas y operación

En **Editar equipo** se pueden ajustar las fechas mientras la jornada está en borrador. El período del grupo debe estar contenido en el de la jornada. Para reducir el período de una jornada, ajuste primero los grupos que quedarían fuera. Los grupos históricos con fechas nulas heredan el período de su jornada.

Los períodos aparecen en Jornadas, su detalle, el centro de control y Operación. Fuera del rango se conserva la consulta; no se permite iniciar el grupo, iniciar una votación ni finalizar una visita. El servidor comprueba tanto el día actual de Chile como la fecha efectiva que se intenta registrar, por lo que enviar un horario anterior no elude el cierre. El horario futuro y la secuencia de visitas conservan sus validaciones.

No se inicia o reinicia un recorrido cada día automáticamente. Cada participación mantiene un único recorrido y su avance durante el período definido. Las correcciones administrativas existentes siguen siendo acciones explícitas y auditadas.

Se conservan los permisos `votaciones.manage-jornadas`, `votaciones.manage-grupos` y `votaciones.operate-group`, además del acceso al módulo y la pertenencia al equipo. La incorporación utiliza los mismos permisos y la misma ruta de creación de grupos. No introduce correos ni WhatsApp.

## Instalación y validaciones

El parche agrega `jornadas_votacion.fecha_fin`, `grupos_votacion.fecha_inicio`, `fecha_fin` y `grupo_origen_id`, con referencia opcional al origen e índice único por jornada/origen. No cambia ni elimina los registros existentes. Requiere aplicar `2026_10_22_200000_add_periodos_to_votaciones.php` con el procedimiento habitual de despliegue y PHP 8.3, después de revisar las migraciones pendientes y disponer de respaldo. No hay variables `.env`, rutas ni recursos compilados nuevos.

Las pruebas usan SQLite en memoria, almacenamiento simulado, reloj controlado y datos sintéticos. Cubren creación, asociación, conservación de origen, duplicados, permisos, validación de períodos, límites inclusivos, zona horaria y reversión de la asociación si falla la bitácora. La contención de escrituras se apoya en el bloqueo de la jornada y restricciones únicas de la base de datos; las pruebas SQLite no sustituyen una prueba de concurrencia con MySQL.
