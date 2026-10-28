#!/usr/bin/env bash
# Start everything Psst needs, in an order that survives a reseed.
#
#   scripts/dev-stack.sh           # start api, reverb, worker and the dev server
#   scripts/dev-stack.sh --seed    # reseed first, then start
#
# The queue worker restarts itself on purpose: `migrate:fresh` drops the `jobs` table
# and a running worker exits. Without a worker every broadcast is queued and nothing
# arrives live — the app silently falls back to a 15s poll, which is hard to notice.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_PORT="${PSST_API_PORT:-8000}"

if [[ "${1:-}" == "--seed" ]]; then
  ( cd "$ROOT/server" && php artisan migrate:fresh --seed )
fi

pids=()
cleanup() {
  trap - INT TERM EXIT
  for pid in "${pids[@]}"; do kill "$pid" 2>/dev/null || true; done
}
trap cleanup INT TERM EXIT

( cd "$ROOT/server" && php artisan reverb:start --host=127.0.0.1 --port=8080 ) &
pids+=("$!")

(
  cd "$ROOT/server"
  while true; do
    php artisan queue:work --tries=1 --sleep=1 || true
    echo "[dev-stack] queue worker exited (database reseeded?); restarting in 2s"
    sleep 2
  done
) &
pids+=("$!")

( cd "$ROOT/server" && php artisan serve --host=127.0.0.1 --port="$API_PORT" ) &
pids+=("$!")

( cd "$ROOT/client" && pnpm dev ) &
pids+=("$!")

echo "[dev-stack] api :$API_PORT · reverb :8080 · client :9000 (ctrl-c stops all)"
wait
