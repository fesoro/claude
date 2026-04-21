# Spring Security OAuth2 Resource Server vs Laravel API Auth

## Giriş

Resource server — qorunan API-dir. Müştəri (SPA, mobil tətbiq, başqa servis) ona sorğu göndərəndə header-də bir token daşıyır və server həmin token-in etibarlı olub-olmadığını yoxlayır. Token iki formatda gələ bilər: **JWT** (özü-özünə yetərli, imzalıdır) və ya **opaque token** (random string, serverdə introspection lazımdır).

Spring dünyasında bu iş üçün `spring-boot-starter-oauth2-resource-server` starter-i var — bir neçə sətir `application.yml` konfiqurasiyası ilə JWT validation açılır. Laravel-də isə üç yol var: **Sanctum** (sadə API token + SPA auth), **Passport** (tam OAuth2 server), və ya `tymon/jwt-auth` kimi üçüncü tərəf paket.

Fərq burdadır: Spring-də resource server yalnız token-i yoxlayır — issuer ayrıca serverdir (Keycloak, Auth0, Okta). Laravel isə tez-tez "hər şeyi mən edim" yanaşması ilə token həm issue, həm validate edir. Production-da microservices mühitində Spring modeli daha təmizdir.

---

## Spring-də istifadəsi

### 1) Starter və asılılıq

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-security</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-oauth2-resource-server</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-web</artifactId>
</dependency>
```

### 2) JWT validation — minimal konfiq

```yaml
# application.yml
spring:
  security:
    oauth2:
      resourceserver:
        jwt:
          issuer-uri: https://auth.example.com/realms/myapp
          # JWK Set URI auto-discovery olunur (issuer + /.well-known/openid-configuration)
          # Manual da vermək olar:
          jwk-set-uri: https://auth.example.com/realms/myapp/protocol/openid-connect/certs
          audiences:
            - my-api
```

Spring Boot bu konfiqlə avtomatik `JwtDecoder` bean yaradır: issuer-i yoxlayır, JWK endpoint-dən public key-ləri çəkir, cache edir və token-in imzasını doğrulayır.

### 3) SecurityFilterChain bean — Spring Security 6 modeli

Spring Security 6-da köhnə `WebSecurityConfigurerAdapter` silindi. İndi bütün konfiq `SecurityFilterChain` bean kimi yazılır:

```java
@Configuration
@EnableWebSecurity
@EnableMethodSecurity(prePostEnabled = true)
public class SecurityConfig {

    @Bean
    public SecurityFilterChain filterChain(HttpSecurity http,
                                           JwtAuthenticationConverter jwtConverter) throws Exception {
        http
            .authorizeHttpRequests(auth -> auth
                .requestMatchers("/actuator/health", "/actuator/info").permitAll()
                .requestMatchers("/public/**").permitAll()
                .requestMatchers(HttpMethod.POST, "/api/admin/**").hasRole("ADMIN")
                .requestMatchers("/api/**").authenticated()
                .anyRequest().denyAll()
            )
            .oauth2ResourceServer(oauth2 -> oauth2
                .jwt(jwt -> jwt.jwtAuthenticationConverter(jwtConverter))
                .authenticationEntryPoint(new BearerTokenAuthenticationEntryPoint())
                .accessDeniedHandler(new BearerTokenAccessDeniedHandler())
            )
            .sessionManagement(s -> s.sessionCreationPolicy(SessionCreationPolicy.STATELESS))
            .csrf(AbstractHttpConfigurer::disable)   // stateless API — CSRF lazım deyil
            .cors(Customizer.withDefaults())
            .exceptionHandling(ex -> ex
                .authenticationEntryPoint((req, res, e) -> {
                    // 401 Unauthorized — token yoxdur və ya yanlışdır
                    res.setStatus(HttpStatus.UNAUTHORIZED.value());
                    res.setContentType("application/json");
                    res.getWriter().write("""
                        {"error":"unauthorized","message":"Valid token required"}
                        """);
                })
                .accessDeniedHandler((req, res, e) -> {
                    // 403 Forbidden — token var, amma icazə yoxdur
                    res.setStatus(HttpStatus.FORBIDDEN.value());
                    res.setContentType("application/json");
                    res.getWriter().write("""
                        {"error":"forbidden","message":"Insufficient permissions"}
                        """);
                })
            );

        return http.build();
    }

