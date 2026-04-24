# Spring Authorization Server vs Laravel Passport

> **Seviyye:** Expert ⭐⭐⭐⭐

## Giriş

Authorization Server — OAuth2/OIDC token-ləri issue edən servisdir. Client burada user-i login edir, icazə alır və cavabında `access_token` + `refresh_token` + (OIDC üçün) `id_token` qaytarılır. Sonra bu token-ləri resource server-ə göndərir.

Spring dünyasında uzun müddət `spring-security-oauth` layihəsi istifadə olunurdu, amma deprecated olundu. Onun yerinə rəsmi **Spring Authorization Server** gəldi — `spring-security-oauth2-authorization-server` starter-i. O, authorization_code + PKCE, client_credentials, refresh_token, device_code, OIDC discovery, userinfo, JWK rotation — hamısı OAuth2/OIDC spec-ə tam uyğun verir.

Laravel-də bu işi **Passport** görür — daxili `league/oauth2-server` üzərində qurulub. Authorization_code + PKCE, client_credentials, password (deprecated), personal access token dəstəkləyir. Laravel 11+ ilə Passport canlıdır, amma Sanctum-a miqrasiya yaygın oldu — çünki əksər layihələrə tam OAuth2 server lazım deyil.

Bu dərsdə hər iki sistemlə tam `authorization_code + PKCE` flow-u qurmağı, RegisteredClient/Client modelini, token customization-u və OIDC endpoint-lərini müqayisə edirik.

---

## Spring-də istifadəsi

### 1) Dependency

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-oauth2-authorization-server</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-jdbc</artifactId>
</dependency>
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-thymeleaf</artifactId>
</dependency>
```

### 2) Authorization server SecurityFilterChain

Authorization Server-in öz daxili filter chain-i var. O, `/oauth2/authorize`, `/oauth2/token`, `/oauth2/jwks`, `/.well-known/openid-configuration`, `/userinfo` endpoint-lərini açır:

```java
@Configuration
@EnableWebSecurity
public class AuthorizationServerConfig {

    @Bean
    @Order(1)
    public SecurityFilterChain authServerFilterChain(HttpSecurity http) throws Exception {
        OAuth2AuthorizationServerConfiguration.applyDefaultSecurity(http);

        http.getConfigurer(OAuth2AuthorizationServerConfigurer.class)
            .oidc(Customizer.withDefaults());   // OIDC endpoint-ləri aç

        http
            // Login səhifəsi olmayanda default login-ə yönləndir
            .exceptionHandling(ex -> ex.defaultAuthenticationEntryPointFor(
                new LoginUrlAuthenticationEntryPoint("/login"),
                new MediaTypeRequestMatcher(MediaType.TEXT_HTML)
            ))
            // Resource server kimi də işləyir (userinfo endpoint üçün)
            .oauth2ResourceServer(rs -> rs.jwt(Customizer.withDefaults()));

        return http.build();
    }

    @Bean
    @Order(2)
    public SecurityFilterChain defaultFilterChain(HttpSecurity http) throws Exception {
        http
            .authorizeHttpRequests(a -> a
                .requestMatchers("/login", "/error", "/css/**", "/assets/**").permitAll()
                .anyRequest().authenticated()
            )
            .formLogin(form -> form
                .loginPage("/login")
                .permitAll()
            );
        return http.build();
    }

    @Bean
    public AuthorizationServerSettings authorizationServerSettings() {
        return AuthorizationServerSettings.builder()
            .issuer("https://auth.example.com")
            .authorizationEndpoint("/oauth2/authorize")
            .tokenEndpoint("/oauth2/token")
            .jwkSetEndpoint("/oauth2/jwks")
            .tokenRevocationEndpoint("/oauth2/revoke")
            .tokenIntrospectionEndpoint("/oauth2/introspect")
            .oidcUserInfoEndpoint("/userinfo")
            .oidcClientRegistrationEndpoint("/connect/register")
            .build();
    }
}
```

### 3) RegisteredClientRepository — client-lər

```java
@Configuration
public class ClientConfig {

