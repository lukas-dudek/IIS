#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

PHP_BIN="${PHP_BIN:-php}"
if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
    echo "PHP nenalezeno: $PHP_BIN" >&2
    exit 1
fi

find app public config tests -name '*.php' -print0 | xargs -0 -n1 "$PHP_BIN" -l >/dev/null

if grep -RInE '(var_dump|print_r|TODO|FIXME|die[[:space:]]*\()' \
    app public config tests --include='*.php' --exclude='config.php'; then
    echo "Nalezen zakázaný vzor v PHP." >&2
    exit 1
fi

if [ -d public/assets/js ] && grep -RIn 'console\.log' public/assets/js --include='*.js'; then
    echo "Nalezen console.log." >&2
    exit 1
fi

if [ -x vendor/bin/phpunit ]; then
    vendor/bin/phpunit
fi

echo "Kontrola prošla."
