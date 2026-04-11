# API Sənədləşdirməsi (API Documentation)

## Giriş

API sənədləşdirməsi frontend developerlərin, mobil komandanın və üçüncü tərəf inteqrasiyaların API-nı düzgün istifadə etməsi üçün vacibdir. Spring-də bu iş üçün SpringDoc OpenAPI (əvvəllər Springfox/Swagger) standart həll kimi istifadə olunur. Laravel-də isə Scramble (avtomatik) və ya L5-Swagger (əl ilə annotasiyalar) istifadə edilir.

---

## Spring-də istifadəsi

### SpringDoc OpenAPI quraşdırması

```xml
<dependency>
    <groupId>org.springdoc</groupId>
    <artifactId>springdoc-openapi-starter-webmvc-ui</artifactId>
    <version>2.3.0</version>
</dependency>
```

```yaml
# application.yml
springdoc:
  api-docs:
    path: /api-docs          # OpenAPI JSON endpoint
  swagger-ui:
    path: /swagger-ui.html   # Swagger UI səhifəsi
    operations-sorter: method
    tags-sorter: alpha
  info:
    title: E-Ticarət API
    version: 1.0.0
    description: E-Ticarət platforması üçün REST API
```

Sadəcə dependency əlavə etməklə, Spring avtomatik olaraq bütün `@RestController` endpoint-lərini aşkarlayır və Swagger UI-da göstərir.

### Əsas konfiqurasiya

```java
@Configuration
public class OpenApiConfig {

    @Bean
    public OpenAPI customOpenAPI() {
        return new OpenAPI()
            .info(new Info()
                .title("E-Ticarət API")
                .version("2.0.0")
                .description("Bu API e-ticarət platformasının bütün əməliyyatlarını əhatə edir.")
                .contact(new Contact()
                    .name("API Dəstək")
                    .email("api@example.com")
                    .url("https://example.com"))
                .license(new License()
                    .name("MIT")
                    .url("https://opensource.org/licenses/MIT")))
            .externalDocs(new ExternalDocumentation()
                .description("Ətraflı sənədləşdirmə")
                .url("https://docs.example.com"))
            .addSecurityItem(new SecurityRequirement().addList("Bearer Authentication"))
            .components(new Components()
                .addSecuritySchemes("Bearer Authentication",
                    new SecurityScheme()
                        .type(SecurityScheme.Type.HTTP)
                        .scheme("bearer")
                        .bearerFormat("JWT")
                        .description("JWT token daxil edin")));
    }
}
```

### Controller-lərdə annotasiyalar

