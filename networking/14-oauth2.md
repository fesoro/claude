# OAuth 2.0

## Nədir? (What is it?)

OAuth 2.0 authorization framework-dur (RFC 6749). Istifadecinin ucuncu teref application-a oz resurslarına mehdud erisim vermesine imkan yaradir - shifresini paylasmadan. Meselen, "Google ile daxil ol" duymesine basanda Google sizin email ve adınızı application-a paylasir amma shifrenizi vermirsiniz.

```
Kohne usul (tehlukeli):
  App: "Google shifrenizi verin ki, email-inizi oxuyaq"
  User: "Buyurun: mypassword123"  ← TEHLUKELİ!

OAuth 2.0 (tehlukesiz):
  App: "Google-a yonlendirirem, orada icaze verin"
  User: Google-da login olur, "Email erisimi icaze verirem" basir
  Google: App-a mehdud token verir (yalniz email oxumaq)
  App: Token ile yalniz email oxuyur, shifre bilmir
```

## Necə İşləyir? (How does it work?)

### OAuth 2.0 Roles

```
+-------------------+----------------------------------------+
| Role              | Izah                                   |
+-------------------+----------------------------------------+
| Resource Owner    | Istifadeci (data sahibi)               |
| Client            | Application (data isteyen)             |
| Authorization     | Google, Facebook, GitHub               |
| Server            | (token veren)                          |
| Resource Server   | API server (data saxlayan)             |
+-------------------+----------------------------------------+
```

### Authorization Code Flow (en tehlukesiz, server-side apps)

```
User        Client App       Auth Server       Resource Server
 |              |                 |                    |
 |-- 1. Login -->|                |                    |
 |              |-- 2. Redirect ->|                    |
 |              |  /authorize?    |                    |
 |              |  response_type= |                    |
 |              |  code&          |                    |
 |              |  client_id=X&   |                    |
 |              |  redirect_uri=Y&|                    |
 |              |  scope=email    |                    |
 |              |  &state=random  |                    |
 |              |                 |                    |
 |<--- 3. Login page ------------|                    |
 |--- 4. Login + consent ------->|                    |
 |              |                 |                    |
 |<--- 5. Redirect back ---------|                    |
 |  /callback?code=AUTH_CODE      |                    |
 |  &state=random                 |                    |
 |              |                 |                    |
 |-- 6. Code -->|                 |                    |
 |              |-- 7. Exchange ->|                    |
 |              |  POST /token    |                    |
 |              |  code=AUTH_CODE |                    |
 |              |  client_id=X    |                    |
 |              |  client_secret=S|                    |
 |              |                 |                    |
 |              |<- 8. Tokens ----|                    |
 |              |  access_token   |                    |
 |              |  refresh_token  |                    |
 |              |                 |                    |
 |              |------------- 9. API Request -------->|
 |              |  Authorization: Bearer ACCESS_TOKEN  |
 |              |<------------ 10. Data --------------|
 |<-- 11. Data -|                 |                    |
```

### Authorization Code + PKCE (SPA/Mobile ucun)

```
PKCE (Proof Key for Code Exchange) - client_secret istifade ede bilmeyen
public client-ler ucun (SPA, mobile app).

1. Client random code_verifier yaradir: "dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk"
2. code_challenge = SHA256(code_verifier) -> base64url encode
3. /authorize?... &code_challenge=E9Melhoa...&code_challenge_method=S256
4. Auth server code_challenge-i yadda saxlayir
5. Token exchange zamani: POST /token ... &code_verifier=dBjftJeZ4CVP...
6. Auth server verify edir: SHA256(code_verifier) == saxlanmis code_challenge
```

### Client Credentials Flow (Machine-to-Machine)

```
Service A (Client)          Auth Server           Service B (Resource)
     |                          |                       |
     |-- POST /token ---------->|                       |
     |   grant_type=            |                       |
     |   client_credentials     |                       |
     |   client_id=X            |                       |
     |   client_secret=S        |                       |
     |   scope=read:users       |                       |
     |                          |                       |
     |<-- access_token ---------|                       |
     |                          |                       |
     |------------- API Request + Bearer token -------->|
     |<------------ Response ----------------------------| 
```

### Refresh Token Flow

