# Routing: Spring vs Laravel

## Giris

Routing (marsrutlama) web tetbiqinin hansi URL-in hansi koda yonlendirileceyini mueyyenlesdiren mexanizmdir. Her HTTP sorgusu bir marsruta uygun gelir ve mueyyen bir controller/metod onu hell edir.

Spring ve Laravel marsrutlamaya tamam ferqli yanasmalar getirmisdir: Spring annotasiyalari controller siniflerinin icinde istifade edir, Laravel ise ayri marsrut fayllarinda merkezi sekilde tanimladir.

## Spring-de Istifadesi

Spring-de marsrutlar controller siniflerinde annotasiyalar vasitesile tanimlanir.

### Esas annotasiyalar

```java
@RestController
@RequestMapping("/api/users")  // Prefiks - butun metodlar /api/users ile baslayir
public class UserController {

    // GET /api/users
    @GetMapping
    public ResponseEntity<List<User>> index() {
        return ResponseEntity.ok(userService.findAll());
    }

    // GET /api/users/5
    @GetMapping("/{id}")
    public ResponseEntity<User> show(@PathVariable Long id) {
        return userService.findById(id)
                .map(ResponseEntity::ok)
                .orElse(ResponseEntity.notFound().build());
    }

    // POST /api/users
    @PostMapping
    public ResponseEntity<User> store(@RequestBody @Valid CreateUserRequest request) {
        User user = userService.create(request);
        return ResponseEntity.status(HttpStatus.CREATED).body(user);
    }

    // PUT /api/users/5
    @PutMapping("/{id}")
    public ResponseEntity<User> update(@PathVariable Long id, @RequestBody @Valid UpdateUserRequest request) {
        User user = userService.update(id, request);
        return ResponseEntity.ok(user);
    }

    // DELETE /api/users/5
    @DeleteMapping("/{id}")
    public ResponseEntity<Void> delete(@PathVariable Long id) {
        userService.delete(id);
        return ResponseEntity.noContent().build();
    }
}
```

### @RequestMapping detallari

```java
@RestController
public class ProductController {

    // Bir nece HTTP metodu qebul eden marsrut
    @RequestMapping(value = "/api/products", method = {RequestMethod.GET, RequestMethod.HEAD})
    public List<Product> listProducts() {
        return productService.findAll();
    }

    // Produces - cavab formati
    @GetMapping(value = "/api/products/{id}", produces = MediaType.APPLICATION_JSON_VALUE)
    public Product getProduct(@PathVariable Long id) {
        return productService.findById(id);
    }

    // Consumes - qebul edilen format
    @PostMapping(value = "/api/products", consumes = MediaType.APPLICATION_JSON_VALUE)
    public Product createProduct(@RequestBody Product product) {
        return productService.save(product);
    }
}
```

### @PathVariable ve @RequestParam

```java
@RestController
@RequestMapping("/api")
public class SearchController {

    // Path variable: /api/users/5
    @GetMapping("/users/{id}")
    public User getUser(@PathVariable Long id) {
        return userService.findById(id);
    }

    // Bir nece path variable: /api/users/5/orders/10
    @GetMapping("/users/{userId}/orders/{orderId}")
    public Order getUserOrder(
            @PathVariable Long userId,
            @PathVariable Long orderId) {
        return orderService.findByUserAndId(userId, orderId);
    }

    // Query parametrleri: /api/products?category=electronics&page=2&size=10
    @GetMapping("/products")
    public Page<Product> searchProducts(
            @RequestParam(required = false) String category,
            @RequestParam(defaultValue = "0") int page,
            @RequestParam(defaultValue = "10") int size,
            @RequestParam(defaultValue = "name") String sortBy) {
        return productService.search(category, PageRequest.of(page, size, Sort.by(sortBy)));
    }

    // Path variable adi ferqli olanda
    @GetMapping("/categories/{category-slug}/products")
    public List<Product> getByCategory(@PathVariable("category-slug") String slug) {
        return productService.findByCategory(slug);
    }

    // Optional path variable
    @GetMapping({"/reports", "/reports/{year}"})
    public List<Report> getReports(@PathVariable(required = false) Integer year) {
        if (year == null) {
            return reportService.getCurrentYearReports();
        }
        return reportService.getByYear(year);
    }
}
```

### Regex ile path variable

