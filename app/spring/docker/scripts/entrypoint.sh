#!/bin/bash
# Laravel-də: docker/entrypoint.sh — composer install, migrate, php-fpm
# Spring-də: Flyway avtomatik migrate edir, sadəcə java -jar lazımdır
set -e

echo "=========================================="
echo "  Ecommerce Spring — Starting (Docker)"
echo "  Profile: ${SPRING_PROFILE:-docker}"
echo "=========================================="

# JVM options
JAVA_OPTS="${JAVA_OPTS:--Xms256m -Xmx512m -XX:+UseG1GC -XX:+UseStringDeduplication}"

exec java $JAVA_OPTS -jar /app/app.jar
