# Padrón completo: actualización segura

## Etapa 1 implementada: previsualización

La ruta existente de carga masiva, restringida a `admin`, ahora recibe la acción
`previsualizar`. Exige confirmar que el archivo contiene el padrón completo,
valida la primera hoja y persiste una revisión separada del personal vigente.
El importador anterior se conserva como método privado, sin acceso por rutas.
El trabajo local de aplicación definitiva se conserva, pero está bloqueado en
`PadronAplicacionService` tanto para llamadas directas como para POST. La existencia
de las tablas de aplicación no habilita escrituras. No cambiar este bloqueo hasta
completar las validaciones de compatibilidad histórica descritas más abajo.

- Campo nullable `reemplazos_personal.fecha_antiguedad`. Es opcional en Excel;
  si falta o está vacío, la propuesta conserva el valor existente.
- Coincidencias inequívocas por RUT y composición contractual conservan el ID
  como referencia. No se ejecutan inserciones, actualizaciones ni bajas de personal.
- La base de comparación usa el último período disponible por establecimiento
  hasta el período del archivo; busca candidatos históricos para reincorporaciones.
  Una carga anterior al período máximo de la base queda bloqueada para autorización.
- Cambios ambiguos muestran candidatos sin seleccionar un ID automáticamente.
- Asignaciones activas del año se informan por RUT o ID; no se trasladan ni eliminan.
- Filas inválidas, duplicadas, RBD desconocidos o períodos mezclados se muestran;
  cualquier error impide proponer bajas por ausencia como si el archivo fuera válido.
- Jornada total considera todos los registros del RUT entre RBD y financiamientos
  cuando tiene al menos un contrato docente. Básica/Media no se suman nuevamente.
  Una persona exclusivamente asistente no queda sujeta a este control docente.
- Sobre 44 horas se permite autorización con justificación de 10–2.000 caracteres,
  usuario, fecha, revisión y jornada. No se sobrescribe una autorización existente.
  La autorización no implica aplicar el registro al padrón.
- Una huella de la base y dependencias detecta revisiones desactualizadas.
- Declaración de Sostenedores mantiene prioridad. Los contratos desconocidos se
  marcan para revisión.
- Dotación y el selector de titulares de Solicitudes de Reemplazo usan alcances
  explícitos de vigencia y excluyen contratos de reemplazo/suplencia, tanto de
  docentes como de asistentes. El POST de creación también valida esta condición.
  La edición que conserva el mismo titular y las relaciones históricas no reciben
  un filtro global. No se borran contratos, documentos ni asignaciones.
- Las autorizaciones vuelven a comprobar el estado persistido bajo bloqueo de la
  revisión; una instancia antigua no permite autorizar una revisión ya cerrada.

## Instalación

Ejecutar las migraciones pendientes con PHP 8.3 antes de usar la previsualización.
La primera migración añade una columna y tres tablas de revisión. La segunda
añade las tablas y marcas reservadas para decisiones, auditoría y control de
aplicación. Ninguna borra ni reconstruye personal existente ni habilita el botón
de aplicación. Su reversión está bloqueada para evitar pérdida de auditoría;
requiere una migración específica autorizada. No usar `migrate:fresh` ni `db:wipe`.

No se han ejecutado estas migraciones sobre datos del entorno de trabajo o producción.
Las pruebas utilizan exclusivamente SQLite en memoria y datos sintéticos.

## Etapas pendientes antes de habilitar aplicación definitiva

1. Completar y validar el trabajo de resolución explícita de candidatos ambiguos y
   conflictos de asignaciones. La resolución manual permanece bloqueada.
2. Validar la aplicación transaccional con revalidación y bloqueo de concurrencia: conservar
   IDs existentes, insertar solo nuevas líneas inequívocas y desactivar ausencias
   confirmadas sin borrar historial. No mover asignaciones entre RBD automáticamente.
3. Auditoría de valores anteriores/nuevos, aplicación idempotente por carga y
   protección frente a cambios posteriores al análisis o la autorización.
4. Adaptar consumidores de vigencia sin perder referencias históricas de trámites,
   solicitudes, licencias y asignaciones existentes.
5. Validar cómo los lectores de documentos históricos recuperarán los valores
   previos cuando se actualice una fila con el mismo ID. Mantener la FK y guardar
   una auditoría no basta si esos lectores siguen usando los valores actuales.
6. Verificar prioridad de Declaración de Sostenedores y regresiones en cálculos,
   exportadores y módulos dependientes antes de habilitar el botón de aplicación.

El trabajo de aplicación debe revisar también la cobertura con la prioridad de
Declaración de Sostenedores, los estamentos mixtos, las ausencias y los cambios de
año, sin desactivar versiones históricas indiscriminadamente. No se presenta como
funcionalidad terminada ni lista para producción.

Pruebas específicas: `php vendor/phpunit/phpunit/phpunit --filter Padron`.
Regresión relacionada: `php vendor/phpunit/phpunit/phpunit --filter "Padron|Dotacion|SolicitudReemplazo"`.
Incluye bloqueo de aplicación con ambas migraciones instaladas, POST directo,
previsualización sin cambios de personal/asignaciones, prioridad de declaración,
selector de titulares y lectura de relación/distribución histórica.