```java
@RestController
public class FileController {

    // Yalniz reqemler: /api/users/123
    @GetMapping("/api/users/{id:\\d+}")
    public User getUser(@PathVariable Long id) {
        return userService.findById(id);
    }

    // Yalniz herfler: /api/categories/electronics
    @GetMapping("/api/categories/{slug:[a-z-]+}")
    public Category getCategory(@PathVariable String slug) {
        return categoryService.findBySlug(slug);
    }
}
```

### Request header ve cookie

```java
@RestController
public class ApiController {

    @GetMapping("/api/data")
    public ResponseEntity<Data> getData(
            @RequestHeader("Authorization") String authHeader,
            @RequestHeader(value = "Accept-Language", defaultValue = "az") String lang,
            @CookieValue(value = "session_id", required = false) String sessionId) {
        // ...
    }
}
```

## Laravel-de Istifadesi

Laravel-de marsrutlar `routes/` qovlugundaki ayri fayllarda tanimlanir.

### Esas marsrut tanimlari

```php
// routes/api.php

use App\Http\Controllers\UserController;

// GET /api/users
Route::get('/users', [UserController::class, 'index']);

// GET /api/users/5
Route::get('/users/{id}', [UserController::class, 'show']);

// POST /api/users
Route::post('/users', [UserController::class, 'store']);

// PUT /api/users/5
Route::put('/users/{id}', [UserController::class, 'update']);

// DELETE /api/users/5
Route::delete('/users/{id}', [UserController::class, 'destroy']);

// PATCH /api/users/5
Route::patch('/users/{id}', [UserController::class, 'partialUpdate']);

// Bir nece HTTP metodu
Route::match(['get', 'post'], '/search', [SearchController::class, 'handle']);

// Butun HTTP metodlari
Route::any('/webhook', [WebhookController::class, 'handle']);
```

### Resource Routes

Bir setirle CRUD marsrutlarinin hamisi yaradilir:

```php
// 7 marsrut yaradir: index, create, store, show, edit, update, destroy
Route::resource('products', ProductController::class);

// Yalniz API ucun (create ve edit olmadan - onlar form gosterir)
Route::apiResource('products', ProductController::class);

// Bir nece resource bir anda
Route::apiResources([
    'users' => UserController::class,
    'products' => ProductController::class,
    'orders' => OrderController::class,
]);

// Yalniz mueyyen metodlar
Route::resource('posts', PostController::class)->only(['index', 'show']);

// Mueyyen metodlardan basqa hamisi
Route::resource('posts', PostController::class)->except(['destroy']);
```

`Route::apiResource('products', ProductController::class)` asagidaki marsrutlari yaradir:

| HTTP Metod | URI | Controller Metod | Route Adi |
|---|---|---|---|
| GET | /api/products | index | products.index |
| POST | /api/products | store | products.store |
| GET | /api/products/{product} | show | products.show |
| PUT/PATCH | /api/products/{product} | update | products.update |
| DELETE | /api/products/{product} | destroy | products.destroy |

### Named Routes (Adli Marsrutlar)

```php
Route::get('/user/profile', [ProfileController::class, 'show'])->name('profile.show');

Route::get('/orders/{order}/invoice', [InvoiceController::class, 'show'])
    ->name('orders.invoice');

// Istifade: URL yaratmaq
$url = route('profile.show');  // /user/profile
$url = route('orders.invoice', ['order' => 5]);  // /orders/5/invoice

// Redirect
return redirect()->route('profile.show');

// Blade template-de
<a href="{{ route('profile.show') }}">Profil</a>
```

### Route Parameters

```php
// Mecburi parametr
Route::get('/users/{id}', function (int $id) {
    return User::findOrFail($id);
});

// Istege bagli parametr
Route::get('/posts/{year?}', function (?int $year = null) {
    $year ??= now()->year;
    return Post::whereYear('created_at', $year)->get();
});

// Regex mehdudiyyeti
Route::get('/users/{id}', [UserController::class, 'show'])
    ->where('id', '[0-9]+');

Route::get('/categories/{slug}', [CategoryController::class, 'show'])
    ->where('slug', '[a-z\-]+');

// Bir nece parametr ucun regex
Route::get('/posts/{year}/{month}', [PostController::class, 'archive'])
    ->where(['year' => '[0-9]{4}', 'month' => '[0-9]{1,2}']);

// Qlobal regex (RouteServiceProvider-de)
// Route::pattern('id', '[0-9]+');  // Butun {id} parametrlerine tetbiq olunur

// Helper metodlar
Route::get('/users/{id}', [UserController::class, 'show'])
    ->whereNumber('id');

Route::get('/categories/{slug}', [CategoryController::class, 'show'])
    ->whereAlpha('slug');

Route::get('/posts/{slug}', [PostController::class, 'show'])
    ->whereAlphaNumeric('slug');
```

