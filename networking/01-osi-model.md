# OSI Model (Junior)

## İcmal

OSI modeli ISO (International Organization for Standardization) tərəfindən 1984-cü ildə yaradılmış 7 layerli referans modeldir. Network kommunikasiyasını standartlaşdırmaq üçün istifadə olunur. Hər layer müəyyən bir funksiyanı yerinə yetirir və yalnız öz yuxarıdakı və aşağıdakı layer ilə əlaqə saxlayır.

OSI modeli **konseptual** modeldir — real dünyada birbaşa implement olunmur, amma networking-i anlamaq üçün ən vacib framework-dür.

```
+-------------------+
| 7. Application    |  <-- User interaction
+-------------------+
| 6. Presentation   |  <-- Data formatting
+-------------------+
| 5. Session        |  <-- Session management
+-------------------+
| 4. Transport      |  <-- End-to-end delivery
+-------------------+
| 3. Network        |  <-- Routing
+-------------------+
| 2. Data Link      |  <-- Node-to-node delivery
+-------------------+
| 1. Physical       |  <-- Bits on the wire
+-------------------+
```

## Niyə Vacibdir

OSI modeli olmadan network problemlərini debugging etmək çox çətindir. Hər layer öz məsuliyyət dairəsini müəyyən etdiyinə görə, "ping işləyir amma HTTP işləmir" kimi vəziyyətlər dəqiq lokalizasiya edilir. Backend developer kimi TCP connection timeout-larını, TLS handshake xətalarını, ya da proxy konfiqurasiyasını debug edərkən bu layer-ların nə etdiyini bilmək vacibdir.

## Əsas Anlayışlar

### Layer 1: Physical Layer

**Funksiya:** Raw bitlərin fiziki medium üzərindən ötürülməsi.

- Elektrik siqnalları, işıq impulsları, radio dalğaları
- Bit rate, voltage levels, pin layout, cable specs
- Simplex, half-duplex, full-duplex kommunikasiya

**Cihazlar:** Hub, Repeater, Modem, Cable
**PDU (Protocol Data Unit):** Bits
**Protokollar:** Ethernet (physical), USB, Bluetooth (physical), DSL

```
Sender                           Receiver
  |                                 |
  |  10110101 (electrical signals)  |
  |  =============================> |
  |  (copper wire / fiber optic)    |
```

Nümunələr:
- Cat5e/Cat6 Ethernet cable (1 Gbps)
- Fiber optic cable (single-mode: 100+ km, multi-mode: 2 km)
- Wi-Fi radio frequencies (2.4 GHz, 5 GHz, 6 GHz)

### Layer 2: Data Link Layer

**Funksiya:** Node-to-node data transfer, error detection, MAC addressing.

İki sub-layer var:
- **LLC (Logical Link Control):** Flow control, error checking
- **MAC (Media Access Control):** Physical addressing, media access

**Cihazlar:** Switch, Bridge, NIC
**PDU:** Frame
**Protokollar:** Ethernet (802.3), Wi-Fi (802.11), PPP, ARP

```
+----------+-------------------+------+---------+----------+
| Preamble | Dest MAC | Src MAC | Type | Payload | FCS      |
| 8 bytes  | 6 bytes  | 6 bytes | 2B   | 46-1500 | 4 bytes  |
+----------+-------------------+------+---------+----------+
```

**MAC Address:** 48-bit unique identifier (e.g., `AA:BB:CC:DD:EE:FF`)
- First 24 bits: OUI (Organizationally Unique Identifier) — vendor
- Last 24 bits: Device-specific

**ARP (Address Resolution Protocol):**
```
1. PC-A: "Kim 192.168.1.1 IP-yə sahibdir?" (broadcast)
2. Router: "Mənim MAC-im AA:BB:CC:DD:EE:FF" (unicast reply)
3. PC-A ARP table-a yazır: 192.168.1.1 -> AA:BB:CC:DD:EE:FF
```

### Layer 3: Network Layer

**Funksiya:** Logical addressing (IP), routing, packet forwarding.

**Cihazlar:** Router, Layer 3 Switch
**PDU:** Packet
**Protokollar:** IP (IPv4/IPv6), ICMP, OSPF, BGP, RIP

```
+----------+-----------+-----------+---------+
| Version  | Src IP    | Dest IP   | Payload |
| IHL, TTL | 4 bytes   | 4 bytes   |         |
+----------+-----------+-----------+---------+
```

Key concepts:
- **Routing:** Paketlərin optimal yolla göndərilməsi
- **TTL (Time to Live):** Loop-ların qarşısını alır (hər hop-da 1 azalır)
- **Fragmentation:** Böyük paketlərin kiçik hissələrə bölünməsi (MTU = 1500 bytes)

Routing table nümunəsi:
```
Destination     Gateway         Interface
192.168.1.0/24  0.0.0.0         eth0
10.0.0.0/8      192.168.1.1     eth0
0.0.0.0/0       192.168.1.1     eth0  (default route)
```

