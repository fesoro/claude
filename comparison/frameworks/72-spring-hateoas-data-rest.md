# Spring HATEOAS + Spring Data REST vs Laravel API Resources — Dərin Müqayisə

## Giriş

**HATEOAS** — "Hypermedia As The Engine Of Application State". REST API cavablarında məlumatla yanaşı **linklər** də qaytarılır: "bu order-i ləğv etmək üçün bu URL-ə POST göndər", "növbəti səhifə burada". Client link-ləri izləyir, URL strukturunu hardcode etmir. Roy Fielding-in orijinal REST təriflərinin son pilləsi bu idi, amma əksər API-lər HATEOAS tətbiq etmir.

**Spring HATEOAS** — HAL (JSON+HAL) formatında cavab qurmaq üçün library. `EntityModel`, `CollectionModel`, `PagedModel`, `Link`, `WebMvcLinkBuilder` təqdim edir. **Spring Data REST** isə bir addım irəli gedir — `JpaRepository` interface-ini HTTP endpoint kimi **avtomatik** açır. `/customers`, `/customers/42`, pagination, sorting, search — hamısı kod yazmadan.

Laravel-də birinci tərəf HATEOAS dəstəyi yoxdur. **API Resources** var (response shaping), **pagination linklər** default gəlir, amma HAL format və hypermedia linklər yoxdur. Package-lar var (`willdurand/hateoas` — Symfony əsaslı, Laravel-ə adapt edilir) amma əksər Laravel API-ləri sadəcə JSON data qaytarır.

---

## Spring-də istifadəsi

### 1) Asılılıqlar

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-hateoas</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-rest</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-jpa</artifactId>
</dependency>
```

### 2) Entity + Repository

```java
@Entity
@Table(name = "products")
@Getter @Setter @NoArgsConstructor
public class Product {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    private String sku;
    private String name;
    private String description;
    private BigDecimal price;
    private int stock;

    @ManyToOne(fetch = FetchType.LAZY)
    @JoinColumn(name = "category_id")
    private Category category;
}

@Entity
@Table(name = "categories")
@Getter @Setter @NoArgsConstructor
public class Category {
    @Id @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;
    private String name;
    private String slug;
}
```

```java
public interface ProductRepository extends JpaRepository<Product, Long> {

    Page<Product> findByNameContainingIgnoreCase(
        @Param("name") String name, Pageable pageable);

    Page<Product> findByCategorySlug(
        @Param("slug") String slug, Pageable pageable);
}

public interface CategoryRepository extends JpaRepository<Category, Long> {}
```

### 3) Spring HATEOAS — manual controller + HAL

```java
@RestController
@RequestMapping("/api/products")
@RequiredArgsConstructor
public class ProductController {

    private final ProductRepository products;
    private final ProductModelAssembler assembler;
    private final PagedResourcesAssembler<Product> pagedAssembler;

    @GetMapping("/{id}")
    public EntityModel<ProductDto> one(@PathVariable Long id) {
        Product p = products.findById(id).orElseThrow(() -> new ResourceNotFound(id));
        return assembler.toModel(p);
    }

    @GetMapping
    public PagedModel<EntityModel<ProductDto>> all(
        @RequestParam(required = false) String name,
        @PageableDefault(size = 20) Pageable pageable
    ) {
        Page<Product> page = (name != null)
            ? products.findByNameContainingIgnoreCase(name, pageable)
            : products.findAll(pageable);

        return pagedAssembler.toModel(page, assembler);
    }

