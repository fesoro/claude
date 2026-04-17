# DNS over HTTPS (DoH) və DNS over TLS (DoT)

## Nədir? (What is it?)

Ənənəvi DNS (RFC 1035) **cleartext UDP port 53** üzərindən işləyir. Bütün DNS sorğuları və cavabları şifrələnməmişdir, yəni:

- **ISP** (Internet Service Provider) sizin hansı saytlara baxdığınızı görür
- **Network operator** (kafedə WiFi, məktəb, iş yeri) trafikə baxa bilər
- **MITM attacker** sorğuları görə və ya dəyişdirə bilər (DNS poisoning)
- **Sensor/Government** müəyyən domain-ləri bloklaya bilər (DNS blocking)

**DoH** (RFC 8484) və **DoT** (RFC 7858) bu problemi həll edir: DNS sorğusunu TLS tuneli içərisində şifrələyir. Resolver yalnız **son nöqtə** (Cloudflare, Google, Quad9) görür, aradakı hər kəs görmür.

```
Classic DNS (plaintext):
  Client --UDP/53--> Resolver  (ISP görür: "example.com?")

DoT (encrypted):
  Client --TLS/853--> Resolver (ISP yalnız IP və TLS handshake görür)

DoH (encrypted, looks like HTTPS):
  Client --HTTPS/443--> Resolver (ISP adi HTTPS trafik kimi görür)
```

**Əsas fərq:** DoT ayrı port (853) istifadə edir — asanlıqla fərqləndirilir/bloklana bilər. DoH adi HTTPS (443) port istifadə edir — başqa HTTPS trafikindən fərqlənmir, bloklanması çətindir.

## Necə İşləyir? (How does it work?)

### DoT (DNS over TLS) — RFC 7858

```
1. Client TCP connection açır port 853-ə (well-known DoT port)
2. TLS handshake (standard TLS 1.2 ya 1.3)
3. TLS tuneli içərisində binary DNS mesajları (wire format, RFC 1035)
4. Response TLS içərisində geri qayıdır
5. Keep-alive istifadə olunur (connection reuse, pipelining)

Packet format (after TLS):
  [2-byte length prefix][DNS message]
  [2-byte length prefix][DNS message]
  ...
```

### DoH (DNS over HTTPS) — RFC 8484

```
1. Client HTTPS connection açır port 443-ə
2. TLS handshake (usual HTTPS)
3. HTTP/2 və ya HTTP/3 üzərində DNS sorğusu göndərir

İki metod:
  GET:  /dns-query?dns=<base64url-encoded-DNS-message>
  POST: /dns-query
        Content-Type: application/dns-message
        <binary DNS wire format>

Response:
  HTTP/2 200 OK
  Content-Type: application/dns-message
  <binary DNS wire format>
```

### Example DoH Request (manual via curl)

```bash
# GET style
curl -H 'accept: application/dns-message' \
  'https://1.1.1.1/dns-query?dns=AAABAAABAAAAAAAAA3d3dwdleGFtcGxlA2NvbQAAAQAB'

# POST style (dig-produced query)
dig +short example.com | xxd  # inspect raw bytes
```

### Example DoT Connection

```bash
# kdig (knot-utils) ilə DoT test
kdig +tls @1.1.1.1 example.com

# Python ile
python3 -c "
import dns.resolver
r = dns.resolver.Resolver()
r.nameservers = ['1.1.1.1']
# dnspython 2.x+:
import dns.query, dns.message
q = dns.message.make_query('example.com', 'A')
resp = dns.query.tls(q, '1.1.1.1', port=853)
print(resp)
"
```

## Əsas Konseptlər (Key Concepts)

### Major Public Resolvers

```
Cloudflare 1.1.1.1 / 1.0.0.1 (primary privacy-focused resolver)
  DoH:  https://cloudflare-dns.com/dns-query
  DoT:  tls://1.1.1.1 (port 853)
  Privacy: 24h log retention, no identifiable data sold
  Apps:   1.1.1.1 mobile app (iOS/Android) one-tap setup

Google 8.8.8.8 / 8.8.4.4
  DoH:  https://dns.google/dns-query
  DoT:  tls://dns.google
  Privacy: logs retained longer, data used in analytics

Quad9 9.9.9.9 (privacy + malware blocking)
  DoH:  https://dns.quad9.net/dns-query
  DoT:  tls://dns.quad9.net
  Feature: blocks known-malicious domains by default

AdGuard DNS (ad blocking)
  DoH:  https://dns.adguard-dns.com/dns-query
  DoT:  tls://dns.adguard-dns.com

NextDNS (customizable filter lists)
  DoH:  https://dns.nextdns.io/<your-id>
  DoT:  tls://<your-id>.dns.nextdns.io
```

