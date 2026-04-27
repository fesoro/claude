# DNS Resolution (Middle ⭐⭐)

## İcmal
DNS (Domain Name System) — human-readable domain adlarını IP ünvanlara çevirən distributed directory sistemidir. "İnternetin telefon kitabı" kimi tanınır. Backend developer kimi DNS-i anlamaq production incident-lərin böyük hissəsini izah etmək, CDN konfiqurasiyası, microservices service discovery, blue-green deployment üçün vacibdir.

## Niyə Vacibdir
"DNS just works" düşüncəsi production-da incident-lərə yol açır. DNS TTL-i düzgün idarə etməmək failover zamanı saatlarla downtime yaradır. DNS cache poisoning security vulnerability-dir. Load balancing üçün DNS round-robin işlədərkən TTL-in sticky session-a necə təsir etdiyini bilmək lazımdır. Bu bilgi həmçinin global CDN, anycast routing, multi-region deployment dizaynında birbaşa istifadə olunur.

## Əsas Anlayışlar

- **DNS Hierarchy:** Root nameservers (13 cluster, anycast ilə yüzlərcə fiziki server) → TLD nameservers (`.com`, `.net`, `.az`) → Authoritative nameservers (domeninizin actual DNS məlumatları)
- **Recursive Resolver:** ISP-nin ya da 8.8.8.8 (Google), 1.1.1.1 (Cloudflare) kimi DNS server. Client-in adından bütün hierarchy-ni sorğulayır, cavabı cache-ə alır. "Stub resolver" browser/OS sadəcə bura göndərir
- **Iterative vs Recursive Resolution:** Iterative — resolver hər name server-ə ayrı sorğu göndərir, referral alır. Recursive — resolver özü bütün işi görür, client-ə hazır cavab verir
- **DNS Resolution Addımları:** Browser cache → OS cache (`/etc/hosts`) → Local recursive resolver cache → Root NS referral → TLD NS referral → Authoritative NS answer
- **DNS Record Növləri:**
  - **A:** Domain → IPv4 (`example.com → 93.184.216.34`)
  - **AAAA:** Domain → IPv6
  - **CNAME:** Domain → başqa domain alias (`www.example.com → example.com`). Apex domain-də (`@`) istifadə edilə bilməz — RFC məhdudiyyəti
  - **MX:** Mail server record
  - **TXT:** Mətn məlumatı — SPF, DKIM email authentication, domain verification, ACME challenge
  - **NS:** Zona üçün authoritative nameserver-lər
  - **SOA (Start of Authority):** Zona metadata — primary NS, admin email, serial, refresh interval
  - **PTR:** Reverse DNS — IP → domain. Email spam filter-ləri üçün vacib
  - **SRV:** Service location record — `_https._tcp.example.com 443 weight priority`. gRPC, SIP, xmpp üçün
  - **CAA (Certification Authority Authorization):** Hansı CA-nın bu domain üçün certificate verə biləcəyi
- **TTL (Time to Live):** DNS record-un neçə saniyə cache olunacağı. Yüksək TTL (3600s) → az DNS sorğusu, dəyişiklik yavaş yayılır. Aşağı TTL (60s) → tez yenilənmə, daha çox DNS yükü. Deployment əvvəli TTL azaltmaq best practice
- **DNS Propagation:** Authoritative NS-dəki dəyişiklik bütün internet-ə yayılmaq üçün köhnə TTL expire olana qədər vaxt lazımdır. "DNS propagation 48 saat çəkir" — yanlış: yalnız TTL qədər çəkir
- **Negative Caching:** "Bu domain mövcud deyil" (NXDOMAIN) cavabı da cache olunur — SOA record-un minimum TTL qədər
- **DNS Caching Layers:** Browser (30-60s own TTL), OS/nscd, router, ISP recursive resolver — hər layer öz cache-ə malik
- **DNS over HTTPS (DoH) / DNS over TLS (DoT):** DNS sorğuları plain text — ISP, MITM izləyə bilər. DoH/DoT şifrələyir — privacy. Cloudflare 1.1.1.1 DoH dəstəkləyir
- **Anycast Routing:** Eyni IP-ni bir neçə coğrafi location-da advertise etmək. BGP routing ən yaxın node-a yönləndirir. Cloudflare 1.1.1.1, Google 8.8.8.8, Root NS-lər anycast istifadə edir
- **Round-Robin DNS:** Eyni domain üçün bir neçə A record → hər sorğuda fərqli IP qaytarılır. Primitive load balancing. Dezavantaj: TTL-ə görə sticky, health check yoxdur, client cache-i TTL bitənə qədər köhnə IP-dən istifadə edir
- **Split-Horizon DNS (Split-Brain DNS):** Daxili şəbəkədən gələn sorğular üçün fərqli IP (internal server), xaricdən gələn üçün fərqli IP (public). Internal/external ayrımı
- **DNSSEC:** DNS record-ların digital imza ilə autentifikasiyası. DNS cache poisoning-ə qarşı. Root, TLD, authoritative NS-lərdə zəncirvari imza
- **DNS-based Service Discovery:** Kubernetes (CoreDNS), Consul internal DNS-i servis discovery üçün istifadə edir: `payment-service.payments.svc.cluster.local`

