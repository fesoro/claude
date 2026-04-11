# Deployment (Yerlesdirme / Deploy)

## Giris

Tetbiqin inkisaf muhetinden istehsal (production) muhetine kocurulmesi - deployment prosesi - her iki framework ucun ferqli addimlar teleb edir. Spring Boot tetbiqi ozunde veb server dasiyir ve JAR/WAR fayil olaraq deploy olunur. Laravel ise PHP veb serveri (Nginx/Apache), PHP-FPM ve bir sira artisan emrleri teleb edir. Docker ile her ikisinin deployment prosesi oxsarlasir.

## Spring-de istifadesi

### JAR ile deployment (en populyar)

Spring Boot tetbiqi icinde Tomcat serveri dasiyir - ayri server qurmaq lazim deyil:

```bash
# Build
./mvnw clean package -DskipTests

# Ve ya Gradle ile
./gradlew build -x test

# Netice: target/myapp-1.0.0.jar (ve ya build/libs/myapp-1.0.0.jar)

# Isletmek
java -jar target/myapp-1.0.0.jar

# Profil ile
java -jar myapp.jar --spring.profiles.active=production

# JVM parametrleri ile
java -Xmx512m -Xms256m \
     -Dserver.port=8080 \
     -Dspring.profiles.active=production \
     -jar myapp.jar
```

### application-production.yml

```yaml
# src/main/resources/application-production.yml
server:
  port: 8080

spring:
  datasource:
    url: jdbc:postgresql://${DB_HOST}:5432/${DB_NAME}
    username: ${DB_USERNAME}
    password: ${DB_PASSWORD}
    hikari:
      maximum-pool-size: 20
      minimum-idle: 5

  jpa:
    hibernate:
      ddl-auto: validate  # Production-da heac vaxt "create" ve ya "update" istifade etmeyin!
    show-sql: false

logging:
  level:
    root: WARN
    com.example: INFO
  file:
    name: /var/log/myapp/application.log
```

### WAR ile deployment (kohne yanasma)

```java
// Xarici Tomcat/WildFly-a deploy etmek ucun
@SpringBootApplication
public class Application extends SpringBootServletInitializer {

    @Override
    protected SpringApplicationBuilder configure(
            SpringApplicationBuilder application) {
        return application.sources(Application.class);
    }

    public static void main(String[] args) {
        SpringApplication.run(Application.class, args);
    }
}
```

```xml
<!-- pom.xml -->
<packaging>war</packaging>

<dependencies>
    <dependency>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-tomcat</artifactId>
        <scope>provided</scope>
    </dependency>
</dependencies>
```

### Docker ile deployment

```dockerfile
# Dockerfile - Multi-stage build
# Stage 1: Build
FROM eclipse-temurin:21-jdk AS builder
WORKDIR /app
COPY . .
RUN ./mvnw clean package -DskipTests

# Stage 2: Run
FROM eclipse-temurin:21-jre
WORKDIR /app

# Non-root istifadeci yaratmaq
RUN groupadd -r spring && useradd -r -g spring spring

COPY --from=builder /app/target/*.jar app.jar

# Health check
HEALTHCHECK --interval=30s --timeout=3s \
    CMD curl -f http://localhost:8080/actuator/health || exit 1

USER spring
EXPOSE 8080

ENTRYPOINT ["java", "-Xmx512m", "-jar", "app.jar"]
```

```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "8080:8080"
    environment:
      - SPRING_PROFILES_ACTIVE=production
      - DB_HOST=postgres
      - DB_NAME=myapp
      - DB_USERNAME=myapp_user
      - DB_PASSWORD=${DB_PASSWORD}
    depends_on:
      postgres:
        condition: service_healthy

  postgres:
    image: postgres:16
    environment:
      POSTGRES_DB: myapp
      POSTGRES_USER: myapp_user
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U myapp_user"]
      interval: 10s
      timeout: 5s
      retries: 5

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf
    depends_on:
      - app

volumes:
  pgdata:
```

