# Security (Tehlukesizlik)

> **Seviyye:** Intermediate ⭐⭐

## Giris

Veb tetbiqlerin tehlukesizliyi en muhum movzulardan biridir. CSRF (Cross-Site Request Forgery), XSS (Cross-Site Scripting), CORS (Cross-Origin Resource Sharing) kimi hucum novlerinden qorunmaq her developer-in vezifesidir.

Spring Security guclu ve konfiqurasiyasi cetin olan tehlukesizlik framework-udur. Laravel ise tehlukesizlik mexanizmlerini framework daxilinde sade ve hazir formada teqdim edir. Her iki framework default olaraq bir cox tehlukesizlik tedbiri teleb edir.

## Spring-de istifadesi

### CSRF qorunmasi

Spring Security default olaraq CSRF qorunmasini aktiv edir:

```java
@Configuration
@EnableWebSecurity
public class SecurityConfig {

    @Bean
    public SecurityFilterChain filterChain(
            HttpSecurity http) throws Exception {

        http
            // CSRF default olaraq aktivdir
            .csrf(csrf -> csrf
                // Cookie-based CSRF token (SPA ucun)
                .csrfTokenRepository(
                    CookieCsrfTokenRepository.withHttpOnlyFalse())
                // Bezi endpoint-leri istisna et
                .ignoringRequestMatchers("/api/webhooks/**")
            )
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/public/**").permitAll()
                .requestMatchers("/admin/**").hasRole("ADMIN")
                .anyRequest().authenticated()
            );

        return http.build();
    }
}
```

**Thymeleaf template-de CSRF token:**

```html
<form method="POST" action="/orders">
    <!-- Spring avtomatik elave edir -->
    <input type="hidden"
           name="_csrf"
           th:value="${_csrf.token}"/>
    <input type="text" name="product" />
    <button type="submit">Sifaris ver</button>
</form>
```

**REST API ucun CSRF-i sondermek:**

```java
@Configuration
@EnableWebSecurity
public class ApiSecurityConfig {

    @Bean
    public SecurityFilterChain apiFilterChain(
            HttpSecurity http) throws Exception {
        http
            .securityMatcher("/api/**")
            // API ucun CSRF sondurulur
            // (cunku JWT/token authentication istifade olunur)
            .csrf(csrf -> csrf.disable())
            .sessionManagement(session -> session
                .sessionCreationPolicy(
                    SessionCreationPolicy.STATELESS))
            .authorizeHttpRequests(auth -> auth
                .anyRequest().authenticated()
            )
            .oauth2ResourceServer(oauth2 -> oauth2
                .jwt(Customizer.withDefaults())
            );

        return http.build();
    }
}
```

### CORS konfiqurasiyasi

```java
@Configuration
public class CorsConfig {

    @Bean
    public CorsConfigurationSource corsConfigurationSource() {
        CorsConfiguration configuration = new CorsConfiguration();

        configuration.setAllowedOrigins(List.of(
            "https://example.com",
            "https://admin.example.com"
        ));

        configuration.setAllowedMethods(List.of(
            "GET", "POST", "PUT", "DELETE", "PATCH", "OPTIONS"
        ));

        configuration.setAllowedHeaders(List.of(
            "Authorization",
            "Content-Type",
            "X-Requested-With"
        ));

        configuration.setExposedHeaders(List.of(
            "X-Total-Count",
            "X-Page-Number"
        ));

        configuration.setAllowCredentials(true);
        configuration.setMaxAge(3600L);

        UrlBasedCorsConfigurationSource source =
            new UrlBasedCorsConfigurationSource();
        source.registerCorsConfiguration("/**", configuration);
        return source;
    }
}

// SecurityConfig-de aktiv et:
http.cors(cors -> cors
    .configurationSource(corsConfigurationSource()));
```

**Controller seviyyesinde CORS:**

