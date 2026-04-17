# TCP/IP Model

## Nədir? (What is it?)

TCP/IP (Transmission Control Protocol / Internet Protocol) modeli Internetin esasini teskil eden 4 layerli praktiki network modeldir. 1970-ci illerde DARPA terefinden yaradilib ve bugun butun Internet bu model uzerinde isleyir. OSI modelinden ferqli olaraq, TCP/IP real implementasiyaya esaslanir.

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

## Necə İşləyir? (How does it work?)

### Layer 1: Network Access Layer (Link Layer)

OSI modelinin Physical + Data Link layer-lerine uygun gelir. Fiziki medium uzerinden frame-lerin oturulmesine cavabdehdir.

**Protokollar:** Ethernet (802.3), Wi-Fi (802.11), ARP, PPP
**Funksiyalar:**
- Physical addressing (MAC)
- Frame-lerin yaradilmasi ve oturulmesi
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

OSI-nin Network layer-ine uygun gelir. Logical addressing ve routing.

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

IPv4 address 32-bit-dir, 4 oktet ile yazilir (dotted decimal):

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

Subnetting boyuk network-u kicik subnet-lere bolmekdir.

```
Numune: 192.168.1.0/26 subnet-ini hesablayaq

/26 = 26 bit network, 6 bit host
Subnet mask: 255.255.255.192 (11111111.11111111.11111111.11000000)

Hostlarin sayi: 2^6 - 2 = 62 usable hosts
Subnet-lerin sayi: 2^2 = 4 subnets (cunku Class C /24-den /26-ya 2 bit elave)

Subnet 1: 192.168.1.0/26    (hosts: .1 - .62,    broadcast: .63)
Subnet 2: 192.168.1.64/26   (hosts: .65 - .126,  broadcast: .127)
Subnet 3: 192.168.1.128/26  (hosts: .129 - .190, broadcast: .191)
Subnet 4: 192.168.1.192/26  (hosts: .193 - .254, broadcast: .255)
```

#### CIDR (Classless Inter-Domain Routing)

CIDR classful addressing-in yerini alir. Slash notation istifade edir:

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

**CIDR aggregation (supernetting):**
```
192.168.0.0/24 + 192.168.1.0/24 + 192.168.2.0/24 + 192.168.3.0/24
= 192.168.0.0/22 (bir route ile 4 network)
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

End-to-end communication. TCP ve UDP bu layer-dedir.

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

OSI-nin Session + Presentation + Application layer-lerine uygun gelir.

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

## Əsas Konseptlər (Key Concepts)

### OSI vs TCP/IP Comparison

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

Private IP-leri public IP-ye cevirir:

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

Network diagnostic ucun istifade olunur:

```
Type 0: Echo Reply (ping response)
Type 3: Destination Unreachable
Type 8: Echo Request (ping)
Type 11: Time Exceeded (traceroute)
Type 30: Traceroute
```

## PHP/Laravel ilə İstifadə

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
// In a Controller:
$clientIp = $request->ip();

// Trusted proxies in Laravel (app/Http/Middleware/TrustProxies.php)
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

### Network-related Laravel configuration

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

## Interview Sualları

### Q1: TCP/IP modelinin 4 layer-ini izah edin.
**A:** 1) Network Access - fiziki oturme, framing; 2) Internet - IP addressing, routing; 3) Transport - TCP/UDP, end-to-end delivery; 4) Application - HTTP, DNS, SMTP kimi user-facing protokollar.

### Q2: Subnetting nece isleyir? 192.168.1.0/26 nece subnet verir?
**A:** /26 demek 26 bit network, 6 bit host. 2^6 = 64 address per subnet (62 usable). 256/64 = 4 subnet: .0, .64, .128, .192.

### Q3: NAT nedir ve niye lazimdir?
**A:** NAT private IP-leri public IP-ye cevirir. IPv4-de address exhaustion problemini hell edir. Bir public IP ile bir cox private device Internet-e cixir. Hem security (internal IP-ler gizli qalir) hem de address conservation ucun istifade olunur.

### Q4: IPv4 ve IPv6 arasinda esas ferqler nelardir?
**A:** IPv4 32-bit (4.3 milyard), IPv6 128-bit (340 undecillion). IPv6-da NAT lazim deyil, IPSec built-in, broadcast yoxdur (multicast var), header fixed 40 byte-dir.

### Q5: CIDR nedir?
**A:** Classless Inter-Domain Routing - classful addressing (A, B, C) evezine flexible subnet masking istifade edir. /notation ile yazilir. Bu IP address space-in daha effektiv istifadesine imkan verir.

### Q6: Private IP range-leri hansilardir?
**A:** 10.0.0.0/8 (Class A), 172.16.0.0/12 (Class B), 192.168.0.0/16 (Class C). Bu IP-ler Internet-de route olunmur.

### Q7: Iki host eyni subnet-dedir ya yox - nece bilmek olar?
**A:** Her iki IP-ye subnet mask tetbiq et. Network address-ler eyni olsa, eyni subnet-dedir. Numune: 192.168.1.10 ve 192.168.1.200 /24 mask ile - her ikisi 192.168.1.0 network-undedir.

## Best Practices

1. **CIDR istifade edin:** Classful addressing kohnelib. CIDR ile IP space-i effektiv istifade edin.

2. **Private IP-ler secerken planlayin:** Boyuk network ucun 10.0.0.0/8, kicik ucun 192.168.0.0/16. Overlap olmamasi ucun diqqetli olun (VPN, peering).

3. **IPv6 transition planlayin:** Dual-stack (IPv4+IPv6) istifade edin. Yeni servislerde IPv6 support elave edin.

4. **Subnet sizing:** Lazim olandan biraz boyuk subnet secin (genislenme ucun). Amma cox boyuk subnet broadcast domain-i boyudur.

5. **VLSM (Variable Length Subnet Masking):** Ferqli subnet-ler ucun ferqli mask istifade edin. 50 host lazim olan subnet ucun /26, 10 host ucun /28 istifade edin.

6. **Documentation:** IP allocation-lari mutleq dokumentasiya edin. IPAM (IP Address Management) tool-larindan istifade edin.
