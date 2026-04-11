# Security Best Practices — Laravel/PHP İntervyu Hazırlığı

## Mündəricat
1. [OWASP Top 10 (2021)](#owasp-top-10)
2. [SQL Injection](#sql-injection)
3. [XSS (Cross-Site Scripting)](#xss)
4. [CSRF](#csrf)
5. [Mass Assignment](#mass-assignment)
6. [Authentication Təhlükəsizliyi](#authentication)
7. [Authorization: Gates və Policies](#authorization)
8. [API Təhlükəsizliyi](#api-security)
9. [Security Headers Middleware](#security-headers)
10. [File Upload Təhlükəsizliyi](#file-upload)
11. [İntervyu Sualları](#intervyu-suallari)

---

## OWASP Top 10 (2021) {#owasp-top-10}

OWASP (Open Web Application Security Project) hər il ən kritik web tətbiq təhlükəsizlik risklərinin siyahısını nəşr edir. 2021-ci il versiyasında 10 əsas risk var.

---

### A01: Broken Access Control

**Nədir:** İstifadəçilər öz icazələrindən kənar hərəkətlər edə bilirlər. Ən geniş yayılmış zəiflikdir.

**Zəif (vulnerable) kod:**

```php
// routes/web.php - heç bir yoxlama yoxdur
Route::get('/admin/users/{id}/delete', [UserController::class, 'destroy']);
Route::get('/invoice/{id}', [InvoiceController::class, 'show']); // hər kəs başqasının fakturasını görə bilər

// InvoiceController.php - zəif
public function show(int $id): View
{
    // Heç bir ownership yoxlaması yoxdur!
    $invoice = Invoice::findOrFail($id);
    return view('invoices.show', compact('invoice'));
}
```

**Düzgün kod — Gates istifadəsi:**

```php
// app/Providers/AuthServiceProvider.php
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Sadə Gate — closure ilə
        Gate::define('view-invoice', function (User $user, Invoice $invoice): bool {
            return $user->id === $invoice->user_id
                || $user->hasRole('admin');
        });

        Gate::define('delete-user', function (User $user, User $target): bool {
            return $user->hasRole('admin') && $user->id !== $target->id;
        });
    }
}

// InvoiceController.php — Gate ilə qorunan
public function show(int $id): View
{
    $invoice = Invoice::findOrFail($id);

    // Gate authorize — icazə yoxdursa 403 qaytarır
    Gate::authorize('view-invoice', $invoice);

    return view('invoices.show', compact('invoice'));
}
```

**Policy ilə daha strukturlu həll:**

```php
// app/Policies/InvoicePolicy.php
class InvoicePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true; // login olan hər kəs siyahıya baxa bilər
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id
            || $user->can('manage-invoices');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id
            && $invoice->status === 'draft';
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id
            || $user->hasRole('admin');
    }

    // Admin bütün invoice-lara tam giriş əldə edir
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super-admin')) {
            return true;
        }
        return null; // digər policy metodlarına keç
    }
}

// AuthServiceProvider-də qeydiyyat
protected $policies = [
    Invoice::class => InvoicePolicy::class,
];

// Controller-də istifadə
public function update(Request $request, Invoice $invoice): RedirectResponse
{
    $this->authorize('update', $invoice); // Policy avtomatik aşkarlanır

    $invoice->update($request->validated());
    return redirect()->route('invoices.show', $invoice);
}

// Blade-də istifadə
@can('delete', $invoice)
    <button>Sil</button>
@endcan
```

---

### A02: Cryptographic Failures

**Nədir:** Həssas məlumatların düzgün şifrələnməməsi. Parolların plain-text saxlanması, zəif alqoritmlər.

**Zəif kod:**

```php
// HEÇ VAXT belə etmə!
$user->password = $_POST['password']; // plain-text!
$user->password = md5($_POST['password']); // MD5 — sındırılmış!
$user->password = sha1($_POST['password']); // SHA1 — zəif!
```

**Düzgün kod — bcrypt və Argon2:**

```php
// config/hashing.php
return [
    'driver' => 'bcrypt', // və ya 'argon2id'

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12), // cost factor — yüksək = daha yavaş = daha təhlükəsiz
    ],

    'argon' => [
        'memory' => 65536,  // 64MB
        'threads' => 1,
        'time' => 4,
    ],
];

// UserController.php — düzgün parol hash-ləmə
use Illuminate\Support\Facades\Hash;

public function register(RegisterRequest $request): RedirectResponse
{
    $user = User::create([
        'name'     => $request->name,
        'email'    => $request->email,
        'password' => Hash::make($request->password), // bcrypt/argon2
    ]);

    // Parol yoxlaması
    if (Hash::check($request->password, $user->password)) {
        // uyğun gəlir
    }

    // Hash-in yenilənməsi lazım olub-olmadığını yoxla
    if (Hash::needsRehash($user->password)) {
        $user->update(['password' => Hash::make($request->password)]);
    }

    return redirect()->route('dashboard');
}
```

**Laravel Crypt — məlumat şifrələmə:**

```php
// Bu kod Laravel Crypt facade ilə məlumatların şifrələnməsini göstərir
use Illuminate\Support\Facades\Crypt;

// Şifrələmə
$encrypted = Crypt::encryptString('həssas məlumat');

// Şifrənin açılması
try {
    $decrypted = Crypt::decryptString($encrypted);
} catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
    // Məlumat dəyişdirilib və ya açar yanlışdır
    abort(400, 'Məlumat bütövlüyü pozulub');
}

// Model-də cast ilə avtomatik şifrələmə
class User extends Model
{
    protected $casts = [
        'ssn'             => 'encrypted',        // string
        'bank_details'    => 'encrypted:array',  // array
        'secret_notes'    => 'encrypted:collection',
    ];
}

// Həssas sahələri log-larda gizlətmək
class User extends Model
{
    protected $hidden = ['password', 'remember_token', 'ssn'];
}
```

**HTTPS məcburi etmə:**

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    if (app()->environment('production')) {
        URL::forceScheme('https');
    }
}
```

---

### A03: Injection

**Nədir:** Güvənsiz məlumatın SQL, OS, LDAP sorğularına daxil edilməsi.

**SQL Injection — zəif kod:**

```php
// BU KOD SON DƏRƏCƏ TƏHLÜKƏLİDİR!
// URL: /users?name='; DROP TABLE users; --
public function search(Request $request): Collection
{
    $name = $request->input('name');

    // Raw SQL — injection üçün açıqdır
    return DB::select("SELECT * FROM users WHERE name = '$name'");
}

// Həmçinin zəif:
$users = DB::select("SELECT * FROM users WHERE id = " . $request->id);
```

**Düzgün kod — Eloquent / Query Builder:**

```php
// Eloquent ORM — tam qorunma
public function search(Request $request): Collection
{
    return User::where('name', $request->input('name'))->get();
}

// Query Builder ilə binding
public function findByEmail(string $email): ?User
{
    return DB::table('users')
        ->where('email', $email)
        ->first();
}

// Raw sorğu lazımdırsa — parametrli binding istifadə et
public function complexSearch(string $term): Collection
{
    return DB::select(
        'SELECT * FROM users WHERE name LIKE ? OR email LIKE ?',
        ["%{$term}%", "%{$term}%"]
    );
}

// whereRaw ilə düzgün binding
User::whereRaw('LOWER(name) = ?', [strtolower($name)])->get();

// Column adları dinamikdirsə — whitelist istifadə et
public function orderBy(Request $request): Collection
{
    $allowedColumns = ['name', 'email', 'created_at'];
    $column = in_array($request->sort, $allowedColumns)
        ? $request->sort
        : 'created_at';

    return User::orderBy($column)->get();
}
```

**Command Injection:**

```php
// ZƏİF — heç vaxt etmə!
$filename = $request->input('filename');
system("convert " . $filename . " output.pdf"); // shell injection!
exec("ls " . $request->path);

// DÜZGÜN — escapeshellarg istifadə et
$filename = escapeshellarg($request->input('filename'));
system("convert {$filename} output.pdf");

// Daha yaxşı — PHP funksiyaları ilə
// shell çağırışından tamamilə qaçın
use Symfony\Component\Process\Process;

$process = new Process(['convert', $request->input('filename'), 'output.pdf']);
$process->run();

if (!$process->isSuccessful()) {
    throw new \RuntimeException($process->getErrorOutput());
}
```

**XSS — Injection növü:**

```php
// ZƏİF Blade — raw HTML render edir
{!! $userInput !!}  // XSS təhlükəsi!

// DÜZGÜN Blade — avtomatik escape
{{ $userInput }}    // htmlspecialchars() tətbiq edilir

// PHP-də manual escape
echo htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
```

---

### A04: Insecure Design

**Nədir:** Təhlükəsizlik arxitektura səviyyəsində düşünülməyib.

***Nədir:** Təhlükəsizlik arxitektura səviyyəsində düşünülməyib üçün kod nümunəsi:*
```php
// Problemli dizayn: bir funksiya həddindən artıq iş görür
// və rate limiting yoxdur
public function requestPasswordReset(Request $request): JsonResponse
{
    $user = User::where('email', $request->email)->first();
    // Zəif: istifadəçinin mövcud olub-olmadığını açıqlayır
    if (!$user) {
        return response()->json(['error' => 'E-poçt tapılmadı'], 404);
    }
    // ...
}

// Düzgün dizayn: timing attack-dan qorunma + məlumat sızmasının qarşısı
public function requestPasswordReset(Request $request): JsonResponse
{
    $request->validate(['email' => 'required|email']);

    // İstifadəçi mövcud olsun ya olmasın eyni cavab
    $status = Password::sendResetLink(['email' => $request->email]);

    // Eyni mesaj — istifadəçi mövcudluğunu açıqlamırıq
    return response()->json([
        'message' => 'E-poçt ünvanınız mövcuddursa, sıfırlama linki göndərilib.'
    ]);
}
```

**Throttle — Rate Limiting dizaynı:**

```php
// routes/api.php
Route::middleware(['throttle:password-reset'])->group(function () {
    Route::post('/forgot-password', [PasswordController::class, 'store']);
});

// app/Providers/RouteServiceProvider.php
protected function configureRateLimiting(): void
{
    RateLimiter::for('password-reset', function (Request $request) {
        return Limit::perHour(5)->by($request->ip())
            ->response(function () {
                return response()->json([
                    'message' => 'Çox sayda cəhd. 1 saat sonra yenidən cəhd edin.'
                ], 429);
            });
    });
}
```

---

### A05: Security Misconfiguration

**Nədir:** Default konfiqurasiya, açıq error mesajları, debug rejimi production-da.

***Nədir:** Default konfiqurasiya, açıq error mesajları, debug rejimi p üçün kod nümunəsi:*
```php
// .env — production üçün
APP_ENV=production
APP_DEBUG=false      // TRUE olsa stack trace göstərilir!
APP_KEY=base64:...   // Mütləq unikal olmalıdır

// config/app.php
'debug' => (bool) env('APP_DEBUG', false),

// Xəta handler — production-da detalları gizlət
// app/Exceptions/Handler.php
public function render($request, Throwable $e): Response
{
    if ($request->expectsJson()) {
        $statusCode = $this->isHttpException($e)
            ? $e->getStatusCode()
            : 500;

        $message = app()->environment('production')
            ? 'Server xətası baş verdi'  // detalları gizlət
            : $e->getMessage();           // development-də detalı göstər

        return response()->json([
            'error'   => $message,
            'code'    => $statusCode,
        ], $statusCode);
    }

    return parent::render($request, $e);
}
```

**Faylların icazələri:**

```bash
# .env faylı yalnız owner tərəfindən oxuna bilər
chmod 600 .env

# storage/ qovluğu
chmod -R 775 storage bootstrap/cache

# Veb serverin .env-ə girişini blokla (nginx)
location ~ /\.env {
    deny all;
}
```

---

### A06: Vulnerable and Outdated Components

**Nədir:** Köhnəlmiş və ya zəifliyi olan paketlər.

***Nədir:** Köhnəlmiş və ya zəifliyi olan paketlər üçün kod nümunəsi:*
```bash
# Composer audit — PHP paketlərinin zəifliklərini yoxla
composer audit

# Output nümunəsi:
# Found 1 security vulnerability advisory affecting 1 package:
# laravel/framework (v9.0.0): CVE-2023-XXXX

# Paketləri yenilə
composer update --with-all-dependencies

# Outdated paketləri göstər
composer outdated

# NPM üçün
npm audit
npm audit fix
```

**Composer.json versiya məhdudiyyətləri:**

```json
{
    "require": {
        "laravel/framework": "^10.0",
        "league/flysystem": "^3.0"
    }
}
```

**CI/CD-də avtomatik audit:**

```yaml
# .github/workflows/security.yml
name: Security Audit
on: [push, pull_request]

jobs:
  security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Run Composer Audit
        run: composer audit --no-dev
      - name: Run NPM Audit
        run: npm audit --audit-level=high
```

---

### A07: Identification and Authentication Failures

**Nədir:** Zəif parollar, brute force, credential stuffing.

***Nədir:** Zəif parollar, brute force, credential stuffing üçün kod nümunəsi:*
```php
// app/Http/Controllers/Auth/LoginController.php
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function store(LoginRequest $request): RedirectResponse
    {
        // Rate limiting — 5 cəhd / dəqiqə, IP+email kombinasiyasına görə
        $this->ensureIsNotRateLimited($request);

        if (!Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request), 60); // 60 saniyə

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));
        $request->session()->regenerate(); // Session fixation-dan qorunma

        return redirect()->intended('/dashboard');
    }

    private function ensureIsNotRateLimited(Request $request): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', ['seconds' => $seconds]),
        ]);
    }

    private function throttleKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower($request->input('email')) . '|' . $request->ip()
        );
    }
}
```

**Parol policy:**

```php
// app/Http/Requests/RegisterRequest.php
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)             // minimum 12 simvol
                    ->mixedCase()             // böyük + kiçik hərf
                    ->numbers()               // rəqəm
                    ->symbols()               // xüsusi simvol
                    ->uncompromised(3),       // HaveIBeenPwned yoxlaması (3 dəfəyə qədər sızıb)
            ],
        ];
    }
}
```

**2FA (Two-Factor Authentication):**

```php
// composer require pragmarx/google2fa-laravel

