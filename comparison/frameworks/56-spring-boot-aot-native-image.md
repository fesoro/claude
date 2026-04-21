# Spring Boot AOT + GraalVM Native Image — Dərin Müqayisə

## Giriş

**AOT (Ahead-of-Time) compilation** — tətbiqi işə salmazdan əvvəl əvvəlcədən kompilyasiya etmək deməkdir. Adi Java tətbiqi JIT (Just-in-Time) istifadə edir: JVM bytecode-u runtime-da native koda çevirir, bu isə "warmup" vaxtı tələb edir və yaddaşı çox istifadə edir. **Spring Boot 3** AOT processing vasitəsilə bean metadatasını, reflection, proxy-ləri build vaxtında hazırlayır. **GraalVM Native Image** bu metadatadan istifadə edərək tətbiqi tək icra edilə bilən binary fayla çevirir — JVM olmadan işləyir, startup ~80ms, yaddaş isə JVM-in 1/5-i qədərdir.

Laravel-də isə dil PHP olduğu üçün "native binary" konsepti fərqlidir. PHP hər sorğuda interpret olunur. Ən yaxın analog **Laravel Octane** (Swoole/RoadRunner/FrankenPHP) — tətbiqi yaddaşda saxlayır, sorğular arasında boot etmir. **FrankenPHP** üstəlik `--embed` rejimində PHP runtime + tətbiqi tək binary kimi paketləyə bilir.

---

## Spring-də istifadəsi

### 1) AOT processing nə verir?

Spring Boot 3.3+ -də AOT yanaşması:

- **Bean definition-lar** build vaxtında generate olunur (runtime scan azalır)
- **Reflection hint-ləri** toplanır — hansı class reflection ilə çağırılır
- **Resource hint-ləri** — hansı resurs yüklənir (template, properties)
- **Proxy class-ları** əvvəlcədən yaradılır
- **Conditional-lar** (`@ConditionalOnClass`) build vaxtında həll olunur

Bunların nəticəsi: JVM modunda da startup təxminən 30–40% sürətlənir. GraalVM Native Image üçün isə AOT metadatası **şərtdir** — olmadan native image build uğursuz olar.

### 2) `pom.xml` quraşdırması

```xml
<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0">
    <modelVersion>4.0.0</modelVersion>

    <parent>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-parent</artifactId>
        <version>3.3.4</version>
    </parent>

    <groupId>com.example</groupId>
    <artifactId>orders-api</artifactId>
    <version>1.0.0</version>

    <properties>
        <java.version>21</java.version>
    </properties>

    <dependencies>
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-web</artifactId>
        </dependency>
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-data-jpa</artifactId>
        </dependency>
        <dependency>
            <groupId>org.postgresql</groupId>
            <artifactId>postgresql</artifactId>
            <scope>runtime</scope>
        </dependency>
    </dependencies>

    <build>
        <plugins>
            <plugin>
                <groupId>org.springframework.boot</groupId>
                <artifactId>spring-boot-maven-plugin</artifactId>
            </plugin>
        </plugins>
    </build>

    <profiles>
        <profile>
            <id>native</id>
            <build>
                <plugins>
                    <plugin>
                        <groupId>org.graalvm.buildtools</groupId>
                        <artifactId>native-maven-plugin</artifactId>
                        <configuration>
                            <buildArgs>
                                <buildArg>-H:+ReportExceptionStackTraces</buildArg>
                                <buildArg>--no-fallback</buildArg>
                                <buildArg>-H:+UnlockExperimentalVMOptions</buildArg>
                            </buildArgs>
                        </configuration>
                    </plugin>
                </plugins>
            </build>
        </profile>
    </profiles>
</project>
```

Gradle variantı:

```kotlin
plugins {
    id("org.springframework.boot") version "3.3.4"
    id("io.spring.dependency-management") version "1.1.6"
    id("org.graalvm.buildtools.native") version "0.10.3"
    java
}

java {
    toolchain {
        languageVersion = JavaLanguageVersion.of(21)
    }
}

graalvmNative {
    binaries {
        named("main") {
            imageName = "orders-api"
            buildArgs.add("--no-fallback")
        }
    }
}
```

### 3) Native image build

```bash
# JVM modunda klassik build
./mvnw package

# AOT metadatası ilə JVM build (test)
./mvnw -Pnative package -DskipNativeTests

# Tam native image build (GraalVM lazımdır)
./mvnw -Pnative native:compile

# Docker image (Paketo buildpack ilə — GraalVM yüklü olmaya bilər)
./mvnw spring-boot:build-image -Pnative \
       -Dspring-boot.build-image.imageName=orders-api:native
```

