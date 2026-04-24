# 023 — Spring Native & GraalVM — Geniş İzah
**Səviyyə:** Ekspert


## Mündəricat
1. [GraalVM Native Image nədir?](#graalvm-native-image-nədir)
2. [Spring Native quraşdırma](#spring-native-quraşdırma)
3. [Native Image build](#native-image-build)
4. [AOT Processing](#aot-processing)
5. [Reflection & Serialization hints](#reflection--serialization-hints)
6. [Testcontainers & Native Testing](#testcontainers--native-testing)
7. [İntervyu Sualları](#intervyu-sualları)

---

## GraalVM Native Image nədir?

```
JVM vs Native Image:

  JVM (Traditional):
    → Java bytecode → JVM interpret/JIT
    → Başlama: 2-10 saniyə (Spring Boot)
    → RAM: 256MB-1GB+
    → JIT optimizasiyası ilə zamanla sürətlənir
    → Serverful (uzun müddət çalışan)

  GraalVM Native Image:
    → Java → Ahead-of-Time (AOT) compile → native binary
    → Başlama: 50-200ms!
    → RAM: 50-150MB
    → JIT yoxdur — peak performance aşağı
    → Serverless, container-lər üçün ideal

Nə vaxt Native?
  ✅ Serverless (AWS Lambda, Azure Functions)
  ✅ CLI tools (graalvm cli apps)
  ✅ Microservice container-ları (sürətli başlama, az RAM)
  ✅ Cost optimization (az RAM → az $)

Nə vaxt JVM?
  ✅ Uzun müddət çalışan servis (JIT zamanla daha sürətli)
  ✅ Reflection-heavy frameworks
  ✅ Dynamic class loading
  ✅ Development (build sürəti vacibdir)

GraalVM Native Image məhdudiyyətləri:
  ❌ Build çox yavaş (2-15 dəqiqə)
  ❌ Dynamic features məhdud (reflection, dynamic proxy)
  ❌ Closed world assumption (runtime class yüklənmir)
  ❌ Bəzi library-lər dəstəkləmir
  ❌ Debug çətin
```

---

## Spring Native quraşdırma

```xml
<!-- pom.xml — Spring Boot 3.x (Native dəstəyi daxildir!) -->
<properties>
    <java.version>21</java.version>
</properties>

<build>
    <plugins>
        <plugin>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-maven-plugin</artifactId>
            <configuration>
                <!-- Native build üçün Buildpacks -->
                <image>
                    <builder>paketobuildpacks/builder-jammy-tiny:latest</builder>
                    <env>
                        <BP_NATIVE_IMAGE>true</BP_NATIVE_IMAGE>
                    </env>
                </image>
            </configuration>
            <executions>
                <execution>
                    <id>process-aot</id>
                    <goals>
                        <goal>process-aot</goal>
                    </goals>
                </execution>
            </executions>
        </plugin>

        <!-- Native Build Tools -->
        <plugin>
            <groupId>org.graalvm.buildtools</groupId>
            <artifactId>native-maven-plugin</artifactId>
            <configuration>
                <buildArgs>
                    <buildArg>--initialize-at-build-time=org.slf4j</buildArg>
                    <buildArg>-H:+ReportExceptionStackTraces</buildArg>
                    <buildArg>--no-fallback</buildArg>
                </buildArgs>
            </configuration>
            <executions>
                <execution>
                    <id>add-reachability-metadata</id>
                    <goals>
                        <goal>add-reachability-metadata</goal>
                    </goals>
                </execution>
            </executions>
        </plugin>
    </plugins>
</build>
```

---

## Native Image build

```bash
# ─── Native Image build üsulları ─────────────────────────

# Üsul 1: Maven Native Plugin (lokal GraalVM lazım)
./mvnw -Pnative native:compile

# Üsul 2: Buildpacks (Docker, GraalVM qurulum lazım deyil!)
./mvnw spring-boot:build-image -Pnative

# Üsul 3: Dockerfile ilə (çox nəzarət)

# ─── Dockerfile — Multi-stage Native Build ────────────────
# Stage 1: Native Image build
FROM ghcr.io/graalvm/native-image-community:21 AS builder

WORKDIR /app
COPY .mvn .mvn
COPY mvnw pom.xml ./
RUN ./mvnw dependency:go-offline -q

COPY src src
# AOT processing
RUN ./mvnw spring-boot:process-aot -q
# Native compile (uzun çəkir!)
RUN ./mvnw native:compile -Pnative -DskipTests

# Stage 2: Minimal runtime image
FROM ubuntu:22.04 AS runtime
RUN apt-get update && apt-get install -y \
    libstdc++6 zlib1g \
    && rm -rf /var/lib/apt/lists/*

RUN groupadd -r spring && useradd -r -g spring spring
WORKDIR /app
COPY --from=builder /app/target/myapp ./myapp
USER spring

EXPOSE 8080
ENTRYPOINT ["./myapp"]

# ─── Müqayisə ─────────────────────────────────────────────
# JVM image:    ~200MB (JRE) + 50MB (app) = 250MB
# Native image: ~80MB total (statically linked)

# Start time:
# JVM:    2-5 saniyə
# Native: 50-100ms

# Memory (idle):
# JVM:    256MB+
# Native: 50-80MB

# Build time:
# JVM:    30 saniyə
# Native: 5-15 dəqiqə

# ─── Native Image Profile ─────────────────────────────────
/*
# pom.xml-ə profile əlavə et:
<profiles>
    <profile>
        <id>native</id>
        <build>
            <plugins>
                <plugin>
                    <groupId>org.graalvm.buildtools</groupId>
                    <artifactId>native-maven-plugin</artifactId>
                    <executions>
                        <execution>
                            <id>build-native</id>
                            <goals>
                                <goal>compile-no-fork</goal>
                            </goals>
                            <phase>package</phase>
                        </execution>
                    </executions>
                </plugin>
            </plugins>
        </build>
    </profile>
</profiles>
*/
```

---

## AOT Processing

```java
// ─── AOT (Ahead-of-Time) Processing nədir? ───────────────
// Spring Boot 3.x build zamanı application context analiz edir:
// → Bean definition-lar
// → Component scan
// → Configuration classes
// → Bu məlumatı source code kimi yaradır

// AOT-generated source (target/spring-aot/main/sources/...):
// → ApplicationContextInitializer implementation
// → BeanDefinitionRegistrar
// → Reflection hints
// → Proxy hints

// ─── @ImportRuntimeHints — Manual hints ──────────────────
// Native Image üçün lazımlı reflection/resource hints əlavə et

@Configuration
@ImportRuntimeHints(MyRuntimeHints.class)
public class MyConfig {
    // ...
}

public class MyRuntimeHints implements RuntimeHintsRegistrar {

    @Override
    public void registerHints(RuntimeHints hints, ClassLoader classLoader) {
        // ─── Reflection hints ─────────────────────────────
        // Bu class-ların reflection-a icazə ver
        hints.reflection()
            .registerType(MyCustomClass.class,
                MemberCategory.INVOKE_DECLARED_CONSTRUCTORS,
                MemberCategory.INVOKE_DECLARED_METHODS,
                MemberCategory.DECLARED_FIELDS)
            .registerType(SomeLibraryDto.class,
                MemberCategory.INVOKE_PUBLIC_CONSTRUCTORS,
                MemberCategory.PUBLIC_FIELDS);

        // ─── Resource hints ───────────────────────────────
        hints.resources()
            .registerPattern("templates/*.html")
            .registerPattern("config/*.yml")
            .registerResourceBundle("messages");

        // ─── Serialization hints ──────────────────────────
        hints.serialization()
            .registerType(MySerializableClass.class)
            .registerType(OrderDto.class);

        // ─── Proxy hints ──────────────────────────────────
        hints.proxies()
            .registerJdkProxy(MyInterface.class);
    }
}

// ─── @Reflective annotation ───────────────────────────────
// Class üzərindəki hint — native-də reflection activate

@Reflective  // Bu class üçün reflection hints avtomatik əlavə
public class ProductMapper {
    public ProductDto toDto(Product product) {
        // Jackson, MapStruct kimi lib-lər reflection istifadə edir
        return new ProductDto(product.getName(), product.getPrice());
    }
}

// ─── @RegisterReflectionForBinding ───────────────────────
@Configuration
@RegisterReflectionForBinding({OrderDto.class, ProductDto.class, UserDto.class})
public class SerializationConfig {
    // Bu DTO-lar JSON serialization üçün reflect ediləcək
}

// ─── Spring Data JPA + Native ─────────────────────────────
// Spring Data JPA native-da işləyir, amma bəzi konfigurasiya lazımdır

@Entity
@Table(name = "orders")
public class Order {
    // Native-də JPA annotation-lar reflection tələb edir
    // Spring Boot 3.x avtomatik handle edir
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;
    // ...
}

// ─── Hibernate Native hints ───────────────────────────────
@Configuration
public class HibernateNativeConfig implements BeanFactoryInitializationAotProcessor {
    // Spring Boot 3.x avtomatik konfiqurasiya edir
    // Manual konfigurasiya nadir lazım olur
}
```

---

## Reflection & Serialization hints

```java
// ─── Jackson Serialization Native ────────────────────────
// Jackson reflection istifadə edir — native-da hints lazım

@Configuration
public class JacksonNativeConfig {

    @Bean
    public RuntimeHintsRegistrar jacksonHints() {
        return (hints, classLoader) -> {
            // Jackson ObjectMapper üçün
            hints.reflection()
                .registerType(ObjectMapper.class,
                    MemberCategory.INVOKE_PUBLIC_CONSTRUCTORS)
                .registerType(JsonNode.class,
                    MemberCategory.INVOKE_PUBLIC_METHODS);

            // DTO-lar üçün
            Stream.of(OrderDto.class, ProductDto.class, UserDto.class)
                .forEach(clazz ->
                    hints.serialization().registerType(clazz)
                );
        };
    }
}

// ─── @JsonAutoDetect + @JsonCreator ───────────────────────
// Native-da daha açıq annotation-lar lazım

@JsonAutoDetect(fieldVisibility = JsonAutoDetect.Visibility.ANY)
public record OrderDto(
    @JsonProperty("id") String id,
    @JsonProperty("status") String status,
    @JsonProperty("total") BigDecimal total
) {
    @JsonCreator
    public OrderDto(
            @JsonProperty("id") String id,
            @JsonProperty("status") String status,
            @JsonProperty("total") BigDecimal total) {
        this(id, status, total);
    }
}

// ─── Native Test ──────────────────────────────────────────
// Native image-ı test et — build etmədən sürətli yoxlama

@SpringBootTest
@TestPropertySource(properties = {
    "spring.aot.enabled=true"  // AOT mode aktivdir
})
class NativeCompatibilityTest {

    @Autowired
    private OrderService orderService;

    @Test
    void contextLoads() {
        // Kontekst native-da da yüklənirmi?
        assertThat(orderService).isNotNull();
    }

    @Test
    void jsonSerializationWorks() throws Exception {
        ObjectMapper mapper = new ObjectMapper();
        OrderDto dto = new OrderDto("1", "PENDING", BigDecimal.TEN);

        String json = mapper.writeValueAsString(dto);
        OrderDto back = mapper.readValue(json, OrderDto.class);

        assertThat(back.id()).isEqualTo("1");
    }
}

// ─── Native Image Agent — hints avtomatik tap ─────────────
// JVM ilə test çalışdır, agent reflection-ları yaz:
// java -agentlib:native-image-agent=config-output-dir=src/main/resources/META-INF/native-image \
//      -jar myapp.jar
// → reflect-config.json, resource-config.json avtomatik yaranır
```

---

## Testcontainers & Native Testing

```yaml
# ─── GitHub Actions — Native CI/CD ───────────────────────
# .github/workflows/native-ci.yml

name: Native CI

on:
  push:
    branches: [main]

jobs:
  native-build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Set up GraalVM
        uses: graalvm/setup-graalvm@v1
        with:
          java-version: '21'
          distribution: 'graalvm-community'
          native-image: true
          cache: 'maven'

      - name: Build and Test Native Image
        run: ./mvnw -Pnative native:compile -DskipTests

      - name: Run native tests
        run: ./mvnw -Pnative test

      - name: Build Docker image
        run: ./mvnw spring-boot:build-image -Pnative -DskipTests

      - name: Check startup time
        run: |
          docker run -d --name native-app myapp:latest
          sleep 1
          docker logs native-app | grep "Started"
          docker stop native-app

  jvm-build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-java@v4
        with:
          java-version: '21'
          distribution: temurin
          cache: maven
      - run: ./mvnw verify
```

```java
// ─── AWS Lambda ilə Spring Native ────────────────────────
// spring-cloud-function-aws-lambda + Spring Native

@SpringBootApplication
public class LambdaApplication {
    public static void main(String[] args) {
        SpringApplication.run(LambdaApplication.class, args);
    }
}

@Component
public class OrderHandler implements Function<OrderRequest, OrderResponse> {

    private final OrderService orderService;

    @Override
    public OrderResponse apply(OrderRequest request) {
        return orderService.process(request);
    }
}

// application.yml:
// spring.cloud.function.definition: orderHandler

// Lambda cold start:
// JVM:    3-5 saniyə (SnapStart ilə ~1s)
// Native: 50-100ms  ← dramatikal fərq!
```

---

## İntervyu Sualları

### 1. GraalVM Native Image nədir?
**Cavab:** GraalVM-in Ahead-of-Time (AOT) kompilyatoru Java kodu birbaşa native binary-yə çevirir. JVM lazım deyil — standart OS executable. Üstünlüklər: başlama 50-200ms (JVM-də 2-10s), RAM 50-150MB (JVM-də 256MB+), kiçik container. Məhdudiyyətlər: dynamic reflection, dynamic class loading, runtime proxy yaratma məhdudlaşır; build 5-15 dəqiqə çəkir; JIT olmadığı üçün peak throughput aşağı ola bilər. Serverless (Lambda), CLI tool, microservice container-ları üçün idealdır.

### 2. AOT Processing nədir?
**Cavab:** Spring Boot 3.x build zamanı application context-i analiz edir (Ahead-of-Time). Bean definition-lar, component scan, configuration class-lar — bunların nəticəsi source code kimi yaranır. Native Image bu kodu alır, runtime analysis lazım olmur. Spring Boot 3.x `spring-boot:process-aot` mərhələsini avtomatik icra edir. Bu native image build-ı mümkün edir: runtime-da reflection/proxy olmadan bütün wiring build-time-da bilinir.

### 3. Reflection hints nə üçün lazımdır?
**Cavab:** Native Image "closed world assumption" istifadə edir — yalnız build-time-da bilinən kod daxil edilir. Reflection istifadəsi (Jackson serialization, JPA, Spring AOP) runtime-da class-ları dinamik yüklər — native-da bu işləmir. `RuntimeHintsRegistrar` ilə: hansı class-ların reflection, serialization, resource, proxy icazəsi olduğunu bildiririk. Spring Boot 3.x çox hints avtomatik konfiqurə edir (`@SpringBootApplication` ilə), amma öz library-lərin üçün `@ImportRuntimeHints` lazımdır.

### 4. Native Image-in məhdudiyyətləri nələrdir?
**Cavab:** (1) **Dynamic reflection** — runtime-da bilinməyən class-lar; hints olmadan `ReflectionException`. (2) **Dynamic class loading** — `Class.forName(dynamicName)` işləmir. (3) **Dynamic proxy** — JDK Proxy build-time-da bilinməlidir. (4) **Build time** — 5-15 dəqiqə (JVM-də 30s). (5) **Library compatibility** — bütün library-lər native-ı dəstəkləmir (GraalVM reachability metadata lazım). (6) **JIT yoxdur** — long-running servis üçün JVM throughput-u üstündür. (7) **Debug çətin** — native binary-ni debug etmək. Spring Boot 3.x bu məhdudiyyətlərin çoxunu avtomatik idarə edir.

### 5. Native Image nə zaman seçilməlidir?
**Cavab:** **Seç**: Serverless (Lambda cold start kritikdir); CLI tools (ani başlama); çoxlu instance container (RAM cost); ödəniş per-request (Lambda cost). **Seçmə**: Uzun müddət çalışan servis (JIT daha sürətli olur); yüksək reflection-heavy framework; development-da (build yavaş); performans-kritik yük altında (JIT optimize edir). Pratikada: production Lambda → native; uzun ömürlü ECS/K8s → JVM (SnapStart ilə kompromis mümkün).

*Son yenilənmə: 2026-04-10*
