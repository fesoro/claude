# HTTP/1.1 vs HTTP/2 vs HTTP/3 (Middle ⭐⭐)

## İcmal
HTTP protokolunun versiyaları arasındakı fərqlər backend developer-in bilməli olduğu fundamental bilikdir. HTTP/1.1-dən HTTP/2-yə, sonra HTTP/3-ə keçid mərhələ-mərhələ baş vermiş real performans problemlərini həll etmişdir. Interview-larda bu mövzu xüsusilə performance optimization, API dizayn, CDN sualları ilə birlikdə gəlir.

## Niyə Vacibdir
Interviewer bu sualı soruşduqda əslində sizin "why" sualını cavablandıra bilib-bilmədiyinizi yoxlayır. HTTP/2-nin niyə yaradıldığını, hansı problemi həll etdiyini, HTTP/3-ün niyə UDP üzərindən qurulduğunu bilmək sizi average developer-dən fərqləndirir. gRPC HTTP/2 üzərindən işləyir — bunu bilmədən gRPC-ni izah etmək mümkün deyil.

## Əsas Anlayışlar

- **HTTP/1.1 — Persistent Connection:** `Connection: Keep-Alive` header ilə eyni TCP connection-u bir neçə request üçün istifadə. HTTP/1.0-da hər request üçün yeni TCP connection + TLS handshake olurdu
- **HTTP/1.1 — Head-of-Line Blocking:** Eyni connection üzərindən yalnız bir request eyni anda işlənə bilər. Əvvəlki request cavabını gözləyənədək növbəti blok olunur. Böyük faylın yüklənməsi kiçik API sorğusunu gözlədə bilər
- **HTTP/1.1 — Workarounds:** Browser-lər per-domain 6 paralel TCP connection aça bilir. Domain sharding (əlavə subdomain-lər) bu limiti keçmək üçün hack-dir. Asset bundling (JS/CSS birləşdirmə) request sayını azaldır
- **HTTP/1.1 — Pipelining:** Texniki olaraq vardı — cavab gözləmədən request-lər ardıcıl göndərmək. Lakin server-lər strict sıra tələb etdiyi üçün baş blocking aradan qalxmırdı, real dəstək yox idi
- **HTTP/2 — Multiplexing:** Eyni TCP connection üzərindən paralel stream-lər. 30 request eyni anda eyni TCP connection-da göndərilir. Application layer-dəki head-of-line blocking aradan qalxır
- **HTTP/2 — HPACK Compression:** HTTP/1.1-də hər request-lə eyni header-lar (User-Agent, Cookie, Authorization) tam göndərilir. HPACK static table + dynamic table ilə header-ları sıxışdırır, daha önce göndərilmiş header-ları yenidən göndərmir. Bandwidth qənaəti əhəmiyyətlidir
- **HTTP/2 — Binary Framing:** HTTP/1.1 text-based idi (human-readable, amma parse etmək ağırdır). HTTP/2 binary frame-lər istifadə edir — daha effektiv parse, daha az overhead
- **HTTP/2 — Server Push:** Server client-in sorğusu olmadan resurs göndərə bilər. HTML sorğusu gəldikdə CSS/JS-i də push et. Praktikada istifadəsi az oldu — browser-lərin cache koordinasiyası çətin, HTTP/3-də deprecated
- **HTTP/2 — Stream Priority:** Client stream-lərə priority weight təyin edə bilər. Critical CSS-ı images-dən əvvəl yüklə
- **HTTP/2 — TCP Head-of-Line Blocking (hələ var):** HTTP/2 application layer-dəki blocking-i həll etdi. Lakin TCP packet itkisində bütün stream-lər blok olunur — bu TCP-nin fundamental problemidir, HTTP/2 çözmür
- **HTTP/3 — QUIC Protokol:** UDP üzərindən qurulub. Hər stream müstəqildir — bir stream-in packet itkisi digərlərini bloklamır. TCP head-of-line blocking tamamilə aradan qalxdı
- **HTTP/3 — 0-RTT Connection:** QUIC əvvəlki connection məlumatını (session ticket) cache edib reconnect zamanı handshake olmadan data göndərə bilər. Mobil cihazlarda WiFi→4G keçidlərində critical
- **HTTP/3 — TLS 1.3 Built-in:** QUIC-in özündə TLS 1.3 inteqrasiyası var — ayrı TLS handshake gecikmə əlavə etmir. HTTP/2 üçün TLS 1-2 əlavə RTT idi
- **HTTP/3 — Connection Migration:** IP dəyişsə belə (WiFi → mobile data) connection qorunur. QUIC Connection ID əsasında — IP-dən asılı deyil. TCP-də IP dəyişdikdə connection kəsilir
- **HTTPS requirement for HTTP/2:** Texniki olaraq HTTP/2 cleartext (h2c) mümkündür, lakin bütün əsas browser-lər yalnız TLS üzərindən HTTP/2 (h2) dəstəkləyir
- **ALPN (Application-Layer Protocol Negotiation):** TLS handshake zamanı client hansı protokol istədiyini bildirir (`h2` ya da `http/1.1`). Server dəstəkləyirsə razılaşır

