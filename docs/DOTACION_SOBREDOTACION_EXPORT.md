# Nómina de sobredotación en Excel

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
