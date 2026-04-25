# Network Security (Senior)

## İcmal

Network security şəbəkə infrastrukturunu və məlumatları icazəsiz girişlərdən, istifadədən, dəyişdirilmədən və dağıdılmadan qorumağa yönəlmiş texnologiya, process və kontrol-lar toplusudur. Defense in depth (layered security) prinsipinə əsaslanır — bir layer break olsa, digəri qoruyur.

```
Layers of Network Security:

  [Physical Security]       -- Data center, hardware access
  [Perimeter Security]      -- Firewall, DDoS protection
  [Network Security]        -- VLANs, segmentation, IDS/IPS
  [Endpoint Security]       -- Antivirus, EDR
  [Application Security]    -- WAF, secure coding
  [Data Security]           -- Encryption at rest/in transit
  [Identity & Access]       -- Authentication, authorization
```

## Niyə Vacibdir

Backend developer kimi network security-ni bilmək əsas infrastruktur qərarlarında iştirak etmək üçün lazımdır: WAF qoymaq, HTTPS enforce etmək, security header-lər əlavə etmək, IP whitelisting. Bunlar yalnız DevOps-un məsuliyyəti deyil — hər developer öz tətbiqini qorumaq üçün bu konseptləri başa düşməlidir. Həmçinin, pentest tapıntılarını anlamaq üçün bu biliklər vacibdir.

## Əsas Anlayışlar

### 1. Firewall

```
Firewall: Network trafik-i rule-lara görə allow/deny edir.

Internet                Firewall              Internal Network
   |                       |                        |
   |--> TCP :80 ---------->|                        |
   |                       |-- Rule: ALLOW :80 ---> |
   |                       |                        |
   |--> TCP :22 ---------->|                        |
   |                       |-- Rule: DENY :22 (ssh) | X
   |<--- REJECT -----------|                        |

Firewall types:

1. Packet Filtering Firewall (Layer 3-4)
   - Checks IP, port, protocol
   - Stateless — hər packet-ə ayrı baxır
   - Fast, amma limited

2. Stateful Firewall (Layer 3-4)
   - Connection state track edir
   - Related traffic-ə icazə verir (ex: outbound response)
   - Modern standart

3. Application Firewall / Proxy (Layer 7)
   - HTTP, FTP content check
   - Deep packet inspection
   - Slower amma granular

4. Next-Generation Firewall (NGFW)
   - Stateful + IPS + application awareness
   - User identity awareness
   - SSL inspection
```

### 2. VPN (Virtual Private Network)

```
VPN: Public network (internet) üzərində secure, encrypted tunnel yaradır.

User                Internet               Company Network
  |                     |                        |
  | [VPN Client]        |                        |
  |---- Encrypted ----->|                        |
  |     Tunnel          |-- [VPN Gateway] -----> |
  |                     |   (decrypt)            |

Benefits:
  - Encryption (man-in-middle protection)
  - Authentication
  - Remote access to internal resources
  - IP masquerading
```

### 3. IPSec (Internet Protocol Security)

```
IPSec: Layer 3-də IP packet-ləri encrypt və authenticate edir.

Two modes:

1. Transport Mode — Yalnız payload encrypt olunur
   [IP Header][ESP Header][Encrypted TCP+Data][ESP Trailer]

2. Tunnel Mode — Bütün IP packet encrypt olunur (VPN üçün)
   [New IP Header][ESP Header][Encrypted Original IP Packet][ESP Trailer]

Protocols:
  - AH (Authentication Header): Integrity + authentication
  - ESP (Encapsulating Security Payload): Encryption + auth
  - IKE (Internet Key Exchange): Key management

Use cases:
  - Site-to-site VPN (office-to-office)
  - Remote access VPN
  - Secure routing
```

### 4. DDoS Protection

```
DDoS (Distributed Denial of Service): Çoxlu compromised device (botnet) target-i basır.

Attack types:

1. Volumetric (Layer 3-4)
   - UDP flood, ICMP flood
   - Bandwidth-i doldurur
   - Mitigation: CDN, scrubbing centers

2. Protocol (Layer 3-4)
   - SYN flood, Ping of Death
   - Server resources tükədir
   - Mitigation: SYN cookies, rate limiting

3. Application (Layer 7)
   - HTTP flood, Slowloris
   - App logic-i target edir
   - Mitigation: WAF, bot detection

Protection layers:

  [Internet]
      |
  [Cloudflare / Akamai]  <- 1st line: scrubbing, caching
      |
  [Your Load Balancer]   <- 2nd line: rate limiting
      |
  [WAF]                  <- 3rd line: Layer 7 rules
      |
  [Application]
```

### 5. WAF (Web Application Firewall)

