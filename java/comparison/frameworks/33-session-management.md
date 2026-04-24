# Session İdarəetməsi: Spring vs Laravel

> **Seviyye:** Intermediate ⭐⭐

## Giriş

Session (sessiya) istifadəçinin müxtəlif HTTP sorğuları arasında məlumatlarını saxlamaq üçün istifadə olunan mexanizmdir. HTTP protokolu stateless (vəziyyətsiz) olduğu üçün session-lar istifadəçinin kim olduğunu, səbətdə nə olduğunu və digər məlumatları yadda saxlamağa imkan verir. Spring və Laravel hər ikisi güclü session dəstəyi təklif edir, lakin yanaşmaları fərqlidir.

## Spring-də istifadəsi

### HttpSession (Servlet API)

Spring MVC-də session idarəetməsi Java Servlet API-nin `HttpSession` interfeysi üzərindən həyata keçirilir:

```java
@Controller
@RequestMapping("/cart")
public class CartController {

    // HttpSession-u birbaşa inject etmək
    @GetMapping
    public String viewCart(HttpSession session, Model model) {
        @SuppressWarnings("unchecked")
        List<CartItem> cart = (List<CartItem>) session.getAttribute("cart");

        if (cart == null) {
            cart = new ArrayList<>();
        }

        model.addAttribute("cart", cart);
        model.addAttribute("total", calculateTotal(cart));
        return "cart/view";
    }

    @PostMapping("/add")
    public String addToCart(@RequestParam Long productId,
                           @RequestParam int quantity,
                           HttpSession session) {
        @SuppressWarnings("unchecked")
        List<CartItem> cart = (List<CartItem>) session.getAttribute("cart");

        if (cart == null) {
            cart = new ArrayList<>();
        }

        Product product = productService.findById(productId);
        cart.add(new CartItem(product, quantity));

        // Session-a yazmaq
        session.setAttribute("cart", cart);

        return "redirect:/cart";
    }

    @PostMapping("/clear")
    public String clearCart(HttpSession session) {
        // Xüsusi atributu silmək
        session.removeAttribute("cart");
        return "redirect:/cart";
    }

    @PostMapping("/logout")
    public String logout(HttpSession session) {
        // Bütün session-u ləğv etmək
        session.invalidate();
        return "redirect:/login";
    }

    // Session ID və metadata
    @GetMapping("/info")
    @ResponseBody
    public Map<String, Object> sessionInfo(HttpSession session) {
        return Map.of(
            "sessionId", session.getId(),
            "creationTime", new Date(session.getCreationTime()),
            "lastAccessedTime", new Date(session.getLastAccessedTime()),
            "maxInactiveInterval", session.getMaxInactiveInterval(),
            "isNew", session.isNew()
        );
    }
}
```

### @SessionAttributes

`@SessionAttributes` annotasiyası controller səviyyəsində model atributlarını session-da saxlamağa imkan verir:

```java
@Controller
@RequestMapping("/orders")
@SessionAttributes({"orderForm", "selectedProducts"})
public class OrderController {

    // Çoxaddımlı form üçün model atributu
    @ModelAttribute("orderForm")
    public OrderForm createOrderForm() {
        return new OrderForm();
    }

    // Addım 1: Şəxsi məlumatlar
    @GetMapping("/step1")
    public String step1(@ModelAttribute("orderForm") OrderForm form) {
        return "orders/step1";
    }

    @PostMapping("/step1")
    public String processStep1(@ModelAttribute("orderForm") OrderForm form,
                               BindingResult result) {
        if (result.hasErrors()) {
            return "orders/step1";
        }
        return "redirect:/orders/step2";
    }

    // Addım 2: Çatdırılma
    @GetMapping("/step2")
    public String step2(@ModelAttribute("orderForm") OrderForm form) {
        // form avtomatik olaraq session-dan gəlir
        return "orders/step2";
    }

    @PostMapping("/step2")
    public String processStep2(@ModelAttribute("orderForm") OrderForm form,
                               BindingResult result) {
        if (result.hasErrors()) {
            return "orders/step2";
        }
        return "redirect:/orders/step3";
    }

    // Addım 3: Təsdiq və sifariş
    @PostMapping("/confirm")
    public String confirm(@ModelAttribute("orderForm") OrderForm form,
                         SessionStatus sessionStatus) {
        orderService.createOrder(form);

        // Session atributlarını təmizləmək
        sessionStatus.setComplete();

        return "redirect:/orders/success";
    }
}
```

