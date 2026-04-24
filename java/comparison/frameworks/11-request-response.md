# Request ve Response

> **Seviyye:** Beginner ⭐

## Giris

Her web framework HTTP request (sorgu) ve response (cavab) ile isleyir. Spring-de `HttpServletRequest`, `@RequestBody`, `@RequestHeader` ve `ResponseEntity` istifade olunur. Laravel-de ise `Request` sinifi, `input()`, `all()` metodlari ve response helper-leri var. Bu iki framework-un HTTP ile isleme usullari ferqli felsefeler uzerine quruludur.

## Spring-de istifadesi

### Request parametrlerini almaq

```java
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import jakarta.servlet.http.HttpServletRequest;

@RestController
@RequestMapping("/api/products")
public class ProductController {

    // Query parametrleri: /api/products?category=electronics&page=1
    @GetMapping
    public ResponseEntity<List<ProductDto>> getProducts(
            @RequestParam(required = false) String category,
            @RequestParam(defaultValue = "1") int page,
            @RequestParam(defaultValue = "10") int size,
            @RequestParam(required = false) String search) {

        // ...
        return ResponseEntity.ok(products);
    }

    // Path variable: /api/products/42
    @GetMapping("/{id}")
    public ResponseEntity<ProductDto> getProduct(@PathVariable Long id) {
        // ...
    }

    // Path variable ile birlikde: /api/products/42/reviews
    @GetMapping("/{productId}/reviews")
    public ResponseEntity<List<ReviewDto>> getReviews(
            @PathVariable Long productId,
            @RequestParam(defaultValue = "latest") String sort) {
        // ...
    }
}
```

### @RequestBody ile JSON qebul etmek

```java
@PostMapping
public ResponseEntity<ProductDto> createProduct(
        @Valid @RequestBody CreateProductRequest request) {

    ProductDto product = productService.create(request);
    return ResponseEntity.status(HttpStatus.CREATED).body(product);
}

// Request DTO
public class CreateProductRequest {

    @NotBlank(message = "Ad bosh ola bilmez")
    private String name;

    @NotNull
    @Positive(message = "Qiymet muesbet olmalidir")
    private BigDecimal price;

    @Size(max = 1000)
    private String description;

    private Long categoryId;
    private List<String> tags;

    // Getters ve Setters
}
```

### @RequestHeader ile header oxumaq

```java
@GetMapping("/profile")
public ResponseEntity<UserDto> getProfile(
        @RequestHeader("Authorization") String authHeader,
        @RequestHeader(value = "Accept-Language", defaultValue = "az") String lang,
        @RequestHeader(value = "X-Request-Id", required = false) String requestId) {

    // ...
}
```

### HttpServletRequest ile tam giris

```java
@GetMapping("/info")
public ResponseEntity<Map<String, String>> getRequestInfo(
        HttpServletRequest request) {

    Map<String, String> info = new HashMap<>();
    info.put("method", request.getMethod());           // GET
    info.put("uri", request.getRequestURI());           // /api/products/info
    info.put("url", request.getRequestURL().toString());// http://localhost:8080/api/products/info
    info.put("remoteAddr", request.getRemoteAddr());    // 127.0.0.1
    info.put("contentType", request.getContentType());
    info.put("userAgent", request.getHeader("User-Agent"));

    // Butun parametrler
    request.getParameterMap().forEach((key, value) ->
        info.put("param_" + key, String.join(",", value)));

    return ResponseEntity.ok(info);
}
```

### ResponseEntity ile cavab qurmaq

```java
@RestController
@RequestMapping("/api/files")
public class FileController {

    // Muxteli HTTP statuslari
    @PostMapping("/upload")
    public ResponseEntity<FileResponse> upload(@RequestParam("file") MultipartFile file) {
        if (file.isEmpty()) {
            return ResponseEntity.badRequest()
                .body(new FileResponse("Fayl bosh ola bilmez"));
        }

        FileResponse response = fileService.save(file);
        return ResponseEntity
            .status(HttpStatus.CREATED)
            .header("X-File-Id", response.getId().toString())
            .body(response);
    }

    // Custom headerler ile
    @GetMapping("/download/{id}")
    public ResponseEntity<byte[]> download(@PathVariable Long id) {
        FileData file = fileService.getFile(id);

        return ResponseEntity.ok()
            .header("Content-Disposition",
                    "attachment; filename=\"" + file.getName() + "\"")
            .header("Content-Type", file.getContentType())
            .body(file.getData());
    }

    // Redirect
    @GetMapping("/redirect")
    public ResponseEntity<Void> redirect() {
        return ResponseEntity
            .status(HttpStatus.FOUND)
            .location(URI.create("/api/files/new-location"))
            .build();
    }

    // No content
    @DeleteMapping("/{id}")
    public ResponseEntity<Void> delete(@PathVariable Long id) {
        fileService.delete(id);
        return ResponseEntity.noContent().build();
    }
}
```

### HttpStatus enum-u

