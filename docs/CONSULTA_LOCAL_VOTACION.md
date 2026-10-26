# Consulta pública de local de votación

La dirección `/consulta-local-votacion` permite buscar por RUT, sin iniciar sesión, las asignaciones para la elección de caja de compensación y la consulta de mutualidad. Muestra nombre, establecimiento donde trabaja la persona, calidad jurídica, grupo, local de destino, RBD del local, día y hora de inicio. No registra votos ni importa personas al padrón de personal del SGA.

## Preparar la jornada

1. Como administrador, entra en **Votaciones CCAF y Mutualidades → Jornadas → Nueva jornada**.
2. Define nombre, fecha de referencia y slug. Selecciona Caja de Compensación y Consulta de Mutualidades si comparten padrón y horarios. Para padrones distintos, crea jornadas separadas, cada una con su proceso correspondiente.
3. En **Padrón y locales**, selecciona la jornada y carga el Excel.
4. Revisa el diagnóstico y los locales cargados. Luego pulsa **Publicar consulta por RUT** y comparte el enlace mostrado.

La publicación de la consulta es independiente de la publicación del seguimiento territorial: no exige configurar grupos operativos ni rutas. La jornada puede continuar en borrador operativo. Una jornada suspendida no aparece en la consulta. Si todos sus procesos se desactivan, tampoco aparece. **Retirar consulta pública** deshabilita la búsqueda para esa jornada.

## Estructura del Excel

Archivo `.xlsx`, máximo 10 MB, 20.000 filas de padrón y 2.000 filas de locales. Encabezados en la fila 1; una persona/asignación por fila, sin celdas combinadas ni fórmulas. Se aceptan encabezados reordenados, espacios exteriores, espacios en lugar de guiones bajos, mayúsculas/minúsculas, tildes y el alias `TIPO_DE_CONTRAT0` (cero final).

Hoja **PADRON VOTACIÓN** (también se acepta **PADRON**):

| Columna | Contenido |
| --- | --- |
| RUT | RUT completo, con dígito verificador correcto. Con o sin puntos y guion; K o k. No se calcula un dígito faltante ni se corrige uno incorrecto. |
| NOMBRE | Nombre completo. |
| RBD | Identificador numérico del establecimiento donde trabaja. |
| ESTABLECIMIENTO | Nombre del establecimiento donde trabaja. |
| TIPO_DE_CONTRATO | Calidad jurídica, hasta 150 caracteres. |
| GRUPO | Identificador/nombre del grupo, hasta 100 caracteres. Puede estar vacío: se muestra «Por confirmar». |
| RBD_LUGAR_DE_VOTACION | Identificador numérico del establecimiento al que debe dirigirse. |
| LUGAR_DE_VOTACION | Nombre del local de destino. |

Hoja **LOCALES VOTACION**:

| Columna | Contenido |
| --- | --- |
| RBD | Establecimiento de origen. |
| ESTABLECIMIENTO | Nombre del establecimiento de origen. |
| RBD_LUGAR_DE_VOTACION | Identificador del local de destino. |
| LUGAR_DE_VOTACION | Nombre oficial del local que se mostrará en la consulta. |
| HORARIO | Hora de inicio, por ejemplo `09:00`, `14:30`, `09:00 hrs.`, o una hora nativa de Excel. No es un intervalo ni una hora de término. |
| DIA | Fecha completa, preferentemente `2026-10-28`, o fecha nativa de Excel. También admite `28-10-2026` y `28/10/2026`. |

Cada fila del padrón se relaciona con una fila de locales mediante **RBD + RBD_LUGAR_DE_VOTACION**. Un mismo destino puede tener horarios distintos para distintos establecimientos de origen. Para una misma pareja de RBD no se admiten días u horarios contradictorios: se informa el número de ambas filas y se debe corregir el Excel. Las diferencias de nombre no cambian la identidad establecida por los RBD; el nombre de destino usado públicamente procede de la hoja de locales.

Las fechas de texto `MM/DD/AAAA` se detectan si al menos una de ellas tiene un día mayor a 12 en la segunda posición. El criterio se aplica a todas las fechas con `/` de esa hoja y el diagnóstico informa la interpretación. Sin esa evidencia se usa `DD/MM/AAAA`. No mezclar ambos órdenes. Preferir fechas nativas de Excel o `AAAA-MM-DD` para evitar ambigüedad.

## Validación y reemplazo

### Plantillas y actualización de algunos votantes

En **Padrón y locales**, seleccione la jornada y pulse **Descargar plantilla de padrón y locales** para preparar una importación completa. Incluye los encabezados requeridos, los locales existentes y una hoja de instrucciones. La carga completa conserva su confirmación de reemplazo.

Para cambiar sólo parte del padrón, despliegue **Actualizar o agregar algunos votantes mediante Excel**:

