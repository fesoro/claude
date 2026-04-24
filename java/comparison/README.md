# Java/Spring vs PHP/Laravel — Müqayisəli Öyrənmə Bələdçisi

Bu folder Java və Spring Framework-ü **PHP/Laravel dünyası ilə müqayisə edərək** öyrənmək üçündür. Hər bir mövzu iki hissədən ibarətdir: Java/Spring tərəfi və onun PHP/Laravel ekvivalenti. Bu sənə artıq bildiyin Laravel bilikləri üzərindən Spring-ə keçid etməyə kömək edəcək.

**Cəmi: 133 fayl** (45 dil səviyyəsi + 88 framework səviyyəsi) — sadədən mürəkkəbə doğru sıralanıb. Fayl nömrələri level-ə görə artır.

## Seviyyə göstəriciləri

Hər fayl içində **`> **Seviyye:** X ⭐`** etiketi var.

| Göstərici | Kimə uyğundur |
|-----------|--------------|
| ⭐ **Beginner** | Java/Spring-ə yeni başlayanlar (əsas syntax, OOP, REST API) |
| ⭐⭐ **Intermediate** | Junior/Mid — gündəlik iş mövzuları (collections, JPA, security, testing) |
| ⭐⭐⭐ **Advanced** | Senior backend — framework internals, concurrency, observability |
| ⭐⭐⭐⭐ **Expert** | Staff/Principal — JVM internals, distributed systems, reactive, native |

---

## Hər faylda nə var?

- **Giriş** — mövzunun qısa izahı
- **Java/Spring-də istifadəsi** — real kod nümunələri ilə
- **PHP/Laravel-də istifadəsi** — real kod nümunələri ilə
- **Əsas fərqlər** — cədvəl və ya detallı müqayisə
- **Niyə belə fərqlər var?** — dizayn fəlsəfəsi izahı
- **Hansı framework-də var, hansında yoxdur?** — unikal xüsusiyyətlər

---

## Languages (Java vs PHP — Dil Səviyyəsi, 45 fayl)

### ⭐ Beginner (01–14) — Java syntax və OOP əsasları

Sıfırdan başlayanlar üçün dil fundamentalları.

| # | Mövzu | Fayl |
|---|-------|------|
| 1 | main metodu və proqramın icrası | [01-main-method-and-program-execution.md](languages/01-main-method-and-program-execution.md) |
| 2 | Data tipləri və dəyişənlər | [02-data-types-and-variables.md](languages/02-data-types-and-variables.md) |
| 3 | Control flow və operatorlar | [03-control-flow-and-operators.md](languages/03-control-flow-and-operators.md) |
| 4 | Primitiv tiplər, wrapper və autoboxing | [04-primitives-wrappers-autoboxing-deep.md](languages/04-primitives-wrappers-autoboxing-deep.md) |
| 5 | String emalı | [05-string-handling.md](languages/05-string-handling.md) |
| 6 | Access modifier-lər (public/private/protected) | [06-access-modifiers-complete.md](languages/06-access-modifiers-complete.md) |
| 7 | Static vs instance üzvlər | [07-static-vs-instance-members.md](languages/07-static-vs-instance-members.md) |
| 8 | Paket və namespace | [08-packages-and-namespaces.md](languages/08-packages-and-namespaces.md) |
| 9 | `this` və `super` açar sözləri | [09-this-and-super-keywords.md](languages/09-this-and-super-keywords.md) |
| 10 | Konstruktorlar — dərin bələdçi | [10-constructors-deep.md](languages/10-constructors-deep.md) |
| 11 | OOP — siniflər və interfeyslar | [11-oop-classes-interfaces.md](languages/11-oop-classes-interfaces.md) |
| 12 | Varislik və polimorfizm | [12-inheritance-and-polymorphism.md](languages/12-inheritance-and-polymorphism.md) |
| 13 | `equals()`, `hashCode()` və bərabərlik | [13-equals-hashcode-and-equality.md](languages/13-equals-hashcode-and-equality.md) |
| 14 | Enum-lar | [14-enums.md](languages/14-enums.md) |

### ⭐⭐ Intermediate (15–30) — gündəlik iş mövzuları

Collection-lar, exception handling, streams, modern Java (records/sealed).

