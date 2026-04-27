# Edge Computing (Lead)

## İcmal

Edge computing — kodu istifadəçiyə yaxın olan POP (Point of Presence) nöqtələrində işlətmək deməkdir. Ənənəvi arxitekturada bütün sorğular mərkəzi datacenter-ə gedir (məsələn, Frankfurt). Edge-də kod 200+ şəhərdə paralel deploy olunur — Bakıdan sorğu ən yaxın POP-a gedir (Istanbul/Warsaw/Frankfurt).

**Latency fərqi (Latency difference):**
- Origin-only: 100-300ms RTT (Baku -> US datacenter)
- Edge: 10-50ms RTT (Baku -> Istanbul POP)


## Niyə Vacibdir

Cloudflare Workers, Lambda@Edge — sorğunu istifadəçiyə ən yaxın edge node-da işlədir. SSR, A/B test, auth middleware edge-də çalışdıqda latency kəskin azalır. D1/KV edge-də data saxlamağa imkan verir. AI trafik artımı ilə edge inferencing real use-case oldu.

## 1. Sadə Arxitektura (Simple Architecture)

```
Without edge (ənənəvi):
  [Client Baku] ---- 200ms ----> [Origin US]
                                  Laravel + DB

With edge (modern):
  [Client Baku] -- 20ms --> [Edge Istanbul] -- 150ms --> [Origin US]
                             - Auth check                 - DB query
                             - Cache hit?                 - Business logic
                             - A/B assign                 - Mutation
                             - Transform
```

Edge layer çox sorğunu origin-ə çatmadan geri qaytarır (cache hit, auth reject, routing).

## 2. Edge Computing Nədir (What Is Edge Computing)

**Əsas ideya (Core idea):**
- Kod istifadəçiyə yaxın icra olunur
- CDN-in proqramlaşdırıla bilən versiyası
- Serverless-in edge variantı

**Klassik CDN vs Edge:**

```
CDN:   static fayllar cache (HTML, CSS, JS, image)
Edge:  CDN + custom kod icrası (JavaScript, WASM)
```

## 3. İstifadə Halları (Use Cases)

Edge çox yaxşı işləyir bu ssenarilərdə:

| Use case | Nümunə |
|----------|--------|
| Auth check | JWT verification, session validate |
| Routing | A/B test, feature flag, geo routing |
| Personalization | User locale, currency seçimi |
| Bot mitigation | Captcha, rate limit |
| Image optimization | On-the-fly resize, format convert |
| Header manipulation | Security headers (CSP, HSTS) |
| HTML transformation | Inject analytics, A/B variant swap |
| Simple API | Config API, feature flags endpoint |
| Edge SSR | React Server Components render |

## 4. Runtime Müqayisəsi (Runtime Comparison)

### Cloudflare Workers
- V8 isolates (Chrome-un JS engine-i)
- Cold start: <5ms
- Languages: JavaScript, TypeScript, WASM (Rust, Go)
- Memory: 128MB
- CPU limit: 50ms free, 30s paid
- 300+ şəhərdə deploy

### Lambda@Edge (AWS)
- Node.js və Python
- Tam Lambda container
- Cold start: 100-500ms
- Regional edge (US-East primary)
- CloudFront ilə inteqrasiya

### CloudFront Functions (AWS)
- Lambda@Edge-dən daha sürətli/ucuz
- JavaScript subset
- <1ms execution
- Yalnız header və URL rewrite
- Çox məhdud, amma çox sürətli

### Vercel Edge Functions
- V8 isolates (Cloudflare-backed)
- React Server Components inteqrasiya
- Next.js `export const runtime = 'edge'`
- TypeScript native

### Deno Deploy
- V8 isolates
- TypeScript native
- Web-standard API (fetch, Request, Response)
- npm ecosystem support

### Fastly Compute@Edge
- WASM (Wasmtime runtime)
- İstənilən dil WASM-ə kompilyasiya olunursa (Rust, Go, AssemblyScript)
- Security və performance yaxşı

## 5. V8 Isolates vs Container (V8 Isolates vs Container)

**V8 Isolate (Cloudflare Workers):**
```
+------------------------+
| Single V8 process      |
|  +-------+ +-------+   |
|  |tenant1| |tenant2|   |  <- isolate = heap
|  |heap   | |heap   |   |
|  +-------+ +-------+   |
+------------------------+
```
- Shared V8 runtime
- Ayrı memory heap hər tenant üçün
- Cold start: millisekund
- Sandbox V8 səviyyəsində

**Lambda Container:**
```
+------+ +------+ +------+
|cont. | |cont. | |cont. |
|OS +  | |OS +  | |OS +  |
|Node  | |Node  | |Python|
+------+ +------+ +------+
```
- Hər request üçün ayrı OS process
- Cold start: yüzlərlə ms
- Daha çox flexible (filesystem, binary)

