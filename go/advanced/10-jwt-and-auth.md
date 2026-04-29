# JWT və Authentication — golang-jwt, middleware, refresh token, OAuth2 (Lead)

## İcmal

Authentication (kimlik doğrulama) hər web servisinin vacib hissəsidir. Bu mövzuda JWT strukturu, `golang-jwt/jwt/v5` ilə token yaratma/doğrulama, stateless middleware, refresh token pattern, OAuth2 flow (Google ilə giriş), TLS konfiqurasiyası öyrəniləcək.

## Niyə Vacibdir

- Stateless JWT — horizontal scaling üçün idealdır (session store lazım deyil)
- Refresh token pattern — access token qısa ömürlü (15 dəq), yalnız memory-of-loss riski azaldır
- RS256 (asymmetric) — microservice-lər arasında token doğrulama üçün: private key yalnız auth servisdə
- HMAC vs RSA seçimi arxitektural qərardır

## Əsas Anlayışlar

### JWT strukturu

```
Header.Payload.Signature

Header:    {"alg":"HS256","typ":"JWT"} → Base64URL
Payload:   {"sub":"user1","exp":1234} → Base64URL
Signature: HMAC(header+"."+payload, secret)
```

### Standard claims

| Claim | Məna | Nümunə |
|-------|------|--------|
| `sub` | Subject (istifadəçi ID) | `"user_123"` |
| `exp` | Expiration time | Unix timestamp |
| `iat` | Issued at | Unix timestamp |
| `iss` | Issuer | `"auth.myapp.com"` |
| `aud` | Audience | `["api.myapp.com"]` |
| `jti` | JWT ID (unikal) | UUID |

### HS256 vs RS256

```
HS256 (Symmetric):
  - Bir açar — həm imzalamaq, həm doğrulamaq üçün
  - Monolit, bir servis üçün uyğun
  - Secret HƏR YERdə olmalıdır — risk

RS256 (Asymmetric):
  - Private key — yalnız auth servisdə (imzalamaq)
  - Public key — bütün servislərdə (doğrulamaq)
  - Microservice üçün tövsiyə edilən
```

## Praktik Baxış

### Token ömrü

```
Access Token:  15 dəqiqə — API çağırışları üçün
Refresh Token: 7–30 gün  — access token yeniləmək üçün (DB-də saxla)
```

### Trade-off-lar

- JWT stateless — token ləğv etmək çətin (TTL bitənə qədər etibarlı). Həll: blacklist Redis-də
- `jti` claim + Redis set — anında ləğv mümkün, amma distributed lookup lazım
- Cookie vs Authorization header: cookie CSRF riski yaradır, amma httpOnly flag ilə XSS-dən qorunur

### Anti-pattern-lər

```go
// YANLIŞ: tokendə həssas məlumat saxlamaq
claims["password"] = "abc123" // JWT decode oluna bilir!

// YANLIŞ: secret key kodda
var secret = []byte("mysecret") // git-ə commit olunur

// YANLIŞ: alg yoxlaması olmadan
token.Parse(tokenStr, func(t *jwt.Token) (interface{}, error) {
    return secret, nil // "alg":"none" hücumuna açıqdır!
})

// DOĞRU: alg yoxlaması
token.Parse(tokenStr, func(t *jwt.Token) (interface{}, error) {
    if _, ok := t.Method.(*jwt.SigningMethodHMAC); !ok {
        return nil, fmt.Errorf("gözlənilməz alg: %v", t.Header["alg"])
    }
    return secret, nil
})
```

## Nümunələr

### Nümunə 1: JWT Token Service — golang-jwt/jwt/v5

