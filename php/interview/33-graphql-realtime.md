# GraphQL & Real-time — Interview Suallar

## Mündəricat
1. GraphQL əsasları
2. N+1 və DataLoader
3. WebSockets / Reverb / Soketi
4. Server-Sent Events
5. Sual-cavab seti

---

## 1. GraphQL əsasları

**S: GraphQL ilə REST arasında 3 əsas fərq?**
C:
1. Endpoint sayı — REST çox, GraphQL bir (`/graphql`)
2. Over/under-fetching — REST var, GraphQL yoxdur (client field seçir)
3. Versioning — REST URL-də (/v1, /v2), GraphQL deprecation

**S: GraphQL nə vaxt yaxşı seçim DEYİL?**
C: 
- Sadə CRUD API
- File-heavy (video stream)
- Strict REST ekosistem (HATEOAS, OpenAPI)
- Small team — operational complexity

**S: Query, Mutation, Subscription fərqi?**
C: 
- Query — read (REST GET)
- Mutation — write (POST/PUT/DELETE)
- Subscription — real-time (WebSocket)

**S: Schema-first və code-first fərqi?**
C: 
- Schema-first — `.graphql` SDL fayl, kod implement edir
- Code-first — PHP class-dan schema generate

**S: Lighthouse ilə webonyx/graphql-php fərqi?**
C: Lighthouse — Laravel-specific, directive-əsaslı (@hasMany), schema-first. Webonyx — framework-agnostic, low-level, hər iki yanaşma.

---

## 2. N+1 və DataLoader

**S: GraphQL-də N+1 niyə daha çox baş verir?**
C: Nested query (`users { posts { comments }}`) — hər user üçün posts query, hər post üçün comments query. Recursive resolver problem.

**S: DataLoader necə işləyir?**
C: Per-request batch loader. Resolver-lər ID-ləri queue-ya yığır, tick sonu bir SQL query (IN clause) ilə fetch.

**S: Lighthouse @hasMany direktivi N+1 həll edir?**
C: Bəli, built-in batch loader var. Eloquent relation-larını bir query-də yükləyir.

**S: Query complexity limit nədir?**
C: Hər field-ə weight verilir. Toplam threshold-u keçən query rədd olunur. DoS qarşı.

**S: Query depth limit?**
C: `query { a { b { c { d ... }}}}` — 20+ level → DoS vektoru. Lighthouse `max_query_depth: 10`.

---

## 3. WebSockets / Reverb / Soketi

**S: WebSocket ilə HTTP polling fərqi?**
C: WebSocket — bidirectional, persistent (single TCP). Polling — client hər N saniyə request, bandwidth, latency yüksək.

**S: SSE (Server-Sent Events) WebSocket ilə nə zaman üstündür?**
C: One-way (server → client) data lazımdırsa: notifications, stock ticker. SSE HTTP-üzəri, proxy-friendly, browser auto-reconnect.

**S: Laravel Reverb nədir?**
C: Laravel 11+ native WebSocket server. ReactPHP əsaslı. Pusher protokol uyumlu (Echo işləyir). Self-hosted Pusher alternativi.

**S: Soketi və Reverb müqayisəsi?**
C: 
- Reverb — PHP, Laravel ekosistem
- Soketi — Node.js + uWebSockets.js (C++), daha sürətli
- Hər ikisi Pusher-uyumlu

**S: Sticky sessions WebSocket-də niyə lazımdır?**
C: Connection stateful — eyni server-ə bağlı qalmalıdır. LB load balance-də ip_hash və ya cookie sticky.

**S: WebSocket scale necə olunur?**
C: Multiple WS server + Redis pub/sub backplane. Server A-dakı message Redis-ə publish → bütün server-lər subscribe → öz client-lərinə forward.

**S: Private və Presence channel fərqi?**
C: 
- Private — auth lazım, user-specific (chat room)
- Presence — Private + "kim online" siyahısı (collaborative editor)

---

## 4. Server-Sent Events

**S: SSE necə işləyir?**
C: HTTP response `Content-Type: text/event-stream`. Server-dən event chunks streaming. Browser `EventSource` API.

**S: SSE-nin browser limit-i?**
C: 6 concurrent SSE connection per domain. WebSocket bu limit-ə daxil deyil.

