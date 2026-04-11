# Miqyasda Session İdarəetməsi — Stateless Auth, JWT vs Opaque Token

## Problem Təsviri

Müasir web tətbiqləri horizontal scaling tələb edir: trafik artdıqda yeni server nümunələri əlavə edilir. Ənənəvi PHP session mexanizmi (`$_SESSION`) default olaraq session məlumatlarını fayl sisteminə yazır. Bu yanaşma tək server üçün işləyir, lakin bir neçə server arxasında load balancer olduqda ciddi problemlər yaranır: istifadəçi hər sorğuda fərqli serverə düşə bilər və öz session məlumatlarını tapa bilməz.

Bu use-case session idarəetməsinin miqyas problemlərini, müasir həll yollarını və token-based authentication arxitekturasını əhatə edir.

---

## 1. Ənənəvi PHP Session-larının Miqyas Problemi

PHP-nin default session mexanizmi `session.save_handler = files` istifadə edir. Session faylları `/tmp` qovluğunda saxlanır.

*Bu kod fayl sisteminə yazan ənənəvi PHP session yanaşmasını göstərir — çox serverdə işləmir:*

```php
<?php
// Ənənəvi yanaşma — tək server üçün işləyir
session_start();
$_SESSION['user_id'] = 42;
$_SESSION['role'] = 'admin';
```

**Problem ssenarisi:**

```
İstifadəçi Login → Server A (session yaradır: sess_abc123)
Növbəti sorğu  → Server B (sess_abc123 tapılmır → istifadəçi logout olunub!)
Növbəti sorğu  → Server C (eyni problem)
```

**Niyə problem yaranır:**
- Hər server öz lokal fayl sisteminə yazır
- Server B, Server A-nın `/tmp` qovluğuna daxil ola bilmir
- İstifadəçi gözlənilmədən autentifikasiya itkisi yaşayır
- Deployment zamanı yeni server əlavə etmək problemi daha da artırır

---

## 2. Sticky Session-lar — Nədir, Niyə Problemdir

**Sticky session** (və ya session affinity) — load balancer-in hər istifadəçini həmişə eyni serverə yönləndirməsidir. Bu, session problemini müvəqqəti həll edir.

*Bu kod Nginx-də ip_hash ilə sticky session konfiqurasiyasını göstərir:*

```nginx
# Nginx sticky session konfiqurasiyası
upstream backend {
    ip_hash;  # Eyni IP həmişə eyni serverə
    server 10.0.0.1:9000;
    server 10.0.0.2:9000;
    server 10.0.0.3:9000;
}
```

**Sticky session-ın problemləri:**

```
1. Yük bərabərsizliyi:
   - İstifadəçi A (çox aktiv) → həmişə Server A → Server A həddindən artıq yüklənir
   - İstifadəçi B (az aktiv) → həmişə Server B → Server B boş qalır

2. Server uğursuzluğu:
   - Server A çökür → o serverə bağlı bütün istifadəçilər session-larını itirir
   - Failover zamanı bütün o istifadəçilər yenidən login olmalıdır

3. Auto-scaling problemi:
   - Yeni server əlavə etdikdə köhnə session-lar yenə köhnə serverlərə bağlıdır
   - Miqyas faydası azalır

4. Zero-downtime deployment çətinliyi:
   - Server yenilənərkən aktiv session-lar kəsilir
```

**Nəticə:** Sticky session-lar həqiqi horizontal scaling deyil, sadəcə problemi gizlədir.

---

## 3. Mərkəzləşdirilmiş Session Saxlama: Redis və Memcached

Düzgün həll — session məlumatlarını bütün serverlərin daxil ola biləcəyi mərkəzi bir yerdə saxlamaqdır.

### Redis ilə Session Saxlama

*Bu kod PHP session-larını Redis-ə yönləndirən runtime konfiqurasiyasını göstərir:*

```php
<?php
// php.ini və ya runtime konfiqurasiya
ini_set('session.save_handler', 'redis');
ini_set('session.save_path', 'tcp://redis-cluster:6379?auth=secret&database=1');

session_start();
$_SESSION['user_id'] = 42;

// Redis-də saxlanır: SETEX "PHPREDIS_SESSION:sess_abc123" 1440 "user_id|i:42;"
```

*Bu kod Predis kitabxanası ilə Redis-ə session yazan, oxuyan və silən manual session handler-i göstərir:*

```php
<?php
// Predis kitabxanası ilə manual session handler
use Predis\Client;

class RedisSessionHandler implements SessionHandlerInterface
{
    private Client $redis;
    private int $ttl;
    private string $prefix;

    public function __construct(Client $redis, int $ttl = 1440, string $prefix = 'sess:')
    {
        $this->redis = $redis;
        $this->ttl = $ttl;
        $this->prefix = $prefix;
    }

    public function open(string $savePath, string $sessionName): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $sessionId): string|false
    {
        $data = $this->redis->get($this->prefix . $sessionId);
        return $data ?? '';
    }

    public function write(string $sessionId, string $data): bool
    {
        $this->redis->setex($this->prefix . $sessionId, $this->ttl, $data);
        return true;
    }

    public function destroy(string $sessionId): bool
    {
        $this->redis->del($this->prefix . $sessionId);
        return true;
    }

    public function gc(int $maxLifetime): int|false
    {
        // Redis TTL avtomatik idarə edir
        return 0;
    }
}

// İstifadəsi
$redis = new Client(['host' => 'redis-cluster', 'port' => 6379]);
$handler = new RedisSessionHandler($redis, ttl: 3600);
session_set_save_handler($handler, true);
session_start();
```

### Laravel-də Redis Session

*Bu kod Laravel-in session driver-ini Redis-ə yönləndirən konfiqurasiya faylını göstərir:*

