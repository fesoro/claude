# DNS (Domain Name System)

## Nədir? (What is it?)

DNS internet-in "telefon kitabcasi"dir. Insan ucun oxunaqli domain adlarini (meselen, google.com) IP adreslerine (meselen, 142.250.185.78) ceviren distributed, hierarchical sistemdir. DNS olmadan her defe website-a daxil olmaq ucun IP adresi yadda saxlamaliydiniz.

DNS 1983-cu ilde Paul Mockapetris terefinden yaradilib (RFC 1035). Distributed database olaraq dizayn edilib - hec bir single server butun DNS melumatlarini saxlamır.

```
Istifadeci brauzerde yazir:  www.example.com
                                  |
                                  v
                          DNS Resolution
                                  |
                                  v
                          IP: 93.184.216.34
                                  |
                                  v
                          HTTP request gonderilir
```

## Necə İşləyir? (How does it work?)

### DNS Hierarchy (Iyerarxiyasi)

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

### Addim-addim proses:

1. **Browser Cache** - Brauzer evvelce sorgulanmis DNS-i yoxlayir
2. **OS Cache** - Emeliyyat sisteminin DNS cache-i yoxlanir (`/etc/hosts` da burda)
3. **Recursive Resolver** - ISP-nin DNS resolver-ine sorgu gonderilir
4. **Root Server** - 13 root server clusteri var (a.root-servers.net - m.root-servers.net)
5. **TLD Server** - .com, .org, .az kimi TLD server-ler
6. **Authoritative Server** - Domain-in oz DNS server-i son cavabi verir

## Əsas Konseptlər (Key Concepts)

### DNS Record Types

| Record | Meqsed | Numune |
|--------|--------|--------|
| **A** | Domain -> IPv4 | `example.com -> 93.184.216.34` |
| **AAAA** | Domain -> IPv6 | `example.com -> 2606:2800:220:1:...` |
| **CNAME** | Alias (baska domain-e yonlendirir) | `www.example.com -> example.com` |
| **MX** | Mail server | `example.com -> mail.example.com (priority: 10)` |
| **TXT** | Text record (SPF, DKIM, verification) | `"v=spf1 include:_spf.google.com ~all"` |
| **NS** | Nameserver | `example.com -> ns1.example.com` |
| **SOA** | Start of Authority (zone info) | Serial, refresh, retry, expire |
| **SRV** | Service location | `_sip._tcp.example.com -> sipserver.example.com:5060` |
| **PTR** | Reverse DNS (IP -> Domain) | `34.216.184.93 -> example.com` |
| **CAA** | Certificate Authority Authorization | `example.com CAA 0 issue "letsencrypt.org"` |

### TTL (Time To Live)

TTL DNS record-un nece muddet cache-de saxlanacagini gosterir (saniye ile):

```
; Yuksek TTL (1 gun) - nadir deyisen recordlar ucun
example.com.    86400   IN  A   93.184.216.34

; Asagi TTL (5 deqiqe) - tez-tez deyisen recordlar ucun
api.example.com.  300   IN  A   10.0.0.1

; Cok asagi TTL (60 san) - failover/migration zamani
staging.example.com. 60 IN  A   10.0.0.2
```

**TTL Strategy:**
- Production: 3600-86400 (1 saat - 1 gun)
- Migration oncesi: TTL-i evvelceden asalt (300-e)
- Failover: 60-300

### DNSSEC (DNS Security Extensions)

DNSSEC DNS cavablarinin authenticity-sini yoxlayir. DNS cache poisoning-in qarsisini alir.

```
Adi DNS:
  Client -> Resolver: "example.com?"
  Resolver -> Client: "93.184.216.34"  (heç bir doğrulama yoxdur!)

DNSSEC ile:
  Client -> Resolver: "example.com?"
  Resolver -> Client: "93.184.216.34" + RRSIG (digital signature)
  Client: Signature-i DNSKEY ile verify edir ✓
```

**DNSSEC Record Types:**
- **RRSIG** - Record-un digital signature-i
- **DNSKEY** - Zone-un public key-i
- **DS** - Delegation Signer (parent zone-a link)
- **NSEC/NSEC3** - Non-existence proof

### DNS Caching Seviyeleri

```
1. Browser Cache        (Chrome: chrome://net-internals/#dns)
2. OS Cache             (Linux: systemd-resolved, Windows: ipconfig /displaydns)
3. Router Cache         (Ev router-i oz cache-i saxlayir)
4. ISP Resolver Cache   (ISP-nin recursive resolver-i)
5. Authoritative TTL    (Domain sahibinin teyin etdiyi TTL)
```

### DNS Query Types

- **Recursive Query** - Resolver tam cavab tapmalidır (client -> resolver)
- **Iterative Query** - Resolver "bilmirem, bura sor" deyir (resolver -> root/TLD/auth)
- **Inverse Query** - IP-den domain tapma (PTR record)

## PHP/Laravel ilə İstifadə

### PHP-de DNS Sorgusu

```php
// A record sorgusu
$ip = gethostbyname('example.com');
echo $ip; // 93.184.216.34

// Butun DNS recordlarini al
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

// DNS record movcudlugunu yoxla
$exists = checkdnsrr('example.com', 'MX');
echo $exists ? "MX record var" : "MX record yoxdur";
```

### Laravel-de Email Validation (MX Check)

```php
// Form Request-de MX record yoxlamasi
class RegisterRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            // dns: MX ve ya A record yoxlayir
            'email' => ['required', 'email:rfc,dns', 'unique:users'],
        ];
    }
}
```

### Custom DNS Health Check

