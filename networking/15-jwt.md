# JWT (JSON Web Token)

## Nədir? (What is it?)

JWT (RFC 7519) iki teref arasinda melumat oturen compact, URL-safe token formatıdır. Authentication ve information exchange ucun istifade olunur. Token 3 hisseden ibaretdir: Header, Payload, Signature. Server session saxlamadan istifadecini taniyi biler (stateless authentication).

```
Sessiya-based auth:
  Client --> Cookie: session_id=abc123 --> Server --> Session Store-dan user tap
  (Server state saxlayir - scaling cetin)

JWT-based auth:
  Client --> Authorization: Bearer eyJhbG... --> Server --> Token-i verify et
  (Server state saxlamır - asan scale olunur)
```

## Necə İşləyir? (How does it work?)

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
  |                               |  JWT yarat ve imzala
  |<-- {access_token, -----------|
  |     refresh_token}           |
  |                               |
  |-- GET /api/users ----------->|
  |   Authorization:              |
  |   Bearer eyJhbG...           |
  |                               |  Signature verify et
  |                               |  Expiration yoxla
  |                               |  Claims-den user tap
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
  (eyni key sign ve verify ucun - microservices ucun uygun deyil)

Asymmetric (public/private key pair):
  RS256 - RSA with SHA-256
  RS384 - RSA with SHA-384
  RS512 - RSA with SHA-512
  ES256 - ECDSA with SHA-256
  (Private key sign edir, public key verify edir - microservices ucun ideal)

  Auth Server: private key ile sign edir
  API Server 1: public key ile verify edir
  API Server 2: public key ile verify edir
  (API server-ler token yarada bilmir, yalniz yoxlaya bilir)
```

## Əsas Konseptlər (Key Concepts)

### Registered Claims (Standard)

```
iss (Issuer)      - Token-i kim yaratdi
sub (Subject)     - Token kimin ucundur (user ID)
aud (Audience)    - Token kimin ucun nezerde tutulub
exp (Expiration)  - Token ne vaxt bitir (Unix timestamp)
nbf (Not Before)  - Token ne vaxtdan kecerlidir
iat (Issued At)   - Token ne vaxt yaradilib
jti (JWT ID)      - Token-in unique ID-si (replay protection)
```

### Token Refresh Strategy

```
Access Token:  Qisa omurlu (15 deq)
Refresh Token: Uzun omurlu (7 gun), DB-de saxlanir

Login:
  -> access_token (15 deq) + refresh_token (7 gun)

API Request:
  -> access_token ile
  -> 401 alinda -> refresh_token ile yeni access_token al

Refresh:
  -> Kohne refresh_token gonderin
  -> Yeni access_token + yeni refresh_token alin
  -> Kohne refresh_token artiq kecersizdir (rotation)
```

### Token Blacklisting / Revocation

```
Problem: JWT stateless-dir, logout olanda nece invalidate ederik?

1. Token Blacklist (Redis/DB):
   Logout -> token-in jti-sini blacklist-e elave et
   Her request -> blacklist-de var mi yoxla
   TTL = token-in qalan omru

2. Short-lived tokens + Refresh token revocation:
   Access token 15 deq yasar - logout-dan sonra max 15 deq isleyir
   Refresh token DB-den silir - yeni access token ala bilmez

3. Token version (user-based):
   User model-de token_version field
   JWT-de version claim elave et
   Logout -> token_version++ -> butun kohne token-ler invalid
```

## PHP/Laravel ilə İstifadə

### Laravel Sanctum (Recommended for SPA/Mobile)

```php
// Sanctum - Laravel-in oz token sistemi (JWT deyil, amma oxsar istifade)
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
        // Butun token-leri sil (butun cihazlardan cix)
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out from all devices']);
    }
}
```

### JWT Package (tymon/jwt-auth)

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

### Token Blacklisting with Redis

```php
namespace App\Services;

use Illuminate\Support\Facades\Redis;

class TokenBlacklist
{
    /**
     * Token-i blacklist-e elave et
     */
    public function add(string $jti, int $expiresIn): void
    {
        Redis::setex("blacklist:jwt:{$jti}", $expiresIn, '1');
    }

    /**
     * Token blacklist-de var mi?
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

### Routes

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

## Interview Sualları

### 1. JWT nedir ve nece isleyir?
**Cavab:** JWT 3 hisseden (Header.Payload.Signature) ibaret, base64url encoded token-dir. Server token-i secret key ile imzalayir. Her request-de client token-i gonderir, server signature-i verify edir. Stateless-dir - server session saxlamir.

### 2. JWT ve session-based auth arasinda ferq nedir?
**Cavab:** Session-based-de server session_id-ni DB/Redis-de saxlayir (stateful). JWT-de butun melumat token icindedir (stateless). JWT daha asan scale olunur (shared session store lazim deyil), amma revocation cetindir.

### 3. JWT-ni nece invalidate edirsiniz (logout)?
**Cavab:** 3 usul: 1) Token blacklist (Redis-de jti saxla), 2) Qisa omurlu access token + refresh token revocation (DB-den sil), 3) User-de token version saxla, version deyisende butun token-ler invalid olur.

### 4. Access token ve refresh token arasinda ferq nedir?
**Cavab:** Access token qisa omurlu (15 deq), API-ya erisim ucun. Refresh token uzun omurlu (7 gun), yeni access token almaq ucun. Refresh token DB-de saxlanir ve revoke oluna biler. Access token stateless-dir.

### 5. HS256 ve RS256 arasinda ferq nedir?
**Cavab:** HS256 symmetric-dir (eyni secret key sign+verify). RS256 asymmetric-dir (private key sign, public key verify). Microservices ucun RS256 daha yaxsidir - API serverlere yalniz public key lazimdir.

### 6. JWT-de hansi melumatlari saxlamamaliyiq?
**Cavab:** Sensitive data: shifre, kredit karti, SSN. JWT base64 encoded-dir (encrypted deyil!) - her kes payload-u decode ede biler. Yalniz non-sensitive identifiers saxlayin: user_id, role, email.

### 7. JWT-nin dezavantajlari nelerdir?
**Cavab:** Token olcusu boyukdur (cookie/session ID-den), revocation cetindir (stateless), payload deyise bilmez (yeni token lazim), token boyudu artdiqca bandwidth artir. XSS-e qarsi hessasdir (localStorage-de saxlananda).

### 8. JWT-ni harada saxlamamliyiq?
**Cavab:** **httpOnly cookie** (XSS-den qorunur, CSRF ucun ayri tedbir lazim) ve ya **memory** (en tehlukesiz, amma sehife yenilenende itir). **localStorage/sessionStorage** XSS-e hessasdir - tovsiye olunmur.

## Best Practices

1. **Qisa omurlu access token** - 15 deqiqe ideal
2. **httpOnly cookie** - Token-i localStorage-de saxlamayin
3. **RS256 istifade edin** - Microservices ucun asymmetric signing
4. **Sensitive data qoymayin** - JWT encrypted deyil, base64-dir
5. **exp claim hemise** - Token-e expiration elave edin
6. **Refresh token rotation** - Her istifadede yeni refresh token
7. **Token blacklisting** - Logout ucun Redis-based blacklist
8. **jti claim** - Replay attack-den qorunmaq ucun unique ID
9. **aud/iss claims** - Token-in hansi service ucun oldugunu yoxlayin
10. **Algorithm specify edin** - `alg: "none"` attack-den qorunun
