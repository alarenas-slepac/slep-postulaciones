# Bloqueos de edición de Dotación

El bloqueo afecta exclusivamente al **rol activo** `funcionario_directivo_estab`. Un usuario con varios roles queda sujeto al bloqueo cuando trabaja como directivo; al cambiar a otro rol autorizado conserva los permisos propios de ese contexto.

## Administración

- Roles: `admin`, `supervisor_plani`, `coordinador_gdp` y `coordinador_uatp`.
- En Dotación Establecimiento, desplegar **Control de edición de Dotación** para activar o desactivar el bloqueo general.
- Al entrar a la Dotación de un establecimiento se muestra también su bloqueo individual.
- Ambos bloqueos comienzan desactivados. Son independientes del año seleccionado y alcanzan todos los procesos anuales.
- El general prevalece: retirar un bloqueo individual no habilita la edición mientras el general siga activo. Al retirar el general se mantienen los individuales existentes.
- El motivo es opcional, hasta 1.000 caracteres. Los cambios registran usuario, rol activo, fecha, estado anterior/nuevo y versión en auditoría. Un formulario de control desactualizado exige recargar; no sobrescribe un cambio posterior.

## Alcance

La política central `DotacionEdicionAcceso::MODULOS` cubre los nombres de rutas de Dotación Establecimiento, Dotación Funciones, planes por establecimiento, cursos y PIE. Incluye asignaciones, reservas, situaciones, docentes por asignatura, cursos combinados, funciones normativas/declaradas, configuraciones y justificaciones, según los permisos preexistentes. No concede permisos nuevos ni permite editar PIE a un rol que ya era de consulta.

El middleware bloquea POST, PUT, PATCH y DELETE. También bloquea las pantallas de creación/edición/importación y `configure` de planes: ese GET puede crear la configuración con `firstOrCreate`. Las consultas, filtros, detalles y descargas continúan disponibles. Las nuevas rutas de escritura bajo estos mismos prefijos quedan cubiertas automáticamente.

El establecimiento del directivo se obtiene de su usuario, nunca del año, filtro o ID enviado en el formulario. Un directivo sin establecimiento asociado no puede escribir. La protección se vuelve a consultar en cada petición: también bloquea envíos de pantallas abiertas antes de activar el control. La interfaz es complementaria; quitar JavaScript o reactivar un botón no evita la comprobación del servidor.

Se conserva la suspensión temporal histórica de asignaciones de 2027; los nuevos controles no levantan otras restricciones de acceso.

## Despliegue

Aplicar la migración aditiva `2026_11_02_140000_create_dotacion_edicion_bloqueos.php` con PHP 8.3 según el procedimiento habitual. Crea las tablas de configuración y auditoría, e inicializa únicamente el estado general desactivado. No modifica contratos, horas, años ni permisos existentes. Sin la migración, los controles informan que todavía no están disponibles.

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --path=database/migrations/2026_11_02_140000_create_dotacion_edicion_bloqueos.php --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
```

No ejecutar rollback destructivo sobre la auditoría de bloqueos.