    @Bean
    public CorsConfigurationSource corsConfigurationSource() {
        CorsConfiguration config = new CorsConfiguration();
        config.setAllowedOrigins(List.of("https://app.example.com"));
        config.setAllowedMethods(List.of("GET", "POST", "PUT", "DELETE", "OPTIONS"));
        config.setAllowedHeaders(List.of("Authorization", "Content-Type"));
        config.setExposedHeaders(List.of("X-Total-Count"));
        config.setAllowCredentials(true);
        config.setMaxAge(3600L);

        UrlBasedCorsConfigurationSource source = new UrlBasedCorsConfigurationSource();
        source.registerCorsConfiguration("/**", config);
        return source;
    }
}
```

**401 vs 403 fərqi:** 401 — sən kimsən bilmirəm (token yoxdur və ya yanlış). 403 — kim olduğunu bilirəm, amma bu əməliyyata icazən yoxdur.

### 4) JwtAuthenticationConverter — claim-ləri authority-lərə çevirmək

Default olaraq Spring Security `scope` claim-indən `SCOPE_xxx` authority yaradır. Keycloak-dan gələn token-lərdə isə rollar `realm_access.roles` altındadır — custom converter lazımdır:

```java
@Bean
public JwtAuthenticationConverter jwtAuthenticationConverter() {
    JwtAuthenticationConverter converter = new JwtAuthenticationConverter();

    converter.setJwtGrantedAuthoritiesConverter(jwt -> {
        Collection<GrantedAuthority> authorities = new ArrayList<>();

        // Standart scope-lar: "scope":"read write"
        String scopes = jwt.getClaimAsString("scope");
        if (scopes != null) {
            Arrays.stream(scopes.split(" "))
                .map(s -> new SimpleGrantedAuthority("SCOPE_" + s))
                .forEach(authorities::add);
        }

        // Keycloak realm roles: "realm_access":{"roles":["USER","ADMIN"]}
        Map<String, Object> realmAccess = jwt.getClaim("realm_access");
        if (realmAccess != null && realmAccess.get("roles") instanceof List<?> roles) {
            roles.stream()
                .map(Object::toString)
                .map(r -> new SimpleGrantedAuthority("ROLE_" + r))
                .forEach(authorities::add);
        }

        // Keycloak client roles: "resource_access":{"my-api":{"roles":["writer"]}}
        Map<String, Object> resourceAccess = jwt.getClaim("resource_access");
        if (resourceAccess != null && resourceAccess.get("my-api") instanceof Map<?, ?> myApi) {
            Object clientRoles = myApi.get("roles");
            if (clientRoles instanceof List<?> list) {
                list.stream()
                    .map(Object::toString)
                    .map(r -> new SimpleGrantedAuthority("ROLE_" + r))
                    .forEach(authorities::add);
            }
        }

        // Custom permissions claim: "permissions":["post:write","post:delete"]
        List<String> permissions = jwt.getClaimAsStringList("permissions");
        if (permissions != null) {
            permissions.stream()
                .map(p -> new SimpleGrantedAuthority("PERM_" + p))
                .forEach(authorities::add);
        }

        return authorities;
    });

    // principal-ın adını hansı claim-dən götürək
    converter.setPrincipalClaimName("preferred_username");
    return converter;
}
```

### 5) Custom JwtDecoder — audience və əlavə yoxlama

```java
@Bean
public JwtDecoder jwtDecoder(OAuth2ResourceServerProperties props) {
    NimbusJwtDecoder decoder = NimbusJwtDecoder
        .withJwkSetUri(props.getJwt().getJwkSetUri())
        .jwsAlgorithm(SignatureAlgorithm.RS256)
        .build();

    OAuth2TokenValidator<Jwt> validators = new DelegatingOAuth2TokenValidator<>(
        JwtValidators.createDefaultWithIssuer(props.getJwt().getIssuerUri()),
        new JwtAudienceValidator("my-api"),
        new JwtTimestampValidator(Duration.ofSeconds(30))   // clock skew
    );
    decoder.setJwtValidator(validators);
    return decoder;
}