Build vaxtı adətən **3–8 dəqiqə** çəkir (adi JAR üçün 30 saniyə). Nəticə: `target/orders-api` — təxminən 80–120 MB icra edilə bilən fayl.

### 4) Reflection hint-ləri (`RuntimeHintsRegistrar`)

Native image reflection-u sevmir — GraalVM closed-world assumption edir. Əgər kodda `Class.forName()`, `Field.get()` və ya JSON serializasiya (Jackson) class-ları reflection-la istifadə edirsə, hint verilməlidir.

```java
public class OrdersRuntimeHints implements RuntimeHintsRegistrar {

    @Override
    public void registerHints(RuntimeHints hints, ClassLoader classLoader) {
        // Reflection — class üzvlərini qeydə al
        hints.reflection().registerType(OrderDto.class,
            MemberCategory.INVOKE_PUBLIC_METHODS,
            MemberCategory.INVOKE_PUBLIC_CONSTRUCTORS,
            MemberCategory.DECLARED_FIELDS);

        // Resource — `src/main/resources/templates/welcome.html`
        hints.resources().registerPattern("templates/*.html");
        hints.resources().registerPattern("static/**");

        // Serialization — Java built-in serialization üçün
        hints.serialization().registerType(OrderEvent.class);

        // Proxy — interface-based dynamic proxy
        hints.proxies().registerJdkProxy(OrderService.class, AuditAware.class);
    }
}

@Configuration
@ImportRuntimeHints(OrdersRuntimeHints.class)
public class HintsConfig {}
```

Alternativ — class səviyyəsində annotasiya:

```java
@RegisterReflectionForBinding({OrderDto.class, CustomerDto.class})
@Configuration
public class ReflectionConfig {}
```

### 5) Native-friendly kod yazmaq

Bu qaydaları izləyin:

```java
// YAX — constructor injection
@Service
public class OrderService {
    private final OrderRepository repo;
    public OrderService(OrderRepository repo) { this.repo = repo; }
}

// PİS — field injection reflection tələb edir
@Service
public class OrderServiceBad {
    @Autowired private OrderRepository repo;
}

// YAX — interface + @HttpExchange declarative client (build vaxtı bilinir)
@HttpExchange("/api/orders")
public interface OrderClient {
    @GetExchange("/{id}")
    Order byId(@PathVariable Long id);
}

// PİS — runtime class loading
Class<?> cls = Class.forName(className);
Object instance = cls.getDeclaredConstructor().newInstance();
```

### 6) Native Tests

```java
// JVM test (adi)
@SpringBootTest
class OrderServiceTest {
    @Autowired OrderService svc;

    @Test void createsOrder() { ... }
}

// Native test — eyni test, amma native binary kimi icra olunur
// mvn -Pnative test
```

Native test yavaşdır (hər test suite üçün yeni native image build) — amma AOT metadatasını tam yoxlayır. CI-da gecə workflow-da işlətmək məsləhətdir.

### 7) Startup və yaddaş müqayisəsi

Real ölçülmüş nəticələr (Spring Petclinic, JDK 21, MacBook M1):

| Metrik | JVM mode | Native image |
|---|---|---|
| Cold start | ~2.1 s | ~0.08 s |
| First request latency | ~240 ms | ~15 ms |
| RSS memory (idle) | ~220 MB | ~55 MB |
| Executable size | ~45 MB JAR | ~95 MB binary |
| Build time | ~25 s | ~4 m |
| Peak throughput (warmed) | daha yüksək (JIT) | biraz aşağı |

### 8) `application.yml` konfiqurasiyası

```yaml
spring:
  application:
    name: orders-api
  main:
    lazy-initialization: false    # native-də lazy yavaşladır
  threads:
    virtual:
      enabled: true               # Java 21 virtual threads
  aot:
    enabled: true                 # JVM modunda da AOT oxu

server:
  port: 8080
  shutdown: graceful

logging:
  level:
    root: INFO
    org.springframework.aot: DEBUG
```

### 9) Dockerfile native image üçün

```dockerfile
# Build stage
FROM ghcr.io/graalvm/graalvm-community:21-muslib AS build
WORKDIR /app
COPY . .
RUN ./mvnw -Pnative native:compile -DskipTests

# Runtime stage — distroless
FROM gcr.io/distroless/base-debian12
COPY --from=build /app/target/orders-api /orders-api
EXPOSE 8080
ENTRYPOINT ["/orders-api"]
```