```php
// config/session.php
return [
    'driver' => env('SESSION_DRIVER', 'redis'),
    'lifetime' => env('SESSION_LIFETIME', 120),
    'connection' => 'session', // config/database.php-dakı redis connection

    // Session cookie parametrləri
    'cookie' => env('SESSION_COOKIE', 'laravel_session'),
    'secure' => env('SESSION_SECURE_COOKIE', true),
    'http_only' => true,
    'same_site' => 'lax',
];

// config/database.php
'redis' => [
    'session' => [
        'host' => env('REDIS_SESSION_HOST', '127.0.0.1'),
        'password' => env('REDIS_SESSION_PASSWORD'),
        'port' => env('REDIS_SESSION_PORT', 6379),
        'database' => env('REDIS_SESSION_DB', 1), // Ayrı DB
    ],
],
```

### Redis vs Memcached Müqayisəsi

```
Redis:
✓ Persistent storage (RDB/AOF)
✓ Data structures (Hash, List, Set)
✓ Pub/Sub dəstəyi
✓ Cluster modu
✓ Session məlumatlarını itirmirsən restart-da
✗ Daha çox yaddaş istehlakı

Memcached:
✓ Çox sürətli (sadə key-value)
✓ Multi-thread arxitektura
✓ Daha az resurs
✗ Persistent deyil (restart-da məlumat itir)
✗ Data structures yoxdur
✗ Cluster dəstəyi zəifdir

Tövsiyə: Session saxlama üçün Redis istifadə edin
```

---

## 4. JWT ilə Stateless Auth — Struktur və Yoxlama

**JWT (JSON Web Token)** — məlumatları özündə saxlayan, imzalanmış token formatıdır. Server heç bir yerdə saxlamır.

### JWT Strukturu

```
header.payload.signature

eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.
eyJ1c2VyX2lkIjo0Miwicm9sZSI6ImFkbWluIiwiaWF0IjoxNzA0MDY3MjAwLCJleHAiOjE3MDQwNzA4MDB9.
SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c
```

**Header (Base64URL encoded):**
```json
{
  "alg": "HS256",
  "typ": "JWT"
}
```

**Payload (Base64URL encoded):**
```json
{
  "user_id": 42,
  "role": "admin",
  "email": "user@example.com",
  "iat": 1704067200,
  "exp": 1704070800,
  "jti": "unique-token-id-abc123"
}
```

**Signature:**
```
HMACSHA256(
  base64UrlEncode(header) + "." + base64UrlEncode(payload),
  secret_key
)
```

### PHP-də JWT Yoxlama

*Bu kod JWT token yaradan, yoxlayan və middleware-də Bearer token-i emal edən servis sinfini göstərir:*

```php
<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class JwtService
{
    private string $secret;
    private string $algorithm;
    private int $accessTokenTtl;

    public function __construct(
        string $secret,
        string $algorithm = 'HS256',
        int $accessTokenTtl = 900 // 15 dəqiqə
    ) {
        $this->secret = $secret;
        $this->algorithm = $algorithm;
        $this->accessTokenTtl = $accessTokenTtl;
    }

    public function generateAccessToken(int $userId, string $role): string
    {
        $now = time();
        $payload = [
            'iss' => 'myapp.com',           // Issuer
            'sub' => (string) $userId,       // Subject
            'iat' => $now,                   // Issued at
            'exp' => $now + $this->accessTokenTtl,
            'jti' => bin2hex(random_bytes(16)), // JWT ID (unikal)
            'role' => $role,
        ];

        return JWT::encode($payload, $this->secret, $this->algorithm);
    }

    public function validateToken(string $token): object
    {
        // JWT::decode avtomatik exp yoxlayır
        return JWT::decode($token, new Key($this->secret, $this->algorithm));
    }

    public function decodeWithoutVerification(string $token): array
    {
        // Yalnız debug üçün — production-da istifadə etmə!
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('Yanlış JWT formatı');
        }
        return json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    }
}

// Middleware-də istifadə
class JwtAuthMiddleware
{
    public function __construct(private JwtService $jwtService) {}

    public function handle(Request $request, callable $next): Response
    {
        $authHeader = $request->header('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            return response()->json(['error' => 'Token tapılmadı'], 401);
        }

        $token = substr($authHeader, 7);

        try {
            $payload = $this->jwtService->validateToken($token);
            $request->attributes->set('user_id', (int) $payload->sub);
            $request->attributes->set('role', $payload->role);

            return $next($request);

        } catch (ExpiredException $e) {
            return response()->json(['error' => 'Token müddəti bitib'], 401);
        } catch (SignatureInvalidException $e) {
            return response()->json(['error' => 'Yanlış token imzası'], 401);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Yanlış token'], 401);
        }
    }
}
```

---

## 5. JWT Üstünlükləri

```
1. Stateless (Vəziyyətsiz):
   - Server heç nə saxlamır
   - Hər server müstəqil yoxlaya bilər
   - Horizontal scaling problemsiz işləyir

2. Cross-service (Xidmətlərarası):
   - Microservice arxitekturada token bir xidmətdən digərinə ötürülür
   - Hər xidmət DB-yə sorğu vurmadan yoxlayır
   - API Gateway sadəcə tokeni verify edir

3. DB lookup yoxdur:
   - Hər sorğuda istifadəçi məlumatı üçün DB-yə getmir
   - Latency azalır, verimlilik artır
   - DB-nin yükü azalır

4. Özündə məlumat daşıyır:
   - user_id, role, permissions payload-da saxlanır
   - Əlavə sorğu tələb olunmur

5. Standart format:
   - RFC 7519 standartı
   - Bütün dillər/frameworklər dəstəkləyir
   - OAuth 2.0 / OpenID Connect ilə uyğun
```

