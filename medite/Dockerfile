# ─────────────────────────────────────────
#  medite/Dockerfile
# ─────────────────────────────────────────
FROM python:3.12.14-slim@sha256:44ff437bba879d4941b710a369a8f19266aea34b29002807f0c487fabc9eec9b

# Refresh the installer inherited from the base image before installing packages.
# pip is outside poetry.lock; keep its security baseline explicit.
COPY bootstrap.requirements.txt /opt/bootstrap.requirements.txt
RUN python -m pip install --no-cache-dir --require-hashes --only-binary=:all: \
    -r /opt/bootstrap.requirements.txt

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PYTHONHASHSEED=0 \
    PYTHONPATH=/app \
    NLTK_DATA=/app/assets/nltk_data \
    POETRY_HOME=/opt/poetry

# ---------- system deps + Poetry ----------
RUN apt-get update && \
    # Refresh the four vulnerable binaries inherited from the pinned base.
    # Debian security backports reviewed on 2026-10-30; no distribution upgrade.
    apt-get install -y --no-install-recommends \
        openssl=3.5.7-1~deb13u3 \
        libssl3t64=3.5.7-1~deb13u3 \
        openssl-provider-legacy=3.5.7-1~deb13u3 \
        libpcre2-8-0=10.46-1~deb13u3 \
        git build-essential curl default-jre-headless && \
    rm -rf /var/lib/apt/lists/*

# Separate, fully pinned build tooling; no moving installer script.
COPY poetry-tooling.requirements.txt /opt/poetry-tooling.requirements.txt
RUN python -m venv /opt/poetry/venv && \
    /opt/poetry/venv/bin/python -m pip install --no-cache-dir \
        --require-hashes --only-binary=:all: -r /opt/poetry-tooling.requirements.txt && \
    /opt/poetry/venv/bin/python -m pip check && \
    ln -s /opt/poetry/venv/bin/poetry /usr/local/bin/poetry

# ---------- install locked runtime deps ----------
WORKDIR /app/variance
COPY app/variance/pyproject.toml app/variance/poetry.lock* ./
RUN poetry config virtualenvs.create false && \
    poetry install --no-interaction --no-ansi --only main --no-root

# Flask, Celery and Redis are part of poetry.lock.

# ---------- copy full source & editable install ----------
WORKDIR /app
COPY app /app
RUN /usr/local/bin/python -m pip install -e /app/variance --no-deps --no-build-isolation && \
    /usr/local/bin/python -m pip check

# ---------- build C/Cython extension once  ----------
WORKDIR /app/variance
RUN python setup.py build_ext --inplace
WORKDIR /app

# ---------- entrypoint ----------
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 5000
ENTRYPOINT ["/entrypoint.sh"]
