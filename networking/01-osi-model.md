# OSI Model (Open Systems Interconnection)

## Nədir? (What is it?)

OSI modeli ISO (International Organization for Standardization) terefinden 1984-cu ilde yaradilmis 7 layerli referans modeldir. Network kommunikasiyasini standartlashdirmaq ucun istifade olunur. Her layer mueyyyen bir funksiyanı yerine yetirir ve yalniz oz yuxaridaki ve asagidaki layer ile elaqe saxlayir.

OSI modeli **konseptual** modeldir - real dunyada birbasa implement olunmur, amma networking-i anlamaq ucun en vacib framework-dur.

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

## Necə İşləyir? (How does it work?)

### Layer 1: Physical Layer

**Funksiyanı:** Raw bitlarin fiziki medium uzerinden oturulmesi.

- Elektrik siqnallari, isiq impulslari, radio dalqalari
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

**Numuneler:**
- Cat5e/Cat6 Ethernet cable (1 Gbps)
- Fiber optic cable (single-mode: 100+ km, multi-mode: 2 km)
- Wi-Fi radio frequencies (2.4 GHz, 5 GHz, 6 GHz)

### Layer 2: Data Link Layer

**Funksiyanı:** Node-to-node data transfer, error detection, MAC addressing.

Iki sub-layer var:
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
- First 24 bits: OUI (Organizationally Unique Identifier) - vendor
- Last 24 bits: Device-specific

**ARP (Address Resolution Protocol):**
```
1. PC-A: "Kim 192.168.1.1 IP-ye sahibdir?" (broadcast)
2. Router: "Menim MAC-im AA:BB:CC:DD:EE:FF" (unicast reply)
3. PC-A ARP table-a yazir: 192.168.1.1 -> AA:BB:CC:DD:EE:FF
```

### Layer 3: Network Layer

**Funksiyanı:** Logical addressing (IP), routing, packet forwarding.

**Cihazlar:** Router, Layer 3 Switch
**PDU:** Packet
**Protokollar:** IP (IPv4/IPv6), ICMP, OSPF, BGP, RIP

```
+----------+-----------+-----------+---------+
| Version  | Src IP    | Dest IP   | Payload |
| IHL, TTL | 4 bytes   | 4 bytes   |         |
+----------+-----------+-----------+---------+
```

**Key concepts:**
- **Routing:** Paketlerin optimal yolla gonderilmesi
- **TTL (Time to Live):** Loop-larin qarsisini alir (her hop-da 1 azalir)
- **Fragmentation:** Boyuk paketlerin kicik hisselere bolunmesi (MTU = 1500 bytes)

**Routing table numunesi:**
```
Destination     Gateway         Interface
192.168.1.0/24  0.0.0.0         eth0
10.0.0.0/8      192.168.1.1     eth0
0.0.0.0/0       192.168.1.1     eth0  (default route)
```

### Layer 4: Transport Layer

**Funksiyanı:** End-to-end communication, segmentation, flow control, error recovery.

**PDU:** Segment (TCP) / Datagram (UDP)
**Protokollar:** TCP, UDP, SCTP

**Port ranges:**
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

**Funksiyanı:** Session-larin yaradilmasi, idare edilmesi ve baglanmasi.

**Protokollar:** NetBIOS, PPTP, RPC, SMB
**PDU:** Data

**Key concepts:**
- **Session establishment:** Authentication, authorization
- **Session maintenance:** Checkpoint/recovery, keep-alive
- **Session termination:** Graceful close

**Numune:** Bir video call zamani session layer elaqeni idare edir. Eger elaqe qirilsa, session layer son checkpoint-dan davam ede biler.

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

**Funksiyanı:** Data formatting, encryption/decryption, compression.

**Protokollar:** SSL/TLS (encryption), JPEG/GIF/PNG (image), MPEG (video), ASCII/UTF-8 (text)
**PDU:** Data

**Key concepts:**
- **Translation:** ASCII <-> EBCDIC, UTF-8 <-> UTF-16
- **Encryption:** Data-nin sifreli formata cevrilmesi (SSL/TLS)
- **Compression:** Data size-in azaldilmasi (gzip, deflate)

```
Application Data: {"name": "Orkhan"}
        |
        v  (Presentation Layer)
Encrypted: a7f3b2c1d4e5...
Compressed: [gzip encoded]
```

### Layer 7: Application Layer

**Funksiyanı:** End-user servisler, network applications.

**Protokollar:** HTTP, HTTPS, FTP, SMTP, DNS, SSH, SNMP, LDAP
**PDU:** Data

Bu layer istifadeciye en yaxin olan layer-dir. Web browser, email client, file transfer application-lar bu layer-de isleyir.

