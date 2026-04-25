# Şəbəkə Əsasları (Junior)

## Nədir? (What is it?)

Networking – kompüterlərin bir-biri ilə ünsiyyət qurması üçün istifadə olunan qaydalar, protokollar və texnologiyalar toplusudur. DevOps mühəndisi üçün şəbəkə əsaslarını bilmək vacibdir, çünki hər bir serverin, servisin və tətbiqin işləyişi şəbəkəyə əsaslanır. OSI modeli, TCP/IP, DNS, HTTP/HTTPS, load balancing, VPN və subnetting DevOps-un əsas biliklərindəndir.

## Əsas Konseptlər (Key Concepts)

### OSI Modeli (7 Layer)

```
┌─────────────────────────────────────────────────────────┐
│ Layer 7: Application   │ HTTP, HTTPS, FTP, SSH, DNS, SMTP│
│ Layer 6: Presentation  │ SSL/TLS, encryption, compression │
│ Layer 5: Session       │ NetBIOS, RPC, session control    │
│ Layer 4: Transport     │ TCP, UDP (ports: 80, 443, 22)   │
│ Layer 3: Network       │ IP, ICMP, routing (IP addresses)│
│ Layer 2: Data Link     │ Ethernet, MAC address, switches │
│ Layer 1: Physical      │ Cables, fiber, WiFi signals     │
└─────────────────────────────────────────────────────────┘

# Mnemonic: "Please Do Not Throw Sausage Pizza Away" (bottom-up)
# TCP/IP model 4 qat: Network Access, Internet, Transport, Application
```

### TCP və UDP

```bash
# TCP (Transmission Control Protocol)
# - Connection-oriented (3-way handshake)
# - Reliable (acknowledgments, retransmission)
# - Ordered delivery
# - Slower but safer
# - Used by: HTTP, HTTPS, SSH, FTP, SMTP

# 3-way handshake:
# Client → SYN → Server
# Client ← SYN-ACK ← Server
# Client → ACK → Server

# UDP (User Datagram Protocol)
# - Connectionless
# - Unreliable (no acknowledgment)
# - No order guarantee
# - Faster, lower overhead
# - Used by: DNS, DHCP, streaming, VoIP, gaming

# TCP vs UDP example
# TCP port 80: HTTP web server
# UDP port 53: DNS query
# TCP port 443: HTTPS
# UDP port 123: NTP

# Port ranges:
# 0-1023    : Well-known (HTTP=80, HTTPS=443, SSH=22, MySQL=3306)
# 1024-49151: Registered (PostgreSQL=5432, Redis=6379, MongoDB=27017)
# 49152-65535: Dynamic/Private (ephemeral ports)
```

### IP Addressing və CIDR

```bash
# IPv4: 32-bit, 4 octets (192.168.1.1)
# IPv6: 128-bit, 8 groups (2001:0db8:85a3::8a2e:0370:7334)

# IP address classes (old way):
# Class A: 1.0.0.0   - 126.255.255.255  (/8)
# Class B: 128.0.0.0 - 191.255.255.255  (/16)
# Class C: 192.0.0.0 - 223.255.255.255  (/24)

# Private IP ranges (RFC 1918):
# 10.0.0.0    /8   (10.0.0.0 - 10.255.255.255)       → 16.7M adres
# 172.16.0.0  /12  (172.16.0.0 - 172.31.255.255)     → 1M adres
# 192.168.0.0 /16  (192.168.0.0 - 192.168.255.255)   → 65K adres

# CIDR (Classless Inter-Domain Routing)
# 192.168.1.0/24 = 256 adres (254 usable)
# /8  = 16,777,216 adres
# /16 = 65,536 adres
# /24 = 256 adres
# /28 = 16 adres (14 usable)
# /30 = 4 adres (2 usable, point-to-point)

# Subnet mask çevirmə:
# /24 = 255.255.255.0
# /16 = 255.255.0.0
# /28 = 255.255.255.240

# Subnet-də adres sayı = 2^(32-prefix)
# Usable = 2^(32-prefix) - 2 (network və broadcast adresləri çıxarılır)

# CIDR notation nümunələri:
# 10.0.0.0/16 = VPC (65,536 IP)
#   ├── 10.0.1.0/24 = public subnet (256 IP)
#   ├── 10.0.2.0/24 = private subnet
#   └── 10.0.3.0/24 = database subnet
```

