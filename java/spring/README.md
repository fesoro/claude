# Spring Framework & Boot — 89 Mövzu (01-89)

Spring Boot, Core Container, MVC/REST, Data/JPA, Security, AOP, integration və Spring testing. Intermediate ⭐⭐ səviyyədən Advanced ⭐⭐⭐ / Expert ⭐⭐⭐⭐-ə qədər.

**Şərt:** [../core/](../core/) qovluğundakı Java fundamentals (OOP, Collections, Generics, Lambdas, Maven/Gradle, Exceptions) bilinməlidir.

**Öyrənmə yolu:** 01 → 89 sıra ilə. Hər fayl müstəqil də oxuna bilər.

---

## Fazalar Xülasəsi

| # | Faza | Aralıq | Səviyyə | Qısa Mövzu |
|---|------|--------|---------|------------|
| 1 | Boot Onboarding | 01-09 | Intermediate ⭐⭐ → Advanced ⭐⭐⭐ | Initializr, starter, autoconfig, properties, logging, actuator |
| 2 | Core Container | 10-20 | Intermediate ⭐⭐ → Advanced ⭐⭐⭐ | IoC, DI, beans, scopes, lifecycle, profiles, events |
| 3 | MVC/REST | 21-31 | Intermediate ⭐⭐ → Advanced ⭐⭐⭐ | Controllers, request/response, exception handling, validation |
| 4 | Data & JPA | 32-47 | Intermediate ⭐⭐ → Advanced ⭐⭐⭐ | JDBC, entity, repositories, JPQL, transactions, Flyway, HikariCP |
| 5 | AOP | 48-52 | Advanced ⭐⭐⭐ → Expert ⭐⭐⭐⭐ | Pointcuts, advices, proxy types |
| 6 | Security | 53-63 | Intermediate ⭐⭐ → Expert ⭐⭐⭐⭐ | Authentication, JWT, OAuth2, CORS, session |
| 7 | Integration & Messaging | 64-84 | Intermediate ⭐⭐ → Expert ⭐⭐⭐⭐ | Cache, Redis, Mongo, Kafka, RabbitMQ, WebSocket, Batch, GraphQL, AI, WebFlux |
| 8 | Spring Testing | 85-89 | Advanced ⭐⭐⭐ | @SpringBootTest, WebMvcTest, DataJpaTest, Testcontainers, Security Testing |

---

## Səviyyə Legendi

- **Beginner ⭐** — Java ilk dəfə görür
- **Intermediate ⭐⭐** — Əsas syntax biləni, istehsalata hazırdır
- **Advanced ⭐⭐⭐** — Mövzunu dərindən başa düşmək üçün
- **Expert ⭐⭐⭐⭐** — Tuning, internals, performance optimization

---

## Phase 1: Spring Boot Onboarding (01-09)

| # | Mövzu | Səv. |
|---|-------|------|
| [01](01-boot-first-app-initializr.md) | Spring Initializr addım-addım, ilk `@RestController` | Intermediate ⭐⭐ |
| [02](02-boot-starter.md) | custom starter yaratma, autoconfigure module | Intermediate ⭐⭐ |
| [03](03-boot-autoconfiguration.md) | @EnableAutoConfiguration, spring.factories, META-INF/spring | Advanced ⭐⭐⭐ |
| [04](04-boot-application-properties-yml.md) | Properties vs YAML, profiles, priority order, @ConfigurationProperties | Intermediate ⭐⭐ |
| [05](05-boot-logging-slf4j-logback.md) | SLF4J, Logback, log levels, MDC, structured JSON log | Intermediate ⭐⭐ |
| [06](06-boot-embedded-server.md) | Tomcat/Jetty/Undertow, server konfiqurasiya | Intermediate ⭐⭐ |
| [07](07-boot-devtools.md) | live reload, restart, remote debugging | Intermediate ⭐⭐ |
| [08](08-boot-actuator.md) | /health, /metrics, /env, custom endpoint | Intermediate ⭐⭐ |
| [09](09-boot-docker-compose.md) | Spring Boot 3 Docker Compose dəstəyi | Intermediate ⭐⭐ |

