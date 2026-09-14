# Aplicación controlada del padrón por terminal

El comando `padron:aplicar-revision` reutiliza el escritor transaccional ensayado.
No habilita la aplicación web ni modifica `.env`. Este parche no requiere nuevas
migraciones; sí requiere tener instaladas las migraciones anteriores de padrón,
historial, períodos y liberaciones por baja/traslado.

## Antes de escribir en producción

1. Desplegar y comprobar PHP 8.3, MariaDB 10.11 y el diagnóstico técnico sin errores.
2. No es necesario detener producción ni poner la aplicación en mantenimiento.
   La actividad documental normal no vence la confirmación CLI. El escritor sigue
   bloqueando dependencias durante la transacción: otras escrituras pueden esperar
   o agotar su tiempo de espera; no se garantiza ausencia de demoras. No iniciar
   otras cargas completas en paralelo ni modificar las decisiones ya confirmadas.
3. Obtener un respaldo completo reciente **después de las últimas resoluciones**,
   guardar su SHA-256 fuera del repositorio y comprobar que puede restaurarse en
   un entorno aislado. No restaurar sobre producción como prueba.
4. Confirmar el ID del administrador responsable. `--usuario` registra atribución:
   no es autenticación interactiva. El acceso SSH debe estar restringido a operadores
   autorizados. El comando exige un usuario existente, no eliminado, con rol `admin`.
5. Consultar nuevamente la revisión y revisar los conteos antes de aplicar.

No usar el ejecutor del laboratorio en producción. No volver a cargar el Excel
ni modificar decisiones únicamente para ejecutar este comando.

## Consulta previa: no aplica cambios

Desde `/home/slepac/apps/slep_postulaciones`, para revisión 21 y administrador ID 1:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php -d memory_limit=256M artisan padron:aplicar-revision 21 --usuario=1
```

El límite de 256 MB afecta únicamente a este proceso; no requiere cambiar cPanel.
El comando rechaza un límite inferior o ilimitado. El consumo exacto depende de
la base y del entorno. La serie MariaDB 10.11 es la validada para esta entrada.

La salida contiene conteos, estado, `confirmacion` (huella de la propuesta) y la frase
`confirmar`. No imprime nombres, RUT, contratos ni credenciales. Si hay errores,
devuelve código 1; consultar sus detalles en la revisión autenticada de la web.
Los avisos por sí solos no impiden aplicar. `ya_aplicada` devuelve fecha/autor sin
repetir escrituras, incluso en un reintento tras perder conexión con la terminal.

## Aplicación: solo después de aprobar el plan y verificar el respaldo

La siguiente plantilla **sí modifica datos**. Sustituir las dos huellas con los
valores reales; no usar una huella de laboratorio ni inventar un respaldo.

```bash
/opt/cpanel/ea-php83/root/usr/bin/php -d memory_limit=256M artisan padron:aplicar-revision 21 --usuario=1 --aplicar --confirmacion=HUELLA_DEL_PLAN --confirmar=APLICAR:21:2026:8 --respaldo-sha256=HUELLA_DEL_RESPALDO
```

`--respaldo-sha256` es una declaración explícita del operador y queda registrada;
el comando valida su formato, pero **no crea, abre ni certifica el respaldo**.
No hay opción para forzar bloqueos ni para saltarse decisiones desactualizadas.
Desde el parche 2026.9.14.519, la confirmación CLI corresponde a la revisión,
filas, decisiones, autorizaciones y liberaciones aprobadas. La salida identifica
`confirmacion_tipo: propuesta_con_revalidacion_transaccional`. Obtener una nueva
confirmación tras instalar este parche; las huellas del formato anterior no sirven.

Los cambios en documentos, declaraciones o asignaciones ya no invalidan por sí
solos esta huella. Al aplicar, el escritor adquiere los bloqueos y recalcula el
plan con los datos actuales: si aparece un conflicto real, falta cobertura o cambia
el alcance de una liberación, rechaza toda la operación. Los documentos nuevos
se protegen con la imagen contractual vigente. No se omite ninguna validación.

Si cambian las filas, decisiones, autorizaciones o confirmaciones de liberación,
se necesita consultar y confirmar nuevamente. Si cambia la base contractual,
se exige un nuevo análisis. Se conservan las decisiones ya registradas.
La web continúa deshabilitada y conserva su diagnóstico de huella completa;
su mensaje transitorio de cambio durante el cálculo no sustituye el diagnóstico CLI.

## Resultado y auditoría

- `aplicada` significa que el escritor confirmó la transacción; incluye revisión,
  fecha, responsable, tiempo y pico de memoria.
- Se conservan IDs seleccionados, imágenes contractuales históricas y versiones
  mensuales. Solo se liberan las asignaciones expresamente confirmadas.
- La auditoría persistente continúa en `padron_personal_cambios`,
  `padron_asignacion_cambios` y `padron_revisiones.aplicada_at/aplicada_por`.
- El canal de log configurado recibe `padron.cli.aplicacion_solicitada` y
  `padron.cli.aplicacion_completada` con revisión, actor y huellas del plan/respaldo.
  El primer evento registra intención, **no prueba de aplicación**. Conservar
  ese registro operativo según la política de respaldos del sistema.
- Ante error o desconexión, consultar sin `--aplicar` antes de reintentar. No asumir
  que hubo rollback si se perdió la confirmación de conexión; no restaurar a ciegas.
- Después, revisar resumen de movimientos, Dotación, historial de documentos y
  exclusiones de reemplazos. La web permanece sin botón de aplicación habilitado.

## Verificación del parche

Pruebas aisladas del comando: consulta sin escritura, confirmaciones obligatorias,
usuario administrador, base/plan cambiados, bloqueos, rechazo HTTP y SQLite,
límite de memoria, escritura auditada, rollback y repetición idempotente.
El escritor conserva además su suite de regresión transaccional existente.

El parche 2026.9.14.519 añade pruebas de actividad documental durante/después de
la consulta, captura de referencias nuevas y rechazo de conflictos o decisiones
modificadas antes de la escritura. La matriz MariaDB admite los casos
`cli_document_update`, `cli_conflict_insert` y `cli_personal_update`, con dos
conexiones y observación de espera real sobre los bloqueos.

Validación local del 14/09/2026: los tres casos pasaron en MariaDB 10.11.18 tanto
con REPEATABLE READ como con READ COMMITTED (seis escenarios). El cambio
documental previo se conserva y la aplicación no duplica datos al reintentar;
los conflictos nuevos y cambios del contrato base se rechazan sin escrituras.
Esto no garantiza ausencia de esperas o timeouts en el hosting.

El respaldo actualizado del 14/09/2026 también se restauró en una base local nueva:
159 tablas presentes y comprobadas, revisión 21 sin aplicar, 366 decisiones,
6.176 destinos, 91 bajas, 60 liberaciones confirmadas y cero errores. La consulta
de esa copia usó una sesión SQL de solo lectura; no se aplicó el padrón real.

Consulta del nuevo comando sobre la copia local de la revisión 21, con MariaDB
10.11.18 y sesión SQL de solo lectura: 6.176 destinos, 91 bajas, 60 asignaciones
por liberar (43 por baja y 17 por traslado), cero bloqueos y 380 avisos; segunda
consulta en 21,32 segundos y pico de 126 MB. No se aplicaron cambios con este
comando sobre esa copia ni producción. El ensayo integral anterior del escritor
está documentado en `PADRON_ENSAYO_APLICACION.md`.
