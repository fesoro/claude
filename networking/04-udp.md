# UDP - User Datagram Protocol (Junior)

## İcmal

UDP Transport Layer-də işləyən connectionless protokoldur. TCP-dən fərqli olaraq, connection qurmadan, acknowledgment almadan, sıra qarantiyası vermədən data göndərir. Buna görə daha sürətli və daha az overhead-ə malikdir.

Əsas xüsusiyyətlər:
- Connectionless (handshake yoxdur)
- Unreliable (çatdırılma qarantiyası yoxdur)
- No ordering (sıra qarantiyası yoxdur)
- No flow control / congestion control
- Minimal overhead (8-byte header)
- Supports multicast və broadcast

## Niyə Vacibdir

UDP-ni başa düşmək iki səbəbə görə vacibdir: birincisi, StatsD, syslog, DNS, DHCP kimi infrastruktur servisləri UDP istifadə edir — bunları konfiqurasiya edərkən niyə "fire-and-forget" seçildiyini bilmək lazımdır. İkincisi, QUIC (HTTP/3-ün transport layer-i) UDP üzərindədir; modern web performance optimizasiyasını anlamaq üçün bu bilik tələb olunur.

## Əsas Anlayışlar

### UDP Header (8 bytes)

```
 0                   1                   2                   3
 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1 2 3 4 5 6 7 8 9 0 1
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|          Source Port          |       Destination Port        |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|            Length             |           Checksum            |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+
|                          Data                                 |
+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+-+

TCP header: 20-60 bytes  vs  UDP header: 8 bytes (fix)
```

Fields:
- **Source Port (16 bit):** Göndərən port
- **Destination Port (16 bit):** Hədəf port
- **Length (16 bit):** Header + data-nın toplam uzunluğu (min 8 bytes)
- **Checksum (16 bit):** Integrity check (IPv4-də optional, IPv6-da məcburi)

### UDP Communication Flow

```
TCP (comparison):                UDP:

Client      Server              Client      Server
  |  SYN     -->  |               |              |
  |  <-- SYN+ACK  |               |  Data  -->   |
  |  ACK     -->  |               |  Data  -->   |
  |  Data    -->  |               |  Data  -->   |
  |  <-- ACK      |               |              |
  |  Data    -->  |               Done. No setup,
  |  <-- ACK      |               no teardown,
  |  FIN     -->  |               no confirmation.
  |  <-- ACK      |
  |  <-- FIN      |
  |  ACK     -->  |
```

UDP-də:
1. Hədəf IP və port-a data göndərilir
2. Çatıb-çatmadığını bilmirik
3. Sıra qarantiyası yoxdur
4. Duplicate ola bilər

### UDP Maximum Datagram Size

```
Theoretical max: 65,535 bytes (Length field 16-bit)
  - minus IP header (20 bytes) = 65,515 bytes
  - minus UDP header (8 bytes) = 65,507 bytes payload

Practical max: ~1472 bytes (MTU 1500 - IP 20 - UDP 8)
  - Bundan böyük datagram-lar IP fragmentation-a uğrayır
  - Fragmentation performance-i azaldır və packet loss riskini artırır

Common sizes:
  DNS: typically < 512 bytes (EDNS ilə 4096-ya qədər)
  VoIP: ~160-320 bytes per packet
  Gaming: ~100-500 bytes per packet
```

### UDP vs TCP

```
+---------------------+------------------+------------------+
| Feature             | TCP              | UDP              |
+---------------------+------------------+------------------+
| Connection          | Connection-based | Connectionless   |
| Reliability         | Guaranteed       | Best-effort      |
| Ordering            | Guaranteed       | Not guaranteed   |
| Header size         | 20-60 bytes      | 8 bytes          |
| Speed               | Slower           | Faster           |
| Flow control        | Yes (window)     | No               |
| Congestion control  | Yes              | No               |
| Broadcast/Multicast | No               | Yes              |
| Overhead            | High             | Low              |
| Use case            | Web, email, file | DNS, video, game |
+---------------------+------------------+------------------+
```

### UDP Use Cases

**1. DNS (Domain Name System) — Port 53**
```
Why UDP?
- Sorğu kiçikdir (< 512 bytes)
- Sürətli cavab lazımdır
- Stateless — connection qurmaq mənasızdır
- Cavab gəlməsə, sadəcə təkrar soruşuruq (application-level retry)

Not: DNS zone transfer-lər TCP istifadə edir (böyük data)
Not: DNSSEC responses böyük ola bilər -> TCP fallback
```

**2. Video/Audio Streaming**
```
Why UDP?
- Real-time data — gec gələn paket işə yaramaz
- Bəzi packet loss qəbul edilir (video/audio quality azca düşər)
- Low latency kritikdir
- TCP retransmission delay yaradır

RTP (Real-time Transport Protocol) UDP üzərində işləyir
Protocols: RTP, RTSP, WebRTC
```

