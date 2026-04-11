# Java & Spring Framework — Öyrənmə Mövzuları

Bu qovluq Java core və Spring Framework-ün hər bir mövzusunu ayrı-ayrı fayllar şəklində əhatə edir.
Hər fayl: ətraflı izah (Azərbaycanca), kod nümunələri (Java), intervyu sualları.

---

## Java — OOP (01–10)
| # | Fayl | Mövzu |
|---|------|-------|
| 01 | [oop-classes-objects.md](topics/01-oop-classes-objects.md) | Class anatomy, constructors, static vs instance |
| 02 | [oop-interfaces.md](topics/02-oop-interfaces.md) | default/static methods, functional interface, marker interface |
| 03 | [oop-abstract-classes.md](topics/03-oop-abstract-classes.md) | abstract vs interface, template method pattern |
| 04 | [oop-inheritance.md](topics/04-oop-inheritance.md) | extends, super, method overriding, covariant return |
| 05 | [oop-polymorphism.md](topics/05-oop-polymorphism.md) | compile-time vs runtime, dynamic dispatch |
| 06 | [oop-encapsulation.md](topics/06-oop-encapsulation.md) | access modifiers, getters/setters, information hiding |
| 07 | [oop-abstraction.md](topics/07-oop-abstraction.md) | abstraction layers, leaky abstraction |
| 08 | [oop-inner-classes.md](topics/08-oop-inner-classes.md) | static nested, inner, local, anonymous classes |
| 09 | [oop-enums.md](topics/09-oop-enums.md) | enum methods, fields, abstract methods, EnumMap/EnumSet |
| 10 | [oop-records.md](topics/10-oop-records.md) | Java 16+ records, compact constructor, limitations |

## Java — Collections (11–20)
| # | Fayl | Mövzu |
|---|------|-------|
| 11 | [collections-overview.md](topics/11-collections-overview.md) | Collection hierarchy, Iterable, Iterator |
| 12 | [collections-arraylist-linkedlist.md](topics/12-collections-arraylist-linkedlist.md) | daxili struktur, Big-O, nə zaman hansı |
| 13 | [collections-hashmap.md](topics/13-collections-hashmap.md) | hashing, bucket, load factor, resize, collision |
| 14 | [collections-linkedhashmap-treemap.md](topics/14-collections-linkedhashmap-treemap.md) | sıra fərqi, NavigableMap, SortedMap |
| 15 | [collections-hashset-treeset.md](topics/15-collections-hashset-treeset.md) | Set semantikası, equals/hashCode müqaviləsi |
| 16 | [collections-queue-deque.md](topics/16-collections-queue-deque.md) | PriorityQueue, ArrayDeque, LinkedList as Deque |
| 17 | [collections-concurrent.md](topics/17-collections-concurrent.md) | ConcurrentHashMap, CopyOnWriteArrayList, BlockingQueue |
| 18 | [collections-comparable-comparator.md](topics/18-collections-comparable-comparator.md) | natural ordering, custom sort |
| 19 | [collections-utility.md](topics/19-collections-utility.md) | Collections, Arrays utility metodlar |
| 20 | [collections-fail-fast-fail-safe.md](topics/20-collections-fail-fast-fail-safe.md) | ConcurrentModificationException, iterator davranışı |

## Java — Generics (21–24)
| # | Fayl | Mövzu |
|---|------|-------|
| 21 | [generics-basics.md](topics/21-generics-basics.md) | generic class/method/interface, type parameter |
| 22 | [generics-wildcards.md](topics/22-generics-wildcards.md) | `?`, `extends`, `super`, PECS prinsipi |
| 23 | [generics-type-erasure.md](topics/23-generics-type-erasure.md) | compile-time vs runtime, reification |
| 24 | [generics-bounded-types.md](topics/24-generics-bounded-types.md) | multiple bounds, recursive bounds |

