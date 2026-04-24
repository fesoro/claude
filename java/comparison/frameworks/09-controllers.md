# Controllerlər: Spring vs Laravel

> **Seviyye:** Beginner ⭐

## Giris

Controller web tetbiqinin "giris qapisi"dir - HTTP sorgularini qebul edir, lazimi emalari baslayir ve cavab qaytarir. Her iki framework-de controller MVC arxitekturasinin "C" hissesidir, amma tanimlanma ve istifade usullari ferqlidir.

Spring-de controllerlər annotasiya esasli isleyir ve iki esas novu var: `@Controller` (view qaytarir) ve `@RestController` (data qaytarir). Laravel-de ise controllerlər adi PHP sinifleridire ve resource controller, invokable controller kimi xususi novleri var.

## Spring-de Istifadesi

### @Controller vs @RestController

```java
// @Controller - View qaytarir (Thymeleaf, Freemarker)
@Controller
@RequestMapping("/users")
public class UserViewController {

    private final UserService userService;

    public UserViewController(UserService userService) {
        this.userService = userService;
    }

    // Thymeleaf template qaytarir
    @GetMapping
    public String index(Model model) {
        model.addAttribute("users", userService.findAll());
        return "users/index";  // resources/templates/users/index.html
    }

    @GetMapping("/{id}")
    public String show(@PathVariable Long id, Model model) {
        User user = userService.findById(id);
        model.addAttribute("user", user);
        return "users/show";
    }

    @PostMapping
    public String store(@Valid @ModelAttribute CreateUserForm form, BindingResult result) {
        if (result.hasErrors()) {
            return "users/create";  // Formani xetalarla geri goster
        }
        userService.create(form);
        return "redirect:/users";  // Redirect et
    }
}

// @RestController = @Controller + @ResponseBody (butun metodlarda)
// JSON/XML data qaytarir
@RestController
@RequestMapping("/api/users")
public class UserApiController {

    private final UserService userService;

    public UserApiController(UserService userService) {
        this.userService = userService;
    }

    @GetMapping
    public List<UserDTO> index() {
        // Avtomatik JSON-a cevrilerek qaytarilir
        return userService.findAll();
    }

    @GetMapping("/{id}")
    public UserDTO show(@PathVariable Long id) {
        return userService.findById(id);
    }
}
```

### @ResponseBody

`@ResponseBody` annotasiyasi metodun qaytardigi obyektin birbase HTTP cavab body-sine yazilacagini bildirir (JSON/XML olaraq):

```java
@Controller
@RequestMapping("/api/mixed")
public class MixedController {

    // Bu view qaytarir
    @GetMapping("/page")
    public String page() {
        return "some-page";
    }

    // Bu JSON qaytarir (@ResponseBody sayesinde)
    @GetMapping("/data")
    @ResponseBody
    public Map<String, String> data() {
        return Map.of("status", "ok");
    }
}
```

### ResponseEntity

`ResponseEntity` HTTP cavabinin butun aspektlerini (status kodu, header-ler, body) idarye etmeye imkan verir:

```java
@RestController
@RequestMapping("/api/products")
public class ProductController {

    private final ProductService productService;

    public ProductController(ProductService productService) {
        this.productService = productService;
    }

    @GetMapping
    public ResponseEntity<List<Product>> index() {
        List<Product> products = productService.findAll();
        return ResponseEntity.ok(products);  // 200 OK
    }

    @GetMapping("/{id}")
    public ResponseEntity<Product> show(@PathVariable Long id) {
        return productService.findById(id)
                .map(ResponseEntity::ok)                           // 200 tapilsa
                .orElse(ResponseEntity.notFound().build());        // 404 tapilmasa
    }

    @PostMapping
    public ResponseEntity<Product> store(@Valid @RequestBody CreateProductRequest request) {
        Product product = productService.create(request);
        URI location = URI.create("/api/products/" + product.getId());
        return ResponseEntity
                .created(location)                                 // 201 Created
                .header("X-Custom-Header", "deger")                // Ozal header
                .body(product);
    }

    @PutMapping("/{id}")
    public ResponseEntity<Product> update(
            @PathVariable Long id,
            @Valid @RequestBody UpdateProductRequest request) {
        try {
            Product product = productService.update(id, request);
            return ResponseEntity.ok(product);                     // 200 OK
        } catch (ResourceNotFoundException e) {
            return ResponseEntity.notFound().build();              // 404
        }
    }

    @DeleteMapping("/{id}")
    public ResponseEntity<Void> delete(@PathVariable Long id) {
        productService.delete(id);
        return ResponseEntity.noContent().build();                 // 204 No Content
    }

    // Fayl yuklemek
    @GetMapping("/{id}/image")
    public ResponseEntity<byte[]> getImage(@PathVariable Long id) {
        byte[] image = productService.getImage(id);
        return ResponseEntity.ok()
                .contentType(MediaType.IMAGE_PNG)
                .contentLength(image.length)
                .body(image);
    }
}
```