### Spring Session (Redis, JDBC)

Defolt olaraq session serverin yaddaşında saxlanılır. Distributed (paylanmış) mühitdə Spring Session layihəsi istifadə olunur:

**Redis ilə session:**

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework.session</groupId>
    <artifactId>spring-session-data-redis</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-redis</artifactId>
</dependency>
```

```properties
# application.properties
spring.session.store-type=redis
spring.session.redis.namespace=myapp:session
spring.session.timeout=30m

spring.redis.host=localhost
spring.redis.port=6379
spring.redis.password=secret
```

**JDBC (verilənlər bazası) ilə session:**

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework.session</groupId>
    <artifactId>spring-session-jdbc</artifactId>
</dependency>
```

```properties
# application.properties
spring.session.store-type=jdbc
spring.session.jdbc.initialize-schema=always
spring.session.jdbc.table-name=SPRING_SESSION
spring.session.timeout=30m
```

Spring Session aktivləşdirildikdə, heç bir kod dəyişikliyi tələb olunmur - `HttpSession` API eyni qalır, yalnız saxlama yeri dəyişir.

### Session konfiqurasiyası

```properties
# application.properties
# Session timeout
server.servlet.session.timeout=30m

# Cookie parametrləri
server.servlet.session.cookie.name=JSESSIONID
server.servlet.session.cookie.http-only=true
server.servlet.session.cookie.secure=true
server.servlet.session.cookie.same-site=lax
server.servlet.session.cookie.max-age=1800

# Session tracking mode
server.servlet.session.tracking-modes=cookie
```

### SessionRegistry (Aktiv session-lar)

Spring Security ilə birlikdə aktiv session-ları izləmək mümkündür:

```java
@Configuration
@EnableWebSecurity
public class SecurityConfig {

    @Bean
    public SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
        http
            .sessionManagement(session -> session
                .maximumSessions(1)  // Hər istifadəçi üçün 1 session
                .maxSessionsPreventsLogin(true)  // Yeni login-i bloklayır
                .sessionRegistry(sessionRegistry())
                .expiredUrl("/login?expired")
            );
        return http.build();
    }

    @Bean
    public SessionRegistry sessionRegistry() {
        return new SessionRegistryImpl();
    }
}

// Admin controller-də aktiv session-ları görmək
@Controller
@RequestMapping("/admin")
public class AdminController {

    @Autowired
    private SessionRegistry sessionRegistry;

    @GetMapping("/active-users")
    public String activeUsers(Model model) {
        List<Object> principals = sessionRegistry.getAllPrincipals();

        List<Map<String, Object>> activeSessions = new ArrayList<>();
        for (Object principal : principals) {
            List<SessionInformation> sessions =
                sessionRegistry.getAllSessions(principal, false);

            for (SessionInformation session : sessions) {
                activeSessions.add(Map.of(
                    "user", ((UserDetails) principal).getUsername(),
                    "sessionId", session.getSessionId(),
                    "lastRequest", session.getLastRequest(),
                    "expired", session.isExpired()
                ));
            }
        }

        model.addAttribute("activeSessions", activeSessions);
        return "admin/active-users";
    }

    // Session-u ləğv etmək (istifadəçini çıxarmaq)
    @PostMapping("/expire-session")
    public String expireSession(@RequestParam String sessionId) {
        SessionInformation session = sessionRegistry.getSessionInformation(sessionId);
        if (session != null) {
            session.expireNow();
        }
        return "redirect:/admin/active-users";
    }
}
```

