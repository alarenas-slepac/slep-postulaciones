# Comparación editable de estudiantes PIE entre años

Parche **2026.10.12.592**, rama `feat/pie-comparacion-anios`. Implementación local; producción y `.env` permanecen intactos.

## Acceso y uso

En **Estudiantes PIE por curso**, abrir **Comparar años**. URL: `/admin/establecimiento-curso-pie-comparacion`. El botón, la consulta y el guardado están reservados a **administrador y coordinador UATP** (`admin`, `coordinador_uatp`), con correo verificado, rol activo autorizado y acceso al módulo PIE existente. Los permisos anteriores del listado y sus acciones individuales se conservan.

1. Buscar el establecimiento por RBD/nombre y elegir dos años distintos, entre 2020 y 2100. Se proponen el año actual y el siguiente; en 2026, 2026/2027.
2. Pulsar **Comparar años**. Se comparan el mismo nivel (`curso_id`) y letra de sección; no se supone que sean los mismos alumnos ni se aplica promoción de curso. Se incluyen todos los cursos activos de ambos años, aunque una sección no tenga contraparte.
3. Editar **NEET y NEEP en ambos años**. La fila muestra matrícula, régimen, estado, total PIE y horas crono, Educador Diferencial y PAEC. Los resúmenes por año y las diferencias se actualizan al instante.
4. Pulsar **Guardar todas las modificaciones**. Se envían sólo las filas modificadas mediante JSON y se guardan juntas. Si alguna falla, no se guarda ninguna modificación. Al terminar, la página vuelve a consultar los datos guardados.

Se advierte antes de abandonar cambios pendientes. El guardado está desactivado cuando no hay modificaciones o las cantidades editadas son inválidas. Sin JavaScript sigue disponible el formulario convencional; las horas se recalculan en el servidor al guardar.

Un curso activo sin registro PIE puede recibir uno nuevo al cambiar sus cantidades iniciales: se crea en **borrador**. Dejarlo en 0/0 no crea un registro vacío. Nunca se crean cursos/secciones desde esta vista. Si existen varias secciones con el mismo nivel/letra, se muestran separadamente dentro del grupo, sin elegir una arbitrariamente. Las identidades PIE contradictorias se muestran para revisión y no se pueden sobrescribir.

## Cálculos e integridad

La vista reutiliza **`PieHorasCalculator`**. La matriz del navegador se genera con ese mismo cálculo y el catálogo vigente, incluyendo JEC, SIN JEC, EPJA, NEET menor a cinco, ausencia de NEET y NEEP sin regla. El servidor recalcula al guardar; no acepta totales ni horas enviados por el cliente.

Cada cantidad debe ser un entero entre 0 y 9999; **NEET + NEEP no puede superar la matrícula**. Cuando faltan reglas o NEEP excede la tabla vigente, se conservan horas `null`: la fila muestra `—` y el resumen avisa que la suma de horas es parcial. No se inventan horas ni se confunde un cálculo pendiente con cero.

Los registros sin modificaciones conservan sus horas guardadas, estado, observación, creador y fecha de actualización. Volver a las cantidades originales recupera sus horas originales. Los registros editados recalculan horas y actualizan `updated_by`; no cambian el estado ni las observaciones. Se admite la identidad histórica `curso_id=null` cuando el vínculo a su curso/sección y establecimiento es válido.

El lote se valida y guarda en una transacción, bloqueando cursos, registros PIE y reglas. Una revisión HMAC del curso, plan y registro detecta cambios concurrentes; otra detecta cambios del catálogo de horas. Si cambiaron desde la apertura, se solicita recargar antes de guardar. Las revisiones son controles de concurrencia; la autorización sigue en los middleware y controlador. Un formulario convencional truncado por `max_input_vars` carece del contador final obligatorio y se rechaza; no se guarda parcialmente. Máximo 400 filas por petición.

La vista usa el layout del SGA, Bootstrap, fuentes existentes y Select2. En móvil, la tabla desplaza horizontalmente dentro de su contenedor; la página conserva su ancho. Los colores de la zona nueva siguen `docs/DESIGN_SYSTEM.md`.

