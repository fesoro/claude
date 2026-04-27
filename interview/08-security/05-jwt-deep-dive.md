# JWT Deep Dive (Senior ⭐⭐⭐)

## İcmal

JWT (JSON Web Token) — JSON formatında məlumatı imzalanmış (signed) şəkildə ötürmək üçün RFC 7519 standartıdır. Authentication token-larında, service-lər arası güvənli kommunikasiyada, API authorization-da geniş istifadə olunur. Interview-da bu mövzu yalnız "nədir" sualı üçün deyil — JWT-nin zəif tərəflərini, security anti-pattern-lərini, production-da düzgün implementasiyanı bilmək Senior developer-dan gözlənilir.

## Niyə Vacibdir

JWT stateless authentication imkanı verir — server heç nə saxlamır, scalability asandır. Lakin səhv implement edilmiş JWT ciddi security problem yaradır: `alg: none` attack, expired token qəbulu, sensitive data payload-da saxlama, HS256 secret-in exposure edilməsi. JWT-nin həm üstünlüklərini, həm zəifliklərini bilmək — session-based auth ilə müqayisə edə bilmək Senior əlamətidir.

## Əsas Anlayışlar

- **JWT struktur — 3 hissə**: `header.payload.signature` — hər hissə Base64URL encoded, nöqtə ilə ayrılmış. `eyJhbGciOiJSUzI1NiJ9.eyJzdWIiOiIxMjMifQ.signature_here`
- **Header**: JSON object: `{"alg": "RS256", "typ": "JWT", "kid": "key-id-2024"}`. `kid` (Key ID) — çoxlu key rotation dəstəyi üçün
- **Payload (Claims)**: Registered claims — `sub` (subject/user ID), `exp` (expiration unix timestamp), `iat` (issued at), `nbf` (not before), `iss` (issuer URL), `aud` (audience), `jti` (JWT ID — unikal UUID). Custom claims — `roles`, `tenant`, `scope`
- **Signature**: `HMACSHA256(base64url(header) + "." + base64url(payload), secret)` ya da RSA private key ilə imzalanır. Dəyişikliyi detect edir, lakin payload şifrəli deyil
- **JWS vs JWE**: JWS (JSON Web Signature) — imzalanmış, payload oxunaqlı. JWE (JSON Web Encryption, RFC 7516) — həm imzalanmış, həm şifrələnmiş. Sensitive data üçün JWE
- **Base64 ≠ Encryption**: payload base64 decode edilir — istənilən kəs oxuya bilər. `jwt.io`-da canlı decode. Sensitive data JWT-də saxlamamaق
- **HS256 (HMAC-SHA256)**: Symmetric — eyni secret key ilə həm sign, həm verify. Bütün service-lər secret-i bilməlidir — secret exposure riski artır. Sadə, sürətli
- **RS256 (RSA-SHA256)**: Asymmetric — private key ilə sign, public key ilə verify. Auth service private key saxlayır. Digər service-lər yalnız public key-ə ehtiyac duyur — secret exposure riski yoxdur. JWKS endpoint ilə key distribution
- **ES256 (ECDSA-SHA256)**: Elliptic curve — RS256-dan daha kiçik key/signature size, eyni security. Modern seçim
- **`alg: none` attack (CVE-2015-9235)**: Header-da `alg` field-ı `none` edilsə bəzi köhnə library-lər signature yoxlamırlar — token forged edilə bilər. Library-nizi seçərkən bu test edilməlidir. Whitelist algoritm — unknown `alg` dəyərini rədd et
- **`kid` SQL/LDAP injection**: Header-dakı `kid` field-ı key store-da lookup üçün istifadə edilirsə, injection mümkündür. `kid` dəyərini validate etmək lazımdır
- **Confused deputy attack**: Eyni HS256 secret-i fərqli service-lər paylaşırsa, bir service üçün nəzərdə tutulmuş token başqa service-ə göndərilə bilər. `aud` (audience) claim bu hücumun qarşısını alır
- **JWT storage dilemması**: localStorage — XSS ilə oğurlana bilər (JavaScript əlçatandır). Memory — page refresh-də itir. HTTP-only cookie — JavaScript-ə görünmür (XSS-ə davamlı), amma CSRF risk. `SameSite=Strict` + HTTP-only cookie recommended
- **Token revocation problemi**: JWT stateless — expire olmadan ləğv etmək çətindir. Həll 1: Qısa expiry (15 dəq) + refresh token. Həll 2: Redis blacklist (`jti` claim-i ilə). Həll 3: User-ə `token_version` saxlamaq
- **Refresh token rotation**: Hər refresh token istifadəsindən sonra yeni refresh token verilir, köhnəsi ləğv edilir. Oğurlanmış token istifadə ediləndə legitimate user-in refresh-i fail olur → detect edilir. Bütün family revoke edilir
- **Clock skew**: Server-lər arası saat fərqi `exp` check-ini poza bilər. 5 saniyə tolerans əlavə etmək lazımdır (`leeway`)
- **JWKS (JSON Web Key Set)**: Public key-ləri distribute etmək üçün standard format. `/.well-known/jwks.json` endpoint. Key rotation dəstəyi — `kid` ilə aktiv key seçilir

