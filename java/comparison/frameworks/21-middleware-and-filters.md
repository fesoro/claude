# Middleware ve Filterlər: Spring vs Laravel

> **Seviyye:** Intermediate ⭐⭐

## Giris

Middleware (ve ya filter) HTTP sorgusunun controller-e catmazdan evvel ve ya cavab geri qaytarilmazdan evvel isleyen "ara qat" kodudur. Autentifikasiya yoxlamasi, logging, CORS header-lerin elave edilmesi, rate limiting kimi meseleleri hell edir.

Spring ve Laravel bu konsepti ferqli adlar ve mexanizmlerle realize edir. Spring-de `Filter`, `HandlerInterceptor` ve `OncePerRequestFilter` var. Laravel-de ise vahid `Middleware` konsepti butun bu isleri gorur.

## Spring-de Istifadesi

Spring-de HTTP sorgusunun islenme prosesi bir nece merheledir ve her merhelede ferqli mexanizm isleyir:

```
HTTP Sorgu --> Filter --> DispatcherServlet --> HandlerInterceptor --> Controller
                                                                          |
HTTP Cavab <-- Filter <-- DispatcherServlet <-- HandlerInterceptor <-- Controller
```

### Servlet Filter

En asagi seviyyeli mexanizm - Spring-den evvel, Servlet spesifikasiyasinin hissesidir:

```java
// Sade Filter implementasiyasi
@Component
@Order(1)  // Filter sirasi - kicik reqem evvel isleyir
public class RequestLoggingFilter implements Filter {

    private static final Logger log = LoggerFactory.getLogger(RequestLoggingFilter.class);

    @Override
    public void doFilter(ServletRequest request, ServletResponse response, FilterChain chain)
            throws IOException, ServletException {

        HttpServletRequest httpRequest = (HttpServletRequest) request;
        long startTime = System.currentTimeMillis();

        log.info("Sorgu basladi: {} {}", httpRequest.getMethod(), httpRequest.getRequestURI());

        // Sorgunu novbeti filter-e ve ya controller-e otur
        chain.doFilter(request, response);

        long duration = System.currentTimeMillis() - startTime;
        log.info("Sorgu bitti: {} {} - {}ms", httpRequest.getMethod(),
                httpRequest.getRequestURI(), duration);
    }
}
```

### OncePerRequestFilter

`Filter`-in tekmillesdirilmis versiyasi - her sorgu ucun yalniz bir defe isleyir (forward/redirect zamani tekrar islemir):

```java
@Component
public class JwtAuthenticationFilter extends OncePerRequestFilter {

    private final JwtService jwtService;
    private final UserDetailsService userDetailsService;

    public JwtAuthenticationFilter(JwtService jwtService, UserDetailsService userDetailsService) {
        this.jwtService = jwtService;
        this.userDetailsService = userDetailsService;
    }

    @Override
    protected void doFilterInternal(
            HttpServletRequest request,
            HttpServletResponse response,
            FilterChain filterChain) throws ServletException, IOException {

        String authHeader = request.getHeader("Authorization");

        // Token yoxdursa, novbeti filter-e kec
        if (authHeader == null || !authHeader.startsWith("Bearer ")) {
            filterChain.doFilter(request, response);
            return;
        }

        String jwt = authHeader.substring(7);

        try {
            String username = jwtService.extractUsername(jwt);

            if (username != null && SecurityContextHolder.getContext().getAuthentication() == null) {
                UserDetails userDetails = userDetailsService.loadUserByUsername(username);

                if (jwtService.isTokenValid(jwt, userDetails)) {
                    UsernamePasswordAuthenticationToken authToken =
                            new UsernamePasswordAuthenticationToken(
                                    userDetails, null, userDetails.getAuthorities());

                    authToken.setDetails(new WebAuthenticationDetailsSource()
                            .buildDetails(request));

                    SecurityContextHolder.getContext().setAuthentication(authToken);
                }
            }
        } catch (JwtException e) {
            response.setStatus(HttpServletResponse.SC_UNAUTHORIZED);
            response.getWriter().write("{\"error\": \"Etibarsiz token\"}");
            return;  // Sorgunu dayandyr
        }

        filterChain.doFilter(request, response);
    }

    // Bu filter-in hansi URL-lere tetbiq olunmayacagini mueyyenlesdirmek
    @Override
    protected boolean shouldNotFilter(HttpServletRequest request) {
        String path = request.getRequestURI();
        return path.startsWith("/api/auth/login")
            || path.startsWith("/api/auth/register")
            || path.startsWith("/public/");
    }
}
```

