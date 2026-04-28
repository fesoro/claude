# OAuth2 Social Login — Google, GitHub (Middle)

## Problem Təsviri

İstifadəçilər yeni application-a qeydiyyatdan keçərkən tez-tez **registration friction** ilə üzləşirlər: yeni username, yeni parol, email doğrulaması — bunların hamısı qeydiyyat prosesini ağırlaşdırır. Araşdırmalar göstərir ki, **73% istifadəçi eyni parolu bir neçə saytda istifadə edir**. Email doğrulaması isə ayrıca bir addımdır — istifadəçinin e-poçt qutusuna keçib link basması lazımdır.

```
Ənənəvi qeydiyyat:
User → Forma doldurur (ad, email, parol, parol təkrar)
     → "E-poçtunuzu yoxlayın" səhifəsi
     → E-poçt qutusunu açır
     → Doğrulama linkini tapır (bəlkə spam-dadır)
     → Linki basır
     → Geri dönür
     → Daxil olur
     [~5-8 addım, 3-5 dəqiqə]

Social login:
User → "Google ilə daxil ol" basır
     → Google-da razılaşır
     → Application-a geri yönləndirilir
     → Daxil olub!
     [2 addım, 10 saniyə]
```

**Social login niyə lazımdır:**
- Qeydiyyat fraksiyasını kəskin azaldır → conversion rate artır
- E-poçt verified olduğunu provider zəmanət verir (Google, GitHub)
- İstifadəçi ayrıca parol yadda saxlamır → daha az "forgot password" sorğusu
- Provider-dən profil şəkli, ad, email avtomatik alınır

---

## OAuth2 Authorization Code Flow

OAuth2-nin bir neçə növü var. **Authorization Code Flow** — server-side application-lar üçün ən güvənli variant. Access token client-in brauzerindən keçmir; yalnız server görür.

```
1. User "Login with Google" basır
   │
   ▼
2. Server → Google OAuth URL-ə redirect (state parameter ilə)
   https://accounts.google.com/o/oauth2/auth
     ?client_id=YOUR_CLIENT_ID
     &redirect_uri=https://yourapp.com/auth/google/callback
     &response_type=code
     &scope=openid email profile
     &state=RANDOM_CSRF_TOKEN
   │
   ▼
3. Google → İstifadəçidən icazə istəyir
   "YourApp sizin ad və e-poçtunuza giriş istəyir"
   │
   ▼
4. İstifadəçi razılaşır → Google redirect edir:
   https://yourapp.com/auth/google/callback?code=4/ABC123&state=RANDOM_CSRF_TOKEN
   │
   ▼
5. Server: state yoxlayır (CSRF qoruması)
   Server: code → Google-a göndərir → access_token alır
   POST https://oauth2.googleapis.com/token
     {code, client_id, client_secret, redirect_uri, grant_type}
   │
   ▼
6. Server: access_token ilə user profilini çəkir
   GET https://www.googleapis.com/oauth2/v3/userinfo
   Header: Authorization: Bearer ACCESS_TOKEN
   ← {id, email, name, picture, email_verified}
   │
   ▼
7. Server: email ilə DB-dən user tapar (və ya yenisini yaradar)
   → Session yaradır / JWT verir
   → User dashboard-a redirect olur
```

**Niyə Implicit Flow istifadə etmirəm?**
Implicit Flow-da access token birbaşa URL fragment-ində (`#token=...`) brauzerə gəlir. Bu, token-i browser history-yə, log-lara, referrer header-ə düşürmə riski yaradır. Authorization Code Flow-da isə yalnız qısa ömürlü `code` URL-ə gəlir; real token server-server əməliyyatı ilə alınır.

---

## Laravel Socialite — Tam Implementation

### Quraşdırma

```bash
composer require laravel/socialite
```

**`config/services.php`** — provider konfiqurasiyası:

```php
// config/services.php
return [
    // ... digər servis konfiqurasiyaları

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

    'github' => [
        'client_id'     => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect'      => env('GITHUB_REDIRECT_URI'),
    ],
];
```

**`.env`** — environment dəyişənləri:

```dotenv
GOOGLE_CLIENT_ID=123456789-abc.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxxxxxxxxxx
GOOGLE_REDIRECT_URI=https://yourapp.com/auth/google/callback

GITHUB_CLIENT_ID=Iv1.xxxxxxxxxxxx
GITHUB_CLIENT_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
GITHUB_REDIRECT_URI=https://yourapp.com/auth/github/callback
```