| # | Mövzu | Fayl |
|---|-------|------|
| 15 | Kolleksiyalar və massivlər | [15-collections-and-arrays.md](languages/15-collections-and-arrays.md) |
| 16 | Exception handling | [16-exception-handling.md](languages/16-exception-handling.md) |
| 17 | try-with-resources və AutoCloseable | [17-try-with-resources-and-autocloseable.md](languages/17-try-with-resources-and-autocloseable.md) |
| 18 | Null safety və Optional | [18-null-safety-and-optionals.md](languages/18-null-safety-and-optionals.md) |
| 19 | Tarix və vaxt (java.time) | [19-date-and-time.md](languages/19-date-and-time.md) |
| 20 | Fayl giriş/çıxış (I/O) | [20-file-io.md](languages/20-file-io.md) |
| 21 | Regulyar ifadələr | [21-regular-expressions.md](languages/21-regular-expressions.md) |
| 22 | Annotasiyalar vs PHP atributları | [22-annotations-vs-attributes.md](languages/22-annotations-vs-attributes.md) |
| 23 | Generics | [23-generics.md](languages/23-generics.md) |
| 24 | Inner, anonymous class və lambda girişi | [24-inner-anonymous-lambda-intro.md](languages/24-inner-anonymous-lambda-intro.md) |
| 25 | Functional interfaces və method reference | [25-functional-interfaces-method-references.md](languages/25-functional-interfaces-method-references.md) |
| 26 | Streams və lambda | [26-streams-and-lambda.md](languages/26-streams-and-lambda.md) |
| 27 | Records və data class-lar | [27-records-and-data-classes.md](languages/27-records-and-data-classes.md) |
| 28 | Package manager-lər (Maven/Gradle vs Composer) | [28-package-managers-build.md](languages/28-package-managers-build.md) |
| 29 | Testing idiomları (JUnit/Mockito vs PHPUnit/Pest) | [29-testing-idioms.md](languages/29-testing-idioms.md) |
| 30 | Sequenced collections və Stream Gatherers (Modern Java) | [30-sequenced-collections-stream-gatherers.md](languages/30-sequenced-collections-stream-gatherers.md) |

### ⭐⭐⭐ Advanced (31–40) — senior backend səviyyə

Sealed types, pattern matching, reflection, concurrency, async.

| # | Mövzu | Fayl |
|---|-------|------|
| 31 | Sealed classes və pattern matching | [31-sealed-classes-and-pattern-matching.md](languages/31-sealed-classes-and-pattern-matching.md) |
| 32 | Switch expressions və record patterns | [32-switch-expressions-and-record-patterns.md](languages/32-switch-expressions-and-record-patterns.md) |
| 33 | Design patterns | [33-design-patterns.md](languages/33-design-patterns.md) |
| 34 | Reflection API | [34-reflection-api.md](languages/34-reflection-api.md) |
| 35 | Serialization dərin (Jackson, Protobuf, Avro) | [35-serialization-deep.md](languages/35-serialization-deep.md) |
| 36 | Multithreading və concurrency | [36-multithreading-and-concurrency.md](languages/36-multithreading-and-concurrency.md) |
| 37 | CompletableFuture və parallelism | [37-completablefuture-and-parallelism.md](languages/37-completablefuture-and-parallelism.md) |
| 38 | Async və coroutines (Virtual Threads vs Fibers) | [38-async-and-coroutines.md](languages/38-async-and-coroutines.md) |
| 39 | Virtual threads dərindən (Loom) | [39-virtual-threads-deep.md](languages/39-virtual-threads-deep.md) |
| 40 | NIO.2 — channels və buffers | [40-nio2-channels-buffers.md](languages/40-nio2-channels-buffers.md) |

### ⭐⭐⭐⭐ Expert (41–45) — JVM internals, staff səviyyə

| # | Mövzu | Fayl |
|---|-------|------|
| 41 | Memory və runtime | [41-memory-and-runtime.md](languages/41-memory-and-runtime.md) |
| 42 | Modullar (JPMS / Project Jigsaw) | [42-modules-jpms.md](languages/42-modules-jpms.md) |
| 43 | JVM internals — JIT və GC | [43-jvm-internals-jit-gc.md](languages/43-jvm-internals-jit-gc.md) |
| 44 | Structured concurrency və scoped values | [44-structured-concurrency-and-scoped-values.md](languages/44-structured-concurrency-and-scoped-values.md) |
| 45 | Foreign Function & Memory API (Panama) | [45-foreign-function-memory-api.md](languages/45-foreign-function-memory-api.md) |

---

## Frameworks (Spring vs Laravel — Framework Səviyyəsi, 88 fayl)

