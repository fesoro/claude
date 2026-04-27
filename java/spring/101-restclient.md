# Spring RestClient — Modern HTTP Client (Middle)

> **Seviyye:** Middle ⭐⭐

## İcmal

**RestClient** — Spring 6.1 (Boot 3.2+) ilə gəlmiş müasir, fluent, sinxron HTTP client. `RestTemplate`-in yerini tutmaq üçün nəzərdə tutulub. `WebClient` ilə eyni infrastruktur üzərindədir, lakin blocking (sinxron) işləyir. Reactive tətbiqlər üçün deyil — classical servlet-based Spring MVC tətbiqlərindədir.

`RestTemplate` hələ də çalışır, silinməyib, lakin `RestClient` artıq tövsiyə olunan yoldur.

---

## Niyə Vacibdir

```
PHP Laravel HTTP Facade:
  Http::withToken($token)->post('/users', $data)

Java RestClient (eyni fluency):
  restClient.post().uri("/users").header("Authorization", "Bearer " + token).body(dto).retrieve().body(UserResponse.class)
```

`RestTemplate` ötmüş builder-siz API-dir — method adları (`getForObject`, `postForEntity`) məntiqi axına uyğun deyil. `RestClient` isə Laravel `Http` Facade kimi oxunur — method chaining ilə left-to-right.

---

## Əsas Anlayışlar

- **RestClient** — Spring 6.1+, sinxron (blocking) HTTP client
- **RestTemplate** — köhnə API, hələ çalışır, yeni proyektdə tövsiyə edilmir
- **WebClient** — reactive (non-blocking), `WebFlux` üçün; RestClient ilə eyni infrastruktur
- **retrieve()** — response body-ni birbaşa oxumaq üçün
- **exchange()** — tam kontrol (request + response) lazım olduqda
- **ClientHttpRequestInterceptor** — hər sorğuya logging, auth, retry əlavə etmək üçün
- **HttpServiceProxyFactory** — `@HttpExchange` interface-ləri üçün proxy yaradan sinif (→ 102)

---

## Praktik Baxış

**Ne vaxt RestClient:**
- Mövcud Spring MVC / servlet-based tətbiq
- Sinxron external API çağırışları
- RestTemplate-dən miqrasiya

**Ne vaxt WebClient:**
- Reactive tətbiq (`WebFlux`)
- Non-blocking, yüksək concurrency lazımdır

**Ne vaxt RestTemplate:**
- Köhnə kod bazası, dəyişdirilməsi risklidir
- Yeni kodda istifadə etmə

**Common mistakes:**
- Her sorğuda `RestClient.create()` çağırmaq — bean kimi bir dəfə yaratmaq lazımdır
- `onStatus` olmadan xətaları idarə etmək — default olaraq `RestClientResponseException` atır, amma domain-specific exception olmur
- Timeout konfiqurasiya etməmək — default timeout yoxdur, connection/read timeout mütləq təyin et

---

## Nümunələr

### Ümumi Nümunə

Xarici ödəniş şirkəti (Stripe kimi) API-si çağırmaq üçün `RestClient` istifadə edilir. Bu client:
1. `@Configuration`-da singleton kimi yaradılır
2. Base URL və default header-lar konfiqurasiya edilir
3. Hər servis sadəcə inject edib işlədir

### Kod Nümunəsi

**1. RestClient yaratma yolları**

```java
// Sadə — base URL yoxdur
RestClient client = RestClient.create();

// Base URL ilə
RestClient client = RestClient.create("https://api.example.com");

// Builder ilə tam konfiqurasiya
RestClient client = RestClient.builder()
    .baseUrl("https://api.example.com")
    .defaultHeader("X-Api-Key", apiKey)
    .defaultHeader("Accept", "application/json")
    .requestInterceptor(new LoggingInterceptor())
    .build();
```

**2. @Configuration-da @Bean**

