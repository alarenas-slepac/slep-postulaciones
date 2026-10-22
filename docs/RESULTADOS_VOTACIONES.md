# Escrutinios diarios de CCAF y Consulta Mutual

En **Votaciones → Resultados diarios**, el administrador registra **un total general de todos los locales por jornada y fecha**. No se registra un voto individual, no se pregunta qué eligió una persona y no se modifica el padrón de consulta ni el seguimiento territorial.

## Papeletas

| Papeleta | Opciones | Proceso de la jornada |
| --- | --- | --- |
| Desafiliación CCAF | Sí / No a desafiliarse de CCAF Los Andes | `CCAF` |
| Afiliación CCAF | Los Héroes / 18 de Septiembre / La Araucana | `CCAF` |
| Consulta Mutual | ACHS / ISL / IST / Mutual | `CONSULTA_MUTUALIDADES` |

Cada papeleta registra además **nulos y blancos**. Votos válidos = suma de sus opciones. Total de papeletas = válidos + nulos + blancos. Los porcentajes se calculan sobre los válidos de cada papeleta; con cero válidos se muestra 0 %. No se suman las tres papeletas para calcular personas ni se presupone que deban tener igual total. No se aplican umbrales legales ni se proclaman ganadores.

El catálogo está en `config/votaciones.php`, sección `papeletas`. Las claves son persistentes: no renombrarlas o reutilizarlas para opciones distintas si existen resultados históricos. Una jornada con sólo un proceso presenta únicamente las papeletas correspondientes. No es necesario volver a importar el Excel.

Los días ya guardados conservan sus papeletas aunque posteriormente se cambien los procesos de la jornada. Las nuevas fechas utilizan los procesos vigentes; los acumulados leen las papeletas efectivamente registradas, sin descartar las anteriores.

## Ingreso y correcciones

1. Seleccionar jornada y día; se admite una fecha real hasta el día actual de `America/Santiago`, sin fechas futuras. La fecha de referencia de la jornada no limita las múltiples fechas de votación.
2. Completar **todas** las cantidades como enteros de 0 a 1.000.000, incluyendo ceros expresos. El navegador calcula los totales al instante; el servidor valida los valores y los vuelve a calcular para las consultas.
3. Guardar el escrutinio. Queda en borrador, disponible sólo en administración.
4. Revisar las cifras guardadas y pulsar **Publicar día**. La observación interna no se publica.
5. Para corregir, abrir el día, modificar y guardar. Si cambian las cifras o la observación, se retira la publicación de ese día y exige nueva revisión/publicación. Guardar exactamente lo mismo conserva el estado.

La combinación jornada/fecha es única. Las operaciones bloquean la jornada en una transacción y usan una versión del registro. Si otro administrador cambió el día, el formulario anterior se rechaza con un aviso de recarga; nunca se acumula el mismo envío ni se sobrescribe silenciosamente. No hay acción de eliminación. Guardados, correcciones, publicaciones y retiros quedan en la bitácora existente, con cantidades anteriores/nuevas cuando se corrigen votos. Un fallo de bitácora revierte la operación.

## Publicación

La vista **`/resultados-votacion?jornada=<slug>`** no requiere login. Permite alternar entre acumulado y día publicado; también admite `&fecha=AAAA-MM-DD`. El acumulado público incluye sólo días publicados. El acumulado interno incluye borradores y publicados, expresamente identificado.

Guardar no publica. La publicación de resultados es independiente de la publicación de la consulta por RUT y del seguimiento territorial. Una jornada suspendida no muestra resultados públicos. El enlace de una jornada sin ningún día publicado devuelve 404; la portada sin parámetros informa que aún no hay resultados. No se exponen observaciones, usuarios, datos del padrón ni días en borrador, y las respuestas no se almacenan en caché HTTP. La consulta del local incluye un enlace a los resultados en el pie de página.

Las rutas de ingreso y publicación exigen autenticación, correo verificado, rol **admin**, acceso al módulo y CSRF. Tener permisos operativos de votaciones con otro rol no habilita estas acciones. Se reutilizan roles existentes sin añadir permisos ni eventos de notificación.

## Logos en la consulta por RUT

Se muestra el logo del local de votación y del lugar de trabajo. Ambos se obtienen de `admision_establecimientos.logo_path` mediante `Establecimiento.admisionPerfil.logoUrl()`, como en Centro de Operaciones. El destino se busca por `RBD_LUGAR_DE_VOTACION` y el origen por el `RBD` del padrón, nunca por el ID interno del establecimiento. Si falta el catálogo o el logo, aparece un icono de establecimiento. Completar el perfil de Admisión existente actualiza la siguiente consulta. No se copian imágenes al padrón ni se crean columnas para logos.

## Despliegue en cPanel

Requiere las tablas existentes del módulo de Votaciones. El parche añade exclusivamente `votacion_resultados_diarios`; no altera tablas históricas ni necesita variables `.env` nuevas. Desplegar código y `public/build`, respaldar la base de datos y revisar primero las pendientes:

```bash
cd /home/slepac/apps/slep_postulaciones
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate:status
# Si las migraciones anteriores de Votaciones ya están aplicadas:
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --path=database/migrations/2026_10_22_010000_create_votacion_resultados_diarios.php --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan route:list --path=resultados
```

Los recursos se generan localmente con `npm run build`; no se requieren dependencias nuevas ni colas. Para logos, mantener el disco de medios de Admisión y su acceso público ya utilizados por el sistema. Un rollback eliminaría los escrutinios: no forma parte de la actualización habitual.

Antes de cargar datos reales, comprobar con un registro ficticio en un entorno de pruebas: guardar sin publicación pública, revisar sumas, publicar, consultar por día y acumulado, corregir (debe ocultarse), republicar y retirar. Las pruebas automatizadas usan exclusivamente SQLite en memoria, datos ficticios y almacenamiento temporal; no envían mensajes.
