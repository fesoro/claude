# TCP/IP Model (Junior)

## İcmal

TCP/IP (Transmission Control Protocol / Internet Protocol) modeli İnternetin əsasını təşkil edən 4 layerli praktiki network modeldir. 1970-ci illərdə DARPA tərəfindən yaradılıb və bu gün bütün İnternet bu model üzərində işləyir. OSI modelindən fərqli olaraq, TCP/IP real implementasiyaya əsaslanır.

```
+------------------------+
| 4. Application Layer   |  HTTP, FTP, DNS, SMTP, SSH
+------------------------+
| 3. Transport Layer     |  TCP, UDP
+------------------------+
| 2. Internet Layer      |  IP, ICMP, ARP, IGMP
+------------------------+
| 1. Network Access      |  Ethernet, Wi-Fi, PPP
|    (Link Layer)        |
+------------------------+
```

## Niyə Vacibdir

TCP/IP modeli real dünyada işləyən modeldır — OSI isə nəzəri referansdır. Backend developer olaraq database connection-larını, Redis-i, API çağırışlarını, hər biri TCP/IP üzərindən işləyir. IP addressing, subnet-ləmə, NAT anlayışları Docker network-lərini, Kubernetes pod-ları arasındakı kommunikasiyanı başa düşmək üçün tələb olunur. Cloud infrastrukturda VPC, security group, routing konfiqurasiyası bu bilikə söykənir.

## Əsas Anlayışlar

### Layer 1: Network Access Layer (Link Layer)

OSI modelinin Physical + Data Link layer-lərinə uyğun gəlir. Fiziki medium üzərindən frame-lərin ötürülməsinə cavabdehdir.

**Protokollar:** Ethernet (802.3), Wi-Fi (802.11), ARP, PPP

Funksiyalar:
- Physical addressing (MAC)
- Frame-lərin yaradılması və ötürülməsi
- Error detection (CRC/FCS)
- Media access control (CSMA/CD, CSMA/CA)

```
Ethernet Frame:
+-----------+----------+----------+------+---------+-----+
| Preamble  | Dest MAC | Src MAC  | Type | Payload | FCS |
| 8 bytes   | 6 bytes  | 6 bytes  | 2B   | 46-1500 | 4B  |
+-----------+----------+----------+------+---------+-----+

Type field values:
  0x0800 = IPv4
  0x0806 = ARP
  0x86DD = IPv6
```

### Layer 2: Internet Layer

OSI-nin Network layer-inə uyğun gəlir. Logical addressing və routing.

**Protokollar:** IPv4, IPv6, ICMP, IGMP

#### IPv4 Header

```
 0                   1                   2                   3
 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|Version|  IHL  |    DSCP   |ECN|         Total Length          |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|         Identification        |Flags|      Fragment Offset    |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|  Time to Live |    Protocol   |       Header Checksum         |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|                       Source Address                          |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|                    Destination Address                        |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
```

#### IP Addressing (IPv4)

IPv4 address 32-bit-dir, 4 oktet ilə yazılır (dotted decimal):

```
IP: 192.168.1.100
Binary: 11000000.10101000.00000001.01100100

IP Address Classes:
+-------+----------------+------------------+------------+
| Class | Range          | Default Mask     | Networks   |
+-------+----------------+------------------+------------+
| A     | 1.0.0.0 -      | 255.0.0.0 (/8)  | 128        |
|       | 126.255.255.255|                  |            |
| B     | 128.0.0.0 -    | 255.255.0.0 (/16)| 16,384    |
|       | 191.255.255.255|                  |            |
| C     | 192.0.0.0 -    | 255.255.255.0    | 2,097,152  |
|       | 223.255.255.255| (/24)            |            |
| D     | 224.0.0.0 -    | Multicast        | N/A        |
|       | 239.255.255.255|                  |            |
| E     | 240.0.0.0 -    | Reserved         | N/A        |
|       | 255.255.255.255|                  |            |
+-------+----------------+------------------+------------+
```

#### Private IP Ranges

```
Class A: 10.0.0.0    - 10.255.255.255   (10.0.0.0/8)
Class B: 172.16.0.0  - 172.31.255.255   (172.16.0.0/12)
Class C: 192.168.0.0 - 192.168.255.255  (192.168.0.0/16)
Loopback: 127.0.0.0  - 127.255.255.255  (127.0.0.0/8)
```

#### Subnetting

Subnetting böyük network-u kiçik subnet-lərə bölməkdir.