> Google Console və GitHub OAuth Apps-da bu `redirect_uri`-lər mütləq qeydiyyatdan keçirilməlidir. Qeydiyyatsız URI-yə redirect etmək mümkün deyil.

---

### Migration — social_accounts cədvəli

Bir user bir neçə provider-ə (Google + GitHub) bağlana bilər. `users` cədvəlinə əlavə sütun əlavə etmək əvəzinə ayrıca `social_accounts` cədvəli daha elastikdir:

```php
// database/migrations/2024_01_01_create_social_accounts_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider');           // 'google', 'github'
            $table->string('provider_id');        // Provider-dəki unikal ID
            $table->string('access_token')->nullable();
            $table->string('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamps();

            // Eyni provider-dəki eyni ID-nin dublikatını önlə
            $table->unique(['provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
```

**`users`** cədvəlinə əlavə:

```php
// Social login ilə yaradılan user-lər üçün parol nullable ola bilər
$table->string('password')->nullable()->change();
$table->string('avatar')->nullable();
```

---

### Model

```php
// app/Models/SocialAccount.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAccount extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
    ];

    // access_token-i məlumat bazasında açıq saxlamayın —
    // real production-da encrypted column istifadə edin (Laravel Encryption).
    protected $hidden = ['access_token', 'refresh_token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

```php
// app/Models/User.php (əlavələr)
public function socialAccounts(): HasMany
{
    return $this->hasMany(SocialAccount::class);
}

public function hasSocialAccount(string $provider): bool
{
    return $this->socialAccounts()->where('provider', $provider)->exists();
}
```

---

### Controller

```php
// app/Http/Controllers/SocialAuthController.php
namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class SocialAuthController extends Controller
{
    /** Yalnız bu provider-lərə icazə var */
    private const ALLOWED_PROVIDERS = ['google', 'github'];

    /**
     * İstifadəçini OAuth provider səhifəsinə yönləndir.
     */
    public function redirect(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::ALLOWED_PROVIDERS), 404);

        return Socialite::driver($provider)
            ->scopes($this->scopesFor($provider))
            ->redirect();
    }

    /**
     * Provider-dən geri dönən callback-i işlə.
     */
    public function callback(string $provider): RedirectResponse
    {
        abort_unless(in_array($provider, self::ALLOWED_PROVIDERS), 404);

        // 1. Provider-dən user məlumatlarını al
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (InvalidStateException $e) {
            // State mismatch — CSRF cəhdi və ya köhnəlmiş sessiya
            Log::warning('OAuth state mismatch', [
                'provider' => $provider,
                'ip' => request()->ip(),
            ]);
            return redirect()->route('login')
                ->withErrors(['social' => 'Giriş prosesi etibarsız oldu. Yenidən cəhd edin.']);
        } catch (\Throwable $e) {
            // İstifadəçi icazəni ləğv etdi və ya provider xətası
            Log::error('OAuth callback error', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);
            return redirect()->route('login')
                ->withErrors(['social' => 'Giriş uğursuz oldu. Zəhmət olmasa yenidən cəhd edin.']);
        }

        // 2. E-poçt doğrulamasını yoxla
        if (empty($socialUser->getEmail())) {
            // GitHub bəzən email qaytarmır — email gizlədilibsə
            return redirect()->route('social.complete-registration', [
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'name' => $socialUser->getName(),
                'avatar' => $socialUser->getAvatar(),
                'token' => encrypt($socialUser->token),
            ]);
        }

        if ($provider === 'google' && !($socialUser->user['email_verified'] ?? false)) {
            return redirect()->route('login')
                ->withErrors(['social' => 'Google hesabınızın e-poçtu doğrulanmayıb.']);
        }

        // 3. User tap və ya yarat, sonra daxil et
        $user = $this->findOrCreateUser($provider, $socialUser);

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Mövcud user-i tap və ya yeni yarat.
     */
    private function findOrCreateUser(string $provider, $socialUser): User
    {
        return DB::transaction(function () use ($provider, $socialUser) {

            // Əvvəlcə bu provider + provider_id kombinasiyasına bax
            $socialAccount = SocialAccount::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->with('user')
                ->first();

            if ($socialAccount) {
                // Mövcud social account — token-i yenilə
                $socialAccount->update([
                    'access_token'    => $socialUser->token,
                    'refresh_token'   => $socialUser->refreshToken,
                    'token_expires_at' => $socialUser->expiresIn
                        ? now()->addSeconds($socialUser->expiresIn)
                        : null,
                ]);
                return $socialAccount->user;
            }

            // Social account yoxdur — eyni email ilə user varmı?
            $user = User::where('email', $socialUser->getEmail())->first();

            if ($user) {
                // Eyni email ilə mövcud user var — social account-u linkla
                $this->linkSocialAccount($user, $provider, $socialUser);
                return $user;
            }

            // Yeni user yarat
            $user = User::create([
                'name'              => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                'email'             => $socialUser->getEmail(),
                'password'          => null, // Social login — parol yoxdur
                'email_verified_at' => now(), // Provider artıq doğruladı
                'avatar'            => $socialUser->getAvatar(),
            ]);

            $this->linkSocialAccount($user, $provider, $socialUser);

            Log::info('New user registered via social login', [
                'user_id'  => $user->id,
                'provider' => $provider,
            ]);

            return $user;
        });
    }

    /**
     * User ilə social account-u əlaqələndir.
     */
    private function linkSocialAccount(User $user, string $provider, $socialUser): void
    {
        $user->socialAccounts()->create([
            'provider'        => $provider,
            'provider_id'     => $socialUser->getId(),
            'access_token'    => $socialUser->token,
            'refresh_token'   => $socialUser->refreshToken,
            'token_expires_at' => $socialUser->expiresIn
                ? now()->addSeconds($socialUser->expiresIn)
                : null,
        ]);
    }

    /**
     * Hər provider üçün lazım olan scope-lar.
     */
    private function scopesFor(string $provider): array
    {
        return match ($provider) {
            'google' => ['openid', 'email', 'profile'],
            'github' => ['user:email'], // E-poçtu görmək üçün əlavə scope
            default  => [],
        };
    }
}
```

---

### Route-lar

```php
// routes/web.php
use App\Http\Controllers\SocialAuthController;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::get('{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->name('social.redirect');

    Route::get('{provider}/callback', [SocialAuthController::class, 'callback'])
        ->name('social.callback');
});
```

---

### GitHub E-poçt Olmadıqda — Tamamlama Forması

GitHub istifadəçiləri e-poçtlarını gizlədə bilər. Bu halda ayrıca qeydiyyat tamamlama forması lazımdır:

```php
// app/Http/Controllers/SocialAuthController.php — əlavə metodlar