### Route Model Binding

Laravel URL parametrini avtomatik olaraq Eloquent modeline cevirir:

```php
// Implicit binding - parametr adi model adi ile eynidir
Route::get('/users/{user}', function (User $user) {
    // Laravel avtomatik User::findOrFail($user) edir
    // 404 qaytarir eger tapilmasa
    return $user;
});

// Ferqli sutun ile
Route::get('/users/{user:slug}', function (User $user) {
    // User::where('slug', $value)->firstOrFail()
    return $user;
});

// Nested binding - ebeveyn-ovlad munasibeti
Route::get('/users/{user}/posts/{post:slug}', function (User $user, Post $post) {
    // Post yalniz hemin user-in postlari arasinda axtarilir
    return $post;
});

// Custom resolution logic (Model-in icinde)
// User.php
public function resolveRouteBinding($value, $field = null): ?self
{
    return $this->where($field ?? 'id', $value)
                ->where('active', true)
                ->firstOrFail();
}

// Explicit binding (RouteServiceProvider-de)
public function boot(): void
{
    Route::model('user', User::class);

    Route::bind('product', function (string $value) {
        return Product::where('slug', $value)
                      ->where('published', true)
                      ->firstOrFail();
    });
}
```

### Route Groups

```php
// Prefiks qrupu
Route::prefix('api/v1')->group(function () {
    Route::get('/users', [UserController::class, 'index']);       // /api/v1/users
    Route::get('/products', [ProductController::class, 'index']); // /api/v1/products
});

// Middleware qrupu
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::apiResource('orders', OrderController::class);
});

// Her seyi birlesdirib
Route::prefix('api/v1')
    ->middleware(['auth:sanctum', 'throttle:60,1'])
    ->name('api.v1.')
    ->group(function () {
        Route::apiResource('users', UserController::class);       // api.v1.users.index
        Route::apiResource('products', ProductController::class); // api.v1.products.index

        Route::prefix('admin')
            ->middleware('admin')
            ->name('admin.')
            ->group(function () {
                Route::get('/dashboard', [AdminController::class, 'dashboard'])
                    ->name('dashboard');  // api.v1.admin.dashboard
            });
    });

// Controller qrupu
Route::controller(OrderController::class)->group(function () {
    Route::get('/orders', 'index');
    Route::post('/orders', 'store');
    Route::get('/orders/{id}', 'show');
});
```

### Closure Marsrutlari

```php
// Sade marsrut - controller olmadan
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
});

// Parametrli closure
Route::get('/greet/{name}', function (string $name) {
    return "Salam, {$name}!";
});
```

### Fallback ve Redirect marsrutlari

```php
// 404 ucun fallback
Route::fallback(function () {
    return response()->json(['message' => 'Marsrut tapilmadi'], 404);
});

// Sadece redirect
Route::redirect('/old-page', '/new-page', 301);

// Sadece view goster
Route::view('/about', 'pages.about', ['title' => 'Haqqimizda']);
```

## Esas Ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Marsrut yeri** | Controller siniflerinde (annotasiya) | Ayri `routes/` fayllarinda |
| **Tanimlama usulu** | `@GetMapping`, `@PostMapping` | `Route::get()`, `Route::post()` |
| **URL parametri** | `@PathVariable` | `{parametr}` + type-hint |
| **Query parametri** | `@RequestParam` | `$request->query()` / `$request->input()` |
| **CRUD marsrutlar** | Elle yazilir | `Route::apiResource()` bir setirde |
| **Adli marsrutlar** | Yoxdur (daxili) | `->name()` ile adinlama + `route()` helper |
| **Marsrut qruplari** | `@RequestMapping` prefix | `Route::group()` / `Route::prefix()` |
| **Model binding** | Yoxdur (daxili) | Avtomatik implicit/explicit binding |
| **Regex** | Path variable-da `{id:\\d+}` | `->where()` metodu |
| **Versiyalama** | `@RequestMapping("/api/v1")` | `Route::prefix('api/v1')` |
| **Marsrut listesi** | Yoxdur (daxili emr) | `php artisan route:list` |
| **Marsrut kesleme** | Lazim deyil | `php artisan route:cache` |