### DNS (Domain Name System)

```bash
# DNS = Domain adları (example.com) → IP adreslər
# Hierarchical və distributed sistem

# DNS hierarchy:
# Root (.) → TLD (.com) → Authoritative (example.com) → Subdomain (api.example.com)

# DNS record types:
# A      - Domain → IPv4       (example.com → 192.0.2.1)
# AAAA   - Domain → IPv6
# CNAME  - Alias (www → example.com)
# MX     - Mail server (priority + hostname)
# TXT    - Text (SPF, DKIM, domain verification)
# NS     - Nameserver
# PTR    - Reverse lookup (IP → domain)
# SRV    - Service records
# SOA    - Start of Authority (zone info)
# CAA    - Certificate Authority Authorization

# DNS query tools
dig example.com                    # Full DNS info
dig example.com A                  # Only A record
dig example.com MX                 # Mail records
dig example.com +short             # Qısa
dig @8.8.8.8 example.com          # Specific DNS server

nslookup example.com
host example.com

# Reverse DNS
dig -x 8.8.8.8

# TTL (Time To Live)
# DNS cache vaxtı saniyələrlə
# Kiçik TTL = tez yenilik, amma çox sorğu
# Böyük TTL = cache-də uzun qalır, gecikmiş yenilik

# DNS propagation: dəyişiklik yayılması 24-48 saat çəkə bilər
```

### HTTP və HTTPS

```bash
# HTTP (Hypertext Transfer Protocol)
# Stateless, request/response protocol
# Port 80 (HTTP), 443 (HTTPS)

# HTTP methods:
# GET     - Resource oxu
# POST    - Resource yarat
# PUT     - Tam yenilə
# PATCH   - Qismən yenilə
# DELETE  - Sil
# HEAD    - Headers yalnız
# OPTIONS - Available methods

# HTTP status codes:
# 1xx Informational (100 Continue)
# 2xx Success (200 OK, 201 Created, 204 No Content)
# 3xx Redirection (301 Moved Permanently, 302 Found, 304 Not Modified)
# 4xx Client Error (400 Bad Request, 401 Unauthorized, 403 Forbidden, 404 Not Found, 429 Too Many)
# 5xx Server Error (500 Internal, 502 Bad Gateway, 503 Unavailable, 504 Timeout)

# HTTP versions:
# HTTP/1.0 - Bir connection, bir request
# HTTP/1.1 - Keep-alive, pipelining (default)
# HTTP/2   - Multiplexing, binary, server push, header compression
# HTTP/3   - QUIC (UDP əsaslı), faster

# HTTPS = HTTP + TLS/SSL
# TLS handshake:
# 1. ClientHello (supported ciphers)
# 2. ServerHello + Certificate
# 3. Key exchange
# 4. Encrypted communication

# HTTP headers nümunələri
curl -I https://example.com

# Request headers:
# Host, User-Agent, Accept, Authorization, Cookie, Content-Type

# Response headers:
# Server, Content-Type, Cache-Control, Set-Cookie, Location
```

### Load Balancing

```bash
# Load Balancer = Trafiği birdən çox serverə bölür
# Layer 4 (TCP/UDP) vs Layer 7 (HTTP/HTTPS)

# Layer 4 LB:
# - IP + Port əsaslı
# - Sürətli
# - Content yoxlamır
# - Nümunə: AWS NLB, HAProxy TCP mode

# Layer 7 LB:
# - HTTP header, URL, cookie əsaslı routing
# - SSL termination
# - Content-based routing
# - Nümunə: Nginx, AWS ALB, HAProxy HTTP mode

# Load balancing algoritmləri:
# 1. Round Robin        - Hər server növbə ilə
# 2. Weighted RR        - Serverlərə çəki ilə
# 3. Least Connections  - Ən az aktiv bağlantı
# 4. IP Hash            - Eyni client → eyni server (sticky)
# 5. Least Response Time - Ən sürətli cavab verən
# 6. Random

# Health checks:
# - Interval (hər 30s)
# - Timeout (5s)
# - Healthy threshold (2 uğurlu check)
# - Unhealthy threshold (3 uğursuz check)
# - Path: /health

# Sticky sessions (session affinity):
# Eyni client-i həmişə eyni serverə yönəlt
# Cookie-based və ya IP-based
```

### VPN (Virtual Private Network)

