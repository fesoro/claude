# Request/Response annotasiyaları (tam bələdçi): Spring vs Laravel

> **Seviyye:** Beginner ⭐

## Giriş

Controller metodu HTTP sorğusundan parametrləri almalıdır: URL path-dan (`/users/123`), query string-dən (`?page=1`), body-dən (JSON), header-dən, cookie-dən. Spring-də hər bir mənbə üçün ayrıca annotasiya var: `@PathVariable`, `@RequestParam`, `@RequestBody`, `@RequestHeader`, `@CookieValue`. Laravel-də isə hamısı `$request` obyekt üzərindən gəlir.

Bu fayl beginner üçündür - hər annotation-un nə vaxt istifadə olunduğunu misal ilə göstərir, sadə səhvləri (traps) vurğulayır.

## Spring/Java-də

### @PathVariable - URL path-dan dəyər

URL pattern-ində `{id}` kimi yer tutucuları variable-lara bağlayır.

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    // /api/users/42
    @GetMapping("/{id}")
    public UserResponse getUser(@PathVariable Long id) {
        return userService.findById(id);
    }

    // /api/users/42/posts/7
    @GetMapping("/{userId}/posts/{postId}")
    public PostResponse getPost(
            @PathVariable Long userId,
            @PathVariable Long postId) {
        return postService.find(userId, postId);
    }

    // Path variable adı parameter adından fərqlidirsə:
    @GetMapping("/{id}/profile")
    public ProfileResponse getProfile(@PathVariable("id") Long userId) {
        return profileService.findByUserId(userId);
    }

    // Java 8+ -parameters compile flag varsa, value lazım deyil
    // @PathVariable Long id  <-- burada id path-da "id" olmalıdır
}
```

**Tip çevirmə**: Spring avtomatik `"42"` string-ini `Long`-a çevirir. Düzgün deyilsə (məsələn, `"abc"`), 400 Bad Request qaytarır.

### @RequestParam - query string və form data

URL-in `?` hissəsindən və ya form (application/x-www-form-urlencoded) body-dən dəyər oxuyur.

```java
@RestController
@RequestMapping("/api/products")
public class ProductController {

    // GET /api/products?page=1&size=10
    @GetMapping
    public Page<ProductResponse> listProducts(
            @RequestParam int page,
            @RequestParam int size) {
        return productService.findAll(page, size);
    }

    // Optional parametr default dəyər ilə
    @GetMapping("/search")
    public List<ProductResponse> search(
            @RequestParam(required = false) String keyword,
            @RequestParam(defaultValue = "name") String sortBy,
            @RequestParam(defaultValue = "asc") String direction) {
        return productService.search(keyword, sortBy, direction);
    }

    // Siyahı parametr: ?tag=java&tag=spring
    @GetMapping("/by-tags")
    public List<ProductResponse> byTags(@RequestParam List<String> tag) {
        return productService.findByTags(tag);
    }

    // Map bütün query string-i yığır
    @GetMapping("/filter")
    public List<ProductResponse> filter(@RequestParam Map<String, String> filters) {
        // ?name=laptop&min_price=100 -> {name=laptop, min_price=100}
        return productService.filter(filters);
    }
}
```

### @RequestBody - HTTP body-dən JSON deserialize

Body-dəki JSON-u Java obyektinə çevirir. POST/PUT/PATCH üçün istifadə olunur. `Content-Type: application/json` olmalıdır.

```java
public record CreateProductRequest(
    @NotBlank String name,
    @Positive BigDecimal price,
    @NotNull Long categoryId
) {}

@RestController
@RequestMapping("/api/products")
public class ProductController {

    @PostMapping
    public ResponseEntity<ProductResponse> create(
            @Valid @RequestBody CreateProductRequest request) {
        Product product = productService.create(request);
        return ResponseEntity
            .status(HttpStatus.CREATED)
            .body(ProductResponse.from(product));
    }

    @PutMapping("/{id}")
    public ProductResponse update(
            @PathVariable Long id,
            @Valid @RequestBody UpdateProductRequest request) {
        return ProductResponse.from(productService.update(id, request));
    }
}
```

**Qeyd**: `@Valid` annotation-ı `@NotBlank`, `@Email` kimi validation-ları tətbiq edir. Olmasa, annotation-lar işləmir.

### @RequestHeader - HTTP header oxumaq

```java
@GetMapping("/info")
public Map<String, String> getInfo(
        @RequestHeader("User-Agent") String userAgent,
        @RequestHeader(value = "X-Request-Id", required = false) String requestId,
        @RequestHeader(value = "Accept-Language", defaultValue = "en") String lang) {
    return Map.of(
        "userAgent", userAgent,
        "requestId", requestId != null ? requestId : "none",
        "language", lang
    );
}

