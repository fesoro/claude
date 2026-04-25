# Resource Limits və Sizing PHP-FPM üçün Docker/K8s-də

> **Səviyyə (Level):** ⭐⭐⭐ Senior
> **Oxu müddəti:** ~20-25 dəqiqə
> **Kateqoriya:** Docker / Kubernetes / Performance

## Nədir? (What is it?)

Containerized PHP-FPM-ə CPU və yaddaş (memory) limitləri təyin etmək — hər pod/konteynerin nə qədər resurs işlədə biləcəyini deklarə etmək deməkdir.

**Bu niyə vacibdir?**
- **Under-allocate** (aşağı verirsən) → pod **OOMKilled** (Out Of Memory Killed), request iteldə ölür, istifadəçi məlumat itirir
- **Over-allocate** (çox verirsən) → cluster-də boş resurs, pis scheduling, **büdcə israfı** (AWS/GCP pulu)
- **Heç qoymursan** → "noisy neighbor" problemi: bir pod bütün node-un resurslarını yeyir, qonşu pod-lar yavaşlayır

Doğru sizing PHP-in xüsusiyyətlərini bilmək tələb edir: single-threaded per-request, opcache shared memory, pm.max_children, FPM pool davranışı.

## Əsas Konseptlər

### 1. PHP-FPM-in Memory Modeli

PHP-FPM "master" prosesi və "worker" prosesləri var. Hər request bir worker-də işlənir. Worker memory-si:

```
Worker memory = Base PHP memory + App memory + Request peak memory
```

Tipik Laravel worker üçün:
- **Base PHP + opcache (per-worker share)** ~ 20-30 MB
- **Laravel framework boot** ~ 20-40 MB
- **Request peak** (hər endpoint fərqli) ~ 10-100 MB
- **Toplam (orta worker)** ~ 50-100 MB

**Nümunə ölçmə:**

```bash
# Konteynerdə worker prosesləri gör
docker exec laravel-app ps aux | grep php-fpm

# USER       PID  %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND
# root         1  0.0  0.5 130432  22100 ?        Ss   10:30   0:00 php-fpm: master
# www-data    12  0.5  1.2 145320  48200 ?        S    10:30   0:02 php-fpm: pool www
# www-data    13  0.6  1.3 148220  52100 ?        S    10:30   0:03 php-fpm: pool www

# RSS (Resident Set Size) = əsl RAM istifadəsi (KB)
# 48200 KB ≈ 47 MB per worker
```

**Memory per pod formula:**

```
pod_memory = master_memory + (pm.max_children × avg_worker_memory) + opcache_shared
```

Nümunə:
```
master         = 20 MB
max_children   = 10
avg_worker     = 60 MB
opcache_shared = 128 MB (opcache.memory_consumption)

pod_memory = 20 + (10 × 60) + 128 = 748 MB
```

Buffer əlavə et (20-30%): **~900 MB memory limit**.

### 2. PHP-FPM Pool Modes — static vs dynamic vs ondemand

**`pm = static`** — sabit sayda worker, həmişə qalxıq

```ini
; php-fpm.conf
pm = static
pm.max_children = 10
```

- **Üstünlük:** predictable memory (həmişə 10 worker), ramp-up yox
- **Çatışmazlıq:** low traffic-də də boş worker-lər yaddaşı tutur
- **Nə vaxt:** K8s-də hər pod stabildir, HPA pod sayını idarə edir — pool-da sabitlik yaxşıdır

**`pm = dynamic`** — min/max/start_servers ilə dinamik

```ini
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 6
```

- **Üstünlük:** traffic-ə uyğun adapt olur
- **Çatışmazlıq:** peak-də yeni worker spawn yavaş ola bilər (cold start)
- **Nə vaxt:** traffic variable-dır, bir podda çox iş var

**`pm = ondemand`** — worker yalnız request gələndə qalxır

```ini
pm = ondemand
pm.max_children = 50
pm.process_idle_timeout = 10s
```

- **Üstünlük:** ən az memory (sakit vaxtda worker yoxdur)
- **Çatışmazlıq:** hər cold start latency qatır
- **Nə vaxt:** çox az traffic (internal tool, background process)

**Tövsiyə:** K8s-də **`static`** əksər halda. Pod restart edir, HPA scale edir — pool-un özünün dinamik olmasına ehtiyac yoxdur.

