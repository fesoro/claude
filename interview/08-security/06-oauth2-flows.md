# OAuth 2.0 Flows (Senior ⭐⭐⭐)

## İcmal

OAuth 2.0 — üçüncü tərəf tətbiqlərə istifadəçi adından məhdud giriş icazəsi vermək üçün RFC 6749 standart protokoludur. "Google ilə giriş", "GitHub API" inteqrasiyası, microservices M2M communication — hamısı OAuth 2.0 ilə işləyir. Interview-da bu mövzu authorization flow-larını, hər birinin nə zaman istifadə olunduğunu, security aspektlərini, PKCE-nin niyə lazım olduğunu bilməyi ölçür.

## Niyə Vacibdir

OAuth 2.0 müasir veb tətbiqlərin fundamentidir. Third-party inteqrasiyalar, SSO (Single Sign-On), mobile app authentication, microservices M2M communication — hamısı OAuth 2.0 ilə işləyir. Bu protokolu dərindən bilmək production-da yanlış flow seçiminin security problemlərini önləyir. Implicit Flow hələ istifadə edən sistemlər var — bunun niyə deprecated olduğunu bilmək təhlükəsizlik savadını göstərir.

## Əsas Anlayışlar

- **Resource Owner**: İcazə verən şəxs — Google hesabı olan user
- **Client**: İcazə istəyən tətbiq — `myapp.com`
- **Authorization Server**: İcazə verən server — Google, GitHub, Keycloak, Auth0. Token-ları issue edir
- **Resource Server**: Qorunan resurslara malik server — GitHub API, Google Drive API
- **Authorization Code Flow (RFC 6749 §4.1)**: Ən güvənli flow. Web app + backend üçün. Token server-side alınır, client secret expose olunmur. State parameter CSRF qoruması üçün
- **Authorization Code + PKCE (RFC 7636)**: SPA + mobile üçün. Client secret yoxdur, PKCE bunu əvəz edir. code_verifier (random 43-128 char) → SHA256 hash → code_challenge. Token alındıqda code_verifier göndərilir — uyğunsa token verilir. Authorization code intercept olunsa faydasızdır — code_verifier olmadan token alınmaz
- **Client Credentials Flow (RFC 6749 §4.4)**: Server-to-server (M2M). İstifadəçi yoxdur. Service öz `client_id` + `client_secret` ilə token alır. Microservices arası trusted communication
- **Device Authorization Flow (RFC 8628)**: TV, CLI, IoT — browser yoxdur. Server device code + user code qaytarır. User başqa cihazdan user code-u daxil edir. CLI polling edir. `gh auth login` bu flow-dur
- **Implicit Flow (DEPRECATED, RFC 6749 §4.2)**: access token URL fragment-də qaytarılırdı → browser history, referrer header-da görünürdü, security risk. 2019-dan bəri RFC 8252 tövsiyə etmir. PKCE ilə əvəzləndi
- **Resource Owner Password Flow (DEPRECATED)**: Tətbiq istifadəçinin şifrəsini görür — 3rd party trust lazımdır. Legacy, migration üçün. Tövsiyə edilmir
- **Access Token**: Bearer token, qısa ömürlü (15 dəq - 1 saat). `Authorization: Bearer access_token`. Resource server-ə göndərilir
- **Refresh Token**: Uzun ömürlü (gün-ay). Yeni access token almaq üçün. HTTP-only cookie-də saxlanılmalıdır. Bir dəfə istifadə (rotation ilə)
- **Scope**: İcazənin həcmi — `read:profile write:orders email`. Principle of least privilege. User consent screen-də göstərilir. `offline_access` → refresh token verilsin
- **State parameter**: CSRF attack-a qarşı. Client random string generate edir, authorize redirect-ə əlavə edir, callback-də verify edir
- **PKCE code_challenge_method**: `S256` (SHA256) ya da `plain`. `S256` mütləq istifadə edilməlidir — `plain` interception-a açıqdır
- **OpenID Connect (OIDC)**: OAuth 2.0 üzərindən authentication layer. ID token (JWT) + `/userinfo` endpoint. `openid` scope → ID token verilir. Who you are (OIDC) vs what you can do (OAuth 2.0)
- **Discovery endpoint**: `/.well-known/openid-configuration` — authorization endpoint, token endpoint, JWKS URI, supported scopes, supported response types
- **Token introspection (RFC 7662)**: Opaque token-ı validate etmək — Authorization Server-ə `POST /introspect` ilə. JWT verification-dan fərqli olaraq network call lazımdır

