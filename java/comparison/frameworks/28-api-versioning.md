# API Versioning (API Versiyalandirma)

> **Seviyye:** Intermediate ⭐⭐

## Giris

API-ler zamanla deyisir - yeni saheler elave olunur, kohne saheler silinir, cavab strukturu deyisir. Movcud musterileri qirmadan yeni versiyalar buraxmaq ucun API versiyalandirma lazimdir. Spring ve Laravel-de bir nece ferqli yanasma mumkundur: URL esasli, header esasli ve media type esasli. Heac bir framework built-in versiyalandirma sistemi teklif etmir - her ikisinde manual implementasiya lazimdir.

## Spring-de istifadesi

### URL esasli versiyalandirma (en populyar)

```java
// V1 Controller
@RestController
@RequestMapping("/api/v1/users")
public class UserControllerV1 {

    @GetMapping("/{id}")
    public UserResponseV1 getUser(@PathVariable Long id) {
        User user = userService.findById(id);
        return new UserResponseV1(
            user.getId(),
            user.getName(),
            user.getEmail()
        );
    }

    @GetMapping
    public List<UserResponseV1> listUsers() {
        return userService.findAll().stream()
            .map(u -> new UserResponseV1(u.getId(), u.getName(), u.getEmail()))
            .toList();
    }
}

// V1 Response
public record UserResponseV1(
    Long id,
    String name,
    String email
) {}
```

```java
// V2 Controller - yeni saheler, ferqli struktur
@RestController
@RequestMapping("/api/v2/users")
public class UserControllerV2 {

    @GetMapping("/{id}")
    public UserResponseV2 getUser(@PathVariable Long id) {
        User user = userService.findById(id);
        return UserResponseV2.from(user);
    }

    @GetMapping
    public Page<UserResponseV2> listUsers(Pageable pageable) {
        return userService.findAll(pageable)
            .map(UserResponseV2::from);
    }
}

// V2 Response - daha zengin struktur
public record UserResponseV2(
    Long id,
    String fullName,       // name -> fullName oldu
    String email,
    String avatarUrl,      // Yeni sahe
    boolean emailVerified, // Yeni sahe
    AddressResponse address, // Ic-ice obyekt elave olundu
    String createdAt
) {
    public static UserResponseV2 from(User user) {
        return new UserResponseV2(
            user.getId(),
            user.getFirstName() + " " + user.getLastName(),
            user.getEmail(),
            user.getAvatarUrl(),
            user.getEmailVerifiedAt() != null,
            AddressResponse.from(user.getAddress()),
            user.getCreatedAt().toString()
        );
    }
}
```

### Header esasli versiyalandirma

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    // X-API-Version header ile versiya secimi
    @GetMapping(value = "/{id}", headers = "X-API-Version=1")
    public UserResponseV1 getUserV1(@PathVariable Long id) {
        User user = userService.findById(id);
        return new UserResponseV1(user.getId(), user.getName(), user.getEmail());
    }

    @GetMapping(value = "/{id}", headers = "X-API-Version=2")
    public UserResponseV2 getUserV2(@PathVariable Long id) {
        User user = userService.findById(id);
        return UserResponseV2.from(user);
    }
}

// Musteri sorgusu:
// GET /api/users/1
// X-API-Version: 2
```

### Custom Media Type ile versiyalandirma

```java
@RestController
@RequestMapping("/api/users")
public class UserController {

    // Accept header ile versiya secimi
    @GetMapping(value = "/{id}",
        produces = "application/vnd.example.v1+json")
    public UserResponseV1 getUserV1(@PathVariable Long id) {
        return new UserResponseV1(userService.findById(id));
    }

    @GetMapping(value = "/{id}",
        produces = "application/vnd.example.v2+json")
    public UserResponseV2 getUserV2(@PathVariable Long id) {
        return UserResponseV2.from(userService.findById(id));
    }
}

// Musteri sorgusu:
// GET /api/users/1
// Accept: application/vnd.example.v2+json
```

### Versiya-agnostik service qati

```java
// Service qati versiyadan asili olmamalidir
@Service
public class UserService {

    private final UserRepository userRepository;

    // Eyni service hem V1, hem V2 controller istifade edir
    public User findById(Long id) {
        return userRepository.findById(id)
            .orElseThrow(() -> new UserNotFoundException(id));
    }

    public Page<User> findAll(Pageable pageable) {
        return userRepository.findAll(pageable);
    }

    public User createUser(String name, String email, String password) {
        // Business logic - versiyadan asili deyil
        User user = new User();
        user.setName(name);
        user.setEmail(email);
        user.setPassword(passwordEncoder.encode(password));
        return userRepository.save(user);
    }
}
```

### Interceptor ile versiya idare etme

```java
@Component
public class ApiVersionInterceptor implements HandlerInterceptor {

