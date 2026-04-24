# ResponseEntity və HTTP status code-lar: Spring vs Laravel

> **Seviyye:** Beginner ⭐

## Giriş

HTTP cavabının 3 əsas hissəsi var: **status code** (200, 201, 404 ...), **headers** (Content-Type, Location, Cache-Control ...) və **body** (JSON, HTML, file bytes). Spring-də `ResponseEntity<T>` bu üç hissəni tək obyektdə birləşdirir. Laravel-də `response()` helper-i eyni işi görür.

Bu fayl beginner üçündür - HTTP status code-ların mənasını, nə vaxt hansını istifadə etməyi, `ResponseEntity`-nin necə qurulmasını öyrədir.

## HTTP status code cədvəli

| Code | Ad | Nə vaxt istifadə olunur |
|---|---|---|
| **200** | OK | Uğurlu GET/PUT/PATCH - body var |
| **201** | Created | Uğurlu POST - yeni resource yaradıldı |
| **202** | Accepted | Async iş qəbul olundu (hələ bitmədi) |
| **204** | No Content | Uğurlu DELETE və ya PUT - body yoxdur |
| **301** | Moved Permanently | URL daimi dəyişib |
| **302** | Found | URL müvəqqəti dəyişib (redirect) |
| **304** | Not Modified | Client cache istifadə etsin |
| **400** | Bad Request | Sorğu formatı səhv (JSON parse error) |
| **401** | Unauthorized | Authentication yoxdur ya səhvdir |
| **403** | Forbidden | Authentication var amma icazə yoxdur |
| **404** | Not Found | Resource yoxdur |
| **405** | Method Not Allowed | URL var amma metod yoxdur (GET var, PUT yox) |
| **409** | Conflict | Conflict (duplicate email, version mismatch) |
| **422** | Unprocessable Entity | Validation error (JSON ok, amma dəyər səhv) |
| **429** | Too Many Requests | Rate limit aşılıb |
| **500** | Internal Server Error | Server-də gözlənilməz xəta |
| **502** | Bad Gateway | Upstream (məsələn, DB ya microservice) cavab vermir |
| **503** | Service Unavailable | Müvəqqəti down (maintenance, overload) |

## Spring/Java-də

### ResponseEntity<T> nədir?

`ResponseEntity<T>` - HTTP cavabın tam modelidir. Generic tip `<T>` body-nin tipini göstərir.

```java
public class ResponseEntity<T> {
    private final HttpStatusCode status;
    private final HttpHeaders headers;
    private final T body;
}
```

### Plain object qaytarmaq (avtomatik 200)

Əgər method ResponseEntity deyil, adi obyekt qaytarsa, Spring avtomatik 200 OK verir.

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    @GetMapping("/{id}")
    public UserResponse getUser(@PathVariable Long id) {
        // 200 OK avtomatik
        return userService.findById(id);
    }
}
```

### ResponseEntity.ok() shortcut

```java
@GetMapping("/{id}")
public ResponseEntity<UserResponse> getUser(@PathVariable Long id) {
    UserResponse user = userService.findById(id);
    return ResponseEntity.ok(user);  // 200 OK + body
}

// Body olmadan
@GetMapping("/ping")
public ResponseEntity<Void> ping() {
    return ResponseEntity.ok().build();  // 200 OK, body yox
}
```

### Status explicit olaraq yazmaq

```java
@PostMapping
public ResponseEntity<UserResponse> create(@Valid @RequestBody CreateUserRequest req) {
    User user = userService.create(req);
    UserResponse dto = UserResponse.from(user);

    // 201 Created
    return ResponseEntity
        .status(HttpStatus.CREATED)
        .body(dto);
}

// Ya statusCode ilə:
return ResponseEntity.status(201).body(dto);
```

### noContent() - 204

DELETE və ya bodysız PUT üçün.

```java
@DeleteMapping("/{id}")
public ResponseEntity<Void> delete(@PathVariable Long id) {
    userService.delete(id);
    return ResponseEntity.noContent().build();  // 204 No Content
}
```

### notFound() - 404

```java
@GetMapping("/{id}")
public ResponseEntity<UserResponse> get(@PathVariable Long id) {
    return userService.findById(id)
        .map(UserResponse::from)
        .map(ResponseEntity::ok)
        .orElse(ResponseEntity.notFound().build());
}