```java
// Tez-tez istifade olunan statuslar:
HttpStatus.OK                    // 200
HttpStatus.CREATED               // 201
HttpStatus.NO_CONTENT            // 204
HttpStatus.BAD_REQUEST           // 400
HttpStatus.UNAUTHORIZED          // 401
HttpStatus.FORBIDDEN             // 403
HttpStatus.NOT_FOUND             // 404
HttpStatus.CONFLICT              // 409
HttpStatus.UNPROCESSABLE_ENTITY  // 422
HttpStatus.INTERNAL_SERVER_ERROR // 500
```

### Cookie ile islemek

```java
@GetMapping("/set-cookie")
public ResponseEntity<String> setCookie() {
    ResponseCookie cookie = ResponseCookie.from("session_id", "abc123")
        .httpOnly(true)
        .secure(true)
        .path("/")
        .maxAge(Duration.ofHours(1))
        .sameSite("Strict")
        .build();

    return ResponseEntity.ok()
        .header(HttpHeaders.SET_COOKIE, cookie.toString())
        .body("Cookie qoyuldu");
}

@GetMapping("/read-cookie")
public ResponseEntity<String> readCookie(
        @CookieValue(name = "session_id", defaultValue = "yoxdur") String sessionId) {
    return ResponseEntity.ok("Session: " + sessionId);
}
```

## Laravel-de istifadesi

### Request sinifi ile melumat almaq

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProductController extends Controller
{
    // Query parametrleri: /api/products?category=electronics&page=1
    public function index(Request $request)
    {
        // Tekli deyerler
        $category = $request->input('category');          // null eger yoxdursa
        $category = $request->input('category', 'all');   // default deyer
        $page = $request->query('page', 1);               // yalniz query string-den

        // Butun inputlari al
        $all = $request->all();                    // butun input + query
        $only = $request->only(['category', 'page']); // yalniz bu saheler
        $except = $request->except(['token']);      // bu sahe xaric

        // Movcudluq yoxlamasi
        if ($request->has('category')) { /* ... */ }
        if ($request->filled('category')) { /* bosh deyilse */ }
        if ($request->missing('category')) { /* yoxdursa */ }

        // Boolean deyer (1, "1", true, "true", "on", "yes" => true)
        $isActive = $request->boolean('is_active');

        // Integer
        $page = $request->integer('page', 1);

        // String
        $search = $request->string('search')->trim();

        $products = Product::when($category, fn ($q) => $q->where('category', $category))
            ->paginate(15);

        return response()->json($products);
    }

    // Route parametri: /api/products/{id}
    public function show(Request $request, int $id)
    {
        $product = Product::findOrFail($id);
        return response()->json($product);
    }

    // Route Model Binding: /api/products/{product}
    public function edit(Product $product)
    {
        // Laravel avtomatik tapir, tapilmasa 404 qaytarir
        return response()->json($product);
    }
}
```

### JSON request body ile islemek

```php
public function store(Request $request)
{
    // JSON body-den melumat almaq
    $name = $request->input('name');
    $price = $request->input('price');

    // Nested JSON: {"address": {"city": "Baki"}}
    $city = $request->input('address.city');

    // Butun JSON body
    $data = $request->json()->all();

    // Content type yoxlamasi
    if ($request->isJson()) {
        // JSON request
    }

    if ($request->wantsJson()) {
        // Client JSON cavab isteyir (Accept header)
    }
}
```

### Header ve melumat

```php
public function profile(Request $request)
{
    // Header oxumaq
    $auth = $request->header('Authorization');
    $lang = $request->header('Accept-Language', 'az');

    // Request melumatlari
    $method = $request->method();          // GET, POST, etc.
    $url = $request->url();                 // Tam URL (query string xaric)
    $fullUrl = $request->fullUrl();         // Query string ile birlikde
    $path = $request->path();               // URI path
    $ip = $request->ip();                   // Client IP
    $userAgent = $request->userAgent();

    // Method yoxlamasi
    if ($request->isMethod('post')) { /* ... */ }

    // Authenticated istifadeci
    $user = $request->user();               // ve ya auth()->user()
}
```

### Form Request ile validation

```php
<?php
// app/Http/Requests/StoreProductRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:1000',
            'category_id' => 'required|exists:categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Mehsulun adi bosh ola bilmez',
            'price.min' => 'Qiymet en az 0.01 olmalidir',
        ];
    }
}

// Controller-de istifade
public function store(StoreProductRequest $request)
{
    // Validation avtomatik isleyib. Bura catibsa, melumat duzgundur
    $product = Product::create($request->validated());
    return response()->json($product, 201);
}
```

### Response helperleri

```php
class ResponseExamplesController extends Controller
{
    // JSON cavab
    public function json()
    {
        return response()->json([
            'message' => 'Ugurlu',
            'data' => ['id' => 1, 'name' => 'Test'],
        ]);
    }

    // Custom status ile
    public function created()
    {
        $product = Product::create([...]);
        return response()->json($product, 201);
    }

    // Custom headerler
    public function withHeaders()
    {
        return response()->json($data)
            ->header('X-Custom-Header', 'deyer')
            ->header('X-Request-Id', uniqid())
            ->cookie('visited', 'true', 60); // 60 deqiqelik cookie
    }