### 3. pm.max_children Necə Hesablanır?

```
pm.max_children = (container_memory - opcache - base) / avg_worker_memory
```

Nümunə — 1 GB memory limit olan konteyner:
```
1024 - 128 (opcache) - 30 (base) = 866 MB
866 / 60 MB per worker ≈ 14 worker
```

Safer: 12 worker (buffer).

**Vacib:** `max_children` sadəcə memory-dən asılıdır. CPU-dan da asılıdır (aşağıda).

### 4. CPU Sizing — PHP Single-Threaded

PHP request per-process işlənir. Tək request blocking I/O etməyəndə 1 CPU core-un yarısını yeyə bilər, amma 2 request paralel işləyəndə 2 CPU core lazımdır.

**Formula:**
```
cpu_needed ≈ concurrent_requests × avg_cpu_per_request
```

Nümunə: 10 req/s, hər request 100ms CPU işlədir:
```
concurrent = 10 × 0.1 = 1 CPU
```

Peak traffic-də 3x buffer: **~3 CPU** pod üçün.

**Gerçəkdə PHP request çox vaxt I/O gözləyir** (DB, Redis, external API). Bu halda 1 CPU 5-10 concurrent request-ə bəs edə bilər. Ölçmə lazımdır.

**Docker `--cpus` vs K8s `cpu:`:**

```bash
# Docker
docker run --cpus=2 laravel
# 2 CPU core-un ekvivalenti (CFS quota)

# K8s
resources:
  requests:
    cpu: "500m"   # 0.5 CPU (500 millicore)
  limits:
    cpu: "2000m"  # 2.0 CPU
```

**1 CPU = 1000m (millicore).** `500m` = yarım core.

### 5. CPU Throttling — Gizli Qatil

K8s `cpu` limiti **CFS quota** işlədir (Completely Fair Scheduler). Hər 100ms-də pod `limit × 100ms` CPU işlədə bilər. Limit bitərsə — pod **throttle** olur (dayandırılır sonrakı period-a qədər).

**Nəticə:** Pod ortalama CPU-nu keçmir, amma p99 latency pis olur, çünki burst vaxtı throttle baş verir.

**Detection:**

```bash
# Prometheus metric
container_cpu_cfs_throttled_seconds_total
container_cpu_cfs_periods_total

# Throttle rate = throttled / periods
# >5% → problem
```

**Həll:** CPU limit-i artır, və ya CPU limit-i heç qoyma (yalnız request). Kubernetes 1.28+-də `cpu.cfs_burst` dəstəyi var — burst icazə verir.

**Vacib debat:** Çox təcrübəli SRE-lər **CPU limit qoymamağa** üstünlük verir (yalnız request). Səbəb: CFS throttling çox gizli problem yaradır, node crash olsa HPA/VPA onsuz da idarə edir.

### 6. OpCache və JIT Memory Təsiri

OpCache compiled PHP-i shared memory-də saxlayır. Default 128 MB:

```ini
opcache.enable = 1
opcache.memory_consumption = 128
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0   # prod-da fayl dəyişikliyini yoxlama
```

128 MB worker-lər arasında **shared** olur (hər worker-da ayrı deyil). Yəni pod memory-sinə 1 dəfə əlavə olunur, hər worker üçün yox.

**JIT (PHP 8+):**
```ini
opcache.jit_buffer_size = 128M
opcache.jit = tracing
```

JIT 128 MB əlavə götürür. API-də adətən ~5-15% sürət verir (CPU-bound kod üçün daha çox). Memory-ni ona uyğun artır.

### 7. K8s Requests vs Limits

```yaml
resources:
  requests:
    memory: "512Mi"   # scheduler-in rezerv etdiyi (guaranteed)
    cpu: "500m"
  limits:
    memory: "1Gi"     # maksimum (keçsə OOMKilled)
    cpu: "2000m"      # maksimum (keçsə throttle)
```

**Requests** — scheduler bunu node-da rezerv edir. Pod başqa pod-ları sıxışdırmır. `kubectl describe node`-da görünür.

**Limits** — pod bu qədər keçə bilməz. Keçsə (memory) OOMKill, (CPU) throttle.