// app/Http/Controllers/TwoFactorController.php
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function enable(Request $request): JsonResponse
    {
        $google2fa = new Google2FA();
        $secret = $google2fa->generateSecretKey();

        // Şifrələnmiş şəkildə saxla
        $request->user()->update([
            'two_factor_secret'     => encrypt($secret),
            'two_factor_confirmed'  => false,
        ]);

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $request->user()->email,
            $secret
        );

        return response()->json(['qr_code' => $qrCodeUrl, 'secret' => $secret]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:6']);

        $google2fa = new Google2FA();
        $secret = decrypt($request->user()->two_factor_secret);

        $valid = $google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            return response()->json(['error' => 'Yanlış kod'], 422);
        }

        $request->user()->update(['two_factor_confirmed' => true]);

        return response()->json(['message' => '2FA aktivləşdirildi']);
    }
}
```

---

### A08: Software and Data Integrity Failures

**Nədir:** Unsigned paketlər, CI/CD pipeline-a güvənilməz girişlər.

***Nədir:** Unsigned paketlər, CI/CD pipeline-a güvənilməz girişlər üçün kod nümunəsi:*
```php
// composer.json — lock faylı mütləq commit edilməli
// composer.lock faylı hash-ları saxlayır

// Paket hash yoxlaması
// composer install --verify-no-changed-files