*Bu kod mikroservis arxitekturasında DB-yə getmədən JWT token-dən user məlumatını oxuyaraq sifariş yaradan servis metodunu göstərir:*

```php
// Microservice ssenarisi — hər xidmət müstəqil yoxlayır
// User Service token yaradır
// Order Service, Payment Service, Notification Service
// hamısı eyni secret ilə verify edir — DB-yə sorğu yoxdur

class OrderService
{
    public function createOrder(string $jwtToken, array $orderData): Order
    {
        // DB-yə getmədən user məlumatı əldə edirik
        $payload = $this->jwtService->validateToken($jwtToken);
        $userId = (int) $payload->sub;
        $userRole = $payload->role;

        // Birbaşa iş məntiqinə keçirik
        return Order::create([
            'user_id' => $userId,
            'items' => $orderData['items'],
        ]);
    }
}
```

---

## 6. JWT Problemləri

### Token Revocation Çətinliyi

*Bu kod JWT token-i Redis blacklist-ə əlavə edərək logout zamanı token-i ləğv etməni göstərir:*

```php
// Problem: JWT stateless-dir, ona görə ləğv etmək çətindir
// İstifadəçi logout olur, amma token hələ də 15 dəqiqə etibarlıdır!

// Ssenario: İstifadəçinin şifrəsi oğurlanır
// Admin hesabı deaktiv edir
// Lakin oğurlanmış token hələ də işləyir!

// Həll 1: Çox qısa TTL (5-15 dəqiqə)
// Həll 2: Redis blacklist (stateless faydası azalır, amma zəruri)
// Həll 3: Token versioning — payload-da version saxla

class TokenBlacklist
{
    public function __construct(private Redis $redis) {}

    public function revoke(string $jti, int $expiresAt): void
    {
        $ttl = $expiresAt - time();
        if ($ttl > 0) {
            // Yalnız token-in qalan ömrü qədər saxla
            $this->redis->setex("blacklist:{$jti}", $ttl, '1');
        }
    }

    public function isRevoked(string $jti): bool
    {
        return (bool) $this->redis->exists("blacklist:{$jti}");
    }
}

// Middleware-ə əlavə et
$payload = $this->jwtService->validateToken($token);
if ($this->blacklist->isRevoked($payload->jti)) {
    return response()->json(['error' => 'Token ləğv edilib'], 401);
}
```

### Token Ölçüsü

```
Session Cookie: ~50 bytes (sadəcə session ID)
JWT Token:      ~300-500 bytes (payload-a görə dəyişir)

Hər HTTP sorğusunda header-da gedir.
Çox claim əlavə etdikdə 1KB-dən çox ola bilər.
Sensitive məlumat saxlama — payload decode edilə bilər!
```

### Secret Rotation (Sirr Dəyişdirmə)

*Bu kod key ID (kid) ilə çoxlu açarı idarə edərək köhnə tokenları geçersiz etmədən secret rotation-ı həyata keçirir:*

```php
// Problem: Secret dəyişdikdə köhnə tokenlar işləmir
// Bütün istifadəçilər logout olunur

// Həll: Key versioning (kid — key ID)
class MultiKeyJwtService
{
    private array $keys = [
        'v1' => 'old-secret-key',
        'v2' => 'new-secret-key', // Yeni açar
    ];
    private string $currentKeyId = 'v2';

    public function generateToken(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT', 'kid' => $this->currentKeyId];
        // Token yeni açarla imzalanır
        return JWT::encode($payload, $this->keys[$this->currentKeyId], 'HS256', $this->currentKeyId);
    }

    public function validateToken(string $token): object
    {
        // Header-dən kid oxu, müvafiq açarı seç
        $header = $this->decodeHeader($token);
        $kid = $header['kid'] ?? $this->currentKeyId;

        if (!isset($this->keys[$kid])) {
            throw new \Exception('Naməlum key ID');
        }

        return JWT::decode($token, new Key($this->keys[$kid], 'HS256'));
    }

    private function decodeHeader(string $token): array
    {
        $parts = explode('.', $token);
        return json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);
    }
}
```

---

## 7. Opaque Token vs Self-Contained Token (JWT)

```
Opaque Token (Reference Token):
┌─────────────────────────────────────────────────────┐
│  Token: "a1b2c3d4e5f6..."  (məna daşımayan string)  │
│  Server: Token → DB/Redis-də məlumat axtarır        │
│  Ölçü: Kiçik (~32 bytes)                            │
│  Revocation: Ani (sadəcə DB-dən sil)                │
│  DB Lookup: Hər sorğuda tələb olunur                │
└─────────────────────────────────────────────────────┘

JWT (Self-Contained Token):
┌─────────────────────────────────────────────────────┐
│  Token: "eyJhbG..." (məlumatı özündə saxlayır)      │
│  Server: İmzanı yoxlayır, DB-yə getmir              │
│  Ölçü: Böyük (~300-500 bytes)                       │
│  Revocation: Çətin (blacklist lazımdır)             │
│  DB Lookup: Yoxdur (stateless)                      │
└─────────────────────────────────────────────────────┘
```

*Bu kod Redis-də saxlanılan, ani ləğv edilə bilən opaque token servisini göstərir:*

