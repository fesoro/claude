# Spring Session və Distributed Sessions — Dərin Müqayisə

## Giriş

HTTP stateless-dir — amma real tətbiqlər user state saxlamalıdır: kim daxil olub, səbətdə nə var, CSRF token hansıdır. İki əsas yanaşma var:

1. **Stateful session** — server yaddaşda (və ya Redis/DB-də) data saxlayır, client yalnız session ID cookie daşıyır.
2. **Stateless JWT** — bütün state JWT token-in özündədir, server heç nə saxlamır.

Çoxlu instansiya (horizontal scale) olanda stateful session-un problemi ortaya çıxır: user birinci node-a girdi, ikinci node-da "kimsən sən?" sualına cavab ala bilmir. Həlli — **distributed session store** (Redis, DB, Hazelcast). Buna "session replication" də deyirlər.

**Spring Session** — session saxlanışını abstrakt edir: kod dəyişmir, sadəcə Redis/JDBC starter əlavə edirsən və `HttpSession` avtomatik distributed olur. **Laravel**-də `SESSION_DRIVER` konfiqurasiyası ilə eyni şey: `file`, `database`, `redis`, `memcached`, `cookie`, `array`.

---

## Spring-də istifadəsi

### 1) Default `HttpSession` — embedded

Spring Boot default olaraq Tomcat session manager istifadə edir — yaddaşda. Tek-node üçün yetərlidir:

```java
@GetMapping("/set")
public String set(HttpSession session) {
    session.setAttribute("cart", List.of("item1", "item2"));
    return "ok";
}

@GetMapping("/get")
public Object get(HttpSession session) {
    return session.getAttribute("cart");
}
```

Horizontal scale olanda problem: hər node öz yaddaşını saxlayır. Sticky session (load balancer affinity) həll etsə də, node ölərsə user session itir.

### 2) Spring Session Redis

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-redis</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.session</groupId>
    <artifactId>spring-session-data-redis</artifactId>
</dependency>
```

`application.yml`:

```yaml
spring:
  data:
    redis:
      host: redis.internal
      port: 6379
      password: ${REDIS_PASSWORD}
  session:
    store-type: redis
    timeout: 30m
    redis:
      namespace: spring:session:myapp
      flush-mode: on_save
      save-mode: on_set_attribute
      repository-type: indexed         # ya default
```

Kod dəyişmir — `HttpSession` eyni işləyir, amma arxada Redis-ə yazılır. Hər node eyni Redis-ə baxır → session paylaşılır.

### 3) `@EnableRedisHttpSession` — manual konfiqurasiya

```java
@Configuration
@EnableRedisHttpSession(
    maxInactiveIntervalInSeconds = 1800,
    redisNamespace = "spring:session:checkout"
)
public class SessionConfig {

    @Bean
    public LettuceConnectionFactory redisConnectionFactory() {
        RedisStandaloneConfiguration cfg = new RedisStandaloneConfiguration("redis.internal", 6379);
        cfg.setPassword(System.getenv("REDIS_PASSWORD"));
        return new LettuceConnectionFactory(cfg);
    }

    @Bean
    public HttpSessionIdResolver httpSessionIdResolver() {
        return CookieHttpSessionIdResolver.defaultWithCookieSerializer(cookieSerializer());
    }

    @Bean
    public DefaultCookieSerializer cookieSerializer() {
        DefaultCookieSerializer serializer = new DefaultCookieSerializer();
        serializer.setCookieName("SESSIONID");
        serializer.setCookiePath("/");
        serializer.setDomainName("example.com");
        serializer.setUseSecureCookie(true);
        serializer.setUseHttpOnlyCookie(true);
        serializer.setSameSite("Lax");
        return serializer;
    }
}
```

### 4) JDBC backend

```xml
<dependency>
    <groupId>org.springframework.session</groupId>
    <artifactId>spring-session-jdbc</artifactId>
</dependency>
```

```yaml
spring:
  session:
    store-type: jdbc
    jdbc:
      initialize-schema: always
      table-name: SPRING_SESSION
      cleanup-cron: "0 * * * * *"
  datasource:
    url: jdbc:postgresql://db.internal/app
