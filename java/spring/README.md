# Spring Framework & Boot — 108 Mövzu (01-108)

Spring Boot, Core Container, MVC/REST, Data/JPA, Security, AOP, integration, Spring testing, Modern Java və HTTP Clients. Middle ⭐⭐ səviyyədən Lead ⭐⭐⭐⭐-ə qədər.

**Şərt:** [../core/](../core/) qovluğundakı Java fundamentals (OOP, Collections, Generics, Lambdas, Maven/Gradle, Exceptions) bilinməlidir.

**Öyrənmə yolu:** 01 → 99 sıra ilə. Hər fayl müstəqil də oxuna bilər.

---

## Fazalar Xülasəsi

| # | Faza | Aralıq | Səviyyə | Qısa Mövzu |
|---|------|--------|---------|------------|
| 1 | Boot Onboarding | 01-09 | Middle ⭐⭐ → Senior ⭐⭐⭐ | Initializr, starter, autoconfig, properties, logging, actuator |
| 2 | Core Container | 10-20 | Middle ⭐⭐ → Senior ⭐⭐⭐ | IoC, DI, beans, scopes, lifecycle, profiles, events |
| 3 | MVC/REST | 21-31 | Middle ⭐⭐ → Senior ⭐⭐⭐ | Controllers, request/response, exception handling, validation |
| 4 | Data & JPA | 32-47 | Middle ⭐⭐ → Senior ⭐⭐⭐ | JDBC, entity, repositories, JPQL, transactions, Flyway, HikariCP |
| 5 | AOP | 48-52 | Senior ⭐⭐⭐ → Lead ⭐⭐⭐⭐ | Pointcuts, advices, proxy types |
| 6 | Security | 53-63 | Middle ⭐⭐ → Lead ⭐⭐⭐⭐ | Authentication, JWT, OAuth2, CORS, session |
| 7 | Integration & Messaging | 64-84 | Middle ⭐⭐ → Lead ⭐⭐⭐⭐ | Cache, Redis, Mongo, Kafka, RabbitMQ, WebSocket, Batch, GraphQL, AI, WebFlux |
| 8 | Spring Testing | 85-89 | Senior ⭐⭐⭐ | @SpringBootTest, WebMvcTest, DataJpaTest, Testcontainers, Security Testing |
| 9 | Modern Spring & Java | 90-100 | Middle ⭐⭐ → Senior ⭐⭐⭐ | Servlet, Transaction propagation, Optional, Lombok, ProblemDetail, Circular dep, Virtual threads, Records, Sealed classes, Observability, Graceful Shutdown |
| 10 | HTTP Clients | 101-103 | Middle ⭐⭐ → Senior ⭐⭐⭐ | RestClient, @HttpExchange deklarativ client, @RestClientTest |
| 11 | Production Patterns | 104-108 | Senior ⭐⭐⭐ → Lead ⭐⭐⭐⭐ | SSE, Webhook delivery, Background jobs, Fuzz testing, Singleflight |

---

## Səviyyə Legendi

- **Junior ⭐** — Java ilk dəfə görür
- **Middle ⭐⭐** — Əsas syntax biləni, istehsalata hazırdır
- **Senior ⭐⭐⭐** — Mövzunu dərindən başa düşmək üçün
- **Lead ⭐⭐⭐⭐** — Tuning, internals, performance optimization

---

## Phase 1: Spring Boot Onboarding (01-09)

