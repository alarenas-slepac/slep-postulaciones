# Rendimiento de la pestaña Docentes

Parche **2026.10.12.593**, rama `perf/dotacion-docentes`. Implementación y mediciones locales; producción y `.env` permanecen intactos.

## Evidencia y alcance

La captura aportada muestra una petición de documento de **33,86 segundos**, `DOMContentLoaded` de 33,91 segundos y aproximadamente 1,3 MB transferidos. Los CSS/JavaScript se obtienen desde caché. El tiempo de finalización de la pestaña Network también incluye otras peticiones; no debe usarse como tiempo del documento. Sin abrir Timing no se puede separar espera del servidor y descarga.

El controlador calculaba el consolidado por asignatura aunque se consultara Docentes. También volvía a consultar/consolidar las asignaciones para diferentes consumidores, recorría todas las asignaciones por cada necesidad curricular y consultaba las personalizadas de cada sección separadamente.

## Cambios

- Las asignaciones se consultan y consolidan una vez por establecimiento/año dentro del ámbito de lectura ya existente. Cada consumidor recibe copias de las filas; las marcas de asignaciones huérfanas no se propagan a otros consumidores. Al terminar o fallar el cálculo se descarta la caché; no hay datos almacenados entre peticiones.
- Las asignaciones curriculares se agrupan por `necesidad_key` antes de construir las necesidades. Los cálculos de funciones, cursos combinados, acompañamiento, horas y asignaciones históricas conservan sus reglas.
- Las personalizadas se consultan en un lote. El vínculo directo al curso/plan conserva prioridad. Si no tiene filas, se conserva el respaldo histórico por establecimiento, nivel, año y plan. Los vínculos directos de contexto histórico se mantienen. Los cursos sin plan válido no generan esta consulta.
- Se precargan las asignaturas y bloques de los planes para evitar consultas por nivel. Sólo se omite el consolidado final por asignatura en las pestañas que no lo muestran. Asistentes, cupos virtuales, necesidades e indicadores comunes siguen calculándose. La pestaña Asignaturas y el PDF mantienen su consolidado.

No cambian rutas, permisos, formularios, estados, cálculos contractuales ni datos guardados. Se completó un doble de usuario en una prueba anterior de navegación de Director ADP con el método `can()` que exige el menú actual; no se modificaron permisos reales.

## Medición local

Fixture: **24 cursos, 90 docentes y 360 asignaciones**, con datos ficticios, SQLite en memoria y PHP 8.3.30. Tres procesos independientes por variante, después de compilar Blade.

| Métrica | Antes | Después |
| --- | ---: | ---: |
| Cálculo del controlador, mediana | 2,614 s | 1,081 s |
| Consultas del cálculo y parcial docente | 169 | 100 |
| Renderizado del parcial docente, mediana | 0,055 s | 0,055 s |
| HTML del parcial docente | 1.138.706 bytes | 1.138.706 bytes |

Reducción local del cálculo: **58,6 %**. Reducción de consultas: **40,8 %**. El resumen conserva la misma huella en todas las ejecuciones. El informe de muestras y límites está en [performance/dotacion-docentes-2026-10-12.json](performance/dotacion-docentes-2026-10-12.json).

Esta medición excluye middleware HTTP, el layout compartido, transferencia y JavaScript. SQLite local tampoco reproduce MySQL ni los recursos del hosting. El HTML de todos los detalles/formularios permanece completo. No se promete que una petición productiva de 34 segundos pase a 1 segundo.

## Validaciones

Las pruebas nuevas cubren el escenario grande, una lectura de asignaciones/personalizadas, integridad sin escrituras, equivalencia de indicadores con asistentes y cupos, aislamiento/frescura de la caché, prioridad directa/respaldo histórico, asignaciones por clave, consolidado de Asignaturas, cursos sin plan y alcance del directivo. La suite relacionada incluye las regresiones de dotación, proporciones, parvularia, PIE, proceso 2027, asignaciones huérfanas, roles y changelog.

Resultado final: **524 pruebas y 6198 aserciones correctas**, incluyendo los seis casos nuevos de rendimiento/integridad. Sintaxis correcta en los seis PHP nuevos/modificados, `optimize:clear` correcto con configuración local aislada, 23 rutas existentes de dotación verificadas y `git diff --check` correcto. No corresponde recompilar Vite: CSS/JavaScript y assets no cambiaron.

Para repetir únicamente la medición local, usar PHP 8.3 y una configuración de pruebas sin caché productiva:

```powershell
$env:SGA_DOTACION_BENCHMARK = '1'
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' vendor/phpunit/phpunit/phpunit --filter test_carga_noventa_docentes_y_veinticuatro_cursos_sin_alterar_datos --do-not-cache-result
```

El test confirma SQLite `:memory:` antes de preparar sus tablas. La salida contiene métricas y consultas sin sus valores enlazados; no utiliza la base real ni envía notificaciones.

## Despliegue y comprobación en cPanel

No requiere migraciones, variables de entorno, dependencias nuevas ni recompilar assets. Desplegar los archivos del parche conservando sus rutas relativas. Desde Terminal:

```bash
cd /home/slepac/apps/slep_postulaciones
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan view:cache
```

Antes y después del despliegue, con la misma cuenta/rol, establecimiento, año y pestaña:

1. Abrir Network y recargar la pestaña Docentes tres veces, usando las mismas condiciones de caché y red. Seleccionar la petición de documento, no `unread-summary` ni el total Finish.
2. En Timing registrar **Waiting for server response** y **Content Download** por separado. Registrar también tamaño transferido, `Content-Encoding` y `DOMContentLoaded`; compartir sólo métricas, sin cookies, tokens ni datos de docentes.
3. Comparar la mediana de las tres cargas. Si domina la espera, pedir al proveedor un registro de consultas lentas/tiempos PHP y revisar índices y recursos con evidencia de MySQL. Si domina la descarga, revisar compresión HTTP y ancho de banda del hosting. No activar `APP_DEBUG` ni un query log con datos personales en producción.
4. Verificar que contratos, horas asignadas, reservas, saldos y estados coincidan; comprobar filtros, detalle y formularios de situaciones. Repetir Asignaturas y, cuando corresponda, 2027/proyección y PDF.

Pendiente: medir la petición completa en cPanel/MySQL y comprobar los indicadores con los datos autorizados del establecimiento grande. No se accedió ni se modificó producción durante este trabajo.

## Archivos del parche

- `app/Http/Controllers/Admin/DotacionEstablecimientoController.php`
- `app/Support/DotacionAsignacionCalculator.php`
- `app/Support/DotacionEstablecimientoCalculator.php`
- `config/changelog.php`
- `tests/Feature/DirectorAdpNormativaAccessTest.php`
- `tests/Feature/DotacionDocentesRendimientoTest.php`
- `docs/DOTACION_DOCENTES_RENDIMIENTO.md`
- `docs/performance/dotacion-docentes-2026-10-12.json`