```java
@RestController
@RequestMapping("/api/v1/products")
@Tag(name = "Məhsullar", description = "Məhsul idarəetmə əməliyyatları")
public class ProductController {

    private final ProductService productService;

    public ProductController(ProductService productService) {
        this.productService = productService;
    }

    @Operation(
        summary = "Məhsulları siyahıla",
        description = "Bütün məhsulları səhifələnmiş şəkildə qaytarır. Kateqoriya və qiymət aralığına görə filtrləmək mümkündür."
    )
    @ApiResponses(value = {
        @ApiResponse(
            responseCode = "200",
            description = "Məhsullar uğurla qaytarıldı",
            content = @Content(
                mediaType = "application/json",
                schema = @Schema(implementation = ProductPageResponse.class)
            )
        ),
        @ApiResponse(
            responseCode = "400",
            description = "Yanlış sorğu parametrləri",
            content = @Content(
                mediaType = "application/json",
                schema = @Schema(implementation = ErrorResponse.class)
            )
        )
    })
    @GetMapping
    public ResponseEntity<Page<ProductDto>> listProducts(
            @Parameter(description = "Səhifə nömrəsi (0-dan başlayır)", example = "0")
            @RequestParam(defaultValue = "0") int page,

            @Parameter(description = "Səhifə ölçüsü", example = "20")
            @RequestParam(defaultValue = "20") int size,

            @Parameter(description = "Kateqoriya ID-si ilə filtr")
            @RequestParam(required = false) Long categoryId,

            @Parameter(description = "Minimum qiymət", example = "10.00")
            @RequestParam(required = false) BigDecimal minPrice,

            @Parameter(description = "Maksimum qiymət", example = "1000.00")
            @RequestParam(required = false) BigDecimal maxPrice,

            @Parameter(description = "Sıralama sahəsi", example = "price")
            @RequestParam(defaultValue = "createdAt") String sortBy,

            @Parameter(description = "Sıralama istiqaməti", schema = @Schema(allowableValues = {"asc", "desc"}))
            @RequestParam(defaultValue = "desc") String sortDir) {

        Page<ProductDto> products = productService.findAll(
            page, size, categoryId, minPrice, maxPrice, sortBy, sortDir);
        return ResponseEntity.ok(products);
    }

    @Operation(
        summary = "Məhsul yarat",
        description = "Yeni məhsul yaradır. Admin icazəsi tələb olunur."
    )
    @ApiResponses(value = {
        @ApiResponse(responseCode = "201", description = "Məhsul yaradıldı"),
        @ApiResponse(responseCode = "400", description = "Validasiya xətası"),
        @ApiResponse(responseCode = "401", description = "Autentifikasiya tələb olunur"),
        @ApiResponse(responseCode = "403", description = "İcazə yoxdur")
    })
    @PostMapping
    @SecurityRequirement(name = "Bearer Authentication")
    public ResponseEntity<ProductDto> createProduct(
            @io.swagger.v3.oas.annotations.parameters.RequestBody(
                description = "Yaradılacaq məhsul məlumatları",
                required = true,
                content = @Content(schema = @Schema(implementation = CreateProductRequest.class))
            )
            @Valid @RequestBody CreateProductRequest request) {

        ProductDto product = productService.create(request);
        URI location = URI.create("/api/v1/products/" + product.getId());
        return ResponseEntity.created(location).body(product);
    }

    @Operation(summary = "Məhsul əldə et", description = "ID-yə görə tək məhsul qaytarır")
    @ApiResponses(value = {
        @ApiResponse(responseCode = "200", description = "Məhsul tapıldı"),
        @ApiResponse(responseCode = "404", description = "Məhsul tapılmadı")
    })
    @GetMapping("/{id}")
    public ResponseEntity<ProductDto> getProduct(
            @Parameter(description = "Məhsul ID-si", required = true, example = "1")
            @PathVariable Long id) {

        ProductDto product = productService.findById(id);
        return ResponseEntity.ok(product);
    }

    @Operation(summary = "Məhsul yenilə")
    @PutMapping("/{id}")
    @SecurityRequirement(name = "Bearer Authentication")
    public ResponseEntity<ProductDto> updateProduct(
            @PathVariable Long id,
            @Valid @RequestBody UpdateProductRequest request) {

        ProductDto product = productService.update(id, request);
        return ResponseEntity.ok(product);
    }

    @Operation(summary = "Məhsul sil")
    @ApiResponse(responseCode = "204", description = "Məhsul silindi")
    @DeleteMapping("/{id}")
    @SecurityRequirement(name = "Bearer Authentication")
    public ResponseEntity<Void> deleteProduct(@PathVariable Long id) {
        productService.delete(id);
        return ResponseEntity.noContent().build();
    }
}
```

### DTO-larda @Schema annotasiyaları

```java
@Schema(description = "Məhsul yaratma sorğusu")
public record CreateProductRequest(

    @Schema(description = "Məhsul adı", example = "iPhone 15 Pro", minLength = 2, maxLength = 255)
    @NotBlank
    @Size(min = 2, max = 255)
    String name,

    @Schema(description = "Məhsul açıqlaması", example = "Ən son Apple smartfonu")
    @Size(max = 5000)
    String description,

    @Schema(description = "Qiymət (AZN)", example = "1999.99", minimum = "0.01")
    @NotNull
    @DecimalMin("0.01")
    BigDecimal price,

    @Schema(description = "Kateqoriya ID-si", example = "1")
    @NotNull
    Long categoryId,

    @Schema(description = "Stok miqdarı", example = "50", minimum = "0")
    @Min(0)
    int stock,

    @Schema(description = "Məhsul şəkilləri URL-ləri")
    List<String> imageUrls
) {}

@Schema(description = "Məhsul cavabı")
public record ProductDto(

    @Schema(description = "Məhsul ID-si", example = "1")
    Long id,

    @Schema(description = "Məhsul adı", example = "iPhone 15 Pro")
    String name,

    @Schema(description = "Qiymət (AZN)", example = "1999.99")
    BigDecimal price,

    @Schema(description = "Kateqoriya")
    CategoryDto category,

    @Schema(description = "Stokda var?", example = "true")
    boolean inStock,

    @Schema(description = "Yaradılma tarixi", example = "2024-01-15T10:30:00")
    LocalDateTime createdAt
) {}
```

### Endpoint-ləri qruplara ayırmaq

```java
@Configuration
public class OpenApiGroupConfig {

    @Bean
    public GroupedOpenApi publicApi() {
        return GroupedOpenApi.builder()
            .group("public")
            .displayName("Açıq API")
            .pathsToMatch("/api/v1/products/**", "/api/v1/categories/**")
            .pathsToExclude("/api/v1/admin/**")
            .build();
    }

    @Bean
    public GroupedOpenApi adminApi() {
        return GroupedOpenApi.builder()
            .group("admin")
            .displayName("Admin API")
            .pathsToMatch("/api/v1/admin/**")
            .build();
    }

    @Bean
    public GroupedOpenApi internalApi() {
        return GroupedOpenApi.builder()
            .group("internal")
            .displayName("Daxili API")
            .pathsToMatch("/internal/**")
            .build();
    }
}
```