    @PostMapping
    public ResponseEntity<EntityModel<ProductDto>> create(@RequestBody @Valid ProductCreateDto dto) {
        Product saved = products.save(dto.toEntity());
        EntityModel<ProductDto> model = assembler.toModel(saved);
        return ResponseEntity
            .created(model.getRequiredLink(IanaLinkRelations.SELF).toUri())
            .body(model);
    }
}
```

### 4) RepresentationModelAssembler

```java
@Component
public class ProductModelAssembler
    implements RepresentationModelAssembler<Product, EntityModel<ProductDto>> {

    @Override
    public EntityModel<ProductDto> toModel(Product product) {
        ProductDto dto = ProductDto.from(product);

        EntityModel<ProductDto> model = EntityModel.of(dto,
            linkTo(methodOn(ProductController.class).one(product.getId())).withSelfRel(),
            linkTo(methodOn(ProductController.class).all(null, Pageable.unpaged())).withRel("products"),
            linkTo(methodOn(CategoryController.class).one(product.getCategory().getId()))
                .withRel("category")
        );

        if (product.getStock() > 0) {
            model.add(linkTo(methodOn(CartController.class)
                .addToCart(product.getId(), 1)).withRel("addToCart"));
        }

        if (SecurityUtils.hasRole("ADMIN")) {
            model.add(linkTo(methodOn(ProductController.class)
                .delete(product.getId())).withRel("delete"));
        }

        return model;
    }
}
```

### 5) Response — HAL format

```http
GET /api/products/42 HTTP/1.1

HTTP/1.1 200 OK
Content-Type: application/hal+json
```

```json
{
  "id": 42,
  "sku": "SKU-42",
  "name": "Wireless Keyboard",
  "price": 89.99,
  "stock": 14,
  "_links": {
    "self": { "href": "http://api.example.com/api/products/42" },
    "products": { "href": "http://api.example.com/api/products{?page,size,sort}", "templated": true },
    "category": { "href": "http://api.example.com/api/categories/3" },
    "addToCart": { "href": "http://api.example.com/api/cart/add?productId=42&qty=1" }
  }
}
```

### 6) PagedModel — pagination with HAL

```http
GET /api/products?page=2&size=10&sort=price,desc
```

```json
{
  "_embedded": {
    "products": [
      { "id": 20, "name": "...", "_links": {...} },
      { "id": 19, "name": "...", "_links": {...} }
    ]
  },
  "_links": {
    "first": { "href": ".../api/products?page=0&size=10&sort=price,desc" },
    "prev":  { "href": ".../api/products?page=1&size=10&sort=price,desc" },
    "self":  { "href": ".../api/products?page=2&size=10&sort=price,desc" },
    "next":  { "href": ".../api/products?page=3&size=10&sort=price,desc" },
    "last":  { "href": ".../api/products?page=12&size=10&sort=price,desc" }
  },
  "page": { "size": 10, "totalElements": 124, "totalPages": 13, "number": 2 }
}
```

### 7) HAL-FORMS — affordances

Client-in "bu resurs üçün mümkün əməliyyatlar" kəşf etməsi üçün. `application/prs.hal-forms+json` media type.

```java
@GetMapping(value = "/{id}", produces = MediaTypes.HAL_FORMS_JSON_VALUE)
public EntityModel<ProductDto> oneWithForms(@PathVariable Long id) {
    Product p = products.findById(id).orElseThrow();

    return EntityModel.of(ProductDto.from(p))
        .add(linkTo(methodOn(ProductController.class).one(id)).withSelfRel()
            .andAffordance(afford(methodOn(ProductController.class)
                .update(id, null)))
            .andAffordance(afford(methodOn(ProductController.class)
                .delete(id))));
}
```

Response-da `_templates` açarı ilə PUT/DELETE üçün schema çıxır — client formu avtomatik yaradır.

### 8) Spring Data REST — avtomatik endpoint

`@RepositoryRestResource` annotation:

```java
@RepositoryRestResource(collectionResourceRel = "products", path = "products")
public interface ProductRepository extends JpaRepository<Product, Long> {

    @RestResource(path = "byName", rel = "byName")
    Page<Product> findByNameContainingIgnoreCase(@Param("name") String name, Pageable pageable);

