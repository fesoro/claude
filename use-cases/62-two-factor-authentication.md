# Two-Factor Authentication (2FA) (Senior)

## Problem Təsviri

Password tək başına etibarlı authentication mexanizmi deyil. Hər il milyardlarla credential data breach-lərdə sızır — və bu sızan məlumatlar dərhal "credential stuffing" hücumlarında istifadə olunur.

```
1. HaveIBeenPwned databazasında 12+ milyard sızmış credential var
2. İstifadəçilərin 65%-i eyni şifrəni bir neçə saytda istifadə edir
3. Hücumçu: LinkedIn breach → user@gmail.com:Password123 → sınayır GitHub, Slack, bank
4. Uğur nisbəti: 0.1%-2% — 10 milyonluq siyahıda 10,000-200,000 uğurlu giriş
```

**Credential stuffing hücumu:**

```
Attacker:
  Sızmış siyahı: [user@gmail.com:Password123, john@work.com:Summer2023!, ...]
  Bot network → Hər credential-i hədəf site-a qarşı sınayır
  Uğurlu giriş → Account takeover → maliyyə ziyanı / data oğurluğu

Single point of failure:
  Şifrə doğrudur → Sistem içəridədir
  Şifrə sızmışdır → Sistem açıqdır
```

**Real nəticə:** Bir breach → digər xidmətlərdə account takeover. Bir istifadəçinin şifrəsi bank, iş maili, SaaS tool-larında eynidir. Biri sızsa hamısı açılır.

**2FA niyə həll edir:**

```
Ənənəvi: Something you know (şifrə) → Tek faktor
2FA:      Something you know (şifrə) + Something you have (telefon/cihaz) → İki faktor

Hücumçuda şifrə var, telefon yoxdur → Giriş mümkün deyil
```

---

## 2FA Növləri

| Metod | Təhlükəsizlik | UX | İmplementasiya | Əsas Risk |
|-------|--------------|-----|----------------|-----------|
| **TOTP** (Google Authenticator, Authy) | ⭐⭐⭐⭐⭐ | ⭐⭐⭐ | ⭐⭐⭐⭐ | Telefon itkisi (recovery codes ilə həll olur) |
| **SMS OTP** | ⭐⭐ | ⭐⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | SIM swap, SS7 hücumu, SMS intercept |
| **Email OTP** | ⭐⭐⭐ | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ | Email account da compromised ola bilər |
| **Hardware Key** (FIDO2/WebAuthn) | ⭐⭐⭐⭐⭐ | ⭐⭐ | ⭐⭐ | Fiziki itkisi, baha hardware |
| **Recovery Codes** | — | ⭐⭐ | ⭐⭐⭐⭐ | Saхlama problemi (plaintext saxlasalar risk) |

**Tövsiyə:** TOTP əsas metod kimi, recovery codes mütləq əlavə etmək, SMS yalnız fallback kimi (əgər istifadəçi 2FA qura bilmirsə).

**SIM swap nədir:** Hücumçu telefon operatoruna zəng edir, özünü siz kimi təqdim edir, nömrəni yeni SIM-ə köçürdür. SMS OTP artıq hücumçuya gedir. Bu hücum xüsusilə yüksək dəyərli hesablara (maliyyə, kripto) tətbiq edilir.

---

## Həll: TOTP Implementation

### TOTP Necə İşləyir

```
Qeydiyyat zamanı:
  Server → unikal 32-byte secret yaradır
  Secret → QR code kimi göstərilir
  User → Authenticator app ilə skan edir
  App → secret-i cihazda saxlayır

Giriş zamanı:
  App + Server eyni hesablamanı aparır:
    TOTP = HMAC-SHA1(secret, floor(current_unix_time / 30))
    → 6 rəqəmli kod

  30 saniyə window-u var (server 1 əvvəl + cari + 1 sonrakı window-u qəbul edir)
  Şəbəkə lazım deyil — offline işləyir
  Her 30 saniyəde bir yeni kod
```

### Paket Quraşdırılması

```bash
composer require pragmarx/google2fa-laravel
composer require bacon/bacon-qr-code  # QR code generate üçün
```

