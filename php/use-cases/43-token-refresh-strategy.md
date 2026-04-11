# Token Refresh Strategy

## Problem necə yaranır?

**JWT-nin revoke edilə bilməməsi:** JWT stateless-dir — server hər request-də DB yoxlamır. Token oğurlansa 1 saatlıq TTL müddətinə qədər istifadə edilə bilər, server heç nə edə bilməz (blacklist olmadan).

**Çox uzun TTL:** 24 saatlıq JWT — oğurlananda bütün gün exploit edilə bilər.

**Çox qısa TTL:** 1 dəqiqəlik JWT — istifadəçi hər dəqiqə login olmaq məcburiyyətindədir.

**Refresh token oğurlanması:** Refresh token uzun müddətli (30 gün). Oğurlansa hacker özünü davamlı yeniləyə bilər, legitimate user bilmir.

---

## Token Arxitekturası

```
Access Token:  15 dəqiqə, JWT (stateless, DB-yə getmir)
Refresh Token: 30 gün, random string, DB-də SHA-256 hash kimi saxlanır

Login    → access_token + refresh_token
Request  → Bearer <access_token>
401 alındı → POST /auth/refresh ilə yenilə
```

**Niyə iki token?** Access token qısa TTL → oğurlansa minimal zərər. Refresh token uzun TTL → user hər dəfə login olmur, lakin DB-dədir — revoke edilə bilər.

---

## Token Rotation & Theft Detection

**Token Rotation:** Hər refresh-də refresh token dəyişir. Köhnə token artıq işləmir (single-use). Bu `leakage window`-u minimuma endirir.

**Theft Detection (Token Family):**
```
Hacker token oğurlayır → refresh edir → yeni token alır
Legitimate user köhnə token ilə refresh cəhd edir
Server: "Bu token artıq istifadə edilib!" (used_at ≠ null)
→ THEFT! Bütün family-ni (login session) invalidate et
→ User yenidən login olmaq məcburiyyətindədir
```

---

## İmplementasiya

*Bu kod token rotation, oğurlanma aşkarlaması (family revocation) və bütün cihazlardan çıxış mexanizmini həyata keçirən auth servisini göstərir:*

```php
class AuthService
{
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)->firstOrFail();
        if (!Hash::check($password, $user->password)) {
            throw new AuthenticationException();
        }

        // Yeni family ID: bu login session-un bütün refresh token-ları eyni family
        return $this->issueTokens($user, familyId: Str::uuid());
    }

    public function refresh(string $rawRefreshToken): array
    {
        return DB::transaction(function () use ($rawRefreshToken) {
            $tokenHash = hash('sha256', $rawRefreshToken);

            // lockForUpdate: concurrent refresh race condition önlənir
            $token = RefreshToken::where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (!$token) {
                throw new InvalidTokenException('Token tapılmadı');
            }

            // Artıq istifadə edilmiş token → oğurlanma əlaməti
            if ($token->used_at !== null) {
                RefreshToken::where('family_id', $token->family_id)
                    ->update(['revoked_at' => now(), 'revoke_reason' => 'theft_detected']);

                throw new TokenTheftException('Şübhəli fəaliyyət. Yenidən daxil olun.');
            }

            if ($token->expires_at->isPast()) {
                throw new TokenExpiredException();
            }

            if ($token->revoked_at !== null) {
                throw new InvalidTokenException('Token ləğv edilib');
            }

            // Köhnə token-i istifadə edildi kimi işarələ (single-use)
            $token->update(['used_at' => now()]);

            // Eyni family-də yeni token cütü yarat
            return $this->issueTokens($token->user, familyId: $token->family_id);
        });
    }

    private function issueTokens(User $user, string $familyId): array
    {
        // Access token: JWT, 15 dəqiqə, stateless
        $accessToken = JWT::encode([
            'sub'  => $user->id,
            'role' => $user->role,
            'exp'  => time() + 900,
            'iat'  => time(),
            'jti'  => Str::uuid(), // JWT ID: blacklist üçün unique identifier
        ], config('jwt.secret'), 'HS256');

        // Refresh token: random string, yalnız hash DB-yə yazılır
        $raw  = Str::random(64);
        $hash = hash('sha256', $raw);

        RefreshToken::create([
            'user_id'    => $user->id,
            'family_id'  => $familyId,
            'token_hash' => $hash,
            'expires_at' => now()->addDays(30),
        ]);

        return [
            'access_token'  => $accessToken,
            'refresh_token' => $raw,  // Plain text bir dəfə göstərilir, sonra hash saxlanır
            'expires_in'    => 900,
        ];
    }

    public function logout(string $rawRefreshToken): void
    {
        $hash = hash('sha256', $rawRefreshToken);
        RefreshToken::where('token_hash', $hash)
            ->update(['revoked_at' => now(), 'revoke_reason' => 'logout']);
    }

    // Bütün cihazlardan çıxış — bütün family-ləri revoke et
    public function logoutAllDevices(int $userId): void
    {
        RefreshToken::where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoke_reason' => 'logout_all']);
    }
}
```