### Layer 4: Transport Layer

**Funksiya:** End-to-end communication, segmentation, flow control, error recovery.

**PDU:** Segment (TCP) / Datagram (UDP)
**Protokollar:** TCP, UDP, SCTP

Port ranges:
- Well-known: 0-1023 (HTTP=80, HTTPS=443, SSH=22, FTP=21)
- Registered: 1024-49151 (MySQL=3306, PostgreSQL=5432, Redis=6379)
- Dynamic/Private: 49152-65535

```
TCP vs UDP:
+------------------+------------------+
|       TCP        |       UDP        |
+------------------+------------------+
| Connection-based | Connectionless   |
| Reliable         | Unreliable       |
| Ordered          | Unordered        |
| Slower           | Faster           |
| Flow control     | No flow control  |
| HTTP, FTP, SSH   | DNS, Video, VoIP |
+------------------+------------------+
```

### Layer 5: Session Layer

**Funksiya:** Session-ların yaradılması, idarə edilməsi və bağlanması.

**Protokollar:** NetBIOS, PPTP, RPC, SMB
**PDU:** Data

Key concepts:
- **Session establishment:** Authentication, authorization
- **Session maintenance:** Checkpoint/recovery, keep-alive
- **Session termination:** Graceful close

```
Client                    Server
  |--- Session Request --->|
  |<-- Session Accept -----|
  |                        |
  |=== Data Exchange ======|
  |                        |
  |--- Session Close ----->|
  |<-- Session Close ACK --|
```

### Layer 6: Presentation Layer

**Funksiya:** Data formatting, encryption/decryption, compression.

**Protokollar:** SSL/TLS (encryption), JPEG/GIF/PNG (image), MPEG (video), ASCII/UTF-8 (text)
**PDU:** Data

Key concepts:
- **Translation:** ASCII <-> EBCDIC, UTF-8 <-> UTF-16
- **Encryption:** Data-nın sifrəli formata çevrilməsi (SSL/TLS)
- **Compression:** Data size-ın azaldılması (gzip, deflate)

```
Application Data: {"name": "Orkhan"}
        |
        v  (Presentation Layer)
Encrypted: a7f3b2c1d4e5...
Compressed: [gzip encoded]
```

### Layer 7: Application Layer

**Funksiya:** End-user servislər, network applications.

**Protokollar:** HTTP, HTTPS, FTP, SMTP, DNS, SSH, SNMP, LDAP
**PDU:** Data

Bu layer istifadəçiyə ən yaxın olan layer-dir. Web browser, email client, file transfer application-lar bu layer-də işləyir.

```
Browser (Application Layer)
  |
  |  GET /index.html HTTP/1.1
  |  Host: example.com
  |
  v
Web Server
```

### Data Encapsulation

Data göndərildikdə hər layer öz header-ini əlavə edir (encapsulation). Qəbul edildikdə hər layer öz header-ini silir (de-encapsulation).

```
Sending side:                          Receiving side:

[Application Data]                     [Application Data]
        |                                     ^
        v                                     |
[Presentation Header + Data]          [Remove Presentation Header]
        |                                     ^
        v                                     |
[Session Header + Data]               [Remove Session Header]
        |                                     ^
        v                                     |
[TCP Header + Data] = Segment         [Remove TCP Header]
        |                                     ^
        v                                     |
[IP Header + Segment] = Packet        [Remove IP Header]
        |                                     ^
        v                                     |
[Frame Header + Packet + FCS] = Frame [Remove Frame Header]
        |                                     ^
        v                                     |
[Bits: 10110101...]                   [Bits: 10110101...]
```

### PDU at Each Layer

| Layer | PDU Name | Contains |
|-------|----------|----------|
| 7 - Application | Data | User data |
| 6 - Presentation | Data | Formatted/encrypted data |
| 5 - Session | Data | Session-managed data |
| 4 - Transport | Segment/Datagram | Port numbers + data |
| 3 - Network | Packet | IP addresses + segment |
| 2 - Data Link | Frame | MAC addresses + packet |
| 1 - Physical | Bits | Raw binary |

### Mnemonic (Yadda saxlamaq üçün)

**Top to bottom:** All People Seem To Need Data Processing
**Bottom to top:** Please Do Not Throw Sausage Pizza Away

## Praktik Baxış

**Real layihələrdə istifadəsi:**
- Backend developer olaraq əsasən Layer 4-7 ilə işləyirsiz (TCP, HTTP, TLS, DNS)
- Docker/Kubernetes network problemi debuggingdə Layer 2-3 anlayışı lazımdır
- Load balancer L4 (TCP) və L7 (HTTP) formalarında gəlir — ikisinin fərqini bilmək vacibdir

