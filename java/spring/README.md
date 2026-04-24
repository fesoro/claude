# Spring Framework & Boot — 88 Mövzu (000-087)

Spring Boot, Core Container, MVC/REST, Data/JPA, Security, AOP, integration və Spring testing. Orta səviyyədən İrəli/Ekspert-ə qədər.

**Şərt:** [../core/](../core/) qovluğundakı Java fundamentals (OOP, Collections, Generics, Lambdas, Maven/Gradle, Exceptions) bilinməlidir.

**Öyrənmə yolu:** 000 → 087 sıra ilə. Hər fayl müstəqil də oxuna bilər.

---

## Fazalar Xülasəsi

| # | Faza | Aralıq | Səviyyə | Qısa Mövzu |
|---|------|--------|---------|------------|
| 1 | Boot Onboarding | 000-008 | Orta → İrəli | Initializr, starter, autoconfig, properties, logging, actuator |
| 2 | Core Container | 009-019 | Orta → İrəli | IoC, DI, beans, scopes, lifecycle, profiles, events |
| 3 | MVC/REST | 020-030 | Orta → İrəli | Controllers, request/response, exception handling, validation |
| 4 | Data & JPA | 031-046 | Orta → İrəli | JDBC, entity, repositories, JPQL, transactions, Flyway, HikariCP |
| 5 | AOP | 047-051 | İrəli → Ekspert | Pointcuts, advices, proxy types |
| 6 | Security | 052-062 | Orta → Ekspert | Authentication, JWT, OAuth2, CORS, session |
| 7 | Integration & Messaging | 063-083 | Orta → Ekspert | Cache, Redis, Mongo, Kafka, RabbitMQ, WebSocket, Batch, GraphQL, AI, WebFlux |
| 8 | Spring Testing | 084-087 | İrəli | @SpringBootTest, WebMvcTest, DataJpaTest, Testcontainers |

---

## Səviyyə Legendi

- **Başlanğıc** — Java ilk dəfə görür
- **Orta** — Əsas syntax biləni, istehsalata hazırdır
- **İrəli** — Mövzunu dərindən başa düşmək üçün
- **Ekspert** — Tuning, internals, performance optimization

---

## Phase 1: Spring Boot Onboarding (000-008)

| # | Mövzu | Səv. |
|---|-------|------|
| [000](000-boot-first-app-initializr.md) | Spring Initializr addım-addım, ilk `@RestController` | Orta |
| [001](001-boot-starter.md) | custom starter yaratma, autoconfigure module | Orta |
| [002](002-boot-autoconfiguration.md) | @EnableAutoConfiguration, spring.factories, META-INF/spring | İrəli |
| [003](003-boot-application-properties-yml.md) | Properties vs YAML, profiles, priority order, @ConfigurationProperties | Orta |
| [004](004-boot-logging-slf4j-logback.md) | SLF4J, Logback, log levels, MDC, structured JSON log | Orta |
| [005](005-boot-embedded-server.md) | Tomcat/Jetty/Undertow, server konfiqurasiya | Orta |
| [006](006-boot-devtools.md) | live reload, restart, remote debugging | Orta |
| [007](007-boot-actuator.md) | /health, /metrics, /env, custom endpoint | Orta |
| [008](008-boot-docker-compose.md) | Spring Boot 3 Docker Compose dəstəyi | Orta |

## Phase 2: Core Container (009-019)

| # | Mövzu | Səv. |
|---|-------|------|
| [009](009-ioc-container.md) | IoC nədir, BeanFactory vs ApplicationContext | Orta |
| [010](010-bean-definition.md) | @Component, @Service, @Repository, @Controller fərqi | Orta |
| [011](011-dependency-injection.md) | constructor, setter, field injection — fərqlər | Orta |
| [012](012-bean-scopes.md) | singleton, prototype, request, session, application | Orta |
| [013](013-bean-lifecycle.md) | instantiation → populate → init → use → destroy | İrəli |
| [014](014-configuration.md) | @Configuration, @Bean, @Import, lite vs full mode | Orta |
| [015](015-value-configprops.md) | @Value, @ConfigurationProperties, relaxed binding | Orta |
| [016](016-profiles.md) | @Profile, spring.profiles.active, multi-env config | Orta |
| [017](017-conditional.md) | @Conditional, @ConditionalOnClass/Bean/Property | İrəli |
| [018](018-beanpostprocessor.md) | BeanPostProcessor, BeanFactoryPostProcessor | İrəli |
| [019](019-events.md) | ApplicationEvent, @EventListener, @TransactionalEventListener | İrəli |