## Java — Streams & Functional (25–31)
| # | Fayl | Mövzu |
|---|------|-------|
| 25 | [streams-basics.md](topics/25-streams-basics.md) | Stream yaratma, lazy evaluation, pipeline |
| 26 | [streams-intermediate-ops.md](topics/26-streams-intermediate-ops.md) | filter, map, flatMap, distinct, sorted, peek |
| 27 | [streams-terminal-ops.md](topics/27-streams-terminal-ops.md) | collect, reduce, count, findFirst, anyMatch |
| 28 | [streams-collectors.md](topics/28-streams-collectors.md) | groupingBy, partitioningBy, joining, toMap |
| 29 | [streams-parallel.md](topics/29-streams-parallel.md) | parallel stream, ForkJoinPool, thread-safety |
| 30 | [functional-interfaces.md](topics/30-functional-interfaces.md) | Function, Predicate, Consumer, Supplier, BiXxx |
| 31 | [optional.md](topics/31-optional.md) | Optional yaratma, map/flatMap/filter, anti-patternlər |

## Java — Concurrency (32–41)
| # | Fayl | Mövzu |
|---|------|-------|
| 32 | [concurrency-thread-basics.md](topics/32-concurrency-thread-basics.md) | Thread, Runnable, Callable, lifecycle |
| 33 | [concurrency-executorservice.md](topics/33-concurrency-executorservice.md) | ThreadPool növləri, Future, submit vs execute |
| 34 | [concurrency-completablefuture.md](topics/34-concurrency-completablefuture.md) | async chaining, thenApply/thenCompose/handle |
| 35 | [concurrency-synchronized.md](topics/35-concurrency-synchronized.md) | synchronized block/method, intrinsic lock, reentrant |
| 36 | [concurrency-locks.md](topics/36-concurrency-locks.md) | ReentrantLock, ReadWriteLock, StampedLock |
| 37 | [concurrency-atomic.md](topics/37-concurrency-atomic.md) | AtomicInteger, AtomicReference, CAS operation |
| 38 | [concurrency-semaphore-latch.md](topics/38-concurrency-semaphore-latch.md) | Semaphore, CountDownLatch, CyclicBarrier, Phaser |
| 39 | [concurrency-volatile.md](topics/39-concurrency-volatile.md) | volatile, happens-before, memory visibility |
| 40 | [concurrency-threadlocal.md](topics/40-concurrency-threadlocal.md) | ThreadLocal, InheritableThreadLocal, memory leak riski |
| 41 | [concurrency-virtual-threads.md](topics/41-concurrency-virtual-threads.md) | Java 21 virtual threads, structured concurrency |

## Java — JVM & Memory (42–48)
| # | Fayl | Mövzu |
|---|------|-------|
| 42 | [jvm-architecture.md](topics/42-jvm-architecture.md) | ClassLoader, Runtime areas, Execution Engine |
| 43 | [jvm-memory-areas.md](topics/43-jvm-memory-areas.md) | Heap, Stack, Metaspace, Code Cache, PC Register |
| 44 | [jvm-classloading.md](topics/44-jvm-classloading.md) | Bootstrap/Platform/App CL, delegation model, custom CL |
| 45 | [jvm-gc-basics.md](topics/45-jvm-gc-basics.md) | GC nədir, root references, mark-sweep-compact |
| 46 | [jvm-gc-algorithms.md](topics/46-jvm-gc-algorithms.md) | G1GC, ZGC, Shenandoah, Serial, Parallel fərqləri |
| 47 | [jvm-gc-tuning.md](topics/47-jvm-gc-tuning.md) | heap sizing, GC flags, GC log analizi |
| 48 | [jvm-jit-compiler.md](topics/48-jvm-jit-compiler.md) | JIT, C1/C2, tiered compilation, inlining, escape analysis |

## Java — Digər Core (49–57)
| # | Fayl | Mövzu |
|---|------|-------|
| 49 | [exceptions-basics.md](topics/49-exceptions-basics.md) | Exception hierarchy, checked/unchecked, Error |
| 50 | [exceptions-best-practices.md](topics/50-exceptions-best-practices.md) | try-with-resources, multi-catch, custom exceptions |
| 51 | [io-streams.md](topics/51-io-streams.md) | InputStream/OutputStream, Reader/Writer, buffering |
| 52 | [io-nio.md](topics/52-io-nio.md) | Channel, Buffer, Selector, non-blocking I/O |
| 53 | [reflection-api.md](topics/53-reflection-api.md) | Class, Method, Field, Constructor introspection |
| 54 | [annotations-custom.md](topics/54-annotations-custom.md) | @interface, retention, target, annotation processor |
| 55 | [design-patterns-creational.md](topics/55-design-patterns-creational.md) | Singleton, Factory, Builder, Prototype, AbstractFactory |
| 56 | [design-patterns-structural.md](topics/56-design-patterns-structural.md) | Adapter, Decorator, Proxy, Facade, Composite |
| 57 | [design-patterns-behavioral.md](topics/57-design-patterns-behavioral.md) | Strategy, Observer, Command, Template, Chain of Responsibility |

