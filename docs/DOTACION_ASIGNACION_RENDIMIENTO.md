# Rendimiento de Asignación de horas

Parche **2026.10.12.595**, rama `perf/dotacion-asignacion`, sobre el merge `de26c1e` de `main`. Desarrollo y pruebas locales con datos ficticios.

## Hallazgo

La pestaña repetía el listado de personal y todos sus metadatos en cada formulario, aunque estuviera cerrado. Con 24 cursos y 90 docentes, el parcial generaba 29,3 MB de HTML en 2026 y 14,5 MB en 2027. El navegador además copiaba las opciones de todos los formularios al iniciar para permitir cambiar entre docente y asistente.

Las optimizaciones del cálculo de Docentes del parche 593 ya se utilizan en esta pestaña. En esta revisión, el renderizado no hizo consultas adicionales; el volumen de opciones repetidas y su preparación en el navegador constituyen un problema distinto. No se atribuye la demora completa de producción a una causa sin medir su petición HTTP.

## Cambios

- Cada conjunto de candidatos autorizado por el servidor se emite una sola vez en una plantilla HTML inerte. Los formularios conservan una referencia a ese conjunto.
- Al abrir un formulario se crean sus opciones y su buscador. Los formularios cerrados no clonan las listas ni inicializan Select2. Cambiar la cobertura utiliza la lista propia del formulario, sin modificar las plantillas ni otros selectores.
- Se conserva el orden de prelación, la fase titular/contrata, la asociación por asignatura, los filtros de Parvularia, Educación Diferencial y cupos virtuales. El formulario fallido conserva desde el servidor su selección válida y sus horas; los demás continúan cerrados.
- Los candidatos se escapan mediante Blade. No se construye HTML a partir de texto ingresado por el usuario ni se hacen peticiones nuevas para cargar opciones. Los datos se preparan sólo para el establecimiento/año ya autorizado.

Se mantienen los controladores de escritura, cálculos, permisos, suspensión de asignaciones y registros históricos. No se usa una caché entre peticiones. La pantalla utiliza el JavaScript de dotación para preparar las opciones; se deben desplegar vistas y assets juntos.

## Medición local

PHP 8.3.30, SQLite en memoria, 24 cursos, 90 docentes y 360 asignaciones ficticias. Tres procesos independientes por variante, con Blade compilado antes de tomar muestras. Los bytes son del parcial sin compresión HTTP.

| Métrica | 2026 antes | 2026 después | 2027 antes | 2027 después |
| --- | ---: | ---: | ---: | ---: |
| HTML, bytes | 29.307.649 | 4.228.669 | 14.463.343 | 4.564.223 |
| Reducción del HTML | — | **85,57 %** | — | **68,44 %** |
| Opciones emitidas, incluidas plantillas | 30.210 | 1.140 | 10.573 | 1.303 |
| Renderizado PHP, mediana | 0,6970 s | 0,5169 s | 0,5614 s | 0,5639 s |
| Cálculo del controlador, mediana | 1,1192 s | 1,0958 s | 1,5640 s | 1,5969 s |
| Consultas | 99 | 99 | 114 | 114 |
| Memoria máxima del proceso | 128 MB | 84 MB | 106 MB | 90 MB |

La huella del resumen y los datos guardados se conservan. El renderizado de 2026 disminuye aproximadamente un 26 %. En 2027 el tiempo PHP es similar: el beneficio principal es menos HTML y trabajo inicial del navegador, no una reducción demostrada del cálculo del servidor.

En Chrome, la vista grande de 2027 mantuvo 324 necesidades y 325 selectores de personal. Inicialmente no había formularios de cobertura preparados; al abrir una asignatura se preparó sólo uno y aparecieron sus 29 docentes asociados. Las 415 opciones nativas iniciales incluyen los placeholders y el selector existente de reservas. Los 15 catálogos inertes conservan los otros candidatos hasta necesitarlos.

Las muestras y límites están en [performance/dotacion-asignacion-2026-10-12.json](performance/dotacion-asignacion-2026-10-12.json). No se midieron middleware, layout compartido, red, compresión ni MySQL/cPanel. No se establece un tiempo productivo de carga a partir de estos datos locales.

## Validaciones