## Phase 3: MVC/REST (020-030)

| # | Mövzu | Səv. |
|---|-------|------|
| [020](020-boot-rest-api-basics.md) | REST prinsipləri, HTTP metodlar, status kodları, ProblemDetail | Orta |
| [021](021-mvc-dispatcherservlet.md) | request lifecycle, handler mapping, view resolution | İrəli |
| [022](022-mvc-controllers.md) | @RequestMapping, @GetMapping, @PathVariable, @RequestParam | Orta |
| [023](023-mvc-request-response.md) | @RequestBody, @ResponseBody, ResponseEntity, HttpEntity | Orta |
| [024](024-boot-jackson-json.md) | ObjectMapper, @Json* annotasiyaları, dates, polymorphic | Orta |
| [025](025-boot-dto-mapping.md) | DTO pattern, MapStruct, ModelMapper, records as DTO | Orta |
| [026](026-mvc-exception-handling.md) | @ExceptionHandler, @ControllerAdvice, ProblemDetail | İrəli |
| [027](027-validation.md) | @Valid, @Validated, @NotNull/@Size, BindingResult | Orta |
| [028](028-validation-custom.md) | custom @Constraint, ConstraintValidator, cross-field validation | İrəli |
| [029](029-mvc-filters-interceptors.md) | Filter vs HandlerInterceptor, fərqlər, istifadə halları | İrəli |
| [030](030-mvc-content-negotiation.md) | Accept header, MessageConverter, produces/consumes | İrəli |

## Phase 4: Data & JPA (031-046)

| # | Mövzu | Səv. |
|---|-------|------|
| [031](031-jdbc-basics.md) | Plain JDBC, PreparedStatement, JdbcTemplate, RowMapper | Orta |
| [032](032-data-entity.md) | @Entity, @Table, @Id, @GeneratedValue, @Column | Orta |
| [033](033-data-repositories.md) | CrudRepository, JpaRepository, PagingAndSortingRepository | Orta |
| [034](034-data-relationships.md) | @OneToOne, @OneToMany, @ManyToOne, @ManyToMany | İrəli |
| [035](035-data-fetch-strategies.md) | LAZY vs EAGER, FetchType, @EntityGraph | İrəli |
| [036](036-data-jpql.md) | @Query, JPQL sintaksisi, named queries | İrəli |
| [037](037-data-native-queries.md) | native SQL, ResultSet mapping | İrəli |
| [038](038-data-projections.md) | interface projection, class projection, dynamic projection | İrəli |
| [039](039-data-specifications.md) | Specification API, JpaSpecificationExecutor, dynamic filter | İrəli |
| [040](040-data-pagination.md) | Pageable, Page, Slice, Sort | Orta |
| [041](041-data-auditing.md) | @CreatedDate, @LastModifiedBy, @EnableJpaAuditing | Orta |
| [042](042-transactions.md) | @Transactional, propagation növləri | İrəli |
| [043](043-transactions-isolation.md) | isolation levels, dirty/phantom read, lost update | İrəli |
| [044](044-hibernate-session-cache.md) | Session, EntityManager, 1st/2nd level cache, evict | İrəli |
| [045](045-flyway-liquibase.md) | migration versioning, rollback, baseline | Orta |
| [046](046-hikaricp-connection-pool.md) | HikariCP tuning, pool size, timeout, leak detection | İrəli |

## Phase 5: AOP (047-051)

| # | Mövzu | Səv. |
|---|-------|------|
| [047](047-aop-concepts.md) | Aspect, Advice, Joinpoint, Pointcut, Weaving | İrəli |
| [048](048-aop-pointcuts.md) | execution, within, @annotation, args expressions | İrəli |
| [049](049-aop-advices.md) | @Before, @After, @AfterReturning, @AfterThrowing | İrəli |
| [050](050-aop-around.md) | @Around, ProceedingJoinPoint, return dəyəri manipulyasiya | İrəli |
| [051](051-aop-proxy.md) | JDK dynamic proxy vs CGLIB, self-invocation problemi | Ekspert |

## Phase 6: Security (052-062)