### ⭐ Beginner (01–20) — Spring Boot-a giriş

Hello World → ilk controller → REST API → validation. Laravel-dən gələnlər üçün ən asan başlanğıc.

| # | Mövzu | Fayl |
|---|-------|------|
| 1 | Spring Boot Hello World addım-addım | [01-spring-boot-hello-world-walkthrough.md](frameworks/01-spring-boot-hello-world-walkthrough.md) |
| 2 | Layihə strukturu | [02-project-structure.md](frameworks/02-project-structure.md) |
| 3 | Spring Boot starter-ləri izahı | [03-spring-boot-starters-explained.md](frameworks/03-spring-boot-starters-explained.md) |
| 4 | `@SpringBootApplication` annotasiyasının sökülməsi | [04-spring-boot-application-annotation-breakdown.md](frameworks/04-spring-boot-application-annotation-breakdown.md) |
| 5 | Konfiqurasiya (application.yml vs .env) | [05-configuration.md](frameworks/05-configuration.md) |
| 6 | Stereotype annotation-lar (@Component/@Service/@Repository/@Controller) | [06-stereotype-annotations-component-service-repository-controller.md](frameworks/06-stereotype-annotations-component-service-repository-controller.md) |
| 7 | Dependency injection | [07-dependency-injection.md](frameworks/07-dependency-injection.md) |
| 8 | Constructor injection, @Autowired, @Value | [08-constructor-injection-autowired-value-for-beginners.md](frameworks/08-constructor-injection-autowired-value-for-beginners.md) |
| 9 | Controller-lər | [09-controllers.md](frameworks/09-controllers.md) |
| 10 | Routing | [10-routing.md](frameworks/10-routing.md) |
| 11 | Request və Response | [11-request-response.md](frameworks/11-request-response.md) |
| 12 | Request/Response annotasiyaları bələdçisi (@PathVariable/@RequestParam/@RequestBody) | [12-request-response-annotations-guide.md](frameworks/12-request-response-annotations-guide.md) |
| 13 | REST API | [13-rest-api.md](frameworks/13-rest-api.md) |
| 14 | Lombok annotasiyaları Spring-də | [14-lombok-annotations-in-spring.md](frameworks/14-lombok-annotations-in-spring.md) |
| 15 | DTO vs Entity ayrılması | [15-dto-vs-entity-separation.md](frameworks/15-dto-vs-entity-separation.md) |
| 16 | Jackson serialization əsasları | [16-jackson-serialization-basics.md](frameworks/16-jackson-serialization-basics.md) |
| 17 | Serialization (ümumi) | [17-serialization.md](frameworks/17-serialization.md) |
| 18 | ResponseEntity və HTTP status code-lar | [18-responseentity-and-http-status-codes.md](frameworks/18-responseentity-and-http-status-codes.md) |
| 19 | Validation | [19-validation.md](frameworks/19-validation.md) |
| 20 | Error handling | [20-error-handling.md](frameworks/20-error-handling.md) |

### ⭐⭐ Intermediate (21–48) — gündəlik iş mövzuları

Database, transactions, security, testing, queue, cache — production-da hər gün görüləcək mövzular.