```bash
# VPN = Təhlükəsiz tunnel iki şəbəkə arasında
# Site-to-Site VPN: iki ofis/DC birləşdirir
# Client VPN: fərdi istifadəçi → corporate network

# VPN protokolları:
# IPSec   - Network layer, Site-to-Site üçün
# OpenVPN - SSL/TLS əsaslı, çevik
# WireGuard - Müasir, sürətli, sadə
# L2TP    - IPSec ilə birlikdə
# PPTP    - Köhnə, təhlükəsiz deyil

# WireGuard nümunəsi
cat > /etc/wireguard/wg0.conf <<EOF
[Interface]
PrivateKey = <server-private-key>
Address = 10.0.0.1/24
ListenPort = 51820

[Peer]
PublicKey = <client-public-key>
AllowedIPs = 10.0.0.2/32
EOF

wg-quick up wg0
systemctl enable wg-quick@wg0
```

### Subnet və Routing

```bash
# Subnet = Şəbəkənin alt-bölməsi
# VPC → Subnet → Instance

# Public vs Private subnet:
# Public:  Internet Gateway (IGW) ilə, internet access
# Private: NAT Gateway ilə outbound only
# Isolated: Heç bir internet access

# Routing Table:
# Destination      Target
# 10.0.0.0/16      local        (VPC daxili)
# 0.0.0.0/0        igw-12345    (Internet Gateway)
# 0.0.0.0/0        nat-67890    (NAT Gateway, private subnet)

# Example architecture:
# VPC: 10.0.0.0/16
# ├── Public Subnet A:  10.0.1.0/24 (ALB, Bastion)
# ├── Public Subnet B:  10.0.2.0/24 (ALB, Bastion)
# ├── Private Subnet A: 10.0.10.0/24 (ECS, EC2)
# ├── Private Subnet B: 10.0.11.0/24 (ECS, EC2)
# ├── DB Subnet A:      10.0.20.0/24 (RDS)
# └── DB Subnet B:      10.0.21.0/24 (RDS)

# Network troubleshooting commands
ping 8.8.8.8                       # ICMP reachability
traceroute example.com             # Route path
mtr example.com                    # Continuous traceroute
netstat -tulpn                     # Open ports
ss -tulpn                          # Faster netstat alternative
tcpdump -i eth0 port 80            # Packet capture
nmap -p 80,443 example.com         # Port scan
curl -v https://example.com        # Verbose HTTP
```

## Praktiki Nümunələr (Practical Examples)

### CIDR hesablama

```bash
# Suallı: 10.0.0.0/16 VPC-də 4 subnet yaradın (eyni ölçülü)
# Cavab:
# /16 = 65,536 IP
# 4 subnet = hər biri /18 (16,384 IP)
# 
# Subnet 1: 10.0.0.0/18   (10.0.0.0   - 10.0.63.255)
# Subnet 2: 10.0.64.0/18  (10.0.64.0  - 10.0.127.255)
# Subnet 3: 10.0.128.0/18 (10.0.128.0 - 10.0.191.255)
# Subnet 4: 10.0.192.0/18 (10.0.192.0 - 10.0.255.255)

# Python ilə hesablama
python3 -c "
import ipaddress
net = ipaddress.ip_network('10.0.0.0/16')
for subnet in net.subnets(new_prefix=18):
    print(subnet, subnet.num_addresses)
"
```

### Nginx Load Balancer

```nginx
upstream laravel_backend {
    least_conn;
    server 10.0.1.10:80 weight=3 max_fails=3 fail_timeout=30s;
    server 10.0.1.11:80 weight=2;
    server 10.0.1.12:80 backup;
    
    keepalive 32;
}

server {
    listen 443 ssl http2;
    server_name api.example.com;
    
    ssl_certificate /etc/ssl/cert.pem;
    ssl_certificate_key /etc/ssl/key.pem;
    
    location / {
        proxy_pass http://laravel_backend;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        proxy_connect_timeout 30s;
        proxy_read_timeout 60s;
    }
    
    location /health {
        access_log off;
        return 200 "healthy\n";
    }
}
```

## PHP/Laravel ilə İstifadə

### Laravel HTTP Client