// Ya exception throw ilə (@ControllerAdvice catch edir):
@GetMapping("/{id}")
public UserResponse get(@PathVariable Long id) {
    return userService.findById(id)
        .map(UserResponse::from)
        .orElseThrow(() -> new ResourceNotFoundException("User", id));
}
```

### created(uri) - 201 + Location header

REST konvensiyası: POST sonra yaradılan resource-un URI-sini `Location` header-də qaytarmaq.

```java
@PostMapping
public ResponseEntity<UserResponse> create(@Valid @RequestBody CreateUserRequest req) {
    User user = userService.create(req);
    UserResponse dto = UserResponse.from(user);

    URI location = URI.create("/api/users/" + user.getId());
    return ResponseEntity.created(location).body(dto);
    // 201 Created
    // Location: /api/users/42
    // Body: { "id": 42, "name": ... }
}

// Ya builder-lə dinamik:
URI location = ServletUriComponentsBuilder
    .fromCurrentRequest()
    .path("/{id}")
    .buildAndExpand(user.getId())
    .toUri();
return ResponseEntity.created(location).body(dto);
```

### Custom header-lər

```java
@GetMapping("/report")
public ResponseEntity<Report> getReport() {
    Report report = reportService.build();

    return ResponseEntity.ok()
        .header("X-Report-Version", "1.0")
        .header("X-Generated-At", Instant.now().toString())
        .cacheControl(CacheControl.maxAge(60, TimeUnit.SECONDS))
        .body(report);
}

// HttpHeaders obyekt ilə:
HttpHeaders headers = new HttpHeaders();
headers.add("X-Custom-Header", "value");
headers.add("X-Another", "value2");

return new ResponseEntity<>(report, headers, HttpStatus.OK);
```

### @ResponseStatus annotation

Sadə hallarda `ResponseEntity` yerinə `@ResponseStatus`:

```java
@PostMapping
@ResponseStatus(HttpStatus.CREATED)  // 201
public UserResponse create(@Valid @RequestBody CreateUserRequest req) {
    return UserResponse.from(userService.create(req));
}

@DeleteMapping("/{id}")
@ResponseStatus(HttpStatus.NO_CONTENT)  // 204
public void delete(@PathVariable Long id) {
    userService.delete(id);
}
```

Fərq: `@ResponseStatus` sabitdir, `ResponseEntity` dinamikdir (başqa-başqa status qaytarmaq olar).

### Full CRUD controller misalı

```java
@RestController
@RequestMapping("/api/products")
public class ProductController {

    private final ProductService productService;

    public ProductController(ProductService productService) {
        this.productService = productService;
    }

    // GET /api/products -> 200 OK
    @GetMapping
    public ResponseEntity<List<ProductResponse>> list() {
        List<ProductResponse> products = productService.findAll()
            .stream()
            .map(ProductResponse::from)
            .toList();
        return ResponseEntity.ok(products);
    }

    // GET /api/products/42 -> 200 OK / 404 Not Found
    @GetMapping("/{id}")
    public ResponseEntity<ProductResponse> get(@PathVariable Long id) {
        return productService.findById(id)
            .map(ProductResponse::from)
            .map(ResponseEntity::ok)
            .orElse(ResponseEntity.notFound().build());
    }

    // POST /api/products -> 201 Created + Location header
    @PostMapping
    public ResponseEntity<ProductResponse> create(
            @Valid @RequestBody CreateProductRequest req) {
        Product product = productService.create(req);
        ProductResponse dto = ProductResponse.from(product);

        URI location = ServletUriComponentsBuilder
            .fromCurrentRequest()
            .path("/{id}")
            .buildAndExpand(product.getId())
            .toUri();

        return ResponseEntity.created(location).body(dto);
    }

    // PUT /api/products/42 -> 200 OK
    @PutMapping("/{id}")
    public ResponseEntity<ProductResponse> update(
            @PathVariable Long id,
            @Valid @RequestBody UpdateProductRequest req) {
        Product product = productService.update(id, req);
        return ResponseEntity.ok(ProductResponse.from(product));
    }