## Phase 2: Core Container (10-20)

| # | Mövzu | Səv. |
|---|-------|------|
| [10](10-ioc-container.md) | IoC nədir, BeanFactory vs ApplicationContext | Intermediate ⭐⭐ |
| [11](11-bean-definition.md) | @Component, @Service, @Repository, @Controller fərqi | Intermediate ⭐⭐ |
| [12](12-dependency-injection.md) | constructor, setter, field injection — fərqlər | Intermediate ⭐⭐ |
| [13](13-bean-scopes.md) | singleton, prototype, request, session, application | Intermediate ⭐⭐ |
| [14](14-bean-lifecycle.md) | instantiation → populate → init → use → destroy | Advanced ⭐⭐⭐ |
| [15](15-configuration.md) | @Configuration, @Bean, @Import, lite vs full mode | Intermediate ⭐⭐ |
| [16](16-value-configprops.md) | @Value, @ConfigurationProperties, relaxed binding | Intermediate ⭐⭐ |
| [17](17-profiles.md) | @Profile, spring.profiles.active, multi-env config | Intermediate ⭐⭐ |
| [18](18-conditional.md) | @Conditional, @ConditionalOnClass/Bean/Property | Advanced ⭐⭐⭐ |
| [19](19-beanpostprocessor.md) | BeanPostProcessor, BeanFactoryPostProcessor | Advanced ⭐⭐⭐ |
| [20](20-events.md) | ApplicationEvent, @EventListener, @TransactionalEventListener | Advanced ⭐⭐⭐ |

## Phase 3: MVC/REST (21-31)

| # | Mövzu | Səv. |
|---|-------|------|
| [21](21-boot-rest-api-basics.md) | REST prinsipləri, HTTP metodlar, status kodları, ProblemDetail | Intermediate ⭐⭐ |
| [22](22-mvc-dispatcherservlet.md) | request lifecycle, handler mapping, view resolution | Advanced ⭐⭐⭐ |
| [23](23-mvc-controllers.md) | @RequestMapping, @GetMapping, @PathVariable, @RequestParam | Intermediate ⭐⭐ |
| [24](24-mvc-request-response.md) | @RequestBody, @ResponseBody, ResponseEntity, HttpEntity | Intermediate ⭐⭐ |
| [25](25-boot-jackson-json.md) | ObjectMapper, @Json* annotasiyaları, dates, polymorphic | Intermediate ⭐⭐ |
| [26](26-boot-dto-mapping.md) | DTO pattern, MapStruct, ModelMapper, records as DTO | Intermediate ⭐⭐ |
| [27](27-mvc-exception-handling.md) | @ExceptionHandler, @ControllerAdvice, ProblemDetail | Advanced ⭐⭐⭐ |
| [28](28-validation.md) | @Valid, @Validated, @NotNull/@Size, BindingResult | Intermediate ⭐⭐ |
| [29](29-validation-custom.md) | custom @Constraint, ConstraintValidator, cross-field validation | Advanced ⭐⭐⭐ |
| [30](30-mvc-filters-interceptors.md) | Filter vs HandlerInterceptor, fərqlər, istifadə halları | Advanced ⭐⭐⭐ |
| [31](31-mvc-content-negotiation.md) | Accept header, MessageConverter, produces/consumes | Advanced ⭐⭐⭐ |

## Phase 4: Data & JPA (32-47)