    @Bean
    public RegisteredClientRepository registeredClientRepository(JdbcTemplate jdbc) {
        // Web SPA — authorization_code + PKCE (client_secret-siz)
        RegisteredClient spaClient = RegisteredClient.withId("spa-client")
            .clientId("my-spa")
            .clientAuthenticationMethod(ClientAuthenticationMethod.NONE)   // public client
            .authorizationGrantType(AuthorizationGrantType.AUTHORIZATION_CODE)
            .authorizationGrantType(AuthorizationGrantType.REFRESH_TOKEN)
            .redirectUri("https://app.example.com/callback")
            .redirectUri("http://localhost:3000/callback")
            .scope(OidcScopes.OPENID)
            .scope(OidcScopes.PROFILE)
            .scope("posts:read")
            .scope("posts:write")
            .clientSettings(ClientSettings.builder()
                .requireAuthorizationConsent(true)
                .requireProofKey(true)       // PKCE məcburidir
                .build())
            .tokenSettings(TokenSettings.builder()
                .accessTokenTimeToLive(Duration.ofMinutes(15))
                .refreshTokenTimeToLive(Duration.ofDays(30))
                .reuseRefreshTokens(false)   // hər refresh yeni token
                .build())
            .build();

        // Backend servis — client_credentials
        RegisteredClient backendClient = RegisteredClient.withId("backend-service")
            .clientId("reports-service")
            .clientSecret("{bcrypt}$2a$10$...")
            .clientAuthenticationMethod(ClientAuthenticationMethod.CLIENT_SECRET_BASIC)
            .authorizationGrantType(AuthorizationGrantType.CLIENT_CREDENTIALS)
            .scope("reports:read")
            .scope("reports:write")
            .tokenSettings(TokenSettings.builder()
                .accessTokenTimeToLive(Duration.ofHours(1))
                .build())
            .build();

        // Mobil — device_code (TV, CLI)
        RegisteredClient deviceClient = RegisteredClient.withId("device-client")
            .clientId("my-cli")
            .clientAuthenticationMethod(ClientAuthenticationMethod.NONE)
            .authorizationGrantType(AuthorizationGrantType.DEVICE_CODE)
            .authorizationGrantType(AuthorizationGrantType.REFRESH_TOKEN)
            .scope("device:read")
            .build();

        JdbcRegisteredClientRepository repo = new JdbcRegisteredClientRepository(jdbc);
        repo.save(spaClient);
        repo.save(backendClient);
        repo.save(deviceClient);
        return repo;
    }

    @Bean
    public OAuth2AuthorizationService authorizationService(JdbcTemplate jdbc,
                                                          RegisteredClientRepository clients) {
        return new JdbcOAuth2AuthorizationService(jdbc, clients);
    }

    @Bean
    public OAuth2AuthorizationConsentService consentService(JdbcTemplate jdbc,
                                                           RegisteredClientRepository clients) {
        return new JdbcOAuth2AuthorizationConsentService(jdbc, clients);
    }
}
```

### 4) JWK Source — açar cütü

```java
@Configuration
public class JwkConfig {

    @Bean
    public JWKSource<SecurityContext> jwkSource() throws NoSuchAlgorithmException {
        KeyPair keyPair = generateRsaKey();
        RSAPublicKey publicKey = (RSAPublicKey) keyPair.getPublic();
        RSAPrivateKey privateKey = (RSAPrivateKey) keyPair.getPrivate();

        RSAKey rsaKey = new RSAKey.Builder(publicKey)
            .privateKey(privateKey)
            .keyID(UUID.randomUUID().toString())
            .keyUse(KeyUse.SIGNATURE)
            .algorithm(JWSAlgorithm.RS256)
            .build();

        return new ImmutableJWKSet<>(new JWKSet(rsaKey));
    }

    private KeyPair generateRsaKey() throws NoSuchAlgorithmException {
        KeyPairGenerator generator = KeyPairGenerator.getInstance("RSA");
        generator.initialize(2048);
        return generator.generateKeyPair();
    }