// Subresource Integrity (SRI) — CDN faylları üçün
// Blade template-də
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"
    integrity="sha384-..."
    crossorigin="anonymous">
</script>
```

---

### A09: Security Logging and Monitoring Failures

**Nədir:** Təhlükəsizlik hadisələrinin loglanmaması.

***Nədir:** Təhlükəsizlik hadisələrinin loglanmaması üçün kod nümunəsi:*
```php
// app/Listeners/LogSecurityEvents.php
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;

class LogAuthEvents
{
    public function handleLogin(Login $event): void
    {
        Log::channel('security')->info('Uğurlu giriş', [
            'user_id'    => $event->user->id,
            'email'      => $event->user->email,
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp'  => now()->toIso8601String(),
        ]);
    }

    public function handleFailed(Failed $event): void
    {
        Log::channel('security')->warning('Uğursuz giriş cəhdi', [
            'email'      => $event->credentials['email'] ?? 'unknown',
            'ip'         => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp'  => now()->toIso8601String(),
        ]);
    }
}

// EventServiceProvider.php
protected $listen = [
    Login::class  => [LogAuthEvents::class . '@handleLogin'],
    Failed::class => [LogAuthEvents::class . '@handleFailed'],
];

// config/logging.php — ayrı security log kanalı
'channels' => [
    'security' => [
        'driver' => 'daily',
        'path'   => storage_path('logs/security.log'),
        'level'  => 'info',
        'days'   => 90, // 90 gün saxla
    ],
],
```

**Audit Log paketi:**

```php
// composer require owen-it/laravel-auditing

