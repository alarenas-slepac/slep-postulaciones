# PDF de docentes por asignatura

En **Dotación Establecimiento → Docentes → Listado docente**, usar **PDF por asignatura**. Descarga el establecimiento y año seleccionados sin aplicar los filtros de revisión de pantalla. Reutiliza los permisos de las otras exportaciones, el alcance del establecimiento del rol activo directivo y la disponibilidad de lectura con edición bloqueada.

El documento se divide primero por bloque de Dotación y después por asignatura, siguiendo la agrupación curricular de `DotacionAsignaturaResumenCalculator` (incluye las horas de libre disposición asociadas a la misma asignatura). Cada sección tiene docentes en la prelación del SGA, con sus desempates por antigüedad y nombre, y suma las **horas aula semanales efectivamente asignadas** en todos sus cursos del mismo bloque. Incluye RUT, nombre, título, grupo, tramo, antigüedad, horas individuales y totales por asignatura y bloque. No convierte las horas aula a contrato.

NT1/NT2 pertenece a **Educación Parvularia**, y los demás cursos a **Plan de estudio**. La libre disposición NT impartida por otro docente se imputa a Plan de estudio usando la regla compartida del proceso, mientras la impartida por una educadora de párvulos conserva Parvularia. Una misma asignatura o persona puede aparecer en ambos bloques, con sus horas separadas. **PIE especializado** conserva su ausencia de asignaturas del plan: las funciones y apoyos PIE no se inventan como asignaturas ni se agregan al informe. Se imprimen sólo bloques con asignaturas.

Sólo toma las asignaciones activas de las necesidades del plan vigente, de la misma fuente usada en Dotación. Excluye cupos virtuales, plazas automáticas, asistentes, reservas, acompañamientos, asignaciones inactivas y fantasmas. Una asignación persistida se cuenta una sola vez por ID. Las asociaciones a asignaturas sin horas no se presentan como asignaciones. Las asignaturas sin docentes asignados se muestran con una nómina vacía y total cero.

Las asignaciones sin antecedentes en la nómina anual conservan el nombre y RUT registrados y sus horas; aparecen al final con **Sin antecedentes en nómina anual**, sin inventar tramo, antigüedad o grupo contractual.

El formato es **México Oficio vertical, 216 × 340 mm**. RUT y nombre se agrupan en una columna; tramo y antigüedad acompañan al grupo de prelación, manteniendo todos los antecedentes legibles. Cada asignatura comienza una sección, con el bloque y las columnas repetidos al continuar la tabla, cabecera institucional y número de página. Los textos se escapan y no se cargan recursos remotos. No escribe en los datos ni requiere migraciones.

Ruta: `admin.dotacion-establecimiento.docentes.asignaturas.pdf`. Pruebas: `tests/Feature/DotacionNominaAsignaturasPdfTest.php`.
