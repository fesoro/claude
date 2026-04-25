# HTTP/3 & QUIC (Senior)

## İcmal

HTTP/3 HTTP protokolunun üçüncü major versiyasıdır və **QUIC** (Quick UDP Internet Connections) nəqliyyat protokolu üzərində işləyir. QUIC Google tərəfindən yaradılıb, IETF tərəfindən RFC 9000 (QUIC) və RFC 9114 (HTTP/3) kimi standardlaşdırılıb.

HTTP/1.1 və HTTP/2 TCP üzərində işləyir. HTTP/3 isə **UDP** üzərində QUIC layer-i istifadə edir. Bu, TCP-nin head-of-line blocking problemini həll edir və daha sürətli connection setup təmin edir.

```
Stack müqayisəsi:

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

## Niyə Vacibdir

Mobil istifadəçilər, zəif şəbəkə şəraiti, yüksək packet loss — bu hallarda HTTP/3 HTTP/2-dən əhəmiyyətli dərəcədə sürətlidir. ~30% internet trafiki artıq HTTP/3 istifadə edir (Cloudflare, Google, Facebook production-da). Laravel developer-i üçün vacibdir: server konfigurasiyasını və CDN seçimini başa düşmək lazımdır.

## Əsas Anlayışlar

### Connection Establishment

```
TCP + TLS 1.2 (HTTP/1.1, HTTP/2):
  Client                          Server
    |------ SYN ------------------->|    \
    |<----- SYN-ACK ----------------|     | 1 RTT (TCP)
    |------ ACK ------------------->|    /
    |------ ClientHello ----------->|    \
    |<----- ServerHello + Cert -----|     | 2 RTT (TLS 1.2)
    |------ Key exchange ---------->|    /
    |<----- Finished ---------------|
  Total: 3 RTT — ilk byte göndərilənə qədər

QUIC (HTTP/3):
  Client                          Server
    |------ Initial + CH ---------->|    \
    |<----- Initial + SH + Cert ----|     | 1 RTT
    |------ Handshake Finished ---->|    /
    |------ Application Data ------>|
  Total: 1 RTT (0-RTT resumption ilə sıfır!)
```

### 0-RTT Resumption

```
İlk ziyarət:
  Client server-dən session ticket alır

Sonrakı ziyarət (0-RTT):
  Client                          Server
    |--- Initial + 0-RTT Data ----->|  <- Data dərhal göndərilir!
    |<-- ServerHello + Response ----|

Təhlükəsizlik: 0-RTT data replay attack-a həssasdır.
Yalnız idempotent request-lər üçün (GET). POST/PUT üçün istifadə etmə.
```

### Multiplexing — HOL Blocking-siz

```
HTTP/2 on TCP (Head-of-Line blocking):
  Stream 1: [====]
  Stream 2: [===X]   <- paket itdi
  Stream 3: [====]

  TCP byte stream-dir, itən paketi gözləməlidir.
  BÜTÜN stream-lər bloklanır.

HTTP/3 on QUIC (HOL blocking yoxdur):
  Stream 1: [====]   <- çatdırıldı
  Stream 2: [===X]   <- yalnız bu stream bloklandı
  Stream 3: [====]   <- çatdırıldı

  QUIC stream-lər transport layer-də müstəqildir.
```

### Connection Migration

```
Ssenari: İstifadəçi Wi-Fi-dan 4G-yə keçir

TCP connection:
  Source IP dəyişdi → Connection qırıldı
  TCP + TLS yenidən qurulmalıdır (3 RTT)

QUIC connection:
  Connection IP:port ilə deyil, Connection ID ilə identifikasiya olunur
  Client yeni IP-dən eyni Connection ID ilə paket göndərir
  Server tanıyır → connection davam edir
  User üçün sıfır downtime
```

### QPACK (Header Compression)

```
HTTP/2: HPACK — header compression üçün
HTTP/3: QPACK — HPACK bənzəri, amma HOL blocking yaratmır

QPACK iki stream istifadə edir:
  - Encoder stream (dynamic table-a əlavə edir)
  - Decoder stream (yenilikləri təsdiqləyir)

Request stream-lərindən ayrılır — blocking önlənir.
```

### Alt-Svc Header

```
HTTP/2 cavabı client-ə deyir: "HTTP/3 də dəstəkləyirəm"

HTTP/2 response header:
  Alt-Svc: h3=":443"; ma=86400