## Spring — Core Container (58–68)
| # | Fayl | Mövzu |
|---|------|-------|
| 58 | [spring-ioc-container.md](topics/58-spring-ioc-container.md) | IoC nədir, BeanFactory vs ApplicationContext |
| 59 | [spring-bean-definition.md](topics/59-spring-bean-definition.md) | @Component, @Service, @Repository, @Controller fərqi |
| 60 | [spring-bean-scopes.md](topics/60-spring-bean-scopes.md) | singleton, prototype, request, session, application |
| 61 | [spring-bean-lifecycle.md](topics/61-spring-bean-lifecycle.md) | instantiation → populate → init → use → destroy |
| 62 | [spring-dependency-injection.md](topics/62-spring-dependency-injection.md) | constructor, setter, field injection — fərqlər |
| 63 | [spring-configuration.md](topics/63-spring-configuration.md) | @Configuration, @Bean, @Import, lite vs full mode |
| 64 | [spring-value-configprops.md](topics/64-spring-value-configprops.md) | @Value, @ConfigurationProperties, relaxed binding |
| 65 | [spring-profiles.md](topics/65-spring-profiles.md) | @Profile, spring.profiles.active, multi-env config |
| 66 | [spring-conditional.md](topics/66-spring-conditional.md) | @Conditional, @ConditionalOnClass/Bean/Property |
| 67 | [spring-beanpostprocessor.md](topics/67-spring-beanpostprocessor.md) | BeanPostProcessor, BeanFactoryPostProcessor |
| 68 | [spring-events.md](topics/68-spring-events.md) | ApplicationEvent, @EventListener, @TransactionalEventListener |

## Spring — AOP (69–73)
| # | Fayl | Mövzu |
|---|------|-------|
| 69 | [spring-aop-concepts.md](topics/69-spring-aop-concepts.md) | Aspect, Advice, Joinpoint, Pointcut, Weaving |
| 70 | [spring-aop-pointcuts.md](topics/70-spring-aop-pointcuts.md) | execution, within, @annotation, args expressions |
| 71 | [spring-aop-advices.md](topics/71-spring-aop-advices.md) | @Before, @After, @AfterReturning, @AfterThrowing |
| 72 | [spring-aop-around.md](topics/72-spring-aop-around.md) | @Around, ProceedingJoinPoint, return dəyəri manipulyasiya |
| 73 | [spring-aop-proxy.md](topics/73-spring-aop-proxy.md) | JDK dynamic proxy vs CGLIB, self-invocation problemi |

## Spring — Web MVC (74–81)
| # | Fayl | Mövzu |
|---|------|-------|
| 74 | [spring-mvc-dispatcherservlet.md](topics/74-spring-mvc-dispatcherservlet.md) | request lifecycle, handler mapping, view resolution |
| 75 | [spring-mvc-controllers.md](topics/75-spring-mvc-controllers.md) | @RequestMapping, @GetMapping, @PathVariable, @RequestParam |
| 76 | [spring-mvc-request-response.md](topics/76-spring-mvc-request-response.md) | @RequestBody, @ResponseBody, ResponseEntity, HttpEntity |
| 77 | [spring-mvc-exception-handling.md](topics/77-spring-mvc-exception-handling.md) | @ExceptionHandler, @ControllerAdvice, ProblemDetail |
| 78 | [spring-mvc-filters-interceptors.md](topics/78-spring-mvc-filters-interceptors.md) | Filter vs HandlerInterceptor, fərqlər, istifadə halları |
| 79 | [spring-mvc-content-negotiation.md](topics/79-spring-mvc-content-negotiation.md) | Accept header, MessageConverter, produces/consumes |
| 80 | [spring-validation.md](topics/80-spring-validation.md) | @Valid, @Validated, @NotNull/@Size, BindingResult |
| 81 | [spring-validation-custom.md](topics/81-spring-validation-custom.md) | custom @Constraint, ConstraintValidator, cross-field validation |

