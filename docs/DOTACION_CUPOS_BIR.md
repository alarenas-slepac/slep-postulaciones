# Cupos por contratar desde Proceso BIR

En Dotación establecimiento, seleccione **2027** y abra **Docentes por contratar**. Presione **Crear cupos desde Proceso BIR 2026**, filtre por bloque o título y marque los cupos. En **Horas a crear** mantenga el máximo precargado o ingrese una cantidad menor; después presione **Crear docentes por contratar seleccionados**. Esta acción utiliza los mismos permisos de creación de cupos: administrador, coordinador UATP, coordinador GDP y supervisor de Planificación.

## Origen y horas

- Para un año destino se revisan exclusivamente las situaciones Proceso BIR del año anterior, del mismo establecimiento.
- El docente debe estar marcado como **no continúa**, conservar horas necesarias y tener contrato docente del año de origen. Si aparece en la nómina efectiva del destino, se impide generar una segunda representación de su contrato.
- La cantidad `horas_traspaso_bir`, limitada al contrato vigente de origen, define el máximo. Cero no genera cupos. Un valor histórico nulo mantiene el contrato completo como máximo, conforme a la regla BIR existente. Se requiere revisar cantidades superiores a 44 horas.
- Puede elegir desde 0,01 h hasta el máximo de cada bloque, con hasta dos decimales. Por ejemplo, con 34 h disponibles puede crear un cupo de 14 h: sólo las 14 h elegidas suman al contrato del año destino. Las otras 20 h no se crean como otro cupo ni se altera la situación BIR. Desmarque una fila si no quiere generar ese cupo.
- El título y la función se copian del docente de origen. La distribución utiliza `DotacionContratoVigentePorBloque`: Plan general, Educación Parvularia y PIE especializado. En contratos mixtos puede haber una fila por bloque; la suma de sus máximos coincide con las horas conservadas. Educación diferencial en escuela especial pertenece a Plan general.

La creación conserva íntegros el padrón, la situación BIR y las asignaciones del año anterior. Los cupos se incorporan como identidades `VACANTE-<id>` al contrato del año destino y bloque, incluso con sobredotación. No se trasladan asignaciones ni asociaciones a asignaturas: se configuran posteriormente mediante las acciones habituales del módulo.

En la pestaña **Docentes**, la nómina incluye los cupos por contratar del establecimiento y año junto con las personas vigentes. Muestra su título, bloque contractual, horas, asignaciones, reservas y saldo. El filtro por bloque respeta el bloque explícito del cupo, aunque conserve el título de otro ámbito. Los totales de la nómina incluyen cada cupo una sola vez; no se agrega nuevamente su contrato a los indicadores que ya lo consideran. Las identidades por contratar no ofrecen formularios de situaciones docentes ni justificaciones personales. Esta presentación se aplica también a cupos genéricos y de no renovación.

## Cambios y duplicados

El formulario envía identificadores, horas elegidas y huellas de revisión; los máximos, títulos, bloques y condiciones se obtienen nuevamente del servidor al guardar. Una revisión obsoleta, una cantidad inválida o una cantidad superior al máximo de su bloque se rechaza completa. No se pueden trasladar límites de horas de un bloque a otro.

La transacción bloquea establecimiento y situaciones de origen. Dos índices únicos protegen origen/año/bloque y establecimiento/año/persona/bloque. Reenviar una selección ya creada con las mismas horas no duplica contrato; reenviarla con otra cantidad no modifica el cupo existente. Un cupo menor al máximo es válido. Eliminar y recrear una situación tampoco habilita otro cupo para la misma persona. Los cupos ajenos a BIR conservan el origen nulo y su funcionamiento previo.

Si cambian el título, la distribución o el máximo baja por debajo de las horas del cupo después de generar, se requiere revisar los cupos existentes. No se actualizan ni eliminan silenciosamente. Para elegir otra cantidad, retire primero el cupo vacío mediante la acción habitual y vuelva a generar. Si tiene asignaciones, se mantiene la protección de retiro existente.

## Instalación y validación

Se agrega la migración `2026_10_27_150000_add_origen_bir_to_dotacion_contrata_habilitaciones.php`, que incorpora dos campos de origen opcionales e índices únicos. No reescribe registros históricos y su reversión no elimina trazabilidad. También deben estar instaladas las migraciones anteriores de situaciones docentes y títulos de cupos. Sin ellas, la nueva vista informa preparación pendiente y no permite generar.

Las rutas GET y POST `admin.dotacion-establecimiento.bir.*` se registran en `routes/dotacion_bir.php`. Utilizan autenticación, verificación, acceso al módulo, rol activo y CSRF; el controlador existente mantiene la coordinación de escrituras del padrón.

Las pruebas `DotacionCuposBirTest` heredan un arranque con SQLite en memoria, almacenamiento simulado y configuración cacheada anulada antes de cargar Laravel. No requieren una base real ni archivos de usuarios. Cubren distribución, conservación histórica, límites del origen, duplicados, cambios posteriores, índice único, transacción, filtros y roles.