```
Browser (Application Layer)
  |
  |  GET /index.html HTTP/1.1
  |  Host: example.com
  |
  v
Web Server
```

## Əsas Konseptlər (Key Concepts)

### Data Encapsulation

Data gonderildikde her layer oz header-ini elave edir (encapsulation). Qebul edildikde her layer oz header-ini silir (de-encapsulation).

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

### Real-World Example: Web Request

```
You type https://example.com in browser:

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

### Mnemonic (Yadda saxlamaq ucun)

**Top to bottom:** All People Seem To Need Data Processing
**Bottom to top:** Please Do Not Throw Sausage Pizza Away

## PHP/Laravel ilə İstifadə

OSI modeli birbasa PHP-de implement olunmasa da, PHP muxtelif layer-lerde isleyir:

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

### Laravel Middleware - Layer analogy

```php
// Middleware is like OSI layers - each adds/removes something
class EncryptCookies extends Middleware      // Like Presentation Layer
class VerifyCsrfToken extends Middleware     // Like Session Layer
class ThrottleRequests extends Middleware    // Like Transport Layer (flow control)
class TrustProxies extends Middleware        // Like Network Layer
```

## Interview Sualları

### Q1: OSI modelinin 7 layer-ini sayın ve her birinin funksiyasini izah edin.
**A:** Physical (bit transmission), Data Link (node-to-node, MAC), Network (routing, IP), Transport (end-to-end, TCP/UDP), Session (session management), Presentation (encryption, formatting), Application (user services, HTTP/DNS).

### Q2: Encapsulation nedir?
**A:** Her layer gondermede oz header-ini data-ya elave edir. Transport layer port numreleri, Network layer IP adresleri, Data Link layer MAC adresleri elave edir. Qebul terefinde bu proses terstine basa verir (de-encapsulation).

### Q3: Switch hansı layer-de isleyir? Router nece?
**A:** Switch Layer 2-de (MAC addresses ile), Router Layer 3-de (IP addresses ile) isleyir. Layer 3 switch-ler her iki funksiyanı yerine yetire biler.

### Q4: OSI ve TCP/IP modeli arasinda ne ferq var?
**A:** OSI 7 layer-dir (theoretical), TCP/IP 4 layer-dir (practical). OSI-nin Session, Presentation, Application layer-leri TCP/IP-de tek Application layer-e uygun gelir. TCP/IP real dunyada istifade olunur, OSI isə ogretme ucun referans modeldir.

### Q5: ARP hansi layer-de isleyir?
**A:** ARP Layer 2 (Data Link) ve Layer 3 (Network) arasinda isleyir. IP adresini (L3) MAC adresine (L2) cevirir. Bu sebebden bezen "Layer 2.5" adlandirilir.

### Q6: TTL nedir ve nə ucun lazimdir?
**A:** Time to Live - paketlerin network-de sonsuz dovr etmesinin qarsisini alir. Her router (hop) TTL-i 1 azaldir. TTL 0 olduqda paket atilir ve ICMP Time Exceeded mesaji gonderilir. Traceroute bu mexanizmden istifade edir.

### Q7: Layer 2 ve Layer 3 addressing arasinda ferq nedir?
**A:** Layer 2 MAC address istifade edir (48-bit, hardware-based, local network scope). Layer 3 IP address istifade edir (32/128-bit, logical, global scope). MAC lokal segmentde, IP ise end-to-end routing ucun istifade olunur.

## Best Practices

1. **Troubleshooting zamani asagidan yuxariya gedin:** Physical -> Data Link -> Network -> Transport -> Application. Eger ping isleyirse amma HTTP islemirse, problem Layer 4-7 arasindadir.

2. **Layer separation prinsipi:** Her layer yalniz oz funksiyasina goredirr. Bu modularity troubleshooting-i asanlasdirir.

3. **Security her layer-de lazimdir:**
   - Physical: Server room access control
   - Data Link: Port security, MAC filtering
   - Network: Firewalls, ACLs
   - Transport: TLS/SSL
   - Application: Authentication, input validation

4. **MTU (Maximum Transmission Unit):** Ethernet ucun standart MTU 1500 bytes-dir. Boyuk paketler fragment olunur ki bu performance-a tesir edir. Path MTU Discovery istifade edin.

5. **Network monitoring:** Her layer-de muxtelif tool-lar istifade olunur:
   - Physical: Cable tester
   - Data Link: `arp -a`, switch port stats
   - Network: `ping`, `traceroute`
   - Transport: `netstat`, `ss`
   - Application: `curl`, `wget`, browser DevTools