```php
// config/google2fa.php (publish after install)
php artisan vendor:publish --provider="PragmaRX\Google2FALaravel\ServiceProvider"
```

### Database Migration

```php
// database/migrations/2024_01_10_add_two_factor_to_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // TOTP secret — şifrələnmiş saxlanılır
            $table->text('two_factor_secret')->nullable()->after('password');

            // 2FA aktiv edildikdə doldurulur (setup tamamlandıqda)
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');

            // Recovery codes — hashed, JSON array
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_secret',
                'two_factor_confirmed_at',
                'two_factor_recovery_codes',
            ]);
        });
    }
};
```

---

## TwoFactorService

*2FA-nın bütün əsas məntiqini: secret yaratmaq, OTP yoxlamaq, recovery code idarəetmə.*

```php
// app/Services/TwoFactorService.php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    /**
     * Yeni TOTP secret yaradır (hələ aktiv deyil, user confirm etməlidir).
     * Secret şifrələnmiş saxlanılır — app key ilə encrypt/decrypt.
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey();
    }

    /**
     * Authenticator app-ə scan ediləcək QR code URI.
     */
    public function getQrCodeUri(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            company: config('app.name'),
            holder: $user->email,
            secret: $secret
        );
    }

    /**
     * İstifadəçinin daxil etdiyi OTP-ni yoxlayır.
     * verifyKey() 1 əvvəlki + cari + 1 sonrakı window-u yoxlayır (90 saniyelik ümumi pəncərə).
     */
    public function verifyOtp(string $secret, string $otp): bool
    {
        // Boşluqları təmizlə (user space ilə yazıb ola bilər: "123 456")
        $otp = preg_replace('/\s+/', '', $otp);

        try {
            return $this->google2fa->verifyKey($secret, $otp);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * 8 ədəd birdəfəlik recovery code generate edir.
     * Format: xxxx-xxxx-xxxx (readable)
     */
    public function generateRecoveryCodes(): array
    {
        return Collection::times(8, function () {
            return implode('-', [
                Str::random(4),
                Str::random(4),
                Str::random(4),
            ]);
        })->all();
    }

    /**
     * Recovery code-ları hash-ləyərək DB-yə yazır.
     * Plaintext kodlar istifadəçiyə göstərilir (bir dəfəlik), DB-də yalnız hash saxlanır.
     */
    public function storeRecoveryCodes(User $user, array $codes): void
    {
        $hashed = array_map(fn (string $code) => [
            'code' => Hash::make($code),
            'used' => false,
        ], $codes);

        $user->update([
            'two_factor_recovery_codes' => json_encode($hashed),
        ]);
    }

    /**
     * Recovery code yoxlayır. Uğurlu olduqda həmin kodu "used" işarələyir.
     * İstifadə edilmiş kod bir daha qəbul edilmir (replay attack qarşısı).
     */
    public function verifyAndConsumeRecoveryCode(User $user, string $inputCode): bool
    {
        $storedCodes = json_decode($user->two_factor_recovery_codes, true);

        if (empty($storedCodes)) {
            return false;
        }

        $inputCode = trim($inputCode);
        $found = false;

        $updatedCodes = array_map(function (array $entry) use ($inputCode, &$found) {
            if (!$entry['used'] && Hash::check($inputCode, $entry['code'])) {
                $found = true;
                return array_merge($entry, ['used' => true]);
            }
            return $entry;
        }, $storedCodes);

        if ($found) {
            $user->update([
                'two_factor_recovery_codes' => json_encode($updatedCodes),
            ]);
        }

        return $found;
    }

    /**
     * İstifadəçinin neçə aktiv (istifadə edilməmiş) recovery kodu qaldığını qaytarır.
     */
    public function remainingRecoveryCodesCount(User $user): int
    {
        $codes = json_decode($user->two_factor_recovery_codes ?? '[]', true);

        return count(array_filter($codes, fn ($entry) => !$entry['used']));
    }

    /**
     * 2FA aktiv edilib-edilmədiyini yoxlayır.
     */
    public function isEnabled(User $user): bool
    {
        return !is_null($user->two_factor_confirmed_at);
    }
}
```

---