| # | Mövzu | Səv. |
|---|-------|------|
| [32](32-jdbc-basics.md) | Plain JDBC, PreparedStatement, JdbcTemplate, RowMapper | Intermediate ⭐⭐ |
| [33](33-data-entity.md) | @Entity, @Table, @Id, @GeneratedValue, @Column | Intermediate ⭐⭐ |
| [34](34-data-repositories.md) | CrudRepository, JpaRepository, PagingAndSortingRepository | Intermediate ⭐⭐ |
| [35](35-data-relationships.md) | @OneToOne, @OneToMany, @ManyToOne, @ManyToMany | Advanced ⭐⭐⭐ |
| [36](36-data-fetch-strategies.md) | LAZY vs EAGER, FetchType, @EntityGraph | Advanced ⭐⭐⭐ |
| [37](37-data-jpql.md) | @Query, JPQL sintaksisi, named queries | Advanced ⭐⭐⭐ |
| [38](38-data-native-queries.md) | native SQL, ResultSet mapping | Advanced ⭐⭐⭐ |
| [39](39-data-projections.md) | interface projection, class projection, dynamic projection | Advanced ⭐⭐⭐ |
| [40](40-data-specifications.md) | Specification API, JpaSpecificationExecutor, dynamic filter | Advanced ⭐⭐⭐ |
| [41](41-data-pagination.md) | Pageable, Page, Slice, Sort | Intermediate ⭐⭐ |
| [42](42-data-auditing.md) | @CreatedDate, @LastModifiedBy, @EnableJpaAuditing | Intermediate ⭐⭐ |
| [43](43-transactions.md) | @Transactional, propagation növləri | Advanced ⭐⭐⭐ |
| [44](44-transactions-isolation.md) | isolation levels, dirty/phantom read, lost update | Advanced ⭐⭐⭐ |
| [45](45-hibernate-session-cache.md) | Session, EntityManager, 1st/2nd level cache, evict | Advanced ⭐⭐⭐ |
| [46](46-flyway-liquibase.md) | migration versioning, rollback, baseline | Intermediate ⭐⭐ |
| [47](47-hikaricp-connection-pool.md) | HikariCP tuning, pool size, timeout, leak detection | Advanced ⭐⭐⭐ |

## Phase 5: AOP (48-52)

| # | Mövzu | Səv. |
|---|-------|------|
| [48](48-aop-concepts.md) | Aspect, Advice, Joinpoint, Pointcut, Weaving | Advanced ⭐⭐⭐ |
| [49](49-aop-pointcuts.md) | execution, within, @annotation, args expressions | Advanced ⭐⭐⭐ |
| [50](50-aop-advices.md) | @Before, @After, @AfterReturning, @AfterThrowing | Advanced ⭐⭐⭐ |
| [51](51-aop-around.md) | @Around, ProceedingJoinPoint, return dəyəri manipulyasiya | Advanced ⭐⭐⭐ |
| [52](52-aop-proxy.md) | JDK dynamic proxy vs CGLIB, self-invocation problemi | Expert ⭐⭐⭐⭐ |

## Phase 6: Security (53-63)

| # | Mövzu | Səv. |
|---|-------|------|
| [53](53-security-architecture.md) | SecurityFilterChain, AuthenticationManager, SecurityContext | Advanced ⭐⭐⭐ |
| [54](54-security-authentication.md) | UserDetailsService, AuthenticationProvider, custom auth | Advanced ⭐⭐⭐ |
| [55](55-security-authorization.md) | @PreAuthorize, @PostAuthorize, @Secured, roles vs authorities | Advanced ⭐⭐⭐ |
| [56](56-security-method-security.md) | method-level security, @EnableMethodSecurity | Advanced ⭐⭐⭐ |
| [57](57-password-hashing-bcrypt.md) | BCrypt, Argon2, salt, work factor | Intermediate ⭐⭐ |
| [58](58-security-cors.md) | CORS konfiqurasiyası, preflight request | Intermediate ⭐⭐ |
| [59](59-security-csrf.md) | CSRF nədir, token mexanizmi, disable nə zaman | Intermediate ⭐⭐ |
| [60](60-security-jwt.md) | JWT strukturu, JwtFilter, token validation | Advanced ⭐⭐⭐ |
| [61](61-security-oauth2.md) | OAuth2 Resource Server, Authorization Server, scopes | Expert ⭐⭐⭐⭐ |
| [62](62-authorization-server.md) | Spring Authorization Server 1.x | Expert ⭐⭐⭐⭐ |
| [63](63-session.md) | Spring Session, Redis-backed, distributed session | Advanced ⭐⭐⭐ |

## Phase 7: Integration, Caching, Messaging (64-84)