    @RestResource(exported = false)
    @Override
    void deleteAll();         // bu method HTTP-də açılmır
}
```

Kod yazmadan aşağıdakı endpoint-lər avtomatik açılır:
- `GET    /products` — sayfalı siyahı
- `GET    /products/42` — tək element
- `POST   /products` — yarat
- `PUT    /products/42` — yenilə
- `PATCH  /products/42` — qismən yenilə
- `DELETE /products/42` — sil
- `GET    /products/search/byName?name=keyboard&page=0&size=20`
- `GET    /products/42/category` — association endpoint
- `PUT    /products/42/category` — association dəyişdir (Content-Type: text/uri-list)

### 9) Projection — custom görünüş

```java
@Projection(name = "summary", types = Product.class)
public interface ProductSummary {
    String getName();
    BigDecimal getPrice();

    @Value("#{target.category.name}")
    String getCategoryName();
}
```

```http
GET /products/42?projection=summary
```

### 10) Repository events

```java
@Component
@RepositoryEventHandler(Product.class)
public class ProductEventHandler {

    @HandleBeforeCreate
    public void beforeCreate(Product product) {
        product.setSku(generateSku(product));
    }

    @HandleAfterSave
    public void afterSave(Product product) {
        cacheInvalidator.invalidate("products:" + product.getId());
    }

    @HandleBeforeDelete
    public void beforeDelete(Product product) {
        if (orderRepo.existsByProductId(product.getId())) {
            throw new DataIntegrityViolationException("Product is in orders");
        }
    }
}
```

### 11) Security ilə inteqrasiya

```java
public interface ProductRepository extends JpaRepository<Product, Long> {

    @PreAuthorize("hasRole('ADMIN')")
    @Override
    <S extends Product> S save(S entity);

    @PreAuthorize("hasRole('ADMIN')")
    @Override
    void deleteById(Long id);
}
```

### 12) `application.yml` — Spring Data REST config

```yaml
spring:
  data:
    rest:
      base-path: /api
      default-page-size: 20
      max-page-size: 100
      return-body-on-create: true
      return-body-on-update: true
      default-media-type: application/hal+json
      detection-strategy: annotated
