# Padrón: pruebas locales MariaDB — 2026.9.9.499

## Actualización 499: denominaciones históricas

Se amplía la correspondencia de etiquetas antiguas `CONTRATA SEP/PIE`,
`INDEFINIDO SEP/PIE` y `PLAZO FIJO SEP/PIE` hacia el mismo tipo base del
archivo. El sufijo debe coincidir exactamente con el financiamiento. Se exige
unicidad en ambos sentidos, mismo RUT, RBD, fecha de ingreso, financiamiento,
estatuto, escalafón y jornadas. No cambia la regla especial existente de PLANTA.
No se normalizan otros sufijos ni se presume regular un REEMPLAZO/SUPLENCIA.

La extensión ocurre después de la conciliación anterior y no vuelve a emparejar
por descarte una línea restante: un cambio contractual real que antes era
ambiguo sigue pendiente. Dos IDs equivalentes (canónico e histórico) tampoco
se resuelven dando prioridad al nombre canónico. La propuesta conserva los
antecedentes originales y explica la corrección; no escribe contratos.

La huella de análisis pasa a **v13**. Analizar nuevamente el Excel tras instalar
el cambio. No se borran revisiones, decisiones ni autorizaciones anteriores,
pero no se trasladan al nuevo análisis ni se aplican propuestas de una versión
anterior. La actividad documental ordinaria sigue sin vencer la revisión manual.

Repetición con el mismo Excel y copia real: revisión local **18**, período
2026/8, 59,3 s, pico **104 MB** bajo límite de 128 MB, sin errores de archivo.
La revisión 17 fue una medición intermedia anterior al cambio de huella, conservada.

| Propuesta | Antes (revisión 16) | Ahora (revisión 18) |
| --- | ---: | ---: |
| Actualización | 4.976 | 5.233 |
| Correspondencia manual | 762 | 505 |
| Ausencia por revisar | 592 | 335 |

Las **257 correspondencias nuevas** conservan el ID existente. Permanecen sin
cambio 306 reemplazos omitidos, 322 incorporaciones propuestas, 101
reactivaciones, ocho traslados y 52 bajas propuestas. El único exceso de
jornada sigue pendiente: no se autorizaron horas ni decisiones administrativas.
La previsualización acreditó igualdad de huellas operativas antes/después.
La comparación de las 6.475 filas del Excel entre revisiones 16 y 18 verificó
257 correcciones de etiqueta y **6.218 propuestas sin cambios**. En cada
corrección se comprobó el ID anterior y la igualdad de RUT, RBD, fecha de
ingreso, financiamiento, estatuto, escalafón y jornadas; el contenido entrante
permaneció idéntico. No hay decisiones ni autorizaciones registradas en esas
dos revisiones. La comparación fue de solo lectura.

El rechazo final de la revisión 18 pasó con **108 MB** bajo límite de 128 MB,
32,4 s y huellas operativas idénticas. Los mensajes bloqueantes bajaron de
1.892 a **1.314**, con 213 grupos RUT/establecimiento bloqueantes y 5.664
destinos propuestos. Los motivos por asignación pueden superponerse; no son
personas distintas. La reducción no autoriza las correspondencias restantes.

Regresión relacionada: **394 pruebas, 2.950 aserciones aprobadas**. La suite
específica se repitió después de añadir tres aserciones de duplicados mixtos:
18 pruebas, 421 aserciones aprobadas. No se cuenta esa repetición como una
segunda suite completa. Incluye aplicación sintética con documentos históricos,
IDs, horas, asignaciones, sufijos contradictorios, ambigüedad y orden de filas.

La aplicación definitiva continúa deshabilitada. No se aplicó el Excel real
ni se modificaron producción, `.env`, rutas o migraciones.

## Resultado y alcance

Se preparó una instancia independiente MariaDB **10.11.18** para Windows,
PHP 8.3.30, Laravel 12.49.0. Se importó el respaldo actualizado autorizado por
el usuario sin anonimizar su contenido. **No se copian datos personales a este
documento ni al repositorio. No se tocó producción ni `.env`.**

