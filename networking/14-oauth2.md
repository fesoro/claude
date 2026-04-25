# OAuth 2.0 (Middle)

## İcmal

OAuth 2.0 authorization framework-dür (RFC 6749). İstifadəçinin üçüncü tərəf application-a öz resurslarına məhdud ərisim verməsinə imkan yaradır — şifrəsini paylaşmadan. Məsələn, "Google ilə daxil ol" düyməsinə basdıqda Google sizin email və adınızı application-a paylaşır, amma şifrənizi vermirsiniz.

```
Köhnə üsul (təhlükəli):
  App: "Google şifrənizi verin ki, email-inizi oxuyaq"
  User: "Buyurun: mypassword123"  ← TƏHLÜKƏLİ!

OAuth 2.0 (təhlükəsiz):
  App: "Google-a yönləndirirəm, orada icazə verin"
  User: Google-da login olur, "Email ərisimi icazə verirəm" basır
  Google: App-a məhdud token verir (yalnız email oxumaq)
  App: Token ilə yalnız email oxuyur, şifrə bilmir
```

## Niyə Vacibdir

"Google/GitHub ilə daxil ol" funksionallığı, üçüncü tərəf API-ları ilə inteqrasiya (Stripe, Slack, Dropbox), microservices arası güvənli komunikasiya — bunların hamısı OAuth 2.0-a əsaslanır. Şifrə paylaşımı olmadan məhdud ərisim vermə konsepti müasir autentifikasiya sistemlərinin özəyidir. Laravel Passport (OAuth server) və Socialite (OAuth client) bu prosesi standartlaşdırır.

## Əsas Anlayışlar

### OAuth 2.0 Roles

```
+-------------------+----------------------------------------+
| Role              | İzah                                   |
+-------------------+----------------------------------------+
| Resource Owner    | İstifadəçi (data sahibi)               |
| Client            | Application (data istəyən)             |
| Authorization     | Google, Facebook, GitHub               |
| Server            | (token verən)                          |
| Resource Server   | API server (data saxlayan)             |
+-------------------+----------------------------------------+
```

### Authorization Code Flow (ən təhlükəsiz, server-side apps)

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

### Authorization Code + PKCE (SPA/Mobile üçün)

```
PKCE (Proof Key for Code Exchange) - client_secret istifadə edə bilməyən
public client-lər üçün (SPA, mobile app).

1. Client random code_verifier yaradır: "dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk"
2. code_challenge = SHA256(code_verifier) -> base64url encode
3. /authorize?... &code_challenge=E9Melhoa...&code_challenge_method=S256
4. Auth server code_challenge-i yadda saxlayır
5. Token exchange zamanı: POST /token ... &code_verifier=dBjftJeZ4CVP...
6. Auth server verify edir: SHA256(code_verifier) == saxlanılmış code_challenge
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
  (köhnə refresh_token artıq keçərsizdir)
```

### Scopes

```
Scope = ərisim məhdudiyyətləri

# Google Scopes
email                    - Email oxumaq
profile                  - Profil məlumatları
https://www.googleapis.com/auth/calendar.readonly

# GitHub Scopes
read:user               - User profil
repo                    - Repository ərisimi
admin:org               - Organization admin

# Custom Scopes
read:users              - Userləri oxu
write:users             - Userləri yaz
delete:users            - Userləri sil
```

### Token Types

```
Access Token:
  - Qısa ömürlü (15 dəq - 1 saat)
  - API request-lərinə əlavə olunur
  - Bearer token olaraq istifadə

Refresh Token:
  - Uzun ömürlü (günlər/həftələr)
  - Yeni access token almaq üçün
  - Təhlükəsiz saxlanmalı (server-side)
  - Bir dəfə istifadə olunur (rotation)

Authorization Code:
  - Çox qısa ömürlü (1-10 dəqiqə)
  - Yalnız bir dəfə access token almaq üçün
```

## Praktik Baxış

**Nə vaxt OAuth 2.0 istifadə etmək lazımdır:**
- Social login (Google, GitHub, Facebook)
- Third-party API inteqrasiyası (Stripe, Slack)
- Machine-to-machine autentifikasiya (Client Credentials)
- İstifadəçinin resurslarına məhdud ərisim vermə