```go
package main

import (
    "errors"
    "fmt"
    "os"
    "time"

    "github.com/golang-jwt/jwt/v5"
    "github.com/google/uuid"
)

// Custom claims
type Claims struct {
    jwt.RegisteredClaims
    UserID string `json:"uid"`
    Email  string `json:"email"`
    Role   string `json:"role"`
}

type TokenPair struct {
    AccessToken  string `json:"access_token"`
    RefreshToken string `json:"refresh_token"`
    ExpiresIn    int    `json:"expires_in"` // saniyə
}

type TokenService struct {
    accessSecret  []byte
    refreshSecret []byte
    accessTTL     time.Duration
    refreshTTL    time.Duration
    issuer        string
}

func NewTokenService() *TokenService {
    return &TokenService{
        accessSecret:  []byte(mustGetenv("JWT_ACCESS_SECRET")),
        refreshSecret: []byte(mustGetenv("JWT_REFRESH_SECRET")),
        accessTTL:     15 * time.Minute,
        refreshTTL:    7 * 24 * time.Hour,
        issuer:        "auth.myapp.com",
    }
}

func mustGetenv(key string) string {
    v := os.Getenv(key)
    if v == "" {
        // Development üçün fallback (production-da panic et)
        return "dev-secret-change-in-production-" + key
    }
    return v
}

// GenerateTokenPair — access + refresh token cütü
func (s *TokenService) GenerateTokenPair(userID, email, role string) (*TokenPair, error) {
    now := time.Now()

    // Access token
    accessClaims := Claims{
        RegisteredClaims: jwt.RegisteredClaims{
            Issuer:    s.issuer,
            Subject:   userID,
            ExpiresAt: jwt.NewNumericDate(now.Add(s.accessTTL)),
            IssuedAt:  jwt.NewNumericDate(now),
            ID:        uuid.New().String(), // jti — unikal ID
        },
        UserID: userID,
        Email:  email,
        Role:   role,
    }

    accessToken := jwt.NewWithClaims(jwt.SigningMethodHS256, accessClaims)
    accessStr, err := accessToken.SignedString(s.accessSecret)
    if err != nil {
        return nil, fmt.Errorf("access token imzalanması: %w", err)
    }

    // Refresh token — minimal claims
    refreshClaims := jwt.RegisteredClaims{
        Issuer:    s.issuer,
        Subject:   userID,
        ExpiresAt: jwt.NewNumericDate(now.Add(s.refreshTTL)),
        IssuedAt:  jwt.NewNumericDate(now),
        ID:        uuid.New().String(),
    }

    refreshToken := jwt.NewWithClaims(jwt.SigningMethodHS256, refreshClaims)
    refreshStr, err := refreshToken.SignedString(s.refreshSecret)
    if err != nil {
        return nil, fmt.Errorf("refresh token imzalanması: %w", err)
    }

    return &TokenPair{
        AccessToken:  accessStr,
        RefreshToken: refreshStr,
        ExpiresIn:    int(s.accessTTL.Seconds()),
    }, nil
}

// ValidateAccessToken — doğrulama + claims qaytarır
func (s *TokenService) ValidateAccessToken(tokenStr string) (*Claims, error) {
    claims := &Claims{}
    token, err := jwt.ParseWithClaims(tokenStr, claims, func(t *jwt.Token) (interface{}, error) {
        // Alqoritm yoxlaması — məcburi!
        if _, ok := t.Method.(*jwt.SigningMethodHMAC); !ok {
            return nil, fmt.Errorf("gözlənilməz imza alqoritmi: %v", t.Header["alg"])
        }
        return s.accessSecret, nil
    })
    if err != nil {
        if errors.Is(err, jwt.ErrTokenExpired) {
            return nil, fmt.Errorf("token vaxtı bitib")
        }
        return nil, fmt.Errorf("token etibarsız: %w", err)
    }
    if !token.Valid {
        return nil, fmt.Errorf("token etibarsızdır")
    }
    return claims, nil
}

// RefreshAccessToken — refresh token ilə yeni access token al
func (s *TokenService) RefreshAccessToken(refreshStr string) (*TokenPair, error) {
    var refreshClaims jwt.RegisteredClaims
    _, err := jwt.ParseWithClaims(refreshStr, &refreshClaims, func(t *jwt.Token) (interface{}, error) {
        if _, ok := t.Method.(*jwt.SigningMethodHMAC); !ok {
            return nil, fmt.Errorf("gözlənilməz alqoritm")
        }
        return s.refreshSecret, nil
    })
    if err != nil {
        return nil, fmt.Errorf("refresh token etibarsız: %w", err)
    }

    // Real layihədə: DB-dən istifadəçi məlumatını al, token blacklist yoxla
    return s.GenerateTokenPair(refreshClaims.Subject, "user@example.com", "user")
}

func main() {
    os.Setenv("JWT_ACCESS_SECRET", "my-access-secret-32-chars-minimum!")
    os.Setenv("JWT_REFRESH_SECRET", "my-refresh-secret-32-chars-minimum!")

    svc := NewTokenService()

    // Token cütü yarat
    pair, err := svc.GenerateTokenPair("user_42", "orkhan@example.com", "admin")
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Printf("Access Token:  %s...\n", pair.AccessToken[:50])
    fmt.Printf("Refresh Token: %s...\n", pair.RefreshToken[:50])

    // Doğrula
    claims, err := svc.ValidateAccessToken(pair.AccessToken)
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Printf("Claims: UserID=%s, Role=%s, Exp=%v\n",
        claims.UserID, claims.Role, claims.ExpiresAt.Time.Format(time.RFC3339))

    // Tampered token
    _, err = svc.ValidateAccessToken(pair.AccessToken + "x")
    fmt.Println("Tampered:", err)
}
```

### Nümunə 2: Auth Middleware — chain

