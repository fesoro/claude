# Capacity Planning (Architect ⭐⭐⭐⭐⭐)

## İcmal
Capacity planning — sistemin gələcək yükü daşıya bilməsi üçün infrastruktur resurslarını öncədən planlamaq prosesidir. "Bizim sistem bu ay 1M request daşıdı, növbəki rübdə 5M-ə çıxacağıq — bu yükü daşıya bilərikmi?" sualının cavabı capacity planningdir. Architect səviyyəsindəki developer bu prosesi data-driven şəkildə qurur, resurs tərtibi edir və business-lə koordinasiya edir.

## Niyə Vacibdir
Capacity planning olmazsa iki risk var: over-provisioning (çox pul xərci) ya da under-provisioning (sistem çökür, SLO pozulur). Black Friday-ə hazırsız olmaq, growth-u gözləmədən infra böyütməmək, ya da 10x traffic artımına 1 saatda cavab verməyə çalışmaq — bunların hamısı sağlam capacity planlamasının olmadığını göstərir. Architect kimi business growth trajectory-si ilə texniki capacity-ni birləşdirmək sizin öhdəliyinizdir.

## Əsas Anlayışlar

### Capacity Planning Prosesi:

**Step 1: Baseline ölçmək**
```
Mövcud resurslara baxmaq:
- CPU orta istifadəsi: 40%, peak: 75%
- Memory istifadəsi: 60%, peak: 85%
- Database connections: orta 50, peak 90 (max 100)
- Storage growth rate: 10GB/ay
- Network: orta 500Mbps, peak 800Mbps
```

**Step 2: Traffic projektion**
```
Business metrics:
- Bu ay: 1M user
- Rüblük artım: +40%
- Projection: 3 ay sonra → ~2.7M user
- Peak/orta nisbəti: 3x

Texniki projection:
- Cari: 1000 req/s (peak)
- 3 ay sonra: ~2700 req/s (peak)
```

**Step 3: Bottleneck analizi**
```
Mövcud darboğaz tapılır:
- DB connections: 90/100 (kritik!)
- CPU: 75% peak (kabul edilə bilər)
- Memory: 85% peak (marjinal)

Həll:
- DB connection pool artır ya da PgBouncer əlavə et
- More instances (horizontal scale)
- Memory limitlərini yenidən konfiqurasiya et
```

---

### Resource Planning Modelləri:

**Linear model:**
Traffic 2x artırsa, resurs 2x artır. Sadə amma çox halda düzgüldür.

**Sub-linear (economies of scale):**
Caching, bulk operations — traffic artdıqca marginal cost azalır.

**Super-linear:**
Cartesian product (N→M connections), şifrələmə — traffic artdıqca exponential yük artır.

---

### Headroom Planlaması:

```
Rule of thumb:
- CPU peak: maksimum 70% (30% headroom)
- Memory peak: maksimum 80% (20% headroom)
- DB connections: maksimum 80% (20% headroom)
- Disk: ayın ortasında 80% (growth üçün)

Niyə headroom?
- Unexpected traffic spike (viral content, marketing campaign)
- Garbage collection pauses
- Deployment zamanı rolling update (2x pod)
- Incident recovery (retry storm)
```

---

### Load Testing ilə Capacity Verification:

```bash
# k6 — capacity limit tapma
export const options = {
  stages: [
    { duration: '5m', target: 500 },   // Ramp up
    { duration: '5m', target: 1000 },  // Baseline
    { duration: '5m', target: 2000 },  // 2x
    { duration: '5m', target: 4000 },  // 4x
    { duration: '5m', target: 6000 },  // 6x — breaking point?
    { duration: '5m', target: 0 },     // Ramp down
  ],
  thresholds: {
    http_req_duration: ['p95<1000'],
    http_req_failed: ['rate<0.05'],
  },
};
```

**Breaking point-i tapdıqda:**
- Hansı resursu doldurdu? (CPU, memory, DB?)
- Xəta tipi nə idi? (timeout, 503, OOMKilled?)
- Graceful degradation etdi mi?

---

### Database Capacity Planning:

**Connection pool sizing:**
```
Formula: connections = (core_count * 2) + effective_spindle_count
(PostgreSQL WIKI)

Nümunə: 4 core, SSD → (4*2) + 1 = 9 connections per server
3 app server → 27 connections lazım

PgBouncer (connection pooler) ilə:
- 100 app connection → 20 real DB connection
```

**Storage projection:**
```
Cari usage: 200GB
Aylıq artım: 15GB
6 ay projektion: 200 + (6*15) = 290GB
Buffer (20%): 290 * 1.2 = 348GB → 400GB provision et
```

**Query performance degradation:**
```
B-tree index: O(log n) — data artdıqca marginal
Full table scan: O(n) — 100M row-da problematik
VACUUM/bloat: PostgreSQL-da dead tuple birikirsə yavaşlar
Partitioning: aylıq partition — hər partition kiçik qalır
```

---

### Auto-scaling vs Pre-provisioning:

**Auto-scaling:**
- HPA (Kubernetes): CPU/memory metric-ə görə pod əlavə edir
- KEDA: Custom metric (queue length, RPS)-ə görə scale
- Reaktiv — scaling latency var (30-90s pod startup)

**Pre-provisioning (peak capacity):**
- Bütün vaxt peak capacity hazır
- Sıfır scaling latency
- Baha: idle capacity xərclənir