```php
// Opaque Token implementasiyası
class OpaqueTokenService
{
    public function __construct(
        private Redis $redis,
        private int $ttl = 3600
    ) {}

    public function createToken(int $userId, array $meta = []): string
    {
        $token = bin2hex(random_bytes(32)); // 64 char hex string

        $data = json_encode([
            'user_id' => $userId,
            'created_at' => time(),
            'meta' => $meta,
        ]);

        $this->redis->setex("token:{$token}", $this->ttl, $data);

        return $token;
    }

    public function validateToken(string $token): ?array
    {
        $data = $this->redis->get("token:{$token}");

        if (!$data) {
            return null; // Token yoxdur və ya müddəti bitib
        }

        // TTL yenilə (sliding expiration)
        $this->redis->expire("token:{$token}", $this->ttl);

        return json_decode($data, true);
    }

    public function revokeToken(string $token): void
    {
        $this->redis->del("token:{$token}"); // Ani ləğv
    }

    public function revokeAllUserTokens(int $userId): void
    {
        // İstifadəçinin bütün tokenlarını sil
        // Bu üçün ayrıca index saxlamaq lazımdır
        $userTokensKey = "user_tokens:{$userId}";
        $tokens = $this->redis->smembers($userTokensKey);

        foreach ($tokens as $token) {
            $this->redis->del("token:{$token}");
        }
        $this->redis->del($userTokensKey);
    }
}

// Nə vaxt hansını seçmək lazımdır?
// Opaque Token: İstifadəçi məlumatları tez-tez dəyişir, ani revocation lazımdır,
//               tək xidmət arxitekturası, məxfilik önəmlidir (payload gizli qalır)
//
// JWT: Microservices, cross-service auth, DB lookup-u azaltmaq, scale tələbi
```

---

## 8. Refresh Token Pattern

Access token qısa müddətli (15 dəqiqə), refresh token uzun müddətli (30 gün) saxlanır.

```
İstifadəçi Login
     ↓
Server → access_token (15 dəq TTL) + refresh_token (30 gün TTL)
     ↓
Client hər sorğuda access_token göndərir
     ↓
Access token bitdikdə → refresh_token ilə yeni access_token alır
     ↓
Refresh token da bitdikdə → yenidən login
```

*Bu kod qısa müddətli JWT access token + uzun müddətli opaque refresh token cütü yaradan auth servisini göstərir:*

```php
<?php

class AuthService
{
    public function __construct(
        private JwtService $jwtService,
        private Redis $redis,
        private UserRepository $userRepo
    ) {}

    public function login(string $email, string $password): array
    {
        $user = $this->userRepo->findByEmail($email);

        if (!$user || !password_verify($password, $user->password_hash)) {
            throw new AuthException('Yanlış email və ya şifrə');
        }

        return $this->generateTokenPair($user);
    }

    public function generateTokenPair(User $user): array
    {
        // Access token: qısa müddətli, JWT
        $accessToken = $this->jwtService->generateAccessToken(
            userId: $user->id,
            role: $user->role
        );

        // Refresh token: uzun müddətli, opaque
        $refreshToken = bin2hex(random_bytes(32));
        $refreshTokenTtl = 30 * 24 * 3600; // 30 gün

        // Refresh token-i Redis-də saxla
        $this->redis->setex(
            "refresh:{$refreshToken}",
            $refreshTokenTtl,
            json_encode([
                'user_id' => $user->id,
                'created_at' => time(),
                'device_id' => request()->header('X-Device-ID'),
            ])
        );

        // İstifadəçinin token siyahısına əlavə et (logout-all üçün)
        $this->redis->sadd("user_refresh_tokens:{$user->id}", $refreshToken);
        $this->redis->expire("user_refresh_tokens:{$user->id}", $refreshTokenTtl);

        return [
            'access_token' => $accessToken,
            'access_token_expires_in' => 900, // 15 dəqiqə saniyə ilə
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
        ];
    }

    public function refreshAccessToken(string $refreshToken): array
    {
        $data = $this->redis->get("refresh:{$refreshToken}");

        if (!$data) {
            throw new AuthException('Refresh token etibarsızdır və ya müddəti bitib');
        }

        $tokenData = json_decode($data, true);
        $user = $this->userRepo->find($tokenData['user_id']);

        if (!$user || !$user->is_active) {
            throw new AuthException('İstifadəçi tapılmadı və ya deaktivdir');
        }

        // Yeni access token ver
        $newAccessToken = $this->jwtService->generateAccessToken($user->id, $user->role);

        return [
            'access_token' => $newAccessToken,
            'access_token_expires_in' => 900,
            'token_type' => 'Bearer',
        ];
    }

    public function logout(string $refreshToken): void
    {
        $data = $this->redis->get("refresh:{$refreshToken}");

        if ($data) {
            $tokenData = json_decode($data, true);
            // İstifadəçinin token siyahısından çıxar
            $this->redis->srem("user_refresh_tokens:{$tokenData['user_id']}", $refreshToken);
        }

        $this->redis->del("refresh:{$refreshToken}");
    }
}
```

---

## 9. Token Refresh Rotation — Reuse Hücumlarını Aşkarlamaq

Refresh token rotation: hər refresh zamanı köhnə token silinir, yeni token verilir. Əgər köhnə token yenidən istifadə edilərsə — hücum aşkarlanır.

*Bu kod hər refresh-də köhnə token-i sılib yeni token verərək token yenidən istifadəsini aşkarlayan rotation mexanizmini göstərir:*

