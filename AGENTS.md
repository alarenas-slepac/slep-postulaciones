# Sistema SGA SLEP Andalién Costa

## Stack

- Laravel 12
- PHP 8.3
- MySQL
- Bootstrap 5
- Vite
- Spatie Permission
- jQuery / Select2
- PhpSpreadsheet
- Producción en cPanel

## Reglas obligatorias

1. Nunca modificar ni versionar `.env`.
2. Nunca incluir credenciales, claves SMTP o datos personales reales.
3. No eliminar rutas, controladores, relaciones de modelos o permisos existentes
   sin verificar previamente sus dependencias.
4. Antes de modificar un archivo, revisar su versión completa y buscar usos,
   rutas, vistas, controladores, servicios y pruebas relacionadas.
5. Los cambios deben ser compatibles con PHP 8.3 y Laravel 12.
6. No ejecutar migraciones destructivas sin autorización.
7. No ejecutar `migrate:fresh`, `db:wipe` o comandos equivalentes.
8. No modificar directamente producción.
9. Trabajar siempre en una rama distinta de `main`.
10. Mantener compatibilidad con datos históricos.
11. Los parches deben contener solamente archivos nuevos o modificados,
    conservando sus rutas relativas.
12. Actualizar `config/changelog.php` cuando el cambio corresponda a un parche.

## Validaciones antes de entregar

- Ejecutar `php -l` sobre todos los PHP modificados.
- Ejecutar pruebas relacionadas cuando existan.
- Ejecutar `php artisan route:list` si se modifican rutas.
- Ejecutar `php artisan optimize:clear`.
- Ejecutar `npm run build` cuando se modifiquen CSS o JavaScript compilados.
- Revisar `git diff`.
- Confirmar que `.env`, `vendor`, `node_modules`, logs y archivos de usuarios
  no estén incluidos.

## Producción

- Ruta productiva: `/home/slepac/apps/slep_postulaciones`
- En producción, Artisan debe ejecutarse con PHP 8.3.
- El Composer predeterminado del servidor puede utilizar una versión PHP inferior.
- No asumir que `$PHP83` estará disponible en procesos no interactivos.
- Usar la ruta absoluta del binario PHP 8.3 en scripts de despliegue.