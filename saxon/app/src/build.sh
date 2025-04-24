#!/usr/bin/env bash
set -e
mkdir -p /app/bin
javac -d /app/bin /app/src/TransformServer.java
