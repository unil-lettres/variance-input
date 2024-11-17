#!/bin/bash

set -e

echo "Building and installing the project..."
poetry run python setup.py build_ext --inplace
poetry run python setup.py install

echo "Starting Celery worker..."
poetry run celery -A flask_app.celery worker --loglevel=info &

echo "Starting Flask server..."
exec poetry run flask run --host=0.0.0.0