**QoS (Quality of Service) sinifləri:**

| Scenario | QoS sinfi | Davranış |
|----------|-----------|----------|
| Requests = Limits | **Guaranteed** | Heç vaxt evict edilmir |
| Requests < Limits | **Burstable** | Node pressure-də evict edilə bilər |
| Heç biri yox | **BestEffort** | İlk evict olunan |

**Prod tövsiyəsi:** Kritik app-lar üçün `requests = limits` (Guaranteed QoS). PHP-FPM pod-u adətən belə qoyulur.

### 8. OOMKilled Detection

```bash
# Pod restart olub — nə üçün?
kubectl describe pod laravel-abc

# Containers:
#   php-fpm:
#     Last State:     Terminated
#       Reason:       OOMKilled
#       Exit Code:    137            ← 137 = SIGKILL (9) + 128
#       Started:      Mon, 20 Apr 2026 14:23:01
#       Finished:     Mon, 20 Apr 2026 14:28:43
#     Restart Count:  3
```

**Exit code 137** → OOMKill. Memory limit-i az idi.

**Alternativ:** Node-da `dmesg | grep -i oom`:

```
[1234567.89] Memory cgroup out of memory: Killed process 12345 (php-fpm)
```

**Tövsiyə:**
- Prometheus-da alert qoy: `kube_pod_container_status_restarts_total` spike
- `container_memory_working_set_bytes / container_spec_memory_limit_bytes > 0.9` → yaxınlaşırıq

### 9. CPU Throttling Detection

```bash
# Metric
sum(rate(container_cpu_cfs_throttled_seconds_total[5m])) by (pod)
  /
sum(rate(container_cpu_cfs_periods_total[5m])) by (pod)
# > 0.05 (5%) → problem
```

**App-ın görüntüsü:** Ortalama response time normaldir, amma p95/p99 spike-ları var. Log-da tipik pattern: bütün request-lər sakit, birdən 3-5 saniyə latency, sonra yenə sakit.

### 10. Real Laravel Sizing Nümunəsi

**Ssenarı:** API 50 RPS, p95 200ms response time.

**1. Concurrent requests:**
```
concurrent = RPS × avg_response_time = 50 × 0.2 = 10 concurrent
```

**2. Replicas sayı:**
Hər podda `pm.max_children = 10` olsa, 1 pod 10 concurrent-i idarə edə bilər. Amma əmin üçün 2-3 pod (HA + buffer):
```
replicas = 3
```

Hər pod 4 concurrent (33% ortalama load).

**3. Per-pod resources:**
```yaml
resources:
  requests:
    cpu: "500m"      # 0.5 CPU
    memory: "768Mi"
  limits:
    cpu: "2000m"     # 2 CPU burst üçün (və ya limit yox)
    memory: "1Gi"
```

**4. K8s manifest:**

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: laravel-api
spec:
  replicas: 3
  selector:
    matchLabels:
      app: laravel-api
  template:
    metadata:
      labels:
        app: laravel-api
    spec:
      containers:
        - name: php-fpm
          image: mycompany/laravel:1.0.0
          ports:
            - containerPort: 9000
          env:
            - name: PHP_FPM_PM
              value: "static"
            - name: PHP_FPM_MAX_CHILDREN
              value: "10"
            - name: PHP_OPCACHE_MEMORY
              value: "128"
          resources:
            requests:
              cpu: "500m"
              memory: "768Mi"
            limits:
              memory: "1Gi"   # CPU limit qoymuruq (throttling qaçınırıq)
          readinessProbe:
            tcpSocket:
              port: 9000
            initialDelaySeconds: 5
            periodSeconds: 5
          livenessProbe:
            tcpSocket:
              port: 9000
            initialDelaySeconds: 30
            periodSeconds: 10
---
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: laravel-api-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: laravel-api
  minReplicas: 3
  maxReplicas: 20
  metrics:
    - type: Resource
      resource:
        name: cpu
        target:
          type: Utilization
          averageUtilization: 70   # 70% CPU-da yeni pod qaldır
  behavior:
    scaleDown:
      stabilizationWindowSeconds: 300   # 5 dəq gözlə (yalançı scale-down-dan qaç)