**Hybrid strategy:**
```
Base capacity: peak-in 60%-ini həmişə hazır saxla (pre-provisioned)
Spike capacity: HPA ilə avtomatik əlavə (reactive, amma startup latency var)
```

**Scale-out vs Scale-up:**
| | Scale-out (horizontal) | Scale-up (vertical) |
|--|----------------------|---------------------|
| Mexanizm | Daha çox instance | Daha böyük instance |
| Limit | Teorik yoxdur | Machine size limiti |
| Cost | İnteraktif, granular | Büyük artışlar |
| Risk | Koordinasiya | Single point of failure |
| Stateless app | Ideal | Mümkün |
| Stateful DB | Mürəkkəb | Sadə |

---

### Capacity Planning Calendar:

```
Quarterly review:
- Son rübün artım faizi hesabla
- Növbəki rübün projektion
- Bottleneck analizi
- Budget request (əgər infra artışı lazımdırsa)

Annual planning:
- Bir illik growth model
- Infrastructure renewal (hardware lifecycle)
- Cost optimization fırsatları

Event-based:
- Marketing campaign (Black Friday, launch)
- Load test ilə event capacity verify
- Extra capacity provision et, campaign sonrası scale-down
```

---

### Cost Model:

```
AWS nümunəsi:
- On-Demand: Çevik, baha — dev/test üçün
- Reserved (1-3 il): 40-60% ucuz — predictable base load
- Spot instances: 70-90% ucuz — fault-tolerant batch iş
- Savings Plans: flexible commitment, 20-50% ucuz

Capacity planning + Cost optimization birləşdirir:
- Base load (daimi): Reserved/Savings Plan
- Variable (peak): On-Demand
- Batch processing: Spot

Projektion:
- 6 ay sonra: +30% traffic
- Mövcud reserved capacity: yetərsiz
- Action: Q3-dən əvvəl reserved instance renewal
```

---

### Failure Capacity (N+1 Redundancy):

```
Sağlam sistem N+1 çalışır:
- 3 app server, istənilən biri düşsə 2-si yükü daşıyır
- DB primary + standby replica
- Multi-AZ deployment

Capacity planlama N+1 üçün:
- 2 server, peak 80% CPU → 1 düşsə, digəri 160% → çökmə!
- 3 server, peak 50% CPU → 1 düşsə, 2-si 75% → kabul edilə bilər
```

## Praktik Baxış

**Interview-da necə yanaşmaq:**
"Sistemin capacity-sini necə planlaşdırırsınız?" sualına "auto-scaling var, problem yoxdur" demə. Baseline ölçmə, projektion model, bottleneck analizi, N+1 redundancy, cost model — bunları birlikdə izah et. Real nümunə: "Q4 öncəsindən 6 həftə əvvəl load test etdik, DB connection pool bottleneck-ini tapdıq, PgBouncer əlavə etdik."

**Follow-up suallar:**
- "Auto-scaling capacity planning-i əvəz edirmi?"
- "Database capacity planlaması niyə application-dan fərqlidir?"
- "Black Friday üçün necə hazırlaşırsınız?"

**Ümumi səhvlər:**
- Yalnız CPU/memory izləmək (DB connections, network, disk unudulur)
- Auto-scaling-in scaling latency-sini unutmaq
- N+1 redundancy üçün capacity planlamak
- Staging-dəki test real production traffic-i simulate etmir

**Yaxşı cavabı əla cavabdan fərqləndirən:**
"HPA qurdum, özü scale edir" vs "HPA reaktivdir — 60s startup latency var. Buna görə base capacity həmişə hazır, spike üçün HPA. Rüblük review ilə base capacity-ni business growth-a uyğun artırıram."

## Nümunələr

### Tipik Interview Sualı
"Şirkətiniz 3 ayda user sayını 10x artırmağı hədəfləyir. Sistemin hazır olması üçün nə edərdiniz?"

### Güclü Cavab
"Prosesi 3 mərhələdə qurardım. Birincisi, baseline: mövcud sistemin hansı yüklə hansı resursu istifadə etdiyini dəqiq ölçərəm — CPU, memory, DB connections, query performance, network. İkincisi, projektion: 10x traffic = hansı komponentin neçə qatına ehtiyac var? Load test ilə current breaking point-i taparam, bottleneck-ləri aşkar edərəm. Üçüncüsü, həll planı: stateless app layer — horizontal scale, HPA. DB — connection pooler, read replica, partition. Storage — tiered storage, archival policy. Bir ayın sonunda benchmark test ilə 10x capacity verify edərəm. N+1 redundancy üçün plan edərəm — bir node düşsə sistem ayaqda qalsın."

## Praktik Tapşırıqlar
- Mövcud sistemin baseline metric-lərini çıxar: CPU/memory/DB connections
- 6 aylıq traffic projektion hazırla (growth rate əsasında)
- k6 ilə breaking point tap: hansı resurs birinci dolar?
- Database connection pool sizing hesabla: PgBouncer lazımdırmı?

## Əlaqəli Mövzular
- [05-sla-slo-sli.md](05-sla-slo-sli.md) — Capacity SLO-nu qorumaq üçün lazımdır
- [02-container-orchestration.md](02-container-orchestration.md) — HPA, Kubernetes scaling
- [09-cost-optimization.md](09-cost-optimization.md) — Capacity = Cost — ikisi birlikdə idarə edilir
- [04-observability-pillars.md](04-observability-pillars.md) — Capacity metric-ləri observability-dən gəlir