    @Bean
    public JwtDecoder jwtDecoder(JWKSource<SecurityContext> jwkSource) {
        return OAuth2AuthorizationServerConfiguration.jwtDecoder(jwkSource);
    }
}
```

**JWK rotation:** Prod-da açarlar keystore-dan (Vault, AWS KMS) yüklənməlidir və vaxtaşırı fırladılmalıdır. Köhnə açarlar JWK Set-də qalır (`kid` fərqlidir) ki, hələ etibarlı token-lər yoxlana bilsin:

```java
@Bean
public JWKSource<SecurityContext> rotatingJwkSource(KeyRotationService rotator) {
    return (jwkSelector, context) -> {
        JWKSet set = rotator.currentKeySet();   // aktiv + son 2 köhnə açar
        return jwkSelector.select(set);
    };
}
```

### 5) Token customizer — əlavə claim-lər

```java
@Bean
public OAuth2TokenCustomizer<JwtEncodingContext> jwtCustomizer(UserService userService) {
    return context -> {
        if (OAuth2TokenType.ACCESS_TOKEN.equals(context.getTokenType())) {
            Authentication principal = context.getPrincipal();
            if (principal.getPrincipal() instanceof UserDetails userDetails) {
                User user = userService.findByUsername(userDetails.getUsername());

                context.getClaims().claims(claims -> {
                    claims.put("email", user.getEmail());
                    claims.put("tenant_id", user.getTenantId());
                    claims.put("roles", user.getRoles());
                    claims.put("permissions", user.getPermissions());
                });
            }
        }

        if (OidcParameterNames.ID_TOKEN.equals(context.getTokenType().getValue())) {
            // ID token-ə əlavə profil claim-ləri
            context.getClaims().claims(claims -> {
                claims.put("preferred_username", context.getPrincipal().getName());
            });
        }
    };
}
```

### 6) Consent səhifəsi — custom

Default consent səhifəsi sadədir. Custom UI vermək üçün:

```java
http.getConfigurer(OAuth2AuthorizationServerConfigurer.class)
    .authorizationEndpoint(endpoint -> endpoint.consentPage("/oauth2/consent"));
```

```java
@Controller
public class ConsentController {

    private final RegisteredClientRepository clients;
    private final OAuth2AuthorizationConsentService consents;

    @GetMapping("/oauth2/consent")
    public String consent(Principal principal, Model model,
                         @RequestParam("client_id") String clientId,
                         @RequestParam("scope") String scope,
                         @RequestParam("state") String state,
                         @RequestParam(name = "user_code", required = false) String userCode) {

        RegisteredClient client = clients.findByClientId(clientId);

        // User artıq hansı scope-ları təsdiqləyib?
        OAuth2AuthorizationConsent currentConsent = consents.findById(client.getId(), principal.getName());
        Set<String> previouslyApproved = currentConsent != null
            ? currentConsent.getScopes()
            : Set.of();

        Set<String> requested = StringUtils.commaDelimitedListToSet(scope.replace(" ", ","));
        Set<String> toApprove = new HashSet<>(requested);
        toApprove.removeAll(previouslyApproved);

        model.addAttribute("clientId", clientId);
        model.addAttribute("clientName", client.getClientName());
        model.addAttribute("state", state);
        model.addAttribute("scopes", toApprove.stream()
            .map(s -> new ScopeDisplay(s, scopeDescription(s)))
            .toList());
        model.addAttribute("principalName", principal.getName());
        return "consent";
    }

    private String scopeDescription(String scope) {
        return switch (scope) {
            case "openid" -> "Authenticate you";
            case "profile" -> "Access your profile";
            case "posts:read" -> "Read your posts";
            case "posts:write" -> "Create and edit posts";
            default -> scope;
        };
    }
}
```

### 7) Tam authorization_code + PKCE flow — client side

SPA-də PKCE-nin tam axını:

```javascript
// 1. code_verifier və code_challenge yarat
function generateVerifier() {
    const array = new Uint8Array(32);
    crypto.getRandomValues(array);
    return base64UrlEncode(array);
}
async function challenge(verifier) {
    const data = new TextEncoder().encode(verifier);
    const hash = await crypto.subtle.digest('SHA-256', data);
    return base64UrlEncode(new Uint8Array(hash));
}