### DoH vs DoT vs DNSCrypt vs ODoH

```
DoT   (port 853):       Standard, easy to detect/block at firewall
DoH   (port 443):       Hard to block, mixed with HTTPS; browser-friendly
DNSCrypt (non-std):     Older, uses elliptic curve crypto, port varies
ODoH  (Oblivious DoH):  Client --proxy--> resolver; resolver doesn't see client IP
                        RFC 9230; Cloudflare + Apple iCloud Private Relay
```

### Browser/OS Adoption Timeline

```
2018 Mar:  Cloudflare 1.1.1.1 launch with DoH and DoT
2019 Sep:  Firefox rolls out DoH by default in USA (Cloudflare)
2020 May:  Chrome 83 "Secure DNS" (uses existing resolver if it supports DoH)
2020 May:  Windows 10 adds native DoH support (preview)
2022:      iOS 14+ supports DoH/DoT via profile; Safari respects system setting
2022:      Android 9+ has "Private DNS" (DoT only) in settings
```

### Privacy Implications (Good and Bad)

```
Good:
  + Local network (ISP, WiFi operator) can't see DNS queries
  + Harder to censor specific domains via DNS blocking
  + Prevents DNS spoofing/injection at middlebox
  + Harder to do wholesale surveillance

Bad / Nuanced:
  - Central resolver (Cloudflare, Google) sees ALL your queries
    ("Trust moved from ISP to single company")
  - ISP fingerprinting still possible via TLS SNI, IP destinations
  - Breaks parental controls / local filtering in schools & homes
  - Breaks enterprise DNS security monitoring
  - Breaks split-horizon DNS (internal vs external views)
```

### Enterprise/Network Monitoring Challenges

```
Traditional corporate network:
  User --> corp DNS --> logs, blocks malicious domains, resolves internal

After DoH in browser:
  User --DoH--> Cloudflare (bypasses corp DNS)
    Problem 1: internal domains (intranet.corp) don't resolve
    Problem 2: no malware blocking at DNS layer
    Problem 3: DLP/monitoring systems blind

Mitigations:
  1. Enterprise policies: disable DoH in Chrome/Firefox via GPO/MDM
     - Chrome: DnsOverHttpsMode = "off"
     - Firefox: network.trr.mode = 5 (off by policy)
  2. Block DoH endpoints at firewall (canary domain "use-application-dns.net")
     Firefox checks it; if blocked, Firefox falls back to system DNS
  3. Deploy own DoH server inside corporate network
  4. Use MDM to push enterprise DoH URL to devices
```

### Canary Domain (use-application-dns.net)

```
RFC draft: canary domain for DoH opt-out.

Firefox queries: use-application-dns.net
  If NXDOMAIN (normal)       -> use DoH
  If resolves to anything    -> don't auto-enable DoH (respect network policy)

Enterprise block:
  bind/unbound: zone "use-application-dns.net" { type master; ... }
  Return NXDOMAIN or any A record to signal "don't use DoH".
```

### DNS Resolver Configuration by OS

```
Linux (systemd-resolved):
  /etc/systemd/resolved.conf
    DNS=1.1.1.1#cloudflare-dns.com
    DNSOverTLS=yes

Linux (resolv.conf + stubby for DoT):
  apt install stubby
  /etc/stubby/stubby.yml -> configure upstream DoT
  /etc/resolv.conf -> nameserver 127.0.0.1

macOS:
  Install profile via config (MDM) or manually:
    Settings -> Privacy -> DNS -> Add encrypted DNS (iOS 14+)

Windows 11:
  Settings -> Network -> Ethernet/WiFi -> DNS settings
    DNS over HTTPS: On
    Preferred: 1.1.1.1

Android 9+:
  Settings -> Network -> Private DNS -> Hostname mode
    cloudflare-dns.com or dns.google
```

## PHP/Laravel ilə İstifadə

PHP-nin standart `dns_get_record()` və `gethostbyname()` funksiyaları cleartext DNS istifadə edir. DoH/DoT üçün manual HTTP client və ya binary protocol lazımdır.

### DoH Resolver Service (JSON API)

