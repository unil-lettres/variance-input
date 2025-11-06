#!/usr/bin/env bash
set -euo pipefail

# Spawn multiple Laravel queue workers so that jobs can run concurrently.
# QUEUE_WORKERS can be provided via the environment (default: 5).
# QUEUE_WORKER_ARGS allows overriding the artisan arguments if needed.

WORKER_COUNT="${QUEUE_WORKERS:-5}"
if ! [[ "${WORKER_COUNT}" =~ ^[0-9]+$ ]] || [ "${WORKER_COUNT}" -lt 1 ]; then
  echo "Invalid QUEUE_WORKERS value: ${WORKER_COUNT}. Falling back to 1 worker." >&2
  WORKER_COUNT=1
fi

if [[ -n "${QUEUE_WORKER_ARGS:-}" ]]; then
  # shellcheck disable=SC2206 # intentional splitting to preserve custom flags
  ARGS=(${QUEUE_WORKER_ARGS})
else
  ARGS=(--queue=facsimiles,page-markers --sleep=2 --timeout=600 --tries=1)
fi

pids=()

cleanup() {
  echo "Stopping queue workers..." >&2
  for pid in "${pids[@]:-}"; do
    if kill -0 "${pid}" 2>/dev/null; then
      kill "${pid}" 2>/dev/null || true
    fi
  done
  wait || true
}

trap cleanup SIGINT SIGTERM

echo "Starting ${WORKER_COUNT} queue worker(s)..." >&2
for i in $(seq 1 "${WORKER_COUNT}"); do
  worker_name="laravel-queue-${i}"
  echo " -> ${worker_name}" >&2
  php artisan queue:work "${ARGS[@]}" --name="${worker_name}" &
  pids+=("$!")
done

wait
