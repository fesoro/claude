# Docker & Spring Boot — Geniş İzah (Senior)

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [Docker əsasları](#docker-əsasları)
2. [Spring Boot üçün Dockerfile](#spring-boot-üçün-dockerfile)
3. [Multi-stage build](#multi-stage-build)
4. [Docker Compose](#docker-compose)
5. [Spring Boot Docker optimizasiyası](#spring-boot-docker-optimizasiyası)
6. [Container registry & CI/CD](#container-registry--cicd)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Docker əsasları

```
Docker — container texnologiyası
  → Application + dependency-lər → bir "image"
  → Image-dən istənilən mühitdə identik "container" başlar
  → "Works on my machine" problemini həll edir

Virtual Machine vs Container:
  VM: Guest OS (GB) + Application
  Container: Host OS kernel paylaşır + Application (MB)
  Container daha yüngül, daha sürətli başlayır

Əsas anlayışlar:
  Image    → Şablon (immutable): jar + JDK + konfigurасiya
  Container → Çalışan image nüsxəsi
  Dockerfile → Image yaratma instruksiyaları
  Registry → Image saxlama yeri (Docker Hub, ECR, GCR)
  Layer    → Image-in qatları (cache mexanizmi)

Layered image:
  [Base OS layer]
  [JDK layer]
  [Application dependencies layer]
  [Application jar layer]   ← Yalnız bu dəyişir!

  Layer cache: yalnız dəyişən qat rebuild olunur → sürətli build
```

```dockerfile
# Ən sadə Dockerfile
FROM eclipse-temurin:21-jre-jammy
WORKDIR /app
COPY target/myapp-1.0.0.jar app.jar
EXPOSE 8080
ENTRYPOINT ["java", "-jar", "app.jar"]
```

```bash
# Əsas Docker əmrləri
docker build -t myapp:1.0 .          # Image build
docker run -p 8080:8080 myapp:1.0    # Container başlat
docker ps                             # Çalışan container-lər
docker logs <container-id>            # Log
docker exec -it <id> sh              # Container-ə gir
docker stop <id>                      # Dayandır
docker rm <id>                        # Sil
docker images                         # Image-lər
docker rmi myapp:1.0                  # Image sil
```

---

## Spring Boot üçün Dockerfile

```dockerfile
# ─── Sadə Dockerfile (başlanğıc) ─────────────────────────
FROM eclipse-temurin:21-jre-jammy

# Non-root user — security best practice
RUN groupadd -r spring && useradd -r -g spring spring

WORKDIR /app

# Jar-ı kopyala
COPY target/myapp-*.jar app.jar

# Non-root user-ə keç
USER spring

# JVM parametrləri
ENV JAVA_OPTS="-XX:+UseContainerSupport \
               -XX:MaxRAMPercentage=75.0 \
               -XX:+PrintCommandLineFlags"

EXPOSE 8080

# Health check
HEALTHCHECK --interval=30s --timeout=3s --retries=3 \
    CMD curl -f http://localhost:8080/actuator/health || exit 1

ENTRYPOINT ["sh", "-c", "java $JAVA_OPTS -jar app.jar"]
```

```dockerfile
# ─── Layered Dockerfile (tövsiyə edilən) ──────────────────
# Spring Boot 2.3+ layered jar

FROM eclipse-temurin:21-jre-jammy as builder
WORKDIR /app
COPY target/myapp.jar app.jar
# Spring Boot layered jar-dan qatları çıxar
RUN java -Djarmode=layertools -jar app.jar extract

FROM eclipse-temurin:21-jre-jammy
RUN groupadd -r spring && useradd -r -g spring spring
WORKDIR /app

# Hər qat ayrı COPY — dəyişməyən qatlar cache-ə alınır
COPY --from=builder /app-laravel/dependencies/ ./
COPY --from=builder /app-laravel/spring-boot-loader/ ./
COPY --from=builder /app-laravel/snapshot-dependencies/ ./
COPY --from=builder /app-laravel/application/ ./      # Yalnız bu dəyişir!

USER spring

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -f http://localhost:8080/actuator/health || exit 1

ENTRYPOINT ["java", \
    "-XX:+UseContainerSupport", \
    "-XX:MaxRAMPercentage=75.0", \
    "-Djava.security.egd=file:/dev/./urandom", \
    "org.springframework.boot.loader.JarLauncher"]
```

```xml
<!-- pom.xml — Spring Boot layered jar aktivləşdir -->
<plugin>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-maven-plugin</artifactId>
    <configuration>
        <layers>
            <enabled>true</enabled>
        </layers>
    </configuration>
</plugin>
```

---

## Multi-stage build

```dockerfile
# ─── Multi-stage: Build + Runtime ayrı image ─────────────
# Məqsəd: Maven/Gradle production image-a daxil olmasın

# Stage 1: Build
FROM maven:3.9-eclipse-temurin-21 as build
WORKDIR /app

# Dependency-ləri əvvəlcə kopyala (cache üçün)
COPY pom.xml .
COPY .mvn .mvn
COPY mvnw .
RUN mvn dependency:go-offline -q

# Mənbə kodu kopyala
COPY src src

# Build (test-ləri skip — CI-da ayrı çalışır)
RUN mvn package -DskipTests -q

# Layered jar-dan qatları çıxar
FROM eclipse-temurin:21-jre-jammy as layers
WORKDIR /app
COPY --from=build /app-laravel/target/myapp.jar app.jar
RUN java -Djarmode=layertools -jar app.jar extract

# Stage 2: Runtime (yüngül image)
FROM eclipse-temurin:21-jre-jammy as runtime

# Security
RUN groupadd -r spring && useradd -r -g spring spring
RUN apt-get update && apt-get install -y curl && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Layered qatları kopyala
COPY --from=layers /app-laravel/dependencies/ ./
COPY --from=layers /app-laravel/spring-boot-loader/ ./
COPY --from=layers /app-laravel/snapshot-dependencies/ ./
COPY --from=layers /app-laravel/application/ ./

USER spring

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:8080/actuator/health || exit 1

ENTRYPOINT ["java", \
    "-XX:+UseContainerSupport", \
    "-XX:MaxRAMPercentage=75.0", \
    "-XX:+ExitOnOutOfMemoryError", \
    "-Djava.security.egd=file:/dev/./urandom", \
    "org.springframework.boot.loader.JarLauncher"]

# Build:
# docker build --target runtime -t myapp:latest .
# docker build --target build -t myapp-build:latest .
```

---

## Docker Compose

```yaml
# docker-compose.yml — Development mühiti
version: '3.8'

services:
  # ─── Application ─────────────────────────────────────────
  app:
    build:
      context: .
      target: runtime
    ports:
      - "8080:8080"
    environment:
      SPRING_PROFILES_ACTIVE: docker
      SPRING_DATASOURCE_URL: jdbc:postgresql://postgres:5432/mydb
      SPRING_DATASOURCE_USERNAME: myuser
      SPRING_DATASOURCE_PASSWORD: mypassword
      SPRING_DATA_REDIS_HOST: redis
      SPRING_KAFKA_BOOTSTRAP_SERVERS: kafka:9092
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    networks:
      - app-network
    restart: unless-stopped
    deploy:
      resources:
        limits:
          memory: 512M
          cpus: '1.0'

  # ─── PostgreSQL ───────────────────────────────────────────
  postgres:
    image: postgres:15-alpine
    environment:
      POSTGRES_DB: mydb
      POSTGRES_USER: myuser
      POSTGRES_PASSWORD: mypassword
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./init-db.sql:/docker-entrypoint-initdb.d/init.sql
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U myuser -d mydb"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - app-network

  # ─── Redis ───────────────────────────────────────────────
  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    command: redis-server --appendonly yes
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 3s
      retries: 3
    networks:
      - app-network

  # ─── Kafka ───────────────────────────────────────────────
  zookeeper:
    image: confluentinc/cp-zookeeper:7.5.0
    environment:
      ZOOKEEPER_CLIENT_PORT: 2181
    networks:
      - app-network

  kafka:
    image: confluentinc/cp-kafka:7.5.0
    depends_on:
      - zookeeper
    ports:
      - "9092:9092"
    environment:
      KAFKA_BROKER_ID: 1
      KAFKA_ZOOKEEPER_CONNECT: zookeeper:2181
      KAFKA_ADVERTISED_LISTENERS: PLAINTEXT://kafka:9092
      KAFKA_OFFSETS_TOPIC_REPLICATION_FACTOR: 1
    networks:
      - app-network

volumes:
  postgres_data:
  redis_data:

networks:
  app-network:
    driver: bridge
```

```bash
# Docker Compose əmrləri
docker compose up -d              # Arxa planda başlat
docker compose up --build         # Image-i rebuild et
docker compose down               # Dayandır
docker compose down -v            # Volume-larla birlikdə sil
docker compose logs -f app        # App log-larını izlə
docker compose ps                 # Servis statusları
docker compose exec app sh        # App container-ə gir
docker compose scale app=3        # 3 nüsxəyə scale
```

---

## Spring Boot Docker optimizasiyası

```java
// ─── Container üçün JVM parametrləri ────────────────────
/*
Köhnə Java (8u121 əvvəl): container memory limitini bilmirdi
→ -Xmx 25% RAM seçərdi (host RAM-a görə, container limitinə yox!)
→ Container 512MB, host 16GB → -Xmx 4GB → OOMKilled!

Java 11+: Container support aktiv
  -XX:+UseContainerSupport (default aktiv Java 11+)
  -XX:MaxRAMPercentage=75.0  → container RAM-ın 75%-i
  -XX:InitialRAMPercentage=50.0
*/

// ─── application.yml (docker profile) ────────────────────
/*
spring:
  config:
    activate:
      on-profile: docker

  datasource:
    url: ${SPRING_DATASOURCE_URL}
    username: ${SPRING_DATASOURCE_USERNAME}
    password: ${SPRING_DATASOURCE_PASSWORD}
    hikari:
      maximum-pool-size: 10  # Container-da daha az

  jpa:
    show-sql: false

logging:
  level:
    root: INFO
  pattern:
    # JSON logging — log aggregation üçün
    console: '{"time":"%d{ISO8601}","level":"%p","logger":"%logger{36}","msg":"%msg"}%n'

management:
  endpoints:
    web:
      exposure:
        include: health,info,metrics,prometheus
  endpoint:
    health:
      show-details: always
*/

// ─── Spring Boot Buildpacks (Dockerfile olmadan) ─────────
// Cloud Native Buildpacks — Dockerfile yazmadan image yarat
/*
mvn spring-boot:build-image \
  -Dspring-boot.build-image.imageName=myapp:latest

# Ya da pom.xml-də:
<plugin>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-maven-plugin</artifactId>
    <configuration>
        <image>
            <name>mycompany/${project.artifactId}:${project.version}</name>
            <builder>paketobuildpacks/builder:base</builder>
            <env>
                <BP_JVM_VERSION>21</BP_JVM_VERSION>
                <BPE_DELIM_JAVA_TOOL_OPTIONS xml:space="preserve"> </BPE_DELIM_JAVA_TOOL_OPTIONS>
                <BPE_APPEND_JAVA_TOOL_OPTIONS>-XX:MaxRAMPercentage=75</BPE_APPEND_JAVA_TOOL_OPTIONS>
            </env>
        </image>
    </configuration>
</plugin>
*/

// ─── .dockerignore ────────────────────────────────────────
/*
# .dockerignore — build context-ə daxil etmə
target/
.git/
.gitignore
*.md
.mvn/wrapper/maven-wrapper.jar
Dockerfile*
docker-compose*
.env
*/
```

---

## Container registry & CI/CD

```yaml
# ─── GitHub Actions — Docker build & push ────────────────
# .github/workflows/docker.yml

name: Docker Build & Push

on:
  push:
    branches: [main]
    tags: ['v*']

env:
  REGISTRY: ghcr.io
  IMAGE_NAME: ${{ github.repository }}

jobs:
  build-and-push:
    runs-on: ubuntu-latest
    permissions:
      contents: read
      packages: write

    steps:
      - uses: actions/checkout@v4

      - name: Set up JDK 21
        uses: actions/setup-java@v4
        with:
          java-version: '21'
          distribution: 'temurin'
          cache: maven

      - name: Build with Maven
        run: mvn package -DskipTests

      - name: Log in to Container Registry
        uses: docker/login-action@v3
        with:
          registry: ${{ env.REGISTRY }}
          username: ${{ github.actor }}
          password: ${{ secrets.GITHUB_TOKEN }}

      - name: Extract Docker metadata
        id: meta
        uses: docker/metadata-action@v5
        with:
          images: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}
          tags: |
            type=ref,event=branch
            type=semver,pattern={{version}}
            type=sha,prefix=sha-

      - name: Build and push Docker image
        uses: docker/build-push-action@v5
        with:
          context: .
          push: true
          tags: ${{ steps.meta.outputs.tags }}
          labels: ${{ steps.meta.outputs.labels }}
          cache-from: type=gha
          cache-to: type=gha,mode=max

      - name: Run Trivy vulnerability scanner
        uses: aquasecurity/trivy-action@master
        with:
          image-ref: ${{ env.REGISTRY }}/${{ env.IMAGE_NAME }}:main
          format: 'table'
          exit-code: '1'
          severity: 'CRITICAL'
```

```bash
# ─── Registry əmrləri ────────────────────────────────────
# Docker Hub
docker login
docker tag myapp:latest username/myapp:latest
docker push username/myapp:latest
docker pull username/myapp:latest

# GitHub Container Registry (ghcr.io)
echo $GITHUB_TOKEN | docker login ghcr.io -u USERNAME --password-stdin
docker tag myapp:latest ghcr.io/org/myapp:latest
docker push ghcr.io/org/myapp:latest

# Image scan
docker scout cves myapp:latest        # Docker Scout
trivy image myapp:latest              # Trivy
```

---

## İntervyu Sualları

### 1. Docker niyə lazımdır, VM-dən fərqi nədir?
**Cavab:** VM — tam Guest OS (GB), yavaş başlama (minutes), ağır. Container — Host OS kernelini paylaşır, yalnız application + dependencies (MB), saniyələrdə başlayır. Docker: (1) "Works on my machine" problemi yoxdur — hər yerdə identik mühit; (2) Sürətli deployment; (3) Microservice-ləri izolə edir; (4) Resource efficient — bir host-da yüzlərlə container. Docker daha uyğun: application deployment. VM daha uyğun: tam OS izolasiyası lazım olan hallarda.

### 2. Multi-stage build nədir?
**Cavab:** Bir Dockerfile-da bir neçə `FROM` istifadə etmək. Məqsəd: build aləti (Maven, Gradle) production image-a daxil olmasın. İlk stage — Maven ilə build, jar yaradılır. İkinci stage — yalnız JRE (JDK yox!), yalnız jar kopyalanır. Nəticə: production image çox kiçik (JDK ~400MB → JRE ~200MB), güvənli (Maven, source code yoxdur), sürətli deploy.

### 3. Spring Boot layered jar nədir?
**Cavab:** Spring Boot 2.3+ jar-ı qatlara bölür: dependencies (tez-tez dəyişmir), spring-boot-loader, snapshot-dependencies, application (tez-tez dəyişir). Docker layer cache mexanizmi: yalnız dəyişən qat rebuild olunur. Dependency dəyişmədən sadəcə application kodu dəyişdikdə — yalnız application qatı yenilənir, digər qatlar cache-dən gəlir. Build müddəti xeyli azalır. `java -Djarmode=layertools -jar app.jar extract` ilə qatlar çıxarılır.

### 4. Container-da JVM parametrləri necə seçilməlidir?
**Cavab:** Java 11+ `-XX:+UseContainerSupport` (default aktiv) ilə container memory limitini tanıyır. `-XX:MaxRAMPercentage=75.0` — container RAM-ın 75%-ini heap üçün istifadə et (25% OS, metaspace, thread stack-lar üçün). Köhnə yanaşma `-Xmx512m` — container 512MB-dəki hər şey üçün, amma ya az ya çox ola bilər. `-XX:+ExitOnOutOfMemoryError` — OOM halında JVM-i dayandır (Kubernetes restart edər). Container-ı 512MB etsəniz, MaxRAMPercentage=75 → ~384MB heap.

### 5. Docker Compose Production-da istifadə edilirmi?
**Cavab:** Docker Compose əsasən **development** və **CI/CD test mühiti** üçün istifadə edilir — lokal öyrənmək, integration test, local development stack. Production üçün Kubernetes (K8s) daha uyğundur: auto-healing (pod restart), horizontal auto-scaling, rolling deployment, service discovery, secrets management, multi-node clustering. Amma kiçik proyektlər ya single server deployment-lar üçün Docker Compose + Nginx production-da da istifadə olunur. Ölçü artdıqca Kubernetes-ə keçid edilir.

*Son yenilənmə: 2026-04-10*