### CI/CD (GitHub Actions)

```yaml
# .github/workflows/deploy.yml
name: Build & Deploy

on:
  push:
    branches: [main]

jobs:
  build:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Set up JDK 21
        uses: actions/setup-java@v4
        with:
          java-version: '21'
          distribution: 'temurin'

      - name: Build with Maven
        run: ./mvnw clean package

      - name: Run tests
        run: ./mvnw test

      - name: Build Docker image
        run: docker build -t myapp:${{ github.sha }} .

      - name: Push to registry
        run: |
          docker tag myapp:${{ github.sha }} registry.example.com/myapp:${{ github.sha }}
          docker push registry.example.com/myapp:${{ github.sha }}

      - name: Deploy to server
        uses: appleboy/ssh-action@master
        with:
          host: ${{ secrets.SERVER_HOST }}
          username: ${{ secrets.SERVER_USER }}
          key: ${{ secrets.SSH_KEY }}
          script: |
            docker pull registry.example.com/myapp:${{ github.sha }}
            docker stop myapp || true
            docker rm myapp || true
            docker run -d --name myapp \
              -p 8080:8080 \
              --env-file /opt/myapp/.env \
              registry.example.com/myapp:${{ github.sha }}
```

### Spring Boot Actuator ile monitoring

```yaml
# application.yml
management:
  endpoints:
    web:
      exposure:
        include: health, info, metrics, prometheus
  endpoint:
    health:
      show-details: when-authorized
```

```bash
# Health check
curl http://localhost:8080/actuator/health
# {"status":"UP","components":{"db":{"status":"UP"},"diskSpace":{"status":"UP"}}}

# Metrics
curl http://localhost:8080/actuator/metrics/jvm.memory.used
```

### Systemd ile servis olaraq qurma

```ini
# /etc/systemd/system/myapp.service
[Unit]
Description=My Spring Boot Application
After=network.target postgresql.service

[Service]
Type=simple
User=spring
Group=spring
ExecStart=/usr/bin/java -Xmx512m -jar /opt/myapp/myapp.jar --spring.profiles.active=production
ExecStop=/bin/kill -TERM $MAINPID
Restart=always
RestartSec=10

EnvironmentFile=/opt/myapp/.env

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable myapp
sudo systemctl start myapp
sudo systemctl status myapp
```

## Laravel-de istifadesi

### Manual deployment

```bash
# 1. Kodu servere cek
git pull origin main

# 2. Asililiqlar
composer install --optimize-autoloader --no-dev

# 3. Environment
cp .env.example .env  # Ilk defedir birdce
php artisan key:generate  # Ilk defe

# 4. Database migrasiylari
php artisan migrate --force

# 5. Optimizasiyalar
php artisan config:cache    # Konfiqurasiya cache-le
php artisan route:cache     # Route-lari cache-le
php artisan view:cache      # View-lari cache-le
php artisan event:cache     # Event-leri cache-le

# 6. Storage link
php artisan storage:link

# 7. Queue worker yeniden baslatmaq
php artisan queue:restart
```

### Deployment skripti

```bash
#!/bin/bash
# deploy.sh

set -e

echo "Deployment baslayir..."

# Maintenance mode
php artisan down --retry=60

# Git pull
git pull origin main

# Composer
composer install --optimize-autoloader --no-dev

# Migrasiyalar
php artisan migrate --force

# Cache temizle ve yeniden yarat
php artisan cache:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Queue restart
php artisan queue:restart

# Maintenance mode-dan cix
php artisan up

echo "Deployment tamamlandi!"
```

### Laravel Forge

Forge Laravel-in resmi server idare etme aletiddir:

```
Forge ne edir:
- Server provision (DigitalOcean, AWS, Hetzner ve s.)
- Nginx konfigurasiyasi
- SSL sertifikat (Let's Encrypt)
- Database qurulmasi
- Queue worker idaresi (Supervisor)
- Avtomatik deployment (git push ile)
- Cron job-lar (schedule)
- Server monitoring
```