    @Override
    public boolean preHandle(HttpServletRequest request,
                              HttpServletResponse response,
                              Object handler) {

        String version = request.getHeader("X-API-Version");
        if (version == null) {
            version = "2"; // Default versiya
        }

        request.setAttribute("apiVersion", version);
        return true;
    }
}

// Controller-de istifade
@RestController
@RequestMapping("/api/users")
public class UserController {

    @GetMapping("/{id}")
    public ResponseEntity<?> getUser(@PathVariable Long id,
                                      @RequestAttribute String apiVersion) {
        User user = userService.findById(id);

        return switch (apiVersion) {
            case "1" -> ResponseEntity.ok(new UserResponseV1(user));
            case "2" -> ResponseEntity.ok(UserResponseV2.from(user));
            default -> ResponseEntity.ok(UserResponseV2.from(user));
        };
    }
}
```

### Kohne versiyanin legv edilmesi (Deprecation)

```java
@RestController
@RequestMapping("/api/v1/users")
public class UserControllerV1 {

    @GetMapping("/{id}")
    public ResponseEntity<UserResponseV1> getUser(@PathVariable Long id) {
        User user = userService.findById(id);

        return ResponseEntity.ok()
            .header("Deprecation", "true")
            .header("Sunset", "Sat, 01 Jan 2027 00:00:00 GMT")
            .header("Link", "</api/v2/users/" + id + ">; rel=\"successor-version\"")
            .body(new UserResponseV1(user));
    }
}
```

## Laravel-de istifadesi

### URL esasli versiyalandirma (route prefix)

```php
// routes/api.php

// V1 route-lari
Route::prefix('v1')->group(function () {
    Route::apiResource('users', App\Http\Controllers\Api\V1\UserController::class);
    Route::apiResource('products', App\Http\Controllers\Api\V1\ProductController::class);
});

// V2 route-lari
Route::prefix('v2')->group(function () {
    Route::apiResource('users', App\Http\Controllers\Api\V2\UserController::class);
    Route::apiResource('products', App\Http\Controllers\Api\V2\ProductController::class);
});
```

```php
// app/Http/Controllers/Api/V1/UserController.php
namespace App\Http\Controllers\Api\V1;

class UserController extends Controller
{
    public function show(User $user)
    {
        return new \App\Http\Resources\V1\UserResource($user);
    }

    public function index()
    {
        return \App\Http\Resources\V1\UserResource::collection(
            User::paginate(15)
        );
    }
}
```

```php
// app/Http/Controllers/Api/V2/UserController.php
namespace App\Http\Controllers\Api\V2;

class UserController extends Controller
{
    public function show(User $user)
    {
        $user->load(['address', 'roles']);
        return new \App\Http\Resources\V2\UserResource($user);
    }

    public function index(Request $request)
    {
        $users = User::query()
            ->with(['address', 'roles'])
            ->when($request->search, fn ($q, $s) =>
                $q->where('name', 'like', "%{$s}%"))
            ->paginate($request->input('per_page', 20));

        return \App\Http\Resources\V2\UserResource::collection($users);
    }
}
```

### Versiyalanmis API Resources

```php
// app/Http/Resources/V1/UserResource.php
namespace App\Http\Resources\V1;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
```

```php
// app/Http/Resources/V2/UserResource.php
namespace App\Http\Resources\V2;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->first_name . ' ' . $this->last_name,
            'email' => $this->email,
            'avatar_url' => $this->avatar_url,
            'email_verified' => $this->email_verified_at !== null,
            'address' => new AddressResource($this->whenLoaded('address')),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
```

### Header esasli versiyalandirma (Middleware ile)

```php
// app/Http/Middleware/ApiVersion.php
class ApiVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $version = $request->header('X-API-Version', '2'); // Default: v2

        // Versiya duzgun olmalidi
        if (!in_array($version, ['1', '2'])) {
            abort(400, 'Yanlim API versiyasi: ' . $version);
        }

        // Request-e versiyani elave edirik
        $request->merge(['api_version' => $version]);

        $response = $next($request);

        // Cavaba versiya header-i elave edirik
        $response->headers->set('X-API-Version', $version);

        return $response;
    }
}
```

```php
// routes/api.php
Route::middleware('api.version')->group(function () {
    Route::get('/users/{user}', [UserController::class, 'show']);
});
```

```php
// Eyni controller-de versiyaya gore ferqli cavab
class UserController extends Controller
{
    public function show(Request $request, User $user)
    {
        $version = $request->input('api_version', '2');

        return match ($version) {
            '1' => new \App\Http\Resources\V1\UserResource($user),
            '2' => new \App\Http\Resources\V2\UserResource($user->load('address')),
            default => new \App\Http\Resources\V2\UserResource($user),
        };
    }
}
```

### Deprecation middleware

```php
class DeprecatedApiMiddleware
{
    public function handle(Request $request, Closure $next,
                           string $sunsetDate): Response
    {
        $response = $next($request);

        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Sunset', $sunsetDate);

        // Loqlayiriq - neacae adam heleade kohne versiya istifade edir
        Log::info('Deprecated API istifade olundu', [
            'path' => $request->path(),
            'user' => $request->user()?->id,
            'version' => 'v1',
        ]);

        return $response;
    }
}