```
Nümunə: 192.168.1.0/26 subnet-ini hesablayaq

/26 = 26 bit network, 6 bit host
Subnet mask: 255.255.255.192 (11111111.11111111.11111111.11000000)

Hostların sayı: 2^6 - 2 = 62 usable hosts
Subnet-lərin sayı: 2^2 = 4 subnets (çünki Class C /24-dən /26-ya 2 bit əlavə)

Subnet 1: 192.168.1.0/26    (hosts: .1 - .62,    broadcast: .63)
Subnet 2: 192.168.1.64/26   (hosts: .65 - .126,  broadcast: .127)
Subnet 3: 192.168.1.128/26  (hosts: .129 - .190, broadcast: .191)
Subnet 4: 192.168.1.192/26  (hosts: .193 - .254, broadcast: .255)
```

#### CIDR (Classless Inter-Domain Routing)

CIDR classful addressing-in yerini alır. Slash notation istifadə edir:

```
CIDR Notation    Subnet Mask         Hosts
/8               255.0.0.0           16,777,214
/16              255.255.0.0         65,534
/24              255.255.255.0       254
/25              255.255.255.128     126
/26              255.255.255.192     62
/27              255.255.255.224     30
/28              255.255.255.240     14
/29              255.255.255.248     6
/30              255.255.255.252     2
/31              255.255.255.254     2 (point-to-point)
/32              255.255.255.255     1 (single host)
```

CIDR aggregation (supernetting):
```
192.168.0.0/24 + 192.168.1.0/24 + 192.168.2.0/24 + 192.168.3.0/24
= 192.168.0.0/22 (bir route ilə 4 network)
```

#### IPv6

128-bit address, 8 qrup hexadecimal:

```
Full:    2001:0db8:0000:0000:0000:0000:0000:0001
Short:   2001:db8::1

IPv6 vs IPv4:
+------------------+------------------+
|      IPv4        |      IPv6        |
+------------------+------------------+
| 32 bits          | 128 bits         |
| ~4.3 billion     | ~340 undecillion |
| NAT required     | NAT not needed   |
| ARP              | NDP              |
| Broadcast        | Multicast        |
| Optional IPSec   | Built-in IPSec   |
| Header: 20-60B   | Header: 40B fixed|
+------------------+------------------+
```

### Layer 3: Transport Layer

End-to-end communication. TCP və UDP bu layer-dədir.

```
TCP (Transmission Control Protocol):
- Connection-oriented (3-way handshake)
- Reliable delivery (acknowledgments)
- Ordered packets (sequence numbers)
- Flow control (sliding window)
- Congestion control

UDP (User Datagram Protocol):
- Connectionless
- Unreliable (no acknowledgments)
- No ordering guarantee
- No flow control
- Minimal overhead (8-byte header)
```

### Layer 4: Application Layer

OSI-nin Session + Presentation + Application layer-lərinə uyğun gəlir.

```
+----------+------+----------+
| Protocol | Port | Function |
+----------+------+----------+
| HTTP     | 80   | Web      |
| HTTPS    | 443  | Secure W |
| FTP      | 21   | File     |
| SSH      | 22   | Secure   |
| SMTP     | 25   | Email tx |
| DNS      | 53   | Names    |
| POP3     | 110  | Email rx |
| IMAP     | 143  | Email rx |
| MySQL    | 3306 | Database |
| Redis    | 6379 | Cache    |
| PostgreS | 5432 | Database |
+----------+------+----------+
```

### OSI vs TCP/IP Müqayisəsi

```
    OSI Model              TCP/IP Model
+------------------+   +------------------+
| 7. Application   |   |                  |
+------------------+   |                  |
| 6. Presentation  |   | 4. Application   |
+------------------+   |                  |
| 5. Session       |   |                  |
+------------------+   +------------------+
| 4. Transport     |   | 3. Transport     |
+------------------+   +------------------+
| 3. Network       |   | 2. Internet      |
+------------------+   +------------------+
| 2. Data Link     |   |                  |
+------------------+   | 1. Network Access|
| 1. Physical      |   |                  |
+------------------+   +------------------+
```

### NAT (Network Address Translation)

Private IP-ləri public IP-yə çevirir:

```
Private Network              NAT Router              Internet
192.168.1.100:5000  -->  203.0.113.1:40000  -->  93.184.216.34:80
192.168.1.101:5001  -->  203.0.113.1:40001  -->  93.184.216.34:80

NAT Table:
+------------------+-------------------+-------------------+
| Internal         | External          | Destination       |
+------------------+-------------------+-------------------+
| 192.168.1.100:5000| 203.0.113.1:40000| 93.184.216.34:80 |
| 192.168.1.101:5001| 203.0.113.1:40001| 93.184.216.34:80 |
+------------------+-------------------+-------------------+
```

### ICMP (Internet Control Message Protocol)

Network diagnostic üçün istifadə olunur:

```
Type 0: Echo Reply (ping response)
Type 3: Destination Unreachable
Type 8: Echo Request (ping)
Type 11: Time Exceeded (traceroute)
Type 30: Traceroute
```

## Praktik Baxış