```
WAF: HTTP trafiki inspect edir, web attack-larını bloqlayır.

Tipik attacks WAF bloqlayır:
  - SQL Injection
  - XSS (Cross-Site Scripting)
  - CSRF
  - File inclusion
  - Remote code execution
  - OWASP Top 10

Example rule (ModSecurity):
  SecRule REQUEST_URI "@contains /admin"
    "id:1001, deny, msg:'Admin access blocked'"

  SecRule ARGS "@detectSQLi"
    "id:1002, deny, msg:'SQL Injection attempt'"

Popular WAFs:
  - AWS WAF
  - Cloudflare WAF
  - ModSecurity (open source)
  - Akamai Kona
```

### 6. IDS vs IPS

```
IDS (Intrusion Detection System): Passive, monitoring only
   Network --> [IDS] --> Alert (log, notify)
                (traffic passes through)

IPS (Intrusion Prevention System): Active, blocks threats
   Network --> [IPS] --> Block / Allow
                (inline, can drop packets)

Detection methods:

1. Signature-based
   - Known attack patterns
   - Fast, amma zero-day yaxalaya bilmir

2. Anomaly-based
   - Baseline deviation detection
   - ML/statistics
   - False positive riski

3. Stateful protocol analysis
   - Protocol compliance check
```

### 7. Network Segmentation

```
Flat network (pis):
  [Internet] --> [All servers together]
  Bir server hack olsa, bütün network açılır!

Segmented network (yaxşı):

  Internet
     |
  [Firewall]
     |
     +--> DMZ (Public-facing)
     |      - Web servers
     |      - Mail servers
     |
     +--> Internal Network
     |      - DB servers
     |      - File servers
     |
     +--> Management Network
            - Admin tools
            - Monitoring

VLAN (Virtual LAN):
  Physical switch-i logical olaraq ayırmaq
  VLAN 10: Finance department
  VLAN 20: Engineering
  Inter-VLAN routing yalnız icazəli
```

### 8. Zero Trust Architecture

```
Traditional (perimeter-based):
  "Inside network = trusted"
  Firewall keç, sonra free access

Zero Trust:
  "Never trust, always verify"
  Hər request auth/authz keçir, hətta internal network-dən də.

Principles:
  1. Verify explicitly (MFA, device check)
  2. Least privilege access
  3. Assume breach (segmentation, monitoring)

Implementation:
  - Identity-aware proxy (Google BeyondCorp)
  - Micro-segmentation
  - Continuous verification
```

### CIA Triad

```
C - Confidentiality (məxfilik)
    - Encryption, access control

I - Integrity (bütövlük)
    - Checksums, digital signatures

A - Availability (əlçatanlıq)
    - Redundancy, DDoS protection
```

### Security Groups vs NACLs (AWS)

```
Security Group:
  - Stateful (response avtomatik allow)
  - Instance level
  - Only allow rules

Network ACL:
  - Stateless (response üçün də rule lazım)
  - Subnet level
  - Allow AND deny rules
```

## Praktik Baxış

**Trade-off-lar:**
- WAF false positive legitimate trafiki bloqlaya bilər — production-a qoymadan əvvəl test lazım
- SSL termination WAF-də privacy concern yaradır — compliance tələblərini yoxlayın (PCI-DSS, HIPAA)
- IPS false positive legitimate trafiki drop edə bilər — IDS-dən başlamaq daha safe

**Nə vaxt istifadə edilməməlidir:**
- Internal service-to-service trafikdə WAF overhead yaradır
- Development mühitdə strict firewall rule-lar inkişafı yavaşladır

**Anti-pattern-lər:**
- "Security through obscurity" — yalnız port gizlətmək kafi deyil
- Firewall rule-larını audit etməmək — köhnəlmiş, artıq lazım olmayan rule-lar risk yaradır
- Bütün trafiki trust etmək — internal network-dən gələn sorğular da verify edilməlidir (Zero Trust)
- Secrets-i kodda saxlamaq — `.env` ilə belə yox, Vault, AWS Secrets Manager istifadə edin

## Nümunələr

### Ümumi Nümunə

Defense in depth: Cloudflare (DDoS + WAF birinci xətt) → Load Balancer (rate limiting) → Nginx (security headers, IP filter) → Laravel (HTTPS enforce, CSRF, encryption at rest). Hər layer bir əvvəlkinin sınmasına qarşı qoruyur.

### Kod Nümunəsi

**HTTPS Enforcement:**

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\URL;

public function boot()
{
    if (app()->environment('production')) {
        URL::forceScheme('https');
    }
}

// Force HTTPS middleware
class ForceHttps
{
    public function handle($request, Closure $next)
    {
        if (!$request->secure() && app()->environment('production')) {
            return redirect()->secure($request->getRequestUri());
        }
        return $next($request);
    }
}
```

**Security Headers Middleware:**

```php
namespace App\Http\Middleware;

class SecurityHeaders
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Strict-Transport-Security',
            'max-age=31536000; includeSubDomains; preload');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy',
            'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy',
            "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");

        return $response;
    }
}
```

**IP Whitelisting / Blacklisting:**

```php
class IpFilter
{
    protected array $whitelist = [
        '192.168.1.0/24',
        '10.0.0.5',
    ];