// Bütün header-ləri map kimi
@GetMapping("/headers")
public Map<String, String> allHeaders(@RequestHeader Map<String, String> headers) {
    return headers;
}
```

### @CookieValue - cookie oxumaq

```java
@GetMapping("/welcome")
public String welcome(
        @CookieValue(value = "sessionId", required = false) String sessionId,
        @CookieValue(value = "theme", defaultValue = "light") String theme) {
    return "Session: " + sessionId + ", Theme: " + theme;
}
```

### @ModelAttribute - form binding (MVC template)

Adətən Thymeleaf/JSP kimi server-side view üçün form-dan data bağlamaq üçün.

```java
@Controller
@RequestMapping("/users")
public class UserViewController {

    @PostMapping
    public String saveUser(@ModelAttribute @Valid CreateUserForm form,
                           BindingResult result) {
        if (result.hasErrors()) {
            return "users/create";
        }
        userService.create(form);
        return "redirect:/users";
    }
}

// Form DTO
public class CreateUserForm {
    @NotBlank private String name;
    @Email private String email;
    // getters, setters
}
```

### @RequestPart - multipart file upload

```java
@PostMapping(value = "/upload", consumes = MediaType.MULTIPART_FORM_DATA_VALUE)
public UploadResponse upload(
        @RequestPart("file") MultipartFile file,
        @RequestPart("metadata") @Valid FileMetadata metadata) {

    String filename = file.getOriginalFilename();
    long size = file.getSize();

    byte[] bytes = file.getBytes();  // faylın contentini oxu
    fileStorageService.save(file, metadata);

    return new UploadResponse(filename, size);
}

// Ya sadə variant:
@PostMapping("/simple-upload")
public String simpleUpload(@RequestParam("file") MultipartFile file) {
    return "Yükləndi: " + file.getOriginalFilename();
}
```

### Low-level: HttpServletRequest / HttpServletResponse

Bəzən low-level access lazım olur.

```java
@GetMapping("/raw")
public void rawAccess(HttpServletRequest request, HttpServletResponse response)
        throws IOException {
    String ip = request.getRemoteAddr();
    String method = request.getMethod();
    String uri = request.getRequestURI();

    response.setStatus(200);
    response.setContentType("text/plain");
    response.getWriter().write("IP: " + ip);
}
```

### Required və default

```java
// Required - default true
@RequestParam String name;  // yoxdursa 400

// Optional
@RequestParam(required = false) String filter;

// Default dəyər
@RequestParam(defaultValue = "10") int size;

// PathVariable adətən required (URL-də onsuz da olmalıdır)
@PathVariable Long id;
```

### Binding qaydaları

- `@PathVariable` - adətən required (URL template-də olduğuna görə)
- `@RequestParam` - default required=true, yoxdursa 400
- `@RequestBody` - default required=true, `Content-Type: application/json` olmalıdır
- `@RequestHeader` - default required=true
- `@CookieValue` - default required=true

## Laravel/PHP-də

Laravel-də bütün request data eyni yerdən gəlir - `Illuminate\Http\Request` obyekti üzərindən.

### Route parametri (Path variable analoqu)

```php
// routes/api.php
use App\Http\Controllers\UserController;

Route::get('/users/{id}', [UserController::class, 'show']);
Route::get('/users/{user}/posts/{post}', [PostController::class, 'show']);
```

Controller-də:

```php
class UserController extends Controller
{
    // Method parametr adı {id}-yə uyğun
    public function show($id)
    {
        return User::findOrFail($id);
    }

    // Route Model Binding - {user} -> User avtomatik
    public function showUser(User $user)
    {
        return $user;
    }
}

