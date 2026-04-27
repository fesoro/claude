# HTTP Client-lər: RestClient vs RestTemplate vs WebClient — Spring vs Laravel HTTP Facade (Senior)

## İcmal

Spring-də HTTP sorğuları göndərmək üçün üç variant mövcuddur: köhnə `RestTemplate`, yeni fluent `RestClient` (Spring 6.1+), və reaktiv `WebClient` (WebFlux). PHP developerinin Laravel-də `Http::get()` ilə bildikləri ilə müqayisəli şəkildə bu client-lərin fərqlərini, hansı halda hansını seçmək lazım olduğunu öyrənirik.

---

## Niyə Vacibdir

Backend developer kimi xarici API-lara (payment gateway, notification service, third-party integration) sorğu göndərməyə davamlı ehtiyac var. Yanlış client seçimi:

- Thread-blocking ilə bütün server-i yavaşlada bilər
- Test etmə çətinliyi yaradar
- Timeout konfiqurasiyasını mürəkkəbləşdirər
- Error handling-i idarəolunmaz edər

Spring 6.1+ / Boot 3.2+ ilə `RestClient` demək olar ki, bütün hallarda `RestTemplate`-i əvəz edir.

---

## Laravel HTTP Facade — Baza Müqayisəsi

Laravel HTTP Facade Spring-in yeni `RestClient`-inə ən yaxın analoqudur: fluent, chain-based, oxunaqlı API.

```php
// Sadə GET
$response = Http::get('https://api.example.com/users');
$users = $response->json();

// POST with JSON body
$response = Http::post('https://api.example.com/users', [
    'name' => 'Orkhan',
    'email' => 'orkhan@example.com',
]);

// Authentication
$response = Http::withToken($token)
    ->acceptJson()
    ->post('https://api.example.com/users', $data);

// Response handling
if ($response->successful()) {
    return $response->json('data');
}

if ($response->clientError()) {   // 4xx
    throw new ApiException($response->json('message'));
}

if ($response->serverError()) {   // 5xx
    throw new ServiceUnavailableException();
}

// Retry
$response = Http::retry(3, 100)  // 3 cəhd, 100ms arasında
    ->get('https://api.example.com/fragile-endpoint');
```

Laravel HTTP Facade-in güclü tərəfləri:
- `Http::fake()` ilə test asanlığı
- `withToken()`, `withBasicAuth()`, `withHeaders()` - fluent chaining
- `timeout()`, `connectTimeout()` - konfiqurasiya rahatlığı
- `retry()` - built-in retry logic

---

## Spring-də HTTP Client Variantları

| Client | Spring versiya | Stil | Blocking | Status |
|--------|---------------|------|----------|--------|
| `RestTemplate` | 3.0+ | İmperative | Blocking | Soft-deprecated |
| `RestClient` | 6.1+ (Boot 3.2+) | Fluent | Blocking | **Tövsiyə olunan** |
| `WebClient` | 5.0+ (WebFlux) | Reactive | Non-blocking | WebFlux proyektlər üçün |

---

## RestTemplate (Köhnə, amma Hələ İstifadədə)

`RestTemplate` Spring 6.0-da soft-deprecated edildi. Köhnə proyektlərdə hələ geniş istifadə olunur.

```java
@Service
public class UserService {

    private final RestTemplate restTemplate;

    public UserService(RestTemplateBuilder builder) {
        this.restTemplate = builder
            .connectTimeout(Duration.ofSeconds(5))
            .readTimeout(Duration.ofSeconds(10))
            .build();
    }

    // GET — entity qaytarır
    public UserDto getUser(Long id) {
        return restTemplate.getForObject(
            "https://api.example.com/users/{id}",
            UserDto.class,
            id
        );
    }

    // POST — entity göndərir
    public UserDto createUser(CreateUserRequest request) {
        return restTemplate.postForObject(
            "https://api.example.com/users",
            request,
            UserDto.class
        );
    }

    // exchange() — full control (headers, status code)
    public ResponseEntity<UserDto> getUserWithHeaders(Long id) {
        HttpHeaders headers = new HttpHeaders();
        headers.setBearerAuth("my-token");

        HttpEntity<Void> entity = new HttpEntity<>(headers);

        return restTemplate.exchange(
            "https://api.example.com/users/{id}",
            HttpMethod.GET,
            entity,
            UserDto.class,
            id
        );
    }

    // List qaytarmaq üçün ParameterizedTypeReference lazımdır
    public List<UserDto> getUsers() {
        ResponseEntity<List<UserDto>> response = restTemplate.exchange(
            "https://api.example.com/users",
            HttpMethod.GET,
            null,
            new ParameterizedTypeReference<List<UserDto>>() {}
        );
        return response.getBody();
    }
}
```