    // PATCH /api/products/42/stock -> 200 OK (partial)
    @PatchMapping("/{id}/stock")
    public ResponseEntity<Void> updateStock(
            @PathVariable Long id,
            @RequestBody StockUpdate update) {
        productService.updateStock(id, update.quantity());
        return ResponseEntity.ok().build();
    }

    // DELETE /api/products/42 -> 204 No Content
    @DeleteMapping("/{id}")
    public ResponseEntity<Void> delete(@PathVariable Long id) {
        productService.delete(id);
        return ResponseEntity.noContent().build();
    }
}
```

### Exception handling (qısa)

Detailed fayl 26 və 73-də. Qısaca:

```java
@RestControllerAdvice
public class GlobalExceptionHandler {

    @ExceptionHandler(ResourceNotFoundException.class)
    public ResponseEntity<ErrorResponse> handleNotFound(ResourceNotFoundException ex) {
        ErrorResponse error = new ErrorResponse(404, ex.getMessage());
        return ResponseEntity.status(404).body(error);
    }

    @ExceptionHandler(ValidationException.class)
    public ResponseEntity<?> handleValidation(ValidationException ex) {
        return ResponseEntity.status(422).body(Map.of("error", ex.getMessage()));
    }
}
```

### ProblemDetail (RFC 7807) - qısa qeyd

Spring 6+ `ProblemDetail` standart error format verir (detail fayl 73-də):

```java
@ExceptionHandler(ResourceNotFoundException.class)
public ProblemDetail handleNotFound(ResourceNotFoundException ex) {
    ProblemDetail problem = ProblemDetail.forStatusAndDetail(
        HttpStatus.NOT_FOUND, ex.getMessage());
    problem.setTitle("Resource Not Found");
    problem.setProperty("resource", ex.getResource());
    return problem;
}
```

### Cache header-ləri

```java
@GetMapping("/catalog")
public ResponseEntity<Catalog> getCatalog() {
    Catalog catalog = catalogService.load();

    return ResponseEntity.ok()
        .cacheControl(CacheControl
            .maxAge(5, TimeUnit.MINUTES)
            .cachePublic())
        .eTag(Integer.toString(catalog.hashCode()))
        .body(catalog);
}
```

## Laravel/PHP-də

### response() helper

Laravel-də cavab `response()` helper-i ilə qurulur.

```php
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    // GET /api/users -> 200 OK
    public function index(): JsonResponse
    {
        $users = User::all();
        return response()->json($users);  // 200 avtomatik
    }

    // GET /api/users/{id} -> 200 OK / 404
    public function show($id): JsonResponse
    {
        $user = User::findOrFail($id);  // yoxdursa avtomatik 404
        return response()->json($user);
    }

    // POST -> 201 Created
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());
        return response()->json($user, 201);
    }

    // PUT -> 200 OK
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user->update($request->validated());
        return response()->json($user);
    }

    // DELETE -> 204 No Content
    public function destroy(User $user)
    {
        $user->delete();
        return response()->noContent();  // 204
    }
}
```

### Response helper variantları

```php
// JSON + status
return response()->json($data, 201);

// Status + header
return response()
    ->json($data)
    ->header('X-Custom', 'value')
    ->header('X-Version', '1.0');

// No content (204)
return response()->noContent();

// Plain text
return response('Hello World', 200);

// File download
return response()->download(storage_path('app/report.pdf'));

// Streamed file
return response()->file(storage_path('app/image.jpg'));

// Redirect
return redirect('/home');
return redirect()->route('users.index');
return redirect()->back()->with('status', 'Saved!');

// JSON with cookie
return response()
    ->json($data)
    ->cookie('theme', 'dark', 60);
```

### abort() - exception with status

```php
public function show($id)
{
    $user = User::find($id);

    if (!$user) {
        abort(404, 'User not found');
    }

    if (!auth()->user()->can('view', $user)) {
        abort(403, 'Forbidden');
    }

    return response()->json($user);
}