```php
<?php

class SecureRefreshTokenService
{
    public function __construct(
        private Redis $redis,
        private AuthService $authService
    ) {}

    public function rotateRefreshToken(string $oldRefreshToken): array
    {
        $tokenKey = "refresh:{$oldRefreshToken}";
        $data = $this->redis->get($tokenKey);

        if (!$data) {
            // Token tapılmadı — ya müddəti bitib, ya da artıq istifadə edilib
            // Bu hücum əlaməti ola bilər!
            $this->handlePossibleTokenTheft($oldRefreshToken);
            throw new SecurityException('Etibarsız refresh token');
        }

        $tokenData = json_decode($data, true);

        // ATOMIK əməliyyat: köhnəni sil, yenisini yaz
        $pipe = $this->redis->pipeline();

        // Köhnə tokeni sil
        $pipe->del($tokenKey);
        $pipe->srem("user_refresh_tokens:{$tokenData['user_id']}", $oldRefreshToken);

        $pipe->execute();

        // Yeni token cütü yarat
        $user = User::find($tokenData['user_id']);
        return $this->authService->generateTokenPair($user);
    }

    private function handlePossibleTokenTheft(string $token): void
    {
        // Token-in hücum log-unu yoxla
        $theftKey = "theft_attempt:{$token}";
        $attempts = $this->redis->incr($theftKey);
        $this->redis->expire($theftKey, 300); // 5 dəqiqəlik pəncərə

        if ($attempts >= 3) {
            // Çoxsaylı cəhd — bütün istifadəçi tokenlarını ləğv et
            // (Əgər bu token-in sahibini bilirsinizsə)
            Log::alert("Şübhəli refresh token istifadəsi aşkarlandı", [
                'token_prefix' => substr($token, 0, 8) . '...',
                'attempts' => $attempts,
                'ip' => request()->ip(),
            ]);
        }
    }

    /**
     * Token oğurluğu aşkarlandıqda — token ailəsinin hamısını sil
     * Token ailesi: eyni istifadəçinin cihazındakı bütün refresh tokenlar
     */
    public function revokeTokenFamily(int $userId, string $deviceId): void
    {
        $pattern = "refresh:*";
        // Redis SCAN ilə — KEYS istifadə etmə (blocking)
        $cursor = 0;

        do {
            [$cursor, $keys] = $this->redis->scan($cursor, ['match' => $pattern, 'count' => 100]);

            foreach ($keys as $key) {
                $data = $this->redis->get($key);
                if ($data) {
                    $tokenData = json_decode($data, true);
                    if ($tokenData['user_id'] === $userId && $tokenData['device_id'] === $deviceId) {
                        $this->redis->del($key);
                    }
                }
            }
        } while ($cursor != 0);
    }
}
```

---

## 10. Token Revocation Strategiyaları

### Strategiya 1: Redis Blacklist

*Bu kod token-in qalan ömrü qədər Redis blacklist-ə əlavə edən və istifadəçinin bütün token-lərini versiya artırmaqla ləğv edən servis sinfini göstərir:*

```php
<?php

class JwtBlacklistService
{
    public function __construct(private Redis $redis) {}

    /**
     * Token-i ləğv et — yalnız qalan müddət qədər saxla
     * Sürəkli böyüyən blacklist problemi həll olunur
     */
    public function blacklist(string $jti, int $expiresAt): void
    {
        $remainingTtl = $expiresAt - time();

        if ($remainingTtl <= 0) {
            return; // Token artıq bitib, blacklist-ə əlavə etmə
        }

        $this->redis->setex("bl:{$jti}", $remainingTtl, '1');
    }

    public function isBlacklisted(string $jti): bool
    {
        return (bool) $this->redis->exists("bl:{$jti}");
    }

    /**
     * İstifadəçinin bütün aktiv JWT-lərini ləğv et
     * Bu üçün JTI-ləri istifadəçiyə bağlı saxlamaq lazımdır
     */
    public function revokeAllForUser(int $userId): void
    {
        // Yanaşma: user_token_version artır
        // Payload-da bu versiya saxlanır, yoxlama zamanı müqayisə edilir
        $this->redis->incr("user_token_version:{$userId}");
        $this->redis->expire("user_token_version:{$userId}", 30 * 24 * 3600);
    }

    public function getUserTokenVersion(int $userId): int
    {
        return (int) ($this->redis->get("user_token_version:{$userId}") ?? 0);
    }
}

// Token yaratarkən versiyanu daxil et
class EnhancedJwtService
{
    public function generateToken(User $user): string
    {
        $version = $this->blacklist->getUserTokenVersion($user->id);

        $payload = [
            'sub' => (string) $user->id,
            'role' => $user->role,
            'ver' => $version, // Token versiyası
            'iat' => time(),
            'exp' => time() + 900,
            'jti' => bin2hex(random_bytes(16)),
        ];

        return JWT::encode($payload, $this->secret, 'HS256');
    }

    public function validateToken(string $token): object
    {
        $payload = JWT::decode($token, new Key($this->secret, 'HS256'));

        // JTI blacklist yoxla
        if ($this->blacklist->isBlacklisted($payload->jti)) {
            throw new \Exception('Token ləğv edilib');
        }

        // Versiya yoxla
        $currentVersion = $this->blacklist->getUserTokenVersion((int) $payload->sub);
        if ($payload->ver < $currentVersion) {
            throw new \Exception('Token köhnəlmiş (şifrə dəyişdirilmiş)');
        }

        return $payload;
    }
}
```

### Strategiya 2: Qısa TTL

```
Access Token TTL seçimi:
- Çox qısa (1-5 dəq): Çox refresh, server yükü artır
- Orta (15 dəq): Optimal balans — tövsiyə olunur
- Uzun (1 saat): Revocation riski artır
- Çox uzun (24 saat): JWT-nin stateless faydası itirilir

Qayda: Nə qədər sensitive məlumat, bir o qədər qısa TTL
Admin paneli: 5-15 dəqiqə
Normal API: 15-30 dəqiqə
```

---

## 11. Təhlükəsiz Saxlama: httpOnly Cookie vs localStorage