**Problemlər:**
- `ParameterizedTypeReference` — generic type üçün verbose syntax
- `exchange()` — çox parametr, oxunaqlılıq aşağı
- Error handling — default olaraq `HttpClientErrorException` throw edir, customize etmək üçün `ResponseErrorHandler` lazımdır
- Test — `MockRestServiceServer` var, amma setup çətindir

---

## RestClient (Spring 6.1+ — Tövsiyə olunan)

`RestClient` Laravel HTTP Facade-ə ən yaxın analoqudur. Fluent API, oxunaqlı, müasir.

```java
@Service
public class UserService {

    private final RestClient restClient;

    public UserService(RestClient.Builder builder) {
        this.restClient = builder
            .baseUrl("https://api.example.com")
            .defaultHeader(HttpHeaders.CONTENT_TYPE, MediaType.APPLICATION_JSON_VALUE)
            .build();
    }

    // GET
    public UserDto getUser(Long id) {
        return restClient.get()
            .uri("/users/{id}", id)
            .retrieve()
            .body(UserDto.class);
    }

    // GET — List
    public List<UserDto> getUsers() {
        return restClient.get()
            .uri("/users")
            .retrieve()
            .body(new ParameterizedTypeReference<List<UserDto>>() {});
    }

    // POST
    public UserDto createUser(CreateUserRequest request) {
        return restClient.post()
            .uri("/users")
            .contentType(MediaType.APPLICATION_JSON)
            .body(request)
            .retrieve()
            .body(UserDto.class);
    }

    // PUT
    public UserDto updateUser(Long id, UpdateUserRequest request) {
        return restClient.put()
            .uri("/users/{id}", id)
            .body(request)
            .retrieve()
            .body(UserDto.class);
    }

    // DELETE
    public void deleteUser(Long id) {
        restClient.delete()
            .uri("/users/{id}", id)
            .retrieve()
            .toBodilessEntity();
    }

    // Error handling — .onStatus()
    public UserDto getUserSafe(Long id) {
        return restClient.get()
            .uri("/users/{id}", id)
            .retrieve()
            .onStatus(HttpStatusCode::is4xxClientError, (request, response) -> {
                throw new UserNotFoundException("User not found: " + id);
            })
            .onStatus(HttpStatusCode::is5xxServerError, (request, response) -> {
                throw new ServiceUnavailableException("Upstream service error");
            })
            .body(UserDto.class);
    }

    // exchange() — full ResponseEntity control
    public ResponseEntity<UserDto> getUserWithStatus(Long id) {
        return restClient.get()
            .uri("/users/{id}", id)
            .retrieve()
            .toEntity(UserDto.class);
    }
}
```

### Base URL, Default Headers, Interceptors

```java
@Configuration
public class HttpClientConfig {

    @Bean
    public RestClient paymentRestClient(RestClient.Builder builder) {
        return builder
            .baseUrl("https://api.payment.com/v2")
            .defaultHeader(HttpHeaders.AUTHORIZATION, "Bearer " + apiKey)
            .defaultHeader("X-Client-Version", "1.0")
            .requestInterceptor(loggingInterceptor())        // logging
            .requestInterceptor(retryInterceptor())          // retry
            .build();
    }

    // Logging interceptor — Laravel-in Http::withOptions()-ə bənzər
    private ClientHttpRequestInterceptor loggingInterceptor() {
        return (request, body, execution) -> {
            log.info("HTTP {} {}", request.getMethod(), request.getURI());
            ClientHttpResponse response = execution.execute(request, body);
            log.info("Response status: {}", response.getStatusCode());
            return response;
        };
    }
}
```

### Timeout Konfiqurasiyası

```java
@Configuration
public class HttpClientConfig {

    @Bean
    public RestClient restClient(RestClient.Builder builder) {
        // Spring Boot 3.2+: factory ilə timeout
        HttpComponentsClientHttpRequestFactory factory =
            new HttpComponentsClientHttpRequestFactory();

        factory.setConnectTimeout(Duration.ofSeconds(5));
        factory.setReadTimeout(Duration.ofSeconds(15));
        factory.setConnectionRequestTimeout(Duration.ofSeconds(3));

        return builder
            .baseUrl("https://api.example.com")
            .requestFactory(factory)
            .build();
    }
}
```

