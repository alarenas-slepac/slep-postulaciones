# Informes de sobredotación

## Detalle completo en PDF

En **Dotación Establecimiento → Detalle sobredotación**, usar **Descargar detalle PDF** en el encabezado. Genera `detalle_sobredotacion_rbd_{RBD}_{año}.pdf`, tamaño **México Oficio (216 × 340 mm), horizontal**, con encabezado institucional y páginas numeradas.

Incluye el resumen general y su base estructural, la conciliación contractual y tres bloques: **Plan de estudio**, **Educación Parvularia** y **PIE**. Cada bloque contiene cuadratura contractual cuando aplica el proceso 2027, nómina de saldos en orden de prelación, antecedentes y contrato individual, totales por calidad, fundamentos completos con su estado, horas originalmente justificadas y fecha de actualización, justificaciones fuera de la nómina de saldos, contratos protegidos, sobreasignaciones y funciones declaradas revisables con su desglose. Las funciones declaradas se agrupan una sola vez por perfil docente y conservan las cifras de la base Aula de la pestaña.

Las justificaciones extensas continúan en las páginas siguientes sin recortarse; las tablas repiten sus encabezados. Se informa **Sin justificación** cuando corresponde y se distinguen fundamentos vigentes, desactualizados y sin saldo actual. Los contratos generales pueden repetirse si una persona aporta a varios bloques; no deben sumarse entre secciones.

Ruta: `admin.dotacion-establecimiento.sobredotacion.pdf`. Consulta los cálculos compartidos en modo lectura y conserva los mismos permisos y alcance que los otros informes docentes. Exporta el establecimiento y año completos, sin aplicar filtros de revisión de pantalla. La descarga es privada, no se almacena en una ruta pública y sigue disponible durante los bloqueos de edición. No requiere nuevas migraciones.

## Nómina compacta de saldos en PDF vertical

En **Detalle sobredotación → Horas contractuales vacantes → Contrato sin asignación registrada**, usar **Exportar sobredotación (PDF)** junto al botón Excel. Genera `nomina_sobredotacion_prelacion_rbd_{RBD}_{año}.pdf`, en **México Oficio (216 × 340 mm), vertical**, con páginas numeradas.

Usa exactamente `DotacionNominaDocentes::datosSobredotacion`, igual que el Excel, y conserva sus bloques, filas, orden de prelación, antecedentes y doce campos de horas. Cada docente aparece en una ficha compacta, con sus justificaciones titular y contrata completas y su estado. Los fundamentos extensos pueden continuar en las páginas siguientes. También conserva el texto anterior cuando una calidad ya no tiene saldo, indica **Sin justificación** cuando falta y presenta totales por bloque con el mismo criterio del Excel. Los valores no determinados se muestran como «-» y no se convierten en cero.

Ruta: `admin.dotacion-establecimiento.sobredotacion.nomina.pdf`. Exporta todos los saldos del establecimiento y año, sin limitarse a filtros de pantalla, y mantiene los permisos y la descarga durante bloqueos de edición. El botón **Descargar detalle PDF** del encabezado conserva el informe general horizontal. No requiere migraciones.

## Nómina de saldos en Excel

En **Dotación Establecimiento → Detalle sobredotación → Contrato sin asignación registrada**, usar **Exportar sobredotación (Excel)**. Exporta todos los saldos del establecimiento y año seleccionados; los filtros de revisión en pantalla no limitan la descarga.

El XLSX tiene una hoja por bloque: **Plan de estudio**, **Educación Parvularia** y **PIE**. Cada hoja ordena docentes por la prelación compartida del SGA, con antigüedad y nombre como desempates. Incluye RUT, nombre, título, grupo, tramo, antigüedad, contrato original y considerado, aula y contrato asignados, reservas y saldo general. Agrega el contrato y saldo del bloque, horas titulares sujetas a supresión y horas a contrata sujetas a no renovación, con/sin justificación vigente.

La fuente es `vacantes_por_bloque` de `DotacionSobredotacionCalculator`, igual que las nóminas de saldos de la vista. Se conservan la distribución entre bloques, el neteo de la fracción NT y las exclusiones de contratos protegidos. No incorpora cupos virtuales, asistentes, funciones declaradas ya asignadas ni docentes sin saldo. Las horas de Etapa 7 son propuestas en revisión, no bajas ejecutadas.

## Justificaciones

Hay columnas de texto y estado separadas para titular y contrata. Cada fundamento se consulta por establecimiento, año, RUT normalizado, bloque y calidad.

- Con horas y sin texto registrado: **Sin justificación**.
- Texto que coincide con las horas actuales: **Vigente**.
- Texto de otra cantidad: se conserva y se indica **Desactualizada**, con las horas para las que se registró. Las horas actuales siguen sin justificación vigente.
- Sin horas de esa calidad: **Sin saldo actual**, conservando el texto anterior cuando existe; de lo contrario, **No aplica** en la columna de justificación.

Los registros completamente fuera de la nómina de saldos permanecen consultables en su sección del SGA; no generan filas artificiales en el Excel. Si falta la tabla de justificaciones, se informa en Definiciones y los saldos se consideran sin justificación.

## Archivo y acceso

El Excel incluye filtros, paneles inmovilizados, fechas y horas numéricas, totales por hoja y una hoja **Definiciones**. Los contratos generales se repiten cuando una persona tiene saldos en varios bloques; no deben sumarse entre hojas. Los textos se escriben como cadenas explícitas, incluso cuando comienzan con un signo de fórmula. Un total queda vacío si alguna de sus filas no tiene un valor determinado.

Ruta: `admin.dotacion-establecimiento.sobredotacion.export`. Conserva los roles y el alcance de la nómina docente: el directivo sólo accede a su establecimiento bajo su rol activo. La descarga sigue disponible durante los bloqueos de edición. No modifica datos ni requiere migraciones nuevas.