```

Redis-in sürəti yoxdur, amma Redis olmadan işləməlisənsə (minimal infra), DB yaxşı alternativdir. Spring Session bir `SPRING_SESSION` və `SPRING_SESSION_ATTRIBUTES` cədvəli yaradır.

### 5) Header-based session ID (mobile client)

Cookie köməyi ilə Android/iOS app-da session saxlamaq çətindir. Header istifadə et:

```java
@Bean
public HttpSessionIdResolver httpSessionIdResolver() {
    return HeaderHttpSessionIdResolver.xAuthToken();   // X-Auth-Token header
}
```

Client cavabdakı `X-Auth-Token`-ı saxlayır və sonrakı sorğularda göndərir — cookie lazım deyil.

### 6) Concurrent session control (Spring Security)

```java
@Configuration
public class SecurityConfig {

    @Bean
    public SecurityFilterChain filterChain(HttpSecurity http) throws Exception {
        http
            .sessionManagement(s -> s
                .sessionCreationPolicy(SessionCreationPolicy.IF_REQUIRED)
                .maximumSessions(1)                    // user yalnız 1 session
                .maxSessionsPreventsLogin(false)       // yeni giriş köhnəni kill edir
                .expiredUrl("/login?expired")
                .sessionRegistry(sessionRegistry())
            )
            .sessionFixation(SessionFixationConfigurer::newSession);  // ID-ni regenerate et
        return http.build();
    }

    @Bean
    public SpringSessionBackedSessionRegistry<?> sessionRegistry() {
        return new SpringSessionBackedSessionRegistry<>(sessionRepository);
    }
}
```

### 7) Session fixation protection

Login sonrası session ID-ni regenerate et (`newSession()`). Spring Security default bunu edir — köhnə session silinir, yenisi yaradılır.

### 8) Remember-me + Spring Session

```java
http
    .rememberMe(rm -> rm
        .key("remember-me-secret")
        .tokenValiditySeconds(14 * 24 * 3600)         // 2 həftə
        .rememberMeCookieName("REMEMBER_ME")
        .tokenRepository(persistentTokenRepository())
        .userDetailsService(userDetailsService)
    );
```

Remember-me ayrıca cookie-dir, session-dan kənar. User yenidən açanda bu cookie ilə avtomatik login olur və yeni session yaradılır.

### 9) WebSocket session inteqrasiya

```xml
<dependency>
    <groupId>org.springframework.session</groupId>
    <artifactId>spring-session-data-redis</artifactId>
</dependency>
```

```java
@Configuration
@EnableRedisIndexedHttpSession
public class SessionConfig {}
```

`@EnableRedisIndexedHttpSession` — session-lar Redis-də indexed saxlanır, WebSocket kimi long-lived protokollarda session timeout avtomatik uzadılır.

### 10) Reactive (WebFlux) session

```java
@Configuration
@EnableRedisWebSession
public class ReactiveSessionConfig {}
```

WebFlux-da `WebSession` istifadə olunur (`HttpSession` deyil):

```java
@GetMapping("/cart")
public Mono<List<String>> cart(WebSession session) {
    return Mono.just(session.getAttribute("cart"));
}
```

### 11) Custom session attribute

```java
@RestController
public class AuthController {

    @PostMapping("/login")
    public String login(@RequestBody Credentials c, HttpServletRequest req) {
        User user = authService.authenticate(c);
        req.getSession(true).setAttribute("USER_ID", user.getId());
        return "ok";
    }

    @GetMapping("/me")
    public Long me(HttpSession session) {
        return (Long) session.getAttribute("USER_ID");
    }

    @PostMapping("/logout")
    public void logout(HttpSession session) {
        session.invalidate();        // Redis-dən silir
    }
}
```

### 12) Session event listener

```java
@Component
public class SessionAuditor {

    @EventListener
    public void onCreated(SessionCreatedEvent e) {
        log.info("Session yaradıldı: {}", e.getSessionId());
    }

    @EventListener
    public void onExpired(SessionExpiredEvent e) {
        log.info("Session vaxtı bitdi: {}", e.getSessionId());
    }

    @EventListener
    public void onDeleted(SessionDeletedEvent e) {
        log.info("Session silindi: {}", e.getSessionId());
    }
}
```

### 13) JWT vs session — Spring Security ilə ikisini birləşdirmək

Stateless API-lər üçün JWT istifadə olunur — server heç nə saxlamır:

```java
http
    .sessionManagement(s -> s.sessionCreationPolicy(SessionCreationPolicy.STATELESS))
    .oauth2ResourceServer(r -> r.jwt(Customizer.withDefaults()));