## Praktik Baxış

**Interview-da yanaşma:**
JWT mövzusunda ən güclü cavab security pitfall-larını bilməkdir. `alg: none` attack, payload-da sensitive data, revocation problemi, RS256 vs HS256 — bunları bilmək sizi orta cavabdan ayırır. Session-based auth ilə müqayisəni, trade-off-ları izah edə bilmək əla cavabın əlamətidir.

**Follow-up suallar (top companies-da soruşulur):**
- "JWT payload-ı encode olunub, amma şifrələnməyib — nə demək olar?" → Base64URL decode edib hər kəs oxuya bilər. Sensitive data — şifrə, kredit kartı, PII — JWT-də saxlamamaq lazımdır. Yalnız user ID, roles, scopes kimi minimal data
- "JWT revoke etmək lazım olsa nə edərsiniz?" → Option 1: Qısa expiry + refresh token rotation. Option 2: Redis blacklist `jti` claim-i ilə (stateless üstünlüyü itirilir). Option 3: User `token_version` — logout-da version artır, köhnə tokenlar invalid olur
- "Refresh token rotation nədir, niyə lazımdır?" → Hər istifadədən sonra yeni token, köhnəsi ləğv. Oğurlanmış token istifadə edilərsə legitimate user-in növbəti refresh-i fail olur → anomaly detected → bütün family revoke
- "HS256 vs RS256 — microservices-da niyə RS256?" → HS256-da bütün service-lər shared secret-i bilməlidir — leak risk artır. RS256-da yalnız auth service private key-i bilir. Digər service-lər yalnız public key-ə (JWKS-dən) ehtiyac duyur. Secret exposure blast radius azalır
- "`alg: none` attack nədir?" → Attacker token header-ını dəyişib `"alg": "none"` edir, signature-ı silir. Bəzi köhnə library-lər `alg: none` tokenı valid qəbul edir. Müdafiə: whitelist allowed algorithms — yalnız RS256/ES256 qəbul et
- "JWT-nin session-based auth-dan üstünlüyü nədir?" → Stateless: DB lookup hər request-də lazım deyil, horizontal scaling asandır, microservices-ə uyğundur. Çatışmazlıq: revocation çətin, payload-u dəyişmək olmur (yenisini issue etmək lazımdır)

**Ümumi səhvlər (candidate-ların etdiyi):**
- JWT-ni şifrəli hesab etmək — Base64 ≠ encryption, payload oxunaqlıdır
- Long-lived access token (24+ saat) — oğurlandıqda uzun müddət istismar üçün açıq
- Refresh token-ı localStorage-da saxlamaq — XSS ilə oğurlana bilər
- `alg: none` attack-a qarşı library-nin qorunduğunu yoxlamamaq
- Bütün sensitive data JWT-yə qoymaq — email, phone, full name — user ID kifayətdir