## TwoFactorController — Setup və Login Axını

*Setup (aktiv etmə), OTP challenge və disable axınları:*

```php
// app/Http/Controllers/Auth/TwoFactorController.php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class TwoFactorController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService
    ) {}

    // -------------------------------------------------------------------------
    // SETUP AXINI
    // -------------------------------------------------------------------------

    /**
     * 2FA setup başladır — secret yaradır, QR code göndərir.
     * User hələ confirm etməyib, two_factor_confirmed_at null qalır.
     */
    public function setup(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($this->twoFactorService->isEnabled($user)) {
            return response()->json([
                'message' => '2FA artıq aktivdir.',
            ], 422);
        }

        // Yeni secret yarat, lakin hələ confirmed deyil
        $secret = $this->twoFactorService->generateSecret();
        $user->update(['two_factor_secret' => encrypt($secret)]);

        $qrCodeUri = $this->twoFactorService->getQrCodeUri($user, $secret);

        // SVG QR code render et
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($qrCodeUri);

        return response()->json([
            'secret' => $secret,            // User manual daxil edə bilsin deyə
            'qr_code' => base64_encode($qrCodeSvg), // Frontend SVG render üçün
            'message' => 'Authenticator app ilə QR kodu skan edin, sonra OTP ilə təsdiqləyin.',
        ]);
    }

    /**
     * İstifadəçi QR kodu skan etdikdən sonra ilk OTP-ni daxil edib təsdiqləyir.
     * Uğurlu olduqda:
     *   - two_factor_confirmed_at doldurulur
     *   - Recovery codes generate edilib bir dəfəlik göstərilir
     */
    public function enable(Request $request): JsonResponse
    {
        $request->validate([
            'otp' => 'required|string|size:6|regex:/^\d+$/',
        ]);

        $user = $request->user();

        if ($this->twoFactorService->isEnabled($user)) {
            return response()->json(['message' => '2FA artıq aktivdir.'], 422);
        }

        if (!$user->two_factor_secret) {
            return response()->json(['message' => 'Əvvəlcə setup edin.'], 422);
        }

        $secret = decrypt($user->two_factor_secret);

        if (!$this->twoFactorService->verifyOtp($secret, $request->otp)) {
            return response()->json([
                'message' => 'Yanlış OTP. Authenticator app-dəki kodu daxil edin.',
            ], 422);
        }

        // Confirmed işarələ
        $user->update(['two_factor_confirmed_at' => now()]);

        // Recovery codes yarat — bir dəfəlik göstərilir, DB-də hashed saxlanır
        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();
        $this->twoFactorService->storeRecoveryCodes($user, $recoveryCodes);

        return response()->json([
            'message' => '2FA uğurla aktiv edildi.',
            'recovery_codes' => $recoveryCodes, // DIQQƏT: yalnız bir dəfə göstər
            'warning' => 'Bu kodları təhlükəsiz yerdə saxlayın. Bir daha göstərilməyəcək.',
        ]);
    }

    /**
     * Recovery code-ları yenidən generate edir (köhnə kodlar ləğv olur).
     * Yalnız 2FA artıq aktiv olan user üçün.
     */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Şifrə yanlışdır.'], 403);
        }

        if (!$this->twoFactorService->isEnabled($user)) {
            return response()->json(['message' => '2FA aktiv deyil.'], 422);
        }

        $recoveryCodes = $this->twoFactorService->generateRecoveryCodes();
        $this->twoFactorService->storeRecoveryCodes($user, $recoveryCodes);

        return response()->json([
            'recovery_codes' => $recoveryCodes,
            'message' => 'Recovery kodlar yeniləndi. Köhnə kodlar artıq işləmir.',
        ]);
    }

    /**
     * 2FA-nı deaktiv edir.
     * Şifrə + cari OTP tələb olunur (ikiqat yoxlama).
     */
    public function disable(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'otp' => 'required|string',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Şifrə yanlışdır.'], 403);
        }

        if (!$this->twoFactorService->isEnabled($user)) {
            return response()->json(['message' => '2FA artıq aktiv deyil.'], 422);
        }

        $secret = decrypt($user->two_factor_secret);

        if (!$this->twoFactorService->verifyOtp($secret, $request->otp)) {
            return response()->json(['message' => 'Yanlış OTP.'], 422);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);

        return response()->json(['message' => '2FA deaktiv edildi.']);
    }

    // -------------------------------------------------------------------------
    // LOGIN CHALLENGE AXINI
    // -------------------------------------------------------------------------

    /**
     * Normal login uğurlu olduqdan sonra 2FA challenge.
     * OTP və ya recovery code qəbul edir.
     *
     * Rate limit: 5 cəhd / 15 dəqiqə (brute force qarşısı).
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        // Login axınında user session-da müvəqqəti saxlanılır
        if (!$request->session()->has('2fa:user_id')) {
            return response()->json(['message' => 'Session bitib. Yenidən giriş edin.'], 401);
        }

        $userId = $request->session()->get('2fa:user_id');

        // Rate limit — IP + user_id kombinasiyası
        $rateLimitKey = "2fa_verify:{$request->ip()}:{$userId}";

        if (RateLimiter::tooManyAttempts($rateLimitKey, maxAttempts: 5)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            return response()->json([
                'message' => "Çox sayda uğursuz cəhd. {$seconds} saniyə sonra yenidən cəhd edin.",
                'retry_after' => $seconds,
            ], 429);
        }

        $user = \App\Models\User::find($userId);

        if (!$user || !$this->twoFactorService->isEnabled($user)) {
            return response()->json(['message' => 'İstifadəçi tapılmadı.'], 404);
        }

        $code = trim($request->code);
        $secret = decrypt($user->two_factor_secret);

        // OTP yoxla
        if ($this->twoFactorService->verifyOtp($secret, $code)) {
            return $this->completeTwoFactorAuth($request, $user, $rateLimitKey);
        }

        // Recovery code yoxla
        if ($this->twoFactorService->verifyAndConsumeRecoveryCode($user, $code)) {
            // Qalan recovery code sayını yoxla — azdırsa xəbərdar et
            $remaining = $this->twoFactorService->remainingRecoveryCodesCount($user);

            $response = $this->completeTwoFactorAuth($request, $user, $rateLimitKey);

            if ($remaining <= 2) {
                // Response-a warning əlavə et
                $data = json_decode($response->getContent(), true);
                $data['warning'] = "Yalnız {$remaining} recovery kodunuz qaldı. Yeni kodlar generate edin.";
                return response()->json($data, $response->getStatusCode());
            }

            return $response;
        }

        // Uğursuz cəhd
        RateLimiter::hit($rateLimitKey, decay: 15 * 60);

        return response()->json([
            'message' => 'Yanlış kod. OTP və ya recovery code daxil edin.',
            'attempts_remaining' => RateLimiter::remaining($rateLimitKey, 5),
        ], 422);
    }

    private function completeTwoFactorAuth(
        Request $request,
        \App\Models\User $user,
        string $rateLimitKey
    ): JsonResponse {
        // Rate limit sıfırla
        RateLimiter::clear($rateLimitKey);

        // Müvəqqəti session-u təmizlə
        $request->session()->forget('2fa:user_id');

        // 2FA tamamlandı — session-a qeyd et
        $request->session()->put('2fa_verified', true);
        $request->session()->put('2fa_verified_at', now()->timestamp);

        // User-i authenticate et
        auth()->login($user);

        // "Bu cihazı yadda saxla" — 30 günlük cookie
        $rememberDevice = $request->boolean('remember_device');
        $cookie = null;

        if ($rememberDevice) {
            $deviceToken = $this->generateSignedDeviceToken($user);
            $cookie = cookie(
                name: '2fa_device',
                value: $deviceToken,
                minutes: 60 * 24 * 30, // 30 gün
                secure: true,
                httpOnly: true,
                sameSite: 'Strict'
            );
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        $responseData = [
            'message' => 'Uğurla daxil oldunuz.',
            'token' => $token,
            'remember_device' => $rememberDevice,
        ];

        $response = response()->json($responseData);

        return $rememberDevice ? $response->withCookie($cookie) : $response;
    }

    /**
     * Cihaz token-i — HMAC ilə imzalanır.
     * Imzalanmamış token-i qəbul etmirdik (anti-pattern #7).
     */
    private function generateSignedDeviceToken(\App\Models\User $user): string
    {
        $payload = json_encode([
            'user_id' => $user->id,
            'created_at' => now()->timestamp,
            'device_fingerprint' => request()->userAgent(),
        ]);

        $signature = hash_hmac('sha256', $payload, config('app.key'));

        return base64_encode($payload) . '.' . $signature;
    }
}
```