### HandlerInterceptor

Spring MVC seviyyesinde isleyir - Filter-den ferqli olaraq Spring kontekstine erisimi var:

```java
@Component
public class RateLimitInterceptor implements HandlerInterceptor {

    private final RateLimiterService rateLimiter;

    public RateLimitInterceptor(RateLimiterService rateLimiter) {
        this.rateLimiter = rateLimiter;
    }

    /**
     * Controller-den EVVEL isleyir.
     * false qaytarsa, sorgu dayandirillir.
     */
    @Override
    public boolean preHandle(HttpServletRequest request, HttpServletResponse response, Object handler)
            throws Exception {

        String clientIp = request.getRemoteAddr();

        if (!rateLimiter.tryConsume(clientIp)) {
            response.setStatus(429);
            response.setContentType("application/json");
            response.getWriter().write("{\"error\": \"Cox sayda sorgu. Gozleyin.\"}");
            return false;  // Sorgunu dayandir
        }

        // Qalan limit sayi header-de gonder
        response.setHeader("X-RateLimit-Remaining",
                String.valueOf(rateLimiter.getRemaining(clientIp)));

        return true;  // Davam et
    }

    /**
     * Controller-den SONRA, view render-den EVVEL isleyir.
     */
    @Override
    public void postHandle(HttpServletRequest request, HttpServletResponse response,
                           Object handler, ModelAndView modelAndView) throws Exception {
        // Meselen: model-e ortaq data elave etmek
        if (modelAndView != null) {
            modelAndView.addObject("appVersion", "2.1.0");
        }
    }

    /**
     * Her sey bitdikden SONRA isleyir (view render-den sonra).
     * Resurs temizleme ucun istifade olunur.
     */
    @Override
    public void afterCompletion(HttpServletRequest request, HttpServletResponse response,
                                Object handler, Exception ex) throws Exception {
        if (ex != null) {
            // Xeta log etmek
            log.error("Sorgu zamani xeta: {}", ex.getMessage());
        }
    }
}
```

### Interceptor-u qeydiyyatdan kecirmek

```java
@Configuration
public class WebMvcConfig implements WebMvcConfigurer {

    private final RateLimitInterceptor rateLimitInterceptor;
    private final AuditLogInterceptor auditLogInterceptor;

    public WebMvcConfig(RateLimitInterceptor rateLimitInterceptor,
                        AuditLogInterceptor auditLogInterceptor) {
        this.rateLimitInterceptor = rateLimitInterceptor;
        this.auditLogInterceptor = auditLogInterceptor;
    }

    @Override
    public void addInterceptors(InterceptorRegistry registry) {
        // Butun API marsrutlarina tetbiq et
        registry.addInterceptor(rateLimitInterceptor)
                .addPathPatterns("/api/**")
                .excludePathPatterns("/api/health");

        // Yalniz admin marsrutlarina
        registry.addInterceptor(auditLogInterceptor)
                .addPathPatterns("/api/admin/**");
    }
}
```

### CORS konfiqurasiyasi

