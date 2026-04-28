# Cloudflare (Architect)

## Ümumi baxış
- **Nə edir:** CDN, DDoS qoruma, WAF, DNS (1.1.1.1), Workers (edge compute), Zero Trust, R2 (object storage), D1 (SQLite at edge), KV, Durable Objects. İnternetin böyük bir hissəsinin "Internet-in security və performance layer"-i.
- **Yaradılıb:** 2009-cu ildə Matthew Prince, Lee Holloway, Michelle Zatlyn tərəfindən.
- **Miqyas (açıq məlumat):**
  - 330+ şəhərdə 120+ ölkədə PoP (Point of Presence).
  - Dünya internet istifadəçilərinin ~20%-ə xidmət edir.
  - Gündə trilyonlarla HTTP request.
  - 1.1.1.1 DNS: Cloudflare-in ən sürətli public DNS resolverlərindən biri.
- **Əsas tarixi anlar:**
  - 2010: launch; ücsüz DDoS protection.
  - 2014: Universal SSL — hamı üçün pulsuz HTTPS.
  - 2017: Cloudflare Workers (V8 isolates at edge).
  - 2018: 1.1.1.1 DNS launch.
  - 2019: public, $CF ticker.
  - 2021: R2 object storage (egress fee-siz S3 alternativi).
  - 2022: blog *"How we built Pingora, the proxy that connects Cloudflare to the Internet"* — Nginx-in yerini tutdu.
  - 2023: Workers AI, Vectorize.

Cloudflare **Rust və Go-nun production-da edge-də həm performance, həm də güvənlik üçün istifadəsi** barədə ən öyrədici müasir nümunələrdən biridir.

## Texnologiya yığını

| Layer | Technology | Why |
|-------|-----------|-----|
| Edge proxy (əsas) | **Pingora (Rust)** — Nginx-in əvəzi | Custom connection reuse, az yaddaş, proqnozlaşdırılan latency |
| Edge compute | **Workers (V8 isolates)** + Rust runtime | Milliseconds-də cold start, minimum overhead |
| QUIC/HTTP3 | **Quiche (Rust)** | Cloudflare-in açıq mənbəli QUIC implementasiyası |
| Control plane | **Go** | API server-lər, orchestration |
| Networking / systems | **Rust, C, Go** | Yüksək performans, statik tiplər |
| DNS | Custom Go-based resolver (RRDNS) | Global 1.1.1.1 |
| Durable state | **Durable Objects** (Workers üzərində) | Strong consistency edge-də |
| Storage — KV | **Workers KV** | Eventually consistent key-value at edge |
| Storage — object | **R2** | Egress-siz S3 alternativi |
| Storage — SQL | **D1** (SQLite at edge) | Developer-friendly, edge-native SQL |
| Storage — vector | **Vectorize** | ML embedding üçün |
| Queues | **Cloudflare Queues** | At-least-once edge delivery |
| Observability | Öz tooling + Prometheus/Grafana | Edge scale-də dərin observability |

## Dillər — Nə və niyə

### Rust — performance-critical path-lar
- **Pingora** — bütün edge HTTP trafik-ini işləyən reverse proxy, Rust ilə yazılıb.
- **Quiche** — QUIC/HTTP3 protokolu implementasiyası, Rust.
- Edge-də network packet processing, cryptographic operations.
- **Niyə Rust:**
  - GC yoxdur → proqnozlaşdırılan tail latency.
  - Memory safety → Nginx-də olan bir çox CVE-ləri compiler-in özü önləyir.
  - Mövcud async (tokio) ekosistemi.
  - Fearless concurrency.

### Go — control plane və API
- Dashboard API, billing, hesab idarəetməsi.
- RRDNS (Cloudflare-in DNS server-i).
- **Niyə Go:** sürətli compile, yaxşı concurrency, yetkin HTTP ekosistemi.

### C — legacy və aşağı-səviyyə
- eBPF program-ları (Linux kernel level networking).
- Tarixən Nginx custom modulları (artıq Pingora ilə əvəzlənir).