// 2. Authorization redirect
const verifier = generateVerifier();
sessionStorage.setItem('pkce_verifier', verifier);
const codeChallenge = await challenge(verifier);

const params = new URLSearchParams({
    response_type: 'code',
    client_id: 'my-spa',
    redirect_uri: 'https://app.example.com/callback',
    scope: 'openid profile posts:read',
    state: generateState(),
    code_challenge: codeChallenge,
    code_challenge_method: 'S256',
});
window.location.href = `https://auth.example.com/oauth2/authorize?${params}`;

// 3. Callback — /callback?code=xxx&state=yyy
const code = new URLSearchParams(window.location.search).get('code');
const verifier = sessionStorage.getItem('pkce_verifier');

const response = await fetch('https://auth.example.com/oauth2/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
        grant_type: 'authorization_code',
        client_id: 'my-spa',
        code,
        redirect_uri: 'https://app.example.com/callback',
        code_verifier: verifier,
    }),
});
const { access_token, id_token, refresh_token } = await response.json();
```

### 8) Discovery endpoint

Spring Authorization Server avtomatik `/.well-known/openid-configuration` verir:

```json
{
  "issuer": "https://auth.example.com",
  "authorization_endpoint": "https://auth.example.com/oauth2/authorize",
  "token_endpoint": "https://auth.example.com/oauth2/token",
  "jwks_uri": "https://auth.example.com/oauth2/jwks",
  "userinfo_endpoint": "https://auth.example.com/userinfo",
  "revocation_endpoint": "https://auth.example.com/oauth2/revoke",
  "introspection_endpoint": "https://auth.example.com/oauth2/introspect",
  "response_types_supported": ["code"],
  "grant_types_supported": ["authorization_code", "client_credentials", "refresh_token", "device_code"],
  "scopes_supported": ["openid", "profile", "posts:read", "posts:write"],
  "code_challenge_methods_supported": ["S256"]
}
```

---

## Laravel-də istifadəsi

### 1) Passport kurulum

```bash
composer require laravel/passport
php artisan migrate
php artisan passport:keys
php artisan passport:client --personal    # Personal access client
```

```php
// config/auth.php
'guards' => [
    'api' => [
        'driver' => 'passport',
        'provider' => 'users',
    ],
],
```

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    Passport::tokensExpireIn(now()->addMinutes(15));
    Passport::refreshTokensExpireIn(now()->addDays(30));
    Passport::personalAccessTokensExpireIn(now()->addMonths(6));

    Passport::enableImplicitGrant();   // köhnədir, istəmirsənsə çıxar
    Passport::tokensCan([
        'posts:read' => 'Read posts',
        'posts:write' => 'Create and edit posts',
        'admin' => 'Full admin access',
    ]);
    Passport::setDefaultScope(['posts:read']);

    // Hansı model-i istifadə edək (custom override)
    Passport::useClientModel(\App\Models\OauthClient::class);
    Passport::useTokenModel(\App\Models\OauthToken::class);
}
```

### 2) User model

```php
class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
}
```

### 3) PKCE client yaratmaq

```bash
# SPA client — client_secret yoxdur, PKCE məcburi
php artisan passport:client --public --redirect_uri=https://app.example.com/callback

# Backend client — client_credentials
php artisan passport:client --client
```

Və ya kodda:

```php
use Laravel\Passport\ClientRepository;

$clients = app(ClientRepository::class);

$spaClient = $clients->createAuthorizationCodeGrantClient(
    name: 'My SPA',
    redirectUris: ['https://app.example.com/callback'],
    confidential: false,     // public client — PKCE işarəsidir
    user: null,
);

$machineClient = $clients->createClientCredentialsGrantClient(
    name: 'Reports Service',
);
```

### 4) Route-lar — Passport avtomatik qurur

```php
// app/Providers/AuthServiceProvider.php
public function boot(): void
{
    Passport::routes();   // /oauth/authorize, /oauth/token, etc.
}
```

Bu aşağıdakı route-ları açır:
- `GET /oauth/authorize` — authorization endpoint
- `POST /oauth/token` — token endpoint
- `POST /oauth/token/refresh` — refresh
- `DELETE /oauth/tokens/{id}` — revoke
- `GET /oauth/scopes` — mövcud scope siyahısı
- `GET /oauth/clients` — user-in clients-ı