```java
@Configuration
public class CorsConfig implements WebMvcConfigurer {

    @Override
    public void addCorsMappings(CorsRegistry registry) {
        registry.addMapping("/api/**")
                .allowedOrigins("http://localhost:3000", "https://example.com")
                .allowedMethods("GET", "POST", "PUT", "DELETE", "PATCH")
                .allowedHeaders("*")
                .exposedHeaders("X-RateLimit-Remaining")
                .allowCredentials(true)
                .maxAge(3600);
    }
}

// Alternativ: Filter ile CORS
@Component
@Order(Ordered.HIGHEST_PRECEDENCE)
public class CorsFilter extends OncePerRequestFilter {

    @Override
    protected void doFilterInternal(HttpServletRequest request, HttpServletResponse response,
                                    FilterChain chain) throws ServletException, IOException {
        response.setHeader("Access-Control-Allow-Origin", "http://localhost:3000");
        response.setHeader("Access-Control-Allow-Methods", "GET, POST, PUT, DELETE");
        response.setHeader("Access-Control-Allow-Headers", "Authorization, Content-Type");
        response.setHeader("Access-Control-Max-Age", "3600");

        if ("OPTIONS".equalsIgnoreCase(request.getMethod())) {
            response.setStatus(HttpServletResponse.SC_OK);
            return;
        }

        chain.doFilter(request, response);
    }
}
```

### Filter Registration (konfiqurasiya ile)

```java
@Configuration
public class FilterConfig {

    @Bean
    public FilterRegistrationBean<RequestLoggingFilter> loggingFilter() {
        FilterRegistrationBean<RequestLoggingFilter> registration = new FilterRegistrationBean<>();
        registration.setFilter(new RequestLoggingFilter());
        registration.addUrlPatterns("/api/*");
        registration.setOrder(1);
        registration.setName("requestLoggingFilter");
        return registration;
    }

    @Bean
    public FilterRegistrationBean<CompressionFilter> compressionFilter() {
        FilterRegistrationBean<CompressionFilter> registration = new FilterRegistrationBean<>();
        registration.setFilter(new CompressionFilter());
        registration.addUrlPatterns("/*");
        registration.setOrder(2);
        return registration;
    }
}
```

## Laravel-de Istifadesi

Laravel-de vahid `Middleware` konsepti var ve o, Spring-in hem Filter, hem de Interceptor islerini gorur.

### Middleware yaratmaq

```bash
php artisan make:middleware EnsureTokenIsValid
```

```php
// app/Http/Middleware/EnsureTokenIsValid.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTokenIsValid
{
    /**
     * Esas middleware metiqi
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->input('token') !== 'valid-secret-token') {
            return response()->json(['error' => 'Etibarsiz token'], 403);
        }

        // Sorgunu novbeti middleware-e ve ya controller-e otur
        return $next($request);
    }
}
```

### Before ve After Middleware

```php
// Before middleware - controller-den EVVEL isleyir
class LogRequestMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Controller-den EVVEL
        Log::info('Sorgu basladi', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
        ]);

        $response = $next($request);

        // Controller-den SONRA (cavab qayitdiqdan sonra)
        Log::info('Sorgu bitti', [
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }
}

// After middleware - yalniz controller-den SONRA isleyir
class AddResponseHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Cavaba header elave et
        $response->headers->set('X-App-Version', '2.1.0');
        $response->headers->set('X-Request-Id', (string) Str::uuid());

        return $response;
    }
}
```

### JWT Authentication Middleware

```php
namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;

class JwtAuthenticate
{
    public function __construct(
        private readonly JwtService $jwtService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['error' => 'Token teqdim olunmayib'], 401);
        }

        try {
            $user = $this->jwtService->validateAndGetUser($token);
            // Istifadecini sorguya elave et
            $request->merge(['auth_user' => $user]);
            // ve ya auth sistemi ile
            auth()->setUser($user);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Etibarsiz token'], 401);
        }

        return $next($request);
    }
}
```

### Middleware Parametrleri

```php
// Parametrli middleware
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user || !in_array($user->role, $roles)) {
            return response()->json(['error' => 'Icazeniz yoxdur'], 403);
        }

        return $next($request);
    }
}

// Route-da istifade
Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])
    ->middleware('role:admin');

Route::get('/reports', [ReportController::class, 'index'])
    ->middleware('role:admin,manager');
```

### Middleware Qeydiyyati