- **549 pruebas PHP y 6.328 aserciones correctas**, incluyendo dotación, contratos, Parvularia, PIE, funciones, prelación, reservas, permisos, suspensión y changelog.
- **10 pruebas JavaScript correctas**: candidatos, orden y metadatos, selección conservada, copias independientes, reapertura sin clonación adicional, referencia ausente y filtros existentes.
- Pruebas de vista: límite de tamaño y opciones para la dotación grande de 2026/2027, una plantilla por referencia, nombres escapados y recuperación del único formulario fallido.
- Chrome: apertura a demanda, cobertura con asistentes y contrato AAEE, búsqueda por teclado, recuperación de errores y filtros de PIE y funciones de 2027. En móvil de 390 × 844, la página conserva su ancho y los selectores funcionan. Todos los envíos de las vistas de prueba estuvieron bloqueados.
- `npm run build` correcto; conserva los assets históricos y agrega el nuevo JavaScript y su referencia en el manifiesto. La compilación informa las advertencias existentes de Sass y fuentes de admisión escolar.
- Sintaxis correcta en los ocho PHP/Blade nuevos o modificados, `git diff --check` y `artisan optimize:clear` correctos con configuración local aislada. No se modifican rutas. Se cerró la vista de prueba, se detuvo el servidor local y se retiraron los archivos temporales.

Para repetir la medición:

```powershell
$env:SGA_ASIGNACION_BENCHMARK = '1'
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' vendor/phpunit/phpunit/phpunit --filter test_asignacion_grande_conserva_necesidades_y_datos --do-not-cache-result
```

La prueba exige SQLite `:memory:` antes de preparar tablas. `SGA_ASIGNACION_PREVIEW=1`, sólo durante esta prueba, permite exportar HTML ficticio a `storage/framework/testing/asignacion-2026-grande-595.html` y `asignacion-2027-grande-595.html`; esas vistas bloquean el envío de formularios y deben retirarse tras su revisión. No se utiliza la base real ni se envían notificaciones.

## Despliegue en cPanel

**Publicar las vistas, `public/build/manifest.json` y `public/build/assets/dotacion-asignacion-DjyiSzqh.js` en el mismo despliegue.** Un manifiesto o JavaScript anterior no prepara las nuevas referencias y dejaría los selectores sin candidatos. El build está incluido en el parche; no es necesario recompilar en el hosting. Conservar los assets históricos permite completar cargas que estaban abiertas durante el despliegue.

Después de actualizar el código publicado:

```bash
cd /home/slepac/apps/slep_postulaciones
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan view:cache
```

No requiere migraciones, nuevas dependencias ni variables en `.env`. Si se genera otro build, publicar su manifiesto y todos los assets que éste referencia.

Antes y después del despliegue, con la misma cuenta, rol, establecimiento, año, pestaña y condiciones de red/caché:

1. Recargar Asignación de horas tres veces y comparar la mediana de la petición de documento en Network.
2. Separar **Waiting for server response** de **Content Download**. Registrar tamaño sin compresión, tamaño transferido, `Content-Encoding` y `DOMContentLoaded`; no compartir cookies, tokens ni nombres/RUT reales.
3. Abrir una asignatura, comprobar los candidatos y buscar por nombre/RUT; cambiar a asistente cuando esté permitido y verificar el campo AAEE. Revisar libre disposición, bolsa diferencial, funciones y reservas de 2027.
4. Comparar horas requeridas, asignadas, saldos e indicadores con los valores previos. Comprobar recuperación de errores y que los formularios cerrados no alteren sus selecciones.
5. Si sigue predominando la espera del servidor, recoger tiempos PHP y consultas lentas con el proveedor para investigar MySQL/recursos del hosting. La reducción del HTML no demuestra por sí sola una mejora del TTFB. No activar `APP_DEBUG` ni logs con datos personales en producción.

El despliegue y la medición de la petición completa en cPanel quedan pendientes. Este trabajo no modificó producción.

## Archivos del parche

- `config/changelog.php`
- `resources/js/dotacion-asignacion.js`
- `resources/views/admin/dotacion-establecimiento/partials/_asignacion.blade.php`
- `resources/views/admin/dotacion-establecimiento/partials/_personal_select_referencia.blade.php`
- `resources/views/admin/dotacion-establecimiento/partials/_personal_select_opcion.blade.php`
- `public/build/manifest.json`
- `public/build/assets/dotacion-asignacion-DjyiSzqh.js`
- `tests/Feature/DotacionDocentesRendimientoTest.php`
- `tests/Feature/DotacionAsignacionUsabilidadTest.php`
- `tests/Feature/DotacionLibreDisposicionParvulariaTest.php`
- `tests/Unit/DotacionAsignacionViewTest.php`
- `tests/js/dotacion-asignacion.test.mjs`
- `docs/DOTACION_ASIGNACION_RENDIMIENTO.md`
- `docs/performance/dotacion-asignacion-2026-10-12.json`
