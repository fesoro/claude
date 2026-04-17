# BGP və Anycast

## Nədir? (What is it?)

**BGP** (Border Gateway Protocol) internetin ana routing protokoludur. Müxtəlif Autonomous System-lər (AS) arasında routing məlumatını paylaşır. RFC 4271-də standardlaşdırılıb. Bir çox ildir ki, internetin "öskürmə" səbəblərindəndir - BGP misconfigurasiyaları qlobal outage-lara səbəb olub (Facebook 2021, AWS bir neçə dəfə).

**Anycast** isə eyni IP ünvanının birdən çox coğrafi məkanda reklam olunma texnikasıdır. İstifadəçi pakəti ən yaxın instance-a BGP routing tərəfindən avtomatik çatdırılır. CDN, DNS və DDoS mitigation üçün əsas texnologiyadır.

```
BGP:
  AS 13335 (Cloudflare) --- AS 7922 (Comcast) --- User
  AS 15169 (Google)    ---/ 
  (AS-lər BGP ilə bir-birinə prefix-lər elan edir)

Anycast:
  Same IP 1.1.1.1 announced from:
    - San Francisco
    - Frankfurt
    - Tokyo
    - Sydney
  User packet goes to NEAREST (by BGP path)
```

## Necə İşləyir? (How does it work?)

### 1. Autonomous Systems (AS)

```
AS = Internetin müstəqil idarə olunan hissəsi (ISP, şirkət, CDN).
ASN = Autonomous System Number (16 və ya 32 bit).

Examples:
  AS 13335 - Cloudflare
  AS 15169 - Google
  AS 16509 - Amazon (AWS)
  AS 32934 - Facebook
  AS 714   - Apple
  AS 8075  - Microsoft
  AS 7922  - Comcast
  AS 29049 - Azeronline (Azerbaijan)

Check any IP's AS:
  $ whois 8.8.8.8 | grep -i origin
  origin:   AS15169
```

### 2. BGP Route Advertisement

```
Each AS announces:
  - IP prefixes it owns (e.g., 1.1.1.0/24)
  - AS_PATH (list of ASes to traverse)
  - Next hop (router to forward to)

Example:
  AS1 announces "I have 1.1.1.0/24, AS_PATH = [AS1]"
  AS2 learns, re-announces "I have 1.1.1.0/24, AS_PATH = [AS2, AS1]"
  AS3 learns, re-announces "I have 1.1.1.0/24, AS_PATH = [AS3, AS2, AS1]"

Router selects shortest AS_PATH (+ local preference, MED, etc.)
```

### 3. BGP Best Path Selection (simplified)

```
Order of preference:
1. Highest LOCAL_PREF (local policy)
2. Shortest AS_PATH (fewer hops)
3. Lowest ORIGIN (IGP > EGP > incomplete)
4. Lowest MED (multi-exit discriminator)
5. eBGP > iBGP
6. Lowest IGP metric to next hop
7. Oldest route (stability)
8. Lowest router ID (tiebreaker)
```

### 4. Anycast Routing

```
Scenario: 1.1.1.1 announced from 300 locations.

User in Istanbul:
  Turkish ISP's BGP sees 1.1.1.1 from:
    - Cloudflare Istanbul PoP (AS_PATH = [AS34984, AS13335])  <- local peering
    - Cloudflare Frankfurt    (AS_PATH = [AS3320, AS13335])
    - Cloudflare London       (AS_PATH = [AS6939, AS13335])

  Shortest path = Istanbul PoP
  User's packet routed to Istanbul (1-2ms latency)

User in Sydney:
  Australian ISP's BGP sees 1.1.1.1 from:
    - Cloudflare Sydney PoP   (closest)
  User's packet routed to Sydney (not Istanbul!)
```

### 5. Why Anycast Works for DNS/HTTP

```
DNS (UDP): Each query is a single packet, fits anycast perfectly.
  Query -> nearest server -> response -> done.

HTTP (TCP): More complex because TCP requires stateful connection.
  BGP convergence must not change path mid-connection.
  Modern BGP is stable enough that connections survive.
  QUIC's connection migration helps further.
```

## Əsas Konseptlər (Key Concepts)

### eBGP vs iBGP