```

---

## Laravel-də istifadəsi

### 1) Model + Migration

```bash
php artisan make:model Product -mfrc
php artisan make:model Category -mfr
```

```php
// database/migrations/2026_04_20_create_products.php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->string('sku')->unique();
    $table->string('name');
    $table->text('description')->nullable();
    $table->decimal('price', 10, 2);
    $table->unsignedInteger('stock')->default(0);
    $table->foreignId('category_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
});
```

```php
// app/Models/Product.php
class Product extends Model
{
    protected $fillable = ['sku', 'name', 'description', 'price', 'stock', 'category_id'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
```

### 2) API Resource — response shaping

```bash
php artisan make:resource ProductResource
php artisan make:resource ProductCollection
php artisan make:resource CategoryResource
```

```php
// app/Http/Resources/ProductResource.php
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'stock' => $this->stock,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'links' => [
                'self' => route('products.show', $this->id),
                'category' => route('categories.show', $this->category_id),
                'add_to_cart' => $this->when(
                    $this->stock > 0,
                    fn () => route('cart.add', ['product' => $this->id, 'qty' => 1])
                ),
            ],
        ];
    }

    public function with(Request $request): array
    {
        return [
            'meta' => [
                'api_version' => 'v1',
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }
}
```

### 3) Controller

```bash
php artisan make:controller Api/ProductController --api --model=Product
```

```php
// app/Http/Controllers/Api/ProductController.php
class ProductController extends Controller
{
    public function index(Request $request): ResourceCollection
    {
        $query = Product::query()->with('category');

        if ($name = $request->query('name')) {
            $query->where('name', 'ilike', "%{$name}%");
        }

        if ($slug = $request->query('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $slug));
        }

        $sort = $request->query('sort', '-created_at');
        foreach (explode(',', $sort) as $field) {
            $direction = str_starts_with($field, '-') ? 'desc' : 'asc';
            $column = ltrim($field, '-');
            if (in_array($column, ['price', 'name', 'created_at', 'stock'])) {
                $query->orderBy($column, $direction);
            }
        }

        return ProductResource::collection(
            $query->paginate($request->integer('per_page', 20))
        );
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load('category'));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());
        return (new ProductResource($product->load('category')))
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('products.show', $product));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());
        return new ProductResource($product->load('category'));
    }

    public function destroy(Product $product): Response
    {
        $this->authorize('delete', $product);
        $product->delete();
        return response()->noContent();
    }
}
```

### 4) Routes

```php
// routes/api.php
Route::apiResource('products', ProductController::class);
Route::apiResource('categories', CategoryController::class)->only(['index', 'show']);
```

`apiResource` avtomatik: index, store, show, update, destroy — Spring Data REST mexanizmasına yaxın, amma `/search/byName` kimi əlavə endpoint-lər yaradılmır.

### 5) Response format — pagination linklər

```json
{
  "data": [
    {
      "id": 42,
      "sku": "SKU-42",
      "name": "Wireless Keyboard",
      "price": 89.99,
      "stock": 14,
      "category": { "id": 3, "name": "Peripherals" },
      "links": {
        "self": "http://api.example.com/api/products/42",
        "category": "http://api.example.com/api/categories/3",
        "add_to_cart": "http://api.example.com/api/cart/add?product=42&qty=1"
      }
    }
  ],
  "links": {
    "first": "http://api.example.com/api/products?page=1",
    "last":  "http://api.example.com/api/products?page=13",
    "prev":  "http://api.example.com/api/products?page=2",
    "next":  "http://api.example.com/api/products?page=4"
  },
  "meta": {
    "current_page": 3,
    "from": 21,
    "last_page": 13,
    "path": "http://api.example.com/api/products",
    "per_page": 10,
    "to": 30,
    "total": 124,
    "api_version": "v1",
    "generated_at": "2026-04-20T10:12:00Z"
  }
}
```

Format HAL deyil, amma pagination linkləri default gəlir.

### 6) HAL output — `willdurand/hateoas` və Fractal

HAL formatı üçün iki populyar paket var:

```bash
composer require willdurand/hateoas
# və ya
composer require league/fractal spatie/laravel-fractal
```

`willdurand/hateoas` annotation əsaslıdır (Symfony üçün yazılıb, Laravel-də sərbəst işləyir):

```php
use Hateoas\Configuration\Annotation as Hateoas;

/**
 * @Hateoas\Relation("self", href = @Hateoas\Route("products.show", parameters = {"id" = "expr(object.id)"}))
 * @Hateoas\Relation("category", href = @Hateoas\Route("categories.show", parameters = {"id" = "expr(object.categoryId)"}))
 */
class ProductDto { public int $id; public string $name; public float $price; public int $categoryId; }

$hateoas = \Hateoas\HateoasBuilder::create()->build();
return response($hateoas->serialize($dto, 'json'))
    ->header('Content-Type', 'application/hal+json');
```

Fractal ilə JSON:API və ya HAL serializer seçmək mümkündür — amma Spring Data REST kimi avtomatik deyil, hər transformer-i özün yazırsan.

### 8) Form Request — validation

```php
// app/Http/Requests/StoreProductRequest.php
class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:64', 'unique:products,sku'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'stock' => ['required', 'integer', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
        ];
    }
}
```

### 9) Nested resource pagination

```php
// GET /api/categories/3/products
class CategoryProductController extends Controller
{
    public function index(Category $category, Request $request): ResourceCollection
    {
        return ProductResource::collection(
            $category->products()
                ->with('category')
                ->paginate($request->integer('per_page', 20))
        );
    }
}

Route::get('categories/{category}/products', [CategoryProductController::class, 'index']);
```

### 10) Repository events — Observer

```bash
php artisan make:observer ProductObserver --model=Product
```

```php
// app/Observers/ProductObserver.php
class ProductObserver
{
    public function creating(Product $product): void
    {
        $product->sku ??= 'SKU-'.Str::upper(Str::random(8));
    }

