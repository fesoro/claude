# Zero Trust Security

## Nədir? (What is it?)

Zero Trust **"heç kəsə güvənmə, hər zaman yoxla"** prinsipinə əsaslanan security modelidir. Klassik "castle and moat" modelindən fərqli olaraq, network perimeter-ə güvənmir - istər daxili, istər xarici istifadəçi olsun, hər sorğu authenticate və authorize olunmalıdır.

Termin Forrester analyst **John Kindervag** tərəfindən 2010-da təqdim edilib. Google-un BeyondCorp layihəsi (2014) ilk geniş miqyaslı real implementation olub.

```
Traditional (Perimeter-based):       Zero Trust:
                                     
  [Internet]                         [Internet]
     |                                  |
  +-----+  <- Firewall                  v
  | DMZ |     (trust boundary)       [Policy Engine]  <- every request verified
  +-----+                               |
     |                              Allow/Deny based on:
  [Internal]                          - User identity
   - Trust users                      - Device posture
   - Trust devices                    - Request context
   - No re-verification               - Resource sensitivity
```

## Necə İşləyir? (How does it work?)

### 1. Core Principles

```
1. Verify explicitly
   - Authenticate every request (user + device)
   - MFA for sensitive resources
   - Continuous verification (not just at login)

2. Least privilege access
   - Just-in-time (JIT) access
   - Just-enough access (JEA)
   - Role-based + attribute-based (RBAC + ABAC)

3. Assume breach
   - Segment network (microsegmentation)
   - Encrypt everything (in transit + at rest)
   - Log + monitor for anomalies
```

### 2. Zero Trust Request Flow

```
User ----> Zero Trust Gateway ----> Application
           |
           v
      Policy Decision Point
           |
    Checks:
    - Identity (SSO + MFA)
    - Device posture (patched? EDR running?)
    - Location (geo, IP reputation)
    - Time (business hours?)
    - Resource sensitivity
    - Behavioral anomaly
           |
    Decision: Allow | Deny | Challenge (MFA)
           |
           v
      Log + Audit
```

### 3. Continuous Verification

```
Old model:
  Login once -> session valid for 8 hours
  (if cookie stolen, attacker has 8 hours)

Zero Trust model:
  Each request re-evaluated:
    - Is session still valid?
    - Has device posture changed?
    - Has user location changed dramatically?
    - Anomaly detected in behavior?
  Session can be revoked mid-flight.
```

## Əsas Konseptlər (Key Concepts)

### BeyondCorp (Google's Model)

```
Key innovations:
1. No VPN for internal apps
2. Every internal app behind an Identity-Aware Proxy (IAP)
3. Access based on:
   - User identity (Google account)
   - Device trust (managed, encrypted, patched)
   - Context (risk signals)
4. Works from ANY network (coffee shop, home, office)
5. Open-sourced as Chrome Enterprise, GCP IAP

Result: Employees work from anywhere without VPN.
```

### SASE (Secure Access Service Edge)

```
Pronounced "sassy". Coined by Gartner 2019.

SASE = Network + Security, delivered as cloud service.

Components:
  - SD-WAN (software-defined networking)
  - SWG (Secure Web Gateway)
  - CASB (Cloud Access Security Broker)
  - ZTNA (Zero Trust Network Access)
  - FWaaS (Firewall as a Service)

Vendors: Zscaler, Cloudflare, Netskope, Palo Alto Prisma

User -> nearest SASE PoP -> security checks -> destination
(replaces MPLS, VPN, on-premise firewall)
```

### ZTNA (Zero Trust Network Access)

```
Replaces VPN for application access.

VPN:                          ZTNA:
  User -> VPN -> Network        User -> ZTNA Broker -> Specific App
  Full network access             Access only to allowed app
  IP-based ACL                    Identity + context-based
  Static                          Dynamic, per-session

Examples: Cloudflare Access, Tailscale, Twingate, Zscaler Private Access
```

### Microsegmentation

```
Traditional flat network:
  Web -- App -- Database
  (all can talk to all, lateral movement easy)

Microsegmented:
  Web ---[policy]--- App ---[policy]--- DB
  - Web can only call App on port 8080
  - App can only call DB on port 5432
  - No other flows allowed

Implementation:
  - Kubernetes NetworkPolicies
  - Service Mesh (Istio, Linkerd)
  - Cloud native: AWS Security Groups, GCP VPC firewalls
  - Host-based: iptables, Illumio
```

### Service Mesh Role in Zero Trust

```
Service mesh (Istio, Linkerd) implements Zero Trust for microservices:

1. mTLS everywhere (automatic certificate issuance)
2. Service identity (SPIFFE ID: spiffe://cluster.local/ns/prod/sa/api)
3. Fine-grained authorization (who can call what)
4. Observability (every call logged)
5. Policy as code (AuthorizationPolicy CRDs)

Example Istio policy:
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

### Identity-Based Access

```
Old: IP-based firewall rules
  "Allow 10.0.0.5 to access database"

