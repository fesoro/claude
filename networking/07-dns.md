# DNS - Domain Name System (Junior)

## İcmal

DNS internet-in "telefon kitabçası"dır. İnsan üçün oxunaqlı domain adlarını (məsələn, google.com) IP adreslərinə (məsələn, 142.250.185.78) çevirən distributed, hierarchical sistemdir. DNS olmadan hər dəfə website-a daxil olmaq üçün IP adresi yadda saxlamalıydınız.

DNS 1983-cü ildə Paul Mockapetris tərəfindən yaradılıb (RFC 1035). Distributed database olaraq dizayn edilib — heç bir single server bütün DNS məlumatlarını saxlamır.

```
İstifadəçi brauzerdə yazır:  www.example.com
                                  |
                                  v
                          DNS Resolution
                                  |
                                  v
                          IP: 93.184.216.34
                                  |
                                  v
                          HTTP request göndərilir
```

## Niyə Vacibdir

Backend developer-in DNS ilə birbaşa işi bir neçə yerdə olur: deployment zamanı DNS TTL-ni planlamaq, email konfiqurasiyası üçün MX/SPF/DKIM record-larını qurmaq, application-da DNS lookup-ları keşləmək (hər request-də `gethostbyname()` çağırmaq lazım deyil), migration zamanı blue-green switching üçün TTL-ni əvvəlcədən azaltmaq. Bundan əlavə, DNS poisoning attack-larını başa düşmək security məsuliyyətinizə daxildir.

## Əsas Anlayışlar

### DNS Hierarchy (İyerarxiyası)

```
                    . (Root)
                   /|\
                  / | \
                 /  |  \
              .com .org .net .az    (TLD - Top Level Domain)
              /|        |
             / |        |
       google example  wikipedia   (Second Level Domain)
          /      |
         /       |
       www      mail               (Subdomain)
```

### DNS Resolution Prosesi (Recursive Query)

```
Browser         Local DNS        Root DNS       TLD DNS (.com)    Authoritative DNS
  |             Resolver         Server          Server           (example.com)
  |                |                |               |                  |
  |-- 1. Query --->|                |               |                  |
  |  example.com   |                |               |                  |
  |                |-- 2. Query --->|               |                  |
  |                |  example.com   |               |                  |
  |                |<-- 3. Refer ---|               |                  |
  |                |  .com TLD NS   |               |                  |
  |                |                                |                  |
  |                |---------- 4. Query ----------->|                  |
  |                |           example.com          |                  |
  |                |<--------- 5. Refer ------------|                  |
  |                |       example.com NS           |                  |
  |                |                                                   |
  |                |------------------ 6. Query ---------------------->|
  |                |                  example.com                      |
  |                |<----------------- 7. Answer ---------------------|
  |                |                  93.184.216.34                    |
  |<-- 8. Answer --|                                                   |
  |  93.184.216.34 |                                                   |
```

Addım-addım proses:

1. **Browser Cache** — Brauzer əvvəlcə sorğulanmış DNS-i yoxlayır
2. **OS Cache** — Əməliyyat sisteminin DNS cache-i yoxlanır (`/etc/hosts` da burda)
3. **Recursive Resolver** — ISP-nin DNS resolver-inə sorğu göndərilir
4. **Root Server** — 13 root server clusteri var (a.root-servers.net — m.root-servers.net)
5. **TLD Server** — .com, .org, .az kimi TLD server-lər
6. **Authoritative Server** — Domain-in öz DNS server-i son cavabı verir

### DNS Record Types

| Record | Məqsəd | Nümunə |
|--------|--------|--------|
| **A** | Domain -> IPv4 | `example.com -> 93.184.216.34` |
| **AAAA** | Domain -> IPv6 | `example.com -> 2606:2800:220:1:...` |
| **CNAME** | Alias (başqa domain-ə yönləndirir) | `www.example.com -> example.com` |
| **MX** | Mail server | `example.com -> mail.example.com (priority: 10)` |
| **TXT** | Text record (SPF, DKIM, verification) | `"v=spf1 include:_spf.google.com ~all"` |
| **NS** | Nameserver | `example.com -> ns1.example.com` |
| **SOA** | Start of Authority (zone info) | Serial, refresh, retry, expire |
| **SRV** | Service location | `_sip._tcp.example.com -> sipserver.example.com:5060` |
| **PTR** | Reverse DNS (IP -> Domain) | `34.216.184.93 -> example.com` |
| **CAA** | Certificate Authority Authorization | `example.com CAA 0 issue "letsencrypt.org"` |

### TTL (Time To Live)

TTL DNS record-un neçə müddət cache-də saxlanacağını göstərir (saniyə ilə):

```
; Yüksək TTL (1 gün) - nadir dəyişən record-lar üçün
example.com.    86400   IN  A   93.184.216.34

; Aşağı TTL (5 dəqiqə) - tez-tez dəyişən record-lar üçün
api.example.com.  300   IN  A   10.0.0.1

; Çox aşağı TTL (60 san) - failover/migration zamanı
staging.example.com. 60 IN  A   10.0.0.2
```

