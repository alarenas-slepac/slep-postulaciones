#!/bin/bash

set -Eeuo pipefail

REPO="/home/slepac/repositories/slep-postulaciones"
APP="/home/slepac/apps/slep_postulaciones"
BACKUPS="/home/slepac/backups/slep_postulaciones"
PUBLIC="/home/slepac/public_html"
PHP83="/opt/cpanel/ea-php83/root/usr/bin/php"
COMPOSER="/home/slepac/bin/composer"
MAINTENANCE_VIEW_SOURCE="$REPO/resources/views/errors/503.blade.php"
MAINTENANCE_VIEW_TARGET="$APP/resources/views/errors/503.blade.php"
MAINTENANCE_ACTIVE=0

STAMP="$(date +%Y%m%d_%H%M%S)"
TIMESTAMP="$(date +%Y%m%d_%H%M%S)"

on_exit() {
    local status=$?

    if [[ "$status" -ne 0 && "$MAINTENANCE_ACTIVE" -eq 1 ]]; then
        echo "ERROR: el despliegue se interrumpió; la aplicación permanece en mantenimiento." >&2
        echo "Cuando se resuelva el problema, ejecute: $PHP83 $APP/artisan up" >&2
    fi
}
trap on_exit EXIT

mkdir -p "$BACKUPS"

echo "1. Preparando la página de mantenimiento..."
if [[ ! -f "$MAINTENANCE_VIEW_SOURCE" ]]; then
    echo "ERROR: no se encontró la vista de mantenimiento: $MAINTENANCE_VIEW_SOURCE" >&2
    exit 1
fi
mkdir -p "$(dirname "$MAINTENANCE_VIEW_TARGET")"
/bin/cp "$MAINTENANCE_VIEW_SOURCE" "$MAINTENANCE_VIEW_TARGET"

echo "2. Activando mantenimiento..."
"$PHP83" "$APP/artisan" down --render=errors::503 --retry=60 --refresh=30
MAINTENANCE_ACTIVE=1

echo "3. Respaldando solamente archivos administrados por Git..."
BACKUP_FILE="$BACKUPS/codigo_antes_deploy_$STAMP.tar.gz"
BACKUP_LIST="$(mktemp)"

while IFS= read -r -d '' file; do
    if [[ -e "$APP/$file" || -L "$APP/$file" ]]; then
        printf '%s\0' "$file" >> "$BACKUP_LIST"
    fi
done < <(git -C "$REPO" ls-files -z)

if [[ ! -s "$BACKUP_LIST" ]]; then
    rm -f "$BACKUP_LIST"
    echo "ERROR: no se encontraron archivos versionados para respaldar." >&2
    exit 1
fi

tar --null -czf "$BACKUP_FILE" -C "$APP" -T "$BACKUP_LIST"
rm -f "$BACKUP_LIST"

echo "Respaldo liviano creado: $BACKUP_FILE"
du -h "$BACKUP_FILE"

echo "4. Sincronizando código..."
/usr/bin/rsync -a --delete \
  --exclude='.git/' \
  --exclude='.env' \
  --exclude='vendor/' \
  --exclude='node_modules/' \
  --exclude='storage/app/' \
  --exclude='storage/logs/' \
  --exclude='storage/framework/cache/' \
  --exclude='storage/framework/sessions/' \
  --exclude='storage/framework/views/' \
  --exclude='storage/framework/maintenance.php' \
  "$REPO/" "$APP/"

mkdir -p "$PUBLIC/build"
/usr/bin/rsync -a --delete "$REPO/public/build/" "$PUBLIC/build/"

cmp -s "$REPO/public/build/manifest.json" "$APP/public/build/manifest.json"
cmp -s "$REPO/public/build/manifest.json" "$PUBLIC/build/manifest.json"

echo "5. Instalando dependencias PHP..."
cd "$APP"
"$PHP83" "$COMPOSER" install --no-dev --prefer-dist --no-interaction --optimize-autoloader

echo "6. Limpiando cachés..."
"$PHP83" "$APP/artisan" optimize:clear

echo "7. Ejecutando migraciones..."
"$PHP83" "$APP/artisan" migrate --force

echo "8. Creando enlace storage..."
"$PHP83" "$APP/artisan" storage:link || true

echo "9. Optimizando aplicación..."
"$PHP83" "$APP/artisan" config:cache
"$PHP83" "$APP/artisan" route:cache
"$PHP83" "$APP/artisan" view:cache

echo "10. Desactivando mantenimiento..."
"$PHP83" "$APP/artisan" up
MAINTENANCE_ACTIVE=0

echo "Despliegue completado: $TIMESTAMP"
