## Core / IoC

IoC və Dependency Injection
Constructor injection (tövsiyə edilən) vs Setter vs Field injection
@Component, @Service, @Repository, @Controller, @RestController
@Bean, @Configuration, @ComponentScan
@Autowired, @Qualifier, @Primary, @Lazy
@Inject (JSR-330 alternative)
Bean Scopes (singleton, prototype, request, session, application, websocket)
Bean Lifecycle (InitializingBean, DisposableBean, @PostConstruct, @PreDestroy, init/destroy methods)
BeanPostProcessor, BeanFactoryPostProcessor
ApplicationContext, BeanFactory
Environment abstraction
PropertySource
ApplicationContextAware, EnvironmentAware
@Import, @ImportResource
Conditional beans (@Conditional, @ConditionalOnProperty, @ConditionalOnClass, @ConditionalOnMissingBean)
FactoryBean

## Spring Boot basics

@SpringBootApplication (= @Configuration + @EnableAutoConfiguration + @ComponentScan)
Auto-configuration (spring.factories → AutoConfiguration.imports)
application.properties / application.yml
Profiles (@Profile, spring.profiles.active)
@Value, @ConfigurationProperties
@ConfigurationPropertiesScan
spring-boot-starter-* dependencies
CommandLineRunner, ApplicationRunner
ExitCodeGenerator
Spring Boot DevTools (hot reload)
SpringApplication customization
banner.txt

## Spring MVC / REST

@RequestMapping (class + method level)
@GetMapping, @PostMapping, @PutMapping, @DeleteMapping, @PatchMapping
@PathVariable, @RequestParam, @RequestBody, @RequestHeader
@ResponseBody, @ResponseStatus
ResponseEntity<T>
@ControllerAdvice + @ExceptionHandler — global exception handling
@RestControllerAdvice
ResponseStatusException
HandlerInterceptor
Filter (jakarta.servlet.Filter)
@Valid + @NotNull, @Size, @NotBlank, @Email, @Pattern (Bean Validation)
@Validated (groups, class-level)
MessageConverter (Jackson, Gson)
ContentNegotiation
CORS (@CrossOrigin, CorsRegistry)
File upload (MultipartFile)
Swagger / OpenAPI (springdoc-openapi-ui)
HATEOAS (Spring HATEOAS)

## Spring Data JPA

@Entity, @Table, @Id, @GeneratedValue (IDENTITY, SEQUENCE, AUTO, TABLE)
@Column, @Transient, @Lob, @Version (optimistic locking)
@OneToOne, @OneToMany, @ManyToOne, @ManyToMany
@JoinColumn, @JoinTable
Cascade types (PERSIST, MERGE, REMOVE, ALL)
Fetch types (LAZY, EAGER); N+1 problem
@EntityGraph (fetch strategy overrides)
JpaRepository, CrudRepository, PagingAndSortingRepository
Derived query methods (findByNameAndStatus)
@Query (JPQL və Native SQL)
@Modifying for UPDATE/DELETE
@Param, named parameters
Projections (interface, DTO, dynamic)
Pageable, Page, Slice, Sort
Specification API (JpaSpecificationExecutor, Predicate)
Criteria API (CriteriaBuilder, CriteriaQuery)
QueryDSL (compile-time safe queries)
@Transactional (propagation: REQUIRED, REQUIRES_NEW, NESTED, ...; isolation: READ_COMMITTED, ...)
@Transactional(readOnly = true)
@Lock (PESSIMISTIC_READ/WRITE, OPTIMISTIC)
EntityManager, PersistenceContext
Second-level cache (Hibernate — Ehcache, Redis)
Auditing (@CreatedDate, @LastModifiedDate, @CreatedBy, @EnableJpaAuditing)
Soft delete (@SQLDelete, @Where)
Hibernate-specific: @NaturalId, @DynamicUpdate, @BatchSize

## Spring Security

SecurityFilterChain (Spring Security 6+)
WebSecurityConfigurerAdapter (legacy, deprecated)
HttpSecurity DSL (authorizeHttpRequests, formLogin, httpBasic, oauth2Login)
UserDetailsService, UserDetails
PasswordEncoder (BCrypt, Argon2, Pbkdf2)
AuthenticationProvider, AuthenticationManager
@PreAuthorize, @PostAuthorize, @Secured, @RolesAllowed
Method security (@EnableMethodSecurity)
SpEL in security
CSRF protection
Session management (stateless for API, SessionCreationPolicy.STATELESS)
Remember-me
JWT Authentication (Spring Security OAuth2 Resource Server — jwt())
JwtDecoder, JwtAuthenticationConverter
OAuth2 / OpenID Connect (Login, Client, Resource Server)
Authorization Server (Spring Authorization Server project)
CORS configuration
SecurityContextHolder / Authentication
AccessDecisionManager (legacy), AuthorizationManager (new)

