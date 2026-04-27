# OAuth2 və OpenID Connect (Lead)

## İcmal

OAuth2 — üçüncü tərəf xidmətlər vasitəsilə autentifikasiya (Google, GitHub, Facebook) üçün standart protokoldur. Go-da `golang.org/x/oauth2` paketi bu protokolu implement edir. OpenID Connect (OIDC) — OAuth2 üzərinə qurulan identity layer-dir.

## Niyə Vacibdir

- "Google ilə daxil ol" — istifadəçi şifrə xatırlamağa ehtiyac duymur
- Öz auth sistemini qurasan, saxlasan, qorusan lazım deyil
- SSO (Single Sign-On) — korporativ mühitdə bir yerə giriş
- JWT token yetərli olmayan yerlərdə (provider bazlı identifikasiya)

## Əsas Anlayışlar

**Authorization Code Flow (ən güvənli):**
1. Client istifadəçini provider-ə yönləndirir (Google login page)
2. İstifadəçi icazə verir → provider `code` ilə yönləndirir
3. Client `code`-u `access_token`-ə dəyişir (server-server)
4. `access_token` ilə user məlumatlarını alır

**PKCE (Proof Key for Code Exchange):**
- Mobile/SPA üçün code interception hücumunu önləyir
- `code_verifier` → `code_challenge` (SHA256)
- Production-da həmişə PKCE istifadə et

**State parametri:**
- CSRF hücumuna qarşı müdafiə
- Random string → cookie/session-da saxla → callback-də yoxla

**Tokens:**
- `access_token` — qısa müddətli (1 saat), API sorğuları üçün
- `refresh_token` — uzun müddətli, yeni access token almaq üçün
- `id_token` — OIDC, istifadəçi məlumatları (JWT formatında)

## Praktik Baxış

**Nə vaxt OAuth2:**
- Social login (Google, GitHub, Facebook)
- SSO korporativ mühit
- İstifadəçi öz hesabını idarə edir (Google Calendar-ə yazma icazəsi)

**Nə vaxt JWT yetərlidir:**
- Öz istifadəçi bazası var
- Üçüncü tərəf provider lazım deyil

**Common mistakes:**
- `state` parametrini yoxlamamaq — CSRF açığı
- `access_token`-i client-side-da saxlamaq (cookie httpOnly istifadə et)
- `refresh_token` saxlanmasını atlamaq — istifadəçi hər gün daxil olur
- PKCE olmadan authorization code flow — code interception

## Nümunələr

### Nümunə 1: Google OAuth2 — tam axın