/**
 * E-poçtu olmayan GitHub user-i üçün tamamlama forması.
 */
public function showCompleteRegistration(Request $request): View
{
    return view('auth.complete-registration', [
        'provider'    => $request->query('provider'),
        'provider_id' => $request->query('provider_id'),
        'name'        => $request->query('name'),
        'avatar'      => $request->query('avatar'),
        'token'       => $request->query('token'),
    ]);
}

/**
 * E-poçt doldurulduqdan sonra qeydiyyatı tamamla.
 */
public function completeRegistration(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'email'       => 'required|email|unique:users,email',
        'provider'    => 'required|in:github',
        'provider_id' => 'required|string',
        'token'       => 'required|string',
    ]);

    $user = DB::transaction(function () use ($validated) {
        $user = User::create([
            'name'              => $request->input('name', 'User'),
            'email'             => $validated['email'],
            'password'          => null,
            'email_verified_at' => null, // E-poçt doğrulanmamışdır — verification e-poçtu göndər
            'avatar'            => $request->input('avatar'),
        ]);

        $user->socialAccounts()->create([
            'provider'     => $validated['provider'],
            'provider_id'  => $validated['provider_id'],
            'access_token' => decrypt($validated['token']),
        ]);

        return $user;
    });

    // E-poçt doğrulama linki göndər
    $user->sendEmailVerificationNotification();

    Auth::login($user, remember: true);

    return redirect()->route('dashboard')
        ->with('info', 'Hesabınız yaradıldı. E-poçtunuzu doğrulamağı unutmayın.');
}
```

---

## Yanaşmaların Müqayisəsi

| Yanaşma | Üstünlük | Risk | Nə zaman istifadə et |
|---------|----------|------|----------------------|
| **E-poçt + Parol** | Tam nəzarət, heç bir xarici asılılıq yoxdur | Şifrə sızması riski, yüksək friction | B2B, tənzimləmə tələbləri, parol meneceri istifadəçiləri |
| **Social Login** | Aşağı friction, verified email, tez qeydiyyat | Provider-ə asılılıq, hesab silinə bilər | B2C, startup, mobile app |
| **Magic Link** | Parol yoxdur, spam yoxdur | E-poçt çatışmazlığı, slow UX | Developer tool, B2B SaaS |
| **Passkey (WebAuthn)** | Ən güvənli, phishing-ə qarşı | Brauzerdən asılı, yeni texnologiya | Bank, yüksək təhlükəsizlik |

**Tövsiyə:** Social login + e-poçt/parol seçimini birlikdə saxlayın. Yalnız social login → user GitHub hesabını silsə, giriş imkanını tamamilə itirir (lock-out riski).

---

## Təhlükəsizlik Məsələləri

### 1. State Parameter (CSRF Qoruması)

Socialite `state` parametrini avtomatik idarə edir. Bu parametr olmadan, hücumçu öz OAuth flow-unu kurban-ın sessiyasına bağlaya bilər (CSRF attack).

```
Hücum ssenarisi (state olmadan):
1. Hücumçu OAuth flow başladır, amma callback-i tamamlamır
2. ?code=ATTACKER_CODE linkini kurban-a göndərir
3. Kurban linki basır → hücumçunun hesabına daxil olur
4. Hücumçu kurban-ın fəaliyyətlərini görür
```

Socialite `InvalidStateException` ilə bunu avtomatik blokur. Heç vaxt `->stateless()` istifadə etməyin (API-lər istisna olmaqla).

### 2. Redirect URI Sabitliyi

Google/GitHub Console-da yalnız konkret URI-ləri qeydiyyatdan keçirin. Wildcard (`*.yourapp.com`) qadağandır — open redirect hücumuna yol açar.

```
Düzgün:  https://yourapp.com/auth/google/callback
Yanlış:  https://*.yourapp.com/callback
Yanlış:  https://yourapp.com/auth/*/callback
```

### 3. Access Token Saxlanması

Provider-dən gələn access_token çox həssasdır. İstifadəçi profilini aldıqdan sonra onu saxlamağa ehtiyac yoxdur. Əgər API çağırışları üçün lazımdırsa:

```php
// Məsləhət görülmür — plain text saxlama
'access_token' => $socialUser->token,