---

## TwoFactorMiddleware

*Hər protected route-da 2FA-nın tamamlanıb-tamamlanmadığını yoxlayan middleware.*

```php
// app/Http/Middleware/TwoFactorMiddleware.php
namespace App\Http\Middleware;

use App\Services\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwoFactorMiddleware
{
    public function __construct(
        private TwoFactorService $twoFactorService
    ) {}

    /**
     * Yoxlama sırası:
     * 1. User login olub?
     * 2. 2FA aktiv edilib?
     * 3. Bu cihaz "remembered"?  → keç
     * 4. Bu session-da 2FA tamamlanıb? → keç
     * 5. 2FA challenge-a yönləndir
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Unauthenticated — auth middleware-ə bırax
        if (!$user) {
            return $next($request);
        }

        // 2FA aktiv deyil — keç
        if (!$this->twoFactorService->isEnabled($user)) {
            return $next($request);
        }

        // "Remember device" cookie yoxla
        if ($this->isRememberedDevice($request, $user)) {
            return $next($request);
        }

        // Bu session-da 2FA tamamlanıb?
        if ($request->session()->get('2fa_verified') === true) {
            // Session-un vaxtını yoxla (maksimum 8 saat)
            $verifiedAt = $request->session()->get('2fa_verified_at');
            if ($verifiedAt && (now()->timestamp - $verifiedAt) < 8 * 3600) {
                return $next($request);
            }

            // Session-u sıfırla
            $request->session()->forget(['2fa_verified', '2fa_verified_at']);
        }

        // 2FA challenge lazımdır
        return response()->json([
            'message' => 'İki faktorlu doğrulama tələb olunur.',
            'requires_2fa' => true,
            'two_factor_challenge_url' => route('two-factor.verify'),
        ], 403);
    }

    /**
     * Cookie-ni yoxlayır: HMAC imzası doğrudur? User ID uyğundur?
     */
    private function isRememberedDevice(Request $request, \App\Models\User $user): bool
    {
        $cookie = $request->cookie('2fa_device');

        if (!$cookie) {
            return false;
        }

        try {
            $parts = explode('.', $cookie, 2);
            if (count($parts) !== 2) {
                return false;
            }

            [$encodedPayload, $signature] = $parts;
            $payload = base64_decode($encodedPayload);

            // İmzanı yoxla
            $expectedSignature = hash_hmac('sha256', $payload, config('app.key'));
            if (!hash_equals($expectedSignature, $signature)) {
                return false; // Tampered cookie
            }

            $data = json_decode($payload, true);

            return isset($data['user_id']) && (int) $data['user_id'] === $user->id;
        } catch (\Throwable) {
            return false;
        }
    }
}
```

