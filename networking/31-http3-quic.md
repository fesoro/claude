# HTTP/3 və QUIC Protocol

## Nədir? (What is it?)

HTTP/3 HTTP protokolunun üçüncü major versiyasıdır və **QUIC** (Quick UDP Internet Connections) nəqliyyat protokolu üzərində işləyir. QUIC Google tərəfindən yaradılıb və IETF tərəfindən RFC 9000 (QUIC) və RFC 9114 (HTTP/3) kimi standardlaşdırılıb.

HTTP/1.1 və HTTP/2 TCP üzərində işləyir. HTTP/3 isə **UDP** üzərində QUIC layer-i istifadə edir. Bu, TCP-nin head-of-line blocking problemini həll edir və daha sürətli connection setup təmin edir.

```
Stack comparison:

HTTP/1.1, HTTP/2:        HTTP/3:
+----------------+       +----------------+
|    HTTP        |       |    HTTP/3      |
+----------------+       +----------------+
|    TLS 1.2/1.3 |       |    QUIC        |  <- TLS 1.3 integrated
+----------------+       +----------------+
|    TCP         |       |    UDP         |
+----------------+       +----------------+
|    IP          |       |    IP          |
+----------------+       +----------------+
```

## Necə İşləyir? (How does it work?)

### 1. Connection Establishment

```
TCP + TLS 1.2 (HTTP/1.1, HTTP/2):
  Client                          Server
    |------ SYN ------------------->|    \
    |<----- SYN-ACK ----------------|     | 1 RTT (TCP)
    |------ ACK ------------------->|    /
    |------ ClientHello ----------->|    \
    |<----- ServerHello + Cert -----|     |
    |------ Key exchange ---------->|     | 2 RTT (TLS 1.2)
    |<----- Finished ---------------|    /
  Total: 3 RTT before first byte sent

QUIC (HTTP/3):
  Client                          Server
    |------ Initial + CH ---------->|    \
    |<----- Initial + SH + Cert ----|     | 1 RTT
    |------ Handshake Finished ---->|    /
    |------ Application Data ------>|
  Total: 1 RTT (or 0-RTT with resumption!)
```

### 2. 0-RTT Resumption

```
First visit:
  Client gets session ticket from server

Subsequent visit (0-RTT):
  Client                          Server
    |--- Initial + 0-RTT Data ----->|  <- Data sent immediately!
    |<-- ServerHello + Response ----|
  Total: 0 RTT for data transmission

Security note: 0-RTT data can be replayed, so use only for idempotent requests (GET).
```

### 3. Multiplexing Without HOL Blocking

```
HTTP/2 on TCP (Head-of-Line blocking):
  Stream 1: [====]
  Stream 2: [===X]   <- packet lost
  Stream 3: [====]

  TCP sees byte stream, must wait for lost packet
  ALL streams blocked until retransmission

HTTP/3 on QUIC (No HOL blocking):
  Stream 1: [====]   <- delivered
  Stream 2: [===X]   <- only this stream blocked
  Stream 3: [====]   <- delivered

  QUIC streams are independent at transport layer
```

### 4. Connection Migration

```
Scenario: User on Wi-Fi walks outside, switches to 4G

TCP connection:
  Source IP changes -> Connection broken
  Must re-establish TCP + TLS (3 RTT)

QUIC connection:
  Connection identified by Connection ID (not IP:port tuple)
  Client sends packet from new IP with same Connection ID
  Server recognizes -> connection continues seamlessly
  Zero downtime for user
```

## Əsas Konseptlər (Key Concepts)

### QUIC Features

```
1. UDP-based         - Bypass TCP limitations
2. Built-in TLS 1.3  - Encryption mandatory
3. Stream multiplexing - Independent streams
4. 0-RTT / 1-RTT     - Fast handshake
5. Connection migration - Survive IP changes
6. Forward error correction (optional)
7. Improved congestion control
```

### HTTP/3 Frame Types

```
DATA        - Request/response body
HEADERS     - HTTP headers (QPACK compressed)
SETTINGS    - Connection parameters
GOAWAY      - Graceful shutdown
MAX_PUSH_ID - Server push limit
CANCEL_PUSH - Cancel server push
```

### QPACK vs HPACK

```
HTTP/2 uses HPACK for header compression.
HTTP/3 uses QPACK - similar but avoids HOL blocking.

QPACK uses two streams:
  - Encoder stream (adds to dynamic table)
  - Decoder stream (acknowledges updates)

Decoupled from request streams to prevent blocking.
```