**Fərq (Difference):** Isolate-lar sürətlidir amma məhduddur (no filesystem, no native binary). Container-lər gec başlayır amma full runtime verir.

## 6. Edge-də Storage (Storage at Edge)

Edge-də DB connection pool problemlidir (hər POP-dan connection açır). Çözüm — edge-native storage.

### Cloudflare KV (Workers KV)
- Eventually consistent global
- Read: ~10ms (cache hit)
- Write: ~60s global propagation
- Use case: config, feature flags, session cache

### Durable Objects (Cloudflare)
- Strongly consistent per-object
- Hər object bir node-da yaşayır
- Use case: chat room state, rate limiter, game session

### R2 / S3
- Object storage (S3 compatible)
- Cloudflare R2 — egress free
- Large files, backups

### D1 (Cloudflare)
- SQLite replication across edges
- Read replica hər region-da
- Write primary-də

### Turso
- libSQL (SQLite fork)
- Edge replication
- Open-source alternative

## 7. Məhdudiyyətlər (Limits)

Edge kod yazanda bu limitləri bil:

```
Cloudflare Workers (free plan):
- CPU: 10ms
- Memory: 128MB
- Script size: 1MB
- Subrequests: 50 per request
- Request size: 100MB

Lambda@Edge:
- CPU: 5s (viewer), 30s (origin)
- Memory: 128MB (viewer), 10GB (origin)
- Package: 1MB (viewer), 50MB (origin)
```

**Əsas məhdudiyyətlər (Main limits):**
- Long-running connection yoxdur (WebSocket nəzarətli)
- DB pooling çətin — hər isolate ayrı connection
- Cold start hələ də var (kiçik amma var)
- File system yoxdur (Workers)
- Long-running process yoxdur

## 8. Ümumi Pattern-lər (Common Patterns)

### JWT Verification at Edge
```javascript
// Cloudflare Worker
export default {
  async fetch(request, env) {
    const token = request.headers.get('Authorization');
    const valid = await verifyJWT(token, env.JWT_SECRET);
    if (!valid) return new Response('Unauthorized', { status: 401 });
    return fetch(request); // origin-ə pass
  }
}
```

### Geolocation Routing
```javascript
const country = request.cf.country; // Cloudflare auto-detect
if (country === 'AZ') return fetch('https://origin-eu.app.com' + path);
else if (country === 'US') return fetch('https://origin-us.app.com' + path);
```

### Image Optimization
```
Client request: /image.jpg?w=400&format=webp
         |
         v
    [Edge Worker]
    - Parse params
    - Check cache
    - Fetch origin image
    - Resize + convert
    - Cache result
         |
         v
    Optimized image
```

### Request Coalescing
Eyni URL üçün paralel sorğular — edge yalnız bir dəfə origin-ə gedir, digərləri cavabı paylaşır.

### A/B Test Assignment
```javascript
const variant = hash(userId) % 2 === 0 ? 'A' : 'B';
const response = await fetch(request);
return new HTMLRewriter()
  .on('button.cta', { element(el) { el.setInnerContent(variant === 'A' ? 'Buy' : 'Get') }})
  .transform(response);
```

## 9. Edge vs Origin (Edge vs Origin)

Arxitektura bölgüsü:

```
EDGE işləri (Edge responsibility):
- Static cache (CDN)
- Auth / JWT check
- Routing (A/B, geo)
- Header manipulation
- Rate limiting
- Request transformation
- HTML rewriting
- Image optimization

ORIGIN işləri (Origin responsibility):
- DB queries (complex JOIN)
- Business logic
- Write/mutation
- Transactions
- Large data processing
- Background jobs
```

**Qayda (Rule):** Edge read-heavy və stateless işlərə, origin write və stateful işlərə.

## 10. Laravel və Edge (Laravel with Edge)

PHP runtime birbaşa edge-də yoxdur. Amma Laravel app-i edge gateway ilə istifadə etmək olar.

```
[Client]
   |
   v
[Cloudflare Worker] <- edge layer
   | - Auth check (JWT)
   | - Rate limit
   | - Static cache
   | - Geo route
   v
[Laravel Origin] <- AWS, DigitalOcean, Vapor
   - Controller
   - Eloquent
   - Queue
   - DB
```

