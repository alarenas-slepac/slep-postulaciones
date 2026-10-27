# Escrutinios diarios de CCAF y Consulta Mutual

En **Votaciones → Resultados diarios**, la pantalla **Resultados y consolidación** permite al administrador registrar **un total general de todos los locales por jornada y fecha**, guardar varios días, consolidarlos y publicar un único resultado general. No se registra un voto individual, no se pregunta qué eligió una persona y no se modifica el padrón de consulta ni el seguimiento territorial.

## Papeletas

| Papeleta | Opciones | Proceso de la jornada |
| --- | --- | --- |
| Desafiliación CCAF | Sí / No a desafiliarse de CCAF Los Andes | `CCAF` |
| Afiliación CCAF | Los Héroes / 18 de Septiembre / La Araucana | `CCAF` |
| Consulta Mutual | ACHS / ISL / IST / Mutual | `CONSULTA_MUTUALIDADES` |

Cada papeleta registra además **nulos y blancos**. Votos válidos = suma de sus opciones. Total de papeletas = válidos + nulos + blancos. Los porcentajes se calculan sobre los válidos de cada papeleta; con cero válidos se muestra 0 %. No se suman las tres papeletas para calcular personas ni se presupone que deban tener igual total. No se aplican umbrales legales ni se proclaman ganadores.

### Participación y animaciones

En la vista pública y la revisión administrativa del consolidado se muestra el avance respecto del padrón total de la jornada: **cantidad de votantes, personas del padrón y porcentaje**. Administración también muestra la participación del día seleccionado cuando ya está guardado; los cambios sin guardar del formulario no alteran ese indicador. Por definición de este informe, votantes es el **mayor total de papeletas**, incluyendo nulos y blancos, del período mostrado. Para un día se comparan sus papeletas; para el acumulado o consolidado se suman primero los días por papeleta y se elige el mayor total acumulado. No se suman papeletas distintas ni máximos diarios.

El padrón corresponde a la **carga vigente de esa jornada**, contando cada RUT normalizado una sola vez aunque tenga varias asignaciones a establecimientos. Se consulta únicamente el agregado y no se envían RUT ni nombres a la página. Los resultados públicos siguen usando sólo los días y conteos de la versión publicada; las cargas o correcciones pendientes de resultados no alteran el numerador público. Si se actualiza el padrón, cambia el denominador vigente mostrado.

Con padrón ausente o vacío se conservan los votantes y se muestra «—» en el porcentaje. Si los votantes superan al padrón, se informa la inconsistencia, se conserva el porcentaje real y se limita el relleno al 100 %. No se modifica ni rechaza un escrutinio histórico por este indicador.

Las barras, votos, totales y porcentajes se animan una sola vez al entrar en pantalla, con el patrón documentado en `docs/DESIGN_SYSTEM.md`. La duración es un segundo y no se altera la base de cálculo de las opciones: sus porcentajes siguen usando votos válidos. Sin JavaScript, con movimiento reducido o al imprimir, los resultados finales permanecen legibles. Se reutilizan los tonos azules institucionales y los paneles claros existentes.

El catálogo está en `config/votaciones.php`, sección `papeletas`. Las claves son persistentes: no renombrarlas o reutilizarlas para opciones distintas si existen resultados históricos. Una jornada con sólo un proceso presenta únicamente las papeletas correspondientes. No es necesario volver a importar el Excel.

Los días ya guardados conservan sus papeletas aunque posteriormente se cambien los procesos de la jornada. Las nuevas fechas utilizan los procesos vigentes; los acumulados leen las papeletas efectivamente registradas, sin descartar las anteriores.

## Ingreso y correcciones

1. En **Registrar días**, seleccionar la jornada y la fecha y pulsar **Abrir día**. Se admite una fecha real hasta el día actual de `America/Santiago`, sin fechas futuras. La fecha de referencia de la jornada no limita las múltiples fechas de votación.
2. Completar **todas** las cantidades como enteros de 0 a 1.000.000, incluyendo ceros expresos. El navegador calcula los totales al instante; el servidor valida los valores.
3. Pulsar **Guardar escrutinio del día**. Seleccionar otra fecha arriba y repetir. Por ejemplo, guardar el 23, 26, 27, 28 y 29 de octubre después del cierre de cada fecha. Los días anteriores se conservan; guardarlos no publica resultados.
4. Revisar **Días registrados que se van a consolidar**. La tabla presenta cada fecha, los totales de cada papeleta y la suma general. Se incluyen **todos los días guardados de esa jornada**, aunque sigan en borrador. La aplicación no supone que ya se hayan ingresado todas las fechas previstas: el administrador debe comprobar la lista.
5. Pulsar **Consolidar todos los días**. Se prepara una versión que conserva fechas, opciones y sumas. Revisar las tres papeletas consolidadas y pulsar **Publicar consolidado**. No hay que publicar cada día por separado.
6. Para corregir, pulsar **Editar día**, modificar y guardar. **El público conserva la última versión publicada**, sin incluir las correcciones ni nuevas fechas pendientes. Volver a consolidar y publicar para actualizar los resultados. Si alguien modifica los días entre la revisión y la publicación, se rechaza la versión desactualizada y se pide reconsolidar.