---

## Login Axınına 2FA İnteqrasiyası

*Normal login controller-ə 2FA redirect əlavə etmək:*

```php
// app/Http/Controllers/Auth/AuthController.php
namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function __construct(
        private TwoFactorService $twoFactorService
    ) {}

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // Credentials yoxla
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Email və ya şifrə yanlışdır.'], 401);
        }

        $user = Auth::user();

        // 2FA aktiv deyil → normal token ver
        if (!$this->twoFactorService->isEnabled($user)) {
            Auth::logout(); // Laravel session auth-u sıfırla

            return response()->json([
                'token' => $user->createToken('auth-token')->plainTextToken,
                'two_factor_required' => false,
            ]);
        }

        // 2FA aktiv → session-da müvəqqəti saxla, challenge-a yönləndir
        Auth::logout(); // Hələ tam login olmasın

        $request->session()->put('2fa:user_id', $user->id);
        $request->session()->put('2fa:expires_at', now()->addMinutes(10)->timestamp);

        return response()->json([
            'two_factor_required' => true,
            'message' => 'OTP daxil edin.',
            'two_factor_challenge_url' => route('two-factor.verify'),
        ], 200);
    }
}
```

---

## Rate Limiting — OTP Brute Force Qoruması

*Laravel-in built-in `RateLimiter`-dən başqa, daha dərin bloklanma mexanizmi:*

