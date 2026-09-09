# Padrón: consumidores y bloqueo personal — 2026.9.9.496

## Regla confirmada por el usuario

El bloqueo pertenece al funcionario, no al establecimiento ni a una línea de
financiamiento. Sigue activo al cambiar RBD, período o ID contractual, hasta un
desbloqueo autorizado. Aplica también a asistentes de la educación bloqueados.

`PadronBloqueoService` compara RUT completos normalizados (puntos, guion, espacios
y mayúsculas). Conserva la búsqueda por FK para antecedentes sin RUT y resuelve
su identidad desde el contrato asociado. Un RUT vacío no coincide con otras
personas. No reconstruye una identidad si se perdieron tanto RUT como contrato.

- La nómina mensual, su contador y el selector de titulares usan la misma regla.
- La validación de envío/edición de solicitudes también la comprueba; no basta
  con evitar el selector. Ya no omite silenciosamente bloqueos de asistentes.
- Las filas originales no se copian ni se trasladan. Motivo, autor, FK y RBD
  permanecen como evidencia de origen, aunque el funcionario trabaje en otro RBD.
- Un bloqueo activo en otra línea evita crear un duplicado desde el nuevo contrato.
- Desbloquear desde cualquier contrato cierra todos los bloqueos activos del RUT,
  conservando registros, usuario y fecha de desbloqueo. La pantalla advierte este
  alcance. Se conservan los permisos anteriores para bloquear/desbloquear.
- La ruta POST de traspaso se conserva, pero ahora **verifica** presencia entre
  períodos sin escribir. Lee también el período archivado desde sus versiones;
  no usa el contrato actual como sustituto de la fila histórica.
- Las vistas archivadas siguen siendo de solo lectura y muestran el bloqueo
  **actual de la persona**, no una reconstrucción de su estado en aquel mes.

No hay migración, backfill, traslado de asignaciones ni modificación del JSON de
documentos. La relación contractual `bloqueoActivo()` se conserva por compatibilidad;
las entradas enumeradas cargan explícitamente el estado personal mediante el servicio.
Un nuevo consumidor debe usar ese servicio, no inferir bloqueo personal desde la FK.

## Otros consumidores revisados

| Entrada | Ajuste o decisión |
| --- | --- |
| Anexo de viático | RUT exacto, vigencia por establecimiento y piso de padrón completo; no resucita meses activos anteriores. Rechaza antecedentes ambiguos entre RBD/cargos. Permite desactivar conservando identidad. Reactivar exige vigencia. Corrige el nombre del establecimiento guardado. |
| Excepción de permiso sin goce | Alta/reactivación requieren titular docente vigente no reemplazo/suplencia; valida DV. Desactivar no requiere que siga contratado. Mantiene antecedentes existentes y permisos. |
| Resumen administrativo de establecimiento | Nómina actual explícita, sin sumar registros de varios meses. La relación histórica del modelo no recibe un filtro global. |
| Búsqueda global | Identifica el resultado como antecedente, añade período y enlaza con el filtro mensual correspondiente; no lo presenta como prueba de vigencia. No amplía permisos. |
| Descuentos CGR | Se conserva identificación histórica y prioridad AC. La desvinculación no impide recuperar la identidad para descuentos; no se introduce una regla nueva de elegibilidad. |
| Importación de establecimientos | Su servicio entra por el coordinador también cuando lo llama el comando CLI existente. No se ejecutó `--truncate` ni se cambió su autorización. |

Las dos entradas de excepciones administrativas se agregan al middleware
coordinado antes del binding. No se modifican rutas ni permisos.

## Verificación de entorno, sin datos personales

Nuevo comando: `php artisan padron:verificar-entorno --json`.

Inspecciona versión de PHP/Laravel/servidor, límite de memoria, aislamiento,
timeout, motores, columnas requeridas, fila de control y nombres de triggers.
No imprime host, usuario, contraseña, nombre de la base, cuerpos de triggers ni
registros de funcionarios. No cambia variables, no ejecuta migraciones ni aplica
revisiones. Devuelve código 1 si hay requisitos pendientes o falla la inspección.

Ejecutarlo primero en un entorno de pruebas equivalente al hosting, con el binario
absoluto PHP 8.3 de ese entorno. No se ejecutó en producción. Una salida técnica
correcta **no certifica el negocio ni habilita la aplicación**.

## Validaciones y límites

Las pruebas usan datos sintéticos y SQLite en memoria: transferencias, varios
contratos, RUT formateado, ausencia de RUT, desbloqueo auditado, no duplicación,
selector AJAX real, verificación de origen archivado, viáticos, excepciones,
resumen administrativo y conservación de identificación histórica en CGR.

Resultado de regresión: 379 pruebas y 2.629 aserciones aprobadas con el filtro
`Padron|Dotacion|SolicitudReemplazo|Sostenedores|Declaracion|Cometido|Incumplimiento|DescuentosCgr|EstablecimientoImport`.
Luego se añadieron dos casos de importación real de establecimientos (ID estable
y rollback); el conjunto `PadronEscrituraTest` pasó con 15 pruebas y 66 aserciones.
No se presenta como una única ejecución de 381 casos.

El comando de diagnóstico se ejecutó sobre la conexión local configurada, pero
no pudo completar su acceso: informó requisitos no verificados, sin exponer
credenciales ni detalles de conexión. No se cuenta como aprobación de MySQL.

Se mantienen las pruebas anteriores de concurrencia MySQL; estas pruebas nuevas
no equivalen a ejecutar todos los controladores en paralelo con el esquema completo.

La aplicación definitiva y los cambios entre años **siguen deshabilitados**.
Actualización 497: el usuario confirmó MariaDB 10.11.18. Se importó una copia
aislada del esquema y datos, con autorización explícita, y se ejecutaron pruebas
en esa versión. Resultados y límites: [laboratorio MariaDB](PADRON_MARIADB_COPIA_AISLADA.md).
No se certificó toda la configuración Linux/cPanel ni una aplicación completa
resuelta. Actualización 498: corregida la memoria de la revalidación bloqueada,
con 108 MB de pico bajo límite de 128 MB y datos operativos sin cambios.
Pendientes técnicos que no se declaran resueltos:

1. Ejecutar flujos completos y la matriz concurrente sobre ese esquema, incluidos
   registro, licencias e importadores que resuelven personas por RUT.
2. Medir memoria y duración de la aplicación final con volumen representativo;
   la prueba previa a 128 MB cubre análisis/preparación, no toda escritura final.
3. Certificar las lecturas indirectas anuales y los efectos externos de archivos
   y notificaciones ante rollback. La transacción SQL no deshace esos efectos.
4. Auditar cascadas/triggers y escrituras que eludan el coordinador. Las sondas
   SQL directas siguen siendo controles negativos, no pasan a ser éxitos.

No se tocó producción ni se eliminó historial. Los datos reales autorizados
se importaron únicamente en la copia privada de laboratorio descrita arriba.