Cloudflare və Google DoH üçün JSON alternative təqdim edir (RFC 8484 wire format daha performant amma JSON debug üçün rahat):

```php
// app/Services/DohResolver.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class DohResolver
{
    public function __construct(
        private string $endpoint = 'https://cloudflare-dns.com/dns-query',
    ) {}

    public function resolve(string $name, string $type = 'A'): array
    {
        $response = Http::withHeaders([
            'Accept' => 'application/dns-json',
        ])->get($this->endpoint, [
            'name' => $name,
            'type' => $type,
        ]);

        if (! $response->ok()) {
            return [];
        }

        $data = $response->json();

        return collect($data['Answer'] ?? [])
            ->map(fn ($a) => [
                'name' => $a['name'],
                'type' => $a['type'],
                'ttl'  => $a['TTL'],
                'data' => $a['data'],
            ])
            ->toArray();
    }
}
```

### Usage in Controller

```php
// app/Http/Controllers/DnsLookupController.php
namespace App\Http\Controllers;

use App\Services\DohResolver;
use Illuminate\Http\Request;

class DnsLookupController extends Controller
{
    public function __invoke(Request $request, DohResolver $resolver)
    {
        $domain = $request->input('domain', 'example.com');
        $type   = $request->input('type', 'A');

        $records = $resolver->resolve($domain, $type);

        return response()->json([
            'domain'  => $domain,
            'type'    => $type,
            'records' => $records,
            'via'     => 'DoH (Cloudflare)',
        ]);
    }
}
```

### DoH Wire Format (RFC 8484) with Binary

```php
// app/Services/DohWireResolver.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class DohWireResolver
{
    public function resolveWire(string $name, int $type = 1): string
    {
        // Build DNS wire-format query (simplified, A record)
        $header = pack('n*', 0x1234, 0x0100, 1, 0, 0, 0);

        $question = '';
        foreach (explode('.', $name) as $label) {
            $question .= chr(strlen($label)) . $label;
        }
        $question .= chr(0) . pack('n*', $type, 1); // type, class IN

        $query = $header . $question;

        $response = Http::withHeaders([
            'Content-Type' => 'application/dns-message',
            'Accept'       => 'application/dns-message',
        ])->withBody($query, 'application/dns-message')
          ->post('https://cloudflare-dns.com/dns-query');

        return $response->body(); // raw DNS wire response
    }
}
```

### HTTP Client Pool with DoH Endpoint

```php
// config/services.php
return [
    'doh' => [
        'primary'   => env('DOH_PRIMARY',   'https://cloudflare-dns.com/dns-query'),
        'secondary' => env('DOH_SECONDARY', 'https://dns.google/dns-query'),
    ],
];

// app/Services/HighAvailabilityResolver.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class HighAvailabilityResolver
{
    public function resolve(string $name, string $type = 'A'): ?array
    {
        foreach (config('services.doh') as $endpoint) {
            try {
                $resp = Http::timeout(3)
                    ->withHeaders(['Accept' => 'application/dns-json'])
                    ->get($endpoint, compact('name', 'type'));

                if ($resp->ok() && ! empty($resp->json('Answer'))) {
                    return $resp->json('Answer');
                }
            } catch (\Throwable $e) {
                continue; // try next
            }
        }
        return null;
    }
}
```

### Blocking Outgoing DoH at Server Level

Server admin-i kimi workload-ınızın həssas data-nı xarici DoH ilə ex-filtrate etməməsi üçün:

```php
// app/Http/Middleware/BlockDohEndpoints.php
namespace App\Http\Middleware;

use Closure;

class BlockDohEndpoints
{
    private array $blockedHosts = [
        'cloudflare-dns.com',
        'dns.google',
        'dns.quad9.net',
        'dns.adguard-dns.com',
    ];

    public function handle($request, Closure $next)
    {
        $host = parse_url($request->input('url', ''), PHP_URL_HOST);
        if (in_array($host, $this->blockedHosts, true)) {
            abort(403, 'DoH endpoint blocked by policy.');
        }
        return $next($request);
    }
}
```

### Testing with HTTPie / curl from Laravel Task