```java
@RestController
@CrossOrigin(origins = "https://example.com",
             maxAge = 3600)
public class ProductController {

    @CrossOrigin(origins = "https://admin.example.com")
    @GetMapping("/api/products")
    public List<Product> getProducts() {
        return productService.findAll();
    }
}
```

### XSS qorunmasi

Spring-de XSS qorunmasi bir nece seviyyede tetbiq olunur:

```java
// 1. Security headers
@Configuration
public class SecurityHeadersConfig {

    @Bean
    public SecurityFilterChain filterChain(
            HttpSecurity http) throws Exception {
        http
            .headers(headers -> headers
                // Content-Security-Policy
                .contentSecurityPolicy(csp -> csp
                    .policyDirectives(
                        "default-src 'self'; " +
                        "script-src 'self'; " +
                        "style-src 'self' 'unsafe-inline'; " +
                        "img-src 'self' data:; " +
                        "frame-ancestors 'none'"))
                // X-Content-Type-Options
                .contentTypeOptions(Customizer.withDefaults())
                // X-Frame-Options
                .frameOptions(frame -> frame.deny())
                // X-XSS-Protection
                .xssProtection(xss -> xss
                    .headerValue(
                        XXssProtectionHeaderWriter
                            .HeaderValue.ENABLED_MODE_BLOCK))
            );

        return http.build();
    }
}

// 2. Input sanitizasiyasi
@RestController
public class CommentController {

    @PostMapping("/api/comments")
    public Comment createComment(
            @RequestBody @Valid CommentDto dto) {
        // Jsoup ile HTML temizleme
        String cleanContent = Jsoup.clean(
            dto.getContent(),
            Safelist.basic() // Yalniz basic HTML tag-lerine icaze
        );

        Comment comment = new Comment();
        comment.setContent(cleanContent);
        return commentRepository.save(comment);
    }
}

// 3. Thymeleaf avtomatik escaping edir
// th:text -- avtomatik escape (tehlukesiz)
// th:utext -- escape etmir (tehlukeli!)
```

```html
<!-- Tehlukesiz: HTML escape olunur -->
<p th:text="${userComment}">
    <!-- <script>alert('XSS')</script> =>
         &lt;script&gt;alert('XSS')&lt;/script&gt; -->
</p>

<!-- TEHLUKELI: escape olunmur, yalniz etibarli melumat ucun -->
<div th:utext="${trustedHtml}"></div>
```

### Password Encoding

```java
@Configuration
public class PasswordConfig {

    @Bean
    public PasswordEncoder passwordEncoder() {
        // BCrypt (default ve toevsiye olunan)
        return new BCryptPasswordEncoder(12);
    }

    // Ve ya DelegatingPasswordEncoder (bir nece algoritm)
    @Bean
    public PasswordEncoder delegatingPasswordEncoder() {
        return PasswordEncoderFactories
            .createDelegatingPasswordEncoder();
        // Default: bcrypt
        // Diger: argon2, scrypt, pbkdf2 destekleyir
    }
}

@Service
public class UserService {

    @Autowired
    private PasswordEncoder passwordEncoder;

    @Autowired
    private UserRepository userRepository;

    public User register(RegistrationDto dto) {
        User user = new User();
        user.setEmail(dto.getEmail());
        // Parolu hash-le
        user.setPassword(
            passwordEncoder.encode(dto.getPassword()));
        return userRepository.save(user);
    }

    public boolean verifyPassword(String raw, String encoded) {
        return passwordEncoder.matches(raw, encoded);
    }
}
```

### Rate Limiting

