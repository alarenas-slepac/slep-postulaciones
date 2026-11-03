# Nóminas de Cometidos por autorizar o gestionar

En **Trámites → Cometidos funcionarios**, las pestañas **AC · Solicitudes por autorizar o gestionar** y **Establecimientos · Solicitudes por autorizar o gestionar** disponen de **Descargar nómina Excel**.

Primero aplicar los filtros con **Buscar**. La descarga XLSX contiene todas las solicitudes coincidentes de la pestaña activa, incluidas las que están en otras páginas. Respeta estado, nombre, RUT, establecimiento y mes, además de las reglas existentes de gestión del rol activo. La exportación se atiende desde la misma ruta del listado con `tab=ac_por_autorizar` o `tab=estab_por_autorizar` y `exportar=excel`; no amplía permisos ni permite descargar otras bandejas mediante este parámetro. Los registros históricos con origen vacío permanecen en Establecimientos.

**Mes de solicitud** usa `fecha_solicitud`, con mes y año (`AAAA-MM`), independientemente de las fechas del viaje. Vacío incluye todos los meses. El filtro también está disponible en las solicitudes registradas de funcionarios, conservando su alcance, y Seguimiento comparte la validación de meses válidos. La paginación conserva los filtros y **Limpiar pestaña** los retira.

El Excel informa ID y número de cometido, fecha de solicitud, funcionario, RUT, estamento, cargo, origen, establecimiento/dependencia, RBD, destino, fechas del viaje, beneficios y estados de gestión general, viático, reembolso y pasaje aéreo. Los textos se escriben como texto explícito y las fechas como fechas nativas de Excel. Incluye filtros de columna, encabezados inmovilizados y total; si no hay coincidencias, informa cero solicitudes. No incorpora archivos adjuntos ni datos bancarios.

Los roles solicitantes `funcionario_estab` y `funcionario_ac` mantienen su listado y no reciben esta nueva exportación de gestión. Los roles internos sólo exportan la bandeja que ya les corresponde. Seguimiento conserva sus descargas existentes. No se modifican solicitudes, notificaciones o datos históricos y no se requieren migraciones.

Pruebas: `tests/Feature/CometidosNominaGestionTest.php`.