### DTO (Data Transfer Object) Pattern

Spring-de DTO-lar model ile controller arasinda data dasimaq ucun genis istifade olunur:

```java
// Request DTO
public record CreateUserRequest(
    @NotBlank String name,
    @Email String email,
    @Size(min = 8) String password
) {}

// Response DTO
public record UserResponse(
    Long id,
    String name,
    String email,
    LocalDateTime createdAt
) {
    public static UserResponse from(User user) {
        return new UserResponse(
            user.getId(),
            user.getName(),
            user.getEmail(),
            user.getCreatedAt()
        );
    }
}

// Controller-de istifade
@RestController
@RequestMapping("/api/users")
public class UserController {

    @PostMapping
    public ResponseEntity<UserResponse> store(@Valid @RequestBody CreateUserRequest request) {
        User user = userService.create(request);
        return ResponseEntity.status(201).body(UserResponse.from(user));
    }

    @GetMapping
    public ResponseEntity<List<UserResponse>> index() {
        List<UserResponse> users = userService.findAll()
                .stream()
                .map(UserResponse::from)
                .toList();
        return ResponseEntity.ok(users);
    }
}
```

### Exception Handling

```java
// Qlobal xeta idare etme
@RestControllerAdvice
public class GlobalExceptionHandler {

    @ExceptionHandler(ResourceNotFoundException.class)
    public ResponseEntity<ErrorResponse> handleNotFound(ResourceNotFoundException ex) {
        ErrorResponse error = new ErrorResponse(
            HttpStatus.NOT_FOUND.value(),
            ex.getMessage(),
            LocalDateTime.now()
        );
        return ResponseEntity.status(404).body(error);
    }

    @ExceptionHandler(MethodArgumentNotValidException.class)
    public ResponseEntity<Map<String, String>> handleValidation(MethodArgumentNotValidException ex) {
        Map<String, String> errors = new HashMap<>();
        ex.getBindingResult().getFieldErrors().forEach(fieldError ->
            errors.put(fieldError.getField(), fieldError.getDefaultMessage())
        );
        return ResponseEntity.badRequest().body(errors);
    }

    @ExceptionHandler(Exception.class)
    public ResponseEntity<ErrorResponse> handleGeneral(Exception ex) {
        ErrorResponse error = new ErrorResponse(
            500,
            "Daxili server xetasi",
            LocalDateTime.now()
        );
        return ResponseEntity.status(500).body(error);
    }
}

// Xususi exception
public class ResourceNotFoundException extends RuntimeException {
    public ResourceNotFoundException(String resource, Long id) {
        super(resource + " tapilmadi: ID=" + id);
    }
}

// record ErrorResponse(int status, String message, LocalDateTime timestamp) {}
```

## Laravel-de Istifadesi

### Esas Controller

```php
// app/Http/Controllers/UserController.php
namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    public function index(): JsonResponse
    {
        $users = $this->userService->getAll();
        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        // Route model binding - $user avtomatik DB-den tapilir
        return response()->json($user);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8',
        ]);

        $user = $this->userService->create($validated);

        return response()->json($user, 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
        ]);

        $user = $this->userService->update($user, $validated);

        return response()->json($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->userService->delete($user);

        return response()->json(null, 204);
    }
}
```

### Resource Controller

Artisan ile yaradilir ve CRUD ucun hazir strukturu var:

```bash
php artisan make:controller ProductController --resource
php artisan make:controller ProductController --api  # create/edit olmadan
```

```php
// php artisan make:controller ProductController --api neticesi
namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with('category')
            ->paginate(15);

        return response()->json($products);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'category_id' => 'required|exists:categories,id',
        ]);

        $product = Product::create($validated);

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        $product->load('category', 'reviews');

        return response()->json($product);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
        ]);

        $product->update($validated);

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->json(null, 204);
    }
}
```

### Invokable Controller

Tek bir is goren controller ucun:

```bash
php artisan make:controller ShowDashboardController --invokable
```

```php
// Yalniz bir metodu var: __invoke
namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class ShowDashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    public function __invoke(): JsonResponse
    {
        $stats = $this->dashboardService->getStats();

        return response()->json([
            'total_users' => $stats->totalUsers,
            'total_orders' => $stats->totalOrders,
            'revenue' => $stats->revenue,
            'recent_orders' => $stats->recentOrders,
        ]);
    }
}

// Route-da istifade - metod adi yazmaq lazim deyil
Route::get('/dashboard', ShowDashboardController::class);
```