---

## Silent Refresh

Frontend-də: access token expire olmadan 60s əvvəl arxa planda refresh et. User heç vaxt 401 görmür. Səhifə yükləndikdə token-in neçə vaxt qaldığını yoxla, timer qur.

```
Token iat: 10:00:00, exp: 10:15:00
Səhifə yükləndikdə: remaining = exp - now = 14 dəq
Timer: 14min - 60s = 13 dəqiqədən sonra silent refresh
```

---

## Cookie vs localStorage

**HttpOnly Cookie:** XSS ilə oxunmur (JS-ə görünmür). CSRF riski — `SameSite=Strict` ilə önlənir. Ən təhlükəsiz seçim.

**localStorage:** XSS həmləsindən token oxunur — kritik risk. Refresh token üçün tövsiyə edilmir.

**Tövsiyə:** Refresh token → `HttpOnly; Secure; SameSite=Strict` cookie. Access token → in-memory (JS variable) — reload-da itirilir, amma silent refresh ilə yenilənir.

---

## JWT Claims Validasiyası

Access token qəbul edərkən yoxlanmalı olan lazımi claims:

*Bu kod JWT claim-lərini (exp, iat, iss) yoxlayan və Redis blacklist ilə ləğv edilmiş tokenləri aşkarlayan validator sinifini göstərir:*

```php
class JwtValidator
{
    public function validate(string $token): array
    {
        $payload = JWT::decode($token, config('jwt.secret'), ['HS256']);

        // exp: expire olub-olmadığı (JWT library avtomatik yoxlayır)
        // iat: token çox köhnəsə rədd et (clock skew tolerance: ±30s)
        if ($payload->iat > time() + 30) {
            throw new InvalidTokenException('Token future date ilə verilmişdir');
        }

        // iss: bizim server tərəfindən verildiyini yoxla
        if ($payload->iss !== config('app.url')) {
            throw new InvalidTokenException('Token fərqli server tərəfindən verilib');
        }

        // Kritik operasiya üçün: Redis blacklist yoxlaması
        if (Cache::has("jwt:blacklist:{$payload->jti}")) {
            throw new InvalidTokenException('Token ləğv edilib');
        }

        return (array) $payload;
    }
}
```

---

## Anti-patterns

- **JWT-ni blacklist olmadan invalidate etməyə çalışmaq:** Stateless-dir, mümkün deyil. Yalnız access token expire olana qədər gözlə (qısa TTL vacibdir).
- **Refresh token-ı localStorage-da saxlamaq:** XSS → token oğurlanması.
- **Token rotation olmadan uzun refresh TTL:** 30 günlük token oğurlansa 30 gün exploit edilir. Rotation ilə: ilk istifadədən sonra theft detect edilir.
- **Family-based revocation olmamaq:** Theft detect edildikdə yalnız bir token-i revoke et → hacker yeni aldığı token ilə davam edir. Family revocation: bütün session invalidate.

---

## İntervyu Sualları

**1. JWT niyə revoke edilə bilmir, necə həll edilir?**
JWT stateless — server DB-yə getmir. Revoke etmək üçün blacklist lazımdır (Redis), lakin bu stateless faydanı azaldır. Həll: çox qısa TTL (15 dəq) — oğurlansa minimal window. Kritik revoke (logout, hack): Redis blacklist `jti` (JWT ID) claim-i ilə.

**2. Token rotation theft detection-ı necə işləyir?**
Hər refresh-də köhnə token `used_at` ilə işarələnir. Oğurlayan köhnə token-i istifadə edərsə server "artıq istifadə edilib" görür. Legitimate user növbəti refresh-də köhnə token göndərsə eyni hal → theft aşkarlanır, bütün family invalidate.

