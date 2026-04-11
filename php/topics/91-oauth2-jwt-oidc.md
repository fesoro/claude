# OAuth2, JWT & OIDC

## Mündəricat
1. [OAuth2 Əsasları](#oauth2-əsasları)
2. [Authorization Code Flow (PKCE ilə)](#authorization-code-flow-pkce-ilə)
3. [Client Credentials Flow](#client-credentials-flow)
4. [JWT Strukturu](#jwt-strukturu)
5. [Token Rotation & Refresh Tokens](#token-rotation--refresh-tokens)
6. [OIDC (OpenID Connect)](#oidc-openid-connect)
7. [Təhlükəsizlik Tələləri](#təhlükəsizlik-tələləri)
8. [PHP İmplementasiyası](#php-implementasiyası)
9. [İntervyu Sualları](#intervyu-sualları)

---

## OAuth2 Əsasları

```
OAuth2 — Authorization framework (autentifikasiya deyil!).
"Third-party app-ın resurslarına icazəli giriş"

Rollar:
  Resource Owner   → İstifadəçi (sən)
  Client           → App (GitHub app, mobile app)
  Authorization Server → Login verən server (Google, Auth0)
  Resource Server  → API (data saxlayan server)

Nümunə — "GitHub ilə giriş":
  1. "GitHub ilə giriş" düyməsinə basırsan
  2. GitHub login səhifəsinə yönləndirirsən
  3. GitHub-da login edirsən
  4. GitHub: "Bu app repo-larını oxumaq istəyir, icazə verirsənmi?"
  5. "Bəli" → App authorization code alır
  6. App → GitHub → Access Token dəyişdirilir
  7. App Access Token ilə GitHub API-nı çağırır

OAuth2 flows:
  Authorization Code → Web apps, mobile (ən təhlükəsiz)
  Client Credentials → Service-to-service (user yoxdur)
  Implicit          → Deprecated (PKCE əvəz etdi)
  Device Code       → TV, CLI apps
```

---

## Authorization Code Flow (PKCE ilə)

```
PKCE (Proof Key for Code Exchange) — Authorization Code-u intercept-dən qoruyur.

1. Client: code_verifier = random_string(43-128 chars)
           code_challenge = BASE64URL(SHA256(code_verifier))

2. Client → Auth Server:
   GET /authorize?
     response_type=code
     &client_id=APP_ID
     &redirect_uri=https://app.com/callback
     &scope=openid email
     &state=RANDOM_STATE      ← CSRF qoruması
     &code_challenge=BASE64...
     &code_challenge_method=S256

3. User login edir, icazə verir.

4. Auth Server → Client:
   GET https://app.com/callback?
     code=AUTH_CODE
     &state=RANDOM_STATE

5. Client state yoxlayır (CSRF check!).

6. Client → Auth Server:
   POST /token
   code=AUTH_CODE
   &code_verifier=ORIGINAL_VERIFIER  ← bunu yalnız client bilir
   &grant_type=authorization_code

7. Auth Server code_verifier ilə challenge-i verify edir.

8. Auth Server → Client:
   {
     "access_token": "...",
     "refresh_token": "...",
     "expires_in": 3600,
     "token_type": "Bearer"
   }
```

---

## Client Credentials Flow

```
Service-to-service auth (user yoxdur).

┌───────────────┐  POST /token           ┌───────────────┐
│  Service A    │  grant_type=client_credentials│Auth Server│
│  (client)     │  client_id=A                  │           │
│               │  client_secret=SECRET         │           │
│               │ ─────────────────────────────►│           │
│               │                               │ verify    │
│               │◄── access_token ─────────────│           │
│               │                               └───────────┘
│               │  Authorization: Bearer <token>
│               │ ─────────────────────────────►┌───────────┐
│               │                               │ Service B │
│               │◄── response ─────────────────  └───────────┘
└───────────────┘

Scope ilə məhdudlaşdırma:
  Service A → scope=orders:read
  Service A → scope=payments:write

Nümunə: Cron job, background worker, microservice API çağırışı
```

---

## JWT Strukturu

```
JWT = Header.Payload.Signature
Hər hissə Base64URL encoded.

Header:
  {
    "alg": "RS256",    ← imza alqoritmi
    "typ": "JWT"
  }

Payload (Claims):
  {
    "sub": "user_123",     ← subject (user ID)
    "iss": "auth.app.com", ← issuer
    "aud": "api.app.com",  ← audience
    "exp": 1698765432,     ← expiry timestamp
    "iat": 1698761832,     ← issued at
    "email": "user@app.com",
    "roles": ["admin"]
  }

Signature (RS256):
  RSASSA-PKCS1-v1_5(
    SHA256(base64url(header) + "." + base64url(payload)),
    private_key
  )

İmza yoxlanması:
  Public key ilə → JWT dəyişdirilib-dəyişdirilməyib?

HS256 vs RS256:
  HS256: Simmetrik (eyni key imza + verify) → sadə, amma key paylaşımı lazım
  RS256: Asimmetrik (private key imzalar, public key verify) → daha etibarlı
```

---

## Token Rotation & Refresh Tokens

```
Access Token: qısa müddətli (15 dəq - 1 saat)
Refresh Token: uzun müddətli (1 gün - 30 gün)

Normal axın:
  Client → API (access_token)
  API → 401 (token expired)
  Client → Auth Server (refresh_token) → yeni access_token
  Client → API (yeni access_token)

Refresh Token Rotation:
  Hər refresh-də yeni refresh_token verilir, köhnəsi ləğv edilir.

  Client: refresh_token_v1 → yeni: access_token_v2 + refresh_token_v2
  refresh_token_v1 artıq etibarsızdır!

  Təhlükəsizlik:
  Əgər refresh_token oğurlandısa:
    Oğru: refresh_token_v1 → access_token + refresh_token_v2
    Həqiqi user: refresh_token_v1 → ERROR (artıq istifadə edilib!)
    → Şübhəli aktivlik! Bütün session-ları ləğv et.

Token revocation:
  JWT stateless-dir — expire olana kimi etibarlıdır.
  Vaxtından əvvəl ləğv etmək üçün:
    Denylist (Redis-də saxla) → hər request-də yoxla
    Token versioning (DB-də version, JWT-də version claim)
```

---

## OIDC (OpenID Connect)

```
OIDC = OAuth2 + Identity Layer
OAuth2 authorization verir, OIDC authentication əlavə edir.

OAuth2: "Bu user X resursuna çata bilər"
OIDC:   "Bu user kimdir + çata bilər"

OIDC əlavə edir:
  ID Token (JWT) → user identity məlumatı
  UserInfo endpoint → /userinfo (əlavə claims)
  Standard claims: sub, name, email, picture, ...

ID Token:
  {
    "iss": "https://accounts.google.com",
    "sub": "110248495899929",
    "aud": "YOUR_CLIENT_ID",
    "exp": 1698765432,
    "iat": 1698761832,
    "email": "user@gmail.com",
    "name":  "John Doe",
    "picture": "https://..."
  }

Scopes:
  openid  → ID Token ver (OIDC üçün mütləq)
  profile → name, picture, ...
  email   → email, email_verified
```

---

## Təhlükəsizlik Tələləri

```
1. alg:none attack:
   Header-də "alg": "none" → imza yoxlanmır!
   Həll: Yalnız icazəli alqoritmlərə icazə ver (RS256, ES256)

2. HS256 secret brute-force:
   Zəif secret → offline brute-force mümkündür
   Həll: ≥ 256 bit random secret + RS256 istifadə et

3. Missing audience (aud) check:
   JWT başqa service üçündür, amma bu service qəbul edir
   Həll: aud claim-i mütləq yoxla

4. Expired token-i qəbul etmək:
   exp claim yoxlanmırsa köhnə token işləyir
   Həll: exp claim-i həmişə yoxla, clock skew ≤ 30s

5. Refresh token localStorage-da:
   XSS ilə oğurlana bilər
   Həll: HttpOnly cookie

6. Missing state parameter:
   CSRF attack — OAuth2 callback-i fərqli state ilə qəbul edir
   Həll: state parameter mütləq yoxla
```

---

## PHP İmplementasiyası

```php
<?php
// JWT Validation (firebase/php-jwt)
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

class JwtValidator
{
    public function validate(string $token, string $audience): array
    {
        // JWKS endpoint-dən public keys al (cache edilməli!)
        $jwks = $this->getJwks();

        try {
            $decoded = JWT::decode($token, JWK::parseKeySet($jwks));
        } catch (\Exception $e) {
            throw new InvalidTokenException('Token etibarsızdır: ' . $e->getMessage());
        }

        $claims = (array) $decoded;

        // Audience yoxla
        if (!in_array($audience, (array)($claims['aud'] ?? []))) {
            throw new InvalidTokenException('Token bu service üçün deyil');
        }

        // Issuer yoxla
        if ($claims['iss'] !== config('auth.issuer')) {
            throw new InvalidTokenException('Naməlum issuer');
        }

        return $claims;
    }

    private function getJwks(): array
    {
        return cache()->remember('jwks', 3600, function() {
            return json_decode(
                file_get_contents(config('auth.jwks_url')),
                true
            );
        });
    }
}
```

```php
<?php
// Client Credentials Flow
class ServiceTokenProvider
{
    private ?string $cachedToken = null;
    private ?int $expiresAt = null;

    public function getToken(): string
    {
        // Cache-də token varsa və vaxtı keçməyibsə qaytar
        if ($this->cachedToken && time() < ($this->expiresAt - 60)) {
            return $this->cachedToken;
        }

        $response = $this->httpClient->post(config('auth.token_url'), [
            'form_params' => [
                'grant_type'    => 'client_credentials',
                'client_id'     => config('auth.client_id'),
                'client_secret' => config('auth.client_secret'),
                'scope'         => 'orders:read payments:write',
            ],
        ]);

        $data = json_decode($response->getBody(), true);

        $this->cachedToken = $data['access_token'];
        $this->expiresAt   = time() + $data['expires_in'];

        return $this->cachedToken;
    }
}
```

---

## İntervyu Sualları

- OAuth2 authentication deyil, authorization-dur — fərqi nədir?
- PKCE nədir? Authorization Code Flow-da niyə lazımdır?
- JWT stateless-dir — token-i vaxtından əvvəl ləğv etmək üçün nə etmək lazımdır?
- `alg:none` attack-ı nədir? Necə önlənir?
- Refresh token rotation "token theft detection" necə imkan verir?
- OIDC OAuth2-dən nə əlavə edir? ID Token nədir?
- `aud` claim-i yoxlamamamaq hansı security riskinə yol açar?
