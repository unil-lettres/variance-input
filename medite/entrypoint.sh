#!/usr/bin/env bash
set -euo pipefail

umask 0002

prepare_shared_upload_tree() {
  if ! getent group www-data >/dev/null 2>&1; then
    return
  fi

  for path in /app/uploads /app/variance_data /app/storage_public; do
    [ -e "$path" ] || continue
    chgrp -R www-data "$path" || true
    find "$path" -type d -exec chmod g+rws {} + || true
    find "$path" -type f -exec chmod g+rw {} + || true
  done
}

prepare_shared_upload_tree

# 1 – (Re)install in editable mode (handles any git-pull you did)
pip install -e /app/variance --no-deps --force-reinstall

# 2 – (Re)build the C/Cython extension
(
  cd /app/variance
  python setup.py build_ext --inplace
)

echo "Starting Celery worker…"
celery -A flask_app.celery worker \
  --loglevel=info \
  --soft-time-limit=1800 \
  --time-limit=2100 &

echo "Starting Flask server…"
exec flask run --host=0.0.0.0 --port=5000
