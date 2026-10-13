# Vacantes por no renovación de contrata

## Operación

1. Ingresar a Dotación establecimiento y elegir el establecimiento y año 2027 o posterior.
2. En Resumen, desplazarse al panel **Docentes por contratar**. Se muestran Plan general, Educación Parvularia y PIE especializado, aun cuando no exista brecha de contratación.
3. En el bloque correspondiente, seleccionar un docente en **Reemplazar contrato a contrata**. La lista permite buscar por nombre, RUT y título.
4. Revisar el título y las horas que se copiarán. Se reemplaza el contrato completo, sin aumentar sus horas. Si había asignaciones en varios bloques, se crean cupos vacíos en esos bloques según el contrato consolidado asignado; el saldo sin asignar permanece en el bloque principal. La suma de los cupos coincide con el contrato reemplazado.
5. Escribir la **Justificación de no renovación** (10 a 3000 caracteres), presionar **Reemplazar por Docente por contratar** y confirmar.
6. Revisar el cupo creado y el historial. El docente sale de esa dotación; el cupo conserva su título, función y horas, pero no recibe asignaciones ni asociaciones a asignaturas. Las reservas del docente también quedan inactivas.
7. Asociar expresamente el cupo a las asignaturas del plan cuando corresponda y luego distribuir las horas. Continúan aplicándose los controles de perfil, prelación, saldo y máximo autorizado.

## Alcance y permisos

La acción mantiene los mismos roles autorizados para habilitar contrataciones: `admin`, `coordinador_uatp`, `coordinador_gdp` y `supervisor_plani`. El rol de establecimiento conserva acceso de consulta al panel.

Sólo admite docentes íntegramente a contrata. No admite contratos mixtos, titulares, cupos virtuales ni contratos protegidos por fuero maternal u horas gremiales. La lactancia con fuero maternal expresamente registrado también conserva la protección existente. No permite reemplazar parcialmente el contrato.

El retiro afecta únicamente el establecimiento y año seleccionados. No elimina ni cambia el padrón de personal, los documentos, las declaraciones ni otras anualidades.

## Persistencia y concurrencia

- `dotacion_no_renovaciones` registra docente, título, contrato, justificación, actor, fechas y una copia de los atributos anteriores de sus asignaciones activas.
- Las filas anteriores permanecen identificadas con `estado = no_renovada`; dejan de cubrir necesidades o consumir saldo disponible del año. Las asociaciones anteriores se conservan, pero el docente retirado deja de ser seleccionable. No se crean asociaciones para el cupo.
- El cupo en `dotacion_contrata_habilitaciones` se vincula al registro y almacena título y función. Los cupos históricos sin estos atributos siguen utilizando sus perfiles originales.
- La operación completa utiliza una transacción y toma primero el bloqueo del establecimiento. Las escrituras de asignación utilizan el mismo bloqueo; además verifican nuevamente el retiro dentro de la transacción.
- Una clave única identifica cada no renovación activa por establecimiento, año y RUT. Una solicitud repetida no crea dos cupos. SQLite comprueba la clave y la validación de solicitudes obsoletas; la contención real de `FOR UPDATE` requiere MySQL.
- Si falla cualquier escritura, se revierten el retiro, el cupo y el archivo de asignaciones.

## Anulación

**Anular reemplazo** sólo se permite si el cupo no tiene asignaciones activas. Conserva la justificación y marca la anulación con usuario y fecha. El docente vuelve a la nómina del año y las asignaciones anteriores permanecen inactivas: deben revisarse y registrarse expresamente para evitar duplicar cobertura ya reasignada.

## Instalación y validación

Aplicar la migración aditiva `2026_10_13_200000_add_dotacion_no_renovaciones.php` mediante el procedimiento habitual del proyecto, usando PHP 8.3. Esta tarea no ejecuta migraciones en bases reales. Su reversión no borra el historial ni los atributos de los cupos.

Pruebas específicas: `tests/Feature/DotacionNoRenovacionTest.php`, sobre SQLite en memoria y discos simulados; complementar con las regresiones de contratación, cupos virtuales, selección de subsectores y asignación de horas. Revisar visualmente el panel y el selector en escritorio y móvil al instalar.

### Resultados locales

- PHP 8.3.30: 112 pruebas aprobadas, con 688 aserciones, incluyendo contratación, cupos virtuales, subsectores, eliminación por curso, reservas, vista, prelación y proceso 2027.
- Tras los últimos ajustes de mensajes y lectura de esquema, se repitieron las 22 pruebas de no renovación, contratación y asignación del cupo al plan: 203 aserciones aprobadas.
- `php -l` sobre los 16 archivos PHP/Blade nuevos o modificados: sin errores. `git diff --check`: sin observaciones.
- `php artisan route:list --path=admin/dotacion-establecimiento`: 23 rutas. `php artisan optimize:clear`: completado con caché de pruebas en memoria.
- Configuración de pruebas previa al bootstrap: `APP_CONFIG_CACHE` apunta a una ruta temporal inexistente; SQLite `:memory:`, `DB_URL=null`, caché/sesión/correo `array` y discos simulados. No se utilizó la base del entorno real.
- Revisión en navegador de una vista con datos sintéticos: escritorio y móvil de 390 px, selección por teclado, búsqueda por título, detalle de contrato y desplegable sin recorte ni desplazamiento horizontal.
- No se modificaron assets compilados; el estilo y código nuevos se incluyen en Blade. No corresponde reconstruir Vite para este parche.

Pendientes al instalar: aplicar la migración y comprobar contención simultánea en MySQL, cuyo bloqueo de filas no se reproduce con SQLite. Los cambios permanecen en la rama `feat/dotacion-vacantes-no-renovacion`.