TTL Strategy:
- Production: 3600-86400 (1 saat — 1 gün)
- Migration öncəsi: TTL-i əvvəlcədən azaldın (300-ə)
- Failover: 60-300

### DNSSEC (DNS Security Extensions)

DNSSEC DNS cavablarının authenticity-sini yoxlayır. DNS cache poisoning-in qarşısını alır.

```
Adi DNS:
  Client -> Resolver: "example.com?"
  Resolver -> Client: "93.184.216.34"  (heç bir doğrulama yoxdur!)

DNSSEC ilə:
  Client -> Resolver: "example.com?"
  Resolver -> Client: "93.184.216.34" + RRSIG (digital signature)
  Client: Signature-i DNSKEY ilə verify edir ✓
```

DNSSEC Record Types:
- **RRSIG** — Record-un digital signature-i
- **DNSKEY** — Zone-un public key-i
- **DS** — Delegation Signer (parent zone-a link)
- **NSEC/NSEC3** — Non-existence proof

### DNS Caching Səviyyələri

```
1. Browser Cache        (Chrome: chrome://net-internals/#dns)
2. OS Cache             (Linux: systemd-resolved, Windows: ipconfig /displaydns)
3. Router Cache         (Ev router-i öz cache-i saxlayır)
4. ISP Resolver Cache   (ISP-nin recursive resolver-i)
5. Authoritative TTL    (Domain sahibinin təyin etdiyi TTL)
```

### DNS Query Types

- **Recursive Query** — Resolver tam cavab tapmalıdır (client -> resolver)
- **Iterative Query** — Resolver "bilmirəm, bura sor" deyir (resolver -> root/TLD/auth)
- **Inverse Query** — IP-dən domain tapma (PTR record)

## Praktik Baxış

**Real layihələrdə istifadəsi:**
- Deployment öncəsi TTL-i 5 dəqiqəyə endirmək (production switch sonra geri qaldırmaq)
- Email spam filter-lərini keçmək üçün SPF, DKIM, DMARC record-larını düzgün qurmaq
- Load balancing üçün Round-robin DNS — bir domain üçün bir neçə A record (health check olmadan sadə)
- Wildcard DNS (`*.staging.example.com`) inkişaf mühitlərini asanlaqla idarə edir

**Trade-off-lar:**
- Yüksək TTL: daha az DNS sorğu, performans yaxşıdır; amma dəyişiklik gec yayılır
- Aşağı TTL: çevik failover; amma DNS server-ə daha çox yük
- Round-robin DNS: sadə load balancing; amma health check yoxdur — down server-ə sorğu gedir

**Common mistakes:**
- Migration öncəsi TTL-i azaltmamaq — köhnə IP günlərlə cache-də qalır
- MX priority-ni yanlış qurmaq — email çatdırılmır
- SPF record-unda bütün mail server-ləri daxil etməməmək — email spam kimi işarələnir
- Wildcard DNS ilə phishing riskini nəzərə almamaq

**Anti-pattern:** Hər API request-də `gethostbyname()` çağırmaq — PHP-nin öz DNS cache-i session-lar arasında itir; application-level caching (Redis) lazımdır.

## Nümunələr

### Ümumi Nümunə

Blue-green deployment ilə DNS switching:

```
Deployment öncəsi (3 gün əvvəl):
  api.example.com TTL: 86400 -> 300-ə endirin

Deployment günü:
  Blue (köhnə): 10.0.0.1
  Green (yeni): 10.0.0.2

  api.example.com A record: 10.0.0.1 -> 10.0.0.2 dəyişin
  300 saniyə sonra bütün client-lər yeni IP-yə keçir

Deployment sonrası:
  TTL-i 86400-ə qayıdın
```

### Kod Nümunəsi

PHP-də DNS Sorğusu:

```php
// A record sorğusu
$ip = gethostbyname('example.com');
echo $ip; // 93.184.216.34

// Bütün DNS record-larını al
$records = dns_get_record('example.com', DNS_ALL);
foreach ($records as $record) {
    echo "Type: {$record['type']}, ";
    if ($record['type'] === 'A') {
        echo "IP: {$record['ip']}";
    } elseif ($record['type'] === 'MX') {
        echo "Mail: {$record['target']}, Priority: {$record['pri']}";
    }
    echo "\n";
}

// MX records
$records = dns_get_record('gmail.com', DNS_MX);
// [['target' => 'gmail-smtp-in.l.google.com', 'pri' => 5], ...]

// Reverse DNS
$host = gethostbyaddr('8.8.8.8');
echo $host; // dns.google

// DNS record mövcudluğunu yoxla
$exists = checkdnsrr('example.com', 'MX');
echo $exists ? "MX record var" : "MX record yoxdur";
```

Laravel-də Email Validation (MX Check):

```php
// Form Request-də MX record yoxlaması
class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // dns: MX və ya A record yoxlayır
            'email' => ['required', 'email:rfc,dns', 'unique:users'],
        ];
    }
}
```

Laravel DNS Cache Layer:

```php
namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CachedDnsResolver
{
    /**
     * DNS nəticəsini cache-lə (PHP-nin öz DNS cache-i session arasında itirir)
     */
    public function resolve(string $domain, int $ttl = 300): ?string
    {
        return Cache::remember(
            "dns:a:{$domain}",
            $ttl,
            function () use ($domain) {
                $ip = gethostbyname($domain);
                // gethostbyname uğursuz olsa domain-in özünü qaytarır
                return $ip !== $domain ? $ip : null;
            }
        );
    }

    /**
     * Bütün record-ları cache-lə
     */
    public function resolveAll(string $domain, int $type = DNS_ALL): array
    {
        return Cache::remember(
            "dns:all:{$domain}:{$type}",
            300,
            fn() => dns_get_record($domain, $type) ?: []
        );
    }

    /**
     * Cache-i təmizlə (DNS dəyişikliyi zamanı)
     */
    public function flush(string $domain): void
    {
        Cache::forget("dns:a:{$domain}");
        Cache::forget("dns:all:{$domain}:" . DNS_ALL);
    }
}
```

Custom DNS Health Check:

```php
namespace App\Services;

class DnsHealthChecker
{
    public function check(string $domain): array
    {
        $results = [];

        // A record
        $a = dns_get_record($domain, DNS_A);
        $results['a_records'] = array_map(fn($r) => $r['ip'], $a);

        // MX record
        $mx = dns_get_record($domain, DNS_MX);
        $results['mx_records'] = array_map(
            fn($r) => ['host' => $r['target'], 'priority' => $r['pri']],
            $mx
        );

        // NS record
        $ns = dns_get_record($domain, DNS_NS);
        $results['ns_records'] = array_map(fn($r) => $r['target'], $ns);

        // TXT records (SPF, DKIM)
        $txt = dns_get_record($domain, DNS_TXT);
        $results['txt_records'] = array_map(fn($r) => $r['txt'], $txt);

        // SPF check
        $results['has_spf'] = collect($txt)
            ->contains(fn($r) => str_starts_with($r['txt'], 'v=spf1'));

        // Resolution time
        $start = microtime(true);
        gethostbyname($domain);
        $results['resolution_time_ms'] = round((microtime(true) - $start) * 1000, 2);

        return $results;
    }
}
```

Artisan Command — DNS Lookup:

```php
namespace App\Console\Commands;

use Illuminate\Console\Command;

class DnsLookup extends Command
{
    protected $signature = 'dns:lookup {domain} {--type=A}';
    protected $description = 'DNS lookup for a domain';

    public function handle(): void
    {
        $domain = $this->argument('domain');
        $typeMap = [
            'A' => DNS_A, 'AAAA' => DNS_AAAA, 'MX' => DNS_MX,
            'CNAME' => DNS_CNAME, 'TXT' => DNS_TXT, 'NS' => DNS_NS,
            'SOA' => DNS_SOA, 'ALL' => DNS_ALL,
        ];

        $type = $typeMap[strtoupper($this->option('type'))] ?? DNS_A;
        $records = dns_get_record($domain, $type);

        if (empty($records)) {
            $this->error("No records found for {$domain}");
            return;
        }

        $this->info("DNS Records for {$domain}:");
        $this->table(
            array_keys($records[0]),
            $records
        );
    }
}

// php artisan dns:lookup example.com --type=MX
```

## Praktik Tapşırıqlar

**Tapşırıq 1: DNS record-larını araşdırın**

```bash
# A record
dig example.com A

# MX records
dig gmail.com MX

# Bütün record-lar
dig example.com ANY

# Authoritative server-dən birbaşa soruşun
dig @ns1.example.com example.com

# TTL-i görün
dig +ttl example.com

# Reverse DNS
dig -x 8.8.8.8
```

**Tapşırıq 2: Email DNS record-larını yoxlayın**

Öz domain-iniz üçün:
1. SPF record-unu yoxlayın: `dig yourdomain.com TXT | grep spf`
2. DKIM record-unu yoxlayın (Google Workspace): `dig google._domainkey.yourdomain.com TXT`
3. DMARC record-unu yoxlayın: `dig _dmarc.yourdomain.com TXT`
4. [MXToolbox](https://mxtoolbox.com) ilə tam audit edin

**Tapşırıq 3: Application-level DNS caching implement edin**

Laravel-də aşağıdakı tələbləri həyata keçirin:
- `CachedDnsResolver` servisini implement edin (yuxarıdakı kod nümunəsindən)
- API client-ləriniz üçün host IP-lərini Redis-də cache edin (TTL: 5 dəqiqə)
- Artisan command: `php artisan dns:lookup yourdomain.com --type=MX`
- Cache-i silmək üçün: `php artisan cache:forget dns:a:yourdomain.com`

## Əlaqəli Mövzular

- [TCP/IP Model](02-tcp-ip-model.md)
- [HTTP Protocol](05-http-protocol.md)
- [HTTPS, SSL/TLS](06-https-ssl-tls.md)
- [Network Security](26-network-security.md)
- [Network Troubleshooting](30-network-troubleshooting.md)
- [Email Protocols](27-email-protocols.md)