```
eBGP (External BGP):
  Between different AS-es
  TTL = 1 (neighbors must be directly connected, or multihop)
  AS_PATH prepended with own AS

iBGP (Internal BGP):
  Within same AS
  Full mesh required (or route reflectors)
  AS_PATH not prepended
```

### Route Hijacking

```
Malicious AS announces prefix it doesn't own:
  Real owner: AS 100 announces 1.1.1.0/24
  Hijacker:   AS 200 announces 1.1.1.0/24 (more specific: 1.1.1.0/25)

BGP prefers more specific prefix -> traffic redirected to hijacker.

Famous incidents:
  - 2008: Pakistan Telecom accidentally hijacked YouTube globally
  - 2018: Amazon Route 53 hijacked, crypto stolen
  - 2022: Russian operator hijacked Twitter prefixes

Mitigation:
  - RPKI (Resource Public Key Infrastructure)
  - ROA (Route Origin Authorization) - cryptographically signed
  - BGP monitoring (BGPmon, RIPEstat)
```

### RPKI (BGP Security)

```
RPKI: X.509 certificates for IP prefixes.
ROA:  "This prefix can be announced by this AS."

Example ROA:
  Prefix: 1.1.1.0/24
  Origin AS: 13335 (Cloudflare)
  Max length: 24

If router sees 1.1.1.0/24 announced by AS 200:
  - ROA check fails
  - Route marked "invalid"
  - Router rejects it

Adoption: ~40% of internet routes RPKI-valid (2026).
```

### Anycast vs GeoDNS

```
GeoDNS:
  DNS returns different IP per region.
  Example: www.example.com
    - US users: 1.2.3.4
    - EU users: 5.6.7.8
  Pros: works with any app, fine-grained control.
  Cons: DNS cache, user geolocation often wrong.

Anycast:
  Same IP for everyone.
  BGP routes to nearest location.
  Pros: low latency, automatic failover.
  Cons: harder to set up (need ASN + BGP peering + multiple PoPs).

Best: combine both - GeoDNS as rough layer, anycast for fine routing.
```

### CDN Use of Anycast

```
Cloudflare model:
  - Single IP: 104.16.0.0/12
  - Announced from 300+ cities
  - User's request -> nearest PoP
  - PoP fetches from origin (or cache)

Benefits:
  - Low latency (users hit nearby PoP)
  - DDoS absorption (attack distributed across 300+ PoPs)
  - Automatic failover (if PoP dies, BGP removes it, traffic shifts)
```

### DNS Root Servers (Classic Anycast)

```
13 root DNS servers (a.root-servers.net ... m.root-servers.net)
But physically: 1700+ instances worldwide, all using same IPs via anycast.

When you query root server, you hit nearest copy automatically.
Without anycast, a single DDoS would take down DNS globally.

Check your nearest root instance:
  $ dig @k.root-servers.net hostname.bind CH TXT
  ; ANSWER: "k1.tr-ist.ripe.net"   <- Istanbul server
```

### BGP Communities

```
Meta-data tags attached to routes.

Examples:
  13335:10000   - Cloudflare: "prefer this PoP"
  0:7018        - "don't export to AT&T"
  65535:666     - RFC 7999: "blackhole this destination" (DDoS mitigation)

Providers publish community lists for customer control.
```

## PHP/Laravel ilə İstifadə

BGP/Anycast application layer-də deyil, infrastructure-də həyata keçir. Amma Laravel-də BGP-aware infrastructure-dən faydalanmaq və monitoring üçün kod yazmaq olar.

### Detect User's PoP via CDN Headers

```php
// app/Http/Middleware/DetectCdnLocation.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DetectCdnLocation
{
    public function handle(Request $request, Closure $next)
    {
        $data = [
            'cf_colo'    => $request->header('Cf-Ray'),        // e.g., "7b3c1234-IST"
            'cf_country' => $request->header('Cf-Ipcountry'),
            'cf_asn'     => $request->header('Cf-Asn'),
            'user_ip'    => $request->header('Cf-Connecting-Ip'),
        ];

        // CF-Ray format: <request-id>-<colo-code>
        // colo = airport code of PoP (IST = Istanbul, FRA = Frankfurt)
        if ($data['cf_colo']) {
            $parts = explode('-', $data['cf_colo']);
            $data['pop_code'] = end($parts);
        }

        $request->attributes->set('cdn', $data);
        return $next($request);
    }
}
```

