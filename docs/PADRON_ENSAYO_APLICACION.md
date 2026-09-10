# Ensayo de aplicación definitiva — laboratorio privado

El ejecutor está exclusivamente en `tests/Integration/padron_mariadb_copy.php`
y `tests/Support/PadronRehearsal.php`. No incorpora rutas, comandos Artisan ni
bindings de producción. `PadronAplicacionService::APLICACION_HABILITADA` sigue
en `false`. No usar este ejecutor en cPanel.

## Requisitos

- Laboratorio local privado ya preparado, bajo `%LOCALAPPDATA%/SlepPadronLab`.
- MariaDB 10.11.18, con eventos deshabilitados y marca de propiedad válida.
- PHP 8.3, binarios del laboratorio y espacio para otra copia SQL/base.
- ID explícito de revisión **local** y usuario existente para la auditoría.
- Revisión vigente, no aplicada, con todas sus decisiones y autorizaciones
  resueltas. Los IDs de producción no se presumen equivalentes a los locales.

No se importan decisiones desde producción, no se seleccionan candidatos ni se
autorizan jornadas automáticamente. Si la revisión está vencida, ya aplicada o
tiene bloqueos, el ensayo se detiene antes de crear una copia de aplicación.

## Ejecución

Primero puede comprobarse el ejecutor con datos totalmente sintéticos:

```powershell
$labRoot = "$env:LOCALAPPDATA/SlepPadronLab/mariadb101118_9b37681b"
php -d memory_limit=128M tests/Integration/padron_mariadb_copy.php --root="$labRoot" --action=rehearse-synthetic
```

La prueba integral de una revisión real resuelta utiliza:

```text
php -d memory_limit=128M tests/Integration/padron_mariadb_copy.php --root=DIRECTORIO_PRIVADO --action=rehearse --revision=ID_LOCAL --user=ID_USUARIO_LOCAL
```

Controles negativos del propio ejecutor, siempre sobre nuevos datos sintéticos:

```text
--action=rehearse-synthetic --synthetic-case=unresolved
--action=rehearse-synthetic --synthetic-case=stale
--action=rehearse-synthetic --synthetic-case=missing-user
```

Se agregan al mismo comando con `--root`. Los dos primeros deben terminar con
`status: blocked`, sin aplicación; el tercero rechaza la falta de actor con
`status: failed`. Estos rechazos tienen salida distinta de cero y no son pruebas
de una aplicación completa aprobada.

## Qué hace

1. Verifica la revisión y calcula el plan sin escribir decisiones ni contratos.
2. Conserva huellas del personal, asignaciones, declaraciones, exclusiones,
   documentos, bloqueos, auditoría, períodos, revisión, filas y decisiones.
3. Exporta el laboratorio a un SQL privado y lo importa en una **base nueva**.
   Conserva esquema y claves foráneas. Rechaza triggers, vistas o motores no
   transaccionales, en vez de omitirlos silenciosamente. La cuenta de importación
   es efímera y solo tiene permisos sobre la nueva base.
4. Comprueba igualdad de las huellas y del plan en la copia adicional.
5. Provoca un fallo después de la primera escritura contractual y su auditoría.
   Exige rollback de datos, copias históricas, versiones y estado de revisión.
6. Aplica mediante el servicio real, en su propia transacción, exclusivamente
   sobre esa nueva base. Mide tiempo y pico de memoria de la aplicación exitosa.
7. Contrasta destinos, campos del Excel, IDs, metadatos conservados, altas,
   reactivaciones, bajas y auditorías. Verifica documentos anteriores, versiones
   mensuales, asignaciones y persistencia de bloqueos personales.
8. Repite la aplicación y exige igualdad de huellas: no duplica movimientos.
9. Regresa a la copia de origen y comprueba que sus huellas no cambiaron.

El rollback compara filas; los contadores AUTO_INCREMENT pueden avanzar por
inserciones revertidas. No se exige que el siguiente ID sea consecutivo.

El SQL, manifiesto y plan esperado quedan en el directorio privado
`rehearsal-<identificador>`, fuera del repositorio y con los permisos heredados
del laboratorio. Pueden contener datos reales: no subirlos ni adjuntarlos a PRs.
La consola/reporte general contiene únicamente resultados técnicos y conteos.
Las bases y archivos de prueba se conservan para investigar; no hay limpieza
destructiva automática, tampoco cuando falla la importación o una verificación.

## Resultado local inicial

### Bajas con liberación diferida (parche 2026.9.10.506)

La revisión incorpora una confirmación explícita por RUT ausente de todo el
archivo, con justificación y alcance de asignaciones del año revisado. Instalar
la migración `2026_09_10_120000_create_padron_bajas_asignaciones.php` permite
registrar estas decisiones; no habilita la aplicación definitiva.

El ensayo admite esas liberaciones en el plan: exige la inactivación exacta de
las asignaciones confirmadas, sin borrar filas ni modificar sus necesidades,
horas, vínculos o años. Contrasta las imágenes antes/después y la confirmación
de origen en `padron_asignacion_cambios`. Todas las demás asignaciones y las
decisiones de baja deben permanecer idénticas. Las nuevas tablas también se
incluyen en las huellas de rollback e idempotencia.

Si cambia el alcance del RUT se requiere confirmar nuevamente; no se presume
retiro si el RUT tiene alguna fila en otro establecimiento del archivo. Las
pruebas de servicio con SQLite no certifican por sí solas la concurrencia ni
el volumen real de este nuevo flujo en MariaDB/cPanel.

Para el escenario sintético de baja con liberación en MariaDB, use
`--action=rehearse-synthetic --synthetic-case=release` con el mismo `--root`
privado. Solo ese fixture genera su confirmación; nunca autoriza bajas de la
copia real. El reporte debe indicar `assignments_released_verified: 1` y
`unplanned_assignments_and_blocks_unchanged: true`.

Validación sintética local del 10/09/2026: MariaDB 10.11.18 completó la baja con
una asignación liberada, seis documentos históricos protegidos y dos versiones
mensuales. Rollback e idempotencia aprobados, origen sin cambios; pico de 32 MB
bajo límite de 128 MB. No se aplicó una carga con datos reales.

### Antecedentes del ensayo anterior

- Ensayo sintético MariaDB: una actualización, una reactivación, una incorporación
  y una baja; seis documentos protegidos, dos versiones mensuales, un contrato
  bloqueado comprobado, rollback y repetición sin duplicados.
- Revisión **local 18**, agosto de 2026: el ensayo encontró **1.318 mensajes
  bloqueantes**, terminó sin crear una copia de aplicación y verificó igualdad
  del estado de origen. 43,3 segundos y 106 MB bajo límite de 128 MB.
- Esos mensajes no equivalen a personas ni describen el estado de producción.
- Regresión del padrón: **286 pruebas, 2.033 aserciones**, más cuatro pruebas
  JavaScript aprobadas. Sintaxis PHP y `git diff --check` correctos.
  El conjunto PHPUnit usó 152 MB; no debe confundirse con los procesos aislados
  del ensayo ejecutados bajo límite de 128 MB.

La aplicación completa del Excel real permanece pendiente de resolver esa
revisión. Una prueba sintética o un rechazo correcto no certifican la escritura
con volumen real, la concurrencia de todos los módulos, los cambios entre años
ni los límites del hosting. Correo, notificaciones, colas y HTTP siguen
interceptados: sus efectos externos tampoco quedan certificados aquí.