```go
package main

import (
    "context"
    "encoding/json"
    "net/http"
    "strings"
)

type contextKey string

const UserClaimsKey contextKey = "user_claims"

// JWTMiddleware — Authorization header-dən token alır, doğrulayır
func JWTMiddleware(tokenSvc *TokenService) func(http.Handler) http.Handler {
    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            authHeader := r.Header.Get("Authorization")
            if authHeader == "" {
                respondJSON(w, http.StatusUnauthorized, map[string]string{
                    "error": "Authorization header tələb olunur",
                })
                return
            }

            parts := strings.SplitN(authHeader, " ", 2)
            if len(parts) != 2 || !strings.EqualFold(parts[0], "Bearer") {
                respondJSON(w, http.StatusUnauthorized, map[string]string{
                    "error": "Format: Bearer <token>",
                })
                return
            }

            claims, err := tokenSvc.ValidateAccessToken(parts[1])
            if err != nil {
                respondJSON(w, http.StatusUnauthorized, map[string]string{
                    "error": err.Error(),
                })
                return
            }

            // Claims-i context-ə yaz
            ctx := context.WithValue(r.Context(), UserClaimsKey, claims)
            next.ServeHTTP(w, r.WithContext(ctx))
        })
    }
}

// RequireRole — müəyyən rol tələb edən middleware
func RequireRole(roles ...string) func(http.Handler) http.Handler {
    allowed := make(map[string]bool)
    for _, r := range roles {
        allowed[r] = true
    }

    return func(next http.Handler) http.Handler {
        return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
            claims, ok := r.Context().Value(UserClaimsKey).(*Claims)
            if !ok {
                respondJSON(w, http.StatusUnauthorized, map[string]string{
                    "error": "autentifikasiya tələb olunur",
                })
                return
            }

            if !allowed[claims.Role] {
                respondJSON(w, http.StatusForbidden, map[string]string{
                    "error": fmt.Sprintf("bu əməliyyat üçün icazə yoxdur (rol: %s)", claims.Role),
                })
                return
            }

            next.ServeHTTP(w, r)
        })
    }
}

// GetCurrentUser — context-dən claims al
func GetCurrentUser(r *http.Request) (*Claims, bool) {
    claims, ok := r.Context().Value(UserClaimsKey).(*Claims)
    return claims, ok
}

func respondJSON(w http.ResponseWriter, status int, v interface{}) {
    w.Header().Set("Content-Type", "application/json")
    w.WriteHeader(status)
    json.NewEncoder(w).Encode(v)
}

func setupRoutes(tokenSvc *TokenService) http.Handler {
    mux := http.NewServeMux()

    // JWT middleware olmadan
    mux.HandleFunc("POST /auth/login", loginHandler(tokenSvc))
    mux.HandleFunc("POST /auth/refresh", refreshHandler(tokenSvc))

    // JWT tələb edən
    jwtMW := JWTMiddleware(tokenSvc)

    mux.Handle("GET /api/profile", jwtMW(http.HandlerFunc(profileHandler)))
    mux.Handle("GET /api/admin/users", jwtMW(RequireRole("admin")(http.HandlerFunc(adminHandler))))

    return mux
}

func loginHandler(svc *TokenService) http.HandlerFunc {
    return func(w http.ResponseWriter, r *http.Request) {
        // Real app-də: istifadəçi/şifrə yoxla
        email := r.FormValue("email")
        // password := r.FormValue("password")
        // ...bcrypt.CompareHashAndPassword(...)

        pair, err := svc.GenerateTokenPair("user_1", email, "user")
        if err != nil {
            respondJSON(w, http.StatusInternalServerError, map[string]string{"error": err.Error()})
            return
        }
        respondJSON(w, http.StatusOK, pair)
    }
}

func refreshHandler(svc *TokenService) http.HandlerFunc {
    return func(w http.ResponseWriter, r *http.Request) {
        var body struct {
            RefreshToken string `json:"refresh_token"`
        }
        if err := json.NewDecoder(r.Body).Decode(&body); err != nil {
            respondJSON(w, http.StatusBadRequest, map[string]string{"error": "yanlış məlumat"})
            return
        }

        pair, err := svc.RefreshAccessToken(body.RefreshToken)
        if err != nil {
            respondJSON(w, http.StatusUnauthorized, map[string]string{"error": err.Error()})
            return
        }
        respondJSON(w, http.StatusOK, pair)
    }
}

func profileHandler(w http.ResponseWriter, r *http.Request) {
    claims, _ := GetCurrentUser(r)
    respondJSON(w, http.StatusOK, map[string]interface{}{
        "user_id": claims.UserID,
        "email":   claims.Email,
        "role":    claims.Role,
    })
}

func adminHandler(w http.ResponseWriter, r *http.Request) {
    respondJSON(w, http.StatusOK, map[string]string{"data": "admin paneli"})
}
```