### 5) Consent səhifəsi

Passport default consent view publish edilir:

```bash
php artisan vendor:publish --tag=passport-views
```

```blade
{{-- resources/views/vendor/passport/authorize.blade.php --}}
<div class="panel-body">
    <p><strong>{{ $client->name }}</strong> is requesting permission to:</p>

    @if (count($scopes) > 0)
        <ul>
            @foreach ($scopes as $scope)
                <li>{{ $scope->description }}</li>
            @endforeach
        </ul>
    @endif

    <form method="post" action="/oauth/authorize">
        @csrf
        <input type="hidden" name="state" value="{{ $request->state }}">
        <input type="hidden" name="client_id" value="{{ $client->id }}">
        <input type="hidden" name="auth_token" value="{{ $authToken }}">
        <button type="submit" class="btn btn-success">Authorize</button>
    </form>
    <form method="post" action="/oauth/authorize">
        @csrf
        @method('DELETE')
        <input type="hidden" name="state" value="{{ $request->state }}">
        <input type="hidden" name="client_id" value="{{ $client->id }}">
        <input type="hidden" name="auth_token" value="{{ $authToken }}">
        <button class="btn btn-danger">Cancel</button>
    </form>
</div>
```

### 6) Token customizer — əlavə claim-lər

Passport `league/oauth2-server` istifadə edir. Custom claim əlavə etmək üçün event listener və ya custom grant lazım ola bilər. Sadə yol — `AccessToken` model-ə əlavə:

```php
// app/Models/OauthToken.php
use Laravel\Passport\Token;

class OauthToken extends Token
{
    // JWT payload-ına əlavə etmək üçün Passport config lazımdır
}
```

Daha dəqiq yol — Passport event:

```php
// AppServiceProvider boot()
Event::listen(function (AccessTokenCreated $event) {
    Log::info('Access token issued', [
        'token_id' => $event->tokenId,
        'user_id' => $event->userId,
        'client_id' => $event->clientId,
    ]);
});
```

Praktikada Passport JWT-ə custom claim əlavə etmək üçün `league/oauth2-server`-in `ClaimSetEntityInterface`-ini implement edən custom `AccessTokenEntity` yaratmaq lazımdır — bu çox boilerplate-dır. Bunu tez-tez layihələr `tymon/jwt-auth` və ya öz issuer-i ilə həll edir.

### 7) Authorization_code + PKCE flow — Laravel + SPA

Server tərəfdə heç bir əlavə konfiq lazım deyil — public client yaradanda PKCE avtomatik aktivləşir:

```bash
php artisan passport:client --public \
    --name="My SPA" \
    --redirect_uri="https://app.example.com/callback"
```

SPA kodu eyni görünür (Spring-dəki kimi), yalnız URL-lər dəyişir:

```javascript
const authUrl = `https://api.example.com/oauth/authorize?` +
    new URLSearchParams({
        response_type: 'code',
        client_id: '9c1a...',
        redirect_uri: 'https://app.example.com/callback',
        scope: 'posts:read posts:write',
        state: state,
        code_challenge: codeChallenge,
        code_challenge_method: 'S256',
    });

// Token exchange
const tokenResponse = await fetch('https://api.example.com/oauth/token', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: new URLSearchParams({
        grant_type: 'authorization_code',
        client_id: '9c1a...',
        redirect_uri: 'https://app.example.com/callback',
        code,
        code_verifier: verifier,
    }),
});
```

### 8) Client credentials grant

```bash
php artisan passport:client --client
```

Middleware:

```php
// app/Http/Kernel.php
protected $middlewareAliases = [
    'client' => \Laravel\Passport\Http\Middleware\CheckClientCredentials::class,
];

// routes/api.php
Route::middleware(['client:reports:read'])->get('/reports', function () {
    return Report::all();
});
```

Client çağırışı:

```bash
curl -X POST https://api.example.com/oauth/token \
  -d "grant_type=client_credentials" \
  -d "client_id=xxx" \
  -d "client_secret=yyy" \
  -d "scope=reports:read"