Son image həcmi: ~100 MB. Müqayisə üçün JVM image (`eclipse-temurin:21-jre-alpine`): ~250 MB.

### 10) Nə zaman native image seçək?

**Məsləhət olunur:**
- Serverless (AWS Lambda, Google Cloud Run, Knative) — cold start kritikdir
- CLI alətləri — hər dəfə yüksək startup istəmirik
- Kubernetes-də auto-scaling aktivdir (çox pod yaradılır)
- Yaddaşa həssas mühitlər (edge, IoT)

**Məsləhət olunmur:**
- Ağır reflection istifadə edən köhnə kitabxanalar (Hibernate bəzi hallar, Drools)
- Tətbiq uzun-ömürlüdür və peak throughput önəmlidir — JIT daha yaxşı optimize edir
- Build vaxtı CI üçün problem yaradır (hər push 5 dəq əlavə)
- Dynamic class loading tələb edən plugin arxitekturası

---

## Laravel-də istifadəsi

PHP interpret olunan dildir — kompilyasiya yoxdur, lakin "warm" etməyin öz yolları var.

### 1) OPcache — bytecode cache

```ini
; php.ini (production)
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0       ; prod-da file mtime yoxlamırıq
opcache.preload=/var/www/preload.php
opcache.preload_user=www-data
opcache.jit=tracing
opcache.jit_buffer_size=128M
```

OPcache PHP source-u parse edib bytecode-u yaddaşda saxlayır. İlk sorğudan sonrakı bütün sorğular üçün parse mərhələsi atılır.

### 2) Preloading (PHP 7.4+)

```php
// preload.php
$files = glob(__DIR__.'/vendor/**/*.php', GLOB_BRACE);
foreach ($files as $file) {
    opcache_compile_file($file);
}
```

PHP-FPM startup-da bütün framework və vendor class-ları bytecode-a çevrilir, bütün worker-lər onları paylaşır.

### 3) Laravel optimize commands

```bash
php artisan config:cache          # config-ları tək faylda merge et
php artisan route:cache           # route-ları cache et
php artisan view:cache            # Blade template-ləri kompilyasiya et
php artisan event:cache           # event-listener xəritəsi
php artisan optimize              # hamısını birdən çağırır
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

Bu komandalar tətbiqin startup latency-ni azaldır. Amma Spring AOT kimi static binary alınmır — hələ də PHP runtime lazımdır.

### 4) Laravel Octane — yaddaşda qalan tətbiq

```bash
composer require laravel/octane
php artisan octane:install        # Swoole, RoadRunner və ya FrankenPHP seçimi
```

```php
// config/octane.php
return [
    'server' => env('OCTANE_SERVER', 'frankenphp'),
    'https'  => env('OCTANE_HTTPS', false),
    'listeners' => [
        WorkerStarting::class => [
            EnsureUploadedFilesAreValid::class,
            EnsureUploadedFilesCanBeMoved::class,
        ],
        RequestReceived::class => [
            ...Octane::prepareApplicationForNextOperation(),
            ...Octane::prepareApplicationForNextRequest(),
        ],
    ],
    'warm' => [
        ...Octane::defaultServicesToWarm(),
    ],
    'cache' => [
        'rows' => 1000,
        'bytes' => 10000,
    ],
    'tables' => [
        'example:1000' => [
            'name' => 'string:1000',
            'votes' => 'int',
        ],
    ],
];
```

```bash
php artisan octane:start --server=frankenphp --port=8000 --workers=4 --task-workers=2
```

Octane tətbiqi yaddaşda saxlayır — hər sorğu üçün framework boot edilmir. Sorğu latency ~15–30 ms-dən 1–3 ms-ə enir.

### 5) FrankenPHP static binary

FrankenPHP Caddy server + PHP runtime-u tək binary kimi paketləyir. `--embed` flag ilə tətbiq də binary-yə qoşulur:

```dockerfile
FROM dunglas/frankenphp:builder AS builder
COPY --from=caddy:builder /usr/bin/xcaddy /usr/bin/xcaddy

