# ──────────────────────────────────────────────────────────────
# Dockerfile for Saxon HTTP transformation service
# ──────────────────────────────────────────────────────────────
FROM openjdk:11-jdk-slim

ENV SAXON_VERSION=12.3 \
    SAXON_EDITION=he \
    SAXON_HOME=/opt/saxon

# ── install dependencies ──────────────────────────────────────
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl unzip && \
    rm -rf /var/lib/apt/lists/*

# ── download Saxon CLI + XML Resolver ────────────────────────
WORKDIR /opt/saxon
RUN curl -fsSL -o saxon-he-12.3.jar \
      https://repo1.maven.org/maven2/net/sf/saxon/Saxon-HE/12.3/Saxon-HE-12.3.jar && \
    curl -fsSL -o xmlresolver-4.5.0.jar \
      https://repo1.maven.org/maven2/org/xmlresolver/xmlresolver/4.5.0/xmlresolver-4.5.0.jar


# ── download javax.json ───────────────────────────────────────
WORKDIR /app/lib
RUN curl -fsSL -O \
    https://repo1.maven.org/maven2/org/glassfish/javax.json/1.1.4/javax.json-1.1.4.jar

# ── compile Java source ───────────────────────────────────────
WORKDIR /app
COPY app /app/
RUN mkdir -p /app/bin && \
    javac -cp "/app/lib/javax.json-1.1.4.jar" \
          -d /app/bin \
          /app/src/variance/saxonsvc/TransformServer.java

# ── expose port & start service ───────────────────────────────
EXPOSE 8080
CMD ["java", "-cp", "/app/bin:/app/lib/javax.json-1.1.4.jar:/opt/saxon/saxonhe12-3j/saxon-he-12.3.jar", "variance.saxonsvc.TransformServer"]