## Spring — Data & JPA (82–94)
| # | Fayl | Mövzu |
|---|------|-------|
| 82 | [spring-data-repositories.md](topics/82-spring-data-repositories.md) | CrudRepository, JpaRepository, PagingAndSortingRepository |
| 83 | [spring-data-entity.md](topics/83-spring-data-entity.md) | @Entity, @Table, @Id, @GeneratedValue, @Column |
| 84 | [spring-data-relationships.md](topics/84-spring-data-relationships.md) | @OneToOne, @OneToMany, @ManyToOne, @ManyToMany |
| 85 | [spring-data-fetch-strategies.md](topics/85-spring-data-fetch-strategies.md) | LAZY vs EAGER, FetchType, @EntityGraph |
| 86 | [spring-data-jpql.md](topics/86-spring-data-jpql.md) | @Query, JPQL sintaksisi, named queries |
| 87 | [spring-data-native-queries.md](topics/87-spring-data-native-queries.md) | native SQL, nResultSet mapping |
| 88 | [spring-data-projections.md](topics/88-spring-data-projections.md) | interface projection, class projection, dynamic projection |
| 89 | [spring-data-specifications.md](topics/89-spring-data-specifications.md) | Specification API, JpaSpecificationExecutor, dynamic filter |
| 90 | [spring-data-pagination.md](topics/90-spring-data-pagination.md) | Pageable, Page, Slice, Sort |
| 91 | [spring-data-auditing.md](topics/91-spring-data-auditing.md) | @CreatedDate, @LastModifiedBy, @EnableJpaAuditing |
| 92 | [spring-transactions.md](topics/92-spring-transactions.md) | @Transactional, propagation növləri |
| 93 | [spring-transactions-isolation.md](topics/93-spring-transactions-isolation.md) | isolation levels, dirty/phantom read, lost update |
| 94 | [hibernate-session-cache.md](topics/94-hibernate-session-cache.md) | Session, EntityManager, 1st/2nd level cache, evict |

## Spring — Security (95–102)
| # | Fayl | Mövzu |
|---|------|-------|
| 95 | [spring-security-architecture.md](topics/95-spring-security-architecture.md) | SecurityFilterChain, AuthenticationManager, SecurityContext |
| 96 | [spring-security-authentication.md](topics/96-spring-security-authentication.md) | UserDetailsService, AuthenticationProvider, custom auth |
| 97 | [spring-security-authorization.md](topics/97-spring-security-authorization.md) | @PreAuthorize, @PostAuthorize, @Secured, roles vs authorities |
| 98 | [spring-security-jwt.md](topics/98-spring-security-jwt.md) | JWT strukturu, JwtFilter, token validation |
| 99 | [spring-security-oauth2.md](topics/99-spring-security-oauth2.md) | OAuth2 Resource Server, Authorization Server, scopes |
| 100 | [spring-security-method-security.md](topics/100-spring-security-method-security.md) | method-level security, @EnableMethodSecurity |
| 101 | [spring-security-cors.md](topics/101-spring-security-cors.md) | CORS konfiqurasiyası, preflight request |
| 102 | [spring-security-csrf.md](topics/102-spring-security-csrf.md) | CSRF nədir, token mexanizmi, disable nə zaman |

## Spring — Boot & Infrastructure (103–112)
| # | Fayl | Mövzu |
|---|------|-------|
| 103 | [spring-boot-autoconfiguration.md](topics/103-spring-boot-autoconfiguration.md) | @EnableAutoConfiguration, spring.factories, META-INF/spring |
| 104 | [spring-boot-starter.md](topics/104-spring-boot-starter.md) | custom starter yaratma, autoconfigure module |
| 105 | [spring-boot-actuator.md](topics/105-spring-boot-actuator.md) | /health, /metrics, /env, custom endpoint |
| 106 | [spring-boot-devtools.md](topics/106-spring-boot-devtools.md) | live reload, restart, remote debugging |
| 107 | [spring-boot-embedded-server.md](topics/107-spring-boot-embedded-server.md) | Tomcat/Jetty/Undertow, server konfiqurasiya |
| 108 | [spring-cache.md](topics/108-spring-cache.md) | @Cacheable, @CacheEvict, @CachePut, CacheManager |
| 109 | [spring-mail.md](topics/109-spring-mail.md) | JavaMailSender, MimeMessage, HTML email, attachment |
| 110 | [spring-scheduling.md](topics/110-spring-scheduling.md) | @Scheduled, fixedRate/fixedDelay/cron, ThreadPoolTaskScheduler |
| 111 | [spring-async.md](topics/111-spring-async.md) | @Async, @EnableAsync, ThreadPoolTaskExecutor |
| 112 | [spring-file-upload.md](topics/112-spring-file-upload.md) | MultipartFile, file storage, size limit konfiqurasiya |