// Qısa form:
$user = User::findOrFail($id);  // 404 avtomatik
abort_if(!$user->active, 403, 'User inactive');
abort_unless($condition, 400, 'Bad request');
```

### API Resource + Response

```php
public function store(StoreUserRequest $request)
{
    $user = User::create($request->validated());

    return (new UserResource($user))
        ->response()
        ->setStatusCode(201)
        ->header('X-Resource-Type', 'User');
}

public function index()
{
    return UserResource::collection(User::paginate(15));
    // Avtomatik 200 OK
}
```

### Location header (201)

```php
public function store(StoreUserRequest $request): JsonResponse
{
    $user = User::create($request->validated());

    return response()
        ->json($user, 201)
        ->header('Location', route('users.show', $user->id));
}
```

### Cache header-ləri

```php
return response()
    ->json($data)
    ->header('Cache-Control', 'public, max-age=300')
    ->header('ETag', '"' . md5(json_encode($data)) . '"');

// Middleware ilə:
Route::get('/catalog', CatalogController::class)
    ->middleware('cache.headers:public;max_age=300;etag');
```

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| **Cavab sinfi** | `ResponseEntity<T>` | `JsonResponse` / `Response` |
| **200 OK** | `ResponseEntity.ok(body)` | `response()->json($data)` |
| **201 Created** | `ResponseEntity.status(201).body()` / `.created(uri)` | `response()->json($data, 201)` |
| **204 No Content** | `ResponseEntity.noContent().build()` | `response()->noContent()` |
| **404 Not Found** | `ResponseEntity.notFound().build()` | `abort(404)` / `findOrFail` |
| **Header əlavə** | `.header("X-Key", "val")` | `->header('X-Key', 'val')` |
| **Location header** | `.created(uri)` avtomatik | Manual `->header('Location', ...)` |
| **Default status** | 200 OK avtomatik | 200 OK avtomatik |
| **Exception -> status** | `@RestControllerAdvice` | `Exceptions` handler / `abort()` |
| **Standart error format** | `ProblemDetail` (RFC 7807) | Manual / built-in validation |
| **Tip safety** | Generic `<T>` compile-time | Dinamik (runtime) |

## Niyə belə fərqlər var?

### Spring (typed builder)

Java statik tipli dildir. `ResponseEntity<T>` generic tip ilə body-nin tipini qoruyur - compile zamanı səhv tutulur. Builder pattern (`ResponseEntity.ok().header(...).body(...)`) chain-able-dir, oxunaqlıdır.

Fərli-fərli status code-lar üçün factory metodlar var (`ok`, `created`, `noContent`, `notFound`, `badRequest`, `status`) - kodu görəndə nə olduğunu başa düşürsən.

### Laravel (fluent helper)

PHP dinamik dildir. `response()->json($data, $status)` ikinci parametr ilə status verilir - qısa sintaksisdir. Chain method-lar header və cookie əlavə edir.

Laravel daha çox "convention" ilə işləyir - `findOrFail` 404 avtomatik atır, `AuthenticationException` 401 avtomatik çıxır. Bu az kod yazmağa imkan verir.

### Exception handling

Hər iki framework-də exception-lar avtomatik HTTP status code-lara çevrilir:
- Spring: `@ResponseStatus` annotation ya `@RestControllerAdvice`
- Laravel: `Exceptions` handler (`bootstrap/app.php` Laravel 11+)

## Hansı framework-də var, hansında yoxdur?

### Yalnız Spring-də

- **`ResponseEntity<T>` generic tip**: Compile-time body tip yoxlaması
- **`ResponseEntity.created(uri)`**: 201 + Location header üçün xüsusi metod
- **`HttpHeaders` sinfi**: Header-lər üçün xüsusi obyekt
- **`ProblemDetail` (RFC 7807)**: Standart error response format (Spring 6+)
- **`CacheControl` builder**: Cache-Control header-i struktur ilə qurmaq
- **`@ResponseStatus`**: Method ya exception-a static status bağlamaq

### Yalnız Laravel-də

- **`abort(code, message)`**: Bir sətirdə exception + status
- **`findOrFail`**: Eloquent 404 avtomatik
- **`response()->download()`**: Fayl yükləmək üçün hazır helper
- **`response()->stream()`**: Streaming response
- **`->cookie()`**: Chain ilə cookie əlavə
- **`redirect()->back()->with()`**: Flash data ilə redirect
- **Sadə status code parametri**: `json($data, 201)` ikinci parametr

## Ümumi səhvlər (Beginner traps)

**1. Hər yerdə 200 OK istifadə etmək**

```java
// PIS - POST-də 200 düzgün deyil
@PostMapping
public ResponseEntity<User> create(@RequestBody User u) {
    return ResponseEntity.ok(userService.create(u));  // 200 səhv
}

