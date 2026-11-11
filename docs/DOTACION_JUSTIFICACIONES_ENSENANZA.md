# Nivel y áreas de enseñanza en justificaciones

En Dotación Establecimiento → Detalle sobredotación → Contrato sin asignación
registrada, cada formulario Justificar incorpora un nivel de enseñanza y una o más
áreas de enseñanza. La selección pertenece al docente, establecimiento, año,
bloque y calidad (titular o contrata) del fundamento; no cambia su perfil personal.

Los niveles son Enseñanza Básica, Enseñanza Media, Educación de Párvulos, EPJA,
Educación Especial y PIE. Las áreas proceden de `areas_desempeno`, con los mismos
filtros del perfil de postulante: `estamento = docente` y `activo = true`.
No se limita el catálogo por el nivel elegido ni se incluyen áreas de AAEE.

Al crear o actualizar se exige nivel, al menos un área y fundamento. La validación
del servidor rechaza niveles desconocidos, áreas duplicadas, de AAEE, inactivas o
inexistentes. Conserva los permisos y el cálculo de horas existentes.

La migración `2026_11_11_140000_add_ensenanza_to_dotacion_justificaciones` agrega dos
columnas anulables, comprobadas de forma independiente para permitir reintentos
tras un fallo parcial de DDL en MySQL. No crea índices ni restricciones nuevos.
Su reversión no elimina los antecedentes guardados.

Los registros históricos no reciben valores inventados ni pierden su vigencia por
carecer de los nuevos campos. La consulta los muestra como no informados hasta
que se actualicen. Cada área guarda su ID y nombre al justificar; cambiar o
desactivar el catálogo no altera ese antecedente en consultas o informes.
Al editar se ofrecen las áreas activas y se avisa de las selecciones históricas
fuera del catálogo para que el usuario las revise.

El nivel y los nombres se incorporan al detalle de justificación en las nóminas
y exportaciones Excel/PDF, y se copian en los antecedentes de nuevas distribuciones
de Docentes a disposición. Las distribuciones previas conservan su copia original.

El selector múltiple usa el componente institucional de Select2 y conserva el
control HTML utilizable sin JavaScript. El desplegable se ubica fuera de las
tablas para que sus opciones no queden recortadas.
En móvil, las filas de horas vacantes se presentan como tarjetas y los
formularios se apilan para mantener accesibles sus campos y acciones.

Tras desplegar código y recursos compilados en cPanel:

```bash
cd /home/slepac/apps/slep_postulaciones
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
```