```php
use Illuminate\Support\Facades\Http;

// Sadə request
$response = Http::get('https://api.example.com/users');

// Headers
$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $token,
    'Accept' => 'application/json',
])->get('https://api.example.com/data');

// Timeout və retry
$response = Http::timeout(10)
    ->retry(3, 100, function ($exception, $request) {
        return $exception instanceof ConnectionException;
    })
    ->get('https://api.example.com/data');

// POST JSON
$response = Http::post('https://api.example.com/users', [
    'name' => 'John',
    'email' => 'john@example.com',
]);

// Response methods
$response->json();        // Array
$response->body();        // Raw body
$response->status();      // 200
$response->ok();          // true/false
$response->failed();      // true/false
$response->header('X-Rate-Limit');
```

### Laravel Trust Proxies (Load Balancer arxasında)

```php
// app/Http/Middleware/TrustProxies.php
namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    // Load balancer/proxy IP-ləri
    protected $proxies = [
        '10.0.0.0/8',        // AWS ALB private IPs
        '172.16.0.0/12',
        '192.168.0.0/16',
    ];
    
    // Və ya bütün proxy-lərə güvən (yalnız private network-də)
    // protected $proxies = '*';
    
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
```

### Laravel DNS lookup

```php
// Custom DNS resolver
use React\Dns\Resolver\Factory;

$factory = new Factory();
$resolver = $factory->create('8.8.8.8');

$resolver->resolve('example.com')->then(function ($ip) {
    echo "IP: $ip\n";
});

// PHP native DNS functions
$records = dns_get_record('example.com', DNS_A);
$mx = dns_get_record('example.com', DNS_MX);

// Laravel-də domain MX qeydi yoxlama (email validation)
use Egulias\EmailValidator\Validation\DNSCheckValidation;

$validator = new EmailValidator();
if ($validator->isValid($email, new DNSCheckValidation())) {
    // Email domain has valid MX record
}
```

### Laravel SSL/TLS konfiqurasiyası

```php
// config/app.php
'url' => env('APP_URL', 'https://example.com'),
'asset_url' => env('ASSET_URL'),

// AppServiceProvider boot()
public function boot()
{
    if (config('app.env') === 'production') {
        \URL::forceScheme('https');
    }
}

// Cookie secure flag
// config/session.php
'secure' => env('SESSION_SECURE_COOKIE', true),
'same_site' => 'lax',
'http_only' => true,
```

## Interview Sualları (5-10 Q&A)

**S1: TCP və UDP arasında fərq nədir? Hər biri nə üçün istifadə olunur?**
C: TCP – connection-oriented, reliable, ordered, 3-way handshake ilə əlaqə qurur. HTTP, HTTPS, SSH, email üçün istifadə olunur. UDP – connectionless, unreliable amma sürətli. DNS, DHCP, video streaming, VoIP, gaming üçün ideal. TCP data bütövlüyü vacibdir, UDP-də sürət prioritetdir.

**S2: CIDR /24 ilə /28 fərqi nədir?**
C: /24 = 256 IP adres (254 usable), subnet mask 255.255.255.0. /28 = 16 IP adres (14 usable), subnet mask 255.255.255.240. /24 daha böyük subnet, /28 daha kiçik (point-to-point linklər üçün /30 də istifadə olunur). Prefix nə qədər böyükdürsə (/32 tam host), subnet o qədər kiçikdir.

**S3: DNS propagation nədir və niyə uzun çəkir?**
C: DNS propagation – DNS dəyişikliyinin bütün dünyada DNS cache-lərinə yayılması. Uzun çəkir, çünki resolver-lər TTL (Time-To-Live) müddətincə köhnə qeydi cache-də saxlayır. TTL 24 saatdırsa, dəyişiklik 24-48 saat tam görünməyə bilər. Migration-dan əvvəl TTL-ni 300 saniyəyə endirmək tövsiyə olunur.

**S4: Forward proxy və reverse proxy arasında fərq nədir?**
C: Forward proxy – client-lər üçün (məs. korporativ şəbəkədə filterləmə, caching, anonymity). Reverse proxy – server-lər üçün (load balancing, SSL termination, caching, security). Nginx, HAProxy, AWS ALB – reverse proxy nümunələri. Laravel adətən reverse proxy arxasında işləyir.

**S5: Layer 4 vs Layer 7 load balancer fərqi?**
C: Layer 4 – TCP/UDP səviyyəsində işləyir, IP+Port əsaslı routing, content görmür, çox sürətli (AWS NLB). Layer 7 – HTTP/HTTPS, header, URL path, cookie əsaslı routing, SSL termination edir, content-aware (AWS ALB, Nginx). Layer 7 daha çox imkan, Layer 4 daha yüksək performans.

