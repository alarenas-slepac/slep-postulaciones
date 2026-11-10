# Recuperación de la migración del padrón definitivo en cPanel

El error MySQL `1059 Identifier name ... is too long` proviene del índice
automático de `votacion_padrones_definitivos` sobre `jornada_votacion_id` y
`publicado_at`. Su nombre tenía 68 caracteres; MySQL admite
[hasta 64](https://dev.mysql.com/doc/mysql-reslimits-excerpt/8.0/en/identifier-length.html).
El parche usa `vpd_jornada_publicado_idx` y una FK explícita `vpd_jornada_fk`
para nuevas instalaciones. Las claves foráneas ya creadas se conservan.

MySQL puede haber creado la tabla y la FK antes del fallo del índice. La
migración corregida completa el índice al reintentar, sin borrar datos.
Una segunda migración también lo completa si un reintento anterior ya había
registrado la primera como aplicada sin crear el índice.

## Reanudar el despliegue

Primero actualizar el repositorio del servidor y desplegar el parche mediante
el procedimiento habitual. No ejecutar solamente `artisan up` tras este error:
faltan las migraciones y las etapas finales del despliegue.

Para comprobar las migraciones, el operador puede ejecutar en la terminal:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php /home/slepac/apps/slep_postulaciones/artisan migrate:status
```

El script `scripts/production/deploy_slep_postulaciones.sh` vuelve a ejecutar
`migrate --force`, prepara las cachés y ejecuta `artisan up` únicamente después
de completar las etapas anteriores. El operador debe reanudar ese procedimiento
con el código corregido. No eliminar la tabla ni editar manualmente el registro
de migraciones.

## Prevención

Asignar nombres explícitos de hasta 64 caracteres a índices, índices únicos y
claves foráneas. Verificar los identificadores del SQL compilado para MySQL:
una prueba ejecutada únicamente en SQLite no detecta este límite. Cubrir además
el reintento cuando la tabla ya existe pero falta el índice.
