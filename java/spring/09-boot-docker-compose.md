# 09 — Spring Boot Docker Compose Support — Geniş İzah

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [Spring Boot Docker Compose nədir?](#spring-boot-docker-compose-nədir)
2. [Əsas istifadə](#əsas-istifadə)
3. [Service Connection](#service-connection)
4. [Testcontainers inteqrasiyası](#testcontainers-inteqrasiyası)
5. [Custom konfigurasiya](#custom-konfiqurasiya)
6. [Development workflow](#development-workflow)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Spring Boot Docker Compose nədir?

```
Spring Boot 3.1+ — Docker Compose avtomatik inteqrasiyası

Köhnə problem:
  Developer → "mvn spring-boot:run" çalışdırır
  Spring: "Redis server tapılmadı!" — xəta
  Developer: ayrı terminal → "docker compose up -d redis"
  Tekrar Spring-i başlat

  Unudulan servis → "Niyə test işləmir?" → saatlarla debug

Spring Boot 3.1 həlli:
  Application start olduqda → docker-compose.yml tapır →
  Lazımlı service-ləri avtomatik başladır →
  Service hazır olduqda → application datasource-u konfiqur edir

Üstünlüklər:
  ✅ Zero configuration: spring.datasource.url yazmaq lazım deyil
  ✅ Service ready-check: Postgres tam hazır olmadan connect etmir
  ✅ App dayandıqda service-lər dayandırılmır (yenidən iş asandır)
  ✅ @ServiceConnection: avtomatik connection properties inject
  ✅ Dev/test workflow sadələşir
```

---

## Əsas istifadə

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-docker-compose</artifactId>
    <scope>developmentOnly</scope>  <!-- Production-a getmir! -->
    <optional>true</optional>
</dependency>
```

```yaml
# compose.yml (ya da docker-compose.yml)
# Spring Boot avtomatik tapır

services:
  postgres:
    image: 'postgres:15'
    environment:
      - 'POSTGRES_DB=mydb'
      - 'POSTGRES_PASSWORD=secret'
      - 'POSTGRES_USER=myuser'
    ports:
      - '5432'        # Random port — collision yoxdur!
    # Spring Boot @ServiceConnection avtomatik konfiqur edir:
    # spring.datasource.url, username, password

  redis:
    image: 'redis:7'
    ports:
      - '6379'

  kafka:
    image: 'confluentinc/cp-kafka:7.5.0'
    environment:
      KAFKA_ZOOKEEPER_CONNECT: zookeeper:2181
      KAFKA_ADVERTISED_LISTENERS: PLAINTEXT://localhost:9092
    ports:
      - '9092'
    depends_on:
      - zookeeper

  zookeeper:
    image: 'confluentinc/cp-zookeeper:7.5.0'
    environment:
      ZOOKEEPER_CLIENT_PORT: 2181
```

```yaml
# application.yml — @ServiceConnection ilə minimal konfiqurasiya
spring:
  # Artıq yazmaq lazım deyil:
  # datasource.url, username, password
  # data.redis.host, port
  # kafka.bootstrap-servers

  docker:
    compose:
      enabled: true
      lifecycle-management: start-and-stop  # App ilə birlikdə
      file: compose.yml                     # Default axtarış qaydası
```

---

## Service Connection

```java
// ─── @ServiceConnection — avtomatik configuration ─────────
// Spring Boot tanınan image-lər üçün avtomatik properties inject edir

// Dəstəklənən service-lər:
// postgres → spring.datasource.*
// redis     → spring.data.redis.*
// mongodb   → spring.data.mongodb.*
// rabbitmq  → spring.rabbitmq.*
// kafka     → spring.kafka.*
// cassandra → spring.cassandra.*
// neo4j     → spring.neo4j.*
// zipkin    → management.zipkin.tracing.*

// ─── Testcontainers ilə @ServiceConnection ───────────────
@SpringBootTest
class OrderServiceIntegrationTest {

    @Container
    @ServiceConnection  // ← Bu annotation magical!
    static PostgreSQLContainer<?> postgres =
        new PostgreSQLContainer<>("postgres:15");

    @Container
    @ServiceConnection
    static RedisContainer redis =
        new RedisContainer(DockerImageName.parse("redis:7"));

    // spring.datasource.* avtomatik postgres container-ından alınır
    // spring.data.redis.* avtomatik redis container-ından alınır
    // Heç bir @DynamicPropertySource lazım deyil!

    @Autowired
    private OrderRepository orderRepository;

    @Test
    void shouldSaveAndRetrieveOrder() {
        Order order = new Order("customer-1", BigDecimal.TEN);
        Order saved = orderRepository.save(order);

        assertThat(saved.getId()).isNotNull();
    }
}

// ─── Custom ServiceConnection ─────────────────────────────
// Öz custom service üçün ServiceConnection əlavə et

@ServiceConnection
@Target(ElementType.FIELD)
@Retention(RetentionPolicy.RUNTIME)
public @interface MyCustomServiceConnection {}

// Implement ServiceConnectionFactory
public class MyCustomServiceConnectionFactory
        implements ServiceConnectionFactory<MyContainer, MyServiceConnection> {

    @Override
    public MyServiceConnection create(ContainerConnectionSource<MyContainer> source) {
        MyContainer container = source.getContainer();
        return new MyServiceConnection(container.getHost(), container.getMappedPort(8080));
    }
}
```

---

## Testcontainers inteqrasiyası

```java
// ─── Spring Boot 3.1+ Testcontainers Dev Mode ────────────
// Test konfiqurasyonu → Lokal development-dəki kimi!

// src/test/java/com/example/TestApplication.java
@SpringBootApplication
public class TestApplication {

    public static void main(String[] args) {
        // Development mühitini test containerları ilə başlat!
        SpringApplication.from(Application::main)
            .with(TestcontainersConfiguration.class)
            .run(args);
    }
}

// src/test/java/com/example/TestcontainersConfiguration.java
@TestConfiguration(proxyBeanMethods = false)
public class TestcontainersConfiguration {

    @Bean
    @ServiceConnection
    PostgreSQLContainer<?> postgresContainer() {
        return new PostgreSQLContainer<>(DockerImageName.parse("postgres:15"))
            .withInitScript("init.sql");
    }

    @Bean
    @ServiceConnection
    RedisContainer redisContainer() {
        return new RedisContainer(DockerImageName.parse("redis:7-alpine"));
    }

    @Bean
    @ServiceConnection
    KafkaContainer kafkaContainer() {
        return new KafkaContainer(DockerImageName.parse("confluentinc/cp-kafka:7.5.0"));
    }
}

// İstifadə: mvn spring-boot:test-run
// Ya da: ./mvnw spring-boot:test-run -Dspring-boot.run.main-class=com.example.TestApplication

// ─── @SpringBootTest ilə Testcontainers ──────────────────
@SpringBootTest(webEnvironment = SpringBootTest.WebEnvironment.RANDOM_PORT)
@Testcontainers
class FullIntegrationTest {

    @Container
    @ServiceConnection
    static PostgreSQLContainer<?> postgres =
        new PostgreSQLContainer<>("postgres:15")
            .withReuse(true);   // Container-ı test-lər arasında saxla

    @Container
    @ServiceConnection
    static GenericContainer<?> redis =
        new GenericContainer<>("redis:7-alpine")
            .withExposedPorts(6379)
            .withReuse(true);

    @Autowired
    private TestRestTemplate restTemplate;

    @Test
    void createOrder_returnsCreatedStatus() {
        var request = new CreateOrderRequest("customer-1",
            List.of(new OrderItemDto("product-1", 2, new BigDecimal("25.00"))));

        var response = restTemplate.postForEntity("/api/orders", request, OrderDto.class);

        assertThat(response.getStatusCode()).isEqualTo(HttpStatus.CREATED);
        assertThat(response.getBody()).isNotNull();
        assertThat(response.getBody().status()).isEqualTo("PENDING");
    }
}
```

---

## Custom konfigurasiya

```yaml
# application.yml — Docker Compose konfiqurasiyası

spring:
  docker:
    compose:
      enabled: true

      # Lifecycle management seçimləri:
      # start-and-stop: App ilə birlikdə başla/dayandır
      # start-only: App başladıqda başlat, dayandırma
      # none: Docker Compose inteqrasiyasını deaktiv et
      lifecycle-management: start-only

      # Compose faylı yeri
      file:
        - compose.yml
        - docker-compose.override.yml

      # Bəzi servisləri skip et
      skip:
        in-tests: false  # Test-lərdə Docker Compose istifadə et

      # Servis-ə spesifik konfiqurasiya
      profiles:
        active:
          - dev    # docker compose --profile dev
```

```yaml
# compose.yml — Profile-based konfigurasiya
services:
  postgres:
    image: postgres:15
    profiles: ['', 'dev', 'full']
    environment:
      POSTGRES_DB: mydb
      POSTGRES_PASSWORD: secret
      POSTGRES_USER: myuser
    ports:
      - '5432'
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U myuser -d mydb"]
      interval: 5s
      timeout: 3s
      retries: 5

  redis:
    image: redis:7-alpine
    profiles: ['', 'dev', 'full']
    ports:
      - '6379'
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s

  # Yalnız 'full' profile-da
  kafka:
    image: confluentinc/cp-kafka:7.5.0
    profiles: ['full']
    ports:
      - '9092'
    environment:
      KAFKA_ADVERTISED_LISTENERS: PLAINTEXT://localhost:9092
      KAFKA_ZOOKEEPER_CONNECT: zookeeper:2181

  # Development alətləri
  pgadmin:
    image: dpage/pgadmin4:latest
    profiles: ['tools']
    ports:
      - '5050:80'
    environment:
      PGADMIN_DEFAULT_EMAIL: admin@example.com
      PGADMIN_DEFAULT_PASSWORD: admin

  redis-commander:
    image: rediscommander/redis-commander:latest
    profiles: ['tools']
    ports:
      - '8081:8081'
    environment:
      REDIS_HOSTS: local:redis:6379

  # Wiremock — external API mock üçün
  wiremock:
    image: wiremock/wiremock:3.3.1
    profiles: ['dev', 'full']
    ports:
      - '8090:8080'
    volumes:
      - ./wiremock:/home/wiremock
    command: --verbose
```

```java
// ─── Compose file customizer ─────────────────────────────
@Component
public class PostgresComposeConnectionCustomizer
        implements DockerComposeConnectionDetailsFactory.DockerComposeConnectionDetails {

    // Custom compose service-ə bağlanma məntiqi əlavə etmək üçün
}

// ─── Connection details custom override ──────────────────
// Bəzi xüsusi service-lər üçün connection details manual müəyyən et

@Configuration
public class CustomConnectionConfig {

    @Bean
    @Primary
    @ConditionalOnMissingBean(DataSource.class)
    public DataSource customDataSource(
            @Value("${custom.db.url}") String url) {
        // Custom connection logic
        return DataSourceBuilder.create().url(url).build();
    }
}
```

---

## Development workflow

```bash
# ─── Typical development workflow ────────────────────────

# 1. İlk dəfə start
./mvnw spring-boot:run
# Spring Boot avtomatik olaraq:
#   → compose.yml tapır
#   → docker compose up postgres redis
#   → Servicelər hazır olana qədər gözləyir
#   → Application başlayır

# 2. Development zamanı (hot reload)
./mvnw spring-boot:run    # Application restart
# → Container-lər hələ çalışır (deyil restart)
# → Sürətli yenidən başlama

# 3. Testcontainers dev mode (3.1+)
./mvnw spring-boot:test-run
# → Test container-ları ilə development
# → Real postgres/redis container

# 4. Dayandırma
Ctrl+C
# → Application dayandı
# → lifecycle-management=start-only → container-lər çalışır
# → lifecycle-management=start-and-stop → container-lər dayandı

# 5. Production (docker-compose dependency yoxdur)
# spring-boot-docker-compose scope=developmentOnly
# → Production jar-ına daxil olmur
```

```java
// ─── application-local.yml — local development ───────────
/*
# application-local.yml (gitignore-da!)
spring:
  docker:
    compose:
      enabled: true
      lifecycle-management: start-only

# IDE-də: Run configuration → VM options:
# -Dspring.profiles.active=local

# Ya da environment variable:
# SPRING_PROFILES_ACTIVE=local ./mvnw spring-boot:run
*/

// ─── ReadinessState — Service connection hazır olana qədər ─
@Component
public class ApplicationStartListener {

    @EventListener
    public void onApplicationReady(ApplicationReadyEvent event) {
        log.info("Application fully started — Docker Compose services connected");
        // Bütün service connection-lar hazırdır
    }
}
```

---

## İntervyu Sualları

### 1. Spring Boot Docker Compose Support nədir?
**Cavab:** Spring Boot 3.1-də gəldi. Application start olduqda `compose.yml` faylı avtomatik tapılır, lazımlı Docker servisləri başladılır, servis hazır olduqda application onlara avtomatik bağlanır. `@ServiceConnection` annotasiyası ilə postgres, redis, kafka kimi tanınan service-lər üçün `spring.datasource.*` kimi properties avtomatik inject olunur. `scope=developmentOnly` — bu dependency production jar-ına daxil olmur.

### 2. @ServiceConnection necə işləyir?
**Cavab:** Spring Boot tanınan Docker image-lər (postgres, redis, mongodb, kafka...) üçün `ConnectionDetailsFactory` implementasiyaları daxil edilib. Container başladıqda (Docker Compose ya da Testcontainers) Spring onun image adını tanıyır, `ConnectionDetails` Bean yaradır, bu Bean application properties-i override edir. Nəticə: `spring.datasource.url` manual yazmaq lazım deyil, random port istifadə edilə bilər (collision yoxdur). Testcontainers + `@ServiceConnection` ilə `@DynamicPropertySource` artıq lazım deyil.

### 3. Testcontainers Dev Mode nədir?
**Cavab:** Spring Boot 3.1+. Test qovluğunda `TestApplication.java` yazılır — `SpringApplication.from(Application::main).with(TestcontainersConfiguration.class).run(args)`. Testcontainers-dəki container-ları `@Bean` kimi konfiqurə edib `@ServiceConnection` əlavə etsən, `./mvnw spring-boot:test-run` ilə real container-larla development edilir. Faydası: production-a yaxın mühit, real DB/Redis/Kafka, amma local kurulum lazım deyil.

### 4. Lifecycle management seçimləri nədir?
**Cavab:** `start-and-stop` (default) — app başladıqda compose up, app dayandıqda compose down. Hər restart-da container-lər yenidən başlayır — yavaş. `start-only` — app başladıqda compose up, amma dayandıqda container-lər qalır. Tövsiyə edilən development üçün: container-lar bir dəfə başlayır, sürətli restart. `none` — Docker Compose inteqrasiyası deaktiv. `compose.yml` tapılmasa da xəta olmur.

### 5. Docker Compose vs Testcontainers nə vaxt istifadə edilir?
**Cavab:** **Docker Compose** (`spring-boot-docker-compose`) — local development üçün. Dev environment-i bir dəfə başladıb development etmək üçün ideal. `compose.yml` bütün komanda üçün standart. **Testcontainers** — integration test-lər üçün. Hər test run-ında təzə container, reproducible. CI/CD-də də işləyir. Spring Boot 3.1+ hər ikisini `@ServiceConnection` ilə birləşdirir: `TestcontainersConfiguration` Testcontainers-i dev mode-da da işlədə bilir. Seçim: automated test → Testcontainers; dev environment → Docker Compose.

*Son yenilənmə: 2026-04-10*