```

### 11. PgBouncer — Connection Pool Vacibliyi

10 replicas × 10 workers × 1 DB connection per worker = **100 connection**. Postgres default `max_connections = 100` — bütün quota bitir.

**PgBouncer** (proxy) ilə:

```
Laravel pods × 100 connections
      ↓
    PgBouncer (1 instance)
      ↓
    20 connections to Postgres
```

PgBouncer transaction-level pool işlədir — worker connection-u buraxanda, PgBouncer onu başqa worker-ə verir. **Hər replicas əlavə etdikdə** bunu yadda saxla.

```yaml
# docker-compose.yml
pgbouncer:
  image: edoburu/pgbouncer
  environment:
    DB_HOST: postgres
    DB_USER: laravel
    POOL_MODE: transaction
    MAX_CLIENT_CONN: 1000
    DEFAULT_POOL_SIZE: 20
  ports:
    - "6432:5432"
```

Laravel `.env`:
```
DB_HOST=pgbouncer
DB_PORT=6432
```

### 12. `--memory-swap` və Swap

```bash
# Docker
docker run --memory=1g --memory-swap=1g laravel
# --memory-swap=1g = swap yoxdur (yalnız 1g RAM)
```

**Produksiyada swap YOXDUR.** K8s default swap disable edir. Swap-a düşən PHP prosesi son dərəcə yavaş olur, p99 latency partlayır. OOMKill swap-dan yaxşıdır (fast fail).

## Best Practices

1. **Həmişə requests və limits qoy** — BestEffort QoS-dan qaç
2. **Requests = limits (Guaranteed QoS)** kritik PHP pod-larında
3. **CPU limit qoyma** (və ya çox yüksək qoy) — throttling pis
4. **Memory limit həmişə qoy** — memory leak-dən qoruma
5. **pm.max_children dəqiq hesabla** — memory limit-ə əsasən
6. **static pool** K8s-də — predictable
7. **OpCache enable + revalidate off** prod-da
8. **Per-request memory ölç** — ağır endpoint-ləri tap
9. **HPA CPU 70%-də** — dərhal scale əvəzinə stabil
10. **PgBouncer işlət** — çox replica-da DB connection exhaust-dan qaç
11. **Prometheus alert-lər** — OOMKill, throttling, memory 90%
12. **Load test et** — real traffic ilə sizing-i doğrula

## Tələlər (Gotchas)

### 1. Memory limit çox aşağı → OOMKill mid-request
Pod request yarı qədər emal edib OOMKill olur, client 502 alır, potensial **data loss** (yarımçıq DB yazma). Limit-i rahat qoy + Prometheus alert.

### 2. Memory limit çox yuxarı → scheduling pis
K8s node-un qalan RAM-ini rezerv edir, digər pod-lar qalmır. Node under-utilized. Realistic ölçü.

### 3. Limit yox → noisy neighbor
Pod bütün node-u yeyir, qonşu pod-lar starvation. Shared cluster-də fəlakət.

### 4. `pm.max_children` fiziki resource-dan az
Memory var, amma worker yoxdur — request queue-da gözləyir, timeout. `max_children`-i həqiqi limit-ə uyğunlaşdır.

### 5. `pm.max_children` resource-dan çox
Çox worker memory tələb edir, pod OOMKill. Formula ilə hesabla.

### 6. CPU limit → throttling → latency spike
Ortalama CPU 30%, amma p99 5 saniyə. `container_cpu_cfs_throttled_seconds_total` yüksəkdir. Limit-i artır və ya sil.

### 7. OpCache yenidən-load prod-da
`opcache.validate_timestamps=1` (default) hər request-də fayl stat edir. I/O yandırır. Prod-da `0` qoy, deploy vaxtı `opcache_reset()` çağır.

### 8. `numactl` problemi
Bəzi node-larda NUMA arxitekturası var. `numactl --hardware` yoxla. Bu ekspert mövzusudur — çox vaxt K8s idarə edir.

### 9. DB connection exhaustion HPA ilə
10 pod-da yaxşı işləyir, HPA 30-a qalxanda DB `too many connections` verir. PgBouncer olmadan scale etmə.

### 10. Memory "spike" sizing-i aldadır
Bir endpoint 500 MB peak işlədir, qalanı 50 MB. Ortalama 60 MB-dır, amma bir request OOMKill edir. **p99 memory per worker**-ə baxmaq lazım.

### 11. Init container-də migration → memory peak
Migration-lar çox yaddaş işlədə bilər. Init container-ə ayrı (daha yüksək) limit ver.

### 12. Queue worker-ləri ayrı pod olmalıdır
`artisan queue:work` uzun-yaşayan prosesdir, `php-fpm` worker-lərindən fərqli profil. Ayrı Deployment, ayrı resources.

## Müsahibə Sualları

### S1: PHP-FPM-də `pm.max_children`-i necə hesablayırsan?
**C:** Formula: `(container_memory - opcache_shared - base) / avg_worker_memory`. Nümunə 1 GB limit, 128 MB opcache, 30 MB base, 60 MB avg worker: (1024 - 128 - 30) / 60 ≈ 14 worker. Safer: 12 (buffer). Ölçmək üçün prod-a yaxın load ilə `ps aux`-da RSS izlə.

### S2: K8s-də `requests` və `limits` arasında fərq nədir?
**C:** **Requests** — scheduler bunu node-da rezerv edir (guaranteed minimum). Pod-un "əsas hüququ"dur. **Limits** — pod bu qədər keçə bilməz; memory keçsə OOMKill, CPU keçsə throttle. Requests = limits → Guaranteed QoS (heç vaxt evict olunmur). Prod kritik pod üçün requests=limits tövsiyə olunur.

### S3: OOMKilled-i necə detect edirsən?
**C:** `kubectl describe pod` çıxışında `Last State: Terminated, Reason: OOMKilled, Exit Code: 137`. 137 = 128 + 9 (SIGKILL). Node-da `dmesg | grep oom`. Prometheus-da `kube_pod_container_status_last_terminated_reason{reason="OOMKilled"}` metrikini monitor et, alert qoy.

### S4: CPU throttling nədir və nəyə görə pisdir?
**C:** K8s `cpu` limit-i CFS quota işlədir — hər 100ms period-da pod `limit × 100ms` CPU işlədə bilər. Burst peak-də pod dayandırılır növbəti period-a qədər. Nəticə: ortalama CPU-da problem yoxdur, amma p95/p99 latency partlayır. Metric: `container_cpu_cfs_throttled_seconds_total / container_cpu_cfs_periods_total > 5%`. Həll: CPU limit-i artır və ya heç qoyma.

### S5: Niyə bəzi SRE-lər CPU limit qoymur?
**C:** CPU limit CFS throttling-ə səbəb olur və gizli latency problemi yaradır. Memory limit mütləqdir (sonsuz memory leak-dən qorumaq üçün), amma CPU üçün yalnız `requests` qoymaq tövsiyə olunur — scheduler pod-u fair yerləşdirir, HPA scale edir, amma throttling yoxdur. Trade-off: noisy neighbor riski.

### S6: PHP-FPM-də `static`, `dynamic`, `ondemand` arasında fərq nədir?
**C:** **static** — sabit `max_children` worker, həmişə qalxıq. Predictable memory. **dynamic** — min/max arası, load-a görə adapt. Ramp-up var. **ondemand** — worker yalnız request gələndə qalxır, idle-də söndürülür. Minimum memory, amma cold start latency. K8s-də HPA pod-u idarə etdiyi üçün `static` tövsiyə olunur.

### S7: Çox replica olanda DB connection exhaustion-dan necə qaçırsan?
**C:** Hər PHP worker ayrı DB connection açır. 20 replica × 10 worker × 1 connection = 200, Postgres default 100 connection limit. Həlllər: 1) **PgBouncer** (transaction-pool proxy) — PHP-dən gəlir, PgBouncer pool-dan verir; 2) `max_connections`-u artır (RAM tələb edir); 3) per-worker connection limit Laravel-də. Prod-da PgBouncer standart.

### S8: Memory sizing üçün hansı metriklərə baxırsan?
**C:** 1) `container_memory_working_set_bytes` — əsl memory (cache daxil deyil); 2) `container_memory_rss` — per-process; 3) `container_spec_memory_limit_bytes` — limit; 4) Nisbət: working_set / limit > 80% → artırma vaxtı. Per-worker: Laravel `APP_DEBUG=false` ilə prod-a yaxın workload altında `ps aux` və ya `smem` işlədib RSS-in p99-una bax.
