# @RestClientTest — HTTP Client Test (Senior)

> **Seviyye:** Senior ⭐⭐⭐

## İcmal

**@RestClientTest** — Spring Boot test slice annotation-u. Yalnız HTTP client komponentlərini (RestClient, RestTemplate, ObjectMapper, MockRestServiceServer) yükləyir. Tam application context yükləmir — sürətli, izolə olunmuş test.

Xarici API çağıran `@Service` sinifini test edərkən: real HTTP server qalxmır, `MockRestServiceServer` ilə HTTP sorğu/cavablar simulyasiya olunur.

---

## Niyə Vacibdir

```
@SpringBootTest ilə xarici HTTP client test etmək:
  - Bütün application context yüklənir (DB, Security, Cache...)
  - Hər test: 5-10 saniyə
  - External API çağırışları real şəbəkə sorğusu edir (ya mock lazım)

@RestClientTest ilə:
  - Yalnız HTTP client komponentləri yüklənir
  - Hər test: < 1 saniyə
  - MockRestServiceServer real şəbəkə olmadan cavab simulyasiya edir
```

PHP Laravel-də `Http::fake()` nə edirsə, Java-da `MockRestServiceServer` onu edir. Hər ikisi: request-i ələ keçirir, saxta cavab qaytarır, real şəbəkə sorğusu olmur.

---

## Əsas Anlayışlar

- **@RestClientTest** — test slice: sadəcə `@Service` (HTTP client olan), `ObjectMapper`, `MockRestServiceServer` yüklənir
- **MockRestServiceServer** — HTTP sorğuları ələ keçirən in-memory mock server; real port açmır
- **server.expect()** — hansı URL-ə, hansı metod, hansı header ilə sorğu gözlənilir
- **server.andRespond()** — mock cavab: body, status, headers
- **server.verify()** — test sonunda bütün gözlənilən sorğuların həqiqətən edilib-edilmədiyini yoxlayır
- **WireMock** — daha güclü alternativ: delays, stateful behaviour, scenario-based; lakin heavier setup

---

## Praktik Baxış

**@RestClientTest istifadə et:**
- Xarici API çağıran servis layeri test etmək
- Mock response-larla müxtəlif ssenariləri yoxlamaq
- Sürətli, CI-friendly unit test

**@SpringBootTest istifadə et:**
- End-to-end integration test lazımdır
- HTTP client davranışı digər bean-larla birlikdə yoxlanmalıdır

**WireMock istifadə et:**
- Gecikmə (delay) simulyasiyası lazımdır
- Stateful scenarios (birinci sorğu 503, ikinci 200)
- Real HTTP server davranışı tələb olunur
- Testcontainers ilə birlikdə

**Common mistakes:**
- Test-dəki service class-ını `@RestClientTest(value = ...)` ilə göstərməmək — context yüklənmir
- `server.verify()` unutmaq — expect edilmiş sorğular edilməsə test keçər
- Response body üçün string hardcode etmək — classpath fixture faylı işlət
- `@MockBean` lazım olmayan digər bean-ları mock etmək — slice bunu avtomatik idarə edir

---

## Nümunələr

### Ümumi Nümunə

`WeatherService` xarici hava proqnozu API-sinə `RestClient` ilə müraciət edir. `@RestClientTest` ilə:
1. Mock server müsbət cavab qaytarır — `WeatherService` düzgün parse edir
2. Mock server 404 qaytarır — `WeatherService` `CityNotFoundException` atır
3. Mock server 500 qaytarır — `WeatherService` `WeatherApiException` atır
4. `server.verify()` ilə sorğunun həqiqətən edildiyi yoxlanır

### Kod Nümunəsi

**1. Test ediləcək servis**

```java
@Service
@RequiredArgsConstructor
public class WeatherService {

    private final RestClient restClient;

    public WeatherResponse getWeather(String city) {
        return restClient.get()
            .uri(uriBuilder -> uriBuilder
                .path("/weather")
                .queryParam("city", city)
                .build())
            .retrieve()
            .onStatus(status -> status == HttpStatus.NOT_FOUND, (req, res) -> {
                throw new CityNotFoundException("City not found: " + city);
            })
            .onStatus(HttpStatusCode::is5xxServerError, (req, res) -> {
                throw new WeatherApiException("Weather API error: " + res.getStatusCode());
            })
            .body(WeatherResponse.class);
    }
}
```

**2. @RestClientTest — müsbət ssenari**

```java
@RestClientTest(WeatherService.class)  // yalnız bu servis yüklənir
class WeatherServiceTest {

    @Autowired
    private WeatherService weatherService;

    @Autowired
    private MockRestServiceServer server;

    @Autowired
    private ObjectMapper objectMapper;

    @Test
    void getWeather_success() throws Exception {
        // Mock cavab hazırla
        WeatherResponse mockResponse = new WeatherResponse("Baku", 28.5, "Sunny");
        String mockJson = objectMapper.writeValueAsString(mockResponse);

        // Sorğu gözləntiləri qur
        server.expect(requestTo(containsString("/weather?city=Baku")))
              .andExpect(method(HttpMethod.GET))
              .andRespond(withSuccess(mockJson, MediaType.APPLICATION_JSON));

        // Metod çağır
        WeatherResponse result = weatherService.getWeather("Baku");

        // Assert
        assertThat(result.city()).isEqualTo("Baku");
        assertThat(result.temperature()).isEqualTo(28.5);

        // Bütün gözlənilən sorğuların edilib-edilmədiyini yoxla
        server.verify();
    }
}
```