### Look Up ASN Information

```php
// app/Services/AsnLookup.php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AsnLookup
{
    public function forIp(string $ip): ?array
    {
        return Cache::remember("asn:{$ip}", 86400, function () use ($ip) {
            // Team Cymru whois or API service
            $response = Http::get("https://api.iptoasn.com/v1/as/ip/{$ip}");

            if (! $response->ok()) return null;

            $data = $response->json();
            return [
                'asn'         => $data['as_number'] ?? null,
                'as_country'  => $data['as_country_code'] ?? null,
                'description' => $data['as_description'] ?? null,
            ];
        });
    }
}
```

### Block Known Malicious ASNs

```php
// app/Http/Middleware/BlockMaliciousAsn.php
use App\Services\AsnLookup;

class BlockMaliciousAsn
{
    private const BLOCKED_ASNS = [
        14061, // DigitalOcean (commonly used for scrapers)
        16509, // AWS (if you want to block cloud traffic)
        // Add specific bad actors
    ];

    public function __construct(private AsnLookup $asn) {}

    public function handle(Request $request, Closure $next)
    {
        $info = $this->asn->forIp($request->ip());

        if ($info && in_array($info['asn'], self::BLOCKED_ASNS)) {
            abort(403, 'Blocked network');
        }

        return $next($request);
    }
}
```

### BGP Monitoring Alert (Cron job)

```php
// app/Console/Commands/CheckBgpAnnouncements.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

class CheckBgpAnnouncements extends Command
{
    protected $signature = 'bgp:check {prefix}';
    protected $description = 'Check if IP prefix is being announced correctly';

    public function handle()
    {
        $prefix = $this->argument('prefix'); // e.g., "203.0.113.0/24"
        $expectedAsn = config('services.bgp.expected_asn');

        // Use RIPE Stat public API
        $response = Http::get('https://stat.ripe.net/data/routing-status/data.json', [
            'resource' => $prefix,
        ]);

        $origins = $response->json('data.origins', []);

        foreach ($origins as $origin) {
            if ($origin['asn'] != $expectedAsn) {
                Notification::route('slack', config('services.slack.webhook'))
                    ->notify(new \App\Notifications\BgpHijackAlert(
                        $prefix, $expectedAsn, $origin['asn']
                    ));
            }
        }

        $this->info("BGP check complete for {$prefix}");
    }
}
```

### Response Metadata (for debugging)

```php
// Show users which PoP served their request (useful for debugging)
Route::get('/api/whoami', function (Request $request) {
    return [
        'your_ip'     => $request->ip(),
        'served_from' => gethostname(),
        'cf_pop'      => $request->header('Cf-Ray'),
        'cf_country'  => $request->header('Cf-Ipcountry'),
        'timestamp'   => now()->toIso8601String(),
    ];
});
```

## Interview Sualları (Q&A)

### 1. BGP nədir və niyə vacibdir?

**Cavab:** Border Gateway Protocol - internetin əsas routing protokoludur. Autonomous System-lər (AS) arasında prefix məlumatını (kim hansı IP range-ə sahibdir) mübadilə edir. BGP olmasaydı, paketlərin qlobal şəbəkədə yolunu tapması mümkün olmazdı. 2021 Facebook outage BGP mis-configuration səbəbindən baş verdi.

### 2. Anycast nədir?

**Cavab:** Eyni IP ünvanının çoxlu coğrafi məkandan BGP ilə elan olunmasıdır. İstifadəçinin paketi ən yaxın instance-a avtomatik routə olur. Aşağı latency, DDoS qorunması və avtomatik failover təmin edir. CDN (Cloudflare 1.1.1.1), DNS root server-ləri anycast istifadə edir.

### 3. AS (Autonomous System) nədir?

**Cavab:** İnternetin müstəqil idarə olunan hissəsidir - ISP, korporativ şəbəkə və ya CDN. Hər AS unikal nömrəyə (ASN) sahibdir. Məsələn: AS 13335 Cloudflare, AS 15169 Google. AS-lər BGP ilə bir-birinə prefix-lər elan edir.

### 4. BGP route hijacking nədir?