### Form Requests

Validasiya mentiqini controller-den ayirmaq ucun:

```bash
php artisan make:request StoreProductRequest
```

```php
// app/Http/Requests/StoreProductRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Istifadecinin bu sorguya icazesi var mi?
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Product::class);
    }

    /**
     * Validasiya qaydalari
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'category_id' => ['required', 'exists:categories,id'],
            'tags' => ['sometimes', 'array', 'max:10'],
            'tags.*' => ['string', 'max:50'],
            'images' => ['sometimes', 'array', 'max:5'],
            'images.*' => ['image', 'mimes:jpg,png,webp', 'max:2048'],
        ];
    }

    /**
     * Ozellesmis xeta mesajlari
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Mehsulun adi mecburidir.',
            'price.min' => 'Qiymet menfi ola bilmez.',
            'category_id.exists' => 'Secilmis kateqoriya movcud deyil.',
        ];
    }

    /**
     * Validasiyadan sonra datani hazirlama
     */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);
        $data['slug'] = Str::slug($data['name']);
        return $data;
    }
}

// Controller-de istifade - otomatik validasiya olur
class ProductController extends Controller
{
    public function store(StoreProductRequest $request): JsonResponse
    {
        // Bura catibsa, validasiya kecib demekdir
        $product = Product::create($request->validated());
        return response()->json($product, 201);
    }
}
```

### API Resource (Cavab formatlama)

```php
// php artisan make:resource UserResource
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'avatar_url' => $this->avatar
                ? asset('storage/' . $this->avatar)
                : null,
            'posts_count' => $this->whenCounted('posts'),
            'role' => $this->whenLoaded('role', fn () => [
                'id' => $this->role->id,
                'name' => $this->role->name,
            ]),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

// Collection Resource
class UserCollection extends ResourceCollection
{
    public function toArray($request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->total(),
                'per_page' => $this->perPage(),
            ],
        ];
    }
}

// Controller-de istifade
class UserController extends Controller
{
    public function index(): UserCollection
    {
        $users = User::withCount('posts')
            ->with('role')
            ->paginate(15);

        return new UserCollection($users);
    }

    public function show(User $user): UserResource
    {
        $user->loadCount('posts')->load('role');
        return new UserResource($user);
    }
}
```

### Response helper-ler

```php
class ApiController extends Controller
{
    // JSON cavab
    public function jsonExample(): JsonResponse
    {
        return response()->json(['key' => 'value'], 200);
    }

    // Fayl yuklemek
    public function download(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        return response()->download(storage_path('app/report.pdf'));
    }

    // Stream cavab
    public function stream(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return response()->stream(function () {
            echo "Boyuk data...";
        }, 200, ['Content-Type' => 'text/plain']);
    }

    // Redirect
    public function redirectExample()
    {
        return redirect()->route('users.index');
        // ve ya
        return redirect()->back()->with('success', 'Ugurla saxlandi!');
    }

    // Xususi header-ler
    public function withHeaders(): JsonResponse
    {
        return response()
            ->json(['data' => 'value'])
            ->header('X-Custom', 'deger')
            ->header('X-Request-Id', Str::uuid());
    }
}
```

### Exception Handling

```php
// app/Exceptions/Handler.php (Laravel 10 ve evvel)
// Laravel 11-de bootstrap/app.php-de

// bootstrap/app.php (Laravel 11+)
use Illuminate\Foundation\Configuration\Exceptions;

->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (ModelNotFoundException $e) {
        return response()->json([
            'message' => 'Resurs tapilmadi',
        ], 404);
    });

    $exceptions->render(function (AuthorizationException $e) {
        return response()->json([
            'message' => 'Icazeniz yoxdur',
        ], 403);
    });

    $exceptions->render(function (ValidationException $e) {
        return response()->json([
            'message' => 'Validasiya xetasi',
            'errors' => $e->errors(),
        ], 422);
    });
})
```