```php
// bootstrap/app.php (Laravel 11+)
use Illuminate\Foundation\Configuration\Middleware;

->withMiddleware(function (Middleware $middleware) {

    // Qlobal middleware - HER sorguya tetbiq olunur
    $middleware->append(LogRequestMiddleware::class);

    // Middleware alias (ad vermek)
    $middleware->alias([
        'jwt' => JwtAuthenticate::class,
        'role' => RoleMiddleware::class,
        'throttle' => ThrottleRequests::class,
        'locale' => SetLocaleMiddleware::class,
    ]);

    // Middleware qrupu
    $middleware->group('api', [
        ThrottleRequests::class . ':60,1',
        SubstituteBindings::class,
    ]);

    $middleware->group('web', [
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        VerifyCsrfToken::class,
        SubstituteBindings::class,
    ]);

    // Middleware sirasini mueyyenlesdirmek
    $middleware->priority([
        StartSession::class,
        AuthenticateSession::class,
        SubstituteBindings::class,
        Authorize::class,
    ]);
})
```

### Route-da Middleware istifadesi

```php
// Tek middleware
Route::get('/profile', [ProfileController::class, 'show'])
    ->middleware('auth');

// Bir nece middleware
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware(['auth', 'role:admin']);

// Qrupda middleware
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::apiResource('orders', OrderController::class);
    Route::apiResource('products', ProductController::class);
});

// Controller-in icinde middleware
class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('role:admin')->only(['destroy']);
        $this->middleware('throttle:10,1')->except(['index', 'show']);
    }
}

// Middleware-i cixarmaq
Route::withoutMiddleware([VerifyCsrfToken::class])->group(function () {
    Route::post('/webhook', [WebhookController::class, 'handle']);
});
```

### Terminable Middleware

HTTP cavab gonderiledikden SONRA isleyen middleware:

```php
class CollectAnalytics
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Cavab gonderiledikden SONRA isleyir.
     * Istifadecinin gozlemesine tesir etmir.
     */
    public function terminate(Request $request, Response $response): void
    {
        // Statistika toplamaq, log yazmaq, analitika gondermek
        Analytics::track([
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'status' => $response->getStatusCode(),
            'duration' => microtime(true) - LARAVEL_START,
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
```

### Rate Limiting

```php
// bootstrap/app.php ve ya AppServiceProvider
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

// AppServiceProvider::boot()
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('uploads', function (Request $request) {
    return $request->user()->isPremium()
        ? Limit::perMinute(100)
        : Limit::perMinute(10);
});

RateLimiter::for('login', function (Request $request) {
    return [
        Limit::perMinute(5)->by($request->ip()),           // IP basina
        Limit::perMinute(10)->by($request->input('email')), // Email basina
    ];
});

// Route-da istifade
Route::middleware('throttle:api')->group(function () {
    Route::apiResource('users', UserController::class);
});

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');
```

## Esas Ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Mexanizm adi** | Filter + HandlerInterceptor | Middleware |
| **Seviyyeler** | 2 seviyye (Servlet + Spring MVC) | 1 vahid seviyye |
| **Qeydiyyat** | `@Component` / `FilterRegistrationBean` / `WebMvcConfigurer` | `bootstrap/app.php` / route-da |
| **Siralama** | `@Order` annotasiyasi | `priority()` metodu |
| **Parametr alma** | Mumkun deyil (birbase) | `handle($request, $next, ...$params)` |
| **Before/After** | `preHandle` / `postHandle` / `afterCompletion` | `$next($request)` evvel/sonra kod |
| **Terminable** | Yoxdur (daxili) | `terminate()` metodu |
| **Qruplar** | Yoxdur | `middleware()->group()` |
| **URL pattern** | `addPathPatterns("/api/**")` | Route qruplarinda |
| **Rate limiting** | Ucuncu teref (Bucket4j, Resilience4j) | Daxili `RateLimiter` |

## Niye Bele Ferqler Var?

### Spring: Tarixden gelen iki seviyye

1. **Filter - Servlet API-den**: Java Servlet spesifikasiyasi web container seviyyesinde `Filter` interface-ini tanimlayir. Bu, Spring-den evvel movcud olub. `OncePerRequestFilter` Spring-in bu esas interface-in usunde yaratdigi rahatliqddir.