## Praktik Baxış

**Interview-da yanaşma:**
OAuth 2.0 flow-larını nümunə ilə izah edin: "Web app üçün Authorization Code + PKCE, backend service-ləri arası üçün Client Credentials." Deprecated flow-ları bilmək (niyə deprecated olduğu ilə) sizi seçdirir. OAuth 2.0 vs OIDC fərqini aydın çəkmək.

**Follow-up suallar (top companies-da soruşulur):**
- "SPA üçün hansı flow istifadə edərdiniz?" → Authorization Code + PKCE. Client Secret yoxdur (public client), PKCE bunu compensate edir. Implicit Flow deprecated
- "Client Credentials Flow nə üçün?" → Server-to-server (M2M). User yoxdur. Microservice-A, microservice-B-dən data almaq üçün service-A token alır. `grant_type=client_credentials`
- "PKCE nə problemi həll edir?" → Public client-lərdə (SPA, mobile) client secret saxlanmır — extract edilə bilər. PKCE: code_verifier generate et, SHA256 hash-i code_challenge kimi göndər. Authorization code intercepted olsa belə code_verifier olmadan token alınmır
- "OAuth 2.0 vs OIDC fərqi nədir?" → OAuth 2.0: authorization (nəyə icazə var, "what"). OIDC: authentication (kim olduğu, "who") — OAuth 2.0 üzərindən ID token (JWT) + `sub` claim. "Google ilə giriş" = OIDC + OAuth 2.0
- "State parameter-ı validate etməsəniz nə olar?" → CSRF attack: attacker öz authorization code-unu victim-in callback-inə inject edə bilər. Victim attacker-ın hesabına bağlanır
- "Microservices-də OAuth token scope-ları necə dizayn edilir?" → Fine-grained: `inventory:read`, `payment:write`, `notification:send`. Service-ə yalnız lazım olan scope-ları ver. Principle of least privilege

**Ümumi səhvlər (candidate-ların etdiyi):**
- Implicit Flow istifadə etmək — deprecated, PKCE ilə əvəzlə
- Access token-ı URL-də göndərmək (`?token=...`) — browser history, server log-larda saxlanır
- Client Secret-i mobil tətbiqə yerləşdirmək — decompile edilə bilər, PKCE istifadə et
- OAuth 2.0 = Authentication düşünmək — OAuth Authorization-dır, OIDC Authentication-dır
- `state` parametrini validate etməmək — CSRF riski

**Yaxşı cavabı əla cavabdan fərqləndirən:**
OAuth 2.0 vs OIDC fərqini aydın izah etmək, PKCE-nin code interception attack-ını niyə önlədiyini, Client Credentials-ın token caching-inin niyə vacib olduğunu, scope-ların minimum principle ilə dizaynını bilmək.

## Nümunələr

### Tipik Interview Sualı

"OAuth 2.0 Authorization Code Flow-nu izah edin. SPA üçün niyə PKCE lazımdır?"

### Güclü Cavab

"Authorization Code Flow: 1) Client user-i auth server-ə yönləndirir (`/authorize?response_type=code&state=random`). 2) User login, consent. 3) Auth server callback URL-ə qısamüddətli code qaytarır. 4) Backend bu code-u `/token`-da access token ilə dəyişir — client secret burada istifadə olunur, URL-də code görünmür. 5) Access + refresh token alınır.

SPA-da client secret saxlanmır (public client). PKCE: SPA random `code_verifier` yaradır, SHA256 hash-i `code_challenge` kimi göndərir. Token istəyəndə `code_verifier` göndərilir — server SHA256 ilə verify edir. Code intercepted olsa belə `code_verifier` olmadan token alınmır. Implicit Flow-da token URL-də görünürdü — browser history-də saxlanırdı, bu yüzdan deprecated."

### Kod/Konfiqurasiya Nümunəsi