## Laravel-də istifadəsi

### Session konfiqurasiyası

Laravel-in session konfiqurasiyası `config/session.php` faylındadır:

```php
// config/session.php
return [
    // Driver: file, cookie, database, redis, memcached, dynamodb, array
    'driver' => env('SESSION_DRIVER', 'file'),

    'lifetime' => env('SESSION_LIFETIME', 120), // dəqiqə

    'expire_on_close' => false,

    'encrypt' => false,

    'files' => storage_path('framework/sessions'),

    'connection' => env('SESSION_CONNECTION'),

    'table' => 'sessions',

    'store' => env('SESSION_STORE'),

    'lottery' => [2, 100], // garbage collection ehtimalı

    'cookie' => env('SESSION_COOKIE', 'laravel_session'),

    'path' => '/',
    'domain' => env('SESSION_DOMAIN'),
    'secure' => env('SESSION_SECURE_COOKIE'),
    'http_only' => true,
    'same_site' => 'lax',
];
```

### Əsas session əməliyyatları

```php
class CartController extends Controller
{
    // Session-dan oxumaq
    public function viewCart(Request $request)
    {
        // Üsul 1: request() ilə
        $cart = $request->session()->get('cart', []);

        // Üsul 2: session() helper
        $cart = session('cart', []);

        // Üsul 3: Session facade
        $cart = Session::get('cart', []);

        // Bütün session məlumatları
        $allData = session()->all();

        // Mövcudluq yoxlaması
        if (session()->has('cart')) {
            // 'cart' mövcuddur və null deyil
        }

        if (session()->exists('cart')) {
            // 'cart' mövcuddur (null ola bilər)
        }

        if (session()->missing('cart')) {
            // 'cart' mövcud deyil
        }

        return view('cart.view', compact('cart'));
    }

    // Session-a yazmaq
    public function addToCart(Request $request)
    {
        $product = Product::findOrFail($request->product_id);

        // Üsul 1: put()
        $cart = session('cart', []);
        $cart[] = [
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => $product->price,
            'quantity' => $request->quantity,
        ];
        session()->put('cart', $cart);

        // Üsul 2: push() - array-ə əlavə etmək
        session()->push('cart', [
            'product_id' => $product->id,
            'name' => $product->name,
        ]);

        return redirect()->route('cart.view');
    }

    // Session-dan silmək
    public function clearCart()
    {
        // Bir açarı silmək (dəyəri qaytarır)
        $oldCart = session()->pull('cart');

        // Bir açarı silmək (dəyər qaytarmır)
        session()->forget('cart');

        // Bir neçə açarı silmək
        session()->forget(['cart', 'discount_code', 'shipping']);

        // Bütün session-u təmizləmək
        session()->flush();

        return redirect()->route('cart.view');
    }

    // Session ID əməliyyatları
    public function sessionInfo()
    {
        $id = session()->getId();

        // Session ID-ni yeniləmək (session fixation hücumundan qorunma)
        session()->regenerate();

        // Session ID-ni yeniləmək + köhnə session-u silmək
        session()->invalidate();

        return response()->json(['session_id' => session()->getId()]);
    }
}
```

### Flash Data (Bir dəfəlik məlumatlar)

Flash data yalnız növbəti HTTP sorğusunda mövcud olur - sonra avtomatik silinir:

```php
class ProductController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|max:255',
            'price' => 'required|numeric',
        ]);

        Product::create($validated);

        // Flash mesaj - yalnız növbəti sorğuda mövcuddur
        session()->flash('success', 'Məhsul uğurla əlavə edildi!');

        // Bir neçə flash
        session()->flash('alert-type', 'success');

        // redirect() ilə flash (daha qısa)
        return redirect()
            ->route('products.index')
            ->with('success', 'Məhsul uğurla əlavə edildi!');
    }

    public function update(Request $request, Product $product)
    {
        $product->update($request->validated());

        // Bir neçə flash dəyər
        return redirect()
            ->route('products.show', $product)
            ->with([
                'success' => 'Məhsul yeniləndi!',
                'product_name' => $product->name,
            ]);
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('error', 'Məhsul silindi.');
    }
}
```