---

## WebClient (Reaktiv)

`WebClient` WebFlux proyektlər üçün nəzərdə tutulub. Non-blocking, reactive stream-lər üzərindən işləyir.

```java
@Service
public class ReactiveUserService {

    private final WebClient webClient;

    public ReactiveUserService(WebClient.Builder builder) {
        this.webClient = builder
            .baseUrl("https://api.example.com")
            .defaultHeader(HttpHeaders.CONTENT_TYPE, MediaType.APPLICATION_JSON_VALUE)
            .build();
    }

    // GET — Mono (0 ya 1 element)
    public Mono<UserDto> getUser(Long id) {
        return webClient.get()
            .uri("/users/{id}", id)
            .retrieve()
            .onStatus(HttpStatusCode::is4xxClientError,
                response -> Mono.error(new UserNotFoundException(id.toString())))
            .bodyToMono(UserDto.class);
    }

    // GET — Flux (0 ya n element, stream)
    public Flux<UserDto> getUsers() {
        return webClient.get()
            .uri("/users")
            .retrieve()
            .bodyToFlux(UserDto.class);
    }

    // POST
    public Mono<UserDto> createUser(CreateUserRequest request) {
        return webClient.post()
            .uri("/users")
            .bodyValue(request)
            .retrieve()
            .bodyToMono(UserDto.class);
    }

    // Parallel sorğular — WebClient-in ən güclü tərəfi
    public Mono<UserProfileDto> getUserProfile(Long userId) {
        Mono<UserDto> userMono = getUser(userId);
        Mono<List<OrderDto>> ordersMono = webClient.get()
            .uri("/users/{id}/orders", userId)
            .retrieve()
            .bodyToFlux(OrderDto.class)
            .collectList();

        // Hər ikisini paralel icra et
        return Mono.zip(userMono, ordersMono)
            .map(tuple -> UserProfileDto.of(tuple.getT1(), tuple.getT2()));
    }
}
```

### WebClient Timeout

```java
@Bean
public WebClient webClient() {
    HttpClient httpClient = HttpClient.create()
        .option(ChannelOption.CONNECT_TIMEOUT_MILLIS, 5_000)
        .responseTimeout(Duration.ofSeconds(15))
        .doOnConnected(conn ->
            conn.addHandlerLast(new ReadTimeoutHandler(15, TimeUnit.SECONDS))
                .addHandlerLast(new WriteTimeoutHandler(5, TimeUnit.SECONDS)));

    return WebClient.builder()
        .baseUrl("https://api.example.com")
        .clientConnector(new ReactorClientHttpConnector(httpClient))
        .build();
}
```

---

## Müqayisə Cədvəli

| Xüsusiyyət | Laravel HTTP Facade | RestTemplate | RestClient | WebClient |
|------------|---------------------|--------------|------------|-----------|
| **API stili** | Fluent chain | İmperative | Fluent chain | Reactive |
| **Blocking** | Blocking | Blocking | Blocking | Non-blocking |
| **Spring versiya** | — | 3.0+ | 6.1+ | 5.0+ |
| **Syntax sadəliyi** | Çox sadə | Orta | Sadə | Mürəkkəb |
| **Error handling** | `->failed()`, `throw()` | Exception catch | `.onStatus()` | `.onStatus()` + Mono/Flux |
| **Test dəstəyi** | `Http::fake()` | MockRestServiceServer | MockRestServiceServer | WebTestClient |
| **Timeout konfiqurasiya** | `->timeout()` | Builder | RequestFactory | HttpClient |
| **Parallel sorğular** | `Http::pool()` | Manual thread | CompletableFuture | `Mono.zip()` / `Flux.merge()` |
| **Status** | Aktiv | Soft-deprecated | Aktiv, tövsiyə olunur | Aktiv (WebFlux üçün) |
| **İstifadə tövsiyəsi** | Laravel-də standart | Legacy kod | Spring standard | Yalnız reactive app |

---

## Nümunələr

### Laravel HTTP Facade

