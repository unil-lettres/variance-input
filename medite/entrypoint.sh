#!/usr/bin/env bash
set -euo pipefail

umask 0002

prepare_shared_upload_tree() {
  if ! getent group www-data >/dev/null 2>&1; then
    return
  fi

  for path in /app/uploads /app/variance_data /app/storage_public; do
    [ -e "$path" ] || mkdir -p "$path"

    # Medite only needs shared-group inheritance on directories it traverses or
    # creates under. Touching every existing file on each boot makes startup
    # degrade badly once the uploads tree grows.
    chgrp www-data "$path" || true
    chmod 2775 "$path" || true
    find "$path" -type d \( ! -group www-data -o ! -perm -2000 -o ! -perm -0020 -o ! -perm -0040 \) \
      -exec chgrp www-data {} + \
      -exec chmod g+rws {} + || true
  done
}

ensure_editable_install() {
  if python - <<'PY' >/dev/null 2>&1
import importlib.metadata
importlib.metadata.version('variance')
PY
  then
    return
  fi

  pip install -e /app/variance --no-deps
}

build_suffix_tree_if_needed() {
  local ext_suffix target
  ext_suffix="$(python - <<'PY'
import sysconfig
print(sysconfig.get_config_var("EXT_SUFFIX") or ".so")
PY
)"
  target="/app/variance/_suffix_tree${ext_suffix}"

  if [ -f "$target" ] \
    && [ /app/variance/setup.py -ot "$target" ] \
    && [ /app/variance/suffix-tree/python_bindings.c -ot "$target" ] \
    && [ /app/variance/suffix-tree/suffix_tree.c -ot "$target" ]; then
    return
  fi

  (
    cd /app/variance
    python setup.py build_ext --inplace
  )
}

prepare_shared_upload_tree

# 1 – Ensure editable install exists
ensure_editable_install

# 2 – Rebuild the native extension only when sources changed or binary is missing
build_suffix_tree_if_needed

echo "Starting Celery worker…"
celery -A flask_app.celery worker \
  --loglevel=info \
  --soft-time-limit=1800 \
  --time-limit=2100 &

echo "Starting Flask server…"
exec flask run --host=0.0.0.0 --port=5000
