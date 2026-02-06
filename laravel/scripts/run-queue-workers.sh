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
HEARTBEAT_FILE="${QUEUE_WORKER_HEARTBEAT_FILE:-/var/www/html/storage/app/private/queue_workers.json}"
HEARTBEAT_INTERVAL="${QUEUE_WORKER_HEARTBEAT_INTERVAL:-30}"

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
worker_pids=()
heartbeat_pid=""
shutdown_requested=0

log() {
  echo "[$(date -u +"%Y-%m-%dT%H:%M:%SZ")] $*"
}

write_heartbeat() {
  local ts
  ts="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
  local pid_list=""
  if [ "${#pids[@]}" -gt 0 ]; then
    pid_list="$(printf '%s\n' "${pids[@]}" | paste -sd, -)"
  fi
  printf '{"count":%s,"timestamp":"%s","interval":%s,"pids":[%s]}\n' \
    "${WORKER_COUNT}" "${ts}" "${HEARTBEAT_INTERVAL}" "${pid_list}" > "${HEARTBEAT_FILE}"
}

cleanup() {
  shutdown_requested=1
  echo "Stopping queue workers..." >&2
  for pid in "${worker_pids[@]:-}"; do
    if kill -0 "${pid}" 2>/dev/null; then
      kill "${pid}" 2>/dev/null || true
    fi
  done
  for pid in "${pids[@]:-}"; do
    if kill -0 "${pid}" 2>/dev/null; then
      kill "${pid}" 2>/dev/null || true
    fi
  done
  if [ -n "${heartbeat_pid}" ] && kill -0 "${heartbeat_pid}" 2>/dev/null; then
    kill "${heartbeat_pid}" 2>/dev/null || true
  fi
  rm -f "${HEARTBEAT_FILE}" 2>/dev/null || true
  wait || true
}

trap cleanup SIGINT SIGTERM

start_worker() {
  local index="$1"
  local worker_name="laravel-queue-${index}"
  local restart_delay="${QUEUE_WORKER_RESTART_DELAY:-2}"

  while true; do
    if [ "${shutdown_requested}" -eq 1 ]; then
      break
    fi

    log "Starting ${worker_name}..."
    "${PHP_BIN}" "${PHP_INI_ARGS[@]}" artisan queue:work "${ARGS[@]}" --name="${worker_name}" &
    local wp="$!"
    worker_pids[$index]="${wp}"

    wait "${wp}"
    local status=$?

    if [ "${shutdown_requested}" -eq 1 ]; then
      break
    fi

    log "Worker ${worker_name} exited with status ${status}. Restarting in ${restart_delay}s..."
    sleep "${restart_delay}"
  done
}

echo "Starting ${WORKER_COUNT} queue worker(s)..." >&2
for i in $(seq 1 "${WORKER_COUNT}"); do
  worker_name="laravel-queue-${i}"
  echo " -> ${worker_name}" >&2
  start_worker "${i}" &
  pids+=("$!")
done

write_heartbeat
(
  while true; do
    sleep "${HEARTBEAT_INTERVAL}"
    write_heartbeat
  done
) &
heartbeat_pid="$!"

wait
