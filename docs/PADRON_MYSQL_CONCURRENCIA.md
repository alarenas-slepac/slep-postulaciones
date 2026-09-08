# Padrón: laboratorio MySQL de concurrencia

## Estado

Validación local del 8 de septiembre de 2026. **No habilitar la aplicación
definitiva**: se encontraron confirmaciones obsoletas aceptadas bajo REPEATABLE
READ y falta certificar la coordinación con los escritores externos.

No se modificó producción, la base habitual, `.env` ni la constante de habilitación.
La instancia que escribe existe únicamente en el laboratorio, con guardas de
entorno, nombre de base y marca de propiedad. No se registra en el contenedor de
servicios utilizado por las rutas.

## Alcance y reproducción

- PHP 8.3.30; MySQL 8.4.3 local; todas las tablas del fixture son InnoDB.
- Aislamiento del servidor: REPEATABLE-READ; espera predeterminada: 50 segundos.
- Cada proceso establece explícitamente REPEATABLE READ o READ COMMITTED y una
  espera de 8 segundos; el caso de timeout utiliza 1 segundo.
- No se verificó la versión/configuración productiva. No es una certificación
  de MySQL/MariaDB del hosting ni una prueba de volumen con el Excel real.
- Fixture reducido de contratos, documentos y cobertura, exclusivamente sintético.
  Usa las cuatro migraciones reales de revisión, aplicación, copia documental e
  historia mensual. Las demás tablas contienen las columnas necesarias para las
  pruebas, no una réplica completa del esquema productivo.
- Documentos con FK contractual; asignaciones con índice pero sin esa FK, como
  su migración. No se presume que bloquear personal bloquee toda asignación nueva.
- El padre crea una base nueva por escenario; dos procesos PHP independientes
  ejecutan el servicio de aplicación y el escritor competidor. Las barreras tienen
  acuse de recepción y las esperas se verifican en `performance_schema.data_lock_waits`.

Configurar fuera del repositorio `PADRON_MYSQL_USER` y, si corresponde,
`PADRON_MYSQL_PASSWORD`. El usuario local necesita crear las bases del laboratorio,
crear sus tablas y consultar las esperas de `performance_schema`. No se crean
cuentas ni se cambian permisos automáticamente. Solo se admite TCP `127.0.0.1`;
el puerto opcional es `PADRON_MYSQL_PORT` (3306 por defecto). No utiliza `DB_*`.

```text
php tests/Integration/padron_mysql_concurrency.php --run
php tests/Integration/padron_mysql_concurrency.php --run --case=deadlock
php tests/Integration/padron_mysql_concurrency.php --run --case=document_update --isolation=RR
```

Es una herramienta optativa fuera de las carpetas de ejecución automática de
PHPUnit. Sin `--run` no crea bases. Los filtros inválidos se rechazan. Requiere
MySQL 8 y la vista de esperas; no acepta MariaDB como sustituto silencioso.

Los reportes JSON se guardan en `storage/app/testing/padron_mysql_<ejecucion>.json`,
ignorados por Git. Contienen versión, aislamiento efectivo, motores, bases,
resultados, reintentos, esperas y consultas parametrizadas sin sus valores. No
contienen contraseñas ni datos del padrón real. Los códigos de salida son:
0 sin hallazgos, 1 pruebas fallidas, 2 observaciones de protocolo externo sin
fallos de aserción. **No ocultar un código 1 o 2 como éxito de certificación.**

Las bases `padron_lab_<ejecucion>_<numero>` se conservan para inspección, incluidas
ejecuciones de diagnóstico del propio laboratorio. No se ejecuta DROP ni se
reutiliza/vacía una base existente. Su eliminación posterior requiere identificar
explícitamente los nombres del reporte; no borrar por prefijos indiscriminados.

## Resultados y hallazgos

La matriz completa contiene 17 escenarios por aislamiento. Ejecución final:
`18c9693592202d63`, reporte local
`storage/app/testing/padron_mysql_18c9693592202d63.json`.
Resultado: **20 pruebas aprobadas, 4 fallidas y 10 observaciones de protocolo**;
código de salida 1. Las cuatro fallidas corresponden a la confirmación obsoleta
bajo REPEATABLE READ, no a una interrupción del laboratorio. Las bases de esta
matriz terminan en `_01` a `_34` bajo su identificador de ejecución.