class PostController extends Controller
{
    public function show(User $user, Post $post)
    {
        // Hər iki parametr avtomatik DB-dən gəlir
        return $post;
    }
}
```

### Query string və body

```php
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        // Query və body qarışıqdır:
        $keyword = $request->input('keyword');
        $page = $request->input('page', 1);  // default 1

        // Yalnız query string-dən:
        $sort = $request->query('sort', 'name');

        // Yalnız JSON body-dən:
        $data = $request->json()->all();

        // Müəyyən field JSON-dan:
        $value = $request->json('user.name');

        return response()->json([
            'keyword' => $keyword,
            'page' => $page,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);

        return Product::create($validated);
    }
}
```

### Header oxumaq

```php
public function info(Request $request)
{
    $userAgent = $request->header('User-Agent');
    $requestId = $request->header('X-Request-Id', 'none');  // default
    $lang = $request->header('Accept-Language', 'en');

    $hasToken = $request->hasHeader('Authorization');
    $allHeaders = $request->headers->all();

    return response()->json([
        'ua' => $userAgent,
        'lang' => $lang,
    ]);
}
```

### Cookie oxumaq

```php
public function welcome(Request $request)
{
    $sessionId = $request->cookie('sessionId');
    $theme = $request->cookie('theme', 'light');

    return view('welcome', compact('sessionId', 'theme'));
}
```

### File upload

```php
public function upload(Request $request)
{
    $request->validate([
        'file' => 'required|file|max:2048',  // 2MB max
        'name' => 'required|string',
    ]);

    if ($request->hasFile('file')) {
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $size = $file->getSize();
        $mime = $file->getMimeType();

        // Save
        $path = $file->store('uploads');
        // və ya xüsusi diskə:
        $path = $file->storeAs('uploads', $originalName, 'public');
    }

    return response()->json(['path' => $path]);
}
```

### FormRequest (validation + input)

```bash
php artisan make:request StoreProductRequest
```

```php
class StoreProductRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ];
    }
}

// Controller
public function store(StoreProductRequest $request)
{
    $product = Product::create($request->validated());
    return response()->json($product, 201);
}
```

### All input helpers

```php
$request->all();                    // Bütün input (query + body + file)
$request->only(['name', 'email']);  // Yalnız göstərilən field-lər
$request->except(['password']);     // Hamısı minus password
$request->has('email');             // Field varmı?
$request->filled('name');           // Varmı və boş deyil?
$request->missing('field');         // Yoxdursa
```

## Əsas fərqlər

| Data mənbəyi | Spring | Laravel |
|---|---|---|
| **URL path** | `@PathVariable Long id` | `function show($id)` / `Route::get('/x/{id}')` |
| **Query string** | `@RequestParam String q` | `$request->query('q')` |
| **JSON body** | `@RequestBody Dto dto` | `$request->json()->all()` |
| **Form body** | `@ModelAttribute` / `@RequestParam` | `$request->input('field')` |
| **Header** | `@RequestHeader("X-Token")` | `$request->header('X-Token')` |
| **Cookie** | `@CookieValue("name")` | `$request->cookie('name')` |
| **File upload** | `@RequestPart MultipartFile file` | `$request->file('file')` |
| **Required flag** | `required = false` | `nullable` rule |
| **Default value** | `defaultValue = "10"` | `$request->input('x', 10)` |
| **Type conversion** | Avtomatik (compile tip) | Manual / cast |
| **Raw request** | `HttpServletRequest` | `$request` (bütün API) |

## Niyə belə fərqlər var?

### Spring-in yanaşması (explicit, typed)

Java statik tipli dildir. Hər parametr üçün ayrıca annotasiya "declarative" yanaşma verir - method signature-ə baxanda nə lazımdır görünür:

```java
public User get(
    @PathVariable Long id,              // URL-den
    @RequestParam boolean includeOrders, // query-dən
    @RequestHeader("Authorization") String token  // header-dən
)
```

Faydaları:
- Compiler yoxlayır (tip səhvini göstərir)
- Sənəd kimi oxunur - where each parameter comes from
- Testing asandır (method-a düz parametr verirsən, request obyekti yox)
- Tip çevirməsi avtomatikdir (`"42"` -> `Long 42`)

Mənfisi: çox annotation, bir az uzun.

### Laravel-in yanaşması (unified, simple)

PHP dinamik dildir. `$request` obyekti bütün data-nı birləşdirir - query, body, file hamısı $request-dədir.

Faydaları:
- Az yazmaq (hamısı `$request->input()`)
- Yeni field əlavə olanda method signature dəyişmir
- Sadə CRUD-lar üçün çox qısa

Mənfisi:
- Hansı data haradan gəlir bilinmir (query yoxsa body?)
- Tip çevirməsi manual (`(int) $request->input('id')`)
- Testing üçün request mock lazım

## Hansı framework-də var, hansında yoxdur?

### Yalnız Spring-də

- **Annotation-based binding**: `@PathVariable`, `@RequestParam` tipli declarative syntax
- **Compile-time type conversion**: `@PathVariable Long id` - səhv gəlsə 400 avtomatik
- **`@RequestPart`**: multipart və JSON qarışıq upload
- **`HttpServletRequest`**: servlet API low-level access
- **Bean Validation on DTO**: `@Valid @RequestBody Dto dto` avtomatik yoxlayır

### Yalnız Laravel-də

- **Route Model Binding**: `show(User $user)` - `{user}` avtomatik DB-dən gəlir
- **FormRequest class**: validation + authorize + ayrı sinif
- **`$request->all()`**: bütün input bir yerdə
- **`$request->only()` / `except()`**: sadə fieldlər seçmək
- **`$request->has()` / `filled()` / `missing()`**: input check helper-ləri

## Ümumi səhvlər (Beginner traps)

**1. `@RequestParam` əvəzinə `@RequestBody` GET üçün**

```java
// PIS - GET-də body adətən olmur
@GetMapping("/search")
public List<Product> search(@RequestBody SearchRequest req) { ... }

