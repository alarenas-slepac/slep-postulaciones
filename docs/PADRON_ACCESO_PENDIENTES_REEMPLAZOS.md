# Padrón: acceso a correspondencias y nuevas líneas de reemplazo

Parche 2026.9.11.515. No habilita la aplicación definitiva ni cambia producción.

## Acceso a las decisiones pendientes

- Los primeros diez mensajes de bloqueo que identifican una fila Excel o un ID
  contractual tienen un enlace «Ver registro y resolver».
- «Resolución de coincidencias» muestra las correspondencias pendientes en
  páginas de diez, incluyendo las ausencias. No depende de tener conflictos de
  Dotación y no carga todos los formularios al abrir la revisión.
- El acceso directo valida que la fila pertenezca a la revisión. Carga las filas
  del mismo RUT, sin filtros de acción heredados, y localiza la página del registro.
  Una fila sin RUT se abre sola. Usa el fragmento ligero existente y funciona
  también como enlace normal sin JavaScript.
- Al guardar se recalculan los pendientes; los demás registros del RUT siguen
  disponibles. No se cambian contratos al navegar ni registrar correspondencias.

## REEMPLAZO pendiente

La propuesta efectiva trata como nueva incorporación una fila del Excel con
acción original `revision_manual`, sin ID asignado, sin decisión registrada y
tipo normalizado exactamente `REEMPLAZO`. El nuevo ID solo nace al aplicar.

La regla actúa sobre la propuesta en memoria, tanto para revisiones existentes
como nuevas. No reescribe el análisis original ni fabrica decisiones en nombre
del administrador. La pantalla distingue «REEMPLAZO: nueva línea automática».
No se copian referencias contractuales ni asignaciones al nuevo ID.

No cambia correspondencias inequívocas previamente identificadas ni decisiones
manuales ya guardadas, incluidas las conservaciones del contrato anterior.
No se extiende a SUPLENCIA ni a etiquetas desconocidas. Errores, duplicados y
reemplazos omitidos por fechas/jornadas conservan sus validaciones. Las ausencias
anteriores no se dan por resueltas y liberar sus asignaciones exige su confirmación.

Las exclusiones de reemplazos existentes en Dotación y selección de titulares
se conservan; este parche no revisa ni amplía exclusiones de otros módulos.
Tampoco autoriza jornadas superiores a 44 horas.

La huella de confirmación final cambia de versión por la nueva política efectiva;
no vence la revisión ni borra decisiones: solo invalida confirmaciones finales
calculadas con la política anterior. El ensayo de la copia real sigue pendiente.
