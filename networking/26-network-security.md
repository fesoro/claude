# Network Security

## Nədir? (What is it?)

Network security şəbəkə infrastrukturunu və məlumatları icazəsiz girişlərdən, istifadədən, dəyişdirilmədən və dağıdılmadan qorumağa yönəlmiş texnologiya, process və kontrol-lar toplusudur. Defense in depth (layered security) prinsipinə əsaslanır - bir layer break olsa, digəri qoruyur.

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

## Necə İşləyir? (How does it work?)

### 1. Firewall

```
Firewall: Network trafik-i rule-lara gore allow/deny edir.

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
   - Stateless - her packet-e ayri baxir
   - Fast, amma limited

2. Stateful Firewall (Layer 3-4)
   - Connection state track edir
   - Related traffic-ə icaze verir (ex: outbound response)
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
VPN: Public network (internet) uzerinde secure, encrypted tunnel yaradir.

User                Internet               Company Network
  |                     |                        |
  | [VPN Client]        |                        |
  |---- Encrypted ----->|                        |
  |     Tunnel          |-- [VPN Gateway] -----> |
  |                     |   (decrypt)            |
  |                     |                        |

Benefits:
  - Encryption (man-in-middle protection)
  - Authentication
  - Remote access to internal resources
  - IP masquerading
```

### 3. IPSec (Internet Protocol Security)

```
IPSec: Layer 3-də IP packet-leri encrypt və authenticate edir.

Two modes:

1. Transport Mode - Yalniz payload encrypt olunur
   [IP Header][ESP Header][Encrypted TCP+Data][ESP Trailer]

2. Tunnel Mode - Butun IP packet encrypt olunur (VPN üçün)
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
DDoS (Distributed Denial of Service): Coxlu compromised device (botnet) target-i basir.

Attack types:

1. Volumetric (Layer 3-4)
   - UDP flood, ICMP flood
   - Bandwidth-i dolduru
   - Mitigation: CDN, scrubbing centers

2. Protocol (Layer 3-4)
   - SYN flood, Ping of Death
   - Server resources tuketir
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
WAF: HTTP trafiki inspect edir, web attack-larini bloklayir.

Typical attacks WAF bloklayir:
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
  Bir server hack olsa, butun network acilir!

Segmented network (yaxsi):

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
  Physical switch-i logical olaraq ayirmaq
  VLAN 10: Finance department
  VLAN 20: Engineering
  Inter-VLAN routing yalniz icazeli
```

### 8. Zero Trust Architecture

```
Traditional (perimeter-based):
  "Inside network = trusted"
  Firewall keç, sonra free access

Zero Trust:
  "Never trust, always verify"
  Her request auth/authz keçir, hətta internal network-dən də.

Principles:
  1. Verify explicitly (MFA, device check)
  2. Least privilege access
  3. Assume breach (segmentation, monitoring)

Implementation:
  - Identity-aware proxy (Google BeyondCorp)
  - Micro-segmentation
  - Continuous verification
```

## Əsas Konseptlər (Key Concepts)

### CIA Triad

```
C - Confidentiality (məxfilik)
    - Encryption, access control

I - Integrity (bütövlük)
    - Checksums, digital signatures

A - Availability (əlçatanlıq)
    - Redundancy, DDoS protection
```

### Defense in Depth

```
Multiple layers of security:

[Perimeter] -> [Network] -> [Host] -> [App] -> [Data]

Bir layer fail olsa, digerleri qoruyur.
```

### Security Groups vs NACLs (AWS)

```
Security Group:
  - Stateful (response avtomatik allow)
  - Instance level
  - Only allow rules

Network ACL:
  - Stateless (response üçün de rule lazim)
  - Subnet level
  - Allow AND deny rules
```

### SSL/TLS Inspection

```
Problem: Encrypted traffic WAF, IDS gormur.

Solution: SSL termination at WAF/proxy:
  Client -->HTTPS--> [Proxy decrypt] --> Inspect --> [Re-encrypt] --> Server

Trade-off: Privacy concerns, certificate management.
```

### Honeypots

```
Intentionally vulnerable system to attract attackers:

  [Real systems]  <-- protected
  [Honeypot]      <-- decoy, monitored

Attacker honeypot-u hack edir, admin notification alir, tactic-lari oyrenir.
```

## PHP/Laravel ilə İstifadə

### HTTPS Enforcement

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

### Security Headers Middleware

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