public class JwtAudienceValidator implements OAuth2TokenValidator<Jwt> {
    private final String audience;

    public JwtAudienceValidator(String audience) { this.audience = audience; }

    @Override
    public OAuth2TokenValidatorResult validate(Jwt jwt) {
        if (jwt.getAudience().contains(audience)) {
            return OAuth2TokenValidatorResult.success();
        }
        return OAuth2TokenValidatorResult.failure(new OAuth2Error(
            "invalid_audience",
            "Required audience missing: " + audience,
            null
        ));
    }
}
```

### 6) Opaque token introspection

Bütün token-lər JWT olmur — bəzən issuer random string qaytarır və hər sorğuda `/introspect` endpoint-inə yoxlamaq lazım olur:

```yaml
spring:
  security:
    oauth2:
      resourceserver:
        opaquetoken:
          introspection-uri: https://auth.example.com/oauth2/introspect
          client-id: my-api
          client-secret: ${INTROSPECTION_SECRET}
```

```java
http.oauth2ResourceServer(oauth2 -> oauth2
    .opaqueToken(Customizer.withDefaults())
);
```

Introspection hər sorğuda şəbəkə gediş-gəliş tələb edir — cache lazımdır:

```java
@Bean
public OpaqueTokenIntrospector introspector(OAuth2ResourceServerProperties props) {
    return new CachingOpaqueTokenIntrospector(
        new SpringOpaqueTokenIntrospector(
            props.getOpaquetoken().getIntrospectionUri(),
            props.getOpaquetoken().getClientId(),
            props.getOpaquetoken().getClientSecret()
        ),
        Duration.ofMinutes(5)
    );
}
```

### 7) Multi-tenant resource server

Bir resource server iki və ya daha çox issuer-dan token qəbul edə bilər — məsələn, bir tenant Keycloak, digəri Auth0:

```java
@Bean
public AuthenticationManagerResolver<HttpServletRequest> authManagerResolver() {
    Map<String, AuthenticationManager> managers = new HashMap<>();

    managers.put("tenant1", buildManager("https://auth1.example.com/realms/t1"));
    managers.put("tenant2", buildManager("https://auth2.example.com/"));

    return request -> {
        String tenantId = request.getHeader("X-Tenant-ID");
        AuthenticationManager manager = managers.get(tenantId);
        if (manager == null) {
            throw new InvalidBearerTokenException("Unknown tenant");
        }
        return manager;
    };
}

private AuthenticationManager buildManager(String issuer) {
    JwtDecoder decoder = JwtDecoders.fromIssuerLocation(issuer);
    JwtAuthenticationProvider provider = new JwtAuthenticationProvider(decoder);
    return provider::authenticate;
}

// SecurityFilterChain-də:
http.oauth2ResourceServer(oauth2 -> oauth2
    .authenticationManagerResolver(authManagerResolver())
);
```

### 8) Method security — claim-lərə görə icazə

```java
@RestController
@RequestMapping("/api/posts")
public class PostController {

    @GetMapping
    @PreAuthorize("hasAuthority('SCOPE_read')")
    public List<PostDto> list(@AuthenticationPrincipal Jwt jwt) {
        String userId = jwt.getSubject();
        return postService.listForUser(userId);
    }

