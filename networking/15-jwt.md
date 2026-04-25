# JWT - JSON Web Token (Junior)

## İcmal

JWT (RFC 7519) iki tərəf arasında məlumat ötürən compact, URL-safe token formatıdır. Authentication və information exchange üçün istifadə olunur. Token 3 hissədən ibarətdir: Header, Payload, Signature. Server session saxlamadan istifadəçini tanıya bilər (stateless authentication).

```
Sessiya-based auth:
  Client --> Cookie: session_id=abc123 --> Server --> Session Store-dan user tap
  (Server state saxlayır - scaling çətin)

JWT-based auth:
  Client --> Authorization: Bearer eyJhbG... --> Server --> Token-i verify et
  (Server state saxlamır - asan scale olunur)
```

## Niyə Vacibdir

Microservices arxitekturasında hər servis ayrı-ayrılıqda token-i verify edə bilir — mərkəzi session store-a ehtiyac yoxdur. Mobile tətbiqlər, SPA-lar, API-lar üçün stateless autentifikasiya standartdır. Payload-da istənilən məlumat (role, permissions) daşına bilər ki, əlavə DB sorğusu olmadan authorization qərarı verilsin.

## Əsas Anlayışlar

### JWT Structure

```
eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6Ik9ya2hhbiIsImlhdCI6MTUxNjIzOTAyMn0.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c
|___________________________________|.|______________________________________________|.|_______________________________________________|
         HEADER (base64url)                        PAYLOAD (base64url)                          SIGNATURE

HEADER:
{
  "alg": "HS256",     // Signing algorithm
  "typ": "JWT"        // Token type
}

PAYLOAD (Claims):
{
  "sub": "1234567890",  // Subject (user ID)
  "name": "Orkhan",     // Custom claim
  "email": "orkhan@example.com",
  "role": "admin",
  "iat": 1516239022,    // Issued At
  "exp": 1516242622,    // Expiration Time
  "iss": "myapp.com",   // Issuer
  "aud": "api.myapp.com" // Audience
}

SIGNATURE:
HMACSHA256(
  base64UrlEncode(header) + "." + base64UrlEncode(payload),
  secret_key
)
```

### JWT Authentication Flow

```
Client                          Server
  |                               |
  |-- POST /login -------------->|
  |   {email, password}          |
  |                               |  Credentials yoxla
  |                               |  JWT yarat və imzala
  |<-- {access_token, -----------|
  |     refresh_token}           |
  |                               |
  |-- GET /api/users ----------->|
  |   Authorization:              |
  |   Bearer eyJhbG...           |
  |                               |  Signature verify et
  |                               |  Expiration yoxla
  |                               |  Claims-dən user tap
  |<-- 200 OK {users...} --------|
  |                               |
  |-- GET /api/users ----------->|
  |   Bearer (expired token)     |
  |<-- 401 Token Expired --------|
  |                               |
  |-- POST /refresh-token ------>|
  |   {refresh_token}            |
  |<-- {new access_token, -------|
  |     new refresh_token}       |
```

### Signing Algorithms

```
Symmetric (shared secret):
  HS256 - HMAC with SHA-256
  HS384 - HMAC with SHA-384
  HS512 - HMAC with SHA-512
  (eyni key sign və verify üçün - microservices üçün uyğun deyil)

Asymmetric (public/private key pair):
  RS256 - RSA with SHA-256
  RS384 - RSA with SHA-384
  RS512 - RSA with SHA-512
  ES256 - ECDSA with SHA-256
  (Private key sign edir, public key verify edir - microservices üçün ideal)

  Auth Server: private key ilə sign edir
  API Server 1: public key ilə verify edir
  API Server 2: public key ilə verify edir
  (API server-lər token yarada bilmir, yalnız yoxlaya bilir)
```

### Registered Claims (Standard)