## Niye Bele Ferqler Var?

### Spring: "Marsrut controller ile birdir"

Spring-de marsrutlar controller siniflerinde yazilir, cunki:

1. **Annotasiya gucu**: Java-da annotasiyalar cok gucludur ve compile zamani islenilir. `@GetMapping("/users/{id}")` hem marsrutu tanimlandir, hem de hansi metodun cagirilacagini gosterir - hamisi bir yerde.

2. **Type safety**: `@PathVariable Long id` yazanda Java compile zamani tipi yoxlayir. Sehv tip yazmaq mumkun deyil.

3. **IDE desteyiir**: IntelliJ kimi IDE-ler annotasiyalari oxuyub marsrutlari tam derk edir - auto-complete, navigation, refactoring hamisi isleyir.

4. **Self-documenting**: Controller-in ozune baxanda butun marsrutlarini gorursen. Baska fayla baxmaga ehtiyac yoxdur.

### Laravel: "Marsrutlar merkezi bir yerdedir"

Laravel marsrutlari ayri fayllarda saxlayir, cunki:

1. **Umumi menzere**: `routes/api.php` faylina baxanda tetbiqin butun API marsrutlarini gorursen. Bu, boyuk layihelerde cok faydalidir.

2. **Cevik sintaksis**: `Route::apiResource('users', UserController::class)` bir setir ile 5 marsrut yaradir. Bunu annotasiya ile etmek olmaz.

3. **Middleware ve prefix qruplari**: Marsrutlari qruplara bolmek ve hamisina middleware tetbiq etmek cox asandir.

4. **Marsrut kesleme**: `php artisan route:cache` butun marsrutlari serializasiya edir ve performance artir. Closure marsrutlari keslene bilmir - bu, controller istifadesini tesviqlendrir.

5. **Route model binding**: URL parametrini avtomatik olaraq DB-den modele cevirmek guclu xususiyyetdir. Spring-de bunu elle yazmaq lazimdir.

## Hansi Framework-de Var, Hansinda Yoxdur?

### Yalniz Laravel-de olan xususiyyetler

- **Route Model Binding**: `Route::get('/users/{user}', ...)` avtomatik olaraq `User::findOrFail()` edir. Spring-de bunu service-de elle yazmaq lazimdir.

- **Resource Routes**: `Route::apiResource()` ile bir setirde 5 CRUD marsrutu. Spring-de her birini ayrica annotasiya ile yazmaq lazimdir.

- **Named Routes**: Marsruta ad vermek ve `route('users.show', 5)` ile URL yaratmaq. Spring-de URL-leri elle yazmaq lazimdir.

- **`php artisan route:list`**: Butun marsrutlarin siyahisini gormek ucun emr. Spring-de Actuator elave etmek ve ya IDE istifade etmek lazimdir.

- **Route::redirect() ve Route::view()**: Controller olmadan sadece redirect ve ya view qaytarmaq.

- **Fallback routes**: Hec bir marsrut uygun gelmedikde isleyen marsrut.

### Yalniz Spring-de olan xususiyyetler

- **`produces` ve `consumes`**: Marsrutun qebul etdiyi ve qaytardigi content type-i annotasiyada daqiq gostermek.

```java
@GetMapping(value = "/data", produces = {MediaType.APPLICATION_JSON_VALUE, MediaType.APPLICATION_XML_VALUE})
```

- **`@RequestHeader` ve `@CookieValue`**: Header ve cookie deyerlerini birbase metod parametri kimi almaq. Laravel-de `$request->header()` ve `$request->cookie()` ile alinir.

- **Compile zamani marsrut yoxlamasi**: Sehv annotasiya ve ya uygunsuz parametr tipi compile zamani xeta verir.

### Her ikisinde olan, amma ferqli isleyen

- **Middleware/Filter**: Spring-de `Filter` ve `HandlerInterceptor`, Laravel-de `Middleware` (ayri bolmede etraflii)
- **Versiyalama**: Her ikisinde URL prefiksi ile, amma Spring-de header-based versiyalama da genis yayilmisdir
- **Rate limiting**: Spring-de ucuncu teref kitabxana, Laravel-de `throttle` middleware daxili olaraq movcuddur