**Yaxşı cavabı əla cavabdan fərqləndirən:**
`alg: none` attack-ı mexanizm ilə izah etmək, RS256-nın microservices key distribution üstünlüyünü bilmək, refresh token rotation-un token theft detection-ı necə verdiyini, `aud` claim-inin confused deputy attack-ından qoruduğunu izah edə bilmək.

## Nümunələr

### Tipik Interview Sualı

"JWT nədir? Session-based auth ilə müqayisəsi, security risklər nələrdir?"

### Güclü Cavab

"JWT üç hissədən ibarət imzalanmış token: header, payload, signature. Stateless — server heç nə saxlamır, hər request-də verify edilir, horizontal scaling asandır.

Security nüansları: payload Base64-dür, şifrəli deyil — sensitive data qoymaq olmaz. Revoke etmək çətindir — qısa expiry (15 dəq) + refresh token pattern istifadə edirəm.

Ən kritik risk: `alg: none` attack — köhnə library-lər unsigned token-ı qəbul edirdi. Whitelist algorithm — yalnız RS256/ES256 qəbul et.

Microservices-da RS256: auth service private key ilə sign edir, digər service-lər JWKS-dən public key alıb verify edir — secret paylaşılmır, blast radius azaldır.

Session-based auth ilə müqayisə: Session — DB lookup lazımdır, revocation asandır, horizontal scaling-də shared session store. JWT — stateless, revocation çətin, scaling asandır."

### Kod/Konfiqurasiya Nümunəsi

```php
// ============================================================
// JWT Structure — Anatomy
// ============================================================

// Token: eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCIsImtpZCI6ImtleS0yMDI0In0.
//        eyJzdWIiOiJ1c2VyLTEyMyIsImlhdCI6MTcxNTAwMDAwMCwiZXhwIjoxNzE1MDAwOTAwfQ.
//        SIGNATURE

// Header decoded: {"alg":"RS256","typ":"JWT","kid":"key-2024"}
// Payload decoded: {"sub":"user-123","iat":1715000000,"exp":1715000900,"roles":["editor"]}
// ⚠️ Payload BASE64 — istənilən kəs oxuya bilər!

// jwt.io-da decode: Header + Payload görünür, Signature verify olunur
```