### IP Whitelisting / Blacklisting

```php
// app/Http/Middleware/IpFilter.php
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
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = -1 << (32 - $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}

// Route
Route::middleware(IpFilter::class)->group(function () {
    Route::get('/admin', [AdminController::class, 'index']);
});
```

### Request Validation (CSRF Protection)

```php
// Laravel default has CSRF protection via middleware
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        \App\Http\Middleware\VerifyCsrfToken::class,
        // ...
    ],
];

// Blade:
<form method="POST">
    @csrf
    <!-- ... -->
</form>

// API-ler üçün Sanctum SPA authentication:
Route::middleware('auth:sanctum')->group(function () {
    // Protected routes
});
```

### Rate Limiting (DDoS Mitigation)

```php
// app/Providers/RouteServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;

RateLimiter::for('global', function ($request) {
    return Limit::perMinute(1000)->by($request->ip());
});

// Aggressive for auth endpoints
RateLimiter::for('login', function ($request) {
    return Limit::perMinute(5)->by($request->ip());
});
```

### Cloudflare Integration

```php
// config/trustedproxy.php - Laravel trusts Cloudflare's IP ranges
return [
    'proxies' => '*', // Trust Cloudflare
    'headers' => Request::HEADER_X_FORWARDED_ALL,
];

// Get real IP behind Cloudflare
$ip = $request->header('CF-Connecting-IP') ?: $request->ip();

// Country code from Cloudflare
$country = $request->header('CF-IPCountry');

// Block specific countries
if (in_array($country, ['XX', 'YY'])) {
    abort(403, 'Not available in your region');
}
```

### Encryption at Rest

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
        'ssn' => 'encrypted',
        'credit_card' => 'encrypted:array',
    ];
}

$user->ssn = '123-45-6789'; // automatically encrypted
echo $user->ssn;            // automatically decrypted
```

### Audit Logging

```php
class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'ip', 'user_agent', 'metadata'];
}