```
iss (Issuer)      - Token-i kim yaratdı
sub (Subject)     - Token kimin üçündür (user ID)
aud (Audience)    - Token kimin üçün nəzərdə tutulub
exp (Expiration)  - Token nə vaxt bitir (Unix timestamp)
nbf (Not Before)  - Token nə vaxtdan keçərlidir
iat (Issued At)   - Token nə vaxt yaradıldı
jti (JWT ID)      - Token-in unique ID-si (replay protection)
```

### Token Refresh Strategy

```
Access Token:  Qısa ömürlü (15 dəq)
Refresh Token: Uzun ömürlü (7 gün), DB-də saxlanır

Login:
  -> access_token (15 dəq) + refresh_token (7 gün)

API Request:
  -> access_token ilə
  -> 401 alındı -> refresh_token ilə yeni access_token al

Refresh:
  -> Köhnə refresh_token göndərin
  -> Yeni access_token + yeni refresh_token alın
  -> Köhnə refresh_token artıq keçərsizdir (rotation)
```

### Token Blacklisting / Revocation

```
Problem: JWT stateless-dir, logout olanda necə invalidate edirik?

1. Token Blacklist (Redis/DB):
   Logout -> token-in jti-sini blacklist-ə əlavə et
   Hər request -> blacklist-də var mı yoxla
   TTL = token-in qalan ömrü

2. Short-lived tokens + Refresh token revocation:
   Access token 15 dəq yaşar - logout-dan sonra max 15 dəq işləyir
   Refresh token DB-dən silir - yeni access token ala bilməz

3. Token version (user-based):
   User model-də token_version field
   JWT-də version claim əlavə et
   Logout -> token_version++ -> bütün köhnə token-lər invalid
```

## Praktik Baxış

**Nə vaxt JWT istifadə etmək lazımdır:**
- Stateless API autentifikasiyası (mobile, SPA, third-party)
- Microservices arası token paylaşımı (RS256 ilə)
- Short-lived authorization token-ləri

**Nə vaxt session-based auth seçmək lazımdır:**
- Ənənəvi web tətbiqləri (server-side rendering)
- Token revocation tez-tez lazım olanda
- Payload boyutu bandwidth üçün problem yaradanda

**Trade-off-lar:**
- Token ölçüsü böyükdür — hər request-ə əlavə olunur
- Revocation çətindir — stateless olduğundan token expire olana qədər işləyir
- Payload dəyişə bilməz — role dəyişsə yeni token lazımdır

**Anti-pattern-lər:**
- Sensitive data (şifrə, kredit kartı) payload-a yazmaq — base64 encoded-dir, encrypted deyil
- Token-i localStorage-də saxlamaq — XSS-ə həssasdır
- `exp` claim-siz token yaratmaq — token heç vaxt bitmir
- `alg: "none"` attack-dən qorunmamaq — algorithm-i explicit specify edin
- Microservices-də HS256 istifadə etmək — bütün servislər secret key-i bilir

## Nümunələr

### Ümumi Nümunə

JWT token anatomy — decoded payload:

```
Header:  { "alg": "RS256", "typ": "JWT" }
Payload: { "sub": "42", "role": "admin", "iat": 1714000000, "exp": 1714000900 }
Sig:     RSA(private_key, header.payload)

Server verify addımları:
1. Signature-ı public key ilə yoxla
2. exp-ı cari vaxtla müqayisə et
3. iss, aud claim-lərini yoxla
4. jti-ni blacklist-dən yoxla (əgər blacklist varsa)
```

### Kod Nümunəsi

**Laravel Sanctum (Recommended for SPA/Mobile):**

```php
// Sanctum - Laravel-in öz token sistemi (JWT deyil, amma oxşar istifadə)
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate

// app/Models/User.php
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
}
```

```php
// Login Controller
namespace App\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Credentials are incorrect.'],
            ]);
        }

        // Token yaratmaq (abilities = scopes)
        $token = $user->createToken(
            $request->device_name,
            ['read', 'write']  // abilities
        );

        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Current token-i sil
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function logoutAll(Request $request): JsonResponse
    {
        // Bütün token-ləri sil (bütün cihazlardan çıx)
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out from all devices']);
    }
}
```

**JWT Package (tymon/jwt-auth):**

