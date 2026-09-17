#!/usr/bin/env bash
# Staging deploy for this host: pull origin/main, then Laravel update.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

export PATH="/usr/local/bin:/usr/bin:/bin:${PATH}"
export COMPOSER_ALLOW_SUPERUSER=1
export GIT_TERMINAL_PROMPT=0

LOG="${ROOT}/storage/logs/deploy.log"
mkdir -p "${ROOT}/storage/logs"
exec >>"${LOG}" 2>&1

echo "========== $(date -Is) deploy start =========="

LOCK="/tmp/sales-klaim-oceanspace.deploy.lock"
exec 9>"${LOCK}"
if ! flock -n 9; then
    echo "another deploy is running, skip"
    exit 0
fi

bring_up() {
    php artisan up --no-interaction || true
    echo "========== $(date -Is) deploy end =========="
}
trap bring_up EXIT

php artisan down --retry=60 --no-interaction || true

git fetch --prune origin
OLD_SHA="$(git rev-parse HEAD)"
git checkout --force main
git reset --hard origin/main
NEW_SHA="$(git rev-parse HEAD)"
echo "HEAD ${OLD_SHA} -> ${NEW_SHA}"

composer install --no-interaction --prefer-dist --optimize-autoloader

php artisan migrate --force --no-interaction

CHANGED="$(git diff --name-only "${OLD_SHA}" "${NEW_SHA}" || true)"
if [ -z "${CHANGED}" ] || echo "${CHANGED}" | grep -Eq '^(package-lock\.json|package\.json|resources/|vite\.config)'; then
    if [ -f package-lock.json ]; then
        npm ci --no-audit --no-fund
    else
        npm install --no-audit --no-fund
    fi
    npm run build
fi

php artisan filament:upgrade --no-interaction || true
php artisan optimize:clear --no-interaction
php artisan storage:link --force --no-interaction || true

echo "deploy ok ${NEW_SHA}"
