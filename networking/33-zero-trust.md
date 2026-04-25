# Zero Trust Security (Senior)

## İcmal

Zero Trust **"heç kəsə güvənmə, hər zaman yoxla"** prinsipinə əsaslanan security modelidir. Klassik "castle and moat" modelindən fərqli olaraq, network perimeter-ə güvənmir — istər daxili, istər xarici istifadəçi olsun, hər sorğu authenticate və authorize olunmalıdır.

Termin Forrester analyst **John Kindervag** tərəfindən 2010-da təqdim edilib. Google-un BeyondCorp layihəsi (2014) ilk geniş miqyaslı real implementation olub.

```
Traditional (Perimeter-based):       Zero Trust:

  [Internet]                         [Internet]
     |                                  |
  +-----+  <- Firewall                  v
  | DMZ |     (trust boundary)       [Policy Engine]  <- hər request yoxlanır
  +-----+                               |
     |                              Allow/Deny əsasən:
  [Internal]                          - User identity
   - İstifadəçiyə güvən               - Device posture
   - Cihaza güvən                     - Request context
   - Yenidən yoxlama yoxdur           - Resource sensitivity
```

## Niyə Vacibdir

VPN breach bütün şəbəkəni açır. Zero Trust blast radius-u məhdudlaşdırır — bir servis pozulsa, lateral movement çətindir. Remote work, cloud-native infrastruktura, microservices dövrundə perimeter yoxdur. Backend developer-i üçün: servislər arası mTLS, context-aware authorization, audit logging bu arxitekturanın praktik hissəsidir.

## Əsas Anlayışlar

### 3 Core Prinsip

```
1. Verify explicitly (açıq şəkildə doğrula)
   - Hər request-i authenticate et (user + device)
   - Sensitive resource-lar üçün MFA
   - Continuous verification (yalnız login zamanı deyil)

2. Least privilege access (minimal icazə)
   - Just-in-time (JIT) access
   - Just-enough access (JEA)
   - RBAC + ABAC

3. Assume breach (pozulma ehtimalını qəbul et)
   - Network-u seqmentlə (microsegmentation)
   - Hər şeyi şifrələ (in transit + at rest)
   - Anomaliya üçün log + monitor et
```

### Zero Trust Request Flow

```
User ----> Zero Trust Gateway ----> Application
           |
           v
      Policy Decision Point
           |
    Yoxlamalar:
    - Identity (SSO + MFA)
    - Device posture (patch olunub? EDR işləyir?)
    - Location (geo, IP reputation)
    - Time (iş saatlarıdır?)
    - Resource sensitivity
    - Behavioral anomaly
           |
    Qərar: Allow | Deny | Challenge (MFA)
           |
           v
      Log + Audit
```

### Continuous Verification

```
Köhnə model:
  Bir dəfə login → 8 saat session etibarlıdır
  (cookie oğurlanırsa, attacker 8 saat vaxtı var)

Zero Trust modeli:
  Hər request yenidən qiymətləndirilir:
    - Session hələ etibarlıdır?
    - Device posture dəyişib?
    - User location anomal görünür?
    - Behavior-da anomaliya?
  Session ortada revoke oluna bilər.
```

### BeyondCorp (Google modeli)

```
Əsas yeniliklər:
1. Daxili app-lar üçün VPN yoxdur
2. Hər daxili app Identity-Aware Proxy (IAP) arxasındadır
3. Giriş əsasdır:
   - User identity (Google account)
   - Device trust (managed, encrypted, patched)
   - Context (risk signals)
4. İstənilən şəbəkədən işləyir (kafe, ev, ofis)
5. Chrome Enterprise, GCP IAP kimi məhsullaşıb

Nəticə: İşçilər VPN olmadan hər yerdən işləyir.
```

### ZTNA (Zero Trust Network Access)

```
VPN-in əvəzləyicisi:

VPN:                          ZTNA:
  User → VPN → Network          User → ZTNA Broker → Specific App
  Tam network girişi              Yalnız icazəli app-a giriş
  IP-based ACL                    Identity + context-based
  Statik                          Dinamik, session başına

Misal: Cloudflare Access, Tailscale, Twingate, Zscaler Private Access
```