// Model-də
use OwenIt\Auditing\Contracts\Auditable;

class Invoice extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $auditInclude = ['status', 'amount', 'user_id'];
    protected $auditEvents  = ['created', 'updated', 'deleted'];
}

// Audit log-ları oxu
$invoice->audits; // Bütün dəyişiklik tarixçəsi
```

---

### A10: Server-Side Request Forgery (SSRF)

**Nədir:** Tətbiq server tərəfindən daxili resurslara sorğu göndərə bilir.

**Zəif kod:**

```php
// URL parametrindən gelen ünvana birbaşa sorğu göndərir
public function fetchUrl(Request $request): JsonResponse
{
    $url = $request->input('url');

    // SSRF! Hacker daxili resurslara çata bilər:
    // url=http://169.254.169.254/latest/meta-data/  (AWS metadata)
    // url=http://localhost:6379  (Redis)
    // url=http://internal-service:8080/admin
    $response = Http::get($url);

    return response()->json(['content' => $response->body()]);
}
```

**Qorunma:**

```php
// app/Services/SafeHttpClient.php
use Illuminate\Support\Facades\Http;

class SafeHttpClient
{
    private array $blockedHosts = [
        'localhost',
        '127.0.0.1',
        '0.0.0.0',
        '::1',
        '169.254.169.254', // AWS metadata
        '100.100.100.200',  // Alibaba Cloud metadata
    ];

    private array $blockedRanges = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '169.254.0.0/16', // link-local
        'fc00::/7',        // IPv6 private
    ];

    public function get(string $url): \Illuminate\Http\Client\Response
    {
        $this->validateUrl($url);
        return Http::timeout(10)->get($url);
    }

    private function validateUrl(string $url): void
    {
        $parsed = parse_url($url);

        if (!$parsed || !isset($parsed['host'])) {
            throw new \InvalidArgumentException('Yanlış URL formatı');
        }

        // Yalnız HTTP/HTTPS
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            throw new \InvalidArgumentException('Yalnız HTTP/HTTPS protokoluna icazə verilir');
        }

        $host = strtolower($parsed['host']);

        // Bloklanmış host-lar
        if (in_array($host, $this->blockedHosts)) {
            throw new \InvalidArgumentException('Bu host-a icazə verilmir');
        }

        // IP ünvanını yoxla
        $ip = gethostbyname($host);
        foreach ($this->blockedRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                throw new \InvalidArgumentException('Daxili şəbəkəyə girişə icazə verilmir');
            }
        }
    }

    private function ipInRange(string $ip, string $range): bool
    {
        [$subnet, $bits] = explode('/', $range);
        $ip     = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask   = -1 << (32 - (int) $bits);
        $subnet &= $mask;
        return ($ip & $mask) === $subnet;
    }
}

// Allowlist yanaşması (daha güclü)
class WebhookService
{
    private array $allowedDomains = [
        'api.stripe.com',
        'api.github.com',
        'hooks.slack.com',
    ];

    public function callWebhook(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);

        $isAllowed = collect($this->allowedDomains)
            ->contains(fn($domain) => str_ends_with($host, $domain));