```
localStorage:
✗ JavaScript ilə daxil olmaq olar
✗ XSS hücumuna qarşı həssas
✗ Token oğurlana bilər
✓ CSRF riski yoxdur (əl ilə header göndərilir)
✓ İstifadəsi rahat

httpOnly Cookie:
✓ JavaScript daxil ola bilmir (document.cookie işləmir)
✓ XSS hücumundan qorunur
✓ Avtomatik göndərilir
✗ CSRF hücumuna qarşı həssas → SameSite + CSRF token ilə həll
```

*Bu kod refresh token-i httpOnly SameSite cookie-də saxlayaraq XSS hücumlarından qoruyan login əməliyyatını göstərir:*

```php
// Tövsiyə olunan yanaşma: httpOnly cookie + SameSite
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $tokens = $this->authService->login(
            $request->email,
            $request->password
        );

        $response = response()->json([
            'access_token' => $tokens['access_token'],
            'expires_in' => $tokens['access_token_expires_in'],
        ]);

        // Refresh token httpOnly cookie-də saxla
        $response->cookie(
            name: 'refresh_token',
            value: $tokens['refresh_token'],
            minutes: 43200, // 30 gün
            path: '/api/auth/refresh', // Yalnız bu endpoint-ə göndərilir
            domain: null,
            secure: true,    // Yalnız HTTPS
            httpOnly: true,  // JavaScript daxil ola bilmir
            sameSite: 'Strict' // CSRF qoruması
        );

        return $response;
    }

    public function refresh(Request $request): JsonResponse
    {
        $refreshToken = $request->cookie('refresh_token');

        if (!$refreshToken) {
            return response()->json(['error' => 'Refresh token tapılmadı'], 401);
        }

        $tokens = $this->authService->refreshAccessToken($refreshToken);

        return response()->json([
            'access_token' => $tokens['access_token'],
            'expires_in' => $tokens['access_token_expires_in'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $refreshToken = $request->cookie('refresh_token');

        if ($refreshToken) {
            $this->authService->logout($refreshToken);
        }

        return response()->json(['message' => 'Uğurla çıxış edildi'])
            ->withoutCookie('refresh_token');
    }
}
```

*->withoutCookie('refresh_token'); üçün kod nümunəsi:*
```javascript
// Frontend: Access token memory-də saxla (localStorage yox!)
// Bu SPA üçün tövsiyə olunan yanaşmadır

class TokenManager {
    #accessToken = null; // Private field — closure-da saxla

    setAccessToken(token) {
        this.#accessToken = token;
    }

    getAccessToken() {
        return this.#accessToken;
    }

    clearAccessToken() {
        this.#accessToken = null;
    }
}

// Hər API sorğusunda:
async function apiRequest(url, options = {}) {
    const token = tokenManager.getAccessToken();

    const response = await fetch(url, {
        ...options,
        headers: {
            ...options.headers,
            'Authorization': `Bearer ${token}`,
        },
        credentials: 'include', // Refresh token cookie-si göndərilsin
    });

    if (response.status === 401) {
        // Access token bitib — refresh et
        await refreshAccessToken();
        return apiRequest(url, options); // Yenidən cəhd et
    }

    return response;
}
```

---

## 12. Laravel Sanctum vs Passport — Nə Vaxt Hansı

### Laravel Sanctum

*Laravel Sanctum üçün kod nümunəsi:*
```php
// Sanctum: SPA və sadə API token auth üçün
// composer require laravel/sanctum

// config/sanctum.php
return [
    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost,127.0.0.1')),
    'expiration' => null, // null = sona çatmır, integer = dəqiqə
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
];

// İstifadəçi modeli
class User extends Authenticatable
{
    use HasApiTokens; // Sanctum

    public function createAccessToken(string $deviceName, array $abilities = ['*']): string
    {
        return $this->createToken($deviceName, $abilities)->plainTextToken;
    }
}

// Token yaratmaq
$user = Auth::user();
$token = $user->createToken('iPhone 15', ['orders:read', 'orders:create']);
return ['token' => $token->plainTextToken];

// SPA üçün cookie-based auth
// config/cors.php-da supports_credentials: true
// Axios: axios.defaults.withCredentials = true

// Token abilities yoxlama
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/orders', function (Request $request) {
        if ($request->user()->tokenCan('orders:read')) {
            return Order::where('user_id', $request->user()->id)->get();
        }
        abort(403);
    });
});

// Sanctum nə vaxt seçilir:
// - SPA (same-domain) + Mobile app
// - Sadə token auth
// - Laravel-in özündə token idarəetməsi
// - Tez qurulum lazımdır
// - Microservice tələb yoxdur
```

### Laravel Passport

*Laravel Passport üçün kod nümunəsi:*
```php
// Passport: Tam OAuth 2.0 server implementasiyası
// composer require laravel/passport

// AuthServiceProvider
use Laravel\Passport\Passport;

public function boot(): void
{
    Passport::tokensExpireIn(now()->addMinutes(15));
    Passport::refreshTokensExpireIn(now()->addDays(30));
    Passport::personalAccessTokensExpireIn(now()->addMonths(6));

    // Token scopes
    Passport::tokensCan([
        'read-orders'   => 'Sifarişlərə baxmaq',
        'create-orders' => 'Sifariş yaratmaq',
        'read-profile'  => 'Profil məlumatlarına baxmaq',
        'admin'         => 'Admin əməliyyatları',
    ]);
}

// Authorization Code Grant (third-party apps üçün)
Route::get('/oauth/redirect', function () {
    $query = http_build_query([
        'client_id' => config('services.myapp.client_id'),
        'redirect_uri' => 'https://thirdparty.com/callback',
        'response_type' => 'code',
        'scope' => 'read-orders read-profile',
    ]);
    return redirect('https://myapp.com/oauth/authorize?' . $query);
});

// Client Credentials Grant (server-to-server)
Route::middleware('client')->group(function () {
    Route::get('/api/internal/users', function () {
        // Yalnız trusted server-lər daxil ola bilər
        return User::all();
    });
});

// Passport nə vaxt seçilir:
// - Third-party OAuth provider olmaq istəyirsən
// - "Login with MyApp" funksionallığı
// - Müxtəlif grant types lazımdır (authorization code, client credentials)
// - Xarici developer-lərə API açırsan
// - Tam OAuth 2.0 uyğunluğu tələb olunur
```