// Tövsiyə edilir — encrypted saxlama
'access_token' => encrypt($socialUser->token),

// Oxuyarkən
$token = decrypt($socialAccount->access_token);
```

### 4. Callback Endpoint Rate Limiting

```php
// routes/web.php
Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->middleware('throttle:10,1'); // 1 dəqiqədə 10 sorğu
```

### 5. HTTPS Məcburiyyəti

OAuth redirect URI mütləq HTTPS olmalıdır. HTTP üzərindən authorization code ələ keçirilə bilər. `config/session.php`-da `secure => true` seçin.

---

## Anti-patternlər

**1. Provider e-poçtunu verified hesab etmək**
Bəzi OAuth provider-lər (`email_verified: false`) olan e-poçt qaytarır. Google, GitHub əsasən doğrulanmış e-poçt verir, amma həmişə bunu yoxlamaq lazımdır.
```php
// Yanlış
$user = User::firstOrCreate(['email' => $socialUser->getEmail()]);

// Düzgün
if ($provider === 'google' && !($socialUser->user['email_verified'] ?? false)) {
    return redirect()->withErrors(['E-poçt doğrulanmayıb.']);
}
```

**2. Access token-i session-da saxlamaq**
`session(['google_token' => $socialUser->token])` — session datası çox yerdə log-lana, dump-lana bilər. Token-i session-da saxlamaq risk yaradır. Ehtiyac varsa DB-də encrypted saxlayın.

**3. Hər callback-da yeni user yaratmaq**
Eyni email ilə hər giriş yeni user yaradırsa, DB tezliklə dublikat user-lərlə dolur. `firstOrCreate` yerinə düzgün `findOrCreateUser` məntiqi yazın.

**4. `stateless()` istifadəsi (web üçün)**
`Socialite::driver('google')->stateless()->user()` — state yoxlamasını deaktiv edir. CSRF hücumuna qapı açır. Yalnız stateless API (token-based) kontekstlərində istifadə edin.

**5. Redirect URI-ni wildcard etmək**
Provider-in developer console-unda `https://yourapp.com/auth/*` kimi pattern istifadəsi open redirect hücumuna yol açır. Mütləq tam URI göstərin.