Növbəti request: Client HTTP/3 istifadə edir
ma=86400: 86400 saniyə (24 saat) cache
```

## Praktik Baxış

- **HTTP/3 hər zaman sürətli deyil:** Yaxşı Wi-Fi, stabil şəbəkədə fərq minimaldır. Üstünlük yüksək packet loss, yüksək latency (mobil, zəif internet) şəraitində görünür.
- **UDP bəzi firewall-larda bloklanır:** Korporativ şəbəkələrdə UDP 443 bağlı ola bilər. HTTP/2 fallback mütləq saxlanılmalıdır.
- **PHP HTTP/3 server kimi işləmir:** Bu web server (Nginx, Caddy) və CDN (Cloudflare) səviyyəsində həll olunur. Laravel server tərəfi deyil.
- **0-RTT yalnız idempotent request-lər üçün:** POST, PUT, DELETE üçün 0-RTT disable edilməlidir (replay attack riski).
- **CDN ən asan yol:** Cloudflare, Fastly avtomatik HTTP/3 dəstəkləyir — origin server-i dəyişmək lazım deyil.

### Anti-patterns

- HTTP/2 fallback-siz HTTP/3 aktiv etmək — korporativ şəbəkələrdə tam əlçatmazlıq
- 0-RTT-ni POST request-lər üçün aktiv saxlamaq — replay attack
- HTTP/3 support-u yoxlamadan "prodda aktiv edək" demək — real traffic ölçümü aparın

## Nümunələr

### Ümumi Nümunə

```
Alt-Svc mexanizmi:

1. İstifadəçi brauzer-dən example.com-a HTTP/2 request göndərir
2. Server cavabında: Alt-Svc: h3=":443"; ma=86400
3. Brauzer bu məlumatı 24 saat cache-ə alır
4. Sonrakı bütün request-lər HTTP/3 (QUIC) üzərindən gedir
5. UDP 443 bloklanıbsa → avtomatik HTTP/2-yə fallback
```

### Kod Nümunəsi

**Nginx HTTP/3 Config (Nginx 1.25+):**
```nginx
server {
    listen 443 quic reuseport;
    listen 443 ssl;
    http2 on;

    server_name example.com;

    ssl_certificate     /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;
    ssl_protocols TLSv1.3;

    # HTTP/3 dəstəyini advertise et
    add_header Alt-Svc 'h3=":443"; ma=86400' always;

    location / {
        proxy_set_header X-Protocol-QUIC $http3;
        proxy_pass http://127.0.0.1:9000;
    }
}
```

**Caddy (avtomatik HTTP/3):**
```
example.com {
    reverse_proxy localhost:8000
    # HTTP/3 v2.6-dan bəri avtomatik aktiv
}
```

**Cloudflare (konfigurasiya sıfır):**
```
Dashboard → Network → HTTP/3 (with QUIC): ON
CDN HTTP/3 termination edir, origin server dəyişməz.
```

**Laravel: Protokol Detect etmə:**
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

**Alt-Svc Header Middleware:**
```php
// app/Http/Middleware/AltSvcHeader.php
public function handle(Request $request, Closure $next)
{
    $response = $next($request);
    $response->headers->set('Alt-Svc', 'h3=":443"; ma=86400');
    return $response;
}
```

**curl ilə HTTP/3 test:**
```bash
# curl HTTP/3 dəstəyi ilə (--http3 flag, ngtcp2 ilə build olunmuş)
curl --http3 -I https://example.com

HTTP/3 200
server: nginx
content-type: text/html
alt-svc: h3=":443"; ma=86400
```

**Guzzle (HTTP/2, HTTP/3 hələ dəstəklənmir):**
```php
// Guzzle 2026-da HTTP/3 dəstəkləmir.
// Outgoing request-lər üçün HTTP/2 istifadə et.
$client = new \GuzzleHttp\Client([
    'version'  => 2.0,
    'base_uri' => 'https://api.example.com',
]);

// HTTP/3 client üçün: ext-curl nghttp3 ilə compile, ya da `curl --http3`
```

## Praktik Tapşırıqlar

1. **HTTP/3 yoxla:** `curl --http3 -I https://cloudflare.com` — cavabda `HTTP/3 200` görünürsə dəstəklənir.

2. **Nginx-də HTTP/3 aktiv et:** Nginx 1.25+ quraşdır, yuxarıdakı konfiqurasiyanı tətbiq et, `Alt-Svc` header-i göstər.

3. **Cloudflare-dən keç:** Laravel layihəsini Cloudflare arxasına qoy, `Network → HTTP/3` aktiv et, brauzer DevTools-da `Protocol: h3` görünür.

4. **Alt-Svc Middleware əlavə et:** Nginx olmadan da Laravel middleware-dən `Alt-Svc` header göndər, brauzer sonrakı request-i HTTP/3 ilə edər.

5. **Protokol logla:** `LogProtocol` middleware-i qur, `X-Protocol-QUIC` header-ini Nginx-dən Laravel-ə ötür, hansı request-lərin HTTP/3 gəldiyini logda izlə.

## Əlaqəli Mövzular

- [HTTP Protocol](05-http-protocol.md)
- [HTTPS & SSL/TLS](06-https-ssl-tls.md)
- [TCP](03-tcp.md)
- [Network Troubleshooting](30-network-troubleshooting.md)
- [mTLS Deep Dive](35-mtls-deep-dive.md)