## Spring — Messaging (113–117)
| # | Fayl | Mövzu |
|---|------|-------|
| 113 | [spring-kafka-producer.md](topics/113-spring-kafka-producer.md) | KafkaTemplate, ProducerRecord, serialization, acks |
| 114 | [spring-kafka-consumer.md](topics/114-spring-kafka-consumer.md) | @KafkaListener, consumer group, offset, error handling |
| 115 | [spring-rabbitmq.md](topics/115-spring-rabbitmq.md) | @RabbitListener, Exchange/Queue/Binding, DLQ |
| 116 | [spring-websocket.md](topics/116-spring-websocket.md) | STOMP, @MessageMapping, SockJS, topic/queue |
| 117 | [spring-batch.md](topics/117-spring-batch.md) | Job/Step, ItemReader/Processor/Writer, chunk, skip/retry |

## Spring — Cloud & Microservices (118–126)
| # | Fayl | Mövzu |
|---|------|-------|
| 118 | [spring-cloud-overview.md](topics/118-spring-cloud-overview.md) | Spring Cloud ekosistemi, komponentlər |
| 119 | [spring-cloud-gateway.md](topics/119-spring-cloud-gateway.md) | routing, predicates, filters, rate limiting |
| 120 | [spring-cloud-eureka.md](topics/120-spring-cloud-eureka.md) | service discovery, self-registration, heartbeat |
| 121 | [spring-cloud-config.md](topics/121-spring-cloud-config.md) | config server, git backend, @RefreshScope |
| 122 | [spring-cloud-openfeign.md](topics/122-spring-cloud-openfeign.md) | declarative HTTP client, fallback, timeout |
| 123 | [spring-cloud-resilience4j.md](topics/123-spring-cloud-resilience4j.md) | CircuitBreaker, Retry, Bulkhead, RateLimiter |
| 124 | [spring-cloud-sleuth-zipkin.md](topics/124-spring-cloud-sleuth-zipkin.md) | distributed tracing, trace/span, Zipkin |
| 125 | [spring-actuator-prometheus.md](topics/125-spring-actuator-prometheus.md) | metrics export, Micrometer, Prometheus/Grafana |
| 126 | [spring-webflux.md](topics/126-spring-webflux.md) | Mono/Flux, reactive endpoints, WebClient, backpressure |

## Architecture (127–134)
| # | Fayl | Mövzu |
|---|------|-------|
| 127 | [solid-principles-java.md](topics/127-solid-principles-java.md) | SRP, OCP, LSP, ISP, DIP — Java nümunələri |
| 128 | [hexagonal-architecture.md](topics/128-hexagonal-architecture.md) | Ports & Adapters, package strukturu, Spring-də tətbiq |
| 129 | [ddd-tactical-java.md](topics/129-ddd-tactical-java.md) | Entity, ValueObject, Aggregate, Repository, DomainService |
| 130 | [cqrs-java.md](topics/130-cqrs-java.md) | command/query ayrılması, read/write model |
| 131 | [event-sourcing-java.md](topics/131-event-sourcing-java.md) | event store, snapshot, replay |
| 132 | [saga-pattern-java.md](topics/132-saga-pattern-java.md) | choreography vs orchestration |
| 133 | [outbox-pattern-java.md](topics/133-outbox-pattern-java.md) | transactional outbox, Debezium CDC |
| 134 | [grpc-java-spring.md](topics/134-grpc-java-spring.md) | protobuf, service definition, Spring gRPC |

---

*Cəmi: 134 fayl | Son yenilənmə: 2026-04-10*