**Real nümunə (Real example):**
```javascript
// Cloudflare Worker front of Laravel
export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    
    // Static cache
    if (url.pathname.startsWith('/assets/')) {
      return env.CACHE.match(request) || fetch(request);
    }
    
    // Auth check
    if (url.pathname.startsWith('/api/')) {
      const token = request.headers.get('Authorization');
      if (!await verifyJWT(token)) {
        return new Response('Unauthorized', { status: 401 });
      }
    }
    
    // Pass to Laravel origin
    return fetch(`https://origin.myapp.com${url.pathname}`, request);
  }
}
```

Laravel Vapor istifadə edirsənsə — o, Lambda-da işləyir, Cloudflare front edir.

## 11. Deployment və Rollback (Deployment and Rollback)

```
git push -> wrangler deploy
            |
            v
       [Global deploy]
       - 300+ POPs
       - 30-60 saniyə
       - Instant rollback
```

Cloudflare Workers deployment addımları:
1. `wrangler publish` komandası
2. Kod bütün POP-lara push olunur
3. Yeni version atomic switch
4. Rollback: `wrangler rollback` — anında köhnə versiyaya qayıt

## 12. Cost Model (Cost Model)

Cloudflare Workers pricing:
```
Free:    100k request/day
Paid:    $5/month = 10M request/month
         $0.50 per M əlavə
         Plus CPU time
```

Lambda@Edge:
```
$0.60 per 1M request + $0.00005001 per GB-second
```

**Müqayisə (Comparison):**
- Always-on VM: ayda $20-100 (24/7 yanır)
- Edge worker: istifadəyə görə, 1000 request/gün = ~$0

Edge sporadic traffic üçün ucuzdur, yüksək sabit traffic üçün bəzən VM ucuz gəlir.

## 13. Trade-offs (Trade-offs)

**Üstünlüklər (Pros):**
- Latency (10-50ms vs 200-300ms)
- DDoS absorption (Cloudflare global network)
- Auto-scale (heç bir konfiqurasiya lazım deyil)
- Global deployment (bir komanda)
- Cost (low traffic ucuz)

**Çatışmazlıqlar (Cons):**
- Limited runtime (JS/WASM)
- DB connection challenges
- Debugging çətin (distributed)
- Vendor lock-in
- Cold start (kiçik də olsa)
- No long-running job
- Observability fərqli

## 14. DB Strategiyaları (DB Strategies)

Edge-də DB bağlantısı problem. Həlllər:

### 1. HTTP-based DB
```
Edge Worker -> HTTPS -> Supabase / Hasura
             (REST / GraphQL)
```
Connection pool yox, HTTP request. Latency var amma işləyir.

### 2. Edge-native DB
```
Edge Worker -> D1 / Turso (SQLite at edge)
```
Hər POP-da read replica. Write primary-də.

### 3. Connection Pool Gateway
```
Edge Worker -> PgBouncer / Neon / PlanetScale serverless
             -> PostgreSQL / MySQL
```
Gateway connection pool-u saxlayır.

### 4. Aggressive Cache
```
Edge Worker -> KV cache (5 min TTL)
            -> Origin fallback
```
DB-yə getməyin əvəzinə KV oxu.

## 15. Security (Security)

Edge platform security model:
- V8 isolate sandbox (memory isolation)
- CPU/memory limits (cryptomining qarşısı)
- Signed deployments (code integrity)
- WAF inteqrasiya (SQL injection, XSS)
- DDoS protection at POP

**Best practice:**
- Secret-ləri Worker environment variable-da saxla (not in code)
- Sensitive data-nı KV-də encrypt et
- Rate limit hər endpoint-ə

## 16. Observability (Observability)

```
[Edge Worker]
    |
    +--> Console log -> wrangler tail
    +--> Logflare -> BigQuery
    +--> Datadog -> metrics
    +--> Sentry -> errors
