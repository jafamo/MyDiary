# shellcheck shell=bash
# Utilidades compartidas por los scripts de bin/ops/ (diagnóstico de producción, solo lectura).
# Requiere el alias SSH `diary-prod` en ~/.ssh/config y la clave cargada en el ssh-agent.

set -euo pipefail

PROD_HOST="${PROD_HOST:-diary-prod}"
PROD_DIR="${PROD_DIR:-/projects/dockers/MyDiary}"

# Claude Code y otros shells no interactivos no heredan SSH_AUTH_SOCK: se usa el agente de systemd.
if [ -z "${SSH_AUTH_SOCK:-}" ] && [ -S "/run/user/$(id -u)/ssh-agent.socket" ]; then
    export SSH_AUTH_SOCK="/run/user/$(id -u)/ssh-agent.socket"
fi

# Ejecuta un comando en el servidor. Nunca pide contraseña: si la clave no está en el agente, falla.
prod_run() {
    local status=0
    ssh -o BatchMode=yes -o ConnectTimeout=8 "$PROD_HOST" "$@" || status=$?
    if [ "$status" -eq 255 ]; then
        echo "No se pudo conectar a $PROD_HOST. ¿Está la clave en el agente? Ejecuta en tu terminal:" >&2
        echo "  SSH_AUTH_SOCK=/run/user/$(id -u)/ssh-agent.socket ssh-add -t 8h ~/.ssh/id_ed25519" >&2
    fi
    return "$status"
}

# Escapa un argumento para pasarlo dentro de un comando remoto.
quote() {
    printf '%q' "$1"
}
