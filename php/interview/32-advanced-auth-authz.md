# Advanced Auth & Authorization (Senior)

## Mündəricat
1. Authentication strategiyaları
2. JWT deep
3. OAuth2 / OIDC
4. RBAC / ABAC / ReBAC
5. Multi-factor authentication
6. Sual-cavab seti

---

## 1. Authentication strategiyaları

**S: Session və JWT arasında necə seçim edirsiniz?**
C: 
- Session: same-domain SPA, server-side state OK, instant revoke lazım → Laravel session
- JWT: mobile, microservices, stateless → Sanctum API token, JWT
- Hibrid: short-lived JWT (access) + refresh token rotation

**S: Session fixation attack nədir?**
C: Attacker user-ə öz session ID-sini "qoyur" (URL-də). User login edir → attacker həmin session-da. Həll: `session_regenerate_id()` login sonrası.

**S: Cookie security flag-ları nələrdir?**
C:
- `Secure` — yalnız HTTPS-də göndər
- `HttpOnly` — JS-dən oxunmasın (XSS qarşı)
- `SameSite=Strict/Lax/None` — CSRF qarşı
- `Domain`, `Path` — scope

**S: CSRF necə qarşısı alınır Laravel-də?**
C: `@csrf` Blade directive form-larda. Middleware (`VerifyCsrfToken`) — POST/PUT/DELETE-də token yoxlanılır. Sanctum `EnsureFrontendRequestsAreStateful` ilə.

**S: "Remember me" cookie necə təhlükəsiz olur?**
C: Random token DB-də saxla (hash). Cookie-də plain (OK çünki opaque). Hər istifadədən sonra rotate. Theft detection: eyni token-i 2 yerdə görsən hamısını invalidate et.

---

## 2. JWT deep

**S: JWT 3 hissədən ibarətdir — nələrdir?**
C: Header (alg, typ) . Payload (claims) . Signature. Base64URL ilə encode, dot-separator. 
`eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjMifQ.signature`

**S: HS256 və RS256 fərqi?**
C: 
- HS256 — symmetric (HMAC-SHA256), bir secret. Sadə, amma bütün servislər secret bilməlidir.
- RS256 — asymmetric (RSA). Private key sign, public key verify. Microservice-arası verification üçün ideal.

**S: JWT revocation niyə çətindir?**
C: Stateless — server JWT haqqında məlumat saxlamır. Çıxış yolları: short expiry + refresh, denylist (Redis), token rotation.

**S: Refresh token rotation nədir?**
C: Hər refresh-də yeni access + yeni refresh verilir. Köhnə refresh invalidate olur. Reuse detection — leaked refresh ikinci dəfə işlədilərsə bütün family blok.

**S: JWT-də claim-lər standartdır?**
C: Standard (RFC 7519): `iss` (issuer), `sub` (subject), `aud` (audience), `exp`, `iat`, `nbf`, `jti`. Custom claim-lər prefix vermək tövsiyə olunur.

**S: JWT URL-də göndərmək niyə təhlükəlidir?**
C: Server log-larında qalır. Browser history. Referer header digər sayta sızdıra bilər. Authorization header istifadə et.

---

## 3. OAuth2 / OIDC

**S: OAuth2 grant type-larından hansılar tövsiyə olunur?**
C:
- ✓ Authorization Code + PKCE (mobile, SPA)
- ✓ Client Credentials (server-to-server)
- ✓ Refresh Token
- ✗ Password (deprecated)
- ✗ Implicit (deprecated, PKCE əvəz etdi)

**S: PKCE nədir?**
C: Proof Key for Code Exchange. Mobile/SPA-da client_secret saxlanmır. Code-verifier hash → code-challenge URL-də. Authorization server callback-da match yoxlayır.

**S: OAuth2 və OIDC fərqi?**
C: OAuth2 — authorization (icazə). OIDC — authentication (kimlik) + identity layer. OIDC OAuth2 üzərində. ID Token (JWT) əlavə edir.

**S: ID Token vs Access Token?**
C:
- ID Token (OIDC) — user kim olduğu, audience client app
- Access Token (OAuth2) — API access, audience resource server

**S: Authorization Server, Resource Server, Client roles?**
C: 
- Authorization Server — token verir (Google, Auth0)
- Resource Server — qorunan API
- Client — token istəyən app

---

## 4. RBAC / ABAC / ReBAC

**S: RBAC niyə "role explosion" probleminə düşür?**
C: Hər xüsusi hal yeni role tələb edir. "Admin", "EditorAdmin", "RegionalAdmin", "RegionalEditorAdmin" — combinatorial artım.

**S: ABAC necə həll edir?**
C: Attribute-əsaslı qaydalar. "User department == document department AND user clearance >= doc sensitivity". Çevik, amma audit çətin.

**S: ReBAC (Google Zanzibar) nə vaxt istifadə olunur?**
C: GitHub-vari permissions (folder owner sub-resource-larin də sahibi). Tuple: `<object>#<relation>@<subject>`.

**S: Laravel Gate və Policy fərqi?**
C: Gate — closure-based (sadə hallar). Policy — class-based, model-ə bağlı (CRUD method-ları).