// routes/api.php
Route::prefix('v1')
    ->middleware('deprecated:Sat, 01 Jan 2027 00:00:00 GMT')
    ->group(function () {
        Route::apiResource('users', V1\UserController::class);
    });
```

### Route model binding ile versiya

```php
// RouteServiceProvider ve ya bootstrap/app.php
Route::bind('user', function (string $value) {
    // V1 ve V2 eyni model istifade ede biler
    return User::findOrFail($value);
});
```

### Versiyalar arasi paylasdirma (DRY)

```php
// Base controller - paylasilmis meiq
namespace App\Http\Controllers\Api;

abstract class BaseUserController extends Controller
{
    protected function findUser(int $id): User
    {
        return User::findOrFail($id);
    }

    protected function queryUsers(Request $request)
    {
        return User::query()
            ->when($request->search, fn ($q, $s) =>
                $q->where('name', 'like', "%{$s}%"))
            ->when($request->active, fn ($q) =>
                $q->where('active', true));
    }
}

// V1
namespace App\Http\Controllers\Api\V1;

class UserController extends BaseUserController
{
    public function show(int $id)
    {
        return new V1\UserResource($this->findUser($id));
    }
}

// V2
namespace App\Http\Controllers\Api\V2;

class UserController extends BaseUserController
{
    public function show(int $id)
    {
        $user = $this->findUser($id);
        $user->load(['address', 'roles']);
        return new V2\UserResource($user);
    }
}
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **URL versiyalandirma** | `@RequestMapping("/api/v1/...")` | `Route::prefix('v1')` |
| **Header versiyalandirma** | `headers = "X-API-Version=1"` | Middleware ile |
| **Media type** | `produces = "...vnd.example.v1+json"` | Manual (az istifade olunur) |
| **Versiyali response** | Ayri DTO/Record sinfleri | Ayri API Resource sinifleri |
| **Built-in destlek** | Yoxdur | Yoxdur |
| **Deprecation** | Header ile manual | Middleware ile |
| **Route qruoplama** | Ayri controller sinifleri | `Route::prefix()` + `group()` |
| **Service qati** | Versiyadan asili deyil | Versiyadan asili deyil |

## Niye bele ferqler var?

**Ortaq felsefe:** Her iki framework-de API versiyalandirma built-in xususiyyet deyil. Bunun sebebi versiyalandirmanin "bir duzgun yolu" olmamasidir - her layihenin ehtiyaclari ferqlidir. URL esasli versiyalandirma en sade ve en cox istifade olunandir, amma REST puristleri bunu sevmez (URL resurs yerini gostermelidir, versiyanl yox).

**Spring-in ustunluyu:** Spring-de `headers` ve `produces` parametrleri ile controller metod seviyyesinde versiya secimi mumkundur. Bu, eyni URL-de ferqli versiyalari desteklemeye imkan verir. Amma bu daha murakkeb konfiqurasiya teleb edir.

**Laravel-in ustunluyu:** Laravel-in route qruoplamasi ve namespace sistemi versiyalandirma ucun coxlu uygundir. `Route::prefix('v1')->group(...)` ile butun V1 route-lari bir yerde toplanir. API Resource-lar namespace ile ayrilaraq temiz qovluq strukturu yaradir (`Resources/V1/`, `Resources/V2/`).

**Praktik tovsiye:** Her iki framework ucun en yaxsi praktika: Service qati versiyadan asili olmamalidir. Yalniz Controller ve Response (DTO/Resource) sinifleri versiyalanmaldir. Bu, business logic-in tekrarlanmasinin qarsisini alir.

## Hansi framework-de var, hansinda yoxdur?

- **`headers = "..."` route parametri** - Yalniz Spring-de. Route mapping-de header sertini birbaşa yazmaq.
- **`produces = "..."` media type** - Yalniz Spring-de. Accept header ile content negotiation.
- **Route prefix qruoplamasi** - Her ikisinde var, amma Laravel-de daha temiz sintaksis (`Route::prefix('v1')->group(...)`).
- **API Resource namespace** - Laravel-de resource-lari `V1/`, `V2/` qovluqlarina ayirmaq cox rahildir.
- **Interceptor/Middleware** - Her ikisinde var. Spring-de `HandlerInterceptor`, Laravel-de `Middleware`.
- **Deprecation header-leri** - Her ikisinde manual elave olunur, heac birinde built-in deyil.
- **Content negotiation** - Spring-de daha guclu, `produces` ve `consumes` ile ince nezaret mumkundur. Laravel-de `Accept` header ile manual yoxlama lazimdir.
