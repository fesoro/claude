# OAuth 2.0 and JWT (Senior ⭐⭐⭐)

## İcmal

OAuth 2.0 — authorization framework-dir: üçüncü tərəf aplikasiyaların istifadəçi şifrəsini bilmədən onların adından resurslara daxil olmasını təmin edir. JWT (JSON Web Token) — imzalanmış, özündə məlumat daşıyan token format-ıdır. Bu iki texnologiya əksər hallarda birlikdə istifadə olunur, lakin fərqli məqsədlər üçündür. 5+ il əvvəl "session vs token" müzakirəsi aparılırdı — bu gün OAuth 2.0 + JWT kombinasiyası standart olmuşdur. Interview-larda bu mövzu API security, microservices auth, SSO sualları ilə gəlir.

## Niyə Vacibdir

Auth sistemi produksiyada ən kritik komponentdir. Yanlış implement edilmiş JWT yaxud OAuth flow ciddi security açığına çevrilir. Interviewer yoxlayır: "JWT-i refresh token olmadan istifadə etmənin nə riski var? OAuth Authorization Code flow niyə Implicit flow-dan güvənlidir? Access token server-side necə revoke edilə bilər?" Bu suallara cavab verə bilmək Senior developer səviyyəsinin nişanəsidir. Real breach-lərin böyük hissəsi auth sistemindəki səhvlərdən qaynaqlanır.

## Əsas Anlayışlar

**JWT (JSON Web Token) — RFC 7519:**
- Üç hissədən ibarətdir: `Header.Payload.Signature` (Base64URL encoded, nöqtə ilə ayrılmış). Məs: `eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjMifQ.xyz`
- Header: algorithm (`alg`: HS256, RS256, ES256) və token type (`typ`: JWT)
- Payload (Claims): `sub` (subject/user id), `iat` (issued at), `exp` (expiry unix timestamp), `iss` (issuer URL), `aud` (audience), `jti` (JWT ID — unikal identifier), custom claims
- Signature: `HMACSHA256(base64url(header) + "." + base64url(payload), secret)`. Dəyişiklik detect edilir, lakin payload şifrəli deyil — Base64 ilə decode olunur
- **Stateless**: Server-də session saxlanmır — hər request-də token verify edilir. Horizontal scaling asandır, database sorğusu lazım deyil
- **Dezavantaj**: Token revoke etmək çətin — expire olmayınca keçərlidir. Həll: short-lived access token (15 dəq) + refresh token + Redis token blacklist
- **HS256 vs RS256**: HS256 symmetric — eyni secret key ilə həm sign, həm verify. RS256 asymmetric — private key ilə sign, public key ilə verify. Microservices-də auth service private key saxlayır, digər service-lər public key ilə (JWKS endpoint-dən) verify edir
- **JWT-ni localStorage-da saxlamaq**: XSS attack riski — JavaScript cookie-ni oxuya bilir. HTTP-only cookie daha güvənlidir, amma CSRF risk yaranır. `SameSite=Strict` + HTTPS ilə cookie yanaşması recommended
- **`alg: none` attack**: Header-da `alg` field-ı `none` edilsə bəzi köhnə library-lər signature yoxlamır — kritik zəiflik. Library seçimində bu yoxlanmalıdır
- **JWT payload-da sensitive data saxlamamaq**: payload Base64 decode edilir — email, role kifayətdir; şifrə, kredit kartı, SSN heç vaxt!

**OAuth 2.0 — RFC 6749:**
- 4 əsas role: Resource Owner (user), Client (application), Authorization Server (auth service), Resource Server (API)
- **Authorization Code Flow** (ən güvənli, web app + SPA üçün PKCE ilə):
  1. Client user-i auth server-ə redirect edir (`/authorize?response_type=code&client_id=...&redirect_uri=...&state=...`)
  2. User login olur, consent verir
  3. Auth server client-ə qısa ömürlü authorization code qaytarır (URL-də, 60 saniyə keçərli)
  4. Client backend-də bu kodu access token ilə dəyişir (`/token` endpoint, client secret ilə)
  5. Backend access token + refresh token alır — frontend heç vaxt client secret-i görmür