| # | Mövzu | Səv. |
|---|-------|------|
| [01](01-boot-first-app-initializr.md) | Spring Initializr addım-addım, ilk `@RestController` | Middle ⭐⭐ |
| [02](02-boot-starter.md) | custom starter yaratma, autoconfigure module | Middle ⭐⭐ |
| [03](03-boot-autoconfiguration.md) | @EnableAutoConfiguration, spring.factories, META-INF/spring | Senior ⭐⭐⭐ |
| [04](04-boot-application-properties-yml.md) | Properties vs YAML, profiles, priority order, @ConfigurationProperties | Middle ⭐⭐ |
| [05](05-boot-logging-slf4j-logback.md) | SLF4J, Logback, log levels, MDC, structured JSON log | Middle ⭐⭐ |
| [06](06-boot-embedded-server.md) | Tomcat/Jetty/Undertow, server konfiqurasiya | Middle ⭐⭐ |
| [07](07-boot-devtools.md) | live reload, restart, remote debugging | Middle ⭐⭐ |
| [08](08-boot-actuator.md) | /health, /metrics, /env, custom endpoint | Middle ⭐⭐ |
| [09](09-boot-docker-compose.md) | Spring Boot 3 Docker Compose dəstəyi | Middle ⭐⭐ |

## Phase 2: Core Container (10-20)

| # | Mövzu | Səv. |
|---|-------|------|
| [10](10-ioc-container.md) | IoC nədir, BeanFactory vs ApplicationContext | Middle ⭐⭐ |
| [11](11-bean-definition.md) | @Component, @Service, @Repository, @Controller fərqi | Middle ⭐⭐ |
| [12](12-dependency-injection.md) | constructor, setter, field injection — fərqlər | Middle ⭐⭐ |
| [13](13-bean-scopes.md) | singleton, prototype, request, session, application | Middle ⭐⭐ |
| [14](14-bean-lifecycle.md) | instantiation → populate → init → use → destroy | Senior ⭐⭐⭐ |
| [15](15-configuration.md) | @Configuration, @Bean, @Import, lite vs full mode | Middle ⭐⭐ |
| [16](16-value-configprops.md) | @Value, @ConfigurationProperties, relaxed binding | Middle ⭐⭐ |
| [17](17-profiles.md) | @Profile, spring.profiles.active, multi-env config | Middle ⭐⭐ |
| [18](18-conditional.md) | @Conditional, @ConditionalOnClass/Bean/Property | Senior ⭐⭐⭐ |
| [19](19-beanpostprocessor.md) | BeanPostProcessor, BeanFactoryPostProcessor | Senior ⭐⭐⭐ |
| [20](20-events.md) | ApplicationEvent, @EventListener, @TransactionalEventListener | Senior ⭐⭐⭐ |

## Phase 3: MVC/REST (21-31)

| # | Mövzu | Səv. |
|---|-------|------|
| [21](21-boot-rest-api-basics.md) | REST prinsipləri, HTTP metodlar, status kodları, ProblemDetail | Middle ⭐⭐ |
| [22](22-mvc-dispatcherservlet.md) | request lifecycle, handler mapping, view resolution | Senior ⭐⭐⭐ |
| [23](23-mvc-controllers.md) | @RequestMapping, @GetMapping, @PathVariable, @RequestParam | Middle ⭐⭐ |
| [24](24-mvc-request-response.md) | @RequestBody, @ResponseBody, ResponseEntity, HttpEntity | Middle ⭐⭐ |
| [25](25-boot-jackson-json.md) | ObjectMapper, @Json* annotasiyaları, dates, polymorphic | Middle ⭐⭐ |
| [26](26-boot-dto-mapping.md) | DTO pattern, MapStruct, ModelMapper, records as DTO | Middle ⭐⭐ |
| [27](27-mvc-exception-handling.md) | @ExceptionHandler, @ControllerAdvice, ProblemDetail | Senior ⭐⭐⭐ |
| [28](28-validation.md) | @Valid, @Validated, @NotNull/@Size, BindingResult | Middle ⭐⭐ |
| [29](29-validation-custom.md) | custom @Constraint, ConstraintValidator, cross-field validation | Senior ⭐⭐⭐ |
| [30](30-mvc-filters-interceptors.md) | Filter vs HandlerInterceptor, fərqlər, istifadə halları | Senior ⭐⭐⭐ |
| [31](31-mvc-content-negotiation.md) | Accept header, MessageConverter, produces/consumes | Senior ⭐⭐⭐ |

## Phase 4: Data & JPA (32-47)