    protected array $blacklist = [
        '1.2.3.4',
    ];

    public function handle($request, Closure $next)
    {
        $ip = $request->ip();

        if (in_array($ip, $this->blacklist)) {
            abort(403, 'Forbidden');
        }

        foreach ($this->whitelist as $allowed) {
            if ($this->ipInRange($ip, $allowed)) {
                return $next($request);
            }
        }

        abort(403, 'Your IP is not allowed');
    }

    private function ipInRange($ip, $range)
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        list($subnet, $bits) = explode('/', $range);
        $ipLong     = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask       = -1 << (32 - $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}

Route::middleware(IpFilter::class)->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});
```

**CSRF Protection:**

```php
// Laravel default-da CSRF middleware var
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\VerifyCsrfToken::class,
    ],
];

// Blade:
<form method="POST">
    @csrf
    <!-- ... -->
</form>

// API-lər üçün Sanctum SPA authentication:
Route::middleware('auth:sanctum')->group(function () {
    // Protected routes
});
```

**DDoS Mitigation (Rate Limiting):**

```php
use Illuminate\Cache\RateLimiting\Limit;

RateLimiter::for('global', function ($request) {
    return Limit::perMinute(1000)->by($request->ip());
});

// Auth endpoint-lər üçün aggressiv limit
RateLimiter::for('login', function ($request) {
    return Limit::perMinute(5)->by($request->ip());
});
```

**Cloudflare Integration:**

```php
// config/trustedproxy.php
return [
    'proxies' => '*', // Trust Cloudflare
    'headers' => Request::HEADER_X_FORWARDED_ALL,
];

// Real IP arxadan Cloudflare
$ip = $request->header('CF-Connecting-IP') ?: $request->ip();

// Country code
$country = $request->header('CF-IPCountry');

// Müəyyən ölkələri bloqlamaq
if (in_array($country, ['XX', 'YY'])) {
    abort(403, 'Not available in your region');
}
```

**Encryption at Rest:**

```php
use Illuminate\Support\Facades\Crypt;

// Encrypt
$encrypted = Crypt::encryptString('sensitive data');

// Decrypt
$decrypted = Crypt::decryptString($encrypted);

// Model-də encrypted cast (Laravel 8+)
class User extends Model
{
    protected $casts = [
        'ssn'         => 'encrypted',
        'credit_card' => 'encrypted:array',
    ];
}

$user->ssn = '123-45-6789'; // avtomatik encrypt olunur
echo $user->ssn;            // avtomatik decrypt olunur
```

**Audit Logging:**

```php
class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'ip', 'user_agent', 'metadata'];
}

class AuditMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (auth()->check() && in_array($request->method(), ['POST', 'PUT', 'DELETE'])) {
            AuditLog::create([
                'user_id'    => auth()->id(),
                'action'     => $request->method() . ' ' . $request->path(),
                'ip'         => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata'   => json_encode([
                    'params' => $request->except(['password']),
                    'status' => $response->status(),
                ]),
            ]);
        }

        return $response;
    }
}
```

## Praktik Tapşırıqlar

1. **Security headers audit:** `curl -I https://your-app.com` ilə response header-lərini yoxlayın. `securityheaders.com` saytında analiz edin. `SecurityHeaders` middleware-i əlavə edib A+ rating alın.

2. **IP whitelist üçün admin panel:** `/admin` qovluğunu yalnız müəyyən IP-lərdən əlçatan edin. Nginx-də `allow 192.168.1.0/24; deny all;` ilə Nginx-level bloklama edin. PHP middleware ilə eyni şeyi edin — iki layerli qoruma.

3. **HTTPS enforce etmək:** `URL::forceScheme('https')` əlavə edin. `ForceHttps` middleware-ini global middleware list-ə qoşun. `HSTS` header-i əlavə edin. `http://` ilə giriş etdikdə avtomatik `https://`-ə yönləndirildiyini yoxlayın.

4. **Audit logging:** `AuditMiddleware`-i `POST`, `PUT`, `DELETE` metodlarına tətbiq edin. `audit_logs` cədvəlini yaradın. `password` sahəsinin log-a düşmədiyini yoxlayın.

5. **Cloudflare setup:** Layihəni Cloudflare arxasına keçirin. `CF-Connecting-IP` header-indən real IP-ni alın. `$request->ip()` ilə müqayisə edin — Cloudflare olmadan düzgün IP göstərmədiyi görünəcək.

## Əlaqəli Mövzular

- [HTTPS / SSL / TLS](06-https-ssl-tls.md)
- [API Security](17-api-security.md)
- [API Rate Limiting](25-api-rate-limiting.md)
- [Zero Trust](33-zero-trust.md)
- [mTLS Deep Dive](35-mtls-deep-dive.md)