**3. Xəta ssenariləri**

```java
@Test
void getWeather_cityNotFound_throwsCityNotFoundException() {
    server.expect(requestTo(containsString("/weather")))
          .andExpect(method(HttpMethod.GET))
          .andRespond(withStatus(HttpStatus.NOT_FOUND));

    assertThatThrownBy(() -> weatherService.getWeather("Atlantis"))
        .isInstanceOf(CityNotFoundException.class)
        .hasMessageContaining("Atlantis");

    server.verify();
}

@Test
void getWeather_serverError_throwsWeatherApiException() {
    server.expect(requestTo(containsString("/weather")))
          .andRespond(withServerError());  // 500 Internal Server Error

    assertThatThrownBy(() -> weatherService.getWeather("Baku"))
        .isInstanceOf(WeatherApiException.class);

    server.verify();
}

@Test
void getWeather_serviceUnavailable() {
    server.expect(requestTo(containsString("/weather")))
          .andRespond(withStatus(HttpStatus.SERVICE_UNAVAILABLE));

    assertThatThrownBy(() -> weatherService.getWeather("Baku"))
        .isInstanceOf(WeatherApiException.class);
}
```

**4. Request body yoxlamaq**

```java
@Test
void createPayment_sendsCorrectBody() throws Exception {
    CreatePaymentRequest request = new CreatePaymentRequest(100L, "AZN", "user-42");
    PaymentResponse mockResponse = new PaymentResponse("pay-001", "PENDING");

    server.expect(requestTo("/v1/payments"))
          .andExpect(method(HttpMethod.POST))
          .andExpect(content().contentType(MediaType.APPLICATION_JSON))
          .andExpect(jsonPath("$.amount").value(100))   // request body yoxla
          .andExpect(jsonPath("$.currency").value("AZN"))
          .andExpect(header("X-Api-Key", "test-key"))   // header yoxla
          .andRespond(withSuccess(
              objectMapper.writeValueAsString(mockResponse),
              MediaType.APPLICATION_JSON
          ));

    PaymentResponse result = paymentService.createPayment(request);

    assertThat(result.id()).isEqualTo("pay-001");
    server.verify();
}
```

**5. Classpath fixture faylları**

```
src/test/resources/
  fixtures/
    weather-success.json
    payment-created.json
    user-not-found.json
```

```json
// src/test/resources/fixtures/weather-success.json
{
  "city": "Baku",
  "temperature": 28.5,
  "condition": "Sunny"
}
```

```java
@Test
void getWeather_fromFixtureFile() {
    // Classpath-dən JSON fixture oxu
    server.expect(requestTo(containsString("/weather")))
          .andRespond(withSuccess(
              new ClassPathResource("fixtures/weather-success.json"),
              MediaType.APPLICATION_JSON
          ));

    WeatherResponse result = weatherService.getWeather("Baku");
    assertThat(result.city()).isEqualTo("Baku");
    server.verify();
}
```

**6. @HttpExchange interface-ni test etmək**

```java
// @HttpExchange interface-i
@HttpExchange("/v1/payments")
public interface PaymentClient {
    @GetExchange("/{id}")
    Payment getPayment(@PathVariable Long id);
}

// Test — MockRestServiceServer ilə eyni approach
@RestClientTest(PaymentClientConfig.class)  // @Bean-lər olan @Configuration
class PaymentClientTest {

    @Autowired
    private PaymentClient paymentClient;

    @Autowired
    private MockRestServiceServer server;

    @Autowired
    private ObjectMapper objectMapper;

    @Test
    void getPayment_success() throws Exception {
        Payment mockPayment = new Payment(1L, 500L, "AZN", "COMPLETED");

        server.expect(requestTo(containsString("/v1/payments/1")))
              .andExpect(method(HttpMethod.GET))
              .andRespond(withSuccess(
                  objectMapper.writeValueAsString(mockPayment),
                  MediaType.APPLICATION_JSON
              ));

        Payment result = paymentClient.getPayment(1L);

        assertThat(result.id()).isEqualTo(1L);
        assertThat(result.status()).isEqualTo("COMPLETED");
        server.verify();
    }
}
```

**7. Ardıcıl sorğular — sequence**