```php
// app/Console/Commands/TestDoh.php
namespace App\Console\Commands;

use App\Services\DohResolver;
use Illuminate\Console\Command;

class TestDoh extends Command
{
    protected $signature = 'dns:doh {domain} {--type=A}';

    public function handle(DohResolver $resolver)
    {
        $records = $resolver->resolve($this->argument('domain'), $this->option('type'));

        $this->table(['Name', 'Type', 'TTL', 'Data'], array_map(
            fn ($r) => [$r['name'], $r['type'], $r['ttl'], $r['data']],
            $records
        ));
    }
}
```

## Interview Sualları (Q&A)

### 1. DoH və DoT arasında fərq nədir?

**Cavab:** İkisi də DNS-i şifrələyir, amma transport layer fərqlidir:
- **DoT** (port 853) TLS üzərində raw DNS binary — aydın DNS trafiki, firewall asanlıqla fərqləndirir və bloklaya bilər.
- **DoH** (port 443) HTTPS/HTTP2 üzərində DNS — adi HTTPS trafikdən fərqlənmir, bloklanması çətindir.
Enterprise üçün DoT daha yaxşıdır (görünəndir, monitor edilir). Son istifadəçi üçün DoH daha güclü məxfilik verir (censor keçmə).

### 2. DoH niyə network monitoring üçün problem yaradır?

**Cavab:** DoH brauzer içərisində işləyir və adi HTTPS trafiki kimi görünür. Korporativ DNS servisi (malware blocking, split-horizon, logging) bypass olunur. Üstəlik internal domain-lər (`jenkins.corp.local`) xarici resolver-də yoxdur. Həll: (1) Brauzer policy ilə DoH-u söndür (Chrome DnsOverHttpsMode=off, Firefox canary domain), (2) Enterprise DoH server deploy et və MDM ilə push et, (3) Firewall-da known DoH endpoint-lərini blokla (amma daim yenilənir).

### 3. Cloudflare 1.1.1.1 niyə populyardır?

**Cavab:** (1) Dünyanın ən sürətli public resolver-lərindən biridir (anycast network, 300+ şəhərdə PoP), (2) Güclü məxfilik siyasəti — 24 saat log, KPMG-nin auditi, heç bir data satışı, (3) Tam DoH/DoT dəstəyi, (4) 1.1.1.1 mobile app ilə asan qurma, (5) WARP VPN inteqrasiyası. Rəqabət üçün mənfi cəhət — bütün sorğular bir şirkətə gedir, mərkəzləşmə narahatlığı var.

### 4. ODoH (Oblivious DoH) nədir?

**Cavab:** RFC 9230 — standart DoH-un eyni zamanda mənfi cəhətini aradan qaldıran versiya. Standart DoH-da resolver bilir "kim" "nə" soruşur (IP + query). ODoH-da client sorğusunu proxy-yə göndərir, proxy resolver-ə göndərir. Client sorğusu şifrələnmiş olur (target public key ilə), proxy məzmununu görmür. Resolver query-ni görür amma client IP-ni yox. Apple iCloud Private Relay oxşar prinsipdən istifadə edir.

### 5. DoH-u brauzer səviyyəsində necə işə salırıq?

**Cavab:**
- **Firefox:** `about:config` → `network.trr.mode=2` (TRR=Trusted Recursive Resolver), `network.trr.uri=https://cloudflare-dns.com/dns-query`.
- **Chrome:** Settings → Privacy → Use secure DNS → With: Cloudflare (1.1.1.1).
- **Edge:** Settings → Privacy → Use secure DNS.
- **Safari/iOS:** Install configuration profile (NextDNS, Cloudflare üçün mövcud), və ya Settings → General → VPN & DNS → DNS.

### 6. "Canary domain" DoH üçün nədir?

**Cavab:** `use-application-dns.net` domaini Firefox-un DoH deploy-undakı network operator signal-dır. Firefox hər açılışda bu domaini yoxlayır: əgər cavab NXDOMAIN-dirsə, DoH-u açır. Əgər hər hansı A record qayıtsa, DoH-u aktiv etmir (network operator "biz DoH istəmirik" deyir). Enterprise DNS-də bu domain üçün "always return A record" zone qura bilərsiz — Firefox-lar DoH-suz qalacaq.

### 7. DoH TLS 1.3 və HTTP/3 ilə necə birləşir?

**Cavab:** Müasir DoH client-ləri TLS 1.3 (0-RTT handshake bəzən) və HTTP/2 və ya HTTP/3 (QUIC üzərində) istifadə edir. HTTP/3 xüsusilə əlverişlidir çünki QUIC UDP-də işləyir, connection migration dəstəkləyir (mobile cihaz WiFi-dan 4G-yə keçəndə), və head-of-line blocking aradan qalxır. Cloudflare hər ikisini dəstəkləyir. Birlikdə DoH + HTTP/3 ilə DNS lookup-ı <10ms anycast PoP-a düşür.

