#!/bin/bash

set -Eeuo pipefail

REPO="/home/slepac/repositories/slep-postulaciones"
DEPLOY_SCRIPT="/home/slepac/bin/deploy_slep_postulaciones.sh"
EXPECTED_BRANCH="main"

if [[ ! -d "$REPO/.git" ]]; then
    echo "ERROR: no se encontró el repositorio Git en $REPO." >&2
    exit 1
fi

if [[ ! -x "$DEPLOY_SCRIPT" ]]; then
    echo "ERROR: el script de despliegue no existe o no es ejecutable: $DEPLOY_SCRIPT." >&2
    exit 1
fi

cd "$REPO"
CURRENT_BRANCH="$(git branch --show-current)"

if [[ "$CURRENT_BRANCH" != "$EXPECTED_BRANCH" ]]; then
    echo "ERROR: el repositorio está en la rama '$CURRENT_BRANCH'." >&2
    echo "Debe estar en la rama '$EXPECTED_BRANCH' para desplegar a producción." >&2
    exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "ERROR: existen modificaciones locales versionadas en $REPO." >&2
    echo "Revise o guarde esos cambios antes de actualizar producción." >&2
    exit 1
fi

echo "1. Descargando referencias de origin/$EXPECTED_BRANCH..."
git fetch origin "$EXPECTED_BRANCH"

echo "2. Actualizando la rama $EXPECTED_BRANCH mediante avance rápido..."
git pull --ff-only origin "$EXPECTED_BRANCH"

echo "3. Ejecutando el despliegue productivo con mantenimiento..."
exec "$DEPLOY_SCRIPT"