```

### 9) Personal Access Token (Passport)

Developer-in öz token yaratması üçün:

```php
$token = $user->createToken('My API Token', ['posts:read', 'posts:write']);
return ['token' => $token->accessToken];
```

### 10) Passport-dan Sanctum-a miqrasiya

Laravel komandası bir çox sənaryoda Sanctum tövsiyə edir. Keçid strategiyası:

```php
// Mərhələ 1 — hər iki guard qoş
'guards' => [
    'api' => ['driver' => 'sanctum', 'provider' => 'users'],
    'api-v1' => ['driver' => 'passport', 'provider' => 'users'],
],

// Route-lar hər ikisini qəbul et
Route::middleware(['auth:sanctum,api-v1'])->get('/posts', ...);

// Mərhələ 2 — yeni login Sanctum token qaytarır
public function login(Request $request)
{
    $user = /* ... validate ... */;
    $token = $user->createToken('app', ['posts:read'])->plainTextToken;
    return ['token' => $token];
}

// Mərhələ 3 — Passport token-ləri expire olduqda Passport-u sil
// composer remove laravel/passport
// migrate: oauth_* cədvəlləri sil
```

Sanctum-un tam OAuth2 server kimi davranması üçün Laravel 11.x-də dəyişiklik yoxdur — o, sadə token verir. Əgər tam OAuth2 server saxlamaq istəyirsənsə, Passport qalır və ya Keycloak/Hydra kimi ayrıca servis istifadə olunur.

### 11) `league/oauth2-server` birbaşa

Ekstrem halda Passport-dan kənar öz OAuth2 server-ini qurmaq istəyirsənsə:

```php
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;

$server = new AuthorizationServer(
    $clientRepository,
    $accessTokenRepository,
    $scopeRepository,
    'file://' . storage_path('oauth-private.key'),
    'your-encryption-key'
);

$authCodeGrant = new AuthCodeGrant(
    $authCodeRepository,
    $refreshTokenRepository,
    new DateInterval('PT10M')
);
$authCodeGrant->setRefreshTokenTTL(new DateInterval('P1M'));