| # | Mövzu | Səv. |
|---|-------|------|
| [052](052-security-architecture.md) | SecurityFilterChain, AuthenticationManager, SecurityContext | İrəli |
| [053](053-security-authentication.md) | UserDetailsService, AuthenticationProvider, custom auth | İrəli |
| [054](054-security-authorization.md) | @PreAuthorize, @PostAuthorize, @Secured, roles vs authorities | İrəli |
| [055](055-security-method-security.md) | method-level security, @EnableMethodSecurity | İrəli |
| [056](056-password-hashing-bcrypt.md) | BCrypt, Argon2, salt, work factor | Orta |
| [057](057-security-cors.md) | CORS konfiqurasiyası, preflight request | Orta |
| [058](058-security-csrf.md) | CSRF nədir, token mexanizmi, disable nə zaman | Orta |
| [059](059-security-jwt.md) | JWT strukturu, JwtFilter, token validation | İrəli |
| [060](060-security-oauth2.md) | OAuth2 Resource Server, Authorization Server, scopes | Ekspert |
| [061](061-authorization-server.md) | Spring Authorization Server 1.x | Ekspert |
| [062](062-session.md) | Spring Session, Redis-backed, distributed session | İrəli |

## Phase 7: Integration, Caching, Messaging (063-083)

| # | Mövzu | Səv. |
|---|-------|------|
| [063](063-cache.md) | @Cacheable, @CacheEvict, @CachePut, CacheManager | Orta |
| [064](064-data-redis.md) | RedisTemplate, Lettuce, pub/sub, sorted sets | İrəli |
| [065](065-data-mongodb.md) | MongoTemplate, @Document, criteria, aggregation | İrəli |
| [066](066-data-elasticsearch.md) | Elasticsearch client, full-text search, indexing | İrəli |
| [067](067-mail.md) | JavaMailSender, MimeMessage, HTML email, attachment | Orta |
| [068](068-scheduling.md) | @Scheduled, fixedRate/fixedDelay/cron, ThreadPoolTaskScheduler | Orta |
| [069](069-async.md) | @Async, @EnableAsync, ThreadPoolTaskExecutor | Orta |
| [070](070-file-upload.md) | MultipartFile, file storage, size limit konfiqurasiya | Orta |
| [071](071-retry.md) | Spring Retry, @Retryable, RecoveryCallback | Orta |
| [072](072-rate-limiting-bucket4j.md) | Bucket4j, token bucket, Redis-backed rate limit | İrəli |
| [073](073-openapi-swagger.md) | springdoc-openapi, OpenAPI 3, Swagger UI | Orta |
| [074](074-api-versioning.md) | URL/header/content-type versioning strategies | İrəli |
| [075](075-idempotency-pattern.md) | Idempotency-Key header, replay prevention | İrəli |
| [076](076-kafka-producer.md) | KafkaTemplate, ProducerRecord, serialization, acks | İrəli |
| [077](077-kafka-consumer.md) | @KafkaListener, consumer group, offset, error handling | İrəli |
| [078](078-rabbitmq.md) | @RabbitListener, Exchange/Queue/Binding, DLQ | İrəli |
| [079](079-websocket.md) | STOMP, @MessageMapping, SockJS, topic/queue | İrəli |
| [080](080-batch.md) | Job/Step, ItemReader/Processor/Writer, chunk, skip/retry | İrəli |
| [081](081-graphql.md) | spring-graphql, schema-first, resolvers, DataLoader | İrəli |
| [082](082-ai.md) | Spring AI, ChatClient, embedding, RAG | İrəli |
| [083](083-webflux.md) | Mono/Flux, reactive endpoints, WebClient, backpressure | Ekspert |

## Phase 8: Spring Testing (084-087)

| # | Mövzu | Səv. |
|---|-------|------|
| [084](084-boot-test.md) | @SpringBootTest, context loading, test slices | İrəli |
| [085](085-webmvctest.md) | @WebMvcTest, MockMvc, controller layer test | İrəli |
| [086](086-datajpatest.md) | @DataJpaTest, embedded DB, TestEntityManager | İrəli |
| [087](087-testcontainers.md) | Testcontainers, Postgres/Kafka/Redis, @ServiceConnection | İrəli |

---

**← Əvvəlki:** [core/](../core/) — Java dili əsasları (95 mövzu)
**Sonrakı →** [advanced/](../advanced/) — Cloud, architecture, deployment (24 mövzu)

*88 fayl | Son yenilənmə: 2026-04-24*