```java
@Test
void retryOn503_thenSuccess() {
    String successJson = """{"city":"Baku","temperature":28.5}""";

    // Birinci sorğu — 503
    server.expect(ExpectedCount.once(), requestTo(containsString("/weather")))
          .andRespond(withStatus(HttpStatus.SERVICE_UNAVAILABLE));

    // İkinci sorğu — 200
    server.expect(ExpectedCount.once(), requestTo(containsString("/weather")))
          .andRespond(withSuccess(successJson, MediaType.APPLICATION_JSON));

    // Retry mexanizmi varsa (Spring Retry ilə), ikinci sorğu uğurlu olur
    WeatherResponse result = weatherService.getWeather("Baku");
    assertThat(result.city()).isEqualTo("Baku");
    server.verify();
}
```

**8. Test base class — reusable pattern**

```java
// Base class — bütün HTTP client testlər üçün
@RestClientTest
abstract class BaseRestClientTest {

    @Autowired
    protected MockRestServiceServer server;

    @Autowired
    protected ObjectMapper objectMapper;

    @AfterEach
    void verifyServer() {
        server.verify();  // hər testdən sonra avtomatik verify
    }

    protected String toJson(Object obj) {
        try {
            return objectMapper.writeValueAsString(obj);
        } catch (JsonProcessingException e) {
            throw new RuntimeException(e);
        }
    }

    protected ResponseActions mockGet(String urlPattern) {
        return server.expect(requestTo(containsString(urlPattern)))
                     .andExpect(method(HttpMethod.GET));
    }

    protected ResponseActions mockPost(String urlPattern) {
        return server.expect(requestTo(containsString(urlPattern)))
                     .andExpect(method(HttpMethod.POST));
    }
}

// İstifadə
@RestClientTest(WeatherService.class)
class WeatherServiceTest extends BaseRestClientTest {

    @Autowired
    private WeatherService weatherService;

    @Test
    void getWeather_success() {
        mockGet("/weather")
            .andRespond(withSuccess(toJson(new WeatherResponse("Baku", 28.5, "Sunny")),
                                    MediaType.APPLICATION_JSON));

        WeatherResponse result = weatherService.getWeather("Baku");
        assertThat(result.city()).isEqualTo("Baku");
        // server.verify() — @AfterEach-də avtomatik çağrılır
    }
}
```

**9. PHP Laravel Http::fake() ilə müqayisə**

```php
// Laravel — Http::fake() ilə mock
public function test_get_weather_success(): void
{
    Http::fake([
        'api.weather.com/weather*' => Http::response([
            'city'        => 'Baku',
            'temperature' => 28.5,
            'condition'   => 'Sunny',
        ], 200),
    ]);

    $result = $this->weatherService->getWeather('Baku');

    $this->assertEquals('Baku', $result['city']);
    Http::assertSent(fn($req) => str_contains($req->url(), 'city=Baku'));
}
```

```java
// Spring — MockRestServiceServer ilə eyni konsept
@Test
void getWeather_success() {
    server.expect(requestTo(containsString("/weather?city=Baku")))
          .andRespond(withSuccess("""
              {"city":"Baku","temperature":28.5,"condition":"Sunny"}
              """, MediaType.APPLICATION_JSON));

    WeatherResponse result = weatherService.getWeather("Baku");

    assertThat(result.city()).isEqualTo("Baku");
    server.verify();  // Http::assertSent qarşılığı
}
```

| Laravel | Spring |
|---------|--------|
| `Http::fake([url => response])` | `server.expect(requestTo(url)).andRespond(...)` |
| `Http::assertSent(fn)` | `server.verify()` + `.andExpect(...)` |
| `Http::response(data, status)` | `withSuccess(json, mediaType)` |
| `Http::response([], 404)` | `withStatus(HttpStatus.NOT_FOUND)` |
| `Http::response([], 500)` | `withServerError()` |

---

## Praktik Tapşırıqlar

**Tapşırıq 1 — Əsas test yazma**
1. `GithubService` yaz: `RestClient` ilə `https://api.github.com/users/{username}` çağırır
2. `@RestClientTest(GithubService.class)` yazan test faylı aç
3. Uğurlu cavab üçün test: 200 + JSON fixture faylı
4. 404 üçün test: `UserNotFoundException` atılır
5. `server.verify()` hər testdə çağrıldığını yoxla

**Tapşırıq 2 — Request yoxlama**
1. `createGist()` metodu `POST /gists` sorğusu edir
2. Test: request body-nin düzgün JSON olduğunu `jsonPath` ilə yoxla
3. Test: `Authorization` header-ının göndərildiyini yoxla

**Tapşırıq 3 — @HttpExchange test**
1. `GithubClient` interface-i `@HttpExchange` ilə yaz
2. `@Configuration`-da `@Bean` yarat
3. `@RestClientTest(GithubClientConfig.class)` ilə test yaz
4. Fixture fayldan mock response oxut

---

## Əlaqəli Mövzular

- [101 — RestClient](101-restclient.md) — test edilən HTTP client
- [102 — @HttpExchange](102-httpexchange.md) — deklarativ HTTP client testi
- [85 — @SpringBootTest](85-boot-test.md) — tam context integration test
- [86 — @WebMvcTest](86-webmvctest.md) — controller layer test
- [88 — Testcontainers](88-testcontainers.md) — real infrastruktur ilə integration test