```go
package main

import (
    "context"
    "crypto/rand"
    "encoding/base64"
    "encoding/json"
    "fmt"
    "log/slog"
    "net/http"
    "time"

    "golang.org/x/oauth2"
    "golang.org/x/oauth2/google"
)

// go get golang.org/x/oauth2

var googleOAuthConfig = &oauth2.Config{
    ClientID:     "YOUR_GOOGLE_CLIENT_ID",
    ClientSecret: "YOUR_GOOGLE_CLIENT_SECRET",
    RedirectURL:  "http://localhost:8080/auth/google/callback",
    Scopes: []string{
        "openid",
        "https://www.googleapis.com/auth/userinfo.email",
        "https://www.googleapis.com/auth/userinfo.profile",
    },
    Endpoint: google.Endpoint,
}

type GoogleUser struct {
    ID            string `json:"id"`
    Email         string `json:"email"`
    VerifiedEmail bool   `json:"verified_email"`
    Name          string `json:"name"`
    Picture       string `json:"picture"`
}

// 1. İstifadəçini Google-a yönləndir
func handleGoogleLogin(w http.ResponseWriter, r *http.Request) {
    // CSRF qarşı random state
    state := generateState()

    // Cookie-ə saxla — callback-də yoxlanacaq
    http.SetCookie(w, &http.Cookie{
        Name:     "oauth_state",
        Value:    state,
        Path:     "/",
        MaxAge:   600, // 10 dəqiqə
        HttpOnly: true,
        Secure:   true,   // HTTPS
        SameSite: http.SameSiteLaxMode,
    })

    // Google autentifikasiya URL-i
    url := googleOAuthConfig.AuthCodeURL(state,
        oauth2.AccessTypeOffline, // refresh_token almaq üçün
    )

    http.Redirect(w, r, url, http.StatusTemporaryRedirect)
}

// 2. Google callback — code → token → user məlumatları
func handleGoogleCallback(w http.ResponseWriter, r *http.Request) {
    // State yoxla — CSRF qarşısı
    stateCookie, err := r.Cookie("oauth_state")
    if err != nil || r.FormValue("state") != stateCookie.Value {
        http.Error(w, "Etibarsız state", http.StatusBadRequest)
        return
    }

    // Cookie-i sil
    http.SetCookie(w, &http.Cookie{
        Name:   "oauth_state",
        MaxAge: -1,
    })

    // Code → token dəyiş (server-server, təhlükəsiz)
    code := r.FormValue("code")
    token, err := googleOAuthConfig.Exchange(r.Context(), code)
    if err != nil {
        http.Error(w, "Token alınmadı", http.StatusInternalServerError)
        return
    }

    // Google API-dən user məlumatları
    user, err := getGoogleUser(r.Context(), token)
    if err != nil {
        http.Error(w, "User məlumatları alınmadı", http.StatusInternalServerError)
        return
    }

    slog.Info("Google login",
        slog.String("email", user.Email),
        slog.String("name", user.Name),
    )

    // İstifadəçini DB-də tap və ya yarat
    // Öz JWT token-ni generasiya et
    // Session yarat

    fmt.Fprintf(w, "Salam, %s! Email: %s", user.Name, user.Email)
}

func getGoogleUser(ctx context.Context, token *oauth2.Token) (*GoogleUser, error) {
    client := googleOAuthConfig.Client(ctx, token)

    resp, err := client.Get("https://www.googleapis.com/oauth2/v2/userinfo")
    if err != nil {
        return nil, fmt.Errorf("userinfo sorğusu: %w", err)
    }
    defer resp.Body.Close()

    var user GoogleUser
    if err := json.NewDecoder(resp.Body).Decode(&user); err != nil {
        return nil, fmt.Errorf("json decode: %w", err)
    }

    return &user, nil
}

func generateState() string {
    b := make([]byte, 32)
    rand.Read(b)
    return base64.URLEncoding.EncodeToString(b)
}
```

### Nümunə 2: GitHub OAuth2

```go
package main

import (
    "golang.org/x/oauth2"
    "golang.org/x/oauth2/github"
)

var githubOAuthConfig = &oauth2.Config{
    ClientID:     "YOUR_GITHUB_CLIENT_ID",
    ClientSecret: "YOUR_GITHUB_CLIENT_SECRET",
    RedirectURL:  "http://localhost:8080/auth/github/callback",
    Scopes:       []string{"user:email", "read:user"},
    Endpoint:     github.Endpoint,
}

type GitHubUser struct {
    ID        int    `json:"id"`
    Login     string `json:"login"`
    Name      string `json:"name"`
    Email     string `json:"email"`
    AvatarURL string `json:"avatar_url"`
}

func getGitHubUser(ctx context.Context, token *oauth2.Token) (*GitHubUser, error) {
    client := githubOAuthConfig.Client(ctx, token)

    resp, err := client.Get("https://api.github.com/user")
    if err != nil {
        return nil, err
    }
    defer resp.Body.Close()

    var user GitHubUser
    if err := json.NewDecoder(resp.Body).Decode(&user); err != nil {
        return nil, err
    }

    return &user, nil
}
```

### Nümunə 3: Token yeniləmə — refresh token

```go
package main

import (
    "context"
    "time"

    "golang.org/x/oauth2"
)

type TokenStore struct {
    tokens map[string]*oauth2.Token // userID → token
}

func (s *TokenStore) GetValidToken(ctx context.Context, userID string, cfg *oauth2.Config) (*oauth2.Token, error) {
    token, ok := s.tokens[userID]
    if !ok {
        return nil, fmt.Errorf("token yoxdur")
    }

    // Token expire olubsa → refresh et
    if token.Expiry.Before(time.Now().Add(5 * time.Minute)) {
        // TokenSource avtomatik yeniləyir
        ts := cfg.TokenSource(ctx, token)
        newToken, err := ts.Token()
        if err != nil {
            return nil, fmt.Errorf("token yenilənmədi: %w", err)
        }

        // Yeni tokeni saxla
        s.tokens[userID] = newToken
        return newToken, nil
    }

    return token, nil
}
```