## Praktik Baxış

**Interview-da yanaşma:**
DNS resolution-u izah edərkən browser-dən başlayıb authoritative NS-ə qədər step-by-step gedişi izah edin. Cache-in hər səviyyədə mövcudluğunu vurğulayın. Sonra TTL-in deployment/failover-a praktik təsirini izah edin.

**Follow-up suallar:**
- "DNS cache poisoning nədir?" — Attacker sahte DNS cavabı inject edir (Kaminsky attack). DNSSEC ilə həll olunur
- "Blue-green deployment-da DNS-i necə istifadə edərdiniz?" — Əvvəlcədən TTL-i azalt (60s), yeni environment hazır olduqda A record-u dəyişdir
- "Kubernetes-də DNS necə işləyir?" — CoreDNS, hər service üçün `service.namespace.svc.cluster.local` format
- "CNAME apex domain-də niyə istifadə edilə bilməz?" — RFC: apex domain-də SOA+NS record var, CNAME başqa record ilə coexist edə bilməz. ALIAS/ANAME record alternativdir
- "13 Root nameserver niyə var?" — UDP 512 byte limit (köhnə) — 13 IP sığır. Anycast ilə əslində yüzlərcə fiziki server var

**Ümumi səhvlər:**
- DNS propagation-ın ani baş verdiyini düşünmək — TTL expire olmadan köhnə IP cache-dədir
- CNAME-i apex domain üçün istifadə etməyə çalışmaq — RFC violation
- Deployment zamanı TTL-i əvvəlcədən azaltmamaq — failover yavaş olur
- Round-robin DNS-in health check etmədiyini bilməmək — down server IP-ni verməkdə davam edir

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- Anycast routing-i izah etmək
- DNSSEC-in necə işlədiyini bilmək
- Kubernetes CoreDNS service discovery mexanizmini izah etmək
- "DNS round-robin health check etmir, load balancer istifadə edin" demək

## Nümunələr

### Tipik Interview Sualı
"We're planning a zero-downtime deployment with DNS failover. What DNS strategies would you use, and what are the gotchas you'd warn us about?"

### Güclü Cavab
Zero-downtime DNS failover üçün addım-addım plan:

**Hazırlıq (deployment-dən 24+ saat əvvəl):**
1. Cari TTL-i yoxla: `dig example.com A` — TTL sütununa bax
2. TTL-i 86400s-dən 60-300s-ə azalt
3. 24+ saat gözlə — köhnə yüksək TTL cache-lər expire olsun

**Deployment günü:**
1. Yeni environment hazırla (blue-green)
2. Health check-lər keçir
3. DNS A record-unu yeni server IP-sinə dəyişdir
4. TTL qədər (60-300s) gözlə — köhnə cache-lər expire olsun
5. Monitoring: error rate, latency izlə

**Gotcha-lar (real problemlər):**
- Bəzi mobile ISP-lər TTL-i ignore edir — 100% ani deyil
- `connection: keep-alive` olan client-lər köhnə connection-u saxlayır — DNS dəyişsə belə
- Load balancer health check yalnız IP-dədir — DNS layer yoxdur
- CNAME chain — `www → example.com → actual-IP` — hər CNAME əlavə lookup

**Daha etibarlı həll:** DNS-in önündə managed load balancer (AWS ALB, Cloudflare Load Balancing). Failover saniyə içində, health check ilə. DNS-in TTL dependency-si aradan qalxır.

