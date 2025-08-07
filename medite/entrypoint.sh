#!/usr/bin/env bash
set -euo pipefail

# 1 – (Re)install in editable mode (handles any git-pull you did)
pip install -e /app/variance --no-deps --force-reinstall

# 2 – (Re)build the C/Cython extension
(
  cd /app/variance
  python setup.py build_ext --inplace
)

echo "Starting Celery worker…"
celery -A flask_app.celery worker --loglevel=info &

echo "Starting Flask server…"
exec flask run --host=0.0.0.0 --port=5000