```php
// Base URL + default headers
$client = Http::baseUrl('https://api.example.com')
    ->withToken(config('services.api.token'))
    ->acceptJson();

// GET
$user = $client->get("/users/{$id}")->json();

// POST
$created = $client->post('/users', $data)->json();

// Error handling
$response = $client->get("/users/{$id}");
if ($response->notFound()) {
    throw new UserNotFoundException($id);
}
$response->throw(); // 4xx/5xx-də exception throw edir

// Fake for testing
Http::fake([
    'api.example.com/users/*' => Http::response(['id' => 1, 'name' => 'Orkhan'], 200),
]);
```

### Spring RestClient

```java
// BaseUrl + default headers
RestClient client = RestClient.builder()
    .baseUrl("https://api.example.com")
    .defaultHeader(HttpHeaders.AUTHORIZATION, "Bearer " + token)
    .build();

// GET
UserDto user = client.get()
    .uri("/users/{id}", id)
    .retrieve()
    .onStatus(HttpStatusCode::is4xxClientError,
        (req, res) -> { throw new UserNotFoundException(id); })
    .body(UserDto.class);

// POST
UserDto created = client.post()
    .uri("/users")
    .contentType(MediaType.APPLICATION_JSON)
    .body(data)
    .retrieve()
    .body(UserDto.class);
```

### Spring WebClient

```java
// Reactive GET
Mono<UserDto> userMono = webClient.get()
    .uri("/users/{id}", id)
    .retrieve()
    .onStatus(HttpStatusCode::is4xxClientError,
        res -> Mono.error(new UserNotFoundException(id)))
    .bodyToMono(UserDto.class);

// Subscribe (MVC controller-də block() işlədir, amma anti-pattern-dir)
UserDto user = userMono.block(); // ❌ Reactive app-da bunu etmə

// Reactive controller-da düzgün istifadə
@GetMapping("/users/{id}")
public Mono<UserDto> getUser(@PathVariable Long id) {
    return userService.getUser(id); // block() çağırmırsan
}
```

### Yan-yana Müqayisə

```php
// Laravel
$response = Http::withToken($token)
    ->post('https://api.example.com/users', $data);

if ($response->failed()) {
    Log::error('API error', ['status' => $response->status()]);
    throw new ApiException($response->json('message'));
}

return $response->json();
```

```java
// Spring RestClient — ekvivalent
UserDto result = restClient.post()
    .uri("/users")
    .header(HttpHeaders.AUTHORIZATION, "Bearer " + token)
    .body(data)
    .retrieve()
    .onStatus(HttpStatusCode::isError, (req, res) -> {
        log.error("API error, status: {}", res.getStatusCode());
        throw new ApiException(readErrorMessage(res));
    })
    .body(UserDto.class);
```

---

## Testing

### Laravel — `Http::fake()`

```php
// Test — Http::fake() ilə mock
Http::fake([
    'api.example.com/users/1' => Http::response(['id' => 1, 'name' => 'Orkhan'], 200),
    'api.example.com/users/*' => Http::response(['message' => 'Not found'], 404),
]);

$user = $this->userService->getUser(1);
$this->assertEquals('Orkhan', $user['name']);

Http::assertSent(function ($request) {
    return $request->url() === 'https://api.example.com/users/1'
        && $request->method() === 'GET';
});
```

### Spring RestClient — `MockRestServiceServer`

```java
@SpringBootTest
class UserServiceTest {

    @Autowired
    private UserService userService;

    @Autowired
    private RestClient.Builder restClientBuilder;

    private MockRestServiceServer server;

    @BeforeEach
    void setUp() {
        server = MockRestServiceServer.bindTo(restClientBuilder).build();
    }

    @Test
    void getUser_success() {
        server.expect(requestTo("https://api.example.com/users/1"))
            .andExpect(method(HttpMethod.GET))
            .andRespond(withSuccess(
                """
                {"id": 1, "name": "Orkhan"}
                """,
                MediaType.APPLICATION_JSON
            ));

        UserDto user = userService.getUser(1L);

        assertThat(user.getName()).isEqualTo("Orkhan");
        server.verify();
    }

    @Test
    void getUser_notFound_throwsException() {
        server.expect(requestTo("https://api.example.com/users/999"))
            .andRespond(withStatus(HttpStatus.NOT_FOUND));

        assertThatThrownBy(() -> userService.getUser(999L))
            .isInstanceOf(UserNotFoundException.class);
    }
}
```

### Spring WebClient — `WebTestClient`