| # | Mövzu | Səv. |
|---|-------|------|
| [64](64-cache.md) | @Cacheable, @CacheEvict, @CachePut, CacheManager | Intermediate ⭐⭐ |
| [65](65-data-redis.md) | RedisTemplate, Lettuce, pub/sub, sorted sets | Advanced ⭐⭐⭐ |
| [66](66-data-mongodb.md) | MongoTemplate, @Document, criteria, aggregation | Advanced ⭐⭐⭐ |
| [67](67-data-elasticsearch.md) | Elasticsearch client, full-text search, indexing | Advanced ⭐⭐⭐ |
| [68](68-mail.md) | JavaMailSender, MimeMessage, HTML email, attachment | Intermediate ⭐⭐ |
| [69](69-scheduling.md) | @Scheduled, fixedRate/fixedDelay/cron, ThreadPoolTaskScheduler | Intermediate ⭐⭐ |
| [70](70-async.md) | @Async, @EnableAsync, ThreadPoolTaskExecutor | Intermediate ⭐⭐ |
| [71](71-file-upload.md) | MultipartFile, file storage, size limit konfiqurasiya | Intermediate ⭐⭐ |
| [72](72-retry.md) | Spring Retry, @Retryable, RecoveryCallback | Intermediate ⭐⭐ |
| [73](73-rate-limiting-bucket4j.md) | Bucket4j, token bucket, Redis-backed rate limit | Advanced ⭐⭐⭐ |
| [74](74-openapi-swagger.md) | springdoc-openapi, OpenAPI 3, Swagger UI | Intermediate ⭐⭐ |
| [75](75-api-versioning.md) | URL/header/content-type versioning strategies | Advanced ⭐⭐⭐ |
| [76](76-idempotency-pattern.md) | Idempotency-Key header, replay prevention | Advanced ⭐⭐⭐ |
| [77](77-kafka-producer.md) | KafkaTemplate, ProducerRecord, serialization, acks | Advanced ⭐⭐⭐ |
| [78](78-kafka-consumer.md) | @KafkaListener, consumer group, offset, error handling | Advanced ⭐⭐⭐ |
| [79](79-rabbitmq.md) | @RabbitListener, Exchange/Queue/Binding, DLQ | Advanced ⭐⭐⭐ |
| [80](80-websocket.md) | STOMP, @MessageMapping, SockJS, topic/queue | Advanced ⭐⭐⭐ |
| [81](81-batch.md) | Job/Step, ItemReader/Processor/Writer, chunk, skip/retry | Advanced ⭐⭐⭐ |
| [82](82-graphql.md) | spring-graphql, schema-first, resolvers, DataLoader | Advanced ⭐⭐⭐ |
| [83](83-ai.md) | Spring AI, ChatClient, embedding, RAG | Advanced ⭐⭐⭐ |
| [84](84-webflux.md) | Mono/Flux, reactive endpoints, WebClient, backpressure | Expert ⭐⭐⭐⭐ |

## Phase 8: Spring Testing (85-89)

| # | Mövzu | Səv. |
|---|-------|------|
| [85](85-boot-test.md) | @SpringBootTest, context loading, test slices | Advanced ⭐⭐⭐ |
| [86](86-webmvctest.md) | @WebMvcTest, MockMvc, controller layer test | Advanced ⭐⭐⭐ |
| [87](87-datajpatest.md) | @DataJpaTest, embedded DB, TestEntityManager | Advanced ⭐⭐⭐ |
| [88](88-testcontainers.md) | Testcontainers, Postgres/Kafka/Redis, @ServiceConnection | Advanced ⭐⭐⭐ |
| [89](89-security-testing.md) | @WithMockUser, @WithUserDetails, JWT/CSRF testing, custom SecurityContext | Advanced ⭐⭐⭐ |

---

**← Əvvəlki:** [core/](../core/) — Java dili əsasları (95 mövzu)
**Sonrakı →** [advanced/](../advanced/) — Cloud, architecture, deployment (24 mövzu)

*89 fayl | Son yenilənmə: 2026-04-25*
