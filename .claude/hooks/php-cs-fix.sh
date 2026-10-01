#!/bin/sh
# Hook PostToolUse de Claude Code: aplica php-cs-fixer (PSR-12) al fichero PHP
# recién editado, dentro de diary-php. Si el stack local no está levantado o el
# fichero queda fuera del Finder (vendor, var, migrations...), no hace nada:
# nunca bloquea la edición, el pre-commit sigue siendo la red de seguridad.

file=$(jq -r '.tool_input.file_path // empty')

case "$file" in
    *.php) ;;
    *) exit 0 ;;
esac

root="${CLAUDE_PROJECT_DIR:-$(pwd)}"
case "$file" in
    "$root"/*) rel="${file#"$root"/}" ;;
    *) exit 0 ;;
esac

[ -f "$file" ] || exit 0

cd "$root" || exit 0
[ -n "$(docker compose --env-file .env ps --status running -q diary-php 2>/dev/null)" ] || exit 0

docker compose --env-file .env exec -T diary-php vendor/bin/php-cs-fixer fix \
    --config=.php-cs-fixer.dist.php --path-mode=intersection --quiet -- "$rel" >/dev/null 2>&1

exit 0
