# Laravel Auth (Middle)

## Mündəricat
1. [Authentication seçimləri](#authentication-seçimləri)
2. [Session-based auth](#session-based-auth)
3. [Sanctum — SPA & API token](#sanctum--spa--api-token)
4. [Passport — Full OAuth2](#passport--full-oauth2)
5. [JWT (firebase, tymon)](#jwt-firebase-tymon)
6. [Müqayisə](#müqayisə)
7. [Token rotation & revocation](#token-rotation--revocation)
8. [Mobile app auth](#mobile-app-auth)
9. [SSO (SAML, OIDC)](#sso-saml-oidc)
10. [Best practices](#best-practices)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Authentication seçimləri

```
Use case → Driver:

Web app (Blade, Inertia)     → Session (Laravel default) və ya Sanctum SPA
SPA (Vue/React same domain)   → Sanctum SPA mode
SPA (different domain)        → Sanctum API token
Mobile app                    → Sanctum API token / JWT
Third-party (OAuth provider)  → Passport (full OAuth2)
Microservice-to-microservice  → JWT və ya internal API token
SSO (enterprise)              → Socialite + SAML / OIDC
```

---

## Session-based auth

```php
<?php
// Laravel default — cookie-based session
// app login → server session yaradır → cookie göndərir
// Sonrakı request-lər cookie ilə gəlir

Route::post('/login', function (Request $request) {
    if (Auth::attempt($request->only('email', 'password'))) {
        $request->session()->regenerate();   // session fixation qarşı
        return redirect()->intended('/dashboard');
    }
    return back()->withErrors(['email' => 'Invalid']);
});

// Logout
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
});

// Middleware
Route::get('/dashboard', fn() => view('dashboard'))->middleware('auth');

// Session driver: file, cookie, database, redis, memcached
// Production: Redis (multi-server üçün shared)
```

```
Pros:
  ✓ Built-in, sıfır setup
  ✓ Server-side session (revoke asan)
  ✓ CSRF protection avtomatik
  ✓ Login form, password reset hazır

Cons:
  ✗ Cookie cross-domain işləmir (CORS əziyyətli)
  ✗ Stateful (session storage lazım)
  ✗ Mobile app üçün uyğun deyil
  ✗ Distributed session — sticky session və ya Redis lazım
```

---

## Sanctum — SPA & API token

```bash
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate
```

```
Sanctum 2 mode-da işləyir:

MODE 1 — SPA Authentication (cookie-based)
  Same-domain SPA üçün
  CSRF token yenilə → login → session cookie
  Sadəcə Laravel auth, amma SPA üçün CSRF flow

MODE 2 — API Token Authentication
  Mobile/3rd-party SPA üçün
  user->createToken() → plain text token
  Bearer token ilə request: Authorization: Bearer xxx
```

### Mode 1 — SPA

```php
<?php
// config/sanctum.php
'stateful' => [
    'localhost',
    'localhost:3000',
    '127.0.0.1',
    'app.example.com',
],

// kernel.php — Stateful API middleware
'api' => [
    \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
    'throttle:api',
    \Illuminate\Routing\Middleware\SubstituteBindings::class,
],

// Login flow:
// 1. Frontend: GET /sanctum/csrf-cookie (CSRF token alır)
// 2. Frontend: POST /login (email + password + X-XSRF-TOKEN)
// 3. Backend: session yaradır, cookie qaytarır
// 4. Frontend: GET /api/user (cookie ilə) → 200

Route::post('/login', function (Request $req) {
    if (! Auth::attempt($req->only('email', 'password'))) {
        return response()->json(['message' => 'Invalid'], 422);
    }
    $req->session()->regenerate();
    return response()->json(['user' => Auth::user()]);
});

Route::middleware('auth:sanctum')->get('/api/user', fn(Request $r) => $r->user());
```

### Mode 2 — API Token

```php
<?php
// User model
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
}

// Token yaratma
$user = User::find(1);
$token = $user->createToken('mobile-app', ['posts:read', 'posts:create']);
echo $token->plainTextToken;
// 1|abcdefghijk...   ← user-ə qaytar

// Database-də saxlanır (hash)
// personal_access_tokens table:
//   id, tokenable_id, tokenable_type, name, token (HASH), abilities, last_used_at, expires_at

// Authentication:
// Header: Authorization: Bearer 1|abcdefghijk...

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/api/posts', fn() => Post::all());
    
    // Ability check
    Route::post('/api/posts', function (Request $req) {
        if (! $req->user()->tokenCan('posts:create')) {
            abort(403);
        }
        // ...
    });
});

// Revoke
$user->tokens()->delete();              // bütün token-lar
$user->tokens()->where('id', $tokenId)->delete();
$user->currentAccessToken()->delete();  // current request-in token-ı

// Expiration (config/sanctum.php)
'expiration' => 525600,   // 1 il (dəqiqə)
// Yoxsa null = heç vaxt expire olmur
```

---

## Passport — Full OAuth2

```bash
composer require laravel/passport
php artisan migrate
php artisan passport:install
```

```
Passport — Laravel-in OAuth2 server implementasiyası.
"Mən OAuth2 PROVIDER olmaq istəyirəm" (kimi Google, Facebook).

OAuth2 grant types Passport-da:
  1. Authorization Code (3rd-party app, with PKCE)
  2. Personal Access Token (sadə API key)
  3. Password (DEPRECATED — istifadə etmə!)
  4. Client Credentials (server-to-server)
  5. Implicit (DEPRECATED)
  6. Refresh Token

Use case:
  ✓ "API marketplace" (3rd-party developers app yaratsın)
  ✓ "OAuth provider olmaq" (Login with MyApp button)
  ✓ Mobile + multiple frontends with refresh

NƏ VAXT istifadə ETMƏ:
  ✗ Sadə SPA və ya mobile app — Sanctum kifayətdir
  ✗ Microservice internal — JWT bəs edər
  ✗ Setup overhead, complexity (10+ table)
```

```php
<?php
// User model
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
}

// AuthServiceProvider
public function boot(): void
{
    Passport::tokensCan([
        'place-orders' => 'Place new orders',
        'check-status' => 'Check order status',
    ]);
    
    Passport::tokensExpireIn(now()->addDays(15));
    Passport::refreshTokensExpireIn(now()->addDays(30));
    Passport::personalAccessTokensExpireIn(now()->addMonths(6));
}

// API auth
Route::middleware('auth:api')->group(function () {
    Route::get('/user', fn(Request $r) => $r->user());
});

// Endpoint-lər avtomatik:
// POST /oauth/token              — token alış
// POST /oauth/authorize          — code grant
// GET  /oauth/clients            — client list
// POST /oauth/personal-access-tokens
```

---

## JWT (firebase, tymon)

```bash
composer require firebase/php-jwt
# Laravel üçün:
composer require tymon/jwt-auth
```

```php
<?php
// JWT token strukturu
// header.payload.signature
// eyJ...header.eyJ...payload.signature

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$key = config('app.jwt_secret');
$payload = [
    'sub' => $userId,
    'iat' => time(),
    'exp' => time() + 3600,
    'aud' => 'mobile-app',
    'roles' => ['user', 'editor'],
];

$jwt = JWT::encode($payload, $key, 'HS256');
// Send to client: Authorization: Bearer $jwt

// Validate (server side)
try {
    $decoded = JWT::decode($jwt, new Key($key, 'HS256'));
    $userId = $decoded->sub;
} catch (\Firebase\JWT\ExpiredException $e) {
    // Token expired
}
```

```
JWT pros:
  ✓ Stateless (DB lookup yox)
  ✓ Self-contained (claims daxilində)
  ✓ Cross-domain işləyir
  ✓ Microservice-friendly

JWT cons:
  ✗ Revoke ÇƏTİN (stateless)
  ✗ Token böyüyür (claims artdıqca)
  ✗ Secret leak → bütün token-lar compromised
  ✗ "stale data" problemi (role dəyişdi, token köhnə)

Həll patterns:
  - Short expiry (5-15 dəqiqə) + refresh token
  - JWT denylist Redis-də (revoked token list)
  - Asymmetric keys (RS256) — public/private
```

---

## Müqayisə

| Feature | Session | Sanctum SPA | Sanctum Token | Passport | JWT |
|---------|---------|-------------|---------------|----------|-----|
| Storage | Server | Server | DB (hash) | DB (hash) | Stateless |
| Mobile-friendly | ✗ | ✗ | ✓ | ✓ | ✓ |
| Cross-domain | ✗ | △ | ✓ | ✓ | ✓ |
| Revoke | ✓ instant | ✓ instant | ✓ instant | ✓ instant | △ (denylist) |
| Setup | trivial | easy | easy | complex | medium |
| OAuth2 provider | ✗ | ✗ | ✗ | ✓ | ✗ |
| Refresh token | ✗ | ✗ | manual | ✓ built-in | manual |
| Best for | Blade SPA | Same-origin SPA | Mobile/API | OAuth provider | Microservices |

---

## Token rotation & revocation

```php
<?php
// PROBLEM: Long-lived token leak → unlimited access

// SOLUTION 1: Short access + refresh token
// Access token:  15 dəqiqə (qısa, leak risk az)
// Refresh token: 30 gün (uzun, amma yalnız refresh üçün)

class TokenService
{
    public function login(User $user): array
    {
        $access = $user->createToken('access', ['*'], now()->addMinutes(15));
        $refresh = $user->createToken('refresh', ['refresh-token'], now()->addDays(30));
        
        return [
            'access_token'  => $access->plainTextToken,
            'refresh_token' => $refresh->plainTextToken,
            'expires_in'    => 900,
        ];
    }
    
    public function refresh(User $user): array
    {
        // Köhnə refresh token-ı sil (rotation)
        $user->currentAccessToken()->delete();
        
        // Yeni cüt yarat
        return $this->login($user);
    }
}

// SOLUTION 2: Refresh token rotation
// Hər refresh-də yeni refresh token verilir, köhnəsi invalidate olunur
// Refresh token-ın "reuse detection" — leak olunsa hər ikisi blok

// SOLUTION 3: JWT denylist
class JwtDenylistMiddleware
{
    public function handle($request, $next)
    {
        $jti = $request->bearerToken()->jti();   // JWT ID claim
        if (Cache::has("denylist:$jti")) {
            return response()->json(['error' => 'Revoked'], 401);
        }
        return $next($request);
    }
}

// Logout — denylist-ə əlavə
$jti = auth()->payload()->get('jti');
$ttl = auth()->payload()->get('exp') - time();
Cache::put("denylist:$jti", true, $ttl);
```

---

## Mobile app auth

```
Mobile app pattern:
  1. Login → access (15min) + refresh (30 day) token
  2. Access token mobil keychain-də saxla
  3. API request — Bearer access token
  4. 401 alanda → refresh token ilə yeni access al
  5. Refresh fail (expired) → login ekran

Storage:
  iOS:     Keychain
  Android: EncryptedSharedPreferences, Keystore
  ✗ Plain text "shared preferences" — root device-də oxuna bilər

Biometric auth:
  Local Touch ID / Face ID + sonra refresh token API-yə göndər
  → user-i hər dəfə şifrə yazmağa məcbur etmə
  ✓ UX yaxşı, ✓ refresh token leak risk az (cihaz unlock olmadan istifadə olunmur)
```

---

## SSO (SAML, OIDC)

```bash
composer require laravel/socialite
# OIDC providers (Google, Microsoft, Okta, Auth0)

# SAML üçün:
composer require slo-tech/laravel-saml-sp
# ya da onelogin/php-saml
```

```php
<?php
// Socialite — Google OIDC
Route::get('/auth/google', fn() => Socialite::driver('google')->redirect());

Route::get('/auth/google/callback', function () {
    $googleUser = Socialite::driver('google')->user();
    
    $user = User::firstOrCreate(
        ['email' => $googleUser->email],
        ['name'  => $googleUser->name, 'google_id' => $googleUser->id]
    );
    
    Auth::login($user);
    return redirect('/dashboard');
});

// SAML — daha kompleks
// IdP (Identity Provider) — Okta, Azure AD
// SP (Service Provider) — siz
// Browser POST flow ilə assertion göndərilir
```

---

## Best practices

```
✓ HTTPS məcburi (token plain text gedir!)
✓ Short-lived access token (5-15 min)
✓ Refresh token rotation
✓ Token storage secure (Keychain, EncryptedSharedPreferences)
✓ Rate limit /login (brute force qarşı)
✓ 2FA (TOTP, WebAuthn) sensitive endpoint-lər üçün
✓ Audit log (login, logout, token created/revoked)
✓ Password hash bcrypt/argon2 (Laravel default)
✓ "Sign out all devices" feature ($user->tokens()->delete())
✓ Session fixation: regenerate() login-dən sonra
✓ CSRF protection cookie-based auth-da

❌ JWT-ı session token kimi istifadə (revoke imkansız)
❌ "Remember me" cookie permanent (rotation lazım)
❌ Plain text password DB-də
❌ MD5/SHA1 hash (zəif)
❌ JWT secret hardcoded code-da
❌ Token URL-də (server log, browser history)
❌ Error message-də "user exists" / "wrong password" fərqi (enumeration)
```

---

## İntervyu Sualları

- Sanctum SPA və Sanctum Token mode arasında fərq?
- Passport nə vaxt seçilir, niyə Sanctum kifayət deyil?
- JWT stateless niyə həm üstünlük, həm dezavantajdır?
- Refresh token rotation niyə təhlükəsizliyi artırır?
- Token revocation JWT-də necə həll olunur?
- HMAC (HS256) və RSA (RS256) JWT signing fərqi?
- Mobile app-də access token harada saxlanır?
- "Sign out all devices" Laravel-də necə implementasiya olunur?
- OAuth2 Authorization Code grant vs Password grant?
- PKCE (Proof Key for Code Exchange) nədir?
- 2FA (TOTP) Laravel-də necə inteqrasiya olunur?
- Session fixation attack — `regenerate()` niyə kömək edir?