    public function saved(Product $product): void
    {
        Cache::forget("products:{$product->id}");
    }

    public function deleting(Product $product): void
    {
        if ($product->orders()->exists()) {
            throw new DomainException('Product is in orders');
        }
    }
}

// app/Providers/AppServiceProvider.php
public function boot(): void
{
    Product::observe(ProductObserver::class);
}
```

Spring Data REST-in `@RepositoryEventHandler`-inin tam ekvivalenti.

---

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| HATEOAS library | `spring-hateoas` first-party | Paket (`willdurand/hateoas`) |
| HAL format default | Bəli (hal+json) | Xeyr (sadə JSON) |
| Hypermedia linklər | `EntityModel.add(linkTo(...))` | Manual `links` field |
| HAL-FORMS | Dəstəklənir | Yoxdur |
| Auto-CRUD endpoint | Spring Data REST — Repository-dən | Yoxdur (scaffolding var) |
| Repository events | `@RepositoryEventHandler` | Model Observer |
| Projection | `@Projection` interface | Manual Resource |
| Association endpoint | `/products/42/category` auto | Manual route |
| Pagination linklər | PagedModel + HAL | Default Laravel pagination |
| URI templates | `{?page,size,sort}` HAL | Yoxdur |
| Scaffolding sürəti | `@RepositoryRestResource` bir annotation | `make:resource` + `make:controller --api` |
| Media type | `application/hal+json`, HAL-FORMS, Siren, Collection+JSON | `application/json` default |
| Linklər discovery | `_links` + `curies` | Manual |

---

## Niyə belə fərqlər var?

**Spring-in REST purism tarixi.** Spring HATEOAS 2012-ci ildə yaradıldı, REST sənayedə yayılanda. Roy Fielding-in orijinal tezisi "hypermedia as the engine of application state" olduğuna görə Spring bu prinsipi əhatəli dəstəkləyib. Enterprise public API-ləri (banking, insurance) HAL/HAL-FORMS istifadə edir.

**Laravel-in "pragmatik REST" yanaşması.** Laravel əsasən startup və kiçik-orta komandalar üçün düşünülüb. Onların API-ləri adətən internal və ya mobile client-lər üçündür — client URL strukturunu bilir, HATEOAS discovery-yə ehtiyac duymur. Buna görə sadə JSON + pagination linklər kifayət edilib.

**Spring Data REST dəyəri nə zaman?** Prototip mərhələsində və ya admin panel üçün — 10 dəqiqəyə tam CRUD API. Amma production-da biznes qaydaları, DTOs, authorization çətinləşir və əksər komandalar əvvəl-axır manual controller-ə keçir. Buna görə Spring Data REST həmişə populyar qalmadı.

**HAL vs JSON.** HAL formatı linkləri structured şəkildə (`_links` açarında) təqdim edir — client-lər `_links.next.href`-ə güvənir, link strukturunu parse etmək üçün unified bir JavaScript library var. Sadə JSON-da `links.next` və `next_page_url` kimi variantlar mövcuddur, standart yoxdur.

**Laravel API Resources flexible.** `JsonResource::toArray()` ilə response format-ı tam sənin əlindədir — `whenLoaded`, `when`, `mergeWhen` kimi conditional field-lər, `with()` metaData. HATEOAS qədər strukturlu deyil, amma daha oynaq.

**Symfony daha yaxındır.** PHP dünyasında Spring HATEOAS-a ən yaxın Symfony + `willdurand/hateoas` paketidir. Laravel bu paketi istifadə edə bilir, amma yerli deyil.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- Spring Data REST — `Repository`-dən avtomatik HTTP endpoint.
- `@RepositoryRestResource`, `@RestResource(exported = false)` annotations.
- `@Projection` ilə selectiv response.
- `@RepositoryEventHandler` — HTTP-driven event handler.
- `EntityModel`, `CollectionModel`, `PagedModel` abstraksiyası.
- `WebMvcLinkBuilder.linkTo(methodOn(...))` — type-safe URL building.
- HAL, HAL-FORMS, Siren, Collection+JSON first-party serializer-lər.
- Association endpoint — `/products/42/category` avtomatik.
- Affordance — link-lərdə HTTP method + body schema.
- `PagedResourcesAssembler` — pagination HAL link-ləri avtomatik.
- Content negotiation — client-in `Accept` başlığına görə format seçimi.

**Yalnız Laravel-də:**
- `whenLoaded` — lazy relationship yalnız yüklənibsə include.
- `when` — conditional field (permission-ə bağlı).
- `mergeWhen` — bir neçə field conditional mərləmə.
- `make:resource`, `make:controller --api --model` — CLI scaffolding.
- `apiResource` route — beş endpoint tək sətirdə.
- Default pagination `links` + `meta` obyekt.
- `Response::setStatusCode()` fluent.
- Model Observer lifecycle (kənar HTTP-dən işləyir).

---

## Best Practices

- **HATEOAS dəyəri public API-də**: əgər API-ni üçüncü tərəf developerlər işlədir və URL strukturu dəyişə bilərsə, HATEOAS discovery onlara URL tutmamağa imkan verir. Internal API üçün overhead artıqdır.
- **HAL formatı standart**: Spring-də `produces = MediaTypes.HAL_JSON_VALUE` explicit qoy — yanlışlıqla JSON-a düşməsin.
- **Affordance ehtiyatlı**: hər endpoint üçün affordance çıxarmaq response-u böyüdür. Yalnız client-in istifadə etdiyi link-ləri əlavə et.
- **Security link-ləri**: yalnız istifadəçinin icazəsi olan link-ləri göstər. `hasRole` yoxlayıb `model.add` et.
- **Spring Data REST məhdud**: prototipdə yaxşıdır, production-da business validation, authorization, DTO üçün manual controller daha yaxşıdır. Ya hər ikisini istifadə et (`@RepositoryRestResource` + custom handler).
- **Laravel-də**: `links` field-ini `toArray()`-də standard saxla. `self`, `next`, `prev` minimum.
- **Pagination default limit**: Spring `default-page-size: 20`, Laravel `paginate(20)`. Client `per_page` ilə dəyişə bilsin, amma max limit qoy (`max-page-size: 100`).
- **Nested resource pagination**: `/categories/3/products?page=2` — nested relation-larda da pagination dəstəklə.
- **Etag + If-None-Match**: Spring-də `@LastModifiedDate` + `@Version` + `ResponseEntity.ok().eTag(...)`. Laravel-də middleware ilə. Band-width qənaəti.
- **Versioning**: Accept header (`application/vnd.api.v1+json`) və ya URL prefix (`/api/v1/products`). Major breaking change halında yeni versiya, minor additive dəyişiklik eyni versiyada.

---

## Yekun

Spring HATEOAS + Spring Data REST birlikdə "tam REST" disciplini dəstəkləyən rəsmi alətlərdir. `Repository`-dən avtomatik CRUD endpoint, HAL linklər, pagination, affordance — əksəriyyəti kod yazmadan. Enterprise public API-lər üçün ideal, amma business logic tələbi artdıqca manual controller-ə keçmək tez-tez baş verir.

Laravel API Resources sadə, oynaq və pragmatikdir. HAL formatı birinci tərəf deyil, amma `JsonResource::toArray()` ilə istənilən format qurmaq mümkündür. Pagination linkləri default gəlir, scaffolding sürətlidir (`make:resource` + `make:controller --api --model` 5 saniyə). HATEOAS discovery tələbi yoxdursa, Laravel-in yanaşması daha asan və daha oxunaqlıdır.

Seçim istifadəçi profilindən asılıdır: üçüncü tərəf developer-lər + public API + hypermedia discovery — Spring daha yaxşı dəstəkləyir. Internal mobile API + sürətli scaffold + fleksibil response format — Laravel pragmatik seçimdir.
