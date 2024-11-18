FROM openjdk:11-jre-slim

ENV SAXON_VERSION=12.3
ENV SAXON_EDITION=he
ENV SAXON_HOME=/opt/saxon
ENV SAXON_JAR=${SAXON_HOME}/saxon-${SAXON_EDITION}-${SAXON_VERSION}.jar

WORKDIR /opt/saxon

RUN apt-get update && apt-get install -y curl unzip && \

    curl -fSL -o saxon.zip \
    https://github.com/Saxonica/Saxon-HE/releases/download/SaxonHE12-3/SaxonHE12-3J.zip && \

    unzip saxon.zip -d /opt/saxon && \

    rm saxon.zip && apt-get clean && rm -rf /var/lib/apt/lists/*

WORKDIR /app

CMD ["tail", "-f", "/dev/null"]