**6. Social login-i parola tam alternativ hesab etmək**
İstifadəçi yalnız Google ilə qeydiyyatdan keçibsə və Google hesabını silsə, application-a bir daha daxil ola bilmir. Həmişə email/parol qeydiyyat seçimini də təklif edin.

**7. Provider-dən gələn adı sanitize etməmək**
`$socialUser->getName()` istənilən mətn qaytara bilər — `<script>alert(1)</script>` daxil olmaqla. Blade avtomatik escape edir, amma raw output (`{!! !!}`) istifadəsindən çəkinin.

---

## Interview Sualları və Cavablar

**S: OAuth2 Authorization Code Flow-u necə izah edərdiniz?**

C: Flow 5 əsas addımdan ibarətdir. İstifadəçi "Login with Google" basır. Bizim server state parameter ilə Google-ın authorization endpoint-inə redirect edir. İstifadəçi icazə verir, Google bizim callback URL-ə qısa ömürlü `code` ilə qayıdır. Biz bu `code`-u Google-a göndərib `access_token` alırıq — bu server-server əməliyyatıdır, brauzer görmür. Access token ilə istifadəçinin profilini çəkirik. Niyə bu flow? Çünki real token heç vaxt brauzerə düşmür; bu, Implicit Flow-dan əsas fərqidir.

---

**S: Authorization Code Flow ilə Implicit Flow arasındakı fərq nədir? Niyə Implicit Flow-dan çəkinirik?**

C: Implicit Flow-da access token birbaşa URL fragment-ində (`#access_token=...`) brauzerə gəlir. Bu, token-i browser history-yə, web server log-larına, JavaScript-ə düşürür. Authorization Code Flow-da isə yalnız qısa ömürlü, bir dəfəlik `code` URL-ə gəlir; real token server-server HTTP POST ilə alınır. PKCE (Proof Key for Code Exchange) əlavə edilərsə, `code` oğurlanmış olsa belə istifadə edilə bilmir. OAuth 2.1 spesifikasiyası Implicit Flow-u tamamilə ləğv edir.

---

**S: İki fərqli provider-dən eyni email gələrsə nə edərdiniz — yeni user yaradarsınız, yoxsa link edərsiniz?**

C: Eyni email-i eyni istifadəçi hesab edib link edərik. Məntiq belədir: əvvəlcə `social_accounts` cədvəlində `provider + provider_id` kombinasiyasına baxırıq. Tapılırsa, mövcud user-ə aid social account-dur. Tapılmırsa, `users` cədvəlində email ilə axtarırıq. Email tapılırsa, yeni social account yaradıb mövcud user-ə bağlayırıq. Email tapılmırsa, yeni user yaradırıq. Yeni social account əlavə edərkən user-ə notification göndərmək məsləhətdir: "GitHub hesabınız Google hesabınıza bağlandı."

---

**S: Access token-i harada və necə saxlayırsınız?**

C: Əsas prinsip: imkan varsa saxlamayın. İlk giriş zamanı profil məlumatlarını (ad, email, avatar) alırıq — bundan sonra access token-ə ehtiyac qalmır. Əgər API çağırışları üçün lazımdırsa (məs: istifadəçinin GitHub repo-larını görmək), `social_accounts` cədvəlində encrypted saxlayırıq. Laravel-in `encrypt()/decrypt()` funksiyaları APP_KEY ilə şifrələyir. Session-da saxlamırıq — session datası çox yerdə leak ola bilər.

---

**S: PKCE nədir və nə zaman lazımdır?**

C: PKCE (Proof Key for Code Exchange) — Authorization Code Flow-a əlavə bir qoruma qatıdır. Redirect etməzdən əvvəl client tərəfindən random `code_verifier` yaradılır. Onun hash-i (`code_challenge`) authorization request-ə əlavə olunur. Token almaq üçün `code_verifier` göndərilir — Google onun hash-ini authorization zamanı göndərilən `code_challenge` ilə müqayisə edir. Bu sayədə `code` oğurlansa belə `code_verifier` olmadan istifadə edilə bilmir. PKCE public client-lər üçün vacibdir — mobil app, SPA. Server-side app-larda client secret olduğundan PKCE optional sayılır, amma tövsiyə edilir.

---

## Əlaqəli Mövzular

- `02-double-charge-prevention.md` — Idempotency və race condition qoruması
- `use-cases/` qovluğundakı authentication-la bağlı digər use-case-lər
- `security/` — JWT vs Session, CSRF qoruması
- `php/topics/` — Sanctum/Passport/JWT müqayisəsi