```java
@Configuration
public class HttpClientConfig {

    @Value("${payment.api.key}")
    private String apiKey;

    @Value("${payment.api.url}")
    private String baseUrl;

    @Bean
    public RestClient paymentRestClient() {
        // HttpComponentsClientHttpRequestFactory ilə timeout
        var factory = new HttpComponentsClientHttpRequestFactory();
        factory.setConnectTimeout(Duration.ofSeconds(5));
        factory.setReadTimeout(Duration.ofSeconds(10));

        return RestClient.builder()
            .baseUrl(baseUrl)
            .defaultHeader("X-Api-Key", apiKey)
            .defaultHeader("Accept", MediaType.APPLICATION_JSON_VALUE)
            .requestFactory(factory)
            .build();
    }
}
```

**3. GET sorğusu**

```java
@Service
@RequiredArgsConstructor
public class UserApiService {

    private final RestClient restClient;

    // Birbaşa body
    public User getUser(Long id) {
        return restClient.get()
            .uri("/users/{id}", id)
            .retrieve()
            .body(User.class);
    }

    // ResponseEntity — status + headers + body
    public ResponseEntity<User> getUserWithMeta(Long id) {
        return restClient.get()
            .uri("/users/{id}", id)
            .retrieve()
            .toEntity(User.class);
    }
}
```

**4. Query params — URI Builder**

```java
public List<User> searchUsers(String query, int page, int size) {
    return restClient.get()
        .uri(uriBuilder -> uriBuilder
            .path("/users/search")
            .queryParam("q", query)
            .queryParam("page", page)
            .queryParam("size", size)
            .build())
        .retrieve()
        .body(new ParameterizedTypeReference<List<User>>() {});
}
```

**5. POST sorğusu**

```java
public UserResponse createUser(CreateUserDto dto) {
    return restClient.post()
        .uri("/users")
        .contentType(MediaType.APPLICATION_JSON)
        .body(dto)                           // Jackson avtomatik serialize edir
        .retrieve()
        .body(UserResponse.class);
}
```

**6. PUT / PATCH / DELETE**

```java
// PUT
public UserResponse updateUser(Long id, UpdateUserDto dto) {
    return restClient.put()
        .uri("/users/{id}", id)
        .body(dto)
        .retrieve()
        .body(UserResponse.class);
}

// PATCH
public void patchUser(Long id, Map<String, Object> fields) {
    restClient.patch()
        .uri("/users/{id}", id)
        .body(fields)
        .retrieve()
        .toBodilessEntity();  // body lazım deyilsə
}

// DELETE
public void deleteUser(Long id) {
    restClient.delete()
        .uri("/users/{id}", id)
        .retrieve()
        .toBodilessEntity();
}
```

**7. Xəta idarəsi — onStatus**

```java
public User getUser(Long id) {
    return restClient.get()
        .uri("/users/{id}", id)
        .retrieve()
        .onStatus(HttpStatusCode::is4xxClientError, (request, response) -> {
            if (response.getStatusCode() == HttpStatus.NOT_FOUND) {
                throw new UserNotFoundException("User not found: " + id);
            }
            throw new BadRequestException("Client error: " + response.getStatusCode());
        })
        .onStatus(HttpStatusCode::is5xxServerError, (request, response) -> {
            throw new ExternalApiException("Payment service error: " + response.getStatusCode());
        })
        .body(User.class);
}

// onStatus olmadan default davranış:
// 4xx/5xx → RestClientResponseException atır
// Status + body capture olunur
```

**8. exchange() — tam kontrol**

```java
// exchange() — response header-larını oxumaq lazımdırsa,
// və ya şərtli logic tələb edilirsə
public Optional<User> getUserSafe(Long id) {
    return restClient.get()
        .uri("/users/{id}", id)
        .exchange((request, response) -> {
            if (response.getStatusCode() == HttpStatus.NOT_FOUND) {
                return Optional.empty();
            }
            if (response.getStatusCode().is2xxSuccessful()) {
                return Optional.of(response.bodyTo(User.class));
            }
            throw new ExternalApiException("Unexpected: " + response.getStatusCode());
        });
}
```