### Nümunə 3: RS256 ilə microservice auth

```go
package main

import (
    "crypto/rand"
    "crypto/rsa"
    "fmt"
    "time"

    "github.com/golang-jwt/jwt/v5"
)

type RSATokenService struct {
    privateKey *rsa.PrivateKey
    publicKey  *rsa.PublicKey
    ttl        time.Duration
}

func NewRSATokenService(bits int, ttl time.Duration) (*RSATokenService, error) {
    privateKey, err := rsa.GenerateKey(rand.Reader, bits)
    if err != nil {
        return nil, fmt.Errorf("RSA açar yaratma: %w", err)
    }
    return &RSATokenService{
        privateKey: privateKey,
        publicKey:  &privateKey.PublicKey,
        ttl:        ttl,
    }, nil
}

// Sign — private key ilə imzala (yalnız auth servis bilir)
func (s *RSATokenService) Sign(userID, role string) (string, error) {
    claims := jwt.MapClaims{
        "sub":  userID,
        "role": role,
        "exp":  jwt.NewNumericDate(time.Now().Add(s.ttl)),
        "iat":  jwt.NewNumericDate(time.Now()),
    }
    token := jwt.NewWithClaims(jwt.SigningMethodRS256, claims)
    return token.SignedString(s.privateKey)
}

// Verify — public key ilə doğrula (bütün servislərdə mümkün)
func (s *RSATokenService) Verify(tokenStr string) (jwt.MapClaims, error) {
    token, err := jwt.Parse(tokenStr, func(t *jwt.Token) (interface{}, error) {
        if _, ok := t.Method.(*jwt.SigningMethodRSA); !ok {
            return nil, fmt.Errorf("gözlənilməz alqoritm: %v", t.Header["alg"])
        }
        return s.publicKey, nil // public key kifayətdir!
    })
    if err != nil {
        return nil, err
    }
    claims, ok := token.Claims.(jwt.MapClaims)
    if !ok || !token.Valid {
        return nil, fmt.Errorf("etibarsız token")
    }
    return claims, nil
}

func main() {
    svc, err := NewRSATokenService(2048, 15*time.Minute)
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }

    tokenStr, err := svc.Sign("user_99", "editor")
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Println("RS256 token yaradıldı")

    claims, err := svc.Verify(tokenStr)
    if err != nil {
        fmt.Println("Xəta:", err)
        return
    }
    fmt.Printf("Doğrulandı: sub=%s, role=%s\n", claims["sub"], claims["role"])
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1 — Blacklist:**
Logout endpoint-i yazın. Refresh token-i Redis-də saxlayın. Logout zamanı token-i blacklist-ə əlavə edin. `ValidateAccessToken` blacklist yoxlasın.

**Tapşırıq 2 — OAuth2 Google:**
`golang.org/x/oauth2/google` ilə Google login əlavə edin. `/auth/google` → Google → `/auth/callback` → JWT pair.

**Tapşırıq 3 — Token rotation:**
Refresh token işləndikdə yeni refresh token da yarat (rotation). Köhnə refresh token-i invalidate et. Bu strategiyanın faydası nədir?

**Tapşırıq 4 — Cookie-based auth:**
`Authorization: Bearer` əvəzinə `httpOnly` + `Secure` cookie istifadə edin. CSRF qorunmasını əlavə edin.

**Tapşırıq 5 — RS256 JWKS endpoint:**
`/auth/.well-known/jwks.json` endpoint-i — public key-i JWKS formatında qaytarsın. Digər servisler buradan public key alsın.

## PHP ilə Müqayisə

Laravel Sanctum/Passport-dan fərqli olaraq Go-da hər şeyi özünüz qurursunuz — bu daha çox nəzarət, amma daha çox məsuliyyət deməkdir. Laravel Sanctum token-ləri DB-də saxlayır (`personal_access_tokens` cədvəli) — Go-da bunu özünüz Redis-də implementasiya edirsiniz. Laravel Passport OAuth2 server kimi işləyir; Go-da `golang.org/x/oauth2` client tərəfini, server tərəfini isə `ory/fosite` kimi kitabxanalar ilə qurursunuz. RS256 microservice ssenarisi Laravel-in çox-servis mühitində Passport-la mürəkkəb olur — Go-da bu daha asan idarə olunur.

## Əlaqəli Mövzular

- [62-security](07-security.md) — bcrypt, HMAC, təhlükəsizlik
- [35-middleware-and-routing](../backend/03-middleware-and-routing.md) — HTTP middleware
- [01-http-server](../backend/01-http-server.md) — TLS server
- [63-caching](08-caching.md) — Redis token blacklist
- [26-microservices](26-microservices.md) — microservice auth