```php
{{-- Blade şablonunda flash mesajları göstərmək --}}
@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger">
        {{ session('error') }}
    </div>
@endif

{{-- Bütün flash mesajlar üçün ümumi komponent --}}
@foreach(['success', 'error', 'warning', 'info'] as $type)
    @if(session($type))
        <div class="alert alert-{{ $type }}">
            {{ session($type) }}
        </div>
    @endif
@endforeach
```

Flash data-nı bir sorğu daha saxlamaq:

```php
// Bütün flash data-nı saxlamaq
session()->reflash();

// Yalnız müəyyən açarları saxlamaq
session()->keep(['success', 'alert-type']);
```

### Session Driver-ları

**Database driver:**

```bash
php artisan session:table
php artisan migrate
```

```php
// Yaradılan migration
Schema::create('sessions', function (Blueprint $table) {
    $table->string('id')->primary();
    $table->foreignId('user_id')->nullable()->index();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();
    $table->longText('payload');
    $table->integer('last_activity')->index();
});
```

```env
# .env
SESSION_DRIVER=database
```

**Redis driver:**

```env
SESSION_DRIVER=redis
SESSION_CONNECTION=default

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**Cookie driver:**

```env
SESSION_DRIVER=cookie
# Bütün session məlumatları şifrələnmiş cookie-də saxlanılır
# Limitasiya: 4KB məlumat limiti
```

### Session Middleware

Laravel-də session middleware `web` middleware qrupunda defolt olaraq aktivdir:

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\EncryptCookies::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \App\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
    'api' => [
        // API route-larında session yoxdur!
        'throttle:api',
        \Illuminate\Routing\Middleware\SubstituteBindings::class,
    ],
];
```

### Aktiv session-ları görmək (Database driver ilə)

```php
// İstifadəçinin bütün aktiv session-larını görmək
class SessionController extends Controller
{
    public function activeSessions(Request $request)
    {
        // Database driver istifadə edildikdə
        $sessions = DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) use ($request) {
                $agent = new Agent();
                $agent->setUserAgent($session->user_agent);

                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'browser' => $agent->browser(),
                    'platform' => $agent->platform(),
                    'last_activity' => Carbon::createFromTimestamp($session->last_activity)
                        ->diffForHumans(),
                    'is_current' => $session->id === $request->session()->getId(),
                ];
            });

        return view('profile.sessions', compact('sessions'));
    }

    // Digər session-ları sonlandırmaq
    public function destroyOtherSessions(Request $request)
    {
        $request->validate([
            'password' => 'required|current_password',
        ]);

        // Laravel daxili metod
        Auth::logoutOtherDevices($request->password);

        return redirect()
            ->route('profile.sessions')
            ->with('success', 'Digər cihazlardakı session-lar sonlandırıldı.');
    }
}
```

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| Defolt saxlama | Server yaddaşı (in-memory) | Fayl sistemi |
| Session API | `HttpSession` (Servlet API) | `session()` helper / `Session` facade |
| Distributed session | Spring Session layihəsi (əlavə dependency) | Daxili driver sistemi (konfiqurasiya dəyişikliyi) |
| Driver-lar | Redis, JDBC, Hazelcast, MongoDB | file, database, Redis, cookie, memcached, DynamoDB, array |
| Flash data | Yoxdur (Spring MVC `RedirectAttributes` ilə) | Daxili `flash()`, `with()` |
| Session atributları | `@SessionAttributes` (controller əsaslı) | Global session istifadəsi |
| Session limiti | SessionRegistry (Spring Security) | `Auth::logoutOtherDevices()` |
| Konfiqurasiya | `application.properties` + Java config | `config/session.php` + `.env` |
| Garbage collection | Servlet container idarə edir | Lottery sistemi (ehtimalla təmizlənir) |
| Cookie əsaslı session | Dəstəklənmir (defolt) | Daxili cookie driver |