```
Client                     Auth Server
  |                            |
  |-- API request ----------->| Resource Server
  |<-- 401 Token Expired -----|
  |                            |
  |-- POST /token ------------>|
  |   grant_type=refresh_token |
  |   refresh_token=REFRESH    |
  |   client_id=X              |
  |                            |
  |<-- new access_token -------|
  |    new refresh_token       |
  |                            |
  (kohne refresh_token artiq kecersiz)
```

## Əsas Konseptlər (Key Concepts)

### Scopes

```
Scope = erişim mehdudiyyetleri

# Google Scopes
email                    - Email oxumaq
profile                  - Profil melumatlari
https://www.googleapis.com/auth/calendar.readonly

# GitHub Scopes
read:user               - User profil
repo                    - Repository erisimi
admin:org               - Organization admin

# Custom Scopes
read:users              - Userleri oxu
write:users             - Userleri yaz
delete:users            - Userleri sil
```

### Token Types

```
Access Token:
  - Qisa omurlu (15 deq - 1 saat)
  - API request-lerine elave olunur
  - Bearer token olaraq istifade

Refresh Token:
  - Uzun omurlu (gunler/hefteler)
  - Yeni access token almaq ucun
  - Tehlukesiz saxlanmali (server-side)
  - Bir defe istifade olunur (rotation)

Authorization Code:
  - Cok qisa omurlu (1-10 deqiqe)
  - Yalniz bir defe access token almaq ucun
```

## PHP/Laravel ilə İstifadə

### Laravel Passport (OAuth2 Server)

```bash
composer require laravel/passport
php artisan passport:install
```

```php
// app/Models/User.php
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
}

// config/auth.php
'guards' => [
    'api' => [
        'driver' => 'passport',
        'provider' => 'users',
    ],
],
```

### Passport Scopes

```php
// app/Providers/AuthServiceProvider.php
use Laravel\Passport\Passport;

public function boot(): void
{
    Passport::tokensCan([
        'read-users' => 'Read user information',
        'write-users' => 'Create and update users',
        'delete-users' => 'Delete users',
        'read-orders' => 'Read orders',
        'admin' => 'Full admin access',
    ]);

    Passport::setDefaultScope(['read-users']);
}

// Route-larda scope yoxlamasi
Route::middleware(['auth:api', 'scope:read-users'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
});

Route::middleware(['auth:api', 'scopes:read-users,write-users'])->group(function () {
    Route::post('/users', [UserController::class, 'store']);
});
```

### Laravel Socialite (OAuth2 Client - Social Login)

```bash
composer require laravel/socialite
```

```php
// config/services.php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI'),
],
'github' => [
    'client_id' => env('GITHUB_CLIENT_ID'),
    'client_secret' => env('GITHUB_CLIENT_SECRET'),
    'redirect' => env('GITHUB_REDIRECT_URI'),
],
```

```php
// app/Http/Controllers/SocialAuthController.php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Google-a yonlendir (Step 1)
     */
    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)
            ->scopes(['email', 'profile'])
            ->redirect();
    }

    /**
     * Callback - Google-dan qayitma (Step 2)
     */
    public function callback(string $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect('/login')->withErrors('Authentication failed');
        }

        // User-i tap ve ya yarat
        $user = User::updateOrCreate(
            [
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
            ],
            [
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
                'provider_token' => $socialUser->token,
                'provider_refresh_token' => $socialUser->refreshToken,
            ]
        );

        Auth::login($user, remember: true);

        return redirect('/dashboard');
    }
}

// routes/web.php
Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect']);
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback']);
```

### Machine-to-Machine (Client Credentials)

```php
// Laravel Passport - Client credentials grant
// Client yaratmaq:
// php artisan passport:client --client

// Token almaq (Service A):
use Illuminate\Support\Facades\Http;

$response = Http::asForm()->post('https://auth.example.com/oauth/token', [
    'grant_type' => 'client_credentials',
    'client_id' => config('services.api.client_id'),
    'client_secret' => config('services.api.client_secret'),
    'scope' => 'read-orders',
]);

$token = $response->json('access_token');

// Token ile API call
$orders = Http::withToken($token)
    ->get('https://api.example.com/api/orders')
    ->json();
```

### Token Refresh Service