$server->enableGrantType($authCodeGrant, new DateInterval('PT1H'));
```

Bu yol yüksək nəzarət verir, amma Passport-un təklif etdiyi işlərin çoxunu əldən yazmaq lazım olur.

---

## Əsas fərqlər

| Xüsusiyyət | Spring Authorization Server | Laravel Passport |
|---|---|---|
| Başlanğıc versiyası | 1.0 (2022), rəsmi | v1 (2016), Laravel rəsmi paketi |
| Əsas kitabxana | Nimbus JOSE + custom | `league/oauth2-server` |
| Authorization code + PKCE | Var | Var |
| Client credentials | Var | Var |
| Refresh token | Var (reuse və ya rotate seçimi) | Var |
| Device code | Var | Yox (manual lazımdır) |
| Password grant | Yox (deprecated) | Var (deprecated) |
| OIDC (id_token, userinfo) | Full built-in | Qismən — `dusterio/lumen-passport` kimi ekstensiya |
| Discovery endpoint | `/.well-known/openid-configuration` daxili | Yox (manual əlavə) |
| JWK rotation | Custom `JWKSource` ilə asan | Manual RSA açar dəyişdirmək |
| Consent səhifəsi | Custom controller + Thymeleaf | Publish edilən blade view |
| Client storage | `JdbcRegisteredClientRepository` və ya in-memory | DB (`oauth_clients`) |
| Token customization | `OAuth2TokenCustomizer` bean — asan | `league/oauth2-server` entity override — çətin |
| Multi-tenant | `RegisteredClient` + custom issuer | Manual middleware |
| Personal Access Token | Birbaşa yox — Sanctum istifadə | Built-in (`createToken`) |
| Rəsmi status | Maintained | Maintained, amma Sanctum tövsiyə edilir |
| Cədvəl sayı | 4-5 (JDBC schema) | 5 (`oauth_*`) |

---

## Niyə belə fərqlər var?

**Spring enterprise IdP ehtiyacından doğdu.** Java ekosistemində Keycloak, Ping Identity, Forgerock kimi hazır həllər var, amma şirkətlər tez-tez öz Authorization Server-lərini yaratmaq istəyir (custom consent UI, custom grant types, corporate SSO inteqrasiyası). Spring Authorization Server bu boşluğu doldurur — tam OAuth2 Provider qurmaq üçün framework verir.

**Laravel Passport yeni başlayanları düşünür.** `league/oauth2-server` üzərinə nazik bir qat qoyub və `php artisan passport:install` ilə 2 dəqiqəyə işlək OAuth2 server verir. Amma bu "tam" OAuth2 server əksər mobil/SPA layihələrə lazım deyil — onlar üçün sadə Sanctum token kifayətdir. Bu səbəbdən Laravel komandası Sanctum-a yönləndirir.

**OIDC dəstəyi səviyyəsi.** Spring Authorization Server OIDC 1.0 core və discovery spec-lərinə tam riayət edir. Passport isə OIDC-ni sonradan əlavə etdi — `id_token`, `userinfo`, discovery üçün əlavə paketlər və konfiqurasiya lazım ola bilər. Əgər `id_token` lazımdırsa və tətbiqin SSO mərkəzi rolu varsa, Spring daha təmiz seçimdir.

**Token customization asanlığı.** Spring-də `OAuth2TokenCustomizer<JwtEncodingContext>` bean yazmaq kifayətdir — 10 sətir kod, hər token-ə custom claim. Passport-da JWT payload-ını dəyişmək üçün `league/oauth2-server` entity-lərini override etmək, custom grant class yazmaq lazım olur — yüksək effort.

**Cədvəl strukturu.** Passport cədvəlləri (`oauth_clients`, `oauth_access_tokens`, `oauth_auth_codes`, `oauth_refresh_tokens`, `oauth_personal_access_clients`) hazır gəlir. Spring-də JDBC schema ayrıca file-dır (`oauth2-authorization-schema.sql`, `oauth2-registered-client-schema.sql`) və əllə migrate etmək lazımdır. Hər ikisi customize oluna bilər, amma Laravel başlanğıcda daha sürətli qurulur.

**Passport-un gələcəyi.** Laravel 11.x-də Passport hələ də aktiv dəstəklənir, amma komandanın `php artisan install:api` default Sanctum seçir. Bu, cəmi "yeni layihə + OAuth2 server lazımdır" halında Passport-u seçdiyini göstərir. Real OAuth2 provider lazımdırsa, əksər komandalar Keycloak / Ory Hydra kimi ayrıca servis qoyur — çünki Laravel app-i həm business logic, həm IdP olmağa uyğun deyil.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring Authorization Server-də:**
- Device code grant (TV, CLI login flow) — built-in
- Rəsmi OIDC discovery endpoint (`/.well-known/openid-configuration`)
- `OAuth2TokenCustomizer` bean — 10 sətirdə claim əlavə
- `JWKSource<SecurityContext>` abstraction — rotation asan
- Federated identity (Spring-in öz OAuth2 client ilə SSO chain)
- Client settings: `requireProofKey`, `requireAuthorizationConsent` — deklarativ
- Reactive variant (`spring-security-oauth2-authorization-server` + WebFlux)
- Spring Security ilə eyni ekosistem — method security, test helper-lər
- Multi-issuer support (birdən çox tenant eyni server-də)

**Yalnız Laravel Passport-da:**
- Personal Access Token — developer üçün self-service token
- `php artisan passport:client --personal/--public/--client` — CLI
- `Passport::tokensCan([...])` — scope-ları kodda sadə şəkildə tərif
- Laravel auth guard-ı ilə tam inteqrasiya (`auth:api` middleware)
- Hazır consent view (publish olunan Blade)
- `league/oauth2-server`-ın bütün abstraction-ları (Entity, Repository interface-ləri)
- Implicit grant (köhnəlib, amma var) — Spring-də yoxdur
- `User::createToken()` — Personal Access Token API
- Laravel ekosistemi ilə (Horizon, Telescope, Nova) inteqrasiya

**Hər ikisində olan amma fərqli həyata keçirilən:**
- Authorization code + PKCE — hər ikisi
- Client credentials — hər ikisi
- Refresh token rotation — Spring-də deklarativ (`reuseRefreshTokens(false)`), Laravel-də default rotation
- JWK endpoint — hər ikisi
- Consent səhifəsi — hər ikisi customize edilə bilər

---

## Best Practices

1. **PKCE-ni public client-lərdə məcburi et.** Spring-də `ClientSettings.builder().requireProofKey(true)`. Laravel-də `--public` client PKCE-siz işləməz. Mobil + SPA-da client secret saxlamaq mümkün deyil — PKCE yeganə düzgün yoldur.

2. **Password grant-ı istifadə etmə.** OAuth2 spec-i deprecate edib. User password client app-də bilməməlidir. Authorization code + PKCE-yə keç.

3. **Access token qısa, refresh token uzun.** Spring-də `accessTokenTimeToLive(15m).refreshTokenTimeToLive(30d)`. Refresh token rotation aktiv et (`reuseRefreshTokens(false)`) — bir refresh bir dəfə işləsin.

4. **JWK rotation planla.** Açarlar 90-180 gündə bir fırladılmalıdır. Köhnə açar JWK set-də 2-3 dövr qalsın ki, hələ etibarlı token-lər yoxlana bilsin.

5. **HTTPS məcburi.** Authorization endpoint və token endpoint yalnız HTTPS üzərindən işləməlidir. Redirect URI-lər də HTTPS olmalıdır (localhost istisnadır).

6. **State parameter-ini yoxla.** CSRF qoruması üçün — authorization redirect-də state göndər, callback-də həmin state-i yoxla. Spring və Laravel client-lərdə bunu əllə implementasiya edirsən.

7. **Redirect URI exact match.** Wildcard redirect URI təhlükəlidir. `https://app.example.com/callback` yalnız bu dəqiq URI-ni qəbul etsin.