| # | Mövzu | Fayl |
|---|-------|------|
| 21 | Middleware və filter-lər | [21-middleware-and-filters.md](frameworks/21-middleware-and-filters.md) |
| 22 | Logging | [22-logging.md](frameworks/22-logging.md) |
| 23 | ORM və database | [23-orm-and-database.md](frameworks/23-orm-and-database.md) |
| 24 | Migration-lar | [24-migrations.md](frameworks/24-migrations.md) |
| 25 | Service/Repository pattern | [25-service-repository-pattern.md](frameworks/25-service-repository-pattern.md) |
| 26 | Tranzaksiyalar | [26-transactions.md](frameworks/26-transactions.md) |
| 27 | Pagination | [27-pagination.md](frameworks/27-pagination.md) |
| 28 | API versioning | [28-api-versioning.md](frameworks/28-api-versioning.md) |
| 29 | API documentation (Swagger/OpenAPI) | [29-api-documentation.md](frameworks/29-api-documentation.md) |
| 30 | Testing | [30-testing.md](frameworks/30-testing.md) |
| 31 | Authentication və authorization | [31-authentication-ve-authorization.md](frameworks/31-authentication-ve-authorization.md) |
| 32 | Security | [32-security.md](frameworks/32-security.md) |
| 33 | Session management | [33-session-management.md](frameworks/33-session-management.md) |
| 34 | Caching | [34-caching.md](frameworks/34-caching.md) |
| 35 | File storage | [35-file-storage.md](frameworks/35-file-storage.md) |
| 36 | Mail | [36-mail.md](frameworks/36-mail.md) |
| 37 | Scheduling | [37-scheduling.md](frameworks/37-scheduling.md) |
| 38 | CLI commands (Artisan vs Spring Shell) | [38-cli-commands.md](frameworks/38-cli-commands.md) |
| 39 | Database seeding və factory-lər | [39-database-seeding-and-factories.md](frameworks/39-database-seeding-and-factories.md) |
| 40 | Bildiriş sistemi (notifications) | [40-notifications.md](frameworks/40-notifications.md) |
| 41 | Template engine-lər (Thymeleaf vs Blade) | [41-template-engines.md](frameworks/41-template-engines.md) |
| 42 | Localization (i18n) | [42-localization-i18n.md](frameworks/42-localization-i18n.md) |
| 43 | Rate limiting | [43-rate-limiting.md](frameworks/43-rate-limiting.md) |
| 44 | Event və listener-lər | [44-events-and-listeners.md](frameworks/44-events-and-listeners.md) |
| 45 | Queue və jobs | [45-queues-and-jobs.md](frameworks/45-queues-and-jobs.md) |
| 46 | WebSocket (giriş) | [46-websocket.md](frameworks/46-websocket.md) |
| 47 | Health checks və monitoring | [47-health-checks-and-monitoring.md](frameworks/47-health-checks-and-monitoring.md) |
| 48 | Deployment | [48-deployment.md](frameworks/48-deployment.md) |

### ⭐⭐⭐ Advanced (49–72) — senior backend səviyyə

AOP, bean lifecycle, transactions deep, resilience, observability, testcontainers.

| # | Mövzu | Fayl |
|---|-------|------|
| 49 | AOP (Aspect-Oriented Programming) | [49-aop-aspect-oriented.md](frameworks/49-aop-aspect-oriented.md) |
| 50 | Spring Bean Lifecycle dərin | [50-spring-bean-lifecycle-deep.md](frameworks/50-spring-bean-lifecycle-deep.md) |
| 51 | Spring Profiles və environment | [51-spring-profiles-environment.md](frameworks/51-spring-profiles-environment.md) |
| 52 | Spring Events dərin (transactional, async) | [52-spring-events-deep.md](frameworks/52-spring-events-deep.md) |
| 53 | Spring Data JPA dərin vs Eloquent | [53-spring-data-jpa-deep.md](frameworks/53-spring-data-jpa-deep.md) |
| 54 | Spring Transactions dərin (propagation, isolation, JTA) | [54-spring-transactions-deep.md](frameworks/54-spring-transactions-deep.md) |
| 55 | Spring Data Redis və MongoDB | [55-spring-data-redis-and-mongo.md](frameworks/55-spring-data-redis-and-mongo.md) |
| 56 | Caching strategies dərin (Caffeine, multi-level, stampede) | [56-caching-strategies-deep.md](frameworks/56-caching-strategies-deep.md) |
| 57 | Spring Retry və resilience | [57-spring-retry-resilience.md](frameworks/57-spring-retry-resilience.md) |
| 58 | Circuit Breaker və resilience pattern-ləri | [58-circuit-breaker-resilience.md](frameworks/58-circuit-breaker-resilience.md) |
| 59 | Background processing dərin | [59-background-processing-deep.md](frameworks/59-background-processing-deep.md) |
| 60 | Spring Boot Actuator dərin | [60-spring-boot-actuator-deep.md](frameworks/60-spring-boot-actuator-deep.md) |
| 61 | Observation API və Micrometer | [61-observation-api-micrometer.md](frameworks/61-observation-api-micrometer.md) |
| 62 | Observability və OpenTelemetry | [62-observability-opentelemetry.md](frameworks/62-observability-opentelemetry.md) |
| 63 | Problem Details (RFC 7807/9457) | [63-problem-details-rfc7807.md](frameworks/63-problem-details-rfc7807.md) |
| 64 | HTTP Interface (@HttpExchange) və RestClient | [64-http-interface-and-restclient.md](frameworks/64-http-interface-and-restclient.md) |
| 65 | Spring HATEOAS və Spring Data REST | [65-spring-hateoas-data-rest.md](frameworks/65-spring-hateoas-data-rest.md) |
| 66 | Spring Session və distributed sessions | [66-spring-session-and-distributed-sessions.md](frameworks/66-spring-session-and-distributed-sessions.md) |
| 67 | Spring Security OAuth2 Resource Server | [67-spring-security-oauth2-resource-server.md](frameworks/67-spring-security-oauth2-resource-server.md) |
| 68 | Method security və authorization | [68-method-security-and-authorization.md](frameworks/68-method-security-and-authorization.md) |
| 69 | Testcontainers və integration testing | [69-testcontainers-integration-testing.md](frameworks/69-testcontainers-integration-testing.md) |
| 70 | Feature flags | [70-feature-flags.md](frameworks/70-feature-flags.md) |
| 71 | Spring Shell və CLI framework-ləri | [71-spring-shell-and-cli-frameworks.md](frameworks/71-spring-shell-and-cli-frameworks.md) |
| 72 | Jakarta EE migration (javax → jakarta) | [72-jakarta-ee-migration.md](frameworks/72-jakarta-ee-migration.md) |