## Praktik Baxış

**Interview-da yanaşma:**
"HTTP/2-nin faydaları nədir?" sualına "daha sürətlidir" demək kifayət etmir. Problem → həll formatında:
1. HTTP/1.1-in head-of-line blocking → HTTP/2-nin multiplexing
2. Header redundancy → HPACK compression
3. TCP packet-loss blocking → HTTP/3 QUIC
4. TCP connection overhead → QUIC 0-RTT

**Follow-up suallar:**
- "HTTP/2 server push niyə populyar olmadı?" — Browser-lər cache-də olan resursu push edilsə redundant transfer, push promise timing problemi
- "gRPC niyə HTTP/2 tələb edir?" — Bidirectional streaming üçün multiplexing + persistent connection lazımdır
- "HTTP/3-ü nə zaman istifadə etməmək lazımdır?" — Firewall-ların UDP-ni bloklaması, UDP-ni dəstəkləməyən köhnə infrastructure
- "HTTPS HTTP/2 üçün məcburidirmi?" — Texniki yox, lakin browser-lər yalnız TLS üzərindən h2 dəstəkləyir
- "HTTP/2 əskiklikləri nələrdir?" — TCP HoL blocking, server push-un effektiv istifadəsinin çətinliyi, debug çətinliyi (binary)

**Ümumi səhvlər:**
- HTTP/2-nin TCP head-of-line blocking-i tam həll etdiyini söyləmək — application layer-dəki həll edildi, TCP-dəki qaldı
- HTTP/3-ün "həmişə daha sürətli" olduğunu söyləmək — yüksək packet-loss mühitdə (mobil, yavaş şəbəkə) daha effektiv
- Server push-u həmişə faydalı hesab etmək
- HPACK-ın yalnız compression olmadığını bilməmək — dynamic table-in attack (CRIME, BREACH) riski var

**Yaxşı cavabı əla cavabdan fərqləndirən:**
- QUIC-in 0-RTT connection resumption mexanizmini bilmək
- HPACK-ın static/dynamic table mexanizmini izah etmək
- "HTTP/2 multiplexing TCP-dəki blocking-i aradan qaldırmır" — dəqiq texniki fərq
- gRPC + HTTP/2 bağlantısını izah etmək

## Nümunələr

### Tipik Interview Sualı
"We're experiencing slow page loads despite having fast backend APIs. Our app makes 30+ HTTP requests on load. What would you investigate, and how would HTTP/2 help?"

### Güclü Cavab
Bu HTTP/1.1-in head-of-line blocking probleminin klassik nümunəsidir.

**Investigation:**
1. Chrome DevTools → Network tab → waterfall chart: request-lərin sequential mi, parallel mi başladığını görün
2. Protocol sütununu yoxla: `http/1.1` yazırsa problem aydındır
3. `Timing` tab-dan `Stalled` vaxtı bax — connection limit-i dolduğundan gözləyən request-lər

**HTTP/2-nin köməyi:**
- 30+ request eyni TCP connection üzərindən parallel göndərilir — multiplexing
- HPACK: hər request-dəki Cookie/Authorization header-ları yenidən göndərilmir — bandwidth qənaəti
- Domain sharding hack-ına ehtiyac yoxdur