## Despliegue en cPanel

No requiere migraciones nuevas, cambios en `.env` ni instalación de dependencias adicionales. Requiere que el módulo PIE y su catálogo de horas ya estén instalados.

1. Desplegar los PHP/Blade/rutas, Vite, changelog y assets del parche, conservando rutas relativas. Copiar `public/build/manifest.json` y los dos assets nuevos referenciados: CSS y JavaScript PIE. Conservar los assets históricos.
2. Ejecutar desde Terminal con PHP 8.3:

   ```bash
   cd /home/slepac/apps/slep_postulaciones
   /opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
   /opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
   /opt/cpanel/ea-php83/root/usr/bin/php artisan route:list --path=admin/establecimiento-curso-pie-comparacion
   ```

3. Entrar con el rol activo administrador o UATP y comprobar el botón, el selector, los dos años y las actualizaciones. Si UATP no tiene acceso al módulo PIE, revisar su asignación en el mantenedor existente de roles/módulos.
4. Comprobar primero un lote autorizado en un entorno de pruebas. No crear alumnos ni cursos ficticios en producción para validar el parche.

## Validación

- **73 pruebas PHP, 1036 aserciones correctas**: 30 casos nuevos de comparación y regresiones de PIE/importación/traspaso, permisos UATP/Planificación, indicadores 2027 y changelog. Se actualizaron dos dobles de pruebas para reconocer el método de permisos `can()` del menú actual; no se ampliaron permisos reales.
- **27 pruebas JavaScript correctas** contra el cálculo real Laravel exportado desde SQLite en memoria: JEC/SIN JEC/EPJA, cero, reglas ausentes, sumas, diferencias, límites y reversión de cantidades.
- Compilación Vite correcta, `php -l` correcto en **12 PHP/Blade**, dos rutas con los middleware requeridos, `optimize:clear`, `git diff --check` y el bloque de instalación con `bash -n` correctos. Se repitieron los 31 casos de comparación/compilación Blade tras el ajuste final de estilos. Las advertencias Sass/fuentes son anteriores al parche.
- El inventario confirma **21 archivos** (13 nuevos y 8 modificados). El manifest sólo agrega CSS/JavaScript de comparación y el changelog histórico permanece idéntico. `.env` y los tres PDF locales previos conservan sus hashes y quedan fuera del parche.
- Chrome: edición de ambos años, recálculo inmediato, un único guardado conjunto y persistencia tras recargar; bloqueo por matrícula y navegación por teclado. Móvil 390 × 844 sin desbordamiento de página, con tabla de desplazamiento local. Se usó exclusivamente una aplicación de prueba con datos ficticios y SQLite en memoria; sin correos, WhatsApp ni acceso a la base real.

Pendiente: despliegue cPanel y comprobación de concurrencia con MySQL real. Las pruebas de bloqueo/revisión y rollback locales no equivalen a una prueba de múltiples conexiones MySQL.

## Archivos del parche

Nuevos:

- `app/Http/Controllers/Admin/PieComparisonController.php`
- `app/Services/Pie/PieComparisonService.php`
- `routes/pie_comparison.php`
- `resources/views/admin/establecimiento-curso-pie/comparison.blade.php`
- `resources/css/pie-comparison.css`
- `resources/js/pie-comparison.js`
- `resources/js/pie-comparison-calculation.js`
- `public/build/assets/pie-comparison-C7ws-Z4O.css`
- `public/build/assets/pie-comparison-CQRO3NbM.js`
- `tests/Feature/PieComparisonTest.php`
- `tests/Support/pie-comparison-fixture.php`
- `tests/js/pie-comparison.test.mjs`
- `docs/PIE_COMPARACION_ANIOS.md`

Modificados:

- `app/Providers/AppServiceProvider.php`
- `app/Support/PieHorasCalculator.php`
- `resources/views/admin/establecimiento-curso-pie/index.blade.php`
- `tests/Feature/CoordinadorUatpDotacionAccessTest.php`
- `tests/Feature/SupervisorPlaniDotacionAccessTest.php`
- `vite.config.js`
- `public/build/manifest.json`
- `config/changelog.php`
