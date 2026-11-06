# Docentes a disposición

Disponible desde la vista general de Dotación Establecimiento, para el año consultado
(2020–2100). Requiere rol asignado y activo `admin`, `coordinador_gdp`,
`coordinador_uatp` o `supervisor_plani`, además del acceso al módulo existente.

## Despliegue

La migración aditiva `2026_11_06_170000_create_dotacion_docente_distribuciones_table`
crea exclusivamente el registro auxiliar. No modifica contratos ni situaciones.
La reversión de código conserva esta tabla y su historial.

Ejecutar en cPanel, tras desplegar el código:

```bash
cd /home/slepac/apps/slep_postulaciones
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
```

## Nómina y saldo

En la vista general, los cuatro roles habilitados pueden marcar cada establecimiento
como **Revisado y finalizado** para el año seleccionado, con confirmación expresa.
La marca es manual, no se deduce del porcentaje de avance ni finaliza automáticamente
otras etapas. No modifica contratos ni bloquea la edición del módulo original.

Sólo los establecimientos con revisión finalizada de ese mismo año aportan docentes
y aparecen como destinos. Inicialmente ninguno queda marcado automáticamente.
La migración aditiva `2026_11_06_170100_create_dotacion_establecimiento_revisiones_table`
conserva responsable y fechas de cada revisión y reapertura. Para reabrir se exige
motivo. Una nueva finalización genera otra entrada y conserva las anteriores.

Reabrir retira el establecimiento del universo de nuevas distribuciones, sin borrar
registros previos. El detalle de sus distribuciones conserva consulta y anulación.
Los permisos y el cierre anual se verifican otra vez al guardar, bloqueando origen
y destino en orden de ID para coordinar las operaciones y las reaperturas.

- Reutiliza las vacantes revisables y las justificaciones del año y establecimiento,
  separadas en Planes de Estudio, Educación Parvularia y PIE, por horas titulares
  o a contrata. Conserva las exclusiones de contratos protegidos de la nómina original.
- Supresión total implica que los saldos de todos los bloques y calidades abarcan
  el contrato considerado de origen. Si sólo abarcan una parte, es parcial.
  Un contrato desconocido se informa como sin clasificar.
- Incorpora no renovaciones registradas vigentes, distribuidas según sus cupos por
  bloque; el retiro y sus cupos no suman dos veces. Retornos o anulaciones del origen
  no borran las distribuciones ya registradas.
- Ordena globalmente por el grupo de prelación del SGA, fecha de antigüedad
  (sin fecha al final) y nombre. Las no renovaciones recuperan los antecedentes de
  carrera del padrón anual cuando existen; sin ellos se presentan en el grupo a
  contrata, con tramo y fecha no informados.
- Saldo = horas actuales del origen menos distribuciones activas para la misma
  combinación año, establecimiento, RUT, bloque y calidad. Usa centésimas de hora.
- El guardado vuelve a consultar el origen después de bloquear su establecimiento
  dentro de una transacción. Comparte el orden de bloqueo de las operaciones
  existentes de asignación y no renovación; reintenta hasta tres veces ante deadlock.
- Una solicitud UUID impide repetir la misma distribución por doble envío.

## Referencia de cupos del destino

La referencia del selector suma **horas de contrato efectivamente asignadas**, no
el contrato completo ni la brecha teórica del establecimiento. Se consulta el mismo
año y bloque de la distribución:

1. Docentes por contratar con asignaciones activas.
2. Docentes reales con contrato positivo, cero horas de planta y todas sus horas
   a contrata, también con asignaciones activas.

Excluye Director, Inspector, UTP, Coordinación PIE y Convivencia Educativa/Escolar,
tanto por función principal como por función de la asignación. Excluye reservas,
asistentes, plazas por asumir, años u orígenes distintos y situaciones de fuero
maternal o gremial (`horas_gremiales`). La lactancia se excluye por fuero sólo
cuando éste consta expresamente.

Se muestran por separado las horas asignadas a cupos por contratar y a docentes
reales íntegramente a contrata. Los destinos con más horas detectadas aparecen
primero; los destinos con cero permanecen seleccionables. **La referencia nunca
actúa como máximo:** el límite es el saldo disponible del docente de origen.

## Historial

El registro auxiliar guarda destino, horas, observación, responsable y una copia
de los antecedentes y justificación de origen al distribuir. Una corrección anula
la versión anterior y crea otra, vinculadas entre sí y dentro de la misma
transacción. Anular libera las horas y conserva responsable, fecha y motivo.

Si baja el saldo de origen, la vista marca el exceso y exige corregir o anular
antes de agregar horas. Si desaparece el origen, permite consultar y anular el
historial sin recrear disponibilidad.

Esta distribución no cambia el padrón, no mueve contratos ni asignaciones
existentes, no consume cupos ni asigna cursos automáticamente en el destino.
No genera notificaciones nuevas.
