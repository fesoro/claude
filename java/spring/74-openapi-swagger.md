# 74 — OpenAPI & Swagger — Geniş İzah

> **Seviyye:** Intermediate ⭐⭐


## Mündəricat
1. [OpenAPI nədir?](#openapi-nədir)
2. [SpringDoc OpenAPI konfiqurasiyası](#springdoc-openapi-konfiqurasiyası)
3. [Controller annotasiyaları](#controller-annotasiyaları)
4. [DTO annotasiyaları](#dto-annotasiyaları)
5. [Security dokumentasiyası](#security-dokumentasiyası)
6. [Kod generasiyası](#kod-generasiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## OpenAPI nədir?

**OpenAPI** (əvvəlki adı Swagger) — RESTful API-ləri təsvir etmək üçün standart. JSON/YAML formatında.

```
OpenAPI spec faydaları:
  ├── API dokumentasiyası — developer portal
  ├── Client kod generasiyası — Java, Python, TypeScript, vb.
  ├── Server stub generasiyası
  ├── API testing (Postman, Insomnia import)
  └── Contract-first development

Spring Boot ilə:
  Kod → @Operation, @Parameter annotation-lar → OpenAPI spec (JSON/YAML) → Swagger UI
```

```xml
<!-- pom.xml — SpringDoc OpenAPI 3 -->
<dependency>
    <groupId>org.springdoc</groupId>
    <artifactId>springdoc-openapi-starter-webmvc-ui</artifactId>
    <version>2.3.0</version>
</dependency>
```

---

## SpringDoc OpenAPI konfiqurasiyası

```yaml
# application.yml
springdoc:
  api-docs:
    enabled: true
    path: /api-docs           # JSON spec URL
  swagger-ui:
    enabled: true
    path: /swagger-ui.html    # Swagger UI URL
    operations-sorter: alpha  # Endpoint-ləri əlifba sırasına görə sırala
    tags-sorter: alpha
    try-it-out-enabled: true  # "Try it out" düyməsi
    display-request-duration: true
  packages-to-scan: com.example.api
  paths-to-match: /api/**
```

```java
// ─── OpenAPI konfigurasyonu ───────────────────────────
@Configuration
public class OpenApiConfig {

    @Bean
    public OpenAPI customOpenAPI() {
        return new OpenAPI()
            .info(new Info()
                .title("Order Management API")
                .version("2.0.0")
                .description("""
                    ## Order Management Service API
                    
                    Bu API sifariş idarəetmə sisteminin REST interfeysidir.
                    
                    ### Authentication
                    JWT Bearer token istifadə edin.
                    
                    ### Rate Limiting
                    - Standard: 100 req/min
                    - Premium: 1000 req/min
                    """)
                .contact(new Contact()
                    .name("API Team")
                    .email("api@example.com")
                    .url("https://example.com"))
                .license(new License()
                    .name("MIT")
                    .url("https://opensource.org/licenses/MIT"))
                .termsOfService("https://example.com/terms"))
            .servers(List.of(
                new Server().url("https://api.example.com").description("Production"),
                new Server().url("https://staging-api.example.com").description("Staging"),
                new Server().url("http://localhost:8080").description("Local Development")
            ))
            .addSecurityItem(new SecurityRequirement().addList("bearerAuth"))
            .components(new Components()
                .addSecuritySchemes("bearerAuth", new SecurityScheme()
                    .type(SecurityScheme.Type.HTTP)
                    .scheme("bearer")
                    .bearerFormat("JWT")
                    .name("bearerAuth")
                    .description("JWT token. Format: Bearer {token}")
                )
                .addSecuritySchemes("apiKey", new SecurityScheme()
                    .type(SecurityScheme.Type.APIKEY)
                    .in(SecurityScheme.In.HEADER)
                    .name("X-API-Key")
                )
            );
    }
}
```

---

## Controller annotasiyaları

```java
@Tag(name = "Orders", description = "Sifariş idarəetmə API-si")
@RestController
@RequestMapping("/api/v2/orders")
public class OrderController {

    // ─── GET all orders ───────────────────────────────
    @Operation(
        summary = "Sifarişlər siyahısı",
        description = "Filtr və pagination ilə sifarişlər siyahısı",
        tags = {"Orders"}
    )
    @ApiResponses({
        @ApiResponse(
            responseCode = "200",
            description = "Sifarişlər uğurla alındı",
            content = @Content(
                mediaType = "application/json",
                schema = @Schema(implementation = PagedOrderResponse.class)
            )
        ),
        @ApiResponse(
            responseCode = "400",
            description = "Yanlış sorğu parametrləri",
            content = @Content(schema = @Schema(implementation = ErrorResponse.class))
        ),
        @ApiResponse(responseCode = "401", description = "Autentifikasiya tələb olunur"),
        @ApiResponse(responseCode = "403", description = "Giriş qadağandır")
    })
    @GetMapping
    public ResponseEntity<Page<OrderResponse>> getOrders(
        @Parameter(description = "Sifariş statusu üzrə filtr")
        @RequestParam(required = false) OrderStatus status,

        @Parameter(description = "Müştəri ID üzrə filtr")
        @RequestParam(required = false) String customerId,

        @Parameter(description = "Tarixdən başlayaraq", example = "2026-01-01")
        @RequestParam(required = false)
        @DateTimeFormat(iso = DateTimeFormat.ISO.DATE) LocalDate from,

        @ParameterObject Pageable pageable
    ) {
        return ResponseEntity.ok(orderService.findAll(status, customerId, from, pageable));
    }

    // ─── GET by ID ────────────────────────────────────
    @Operation(summary = "Sifariş detalları", description = "ID ilə sifariş məlumatları")
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "Sifariş tapıldı",
            content = @Content(schema = @Schema(implementation = OrderResponse.class))),
        @ApiResponse(responseCode = "404", description = "Sifariş tapılmadı")
    })
    @GetMapping("/{id}")
    public ResponseEntity<OrderResponse> getOrder(
        @Parameter(description = "Sifariş ID", example = "12345", required = true)
        @PathVariable Long id
    ) {
        return orderService.findById(id)
            .map(ResponseEntity::ok)
            .orElse(ResponseEntity.notFound().build());
    }

    // ─── POST create ──────────────────────────────────
    @Operation(
        summary = "Yeni sifariş",
        description = "Yeni sifariş yaratmaq. Müştəri məlumatları tələb olunur."
    )
    @io.swagger.v3.oas.annotations.parameters.RequestBody(
        description = "Sifariş yaratma sorğusu",
        required = true,
        content = @Content(
            mediaType = "application/json",
            schema = @Schema(implementation = OrderRequest.class),
            examples = @ExampleObject(
                name = "Standart sifariş",
                value = """
                    {
                      "customerId": "customer-123",
                      "items": [
                        {"productId": "prod-1", "quantity": 2},
                        {"productId": "prod-2", "quantity": 1}
                      ],
                      "deliveryAddress": "Bakı, Nizami küç. 10"
                    }
                    """
            )
        )
    )
    @ApiResponse(responseCode = "201", description = "Sifariş yaradıldı")
    @ApiResponse(responseCode = "400", description = "Yanlış məlumatlar")
    @PostMapping
    public ResponseEntity<OrderResponse> createOrder(
        @Valid @RequestBody OrderRequest request
    ) {
        OrderResponse response = orderService.createOrder(request);
        return ResponseEntity
            .created(URI.create("/api/v2/orders/" + response.id()))
            .body(response);
    }

    // ─── PUT update ───────────────────────────────────
    @Operation(summary = "Sifariş yeniləmə", tags = {"Orders", "Admin"})
    @SecurityRequirement(name = "bearerAuth")
    @PutMapping("/{id}")
    public ResponseEntity<OrderResponse> updateOrder(
        @PathVariable Long id,
        @Valid @RequestBody UpdateOrderRequest request
    ) {
        return ResponseEntity.ok(orderService.update(id, request));
    }

    // ─── DELETE ───────────────────────────────────────
    @Operation(summary = "Sifariş ləğvetmə")
    @SecurityRequirement(name = "bearerAuth")
    @DeleteMapping("/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void cancelOrder(@PathVariable Long id) {
        orderService.cancel(id);
    }
}
```

---

## DTO annotasiyaları

```java
// ─── Request DTO ─────────────────────────────────────
@Schema(description = "Sifariş yaratma sorğusu")
public record OrderRequest(

    @Schema(
        description = "Müştəri unikal ID",
        example = "customer-123",
        requiredMode = Schema.RequiredMode.REQUIRED
    )
    @NotBlank
    String customerId,

    @Schema(
        description = "Sifariş maddələri siyahısı. Ən az 1 maddə olmalıdır.",
        requiredMode = Schema.RequiredMode.REQUIRED
    )
    @NotEmpty
    @Valid
    List<OrderItemRequest> items,

    @Schema(
        description = "Çatdırılma ünvanı",
        example = "Bakı, Nizami küçəsi 10, mənzil 5",
        nullable = true
    )
    String deliveryAddress
) {}

@Schema(description = "Sifariş maddəsi")
public record OrderItemRequest(

    @Schema(description = "Məhsul ID", example = "prod-laptop-001",
            requiredMode = Schema.RequiredMode.REQUIRED)
    @NotBlank
    String productId,

    @Schema(description = "Miqdar", example = "2", minimum = "1", maximum = "100")
    @Min(1) @Max(100)
    int quantity
) {}

// ─── Response DTO ─────────────────────────────────────
@Schema(description = "Sifariş məlumatları")
public record OrderResponse(

    @Schema(description = "Sifariş ID", example = "12345")
    Long id,

    @Schema(description = "Sifariş statusu")
    OrderStatus status,

    @Schema(description = "Ümumi məbləğ (AZN)", example = "149.99")
    BigDecimal totalAmount,

    @Schema(description = "Müştəri məlumatları")
    CustomerSummary customer,

    @Schema(description = "Sifariş maddələri")
    List<OrderItemResponse> items,

    @Schema(description = "Yaradılma tarixi", example = "2026-01-15T10:30:00Z",
            format = "date-time")
    Instant createdAt
) {}

// ─── Error response ───────────────────────────────────
@Schema(description = "Xəta cavabı")
public record ErrorResponse(

    @Schema(description = "HTTP status kodu", example = "400")
    int status,

    @Schema(description = "Xəta başlığı", example = "Bad Request")
    String title,

    @Schema(description = "Xəta mesajı", example = "customerId boş ola bilməz")
    String detail,

    @Schema(description = "Xəta baş verdiyi yol", example = "/api/v2/orders")
    String instance,

    @Schema(description = "Validasiya xətaları")
    List<FieldError> errors
) {
    @Schema(description = "Sahə xətası")
    public record FieldError(
        @Schema(description = "Sahə adı", example = "customerId")
        String field,
        @Schema(description = "Xəta mesajı", example = "boş ola bilməz")
        String message
    ) {}
}

// ─── Enum dokumentasiyası ────────────────────────────
@Schema(description = "Sifariş statusu")
public enum OrderStatus {
    @Schema(description = "Gözləmədə — yeni yaradılmış sifariş")
    PENDING,

    @Schema(description = "Təsdiqləndi — ödəniş qəbul edildi")
    CONFIRMED,

    @Schema(description = "Göndərildi — kargo teslim alındı")
    SHIPPED,

    @Schema(description = "Çatdırıldı — müştəriyə teslim edildi")
    DELIVERED,

    @Schema(description = "Ləğv edildi")
    CANCELLED
}
```

---

## Security dokumentasiyası

```java
// ─── Endpoint-ə security tətbiq etmək ────────────────

// Bütün controller-ə
@SecurityRequirement(name = "bearerAuth")
@RestController
public class AdminController { ... }

// Yalnız müəyyən metoda
@Operation(security = {@SecurityRequirement(name = "bearerAuth")})
@GetMapping("/admin/stats")
public StatsResponse getStats() { ... }

// Public endpoint — security olmadan
@Operation(security = {}) // Boş = public
@GetMapping("/public/products")
public List<Product> getPublicProducts() { ... }

// ─── Auth Controller dokumentasiyası ─────────────────
@Tag(name = "Authentication", description = "JWT token əməliyyatları")
@RestController
@RequestMapping("/api/auth")
public class AuthController {

    @Operation(
        summary = "Login",
        description = "Email və şifrə ilə JWT token alın",
        security = {} // Public endpoint
    )
    @ApiResponses({
        @ApiResponse(responseCode = "200", description = "Token uğurla alındı",
            content = @Content(schema = @Schema(implementation = AuthResponse.class))),
        @ApiResponse(responseCode = "401", description = "Yanlış email/şifrə")
    })
    @PostMapping("/login")
    public ResponseEntity<AuthResponse> login(
        @Valid @RequestBody LoginRequest request
    ) {
        return ResponseEntity.ok(authService.login(request));
    }

    @Operation(
        summary = "Token refresh",
        description = "Köhnə refresh token ilə yeni access token alın",
        security = {}
    )
    @PostMapping("/refresh")
    public ResponseEntity<AuthResponse> refresh(
        @RequestBody RefreshRequest request
    ) {
        return ResponseEntity.ok(authService.refresh(request));
    }
}
```

---

## Kod generasiyası

```xml
<!-- OpenAPI Generator Maven Plugin -->
<plugin>
    <groupId>org.openapitools</groupId>
    <artifactId>openapi-generator-maven-plugin</artifactId>
    <version>7.2.0</version>
    <executions>
        <execution>
            <goals>
                <goal>generate</goal>
            </goals>
            <configuration>
                <inputSpec>${project.basedir}/src/main/resources/openapi.yaml</inputSpec>
                <generatorName>java</generatorName>
                <output>${project.build.directory}/generated-sources</output>
                <apiPackage>com.example.client.api</apiPackage>
                <modelPackage>com.example.client.model</modelPackage>
                <configOptions>
                    <library>resttemplate</library>  <!-- ya da webclient, feign -->
                    <dateLibrary>java8</dateLibrary>
                    <java8>true</java8>
                </configOptions>
            </configuration>
        </execution>
    </executions>
</plugin>
```

```yaml
# openapi.yaml — Contract-first development
openapi: "3.0.3"
info:
  title: Order API
  version: "2.0"
paths:
  /api/orders:
    get:
      summary: Get orders
      parameters:
        - name: status
          in: query
          schema:
            $ref: '#/components/schemas/OrderStatus'
      responses:
        '200':
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/OrderListResponse'
components:
  schemas:
    OrderStatus:
      type: string
      enum: [PENDING, CONFIRMED, SHIPPED, DELIVERED, CANCELLED]
    OrderResponse:
      type: object
      properties:
        id:
          type: integer
          format: int64
        status:
          $ref: '#/components/schemas/OrderStatus'
        totalAmount:
          type: number
          format: double
      required: [id, status]
```

---

## İntervyu Sualları

### 1. OpenAPI nədir?
**Cavab:** RESTful API-ləri maşın oxunaqlı formatda (JSON/YAML) təsvir etmək üçün açıq standart (OpenAPI 3.x). Əvvəlki adı Swagger Specification. Spring Boot-da `springdoc-openapi` annotation-lardan (`@Operation`, `@ApiResponse`, `@Schema`) avtomatik OpenAPI spec yaradır. Swagger UI bu spec-i vizual şəkildə göstərir, "Try it out" ilə test mümkündür.

### 2. @Schema annotasiyasının rolu?
**Cavab:** DTO field-lərini dokumentasiya etmək üçün. `description` — field izahatı. `example` — nümunə dəyər. `requiredMode` — məcburi/ixtiyari. `nullable` — null ola bilərmi. `minimum`/`maximum` — dəyər aralığı. `format` — `date-time`, `email`, `uuid` kimi format. Bu annotasiyalar Swagger UI-da daha aydın dokumentasiya yaradır.

### 3. Contract-first vs Code-first?
**Cavab:** **Code-first** (Spring-də) — kod yazılır, annotation-lardan spec yaradılır; tez başlamaq üçün. **Contract-first** — əvvəl `.yaml` spec yazılır, sonra kod generasiya edilir; API dizayn əvvəl, implementasiya sonra; team-lər arasında razılaşma; client/server paralel inkişaf. Böyük layihədə contract-first tövsiyə edilir — API dizaynı implementasiyadan ayrılır.

### 4. OpenAPI generasiyasının faydası?
**Cavab:** Spec-dən Java/TypeScript/Python/Go/Kotlin client kod avtomatik generasiya olunur. Manual client yazılmır — spec dəyişdikdə client regenerasiya edilir, həmişə uyğundur. `openapi-generator-maven-plugin` Java RestTemplate/WebClient/Feign client-ları yaradır. Frontend `openapi-generator` ilə TypeScript axios client alır. Azaldılmış integration boilerplate.

### 5. Production-da Swagger UI-ı aktiv etmək düzgündürmü?
**Cavab:** Çox vaxt xeyr. Production API strukturunu, endpoint-ləri, parametrləri aşkar edir — attack surface artırır. Alternativ: (1) Swagger UI yalnız internal network-ə açmaq; (2) `springdoc.swagger-ui.enabled=false` production profile-da; (3) Ayrı developer portal (API gateway ilə); (4) Basic auth ilə qorumaq. API spec fayl (JSON/YAML) ayrıca saxlanılıb version control-da idarə oluna bilər.

*Son yenilənmə: 2026-04-10*
