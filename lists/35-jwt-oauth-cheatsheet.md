## JWT structure

header.payload.signature
eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0In0.<sig>

# Header
{ "alg": "RS256", "typ": "JWT", "kid": "key-id-2024" }

# Payload (registered claims)
iss — issuer (token verən)
sub — subject (token kimin üçün)
aud — audience (string or array; kimin üçün nəzərdə tutulub)
exp — expiry (unix timestamp; məcburi yoxla)
nbf — not before
iat — issued at
jti — JWT ID (idempotency / revocation üçün)

# Payload (common custom claims)
roles, permissions, email, name, tenant_id, ...
Sensitive data saxlama — həmişə encrypt et (JWE) və ya sadəcə ID saxla

## Algorithms

HS256 / HS384 / HS512   — HMAC-SHA; symmetric; secret paylaşılır → secret server-side
RS256 / RS384 / RS512   — RSA-PKCS1v15; asymmetric; public key ile verify
ES256 / ES384 / ES512   — ECDSA (P-256/384/521); asymmetric; RSA-dən kiçik token
EdDSA (Ed25519)          — modern; fastest verify; libsodium friendly
PS256 / PS384 / PS512   — RSA-PSS; RS256-dən daha secure (padding randomized)

# Seçim qaydaları
Single service / same box  → HS256 (sadə, sürətli)
Distributed / microservices → RS256 / ES256 (private key only on auth server)
High throughput verify       → ES256 / EdDSA (smaller sig, faster ops)
FIPS compliance              → PS256

## Signing / verifying (examples)

# PHP — firebase/php-jwt
$token = JWT::encode($payload, $privateKey, 'RS256', 'kid-2024');
$decoded = JWT::decode($token, new Key($publicKey, 'RS256'));

# Go — golang-jwt/jwt
token := jwt.NewWithClaims(jwt.SigningMethodRS256, claims)
signed, _ := token.SignedString(privateKey)

parsed, _ := jwt.ParseWithClaims(signed, &MyClaims{}, func(t *jwt.Token) (any, error) {
    if _, ok := t.Method.(*jwt.SigningMethodRSA); !ok { return nil, ErrBadAlg }
    return publicKey, nil
})

# Java — jjwt
Jwts.builder().subject("u1").expiration(exp).signWith(key, Jwts.SIG.RS256).compact()
Jwts.parser().verifyWith(pub).build().parseSignedClaims(jwt)

## JWKS (JSON Web Key Set)

# GET /.well-known/jwks.json → { "keys": [ { "kty":"RSA","kid":"key-1","n":"...","e":"AQAB" } ] }

# Key rotation workflow
1. Generate new key pair → add to JWKS (old + new present)
2. Start signing new tokens with new kid
3. Old tokens expire naturally (exp)
4. Remove old key from JWKS after max token lifetime passes

# Client-side JWKS caching
Cache with 5-min TTL; on unknown kid → refresh JWKS (once), then verify; prevent cache-poisoning

## JWT attacks & defenses

none algorithm attack    — verify alg != "none"; whitelist allowed algs
alg confusion (RS→HS)   — symmetric verify with public key as secret
                         — fix: explicit alg whitelist server-side
weak HMAC secret         — brute-forceable; use >= 256-bit random secret
missing exp validation   — always validate exp, nbf, iat
missing aud validation   — validate aud matches your service
JWT header injection      — attacker provides own jwks_uri/jku header
                         — fix: never fetch keys from JWT header; use static JWKS
info disclosure in payload — JWT is Base64url, NOT encrypted — use JWE for sensitive data
token sidejacking        — token stolen from localStorage via XSS
                         — fix: HttpOnly cookie + Secure + SameSite=Strict

## OAuth 2.0 grant types

### Authorization Code + PKCE (public clients / SPA / mobile)
1. code_verifier = random 43-128 chars; code_challenge = BASE64URL(SHA256(verifier))
2. Redirect: /authorize?response_type=code&client_id=X&redirect_uri=Y&code_challenge=Z&code_challenge_method=S256&state=CSRF
3. User authenticates → auth server redirects back with ?code=XXX&state=CSRF
4. Verify state; POST /token: grant_type=authorization_code&code=XXX&code_verifier=ORIG
5. Receive: access_token, refresh_token, id_token, expires_in