**Real layihələrdə istifadəsi:**
- Docker network-ləri `/24` subnet-lər kimi işləyir; `docker network inspect` ilə görə bilərsiniz
- Kubernetes pod CIDR adətən `10.244.0.0/16` kimidir — hər node bir subnet alır
- AWS VPC-də subnet-ləri doğru ölçüdə seçmək resource israfının qarşısını alır

**Trade-off-lar:**
- Böyük subnet → daha çox host, amma broadcast domain genişlənir
- Kiçik subnet → əhatəli idarəetmə, amma genişlənmə çətindir
- IPv6 istifadəsi əsasən cloud/CDN tərəfindədir; backend-də hələ IPv4 dominant-dır

**Common mistakes:**
- Private IP range-lərin overlap etməsi (məs., VPN + Docker hər ikisi `172.16.0.0/12` istifadə edir)
- Subnetting zamanı broadcast adresini host kimi planlamaq (2 adres itirilir: network + broadcast)
- NAT arxasında olan servisin real client IP-sini almağı unutmaq — `X-Forwarded-For` lazımdır

**Anti-pattern:** `FILTER_FLAG_NO_PRIV_RANGE` yoxlamadan gələn IP-ə güvənmək — load balancer arxasında real IP `X-Forwarded-For`-dan gəlir.

## Nümunələr

### Ümumi Nümunə

Docker-də iki container-in kommunikasiyası: hər container `172.17.0.0/16` subnet-dən IP alır. NAT sayəsində host-un public IP-si ilə İnternəetə çıxırlar. `docker0` bridge — Layer 2 switch kimi davranır.

### Kod Nümunəsi

```php
// IP address operations in PHP
$ip = '192.168.1.100';

// Validate IP
filter_var($ip, FILTER_VALIDATE_IP);                    // true
filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);  // true
filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);  // false
filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE); // false (private)

// Convert IP to long and back
$long = ip2long('192.168.1.100');   // 3232235876
$ip = long2ip(3232235876);          // '192.168.1.100'

// Check if IP is in subnet (CIDR)
function ipInCidr(string $ip, string $cidr): bool
{
    [$subnet, $mask] = explode('/', $cidr);
    return (ip2long($ip) & ~((1 << (32 - $mask)) - 1)) === ip2long($subnet);
}

ipInCidr('192.168.1.100', '192.168.1.0/24'); // true
ipInCidr('10.0.0.1', '192.168.1.0/24');      // false

// DNS lookup
$ip = gethostbyname('example.com');           // '93.184.216.34'
$host = gethostbyaddr('93.184.216.34');       // 'example.com'
$records = dns_get_record('example.com', DNS_ALL); // All DNS records

// Laravel - Getting client IP (respecting proxies)
$clientIp = $request->ip();
```

Laravel-də trusted proxy konfiqurasiyası:

```php
// app/Http/Middleware/TrustProxies.php
class TrustProxies extends Middleware
{
    protected $proxies = [
        '10.0.0.0/8',
        '172.16.0.0/12',
    ];

    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;
}
```

Network-related Laravel configuration:

```php
// config/database.php - TCP connection to database
'mysql' => [
    'host' => env('DB_HOST', '127.0.0.1'),  // Layer 3: IP
    'port' => env('DB_PORT', '3306'),         // Layer 4: Port
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
],

// config/cache.php - Redis TCP connection
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'default' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),  // IP
        'port' => env('REDIS_PORT', 6379),           // Port
        'password' => env('REDIS_PASSWORD', null),
        'database' => env('REDIS_DB', 0),
    ],
],
```

## Praktik Tapşırıqlar

**Tapşırıq 1: IP hesablamaları**

Verilmiş `10.0.0.0/22` subnet üçün tapın:
- Subnet mask
- Neçə host tutulur?
- İlk və son usable host IP-ləri
- Broadcast adresi

Cavab: mask=`255.255.252.0`, hosts=1022, first=`10.0.0.1`, last=`10.0.3.254`, broadcast=`10.0.3.255`

**Tapşırıq 2: Docker network analizi**

```bash
# Docker-in network-lərini görün
docker network ls

# Default bridge network-i inspect edin
docker network inspect bridge

# Subnet və gateway-i tapın
# Sonra bir container içindən başqa container-ə ping vurun
```

**Tapşırıq 3: Private IP yoxlama middleware**

Laravel-də gələn request-in IP-sinin private range-dən olub-olmadığını yoxlayan middleware yazın. Private IP-lər üçün 403 qaytarın (internal-only API endpoint).

```php
class AllowOnlyPublicIps
{
    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();

        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            abort(403, 'Private IP not allowed');
        }

        return $next($request);
    }
}
```

## Əlaqəli Mövzular

- [OSI Model](01-osi-model.md)
- [TCP](03-tcp.md)
- [UDP](04-udp.md)
- [DNS](07-dns.md)
- [IP Addressing](41-ip-addressing.md)
- [Network Troubleshooting](30-network-troubleshooting.md)