```

Hibrid model — SPA browser session (CSRF üçün), mobile API JWT:

```java
http
    .securityMatcher("/api/**")
    .sessionManagement(s -> s.sessionCreationPolicy(SessionCreationPolicy.STATELESS))
    .oauth2ResourceServer(r -> r.jwt());

// ayrıca chain:
http
    .securityMatcher("/web/**")
    .sessionManagement(s -> s.sessionCreationPolicy(SessionCreationPolicy.IF_REQUIRED))
    .formLogin();
```

---

## Laravel-də istifadəsi

### 1) `config/session.php` — driver seçimi

```php
// config/session.php
return [
    'driver'          => env('SESSION_DRIVER', 'database'),
    'lifetime'        => env('SESSION_LIFETIME', 120),          // minutes
    'expire_on_close' => false,
    'encrypt'         => false,
    'files'           => storage_path('framework/sessions'),
    'connection'      => env('SESSION_CONNECTION'),
    'table'           => env('SESSION_TABLE', 'sessions'),
    'store'           => env('SESSION_STORE'),
    'lottery'         => [2, 100],
    'cookie'          => env('SESSION_COOKIE', 'laravel_session'),
    'path'            => '/',
    'domain'          => env('SESSION_DOMAIN'),
    'secure'          => env('SESSION_SECURE_COOKIE'),
    'http_only'       => true,
    'same_site'       => 'lax',
    'partitioned'     => false,
];
```

Driver-lər: `file`, `cookie`, `database`, `redis`, `memcached`, `array`, `apc`, `dynamodb`.

### 2) Redis session — scale üçün

`.env`:

```env
SESSION_DRIVER=redis
SESSION_CONNECTION=session      # redis config-də session əlavə
SESSION_LIFETIME=120
SESSION_COOKIE=laravel_session
SESSION_DOMAIN=.example.com
SESSION_SECURE_COOKIE=true
```

`config/database.php`:

```php
'redis' => [
    'session' => [
        'url'      => env('REDIS_URL'),
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port'     => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_SESSION_DB', '2'),  // ayrıca DB number
    ],
],
```

Horizontal scale olanda hər Laravel instansiyası eyni Redis-ə qoşulur — session paylaşılır.

### 3) Database session

```bash
php artisan session:table
php artisan migrate
```

`.env`:

```env
SESSION_DRIVER=database
SESSION_CONNECTION=pgsql
```

`sessions` cədvəli avtomatik yaradılır: `id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`.

### 4) Session API

```php
use Illuminate\Http\Request;

Route::post('/cart/add', function (Request $req) {
    $cart = $req->session()->get('cart', []);
    $cart[] = $req->input('product_id');
    $req->session()->put('cart', $cart);
    return response()->json(['cart' => $cart]);
});

Route::get('/cart', function (Request $req) {
    return $req->session()->get('cart', []);
});

// Facade variantı
use Illuminate\Support\Facades\Session;

Session::put('theme', 'dark');
Session::get('theme');
Session::has('theme');
Session::forget('theme');
Session::flush();
```

### 5) Flash data — next-request-only

```php
// İndiki və növbəti sorğuda mövcuddur
Session::flash('status', 'Message sent successfully');

// Controller
return redirect('/dashboard')->with('status', 'Saved!');

// Blade
@if (session('status'))
    <div class="alert">{{ session('status') }}</div>
@endif
```

### 6) CSRF token — session-a bağlı

```php
// Blade form
<form method="POST">
    @csrf
    <input name="email">
</form>

// ya ajax üçün
<meta name="csrf-token" content="{{ csrf_token() }}">

// JavaScript
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name=csrf-token]').content;
```

`VerifyCsrfToken` middleware bütün POST/PUT/DELETE sorğularını yoxlayır — token session-dan gəlir. Redis session driver + horizontal scale-də token hər node-da işləyir.

### 7) Sanctum SPA — session + XSRF cookie

Sanctum SPA auth-u JWT yox, Laravel session istifadə edir. Axından:

1. Client `/sanctum/csrf-cookie` çağırır → `XSRF-TOKEN` cookie alır.
2. Client `/login` POST edir, `X-XSRF-TOKEN` header-ində cookie-ni göndərir.
3. Laravel session yaradır, `laravel_session` cookie qaytarır.
4. Sonrakı hər sorğuda cookie avtomatik gedir.

```php
// bootstrap/app.php
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

->withMiddleware(function ($middleware) {
    $middleware->statefulApi();   // Sanctum-un middleware-i əlavə edir
});
```

`.env`:

```env
SESSION_DOMAIN=.example.com
SANCTUM_STATEFUL_DOMAINS=app.example.com
```

Client (axios):

```javascript
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;