// YAXSHI - 201 Created
@PostMapping
public ResponseEntity<User> create(@RequestBody User u) {
    return ResponseEntity.status(201).body(userService.create(u));
}
```

**2. DELETE-də body qaytarmaq**

```java
// PIS - DELETE body qaytarmalı deyil
@DeleteMapping("/{id}")
public Map<String, String> delete(@PathVariable Long id) {
    userService.delete(id);
    return Map.of("status", "deleted");
}

// YAXSHI - 204 No Content
@DeleteMapping("/{id}")
public ResponseEntity<Void> delete(@PathVariable Long id) {
    userService.delete(id);
    return ResponseEntity.noContent().build();
}
```

**3. Validation error-u 400 qaytarmaq (422 olmalıdır)**

400 - JSON parse səhvi üçündür. 422 - JSON ok, amma dəyərlər düzgün deyil.

```java
// JSON səhv: '{"name":' -> 400 Bad Request
// JSON ok amma: {"email": "not-email"} -> 422 Unprocessable Entity
```

**4. 401 vs 403 qarışdırmaq**

- **401 Unauthorized**: authentication yoxdur (login etməmisən)
- **403 Forbidden**: authenticated amma icazə yoxdur (login var amma admin deyil)

**5. Spring-də `.build()` unutmaq**

```java
// PIS - compile error
return ResponseEntity.notFound();

// YAXSHI
return ResponseEntity.notFound().build();
```

**6. Laravel-də `response()->json(null, 204)`**

```php
// PIS - 204 ilə body qaytarma
return response()->json(null, 204);

// YAXSHI
return response()->noContent();
```

**7. Location header-i manual yazmaq Spring-də**

```java
// UZUN yol
return ResponseEntity
    .status(201)
    .header("Location", "/api/users/" + user.getId())
    .body(dto);

// ASAN yol
URI uri = URI.create("/api/users/" + user.getId());
return ResponseEntity.created(uri).body(dto);
```

## Mini müsahibə sualları

**Sual 1**: 200 OK və 201 Created nə vaxt istifadə olunur? 204 No Content nə vaxt?

*Cavab*: 200 - uğurlu GET, uğurlu PUT/PATCH-də body ilə. 201 - POST ilə yeni resource yaradılıb (adətən Location header ilə). 204 - uğurlu DELETE, ya body lazım olmayan hallarda (`PUT /users/42/activate`).

**Sual 2**: 401 və 403 fərqi nədir?

*Cavab*: 401 Unauthorized - client authentication etməyib (login lazımdır ya token yoxdur/səhvdir). 403 Forbidden - authenticated, amma bu resource-a icazə yoxdur (məsələn, user admin endpoint-ə yığıb). 401 "sən kimsən bilmirəm", 403 "bilirəm, amma olmaz".

**Sual 3**: `ResponseEntity<T>` və plain object qaytarmaq arasında nə fərq var?

*Cavab*: Plain object avtomatik 200 OK verir, header dəyişmək olmur. `ResponseEntity<T>` status, header və body-nin üçünü kontrol etməyə imkan verir. Error halları (404, 204, 201 Location) yalnız `ResponseEntity` ilə asan olur.

**Sual 4**: 422 Unprocessable Entity nə vaxt düzgündür?

*Cavab*: JSON düzgün formatda olanda amma dəyərlər validation qaydalarına uyğun deyilsə (email düzgün deyil, yaş mənfidir, field yoxdur). JSON parse ola bilməyəndə isə 400 Bad Request. Fərq: 400 syntax problemi, 422 semantic problem.