**S6: HTTP/2 HTTP/1.1-dən nə ilə fərqlənir?**
C: HTTP/2 – binary protocol (HTTP/1.1 text), multiplexing (bir TCP üzərində bir neçə request paralel), server push (browser sormadan resource göndərmək), header compression (HPACK). Performans xüsusilə çox resurslu səhifələrdə 50%+ yaxşılaşa bilər. TLS məcburi deyil, amma praktikada HTTPS üzərində işlədilir.

**S7: NAT nədir və nə üçün lazımdır?**
C: NAT (Network Address Translation) – private IP adresləri public IP-yə çevirir. IPv4 adres defisiti səbəbiylə yaranıb. Private instance-lərin internet-ə outbound çıxışı üçün istifadə olunur (package update, API call). AWS-də NAT Gateway bu funksiyanı yerinə yetirir. Inbound traffic-ə icazə vermir – buna ayrıca load balancer və ya Elastic IP lazımdır.

**S8: DNS record A, CNAME, MX fərqi?**
C: A record – domain adı → IPv4 adres (example.com → 192.0.2.1). CNAME – alias, başqa domain-ə yönləndirmə (www → example.com), root domain üçün istifadə olunmur. MX – mail server (priority + hostname), email routing üçün. AWS-də Route53 ALIAS record CNAME-ə bənzər amma root domain üçün də işləyir.

**S9: Stateful vs Stateless firewall fərqi?**
C: Stateless firewall – hər packet-i ayrı-ayrı yoxlayır, əvvəlki paketlərdən xəbəri yoxdur (AWS NACL). Stateful firewall – əlaqə state-ini yadda saxlayır, established connection cavablarına avtomatik icazə verir (AWS Security Group, iptables conntrack). Stateful daha təhlükəsiz və istifadəsi asandır, amma resource tələb edir.

**S10: Private IP range-ləri hansılardır və niyə rezerv edilib?**
C: RFC 1918 private range-ləri: 10.0.0.0/8 (böyük şəbəkələr üçün), 172.16.0.0/12 (orta), 192.168.0.0/16 (kiçik, ev/ofis). Bu adreslər internet-də marşrutlaşdırılmır, yəni yalnız daxili şəbəkə üçün istifadə olunur. IPv4 adres defisitini yüngülləşdirmək və təhlükəsizlik (internal resources internet-dən gizli) məqsədi ilə ayrılıb.

## Best Practices

1. **Subnet planlaşdırması**: VPC /16 götürün, ehtiyatlı subnet sxemi qurun (dev/staging/prod ayrı).
2. **Public subnet-də yalnız LB və Bastion**: Uygulama server-lərini private subnet-də saxlayın.
3. **Private IP istifadə edin**: Services arasında private IP (məs. RDS) – təhlükəsizlik və qiymət.
4. **DNS TTL strategy**: Dayanıqlı qeydlər üçün uzun TTL (3600s), dəyişəcək qeydlər üçün qısa (300s).
5. **HTTPS everywhere**: HTTP-dən HTTPS-ə 301 redirect, HSTS header qoyun.
6. **Health check endpoint**: `/health` endpoint yaradın (DB, cache yoxlamalı), LB istifadə etsin.
7. **Connection pooling**: Database və Redis connection-larını pool edin (Laravel persistent connections).
8. **CDN istifadə edin**: Statik fayllar üçün CloudFront/Cloudflare, origin yükünü azaldar.
9. **DDoS protection**: AWS Shield, Cloudflare, rate limiting (Nginx limit_req).
10. **Network segmentation**: Public/Private/Database subnet ayrı, security group-larla məhdudlaşdırın.
11. **VPC Flow Logs**: Aktivləşdirin, şübhəli trafik analizi üçün.
12. **IPv6 planlaşdırması**: Yeni şəbəkələr üçün IPv6 dəstəyi əlavə edin.
13. **VPN client routing**: Split-tunnel və ya full-tunnel – ehtiyaca görə seçin.
14. **Load balancer sticky session**: Yalnız zəruri olduqda (session storage external olmalıdır).
15. **Network monitoring**: Prometheus + Blackbox Exporter ilə endpoint-ləri yoxlayın.