```php
namespace App\Services;

class DnsHealthChecker
{
    /**
     * Domain-in DNS-ini yoxla
     */
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

### Laravel DNS Cache Layer

```php
namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CachedDnsResolver
{
    /**
     * DNS neticesini cache-le (PHP-nin oz DNS cache-i session arasinda itir)
     */
    public function resolve(string $domain, int $ttl = 300): ?string
    {
        return Cache::remember(
            "dns:a:{$domain}",
            $ttl,
            function () use ($domain) {
                $ip = gethostbyname($domain);
                // gethostbyname ugursuz olsa domain-in ozunu qaytarir
                return $ip !== $domain ? $ip : null;
            }
        );
    }

    /**
     * Butun recordlari cache-le
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
     * Cache-i temizle (DNS deyisikliyi zamani)
     */
    public function flush(string $domain): void
    {
        Cache::forget("dns:a:{$domain}");
        Cache::forget("dns:all:{$domain}:" . DNS_ALL);
    }
}
```

### Artisan Command - DNS Lookup

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

## Interview Sualları

### 1. DNS nedir ve niye lazimdir?
**Cavab:** DNS (Domain Name System) domain adlarini IP adreslerine ceviren distributed, hierarchical naming sistemdir. Insanlar IP adreslerini yadda saxlaya bilmez, DNS bu problemi hell edir. DNS olmadan `google.com` yerine `142.250.185.78` yazmaliydiniz.

### 2. DNS resolution prosesini addim-addim izah edin.
**Cavab:** 1) Browser oz cache-ine baxir, 2) OS cache yoxlanir (/etc/hosts daxil), 3) Recursive resolver-e sorgu gedir, 4) Resolver root server-e sorgu gonderir, 5) Root server TLD server-i gosterir, 6) TLD server authoritative NS-i gosterir, 7) Authoritative server son cavabi verir, 8) Resolver neticeni cache-leyir ve client-e qaytarir.

### 3. A, AAAA, CNAME, MX recordlarinin ferqi nedir?
**Cavab:** **A** record domain-i IPv4 adresine map edir. **AAAA** record IPv6 adresine map edir. **CNAME** bir domain-i basqa domain-e yonlendirir (alias). **MX** record domain ucun mail server-i gosterir ve priority deyeri olur.

### 4. TTL nedir ve niye vacibdir?
**Cavab:** TTL (Time To Live) DNS record-un cache-de nece muddet (saniye ile) saxlanacagini bildirir. Yuksek TTL performansi artırır amma deyisiklikler gec yayilir. Asagi TTL deyisikliklerin tez yayilmasini temin edir amma DNS server-lere daha cox sorgu gedir. Migration oncesi TTL-i asaltmaq best practice-dir.

### 5. Recursive ve Iterative query arasinda ferq nedir?
**Cavab:** **Recursive query**-de client resolver-den tam cavab gozleyir - resolver butun ishi gorur. **Iterative query**-de resolver her server-den "bilmirem, bu servere sor" cavabi alir ve ozü novbeti servere sorgu gonderir. Client adeten recursive, resolver ise iterative query istifade edir.

### 6. DNSSEC nedir ve hansı problemi hell edir?
**Cavab:** DNSSEC DNS cavablarinin dogrulugunu digital signature ile verify eden genislemedir. DNS cache poisoning (attacker-in saxta DNS cavabi inject etmesi) problemini hell edir. RRSIG, DNSKEY, DS recordlari istifade edir.

### 7. DNS cache poisoning nedir?
**Cavab:** Attacker DNS resolver-in cache-ine yanlis IP adresi inject edir. Netice olaraq istifadeciler legit domain-e daxil olmaq isteyende attacker-in server-ine yonlendirilir. Buna DNS spoofing de deyilir. DNSSEC bu hucumun qarsisini alir.

### 8. /etc/hosts faylinin DNS ile elaqesi nedir?
**Cavab:** `/etc/hosts` lokal DNS override faylidir. OS DNS resolver-e sorgu gondermezden evvel bu fayla baxir. Development ucun `127.0.0.1 myapp.local` kimi entry-ler elave etmek ucun istifade olunur.

### 9. Niye 13 root server var?
**Cavab:** Texniki olaraq 13 root server **adresi** var (a-dan m-ye), amma Anycast texnologiyasi ile bu serverlerin yuzlerle instance-i dunyanin muxtelif yerlerinde yerlesir. 13 limiti DNS UDP paketinin 512 byte olcusunden ireli gelir.

### 10. Round-robin DNS nedir?
**Cavab:** Bir domain ucun bir nece A record teyin etmek ve DNS-in her sorguya ferqli IP qaytarmasi ile primitive load balancing yaratmaq usulur. Meselen: `example.com -> 10.0.0.1, 10.0.0.2, 10.0.0.3`. Health check yoxdur, buna gore real load balancer-in yerine kecmir.

## Best Practices

1. **TTL-i duzgun teyin edin** - Production ucun 3600+, migration oncesi 300-e endir
2. **DNSSEC aktivlesdirin** - Domain-iniz ucun DNSSEC enable edin
3. **Redundant NS istifade edin** - En az 2 nameserver, muxtelif network-lerde
4. **SPF, DKIM, DMARC qurun** - Email spoofing-in qarsisini alin
5. **CAA record elave edin** - Hansı CA-nin sertifikat vere bileceyini mehdudlashdirin
6. **DNS monitoring qurun** - Resolution time ve availability izleyin
7. **Wildcard DNS-den qaçın** - `*.example.com` tehlukeli ola biler
8. **DNS failover** - Health check ile avtomatik failover ucun Cloudflare/Route53 istifade edin
9. **Low TTL during migrations** - DNS deyisikliyi oncesi TTL-i asaldin, sonra geri qaldir
10. **Application-level DNS caching** - PHP/Laravel-de DNS neticesini cache-leyin (her request-de DNS sorgusu gondermemek ucun)