`retrieve()` çox hallarda kifayətdir. `exchange()` yalnız: response header-ları oxumaq, status-a görə fərqli type decode etmək, yaxud custom streaming lazım olduqda istifadə et.

**9. Interceptor — Logging və Auth**

```java
@Component
public class LoggingInterceptor implements ClientHttpRequestInterceptor {

    private static final Logger log = LoggerFactory.getLogger(LoggingInterceptor.class);

    @Override
    public ClientHttpResponse intercept(
            HttpRequest request, byte[] body,
            ClientHttpRequestExecution execution) throws IOException {

        log.info("HTTP {} {}", request.getMethod(), request.getURI());

        ClientHttpResponse response = execution.execute(request, body);

        log.info("HTTP {} → {}", request.getURI(), response.getStatusCode());
        return response;
    }
}

// @Bean-ə qoşmaq:
RestClient.builder()
    .requestInterceptor(new LoggingInterceptor())
    .build();
```

**10. ObjectMapper customization**

```java
@Bean
public RestClient restClient(ObjectMapper objectMapper) {
    // Spring Boot artıq ObjectMapper bean-ı konfiqurasiya edir
    // Jackson2ObjectMapperBuilder ilə extend etmək lazımdırsa:
    MappingJackson2HttpMessageConverter converter =
        new MappingJackson2HttpMessageConverter(objectMapper);

    return RestClient.builder()
        .baseUrl(baseUrl)
        .messageConverters(converters -> {
            converters.removeIf(c -> c instanceof MappingJackson2HttpMessageConverter);
            converters.add(0, converter);
        })
        .build();
}
```

**PHP Laravel ilə müqayisə**

```php
// Laravel HTTP Facade
$response = Http::withToken($token)
    ->post('/users', $data);

// Xəta handle:
$response->throw();  // 4xx/5xx → exception
$response->throwIf($condition);
```

```java
// Spring RestClient — eyni fluency
UserResponse response = restClient.post()
    .uri("/users")
    .header("Authorization", "Bearer " + token)
    .body(data)
    .retrieve()
    .onStatus(HttpStatusCode::isError, (req, res) -> {
        throw new ExternalApiException(res.getStatusCode().toString());
    })
    .body(UserResponse.class);
```

Fərq: Laravel `Http::fake()` test üçün; Java-da `MockRestServiceServer` istifadə olunur (→ 103).

---

## Praktik Tapşırıqlar

**Tapşırıq 1 — Basic GET/POST**
1. `https://jsonplaceholder.typicode.com` API-sinə `RestClient` ilə bağlan
2. `GET /posts/{id}` — `Post` record-una map et
3. `POST /posts` — yeni post yarat, cavabı print et
4. `GET /posts?userId=1` — query param ilə filter et, `List<Post>` qaytar

**Tapşırıq 2 — Error handling**
1. Mövcud olmayan ID üçün `GET /posts/9999` → 404 cavabı
2. `onStatus` ilə `PostNotFoundException` at
3. Testi yaz: `MockRestServiceServer` (→ 103-faylda ətraflı)

**Tapşırıq 3 — Production-ready bean**
1. `@Configuration`-da `RestClient` bean yarat
2. Base URL, default headers `application.yml`-dən oxu
3. Connect timeout 5s, read timeout 10s konfiqurasiya et
4. Logging interceptor əlavə et
5. `@Service`-də inject edib istifadə et

---

## Əlaqəli Mövzular

- [102 — @HttpExchange](102-httpexchange.md) — Deklarativ HTTP client, RestClient üzərindədir
- [103 — @RestClientTest](103-rest-client-test.md) — RestClient-i test etmək
- [84 — WebFlux/WebClient](84-webflux.md) — Reactive HTTP client
- [72 — Spring Retry](72-retry.md) — `@Retryable` ilə RestClient retry
- [73 — Rate Limiting](73-rate-limiting-bucket4j.md) — Outbound rate limit