La combinación jornada/fecha es única. Las operaciones bloquean la jornada en una transacción y usan una versión del registro diario y una huella de los datos consolidados. Si otro administrador cambió el día, el formulario anterior se rechaza con un aviso de recarga; nunca se acumula el mismo envío ni se sobrescribe silenciosamente. Repetir la consolidación de los mismos datos reutiliza la versión ya preparada; repetir una publicación activa no duplica la bitácora. No hay acción de eliminación. Guardados, correcciones, consolidaciones, publicaciones y retiros quedan en la bitácora existente. Un fallo de bitácora revierte la operación completa.

## Publicación

La vista **`/resultados-votacion?jornada=<slug>`** no requiere login. Presenta la última versión del **consolidado publicado**, con todas las fechas que incluía al prepararlo. Permite consultar el total general o el detalle de una fecha incluida, mediante los botones o `&fecha=AAAA-MM-DD`. El detalle diario se calcula sobre la misma copia publicada; no mezcla borradores posteriores.

Guardar días y preparar un consolidado no publica. **Publicar consolidado** sustituye la versión pública anterior de forma atómica y conserva su copia para historial. **Retirar consolidado publicado** oculta los resultados hasta una nueva publicación; no hace reaparecer las publicaciones diarias antiguas. La publicación de resultados es independiente de la consulta por RUT y del seguimiento territorial. Una jornada suspendida no muestra resultados públicos. El enlace de una jornada sin publicación devuelve 404; la portada sin parámetros informa que aún no hay resultados. No se exponen observaciones, usuarios, datos del padrón ni revisiones pendientes, y las respuestas no se almacenan en caché HTTP. La consulta del local incluye un enlace a los resultados en el pie de página.

Las publicaciones diarias realizadas con el parche anterior se conservan y se siguen mostrando hasta publicar el primer consolidado. Las rutas diarias antiguas permanecen por compatibilidad, pero una jornada que ya preparó una consolidación no admite nuevas publicaciones diarias por esa vía. Al publicar el primer consolidado se usan todos los días registrados, independientemente de sus marcas diarias históricas.

Las copias y sus versiones se guardan en `votacion_resultados_consolidados`. Las fechas y el resumen se serializan como JSON en columnas TEXT para conservar el orden de las papeletas y opciones en MySQL. El modelo impide modificar el contenido de una versión o eliminarla; los cambios de votos requieren una versión nueva. Las etiquetas del consolidado publicado no cambian si después se modifica el catálogo de papeletas.

Las rutas de ingreso y publicación exigen autenticación, correo verificado, rol **admin**, acceso al módulo y CSRF. Tener permisos operativos de votaciones con otro rol no habilita estas acciones. Se reutilizan roles existentes sin añadir permisos ni eventos de notificación.

## Logos en la consulta por RUT

Se muestra el logo del local de votación y del lugar de trabajo. Ambos se obtienen de `admision_establecimientos.logo_path` mediante `Establecimiento.admisionPerfil.logoUrl()`, como en Centro de Operaciones. El destino se busca por `RBD_LUGAR_DE_VOTACION` y el origen por el `RBD` del padrón, nunca por el ID interno del establecimiento. Si falta el catálogo o el logo, aparece un icono de establecimiento. Completar el perfil de Admisión existente actualiza la siguiente consulta. No se copian imágenes al padrón ni se crean columnas para logos.

## Despliegue en cPanel

Requiere las tablas existentes del módulo de Votaciones y `votacion_resultados_diarios` del parche anterior. Esta ampliación agrega `votacion_resultados_consolidados`, sin modificar ni borrar los resultados diarios existentes y sin variables `.env` nuevas. Desplegar código, respaldar la base de datos y revisar primero las pendientes:

```bash
cd /home/slepac/apps/slep_postulaciones
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate:status
# Si las migraciones anteriores de Votaciones ya están aplicadas:
# Sólo si la tabla diaria aún no está instalada:
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --path=database/migrations/2026_10_22_010000_create_votacion_resultados_diarios.php --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --path=database/migrations/2026_10_22_020000_create_votacion_resultados_consolidados.php --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan route:list --path=resultados
```

La ampliación de participación y animaciones no requiere migraciones adicionales a las ya indicadas, dependencias nuevas ni colas. Compilar con `npm run build` y desplegar también el manifiesto y los recursos de `public/build`, incluidos `progreso-animado` y el CSS de resultados. Para logos, mantener el disco de medios de Admisión y su acceso público ya utilizados por el sistema. Un rollback eliminaría los escrutinios: no forma parte de la actualización habitual.

Antes de cargar datos reales, comprobar en un entorno de pruebas: guardar al menos tres fechas sin publicación pública, consolidar las tres, revisar sumas y publicar en una acción; añadir/corregir un día y comprobar que la versión pública permanece igual; volver a consolidar y publicar; consultar una fecha y retirar el consolidado. Las pruebas automatizadas usan exclusivamente SQLite en memoria, datos ficticios y almacenamiento temporal; no envían mensajes.
