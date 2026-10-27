# Cupos por contratar desde Proceso BIR

En Dotación establecimiento, seleccione **2027** y abra **Docentes por contratar**. Presione **Crear cupos desde Proceso BIR 2026**, filtre por bloque o título, marque los cupos y presione **Crear docentes por contratar seleccionados**. Esta acción utiliza los mismos permisos de creación de cupos: administrador, coordinador UATP, coordinador GDP y supervisor de Planificación.

## Origen y horas

- Para un año destino se revisan exclusivamente las situaciones Proceso BIR del año anterior, del mismo establecimiento.
- El docente debe estar marcado como **no continúa**, conservar horas necesarias y tener contrato docente del año de origen. Si aparece en la nómina efectiva del destino, se impide generar una segunda representación de su contrato.
- Se usa la cantidad `horas_traspaso_bir`, limitada al contrato vigente de origen. Cero no genera cupos. Un valor histórico nulo mantiene el contrato completo, conforme a la regla BIR existente. Se requiere revisar cantidades superiores a 44 horas.
- El título y la función se copian del docente de origen. La distribución utiliza `DotacionContratoVigentePorBloque`: Plan general, Educación Parvularia y PIE especializado. En contratos mixtos puede haber una fila por bloque; su suma coincide con las horas conservadas. Educación diferencial en escuela especial pertenece a Plan general.

La creación conserva íntegros el padrón, la situación BIR y las asignaciones del año anterior. Los cupos se incorporan como identidades `VACANTE-<id>` al contrato del año destino y bloque, incluso con sobredotación. No se trasladan asignaciones ni asociaciones a asignaturas: se configuran posteriormente mediante las acciones habituales del módulo.

## Cambios y duplicados

El formulario envía identificadores y huellas de revisión; las horas, títulos, bloques y condiciones se obtienen nuevamente del servidor al guardar. Una revisión obsoleta se rechaza completa.

La transacción bloquea establecimiento y situaciones de origen. Dos índices únicos protegen origen/año/bloque y establecimiento/año/persona/bloque. Reenviar una selección ya creada no duplica contrato; eliminar y recrear una situación tampoco habilita otro cupo para la misma persona. Los cupos ajenos a BIR conservan el origen nulo y su funcionamiento previo.

Si cambian el título, las horas o la distribución después de generar, se requiere revisar los cupos existentes. No se actualizan ni eliminan silenciosamente. Un cupo vacío se puede retirar mediante la acción habitual y luego volver a generar. Si tiene asignaciones, se mantiene la protección de retiro existente.

## Instalación y validación

Se agrega la migración `2026_10_27_150000_add_origen_bir_to_dotacion_contrata_habilitaciones.php`, que incorpora dos campos de origen opcionales e índices únicos. No reescribe registros históricos y su reversión no elimina trazabilidad. También deben estar instaladas las migraciones anteriores de situaciones docentes y títulos de cupos. Sin ellas, la nueva vista informa preparación pendiente y no permite generar.

Las rutas GET y POST `admin.dotacion-establecimiento.bir.*` se registran en `routes/dotacion_bir.php`. Utilizan autenticación, verificación, acceso al módulo, rol activo y CSRF; el controlador existente mantiene la coordinación de escrituras del padrón.

Las pruebas `DotacionCuposBirTest` heredan un arranque con SQLite en memoria, almacenamiento simulado y configuración cacheada anulada antes de cargar Laravel. No requieren una base real ni archivos de usuarios. Cubren distribución, conservación histórica, límites del origen, duplicados, cambios posteriores, índice único, transacción, filtros y roles.
