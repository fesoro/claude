# 05 — Networking (Interview Hazırlığı)

Backend developer üçün networking biliklərini əhatə edən interview hazırlıq materialları. HTTP protokollarından başlayaraq DDoS mitigation-a qədər real interview sualları, güclü cavab nümunələri, və praktik kod nümunələri.

---

## Mövzular

### Middle ⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 01 | [tcp-vs-udp.md](01-tcp-vs-udp.md) | TCP vs UDP — transport layer fundamentals |
| 02 | [http-versions.md](02-http-versions.md) | HTTP/1.1 vs HTTP/2 vs HTTP/3 |
| 04 | [dns-resolution.md](04-dns-resolution.md) | DNS Resolution — how names become IPs |
| 05 | [rest-graphql-grpc.md](05-rest-graphql-grpc.md) | REST vs GraphQL vs gRPC |
| 08 | [api-versioning.md](08-api-versioning.md) | API Versioning Strategies |
| 10 | [cors.md](10-cors.md) | CORS — Cross-Origin Resource Sharing |
| 13 | [proxy-reverse-proxy.md](13-proxy-reverse-proxy.md) | Proxy vs Reverse Proxy |

### Senior ⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 03 | [tls-ssl-handshake.md](03-tls-ssl-handshake.md) | TLS/SSL Handshake |
| 06 | [websockets.md](06-websockets.md) | WebSockets |
| 07 | [polling-sse-websocket.md](07-polling-sse-websocket.md) | Long Polling vs SSE vs WebSocket |
| 09 | [http-caching.md](09-http-caching.md) | HTTP Caching (ETags, Cache-Control) |
| 11 | [oauth-jwt.md](11-oauth-jwt.md) | OAuth 2.0 and JWT |
| 12 | [webhook-design.md](12-webhook-design.md) | Webhook Design |
| 16 | [rest-api-design.md](16-rest-api-design.md) | REST API Design Principles |

### Lead ⭐⭐⭐⭐

| # | Fayl | Mövzu |
|---|------|-------|
| 14 | [network-latency.md](14-network-latency.md) | Network Latency and Bandwidth |
| 15 | [ddos-protection.md](15-ddos-protection.md) | DDoS Protection Strategies |

---

## Reading Paths

### API Developer — Hızlı hazırlıq

REST/GraphQL/gRPC seçimi, versioning, CORS, auth, REST design principles — API dizayn interview-ları üçün:
`05` → `08` → `16` → `10` → `11` → `12`

### Backend Infrastructure — Dərin hazırlıq

HTTP protokolları, proxy, caching, TLS — sistem arxitekturası üçün:
`01` → `02` → `03` → `09` → `13`

### Real-time Systems — Focused path

WebSocket, SSE, polling — real-time feature interview-ları üçün:
`01` → `06` → `07` → `05`

### Security-focused path

TLS, OAuth, CORS, DDoS — security interview-ları üçün:
`03` → `10` → `11` → `15`

### Full path (tövsiyə olunur)

`01` → `02` → `03` → `04` → `05` → `06` → `07` → `08` → `09` → `10` → `11` → `12` → `13` → `14` → `15` → `16`

---

## Ən Çox Soruşulan Interview Sualları

- TCP vs UDP fərqi — real use case ilə izah et
- HTTP/2-nin HTTP/1.1-dən üstünlüyü nədir?
- TLS handshake addımlarını izah et
- REST vs GraphQL vs gRPC — nə zaman hansını seçərsiniz?
- Yaxşı REST API dizaynı necə olur? RFC 7807, idempotency, pagination?
- WebSocket scaling problemi — necə həll edərsiniz?
- JWT refresh token strategy — güvənli necə implement edilir?
- CORS `*` ilə `credentials: true` niyə birlikdə işləmir?
- DDoS incident response — addım-addım izah et