| # | Mövzu | Səv. |
|---|-------|------|
| [32](32-jdbc-basics.md) | Plain JDBC, PreparedStatement, JdbcTemplate, RowMapper | Middle ⭐⭐ |
| [33](33-data-entity.md) | @Entity, @Table, @Id, @GeneratedValue, @Column | Middle ⭐⭐ |
| [34](34-data-repositories.md) | CrudRepository, JpaRepository, PagingAndSortingRepository | Middle ⭐⭐ |
| [35](35-data-relationships.md) | @OneToOne, @OneToMany, @ManyToOne, @ManyToMany | Senior ⭐⭐⭐ |
| [36](36-data-fetch-strategies.md) | LAZY vs EAGER, FetchType, @EntityGraph | Senior ⭐⭐⭐ |
| [37](37-data-jpql.md) | @Query, JPQL sintaksisi, named queries | Senior ⭐⭐⭐ |
| [38](38-data-native-queries.md) | native SQL, ResultSet mapping | Senior ⭐⭐⭐ |
| [39](39-data-projections.md) | interface projection, class projection, dynamic projection | Senior ⭐⭐⭐ |
| [40](40-data-specifications.md) | Specification API, JpaSpecificationExecutor, dynamic filter | Senior ⭐⭐⭐ |
| [41](41-data-pagination.md) | Pageable, Page, Slice, Sort | Middle ⭐⭐ |
| [42](42-data-auditing.md) | @CreatedDate, @LastModifiedBy, @EnableJpaAuditing | Middle ⭐⭐ |
| [43](43-transactions.md) | @Transactional, propagation növləri | Senior ⭐⭐⭐ |
| [44](44-transactions-isolation.md) | isolation levels, dirty/phantom read, lost update | Senior ⭐⭐⭐ |
| [45](45-hibernate-session-cache.md) | Session, EntityManager, 1st/2nd level cache, evict | Senior ⭐⭐⭐ |
| [46](46-flyway-liquibase.md) | migration versioning, rollback, baseline | Middle ⭐⭐ |
| [47](47-hikaricp-connection-pool.md) | HikariCP tuning, pool size, timeout, leak detection | Senior ⭐⭐⭐ |

## Phase 5: AOP (48-52)

| # | Mövzu | Səv. |
|---|-------|------|
| [48](48-aop-concepts.md) | Aspect, Advice, Joinpoint, Pointcut, Weaving | Senior ⭐⭐⭐ |
| [49](49-aop-pointcuts.md) | execution, within, @annotation, args expressions | Senior ⭐⭐⭐ |
| [50](50-aop-advices.md) | @Before, @After, @AfterReturning, @AfterThrowing | Senior ⭐⭐⭐ |
| [51](51-aop-around.md) | @Around, ProceedingJoinPoint, return dəyəri manipulyasiya | Senior ⭐⭐⭐ |
| [52](52-aop-proxy.md) | JDK dynamic proxy vs CGLIB, self-invocation problemi | Lead ⭐⭐⭐⭐ |

## Phase 6: Security (53-63)

| # | Mövzu | Səv. |
|---|-------|------|
| [53](53-security-architecture.md) | SecurityFilterChain, AuthenticationManager, SecurityContext | Senior ⭐⭐⭐ |
| [54](54-security-authentication.md) | UserDetailsService, AuthenticationProvider, custom auth | Senior ⭐⭐⭐ |
| [55](55-security-authorization.md) | @PreAuthorize, @PostAuthorize, @Secured, roles vs authorities | Senior ⭐⭐⭐ |
| [56](56-security-method-security.md) | method-level security, @EnableMethodSecurity | Senior ⭐⭐⭐ |
| [57](57-password-hashing-bcrypt.md) | BCrypt, Argon2, salt, work factor | Middle ⭐⭐ |
| [58](58-security-cors.md) | CORS konfiqurasiyası, preflight request | Middle ⭐⭐ |
| [59](59-security-csrf.md) | CSRF nədir, token mexanizmi, disable nə zaman | Middle ⭐⭐ |
| [60](60-security-jwt.md) | JWT strukturu, JwtFilter, token validation | Senior ⭐⭐⭐ |
| [61](61-security-oauth2.md) | OAuth2 Resource Server, Authorization Server, scopes | Lead ⭐⭐⭐⭐ |
| [62](62-authorization-server.md) | Spring Authorization Server 1.x | Lead ⭐⭐⭐⭐ |
| [63](63-session.md) | Spring Session, Redis-backed, distributed session | Senior ⭐⭐⭐ |