```bash
# Forge deployment skripti (Forge panel-de konfiqurasiya olunur)
cd /home/forge/example.com
git pull origin $FORGE_SITE_BRANCH

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan config:cache
    $FORGE_PHP artisan route:cache
    $FORGE_PHP artisan view:cache
fi
```

### Laravel Vapor (Serverless)

```yaml
# vapor.yml
id: 12345
name: myapp
environments:
  production:
    memory: 1024
    cli-memory: 512
    runtime: php-8.3:al2
    build:
      - 'composer install --no-dev'
    deploy:
      - 'php artisan migrate --force'
      - 'php artisan config:cache'
    database: myapp-db
    cache: myapp-cache
    queues:
      - default
      - emails
    storage: myapp-storage

  staging:
    memory: 512
    build:
      - 'composer install'
    database: myapp-staging-db
```

```bash
# Vapor ile deploy
vapor deploy production
vapor deploy staging
```

### Docker ile deployment

```dockerfile
# Dockerfile
FROM php:8.3-fpm-alpine

# Elave paketler
RUN apk add --no-cache \
    nginx supervisor curl \
    libpng-dev libjpeg-turbo-dev libzip-dev \
    && docker-php-ext-install pdo pdo_mysql gd zip opcache

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Asililiqlar evvel yuklenir (cache ucun)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Tetbiq kodunu kopyala
COPY . .

# Artisan emrleri
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# Icazeler
RUN chown -R www-data:www-data storage bootstrap/cache

# Nginx konfigurasiyasi
COPY docker/nginx.conf /etc/nginx/http.d/default.conf

# Supervisor konfigurasiyasi
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

```nginx
# docker/nginx.conf
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

```ini
# docker/supervisord.conf
[supervisord]
nodaemon=true

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true

[program:php-fpm]
command=php-fpm -F
autostart=true
autorestart=true

[program:queue-worker]
command=php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
numprocs=2
process_name=%(program_name)s_%(process_num)02d
```

```yaml
# docker-compose.yml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "80:80"
    environment:
      - APP_ENV=production
      - APP_KEY=${APP_KEY}
      - DB_HOST=mysql
      - DB_DATABASE=myapp
      - DB_USERNAME=myapp_user
      - DB_PASSWORD=${DB_PASSWORD}
      - CACHE_DRIVER=redis
      - QUEUE_CONNECTION=redis
      - SESSION_DRIVER=redis
    depends_on:
      - mysql
      - redis

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: myapp
      MYSQL_USER: myapp_user
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    volumes:
      - mysqldata:/var/lib/mysql

  redis:
    image: redis:alpine
    volumes:
      - redisdata:/data

volumes:
  mysqldata:
  redisdata:
```

### CI/CD (GitHub Actions)

```yaml
# .github/workflows/deploy.yml
name: Deploy Laravel

on:
  push:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_DATABASE: testing
          MYSQL_ROOT_PASSWORD: password
        ports:
          - 3306:3306

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, pdo_mysql

      - name: Install Dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run Tests
        env:
          DB_HOST: 127.0.0.1
          DB_DATABASE: testing
          DB_USERNAME: root
          DB_PASSWORD: password
        run: php artisan test

  deploy:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - name: Deploy to Forge
        uses: jbrooksuk/laravel-forge-action@v1.0.4
        with:
          trigger_url: ${{ secrets.FORGE_DEPLOY_WEBHOOK }}
```

### .env.production

```env
APP_NAME=MyApp
APP_ENV=production
APP_KEY=base64:xxxxxxx
APP_DEBUG=false
APP_URL=https://example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=myapp
DB_USERNAME=myapp_user
DB_PASSWORD=secret

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1

MAIL_MAILER=ses
MAIL_FROM_ADDRESS=noreply@example.com
```

## Esas ferqler