```java
// Bucket4j ile rate limiting
@Configuration
public class RateLimitConfig {

    @Bean
    public FilterRegistrationBean<RateLimitFilter>
            rateLimitFilter() {
        FilterRegistrationBean<RateLimitFilter> registrationBean =
            new FilterRegistrationBean<>();
        registrationBean.setFilter(new RateLimitFilter());
        registrationBean.addUrlPatterns("/api/*");
        return registrationBean;
    }
}

public class RateLimitFilter extends OncePerRequestFilter {

    private final Map<String, Bucket> buckets =
        new ConcurrentHashMap<>();

    @Override
    protected void doFilterInternal(
            HttpServletRequest request,
            HttpServletResponse response,
            FilterChain chain) throws ServletException, IOException {

        String clientIp = request.getRemoteAddr();

        Bucket bucket = buckets.computeIfAbsent(clientIp,
            k -> Bucket.builder()
                .addLimit(Bandwidth.classic(
                    100, // 100 sorgu
                    Refill.intervally(100, Duration.ofMinutes(1))))
                .build());

        if (bucket.tryConsume(1)) {
            chain.doFilter(request, response);
        } else {
            response.setStatus(HttpStatus.TOO_MANY_REQUESTS.value());
            response.getWriter().write("Limit ashildi");
        }
    }
}
```

### SQL Injection qorunmasi

```java
// DUZGUN: JPA parameterized query (avtomatik qorunma)
@Repository
public interface UserRepository extends JpaRepository<User, Long> {

    // Spring Data JPA -- avtomatik parametrize olunur
    List<User> findByEmail(String email);

    // JPQL ile -- parametrize olunur
    @Query("SELECT u FROM User u WHERE u.email = :email")
    Optional<User> findByEmailQuery(@Param("email") String email);

    // Native query -- parametrize olunur
    @Query(value = "SELECT * FROM users WHERE email = ?1",
           nativeQuery = true)
    Optional<User> findByEmailNative(String email);
}

// SEHV: String concatenation (SQL Injection tehlukesi!)
// String query = "SELECT * FROM users WHERE email = '"
//     + email + "'"; // HECH VAXT BELE ETMEYIN!
```

## Laravel-de istifadesi

### CSRF qorunmasi

Laravel avtomatik olaraq CSRF qorunmasi temin edir:

```php
// Blade template-de @csrf directive
<form method="POST" action="/orders">
    @csrf   {{-- Avtomatik CSRF token elave edir --}}
    <input type="text" name="product" />
    <button type="submit">Sifaris ver</button>
</form>

<!-- Yaradilan HTML: -->
<input type="hidden"
       name="_token"
       value="abc123def456..." />
```

**Meta tag ile (AJAX ucun):**

```html
<!-- Layout-da -->
<meta name="csrf-token"
      content="{{ csrf_token() }}">

<!-- JavaScript-de -->
<script>
// Axios avtomatik goturur (bootstrap.js-de qurulur)
window.axios.defaults.headers.common['X-CSRF-TOKEN'] =
    document.querySelector('meta[name="csrf-token"]')
        .getAttribute('content');

// Manuel fetch ile
fetch('/api/orders', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': document.querySelector(
            'meta[name="csrf-token"]').content,
        'Content-Type': 'application/json',
    },
    body: JSON.stringify(data),
});
</script>
```

**Bezi route-lari istisna etmek:**

```php
// bootstrap/app.php (Laravel 11+)
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'stripe/webhook',
        'api/webhooks/*',
    ]);
})
```

### CORS konfiqurasiyasi

**config/cors.php:**

```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],
    // ve ya ['GET', 'POST', 'PUT', 'DELETE', 'PATCH']

    'allowed_origins' => [
        'https://example.com',
        'https://admin.example.com',
    ],

    // Pattern ile
    'allowed_origins_patterns' => [
        'https://*.example.com',
    ],

    'allowed_headers' => ['*'],
    // ve ya ['Content-Type', 'Authorization', 'X-Requested-With']

    'exposed_headers' => [
        'X-Total-Count',
        'X-Page-Number',
    ],

    'max_age' => 3600,

    'supports_credentials' => true,
];
```

### XSS qorunmasi (Blade Escaping)