1. Marque los campos que desea tratar: Nombre, Tipo de contrato, Grupo y Establecimiento de origen/local.
2. Mantenga **Precargar la plantilla** para obtener las asignaciones actuales, o desmárquelo para un archivo vacío. Pulse **Descargar plantilla de actualización**.
3. En **ACTUALIZAR PADRON**, conserve sólo las filas que desea cambiar. No altere su ID_REGISTRO ni RUT. Para un RUT nuevo deje el ID vacío y complete Nombre, Tipo de contrato y RBD de origen/local. Seleccione esos tres campos en la pantalla para incorporar votantes nuevos.
4. Si no se informa ID, un RUT existente sólo se actualiza cuando tiene una única asignación en esa jornada. Las coincidencias múltiples se omiten y se informan en el reporte. Un ID inexistente o que no corresponde al RUT/jornada nunca se utiliza para crear otro registro.
5. Seleccione el archivo, confirme la operación y pulse **Aplicar cambios y descargar reporte**. Obtendrá un Excel con el resultado de cada fila. La pantalla muestra los conteos; si el navegador conserva la vista anterior después de la descarga, pulse **revise el estado de la consulta**.
6. Revise los resultados y vuelva a publicar la consulta si se actualizó o agregó algún votante. Una carga sin cambios efectivos conserva su publicación.

Sólo se modifican los campos seleccionados. Grupo vacío quita el grupo; otros valores seleccionados son obligatorios. Los RBD deben corresponder a una pareja de origen/local ya cargada en la jornada: de ella se obtienen el nombre del establecimiento, local, día y horario. La hoja **LOCALES VOTACION** de la plantilla de actualización es sólo una referencia y no se importa. Para cambiar locales compartidos se utiliza la importación completa existente.

La actualización admite hasta 20.000 filas y 10 MB, sin fórmulas. Una estructura inválida bloquea la carga completa; los problemas de una fila se informan como omitidos sin alterar ese registro. Las identificaciones repetidas se omiten, y se impide duplicar otra asignación. Los demás votantes, locales y jornadas se conservan. La transacción comparte el bloqueo de jornada con importación y publicación, escribe en lotes y revierte el conjunto ante un fallo de base o bitácora. El reporte contiene únicamente los identificadores aportados por el administrador, se descarga de forma privada y no se conserva en archivos públicos, sesión ni logs. La sesión y la bitácora sólo contienen conteos y campos seleccionados.

### Importación completa

- Cualquier error bloqueante impide modificar los datos existentes. Se muestran hasta 100 observaciones con hoja y fila, además del total de errores.
- Se validan obligatoriedad, longitud de texto, RBD numéricos, dígito verificador, fechas reales, horas y correspondencia entre hojas. Las fórmulas se rechazan sin calcularlas.
- Se omiten filas idénticas de padrón. Varias filas para un mismo RUT se conservan cuando representan asignaciones diferentes; la consulta presenta todas sus asignaciones de la jornada.
- En locales se omiten duplicados de la misma pareja de RBD con idéntico día y hora. Los nombres no se usan como clave.
- El grupo vacío no bloquea la carga. Debe completarlo la organización cuando corresponda; no se deduce desde otros módulos ni se inventa.
- Para sustituir una carga existente hay que marcar la confirmación. Se reemplazan los locales y votantes de esa jornada en una transacción con bloqueo; un error revierte el conjunto. La consulta queda sin publicar después de cada importación válida y exige una nueva revisión/publicación.
- El Excel se procesa desde el archivo temporal de la carga y no se copia a un directorio público ni a Git. La bitácora registra conteos y acciones, sin RUT, nombres ni contenido de las filas. El diagnóstico no incluye datos personales.

## Acceso y protección

### Dirección y mapa del destino

El resultado busca el **RBD_LUGAR_DE_VOTACION** en `establecimientos.rbd`. La relación utiliza el RBD del destino, no el RBD de origen ni `establecimientos.id`. Obtiene `latitud`, `longitud` y `comuna` desde ese catálogo. En la estructura actual, la dirección está en `admision_establecimientos.direccion_publica`, relacionada mediante `establecimiento_id` y `Establecimiento.admisionPerfil`; es la misma dirección pública utilizada por los avisos al reemplazante. No se crea otra copia de esos datos en el padrón de votación.

El mapa usa Leaflet y OpenStreetMap, como Centro de Operaciones. Muestra únicamente el punto del local y ofrece **Cómo llegar** para abrir las coordenadas en Google Maps. No solicita la geolocalización del funcionario. Los datos del mapa no incluyen RUT, nombre de la persona, contrato ni información de otros votantes. Las teselas usan `referrerPolicy: origin`, por lo que sólo envían el origen del sitio, conservando el `no-referrer` del resto de la consulta. Se mantiene visible la atribución de OpenStreetMap.