**3. Refresh token DB-də necə saxlanır?**
Plain text heç vaxt! SHA-256 hash saxlanır. Haker DB-ni oğurlasa plain token-ları bilmir (preimage attack mümkün deyil). Verilen raw token: yalnız bir dəfə response-da göstərilir.

**4. "Logout all devices" necə işləyir?**
User-in bütün aktiv refresh token-larını (`family_id` fərqli, lakin eyni `user_id`) `revoked_at` ilə işarələ. Access token-lar hələ qısa TTL müddəti aktiv qalar (stateless) — ya gözlə (15 dəq), ya da Redis blacklist-ə əlavə et. Audit log-da "logout_all" səbəbi saxla.

**5. Concurrent refresh race condition nədir?**
İki tab eyni anda access token expire olur, hər ikisi refresh endpoint-ə eyni refresh token ilə müraciət edir. `lockForUpdate()` olmadan: hər ikisi `used_at=null` görür, hər ikisi yeni token alır — köhnə refresh token iki dəfə istifadə olunur. `lockForUpdate` + transaction: birincisi token alır, ikincisi `used_at != null` görür → yeni token-dən refresh edir (rotation zənciri).

**6. JWT payload-da nə saxlamaq lazımdır, nə yox?**
Saxla: `sub` (user_id), `role`, `exp`, `iat`, `iss`, `jti`. Saxlama: şifrə, email, həssas PII — JWT base64 decode-dur, şifrələnmir. `role` claim-i cache kimi işləyir: hər request-də DB-yə getmədən authorization. Lakin role dəyişsə JWT expire olana qədər köhnə role işləyər — critical deyilsə qəbul edilə bilər.

---

## Anti-patternlər

**1. Access token TTL-ni çox uzun saxlamaq**
JWT access token-in TTL-ni 24 saat və ya daha uzun etmək — token oğurlandıqda istifadəçi 24 saat ərzində exploit edilir, blacklist olmadan revoke etmək mümkün deyil. Access token TTL 15 dəqiqədən çox olmamalıdır.

**2. Refresh token-i access token ilə eyni yerdə saxlamaq**
Həm access, həm refresh token-i `localStorage`-da saxlamaq — XSS hücumu ilə hər ikisi eyni anda oğurlana bilər. Refresh token yalnız `HttpOnly; Secure; SameSite=Strict` cookie-də saxlanmalıdır.

**3. Token rotation zamanı köhnə token-i dərhal invalidate etməmək**
Yeni refresh token verildikdə köhnə token-i `used` kimi işarələməmək — köhnə token hələ də işlək qalır, oğurlanmış token theft detect edilmir. Köhnə token dərhal `used_at` ilə işarələnməli, yenidən istifadə cəhdi theft siqnalı kimi qəbul edilməlidir.

**4. Refresh token-i plain text DB-də saxlamaq**
Raw token string-i `refresh_tokens.token` sütununda saxlamaq — DB breach olduqda bütün aktiv session-lar kompromis olur. Yalnız SHA-256 hash saxlanmalı, orijinal token yalnız bir dəfə response-da göndərilməlidir.

**5. Bütün tokenləri eyni `revoke` endpoint-i ilə silmək**
Token theft aşkarlandıqda yalnız o bir token-i revoke etmək, qalan family-ni saxlamaq — haker artıq yeni token almış ola bilər, family-nin qalan üzvlərini istifadə edir. Theft aşkarlandıqda `family_id` əsasında bütün token ailəsi eyni anda invalidate edilməlidir.

**6. Refresh token endpoint-ini rate limit olmadan buraxmaq**
`/auth/refresh` endpoint-inə request limiti qoymamaq — bruteforce və ya token enumeration hücumları üçün açıq qapı. Refresh endpoint-i IP + user_id əsasında rate limit ilə qorunmalı, ardıcıl uğursuz cəhdlər account lockout tetikləməlidir.

**7. JWT-də həssas data saxlamaq**
`email`, `phone`, istifadəçinin şəxsi məlumatlarını JWT payload-a əlavə etmək — JWT yalnız base64 encode-dur, şifrələnmir; hər kəs decode edib oxuya bilər. Payload-da yalnız non-sensitive identifiers (`user_id`, `role`, system claims) saxlanmalıdır.