## Niyə belə fərqlər var?

### Servlet API vs Framework abstraction

Spring Java Servlet spesifikasiyasının `HttpSession` interfeysinə əsaslanır. Bu interfeys Java EE standartının hissəsidir və bütün Java web server-lər (Tomcat, Jetty, Undertow) tərəfindən həyata keçirilir. Buna görə session-lar əslində servlet container tərəfindən idarə olunur, Spring isə üstündə abstraksiya qatı əlavə edir. Spring Session layihəsi bu abstraksiya qatını daha da genişləndirir.

Laravel isə PHP-nin `$_SESSION` superglobal-ından istifadə etmir. Onun əvəzinə öz session abstraction layer-ini yaradıb ki, bu da müxtəlif driver-lar arasında asanlıqla keçid etməyə imkan verir. `config/session.php`-də `driver` dəyişənini dəyişmək kifayətdir.

### Flash data

Laravel-in flash data mexanizmi PHP-nin stateless təbiətindən qaynaqlanır. Hər PHP sorğusu müstəqildir, ona görə redirect-dən sonra mesaj göstərmək üçün flash data lazımdır. Spring MVC-də `RedirectAttributes.addFlashAttribute()` oxşar funksionallıq verir, lakin Laravel-dəki qədər sadə və yaygın istifadə olunmur.

### Driver sistemi

Laravel-in driver-əsaslı arxitekturası bütün framework boyunca tutarlıdır - cache, queue, session, mail hamısı driver pattern istifadə edir. Bu, development-dən production-a keçidi asanlaşdırır: development-də `file` driver, production-da `redis` driver istifadə etmək sadəcə bir konfiqurasiya dəyişikliyi tələb edir.

Spring Session isə ayrı bir layihə olaraq yaranıb, çünki orijinal Servlet session mexanizmi distributed mühitlər üçün nəzərdə tutulmayıb. Spring Session sonradan bu boşluğu doldurmaq üçün yaradılıb.

## Hansı framework-də var, hansında yoxdur?

### Yalnız Laravel-də olan xüsusiyyətlər:
- **Flash data** - `session()->flash()` və `redirect()->with()` ilə bir dəfəlik mesajlar
- **`reflash()` / `keep()`** - flash data-nı əlavə bir sorğu saxlamaq
- **Cookie session driver** - bütün session-u şifrələnmiş cookie-də saxlamaq
- **Array driver** - test mühiti üçün yaddaşda session
- **`session()->push()`** - array tipli session dəyərinə əlavə etmək
- **`session()->pull()`** - oxu və sil bir əməliyyatda
- **`session()->missing()`** - mövcud olmamanı yoxlamaq
- **Lottery garbage collection** - session təmizləmə ehtimalını təyin etmək
- **`Auth::logoutOtherDevices()`** - digər cihazları çıxarmaq (bir sətirdə)

### Yalnız Spring-də olan xüsusiyyətlər:
- **`@SessionAttributes`** - controller səviyyəsində model atributlarını session-da saxlamaq
- **`SessionStatus.setComplete()`** - controller session atributlarını təmizləmək
- **`SessionRegistry`** - bütün aktiv session-ları izləmək və idarə etmək (admin panel üçün)
- **`session.isNew()`** - session-un yeni yaradılıb-yaradılmadığını yoxlamaq
- **Servlet container inteqrasiyası** - Tomcat/Jetty-nin öz session idarəetməsindən istifadə
- **Spring Session + Hazelcast** - distributed cache ilə session paylaşma
- **Maximum sessions per user** - Spring Security ilə istifadəçi başına session limiti (yeni login-i bloklama)