**S: OPA (Open Policy Agent) nə üçündür?**
C: Centralized policy engine. Rego dilində qayda yaz. PHP-dən HTTP API ilə soruş. Microservice-lərarası ortaq policy.

**S: "Deny by default" niyə vacib prinsipdir?**
C: Şübhədə rədd et. Policy match olmazsa → deny. "Allow if no rule matches" → security hole.

---

## 5. Multi-factor authentication

**S: 2FA üçün hansı metodlar var?**
C:
- TOTP (Time-based) — Google Authenticator, Authy
- SMS — phishable, SS7 attack riski
- Email — zəif (email hijack)
- Hardware key (FIDO2/WebAuthn) — ən təhlükəsiz
- Push notification — UX yaxşı

**S: TOTP necə işləyir?**
C: Server və client ortaq secret. HMAC-SHA1(secret, current_30sec_window) → 6 rəqəm. RFC 6238.

**S: WebAuthn nə üstündür?**
C: Public key cryptography. Phishing-resistant (origin yoxlanır). Biometric ilə birləşir. FIDO2 standartı.

**S: Backup codes niyə vacibdir?**
C: User telefonu itirir → 2FA app yoxdur → login mümkünsüz. Backup codes (one-time) lazımdır.

---

## 6. Sual-cavab seti

**S: bcrypt vs argon2 password hashing?**
C: bcrypt — köhnə standart, GPU-resistant qismən. Argon2 (Argon2id) — modern, daha güclü (memory-hard). PHP 7.2+ `PASSWORD_ARGON2ID`.

**S: `password_hash()` vs `hash('bcrypt', ...)` fərqi?**
C: `password_hash()` salt avtomatik generate edir, cost adjustable, format standardlaşıb. `hash()` raw — manual salt etməlisən.

**S: Timing attack nədir, `hash_equals()` necə kömək edir?**
C: `==` müqayisə first-mismatch-də qayıdır → timing fərqindən hash bilmək olur. `hash_equals()` constant-time — hər zaman eyni vaxt.

**S: Password reset token necə təhlükəsiz olur?**
C: Random 32-byte (csprng), DB-də hash saxla, expiry 1 saat, single-use, email ilə link, IP/UA log.

**S: Brute force necə qarşısı alınır?**
C: Rate limit (5 attempt / 15 min), exponential backoff, CAPTCHA, account lockout (DOS riski!), monitoring (anomaly detection).

**S: Account enumeration nədir?**
C: "User exists" / "wrong password" fərqli error → attacker hansı email-lərin qeydiyyatda olduğunu öyrənir. Eyni mesaj qaytar.

**S: Session timeout strategiyası?**
C: 
- Idle timeout (15-30 dəq fəaliyyətsizlik)
- Absolute timeout (8 saat sonra login lazım)
- Sliding window (hər istifadə-də artır)

**S: Cross-device login alert?**
C: Yeni device/IP-dən giriş → email "X şəhərində yeni giriş, sizinizdir?". Suspicious activity flag.

**S: Sosial login (Google, Facebook) implementasiyası?**
C: Laravel Socialite. OAuth2 redirect → callback → user info al → DB-də sync (firstOrCreate).

**S: SSO (Single Sign-On) nədir?**
C: Bir dəfə login → çoxlu app-də giriş. SAML (enterprise), OIDC (modern). Identity Provider (IdP) + Service Provider (SP).

**S: SAML və OIDC fərqi?**
C: SAML — XML, browser POST flow, enterprise. OIDC — JSON/JWT, REST, modern apps.

**S: API key və OAuth token fərqi?**
C: API key — long-lived, sadə (header-də göndər), no expiry. Token — short-lived, refresh ilə rotation, user-bound.

**S: HMAC-signed webhook necə validate edilir?**
C: Sender HMAC-SHA256(payload, secret) hesablayır → header-də göndərir. Receiver eyni hesablayır, `hash_equals()` ilə yoxlayır.

**S: CORS preflight nə vaxt baş verir?**
C: Non-simple request (custom header, PUT/DELETE, application/json). Browser əvvəl OPTIONS göndərir, server `Access-Control-*` header-ləri ilə icazə verir.

**S: Content Security Policy (CSP) niyə vacibdir?**
C: XSS qoruma. Browser-ə "yalnız bu source-lardan script load et" deyir. `Content-Security-Policy: script-src 'self'`.

**S: Subresource Integrity (SRI) nədir?**
C: CDN-dən load edilən script-in hash-i HTML-də. CDN compromise olarsa browser script-i load etməz.

**S: HTTPS-də HSTS nə üçündür?**
C: Browser yadda saxlayır "yalnız HTTPS". `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`.

**S: JWT secret rotate necə edilir?**
C: Yeni key generate. Yeni token-lər yeni key ilə imza. Köhnə token-lər köhnə key ilə verify (key ID claim ilə). Bütün köhnə token expire olduqdan sonra köhnə key sil.

**S: Authorization caching təhlükəlidirmi?**
C: Bəli — role dəyişdi, cache köhnəni göstərir. Short TTL (1-5 dəq) və ya invalidation event.

**S: Privilege escalation prevention?**
C: 
- Object-level authz hər request (not just route)
- IDOR qarşı (Insecure Direct Object Reference) — user X-in resource Y-ni görmə icazəsi yoxlanmalıdır
- Default minimum privilege