- **PKCE (Proof Key for Code Exchange) — RFC 7636**: Public client-lər (SPA, mobile) üçün — code_verifier (random 43-128 char) → SHA256 hash → code_challenge. Authorization code intercepting attack-ından qorunma. Client secret olmadan güvənli
- **Client Credentials Flow**: Server-to-server, istifadəçi yoxdur. Microservice öz `client_id` + `client_secret` ilə token alır. Human user yoxdur
- **Implicit Flow**: DEPRECATED (RFC 8252) — access token URL fragment-də qaytarılırdı, browser history-ə düşürdü. 2019-dan bəri tövsiyə edilmir
- **Device Authorization Flow — RFC 8628**: TV, IoT, CLI üçün — browser yoxdur. User ayrı cihazdan kodu daxil edir
- **Token types**:
  - Access token: Qısa ömürlü (15 dəqiqə - 1 saat), resurslara daxil olmaq üçün, Bearer scheme ilə
  - Refresh token: Uzun ömürlü (gün-ay), yeni access token almaq üçün, bir dəfə istifadə (rotation ilə)
  - ID token: OIDC-də user identity məlumatı, JWT format, `openid` scope-u ilə

**OpenID Connect (OIDC) — OAuth 2.0 üzərindən authentication layer:**
- OAuth 2.0 authorization edir (nəyə icazə var), OIDC authentication edir (kim olduğu)
- ID token: Signed JWT, `sub`, `email`, `name`, `picture` claims. User identity
- `/userinfo` endpoint: ID token-la user məlumatlarını almaq
- Discovery: `/.well-known/openid-configuration` — JWKS URI, token endpoint, supported scopes

**Token revocation strategiyaları:**
- Short expiry + refresh rotation: Access token 15 dəqiqə. Logout zamanı refresh token silmək kifayət edir
- Redis blacklist: Revoke edilmiş `jti` claim-lərini Redis-də saxlamaq. Hər request-də yoxlanılır — stateless üstünlüyü itirilir, lakin immediate revocation mümkün
- JWT version/generation: User model-də `token_version` saxlamaq — token-dakı versiya uyğun deyilsə rədd et

**Scope — principle of least privilege:**
- `read:profile`, `write:orders`, `email` — token yalnız bu əməliyyatları edə bilər
- Client yalnız lazım olan scope-ları istəyir
- User consent screen-də scope-lar göstərilir

## Praktik Baxış

**Interview-da yanaşma:**
OAuth flow-u whiteboard üzərindən step-by-step izah edin. JWT-nin stateless olmasının üstünlüklərini, revocation çətinliyinin trade-off-u ilə birlikdə qeyd edin. "Production-da HS256 yoxsa RS256?" sualına hazır olun.

**Follow-up suallar (top companies-da soruşulur):**
- "JWT payload şifrələnirmi?" → Xeyr, sadəcə imzalanır (Base64URL encode). İstənilən kəs decode edə bilər. jwt.io-da canlı nümunə göstərin. Sensitive data JWT-də saxlanmamalıdır
- "Refresh token rotation nədir?" → Hər refresh token istifadəsindən sonra yeni refresh token verilir, köhnəsi ləğv edilir. Token theft detect etmək imkanı verir — oğurlanmış token-dan yeni token alınırsa, legitimate user-in növbəti refreshi uğursuz olacaq, anomaly detect ediləcək
- "SSO (Single Sign-On) OAuth 2.0 ilə necə işləyir?" → Mərkəzi auth server, birdən çox resource server. User bir dəfə login olur, bütün service-lər eyni access token-ı qəbul edir. OIDC session management bunun üstünə qurulur
- "Laravel Sanctum vs Passport fərqi?" → Sanctum SPA + mobile üçün sadə opaque token auth. Passport full OAuth 2.0 server implementation — authorization code flow, client credentials, PKCE dəstəyi
- "PKCE-siz Authorization Code Flow-da nə risk var?" → Public client-lərdə (SPA, mobile) client secret olmadığından authorization code intercepting attack-ı mümkündür. Attacker araya girib code-u oğurlaya bilər
- "JWT-nin expiry-sini necə yoxlayırsınız?" → `exp` claim-i current unix timestamp ilə müqayisə. Clock skew toleransı (5 saniyə) əlavə etmək lazımdır — server-lər arası saat fərqi ola bilər
- "Microservices-də token propagation necə işləyir?" → Access token hər downstream service-ə `Authorization: Bearer` ilə ötürülür. Hər service öz doğrulamasını edir. Alternativ: token exchange (service-specific token)

**Ümumi səhvlər (candidate-ların etdiyi):**
- JWT-ni şifrəli hesab etmək — decode edilə bilər, sensitive data saxlamayın
- Access token-i uzun ömürlü etmək (24 saat+) — revocation çətin olur, oğurlanma uzun müddət istismar üçün açıq qalır
- Refresh token-i localStorage-da saxlamaq — XSS ilə oğurlana bilər
- OAuth 2.0 ilə "authentication etdik" demək — OAuth authorization framework-dir, OIDC authentication üçündür
- PKCE olmadan public client-lərdə Authorization Code flow istifadə etmək
- `state` parameter-ı validate etməmək — CSRF attack-a açıq qalır