    @PostMapping
    @PreAuthorize("hasAuthority('SCOPE_write') and hasAuthority('ROLE_USER')")
    public PostDto create(@RequestBody PostDto dto,
                          @AuthenticationPrincipal Jwt jwt) {
        return postService.create(dto, jwt.getSubject());
    }

    @DeleteMapping("/{id}")
    @PreAuthorize("hasAuthority('ROLE_ADMIN') or @postSecurity.isOwner(#id, authentication.name)")
    public void delete(@PathVariable Long id) {
        postService.delete(id);
    }

    // Tenant claim üzrə filter
    @GetMapping("/tenant")
    @PreAuthorize("authentication.token.claims['tenant_id'] == #tenantId")
    public List<PostDto> byTenant(@RequestParam String tenantId) {
        return postService.listByTenant(tenantId);
    }
}

@Component("postSecurity")
public class PostSecurity {
    private final PostRepository repo;
    public PostSecurity(PostRepository repo) { this.repo = repo; }

    public boolean isOwner(Long postId, String username) {
        return repo.findById(postId)
            .map(p -> p.getOwner().equals(username))
            .orElse(false);
    }
}
```

### 9) Test — `@WithMockJwt`

```java
@WebMvcTest(PostController.class)
class PostControllerTest {

    @Autowired MockMvc mvc;

    @Test
    void unauthenticated_returns_401() throws Exception {
        mvc.perform(get("/api/posts"))
            .andExpect(status().isUnauthorized());
    }

    @Test
    void authenticated_without_scope_returns_403() throws Exception {
        mvc.perform(get("/api/posts")
                .with(jwt().authorities(new SimpleGrantedAuthority("SCOPE_other"))))
            .andExpect(status().isForbidden());
    }

    @Test
    void authenticated_with_read_scope_returns_200() throws Exception {
        mvc.perform(get("/api/posts")
                .with(jwt()
                    .jwt(j -> j.subject("user-123").claim("tenant_id", "t1"))
                    .authorities(new SimpleGrantedAuthority("SCOPE_read"))))
            .andExpect(status().isOk());
    }
}
```

---

## Laravel-də istifadəsi

Laravel-də üç əsas auth sistemi var: **Sanctum** (sadə, 80% layihədə kifayət), **Passport** (tam OAuth2 server), **JWT paketləri** (`tymon/jwt-auth`, `firebase/php-jwt`).

### 1) Sanctum — kurulum

```bash
composer require laravel/sanctum
php artisan install:api
# Laravel 11-də bu komanda api routes, Sanctum və migration-ları qurur
```

```php
// config/sanctum.php
return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,127.0.0.1')),
    'guard' => ['web'],
    'expiration' => 60 * 24,        // 24 saat — null olsa heç vaxt bitməz
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
    'middleware' => [
        'authenticate_session' => Authenticate::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
```

### 2) Personal Access Token + abilities

```php
// app/Models/User.php
class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
}

// Token yaradılması — login endpoint
class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = $request->user();
        $token = $user->createToken(
            name: $request->userAgent() ?? 'api',
            abilities: ['posts:read', 'posts:write'],
            expiresAt: now()->addDays(7),
        );

        return response()->json([
            'token' => $token->plainTextToken,   // yalnız bu dəfə göstərilir
            'expires_at' => $token->accessToken->expires_at,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function logoutAll(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'All devices logged out']);
    }
}
```

### 3) Route-larda `auth:sanctum` və abilities

```php
// routes/api.php
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $r) => $r->user());
    Route::post('/logout', [AuthController::class, 'logout']);

    // Ability tələb et (JWT-də scope-a bənzər)
    Route::get('/posts', [PostController::class, 'index'])
        ->middleware('ability:posts:read');

    Route::post('/posts', [PostController::class, 'store'])
        ->middleware('abilities:posts:read,posts:write');   // hər ikisi lazımdır

    Route::delete('/posts/{post}', [PostController::class, 'destroy'])
        ->middleware('ability:posts:delete');
});
```

Controller daxilində də yoxlanıla bilər:

```php
public function store(Request $request)
{
    if (! $request->user()->tokenCan('posts:write')) {
        abort(403, 'Missing ability: posts:write');
    }
    // ...
}
```

### 4) SPA auth — stateful mode (cookie-based)

Sanctum SPA üçün ayrıca mexanizm verir — token əvəzinə `laravel_session` cookie istifadə olunur, `XSRF-TOKEN` header ilə CSRF qoruması var:

```php
// bootstrap/app.php (Laravel 11)
->withMiddleware(function (Middleware $middleware) {
    $middleware->statefulApi();
    $middleware->api(prepend: [
        \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    ]);
})
```

```javascript
// Frontend (axios)
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;

