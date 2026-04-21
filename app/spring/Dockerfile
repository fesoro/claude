# =====================================================================
# Multi-stage build — Laravel-də PHP-FPM Dockerfile-in qarşılığı
# =====================================================================
FROM eclipse-temurin:21-jdk-alpine AS builder
WORKDIR /workspace

# Maven Wrapper və pom.xml əvvəl köçürürük ki, dependency-lər cache olunsun
COPY .mvn .mvn/
COPY mvnw pom.xml ./
RUN ./mvnw dependency:go-offline -B || true

# İndi qaynaq kod
COPY src src/
RUN ./mvnw clean package -DskipTests -B

# =====================================================================
# Runtime image — kiçik, JRE-only
# =====================================================================
FROM eclipse-temurin:21-jre-alpine
WORKDIR /app

RUN apk add --no-cache curl bash

COPY --from=builder /workspace/target/*.jar app.jar
COPY docker/scripts/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080

# Spring Boot Actuator health endpoint ilə Docker healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
  CMD curl -f http://localhost:8080/actuator/health/liveness || exit 1

ENTRYPOINT ["/entrypoint.sh"]
