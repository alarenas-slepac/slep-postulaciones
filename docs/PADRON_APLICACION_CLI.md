# Aplicación controlada del padrón por terminal

El comando `padron:aplicar-revision` reutiliza el escritor transaccional ensayado.
No habilita la aplicación web ni modifica `.env`. Este parche no requiere nuevas
migraciones; sí requiere tener instaladas las migraciones anteriores de padrón,
historial, períodos y liberaciones por baja/traslado.

## Antes de escribir en producción

1. Desplegar y comprobar PHP 8.3, MariaDB 10.11 y el diagnóstico técnico sin errores.
2. Coordinar una ventana breve sin cambios de padrón, decisiones, Dotación o sus
   documentos relacionados. El escritor bloquea dependencias; otras operaciones
   pueden esperar o agotar su tiempo de espera. No iniciar otras cargas en paralelo.
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

La salida contiene conteos, estado, `confirmacion` (huella del plan) y la frase
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
No hay opción para forzar bloqueos ni para saltarse una huella desactualizada.
Si cambian las dependencias, consultar nuevamente y revisar el plan actualizado;
las decisiones siguen guardadas. Si cambia la base contractual, se exige un nuevo
análisis. La confirmación se comprueba nuevamente dentro de la transacción bajo
los bloqueos del escritor, no solo antes de entrar.

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

Consulta del nuevo comando sobre la copia local de la revisión 21, con MariaDB
10.11.18 y sesión SQL de solo lectura: 6.176 destinos, 91 bajas, 60 asignaciones
por liberar (43 por baja y 17 por traslado), cero bloqueos y 380 avisos; segunda
consulta en 21,32 segundos y pico de 126 MB. No se aplicaron cambios con este
comando sobre esa copia ni producción. El ensayo integral anterior del escritor
está documentado en `PADRON_ENSAYO_APLICACION.md`.