### Nümunə 4: OIDC ilə JWT id_token yoxlama

```go
package main

import (
    "context"

    "github.com/coreos/go-oidc/v3/oidc"
    "golang.org/x/oauth2"
)

// go get github.com/coreos/go-oidc/v3

func setupOIDC(ctx context.Context) (*oidc.Provider, *oauth2.Config) {
    provider, err := oidc.NewProvider(ctx, "https://accounts.google.com")
    if err != nil {
        panic(err)
    }

    config := &oauth2.Config{
        ClientID:     "YOUR_CLIENT_ID",
        ClientSecret: "YOUR_CLIENT_SECRET",
        RedirectURL:  "http://localhost:8080/callback",
        Scopes:       []string{oidc.ScopeOpenID, "profile", "email"},
        Endpoint:     provider.Endpoint(),
    }

    return provider, config
}

func verifyIDToken(ctx context.Context, provider *oidc.Provider, clientID, rawIDToken string) (*oidc.IDToken, error) {
    verifier := provider.Verifier(&oidc.Config{ClientID: clientID})
    return verifier.Verify(ctx, rawIDToken)
}

// Callback-də id_token-i yoxla
func handleOIDCCallback(provider *oidc.Provider, config *oauth2.Config) http.HandlerFunc {
    return func(w http.ResponseWriter, r *http.Request) {
        token, err := config.Exchange(r.Context(), r.FormValue("code"))
        if err != nil {
            http.Error(w, err.Error(), http.StatusInternalServerError)
            return
        }

        rawIDToken, ok := token.Extra("id_token").(string)
        if !ok {
            http.Error(w, "id_token yoxdur", http.StatusInternalServerError)
            return
        }

        idToken, err := verifyIDToken(r.Context(), provider, config.ClientID, rawIDToken)
        if err != nil {
            http.Error(w, "id_token yanlışdır", http.StatusUnauthorized)
            return
        }

        var claims struct {
            Email   string `json:"email"`
            Name    string `json:"name"`
        }
        idToken.Claims(&claims)

        fmt.Fprintf(w, "Doğrulandı: %s", claims.Email)
    }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1:**
Google OAuth2 tam axınını implement edin: login button → Google redirect → callback → user məlumatları göstər. State yoxlama əlavə edin.

**Tapşırıq 2:**
GitHub OAuth2 əlavə edin. Eyni user fərqli provider ilə giriş etdikdə (eyni email) necə birləşdiriləcək? `social_accounts` cədvəli planını çıxarın.

**Tapşırıq 3:**
Token refresh strategiyası: access_token expire olmadan 5 dəqiqə əvvəl avtomatik yenilə. Bunu background goroutine ilə et.

## PHP ilə Müqayisə

Laravel Socialite paketi OAuth2 provider-lərini abstrakt edir. Go-da `golang.org/x/oauth2` eyni işi görür — daha az abstraktsiya, daha çox açıqlıq.

```php
// Laravel Socialite
Route::get('/auth/google/redirect', function () {
    return Socialite::driver('google')->redirect();
});

Route::get('/auth/google/callback', function () {
    $user = Socialite::driver('google')->user();
    // $user->email, $user->name, $user->token
});
```

```go
// Go — golang.org/x/oauth2
// Login: googleOAuthConfig.AuthCodeURL(state)
// Callback: googleOAuthConfig.Exchange(ctx, code)
//           getGoogleUser(ctx, token)
```

**Əsas fərqlər:**
- Laravel Socialite: provider-lər hazır konfiqurasiya ilə gəlir; Go-da `ClientID`, `ClientSecret`, `Scopes` əl ilə
- Laravel: `Socialite::driver()->user()` bütün axını idarə edir; Go-da hər addım açıqdır
- State/CSRF yoxlaması Go-da manual; Laravel Socialite daxildir

## Əlaqəli Mövzular

- [65-jwt-and-auth.md](65-jwt-and-auth.md) — JWT əsasları
- [62-security.md](62-security.md) — Security principles
- [35-middleware-and-routing.md](35-middleware-and-routing.md) — Auth middleware
- [28-context.md](28-context.md) — Context propagation