Zero Trust: Identity-based
  "Allow user alice@company.com using managed laptop
   to access payment-db during business hours from US"

Identity sources:
  - Humans: Okta, Azure AD, Google Workspace
  - Services: SPIFFE/SPIRE, workload identity
  - Devices: Device certificates, MDM
```

### Trust Algorithm (Dynamic Risk Scoring)

```
Factors:
  + Valid MFA              (+50 trust)
  + Managed device         (+30)
  + Known IP / location    (+20)
  - New device             (-40)
  - Anomalous behavior     (-30)
  - Off-hours access       (-10)

Score 80+: Full access
Score 50-79: Require MFA
Score < 50: Deny
```

## PHP/Laravel ilə İstifadə

Laravel app-ı Zero Trust architecture-də genelde **arxada** qalır, ZTNA broker (Cloudflare Access, Google IAP) qarşısında. Laravel tərəfində kontekst-aware authorization-u gücləndiririk.

### Cloudflare Access + Laravel

Cloudflare Access JWT header kimi identity göndərir.

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

        // $decoded->email, $decoded->sub, $decoded->identity_nonce
        $request->attributes->set('cf_user_email', $decoded->email);

        return $next($request);
    }
}
```

### Context-Aware Authorization (Laravel Gate)

```php
// app/Providers/AuthServiceProvider.php
use Illuminate\Support\Facades\Gate;

public function boot(): void
{
    Gate::define('access-payment-admin', function ($user, Request $request) {
        // Identity
        if (! $user->hasRole('finance-admin')) return false;

        // MFA recent?
        if (! $user->mfa_verified_at || $user->mfa_verified_at->lt(now()->subHour())) {
            return false;
        }

        // Business hours (UTC 08-18)
        $hour = now()->utc()->hour;
        if ($hour < 8 || $hour > 18) return false;

        // Known IP range
        $allowedIps = config('security.payment_admin_ips');
        if (! in_array($request->ip(), $allowedIps)) return false;

        // Device attestation header (set by MDM or ZTNA broker)
        if ($request->header('X-Device-Posture') !== 'managed-compliant') {
            return false;
        }

        return true;
    });
}
```

### Request Signals Logging (for anomaly detection)

```php
// app/Http/Middleware/ZeroTrustSignals.php
use Illuminate\Support\Facades\Log;

class ZeroTrustSignals
{
    public function handle(Request $request, Closure $next)
    {
        Log::channel('zero-trust')->info('request', [
            'user_id'     => $request->user()?->id,
            'ip'          => $request->ip(),
            'country'     => $request->header('Cf-Ipcountry'),
            'user_agent'  => $request->userAgent(),
            'device'      => $request->header('X-Device-Id'),
            'posture'     => $request->header('X-Device-Posture'),
            'asn'         => $request->header('Cf-Asn'),
            'path'        => $request->path(),
            'method'      => $request->method(),
        ]);

        return $next($request);
    }
}
```

### Service-to-Service mTLS (Laravel Guzzle)

```php
// config/services.php
'billing' => [
    'url' => env('BILLING_URL'),
    'cert' => env('MTLS_CLIENT_CERT'),
    'key'  => env('MTLS_CLIENT_KEY'),
    'ca'   => env('MTLS_CA'),
],

// Call internal service with mTLS
$response = Http::withOptions([
    'cert'   => config('services.billing.cert'),
    'ssl_key'=> config('services.billing.key'),
    'verify' => config('services.billing.ca'),
])->post(config('services.billing.url').'/charge', [
    'amount' => 1000,
]);
```

### Short-lived Sessions

```php
// config/session.php
'lifetime' => 15,            // 15 minutes
'expire_on_close' => true,

// Re-auth for sensitive actions
Route::middleware(['auth', 'password.confirm'])->group(function () {
    Route::post('/admin/delete-user', [AdminController::class, 'deleteUser']);
});
```

## Interview Sualları (Q&A)

### 1. Zero Trust nədir?

**Cavab:** Security modelidir ki, network perimeter-ə güvənmir - hər request-i authenticate və authorize edir, istər daxili, istər xarici olsun. 3 əsas prinsip: (1) explicit verify, (2) least privilege, (3) assume breach. "Never trust, always verify" şüarı.

### 2. Zero Trust və VPN arasında fərq nədir?

**Cavab:** VPN network-ə full access verir - bir dəfə daxil olandan sonra istənilən resurs-a çata bilirsən (flat trust). Zero Trust (ZTNA) hər tətbiqə ayrıca, konteksttə (user + device + context) əsaslanaraq icazə verir. VPN IP-based, ZTNA identity-based-dir. VPN breach bütün şəbəkəni açır, ZTNA blast radius-u məhdudlaşdırır.