**Yaxşı cavabı əla cavabdan fərqləndirən:**
RS256-nın microservices-da HS256-dan üstünlüyünü (public key distribution vs shared secret), refresh token rotation-ın token theft detection-ı necə verdiyini, PKCE-nin authorization code intercept attack-ını mexanizm ilə necə önlədiyini, `aud` claim-inin audience restriction-ı necə təmin etdiyini izah edə bilmək.

## Nümunələr

### Tipik Interview Sualı

"Design the authentication system for a microservices application. Multiple services need to verify user identity. How would you use JWT and OAuth?"

### Güclü Cavab

Microservices auth üçün centralized auth service + JWT (RS256) kombinasiyasını seçərdim.

**Arxitektura:**
- Auth Service: OIDC-compliant server, RS256 ilə JWT imzalayır. `/.well-known/jwks.json` endpoint-i ilə public key-ləri paylaşır
- Hər microservice: Auth service-dən public key alır (startup-da, cache edilir), gələn JWT-ni lokal olaraq verify edir. Hər request üçün auth service-ə call etmək lazım deyil — performance üstünlüyü

**Token flow:**
- Access token: 15-30 dəqiqə, user id + roles + tenant + scopes payload-da
- Refresh token: Auth service-də database-də saxlanır, HTTP-only `SameSite=Strict` cookie-də
- Refresh rotation: Hər refresh-də yeni token pair, köhnəsi ləğv

**Service-to-service:**
- Client Credentials flow: Service-A, auth service-dən service-specific token alır, Service-B-yə göndərir
- Scope-lar: `inventory:read`, `payment:write` — minimum privilege

**Revocation:**
- Refresh token database-də saxlandığı üçün logout zamanı silmək kifayətdir
- Access token 15 dəqiqəyə qədər keçərlidir — qəbul ediləbilən window
- Emergency revocation: Redis blacklist `jti` claim-i ilə

### Kod Nümunəsi

```php
// Laravel Sanctum ilə API token auth
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            // 401 — kimlik doğrulanmadı
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = $request->user();

        // Device-specific token ilə scope
        $token = $user->createToken(
            name: $request->input('device_name', 'api'),
            abilities: ['orders:read', 'orders:write', 'profile:read'],
            expiresAt: now()->addDays(30),
        );

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_at'   => $token->accessToken->expires_at?->toIso8601String(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Yalnız cari device-in token-ini revoke et
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Successfully logged out']);
    }

    public function logoutAllDevices(Request $request): JsonResponse
    {
        // Bütün token-ləri revoke et — bütün cihazlardan çıxış
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out from all devices']);
    }
}
```

```php
// Microservices üçün RS256 JWT verify etmək
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class JwtVerificationMiddleware
{
    private const CACHE_KEY    = 'auth_service_jwks';
    private const CACHE_TTL    = 3600; // 1 saat
    private const CLOCK_LEEWAY = 5;    // saniyə — clock skew tolerans

    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractBearerToken($request);

        try {
            // JWKS-dən public key-ləri al (cache-lənmiş)
            $jwks = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
                $response = Http::timeout(5)->get(config('auth.jwks_uri'));
                if (!$response->ok()) {
                    throw new \RuntimeException('Cannot fetch JWKS');
                }
                return $response->json();
            });

            $keys = JWK::parseKeySet($jwks);

            // JWT::decode exp, iat, nbf claim-lərini avtomatik yoxlayır
            $decoded = JWT::decode($token, $keys);
            $decoded->leeway = self::CLOCK_LEEWAY;

            // Əlavə claim yoxlamaları
            if ($decoded->iss !== config('auth.issuer')) {
                throw new UnauthorizedException('Token issuer mismatch');
            }

            if (!in_array(config('app.service_name'), (array)$decoded->aud, true)) {
                throw new UnauthorizedException('Token audience mismatch');
            }

            // User context-i request-ə əlavə et
            $request->merge(['auth_user' => [
                'id'     => $decoded->sub,
                'roles'  => $decoded->roles ?? [],
                'scopes' => explode(' ', $decoded->scope ?? ''),
                'tenant' => $decoded->tenant ?? null,
            ]]);

        } catch (ExpiredException $e) {
            return response()->json(['error' => 'Token expired'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        return $next($request);
    }

    private function extractBearerToken(Request $request): string
    {
        $header = $request->header('Authorization', '');

        if (!str_starts_with($header, 'Bearer ')) {
            throw new UnauthorizedException('No bearer token provided');
        }

        return substr($header, 7);
    }
}
```