**3. Online Gaming**
```
Why UDP?
- Player position, action data sürətli çatmalıdır
- 50ms latency artımı gameplay-i pozur
- Köhnə position data-sı artıq lazım deyil (yenisi var)
- Game engine öz reliability mechanism-ini implement edir

Typical: 20-60 packets/second, hər biri ~100-500 bytes
```

**4. VoIP (Voice over IP)**
```
Why UDP?
- Real-time voice — gec gələn səs paketi istifadə olunmaz
- 150ms one-way delay limiti var
- Codec-lər packet loss-u kompensasiya edə bilər
- Jitter buffer istifadə olunur

Protocols: SIP (signaling) + RTP (media, UDP üzərində)
```

**5. DHCP (Dynamic Host Configuration Protocol)**
```
Why UDP?
- Client-in hələ IP adresi yoxdur
- Broadcast lazımdır
- Sadəcə 4 paket: Discover, Offer, Request, Ack
```

**6. IoT / Telemetry**
```
Why UDP?
- Sensor data — bəzi data itsə problem deyil
- Embedded device-lərdə TCP stack bahalıdır (memory/CPU)
- CoAP (Constrained Application Protocol) UDP üzərindədir
- MQTT-SN da UDP istifadə edə bilər
```

### UDP-based Reliable Protocols

Bəzi application-lar UDP üzərində öz reliability layer-lərini qurur:

```
+----------+-------------------------------------------+
| Protocol | Description                               |
+----------+-------------------------------------------+
| QUIC     | Google/IETF. HTTP/3-ün transport layer-i  |
|          | UDP üzərində TCP-like reliability + TLS    |
+----------+-------------------------------------------+
| DTLS     | TLS-in UDP versiyası. WebRTC istifadə edir|
+----------+-------------------------------------------+
| KCP      | Fast reliable UDP. Gaming üçün populyar   |
+----------+-------------------------------------------+
| RUDP     | Reliable UDP. Custom implementations      |
+----------+-------------------------------------------+
| UDT      | UDP-based Data Transfer. Big data transfer|
+----------+-------------------------------------------+
```

### QUIC Protocol (HTTP/3)

```
Traditional stack:          QUIC stack:
+------------------+       +------------------+
| HTTP/2           |       | HTTP/3           |
+------------------+       +------------------+
| TLS 1.3          |       | QUIC             |
+------------------+       | (reliability +   |
| TCP              |       |  encryption +    |
+------------------+       |  multiplexing)   |
| IP               |       +------------------+
+------------------+       | UDP              |
                           +------------------+
                           | IP               |
                           +------------------+

QUIC advantages over TCP:
- 0-RTT connection establishment (reconnection)
- 1-RTT for new connections (vs TCP+TLS = 3 RTT)
- No head-of-line blocking (independent streams)
- Connection migration (IP change-də connection qalır)
- Built-in encryption
```

### Multicast və Broadcast

```
Broadcast (yalnız UDP):
  Destination: 255.255.255.255 (limited broadcast)
  Destination: 192.168.1.255   (directed broadcast for /24)
  Bütün network-dəki device-lara gedir

Multicast (yalnız UDP):
  Destination: 224.0.0.0 - 239.255.255.255
  Yalnız subscribe olan device-lara gedir
  IGMP protocol ilə group membership idarə olunur

Unicast: 1-to-1
Broadcast: 1-to-all
Multicast: 1-to-many (subscribed)
```

## Praktik Baxış

**Real layihələrdə istifadəsi:**
- StatsD metrics collector UDP port 8125-dən qəbul edir — application metrics göndərməsi hətta StatsD down olsa belə app-i blok etmir
- Syslog UDP port 514-dən log qəbul edir
- DNS resolver-i Laravel/PHP-dən hər sorğuda UDP-dən istifadə edir (`gethostbyname()`)

**Trade-off-lar:**
- UDP: sürət > etibarlılıq; TCP: etibarlılıq > sürət
- UDP-də congestion control yoxdur — network-ü doldurmaq mümkündür; öz rate limiting-inizi implement edin
- Fragmentation UDP-ni TCP-dən daha az reliable edir — datagram-ı MTU-dan kiçik saxlayın

**Ne zaman istifadə olunmamalı:**
- Maliyyə əməliyyatları, kritik data transfer — hər paketi qəbul etdiyiniz təsdiqlənməlidir
- Application-level retry tətbiq edə bilmədiyiniz hallarda
- Böyük data transferi (video file download) — TCP daha effektivdir

**Common mistakes:**
- UDP-dən istifadə edib packet loss-u ignore etmək — metrics göndərəndə `@socket_sendto` ilə xəta supression düzgündür; amma business-critical data üçün bu anti-pattern-dir
- Datagram-ı 1472 bytes-dan böyük göndərmək — fragmentation baş verir, bir fragment itsə datagram tamamilə itirilir

## Nümunələr

### Ümumi Nümunə