### Service Discovery: Alt-Svc Header

```
HTTP/2 response tells client: "I support HTTP/3 too"

HTTP/2 response header:
  Alt-Svc: h3=":443"; ma=86400

Next request: Client uses HTTP/3 directly
ma=86400: cache for 86400 seconds (24 hours)
```

### DNS HTTPS Record (RFC 9460)

```
Newer approach: DNS tells client about HTTP/3 before first request

example.com. IN HTTPS 1 . alpn="h3,h2" port=443

Client resolves DNS, sees h3 support, connects directly via QUIC.
```

## PHP/Laravel ilə İstifadə

PHP özü HTTP/3 server kimi işləmir - bu web server (Nginx, Caddy, LiteSpeed) və CDN (Cloudflare) səviyyəsində həll olunur.

### Nginx HTTP/3 Config (Nginx 1.25+)

```nginx
server {
    listen 443 quic reuseport;
    listen 443 ssl;
    http2 on;

    server_name example.com;

    ssl_certificate     /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;
    ssl_protocols TLSv1.3;

    # Advertise HTTP/3 support
    add_header Alt-Svc 'h3=":443"; ma=86400' always;

    location / {
        proxy_pass http://127.0.0.1:9000;
    }
}
```

### Caddy (automatic HTTP/3)

```
example.com {
    reverse_proxy localhost:8000
    # HTTP/3 enabled automatically since v2.6
}
```

### Cloudflare (zero config)

Dashboard -> Network -> HTTP/3 (with QUIC): ON. CDN handles HTTP/3 termination.

### Laravel Side: Detect Protocol

```php
// app/Http/Middleware/LogProtocol.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogProtocol
{
    public function handle(Request $request, Closure $next)
    {
        // Check Alt-Svc usage via custom header set by Nginx
        $protocol = $request->server('SERVER_PROTOCOL', 'unknown');
        $viaQuic  = $request->header('X-Protocol-QUIC') === '1';

        Log::info('Request protocol', [
            'protocol' => $protocol,
            'quic'     => $viaQuic,
            'path'     => $request->path(),
        ]);

        return $next($request);
    }
}
```

### Nginx variable to detect HTTP/3

```nginx
location / {
    proxy_set_header X-Protocol-QUIC $http3;
    proxy_pass http://127.0.0.1:9000;
}
```

### Testing HTTP/3 with curl

```bash
# curl with HTTP/3 support (requires --http3 flag, built with ngtcp2)
curl --http3 -I https://example.com

HTTP/3 200
server: nginx
content-type: text/html
alt-svc: h3=":443"; ma=86400
```

### Guzzle Client (HTTP/2 only today)

```php
// Guzzle does not yet support HTTP/3 natively (as of 2026).
// For outgoing requests, use HTTP/2 until support lands.
$client = new \GuzzleHttp\Client([
    'version' => 2.0, // HTTP/2
    'base_uri' => 'https://api.example.com',
]);

// For HTTP/3 client: use ext-curl compiled with nghttp3, or shell out to curl --http3
```

### Advertising HTTP/3 from Laravel directly

```php
// app/Http/Middleware/AltSvcHeader.php
public function handle(Request $request, Closure $next)
{
    $response = $next($request);
    $response->headers->set('Alt-Svc', 'h3=":443"; ma=86400');
    return $response;
}
```

## Interview Sualları (Q&A)

### 1. HTTP/3 niyə UDP istifadə edir TCP yerinə?

**Cavab:** TCP operating system kernel-də implementasiya olunur və dəyişdirilməsi illər tələb edir. UDP isə sadə və userspace-də control olunur. QUIC userspace-də işləyərək daha sürətli innovation imkan verir. Həmçinin TCP head-of-line blocking problem-ini həll edir: bir paket itəndə bütün streams bloklanır. QUIC-də streams transport səviyyəsində müstəqildir.

### 2. 0-RTT nədir və niyə təhlükəlidir?

**Cavab:** 0-RTT (Zero Round-Trip Time) client əvvəlki session-dan istifadə edərək handshake olmadan data göndərməyə imkan verir. Problem: bu data replay attack-a həssasdır - attacker həmin paketi sonra yenidən göndərə bilər. Buna görə 0-RTT yalnız **idempotent** request-lər (GET) üçün istifadə olunmalıdır, POST/PUT üçün deyil.

