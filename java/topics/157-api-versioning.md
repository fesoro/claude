# API Versioning — Geniş İzah

## Mündəricat
1. [API Versioning niyə lazımdır?](#api-versioning-niyə-lazımdır)
2. [URL Path Versioning](#url-path-versioning)
3. [Header Versioning](#header-versioning)
4. [Query Parameter Versioning](#query-parameter-versioning)
5. [Content Negotiation Versioning](#content-negotiation-versioning)
6. [Spring Boot-da versioning strategiyaları](#spring-boot-da-versioning-strategiyaları)
7. [İntervyu Sualları](#intervyu-sualları)

---

## API Versioning niyə lazımdır?

```
Problem:
  V1 API mövcuddur, 1000 client istifadə edir
  Breaking change lazımdır (field silinir, tip dəyişir)
  Bütün client-ləri eyni anda yeniləmək mümkün deyil

Həll — API Versioning:
  /api/v1/orders → Köhnə format (köhnə client-lər)
  /api/v2/orders → Yeni format (yeni client-lər)

Breaking vs Non-breaking changes:
  Non-breaking (versioning lazım deyil):
    ✅ Yeni optional field əlavə etmək
    ✅ Yeni endpoint əlavə etmək
    ✅ Response-a yeni data əlavə etmək

  Breaking (versioning lazım):
    ❌ Field silmək
    ❌ Field adını dəyişmək
    ❌ Field tipini dəyişmək (String → Integer)
    ❌ Endpoint-i silmək
    ❌ Request body strukturunu dəyişmək
```

---

## URL Path Versioning

```java
// ─── Ən geniş yayılmış üsul ─────────────────────────
// /api/v1/orders, /api/v2/orders

// V1 Controller
@RestController
@RequestMapping("/api/v1/orders")
public class OrderControllerV1 {

    @Autowired
    private OrderServiceV1 orderService;

    @GetMapping("/{id}")
    public ResponseEntity<OrderResponseV1> getOrder(@PathVariable Long id) {
        return ResponseEntity.ok(orderService.findById(id));
    }

    @PostMapping
    public ResponseEntity<OrderResponseV1> createOrder(
            @Valid @RequestBody OrderRequestV1 request) {
        OrderResponseV1 response = orderService.create(request);
        return ResponseEntity.created(
            URI.create("/api/v1/orders/" + response.id()))
            .body(response);
    }
}

// V2 Controller — breaking change: customerId → customer (nested object)
@RestController
@RequestMapping("/api/v2/orders")
public class OrderControllerV2 {

    @Autowired
    private OrderServiceV2 orderService;

    @GetMapping("/{id}")
    public ResponseEntity<OrderResponseV2> getOrder(@PathVariable Long id) {
        return ResponseEntity.ok(orderService.findById(id));
    }

    @PostMapping
    public ResponseEntity<OrderResponseV2> createOrder(
            @Valid @RequestBody OrderRequestV2 request) {
        OrderResponseV2 response = orderService.create(request);
        return ResponseEntity.created(
            URI.create("/api/v2/orders/" + response.id()))
            .body(response);
    }
}

// ─── V1 DTO ───────────────────────────────────────────
public record OrderResponseV1(
    Long id,
    String customerId,     // V1: sadə ID string
    String customerName,   // V1: ayrı sahə
    String status,
    BigDecimal totalAmount
) {}

// ─── V2 DTO ───────────────────────────────────────────
public record OrderResponseV2(
    Long id,
    CustomerSummary customer, // V2: nested object
    String status,
    BigDecimal totalAmount,
    List<OrderItemSummary> items // V2: items əlavə edilib
) {
    public record CustomerSummary(String id, String name, String email) {}
    public record OrderItemSummary(String productId, int qty, BigDecimal price) {}
}
```

---

## Header Versioning

```java
// ─── Custom header ilə versioning ────────────────────
// Headers: X-API-Version: 1  ya da  X-API-Version: 2

@RestController
@RequestMapping("/api/orders")
public class OrderControllerWithHeaderVersion {

    @GetMapping(headers = "X-API-Version=1")
    public ResponseEntity<OrderResponseV1> getOrderV1(@PathVariable Long id) {
        return ResponseEntity.ok(orderServiceV1.findById(id));
    }

    @GetMapping(headers = "X-API-Version=2")
    public ResponseEntity<OrderResponseV2> getOrderV2(@PathVariable Long id) {
        return ResponseEntity.ok(orderServiceV2.findById(id));
    }
}

// ─── Custom annotation ilə ────────────────────────────
@Target(ElementType.METHOD)
@Retention(RetentionPolicy.RUNTIME)
@GetMapping(headers = "X-API-Version=1")
public @interface GetMappingV1 {}

@Target(ElementType.METHOD)
@Retention(RetentionPolicy.RUNTIME)
@GetMapping(headers = "X-API-Version=2")
public @interface GetMappingV2 {}

// İstifadə:
@RestController
@RequestMapping("/api/orders")
public class CleanOrderController {

    @GetMappingV1("/{id}")
    public OrderResponseV1 getOrderV1(@PathVariable Long id) {
        return orderServiceV1.findById(id);
    }

    @GetMappingV2("/{id}")
    public OrderResponseV2 getOrderV2(@PathVariable Long id) {
        return orderServiceV2.findById(id);
    }
}
```

---

## Query Parameter Versioning

```java
// ─── ?version=1 ya da ?v=2 ────────────────────────────
// /api/orders?version=1  ya da /api/orders?version=2

@RestController
@RequestMapping("/api/orders")
public class OrderControllerWithParamVersion {

    @GetMapping(params = "version=1")
    public ResponseEntity<OrderResponseV1> getOrderV1(@PathVariable Long id) {
        return ResponseEntity.ok(orderServiceV1.findById(id));
    }

    @GetMapping(params = "version=2")
    public ResponseEntity<OrderResponseV2> getOrderV2(@PathVariable Long id) {
        return ResponseEntity.ok(orderServiceV2.findById(id));
    }
}

// ─── Default version ──────────────────────────────────
@RestController
@RequestMapping("/api/orders")
public class OrderControllerDefaultVersion {

    // Version göstərilməyibsə → V1 (default)
    @GetMapping
    public ResponseEntity<OrderResponseV1> getOrderDefault(@PathVariable Long id) {
        return getOrderV1(id);
    }

    @GetMapping(params = "version=1")
    public ResponseEntity<OrderResponseV1> getOrderV1(@PathVariable Long id) {
        return ResponseEntity.ok(orderServiceV1.findById(id));
    }

    @GetMapping(params = "version=2")
    public ResponseEntity<OrderResponseV2> getOrderV2(@PathVariable Long id) {
        return ResponseEntity.ok(orderServiceV2.findById(id));
    }
}
```

---

## Content Negotiation Versioning

```java
// ─── Accept header ilə versioning ─────────────────────
// Accept: application/vnd.example.v1+json
// Accept: application/vnd.example.v2+json

@RestController
@RequestMapping("/api/orders")
public class OrderControllerContentNegotiation {

    @GetMapping(
        value = "/{id}",
        produces = "application/vnd.example.v1+json"
    )
    public ResponseEntity<OrderResponseV1> getOrderV1(@PathVariable Long id) {
        return ResponseEntity.ok(orderServiceV1.findById(id));
    }

    @GetMapping(
        value = "/{id}",
        produces = "application/vnd.example.v2+json"
    )
    public ResponseEntity<OrderResponseV2> getOrderV2(@PathVariable Long id) {
        return ResponseEntity.ok(orderServiceV2.findById(id));
    }
}

// Spring MVC konfigurasyonu
@Configuration
public class WebMvcConfig implements WebMvcConfigurer {

    @Override
    public void configureContentNegotiation(ContentNegotiationConfigurer configurer) {
        configurer
            .favorParameter(false)
            .favorPathExtension(false)
            .ignoreAcceptHeader(false)
            .defaultContentType(MediaType.APPLICATION_JSON)
            .mediaType("v1", MediaType.parseMediaType("application/vnd.example.v1+json"))
            .mediaType("v2", MediaType.parseMediaType("application/vnd.example.v2+json"));
    }
}
```

---

## Spring Boot-da versioning strategiyaları

```java
// ─── Ən yaxşı praktika: Paket əsasında ───────────────
// com.example.api.v1
// com.example.api.v2

// Paket strukturu:
// ├── api/
// │   ├── v1/
// │   │   ├── OrderControllerV1.java
// │   │   ├── dto/
// │   │   │   ├── OrderRequestV1.java
// │   │   │   └── OrderResponseV1.java
// │   │   └── service/
// │   │       └── OrderServiceV1.java
// │   └── v2/
// │       ├── OrderControllerV2.java
// │       ├── dto/
// │       │   ├── OrderRequestV2.java
// │       │   └── OrderResponseV2.java
// │       └── service/
// │           └── OrderServiceV2.java

// ─── Service reuse strategiyası ──────────────────────
// V2 service V1 service-i genişləndirir
@Service
public class OrderServiceV2 extends OrderServiceV1 {

    @Override
    public OrderResponseV2 findById(Long id) {
        // V1 məntiqini yenidən istifadə et + yeni field-lər əlavə et
        Order order = orderRepository.findById(id).orElseThrow();
        return mapToV2Response(order);
    }

    // V1-dən fərqli metod
    public OrderResponseV2 createWithItems(OrderRequestV2 request) {
        // Yeni V2 məntiq
    }
}

// ─── Mapper-based versioning ──────────────────────────
@Component
public class OrderVersionMapper {

    public OrderResponseV1 toV1Response(Order order) {
        return new OrderResponseV1(
            order.getId(),
            order.getCustomer().getId(),
            order.getCustomer().getFullName(),
            order.getStatus().name(),
            order.getTotalAmount()
        );
    }

    public OrderResponseV2 toV2Response(Order order) {
        return new OrderResponseV2(
            order.getId(),
            new OrderResponseV2.CustomerSummary(
                order.getCustomer().getId(),
                order.getCustomer().getFullName(),
                order.getCustomer().getEmail()
            ),
            order.getStatus().name(),
            order.getTotalAmount(),
            order.getItems().stream()
                .map(item -> new OrderResponseV2.OrderItemSummary(
                    item.getProductId(),
                    item.getQuantity(),
                    item.getUnitPrice()
                ))
                .collect(Collectors.toList())
        );
    }
}

// ─── Version deprecation ──────────────────────────────
@GetMapping
@Deprecated
@Operation(deprecated = true, description = "V1 deprecated — V2 istifadə edin")
public ResponseEntity<OrderResponseV1> getOrderV1(@PathVariable Long id) {
    // Deprecation header əlavə et
    return ResponseEntity.ok()
        .header("Deprecation", "true")
        .header("Sunset", "2027-01-01")
        .header("Link", "</api/v2/orders/" + id + ">; rel=\"successor-version\"")
        .body(orderServiceV1.findById(id));
}

// ─── Version routing interceptor ─────────────────────
@Component
public class ApiVersionInterceptor implements HandlerInterceptor {

    @Override
    public boolean preHandle(HttpServletRequest request,
                              HttpServletResponse response,
                              Object handler) throws Exception {
        String version = request.getHeader("X-API-Version");
        if (version == null) {
            version = "1"; // Default
        }

        // Request attribute-a yaz
        request.setAttribute("apiVersion", version);

        // Log
        log.info("API Version: {} | URI: {}", version, request.getRequestURI());

        return true;
    }
}
```

---

## İntervyu Sualları

### 1. API versioning niyə lazımdır?
**Cavab:** Mövcud client-ləri pozmadan API-ni inkişaf etdirmək üçün. Breaking change — field silmək, tip dəyişmək, endpoint silmək — köhnə client-ləri qırır. Versioning ilə köhnə client V1-i, yeni client V2-ni istifadə edir. Parallel versiyalar müəyyən müddət (deprecation period) saxlanılır, sonra köhnə versiya sunset olunur.

### 2. URL Path vs Header vs Content Negotiation versioning?
**Cavab:** **URL Path** (`/api/v1/orders`) — ən aydın, cache-friendly, test edilməsi asan. Ən geniş yayılmış. **Header** (`X-API-Version: 2`) — URL-i qoruyur, amma test çətin (curl-da extra flag). **Content Negotiation** (`Accept: application/vnd.example.v2+json`) — RESTful-ə daha uyğun, amma mürəkkəb. Praktikada URL path tövsiyə edilir — ən sadə, ən aydın.

### 3. Breaking vs non-breaking change nədir?
**Cavab:** **Non-breaking** (versioning lazım deyil): yeni optional field əlavə, yeni endpoint əlavə, response-a əlavə data. **Breaking**: field silmək, field adı/tipi dəyişmək, required field əlavə, endpoint silmək, response strukturunu dəyişmək. Breaking change-lər yeni versiya tələb edir — köhnə versiya mövcud client-lər üçün qorunur.

### 4. Deprecation necə idarə edilir?
**Cavab:** (1) `Deprecation: true` HTTP header. (2) `Sunset: 2027-01-01` — versiya nə vaxt ləğv olunur. (3) `Link: </api/v2/...>; rel="successor-version"` — yeni versiyanın linki. (4) Changelog/dokumentasiya bildirişi. (5) Monitoring — köhnə versiyaya traffic var mı? Sunset tarixindən 6-12 ay əvvəl bildiriş göndərilir. Sunset tarixindən sonra 410 Gone qaytarılır.

### 5. Bütün controller-ləri duplicate etmək düzgündürmü?
**Cavab:** Tam duplicate pis. Yaxşı praktika: (1) **Paylaşılan domain service** — biznes məntiqi yenidən yazılmır; controller → service → entity. (2) **Mapper pattern** — OrderVersionMapper entity-ni V1 ya V2 DTO-ya çevirir. (3) **Inheritance/delegation** — V2Service V1Service-dən miras ya delegation. (4) Yalnız fərqlənən hissə (DTO mapping) versiyalanır. Domain logic bir yerdə qalır.

*Son yenilənmə: 2026-04-10*