### JavaScript / TypeScript — Workers runtime
- Workers əsasən V8 isolates üzərində işləyir — **Node.js deyil**.
- Customer kodu TypeScript/JavaScript-dır.
- Runtime-ın özü **workerd** (C++) Chromium V8 üzərində qurulub.

## Framework seçimləri — Nə və niyə
- **Pingora (custom Rust proxy framework)** — Cloudflare tərəfindən açıq mənbəli edildi 2024-də.
- **Quiche (Rust QUIC)** — açıq mənbəli.
- **workerd (C++ V8 host)** — açıq mənbəli; Workers runtime bunun üzərində.
- **tokio + hyper (Rust)** — async ekosistemi.
- **cap'n proto** — bəzi daxili RPC.

## Verilənlər bazası seçimləri — Nə və niyə

### Heç vaxt tək mərkəzi DB yoxdur
Cloudflare-in fundamental prinsipi: **edge-də state.** Bir regional veritabanı özünü xarab etsə, bütün internet itməz.

### Workers KV
- Eventually consistent key-value storage.
- Read-heavy workload üçün optimallaşdırılıb.
- Dəyişiklik dünyaya 60 saniyədən az vaxta yayılır.

### Durable Objects
- Workers-in strong consistency üçün cavabı.
- Hər obyekt tək bir fiziki məkanda olur, amma bu dünya üzərində düzgün yerə köçürülə bilər.
- Chat servers, multiplayer game state, collaborative docs üçün idealdır.

### R2 — S3 alternativi
- S3 API-uyğun, lakin **egress fee yoxdur**.
- Bu, multi-cloud dizaynda inqilabi həll idi — yüz milyonlarla dollar qənaət.
- Cloudflare-in global network-u üzərində dağılmış.

### D1 — SQLite at edge
- Hər Worker SQLite database-inə bağlana bilər, lakin paylanmış.
- Yazılar primary location-a getdikdən sonra read replica-lara yayılır.
- Dev-friendly: sadə SQL, lokal `wrangler` ilə test.

### Vectorize
- Vector DB, embedding-lər üçün. AI use case-lər.

### Daxili storage
- Control plane üçün PostgreSQL (müştəri hesabları, billing, konfiq).
- ClickHouse analytics üçün (trilyonlarla event üçün).

## Proqram arxitekturası

Cloudflare **Anycast network** üzərində qurulmuşdur. Hər bir PoP eyni BGP IP-lərini anons edir; internet routing istifadəçini ən yaxın PoP-a göndərir.

```
            User (anywhere in the world)
                     |
              [Anycast routes → nearest PoP]
                     |
                 [Cloudflare PoP]
                  /      |      \
         [Pingora]   [Workers]  [DNS]
            |            |
      [Origin server]  [Durable Objects / R2 / KV / D1]
                         |
              [Replicated globally over Cloudflare network]
```

### Pingora — Nginx-in əvəzi
- 2022-də Cloudflare blog-da: *"How we built Pingora"*.
- Problem: Nginx process-per-request model müştərilərin HTTP/1.1, HTTP/2, QUIC qarışıq workloads-ı üçün effektiv connection reuse yarada bilmirdi.
- Həll: **Pingora** — Rust-da multi-threaded async proxy, hər CPU core-dan effektiv istifadə edir, daxili connection pool-u PoP boyu bütün müştərilər üçün paylaşır.
- Nəticələr (Cloudflare blog-undan):
  - 160x daha az connection-a origin-ə (connection reuse).
  - Origin server-lərdə 434x daha az new connection.
  - CPU və yaddaş Nginx-dən daha az istifadə.
  - Daha proqnozlaşdırılan p99 latency.

### Workers — V8 isolates
- Hər Worker müştərisi **öz V8 isolate**-ında işləyir, tam Node.js prosesi deyil.
- Cold start < 5ms (Lambda-nın 200-500ms-i ilə müqayisədə).
- Bir fiziki server minlərlə isolate işlədə bilər.
- Eyni server-də müxtəlif client-lər, memory-safe V8 izolyasiyası ilə.