```php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OAuthTokenManager
{
    /**
     * Access token al (cache-den ve ya refresh et)
     */
    public function getToken(string $service): string
    {
        $cacheKey = "oauth_token:{$service}";

        return Cache::remember($cacheKey, 3500, function () use ($service) {
            $config = config("services.{$service}");

            $response = Http::asForm()->post($config['token_url'], [
                'grant_type' => 'client_credentials',
                'client_id' => $config['client_id'],
                'client_secret' => $config['client_secret'],
                'scope' => $config['scope'],
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException("Failed to get OAuth token for {$service}");
            }

            return $response->json('access_token');
        });
    }

    /**
     * Token-i yenile (refresh token ile)
     */
    public function refreshToken(string $service, string $refreshToken): array
    {
        $config = config("services.{$service}");

        $response = Http::asForm()->post($config['token_url'], [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
        ]);

        return $response->json();
    }
}
```

## Interview Sualları

### 1. OAuth 2.0 nedir? Authentication ve ya Authorization-dir?
**Cavab:** OAuth 2.0 **authorization** framework-dur. Ucuncu teref app-a user-in resurslarina mehdud erisim verir. Authentication deyil - amma OpenID Connect (OIDC) OAuth 2.0 uzerine authentication layer elave edir.

### 2. Authorization Code flow-nu izah edin.
**Cavab:** 1) Client useri auth server-e yonlendirir, 2) User login olub icaze verir, 3) Auth server useri geri yonlendirir authorization code ile, 4) Client backend-de code-u access token ile deyisdirir (client_secret ile), 5) Access token ile API-ya muraciet edir.

### 3. PKCE nedir ve niye lazimdir?
**Cavab:** Proof Key for Code Exchange - SPA/mobile app-lar ucun. Bu app-lar client_secret saxlaya bilmir (public client). PKCE code_verifier/code_challenge ile authorization code-un ogurlunmasinin qarsisini alir. Indi hemise istifade olunmasi tovsiye olunur.

### 4. Access token ve refresh token arasinda ferq nedir?
**Cavab:** Access token qisa omurlu (deqiqeler/saatlar), API request-lerine elave olunur. Refresh token uzun omurlu, yalniz yeni access token almaq ucun istifade olunur, tehlukesiz saxlanmali. Access token expire olanda refresh token ile yenisi alinir.

### 5. Client Credentials flow ne vaxt istifade olunur?
**Cavab:** Machine-to-machine kommunikasiya ucun - user istirak etmir. Service A-nin Service B-nin API-sina erisimi lazim olanda. Meselen, backend cron job-un payment API-a muraciet etmesi.

### 6. State parametri niye vacibdir?
**Cavab:** CSRF hucumunun qarsisini almaq ucun. Client random deger yaradir, auth request-e elave edir, callback-de eyni degeri yoxlayir. Olmazsa attacker oz authorization code-unu victim-in session-una inject ede biler.

### 7. Implicit flow niye deprecated olundu?
**Cavab:** Access token URL fragment-de qaytarilir (#token=...) - bu browser history-de gorunur, referer header ile sizir. PKCE ile Authorization Code flow daha tehlukesizdir. OAuth 2.1-de implicit flow tamam silinib.

### 8. Scope nedir?
**Cavab:** Access token-in erisim mehdudiyyetleridir. Meselen, `email` scope yalniz email oxumaga icaze verir, `repo` scope repository erisimi verir. Minimum lazim olan scope isteyin (principle of least privilege).

## Best Practices

1. **Hemise PKCE istifade edin** - Public ve confidential client-ler ucun
2. **State parametri** - CSRF qorunmasi ucun random deger
3. **Short-lived access tokens** - 15 deqiqe - 1 saat
4. **Refresh token rotation** - Her istifadede yeni refresh token verin
5. **Scope minimalligi** - Yalniz lazim olan scope-lari isteyin
6. **HTTPS hemise** - Token-ler yalniz sifreli kanal ile gonderilmeli
7. **Token storage** - Access token memory-de, refresh token httpOnly cookie-de
8. **Client secret qoruyun** - Backend-de .env-de saxlayin
9. **Token revocation** - Logout zamani token-leri legv edin
10. **Implicit flow istifade etmeyin** - PKCE ile Auth Code flow istifade edin