### Kod Nümunəsi
```bash
# DNS resolution debug
dig example.com A                   # A record sorğusu
dig example.com A +short            # Yalnız IP
dig example.com A +trace            # Bütün resolution path
dig @8.8.8.8 example.com A         # Spesifik DNS server ilə
dig @1.1.1.1 example.com A         # Cloudflare DNS ilə müqayisə

# TTL yoxlamaq
dig example.com A | awk '/ANSWER/{flag=1;next}flag{print;exit}'
# example.com.  300  IN  A  93.184.216.34
#               ^^^— TTL (saniyə) — 300 = 5 dəqiqə

# Bütün record növlərini gör
dig example.com ANY
dig example.com MX
dig example.com TXT
dig example.com NS

# Reverse DNS (PTR record)
dig -x 93.184.216.34

# Authoritative NS-dən birbaşa sorğu
dig @ns1.example.com example.com A  # TTL cache-siz, authoritative cavab

# DNS propagation yoxla (müxtəlif resolver-lərdən)
for ns in 8.8.8.8 1.1.1.1 9.9.9.9 208.67.222.222; do
    echo -n "=== $ns: "
    dig @"$ns" example.com A +short
done

# DNSSEC yoxla
dig example.com +dnssec
dig example.com DS @k.root-servers.net

# TTL azaltmaq üçün DNS provider-də (Route53 nümunəsi):
# aws route53 change-resource-record-sets --hosted-zone-id Z123 \
#   --change-batch '{"Changes":[{"Action":"UPSERT","ResourceRecordSet":{
#   "Name":"example.com","Type":"A","TTL":60,"ResourceRecords":[{"Value":"1.2.3.4"}]}}]}'
```

```php
// PHP-də DNS lookup
$records = dns_get_record('example.com', DNS_A | DNS_AAAA | DNS_MX | DNS_TXT);
foreach ($records as $record) {
    printf("%-6s %-5d %s\n",
        $record['type'],
        $record['ttl'],
        $record['ip'] ?? $record['target'] ?? $record['txt'] ?? ''
    );
}

// Service discovery — Kubernetes-də service-ə qoşulma
// Kubernetes: service-name.namespace.svc.cluster.local
$host = 'payment-service.payments.svc.cluster.local';
$ip   = gethostbyname($host);
if ($ip === $host) {
    throw new RuntimeException("Service {$host} not found");
}

// DNS-based health check
function isDnsResolvable(string $host): bool
{
    return gethostbyname($host) !== $host;
}

// HTTP Client-i spesifik IP-yə resolve etmək
// (DNS cache by-pass — canary deployment test üçün)
$response = Http::withOptions([
    'curl' => [
        CURLOPT_RESOLVE => [
            'api.example.com:443:192.168.1.100',  // Bu host:port → bu IP
        ],
    ],
])->withHeaders([
    'Host' => 'api.example.com',
])->get('https://api.example.com/health');

// Deployment öncəsi TTL yoxlama
function checkDnsTtl(string $domain, int $warningThresholdSeconds = 300): array
{
    $records = dns_get_record($domain, DNS_A);
    if (empty($records)) {
        return ['error' => 'No A record found'];
    }

    $ttl = $records[0]['ttl'];
    return [
        'domain' => $domain,
        'ip'     => $records[0]['ip'],
        'ttl'    => $ttl,
        'status' => $ttl > $warningThresholdSeconds ? 'HIGH_TTL_WARNING' : 'OK',
        'message' => $ttl > $warningThresholdSeconds
            ? "TTL is {$ttl}s — lower it before deployment!"
            : "TTL is {$ttl}s — safe to deploy"
    ];
}
```

```
DNS Resolution Process (tam addımlar):

Browser               OS            Recursive         Root     .com     Auth
  |                   |             Resolver           NS       NS       NS
  |--check cache----->|
  |  (miss)           |--check--------->|
  |                   |  local cache    |
  |                   |  (miss)         |--query example.com-->|
  |                   |                 |                       |--"ask .com NS"
  |                   |                 |<--referral: b.gtld-servers.net
  |                   |                 |
  |                   |                 |--query example.com-->|
  |                   |                 |                              |--"ask ns1.example.com"
  |                   |                 |<--referral: ns1.example.com
  |                   |                 |
  |                   |                 |--query example.com------------>|
  |                   |                 |<--answer: 93.184.216.34 TTL 3600
  |                   |<--93.184.216.34--|
  |<--93.184.216.34---|  (caches result)
  (caches in browser for TTL seconds)
```