| Xususiyyet | Spring Boot | Laravel |
|---|---|---|
| **Deployment formati** | JAR (embedded server) | PHP fayllari + Nginx/Apache |
| **Veb server** | Daxili (Tomcat/Jetty/Netty) | Xarici (Nginx + PHP-FPM) |
| **Build prosesi** | `mvn package` / `gradle build` | `composer install --no-dev` |
| **Konfiqurasiya cache** | Yoxdur (lazim deyil) | `config:cache`, `route:cache` |
| **Hosting xidmetleri** | AWS, GCP, Azure (isteniley) | Forge, Vapor, isteniley server |
| **Serverless** | AWS Lambda (Spring Cloud Function) | Laravel Vapor (AWS Lambda) |
| **Monitoring** | Actuator (built-in) | Telescope, Pulse (paketler) |
| **Zero-downtime** | Rolling deployment (Docker) | `php artisan down` + `up` |
| **Proses idaraesi** | JVM prosesi (systemd) | PHP-FPM + Supervisor |
| **Migrasiyalar** | Flyway/Liquibase | `php artisan migrate` |

## Niye bele ferqler var?

**Spring Boot-un ustunluyu:** Spring Boot tetbiqi tek JAR fayldir - icinde hem tetbiq kodu, hem de veb server var. Bu "fat JAR" yanasmasi deployment-i son derece sadelesdirir: `java -jar app.jar` ve tetbiq isleyir. Xarici veb server, konfiqurasiya, plugin qurmaq lazim deyil. Bu, Docker container-leri ucun ideal yanasmadirr.

**Laravel-in ustunluyu:** Laravel PHP-FPM + Nginx arxitekturasi uezerine quruludur. PHP-FPM her sorgu ucun ayri proses yaradir ve bu prosesler arasinda yaddas paylasmi yoxdur - bu, bir sogrgunun xetasinin basqalarini xetalandirmasinin qarsisini alir. Forge ve Vapor kimi aletler deployment-i coxlu sadelesdirir - Forge ile git push edersiniz, deploy avtomatik bas verir.

**Konfiqurasiya cache:** Laravel-de `config:cache`, `route:cache` emrleri var, cunki PHP her sorquda fayllari oxuyur. Bu cache-ler I/O emeliyyatlarini azaldir. Spring-de buna ehtiyac yoxdur, cunki JVM basladiqda her sey yaddasa yuklenmir ve davaml isleyir.

**Serverless ferqi:** Vapor Laravel ucun tam serverless helldir - AWS Lambda uezerine quruludur, server idaraesine ehtiyac yoxdur, avtomatik scale olunur. Spring Cloud Function da buna oxsar funksionalliq verir, amma daha cox konfiqurasiya teleb edir ve cold start problemi JVM sebebile daha ciddidir.

## Hansi framework-de var, hansinda yoxdur?

- **Embedded server (fat JAR)** - Yalniz Spring Boot-da. Tek fayl ile tetbiqi baslatmaq mumkundur.
- **Laravel Forge** - Yalniz Laravel ucun. Server provision ve deploy idare etme paneli.
- **Laravel Vapor** - Yalniz Laravel ucun. Tam serverless deployment (AWS Lambda).
- **Actuator** - Yalniz Spring-de. Built-in health check, metrics, monitoring.
- **`php artisan down/up`** - Yalniz Laravel-de. Maintenance mode ile zero-downtime-a yaxin deployment.
- **`config:cache` / `route:cache`** - Yalniz Laravel-de. Konfiqurasiya ve route optimizasiyasi.
- **GraalVM native image** - Yalniz Spring-de (Spring Native). Java tetbiqini native binary-e cevirmek - ani baslayis ve az yaddas.
- **`php artisan migrate --force`** - Laravel-de production-da migrasiya isletmek ucun xususi flag.
- **Supervisor inteqrasiyasi** - Laravel queue worker-leri ucun Supervisor konfiqurasiyasi standartdir. Spring-de buna ehtiyac yoxdur (JVM daxilinde isleyir).
- **Multi-stage Docker build** - Her ikisinde mumkundur, amma Spring ucun daha vacibdir (JDK build ucun, JRE runtime ucun).
