# Asignación de horas y prelación en Dotación 2027

Parche `2026.10.12.594`. Estas reglas se aplican al proceso 2027 y reutilizan el orden de prelación, tramo y antigüedad calculado por el SGA.

## Reglas por destino

| Destino de las horas | Docentes que aparecen | Efecto de la prelación |
| --- | --- | --- |
| Libre disposición de NT1/NT2 Con JEC, incluida la agrupación de cursos | Docentes asociados a la asignatura que tengan saldo suficiente | Orden informativo: se puede elegir un especialista aunque una Educadora de Párvulos aparezca primero. |
| Bolsa Educadoras Diferenciales PIE, cobertura docente | Educación Diferencial y personas sin título declarado | Obligatoria, calculada sólo entre los candidatos elegibles para esta bolsa. Un título de otra especialidad no bloquea a una Educadora Diferencial. |
| Funciones directivas, técnico-pedagógicas, planes normativos y otras funciones | Docentes con saldo para el bloque seleccionado; conserva los cupos existentes | Orden informativo: un docente de menor prioridad puede recibir la función, tanto al crear como al editar. |
| Otras asignaciones curriculares, trabajo colaborativo PIE y acompañamiento | Candidatos según las reglas existentes | Conserva la prelación y los requisitos existentes. |

### Libre disposición de Parvularia

El selector mantiene el orden del proceso y permite seleccionar, por ejemplo, al profesor de Inglés situado detrás de las educadoras. Debe estar asociado a esa asignatura en la etapa correspondiente y contar con saldo contractual suficiente. Se conserva el mínimo de una hora cronológica y la conversión contractual según el perfil del docente.

La excepción se determina por la necesidad real del plan, no por un subtipo enviado desde el navegador. Los componentes obligatorios conservan titularidad primero y prelación. Los cursos Sin JEC conservan la regla vigente que no permite asignar libre disposición; este parche se limita a Con JEC.

### Bolsa Educadoras Diferenciales

La profesión de la declaración del sostenedor es la fuente autoritativa. El SGA reconoce las variantes de Educación Diferencial mediante `DotacionProfesionDocenteResolver`. La excepción por ausencia de título acepta un valor vacío o los marcadores del sistema `Sin título` y `Sin título declarado`, con normalización de mayúsculas, espacios y tildes. Un texto arbitrario como `Título pendiente de validación` no equivale a ausencia de título.

Dentro de estos candidatos se conserva la prelación: un docente sin título de mayor prioridad puede bloquear a un docente diferencial de menor prioridad hasta completar su saldo. Un título declarado de Inglés, Educación Física o Educación de Párvulos queda fuera del selector y no participa en ese bloqueo. Para corregir una clasificación, revisar la declaración del docente; el parche no reescribe títulos.

La validación se ejecuta también en el servidor al crear y editar, por lo que cambiar manualmente el formulario no permite asignar la bolsa a otro título. La modalidad de cobertura con asistentes y los cupos por contratar conservan sus reglas vigentes.

### Funciones

Los tipos `funcion_directiva`, `funcion_tecnico_pedagogica`, `plan_normativo` y `otra_funcion` conservan el listado ordenado. La guía del selector indica que ese orden es informativo y no bloquea la selección. También se actualiza la guía del selector de Director ADP.

Permanecen las validaciones de saldo, bloque contractual, máximo individual, horas definidas de las funciones normativas y avance de las etapas. La excepción no convierte una reserva ni una asignación curricular en una función, ni habilita permisos adicionales.

## Compatibilidad y archivos

Se mantienen las asignaciones guardadas y los datos históricos. No se realizan migraciones ni correcciones automáticas. Al editar una asignación 2027 se comprueba su elegibilidad actual.

Archivos del parche, conservando sus rutas:

- `app/Http/Controllers/Admin/DotacionAsignacionController.php`
- `app/Support/DotacionAsignacionElegibilidad2027.php`
- `resources/views/admin/dotacion-establecimiento/partials/_asignacion.blade.php`
- `tests/Feature/DotacionLibreDisposicionParvulariaTest.php`
- `tests/Unit/DotacionAsignacionElegibilidad2027Test.php`
- `config/changelog.php`
- `docs/DOTACION_ASIGNACION_PRELACION_2027.md`

## Validación

Las pruebas usan SQLite en memoria y personas ficticias. Cubren un profesor de Inglés tercero en el orden, creación y reasignación de los cuatro tipos de funciones, límites de funciones normativas, filtrado por título, prioridad dentro de la bolsa y rechazo de títulos ajenos tanto al crear como al editar. También mantienen las regresiones de titularidad primero, Sin JEC, horas disponibles y selección libre en cursos combinados.

Pruebas específicas:

```powershell
& 'C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe' vendor/phpunit/phpunit/phpunit --filter 'DotacionLibreDisposicionParvulariaTest|DotacionAsignacionElegibilidad2027Test|DotacionPlanTitularPrimeroTest|DotacionAsignacionViewTest' --do-not-cache-result
```

Resultado local con PHP 8.3: **47 pruebas específicas, 210 aserciones**, y **546 pruebas de regresión, 6.284 aserciones**, todas correctas. La regresión incluye dotación, horas no lectivas, accesos de coordinación y planificación y changelog. La ejecución utiliza una configuración aislada para evitar conexiones a la base de datos local.

También pasan `php -l` en los seis archivos PHP/Blade y `git diff --check`; se ejecutó `artisan optimize:clear` localmente. La revisión en Chrome confirmó el filtro de dos candidatos PIE, selección por teclado de un docente de menor prioridad en funciones y selección de un especialista en libre disposición. La vista móvil de 390 × 844 conserva los selectores y no desborda el ancho de la página. Se usó una vista previa con datos ficticios y envíos de formularios bloqueados; el servidor temporal y la vista previa se retiraron al finalizar.

Para revisar visualmente el HTML real de los selectores con datos ficticios, la prueba `test_selector_bolsa_filtra_perfiles_y_funcion_conserva_orden_informativo` permite exportar una vista previa a `storage/framework/testing/prelacion-2027-preview.html` definiendo `SGA_PRELACION_PREVIEW=1` sólo durante esa prueba. La vista previa impide enviar los formularios. Es un archivo de diagnóstico ignorado por Git que debe eliminarse después de la revisión.

## Despliegue en cPanel

Después de publicar el parche, actualizar el código mediante el procedimiento habitual y ejecutar:

```bash
cd /home/slepac/apps/slep_postulaciones
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan view:cache
```

No se requieren cambios en `.env`, instalación de dependencias, migraciones ni compilación de CSS/JavaScript. Se modifican únicamente reglas PHP y el contenido de los selectores Blade.

Comprobación tras desplegar, con un usuario que tenga permiso para asignar en ese establecimiento:

1. En un curso NT Con JEC, abrir libre disposición y verificar que se puede seleccionar un especialista asociado aunque las educadoras aparezcan primero.
2. En la bolsa PIE con cobertura docente, verificar Educación Diferencial y ausencia de título; los docentes de otras especialidades no deben aparecer.
3. En una función, verificar que el selector mantiene el orden y permite un docente de menor prioridad con saldo, dentro de las horas definidas.
4. Verificar que los componentes obligatorios siguen respetando titularidad primero y prelación.

El despliegue y estas comprobaciones en el servidor quedan a cargo del usuario; el desarrollo y las pruebas de este parche no modifican producción.