```

Hər request üçün:
- Duration (ms)
- CPU time
- POP location
- Status code
- Error trace

## 17. Ne Vaxt İstifadə Etməməli (When NOT to Use)

Edge bu hallarda uyğun deyil:
- Complex stateful workflow
- Long-running job (video encode, ML training)
- Large dependency (AI model, PDF library)
- Heavy DB query (JOIN 10 table)
- WebSocket real-time (məhduddur)
- Filesystem lazımdırsa

Bu halda VM / container daha yaxşıdır.

## 18. Standartlar (Standards)

**WinterCG (Web-interoperable Runtime Community Group):**
- Cloudflare, Vercel, Deno, Node.js birlikdə
- Edge function-ları portable etmək
- Standard API: fetch, Request, Response, Headers, URL
- Eyni kod bir runtime-dan digərinə keçə bilər

```javascript
// Bu kod həm Cloudflare Workers, həm Vercel Edge, həm Deno Deploy-da işləyir
export default {
  async fetch(request) {
    return new Response('Hello from edge');
  }
}
```

## 19. Real Nümunələr (Real Examples)

- **Vercel** — Next.js edge runtime, React Server Components
- **Cloudflare** — Workers platform, 300+ POP
- **Fly.io** — Regional apps (full VM at edge, not isolate)
- **Shopify Oxygen** — Hydrogen storefront at edge
- **Netlify Edge Functions** — Deno-backed
- **AWS** — Lambda@Edge + CloudFront Functions

## Praktik Tapşırıqlar

### Sual 1: Edge computing CDN-dən nə ilə fərqlənir? (How is edge computing different from CDN?)

CDN static fayllar cache edir (HTML, CSS, JS, image). Edge computing əlavə olaraq custom kod icra edir — JS/WASM. Yəni CDN proqramlaşdırıla bilən versiyasıdır. Auth, routing, transformation kimi dinamik işlər edə bilirsən.

### Sual 2: V8 isolate və container arasında fərq nədir? (Difference between V8 isolate and container?)

V8 isolate — bir V8 prosesi daxilində ayrı memory heap-lər. Cold start millisekund, amma məhdud (no FS). Container — ayrı OS proses, cold start yüzlərlə ms, amma tam runtime. Edge isolate-ları istifadə edir, Lambda container-ləri.

### Sual 3: Edge-də DB-yə necə qoşulursan? (How to connect to DB from edge?)

4 yol var: (1) HTTP-based DB (Supabase, Hasura) — REST/GraphQL ilə, (2) Edge-native DB (D1, Turso) — SQLite replicated, (3) Connection pool gateway (Neon, PlanetScale), (4) Aggressive cache (KV) ilə DB-yə daha az get. Birbaşa PostgreSQL connection hər POP-dan problemdir.

### Sual 4: Laravel app-i edge ilə necə istifadə edirsən? (How to use Laravel app with edge?)

Laravel origin-də qalır (Vapor, AWS, DigitalOcean). Cloudflare Worker front-da: auth check, static cache, rate limit, geo routing, A/B assignment. Dinamik request origin-ə pass olunur. Edge read-heavy, origin write/DB.

### Sual 5: Edge Computing məhdudiyyətləri nədir? (What are edge computing limits?)

CPU time (5-50ms typical), memory (128MB), script size (1MB), no filesystem, no long-running process, DB connection problemli, subrequest sayı məhdud (50). Heavy ML, video encode, complex stateful job edge-də işləməz.

### Sual 6: Request coalescing nədir? (What is request coalescing?)

Eyni URL üçün paralel gələn sorğular — edge yalnız bir dəfə origin-ə gedir, digər client-lər həmin cavabı paylaşır. Origin-i overload-dan qoruyur. Cloudflare bunu avtomatik edir populyar URL-lərdə (cache stampede prevention).

### Sual 7: Edge vs origin nəyi harada işlədirsən? (Edge vs origin — what runs where?)

Edge: auth check, routing, cache, header manipulation, HTML rewrite, image optimize, rate limit. Origin: DB query (complex), business logic, write/mutation, transaction, background job. Qayda — read və stateless edge-də, write və stateful origin-də.

### Sual 8: WinterCG nədir? (What is WinterCG?)

Web-interoperable Runtime Community Group. Cloudflare, Vercel, Deno, Node.js birlikdə standart API müəyyən edir (fetch, Request, Response). Məqsəd — bir edge platforma-da yazılan kod başqasında da işləsin. Vendor lock-in azalır, portability artır.

## Praktik Baxış

- Static kontenti həmişə edge-də cache et (Cache-Control header ilə)
- Auth JWT-ni edge-də verify et, origin-ə tokensiz sorğu buraxma
- CPU limit-i nəzərə al — 50ms-dən çox işləmə, break into async tasks
- DB connection əvəzinə HTTP-based DB və ya KV cache istifadə et
- Secret-ləri code-da yox, environment variable-da saxla
- Edge və origin arasında request ID propagate et (tracing üçün)
- Observability qur — Logflare, Datadog, per-POP metrics
- Cold start-ı azaltmaq üçün bundle size kiçik tut
- WinterCG standartlarına sadiq qal (portability üçün)
- Test hər POP region-dan et (Pingdom, GTmetrix)
- Rate limit edge-də qur (Durable Objects ilə dəqiq say)
- Image optimization edge-də et, origin-dən raw image al
- A/B test variant assignment edge-də et, origin bilməsin
- Fallback strategy hazırla — edge fail olarsa origin birbaşa işləsin
- Cost monitoring qur — request count və CPU time track et
- Vendor lock-in-i azalt — abstraction layer yaz, runtime dəyişkən olsun


## Əlaqəli Mövzular

- [CDN](04-cdn.md) — edge computing-in əsası
- [Feature Flags](94-feature-flags-progressive-delivery.md) — edge-də flag evaluation
- [API Gateway](02-api-gateway.md) — edge-də API proxy
- [Caching](03-caching-strategies.md) — edge cache
- [Deployment Strategies](72-deployment-strategies.md) — edge rollout
