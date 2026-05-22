# ─────────────────────────────────────────
#  medite/Dockerfile
# ─────────────────────────────────────────
FROM python:3.12-slim

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PYTHONPATH=/app \
    NLTK_DATA=/app/assets/nltk_data \
    POETRY_HOME=/opt/poetry \
    PATH="/opt/poetry/bin:$PATH"

# ---------- system deps + Poetry ----------
RUN apt-get update && \
    # add default-jre-headless (≈ 70 MB) for Java
    apt-get install -y --no-install-recommends \
        git build-essential curl default-jre-headless && \
    curl -sSL https://install.python-poetry.org | python3 - && \
    ln -s /opt/poetry/bin/poetry /usr/local/bin/poetry && \
    poetry --version && \
    rm -rf /var/lib/apt/lists/*

# ---------- install locked runtime deps ----------
WORKDIR /app/variance
COPY app/variance/pyproject.toml app/variance/poetry.lock* ./
RUN poetry config virtualenvs.create false && \
    poetry install --no-interaction --no-ansi --only main --no-root

# Flask, Celery, Redis (not in variance lock-file)
RUN pip install flask "celery>=5" "redis>=5"

# ---------- copy full source & editable install ----------
WORKDIR /app
COPY app /app
RUN pip install -e /app/variance --no-deps

# ---------- build C/Cython extension once  ----------
WORKDIR /app/variance
RUN python setup.py build_ext --inplace
WORKDIR /app

# ---------- entrypoint ----------
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 5000
ENTRYPOINT ["/entrypoint.sh"]