### ⭐⭐⭐⭐ Expert (73–88) — distributed, reactive, native

Microservices, Spring Cloud, reactive, Kafka, Batch, GraphQL, AI, Modulith, AOT.

| # | Mövzu | Fayl |
|---|-------|------|
| 73 | Spring WebFlux (reactive streams) | [73-spring-webflux-reactive.md](frameworks/73-spring-webflux-reactive.md) |
| 74 | Spring WebSocket/STOMP dərin | [74-spring-websocket-stomp-deep.md](frameworks/74-spring-websocket-stomp-deep.md) |
| 75 | Microservices | [75-microservices.md](frameworks/75-microservices.md) |
| 76 | Spring Cloud Config Server | [76-spring-cloud-config-server.md](frameworks/76-spring-cloud-config-server.md) |
| 77 | Spring Cloud Gateway | [77-spring-cloud-gateway.md](frameworks/77-spring-cloud-gateway.md) |
| 78 | Service Discovery (Eureka/Consul/K8s) | [78-service-discovery-eureka.md](frameworks/78-service-discovery-eureka.md) |
| 79 | OpenFeign və declarative HTTP clients | [79-openfeign-declarative-clients.md](frameworks/79-openfeign-declarative-clients.md) |
| 80 | Spring Cloud Stream (Kafka/RabbitMQ abstraction) | [80-spring-cloud-stream.md](frameworks/80-spring-cloud-stream.md) |
| 81 | Spring for Apache Kafka | [81-spring-kafka.md](frameworks/81-spring-kafka.md) |
| 82 | Spring Batch | [82-spring-batch.md](frameworks/82-spring-batch.md) |
| 83 | GraphQL support (ümumi müqayisə) | [83-graphql-support.md](frameworks/83-graphql-support.md) |
| 84 | Spring for GraphQL (dərin) | [84-spring-graphql.md](frameworks/84-spring-graphql.md) |
| 85 | Spring Authorization Server | [85-spring-authorization-server.md](frameworks/85-spring-authorization-server.md) |
| 86 | Spring AI (LLM və RAG) | [86-spring-ai.md](frameworks/86-spring-ai.md) |
| 87 | Spring Modulith | [87-spring-modulith.md](frameworks/87-spring-modulith.md) |
| 88 | Spring Boot AOT və GraalVM Native Image | [88-spring-boot-aot-native-image.md](frameworks/88-spring-boot-aot-native-image.md) |

---

## Tövsiyə olunan oxuma yolu

**Laravel developer-si Spring-ə keçir?** Bu yolu izlə:

1. **Languages ⭐ 01–14** (Java syntax, OOP əsasları) — 1–2 həftə
2. **Frameworks ⭐ 01–20** (Spring Boot ilk addımlar, REST API, validation) — 1–2 həftə
3. **Languages ⭐⭐ 15–30** (collections, streams, modern Java) — 2–3 həftə
4. **Frameworks ⭐⭐ 21–48** (JPA, transactions, security, testing, queue) — 3–4 həftə
5. **Advanced/Expert** mövzularını işin tələb etdiyi ardıcıllıqla oxu.

**Junior Java developer (Spring yenisi)?** → Frameworks 01–20 → 21–48 kifayətdir başlanğıc üçün.

**Senior interview-a hazırlaşır?** → Advanced və Expert bölmələri (həm Languages 31–45, həm Frameworks 49–88).