```php
// ============================================================
// JWT Service — Secure Implementation (lcobucci/jwt v5)
// ============================================================
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Rsa\Sha256 as RS256Signer;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint;

class JwtService
{
    private Configuration $config;

    public function __construct()
    {
        // RS256 — asymmetric
        $this->config = Configuration::forAsymmetricSigner(
            new RS256Signer(),
            // Auth service private key — yalnız burada
            InMemory::file(storage_path('keys/jwt-private.pem')),
            // Public key — verify üçün (JWKS-dən alınar real sistemdə)
            InMemory::file(storage_path('keys/jwt-public.pem'))
        );

        // Validation constraints
        $this->config->setValidationConstraints(
            new Constraint\IssuedBy(config('app.url')),
            new Constraint\PermittedFor(config('app.url')),
            new Constraint\SignedWith(
                $this->config->signer(),
                $this->config->signingKey()
            ),
            new Constraint\StrictValidAt(
                new \Lcobucci\Clock\SystemClock(new \DateTimeZone('UTC')),
                leeway: new \DateInterval('PT5S') // 5 saniyə clock skew tolerans
            ),
        );
    }

    public function issue(User $user, string $audience = null): string
    {
        $now = new \DateTimeImmutable();

        $token = $this->config->builder()
            ->issuedBy(config('app.url'))                    // iss
            ->permittedFor($audience ?? config('app.url'))  // aud — confused deputy attack önlər
            ->identifiedBy((string) Str::uuid())            // jti — blacklist üçün unikal ID
            ->issuedAt($now)                                // iat
            ->canOnlyBeUsedAfter($now)                      // nbf
            ->expiresAt($now->modify('+15 minutes'))        // exp — QISA!
            ->withHeader('kid', config('jwt.current_key_id')) // Key rotation dəstəyi
            // Payload — MINIMUM data, sensitive data HEÇ VAXT
            ->withClaim('sub', $user->id)
            ->withClaim('roles', $user->getRoleNames()->toArray())
            ->withClaim('tenant', $user->tenant_id)
            // Qoyma: email, password hash, credit card, SSN, address
            ->getToken($this->config->signer(), $this->config->signingKey());

        return $token->toString();
    }

    public function verify(string $tokenString): \Lcobucci\JWT\Token\Plain
    {
        try {
            $token = $this->config->parser()->parse($tokenString);

            // Validation constraints-i yoxla (exp, iss, aud, sig, nbf)
            $this->config->validator()->assert($token, ...$this->config->validationConstraints());

            // ⚠️ alg: none attack — lcobucci/jwt v5 default olaraq rədd edir
            // Köhnə library-lərdə manual whitelist lazımdır:
            // $allowedAlgs = ['RS256', 'ES256'];
            // if (!in_array($token->headers()->get('alg'), $allowedAlgs, true)) {
            //     throw new \RuntimeException('Algorithm not allowed');
            // }

            // Redis blacklist yoxlama (optional — stateless qorunur)
            $jti = $token->claims()->get('jti');
            if (Cache::has("jwt_blacklist:{$jti}")) {
                throw new \RuntimeException('Token has been revoked');
            }

            return $token;

        } catch (\Exception $e) {
            throw new UnauthorizedException('Invalid token: ' . $e->getMessage());
        }
    }

    public function revoke(string $tokenString): void
    {
        $token = $this->config->parser()->parse($tokenString);
        $jti   = $token->claims()->get('jti');
        $exp   = $token->claims()->get('exp');

        // Token expire olana qədər blacklist-də saxla
        $ttl = max(0, $exp->getTimestamp() - time());
        Cache::put("jwt_blacklist:{$jti}", true, $ttl);
    }
}
```

```php
// ============================================================
// Refresh Token Rotation — Full Implementation
// ============================================================
class RefreshTokenService
{
    public function issue(User $user): array
    {
        $raw    = Str::random(64);
        $hashed = hash('sha256', $raw);
        $family = (string) Str::uuid(); // Token family tracking

        RefreshToken::create([
            'user_id'    => $user->id,
            'token_hash' => $hashed,
            'family'     => $family,
            'expires_at' => now()->addDays(7),
        ]);

        return ['raw' => $raw, 'family' => $family];
    }

    public function rotate(string $rawToken): array
    {
        $hashed = hash('sha256', $rawToken);

        $token = RefreshToken::where('token_hash', $hashed)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->with('user')
            ->first();

        if (!$token) {
            // Token tapılmadı — artıq istifadə edilmiş ola bilər → reuse attack
            $this->handlePossibleReuse($hashed);
            throw new UnauthorizedException('Invalid refresh token');
        }

        // Rotation: köhnəni revoke et
        $token->update(['revoked_at' => now()]);

        // Yenisini yarat (eyni family)
        $newRaw    = Str::random(64);
        $newHashed = hash('sha256', $newRaw);

        RefreshToken::create([
            'user_id'    => $token->user_id,
            'token_hash' => $newHashed,
            'family'     => $token->family,  // Eyni family
            'expires_at' => now()->addDays(7),
        ]);

        return [
            'user'          => $token->user,
            'refresh_token' => $newRaw,
        ];
    }

    private function handlePossibleReuse(string $hashed): void
    {
        // Artıq revoke edilmiş token istifadə edildi → theft şübhəsi
        $compromised = RefreshToken::where('token_hash', $hashed)
            ->whereNotNull('revoked_at')
            ->first();

        if ($compromised) {
            // Eyni family-dəki bütün aktiv token-ları revoke et
            RefreshToken::where('family', $compromised->family)
                ->whereNull('revoked_at')
                ->update(['revoked_at' => now()]);

            Log::critical('Refresh token reuse detected — possible theft', [
                'user_id' => $compromised->user_id,
                'family'  => $compromised->family,
            ]);
        }
    }
}
```