**Limitlər:**
Yüksək packet-loss şəbəkədə (mobil) HTTP/2 hələ TCP HoL blocking-dən əziyyət çəkir → HTTP/3 daha effektiv olur. Backend cavabları yavaşdırsa multiplexing kömək etmir.

**Practik addımlar:**
1. Nginx-dən `listen 443 ssl http2` aktiv et
2. CDN-in HTTP/2 dəstəyini yoxla
3. Chrome DevTools-da `Protocol` sütununun `h2` göstərdiyini verify et

### Kod Nümunəsi
```nginx
# Nginx HTTP/2 konfiqurasiyası
server {
    listen 443 ssl http2;
    server_name example.com;

    ssl_certificate     /etc/ssl/certs/example.crt;
    ssl_certificate_key /etc/ssl/private/example.key;

    # TLS 1.2 minimum (HTTP/2 üçün TLS 1.2+ tələb olunur)
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;

    # HSTS — browser-i daima HTTPS-ə yönləndir
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    location / {
        proxy_pass         http://backend;
        proxy_http_version 1.1;          # Upstream üçün HTTP/1.1 (çox app server HTTP/2 dəstəkləmir)
        proxy_set_header   Connection ""; # Keep-Alive upstream üçün
    }

    # Static assets — immutable cache + HTTP/2 push ipucu
    location /assets/ {
        add_header Cache-Control "public, max-age=31536000, immutable";
        # HTTP/2 server push (deprecated olub, alternativ: preload link header)
        # add_header Link "</assets/app.css>; rel=preload; as=style";
    }
}

# HTTP/1.1-i HTTP/2-yə redirect et
server {
    listen 80;
    server_name example.com;
    return 301 https://$host$request_uri;
}
```

```bash
# HTTP versiyasını yoxlamaq
curl -v --http2 https://example.com 2>&1 | grep -i "using http"
# < Using HTTP2, server supports multiplexing

# HTTP/1.1 məcbur et
curl -v --http1.1 https://example.com

# HTTP/3 test (curl 7.66+)
curl -v --http3 https://cloudflare.com

# nghttp2 ilə HTTP/2 debug
nghttp -v https://example.com
# stream ID-lər, header compression, frame-lər görünür

# OpenSSL ilə ALPN yoxla
openssl s_client -connect example.com:443 -alpn h2 < /dev/null 2>&1 | grep ALPN
# ALPN protocol: h2   ← HTTP/2 razılaşdırıldı
# ALPN protocol: http/1.1  ← HTTP/2 dəstəklənmir
```

```python
# httpx ilə HTTP/2 benchmark
import httpx
import asyncio
import time

async def benchmark_http_versions():
    urls = [f"https://api.example.com/items/{i}" for i in range(50)]

    # HTTP/1.1 — max 6 parallel connection (browser default)
    start = time.perf_counter()
    async with httpx.AsyncClient(http2=False) as client:
        responses = await asyncio.gather(*[client.get(url) for url in urls])
    http1_time = time.perf_counter() - start
    print(f"HTTP/1.1: {http1_time:.2f}s ({len(responses)} requests)")

    # HTTP/2 — 50 request, eyni connection üzərindən parallel
    start = time.perf_counter()
    async with httpx.AsyncClient(http2=True) as client:
        responses = await asyncio.gather(*[client.get(url) for url in urls])
    http2_time = time.perf_counter() - start
    print(f"HTTP/2:   {http2_time:.2f}s ({len(responses)} requests)")

    print(f"Speedup: {http1_time/http2_time:.1f}x")
    # Nümunə nəticə (high-latency network):
    # HTTP/1.1: 8.5s
    # HTTP/2:   1.2s
    # Speedup: 7.1x

asyncio.run(benchmark_http_versions())
```

```
HTTP versiyalarının müqayisəsi:

HTTP/1.1:
Connection 1: [Req1 → Resp1][Req2 → Resp2][Req3 → Resp3]
Connection 2: [Req4 → Resp4][Req5 → Resp5]
Connection 3: [Req6 → Resp6]
...max 6 connection per domain
30 request için ≈ 5 round, sequential

HTTP/2 — Multiplexing:
Single TCP Connection:
  Frame: [Stream1:H][Stream2:H][Stream1:D][Stream3:H][Stream2:D]
  (H=headers, D=data — interleaved frames)
30 request eyni anda, 1 connection — parallel

HTTP/3 — QUIC/UDP:
UDP datagram-ları — hər stream müstəqil:
  [Stream1][Stream2][Stream3]...
  Stream2 packet itərsə → yalnız Stream2 gözləyir
  Stream1 və Stream3 normal davam edir
```