Regresión relacionada: `php vendor/phpunit/phpunit/phpunit --filter "Padron|Dotacion|SolicitudReemplazo"`:
332 pruebas, 2.403 aserciones, sin fallos. Esa suite usa SQLite y no contradice
los hallazgos de concurrencia del laboratorio MySQL. Sintaxis PHP validada en los
tres archivos nuevos del laboratorio y en `config/changelog.php`.

| Comprobación | REPEATABLE READ | READ COMMITTED |
| --- | --- | --- |
| Aplicación simple, IDs y copia histórica | Pasa | Pasa |
| Misma revisión simultánea, sin duplicar auditoría | Pasa | Pasa |
| Revisiones distintas de la misma base | Segunda rechazada | Segunda rechazada |
| Fallo intermedio y desconexión, rollback y reintento | Pasa | Pasa |
| Timeout y tres intentos, reintento posterior | Pasa | Pasa |
| Interbloqueo real y reintento transaccional | Pasa | Pasa |
| Cambio concurrente de contrato existente | Rechaza | Rechaza |
| Cambio de documento, asignación, declaración o exclusión | **Acepta confirmación obsoleta** | Rechaza |
| INSERT externo después del chequeo final | Espera hasta el commit | Cuatro escenarios no esperan; documento con FK sí espera |

### Confirmación obsoleta en REPEATABLE READ

Un escritor modifica una dependencia y mantiene su transacción abierta. El
aplicador espera su bloqueo. Después del commit externo, una conexión independiente
comprueba que el token original **ya no coincide**. Aun así, el aplicador acepta
ese token en los cuatro escenarios de dependencias bajo REPEATABLE READ.

La traza muestra lecturas de metadatos (`Schema::hasTable`) intercaladas entre
los bloqueos y lecturas normales posteriores para las huellas. El resultado es
compatible con validar contra una vista transaccional anterior al cambio: obtener
los IDs con `FOR UPDATE` no acredita que las lecturas posteriores usadas por el
plan vean los mismos datos. La causa exacta debe cerrarse al corregir y repetir
estas pruebas; no se presenta como solucionada en este parche.

Los cambios probados son inocuos por sí mismos (por ejemplo, 20 a 21 horas de
asignación), pero bastan para demostrar que la confirmación antigua se acepta.
No se afirma que esta prueba haya sobrescrito documentos o producido pérdida de
datos reales: incumple el rechazo esperado de la confirmación final.

### Escritores externos y altas concurrentes

Los cinco escenarios de altas utilizan SQL directo deliberadamente, sin adoptar
el control global del padrón. Son sondas de la protección de la base, **no pruebas
de los controladores HTTP**. En particular, el INSERT documental no ejecuta el
trait `ConservaPadronHistorico`; su falta de copia no demuestra que el modelo
productivo omita esa captura.

En READ COMMITTED pueden confirmarse altas de personal, asignaciones,
declaraciones y exclusiones después del chequeo final y antes del commit del
padrón. En REPEATABLE READ esas inserciones esperan en este fixture, pero guardar
después de esperar tampoco demuestra que el escritor vuelva a comprobar vigencia
y cobertura. La sonda de asignaciones puede dejar una asignación activa vinculada
al contrato que el padrón acaba de desactivar.

Los diez casos se etiquetan `requires_writer_protocol`: son observaciones de
intercalaciones externas que requieren auditar sus puntos de escritura, no diez
defectos ya demostrados en endpoints reales. Cambiar solo el aislamiento no cierra
ambos problemas.

## Trabajo necesario antes de habilitar

1. Corregir la revalidación final para que use datos actuales tras adquirir los
   bloqueos, sin restablecer la invalidación continua de decisiones manuales.
2. Inventariar todos los escritores de contratos y dependencias y adoptar un
   protocolo transaccional compatible, con orden de bloqueos y revalidación tras
   esperas. Incluir altas por RUT, sin FK y nuevas filas de cobertura.
3. Añadir concurrencia mediante los modelos/controladores reales y probar el
   esquema completo, índices y configuración equivalentes al hosting.
4. Repetir la matriz sin confirmaciones obsoletas aceptadas ni intercalaciones
   inseguras. Completar por separado la auditoría de consumidores históricos.

Este parche aporta pruebas y evidencia; no modifica las reglas productivas de
aplicación, no levanta bloqueos y no certifica que el padrón esté listo para aplicar.