### Microsegmentation

```
Ənənəvi flat network:
  Web -- App -- Database
  (hamı hər şeyə danışa bilər, lateral movement asandır)

Microsegmented:
  Web ---[policy]--- App ---[policy]--- DB
  - Web yalnız App-ı 8080-dən çağıra bilər
  - App yalnız DB-ni 5432-dən çağıra bilər
  - Digər axınlara icazə verilmir

İmplementasiya:
  - Kubernetes NetworkPolicies
  - Service Mesh (Istio, Linkerd)
  - Cloud native: AWS Security Groups, GCP VPC firewalls
```

### Service Mesh-in rolu

```
Service mesh (Istio, Linkerd) microserviceslər üçün Zero Trust implementasiya edir:

1. mTLS everywhere (avtomatik sertifikat verilməsi)
2. Service identity (SPIFFE ID: spiffe://cluster.local/ns/prod/sa/api)
3. Fine-grained authorization (kim nəyi çağıra bilər)
4. Observability (hər çağırış loglanır)
5. Policy as code (AuthorizationPolicy CRD-lər)

Istio policy nümunəsi:
  apiVersion: security.istio.io/v1
  kind: AuthorizationPolicy
  spec:
    selector: { matchLabels: { app: payment } }
    rules:
    - from:
      - source:
          principals: ["cluster.local/ns/prod/sa/orders"]
      to:
      - operation:
          methods: ["POST"]
          paths: ["/charge"]
```

### Trust Algorithm (Dynamic Risk Scoring)

```
Amillər:
  + Valid MFA              (+50 trust)
  + Managed device         (+30)
  + Known IP / location    (+20)
  - New device             (-40)
  - Anomalous behavior     (-30)
  - Off-hours access       (-10)

Score 80+: Tam giriş
Score 50-79: MFA tələb et
Score < 50: Rədd et
```

## Praktik Baxış

- **Incremental migration:** "Big bang" deyil — pilot app-lardan başla, tədricən genişləndir. Perimeter-i birdən silmə.
- **Legacy app-lar çətindir:** Zero Trust-ı hər app-a tətbiq etmək lazımdır, köhnə app-lar identity-aware olmaya bilər.
- **Performance overhead:** Hər request policy check — latency artır. Caching və token-based check-lər ilə azalt.
- **Bahalıdır:** SASE, EDR, SIEM, MDM kimi çoxlu alət tələb olunur. Pragmatik başla: SSO + MFA + Cloudflare Access.
- **User experience:** Çox MFA prompt istifadəçini bezdirir. Risk score-a görə adaptiv MFA qur.

### Anti-patterns

- VPN-i bir anda söküb Zero Trust qurmaq — disruption böyük olur
- Microsegmentation olmadan "Zero Trust var" iddiası — flat network hələ də lateral movement-ə açıqdır
- Audit log olmadan — anomaly detection mümkün deyil
- Permanent admin hesabları — JIT access tətbiq et

## Nümunələr

### Ümumi Nümunə

```
Cloudflare Access + Laravel arxitekturası:

  [İstifadəçi] → [Cloudflare Access] → [Laravel App]
                      |
              JWT header əlavə edir:
              Cf-Access-Jwt-Assertion: eyJ...

Laravel:
  1. JWT-ni JWKS ilə verify et
  2. Email/identity çıxar
  3. Context-aware authorization tətbiq et
```

### Kod Nümunəsi

**Cloudflare Access Middleware:**
```php
// app/Http/Middleware/CloudflareAccess.php
namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CloudflareAccess
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('Cf-Access-Jwt-Assertion');
        abort_unless($token, 401, 'Missing Cloudflare Access token');

        $teamDomain = config('services.cloudflare.team_domain');
        $jwks = Cache::remember('cf-jwks', 3600, fn () =>
            Http::get("{$teamDomain}/cdn-cgi/access/certs")->json()
        );

        try {
            $decoded = JWT::decode($token, JWK::parseKeySet($jwks));
        } catch (\Throwable $e) {
            abort(401, 'Invalid Cloudflare Access token');
        }

        $request->attributes->set('cf_user_email', $decoded->email);
        return $next($request);
    }
}
```