```php
// Refresh Token Rotation — secure implementation
class TokenRefreshController extends Controller
{
    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->cookie('refresh_token');

        if (!$refreshToken) {
            return response()->json(['error' => 'No refresh token'], 401);
        }

        // Hash ilə DB-də saxlanılır — orijinal token görünmür
        $hashedToken = hash('sha256', $refreshToken);

        $tokenRecord = RefreshToken::query()
            ->with('user')
            ->where('token_hash', $hashedToken)
            ->where('expires_at', '>', now())
            ->whereNull('revoked_at')
            ->first();

        if (!$tokenRecord) {
            // Token tapılmadısa — possible token reuse attack
            // Əgər artıq revoke edilmişsə, bütün family-ni revoke et
            $this->detectAndHandleTokenReuse($hashedToken);
            return response()->json(['error' => 'Invalid refresh token'], 401);
        }

        // Rotation: köhnəni revoke et, yenisini yarat
        $tokenRecord->update(['revoked_at' => now()]);

        $newRefreshRaw   = Str::random(64);
        $newRefreshHash  = hash('sha256', $newRefreshRaw);

        RefreshToken::create([
            'user_id'      => $tokenRecord->user_id,
            'token_hash'   => $newRefreshHash,
            'family'       => $tokenRecord->family, // Token family tracking
            'expires_at'   => now()->addDays(7),
        ]);

        $newAccessToken = $this->jwtService->issue($tokenRecord->user);

        return response()
            ->json([
                'access_token' => $newAccessToken,
                'token_type'   => 'Bearer',
            ])
            ->withCookie(
                cookie('refresh_token', $newRefreshRaw)
                    ->httpOnly()
                    ->secure()
                    ->sameSite('Strict')
                    ->minutes(60 * 24 * 7)
            );
    }

    private function detectAndHandleTokenReuse(string $hashedToken): void
    {
        // Artıq revoke edilmiş token istifadə edildi — token theft şübhəsi
        $compromised = RefreshToken::where('token_hash', $hashedToken)
            ->whereNotNull('revoked_at')
            ->first();

        if ($compromised) {
            // Eyni family-dəki bütün token-ləri revoke et
            RefreshToken::where('family', $compromised->family)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            Log::warning('Refresh token reuse detected', [
                'user_id' => $compromised->user_id,
                'family'  => $compromised->family,
            ]);
        }
    }
}
```

```
OAuth 2.0 Authorization Code Flow (PKCE ilə):

User → Browser (SPA)         Auth Server         Resource API
  |                               |                    |
  |--- Login click ------------> |                    |
  |    code_verifier generate     |                    |
  |    code_challenge=SHA256(cv)  |                    |
  |--- GET /authorize? ---------->|                    |
  |    response_type=code         |                    |
  |    code_challenge=...         |                    |
  |    state=random_csrf_token    |                    |
  |                               |--- Login UI -----> |
  |<-- redirect?code=ABC&state=--  |                   |
  |    (verify state == original)  |                   |
  |                                                    |
  |--- POST /token ------------------------------>     |
  |    code=ABC, code_verifier=original_random         |
  |                               |                    |
  |<-- access_token + refresh_token <----------        |
  |    (code_verifier matches challenge → valid)       |
  |                                                    |
  |--- GET /api/profile ----------------------------> |
  |    Authorization: Bearer access_token             |
  |<-- 200 OK user data ------------------------------ |
```

## Praktik Tapşırıqlar

1. jwt.io-da JWT decode edin: payload-ı görmək nə qədər asandır — sensitive data saxlamayın
2. `alg: none` attack-ı test edin: `{"alg":"none","typ":"JWT"}` header ilə token yaradın, istifadə etdiyiniz library bunu rədd edirmi?
3. Laravel Passport ilə tam OAuth 2.0 server qurun: Authorization Code + PKCE flow, Client Credentials flow
4. RS256 ilə signed JWT: auth service private key ilə sign edir, resource service yalnız public key ilə verify edir — private key heç vaxt paylaşılmır
5. Refresh token rotation implement edin + token reuse detection: oğurlanmış token istifadə ediləndə bütün family revoke olunur
6. Token blacklist Redis-də implement edin: logout → `jti` Redis-ə əlavə et, middleware-də hər request-də yoxla
7. PKCE flow-u Postman ilə manual test edin: code_verifier → code_challenge → token exchange

## Əlaqəli Mövzular

- [TLS/SSL Handshake](03-tls-ssl-handshake.md) — OAuth token-lər TLS üzərindən göndərilir, HTTPS mütləq
- [CORS](10-cors.md) — Authorization header CORS preflight tələb edir
- [HTTP Caching](09-http-caching.md) — Auth endpoint-lər cache olunmamalıdır (`no-store`)
- [Webhook Design](12-webhook-design.md) — Webhook auth: HMAC signature vs OAuth token
- [API Versioning](08-api-versioning.md) — Auth versiyalanması, breaking change-lər