```
DNS Record Types müqayisəsi:

example.com.     3600  IN  A      93.184.216.34
www.example.com. 3600  IN  CNAME  example.com.     ← alias
api.example.com. 3600  IN  A      93.184.216.35
example.com.     3600  IN  MX  10 mail.example.com.
example.com.     3600  IN  TXT    "v=spf1 include:_spf.google.com ~all"
example.com.     3600  IN  NS     ns1.example.com.
example.com.     3600  IN  CAA  0 issue "letsencrypt.org"
_https._tcp.example.com. 3600 IN SRV 1 1 443 api.example.com.

CNAME chain (her biri əlavə lookup!):
www.example.com → cdn.example.com → d123.cloudfront.net → 13.54.x.x
(3 lookup — mümkün olduqda azald)
```

```yaml
# Kubernetes CoreDNS service discovery
# /etc/resolv.conf (pod içərisindən)
nameserver 10.96.0.10       # CoreDNS cluster IP
search default.svc.cluster.local svc.cluster.local cluster.local
options ndots:5

# Pod-dan service-lərə qoşulma:
# payment-service                                 → payment-service.default.svc.cluster.local
# payment-service.payments                        → payment-service.payments.svc.cluster.local
# payment-service.payments.svc.cluster.local      → tam FQDN

# CoreDNS ConfigMap
apiVersion: v1
kind: ConfigMap
metadata:
  name: coredns
  namespace: kube-system
data:
  Corefile: |
    .:53 {
        errors
        health {
           lameduck 5s
        }
        kubernetes cluster.local in-addr.arpa ip6.arpa {
           pods insecure
           fallthrough in-addr.arpa ip6.arpa
           ttl 30
        }
        prometheus :9153
        forward . /etc/resolv.conf
        cache 30
        loop
        reload
        loadbalance
    }
```

### İkinci Nümunə — Split-Horizon DNS

```
Split-Horizon DNS — İnternal vs External:

Problem: Eyni api.company.com domain-i
  - Xaricdən: public IP (load balancer) → 203.0.113.10
  - İçəridən: private IP (direct service) → 10.0.1.50

Niyə?:
  - Internal traffic external load balancer-dən keçməsin (latency, cost)
  - Internal service-ləri external-a expose etmə
  - VPN-li developer-lər internal IP-yə birbaşa qoşulsun

Konfiqurasiya:
  External DNS (Route53, Cloudflare):
    api.company.com → 203.0.113.10

  Internal DNS (BIND, CoreDNS, Active Directory):
    api.company.com → 10.0.1.50

  Internal resolver (/etc/resolv.conf):
    nameserver 10.0.0.53  ← internal DNS server

Zaman:
  Internal dev: api.company.com → 10.0.1.50 (direkt)
  External user: api.company.com → 203.0.113.10 (load balancer)

AWS Route53 ilə:
  Private Hosted Zone → VPC-yə bağlı → internal IP
  Public Hosted Zone  → public IP
  Eyni domain adı, fərqli cavablar
```

## Praktik Tapşırıqlar

- `dig +trace google.com` ilə bütün DNS resolution path-ı izləyin — root, TLD, authoritative NS-ləri görün
- Öz layihənizdəki DNS TTL dəyərlərini yoxlayın — deployment üçün uyğundurlarmı? 3600s-dən artıq isə azaltmaq üçün plan qurun
- DNS round-robin ssenarisi qurun: eyni domain üçün 3 A record, `dig +short` ilə hər dəfə fərqli IP gəldiyini görün
- Kubernetes-də `nslookup payment-service.default.svc.cluster.local` çalışdırın, CoreDNS-in response-unu analiz edin
- Blue-green deployment simulasiyası: TTL-i 60s edin, A record-u yeni IP-yə dəyişin, 60s sonra propagation-ı yoxlayın
- PHP-də service discovery skripti yazın: Kubernetes DNS adından IP-ni resolve edin, resolve olmasa fallback həlli implement edin

## Əlaqəli Mövzular
- [HTTP Versions](02-http-versions.md) — DNS resolution HTTP connection-dan əvvəl baş verir — toplam latency-yə təsiri var
- [TLS/SSL Handshake](03-tls-ssl-handshake.md) — DNS sonra TCP, sonra TLS — tam "time to first byte" zənciri
- [HTTP Caching](09-http-caching.md) — CDN-in DNS ilə işləməsi — anycast routing CDN-in əsasıdır
- [WebSockets](06-websockets.md) — WebSocket qoşulmasında DNS lookup eyni zəncirlə gedir