### Anycast
- Hər PoP eyni IP-ni BGP ilə anons edir.
- İstifadəçilərin sorğuları BGP vasitəsilə ən yaxın PoP-a router olunur.
- DDoS-u doğal olaraq həll edir: 10 Tbps attack 300+ PoP-a paylanır → hər PoP ~30 Gbps, manageable.

### DDoS qorunma
- Edge-də aggregated signatures (packet rate, ASN reputation).
- Sürətli L3/L4 drop-lar XDP/eBPF ilə.
- L7 protection: WAF, challenge (CAPTCHA, JS challenge).

## İnfrastruktur və deploy
- Öz global network — datacenter, fiber contracts, interconnects.
- Gradual global rollout — canary PoP-lar, sonra regional, sonra global.
- Heavy observability: hər packet üçün metrics, distributed tracing.
- Ağır incident culture — transparent public postmortems.

## Arxitekturanın təkamülü

| İl | Dəyişiklik |
|------|--------|
| 2010 | Launch, Nginx + Lua üzərində qurulmuş |
| 2014 | Universal SSL — hamı üçün pulsuz HTTPS |
| 2017 | Workers launched (V8 isolates) |
| 2018 | 1.1.1.1 DNS launch |
| 2020 | R2 object storage elan edildi |
| 2022 | Pingora Nginx-in əvəzinə düşdü (Rust) |
| 2022 | D1, Durable Objects, Queues |
| 2023+ | Workers AI, Vectorize — edge ML inference |

## Əsas texniki qərarlar

1. **Rust-a Nginx-dən keçid (Pingora).** C kodunda memory bugs və Nginx-in connection model-inin limitləri istehsalda ağır idi. Rust-un ownership model-i bu iki problemi həll etdi. İllərlə inkişaf, çoxlu iterasiya.
2. **Global Anycast.** Hər PoP-un eyni IP anons etməsi DDoS resilience-i doğal hala gətirir və latency-ni minimum saxlayır.
3. **V8 isolates vs containers.** AWS Lambda konteynerlər istifadə edir, 200-500ms cold start. Cloudflare Workers V8 isolates istifadə edir, <5ms cold start. Cold-start problem həll olundu.
4. **R2 zero egress.** Cloud-lar arasında bandwidth fee-ləri Cloudflare üçün differensiasiya nöqtəsi oldu.
5. **Edge-first, heç vaxt tək mərkəzi region.** Hər məhsul ilk gündən global replicate olunur. Az region-da rollout yoxdur.

## Müsahibədə necə istinad etmək

1. **DDoS protection:** "Cloudflare 300+ PoP-a Anycast ilə paylanır, L3/L4 drop eBPF/XDP ilə, L7 WAF və challenge. Sənin öz sistemin olsaydı, start: rate limit + in-kernel filtering + cloud scrubbing service."
2. **Edge compute:** "Lambda vs Workers trade-off: Lambda daha zəngin runtime, Workers daha sürətli cold start. Workers V8 isolates istifadə edir, Lambda container-lər."
3. **CDN necə qurular:** "Anycast ilə hər PoP-da cache-lə. Cache invalidation purge API ilə. Origin connection pool (Pingora pattern) ilə origin load-u azalt."
4. **Object storage alternativ:** "R2 S3-uyğun lakin egress-siz. Əgər outgoing bandwidth-ın ağır costdur, R2 düşünmək məntiqli."
5. **Consistency trade-off:** "Workers KV eventually consistent (60s global sync). Durable Objects strong consistent, lakin tək location. Kənd üçün seçim: KV (ucuz, sürətli read), DO (strong consistency lazım).".

## Əlavə oxu üçün
- Cloudflare Blog: *How we built Pingora, the proxy that connects Cloudflare to the Internet*
- Cloudflare Blog: *Announcing Pingora open source*
- Cloudflare Blog: *Introducing Cloudflare Workers*
- Cloudflare Blog: *Durable Objects — Cloudflare*
- Cloudflare Blog: *Quiche: QUIC and HTTP/3 library*
- Cloudflare Blog: *Announcing R2, object storage without egress fees*
- Talk: *Why Cloudflare Chose Rust* (various conferences)
- Talk: Kenton Varda on Cloudflare Workers architecture