## Esas Ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Controller novu** | `@Controller` (view), `@RestController` (API) | Tek sinif, cavab novune gore deyisir |
| **JSON cavab** | `@RestController` ve ya `@ResponseBody` | `response()->json()` |
| **View cavab** | `@Controller` + String qaytarma | `view()` helper |
| **Status kodu** | `ResponseEntity.status(201).body(...)` | `response()->json($data, 201)` |
| **Validasiya** | `@Valid` + DTO | `$request->validate()` ve ya FormRequest |
| **CRUD generator** | Yoxdur | `--resource` / `--api` flag |
| **Tek metod controller** | Yoxdur (daxili) | Invokable controller (`__invoke`) |
| **Cavab formatlama** | DTO + Jackson | API Resource sinfleri |
| **Exception handling** | `@RestControllerAdvice` | `Exceptions` handler |
| **Route model binding** | Yoxdur | Avtomatik model injection |
| **Fayl yuklemek** | `ResponseEntity<byte[]>` | `response()->download()` |

## Niye Bele Ferqler Var?

### Spring-in yanasmasi

1. **`@Controller` vs `@RestController` ayriligi**: Spring tarixi olaraq server-side rendering (JSP, Thymeleaf) ile baslayib. REST API-ler sonradan elave olunub. Ona gore iki ferqli annotasiya var - geriye uygunluq saxlanib.

2. **ResponseEntity**: Java-nin guclu tip sistemi sayesinde HTTP cavabinin butun hisselerini (status, headers, body) bir obyektde ifade etmek mumkundur. Bu, compile zamani yoxlama verir.

3. **DTO pattern**: Java-da entity-ni birbase JSON olaraq qaytarmaq tehlukelidir (lazy loading, circular reference, hassas melumatlar). DTO-lar bu problemleri hell edir. Java record-lar (Java 16+) bu prosesi asanlasdirib.

4. **`@RestControllerAdvice`**: Merkezi xeta idare etme - butun controller-ler ucun bir yerde exception handling. Bu, AOP (Aspect-Oriented Programming) felsefesinin tezahuurudur.

### Laravel-in yanasmasi

1. **Tek controller tipi**: Laravel-de controller sadece bir sinifdir. `response()->json()` ve ya `view()` cagiraraq cavab novunu secirsiniz. Ayri annotasiya lazim deyil - bu, PHP-nin dinamik tebietine uygunudur.

2. **Form Request**: Validasiya mentiqini controller-den ayirmaq ucun ayri sinif. Bu, Single Responsibility prinsipine uygunudur ve controller-i temiz saxlayir. Spring-de validasiya DTO-nun icinde annotasiya ile olur.

3. **Invokable Controller**: Bezi endpoint-ler ucun tam bir sinif yaratmaq lazim deyil - tek metod kifayetdir. `__invoke()` PHP-nin magic metodu ile bu asanliqla olur.

4. **API Resource**: Laravel-de model birbase JSON-a cevrile biler, amma API Resource sinfleri cavab formatini ozellesdirir, `whenLoaded()` kimi serti daxiletmelere imkan verir. Bu, Spring-in DTO-larina alternativdir.

5. **Resource Controller**: `php artisan make:controller --resource` CRUD ucun hazir struktur yaradir. Bu, Laravel-in "convention over configuration" ve developer productiviyy felsefesini eks etdirir.

## Hansi Framework-de Var, Hansinda Yoxdur?

### Yalniz Spring-de olan xususiyyetler

- **`@RestControllerAdvice`**: Merkezi, qlobal exception handling sinfi. Laravel-de bu `Handler.php` ve ya `bootstrap/app.php`-de ede biler, amma Spring-in yanasmasi daha strukturludur.

- **`ResponseEntity<T>` generics**: Tip tehlukesizliyi ile cavab body-sinin tipini mueyyenlesdirmek. Meselen `ResponseEntity<List<UserDTO>>` qaytarma tipi daqiq gorunur.

- **Content negotiation**: Eyni endpoint-den `Accept` header-e gore JSON ve ya XML qaytarmaq. `produces = {APPLICATION_JSON, APPLICATION_XML}`.

- **`@ModelAttribute`**: Form data-sini avtomatik olaraq Java obyektine cevirmek (view-based controller-lerde).

### Yalniz Laravel-de olan xususiyyetler

- **Invokable Controller**: Tek metodu olan controller sinfi. Spring-de bele daxili konsept yoxdur.

- **Form Request sinfleri**: Validasiya + avtorizasiya mentiqini ayri sinifde saxlamaq. Spring-de validasiya DTO annotasiyalari ile olur.

- **API Resource / Resource Collection**: Cavab formatini ayri sinifde tanimlamaq, serti daxiletmeler (`whenLoaded`, `whenCounted`).

- **`response()->download()`**: Bir setirde fayl yukleme cavabi. Spring-de header-leri elle qurmaq lazimdir.

- **Route Model Binding controller-de**: `show(User $user)` yazmaqla Laravel avtomatik DB-den modeli tapir. Spring-de service-de `findById()` cagirilir.