```java
@SpringBootTest(webEnvironment = SpringBootTest.WebEnvironment.RANDOM_PORT)
class ReactiveUserServiceTest {

    @Autowired
    private WebTestClient webTestClient;

    @Test
    void getUser_success() {
        webTestClient.get()
            .uri("/api/users/1")
            .exchange()
            .expectStatus().isOk()
            .expectBody(UserDto.class)
            .value(user -> assertThat(user.getName()).isEqualTo("Orkhan"));
    }
}
```

---

## RestTemplate → RestClient Miqrasiya Yolu

İki API-nin paraleli çox oxşardır — miqrasiya sadədir:

```java
// RestTemplate (köhnə)
UserDto user = restTemplate.getForObject(
    "/users/{id}", UserDto.class, id
);

// RestClient (yeni) — ekvivalent
UserDto user = restClient.get()
    .uri("/users/{id}", id)
    .retrieve()
    .body(UserDto.class);

// RestTemplate (köhnə)
ResponseEntity<UserDto> response = restTemplate.exchange(
    "/users/{id}", HttpMethod.GET, entity, UserDto.class, id
);

// RestClient (yeni) — ekvivalent
ResponseEntity<UserDto> response = restClient.get()
    .uri("/users/{id}", id)
    .retrieve()
    .toEntity(UserDto.class);
```

**Addım-addım miqrasiya:**
1. `RestTemplate` bean-ləri `RestClient.Builder` bean-ləri ilə əvəz et
2. `getForObject()` → `.get().uri().retrieve().body()`
3. `postForObject()` → `.post().uri().body().retrieve().body()`
4. `exchange()` → `.retrieve().toEntity()` ya da `.exchange()`
5. `ResponseErrorHandler` → `.onStatus()`
6. `MockRestServiceServer` — hər ikisi üçün eyni şəkildə işləyir

---

## Hansını Seçməli?

```
Proyekt WebFlux / reactive stack üzərindədir?
├── Bəli → WebClient
└── Xeyr → RestClient istifadə et (95% hal)

RestTemplate artıq mövcuddur?
├── Yeni feature → RestClient ilə yaz
└── Legacy kod → miqrasiya planla, tələsmə

Paralel HTTP sorğular lazımdır?
├── RestClient → CompletableFuture + paralel execution
└── WebClient → Mono.zip() / Flux.merge() (reaktiv app-da)
```

**Qısa qayda:**
- `RestClient` — **default seçim**, bütün yeni Spring MVC proyektlər üçün
- `WebClient` — yalnız WebFlux / fully-reactive stack
- `RestTemplate` — mövcud kodu qır mə, amma yeni kod yazma

---

## Praktik Tapşırıqlar

**Tapşırıq 1 — Xarici API inteqrasiyası:**
1. Hər hansı public API seç (məs: `https://jsonplaceholder.typicode.com`)
2. `RestClient` bean yarat, `baseUrl` + default `Accept: application/json` header
3. `GET /users`, `GET /users/{id}`, `POST /posts` endpoint-lərini implement et
4. `onStatus()` ilə 404 üçün custom exception throw et

**Tapşırıq 2 — Timeout + Retry:**
1. `RestClient` üçün `connectTimeout=3s`, `readTimeout=10s` konfiqurasiya et
2. `ClientHttpRequestInterceptor` ilə request/response logging əlavə et
3. `@Retryable` annotation ilə 5xx xətalarında 3 cəhd implement et

**Tapşırıq 3 — Test:**
1. `MockRestServiceServer` ilə UserService üçün unit test yaz
2. Success + 404 + 500 scenariolarını test et
3. Request assertion-larını (`assertSent`) doğrula

**Tapşırıq 4 — RestTemplate → RestClient miqrasiya:**
1. Mövcud `RestTemplate` kodu tap
2. `RestClient.Builder` inject et
3. Bütün metodları yeni fluent API-ya çevir
4. Test-lər hələ pass edir mi — yoxla

---

## Əlaqəli Mövzular

- `64-http-interface-and-restclient.md` — `@HttpExchange` ilə declarative HTTP client-lər
- `69-testcontainers-integration-testing.md` — Integration test-lərdə real HTTP server mock
- `73-spring-webflux-reactive.md` — WebClient-in ev mühiti olan reaktiv stack
- `79-openfeign-declarative-clients.md` — OpenFeign ilə interface-based HTTP client-lər
- `20-error-handling.md` — Global exception handling + HTTP client exception-ları