## AOP

Spring AOP (proxy-based: JDK dynamic or CGLIB)
@Aspect, @EnableAspectJAutoProxy
Advice types: @Before, @After, @AfterReturning, @AfterThrowing, @Around
Pointcut expressions (execution, within, @annotation, args)
JoinPoint, ProceedingJoinPoint
AspectJ weaving (compile-time) for fuller AOP

## Caching

@EnableCaching
@Cacheable(value="...", key="#id", condition, unless, sync)
@CacheEvict(allEntries=true)
@CachePut
@Caching (combine)
CacheManager (Simple, Caffeine, Redis, Hazelcast, Ehcache)
Redis cache (RedisCacheManager, RedisTemplate)

## Scheduling / async

@EnableScheduling
@Scheduled(fixedRate, fixedDelay, cron = "0 */5 * * * *", zone)
@EnableAsync
@Async (void / Future / CompletableFuture)
TaskExecutor (ThreadPoolTaskExecutor)
TaskScheduler

## Events

ApplicationEvent, ApplicationEventPublisher
@EventListener
@TransactionalEventListener (AFTER_COMMIT, BEFORE_COMMIT)
@Async + @EventListener — async events
Spring Modulith / ApplicationModule events

## Spring WebFlux (reactive)

@EnableWebFlux
RouterFunction, HandlerFunction
Mono<T>, Flux<T>
WebClient (WebClient.builder())
Reactive repositories (R2dbcRepository, ReactiveMongoRepository)
Reactive Security
Backpressure (onBackpressureBuffer/Drop/Latest)
Schedulers (boundedElastic, parallel)
Context propagation (Context, ContextView)

## Spring Messaging / Integration

Spring Kafka (@KafkaListener, KafkaTemplate)
Spring AMQP / RabbitMQ (@RabbitListener, RabbitTemplate)
Spring JMS (@JmsListener)
Spring WebSocket + STOMP (@MessageMapping)
Spring Integration (EIP)
Spring Cloud Stream (binder abstraction)

## Spring Batch

Job, Step, ItemReader, ItemProcessor, ItemWriter
JobRepository, JobLauncher
Chunk-oriented processing
Tasklet
Partitioning / scaling

## Spring Cloud (microservices)

Spring Cloud Config
Spring Cloud Gateway
Spring Cloud Netflix Eureka (legacy)
Spring Cloud LoadBalancer
Spring Cloud OpenFeign (declarative REST client)
Resilience4j (Circuit Breaker, Retry, RateLimiter, Bulkhead)
Spring Cloud Sleuth → Micrometer Tracing
Spring Cloud Bus
Spring Cloud Stream (Kafka, Rabbit)
Config Server + Vault
Service Discovery (Eureka, Consul)

## Observability

Spring Boot Actuator (/actuator/health, /info, /metrics, /env, /loggers, /httpexchanges)
Micrometer (metrics API)
Prometheus registry
Micrometer Tracing (replaces Sleuth; OpenTelemetry bridge)
HealthIndicator (custom)
InfoContributor
Admin Server (Spring Boot Admin)
Logback / Log4j2 config

## Database / migrations

Flyway (classpath:db/migration/V1__init.sql)
Liquibase (changelog.xml / yaml)
JDBC (JdbcTemplate, NamedParameterJdbcTemplate)
DataSource (HikariCP default)
Connection pooling
Multiple datasources (@Primary, @Qualifier, transaction manager per DS)
R2DBC (reactive)

## Testing

@SpringBootTest (full context)
@WebMvcTest (controller slice)
@DataJpaTest (repo slice, H2 default)
@DataMongoTest, @DataRedisTest
@JdbcTest, @JsonTest
@RestClientTest
MockMvc (standaloneSetup, webAppContextSetup)
@MockBean, @SpyBean (replaced by @MockitoBean in SB 3.4+)
WebTestClient (reactive)
Testcontainers (@Container, @DynamicPropertySource, @ServiceConnection in SB 3.1+)
@ActiveProfiles("test")
@DirtiesContext
TestRestTemplate
@AutoConfigureMockMvc
Spring Cloud Contract

## Build / packaging

Maven / Gradle Spring Boot plugin
spring-boot-starter-parent (BOM)
Executable JAR (fat jar, nested)
spring-boot:build-image (Cloud Native Buildpacks → OCI image)
GraalVM native image (Spring Boot 3+)
Layered JARs (for better Docker caching)

## Modern features (Spring Boot 3.x)

Jakarta EE migration (javax → jakarta)
Native image support (GraalVM)
Observability abstraction (Micrometer Tracing)
Virtual Threads (Java 21 + spring.threads.virtual.enabled=true)
@HttpExchange declarative clients
Problem Details (RFC 7807) ProblemDetail
Spring Modulith (modular monolith)
Project Reactor + Structured Concurrency interop