# Tətbiqi embed et
RUN CGO_ENABLED=1 \
    XCADDY_SETCAP=1 \
    XCADDY_GO_BUILD_FLAGS="-ldflags='-w -s' -tags=nobadger,nomysql,nopgx" \
    CGO_CFLAGS=$(php-config --includes) \
    CGO_LDFLAGS="$(php-config --ldflags) $(php-config --libs)" \
    xcaddy build \
        --output /usr/local/bin/frankenphp \
        --with github.com/dunglas/frankenphp=./ \
        --with github.com/dunglas/frankenphp/caddy=./caddy/ \
        --with github.com/dunglas/mercure/caddy

# App source
COPY . /app
WORKDIR /app
RUN composer install --no-dev --optimize-autoloader \
 && php artisan config:cache \
 && php artisan route:cache

# Embed — tətbiq binary-nin içində
RUN frankenphp build-static --source=. --output=/usr/local/bin/app

FROM debian:bookworm-slim
COPY --from=builder /usr/local/bin/app /app
EXPOSE 80
CMD ["/app", "php-server"]
```

Nəticə: ~60–90 MB binary + tətbiq. PHP runtime ayrıca yüklənməsinə ehtiyac yoxdur.

### 6) `composer.json` optimize settings

```json
{
    "name": "acme/orders",
    "require": {
        "php": "^8.3",
        "laravel/framework": "^11.0",
        "laravel/octane": "^2.3"
    },
    "config": {
        "optimize-autoloader": true,
        "classmap-authoritative": true,
        "apcu-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true
    },
    "scripts": {
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi"
        ],
        "post-install-cmd": [
            "@php artisan optimize"
        ]
    }
}
```

### 7) Startup və yaddaş müqayisəsi

| Metrik | Laravel PHP-FPM | Laravel Octane (Swoole) | FrankenPHP embed |
|---|---|---|---|
| Cold start (1-ci sorğu) | ~200 ms | ~50 ms | ~40 ms |
| İkinci sorğu latency | ~80 ms | ~3 ms | ~2 ms |
| RSS memory (idle) | ~80 MB (per worker) | ~120 MB | ~90 MB |
| Executable size | PHP runtime + app | PHP runtime + app | ~85 MB binary |
| Build time | ~20 s (composer) | ~25 s | ~60 s (xcaddy) |

### 8) Limitlər

- Octane-də **global state qalır** — controller-də static field, singleton-lar növbəti sorğuya sızır
- Uzun-ömürlü worker yaddaş sızıntısı riski var — `max_requests` limiti qoyulmalıdır
- FrankenPHP embed rejimi hələ bəzi extension-larla işləmir (imagick, oci8)

---

## Əsas fərqlər

| Xüsusiyyət | Spring Boot Native | Laravel (Octane/FrankenPHP) |
|---|---|---|
| Kompilyasiya növü | AOT — bytecode → native ASM | Bytecode cache (OPcache) + warm runtime |
| Cold start | ~80 ms | ~40–200 ms (rejimə görə) |
| Warm request latency | ~15 ms | ~2–5 ms |
| Yaddaş istifadəsi | ~55 MB | ~80–120 MB |
| Binary həcmi | ~95 MB | ~85 MB (FrankenPHP embed) |
| Reflection dəstəyi | Hint tələb olunur | Tam — runtime dinamik |
| Dynamic class loading | Yoxdur (closed world) | Var |
| Build vaxtı | 3–8 dəqiqə | 30–60 saniyə |
| Peak throughput | JVM-dən bir qədər aşağı | Octane yüksək, PHP-FPM aşağı |
| CPU profile | Aşağı startup CPU | Startup CPU PHP parse-dır |
| Debugger | GDB, profiler məhdud | Xdebug, PHP-specific alətlər |
| Serverless uyğunluğu | Əla — cold start qısa | Orta — runtime layer lazımdır |

---

## Niyə belə fərqlər var?

**JVM-in iki üzü.** JVM warm-up mərhələsində yavaşdır amma sonra JIT sayəsində çox optimal native kod generate edir. Native image ilə startup-ı qurban verib sürət qazanmaq istəyirik. Bu trade-off bütün native image ekosistemini formalaşdırır: closed-world assumption, reflection hints, ağır build vaxtı.

**PHP-nin request-per-process modeli.** PHP-FPM hər sorğu üçün yeni worker götürür, tətbiqi yenidən boot edir. Buna görə "cold start" hər sorğuda baş verir — əlacı OPcache ilə bytecode-u cache etmək və Octane ilə tətbiqi yaddaşda saxlamaqdır. Tətbiqi tam native kompilyasiya etmək lazım deyil, çünki PHP özü interpret olunur.

**Closed-world vs open-world.** GraalVM native image build vaxtında bütün class-ları bilməlidir — hansı reflection çağırılacaq, hansı resurs yüklənəcək. PHP-də isə `include`, `eval`, class auto-loading tamamilə dinamikdir. Buna görə PHP-də "native image" konsepti mümkün deyil.

**FrankenPHP yanaşması.** FrankenPHP `--embed` static binary yaradır, amma içində hələ də PHP interpretatoru var — sadəcə PHP runtime + tətbiq + Caddy server tək file-dadır. Bu, GraalVM native image-dan fərqlidir (orada bytecode həqiqətən native machine code-a çevrilir). Nəticədə FrankenPHP binary PHP tətbiqinin dərhal başlamasına imkan verir, amma run vaxtı performansında dəyişiklik minimaldır.

**Docker image həcmi.** Spring native ~100 MB, Laravel Octane ~200 MB, FrankenPHP embed ~90 MB. Fərq əsasən JVM vs PHP runtime ölçüsündən gəlir.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- GraalVM Native Image ilə həqiqi AOT kompilyasiya (bytecode → machine code)
- `RuntimeHintsRegistrar` — reflection/resource hint API
- Closed-world tətbiqi — daha kiçik attack surface
- Native Tests — native binary-də test icrası
- `spring-boot-starter-parent` + `native` profile — tək command-la build
- `org.graalvm.buildtools.native` Maven/Gradle plugin
- `@RegisterReflectionForBinding` — sadə hint annotasiya
- Tək binary, JVM olmadan çalışma
- Sub-100ms cold start — Lambda üçün ideal

**Yalnız Laravel-də (birbaşa analog):**
- FrankenPHP `--embed` static binary (runtime daxil)
- Laravel Octane Swoole/RoadRunner ilə long-running worker
- `php artisan optimize` — config/route/view cache
- OPcache preload — vendor class-larını startup-da parse
- Octane table-lar — worker-lər arası shared memory
- `--max-requests` avtomatik worker restart (yaddaş sızıntısına qarşı)
- Swoole coroutine-ləri (native PHP fiber-dən əvvəl)

---

## Best Practices

1. **Constructor injection istifadə et** — Spring native field injection-u reflection ilə həll edir, amma constructor injection daha təmiz və sürətli olur.
2. **`application.yml`-də profile-lar** — native-specific konfiqurasiya `application-native.yml`-də saxlanmalıdır.
3. **Reflection hint-ləri yalnız lazımlı yerlərdə** — artıq hint native binary-ni şişirdir.
4. **CI-da iki workflow** — hər push-da JVM test, nightly-də native test.
5. **Native image build üçün ayrı Docker stage** — GraalVM 2 GB+ yer tutur.
6. **Laravel-də Octane production-da** — dev-də PHP-FPM rahatdır, prod-da Octane latency qazancı verir.
7. **FrankenPHP-yə kecid tədricən** — əvvəlcə Octane + Swoole, sonra FrankenPHP, axırda embed binary.
8. **Global state yoxla** — Laravel Octane-də static property-lər sorğular arası qalır, təmizlə.
9. **Startup metrikini izlə** — CloudWatch/Datadog-da `init duration` Lambda-da kritikdir.
10. **Binary size vs startup trade-off** — Spring native binary böyükdür, amma Lambda cold start qazancı dəyərli olur.

---

## Yekun

Spring Boot 3 + GraalVM Native Image həqiqi AOT kompilyasiya verir — sub-100ms startup, 55 MB yaddaş. Lakin bunun qiyməti var: uzun build, reflection hint-ləri, closed-world məhdudiyyət. Serverless, CLI və Kubernetes auto-scaling üçün idealdır.

Laravel-də "native" konsepti fərqlidir — PHP interpret olunur. `OPcache` + `php artisan optimize` + **Octane** ilə warm runtime qurulur, **FrankenPHP `--embed`** ilə static binary alınır. Octane sorğu latency-ni 1–3 ms-ə endirir, amma cold start hələ də interpret cost daşıyır.

Seçim qaydası: **serverless/Lambda-da** Spring Native qazanır. **Long-running container-də** hər ikisi rahat — Spring JVM mode throughput-da, Laravel Octane sadəlikdə. **Build time həssasdırsa**, Spring Native CI-da çətinlik yaradır, Laravel optimize saniyələrdə bitir.