### Müqayisə Cədvəli

```
Xüsusiyyət          | Sanctum              | Passport
--------------------|----------------------|---------------------------
Qurulum             | Sadə                 | Mürəkkəb
OAuth 2.0           | Yox                  | Bəli (tam)
Token növü          | Opaque (DB-də)       | JWT + Opaque
SPA dəstəyi         | Əla (cookie-based)   | Var amma mürəkkəb
Third-party auth    | Yox                  | Bəli
Server-to-server    | Limitli              | Client Credentials Grant
Performans          | Yüksək               | Orta (OAuth overhead)
İstifadə halı       | SPA, Mobile, API     | OAuth Provider, Public API
```

---

## 13. Real Ssenari: Çox Cihazlı Giriş və Bütün Cihazlardan Çıxış

*13. Real Ssenari: Çox Cihazlı Giriş və Bütün Cihazlardan Çıxış üçün kod nümunəsi:*
```php
<?php

class MultiDeviceAuthService
{
    public function __construct(
        private Redis $redis,
        private JwtService $jwtService
    ) {}

    /**
     * Cihaza xas token yaratmaq
     */
    public function loginFromDevice(User $user, string $deviceId, string $deviceName): array
    {
        $accessToken = $this->jwtService->generateAccessToken($user->id, $user->role);
        $refreshToken = bin2hex(random_bytes(32));

        $refreshData = [
            'user_id'     => $user->id,
            'device_id'   => $deviceId,
            'device_name' => $deviceName,
            'created_at'  => time(),
            'last_used'   => time(),
            'ip'          => request()->ip(),
            'user_agent'  => request()->userAgent(),
        ];

        $ttl = 30 * 24 * 3600; // 30 gün

        // Refresh token saxla
        $this->redis->setex("refresh:{$refreshToken}", $ttl, json_encode($refreshData));

        // İstifadəçinin cihaz siyahısına əlavə et
        $this->redis->hset(
            "user_devices:{$user->id}",
            $deviceId,
            json_encode([
                'refresh_token' => $refreshToken,
                'device_name'   => $deviceName,
                'last_active'   => time(),
            ])
        );

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $refreshToken,
            'device_id'     => $deviceId,
        ];
    }

    /**
     * İstifadəçinin bütün aktiv cihazlarını gör
     */
    public function getActiveSessions(int $userId): array
    {
        $devices = $this->redis->hgetall("user_devices:{$userId}");
        $sessions = [];

        foreach ($devices as $deviceId => $deviceJson) {
            $device = json_decode($deviceJson, true);

            // Refresh token-in hələ etibarlı olub-olmadığını yoxla
            $isActive = (bool) $this->redis->exists("refresh:{$device['refresh_token']}");

            if ($isActive) {
                $sessions[] = [
                    'device_id'   => $deviceId,
                    'device_name' => $device['device_name'],
                    'last_active' => date('Y-m-d H:i:s', $device['last_active']),
                    'is_current'  => $deviceId === request()->header('X-Device-ID'),
                ];
            } else {
                // Köhnəlmiş qeyd — təmizlə
                $this->redis->hdel("user_devices:{$userId}", $deviceId);
            }
        }

        return $sessions;
    }

    /**
     * Konkret cihazdan çıxış
     */
    public function logoutFromDevice(int $userId, string $deviceId): void
    {
        $deviceJson = $this->redis->hget("user_devices:{$userId}", $deviceId);

        if ($deviceJson) {
            $device = json_decode($deviceJson, true);
            $this->redis->del("refresh:{$device['refresh_token']}");
            $this->redis->hdel("user_devices:{$userId}", $deviceId);
        }
    }

    /**
     * Bütün cihazlardan çıxış (şifrə dəyişdikdə, şübhəli fəaliyyət zamanı)
     */
    public function logoutFromAllDevices(int $userId, ?string $exceptDeviceId = null): int
    {
        $devices = $this->redis->hgetall("user_devices:{$userId}");
        $revokedCount = 0;

        foreach ($devices as $deviceId => $deviceJson) {
            if ($exceptDeviceId && $deviceId === $exceptDeviceId) {
                continue; // Cari cihazı saxla
            }

            $device = json_decode($deviceJson, true);
            $this->redis->del("refresh:{$device['refresh_token']}");
            $this->redis->hdel("user_devices:{$userId}", $deviceId);
            $revokedCount++;
        }

        // JWT-ləri ləğv et: token versiyasını artır
        $this->redis->incr("user_token_version:{$userId}");

        Log::info("İstifadəçi bütün cihazlardan çıxdı", [
            'user_id'       => $userId,
            'revoked_count' => $revokedCount,
            'reason'        => 'manual_logout_all',
        ]);

        return $revokedCount;
    }

    /**
     * Şifrə dəyişdikdə cari cihaz xaricindəkiləri çıxar
     */
    public function onPasswordChange(int $userId, string $currentDeviceId): void
    {
        $this->logoutFromAllDevices($userId, exceptDeviceId: $currentDeviceId);

        // İstifadəçiyə bildiriş göndər
        event(new PasswordChangedEvent($userId, $currentDeviceId));
    }
}

// Controller
class SessionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $sessions = $this->authService->getActiveSessions($request->user()->id);
        return response()->json(['sessions' => $sessions]);
    }

    public function destroyAll(Request $request): JsonResponse
    {
        $count = $this->authService->logoutFromAllDevices(
            $request->user()->id,
            exceptDeviceId: $request->header('X-Device-ID')
        );

        return response()->json([
            'message' => "{$count} cihazdan çıxış edildi",
            'revoked_sessions' => $count,
        ]);
    }

    public function destroy(Request $request, string $deviceId): JsonResponse
    {
        $this->authService->logoutFromDevice($request->user()->id, $deviceId);
        return response()->json(['message' => 'Cihazdan çıxış edildi']);
    }
}
```