**Cavab:** Attacker AS-i ona məxsus olmayan prefix-i elan edir. BGP daha specific (uzun) prefix-ə üstünlük verdiyi üçün trafik hijackerə yönlənir. 2008 Pakistan Telecom YouTube hijack, 2018 Amazon Route 53 hijack məşhur misallardır. **RPKI** (ROA sertifikatları) bu təhlükənin qarşısını alır.

### 5. RPKI nədir?

**Cavab:** Resource Public Key Infrastructure - IP prefix-ləri üçün kriptoqrafik identity. ROA (Route Origin Authorization) "bu prefix yalnız bu AS tərəfindən elan oluna bilər" deyir. Router RPKI-invalid route-u rədd edir. 2026 itibarilə ~40% internet route-ları RPKI-valid.

### 6. Anycast DNS üçün niyə mükəmməldir, HTTP üçün isə çətindir?

**Cavab:** DNS UDP əsaslıdır - hər sorğu müstəqil paketdir. Anycast BGP path orta dəyişsə də problem olmur. HTTP TCP əsaslıdır - connection mid-flight başqa PoP-a getsə, RST olacaq. Müasir BGP konvergensiyası kifayət qədər stabildir ki, bu nadir hal olur. QUIC connection migration daha da kömək edir.

### 7. eBGP və iBGP arasında fərq nədir?

**Cavab:**
- **eBGP** - müxtəlif AS-lər arasında. Neighbors bir-birinə direct connect olmalıdır (TTL=1). AS_PATH prepend olunur.
- **iBGP** - eyni AS daxilində. Full mesh və ya route reflector lazımdır. AS_PATH dəyişmir.

### 8. Anycast və GeoDNS arasında fərq nədir?

**Cavab:** GeoDNS DNS səviyyəsində fərqli IP qaytarır regiona görə - cache problemi var, IP geolocation dəqiq olmaya bilər. Anycast şəbəkə səviyyəsində işləyir, eyni IP BGP ilə ən yaxına routə olunur. Anycast daha dəqiq, sürətli və automatic failover təmin edir. Amma setup çətin (ASN + BGP peering + çoxlu PoP).

### 9. BGP community nədir?

**Cavab:** Route-lara əlavə olunan meta-data tag-lər. Provider-lər customer-ə routing policy nəzarəti verir: "bu AS-ə export etmə", "local preference artır", "blackhole et". Məsələn `65535:666` (RFC 7999) - bu destination-u blackhole et (DDoS-da istifadə olunur).

### 10. BGP-nin əsas zəifliyi nədir?

**Cavab:** BGP inherently "güvən əsaslı"dır - neighbor AS-ə elan etdiyi prefix-lərə etibar edilir. Authentication zəifdir (TCP MD5, BGPsec hələ də az yayılıb). Hijacking və route leak asandır. RPKI kömək edir amma yalnız origin validation-u həll edir, AS_PATH validation-ı BGPsec lazımdır ki, hələ yayılmayıb.

## Best Practices

1. **RPKI ROA yarat** - öz prefix-lərin üçün ROA imzala, hijacking-dən qorun.
2. **BGP monitoring qur** - BGPmon, ThousandEyes və ya RIPE Stat ilə prefix-lərini 24/7 izlə.
3. **Maksimum prefix filter** - peer-dən həddən artıq prefix gələndə session-u kəs (route leak qorunması).
4. **Bogon filter tətbiq et** - private IP-lər (10.0.0.0/8, 192.168.0.0/16) və martian address-lər BGP-də elan olunmamalıdır.
5. **Anycast + CDN istifadə et** - öz infrastructure-ni qurmağa ehtiyac yoxdur, Cloudflare/Fastly anycast-ı abstrakt edir.
6. **Multi-homed ol** - critical service-lər üçün 2+ ISP-yə qoş, BGP-də failover et.
7. **BGP TTL security aktivləşdir** (GTSM) - hop count yoxlaması ilə spoofing-dən qorun.
8. **Prefix-lərini /24-dən kiçik elan etmə** - çox filter olunur, /24 minimum best practice-dir.
9. **Private ASN peering-də istifadə etmə** - yalnız public ASN (1-64495, 131072-4199999999) internet üçün.
10. **DDoS mitigation-da RTBH (Remote Triggered Black Hole) hazırla** - hücum vaxtı prefix-i blackhole community ilə elan et, ISP absorb etsin.