await axios.get('/sanctum/csrf-cookie');
await axios.post('/login', { email, password });
await axios.get('/api/user');         // auth olunmuş
```

### 8) Session regeneration — login zamanı

Session fixation hücumuna qarşı login sonrası regenerate:

```php
public function login(Request $req)
{
    $credentials = $req->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (! Auth::attempt($credentials, $req->boolean('remember'))) {
        return back()->withErrors(['email' => 'Invalid credentials']);
    }

    $req->session()->regenerate();       // ID dəyişir, data qalır
    return redirect()->intended('/dashboard');
}

public function logout(Request $req)
{
    Auth::logout();
    $req->session()->invalidate();
    $req->session()->regenerateToken();  // yeni CSRF
    return redirect('/');
}
```

### 9) Remember-me

```php
if (Auth::attempt(['email' => $email, 'password' => $password], $remember = true)) {
    // cookie 5 il qalacaq (konfiqurasiya edilə bilər)
}
```

Laravel `remember_token` sütununu users cədvəlində saxlayır. Cookie-də bu token var. User gələndə Laravel token-i yoxlayır, yeni session yaradır.

### 10) Sessions cleanup

File driver-də qarbaj kolleksiya automatic (lottery). Database driver-də:

```bash
php artisan session:prune-expired      # Laravel 11+
```

Scheduler-ə əlavə:

```php
// bootstrap/app.php
->withSchedule(function ($schedule) {
    $schedule->command('session:prune-expired')->daily();
});
```

### 11) JWT alternativ (Sanctum token guard)

Mobile app üçün session əvəzinə API token:

```php
// User model
use Laravel\Sanctum\HasApiTokens;

$user = User::where('email', $email)->first();
if (! Hash::check($password, $user->password)) {
    throw ValidationException::withMessages(['email' => ['Invalid']]);
}
$token = $user->createToken('mobile', ['read', 'write'])->plainTextToken;

return response()->json(['token' => $token]);
```

Client `Authorization: Bearer <token>` header ilə gəlir. Server stateless olur, session lazım deyil.

### 12) Horizontal scale — stateful vs stateless decision

Stateful (Redis session):

```
[Load Balancer] → [Laravel-1] ↘
                  [Laravel-2] → [Redis] (session store)
                  [Laravel-3] ↗