// 1. CSRF cookie al
await axios.get('/sanctum/csrf-cookie');

// 2. Login
await axios.post('/login', { email, password });

// 3. Sonrakı sorğular avtomatik cookie daşıyır
const posts = await axios.get('/api/posts');
```

### 5) Passport — tam OAuth2 server

Passport `league/oauth2-server` üzərində qurulub və authorization_code, client_credentials, password (deprecated), personal access token verir:

```bash
composer require laravel/passport
php artisan migrate
php artisan passport:install
php artisan passport:keys
```

```php
// config/auth.php
'guards' => [
    'api' => [
        'driver' => 'passport',
        'provider' => 'users',
    ],
],

// app/Providers/AppServiceProvider.php
public function boot(): void
{
    Passport::tokensExpireIn(now()->addDays(15));
    Passport::refreshTokensExpireIn(now()->addDays(30));
    Passport::personalAccessTokensExpireIn(now()->addMonths(6));

    Passport::tokensCan([
        'posts:read'  => 'Read posts',
        'posts:write' => 'Create and edit posts',
        'admin'       => 'Admin access',
    ]);
    Passport::setDefaultScope(['posts:read']);
}
```

Route-larda `auth:api` middleware + `scope` middleware istifadə olunur:

```php
Route::middleware(['auth:api', 'scope:posts:write'])->post('/posts', ...);
Route::middleware(['auth:api', 'scopes:posts:read,posts:write'])->post(...);   // hər ikisi
```

### 6) JWT guard — `tymon/jwt-auth`

Əgər layihə artıq JWT-ni resource server kimi validate etmək istəyirsə (məsələn, Spring-dən gələn token-i qəbul etmək):

```bash
composer require tymon/jwt-auth
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
php artisan jwt:secret
```

```php
// config/auth.php
'guards' => [
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

Xarici issuer (Keycloak, Auth0) JWT-ni qəbul etmək üçün custom guard:

```php
// app/Auth/ExternalJwtGuard.php
class ExternalJwtGuard implements Guard
{
    use GuardHelpers;

    public function __construct(
        private Request $request,
        private UserProvider $provider,
        private JwksClient $jwks,
    ) {}

    public function user(): ?Authenticatable
    {
        if ($this->user !== null) return $this->user;

        $token = $this->extractBearer();
        if (! $token) return null;

        try {
            $decoded = $this->decodeAndVerify($token);
        } catch (\Throwable $e) {
            return null;
        }

        // JWT sub claim-ə görə lokal user tap və ya yarat
        $user = User::firstOrCreate(
            ['external_id' => $decoded->sub],
            ['email' => $decoded->email ?? null, 'name' => $decoded->name ?? 'user']
        );

        // Token-dən gələn scope-ları cache-lə
        $user->setAttribute('token_scopes', $decoded->scope ? explode(' ', $decoded->scope) : []);
        $user->setAttribute('token_claims', (array) $decoded);

        return $this->user = $user;
    }

    private function extractBearer(): ?string
    {
        $header = $this->request->header('Authorization', '');
        return str_starts_with($header, 'Bearer ')
            ? substr($header, 7)
            : null;
    }

    private function decodeAndVerify(string $token): object
    {
        $keys = $this->jwks->getKeys();    // JWK Set URI-dan yüklənir, cache-lənir
        $decoded = JWT::decode($token, $keys);

        if (($decoded->iss ?? null) !== config('auth.jwt.issuer')) {
            throw new \RuntimeException('Invalid issuer');
        }
        if (! in_array(config('auth.jwt.audience'), (array) ($decoded->aud ?? []))) {
            throw new \RuntimeException('Invalid audience');
        }
        return $decoded;
    }

    public function validate(array $credentials = []): bool
    {
        return $this->user() !== null;
    }
}

// Register
Auth::extend('external-jwt', function ($app, $name, $config) {
    return new ExternalJwtGuard(
        request: $app['request'],
        provider: Auth::createUserProvider($config['provider']),
        jwks: $app->make(JwksClient::class),
    );
});
```

```php
// config/auth.php
'guards' => [
    'api' => [
        'driver' => 'external-jwt',
        'provider' => 'users',
    ],
],
```

### 7) Passport-dan Sanctum-a miqrasiya — nə üçün

Çox komanda Passport-u tərk edir. Səbəblər:

- Passport tam OAuth2 server qurmaq üçündür — əksər mobil/SPA layihələrdə yalnız token lazımdır, amma Passport 10 cədvəl, RSA açar cütü, scope tərifi tələb edir.
- Sanctum 1 cədvəl (`personal_access_tokens`) ilə eyni şeyi daha sadə verir.
- SPA üçün Sanctum-un cookie-based modeli (CSRF qoruma ilə) token-i `localStorage`-da saxlamaqdan daha təhlükəsizdir.
- Passport hələ də dəstəklənir, amma yeni layihələrdə Sanctum default-dır.

Miqrasiya strategiyası:

```php
// 1. Mövcud Passport token-ləri expire olana qədər hər iki guard işə salın
'guards' => [
    'api' => ['driver' => 'sanctum', 'provider' => 'users'],
    'api-legacy' => ['driver' => 'passport', 'provider' => 'users'],
],

// 2. Route middleware
Route::middleware(['auth:sanctum,api-legacy'])->group(function () {
    // Hər iki token işləyir
});

// 3. Yeni login Sanctum token qaytarır, köhnələri expire olduqda silin
```

### 8) Controller-də token claim-i oxumaq

```php
class PostController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Sanctum — ability yoxla
        if (! $user->tokenCan('posts:read')) {
            abort(403);
        }

        // Custom JWT guard — claim oxu
        $tenantId = $user->getAttribute('token_claims')['tenant_id'] ?? null;

        return Post::where('tenant_id', $tenantId)->get();
    }
}
```

### 9) Test

```php
use Laravel\Sanctum\Sanctum;

class PostApiTest extends TestCase
{
    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/posts')->assertStatus(401);
    }

    public function test_missing_ability_returns_403(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['other:scope']);
        $this->getJson('/api/posts')->assertStatus(403);
    }

    public function test_valid_ability_returns_200(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['posts:read']);
        $this->getJson('/api/posts')->assertStatus(200);
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| JWT validation | Built-in resource server starter | Custom guard + `firebase/php-jwt` |
| JWK Set auto-discovery | Var (issuer + `/.well-known/...`) | Manual (JwksClient paketi) |
| Opaque token introspection | Built-in | Manual HTTP çağırışı |
| Multi-issuer | `AuthenticationManagerResolver` | Custom middleware ilə əllə |
| Default token format | Yoxdur — issuer qərar verir | Sanctum: DB-də hash (random string) |
| Scope/ability modeli | `SCOPE_xxx` authority | `tokenCan('posts:read')` |
| Session+API mixed | Deyil (adətən ayrı FilterChain) | Sanctum SPA mode (cookie) |
| Token revocation | JWT-də yox, opaque-də var | Sanctum — DB-dən sil |
| CSRF for API | Disabled | Sanctum SPA mode-da aktiv |
| Password grant | Deprecated (hər yerdə) | Passport-da var (köhnəlmiş) |
| OAuth2 server rolu | Ayrıca (Authorization Server) | Passport daxildir |
| Test helpers | `.with(jwt())` MockMvc-də | `Sanctum::actingAs($user, $abilities)` |
| 401 vs 403 | `BearerTokenAuthenticationEntryPoint` + `AccessDeniedHandler` | `abort(401)` / `abort(403)` |

---

## Niyə belə fərqlər var?

**Spring microservice-first yanaşması.** Java enterprise mühitində tez-tez bir Authorization Server (Keycloak, Okta, Ping) 10-20 resource server-ə xidmət edir. Buna görə `spring-boot-starter-oauth2-resource-server` yalnız "token yoxla" rolunu təmiz şəkildə verir. Issuer-i issue etmir — bu başqa servisin işidir.

**Laravel monolit-first yanaşması.** Tarixən Laravel layihələri bir tətbiq idi — həm frontend, həm API, həm auth. Buna görə Sanctum həm issue, həm validate edir. Passport da eyni — bir Laravel app həm OAuth2 server, həm resource server ola bilər.

**PHP-də JWK caching çətindir.** Spring-də `NimbusJwtDecoder` public key-ləri avtomatik cache edir, rotation zamanı yeniləyir. PHP-də isə hər sorğu ayrı proses olduğu üçün cache manual (Redis/file) qurulmalıdır. Buna görə Laravel-də JWT resource server sənaryosu az istifadə olunur — Sanctum stateful token DB-dən yoxlamağı sadə görür.

**Sanctum-un sadəliyi niyə seçilir.** Bir cədvəl (`personal_access_tokens`), hash-lənmiş token, `HasApiTokens` trait — bu qədər. Mobil tətbiq üçün "login ver, token al, token-i header-də göndər" sənaryosu 10 dəqiqəyə qurulur. Spring-də eyni iş üçün ya Spring Authorization Server, ya da Keycloak qurmaq lazımdır.

**401 vs 403 distinction.** Spring Security bunu çox ciddi ayırır — `AuthenticationEntryPoint` 401, `AccessDeniedHandler` 403. Laravel-də tez-tez hər ikisi `abort()` ilə verilir və developer səhvən 403 yerinə 401 qaytara bilər. Prod-da bu fərq vacibdir — 401 client-ə "token yenilə" siqnal verir, 403 isə "heç vaxt olmayacaq" deməkdir.

**CSRF-in statusu.** Stateless JWT API-də CSRF saldırısı mümkün deyil (çünki brauzer avtomatik Bearer header göndərmir). Spring-də `.csrf(disable)` standart pattern-dir. Laravel Sanctum SPA mode-da isə cookie istifadə olunduğu üçün CSRF aktiv qalır — axios `withXSRFToken: true` ilə avtomatik həll edir.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- Built-in JWK Set auto-discovery və cache
- `AuthenticationManagerResolver` ilə multi-tenant resource server
- Opaque token introspection starter
- `BearerTokenAuthenticationEntryPoint` / `BearerTokenAccessDeniedHandler` — OAuth2 spec-ə uyğun error format
- `@AuthenticationPrincipal Jwt jwt` ilə type-safe claim access
- Test üçün `.with(jwt().jwt(j -> j.claim(...)))` fluent builder
- Clock skew validator (`JwtTimestampValidator`)
- `JwtAudienceValidator`, `DelegatingOAuth2TokenValidator` composition
- WebFlux üçün eyni starter (reactive resource server)

**Yalnız Laravel-də:**
- Sanctum SPA mode — cookie + CSRF + session birlikdə
- `tokenCan()` API — token abilities (fine-grained per-token)
- `personal_access_tokens` cədvəli ilə hash-based token (JWT-siz)
- `createToken(name, abilities, expiresAt)` — bir sətir token issue
- `$user->tokens()->delete()` — bir user-in bütün token-lərini revoke
- Passport ilə eyni app daxilində OAuth2 server + resource server
- Sanctum token prefix (`SANCTUM_TOKEN_PREFIX=prod_`) — log-da token sızmalarını tapmaq
- `Sanctum::actingAs($user, $abilities)` test helper

---

## Best Practices

1. **Stateless API-də CSRF-i söndür, session-u STATELESS et.** Spring-də `sessionCreationPolicy(STATELESS)` + `.csrf(disable)`. Laravel-də Sanctum default stateless-dir, SPA mode yalnız eyni domain-dən SPA üçündür.

2. **401 və 403-ü ayır.** Token yoxdur/yanlışdır → 401. Token var, amma icazə yoxdur → 403. Client-lər bunu fərqli idarə edir.

3. **JWT-də şəxsi məlumat saxlama.** JWT base64 imzalanıb, amma şifrələnməyib. Yalnız ID, rol, scope-lar qoy — email, telefon, kart nömrəsi qoyma.

4. **Audience və issuer hər ikisini yoxla.** Spring-də default issuer yoxlanır, audience əllə əlavə et. Laravel custom guard-da hər ikisini `decodeAndVerify()`-də yoxla.

5. **Token lifetime qısa olsun.** Access token 15-60 dəqiqə, refresh token gün/həftə. Spring-də tövsiyə Authorization Server tərəfindən gəlir, Laravel Sanctum-da `expiresAt` ver.

6. **Scope/ability granular olsun.** `posts:read`, `posts:write`, `admin:users` — ümumi `read`, `write` yerinə. İstifadəçi token-i kompromis olsa, zərər dar qalar.

7. **Revocation strategiyası qur.** JWT stateless-dir — logout olanda invalidate etmək çətindir. Həll: qısa TTL + refresh token revocation, və ya blacklist (Redis-də expire-ə qədər). Sanctum-da sadədir — DB-dən silmək kifayətdir.

8. **JWK rotation-ı test et.** Authorization Server açarları fırladanda (key rotation) resource server-in cache-i yenilənməlidir. Spring avtomatik edir, Laravel JwksClient-i `cache_ttl` parametri ilə qur.

9. **Rate limit + auth birlikdə.** Spring-də `HttpFirewall` + `bucket4j`, Laravel-də `throttle:60,1` middleware. Brute force və token stuffing hücumlarına qarşı.

10. **Test hər sənaryoda.** Unauth (401), yanlış scope (403), valid scope (200), expired token (401), yanlış audience (401), yanlış issuer (401).

---

## Yekun

Spring-də `spring-boot-starter-oauth2-resource-server` JWT validation-u 3 sətir YAML ilə açır — JWK auto-discovery, issuer yoxlaması, default authority converter daxildir. Real prod-da `JwtAuthenticationConverter` custom yazılır (Keycloak realm roles-u oxumaq üçün) və `SecurityFilterChain` bean-ı stateless konfiqurasiya ilə qurulur. Opaque token, multi-tenant, method security birinci-sinif dəstəklənir.

Laravel-də isə seçim layihə tipinə bağlıdır: mobil/SPA üçün **Sanctum** (token və ya cookie), tam OAuth2 server lazımdırsa **Passport**, xarici issuer-dən gələn JWT üçün **custom guard**. Sanctum sadə və 80% layihədə kifayətdir, Passport hər yeni layihədə seçilməyi dayandırıb (Sanctum-a miqrasiya yaygındır).

Microservices + external IdP (Keycloak, Auth0) mühitində Spring modeli təbii oturur. Monolith + öz auth server lazımdırsa, Laravel Passport və ya Sanctum daha qısa yoldur.
