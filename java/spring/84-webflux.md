# 84 — Spring WebFlux (Reactive) — Geniş İzah

> **Seviyye:** Expert ⭐⭐⭐⭐


## Mündəricat
1. [Reactive Programming nədir?](#reactive-programming-nədir)
2. [Mono və Flux](#mono-və-flux)
3. [WebFlux Controller](#webflux-controller)
4. [WebClient (Reactive HTTP client)](#webclient-reactive-http-client)
5. [R2DBC (Reactive DB)](#r2dbc-reactive-db)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Reactive Programming nədir?

**Reactive Programming** — non-blocking, asenkron, event-driven proqramlaşdırma modeli. Az thread ilə çox concurrent sorğu idarə etmək üçün.

```
Blocking (Spring MVC):
  Thread 1: Request → IO gözlə... → Response
  Thread 2: Request → IO gözlə... → Response
  (1000 concurrent = 1000 thread)

Non-blocking (WebFlux):
  Thread 1: Request → IO başlat → başqa iş et... → IO bitdi → Response
  Thread 2: Request → IO başlat → başqa iş et...
  (1000 concurrent = bir neçə thread — event loop)
```

**Nə zaman WebFlux:**
- Yüksək concurrent sorğu (chatting, streaming)
- IO-bound əməliyyatlar (çox DB/API sorğusu)
- Server-Sent Events, WebSocket

**Nə zaman Spring MVC:**
- Sadə CRUD app
- CPU-intensive iş
- Team reactive programming bilmirsə

---

## Mono və Flux

**Mono<T>** — 0 yaxud 1 element (Optional kimi)
**Flux<T>** — 0 dən N elementə qədər (Stream kimi)

```java
// Mono
Mono<User> userMono = Mono.just(new User(1L, "Ali"));
Mono<User> empty = Mono.empty();
Mono<User> error = Mono.error(new UserNotFoundException("User tapılmadı"));

// Flux
Flux<Integer> numbers = Flux.range(1, 10);
Flux<String> names = Flux.just("Ali", "Vəli", "Rəhim");
Flux<User> userStream = Flux.fromIterable(userList);

// Operators
Mono<UserDto> userDto = userMono
    .map(user -> new UserDto(user.getId(), user.getName()))    // Transform
    .filter(dto -> dto.getName() != null)                      // Filter
    .defaultIfEmpty(new UserDto(0L, "Anonymous"))              // Default
    .onErrorResume(e -> Mono.just(new UserDto(-1L, "Error"))); // Fallback

// flatMap — async transform (başqa Mono qaytarır)
Mono<Order> orderWithUser = orderMono
    .flatMap(order -> userRepository.findById(order.getUserId())
        .map(user -> {
            order.setUser(user);
            return order;
        })
    );

// Flux operators
Flux<UserDto> dtos = userFlux
    .filter(user -> user.isActive())
    .map(userMapper::toDto)
    .take(10)           // İlk 10-u al
    .skip(5)            // İlk 5-i keç
    .distinct()         // Duplicate-ları çıxar
    .sort(Comparator.comparing(UserDto::getName))
    .collectList();     // Mono<List<UserDto>> qaytarır

// zip — bir neçə Mono-nu birləşdir
Mono<UserProfile> profile = Mono.zip(
    userRepository.findById(userId),
    orderRepository.countByUserId(userId),
    reviewRepository.findLatestByUserId(userId)
).map(tuple -> new UserProfile(
    tuple.getT1(), // User
    tuple.getT2(), // Order count
    tuple.getT3()  // Latest review
));
```

---

## WebFlux Controller

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    private final UserRepository userRepository;

    // Mono<T> — tək element
    @GetMapping("/{id}")
    public Mono<ResponseEntity<User>> getUser(@PathVariable Long id) {
        return userRepository.findById(id)
            .map(user -> ResponseEntity.ok(user))
            .defaultIfEmpty(ResponseEntity.notFound().build());
    }

    // Flux<T> — stream
    @GetMapping
    public Flux<User> getAllUsers() {
        return userRepository.findAll();
    }

    // Server-Sent Events — real-time stream
    @GetMapping(value = "/stream", produces = MediaType.TEXT_EVENT_STREAM_VALUE)
    public Flux<User> streamUsers() {
        return userRepository.findAll()
            .delayElements(Duration.ofMillis(500)); // Hər 500ms-də bir
    }

    // Mono<Void> — create/update
    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public Mono<User> createUser(@RequestBody @Valid Mono<UserRequest> requestMono) {
        return requestMono
            .map(userMapper::toEntity)
            .flatMap(userRepository::save);
    }

    // Functional Router (alternativ)
    @Bean
    public RouterFunction<ServerResponse> userRoutes(UserHandler handler) {
        return RouterFunctions.route()
            .GET("/api/v2/users/{id}", handler::getUser)
            .GET("/api/v2/users", handler::getAllUsers)
            .POST("/api/v2/users", handler::createUser)
            .build();
    }
}

// Handler (Functional style)
@Component
public class UserHandler {

    private final UserRepository userRepository;

    public Mono<ServerResponse> getUser(ServerRequest request) {
        Long id = Long.parseLong(request.pathVariable("id"));
        return userRepository.findById(id)
            .flatMap(user -> ServerResponse.ok().bodyValue(user))
            .switchIfEmpty(ServerResponse.notFound().build());
    }

    public Mono<ServerResponse> getAllUsers(ServerRequest request) {
        Flux<User> users = userRepository.findAll();
        return ServerResponse.ok().body(users, User.class);
    }

    public Mono<ServerResponse> createUser(ServerRequest request) {
        return request.bodyToMono(UserRequest.class)
            .map(userMapper::toEntity)
            .flatMap(userRepository::save)
            .flatMap(user -> ServerResponse.created(
                URI.create("/api/v2/users/" + user.getId()))
                .bodyValue(user));
    }
}
```

---

## WebClient (Reactive HTTP client)

```java
@Service
public class ExternalApiService {

    private final WebClient webClient;

    public ExternalApiService(WebClient.Builder builder) {
        this.webClient = builder
            .baseUrl("https://api.external.com")
            .defaultHeader(HttpHeaders.ACCEPT, MediaType.APPLICATION_JSON_VALUE)
            .filter(ExchangeFilterFunction.ofRequestProcessor(request -> {
                log.debug("Request: {} {}", request.method(), request.url());
                return Mono.just(request);
            }))
            .build();
    }

    // GET sorğusu
    public Mono<Product> getProduct(Long productId) {
        return webClient.get()
            .uri("/products/{id}", productId)
            .retrieve()
            .onStatus(HttpStatus::is4xxClientError,
                response -> Mono.error(new ProductNotFoundException(productId)))
            .onStatus(HttpStatus::is5xxServerError,
                response -> Mono.error(new ExternalServiceException("API xətası")))
            .bodyToMono(Product.class)
            .timeout(Duration.ofSeconds(5))
            .retryWhen(Retry.backoff(3, Duration.ofMillis(500)));
    }

    // POST sorğusu
    public Mono<Order> createOrder(OrderRequest request) {
        return webClient.post()
            .uri("/orders")
            .contentType(MediaType.APPLICATION_JSON)
            .bodyValue(request)
            .retrieve()
            .bodyToMono(Order.class);
    }

    // Flux — stream cavab
    public Flux<Product> getAllProducts() {
        return webClient.get()
            .uri("/products")
            .retrieve()
            .bodyToFlux(Product.class);
    }

    // Paralel sorğular
    public Mono<UserDashboard> getUserDashboard(Long userId) {
        Mono<User> userMono = getUser(userId);
        Mono<List<Order>> ordersMono = getOrders(userId).collectList();
        Mono<List<Product>> favoritesMono = getFavorites(userId).collectList();

        return Mono.zip(userMono, ordersMono, favoritesMono)
            .map(tuple -> new UserDashboard(
                tuple.getT1(),
                tuple.getT2(),
                tuple.getT3()
            ));
    }
}
```

---

## R2DBC (Reactive DB)

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-r2dbc</artifactId>
</dependency>
<dependency>
    <groupId>io.r2dbc</groupId>
    <artifactId>r2dbc-postgresql</artifactId>
</dependency>
```

```yaml
spring:
  r2dbc:
    url: r2dbc:postgresql://localhost:5432/mydb
    username: postgres
    password: password
```

```java
// Reactive Repository
public interface UserRepository extends ReactiveCrudRepository<User, Long> {

    Flux<User> findByActiveTrue();
    Mono<User> findByEmail(String email);
    Flux<User> findByAgeGreaterThan(int age);
}

// Entity
@Table("users")
public class User {
    @Id
    private Long id;
    private String name;
    private String email;
    private boolean active;
}

// Service
@Service
public class UserService {

    private final UserRepository userRepository;

    public Flux<UserDto> getActiveUsers() {
        return userRepository.findByActiveTrue()
            .map(userMapper::toDto);
    }

    @Transactional // Reactive transaction
    public Mono<User> createUser(UserRequest request) {
        return userRepository.findByEmail(request.getEmail())
            .flatMap(existing -> Mono.<User>error(
                new EmailAlreadyExistsException(request.getEmail())))
            .switchIfEmpty(
                Mono.just(userMapper.toEntity(request))
                    .flatMap(userRepository::save)
            );
    }
}
```

---

## İntervyu Sualları

### 1. WebFlux vs Spring MVC nə zaman seçmək?
**Cavab:** WebFlux — yüksək concurrent sorğu, IO-bound (çox external API/DB call), streaming (SSE, WebSocket). Spring MVC — sadə CRUD, blocking library istifadəsi (JPA), team reactive bilmirsə. Reactive sistem tamamilə non-blocking olmalıdır — bir blocking çağırış bütün thread-i bloklar.

### 2. Mono vs Flux fərqi nədir?
**Cavab:** `Mono<T>` — 0 yaxud 1 element qaytarır (Optional-a bənzər). `Flux<T>` — 0-dan N elementə qədər (Stream/List-ə bənzər). Hər ikisi `Publisher<T>` implementasiyasıdır. Subscribe olmadan heç bir əməliyyat işləmir (lazy evaluation).

### 3. blockingdən niyə qaçmaq lazımdır?
**Cavab:** WebFlux az sayda event loop thread istifadə edir. Blocking çağırış (Thread.sleep, JDBC query, .block()) bu thread-i tutur — digər sorğular bloklanır. Throughput sıfıra düşə bilər. Məcburi blocking əməliyyatlar varsa `subscribeOn(Schedulers.boundedElastic())` ilə ayrı thread pool-da işlət.

### 4. flatMap vs map fərqi?
**Cavab:** `map` — dəyəri sinxron transform edir (`User → UserDto`). `flatMap` — async transform edir — yeni `Mono`/`Flux` qaytarır (başqa reactive əməliyyat). Məsələn, `flatMap(user -> repository.findOrders(user.getId()))` — hər user üçün DB-dən sifarişlər yüklənir.

### 5. Reactive transaction necə işləyir?
**Cavab:** R2DBC + `@Transactional` Spring reactive transaction idarə edir. Context propagation `ReactiveTransactionManager` vasitəsilə işləyir. JPA/Hibernate reactive dəstəkləmir — R2DBC istifadə edilməlidir. Reactive transaction `Mono`/`Flux` chain-i boyunca yayılır.

*Son yenilənmə: 2026-04-10*
