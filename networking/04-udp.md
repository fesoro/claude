# UDP (User Datagram Protocol)

## Nədir? (What is it?)

UDP Transport Layer-de isleyen connectionless protokoldur. TCP-den ferqli olaraq, connection qurmadan, acknowledgment almadan, sira qarantiyas vermeden data gonderir. Buna gore daha suretli ve daha az overhead-e malikdir.

**Esas xususiyyetler:**
- Connectionless (handshake yoxdur)
- Unreliable (catdirilma qarantiyasi yoxdur)
- No ordering (sira qarantiyasi yoxdur)
- No flow control / congestion control
- Minimal overhead (8-byte header)
- Supports multicast ve broadcast

## Necə İşləyir? (How does it work?)

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

**Fields:**
- **Source Port (16 bit):** Gonderen port
- **Destination Port (16 bit):** Hedef port
- **Length (16 bit):** Header + data-nin toplam uzunlugu (min 8 bytes)
- **Checksum (16 bit):** Integrity check (IPv4-de optional, IPv6-da mecburi)

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

UDP-de:
1. Hedef IP ve port-a data gonderilir
2. Catib-catmadigini bilmirik
3. Sira qarantiyasi yoxdur
4. Duplicate ola biler

### UDP Maximum Datagram Size

```
Theoretical max: 65,535 bytes (Length field 16-bit)
  - minus IP header (20 bytes) = 65,515 bytes
  - minus UDP header (8 bytes) = 65,507 bytes payload

Practical max: ~1472 bytes (MTU 1500 - IP 20 - UDP 8)
  - Bundan boyuk datagram-lar IP fragmentation-a ugrayir
  - Fragmentation performance-i azaldir ve packet loss risikini artirir

Common sizes:
  DNS: typically < 512 bytes (EDNS ile 4096-ya qeder)
  VoIP: ~160-320 bytes per packet
  Gaming: ~100-500 bytes per packet
```

## Əsas Konseptlər (Key Concepts)

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

**1. DNS (Domain Name System) - Port 53**
```
Why UDP?
- Sorgu kicikdir (< 512 bytes)
- Suretli cavab lazimdir
- Stateless - connection qurmaq manasizdir
- Cavab gelmese, sadece tekrar sorusuruq (application-level retry)

Not: DNS zone transfer-ler TCP istifade edir (boyuk data)
Not: DNSSEC responses boyuk ola biler -> TCP fallback
```

**2. Video/Audio Streaming**
```
Why UDP?
- Real-time data - gec gelen paket isə yaramaz
- Bezi packet loss qebul edilir (video/audio quality azca duser)
- Low latency kritikdir
- TCP retransmission delay yaradir

RTP (Real-time Transport Protocol) UDP uzerinde isleyir
Protocols: RTP, RTSP, WebRTC
```

**3. Online Gaming**
```
Why UDP?
- Player position, action data suretli catmalidir
- 50ms latency artimi gameplay-i pozur
- Kohne position data-si artiq lazim deyil (yenisi var)
- Game engine oz reliability mechanism-ini implement edir

Typical: 20-60 packets/second, her biri ~100-500 bytes
```

**4. VoIP (Voice over IP)**
```
Why UDP?
- Real-time voice - gec gelen ses paketi istifade olunmaz
- 150ms one-way delay limiti var
- Codec-ler packet loss-u kompensasiya ede biler
- Jitter buffer istifade olunur

Protocols: SIP (signaling) + RTP (media, UDP uzerinde)
```

**5. DHCP (Dynamic Host Configuration Protocol)**
```
Why UDP?
- Client-in hele IP adresi yoxdur
- Broadcast lazimdir
- Sadece 4 paket: Discover, Offer, Request, Ack
```

**6. IoT / Telemetry**
```
Why UDP?
- Sensor data - bezi data itse problem deyil
- Embedded device-larda TCP stack bahadir (memory/CPU)
- CoAP (Constrained Application Protocol) UDP uzerindedir
- MQTT-SN da UDP istifade ede biler
```

### UDP-based Reliable Protocols

Bezi application-lar UDP uzerinde oz reliability layer-lerini qurur:

```
+----------+-------------------------------------------+
| Protocol | Description                               |
+----------+-------------------------------------------+
| QUIC     | Google/IETF. HTTP/3-un transport layer-i  |
|          | UDP uzerinde TCP-like reliability + TLS    |
+----------+-------------------------------------------+
| DTLS     | TLS-in UDP versiyasi. WebRTC istifade edir|
+----------+-------------------------------------------+
| KCP      | Fast reliable UDP. Gaming ucun populyar   |
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
- Connection migration (IP change-de connection qalir)
- Built-in encryption
```

### Multicast ve Broadcast

```
Broadcast (yalniz UDP):
  Destination: 255.255.255.255 (limited broadcast)
  Destination: 192.168.1.255   (directed broadcast for /24)
  Butun network-deki device-lara gedir

Multicast (yalniz UDP):
  Destination: 224.0.0.0 - 239.255.255.255
  Yalniz subscribe olan device-lara gedir
  IGMP protocol ile group membership idare olunur

Unicast: 1-to-1
Broadcast: 1-to-all
Multicast: 1-to-many (subscribed)
```