```php
{{-- TEHLUKESIZ: Avtomatik HTML escape --}}
<p>{{ $userComment }}</p>
{{-- <script>alert('XSS')</script> =>
     &lt;script&gt;alert('XSS')&lt;/script&gt; --}}

{{-- TEHLUKELI: Escape olunmur! Yalniz etibarli melumat ucun --}}
<div>{!! $trustedHtml !!}</div>

{{-- JavaScript-de deyishken istifade --}}
<script>
    // TEHLUKESIZ: JSON encode + escape
    var data = @json($userData);

    // TEHLUKELI:
    // var data = '{!! $userData !!}'; // XSS tehlukesi!
</script>
```

**Input sanitizasiyasi:**

```php
class CommentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
        ]);

        // HTML tag-lerini temizle
        $cleanContent = strip_tags($validated['content']);

        // Ve ya HTMLPurifier ile (daha guclu)
        $cleanContent = clean($validated['content']);

        $comment = Comment::create([
            'content' => $cleanContent,
            'user_id' => auth()->id(),
        ]);

        return response()->json($comment, 201);
    }
}
```

### Password Hashing

```php
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            // BCrypt ile hash-le (default)
            'password' => Hash::make($request->password),
        ]);

        return response()->json($user, 201);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = auth()->user();

        // Cari parolu yoxla
        if (!Hash::check(
                $request->current_password,
                $user->password)) {
            return response()->json([
                'message' => 'Cari parol sehvdir',
            ], 422);
        }

        // Yeni parolu hash-le ve saxla
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        // Hash-in yenilenmesine ehtiyac var?
        // (algoritm ve ya cost deyishdikde)
        if (Hash::needsRehash($user->password)) {
            $user->update([
                'password' => Hash::make($request->new_password),
            ]);
        }

        return response()->json([
            'message' => 'Parol ugurla deyishdirildi',
        ]);
    }
}
```

**Hashing konfiqurasiyasi:**

```php
// config/hashing.php
return [
    'driver' => 'bcrypt', // bcrypt, argon, argon2id

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
    ],

    'argon' => [
        'memory' => 65536,  // KB
        'threads' => 1,
        'time' => 4,
    ],
];
```

### Rate Limiting

```php
// bootstrap/app.php ve ya RouteServiceProvider
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)
        ->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('login', function (Request $request) {
    return [
        // IP-ye gore
        Limit::perMinute(5)->by($request->ip()),
        // Email-e gore
        Limit::perMinute(3)->by($request->input('email')),
    ];
});

RateLimiter::for('uploads', function (Request $request) {
    return $request->user()->isPremium()
        ? Limit::none()  // Premium istifadeciler ucun limit yoxdur
        : Limit::perMinute(10)->by($request->user()->id);
});
```

**Route-da istifade:**

```php
Route::middleware('throttle:api')->group(function () {
    Route::apiResource('products', ProductController::class);
});

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');
```

### SQL Injection qorunmasi

```php
// DUZGUN: Eloquent (avtomatik parametrize olunur)
$users = User::where('email', $email)->get();

// DUZGUN: Query builder (parametrize olunur)
$users = DB::table('users')
    ->where('email', '=', $email)
    ->get();

// DUZGUN: Raw query + binding
$users = DB::select(
    'SELECT * FROM users WHERE email = ?',
    [$email]
);

// Named binding
$users = DB::select(
    'SELECT * FROM users WHERE email = :email',
    ['email' => $email]
);

// SEHV: Raw ifade (SQL Injection tehlukesi!)
// $users = DB::select(
//     "SELECT * FROM users WHERE email = '$email'"
// ); // HECH VAXT BELE ETMEYIN!
```

### Encryption