### Client Credentials (M2M — service-to-service)
POST /token: grant_type=client_credentials&client_id=X&client_secret=Y&scope=api:read
→ access_token only (no refresh token)
Use: microservice calls, CI/CD, background jobs

### Refresh Token flow
POST /token: grant_type=refresh_token&refresh_token=XXX&client_id=X
→ new access_token (+ optionally new refresh_token — rotation)
Refresh token rotation: invalidate old on use (detect theft via reuse)

### Device Authorization (TV / CLI tools)
POST /device_authorization → device_code, user_code, verification_uri, interval
User visits URL + enters user_code; device polls /token until approved

### DEPRECATED / AVOID
Implicit grant              — tokens in URL fragment; replaced by Auth Code + PKCE
Resource Owner Password Credentials — shares user password with app; avoid

## OIDC (OpenID Connect)

Built on top of OAuth 2.0; returns ID Token (JWT) + access token

# Discovery
GET /.well-known/openid-configuration
→ issuer, authorization_endpoint, token_endpoint, userinfo_endpoint, jwks_uri, ...

# ID Token claims
sub, iss, aud, exp, iat, nonce (replay protection), auth_time, acr, amr
email, email_verified, name, picture, locale — via scopes (openid profile email)

# Nonce flow (prevents replay)
1. Generate random nonce; store in session / cookie
2. Include nonce param in /authorize
3. Verify id_token.nonce == stored nonce

# UserInfo endpoint
GET /userinfo  Authorization: Bearer <access_token>
→ { sub, email, name, ... }

## Token storage (frontend)

localStorage / sessionStorage   — XSS risk → attacker reads token; avoid for auth tokens
HttpOnly cookie                  — XSS-safe; CSRF risk → mitigate with SameSite=Strict/Lax
Memory (React state)             — safest; lost on page refresh → use silent refresh
Service Worker                   — can intercept; complex; fingerprinting risk

# Recommended pattern (SPA)
Access token: memory (5-15 min TTL)
Refresh token: HttpOnly Secure SameSite=Strict cookie
Silent refresh: hidden iframe or background fetch to /token before access token expires

## Token introspection & revocation

# RFC 7662 — Introspection
POST /introspect: token=XXX&token_type_hint=access_token
→ { "active": true, "sub": "u1", "exp": 1234567890, ... }
Used by resource servers that can't verify locally (opaque tokens)

# RFC 7009 — Revocation
POST /revoke: token=XXX&token_type_hint=refresh_token
Revoke on logout, password change, suspicious activity

## Scope design

Granular scopes: read:users write:users delete:users
Resource indicator (RFC 8707): resource=https://api.example.com  — audience-bound tokens
Scope hierarchy: admin > editor > viewer

## Access token lifetimes (recommendations)

Access token      5–15 min  (stolen token damage window)
Refresh token     7–30 days (revocable; rotate on use)
ID token          5–15 min  (nonce prevents replay)
M2M token         1 hour    (no refresh token → re-issue via client creds)

## JWT vs Opaque tokens

JWT (self-contained)          — stateless verify, no DB round-trip; can't revoke before exp
                               — use: short-lived access tokens
Opaque tokens                  — random string; must introspect → DB/cache lookup
                               — use: refresh tokens (revocable), long-lived sessions

## Common patterns

# Bearer token header
Authorization: Bearer <access_token>

# DPoP (Demonstrating Proof of Possession — RFC 9449) — binding token to key pair
DPoP: <proof-jwt>    — prevents token replay if stolen
Authorization: DPoP <token>

# Token binding (mTLS client cert bound — RFC 8705)
cnf.x5t#S256 claim = SHA-256(client_cert)

## Best practices

Short access token TTL (≤15 min) — limit blast radius
Always validate: exp, nbf, iss, aud, alg (whitelist)
Use kid in header + JWKS rotation — no downtime key rotation
Never put PII in JWT payload without encryption (JWE)
Use PKCE for all public clients — even if server-side
Refresh token rotation + reuse detection — catch stolen tokens
Bind tokens to client when possible (DPoP / mTLS)
Log jti at auth server — enables revocation audit
Rate-limit /token endpoint — prevent brute force
CORS on /authorize — strict allowed origins list