## Phase 7: Integration, Caching, Messaging (64-84)

| # | Mövzu | Səv. |
|---|-------|------|
| [64](64-cache.md) | @Cacheable, @CacheEvict, @CachePut, CacheManager | Middle ⭐⭐ |
| [65](65-data-redis.md) | RedisTemplate, Lettuce, pub/sub, sorted sets | Senior ⭐⭐⭐ |
| [66](66-data-mongodb.md) | MongoTemplate, @Document, criteria, aggregation | Senior ⭐⭐⭐ |
| [67](67-data-elasticsearch.md) | Elasticsearch client, full-text search, indexing | Senior ⭐⭐⭐ |
| [68](68-mail.md) | JavaMailSender, MimeMessage, HTML email, attachment | Middle ⭐⭐ |
| [69](69-scheduling.md) | @Scheduled, fixedRate/fixedDelay/cron, ThreadPoolTaskScheduler | Middle ⭐⭐ |
| [70](70-async.md) | @Async, @EnableAsync, ThreadPoolTaskExecutor | Middle ⭐⭐ |
| [71](71-file-upload.md) | MultipartFile, file storage, size limit konfiqurasiya | Middle ⭐⭐ |
| [72](72-retry.md) | Spring Retry, @Retryable, RecoveryCallback | Middle ⭐⭐ |
| [73](73-rate-limiting-bucket4j.md) | Bucket4j, token bucket, Redis-backed rate limit | Senior ⭐⭐⭐ |
| [74](74-openapi-swagger.md) | springdoc-openapi, OpenAPI 3, Swagger UI | Middle ⭐⭐ |
| [75](75-api-versioning.md) | URL/header/content-type versioning strategies | Senior ⭐⭐⭐ |
| [76](76-idempotency-pattern.md) | Idempotency-Key header, replay prevention | Senior ⭐⭐⭐ |
| [77](77-kafka-producer.md) | KafkaTemplate, ProducerRecord, serialization, acks | Senior ⭐⭐⭐ |
| [78](78-kafka-consumer.md) | @KafkaListener, consumer group, offset, error handling | Senior ⭐⭐⭐ |
| [79](79-rabbitmq.md) | @RabbitListener, Exchange/Queue/Binding, DLQ | Senior ⭐⭐⭐ |
| [80](80-websocket.md) | STOMP, @MessageMapping, SockJS, topic/queue | Senior ⭐⭐⭐ |
| [81](81-batch.md) | Job/Step, ItemReader/Processor/Writer, chunk, skip/retry | Senior ⭐⭐⭐ |
| [82](82-graphql.md) | spring-graphql, schema-first, resolvers, DataLoader | Senior ⭐⭐⭐ |
| [83](83-ai.md) | Spring AI, ChatClient, embedding, RAG | Senior ⭐⭐⭐ |
| [84](84-webflux.md) | Mono/Flux, reactive endpoints, WebClient, backpressure | Lead ⭐⭐⭐⭐ |

## Phase 8: Spring Testing (85-89)

| # | Mövzu | Səv. |
|---|-------|------|
| [85](85-boot-test.md) | @SpringBootTest, context loading, test slices | Senior ⭐⭐⭐ |
| [86](86-webmvctest.md) | @WebMvcTest, MockMvc, controller layer test | Senior ⭐⭐⭐ |
| [87](87-datajpatest.md) | @DataJpaTest, embedded DB, TestEntityManager | Senior ⭐⭐⭐ |
| [88](88-testcontainers.md) | Testcontainers, Postgres/Kafka/Redis, @ServiceConnection | Senior ⭐⭐⭐ |
| [89](89-security-testing.md) | @WithMockUser, @WithUserDetails, JWT/CSRF testing, custom SecurityContext | Senior ⭐⭐⭐ |

