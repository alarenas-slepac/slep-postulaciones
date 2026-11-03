# Bloqueos de edición de Dotación

El bloqueo afecta exclusivamente al **rol activo** `funcionario_directivo_estab`. Un usuario con varios roles queda sujeto al bloqueo cuando trabaja como directivo; al cambiar a otro rol autorizado conserva los permisos propios de ese contexto.

## Administración

- Roles: `admin`, `supervisor_plani`, `coordinador_gdp` y `coordinador_uatp`.
- En Dotación Establecimiento, desplegar **Control de edición de Dotación** para activar o desactivar el bloqueo general.
- Al entrar a la Dotación de un establecimiento se muestra también su bloqueo individual.
- Ambos bloqueos comienzan desactivados. Son independientes del año seleccionado y alcanzan todos los procesos anuales.
- El general mantiene bloqueados todos los establecimientos, salvo aquellos con un **desbloqueo temporal autorizado y vigente**. Un estado individual histórico desactivado no constituye una autorización ni exceptúa del general.
- En el establecimiento bloqueado, completar **Motivo de la autorización** y **Fecha y hora del próximo bloqueo**, y usar **Autorizar desbloqueo temporal**. Sólo habilita ese establecimiento y todos sus años/módulos de Dotación; no habilita otros establecimientos ni concede permisos nuevos.
- El motivo es obligatorio para autorizar o renovar y opcional para bloquear, hasta 1.000 caracteres. La fecha y hora deben ser futuras, en `app.display_timezone` (por defecto `America/Santiago`), visible junto al calendario institucional; sin JavaScript se ingresa `AAAA-MM-DD HH:MM`. El plazo se convierte a `app.timezone` para almacenarlo.
- Una autorización vigente permite **Renovar autorización** o **Revocar autorización y bloquear**. Al vencer, el establecimiento vuelve a quedar bloqueado, incluso si entretanto se desactivó el bloqueo general. Los restantes establecimientos conservan su propio estado.
- Los cambios registran usuario, rol activo, fecha, estado anterior/nuevo, plazo anterior/nuevo y versión en auditoría, dentro de la misma transacción. Un formulario de control desactualizado exige recargar; no sobrescribe una renovación o revocación posterior.

## Alcance

La política central `DotacionEdicionAcceso::MODULOS` cubre los nombres de rutas de Dotación Establecimiento, Dotación Funciones, planes por establecimiento, cursos y PIE. Incluye asignaciones, reservas, situaciones, docentes por asignatura, cursos combinados, funciones normativas/declaradas, configuraciones y justificaciones, según los permisos preexistentes. No concede permisos nuevos ni permite editar PIE a un rol que ya era de consulta.

El middleware bloquea POST, PUT, PATCH y DELETE. También bloquea las pantallas de creación/edición/importación y `configure` de planes: ese GET puede crear la configuración con `firstOrCreate`. Las consultas, filtros, detalles y descargas continúan disponibles. Las nuevas rutas de escritura bajo estos mismos prefijos quedan cubiertas automáticamente.

El establecimiento del directivo se obtiene de su usuario, nunca del año, filtro o ID enviado en el formulario. Un directivo sin establecimiento asociado no puede escribir. La protección se vuelve a consultar en cada petición: también bloquea envíos de pantallas abiertas antes de activar el control. La interfaz es complementaria; quitar JavaScript o reactivar un botón no evita la comprobación del servidor.

Se conserva la suspensión temporal histórica de asignaciones de 2027; los nuevos controles no levantan otras restricciones de acceso.

## Vencimiento automático

El plazo es exclusivo: se permite editar mientras la hora actual sea anterior al vencimiento. Desde ese instante, el middleware y los permisos de las vistas vuelven al modo sólo consulta. No requiere cron, workers ni escrituras al consultar; el historial conserva la autorización y su plazo. La pantalla abierta muestra el aviso de vencimiento y deshabilita la escritura como complemento de la comprobación del servidor.

## Despliegue

Aplicar la migración aditiva `2026_11_02_140000_create_dotacion_edicion_bloqueos.php` con PHP 8.3 según el procedimiento habitual. Crea las tablas de configuración y auditoría, e inicializa únicamente el estado general desactivado. No modifica contratos, horas, años ni permisos existentes. Sin la migración, los controles informan que todavía no están disponibles.

Para los desbloqueos temporales, aplicar también `2026_11_03_210000_add_desbloqueo_temporal_to_dotacion_edicion_bloqueos.php`. Añade un plazo nullable al estado y los plazos anterior/nuevo a la auditoría, conservando los registros existentes. Hasta instalarla, los bloqueos previos siguen funcionando y no se pueden autorizar reaperturas temporales. Compilar y desplegar los recursos Vite actualizados.

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --path=database/migrations/2026_11_02_140000_create_dotacion_edicion_bloqueos.php --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --path=database/migrations/2026_11_03_210000_add_desbloqueo_temporal_to_dotacion_edicion_bloqueos.php --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
```

No ejecutar rollback destructivo sobre la auditoría de bloqueos.