```php
// ============================================================
// JWKS Endpoint — Public key distribution (auth service)
// ============================================================
class JwksController extends Controller
{
    public function index(): JsonResponse
    {
        // Public key-ləri JWKS formatında paylaş
        $keyData = openssl_pkey_get_details(
            openssl_pkey_get_public(file_get_contents(storage_path('keys/jwt-public.pem')))
        );

        return response()->json([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'kid' => config('jwt.current_key_id'),
                    'n'   => base64_encode($keyData['rsa']['n']),
                    'e'   => base64_encode($keyData['rsa']['e']),
                ],
            ],
        ], 200, [
            'Cache-Control' => 'public, max-age=3600', // JWKS 1 saat cache
        ]);
    }
}

// routes/api.php
Route::get('/.well-known/jwks.json', [JwksController::class, 'index'])
    ->withoutMiddleware(['auth']);
```

### Attack/Defense Nümunəsi

```
ALG: NONE ATTACK:

1. Legitimate token:
   Header: {"alg":"RS256","typ":"JWT"}
   Payload: {"sub":"1","roles":["user"],"exp":1715000900}
   Signature: abc123...

2. Attacker modifiye edir:
   Header: {"alg":"none","typ":"JWT"}   ← alg dəyişdi
   Payload: {"sub":"1","roles":["admin"],"exp":9999999999}  ← admin oldu, exp artdı
   Signature: (boş)

3. Əgər server "alg" header-ından trust edərsə:
   → "alg: none → signature check etmə" → attacker admin oldu!

DEFENSE:
   // Whitelist — yalnız RS256/ES256 qəbul et
   $allowedAlgorithms = ['RS256', 'ES256'];
   $algorithm = $token->headers()->get('alg');
   
   if (!in_array($algorithm, $allowedAlgorithms, true)) {
       throw new SecurityException('Algorithm not allowed: ' . $algorithm);
   }
   
   // Müasir library-lər (lcobucci/jwt v5, firebase/jwt) default olaraq qoruyur

CONFUSED DEPUTY ATTACK:
   Service A token-ı service B-yə göndərir (HS256 shared secret)
   
   Service A üçün token: {"sub":"user-1","aud":"service-a","role":"user"}
   Attacker bu token-ı service B-yə göndərir (eyni HS256 secret)
   Service B başqa service üçün nəzərdə tutulmuş token-ı qəbul edir

DEFENSE:
   // aud claim-i validate et
   if (!in_array(config('app.service_name'), (array)$token->claims()->get('aud'), true)) {
       throw new SecurityException('Token audience mismatch');
   }
```

## Praktik Tapşırıqlar

1. jwt.io-da öz token-ınızı decode edin — payload-da nə var? Sensitive data varmı?
2. `alg: none` attack-ı test edin: istifadə etdiyiniz JWT library header-ı `none` ilə göndərdikdə bunu rədd edirmi?
3. Refresh token rotation implement edin + token reuse detection: oğurlanmış token yenidən istifadə edildikdə family-nin hamısı revoke olunur
4. HS256 vs RS256 microservices ssenarisi: shared secret vs public/private key pair — key distribution fərqini code ilə göstərin
5. JWKS endpoint implement edin: public key-i `/.well-known/jwks.json` endpoint-indən serve edin
6. Token blacklist Redis-də implement edin: logout → `jti` claim-i Redis-ə əlavə et, middleware-də yoxla
7. `exp` claim-i manipulate edin: token-ın `exp`-ini artırıb göndərin — library valid hesab edirmi?

## Əlaqəli Mövzular

- `04-authentication-authorization.md` — AuthN/AuthZ əsasları, session vs token
- `06-oauth2-flows.md` — OAuth 2.0 + JWT kombinasiyası, access/refresh token
- `08-secrets-management.md` — JWT private key/secret idarəsi, rotation
- `13-data-encryption.md` — JWE (şifrəli JWT), sensitive payload-lar üçün