8. **Consent səhifəsini skip etmə (sensitive scope-larda).** `email`, `posts:write` kimi scope-lar ilk dəfə user-ə göstərilməlidir. Spring-də `requireAuthorizationConsent(true)`, Laravel-də default aktiv.

9. **Client secret hash-lə.** DB-də açıq saxlama. Spring-də `{bcrypt}` prefix-i ilə, Laravel Passport avtomatik hash edir.

10. **Monitoring + audit.** Authorization_code exchange, refresh, revoke — hər biri audit log-a yazılmalıdır. Spring-də `ApplicationListener<AbstractAuthenticationEvent>`, Laravel-də `AccessTokenCreated` event.

11. **Keycloak / Hydra seçimini düşün.** Əgər komanda kiçikdir və OAuth2 server qurmağa səy ayıra bilməzsə, Keycloak və ya Ory Hydra qoy — bu hazır məhsullardır və OIDC/OAuth2 spec-ə tam uyğundur.

---

## Yekun

Spring Authorization Server rəsmi, spec-ə tam uyğun, enterprise üçün qurulub OAuth2 Provider framework-dür. `RegisteredClient` builder, `OAuth2TokenCustomizer`, `JWKSource` abstraction, OIDC discovery, device code grant — hamısı built-in. Custom consent, multi-issuer, reactive variant — uzanır. Keycloak qədər "hazır məhsul" deyil, amma OAuth2 server-i öz kodunda saxlamaq istəyənlər üçün birinci seçimdir.

Laravel Passport `league/oauth2-server`-a nazik bir qat qoyur və 2 dəqiqəyə işlək OAuth2 server verir. Authorization code + PKCE, client credentials, personal access token — hazır. Amma OIDC dəstəyi qismən, device code yoxdur, token customization çətin. Production-da tam OAuth2 Provider kimi saxlamaq az görülür — daha çox "Laravel app + mobile client" sənaryosu üçün.

Praktiki tövsiyə: yeni Laravel layihələrdə **Sanctum** (80% hal), tam OAuth2 Provider lazımdırsa **Keycloak və ya Ory Hydra** (ayrıca servis kimi). Java mühitində əlavə IdP istəməyənlər üçün **Spring Authorization Server** mükəmməl vasitədir.