```php
// ============================================================
// Authorization Code Flow — Laravel Socialite (Google OIDC)
// ============================================================

// config/services.php
'google' => [
    'client_id'     => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),  // Server-side saxlanır
    'redirect'      => env('GOOGLE_REDIRECT_URI'),    // https://app.com/auth/google/callback
],

class OAuthController extends Controller
{
    // Step 1: User-i Google-a redirect et
    public function redirect(): RedirectResponse
    {
        // State generate — CSRF qorunması
        $state = Str::random(40);
        session(['oauth_state' => $state]);

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])  // Minimum scope — privacy
            ->with(['state' => $state])
            ->redirect();

        // Redirect URL:
        // https://accounts.google.com/o/oauth2/auth
        //   ?client_id=xxx
        //   &redirect_uri=https://app.com/auth/google/callback
        //   &response_type=code
        //   &scope=openid+profile+email
        //   &state=random_csrf_token
    }

    // Step 2: Google callback — code ile token exchange (server-side)
    public function callback(Request $request): RedirectResponse
    {
        // State validation — CSRF qorunması
        if ($request->state !== session('oauth_state')) {
            abort(403, 'Invalid state — possible CSRF attack');
        }

        if ($request->has('error')) {
            return redirect('/login')->with('error', 'OAuth authorization denied: ' . $request->error);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
            // Socialite: code → POST /token ilə server-side token exchange
            // Client Secret URL-də heç görünmür
        } catch (\Exception $e) {
            Log::warning('OAuth callback failed', ['error' => $e->getMessage()]);
            return redirect('/login')->with('error', 'Authentication failed');
        }

        // ID token-dən identity (OIDC)
        $user = User::updateOrCreate(
            ['google_id' => $googleUser->getId()],
            [
                'name'             => $googleUser->getName(),
                'email'            => $googleUser->getEmail(),
                'avatar'           => $googleUser->getAvatar(),
                'email_verified_at' => now(), // Google email-i verify etmişdir
            ]
        );

        Auth::login($user, remember: true);

        Log::channel('audit')->info('user.oauth_login', [
            'user_id'  => $user->id,
            'provider' => 'google',
            'ip'       => $request->ip(),
        ]);

        return redirect()->intended('/dashboard');
    }
}
```

```php
// ============================================================
// Client Credentials Flow — Microservice-to-Microservice
// ============================================================

class ServiceTokenProvider
{
    private const CACHE_TTL_BUFFER = 60; // Expire-dən 60s əvvəl yenilə

    public function getToken(): string
    {
        $cacheKey = 'service_token_' . config('services.auth.client_id');

        return Cache::remember($cacheKey, function () {
            return $this->fetchNewToken();
        }, fn($token) => $this->getRemainingTtl($token));
    }

    private function fetchNewToken(): array
    {
        $response = Http::asForm()
            ->timeout(10)
            ->retry(3, 1000) // 3 cəhd, 1s aralarında
            ->post(config('services.auth.token_url'), [
                'grant_type'    => 'client_credentials',
                'client_id'     => config('services.auth.client_id'),
                'client_secret' => config('services.auth.client_secret'),
                'scope'         => 'inventory:read inventory:write payment:read',
            ]);

        if (!$response->successful()) {
            throw new ServiceTokenException('Failed to obtain service token');
        }

        return $response->json();
    }

    private function getRemainingTtl(array $tokenData): int
    {
        return max(1, ($tokenData['expires_in'] ?? 3600) - self::CACHE_TTL_BUFFER);
    }
}

// İstifadəsi
class InventoryClient
{
    public function __construct(private ServiceTokenProvider $tokens) {}

    public function getStock(string $productId): int
    {
        $response = Http::withToken($this->tokens->getToken())
            ->timeout(5)
            ->get(config('services.inventory.url') . "/products/{$productId}/stock");

        return $response->json('quantity', 0);
    }
}
```