```

Hər instansiya eyni Redis-ə baxır — user hansı node-a gəlsə, session tapılır. CSRF var, Flash var, Sanctum SPA rahat işləyir.

Stateless (JWT/Sanctum token):

```
[Load Balancer] → [Laravel-1/2/3]  (DB-də user tablosu, session yoxdur)
```

Her sorğu token ilə gəlir, server token-i yoxlayır (DB və ya JWT signature). Heç bir session store lazım deyil. Amma CSRF, Flash, session-əsaslı login çatışmır.

---

## Əsas fərqlər

| Xüsusiyyət | Spring Session | Laravel Session |
|---|---|---|
| Default store | Tomcat in-memory | file |
| Redis backend | `spring-session-data-redis` | `SESSION_DRIVER=redis` |
| JDBC/DB | `spring-session-jdbc` | `SESSION_DRIVER=database` |
| MongoDB | `spring-session-data-mongodb` | Community |
| Hazelcast | `spring-session-hazelcast` | Yoxdur |
| DynamoDB | Community | Yoxdur (first-party) |
| Cookie-əsaslı session | Default | `SESSION_DRIVER=cookie` |
| Header-əsaslı | `HeaderHttpSessionIdResolver` | Manual |
| Konfiqurasiya | `application.yml` + `@EnableXxxHttpSession` | `config/session.php` |
| Kod dəyişir mi? | Yox | Yox |
| Concurrent session control | Spring Security ilə | Yox (əl ilə) |
| Session fixation | `SessionFixationConfigurer` | `$req->session()->regenerate()` |
| Remember-me | Spring Security `rememberMe()` | `Auth::attempt($c, remember: true)` |
| CSRF | Security filter | `VerifyCsrfToken` middleware |
| WebSocket inteqrasiya | `@EnableRedisIndexedHttpSession` | Reverb ayrıca |
| Reactive session | `@EnableRedisWebSession` (WebFlux) | Octane-da eyni API |
| Cleanup | Redis TTL / JDBC cron | `session:prune-expired` |
| JWT alternativ | OAuth2 Resource Server | Sanctum (token və ya session) |

---

## Niyə belə fərqlər var?

**Process model fərqi.** Spring tətbiqi JVM-də uzun-ömürlü prosesdir — hər instansiya session-ları yaddaşda saxlaya bilər. PHP-də hər sorğu ayrı prosesdir — session yaddaşda saxlamaq mümkün deyil, default olaraq fayl sisteminə yazılır. Buna görə Laravel-də default `file` driver-dir, Spring-də Tomcat in-memory.

**Spring Session `HttpSession`-u əvəz edir.** Spring Session-un əsas gücü — kod dəyişmir. `request.getSession()` çağırırsan, arxada Redis. Laravel-də eyni — `$req->session()->put()` hər driver üçün eyni API.

**Concurrent session control.** Spring Security daxili verir (`maximumSessions(1)`). Laravel-də bu yoxdur — "user bir yerdən girə bilsin" istəsən, Redis-də user_id → session_id map saxlamalısan, login-də köhnə session-u silməlisən.

**CSRF strategiya.** Spring Security CSRF token-u cookie və ya header ilə ötürür. Laravel session-dan oxuyur. İkisi də session-əsaslıdır — amma Laravel-də "blade-də @csrf" demək daha sadədir, Spring-də konfigurasiya daha çevikdir.

**Hazelcast və digər store-lar.** Spring-də enterprise store-lar (Hazelcast, Infinispan) rəsmi dəstəklənir. Laravel-də Redis/DB/file kifayətdir — Laravel auditoriyası ənənəvi LAMP stack-dan gəlir, Hazelcast kimi Java-xas sistemləri az istifadə edir.

**Header-based ID.** Mobile app-da cookie problemlidir. Spring `HeaderHttpSessionIdResolver.xAuthToken()` ilə `X-Auth-Token` header-dən oxuyur. Laravel-də bu yoxdur, əvəzinə Sanctum token istifadə olunur (stateless).

**WebSocket indexed session.** Spring-də WebSocket açıq qalanda session timeout uzadılır — `@EnableRedisIndexedHttpSession` bunu təmin edir. Laravel-də Reverb ayrı prosesdir, session-a toxunmur — WebSocket bağlı olsa belə Laravel session vaxtı bitə bilər.

**Reactive (non-blocking).** WebFlux-da `HttpSession` sinxron API-dir. Spring `WebSession` (reactive) verir. Laravel Octane-da eyni blocking API işləyir — reactive session konsepti Laravel-də yoxdur.

**JWT qərarı.** İki ekosistemdə də JWT-yə sürətlə keçid prosesi var. Spring-də `SessionCreationPolicy.STATELESS` + OAuth2 Resource Server kifayətdir. Laravel Sanctum token guard eyni rolu oynayır. Amma SPA üçün hər ikisi də XSRF-lı session tövsiyə edir — çünki JWT-ni brauzerdə saxlamaq (localStorage) XSS-ə qarşı zəifdir.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- `HeaderHttpSessionIdResolver` — X-Auth-Token built-in
- `SpringSessionBackedSessionRegistry` — Spring Security concurrent session limit
- `@EnableRedisIndexedHttpSession` — WebSocket inteqrasiya
- `@EnableRedisWebSession` — reactive WebFlux session
- Hazelcast, MongoDB, DynamoDB first-party backend
- Session event-lər (`SessionCreatedEvent`, `SessionDeletedEvent`)
- Cookie serializer konfiqurasiyası (domain, SameSite, partitioned) bir yerdə
- Session cleanup cron (JDBC backend-də)

**Yalnız Laravel-də:**
- `cookie` driver — session bütövlükdə encrypted cookie içində (serversiz)
- Flash data (`Session::flash`, `->with()`) — next-request-only
- `@csrf` blade direktivi
- Sanctum SPA (session + XSRF) out-of-box
- `SESSION_LOTTERY` garbage collection
- `array` driver — test üçün
- Memcached və APC driver-lər
- `session:table` artisan command

**Hər ikisində var:**
- Redis backend
- DB backend
- Session fixation protection
- Remember-me
- Session timeout
- Horizontal scale üçün shared store
- Stateless JWT alternativ

---

## Best Practices

**Production-da heç vaxt `file` driver istifadə etmə.** Horizontal scale olanda hər node öz fayl sistemi var → session paylaşılmır. Redis ən yaxşısıdır, DB də işləyir (amma daha yavaş).

**Redis cluster — ayrıca DB number.** Session-ları cache-dən ayrıca Redis DB-də saxla (məs. `database: 2`). Cache flush session-ları öldürməsin.

**SameSite və Secure cookie.** Production-da `secure: true`, `same_site: lax` (və ya `strict` sensitiv tətbiqlərdə). Cross-domain iframe üçün `same_site: none` + `secure: true` məcburidir.

**Session ID uzunluğu.** Spring default 32 bytes, Laravel default 40 chars. Dəyişmə — aşağı salmaq təhlükəsizliyi azaldır.

**Login-də session regenerate.** Spring Security default edir. Laravel-də `$req->session()->regenerate()` əl ilə çağır — unutma.

**Session timeout-u aşağı et (sensitiv tətbiqlərdə).** Bank, admin paneli — 15-30 dəqiqə. Normal app — 2 saat. Absolute maximum timeout da qoy (8-24 saat).

**Stateless API üçün JWT seç.** Mobile app, mikroservis-lər arası çağırış, public API — JWT. Spring `SessionCreationPolicy.STATELESS`, Laravel Sanctum token guard.

**SPA üçün session + XSRF seç.** Brauzer tətbiqində localStorage JWT XSS-ə qarşı zəif. HttpOnly cookie + XSRF token daha təhlükəsizdir. Laravel Sanctum SPA və Spring Session bu modeli yaxşı dəstəkləyir.

**Concurrent session — ya limit qoy, ya list göstər.** User "başqa yerdə session aktivdir" xəbərdarlığı görsün. Spring Security `maximumSessions(1)` və ya sessionRegistry ilə list. Laravel-də əl ilə yazılır.

**Remember-me token-ləri ayrıca saxla.** Session-dan uzundur (2 həftə vs 2 saat), ayrıca cədvəldə saxlanmalıdır. `persistent_logins` cədvəli (Spring) və ya `users.remember_token` (Laravel).

**Session audit logla.** SessionCreated, SessionExpired, login/logout event-lərini logla. Şübhəli davranış (eyni user-dən 10 IP-dən session) aşkarlanmalıdır.

**CSRF double-submit pattern.** Yalnız cookie CSRF saxlama — cookie + header eyni dəyər, server müqayisə edir. Laravel və Spring ikisi də bunu edir.

**Session store backup.** Redis üçün persistence (AOF/RDB) aktiv et — prod-da Redis restart olsa bütün user-lər çıxa. Enterprise-da Redis cluster + failover.

**Cookie-based driver Laravel-də məhduddur.** Cookie max 4KB. Çoxlu data saxlamaq lazım gələrsə istifadə etmə — Redis seç.

---

## Yekun

**Spring Session** — `HttpSession`-u abstrakt edir. Redis, JDBC, MongoDB, Hazelcast backend-lərindən seç. Kod dəyişmir — `application.yml`-də store-type dəyişdirməklə distributed sessions alırsan. Spring Security ilə sıx inteqrasiya — concurrent session control, fixation protection, remember-me daxili. WebFlux üçün reactive `WebSession`, WebSocket üçün indexed session var.

**Laravel Session** — `SESSION_DRIVER` konfiqurasiyası ilə file/cookie/database/redis/memcached seçirsən. `$req->session()->put()` API hər driver üçün eynidir. CSRF, Flash data, Sanctum SPA Laravel session üzərində qurulub. Horizontal scale üçün Redis ən sürətlidir.

**Seçim:**

- **Stateful session (Redis):** SPA browser auth, CSRF vacib, flash data lazımdır, concurrent session limit — session store ən yaxşı seçim. Hər iki framework-də eyni dərəcədə asandır.
- **Stateless (JWT/Sanctum token):** Mobile app, mikroservislərarası API, public API — token-əsaslı daha təmizdir. Horizontal scale üçün heç bir paylaşılan state lazım deyil.
- **Hibrid:** SPA + mobile eyni backend — iki auth chain qur. Browser `/web/**` session ilə, mobile `/api/**` token ilə.

Hər iki framework-də distributed session konsepti yaxşı dəstəklənir. Əsas fərq — Laravel sadə `SESSION_DRIVER=redis` ilə sonuc alır; Spring daha çevik konfigurasiya verir (per-session namespace, indexed mode, cookie serializer) amma daha çox kod yazmaq lazım gəlir. Hər ikisi də production-da işləyir — seçim ekosistemin və stack-ın qalan hissəsindən asılıdır.