**S: SSE auto-reconnect?**
C: Browser built-in. `Last-Event-ID` header send edir → server hardan davam etmək lazımdır bilir.

**S: PHP-də SSE necə implementasiya olunur?**
C: 
```php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
while (true) {
    echo "data: " . json_encode($payload) . "\n\n";
    ob_flush(); flush();
    sleep(1);
}
```
Long-running PHP lazımdır (FPM-də `max_execution_time` set et).

---

## 5. Sual-cavab seti

**S: GraphQL caching REST-dən niyə çətindir?**
C: URL həmişə eyni `/graphql` (CDN cache key). Həll: persisted queries (hash → cacheable URL).

**S: Persisted query nədir?**
C: Client query-ni hash-ləyir, server qoruyur. Sonrakı request yalnız hash göndərir → CDN-dostu.

**S: GraphQL Federation nədir?**
C: Multiple GraphQL service-i tək gateway altında birləşdir. Apollo Federation, Hasura Remote Schema. Microservice-friendly.

**S: GraphQL upload (multipart) necə işləyir?**
C: GraphQL multipart spec — `Content-Type: multipart/form-data`. operations + map + files. Lighthouse dəstəkləyir.

**S: GraphQL subscription transport?**
C: Klassik: WebSocket (graphql-ws protokol). Modern: SSE. Lighthouse Pusher/Reverb istifadə edir.

**S: WebSocket connection reconnect storm necə qarşısı alınır?**
C: Client exponential backoff (1s, 2s, 4s, 8s ... max 30s) + jitter. Server burst handling.

**S: WebSocket auth necə olur?**
C: 
1. JWT URL query (sub-optimal — log-larda)
2. Cookie (browser auto-send)
3. İlk message-də auth (custom protocol)
Reverb: HTTP route-da auth → channel subscribe authorize callback.

**S: Long-polling vs WebSocket necə seçim?**
C: 
- Long-polling: simple, proxy-friendly, low message rate
- WebSocket: high message rate, bi-directional, lower latency

**S: WebRTC nə zaman lazımdır?**
C: Peer-to-peer (video call, file share). WebSocket server-mediated. WebRTC NAT traversal (STUN/TURN).

**S: Server-side rendering GraphQL ilə necə?**
C: Apollo SSR — server query işlədir, HTML-ə inject edir. Client hydrate. SEO + fast first paint.

**S: GraphQL error handling — 200 OK + errors array niyə?**
C: Partial data mümkündür (bir field fail, qalanlar OK). REST 5xx tam fail bildirir. GraphQL granularity yüksək.

**S: GraphQL introspection production-da niyə bağlanır?**
C: Schema açıq olarsa attacker bütün query-ləri görür → query complexity exploit asanlaşır. Internal client-lər üçün açıq qoyula bilər.

**S: WebSocket ping/pong frame nəyə xidmət edir?**
C: Keep-alive. Idle connection-ları detect (NAT timeout 60-120s). Stale connection-ları drop et.

**S: Client message rate limiting WebSocket-də?**
C: Per-connection token bucket. Aşıqsa connection close (DoS qarşı). Reverb middleware ilə.

**S: GraphQL DataLoader cache scope?**
C: Per-request. Bir request-də eyni ID iki dəfə resolve olunmaz. Sonrakı request fresh fetch.

**S: GraphQL schema directive nə üçün?**
C: Schema-da metadata: `@auth`, `@deprecated`, `@cache`. Server build/runtime-da oxuyur.

**S: Apollo Federation entity resolver?**
C: Hər servis öz entity-ləri resolve edir. `@key(fields: "id")` ilə entity bölünür. Gateway query-ni bölüb müvafiq servislərə göndərir.

**S: Subscription scaling məsələsi?**
C: Hər subscription persistent connection. 100k user → 100k WebSocket → çoxlu server lazımdır. Redis pub/sub coordinate edir.

**S: Pusher vs self-hosted (Reverb)?**
C: Pusher — managed, zero ops, paid. Reverb — self-host, free, scale öz öhdənizdə.

**S: GraphQL niyə binary deyil (Protobuf kimi)?**
C: Web-friendly (JSON). Browser native parse. Debug asan. Performance-critical-də gRPC üstündür.