```php
// ============================================================
// PKCE Flow — Frontend JavaScript (SPA)
// ============================================================
// Bu PHP deyil — frontend PKCE implementation
// Göstərmək üçün pseudo-code (JavaScript kimi)

/*
// 1. code_verifier generate et (43-128 char, URL-safe)
const codeVerifier = base64urlEncode(crypto.getRandomValues(new Uint8Array(32)));

// 2. code_challenge = SHA256(code_verifier)
const codeChallenge = base64urlEncode(
    await crypto.subtle.digest('SHA-256', new TextEncoder().encode(codeVerifier))
);

// 3. code_verifier-i sessionStorage-da saxla
sessionStorage.setItem('pkce_verifier', codeVerifier);

// 4. Authorize redirect
window.location.href = `https://auth.example.com/authorize?
    response_type=code&
    client_id=spa-client&
    redirect_uri=https://app.com/callback&
    code_challenge=${codeChallenge}&
    code_challenge_method=S256&
    state=${generateRandomState()}&
    scope=openid profile email`;

// 5. Callback-də token exchange
const params = new URLSearchParams(window.location.search);
const code = params.get('code');
const verifier = sessionStorage.getItem('pkce_verifier');

const tokenResponse = await fetch('https://auth.example.com/token', {
    method: 'POST',
    body: new URLSearchParams({
        grant_type: 'authorization_code',
        code: code,
        code_verifier: verifier,  // Server SHA256 ilə verify edir
        redirect_uri: 'https://app.com/callback',
        client_id: 'spa-client',  // Client secret YOX
    }),
});
// code intercepted olsa belə code_verifier olmadan token alınmır
*/
```

```php
// ============================================================
// Laravel Passport — OAuth 2.0 Server
// ============================================================

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Passport::routes();

        Passport::tokensExpireIn(now()->addMinutes(15));
        Passport::refreshTokensExpireIn(now()->addDays(7));

        // Scopes müəyyən et
        Passport::tokensCan([
            'orders:read'    => 'View your orders',
            'orders:write'   => 'Create and update orders',
            'profile:read'   => 'View your profile',
            'profile:write'  => 'Update your profile',
            'admin:*'        => 'Full admin access',
        ]);
    }
}

// Route qoruma — scope-a görə
Route::middleware(['auth:api', 'scope:orders:read'])->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
});

Route::middleware(['auth:api', 'scope:orders:write'])->group(function () {
    Route::post('/orders', [OrderController::class, 'store']);
    Route::put('/orders/{id}', [OrderController::class, 'update']);
});
```

```
OAuth 2.0 Flow Seçim Matrix:

Scenario                              Flow
──────────────────────────────────────────────────────
Web app (server-side, backend code)   Authorization Code
SPA (React, Vue, Angular)             Authorization Code + PKCE
Mobile app (iOS, Android)             Authorization Code + PKCE
Microservice → Microservice (M2M)     Client Credentials
CLI tool                              Device Authorization Flow
TV / Smart TV / IoT                   Device Authorization Flow
Legacy system migration               Resource Owner Password (geçici, migrate et)
Third-party "Login with X"            Authorization Code (+ OIDC ID token)

Token type seçimi:
  JWT access token: Stateless, service-lər öz başına verify edir
  Opaque access token: Authorization server-ə introspect lazımdır
  → JWT: microservices, performance
  → Opaque: immediate revocation vacibdirsə
```

## Praktik Tapşırıqlar

1. Laravel Socialite ilə Google OIDC implement edin: state validation, email verification, user creation
2. Client Credentials token-ı cache etmək üçün `ServiceTokenProvider` yazın — token expire olmadan yenilə
3. Postman-da Authorization Code Flow-nu manual addım-addım keçin: redirect URL-ni explore edin, code-u token ilə dəyişin
4. PKCE code_verifier → code_challenge transformasiyasını kodda göstərin (PHP-də `hash('sha256', ...)` + `base64_url_encode`)
5. Laravel Passport-da scope-larla route qoruyun: `scope:orders:write` middleware ilə test edin
6. Deprecated Implicit Flow-un niyə güvənsiz olduğunu praktikada simulate edin: access token URL fragment-ında nə problemdir?
7. Device Flow-u oxuyun: GitHub CLI (`gh auth login`) necə işləyir?

## Əlaqəli Mövzular

- `05-jwt-deep-dive.md` — OAuth access token olaraq JWT, RS256 signing
- `04-authentication-authorization.md` — OAuth 2.0 authorization konteksti
- `08-secrets-management.md` — Client secret idarəsi, rotation
- `11-least-privilege.md` — Scope = minimum permission principle