        if (!$isAllowed) {
            throw new \InvalidArgumentException("'{$host}' domeninə icazə verilmir");
        }

        Http::post($url, [/* data */]);
    }
}
```

---

## SQL Injection — 3 Növ Zəif Kod {#sql-injection}

### 1. Classic SQL Injection

*1. Classic SQL Injection üçün kod nümunəsi:*
```php
// ZƏİF
public function login(Request $request): bool
{
    $email    = $request->email;
    $password = $request->password;

    // Payload: email = admin@site.com' OR '1'='1
    $user = DB::select(
        "SELECT * FROM users WHERE email = '$email' AND password = '$password'"
    );
    return !empty($user);
}

// DÜZGÜN
public function login(Request $request): bool
{
    return Auth::attempt([
        'email'    => $request->email,
        'password' => $request->password,
    ]);
}
```

### 2. Blind SQL Injection

*2. Blind SQL Injection üçün kod nümunəsi:*
```php
// ZƏİF — boolean-based blind injection
// Payload: id=1 AND SLEEP(5)--  və ya  id=1 AND 1=1--
public function getUser(int $id): array
{
    return DB::select("SELECT * FROM users WHERE id = $id");
}

// DÜZGÜN
public function getUser(int $id): ?User
{
    return User::find($id);
}
```

### 3. Second-Order SQL Injection

*3. Second-Order SQL Injection üçün kod nümunəsi:*
```php
// İlk addım — saxlama (görünür təhlükəsiz)
// Məlumat DB-ə saxlanılır amma sonra təhlükəli şəkildə istifadə edilir
$username = "admin'--"; // DB-ə təmiz saxlanılır
User::create(['username' => $username]);

// İkinci addım — zəif istifadə
$user = User::where('username', $username)->first();
// SONRA bu məlumat raw SQL-ə əlavə edilir — injection baş verir

// Həll: Həmişə parametrli sorğu istifadə et, mənşəyindən asılı olmayaraq
```

---

## XSS (Cross-Site Scripting) {#xss}

*XSS (Cross-Site Scripting) {#xss} üçün kod nümunəsi:*
```php
// Blade template-lər
// ZƏİF — raw HTML
{!! $comment->body !!}  // <script>alert('xss')</script> işə düşər!

// DÜZGÜN — avtomatik escape
{{ $comment->body }}    // &lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;

// HTML-ə icazə vermək lazımdırsa — sanitize et
use Illuminate\Support\HtmlString;

$clean = clean($userHtml); // HTMLPurifier ilə

// HTMLPurifier paketi: composer require ezyang/htmlpurifier
use HTMLPurifier;
use HTMLPurifier_Config;

$config   = HTMLPurifier_Config::createDefault();
$purifier = new HTMLPurifier($config);
$safe     = $purifier->purify($userHtml);
```

**Content Security Policy (CSP) Middleware:**

```php
// app/Http/Middleware/ContentSecurityPolicy.php
class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-" . $this->generateNonce() . "'",
            "style-src 'self' https://fonts.googleapis.com",
            "img-src 'self' data: https:",
            "font-src 'self' https://fonts.gstatic.com",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);

        $response->headers->set('Content-Security-Policy', $csp);

        return $response;
    }

    private function generateNonce(): string
    {
        return base64_encode(random_bytes(16));
    }
}

// Kernel.php-ə əlavə et
protected $middleware = [
    \App\Http\Middleware\ContentSecurityPolicy::class,
];
```

---

## CSRF (Cross-Site Request Forgery) {#csrf}

**Necə işləyir:**

```
1. İstifadəçi bank.com-da login olur → session açılır
2. Hacker evil.com saytına cəlb edir
3. evil.com bank.com/transfer?to=hacker&amount=1000 sorğusu göndərir
4. Brauzer avtomatik bank.com cookie-lərini əlavə edir
5. Bank server sorğunu real istifadəçidən gəlmiş kimi qəbul edir
```

**Laravel CSRF qoruması:**

```php
// Blade form-da
<form method="POST" action="/transfer">
    @csrf  {{-- Hidden input: <input type="hidden" name="_token" value="..."> --}}
    ...
</form>

// Verifiy CSRF token middleware avtomatik işləyir
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\VerifyCsrfToken::class, // Token yoxlanması
    ],
];

// Bəzi route-ları CSRF-dən istisna et (webhooks üçün)
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'stripe/webhook',
    'github/webhook',
];
```

**SPA üçün Sanctum ilə CSRF:**

```php
// config/sanctum.php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
    '%s%s',
    'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
    Sanctum::currentApplicationUrlWithPort()
))),

// Frontend (JavaScript)
// İlk olaraq CSRF cookie al
await axios.get('/sanctum/csrf-cookie');

// Sonra request göndər — Cookie avtomatik əlavə olunur
await axios.post('/api/login', { email, password });

// routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn(Request $r) => $r->user());
});
```

---

## Mass Assignment {#mass-assignment}

**Exploitation nümunəsi:**

```php
// ZƏİF — $fillable yoxdur
class User extends Model
{
    // Heç bir qorunma yoxdur!
}