---

## Əsas Nəticələr

**Arxitektura Qərarları:**
- Ənənəvi fayl-əsaslı PHP session-ları horizontal scale-ə uyğun deyil — Redis/Memcached lazımdır
- Sticky session-lar həqiqi həll deyil: yük bərabərsizliyi, failover problemi yaradır
- Redis session saxlama üçün optimal seçimdir: persistent, cluster modu, data structures

**JWT vs Opaque Token:**
- JWT: microservices, cross-service auth, DB lookup azaltmaq üçün; lakin revocation çətindir
- Opaque token: ani revocation lazım olduqda, məxfilik əhəmiyyətli olduqda
- Hibrid yanaşma optimal: access token (JWT, qısa) + refresh token (opaque, uzun)

**Təhlükəsizlik:**
- Refresh token rotation — token reuse hücumlarını aşkarlayır
- httpOnly cookie + SameSite=Strict — XSS + CSRF qoruması
- Qısa access token TTL (15 dəq) + Redis blacklist — revocation balansı
- Token versioning — "bütün cihazlardan çıxış" üçün effektiv üsul

**Laravel Seçimi:**
- Sanctum: SPA, mobile, sadə API — sürətli qurulum, kifayətdirici funksionallıq
- Passport: third-party OAuth provider, public API, client credentials grant lazım olduqda

**Çox Cihazlı Ssenari:**
- Hər cihaz üçün ayrı refresh token saxla
- `user_devices:{id}` hash-i ilə aktiv sessiyaları idarə et
- Şifrə dəyişdikdə token versiyasını artır — bütün köhnə JWT-lər avtomatik etibarsız olur

---

## Anti-patternlər

**1. JWT-ni revoke etmək üçün mexanizm qurmamaq**
Uzun TTL-li JWT token yaradıb heç bir blacklist saxlamamaq — istifadəçi çıxış etdikdə, şifrəsini dəyişdikdə və ya hesabı bloklandıqda token hələ də etibarlı qalır. Qısa TTL (15 dəq) + Redis blacklist və ya token versioning kombinasiyasından istifadə et.

**2. Refresh token-i localStorage-da saxlamaq**
Uzun ömürlü refresh token-i JavaScript ilə əlçatan `localStorage`-da saxlamaq — XSS hücumu zamanı token oğurlanır, hesab ele keçirilir. Refresh token-i mütləq `httpOnly; Secure; SameSite=Strict` cookie-də saxla.

**3. Bütün cihazlar üçün tək refresh token istifadə etmək**
İstifadəçinin bütün cihazlarına eyni refresh token-i vermək — bir cihazdan çıxış etdikdə bütün cihazlar təsirlənir, ya da əksinə, bir cihazın tokeni oğurlananda hamısı risk altına girə bilər. Hər cihaz üçün ayrı refresh token yarat, `user_devices` cədvəlində idarə et.

**4. Session data-sını DB-də saxlayıb hər request-də sorğu etmək**
Hər API sorğusunda `SELECT * FROM sessions WHERE token = ?` etmək — yüksək trafik altında DB bottleneck olur, gecikməni artırır. Session məlumatlarını Redis-də cache-lə, oxuma yükünü DB-dən qaldır.

**5. Access token TTL-ni çox uzun tutmaq**
Access token-in ömrünü 24 saat və ya daha uzun qurmaq — token oğurlananda uzun müddət istifadə edilə bilir, revocation mexanizmi olmadan risk böyüyür. Access token TTL-ni 15 dəqiqəyə endirin, yeniləmək üçün refresh token mexanizmi qur.

**6. "Bütün cihazlardan çıxış" üçün bütün tokenləri ayrıca silmək**
İstifadəçinin bütün aktiv token-lərini bir-bir tapıb silməyə çalışmaq — race condition riski var, yeni token əlavə olunarsa keçilə bilər. Token versioning tətbiq et: şifrə dəyişdikdə `token_version`-ı artır, köhnə JWT-lər avtomatik etibarsız olur.

**7. CSRF qorumasını yalnız SameSite cookie-yə güvənərək etmək**
`SameSite=Lax` cookie-si çox hallarda CSRF-i önləyir, amma bütün browser-lərin bütün versiyalarında etibarlı deyil. Əlavə olaraq CSRF double-submit token tətbiq et: server random token yaradır, həm cookie-də, həm formda/header-da göndərilir, server hər ikisini müqayisə edir. Laravel `VerifyCsrfToken` middleware bu mexanizmi avtomatik idarə edir.

**8. JWT payload-a həssas məlumat yazmaq**
JWT-nin payload hissəsi sadəcə Base64URL-encode edilmişdir — şifrəli deyil. İstifadəçi `atob(payload_part)` ilə oxuya bilir. Şifrə hash, tam kredit kartı nömrəsi, tibbi qeydlər JWT-yə yazılmamalıdır. Yalnız session idarəetməsi üçün lazım olan minimum data: `user_id`, `role`, `exp`, `jti`.
