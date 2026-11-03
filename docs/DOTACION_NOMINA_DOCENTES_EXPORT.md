# Nómina docente por bloques en Excel y PDF

En **Dotación Establecimiento → Docentes → Listado docente**, usar **Exportar docentes (Excel)** o **Exportar docentes (PDF)**. Ambos descargan la nómina completa del establecimiento y año seleccionados, independientemente de los filtros de revisión en pantalla.

Acceden los mismos roles de la vista: `admin`, `funcionario_directivo_estab`, `coordinador_uatp`, `coordinador_gdp` y `supervisor_plani`. Se evalúa el rol activo. El directivo sólo puede descargar su establecimiento; tener otro rol asignado no amplía el alcance mientras actúe como directivo. Las salas cuna conservan su exclusión del proceso. La descarga sigue disponible durante los bloqueos de edición.

## Datos y orden

El archivo incluye RUT, nombre completo, título, grupo de prelación, tramo, fecha de antigüedad, contrato original, contrato considerado en el proceso, horas aula asignadas, contrato asignado, reservas, saldo sin asignar, horas de Etapa 7 con/sin justificación vigente y el detalle de los respaldos.

Se reutiliza `DotacionProceso2027Calculator::docentesPriorizados`: protección por fuero maternal/horas gremiales, No Regido Ley 20903, titulares de tramos preferentes, otros titulares y contrata; después antigüedad y nombre. Las fechas sin informar quedan al final de su grupo. Este orden de presentación no modifica los cálculos históricos del año seleccionado.

La fuente es la misma de la pestaña Docentes, usando `DotacionEstablecimientoCalculator` y el proceso 2027 cuando corresponde. Los cupos virtuales por contratar y las coberturas de asistentes no son personas docentes y no se incluyen. Los retiros que ya dejaron de formar parte de esa nómina tampoco se reincorporan.

## Separación en bloques

Se presentan **Plan de estudio**, **Educación Parvularia** y **PIE**, como en el detalle de Sobredotación. La agrupación utiliza el aporte contractual PIE y la clasificación contractual de Parvularia del SGA; el remanente corresponde al Plan de estudio. En establecimientos especiales se conserva el tratamiento de PIE como parte del bloque general.

La nómina sigue incluyendo docentes sin saldo, protegidos, sobreasignados y con contrato cero o desconocido. Estos últimos conservan su bloque según el perfil del SGA. Una persona con aporte a varios bloques aparece en cada uno de ellos y conserva su prelación. Las horas generales del docente se repiten en esas filas: **no sumar los contratos, asignaciones o justificaciones entre hojas o secciones**. Esta exportación no se limita a las personas con saldo de Sobredotación.

## Horas

- **Contrato original:** base del padrón utilizado para el proceso. El encabezado identifica el mes/año real de origen; puede ser anterior al año consultado.
- **Contrato considerado:** aporte después de aplicar las situaciones docentes de ese año. Proceso BIR conserva su tratamiento vigente.
- **Contrato asignado:** cifra consolidada de la pestaña, incluido el redondeo NT y sin reservas. No se vuelven a convertir ni redondear asignaturas por separado.
- **Reservas:** ya consumen contrato y se descuentan una sola vez del saldo.
- **Saldo pendiente:** el mismo saldo de la pestaña, después del redondeo NT. Un negativo identifica sobreasignación. Los valores no determinados quedan vacíos y no generan totales parciales presentados como completos.

Las columnas de horas suprimidas/no renovadas corresponden a las **horas en revisión de Etapa 7**, según lo solicitado; no acreditan bajas ejecutadas. Se reutilizan los saldos de `vacantes_por_bloque` de Sobredotación, conservando la exclusión de contratos protegidos.

Una justificación cuenta sólo si tiene texto y coincide con la cantidad de horas actual para el establecimiento, año, RUT, bloque y tipo. Si las horas cambiaron, la fila completa queda sin justificación vigente; no se acredita una cobertura parcial con el respaldo anterior. Se suman Plan general, Educación Parvularia y PIE, distinguiendo titular/contrata en el detalle. Si la tabla de justificaciones no está instalada, la hoja de definiciones informa esa condición y las horas quedan pendientes.

## Archivo y validación

El XLSX contiene **Plan de estudio**, **Educación Parvularia**, **PIE** y **Definiciones**, filtros, columnas inmovilizadas, fechas de Excel, horas numéricas, totales por hoja y configuración de impresión horizontal. Los textos se escriben como cadenas explícitas para impedir que nombres, títulos o justificaciones se interpreten como fórmulas.

El PDF usa **México Oficio de 216 x 340 mm en horizontal** (340 mm de ancho), tamaño personalizado en Dompdf. Cada bloque empieza en una página, con tabla ajustada al ancho disponible, cabeceras repetidas y numeración de páginas. Las filas pueden continuar en páginas adicionales. Los fundamentos se muestran completos después de la tabla de cada bloque, identificados por número, nombre y RUT; no ensanchan las columnas ni se truncan. Cierra con las definiciones compartidas con el Excel. La definición de papel corresponde a la [especificación México-Oficio de Epson](https://files.support.epson.com/docid/cpd5/cpd56917/source/specifications/references/xp4100_4105/spex_paper_spc_xp4100_4105.html).

Rutas de lectura: `admin.dotacion-establecimiento.docentes.export` y `admin.dotacion-establecimiento.docentes.pdf`, con `anio` como parámetro. Ambas conservan el rol activo y el alcance del establecimiento, también durante bloqueos de edición. No requieren migraciones nuevas ni escriben en Dotación. El PDF deshabilita recursos remotos y ejecución PHP. Pruebas específicas: `tests/Feature/DotacionNominaDocentesExportTest.php`.