### 8. DoH-un attack surface-i nədir?

**Cavab:**
- **Mərkəzləşmə:** Bir resolver-ə çox etibar (Cloudflare, Google) — target for nation-state pressure.
- **Malware bypass:** Malware öz DoH resolver işlədib corporate monitoring-dən qaçır. Son illər malware families (Godlua, Oilrig) DoH istifadə edir C2 üçün.
- **Data exfiltration:** DNS tunneling DoH içində daha gizli olur — base64 encoded data subdomain kimi soruşula bilər.
- **Parental controls break:** Home DNS-based filtering (OpenDNS, Family Shield) artıq uşağın brauzerinə təsir etmir.

### 9. DoT-u komanda sətrindən necə test edirik?

**Cavab:**
```bash
# kdig (knot-utils)
kdig +tls @1.1.1.1 example.com

# openssl + manual DNS construction
openssl s_client -connect 1.1.1.1:853 -servername cloudflare-dns.com

# stubby (konfiqurasiya ilə)
stubby -C /etc/stubby/stubby.yml
```
DoH üçün `curl -H 'accept: application/dns-json' 'https://1.1.1.1/dns-query?name=example.com&type=A'` — anında JSON cavab görürsünüz.

### 10. Laravel backend-in xarici DoH-u cleartext DNS əvəzinə işlətməli olmağı hansı hallarda mənalıdır?

**Cavab:** Çox nadir hallarda. OS-level DNS (`resolv.conf`) artıq keşlənir və ən sürətlidir. DoH-u Laravel-dən istifadə etmək məntiqlidir:
(1) Üçüncü tərəf DNS lookup servisi qurarkən (security scanner, DMARC report),
(2) Vendor DNS-i manipulyasiya ehtimalı varsa (public WiFi, bəzi bulud provider-ləri),
(3) Third-party integration query-lərini auditsiz keşləmək lazım gəldikdə,
(4) Xüsusi network-də (container cluster) istəyirsiz ki, external DNS bypass olunsun. Əks halda, system resolver DoH/DoT ilə konfiqurasiya edin (systemd-resolved), app normal PHP funksiyalarını istifadə etsin.

## Best Practices

1. **OS səviyyəsində DoT/DoH qur** — systemd-resolved (Linux), Private DNS (Android), encrypted DNS profile (iOS). Belə hər app avtomatik yararlanır.
2. **Çox resolver qur** — primary 1.1.1.1, fallback 9.9.9.9. Tək nöqtədən asılılığı azaldır, sensorluq riskini azaldır.
3. **Enterprise-də policy qoy** — Chrome Enterprise `DnsOverHttpsMode` GPO ilə, Firefox `security.enterprise_roots.enabled` və `network.trr.mode=5`. Auditsiz DoH-u söndür.
4. **Canary domain istifadə et** — enterprise DNS-də `use-application-dns.net` üçün zone yarat. Firefox DoH-un enterprise network-də avtomatik açılmamasını təmin et.
5. **Private Relay / ODoH nəzərdən keçir** — iCloud Private Relay kimi hiybrid həll daha güclü məxfilik verir, single-point-of-trust problemi azaldır.
6. **DNS cache TTL-ə hörmət et** — DoH/DoT TTL-i dəyişdirmir. Client cache qaydaları eynidir; aşağı TTL backend-ə daha çox yük deməkdir.
7. **Third-party DoH endpoint-lərini monitor et** — Cloudflare, Google uptime yüksəkdir amma incident olur (2020 Jul Cloudflare outage). Fallback həmişə olsun.
8. **Malware/phishing list üçün Quad9 və ya NextDNS istifadə et** — DoH-un üzərinə əlavə təhlükəsizlik qatı əlavə edir.
9. **TLS sertifikatını verify et** — DoT-da resolver FQDN (`cloudflare-dns.com`) üçün pinning, DoH üçün standart HTTPS cert validation. Rogue resolver riskini azaldır.
10. **Məlumatlandırma ver** — user-lərə DoH-un nə olduğunu, məxfilik təsirini (resolver artıq sizin ISP-niz deyil, Cloudflare) izah et. Trust delegation şüurlu seçim olmalıdır.