    // Redirect
    public function redirectExample()
    {
        // Sade redirect
        return redirect('/dashboard');

        // Named route-a redirect
        return redirect()->route('products.show', ['product' => 1]);

        // Geri qayitmaq
        return back()->with('success', 'Mehsul yaradildi');

        // Flash mesaj ile redirect
        return redirect()->route('products.index')
            ->with('message', 'Mehsul ugurla yaradildi');
    }

    // File download
    public function download()
    {
        return response()->download(
            storage_path('app/files/report.pdf'),
            'hesabat.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }

    // No content
    public function noContent()
    {
        return response()->noContent(); // 204
    }

    // Stream
    public function stream()
    {
        return response()->stream(function () {
            echo "data: " . json_encode(['time' => now()]) . "\n\n";
            ob_flush();
            flush();
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
```

### Cookie ile islemek

```php
// Cookie qoymaq
return response('Salam')
    ->cookie('name', 'deyer', $deqiqe = 60);

// Cookie oxumaq
$value = $request->cookie('name');

// Cookie silmek
return response('Salam')->withoutCookie('name');

// Encrypted cookie (default davranis)
// Laravel butun cookie-leri encrypt edir
// Encrypt olunmasin isterseniz middleware-de istisna elave edin
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Request sinifi** | `HttpServletRequest` + annotasiyalar | `Illuminate\Http\Request` sinifi |
| **Query params** | `@RequestParam` annotasiyasi | `$request->query()`, `$request->input()` |
| **Request body** | `@RequestBody` + DTO sinifi | `$request->input()`, `$request->all()` |
| **Path variable** | `@PathVariable` | Route parametri ve ya Route Model Binding |
| **Headers** | `@RequestHeader` annotasiyasi | `$request->header()` |
| **Cookie** | `@CookieValue` annotasiyasi | `$request->cookie()` |
| **Response** | `ResponseEntity<T>` | `response()->json()`, `response()` |
| **Status code** | `HttpStatus` enum | Reqem (200, 201, 404) |
| **Redirect** | `ResponseEntity` ile `FOUND` status | `redirect()`, `back()` |
| **File upload** | `@RequestParam MultipartFile` | `$request->file()` |
| **Validation** | `@Valid` + DTO annotasiyalari | `$request->validate()` ve ya Form Request |
| **Tip tehlukesizliyi** | Compile-time yoxlama | Runtime yoxlama |

## Niye bele ferqler var?

### Annotasiya vs Metod cagirisi

Spring-de request parametrleri annotasiyalarla (`@RequestParam`, `@PathVariable`, `@RequestBody`) metod parametrlerine inject olunur. Bu deklarativ yaklasimdir - ne istediginizi bildirirsiniz, framework onu verir. Ustunluyu ondadir ki, IDE ve compiler parametr tiplerini yoxlaya bilir.

Laravel-de ise `$request->input('name')` metod cagirisi ile alirsiniz. Bu imperativ yaklasimdir - ozunuz isteyirsiniz. Daha cevikdir, amma compile-time tip yoxlamasi yoxdur.

### DTO vs dinamik input

Spring-de JSON body-ni qebul etmek ucun bir DTO sinifi yaratmaq lazimdir. Bu elave is olsa da, request-in strukturu tamamen senedle$dirmi$ olur ve validation annotasiyalari ile birge cali$ir.

Laravel-de ise `$request->input('name')` yazmaq kifayetdir - hecsne DTO yaratmag lazim deyil. Bu suretli inkisaf ucun elaveridir, amma boyuk proyektlerde request-in strukturu qeyri-museyyen ola biler.

### Redirect felsefesi

Laravel-de redirect cox zenginddir: `redirect()->route()`, `back()`, `redirect()->with()` (flash session melumati ile). Bu web application-lar ucun yaradilib. Spring REST API-lerde redirect nadir istifade olunur, amma `ResponseEntity` ile tam kontrol mumkundur.

## Hansi framework-de var, hansinda yoxdur?

### Yalniz Spring-de (ve ya daha asandir):
- **`ResponseEntity<T>`** - Generics ile tip-tehlukesiz response
- **`HttpStatus` enum** - Butun HTTP statuslari enum olaraq
- **Content negotiation** - Eyni metod JSON, XML qaytara biler
- **`@RequestBody` ile avtomatik deserialization** - JSON-dan Java obyektine avtomatik cevrilme
- **`@ModelAttribute`** - Form melumati birbaşa obyekte mapping

### Yalniz Laravel-de (ve ya daha asandir):
- **`$request->boolean()`**, **`$request->integer()`** - Tip cevirmeli helper-ler
- **`$request->filled()`**, **`$request->missing()`** - Movcudluq yoxlamalari
- **`back()`** - Evvelki sehfeye qayitmaq
- **`redirect()->with()`** - Flash session melumati ile redirect
- **Route Model Binding** - URL parametrindan avtomatik model tapma
- **`$request->validated()`** - Yalniz validation kecmis saheleri almaq
- **Avtomatik cookie encryption** - Butun cookie-ler default olaraq sifrelenır
- **`$request->string()`, `$request->date()`** - Stringable ve Carbon obyekti qaytaran helper-ler