2. **HandlerInterceptor - Spring MVC-den**: Spring oz MVC framework-unu yaradanda, controller-lerin evvelinde ve sonrasinda isleyecek mexanizm lazim oldu. `HandlerInterceptor` Servlet Filter-den ferqli olaraq Spring kontekstine (bean-lere, service-lere) birbase erisim verir ve `preHandle`, `postHandle`, `afterCompletion` kimi daha incə lifecycle noqteleri teklif edir.

3. **Niye her ikisi lazimdir?** Filter daha asagi seviyyededir - Spring konteksti yuklenmedikden evvel bele isleye biler (meselen, encoding, CORS). Interceptor ise Spring bean-lerine erisim teleb eden meantiq ucundur (autentifikasiya, avtorizasiya). Praktikada, bir cox developer yalniz `OncePerRequestFilter` istifade edir, cunki o, hem asagi seviyyeli erisim, hem de Spring bean injection verir.

### Laravel: Sade ve vahid model

1. **Tek konsept**: Laravel hec vaxt iki seviyyeli olmayib. `Middleware` konsepti hem before, hem after, hem de terminable meantiq ucun kifayetdir. `$next($request)` cagirisinin evvelindeki kod "before", sonrakini "after" meantiqdir - bu, sade ve intuitiv yaranisdir.

2. **Parametrli middleware**: `role:admin,manager` kimi parametr gonderme imkani middleware-leri cok cevik edir. Spring-de bu, filter/interceptor-a konfiqurasiya inject etmekle hell olunur, bu ise daha cok kod teleb edir.

3. **Terminable middleware**: PHP-nin request-response lifecycle-i bunu mumkun edir. Cavab gonderilib, amma PHP prosesi hele isleyir - bu zaman analitika toplamaq, log yazmaq ve s. olar. Java-da servlet thread-leri ferqli isleyir, ona gore bele birbase mexanizm yoxdur.

4. **Qruplar**: `web` ve `api` qruplari genis istifade olunan middleware desti bir yerde saxlamaga imkan verir. Spring-de bunu `SecurityFilterChain` ve ya `WebMvcConfigurer`-de konfiqurasiya ile etmek lazimdir.

## Hansi Framework-de Var, Hansinda Yoxdur?

### Yalniz Spring-de olan xususiyyetler

- **`postHandle` ve `afterCompletion` ayri metodlar**: Spring-de controller-den sonra, amma view render-den evvel (`postHandle`) ve her sey bitdikden sonra (`afterCompletion`) ayri-ayri metodlar var. Laravel-de hamisi `handle()` icinde `$next()` etrafinda hell olunur.

- **Security Filter Chain**: Spring Security oz filter zencirini yaradir (CSRF, CORS, authentication, authorization) ve bu, adi filter-lerden ayridir. Cok guclu, amma murekkebdir.

```java
@Configuration
@EnableWebSecurity
public class SecurityConfig {
    @Bean
    public SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
        return http
            .csrf(csrf -> csrf.disable())
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/api/auth/**").permitAll()
                .requestMatchers("/api/admin/**").hasRole("ADMIN")
                .anyRequest().authenticated()
            )
            .addFilterBefore(jwtFilter, UsernamePasswordAuthenticationFilter.class)
            .build();
    }
}
```

- **`shouldNotFilter()` metodu**: `OncePerRequestFilter`-de filter-in hansi URL-lere tetbiq olunmayacagini filter-in ozunde mueyyenlesdirmek.

### Yalniz Laravel-de olan xususiyyetler

- **Terminable Middleware**: HTTP cavab gonderiledikden sonra isleyen `terminate()` metodu. Spring-de bunu async event ve ya `@Async` metod ile simulyasiya etmek lazimdir.

- **Middleware parametrleri**: `middleware('role:admin')` kimi birbase parametr gondermek.

- **Middleware exclusion**: `->withoutMiddleware([VerifyCsrfToken::class])` ile mueyyen marsrutlardan middleware-i cixarmaq.

- **Daxili Rate Limiter**: `RateLimiter::for()` ile cevik rate limiting qaydalari. Spring-de Bucket4j ve ya Resilience4j kimi ucuncu teref kitabxana istifade olunur.

- **Middleware alias**: Middleware-e qisa ad vermek (`'auth'` => `Authenticate::class`) ve route-larda bu adi istifade etmek.