// YAXSHI
@GetMapping("/search")
public List<Product> search(@RequestParam String keyword) { ... }
```

**2. PathVariable name vs parameter name uymaması**

```java
// URL: /users/{userId}
// PIS - compile ola biler amma runtime-da fail
@GetMapping("/{userId}")
public User get(@PathVariable Long id) { ... }

// YAXSHI - explicit name
@GetMapping("/{userId}")
public User get(@PathVariable("userId") Long id) { ... }

// YA
@GetMapping("/{id}")
public User get(@PathVariable Long id) { ... }
```

Java 8+ `-parameters` compile flag ilə adlar saxlanır - bu halda value lazım deyil. Spring Boot-da bu flag default aktivdir, amma manual Maven setup-da yoxlayın.

**3. `@RequestBody` olmadan JSON oxumaq**

```java
// PIS - request body ignore olunur, bütün field-lər null
@PostMapping
public User create(CreateUserRequest request) { ... }

// YAXSHI
@PostMapping
public User create(@RequestBody CreateUserRequest request) { ... }
```

**4. `@Valid` qoymamaq**

```java
// PIS - annotation-lar (@NotBlank, @Email) işləmir
@PostMapping
public User create(@RequestBody @Validated_annotations_var CreateUserRequest req) { ... }

// YAXSHI
@PostMapping
public User create(@Valid @RequestBody CreateUserRequest req) { ... }
```

**5. Laravel-də `input()` vs `query()` qarışdırmaq**

```php
// URL: /search?q=laravel və body {"q": "php"}
$request->input('q');   // "php" (body birinci)
$request->query('q');   // "laravel" (yalnız query)
$request->json('q');    // "php"
```

**6. Path variable-i string tuta bilməsi**

```java
// URL: /users/abc
@GetMapping("/{id}")
public User get(@PathVariable Long id) { ... }
// "abc" -> Long çevrilə bilmir -> 400 Bad Request avtomatik
```

**7. `required=false` amma primitive tip**

```java
// PIS - primitive null ola bilmir -> NPE
@RequestParam(required = false) int page

// YAXSHI
@RequestParam(required = false) Integer page
// ya
@RequestParam(defaultValue = "1") int page
```

## Mini müsahibə sualları

**Sual 1**: `@RequestParam` və `@RequestBody` arasında nə fərq var? Nə vaxt hansını seçirsən?

*Cavab*: `@RequestParam` query string (`?key=value`) və form data-dan dəyər oxuyur, tək primitive tiplər üçündür. `@RequestBody` HTTP body-dəki JSON-u bütünlüklə obyektə çevirir (Content-Type application/json olmalıdır). GET/DELETE-də `@RequestParam`, POST/PUT-da kompleks data üçün `@RequestBody` istifadə olunur.

**Sual 2**: Laravel-də `$request->input('x')` və `$request->query('x')` fərqi nədir?

*Cavab*: `input()` həm query string-dən, həm də body-dən axtarır (body birincidir). `query()` yalnız URL query string-dən oxuyur. JSON body üçün `$request->json('x')`.

**Sual 3**: Spring-də path variable adı method parameter adından fərqlidirsə nə etmək lazımdır?

*Cavab*: `@PathVariable("pathName") Type paramName` yazmaq lazımdır - explicit ad vermək. Java 8+ `-parameters` compile flag ilə parameter adları compiled class-da saxlanırsa, Spring adları avtomatik uyğunlaşdırır, amma belə halda da fərq olduqda explicit value yazmaq təhlükəsizdir.

**Sual 4**: Spring-də `required=false` primitive `int` ilə niyə problem yaradır?

*Cavab*: Primitive tiplər (int, long, boolean) `null` dəyər ola bilmir. Request-də parametr yoxdursa Spring null vermək istəyir, amma `int`-ə null olmaz - `IllegalStateException` atır. Həlli: `Integer` istifadə et ya `defaultValue` ver.
