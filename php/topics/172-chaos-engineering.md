# Chaos Engineering

## Mündəricat
1. [Chaos Engineering nədir?](#chaos-engineering-nədir)
2. [Netflix Chaos Monkey tarixi](#netflix-chaos-monkey-tarixi)
3. [Chaos Engineering prinsipləri](#chaos-engineering-prinsipləri)
4. [Nə pozulur? (experiment types)](#nə-pozulur-experiment-types)
5. [Blast radius və safety](#blast-radius-və-safety)
6. [GameDays](#gamedays)
7. [Toolkit](#toolkit)
8. [PHP servis-də chaos testing](#php-servis-də-chaos-testing)
9. [Fault injection nümunələri](#fault-injection-nümunələri)
10. [Organisational adoption](#organisational-adoption)
11. [İntervyu Sualları](#intervyu-sualları)

---

## Chaos Engineering nədir?

```
Chaos Engineering — sistemi qəsdən pozaraq ZƏİFLİKLƏRİ kəşf etmək.
"System failure-i gözləməyim, onu QURA YARATIM və nəticələrini görüm."

Məqsəd:
  1. Naməlum failure mode-ları tap
  2. Assumption-ları test et ("DB getsə, cache olacaq" — doğrudurmu?)
  3. Resilience qurulmasını sürətləndir
  4. Team-in incident response bacarığını artır
  5. Monitoring və alerting effektivliyini yoxla

Yanaşma:
  - PRODUCTION-da kontrollu təcrübə (canlı sistem)
  - Hypothesis: "Servis A getsə, ümumi sistem bu cür cavab verəcək"
  - Experiment: A-nı söndür
  - Measure: real davranış vs gözlənilən
  - Fix: kəşf olunmuş zəiflikləri düzəlt

Testing vs Chaos:
  Unit/integration test: kod bilinən pathlar üçün
  Chaos engineering:     sistem unknown unknowns üçün
```

---

## Netflix Chaos Monkey tarixi

```
2010: Netflix AWS-ə miqrasiya edir.
  Problem: Cloud — single instance sabit olmadı.
  "Əgər istənilən instance hər an çıxa bilir, biz ona hazır olmalıyıq."

Həll: Chaos Monkey — production-da təsadüfi EC2 instance-lərini söndürür.
  Qayda: YALNIZ iş saatında, YALNIZ müşahidə altında.
  Fayda: "Pet → Cattle" kulturası möhkəmləndi.

Sonra — Simian Army:
  Chaos Monkey       — Random instance termination
  Latency Monkey     — Network latency artırır
  Conformity Monkey  — Best practice qanunlarına əməl etməyən instance-ləri söndürür
  Doctor Monkey      — Health check
  Janitor Monkey     — Unused resource cleanup
  Chaos Gorilla      — Availability Zone-u söndürür (bütöv AZ)
  Chaos Kong         — Bütöv AWS Region-u söndürür
  
2017: Chaos Engineering PRİNSİPLƏRİ (Principles of Chaos) sənədi.
2020: Industry adoption — hər tech kompaniya öz chaos tool-u.
```

---

## Chaos Engineering prinsipləri

```
1. STEADY STATE HYPOTHESIS qur
   Sistemin "normal" davranışı nədir?
   Misal: "P99 latency < 200ms, error rate < 0.1%"
   Bu metrikləri təyin et.

2. Real-world event-ləri modelləşdir
   Server crash, network partition, DNS fail,
   disk full, clock skew, memory leak, zone outage.

3. Production-da experiment et
   Staging bənzətmə olsa da, production boyda sistemi tam köçürə bilməz.
   BAŞLANĞICDA staging-də başla, sonra production-a gətir.

4. Kontinuous automation
   Chaos experiment-ləri CI/CD pipeline-a inteqrasiya et.
   Hər release növbəti chaos round-u başladır.

5. Blast radius-u kiçilt
   "Hamısı getsin"-dən başlama.
   1% user üçün experiment → monitor → 5% → 10% → tam.

6. Abort mechanism
   Hər chaos experiment dərhal dayandırılmalıdır.
   "Big Red Button" — kill-switch.
```

---

## Nə pozulur? (experiment types)

```
Infrastructure level:
  □ VM/container termination
  □ CPU exhaustion (loop burner)
  □ Memory pressure
  □ Disk fill (no inode-lar, log yazılmır)
  □ Disk slow (IO throttle)
  □ Clock skew (NTP drift)
  □ Process kill
  
Network level:
  □ Packet loss (%10)
  □ Latency injection (300ms)
  □ DNS resolution fail
  □ TCP reset
  □ Network partition (split brain)
  □ Bandwidth limit

Application level:
  □ Exception injection (random 1% request fail)
  □ Slow response (sleep 5s)
  □ Invalid JSON payload
  □ Token expiration
  □ Database connection loss
  □ Cache unavailable
  □ Queue backlog
  
Data level:
  □ Corrupted data (bit flip)
  □ Replica lag artırma
  □ Backup restore drill
  □ Schema migration rollback
```

---

## Blast radius və safety

```
Blast radius — experiment-in sistemə təsir dairəsi.

Başlanğıc (kiçik):
  - Staging environment
  - Dev team-in öz service-i
  - 1 instance (100-dən)
  - İş saatları daxilində
  - On-call mühəndis hazır

Progressive:
  1. Staging / QA environment
  2. Production, 1% traffic (canary)
  3. 10% traffic
  4. Tam istehsalat (bir DC)
  5. Multi-DC / region

Safety qaydaları:
  ✓ İşçi saatlarında (gecə yox)
  ✓ On-call hazır
  ✓ Abort button işləyir
  ✓ İş günündə, həftə sonunda yox
  ✓ Incident olarsa dərhal dayandır
  ✓ Change freeze dönəmindən kənar
  ✓ Stakeholder-lər xəbərdar edilib (PM, support)
```

---

## GameDays

```
GameDay — "planlaşdırılmış chaos təcrübəsi günü".
Bütün team yığılır, birlikdə failure scenaryo keçirir.

Tipik GameDay:
  09:00 — Başlayır: senaryo elan olunur
         "Primary DB failover test edilir"
  09:15 — Hypothesis yazılır:
         "Read replica otomatik promote olacaq, 10s downtime"
  09:30 — Experiment başlayır: DB primary killed
  09:31 — Monitor: dashboard-lar, log-lar
  09:35 — Nəticə: downtime 45s (gözləniləndən 4× uzun!)
  10:00 — Retrospective: nə düzəldilməlidir?
         - Failover timeout configurasiyası
         - Connection pool refresh
         - Alert threshold yenilə
  10:30 — Runbook update

Fayda:
  - Team shared knowledge
  - Muscle memory (incident zamanı panikə düşmə)
  - Documentation aktual qalır
  - Monitoring gap-lər kəşf olunur
```

---

## Toolkit

```
Netflix Simian Army:
  - Chaos Monkey (instance kill)
  - Latency Monkey (network)
  
Gremlin (SaaS):
  - UI-dən experiment
  - Attack library (CPU, memory, network)
  - Kubernetes, AWS, GCP

LitmusChaos (CNCF):
  - Kubernetes-native
  - Open source
  - CRD-lər ilə chaos experiments

Chaos Mesh (CNCF):
  - Kubernetes-native
  - Pod kill, network delay, IO chaos
  - Dashboard UI

AWS Fault Injection Service:
  - EC2 terminate
  - Network partition
  - API throttle

Istio fault injection:
  - Built-in service mesh level
  - Delay, abort inject via VirtualService
  - HTTP 500 forced response
  
ToxiProxy:
  - TCP-level proxy
  - Latency, bandwidth, partial read
  - Development/testing üçün əla
```

---

## PHP servis-də chaos testing

```php
<?php
// Laravel middleware — 1% request-də random fault inject
namespace App\Http\Middleware;

use Closure;

class ChaosMiddleware
{
    public function handle($request, Closure $next)
    {
        if (!config('chaos.enabled') || app()->environment() !== 'staging') {
            return $next($request);
        }
        
        // 1% chance of delay
        if (random_int(1, 100) === 1) {
            usleep(random_int(500_000, 2_000_000));  // 0.5-2s delay
        }
        
        // 0.1% chance of 500
        if (random_int(1, 1000) === 1) {
            return response()->json(['error' => 'chaos'], 500);
        }
        
        return $next($request);
    }
}

// Config-də controlled
// config/chaos.php
return [
    'enabled'       => env('CHAOS_ENABLED', false),
    'delay_chance'  => env('CHAOS_DELAY_CHANCE', 0.01),
    'fail_chance'   => env('CHAOS_FAIL_CHANCE', 0.001),
    'abort_token'   => env('CHAOS_ABORT_TOKEN'),  // emergency stop
];
```

```php
<?php
// Service dependency chaos — external API
class StripeClient
{
    public function __construct(
        private HttpClient $http,
        private Config $config,
    ) {}
    
    public function charge(int $cents): ChargeResult
    {
        // Chaos mode — production-da söndürülür
        if ($this->shouldInjectFault()) {
            throw new StripeException('Injected chaos failure');
        }
        
        return $this->http->post('/charges', ['amount' => $cents]);
    }
    
    private function shouldInjectFault(): bool
    {
        if (!$this->config->get('chaos.stripe_enabled')) return false;
        return mt_rand(1, 1000) <= $this->config->get('chaos.stripe_fail_pct', 10);
    }
}

// Circuit breaker-in test-i:
// Chaos ilə 50% fail inject et → circuit OPEN olmalıdır
// Monitoring: alert işləməlidir
// Fallback: cache-dən oxunmalıdır
```

---

## Fault injection nümunələri

```bash
# Linux tc — network latency
sudo tc qdisc add dev eth0 root netem delay 200ms
# "Bu serverə gələn bütün trafikə 200ms əlavə et"

# Packet loss
sudo tc qdisc add dev eth0 root netem loss 10%

# CPU stress
stress-ng --cpu 8 --timeout 60s

# Memory pressure
stress-ng --vm 2 --vm-bytes 4G --timeout 60s

# Disk fill
dd if=/dev/zero of=/tmp/bigfile bs=1M count=10240

# Kill process randomly
ps aux | grep myservice | awk '{print $2}' | shuf -n 1 | xargs kill -9
```

```yaml
# Kubernetes pod kill — Chaos Mesh
apiVersion: chaos-mesh.org/v1alpha1
kind: PodChaos
metadata:
  name: api-kill
  namespace: chaos
spec:
  action: pod-kill
  mode: one           # bir random pod
  selector:
    namespaces:
      - production
    labelSelectors:
      app: api-service
  scheduler:
    cron: "@every 10m"   # hər 10 dəqiqədə bir

# Network latency chaos
apiVersion: chaos-mesh.org/v1alpha1
kind: NetworkChaos
metadata:
  name: delay-db
spec:
  action: delay
  mode: all
  selector:
    labelSelectors:
      app: mysql
  delay:
    latency: 300ms
    jitter: 100ms
  duration: 5m
```

---

## Organisational adoption

```
Path to chaos maturity:

LEVEL 1 — Ad-hoc
  "Production-da bəzən random çökür, amma bu "təbii"-dir"
  Chaos yoxdur, sadəcə incident response var.

LEVEL 2 — Test environment
  Staging-də failure scenario-lar test edilir.
  Unit/integration test → chaos simulation.

LEVEL 3 — Planned GameDays
  Rəsmi "GameDay" təcrübələri production-da.
  Team-lə birgə, planlaşdırılmış chaos.

LEVEL 4 — Automated in staging
  CI/CD pipeline-da chaos addımları.
  Hər deploy-dan sonra toy etməni.

LEVEL 5 — Continuous production chaos
  Netflix model — random instance termination, 24/7.
  Team bu durumu "normal" sayır.

Praktik məsləhətlər:
  ✓ Başla monitoring-dən — ölçə bilmədiyini pozma
  ✓ Post-mortem kulturasını qur (blameless)
  ✓ Runbook hər scenario üçün
  ✓ "MTTR reduce" (mean time to recover) KPI kimi
  ✓ Leadership dəstəyi şərt — qorxu kulturası öldürür
```

---

## İntervyu Sualları

- Chaos Engineering nədir? Niyə "testing"-dən fərqlidir?
- Netflix Chaos Monkey hansı problemi həll etmək üçün yarandı?
- "Blast radius" nədir və niyə kontrol edilməlidir?
- Steady state hypothesis nədir?
- GameDay nədir və necə keçirilir?
- Production-da chaos experiment aparmaq niyə staging-dən daha faydalıdır?
- Hansı experiment-lərdən başlamaq daha təhlükəsizdir?
- Chaos Mesh və LitmusChaos arasında fərq nədir?
- Tipik PHP servisdə hansı fault inject etmək olar?
- Chaos engineering-in MTTR-ə necə təsiri var?
- "Abort button" niyə vacibdir?
- Small team (5 nəfər) chaos engineering-ə necə başlamalıdır?