Swagger UI-da yuxarıda dropdown ilə qruplar arasında keçid etmək olur.

### Əl ilə endpoint gizlətmək

```java
@Hidden  // Bu controller Swagger-də görünməyəcək
@RestController
@RequestMapping("/internal/health")
public class InternalHealthController {
    // ...
}

// Və ya tək metodu gizlətmək
@Operation(hidden = true)
@GetMapping("/debug")
public String debug() {
    return "debug info";
}
```

---

## Laravel-də istifadəsi

### Scramble — Avtomatik sənədləşdirmə

Scramble, Laravel üçün sıfır annotasiya ilə OpenAPI sənədləşdirməsi generasiya edir. Controller kodunuzu oxuyaraq avtomatik sənəd yaradır:

```bash
composer require dedoc/scramble
```

Quraşdırmadan sonra avtomatik olaraq bu endpoint-lər yaranır:
- `/docs/api` — Swagger UI
- `/docs/api.json` — OpenAPI JSON

```php
// Heç bir annotasiya lazım deyil — Scramble kodunuzu təhlil edir
class ProductController extends Controller
{
    /**
     * Məhsulları siyahıla.
     *
     * Bütün məhsulları səhifələnmiş şəkildə qaytarır.
     * Kateqoriya və qiymət aralığına görə filtrləmək mümkündür.
     */
    public function index(Request $request): ProductCollection
    {
        $products = Product::query()
            ->when($request->category_id, fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->min_price, fn ($q, $min) => $q->where('price', '>=', $min))
            ->when($request->max_price, fn ($q, $max) => $q->where('price', '<=', $max))
            ->paginate($request->integer('per_page', 20));

        return new ProductCollection($products);
    }

    /**
     * Yeni məhsul yarat.
     *
     * Admin icazəsi tələb olunur.
     *
     * @response 201
     */
    public function store(StoreProductRequest $request): ProductResource
    {
        $product = Product::create($request->validated());
        return new ProductResource($product);
    }

    /**
     * Məhsul əldə et.
     */
    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load('category'));
    }

    /**
     * Məhsul yenilə.
     */
    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());
        return new ProductResource($product);
    }

    /**
     * Məhsul sil.
     *
     * @response 204
     */
    public function destroy(Product $product): Response
    {
        $product->delete();
        return response()->noContent();
    }
}
```

Scramble bu məlumatları avtomatik aşkarlayır:
- `StoreProductRequest` daxilindəki validation qaydalarından request body schema-sı
- `ProductResource` daxilindəki `toArray()` metodundan response schema-sı
- Route model binding-dən path parametrləri
- PHPDoc şərhlərindən açıqlama və başlıq
- Return type-dan response tipi

```php
// Scramble FormRequest-dən schema çıxarır
class StoreProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'category_id' => ['required', 'exists:categories,id'],
            'stock' => ['required', 'integer', 'min:0'],
            'image_urls' => ['nullable', 'array'],
            'image_urls.*' => ['url'],
        ];
    }
}

// Scramble Resource-dan response schema-sı çıxarır
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'in_stock' => $this->stock > 0,
            'stock' => $this->when($request->user()?->isAdmin(), $this->stock),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

### Scramble konfiqurasiyası

```php
// config/scramble.php
return [
    'api_path' => 'api',
    'api_domain' => null,

    'info' => [
        'title' => 'E-Ticarət API',
        'version' => '1.0.0',
        'description' => 'E-Ticarət platforması REST API sənədləşdirməsi',
    ],

    'servers' => [
        ['url' => 'https://api.example.com', 'description' => 'Production'],
        ['url' => 'https://staging-api.example.com', 'description' => 'Staging'],
    ],
];
```

```php
// AppServiceProvider-da əlavə konfiqurasiya
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer', 'JWT')
            );
        });

        // Hansı route-lar sənədləşdirilsin
        Scramble::routes(function (Route $route) {
            return Str::startsWith($route->uri, 'api/');
        });
    }
}
```

### L5-Swagger — Əl ilə annotasiyalar

Scramble-dan fərqli olaraq, L5-Swagger OpenAPI annotasiyalarını əl ilə yazmağı tələb edir:

```bash
composer require darkaonline/l5-swagger
```

```php
/**
 * @OA\Info(
 *     title="E-Ticarət API",
 *     version="1.0.0",
 *     description="API sənədləşdirməsi",
 *     @OA\Contact(email="api@example.com")
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
class Controller extends BaseController
{
}

class ProductController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/products",
     *     summary="Məhsulları siyahıla",
     *     tags={"Məhsullar"},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Səhifə nömrəsi",
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Səhifə ölçüsü",
     *         @OA\Schema(type="integer", default=20)
     *     ),
     *     @OA\Parameter(
     *         name="category_id",
     *         in="query",
     *         description="Kateqoriya ID filtr",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Uğurlu",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(ref="#/components/schemas/Product")
     *             ),
     *             @OA\Property(property="meta", ref="#/components/schemas/PaginationMeta")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Yanlış sorğu")
     * )
     */
    public function index(Request $request)
    {
        // ...
    }

    /**
     * @OA\Post(
     *     path="/api/v1/products",
     *     summary="Məhsul yarat",
     *     tags={"Məhsullar"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateProductRequest")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Yaradıldı",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validasiya xətası")
     * )
     */
    public function store(StoreProductRequest $request)
    {
        // ...
    }
}