### 3. BeyondCorp nədir?

**Cavab:** Google-un Zero Trust implementasiyasıdır (2014). Heç bir VPN yoxdur, bütün internal app-lar Identity-Aware Proxy arxasındadır. Access: user identity + device trust + context əsasında. İşçilər harda işləsə də (kafe, ev, ofis) eyni security applying olur. Chrome Enterprise və GCP IAP kimi məhsulluşub.

### 4. SASE nədir?

**Cavab:** Secure Access Service Edge - network + security-ni cloud service kimi birləşdirən arxitekturadır. Komponentlər: SD-WAN, SWG, CASB, ZTNA, FWaaS. User ən yaxın SASE PoP-a qoşulur, oradan security checks + routing. Köhnə MPLS + VPN + on-prem firewall yığımını əvəz edir. Vendors: Zscaler, Cloudflare, Palo Alto Prisma.

### 5. Microsegmentation nədir?

**Cavab:** Network-u kiçik zonalara ayırıb hər zonaya sərt access policy tətbiq etməkdir. Lateral movement-in qarşısını alır - əgər attacker bir servis-ə düşsə, digərinə keçə bilmir. Kubernetes NetworkPolicy, Istio AuthorizationPolicy, AWS Security Groups misal kimi.

### 6. Service mesh Zero Trust-da hansı rolu oynayır?

**Cavab:** Microservice-lər arasında mTLS-i avtomatik tətbiq edir (hər servisə sertifikat paylayır), identity verir (SPIFFE ID), fine-grained authorization (AuthorizationPolicy), və hər çağırışı loglayır. Zero Trust for east-west traffic (servislər arası) implement edir. Istio, Linkerd, Consul Connect nümunələrdir.

### 7. Continuous verification nə deməkdir?

**Cavab:** Klassik modeldə login bir dəfə olur və session 8 saat davam edir. Zero Trust-da hər request yenidən yoxlanılır: session hələ etibarlıdır? Device posture dəyişib? User location anomal göründü? Bu, cookie oğurluğu və session hijacking təsirini minimuma endirir. Real-time risk scoring tətbiq olunur.

### 8. SPIFFE/SPIRE nədir?

**Cavab:** **SPIFFE** (Secure Production Identity Framework For Everyone) workload-lar üçün universal identity standartıdır. `spiffe://trust-domain/path` formatı. **SPIRE** bu standart-ın reference implementation-u - hər workload-a short-lived X.509 sertifikat paylayır. Service mesh (Istio) ilə integrate olur. Hər microservice unikal kriptoqrafik identity-ə sahibdir.

### 9. Zero Trust-ı kiçik komandaya necə tətbiq etmək olar?

**Cavab:** Pragmatik addımlar: (1) SSO + MFA bütün daxili alətlərə, (2) VPN əvəzinə Cloudflare Access / Tailscale istifadə et, (3) least privilege IAM rolları, (4) mTLS service-to-service üçün, (5) audit log-u mərkəzləşdir. Big-bang yox, incremental migration.

### 10. Zero Trust-ın çətinlikləri nələrdir?

**Cavab:** (1) Kompleks implementation - bütün app-lar identity-aware olmalıdır, (2) legacy app-lar çətin uyğunlaşır, (3) performance overhead (hər request policy check), (4) user experience (çoxlu MFA prompt-ları), (5) bahadır - SASE, EDR, SIEM, MDM kimi çoxlu alət tələb olur.

## Best Practices

1. **SSO + MFA bütün resurslara tətbiq et** - identity fundament-dir, tək parol kifayət deyil.
2. **Device trust əlavə et** - MDM və ya EDR ilə cihaz posture-unu yoxla, managed olmayan cihazdan sensitive resurs-a girişi bağla.
3. **Least privilege + JIT access** - permanent admin yoxdur, sadə task-lar üçün temporary elevation istifadə et.
4. **Microsegmentation tətbiq et** - flat network təhlükəlidir, blast radius-u məhdudlaşdır.
5. **mTLS service-to-service** - internal traffic da şifrəli və authenticated olmalıdır.
6. **Log hər şeyi** - SIEM-ə göndər (Splunk, Elastic, Datadog), anomaly detection qurn.
7. **Short-lived credentials** - statik password/token yerinə qısa TTL-li cert və JWT-lər.
8. **Policy as code** - manual firewall rule əvəzinə Git-də saxla (Istio policy, OPA, Terraform).
9. **Continuous verification** - session mid-flight revoke oluna bilməlidir, risk score real-time yenilənməlidir.
10. **Incremental migration planla** - perimeter-i bir anda silmə, pilot app-lardan başla, tədricən genişləndir.