// Middleware
class AuditMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (auth()->check() && in_array($request->method(), ['POST', 'PUT', 'DELETE'])) {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $request->method() . ' ' . $request->path(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => json_encode([
                    'params' => $request->except(['password']),
                    'status' => $response->status(),
                ]),
            ]);
        }

        return $response;
    }
}
```

## Interview Sualları

**Q1: Firewall-in stateful və stateless fərqi nədir?**

**Stateless**: Hər packet-ə ayri baxır, connection context yoxdur. Məsələn, outbound request-in response-unu allow etmir əgər explicit rule yoxdursa. Sadə və sürətlidir.

**Stateful**: Active connection-lari track edir. Outbound request açıq-aşkar icazəlidirsə, return traffic də avtomatik icazəlidir. Modern firewall-lar əsasən stateful-dur.

**Q2: DDoS vs DoS fərqi?**

**DoS** (Denial of Service): Tək bir source-dan attack. Adəten bir server, bir IP.

**DDoS** (Distributed DoS): Çoxlu compromised device-dən (botnet) simultaneously attack. Blok etmək çətindir çünki trafik legitimate traffic kimi dağılmış olur.

Müdafiə: Anycast routing, CDN scrubbing (Cloudflare, Akamai), rate limiting, SYN cookies.

**Q3: IDS ve IPS arasındaki fərq?**

**IDS** (Intrusion **Detection** System): Passive. Trafik-i monitor edir, anomaly-lərdə alert verir. Out-of-band (network-ə paralel).

**IPS** (Intrusion **Prevention** System): Active. Inline, packet-ləri drop edə bilər. Prevention həm alert həm block.

Trade-off: IPS false positive legitimate traffic-i blok edə bilər. IDS safer amma reactive.

**Q4: WAF nədir və hansi attack-lardan qoruyur?**

WAF (Web Application Firewall) HTTP-level inspection edir. OWASP Top 10:
- SQL Injection
- XSS (Cross-Site Scripting)
- CSRF
- File inclusion
- Command injection
- Insecure deserialization

Nümunə WAF-lar: Cloudflare, AWS WAF, ModSecurity.

**Q5: VPN necə işləyir və niyə lazimdir?**

VPN public network (internet) üstündə encrypted tunnel yaradır. İşləmə:
1. Client authenticate olunur (cert, password, MFA)
2. Encrypted tunnel qurulur (IPSec, OpenVPN, WireGuard)
3. Butun trafik tunnel-dən keçir
4. VPN server-də decrypt olur və internal network-ə forward

İstifadə:
- Remote work (home-to-office)
- Site-to-site (branch offices)
- Privacy (ISP, tracking)
- Geographic bypass

**Q6: Network segmentation və zero trust fərqi?**

**Network segmentation**: Network-u hissələrə (VLAN, subnet) bölür. Inter-segment trafic firewall-dan keçir. "Inside trusted" assumption hələ də var.

**Zero Trust**: "Never trust, always verify". Hətta internal network-də də hər request auth/authz yoxlanir. Identity-based, device-based verification. BeyondCorp (Google), BeyondProd model-ləri.

Zero Trust segmentation-in təkamülüdür - micro-segmentation + continuous verification.

**Q7: Security Group vs NACL (AWS)?**

**Security Group**: Stateful, instance-level, only allow rules. Response traffic avtomatik allowed. Virtual firewall per-instance.

**NACL** (Network ACL): Stateless, subnet-level, allow AND deny rules. Rule-lar numbered (priority). Both inbound and outbound explicit.

Birlikdə: NACL perimeter üçün, SG instance-level fine-grained.

**Q8: TLS/SSL termination WAF-də niye lazimdir?**

Encrypted traffic (HTTPS) WAF deep inspection edə bilmir. Həll: WAF-də SSL termination.

1. Client --HTTPS--> WAF (decrypt)
2. WAF inspection edir (WAF rule-larini tətbiq edir)
3. WAF --HTTP/HTTPS--> Backend server

Trade-off:
- WAF-də cert management
- Privacy (compliance - PCI-DSS, HIPAA)
- Performance overhead

Alternativ: Pass-through mode (limited inspection).

**Q9: SYN flood attack-a qarşı müdafiə?**

**SYN flood**: TCP handshake-in SYN step-ini abuse edir. Attacker SYN göndərir, SYN-ACK alir, amma ACK göndərmir. Server half-open connection-lari saxlayir, table dolur.

**Müdafiə**:
1. **SYN cookies**: Server state saxlamir, cookies-də info encode olunur
2. **SYN proxy**: Load balancer complete handshake edir, sonra server-ə forward
3. **Rate limiting**: SYN-lar üçün limit
4. **Firewall drop**: Anomaly detection

**Q10: Defense in depth prinsipi nədir?**

Tək bir security layer-a güvənməmək prinsipi. Multiple layers:
1. Physical (data center security)
2. Perimeter (firewall, DDoS)
3. Network (segmentation, VLAN)
4. Host (OS hardening, antivirus)
5. Application (WAF, secure coding)
6. Data (encryption at rest/in transit)
7. Identity (MFA, RBAC)

Məntiq: Bir layer compromise olsa, attacker hələ də digərlərini keçməli. Redundancy defeating single point of failure.

## Best Practices

1. **Layered security** tətbiq et - hər layer-də kontrol, heç biri tam güvən deyil.

2. **Principle of least privilege** - user/service minimum lazim olan icazəyə malik olsun.

3. **Network segmentation** - DMZ, internal, management network ayri.

4. **Firewall rules audit** - reqular review, unused rules sil, shadow rules yoxla.

5. **Patch management** - OS, firmware, software regular update.

6. **Strong authentication** - MFA hər yerde, SSH key-based.

7. **Log və monitor** - SIEM (Security Information Event Management), anomaly detection.

8. **Incident response plan** - breach zamanı nə etmək lazim olduğunu dokumentlə.

9. **Regular penetration testing** - annual pen test, bug bounty program.

10. **Encrypted communications** - TLS 1.3, VPN, IPSec butun traffic üçün.

11. **DDoS protection layered** - Cloudflare/Akamai + rate limiting + WAF.

12. **WAF rule tuning** - default rule-ları production-a atmazdan əvvəl test et (false positives).

13. **Zero Trust principles** - internal network-ə də güvənmə, hər request verify et.

14. **Security training** - staff phishing awareness, secure coding.

15. **Backup & recovery** - ransomware attack-a qarşı offline backup-lar, tested restore process.

16. **Certificate management** - TLS cert-lər auto-renewal (Let's Encrypt), expiry monitoring.

17. **Secrets management** - Vault, AWS Secrets Manager. Kod-da secret YAZMAMAQ.

18. **Compliance** - GDPR, PCI-DSS, HIPAA requirements-i bil, audit-ə hazır ol.