```bash
composer require tymon/jwt-auth
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
php artisan jwt:secret
```

```php
// app/Models/User.php
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'role' => $this->role,
            'permissions' => $this->permissions->pluck('name'),
        ];
    }
}

// config/auth.php
'guards' => [
    'api' => [
        'driver' => 'jwt',
        'provider' => 'users',
    ],
],
```

```php
// JWT Auth Controller
namespace App\Http\Controllers\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class JwtAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!$token = auth('api')->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    public function me(): JsonResponse
    {
        return response()->json(auth('api')->user());
    }

    public function refresh(): JsonResponse
    {
        $token = auth('api')->refresh();
        return $this->respondWithToken($token);
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();  // Token blacklist olunur
        return response()->json(['message' => 'Logged out']);
    }

    private function respondWithToken(string $token): JsonResponse
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ]);
    }
}
```

**Token Blacklisting with Redis:**

```php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class TokenBlacklist
{
    /**
     * Token-i blacklist-ə əlavə et
     */
    public function add(string $jti, int $expiresIn): void
    {
        Redis::setex("blacklist:jwt:{$jti}", $expiresIn, '1');
    }

    /**
     * Token blacklist-də var mı?
     */
    public function isBlacklisted(string $jti): bool
    {
        return (bool) Redis::exists("blacklist:jwt:{$jti}");
    }
}

// Middleware
namespace App\Http\Middleware;

use App\Services\TokenBlacklist;
use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class CheckTokenBlacklist
{
    public function __construct(private TokenBlacklist $blacklist) {}

    public function handle(Request $request, Closure $next)
    {
        $payload = JWTAuth::parseToken()->getPayload();
        $jti = $payload->get('jti');

        if ($this->blacklist->isBlacklisted($jti)) {
            return response()->json(['error' => 'Token has been revoked'], 401);
        }

        return $next($request);
    }
}
```

**Routes:**

```php
// routes/api.php
Route::prefix('auth')->group(function () {
    Route::post('login', [JwtAuthController::class, 'login']);
    Route::post('register', [JwtAuthController::class, 'register']);

    Route::middleware('auth:api')->group(function () {
        Route::get('me', [JwtAuthController::class, 'me']);
        Route::post('refresh', [JwtAuthController::class, 'refresh']);
        Route::post('logout', [JwtAuthController::class, 'logout']);
    });
});
```

## Praktik Tapşırıqlar

1. **JWT anatomy:** jwt.io saytında öz token-inizi decode edin. Header, payload, signature-ı müəyyən edin. `exp` claim-ini Unix timestamp-dən real vaxta çevirin.

2. **Sanctum token sistemi:** Login/logout/logoutAll endpoint-lərini implement edin. Abilities (`read`, `write`) ilə token yaradın, middleware-də `tokenCan()` ilə yoxlayın.

3. **JWT refresh flow:** `access_token` (15 dəq) + `refresh_token` (7 gün) məntiqi implement edin. Refresh zamanı köhnə refresh token-i ləğv edib yenisini verin.

4. **Redis blacklist:** `TokenBlacklist` service-ini yazın. Logout zamanı `jti`-ni Redis-ə əlavə edin (TTL = token-in qalan ömrü). `CheckTokenBlacklist` middleware-ini bütün protected route-lara tətbiq edin.

5. **RS256 keçid:** HS256-dan RS256-ya keçin. RSA key pair generasiya edin. Private key ilə sign, public key ilə verify konfiqurasiyasını qurun.

6. **Token version pattern:** `User` modelinə `token_version` sütunu əlavə edin. JWT-yə `version` claim əlavə edin. Logout zamanı `token_version++` edin. Middleware-də version uyğunluğunu yoxlayın.

## Əlaqəli Mövzular

- [OAuth 2.0](14-oauth2.md)
- [API Security](17-api-security.md)
- [HTTPS/SSL/TLS](06-https-ssl-tls.md)
- [REST API](08-rest-api.md)
- [mTLS Deep Dive](35-mtls-deep-dive.md)