```php
// app/Providers/AppServiceProvider.php (və ya RouteServiceProvider)
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('two-factor', function (Request $request) {
    return [
        // IP başına: 15 dəqiqədə 5 cəhd
        Limit::perMinutes(15, 5)->by($request->ip()),

        // User başına: 15 dəqiqədə 10 cəhd
        // (fərqli IP-dən gəlsə belə)
        Limit::perMinutes(15, 10)->by(
            $request->session()->get('2fa:user_id') ?? $request->ip()
        ),
    ];
});
```

```php
// routes/api.php
use App\Http\Controllers\Auth\TwoFactorController;
use Illuminate\Support\Facades\Route;

Route::post('/two-factor/verify', [TwoFactorController::class, 'verify'])
    ->middleware('throttle:two-factor')
    ->name('two-factor.verify');

Route::middleware(['auth:sanctum', TwoFactorMiddleware::class])->group(function () {
    Route::post('/two-factor/setup', [TwoFactorController::class, 'setup']);
    Route::post('/two-factor/enable', [TwoFactorController::class, 'enable']);
    Route::post('/two-factor/disable', [TwoFactorController::class, 'disable']);
    Route::post('/two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes']);
});
```

---

## Anti-patternlər

**1. Yalnız SMS 2FA istifadə etmək**
SIM swap hücumunda telefon nömrəsi hücumçuya köçürülür, bütün SMS OTP-lər ona gedir. TOTP əsas metod kimi istifadə edin; SMS yalnız fallback kimi (məs. TOTP app itkisində). Yüksək risk hesabları (maliyyə, admin) üçün SMS heç qəbul edilməməlidir.

**2. Recovery code olmadan 2FA aktivləşdirmək**
İstifadəçi telefonunu itirərsə hesabına girə bilmir — permanent lockout. 2FA-nı aktiv edən kimi 8 recovery code generate edib istifadəçiyə göstərin. Bu kodlar bir daha göstərilmir, user onları saxlamaq məsuliyyətini bilməlidir.

**3. OTP cəhdlərini rate-limit etməmək**
TOTP 6 rəqəmli kod — 1,000,000 kombinasiya. 30 saniyəlik window-da brute force etmək mümkün deyil, amma limit olmadıqda botlar minlərlə cəhd edə bilər. `RateLimiter` ilə IP + user_id üzrə 5 cəhd / 15 dəqiqə qoyun.

**4. 2FA secret-i plaintext saxlamaq**
DB breach-də bütün TOTP secret-lər açılır, hücumçu bütün account-ları bypass edə bilər. Laravel-in `encrypt()` / `decrypt()` funksiyaları ilə (AES-256-CBC + app key) şifrələyin. DB-də yalnız ciphertext.

**5. Admin üçün 2FA bypass endpoint-i yaratmaq**
"Admin 2FA-sız girə bilsin" deyə ayrı route — bu route-u tapan hücumçu 2FA-nı tamam bypass edir. Admin hesabları adi hesablardan daha çox qorunmalıdır, az deyil. Admin üçün hardware key (FIDO2) düşünün.

**6. Köhnə OTP-ləri qəbul etmək**
`verifyKey()` window parametrini artırmaq (məs: 10 əvvəlki window) — replay attack imkanı yaranır. Sızmış OTP-lər dəqiqələrlə işlək qalır. Default window (1 əvvəl + cari + 1 sonra = 90 saniyə) kifayətdir. Window-u artırmayın.

**7. Device "remember" cookie-ni imzalamamaq**
İmzasız cookie-ni hücumçu yarada və ya dəyişdirə bilər — başqa user kimi 2FA-nı keçər. `hash_hmac('sha256', $payload, config('app.key'))` ilə imzalayın, middleware-də `hash_equals()` ilə yoxlayın.