## PHP/Laravel ilə İstifadə

### PHP UDP Socket Programming

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

### Stream-based UDP

```php
// UDP with streams (simpler API)
$socket = stream_socket_server('udp://0.0.0.0:9999', $errno, $errstr, STREAM_SERVER_BIND);

if (!$socket) {
    die("Error: $errstr ($errno)");
}

while (true) {
    $pkt = stream_socket_recvfrom($socket, 2048, 0, $peer);
    echo "Received from $peer: $pkt\n";
    stream_socket_sendto($socket, "ACK: $pkt", 0, $peer);
}
```

### Simple UDP Logger (Practical Example)

```php
// Application sends logs via UDP (fire-and-forget)
class UdpLogger
{
    private $socket;
    private string $host;
    private int $port;

    public function __construct(string $host = '127.0.0.1', int $port = 5140)
    {
        $this->host = $host;
        $this->port = $port;
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

### Laravel StatsD Integration (UDP)

```php
// StatsD metrics are sent over UDP
// Why UDP? If metrics server is down, application should not be affected

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

## Interview Sualları

### Q1: UDP nece isleyir ve TCP-den ne ferqi var?
**A:** UDP connectionless protokoldur - handshake yoxdur, acknowledgment yoxdur, ordering qarantiyasi yoxdur. 8-byte header ile minimal overhead var. TCP reliable, ordered, connection-based-dir; UDP fast, simple, best-effort-dir.

### Q2: UDP nə zaman TCP-den daha yaxsidir?
**A:** 1) Real-time apps (video/audio streaming, gaming) - latency kritikdir, bezi loss qebul edilir. 2) DNS queries - kicik, stateless, suretli cavab lazim. 3) Metrics/logging - fire-and-forget, app-i yavaslatmamalıdir. 4) Broadcast/multicast lazim olanda. 5) IoT/embedded - limited resources.

### Q3: UDP reliable ola bilermi?
**A:** UDP ozunde reliable deyil, amma uzerinde reliability qurmaq olar. QUIC (HTTP/3), DTLS, KCP, custom game protocols bunu edir. Bu application-level reliability-dir. Meselen, QUIC UDP uzerinde sequence numbers, acknowledgments, retransmission implement edir.

### Q4: UDP packet-in maximum size-i nedir?
**A:** Theoretical: 65,507 bytes (65,535 - 20 IP header - 8 UDP header). Practical: 1472 bytes (fragmentation-dan qacmaq ucun MTU 1500 - 28). DNS traditionally 512 bytes limit qoyur.

### Q5: QUIC nedir ve niye UDP istifade edir?
**A:** QUIC HTTP/3-un transport layer-idir. UDP uzerinde TCP-like reliability + TLS encryption + multiplexing verir. UDP istifade edir cunki: 1) Middlebox ossification - TCP-ni deyismek coxdur (router/firewall interference). 2) UDP-ni user-space-de implement etmek asandir. 3) 0-RTT reconnection. 4) No head-of-line blocking.

### Q6: Video streaming niye UDP istifade edir?
**A:** 1) Gec gelen frame artiq lazim deyil (yenisi gosterilir). 2) TCP retransmission stutter/buffering yaradir. 3) Player bezi frame loss-u compensate ede biler (interpolation). 4) Latency daha vacibdir quantity-den. Amma Netflix kimi buffered streaming HTTP/TCP istifade edir - yalniz live streaming UDP tercih edir.

### Q7: DNS niye UDP istifade edir amma bezen TCP-ye kecir?
**A:** Normal DNS query kicikdir ve UDP-ye uygundir (suretli, stateless). TCP-ye kecir: 1) Response 512 bytes-den boyukdur (DNSSEC, cox record). 2) Zone transfer (AXFR/IXFR). 3) DNS over TCP/TLS (DoT, port 853). 4) Truncated flag (TC) set olanda.

## Best Practices

1. **Application-level reliability elave edin:** Eger UDP istifade edirsizse ve bezi data muhimdirse, sequence numbers, ACKs, retry logic implement edin.

2. **Datagram size-i MTU-dan kicik saxlayin:** 1472 bytes-den boyuk UDP datagram gondermekden qacinin. IP fragmentation reliability-ni azaldir.

3. **Timeout ve retry:** UDP-de cavab gelmeyine hazir olun. Timeout set edin ve retry logic implement edin.

4. **Rate limiting:** UDP-de congestion control yoxdur. Oz rate limiting-inizi implement edin, eks halda network-u doldurarsiniz.

5. **Checksum istifade edin:** IPv4-de UDP checksum optional olsa da, hemise aktiv saxlayin (data integrity ucun).

6. **Fire-and-forget pattern:** Logging, metrics, telemetry kimi non-critical data ucun UDP ideal secimdir. Application performansi tesir olunmaz.

7. **Security:** UDP amplification attack-lara hessasdir (DNS amplification, NTP amplification). Server-de rate limiting ve response size limiting tetbiq edin.