Si no hay coincidencia de RBD, la consulta conserva el local y horario del padrón e indica **Ubicación en mapa por confirmar**. Si falta la dirección, muestra **Dirección por confirmar**. Las coordenadas fuera de rango, incompletas y el par `0,0` no generan un marcador. Ante errores del proveedor del mapa, sigue disponible la dirección y el enlace externo cuando hay coordenadas válidas.

Para corregir coordenadas o comuna, actualiza el establecimiento en el catálogo existente. Para completar la dirección, usa su perfil de Admisión Escolar. Los cambios se reflejan en la siguiente consulta sin volver a importar el Excel. Esta ampliación no requiere migraciones, paquetes ni variables `.env` adicionales; desplegar también el nuevo JavaScript y los recursos compilados de `public/build`.

La tarjeta muestra también los logos del local de votación y del establecimiento donde trabaja la persona. Reutiliza `admision_establecimientos.logo_path` y `logoUrl()` del perfil asociado al catálogo. Cada imagen se busca por su RBD respectivo; cuando falta el establecimiento o su logo, se muestra un icono institucional de edificio. No se copian imágenes al padrón.

El pie de página enlaza a **Resultados de votación**, que muestra el consolidado publicado por administración y conserva las publicaciones históricas. Permite registrar varios días y luego consolidarlos y publicarlos en una sola acción. Su operación y migraciones se detallan en `docs/RESULTADOS_VOTACIONES.md`.

### Consulta y administración

Las rutas administrativas exigen autenticación, correo verificado, rol `admin` y el control de módulo existente. La consulta pública acepta solamente búsquedas exactas por RUT válido y jornada publicada para consulta. No hay listado público, búsqueda parcial, exportación ni API que entregue el padrón completo.

La búsqueda usa POST con protección CSRF, hasta 15 intentos por minuto e IP y un límite general de 60 peticiones por minuto. No incorpora RUT en enlaces ni resultados en la sesión. Las respuestas usan `no-store`, `noindex`, `nofollow`, `noarchive` y `no-referrer`. La consulta por RUT solamente es una decisión de acceso del módulo: no autentica la identidad de quien consulta. El proveedor debe excluir esta ruta de cualquier caché de HTML del proxy/CDN y evitar registrar cuerpos POST en herramientas de depuración.

## Instalar localmente y en cPanel

Requiere PHP 8.3, Laravel 12, la dependencia PhpSpreadsheet ya instalada y extensiones ZIP/XML existentes. No incorpora paquetes nuevos, colas, servicios Node ni variables `.env`.

Tras desplegar el código y los recursos compilados, respaldar la BD y revisar las migraciones pendientes. La migración de este módulo sólo agrega `consulta_publica` (desactivada por defecto) a `jornadas_votacion` y crea `votacion_locales_consulta` y `votacion_padron_consulta`. Depende de las tablas del módulo de votaciones de agosto de 2026.

En cPanel, con el código desplegado en la ruta productiva, ejecutar con PHP 8.3:

```bash
cd /home/slepac/apps/slep_postulaciones
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate:status
# Tras revisar las pendientes y disponer de respaldo:
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan route:list --path=consulta-local-votacion
/opt/cpanel/ea-php83/root/usr/bin/php artisan route:list --path=gestion/votaciones/admin/padrones
```

Las migraciones adicionales pendientes deben revisarse antes del comando general. No ejecutar `migrate:fresh`, `db:wipe` ni revertir el módulo para corregir un Excel. Un rollback elimina las tablas propias y sus datos: no forma parte del despliegue habitual.

Los recursos se generan con `npm run build` en el entorno de desarrollo y se despliega `public/build/manifest.json` junto con los archivos generados. Si el servidor no dispone de Node, puede usar esos recursos ya compilados. Asegurar `upload_max_filesize` y `post_max_size` compatibles con el archivo (por ejemplo 10 MB y 12 MB, respectivamente); según el plan cPanel, se ajustan desde MultiPHP INI Editor o con el proveedor.

Después de instalar, probar con datos ficticios: carga inválida sin cambios, carga válida sin publicación automática, publicación, consulta de un RUT, RUT desconocido, retiro de consulta y acceso denegado a una cuenta sin rol administrador. Las pruebas automatizadas usan exclusivamente SQLite en memoria y archivos temporales.

## Referencias técnicas

La lectura utiliza las opciones documentadas de [PhpSpreadsheet](https://phpspreadsheet.readthedocs.io/en/latest/topics/reading-files/), con lectura de valores sin evaluar fórmulas y límites de archivo, hojas, filas, columnas y tamaño descomprimido. Los controles de carga y límites HTTP usan los componentes existentes de Laravel 12.

El mapa sigue la [referencia de Leaflet 1.9.4](https://leafletjs.com/reference.html) y la [política de teselas de OpenStreetMap](https://operations.osmfoundation.org/policies/tiles/), con URL HTTPS, atribución visible y referer de origen sin información del votante.