---

## Interview Sualları və Cavablar

**S: TOTP necə işləyir — şəbəkə olmadan necə kodu yoxlayır?**

TOTP (Time-based One-Time Password, RFC 6238) shared secret + cari vaxt əsasında işləyir. Qeydiyyat zamanı server bir secret yaradır — bu secret həm server-də, həm authenticator app-də saxlanılır. Kod yaratmaq üçün ikisi də eyni düsturu tətbiq edir: `HOTP(secret, floor(unix_time / 30))`. Nəticə 6 rəqəmli kod olur, hər 30 saniyədə dəyişir. Server-ə istifadəçi kodu göndərəndə, server öz hesablamasını edir — nəticə eynidir. Şəbəkə lazım deyil, çünki iki tərəf eyni secret və eyni vaxt (NTP ilə sinxronlaşmış) ilə eyni nəticəyə gəlir.

**S: SIM swap nədir və SMS 2FA-nı necə devre edir?**

SIM swap sosial mühəndislik hücumudur. Hücumçu telefon operatoruna zəng edir, istifadəçi kimi özünü təqdim edir (ad, doğum tarixi, adres — bunlar data breach-lərdən tapıla bilər) və nömrəni yeni SIM-ə köçürdürmək istəyini bildirir. Operator razılaşarsa, hücumçunun SIM-i qurbanın nömrəsini alır. Bundan sonra gələn bütün SMS-lər — o cümlədən OTP-lər — hücumçuya gedir. Bu üzdən SMS 2FA yüksək risk hesabları üçün tövsiyə edilmir.

**S: Recovery code-ları DB-də necə saxlamaq lazımdır?**

Recovery code-lar plaintext saxlanmamalıdır. DB breach-də açılmış recovery code-lar hücumçuya 2FA-nı bypass etmək imkanı verir. Düzgün yanaşma: hər kodu `Hash::make()` (bcrypt) ilə hash-ləyib JSON array kimi saxlamaq. İstifadəçi kodu daxil edəndə, `Hash::check()` ilə hər hash-ə qarşı yoxlayırıq. Uğurlu olan kodu `used: true` işarələyirik — eyni kod bir daha işləmir (replay attack qarşısı).

**S: Hücumçu 2FA-nı bypass etsə nə baş verir, necə aşkar edərsiniz?**

2FA bypass adətən ya session hijacking (session token oğurlanır, 2FA artıq tamamlanmışdır), ya phishing (user özü fake site-a OTP daxil edir, hücumçu real-time onu istifadə edir), ya da implementation bug (rate limit yoxdur, köhnə OTP-lər qəbul edilir) ilə baş verir. Aşkar etmək üçün: anomal giriş davranışını log edin (yeni IP/ölkə, qeyri-adi vaxt), hər uğurlu 2FA girişini log edin, anormal sayda uğursuz cəhdi alert kimi qaldırın. Şübhəli hərəkətdə user-ə email göndərin: "Yeni cihazdan giriş edildi."

**S: WebAuthn / FIDO2 TOTP-dən nə ilə fərqlənir?**

TOTP shared secret əsasında işləyir — server-də secret saxlanılır, bu server breach-lərə risk yaradır. WebAuthn (FIDO2) public key kriptografiyası istifadə edir. Qeydiyyatda cihaz (hardware key, telefon biometrics) bir açar cütü yaradır: public key server-ə göndərilir, private key heç vaxt cihazı tərk etmir. Autentifikasiyada cihaz server-in challenge-ını private key ilə imzalayır, server public key ilə yoxlayır. Phishing-ə davamlıdır (origin binding — yalnız qeydiyyat olunan domain üçün işləyir), shared secret yoxdur, brute force mümkün deyil. Dezavantajı: implementasiya mürəkkəbdir, hardware lazımdır, istifadəçi təcrübəsi qurumlar arasında fərqlidir.

---

## Əlaqəli Mövzular

- `01-user-authentication.md` — Session və token autentifikasiyası
- `02-double-charge-prevention.md` — Idempotency pattern
- `03-rate-limiting.md` — Brute force qoruması
- `11-session-management.md` — Session security