```
HPACK Header Compression nümunəsi:

Request 1 (full headers):
:method: GET
:path: /api/users
:scheme: https
:authority: api.example.com
user-agent: Mozilla/5.0 (X11; Linux x86_64)...
accept: application/json
authorization: Bearer eyJhbGciOiJIUzI1NiJ9...
cookie: session=abc123; _ga=GA1...

Request 2 (HPACK — dəyişən field-lər göndərilir):
:path: /api/orders        ← dəyişdi
                          ← user-agent, accept, authorization, cookie
                          ← eyni olduğu üçün göndərilmir — index reference
Bandwidth qənaəti: 400 byte → 20 byte
```

```php
// Laravel-də HTTP versiyasını yoxlamaq və HTTP/2 push header
class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        // Müştərinin HTTP versiyasını log et
        $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';
        // "HTTP/2.0" ya da "HTTP/1.1"

        $response = response()->view('home');

        // HTTP/2 preload hint (server push əvəzinə daha etibarlı)
        $response->headers->set('Link',
            '</css/app.css>; rel=preload; as=style, ' .
            '</js/app.js>; rel=preload; as=script'
        );

        return $response;
    }
}

// HTTP/2 dəstəyini detect etmək
function isHttp2(): bool
{
    return isset($_SERVER['HTTP2']) ||
           ($_SERVER['SERVER_PROTOCOL'] ?? '') === 'HTTP/2.0';
}
```

### İkinci Nümunə — gRPC + HTTP/2

```
gRPC HTTP/2 tələb edir — niyə?

gRPC Communication Patterns:
1. Unary RPC:
   Client:  GetUser(req) →
   Server:              ← User{id:1, name:"Ali"}

2. Server Streaming:
   Client:  ListUsers(filter) →
   Server:                   ← User{1}, User{2}, ... User{N}
   (HTTP/2 stream — hamısı eyni connection-da)

3. Client Streaming:
   Client:  UploadChunk(chunk1), UploadChunk(chunk2) →
   Server:                                           ← UploadComplete

4. Bidirectional Streaming:
   Client: Message →              ← ServerMessage
           Message →   ← ServerMessage ← ServerMessage
   (WebSocket kimi amma HTTP/2 üzərindən, strongly typed)

HTTP/2 olmadan bu mümkün olmazdı:
- Persistent connection lazımdır (server push edə bilsin)
- Multiplexing lazımdır (paralel stream-lər)
- Binary framing — protobuf ilə uyğun
```

## Praktik Tapşırıqlar

- `curl -v --http2 https://your-api.com` ilə HTTP/2 sorğusu göndərin, `h2` protokolunu confirm edin
- Chrome DevTools → Network tab → Protocol sütununu aktivləşdirin, bir saytın hansı HTTP versiyasını istifadə etdiyini izləyin
- Nginx-də HTTP/2 aktiv edin, `nghttp -v` ilə multiplexing frame-lərini izləyin
- `httpx` kitabxanası ilə HTTP/1.1 vs HTTP/2 50 request benchmark edin, vaxt fərqini ölçün
- `curl --http3 https://cloudflare.com` ilə HTTP/3 test edin (curl 7.88+)
- gRPC-nin niyə HTTP/2 tələb etdiyini, bidirectional streaming-in TCP üzərindən necə işlədiyini izah edin

## Əlaqəli Mövzular
- [TCP vs UDP](01-tcp-vs-udp.md) — HTTP/3-ün QUIC/UDP üzərindən işləməsi
- [TLS/SSL Handshake](03-tls-ssl-handshake.md) — HTTP/2 TLS tələb edir; HTTP/3 built-in TLS 1.3
- [REST vs GraphQL vs gRPC](05-rest-graphql-grpc.md) — gRPC HTTP/2 üzərindən işləyir
- [HTTP Caching](09-http-caching.md) — Cache-Control header-ları HTTP versiyalarında