## Phase 9: Modern Spring & Java (90-99)

| # | Mövzu | Səv. |
|---|-------|------|
| [90](90-servlet-fundamentals.md) | Servlet API, DispatcherServlet, Filter vs HandlerInterceptor | Middle ⭐⭐ |
| [91](91-transaction-propagation.md) | Transaction propagation: REQUIRED, REQUIRES_NEW, NESTED, self-invocation | Middle ⭐⭐ |
| [92](92-optional-null-safety.md) | Optional<T> — map/flatMap/orElse, anti-pattern-lər, @NonNull | Middle ⭐⭐ |
| [93](93-lombok-best-practices.md) | @Data, @Builder, @RequiredArgsConstructor, JPA entity qaydaları | Middle ⭐⭐ |
| [94](94-problem-detail-rfc7807.md) | RFC 7807 ProblemDetail, ErrorResponse, Global ExceptionHandler | Middle ⭐⭐ |
| [95](95-circular-dependency.md) | Circular dependency: @Lazy, event-driven, dizayn həlləri | Middle ⭐⭐ |
| [96](96-virtual-threads-spring.md) | Virtual Threads, spring.threads.virtual.enabled, pinning, WebFlux müqayisəsi | Senior ⭐⭐⭐ |
| [97](97-records-as-dtos.md) | Records DTO kimi, Jackson, MapStruct, compact constructor, generic records | Senior ⭐⭐⭐ |
| [98](98-sealed-classes-domain.md) | Sealed classes, pattern matching switch, Result pattern, domain modeling | Senior ⭐⭐⭐ |
| [99](99-observability-architecture.md) | Structured logging, MDC, Micrometer metrics, OpenTelemetry, production stack | Lead ⭐⭐⭐⭐ |
| [100](100-graceful-shutdown.md) | server.shutdown=graceful, SmartLifecycle, Kubernetes preStop/readiness | Senior ⭐⭐⭐ |

## Phase 10: HTTP Clients (101-103)

| # | Mövzu | Səv. |
|---|-------|------|
| [101](101-restclient.md) | RestClient — Spring 6.1+ fluent sinxron HTTP client, RestTemplate-in yerini tutur | Middle ⭐⭐ |
| [102](102-httpexchange.md) | @HttpExchange — deklarativ HTTP client interface, Spring-native OpenFeign alternativi | Senior ⭐⭐⭐ |
| [103](103-rest-client-test.md) | @RestClientTest — HTTP client test slice, MockRestServiceServer | Senior ⭐⭐⭐ |

## Phase 11: Production Patterns (104-108)

| # | Mövzu | Səv. |
|---|-------|------|
| [104](104-sse-server-sent-events.md) | SSE — SseEmitter (MVC) + Flux\<ServerSentEvent\> (WebFlux), Redis Pub/Sub multi-instance | Senior ⭐⭐⭐ |
| [105](105-webhook-delivery.md) | Webhook Delivery — HMAC imzalama, exponential backoff retry, receiver verification | Senior ⭐⭐⭐ |
| [106](106-background-jobs-patterns.md) | Background Jobs — @Scheduled+ShedLock, @Async, Jobrunr, Kafka consumer patterns | Senior ⭐⭐⭐ |
| [107](107-fuzz-testing.md) | Fuzz Testing — jqwik property-based, jazzer coverage-guided, MockMvc bulk testing | Lead ⭐⭐⭐⭐ |
| [108](108-singleflight-request-coalescing.md) | Singleflight / Request Coalescing — ConcurrentHashMap+CF, Caffeine AsyncLoadingCache | Lead ⭐⭐⭐⭐ |

---

**← Əvvəlki:** [core/](../core/) — Java dili əsasları (99 mövzu)
**Sonrakı →** [advanced/](../advanced/) — Cloud, architecture, deployment (27 mövzu)

*108 fayl | Son yenilənmə: 2026-04-27*