/**
 * @OA\Schema(
 *     schema="Product",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="iPhone 15 Pro"),
 *     @OA\Property(property="price", type="number", format="float", example=1999.99),
 *     @OA\Property(property="in_stock", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="CreateProductRequest",
 *     required={"name", "price", "category_id"},
 *     @OA\Property(property="name", type="string", minLength=2, maxLength=255),
 *     @OA\Property(property="price", type="number", minimum=0.01),
 *     @OA\Property(property="category_id", type="integer"),
 *     @OA\Property(property="stock", type="integer", minimum=0, default=0)
 * )
 */
```

Sənədləşdirməni generasiya etmək:

```bash
php artisan l5-swagger:generate
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring (SpringDoc) | Laravel |
|---|---|---|
| Əsas alət | SpringDoc OpenAPI (standart) | Scramble (avtomatik) və ya L5-Swagger (əl ilə) |
| Avtomatik aşkarlama | Bəli — controller-ləri avtomatik tapır | Scramble: bəli, L5-Swagger: xeyr |
| Annotasiya tələbi | İstəyə bağlı (əlavə detallar üçün) | Scramble: yoxdur, L5-Swagger: tam əl ilə |
| Swagger UI | Daxili (dependency ilə gəlir) | Daxili (hər iki paketdə) |
| Schema çıxarma | Java tipləri + annotasiyalardan | FormRequest + Resource siniflərindən |
| Qruplama | `GroupedOpenApi` bean ilə | Route prefix-ə görə |
| Tip sistemi dəstəyi | Güclü — Java tipləri tam istifadə olunur | Scramble PHP tipləri oxuyur, L5-Swagger əl ilə |
| Endpoint gizlətmə | `@Hidden` annotasiyası | Route-da middleware və ya Scramble filtr |

---

## Niyə belə fərqlər var?

**Java-nın güclü tip sistemi** SpringDoc-un işini asanlaşdırır. `Long id`, `BigDecimal price`, `List<String> tags` — bu tiplərdən avtomatik olaraq schema yaradılır. PHP-də tiplər daha zəif olduğu üçün, Scramble validation qaydalarını (`required|string|max:255`) oxuyaraq schema çıxarmalı olur.

**Spring-in annotasiya mədəniyyəti** OpenAPI annotasiyalarını təbii edir. Java developer-lər artıq `@RequestMapping`, `@Valid`, `@NotNull` kimi annotasiyalarla işləyir — `@Operation`, `@Schema` sadəcə daha bir annotasiyadır. PHP-də PHPDoc annotasiyaları daha az populyardır, bu səbəbdən Scramble heç annotasiya tələb etməməyi seçib.

**Scramble Laravel-in strukturundan istifadə edir.** Laravel-in `FormRequest`, `JsonResource`, route model binding kimi standart strukturları var. Scramble bunları oxuyaraq sənədləşdirmə yaradır. Spring-də belə standart strukturlar yoxdur — hər layihə DTO-ları fərqli yaza bilər.

**Ekosistem yetkinliyi:** Spring-in OpenAPI dəstəyi illərlə inkişaf edib və çox stabildir. Laravel-də isə Scramble nisbətən yeni layihədir (2023), amma sürətlə inkişaf edir.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- `GroupedOpenApi` ilə mürəkkəb API qruplama
- Java tip sistemindən tam avtomatik schema generasiyası
- `@SecurityRequirement` ilə endpoint səviyyəsində təhlükəsizlik
- Actuator endpoint-lərinin avtomatik sənədləşdirməsi
- Bean validation annotasiyalarından (`@NotNull`, `@Size`) avtomatik constraint çıxarma

**Yalnız Laravel-də:**
- Scramble ilə sıfır annotasiya / sıfır konfiqurasiya sənədləşdirmə
- FormRequest validation qaydalarından avtomatik request schema
- JsonResource-dan avtomatik response schema
- `php artisan scramble:analyze` ilə sənədləşdirmə keyfiyyətini yoxlama
- Route model binding-dən avtomatik path parametr aşkarlama
