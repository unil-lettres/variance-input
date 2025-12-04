#!/usr/bin/env bash
set -euo pipefail

# Spawn multiple Laravel queue workers so that jobs can run concurrently.
# QUEUE_WORKERS can be provided via the environment (default: 5).
# QUEUE_WORKER_ARGS allows overriding the artisan arguments if needed.

PHP_BIN="${PHP_BINARY:-php}"
PHP_MEMORY_LIMIT_VALUE="${PHP_MEMORY_LIMIT:-512M}"
PHP_INI_ARGS=()
if [[ -n "${PHP_MEMORY_LIMIT_VALUE}" ]]; then
  PHP_INI_ARGS=(-d "memory_limit=${PHP_MEMORY_LIMIT_VALUE}")
fi

WORKER_COUNT="${QUEUE_WORKERS:-5}"
if ! [[ "${WORKER_COUNT}" =~ ^[0-9]+$ ]] || [ "${WORKER_COUNT}" -lt 1 ]; then
  echo "Invalid QUEUE_WORKERS value: ${WORKER_COUNT}. Falling back to 1 worker." >&2
  WORKER_COUNT=1
fi

WORKER_MEMORY="${QUEUE_WORKER_MEMORY:-512}"

if [[ -n "${QUEUE_WORKER_ARGS:-}" ]]; then
  # shellcheck disable=SC2206 # intentional splitting to preserve custom flags
  ARGS=(${QUEUE_WORKER_ARGS})
else
  ARGS=(--queue=facsimiles,page-markers --sleep=2 --timeout=600 --tries=1)
fi

# Append a --memory flag unless the caller already provided one.
if [[ " ${ARGS[*]} " != *" --memory"* ]]; then
  ARGS+=(--memory="${WORKER_MEMORY}")
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
  "${PHP_BIN}" "${PHP_INI_ARGS[@]}" artisan queue:work "${ARGS[@]}" --name="${worker_name}" &
  pids+=("$!")
done

wait