### 3. HTTP/2 və HTTP/3 arasında əsas fərq nədir?

**Cavab:**
- **Transport:** HTTP/2 TCP, HTTP/3 QUIC (UDP)
- **HOL blocking:** HTTP/2 TCP səviyyəsində var, HTTP/3-də yoxdur
- **Handshake:** HTTP/2 3 RTT (TCP+TLS), HTTP/3 1 RTT (0-RTT mümkündür)
- **Connection migration:** HTTP/3 dəstəkləyir, HTTP/2 yox
- **Encryption:** HTTP/2 optional (h2c exists), HTTP/3 mandatory (TLS 1.3)
- **Header compression:** HPACK vs QPACK

### 4. Connection migration nədir?

**Cavab:** Client IP dəyişsə belə (Wi-Fi-dan 4G-yə keçid), connection qırılmır. QUIC connection IP:port ilə deyil, **Connection ID** ilə identifikasiya olunur. Client yeni IP-dən eyni Connection ID ilə paket göndərir, server həmin session-a davam edir. Mobil istifadəçilər üçün çox faydalıdır.

### 5. HTTP/3 production-da hazırdırmı?

**Cavab:** Bəli. 2026 itibarilə:
- Chrome, Firefox, Safari, Edge dəstəkləyir
- Cloudflare, Fastly, Google, Facebook production-da
- Nginx 1.25+, Caddy 2.6+, LiteSpeed dəstəkləyir
- Trafik: ~30% of internet HTTP/3 istifadə edir
Amma bəzi corporate firewall-lar UDP port 443-ü bloklayır, fallback HTTP/2-yə ehtiyac var.

### 6. QUIC niyə TLS 1.3-ü məcburi edir?

**Cavab:** QUIC-in dizaynında security by default prinsipi var. Plain-text QUIC yoxdur. TLS 1.3 QUIC-ə **integrated** edilib - ayrı handshake deyil, QUIC paketlərinin bir hissəsidir. Bu həm performance (az RTT) həm də security (metadata şifrəli) verir. TLS 1.2 dəstəklənmir çünki 0-RTT və handshake optimizasiyaları yalnız TLS 1.3-də mümkündür.

### 7. Alt-Svc header nədir?

**Cavab:** `Alt-Svc: h3=":443"; ma=86400` - server client-ə "mən HTTP/3-də də əlçatanam" deyir. İlk request HTTP/2 ilə gəlir, server bu header-lə HTTP/3-ü advertise edir. Növbəti request-lər HTTP/3 ilə göndərilir. `ma` (max-age) - neçə saniyə cache edilməlidir.

### 8. HTTP/3 hər zaman HTTP/2-dən sürətlidirmi?

**Cavab:** Yox. HTTP/3 **high packet loss** və ya **high latency** şəraitində daha yaxşıdır (mobil, zəif internet). Yaxşı Wi-Fi və stabil şəbəkədə fərq minimal ola bilər. UDP bəzi şəbəkələrdə rate-limited olduğu üçün TCP bəzən daha stabil olur. Real ölçmə lazımdır.

## Best Practices

1. **HTTP/2 fallback saxla** - bəzi firewall-lar UDP 443-ü bloklayır, client HTTP/2-yə geri dönsün.
2. **TLS 1.3 certificate hazırla** - HTTP/3 TLS 1.3 tələb edir, köhnə cipher suite-ləri disable et.
3. **Alt-Svc header advertise et** - browser HTTP/3-ə avtomatik keçsin.
4. **0-RTT yalnız idempotent request-lər üçün** - replay attack-dan qoruyun, POST üçün 0-RTT disable et.
5. **Connection ID rotation istifadə et** - privacy üçün müxtəlif network-lərdə fərqli ID-lər.
6. **UDP 443 firewall-da aç** - server-side HTTP/3 traffic üçün lazımdır.
7. **CDN səviyyəsində aktivləşdir** - Cloudflare, Fastly avtomatik HTTP/3 təklif edir, origin server-ə yük salmır.
8. **Monitoring əlavə et** - `$http3` variable Nginx-də, hansı request hansı protokolla gəlir izlə.
9. **QPACK settings tune et** - yüksək throughput üçün dynamic table size artır (`http3_max_field_size`).
10. **PMTU discovery-yə diqqət** - QUIC-də default 1200 bytes, şəbəkən dəstəkləyirsə artır.