// Hacker bu payload-ı göndərir:
// POST /register
// { "name": "John", "email": "john@test.com", "password": "...", "is_admin": true }

public function register(Request $request): RedirectResponse
{
    // is_admin=true massово əlavə edilir!
    $user = User::create($request->all()); // TƏHLÜKƏLİ!
    return redirect('/dashboard');
}
```

**Düzgün həll — $fillable:**

```php
// app/Models/User.php
class User extends Model
{
    // Yalnız bu sahələr toplu əlavə edilə bilər
    protected $fillable = [
        'name',
        'email',
        'password',
    ];
    // is_admin, role, email_verified_at — burada deyil, əlavə edilə bilməz

    // Alternativ: $guarded — bunlardan başqa hamısına icazə verilir
    // protected $guarded = ['is_admin', 'role'];
}

// Controller-də — validate + only istifadə et
public function register(RegisterRequest $request): RedirectResponse
{
    $user = User::create($request->validated()); // Yalnız validated məlumat
    return redirect('/dashboard');
}

// Request class-da
class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
        // is_admin buraya daxil deyil — validated() onu qaytarmaz
    }
}
```

---

## Authentication Təhlükəsizliyi {#authentication}

*Authentication Təhlükəsizliyi {#authentication} üçün kod nümunəsi:*
```php
// config/auth.php — session timeout
'guards' => [
    'web' => [
        'driver'   => 'session',
        'provider' => 'users',
    ],
],

// Session-ı müəyyən müddət sonra expire et
// config/session.php
'lifetime'   => 120, // 2 saat
'expire_on_close' => false, // Brauzer bağlananda bitsin?

// Account lockout — N uğursuz cəhddən sonra
// app/Http/Requests/Auth/LoginRequest.php (Laravel Breeze-dən)
public function authenticate(): void
{
    $this->ensureIsNotRateLimited();

    if (!Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
        RateLimiter::hit($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]);
    }

    RateLimiter::clear($this->throttleKey());
}

// Suspicious login detection
class LoginListener
{
    public function handle(Login $event): void
    {
        $user = $event->user;
        $ip   = request()->ip();

        // Son login IP ilə müqayisə et
        if ($user->last_login_ip && $user->last_login_ip !== $ip) {
            // Şübhəli — bildiriş göndər
            $user->notify(new SuspiciousLoginNotification($ip));
        }

        $user->update([
            'last_login_ip' => $ip,
            'last_login_at' => now(),
        ]);
    }
}
```

---

## Authorization: Gates, Policies, Spatie Permission {#authorization}

*Authorization: Gates, Policies, Spatie Permission {#authorization} üçün kod nümunəsi:*
```php
// Spatie Laravel Permission paketi
// composer require spatie/laravel-permission

// Migration-dan sonra
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate

// Role və permission yaratma
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

$adminRole   = Role::create(['name' => 'admin']);
$editorRole  = Role::create(['name' => 'editor']);

$viewPosts   = Permission::create(['name' => 'view posts']);
$editPosts   = Permission::create(['name' => 'edit posts']);
$deletePosts = Permission::create(['name' => 'delete posts']);

$adminRole->givePermissionTo([$viewPosts, $editPosts, $deletePosts]);
$editorRole->givePermissionTo([$viewPosts, $editPosts]);

// İstifadəçiyə rol vermə
$user->assignRole('admin');
$user->assignRole(['editor', 'writer']);

// Yoxlama
$user->hasRole('admin');
$user->can('edit posts');
$user->hasPermissionTo('delete posts');

// Middleware ilə qoruma
// routes/web.php
Route::middleware(['role:admin'])->group(function () {
    Route::resource('users', UserController::class);
});

Route::middleware(['permission:edit posts'])->group(function () {
    Route::put('/posts/{post}', [PostController::class, 'update']);
});

// Blade directive
@role('admin')
    <a href="/admin">Admin Panel</a>
@endrole

@hasanyrole('writer|admin')
    <button>Məqalə Yaz</button>
@endhasanyrole
```

---

## API Təhlükəsizliyi: Sanctum vs Passport {#api-security}

| Xüsusiyyət | Sanctum | Passport |
|---|---|---|
| Yönəlim | SPA, mobil, sadə API | OAuth2 server |
| Token növü | Personal Access Token | OAuth2: authorization_code, client_credentials |
| Kurulum | Sadə | Mürəkkəb |
| 3rd party auth | Yox | Bəli |
| İstifadə halı | Öz frontend/mobil | Kənar tətbiqləre giriş |

*həll yanaşmasını üçün kod nümunəsi:*
```php
// Sanctum qurulumu
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate

// Token yaratma
$token = $user->createToken('mobile-app', ['read', 'write'])->plainTextToken;

// Token ability yoxlaması
Route::middleware('auth:sanctum')->get('/posts', function (Request $request) {
    if (!$request->user()->tokenCan('read')) {
        abort(403, 'Bu token oxuma icazəsinə malik deyil');
    }
    return Post::all();
});

// Token silmə (logout)
$request->user()->currentAccessToken()->delete();