**Nə vaxt sadə token auth (Sanctum) seçmək lazımdır:**
- Yalnız öz tətbiqinizin API-sı üçün
- Mobile app + Laravel backend

**Trade-off-lar:**
- OAuth 2.0 kompleksdir — əlavə infrastruktur (auth server) tələb edir
- Token saxlama strategiyası həll edilməlidir (httpOnly cookie vs memory)
- Refresh token rotation hər dəfə DB əməliyyatı deməkdir

**Anti-pattern-lər:**
- Implicit flow istifadə etmək (OAuth 2.1-də silinib, PKCE istifadə edin)
- `state` parametrini unutmaq — CSRF hücumuna açıqdır
- Access token-i localStorage-də saxlamaq — XSS-ə həssasdır
- Lazımsız scope-lar istəmək — minimum lazım olanı istəyin

## Nümunələr

### Ümumi Nümunə

Authorization Code flow — "GitHub ilə daxil ol":

```
1. User "GitHub ilə daxil ol" düyməsinə basır
2. App GitHub-a yönləndirir: GET /oauth/authorize?client_id=...&scope=read:user
3. User GitHub-da login olur, icazə verir
4. GitHub app-a yönləndirir: /callback?code=abc123&state=xyz
5. App backend-də code-u token ilə dəyişdirir: POST /oauth/token
6. GitHub access_token qaytarır
7. App access_token ilə GitHub API-ya müraciət edir: GET /user
```

### Kod Nümunəsi

**Laravel Passport (OAuth2 Server):**

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

**Passport Scopes:**

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

// Route-larda scope yoxlaması
Route::middleware(['auth:api', 'scope:read-users'])->group(function () {
    Route::get('/users', [UserController::class, 'index']);
});

Route::middleware(['auth:api', 'scopes:read-users,write-users'])->group(function () {
    Route::post('/users', [UserController::class, 'store']);
});
```

**Laravel Socialite (OAuth2 Client - Social Login):**

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
     * Google-a yönləndir (Step 1)
     */
    public function redirect(string $provider): RedirectResponse
    {
        return Socialite::driver($provider)
            ->scopes(['email', 'profile'])
            ->redirect();
    }

    /**
     * Callback - Google-dan qayıtma (Step 2)
     */
    public function callback(string $provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect('/login')->withErrors('Authentication failed');
        }

        // User-i tap və ya yarat
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

**Machine-to-Machine (Client Credentials):**

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

// Token ilə API call
$orders = Http::withToken($token)
    ->get('https://api.example.com/api/orders')
    ->json();
```

**Token Refresh Service:**

```php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OAuthTokenManager
{
    /**
     * Access token al (cache-dən və ya refresh et)
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
     * Token-i yenilə (refresh token ilə)
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

## Praktik Tapşırıqlar

1. **Social login:** Laravel Socialite ilə "GitHub ilə daxil ol" funksionallığını implement edin. İstifadəçi artıq varsa mövcud hesaba bağlasın, yoxdursa yenisini yaratsın.

2. **Passport OAuth server:** Laravel Passport quraşdırın. Scope-lar təyin edin (`read-users`, `write-users`). Authorization Code flow-nu test edin.

3. **Client Credentials:** İki microservice arasında machine-to-machine autentifikasiya qurun. `OAuthTokenManager` ilə token-i cache-ləyin (expire-dan 100 saniyə əvvəl yenilənsin).

4. **PKCE implement:** SPA üçün PKCE flow-nu JavaScript-də implement edin. `code_verifier`, `code_challenge` generasiyasını yazın.

5. **State parametri:** Authorization request-ə `state` parametri əlavə edin. Callback-də `state`-i yoxlayın. CSRF simulyasiyası edin — `state` uyğun gəlmədikdə reject edin.

6. **Scope middleware:** `scope:write-users` middleware-ini test edin — yalnız `read-users` olan token ilə `POST /users`-a müraciət cəhdini handle edin.

## Əlaqəli Mövzular

- [JWT - JSON Web Token](15-jwt.md)
- [API Security](17-api-security.md)
- [HTTPS/SSL/TLS](06-https-ssl-tls.md)
- [REST API](08-rest-api.md)
- [Zero Trust](33-zero-trust.md)
