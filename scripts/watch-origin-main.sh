#!/usr/bin/env bash
# Cron entrypoint: if origin/main moved, run the staging Laravel deploy.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

export PATH="/usr/local/bin:/usr/bin:/bin:${PATH}"
export GIT_TERMINAL_PROMPT=0

git fetch --prune origin

if [ "$(git rev-parse HEAD)" = "$(git rev-parse origin/main)" ]; then
    exit 0
fi

exec "${ROOT}/scripts/deploy-staging.sh"