El laboratorio escucha únicamente en `127.0.0.1:33118`; no reemplaza MySQL de
Laragon ni instala un servicio Windows. Su directorio está fuera del repositorio,
bajo `%LOCALAPPDATA%/SlepPadronLab`, con ACL restringida al usuario local y SYSTEM.
Binarios, base, credencial aleatoria y resultados privados permanecen allí.
El respaldo original de Downloads no se modificó.

El ZIP se descargó del [distribuidor oficial de MariaDB](https://dlm.mariadb.com/browse/mariadb_server/10.11.18/winx64-packages/)
y se comprobó SHA-256 contra su manifiesto. Se inicializó una carpeta nueva;
el servidor tiene eventos deshabilitados, `local_infile=0`, sin binlog y sin
acceso SQL a archivos mediante `secure_file_priv=NULL`. La importación utiliza
una cuenta efímera limitada a una base recién creada, sin permisos globales,
FILE, EVENT, TRIGGER o rutinas. Esa cuenta se elimina al finalizar; no se elimina
ninguna base. La importación terminó sin errores.

No se levantó servidor web, scheduler ni worker de colas. El ejecutor carga
Laravel sin `.env` ni caché de configuración productiva, usa almacenamiento de
sesión/caché en memoria, registro nulo e intercepta correo, notificaciones, colas,
bus y peticiones HTTP. Estas pruebas no validan entregas externas ni archivos PDF.

## Copia real y previsualización

Respaldo: 825.136.092 bytes. Importación: aproximadamente 199 segundos.
La copia tiene 157 tablas del sistema, más una marca exclusiva del laboratorio.
El diagnóstico de columnas y motores requeridos pasó; no encontró triggers
en las tablas inspeccionadas. No hubo que ejecutar migraciones sobre la copia.

| Datos comprobados | Cantidad |
| --- | ---: |
| Registros contractuales | 21.627 |
| Asignaciones de Dotación | 13.918 |
| Bloqueos registrados | 48 |
| Solicitudes de reemplazo | 3.504 |
| Cometidos | 845 |
| Incumplimientos laborales | 2.612 |

El padrón de la copia contiene períodos de enero a mayo de 2026. Se utilizó el
Excel de carga ya proporcionado por el usuario, cuyo período leído es agosto
de 2026. No se modificó su contenido. Se creó una revisión de prueba adicional
solo en la copia local, sin registrar decisiones o autorizaciones automáticas.

| Propuesta del Excel | Filas |
| --- | ---: |
| Actualización | 4.976 |
| Correspondencia manual | 762 |
| Reemplazo anterior omitido por vigencia | 306 |
| Incorporación | 322 |
| Reactivación | 101 |
| Traslado | 8 |
| Ausencia por revisar | 592 |
| Baja propuesta | 52 |

No hay errores de formato/validación del archivo; una persona requiere
autorización por superar 44 horas. El plan devuelve 1.892 mensajes bloqueantes
(no equivalen a 1.892 personas) y 240 grupos RUT/establecimiento bloqueantes.
Revisa 1.950 grupos y 13.918 asignaciones. Reconoce 314 grupos con exceso
preexistente; ese exceso por sí solo no bloquea. Los conteos de motivos por
asignación pueden superponerse: no deben sumarse como personas distintas.

## Memoria y conservación de datos

| Prueba sobre la copia real | Límite | Pico | Resultado |
| --- | ---: | ---: | --- |
| Leer Excel y crear revisión | 128 MB | 104 MB | Pasó; 46,5 s |
| Calcular plan | 128 MB | 104 MB | Pasó; 15,5 s |
| Revalidar/aplicar una revisión bloqueada, antes de 498 | 128 MB | Agotó límite | Falló antes de escritura definitiva |
| Rechazar la misma revisión, antes de 498 | 256 MB | 148 MB | Rechazo correcto; 32,3 s; sin cambios operativos |
| Rechazar la misma revisión, parche 498 | 128 MB | 108 MB | Rechazo correcto; 29,1 s; sin cambios operativos |
| Conteo y detalle de bloqueos | 128 MB | 32 MB | 90 contratos comprobados; sin cambios |
| Traslado y nuevo ID del mismo funcionario | 128 MB | 32 MB | Bloqueo conservado; rollback exacto |
| Congelación documental y versiones mensuales | 128 MB | 42 MB | Copias válidas; rollback exacto |

El fallo anterior de 128 MB se observó en `PadronConflictosAsignacionService::snapshot`,
al acumular las asignaciones durante la revalidación final. En ese momento
`PadronAplicacionService` conservaba todos los contratos anteriores en memoria.
La fase de previsualización aprobada no certifica el consumo de esa fase final.
En 497 no se corrigió el algoritmo ni se aumentó el límite del sistema:
256 MB se usaron exclusivamente en otro proceso CLI de diagnóstico.
El fallo se reprodujo dos veces. Tras la segunda interrupción se abrió otro
proceso y se compararon las huellas guardadas antes del intento: contratos,
asignaciones, documentos, bloqueos, auditoría, versiones y revisión permanecen
sin cambios parciales.

El parche 498 retiene inicialmente un mapa ID/RUT, adquirido con el mismo
`SELECT ... FOR UPDATE` y orden de bloqueos. No retiene todos los contratos
completos durante `plan()`. Antes de modificar cada ID consulta sus columnas
completas dentro de la transacción ya bloqueada, para conservar íntegra la
auditoría anterior/nueva. Conserva IDs, metadatos internos y formato de RUT.
No cambia la huella base v12 ni la confirmación v3: esta optimización no exige
regenerar revisiones ni elimina decisiones/autorizaciones.

La repetición sobre la misma revisión y copia real completó la revalidación
con 108 MB de pico bajo límite de 128 MB. Conservó los 1.892 mensajes,
5.407 destinos, 52 bajas propuestas y 240 grupos bloqueantes; las huellas
operativas permanecieron idénticas. Es un rechazo correcto sin escritura,
**no una certificación del consumo de una aplicación completa resuelta**.

La prueba histórica ejecutó los servicios reales en una transacción revertida:
creó 6.871 copias documentales faltantes, validó 6.946 documentos vinculados,
generó cinco versiones mensuales y 21.627 copias contractuales. Verificó igualdad
de huellas antes/después del rollback, incluidas asignaciones, documentos,
contratos, bloqueos, auditoría y versiones. No guardó esas copias en producción
ni las dejó aplicadas en la base de prueba.

El traslado reversible utilizó un funcionario bloqueado de la copia: conservó
el bloqueo al cambiar su establecimiento y también al crear otro ID del mismo
RUT. No se copió ni alteró el registro de bloqueo original. Finalmente revirtió
el traslado y la fila de prueba.

## Concurrencia MariaDB

Matriz sintética reducida, separada de la base real: **48 escenarios**, 24 por
aislamiento (REPEATABLE READ y READ COMMITTED). Ejecución `167d956bacbd8723`:
**38 aprobados, cero fallos de aserción, 10 controles negativos**
`requires_writer_protocol`. Reporte ignorado por Git:
`storage/app/testing/padron_mysql_167d956bacbd8723.json`.

Repetición con la optimización 498: ejecución `152f99a540dd21c8`, los mismos
48 escenarios, **38 aprobados, 10 controles negativos y cero fallos**. Se
comprobó nuevamente que todas las lecturas iniciales son bloqueantes antes
de revalidar; la lectura compacta no adelanta una vista consistente antigua.

Comprueba aplicación, IDs, historial, idempotencia, dos confirmaciones,
rollback, desconexión, timeout, deadlock, cambios concurrentes y revalidación
de escritores coordinados. Los diez INSERT SQL que omiten la coordinación
continúan siendo controles negativos; no se transforman en éxitos de seguridad.

La primera adaptación consultaba las esperas cada 20 ms y produjo 17 falsos
negativos de observación (ejecución `b31fbe525cdef9e6`, conservada).
MariaDB renueva la caché compartida de `INNODB_TRX`/`INNODB_LOCK_WAITS` solo
tras más de 100 ms sin lecturas. El observador ahora espera 200 ms entre
consultas y exige la relación real entre conexiones bloqueada/bloqueadora.
No se alteraron las transacciones ni se añadieron bloqueos artificiales para
lograr el resultado. Referencia: [código MariaDB 10.11.18, caché de transacciones](https://github.com/MariaDB/server/blob/mariadb-10.11.18/storage/innobase/trx/trx0i_s.cc#L901).

## Repetición segura

Herramientas optativas, fuera de la ejecución automática de PHPUnit:

- `tests/Integration/padron_mysql_concurrency.php`: matriz sintética nueva por ejecución.
- `tests/Integration/padron_mariadb_copy.php`: copia privada de MariaDB exacta.

La herramienta de copia exige `--root` bajo el directorio privado del laboratorio,
comprueba versión, puerto, datadir y marca de propiedad. No acepta una base de
producción ni reutiliza una base para importar encima. Para repetir en otra copia
hay que inicializar otra carpeta/instancia aislada, no vaciar la existente.

En el entorno ya preparado, sustituir `DIRECTORIO_PRIVADO` por su ruta local:

```text
php tests/Integration/padron_mariadb_copy.php --root=DIRECTORIO_PRIVADO --action=inspect
php tests/Integration/padron_mariadb_copy.php --root=DIRECTORIO_PRIVADO --action=matrix
php -d memory_limit=128M tests/Integration/padron_mariadb_copy.php --root=DIRECTORIO_PRIVADO --action=plan --revision=16
php -d memory_limit=128M tests/Integration/padron_mariadb_copy.php --root=DIRECTORIO_PRIVADO --action=reject --revision=16
php -d memory_limit=128M tests/Integration/padron_mariadb_copy.php --root=DIRECTORIO_PRIVADO --action=transfer
php -d memory_limit=128M tests/Integration/padron_mariadb_copy.php --root=DIRECTORIO_PRIVADO --action=history --revision=16
```

`preview` requiere `--excel` y crea otra revisión únicamente local. `reject`
solo acepta una revisión con bloqueos y prueba su rechazo: nunca autoriza casos.
`history` y `transfer` escriben exclusivamente dentro de una transacción de prueba
que siempre se revierte. `verify-fatal` contrasta las huellas guardadas al iniciar
el último fallo fatal, sin reintentar la aplicación. Los informes contienen
conteos, tiempos, memoria, códigos de error y huellas, no nombres ni RUT.

## Pendientes que impiden habilitar

1. Resolver con criterio administrativo las correspondencias, ausencias y
   autorización pendientes del Excel. No se ha aplicado ese padrón completo.
2. Repetir una aplicación completa sin bloqueos con volumen real; el pico de
   108 MB corresponde al rechazo, no al ciclo completo de escritura.
3. Completar concurrencia de endpoints y consumidores indirectos, efectos
   de archivos/notificaciones y protección histórica entre años.
4. Contrastar configuración efectiva Linux/cPanel (PHP web, límites, tiempos,
   aislamiento y tareas externas). Igualar la versión del motor en Windows
   no certifica automáticamente todos esos aspectos del hosting.

La constante de habilitación sigue en `false`. No hay cambios en rutas,
permisos, migraciones ni compilados de interfaz en este parche de laboratorio.

## Regresión adicional

Suite relacionada con SQLite en 497: 385 pruebas y 2.650 aserciones.
Repetida en 498: **387 pruebas, 2.663 aserciones, sin fallos** (123,8 s).
Añade comprobación de auditoría íntegra, incluso columnas adicionales, y del
formato de RUT conservado al crear otra línea de una identidad existente.
Incluye las cuatro pruebas de selección explícita de motor y observador.
La suite completa tiene pico de 178 MB; no es la medición aislada a 128 MB.
Además, el caso de confirmación duplicada se repitió en MySQL 8.4.3 con ambos
aislamientos: dos aprobados en 498 (`8397add8a6d5acb4`), además de la ejecución
497 (`c82fe30e750c616a`). Ninguno de estos resultados
reemplaza la validación de una aplicación completa resuelta sobre la copia real.