**Context-Aware Authorization (Laravel Gate):**
```php
// app/Providers/AuthServiceProvider.php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('access-payment-admin', function ($user, Request $request) {
        // Identity
        if (! $user->hasRole('finance-admin')) return false;

        // MFA yeni olmalıdır (1 saat)
        if (! $user->mfa_verified_at || $user->mfa_verified_at->lt(now()->subHour())) {
            return false;
        }

        // İş saatları (UTC 08-18)
        $hour = now()->utc()->hour;
        if ($hour < 8 || $hour > 18) return false;

        // Tanınan IP range
        $allowedIps = config('security.payment_admin_ips');
        if (! in_array($request->ip(), $allowedIps)) return false;

        // Device attestation header (MDM və ya ZTNA broker tərəfindən)
        if ($request->header('X-Device-Posture') !== 'managed-compliant') {
            return false;
        }

        return true;
    });
}
```

**Zero Trust Signals Logging:**
```php
// app/Http/Middleware/ZeroTrustSignals.php
use Illuminate\Support\Facades\Log;

class ZeroTrustSignals
{
    public function handle(Request $request, Closure $next)
    {
        Log::channel('zero-trust')->info('request', [
            'user_id'    => $request->user()?->id,
            'ip'         => $request->ip(),
            'country'    => $request->header('Cf-Ipcountry'),
            'user_agent' => $request->userAgent(),
            'device'     => $request->header('X-Device-Id'),
            'posture'    => $request->header('X-Device-Posture'),
            'asn'        => $request->header('Cf-Asn'),
            'path'       => $request->path(),
            'method'     => $request->method(),
        ]);

        return $next($request);
    }
}
```

**Service-to-Service mTLS (Laravel):**
```php
// config/services.php
'billing' => [
    'url'  => env('BILLING_URL'),
    'cert' => env('MTLS_CLIENT_CERT'),
    'key'  => env('MTLS_CLIENT_KEY'),
    'ca'   => env('MTLS_CA'),
],

// Internal servis-ə mTLS ilə çağırış
$response = Http::withOptions([
    'cert'    => config('services.billing.cert'),
    'ssl_key' => config('services.billing.key'),
    'verify'  => config('services.billing.ca'),
])->post(config('services.billing.url') . '/charge', [
    'amount' => 1000,
]);
```

**Short-lived Sessions:**
```php
// config/session.php
'lifetime'        => 15,   // 15 dəqiqə
'expire_on_close' => true,

// Sensitive action-lar üçün re-auth
Route::middleware(['auth', 'password.confirm'])->group(function () {
    Route::post('/admin/delete-user', [AdminController::class, 'deleteUser']);
});
```

## Praktik Tapşırıqlar

1. **SSO + MFA aktiv et:** Daxili alətlər (Jira, Notion, admin panel) üçün Google Workspace SSO + MFA qur. İlk addım ən vacibdir.

2. **Cloudflare Access qur:** Bir daxili app-ı (məs: Laravel admin panel) Cloudflare Access arxasına qoy. JWT validation middleware-i yaz.

3. **Context-aware gate yaz:** `Gate::define` ilə yuxarıdakı kimi MFA time, business hours, IP check tətbiq edən gate yaz.

4. **mTLS service-to-service:** İki Laravel servis arasında mTLS qur. CA yarat, hər servisə sertifikat ver, `Http::withOptions()` ilə çağırış et.

5. **Audit log qur:** `ZeroTrustSignals` middleware-i əlavə et, SIEM-ə göndər (Datadog, Elastic). Anomalous IP, off-hours request-lər üçün alert yaz.

6. **Microsegmentation planla:** Öz servislərin üçün "kim kimi çağıra bilər" matrix-ini çək. Hansı portlar açıq olmalıdır, hansılar bağlı?

## Əlaqəli Mövzular

- [API Security](17-api-security.md)
- [OAuth2](14-oauth2.md)
- [JWT](15-jwt.md)
- [mTLS Deep Dive](35-mtls-deep-dive.md)
- [Network Security](26-network-security.md)