// Bütün token-ları sil
$request->user()->tokens()->delete();
```

---

## Security Headers Middleware {#security-headers}

*Security Headers Middleware {#security-headers} üçün kod nümunəsi:*
```php
// app/Http/Middleware/SecurityHeaders.php
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            // Brauzer MIME type-ı dəyişdirməsin
            'X-Content-Type-Options' => 'nosniff',

            // Clickjacking qoruması
            'X-Frame-Options' => 'SAMEORIGIN',

            // XSS filter (köhnə brauzerler üçün)
            'X-XSS-Protection' => '1; mode=block',

            // HTTPS məcburi et (1 il)
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',

            // Referrer məlumatını məhdudlaşdır
            'Referrer-Policy' => 'strict-origin-when-cross-origin',

            // Brauzer feature-larını məhdudlaşdır
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(self)',

            // Content Security Policy
            'Content-Security-Policy' => "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'",
        ];

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }

        // Server məlumatını gizlət
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        return $response;
    }
}

// app/Http/Kernel.php — global middleware kimi əlavə et
protected $middleware = [
    \App\Http\Middleware\SecurityHeaders::class,
    // ...
];
```

---

## File Upload Təhlükəsizliyi {#file-upload}

*File Upload Təhlükəsizliyi {#file-upload} üçün kod nümunəsi:*
```php
// app/Http/Controllers/FileController.php
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class FileController extends Controller
{
    // İcazə verilən MIME tiplər
    private array $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'application/pdf',
    ];

    // Maksimum fayl ölçüsü (bytes)
    private int $maxSize = 5 * 1024 * 1024; // 5MB

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => [
                'required',
                'file',
                'max:5120',                            // 5MB
                'mimes:jpeg,png,gif,webp,pdf',         // Whitelist
                'mimetypes:image/jpeg,image/png,application/pdf', // Real MIME yoxlaması
            ],
        ]);

        $file = $request->file('file');

        // Real MIME tip yoxlaması (extension-a güvənmə!)
        $realMime = $file->getMimeType(); // finfo_file() istifadə edir
        if (!in_array($realMime, $this->allowedMimes)) {
            abort(422, 'Bu fayl növünə icazə verilmir');
        }

        // Fayl adını sanitize et — orijinal adı istifadə etmə!
        $filename = $this->generateSafeFilename($file);

        // Public direktoriyanın xaricinə saxla
        $path = $file->storeAs('uploads/' . date('Y/m'), $filename, 'private');

        // PHP fayllarını web-dən əlçatımsız saxla
        // Storage disk: 'private' — public storage-da deyil

        return response()->json([
            'path' => $path,
            'url'  => route('files.download', ['path' => $path]),
        ]);
    }

    public function download(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $path = $request->input('path');

        // Path traversal qoruması
        $path = str_replace(['../', '..\\', '..'], '', $path);

        if (!Storage::disk('private')->exists($path)) {
            abort(404);
        }

        // Authorization yoxlaması
        $this->authorize('download-file', $path);

        return Storage::disk('private')->download($path);
    }

    private function generateSafeFilename(UploadedFile $file): string
    {
        // Unikal, təhlükəsiz fayl adı
        return sprintf(
            '%s_%s.%s',
            time(),
            bin2hex(random_bytes(8)),
            $file->getClientOriginalExtension() // Yalnız extension, tam ad yox
        );
    }
}

// config/filesystems.php — private disk
'disks' => [
    'private' => [
        'driver'     => 'local',
        'root'       => storage_path('app/private'),
        'visibility' => 'private',
    ],
],
```

---

## İntervyu Sualları {#intervyu-suallari}

**1. SQL Injection-dan qorunmaq üçün Laravel-də hansı yanaşmaları istifadə edirsiniz?**

Əsasən Eloquent ORM istifadə edirəm — avtomatik parametrli binding edir. Raw sorğu lazım olduqda `DB::select('SELECT * FROM t WHERE id = ?', [$id])` formatından istifadə edirəm. Column adları dinamik olarsa, whitelist ilə yoxlayıram.

**2. CSRF token necə işləyir?**

Server hər session üçün unikal token yaradır. `VerifyCsrfToken` middleware POST/PUT/DELETE sorğularda bu token-ı yoxlayır. Token form hidden input-unda və ya `X-CSRF-TOKEN` header-ında göndərilir. Brauzer kənar saytdan bu token-ı bilə bilmir.

**3. Gate ilə Policy arasındakı fərq nədir?**

Gate — sadə closure-based, model ilə bağlı olmayan hallar üçün. Policy — konkret model ətrafında qruplaşdırılmış authorization məntiqidir. `User`, `Post`, `Invoice` kimi modellər varsa Policy daha strukturludur.

**4. Mass Assignment nədir, necə qarşısı alınır?**

`$request->all()` ilə `Model::create()` çağırıldıqda istifadəçi `is_admin=true` kimi sahələri göndərə bilər. `$fillable` array-i ilə yalnız icazəli sahələri müəyyənləşdirirəm. Controller-də isə `$request->validated()` istifadə edirəm.

**5. Bcrypt cost factor nədir? Nə qədər olmalıdır?**

Cost factor bcrypt-in neçə iteration edəcəyini müəyyən edir. Hər vahid iki dəfə daha yavaş edir. 10-12 arası standartdır. Server hardware-ına görə 300-500ms hash vaxtı hədəflənir. `Hash::check()` vaxtı sabit saxlayır — timing attack qarşısı.

**6. Sanctum ilə Passport arasındakı fərq nədir?**

Sanctum: öz SPA və mobil tətbiqiniz üçün, sadə token-based auth. Passport: tam OAuth2 server, kənar tərəf tətbiqlərinə giriş icazəsi verəcəksinizsə lazımdır. Əksər hallarda Sanctum kifayətdir.

**7. XSS-dən Blade-də necə qorunursunuz?**

`{{ }}` sintaksisi `htmlspecialchars()` tətbiq edir — default olaraq qorunmuşdur. `{!! !!}` yalnız HTML sanitize edildikdən sonra, məsələn HTMLPurifier-dən keçirilmiş kontentlər üçün istifadə edilir.

**8. SSRF nədir? Nümunə verin.**

İstifadəçinin daxil etdiyi URL-ə `Http::get()` edilməsi. Hacker `http://169.254.169.254/latest/meta-data/` kimi daxili resurslara çata bilər. Həll: URL-i allowlist ilə yoxlamaq, daxili IP range-ləri bloklamaq.

