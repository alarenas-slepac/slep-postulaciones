# Parche 2026.9.11.516: memoria al renderizar el historial

El archivo compilado productivo `3d749a9ee3e624f2a55f91d1404578c3.php`
corresponde a `resources/views/partials/changelog-modal.blade.php`. El modal
generaba todas las entradas históricas incluso cerrado; además, un compositor
global repetía el filtrado del catálogo en todas las vistas y parciales.

## Cambio

- El HTML inicial solo incluye la estructura del modal.
- `GET /changelog/entries` exige autenticación y filtra por roles antes de
  paginar: diez entradas como máximo, separando versión actual e historial.
- La respuesta es un fragmento sin layout, privado y no almacenable en caché.
- El compositor se limita al layout, footer y modal. Los indicadores se
  calculan una vez por usuario y petición; no se guardan entradas en ese resumen.
- La navegación reemplaza el contenido, cancela peticiones anteriores y permite
  reintentar errores. Una carga fallida no se marca como leída.
- Se mantiene todo el historial y la ruta existente de confirmación de lectura.

## Verificación local

- `vendor/bin/phpunit tests/Feature/ChangeLogLazyTest.php`: autenticación,
  visibilidad por roles, paginación, escape HTML, confirmación y resumen acotado.
- `vendor/bin/phpunit tests/Feature/PadronMemoriaTest.php`: 6.500 filas sintéticas,
  dependencias voluminosas y límite de memoria de 128 MB en procesos separados.
  Ahora renderiza la página completa con el compositor web y un administrador;
  antes solo preparaba la vista completa y renderizaba el fragmento de filas.
- `node --test tests/JavaScript/changelog-lazy.test.cjs`: carga diferida,
  paginación, cancelación, respuestas atrasadas, errores y confirmación de lectura.

Estas pruebas no importan ni modifican datos productivos. No equivalen a medir
el pico exacto del respaldo real de producción. El parche no cambia la revisión
del padrón, sus decisiones, contratos, asignaciones ni la habilitación del ejecutor.
No requiere volver a subir el Excel ni migraciones. Al desplegar, deben limpiarse
las cachés de Laravel con el binario PHP 8.3 absoluto del servidor.
