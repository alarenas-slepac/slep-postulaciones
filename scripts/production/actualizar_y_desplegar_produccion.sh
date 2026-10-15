#!/bin/bash

set -Eeuo pipefail

REPO="/home/slepac/repositories/slep-postulaciones"
DEPLOY_SCRIPT="/home/slepac/bin/deploy_slep_postulaciones.sh"
EXPECTED_BRANCH="main"

# cPanel puede limitar los procesos/hilos de la cuenta. Aplicar estas opciones
# por comando evita alterar la configuración global o local del repositorio.
git_cpanel() {
    git -c core.preloadIndex=false -c index.threads=1 -c pack.threads=1 "$@"
}

check_tracked_changes() {
    local scope="$1"
    local status
    shift

    if git_cpanel diff --quiet --no-ext-diff --no-textconv "$@"; then
        return 0
    else
        status=$?
    fi

    if [[ "$status" -eq 1 ]]; then
        echo "ERROR: existen modificaciones locales versionadas en $REPO ($scope)." >&2
        echo "Revise o guarde esos cambios antes de actualizar producción." >&2
        exit 1
    fi

    echo "ERROR: Git no pudo comprobar $scope en $REPO (código $status)." >&2
    echo "No se pudo determinar si hay modificaciones locales. Revise los límites de recursos de la cuenta cPanel y el error de Git anterior." >&2
    exit "$status"
}

if [[ ! -d "$REPO/.git" ]]; then
    echo "ERROR: no se encontró el repositorio Git en $REPO." >&2
    exit 1
fi

if [[ ! -x "$DEPLOY_SCRIPT" ]]; then
    echo "ERROR: el script de despliegue no existe o no es ejecutable: $DEPLOY_SCRIPT." >&2
    exit 1
fi

cd "$REPO"
CURRENT_BRANCH="$(git_cpanel branch --show-current)"

if [[ "$CURRENT_BRANCH" != "$EXPECTED_BRANCH" ]]; then
    echo "ERROR: el repositorio está en la rama '$CURRENT_BRANCH'." >&2
    echo "Debe estar en la rama '$EXPECTED_BRANCH' para desplegar a producción." >&2
    exit 1
fi

check_tracked_changes "los archivos de trabajo"
check_tracked_changes "el área de preparación" --cached

echo "1. Descargando referencias de origin/$EXPECTED_BRANCH..."
git_cpanel fetch origin "$EXPECTED_BRANCH"

echo "2. Actualizando la rama $EXPECTED_BRANCH mediante avance rápido..."
git_cpanel pull --ff-only origin "$EXPECTED_BRANCH"

echo "3. Ejecutando el despliegue productivo con mantenimiento..."
exec "$DEPLOY_SCRIPT"