**Troubleshooting strategiyası:**
- Aşağıdan yuxarıya gedin: Physical → Data Link → Network → Transport → Application
- Ping işləyirsə amma HTTP işləmirsə — problem Layer 4-7 arasındadır
- TCP connect olur amma TLS handshake fails — problem Layer 6-dadır

**Security hər layer-də lazımdır:**
- Physical: Server room access control
- Data Link: Port security, MAC filtering
- Network: Firewalls, ACLs
- Transport: TLS/SSL
- Application: Authentication, input validation

**Common mistakes:**
- OSI-ni real implementation kimi düşünmək — bu yalnız referans modeldir
- Switch-in Layer 2, Router-in Layer 3 olduğunu unutmaq
- MTU (1500 bytes) limitini nəzərə almamaq — böyük paketlər fragment olunur, bu performance-a təsir edir

**Ne zaman istifadə olunmamalı:**
- OSI terminologiyası real implementasiyada TCP/IP stack-i izah etmir; layihə dokumentasiyasında TCP/IP model terminlərini istifadə etmək daha dəqiqdir

## Nümunələr

### Ümumi Nümunə

Brauzerinizdə `https://example.com` yazdığınızda:

```
Layer 7 (Application):
  Browser creates HTTP GET request

Layer 6 (Presentation):
  TLS encrypts the data, compresses if needed

Layer 5 (Session):
  TLS session established/maintained

Layer 4 (Transport):
  TCP segments data, adds src port (49152) and dest port (443)

Layer 3 (Network):
  IP adds src IP (192.168.1.100) and dest IP (93.184.216.34)

Layer 2 (Data Link):
  Ethernet adds src MAC and dest MAC (gateway MAC)

Layer 1 (Physical):
  Converts to electrical signals on the wire
```

### Kod Nümunəsi

OSI modeli birbaşa PHP-də implement olunmasa da, PHP müxtəlif layer-lərdə işləyir:

```php
// Layer 7 - Application Layer: HTTP Request in Laravel
Route::get('/api/users', function () {
    return User::all(); // HTTP response
});

// Layer 6 - Presentation Layer: JSON encoding
return response()->json(['name' => 'Orkhan'], 200, [], JSON_UNESCAPED_UNICODE);

// Layer 4 - Transport Layer: PHP Socket (TCP)
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_connect($socket, '93.184.216.34', 80);
socket_write($socket, "GET / HTTP/1.1\r\nHost: example.com\r\n\r\n");
$response = socket_read($socket, 2048);
socket_close($socket);

// Layer 3 - Network Layer: IP address operations
$ip = request()->ip(); // Get client IP
$isPrivate = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) === false;

// PHP can inspect network info
$hostname = gethostbyaddr('93.184.216.34'); // Reverse DNS
$ip = gethostbyname('example.com');         // Forward DNS
```

Laravel Middleware — Layer analogy:

```php
// Middleware is like OSI layers - each adds/removes something
class EncryptCookies extends Middleware      // Like Presentation Layer
class VerifyCsrfToken extends Middleware     // Like Session Layer
class ThrottleRequests extends Middleware    // Like Transport Layer (flow control)
class TrustProxies extends Middleware        // Like Network Layer
```

## Praktik Tapşırıqlar

**Tapşırıq 1: Network tool-larla layer-ları müşahidə edin**

```bash
# Layer 1-2: Network interface stats
ip link show

# Layer 2: ARP table (IP → MAC mapping)
arp -a

# Layer 3: Routing table
ip route show

# Layer 3: ICMP ping (packet-level connectivity)
ping -c 4 google.com

# Layer 3: Traceroute (hər hop-u görürsüz)
traceroute google.com

# Layer 4: Open TCP/UDP connections
ss -tuln
netstat -an

# Layer 7: HTTP request
curl -v https://example.com
```

**Tapşırıq 2: Encapsulation-ı Wireshark ilə müşahidə edin**

1. Wireshark quraşdırın
2. `ping google.com` işlədin
3. ICMP paketini seçin
4. Aşağıda görəcəksiniz: Frame (L2) → IP (L3) → ICMP (L3/L4) — hər layer-in header-i göstərilir

**Tapşırıq 3: Troubleshooting simulyasiyası**

Verilmiş ssenari: "Laravel app-ınız database-ə qoşula bilmir"

Necə diaqnoz qoyursunuz:
1. L3: `ping db-host` — IP connectivity var mı?
2. L4: `telnet db-host 3306` — TCP port açıq mı?
3. L7: Laravel `.env` DB credentials doğrudur mu?
4. L7: MySQL user-in remote access icazəsi var mı?

## Əlaqəli Mövzular

- [TCP/IP Model](02-tcp-ip-model.md)
- [TCP](03-tcp.md)
- [UDP](04-udp.md)
- [HTTP Protocol](05-http-protocol.md)
- [Network Troubleshooting](30-network-troubleshooting.md)