**9. Security header-lardan hansıları ən vacibdir?**

`Content-Security-Policy` (XSS məhdudlaşdırır), `Strict-Transport-Security` (HTTPS məcburi edir), `X-Frame-Options` (clickjacking), `X-Content-Type-Options` (MIME sniffing). Bunları global middleware-də əlavə edirəm.

**10. File upload zamanı hansı təhlükəsizlik tədbirlərini görürsünüz?**

Extension-a yox, real MIME tipinə baxıram (`getMimeType()`). Orijinal fayl adını saxlamıram — random ad generasiya edirəm. Faylı `public/` xaricinə, `storage/app/private/` altına saxlayıram. Download zamanı authorization yoxlayıram. Path traversal üçün `..` sekvensalarını sanitize edirəm.

**11. Argon2 nədir, bcrypt-dən nə ilə fərqlənir?**

Argon2 memory-hard alqoritmdur — GPU ilə brute force-u çətinləşdirir. Üç variant var: Argon2i (side-channel), Argon2d (GPU), Argon2id (hər ikisi — tövsiyə edilən). Laravel `argon2id` driver-ını dəstəkləyir. Bcrypt CPU-bound, Argon2id isə həm CPU, həm RAM istifadə edir.

**12. Composer audit nə edir? CI/CD-yə necə inteqrasiya edirsiniz?**

`composer audit` paketlərinizdə bilinen zəiflikləri (CVE) yoxlayır. GitHub Actions-da push-da işlədirsəm — zəiflik tapılarsa pipeline uğursuz olur. `--no-dev` flag-ı ilə production paketlərini yoxlayıram.

---

## Anti-patternlər

**1. İstifadəçi girişini sanitize etmədən birbaşa SQL sorğusuna yerləşdirmək**
`DB::select("SELECT * FROM users WHERE id = $id")` — SQL injection ilə bütün database məhv edilə bilər. Eloquent ORM, ya da `DB::select("... WHERE id = ?", [$id])` kimi prepared statement-lər işlət.

**2. Plain text şifrə saxlamaq və ya zəif hash işlətmək**
MD5 və ya SHA1 ilə şifrə hash-ləmək — rainbow table ilə saniyələr içində sındırılır. `bcrypt` və ya `argon2id` işlət, Laravel-in `Hash::make()` metodunu istifadə et, cost faktoru konfiqurasiya et.

**3. CSRF qorumasını bütün POST route-larından deaktiv etmək**
`VerifyCsrfToken` middleware-ni global olaraq söndürmək — istifadəçinin sessiyasından icazəsiz əməliyyatlar icra edilə bilər. Yalnız `except` array-inə real webhook endpoint-lərini əlavə et, qalan bütün form POST-larında CSRF token tələb et.

**4. `.env` faylını versiya kontroluna commit etmək**
`APP_KEY`, DB şifrəsi, API key-ləri olan `.env`-i Git-ə push etmək — bütün credentials ictimai olur, real sistemlər kompromisə uğrayır. `.gitignore`-a `.env` əlavə et, `.env.example` saxla, secret-ləri Vault ya da environment variable-lər vasitəsilə idarə et.

**5. İstifadəçinin yüklədiyini faylı `public/` qovluğuna birbaşa saxlamaq**
Upload edilmiş PHP faylının `public/uploads/shell.php`-ə yazılmasına icazə vermək — server-side kodu icra edilə bilər. Faylları `storage/app/private/`-a saxla, MIME tipini yoxla, orijinal fayl adından istifadə etmə, download üçün authentication tələb et.

**6. Rate limiting olmadan authentication endpoint-lərini açıq buraxmaq**
Login, şifrə sıfırlama, OTP endpoint-lərini throttle etməmək — brute force ilə şifrələr sındırıla bilər. Laravel `throttle` middleware-ini tətbiq et, login cəhd limitini qur, şübhəli fəaliyyət üçün IP-ni müvəqqəti blokla.