```php
use Illuminate\Support\Facades\Crypt;

class SensitiveDataService
{
    // Verileneri shifrele
    public function storeApiKey(User $user, string $apiKey): void
    {
        $user->update([
            'api_key' => Crypt::encryptString($apiKey),
        ]);
    }

    // Shifreni ac
    public function getApiKey(User $user): string
    {
        return Crypt::decryptString($user->api_key);
    }
}

// Model seviyyesinde avtomatik encryption
class User extends Authenticatable
{
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'ssn' => 'encrypted',
        ];
    }
}
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **CSRF token** | `_csrf` hidden field (avtomatik) | `@csrf` directive |
| **CSRF API** | Sondurulur + JWT | Sanctum / API token |
| **CORS** | Java Config / `@CrossOrigin` | config/cors.php |
| **XSS** | Thymeleaf `th:text` escape | Blade `{{ }}` escape |
| **Password hash** | `PasswordEncoder` bean | `Hash::make()` facade |
| **Hash algoritm** | BCrypt, Argon2, SCrypt, PBKDF2 | BCrypt, Argon2 |
| **Rate limiting** | Xarici (Bucket4j ve s.) | Daxili `RateLimiter` |
| **SQL Injection** | JPA parameterized query | Eloquent / Query Builder |
| **Security headers** | `http.headers()` konfiq | Middleware |
| **Encryption** | Xarici kitabxana | `Crypt` facade (daxili) |

## Niye bele ferqler var?

**Spring Security-nin murekkebliyi:**
Spring Security enterprise tetbiqler ucun nezerde tutulub ve son derece konfiqurasiya oluna bilen bir framework-dur. LDAP, OAuth2, SAML, Kerberos kimi muxtelif autentifikasiya mexanizmlerini destekleyir. Bu gucludur, amma oyrenmesi cetindir. Konfiqurasiya Java kodu ile yazilir ve filter chain mexanizmi ile isleyir.

**Laravel-in sade yanasmasi:**
Laravel tehlukesizlik mexanizmlerini "convention over configuration" prensipi ile teqdim edir. `@csrf` directive, `{{ }}` escaping, `Hash::make()` -- bunlar sadedir ve derhal isleyir. Elave konfiqurasiyaya az ehtiyac var. Bu, kicik ve orta olculu tetbiqler ucun idealdir.

**CORS:**
Spring-de CORS hem qlobal (Java Config), hem de controller seviyyesinde (`@CrossOrigin`) konfiqurasiya oluna biler. Laravel-de ise tek bir konfiqurasiya faylidir (`config/cors.php`). Laravel-in yanasmasi daha sadedir, Spring-inki daha cevikdir.

**Rate Limiting:**
Laravel rate limiting-i framework daxilinde teqdim edir -- `RateLimiter::for()` ile sade sade konfiqurasiya olunur. Spring-de ise xarici kitabxana (Bucket4j, Resilience4j) lazimdir. Bu ferq yene de Spring-in "her sheyi ozun sec" ve Laravel-in "hershey hazirdir" felsefesinden gelir.

## Hansi framework-de var, hansinda yoxdur?

**Yalniz Spring-de:**
- Spring Security filter chain -- murekkeb security pipeline
- `@CrossOrigin` -- controller/method seviyyesinde CORS
- `@PreAuthorize`, `@PostAuthorize` -- method seviyyesinde avtorizasiya SpEL ile
- `@Secured`, `@RolesAllowed` -- role-based access control annotasiyalari
- LDAP, SAML, Kerberos inteqrasiyasi
- `DelegatingPasswordEncoder` -- birden cox hash algoritmi eyni anda
- Security context propagation (thread-ler arasi)
- Content-Security-Policy header-i avtomatik konfiqurasiyasi

**Yalniz Laravel-de:**
- `@csrf` Blade directive -- bir soze CSRF token
- `Crypt::encryptString()` / `Crypt::decryptString()` -- daxili encryption
- `encrypted` model cast -- avtomatik field encryption
- `Hash::needsRehash()` -- hash-in yenilenmesi lazim olub-olmadiqini yoxlamaq
- `RateLimiter::for()` -- daxili rate limiting konfiqurasiyasi
- `throttle` middleware -- route seviyyesinde rate limiting
- Sanctum -- SPA ve mobile ucun sade token authentication
- `@json()` Blade directive -- tehlukesiz JSON output