StatsD pattern: Laravel ərizəsi hər HTTP request-in response time-ını `myapp.http.response_time:45.2|ms` formatında UDP ilə 127.0.0.1:8125-ə göndərir. StatsD server aggregate edir, Graphite/Prometheus-a yönləndirir. Əgər StatsD down olsa, `@socket_sendto` xətanı suppress edir — app işləməyə davam edir.

### Kod Nümunəsi

UDP Server və Client:

```php
// UDP Server
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
socket_bind($socket, '0.0.0.0', 9999);

echo "UDP Server listening on port 9999\n";

while (true) {
    $from = '';
    $port = 0;
    // Blocking call - waits for data
    socket_recvfrom($socket, $buf, 2048, 0, $from, $port);
    echo "Received from $from:$port: $buf\n";
    
    // Send response back
    $response = "ACK: $buf";
    socket_sendto($socket, $response, strlen($response), 0, $from, $port);
}

socket_close($socket);
```

```php
// UDP Client
$socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

$message = "Hello UDP Server!";
socket_sendto($socket, $message, strlen($message), 0, '127.0.0.1', 9999);

// Receive response (with timeout)
socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, [
    'sec' => 2,
    'usec' => 0,
]);

$from = '';
$port = 0;
$result = @socket_recvfrom($socket, $buf, 2048, 0, $from, $port);

if ($result === false) {
    echo "No response (timeout) - typical for UDP!\n";
} else {
    echo "Response: $buf\n";
}

socket_close($socket);
```

Simple UDP Logger (Fire-and-forget pattern):

```php
class UdpLogger
{
    private $socket;

    public function __construct(
        private string $host = '127.0.0.1',
        private int $port = 5140
    ) {
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $payload = json_encode([
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'timestamp' => microtime(true),
            'hostname' => gethostname(),
        ]);

        // Fire and forget - no waiting for response
        // If the log server is down, we don't care (app continues)
        @socket_sendto(
            $this->socket,
            $payload,
            strlen($payload),
            0,
            $this->host,
            $this->port
        );
    }

    public function __destruct()
    {
        if ($this->socket) {
            socket_close($this->socket);
        }
    }
}

// Usage
$logger = new UdpLogger('logserver.local', 5140);
$logger->log('info', 'User logged in', ['user_id' => 42]);
$logger->log('error', 'Payment failed', ['order_id' => 123]);
```

Laravel StatsD Integration (UDP):

```php
class StatsD
{
    private $socket;

    public function __construct(
        private string $host = '127.0.0.1',
        private int $port = 8125,
        private string $prefix = 'myapp'
    ) {
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    }

    public function increment(string $metric, int $value = 1): void
    {
        $this->send("$this->prefix.$metric:$value|c");
    }

    public function timing(string $metric, float $ms): void
    {
        $this->send("$this->prefix.$metric:{$ms}|ms");
    }

    public function gauge(string $metric, float $value): void
    {
        $this->send("$this->prefix.$metric:{$value}|g");
    }

    private function send(string $data): void
    {
        @socket_sendto($this->socket, $data, strlen($data), 0, $this->host, $this->port);
    }
}

// In Laravel middleware
class TrackResponseTime
{
    public function handle($request, Closure $next)
    {
        $start = microtime(true);
        $response = $next($request);
        $duration = (microtime(true) - $start) * 1000;

        app(StatsD::class)->timing('http.response_time', $duration);
        app(StatsD::class)->increment('http.requests');

        return $response;
    }
}
```

## Praktik Tapşırıqlar

**Tapşırıq 1: UDP packet loss müşahidəsi**

```bash
# UDP traffic-i monitor edin
sudo tcpdump -i lo -n udp port 9999

# Başqa terminaldən UDP göndərin
php -r "
\$s = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
for (\$i = 0; \$i < 100; \$i++) {
    socket_sendto(\$s, 'packet-'.\$i, 8+strlen(\$i), 0, '127.0.0.1', 9999);
}
echo 'Sent 100 packets\n';
"
```

**Tapşırıq 2: Metrics middleware implement edin**

Laravel-də bütün API request-lərin response time-ını StatsD-yə göndərən middleware yazın. Test edin: StatsD server olmasa belə application işləməyə davam etməlidir.

**Tapşırıq 3: DNS query analizi**

```bash
# DNS sorğusunun UDP üzərindən getdiyini görün
sudo tcpdump -i any -n port 53

# Başqa terminaldən
php -r "echo gethostbyname('google.com');"

# DNS response-u nə vaxt TCP-yə keçir?
# DNSSEC-li domain istifadə edin:
dig +dnssec google.com
```

## Əlaqəli Mövzular

- [TCP](03-tcp.md)
- [DNS](07-dns.md)
- [HTTP/3 & QUIC](31-http3-quic.md)
- [WebRTC](32-webrtc.md)
- [Network Troubleshooting](30-network-troubleshooting.md)
