# Chaos Engineering (Lead)

## Nədir? (What is it?)

Chaos engineering – sistemin real production şərtlərində necə davranacağını anlamaq və zəif yerlərini aşkar etmək üçün şüurlu olaraq fəlakətlər (failure) yaratmaq disiplindir. Netflix tərəfindən məşhurlaşdırılmış (Chaos Monkey, 2011). Əsas ideya: "hope is not a strategy" – ümid əvəzinə faktiki test et. Distributed sistem olduqca mürəkkəbləşir, unexpected failure mode-lar yaranır. Chaos engineering zəifliyi erkən aşkar edir – production-da real insident olmamış.

## Əsas Konseptlər (Key Concepts)

### Chaos Engineering Prinsipləri

```
Netflix-in 5 prinsipi (principlesofchaos.org):

1. STEADY STATE HYPOTHESIS
   Sistemin normal işləməsini tərif et (SLI metrics)
   Məs: 99% request <500ms, error rate <0.1%

2. REAL-WORLD EVENTS VARY INPUTS
   Real problemləri simulyasiya et:
   - Instance crash
   - Network latency/partition
   - Disk full
   - DNS failure
   - Dependency outage

3. RUN EXPERIMENTS IN PRODUCTION
   Staging ≠ Production
   Real trafik, real yük, real konfiqurasiya

4. AUTOMATE CONTINUOUSLY
   Bir dəfə yox, davamlı test
   CI/CD-də, scheduled job-larda

5. MINIMIZE BLAST RADIUS
   Kiçik başla: 1 instance, 1% trafik
   Monitor et, tədricən böyüt
   Abort switch hər zaman hazır
```

### Chaos Experiment Process

```
1. PLAN
   - Hypothesis: "DB replica itkisi app-ə təsir etməyəcək"
   - Steady state: SLI metrics
   - Blast radius: 1 replica
   - Abort conditions: error rate > 5%, latency > 2s
   - Rollback procedure

2. RUN
   - Notify team (Slack, dashboard)
   - Start monitoring
   - Inject failure
   - Observe behavior

3. ANALYZE
   - Did system behave as expected?
   - What broke unexpectedly?
   - What metrics alerted / didn't alert?

4. IMPROVE
   - Fix issues found
   - Update runbook
   - Add monitoring
   - Automate fix

5. REPEAT
```

### Failure Injection Types

```bash
# 1. INFRASTRUCTURE FAILURES
# Instance termination
aws ec2 terminate-instances --instance-ids i-1234

# Disk full
dd if=/dev/zero of=/tmp/fill bs=1M count=10000

# Memory pressure
stress --vm 4 --vm-bytes 512M --timeout 60s

# CPU load
stress --cpu 8 --timeout 60s

# 2. NETWORK FAILURES
# Packet loss
tc qdisc add dev eth0 root netem loss 10%

# Latency
tc qdisc add dev eth0 root netem delay 500ms

# Bandwidth limit
tc qdisc add dev eth0 root tbf rate 1mbit burst 32kbit latency 400ms

# Remove rules
tc qdisc del dev eth0 root

# 3. APPLICATION FAILURES
# Kill process
kill -9 $(pgrep php-fpm | head -1)

# DB connection drop
iptables -A OUTPUT -p tcp --dport 3306 -j DROP

# DNS failure
echo "nameserver 127.0.0.1" > /etc/resolv.conf

# 4. DEPENDENCY FAILURES
# External API outage
iptables -A OUTPUT -d api.stripe.com -j DROP

# Redis outage
systemctl stop redis

# 5. TIME-RELATED
# Clock skew
date -s "2030-01-01"
```

### Chaos Engineering Tools

```bash
# 1. Chaos Monkey (Netflix, original)
# Random EC2 termination
# https://github.com/Netflix/chaosmonkey

# 2. Litmus (CNCF, Kubernetes)
kubectl apply -f https://litmuschaos.github.io/litmus/litmus-operator.yaml

# Experiment: pod-delete
apiVersion: litmuschaos.io/v1alpha1
kind: ChaosEngine
metadata:
  name: laravel-chaos
  namespace: production
spec:
  appinfo:
    appns: production
    applabel: app=laravel-api
    appkind: deployment
  engineState: active
  chaosServiceAccount: litmus-admin
  experiments:
  - name: pod-delete
    spec:
      components:
        env:
        - name: TOTAL_CHAOS_DURATION
          value: "60"
        - name: CHAOS_INTERVAL
          value: "10"
        - name: FORCE
          value: "false"

# 3. Chaos Mesh (alternative, CNCF)
kubectl apply -f chaos-mesh.yaml

# 4. Gremlin (commercial, enterprise)
gremlin attack cpu --cores 4 --length 60

# 5. AWS Fault Injection Simulator (FIS)
aws fis create-experiment-template \
  --cli-input-json file://experiment.json

# 6. Toxiproxy (network chaos)
toxiproxy-cli create mysql --listen 127.0.0.1:3307 --upstream 127.0.0.1:3306
toxiproxy-cli toxic add mysql -t latency -a latency=1000

# 7. Pumba (Docker chaos)
pumba kill --interval 30s "re2:^laravel"
pumba netem --duration 5m delay --time 3000 "laravel-api"

# 8. Stress-ng (Linux stress)
stress-ng --cpu 8 --io 4 --vm 2 --vm-bytes 512M --timeout 60s
```

### Litmus Experiments Catalog

```yaml
# Common experiments:
# - pod-delete: Random pod kill
# - pod-cpu-hog: CPU stress
# - pod-memory-hog: Memory stress
# - pod-network-loss: Packet loss
# - pod-network-latency: Latency
# - pod-network-duplication: Duplicate packets
# - disk-fill: Fill disk
# - kubelet-service-kill: Node agent kill
# - node-cpu-hog: Entire node CPU
# - container-kill: Kill specific container

# Example: Network latency
apiVersion: litmuschaos.io/v1alpha1
kind: ChaosEngine
metadata:
  name: network-chaos
spec:
  appinfo:
    appns: production
    applabel: app=laravel-api
    appkind: deployment
  chaosServiceAccount: litmus-admin
  experiments:
  - name: pod-network-latency
    spec:
      components:
        env:
        - name: NETWORK_LATENCY
          value: "2000"         # 2 second delay
        - name: TOTAL_CHAOS_DURATION
          value: "60"
        - name: CONTAINER_RUNTIME
          value: "containerd"
```

### Game Days

```markdown
# Game Day - planlı chaos testing

## Before
- Scenario hazırla (məs. "DB primary çökməsi")
- Hypothesis yaz
- Team topla (Dev, Ops, Product)
- Monitoring hazırla (Grafana dashboards)
- Runbook hazır
- Communication channel (Slack war room)
- Rollback plan

## During
- Scribe (notes yazır)
- Observer (metrikləri izləyir)
- Injector (failure inject edir)
- Incident Commander (koordinasiya)

## After
- Retrospective (1 həftə içində)
- Action items: fix issues, update runbook
- Share findings (blog post, all-hands)

## Scenarios (nümunələr)
1. Primary database failover
2. Entire AWS region outage
3. CDN cache poisoning
4. DNS provider down
5. SSL certificate expired
6. All queue workers crash
7. Redis cluster down
8. Kubernetes API server unreachable
9. 10x traffic spike
10. Malicious SQL injection attempt
```

## Praktiki Nümunələr (Practical Examples)

### Laravel app chaos experiment

```yaml
# Scenario: "Redis down - fallback to database cache"
# Hypothesis: Users can still access site, slower but functional

apiVersion: litmuschaos.io/v1alpha1
kind: ChaosEngine
metadata:
  name: redis-down-test
  namespace: production
spec:
  appinfo:
    appns: production
    applabel: app=redis
    appkind: statefulset
  chaosServiceAccount: litmus-admin
  
  experiments:
  - name: pod-delete
    spec:
      probe:
      - name: laravel-availability
        type: httpProbe
        mode: Continuous
        httpProbe/inputs:
          url: "http://laravel-api/health"
          insecureSkipVerify: false
          method:
            get:
              criteria: "=="
              responseCode: "200"
        runProperties:
          probeTimeout: 5
          retry: 3
          interval: 2
          stopOnFailure: true      # Abort if app unhealthy
      
      components:
        env:
        - name: TOTAL_CHAOS_DURATION
          value: "120"
        - name: CHAOS_INTERVAL
          value: "20"
```

### Custom chaos script

```bash
#!/bin/bash
# chaos-test-db.sh
# Simulate MySQL slow queries

HYPOTHESIS="Laravel handles slow DB queries gracefully"
BLAST_RADIUS="1 pod"
DURATION=300

echo "Chaos experiment: DB latency"
echo "Hypothesis: $HYPOTHESIS"
echo "Duration: ${DURATION}s"

# Pre-check
BASELINE_LATENCY=$(curl -w "%{time_total}\n" -so /dev/null https://app.example.com/health)
echo "Baseline latency: ${BASELINE_LATENCY}s"

if (( $(echo "$BASELINE_LATENCY > 1.0" | bc -l) )); then
    echo "ABORT: Baseline already degraded"
    exit 1
fi

# Notify team
curl -X POST $SLACK_WEBHOOK -d '{"text":"🔥 Chaos experiment starting: DB latency"}'

# Inject chaos: Add 1s latency to MySQL
kubectl exec -n production mysql-0 -- tc qdisc add dev eth0 root netem delay 1000ms

# Monitor for duration
END=$(($(date +%s) + DURATION))
while [ $(date +%s) -lt $END ]; do
    LATENCY=$(curl -w "%{time_total}\n" -so /dev/null https://app.example.com/api/users)
    ERROR_RATE=$(curl -s http://prometheus:9090/api/v1/query?query='rate(http_requests_total{status=~"5.."}[1m])' | jq .data.result[0].value[1])
    
    echo "$(date): Latency=${LATENCY}s, ErrorRate=${ERROR_RATE}"
    
    # Abort if error rate too high
    if (( $(echo "$ERROR_RATE > 0.05" | bc -l) )); then
        echo "ABORT: Error rate too high"
        break
    fi
    
    sleep 10
done

# Cleanup
kubectl exec -n production mysql-0 -- tc qdisc del dev eth0 root

curl -X POST $SLACK_WEBHOOK -d '{"text":"✅ Chaos experiment completed"}'

echo "Experiment complete. Analyze results in Grafana."
```

## PHP/Laravel ilə İstifadə

### Laravel resilience patterns

```php
// Laravel app-ı chaos-resistant etmək

// 1. Retry with exponential backoff
use Illuminate\Support\Facades\Http;

$response = Http::retry(3, function ($attempt) {
    return 100 * pow(2, $attempt);      // 100, 200, 400ms
}, function ($exception) {
    return $exception instanceof ConnectionException;
})->timeout(5)->get('https://api.example.com');

// 2. Circuit breaker (custom)
class CircuitBreaker
{
    const OPEN = 'open';
    const CLOSED = 'closed';
    const HALF_OPEN = 'half_open';
    
    public function __construct(
        protected string $service,
        protected int $threshold = 5,
        protected int $timeout = 60
    ) {}
    
    public function call(callable $callback)
    {
        $state = Cache::get("cb:{$this->service}:state", self::CLOSED);
        
        if ($state === self::OPEN) {
            $openedAt = Cache::get("cb:{$this->service}:opened_at");
            if (time() - $openedAt > $this->timeout) {
                Cache::put("cb:{$this->service}:state", self::HALF_OPEN);
            } else {
                throw new CircuitBreakerOpenException($this->service);
            }
        }
        
        try {
            $result = $callback();
            Cache::put("cb:{$this->service}:state", self::CLOSED);
            Cache::forget("cb:{$this->service}:failures");
            return $result;
        } catch (\Exception $e) {
            $failures = Cache::increment("cb:{$this->service}:failures");
            if ($failures >= $this->threshold) {
                Cache::put("cb:{$this->service}:state", self::OPEN);
                Cache::put("cb:{$this->service}:opened_at", time());
            }
            throw $e;
        }
    }
}

// İstifadə
$breaker = new CircuitBreaker('payment-api');
$result = $breaker->call(fn() => Http::get('https://payment-api/charge'));

// 3. Fallback pattern
public function getUser(int $id): User
{
    try {
        return Cache::remember("user:$id", 3600, fn() => User::findOrFail($id));
    } catch (\Exception $e) {
        // Fallback: stale cache
        $stale = Cache::get("user:$id:stale");
        if ($stale) {
            Log::warning('Using stale cache', ['user_id' => $id]);
            return $stale;
        }
        throw $e;
    }
}

// 4. Bulkhead pattern (resource isolation)
// Queue separation: critical, default, low
ProcessPayment::dispatch($order)->onQueue('critical');
SendEmail::dispatch($user)->onQueue('default');
GenerateReport::dispatch()->onQueue('low');

// 5. Timeout everywhere
DB::statement('SET SESSION max_execution_time=5000');  // 5s
Http::timeout(10)->get(...);
Redis::connection()->setOption(Redis::OPT_READ_TIMEOUT, 3);
```

### Laravel chaos middleware (testing)

```php
// app/Http/Middleware/ChaosMiddleware.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ChaosMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('chaos.enabled') || app()->environment('production')) {
            return $next($request);
        }
        
        // Random latency
        if (rand(1, 100) <= config('chaos.latency_percentage', 10)) {
            usleep(rand(500, 3000) * 1000);   // 500ms-3s
        }
        
        // Random errors
        if (rand(1, 1000) <= config('chaos.error_percentage', 1)) {
            abort(500, 'Chaos: random error');
        }
        
        return $next($request);
    }
}
```

## Interview Sualları (5-10 Q&A)

**S1: Chaos engineering nədir və necə meydana gəlib?**
C: Chaos engineering – sistemin zəifliyini aşkar etmək üçün şüurlu failure injection etmək disiplindir. Netflix 2011-də Chaos Monkey alətini yaratdı: random EC2 termination. Məqsəd: "Ümid strategiya deyil" – real test et. AWS-də DC outage-lər onların sistemini zəifliyin üzə çıxarmağa və istifadəçiyə təsirsiz keçirməyə hazırladı.

**S2: Steady state hypothesis nədir?**
C: Chaos experiment-dən əvvəl sistemin "normal" davranışını müəyyən edən ölçülə bilən metriklər (SLI). Məsələn: "99% request <500ms, error rate <0.1%". Experiment zamanı bu metriklər izlənir – hipotez: "failure baş verdikdə sistem hələ də steady state-də qalmalıdır". Əgər pozulursa, weakness tapılmışdır.

**S3: Blast radius nədir və niyə vacibdir?**
C: Chaos experiment-in təsir dairəsi – neçə istifadəçi/servis təsirlənir. Kiçikdən başla (1 instance, 1% traffic, 5 dəqiqə) – təhlükəsiz və azaltlı risk. Tədricən böyüt – tam region, 100% traffic, 1 saat. Production-da blast radius idarəsi kritikdir. Emergency stop hər zaman hazır olmalıdır.

**S4: Chaos Monkey, Litmus və Gremlin arasında fərq?**
C: Chaos Monkey – Netflix, original, EC2 random termination. Litmus – CNCF, Kubernetes-focused, open source, experiment catalog. Gremlin – commercial, GUI, enterprise feature, support. Chaos Mesh (CNCF) – Litmus alternativi, başqa dizayn. Mikroservice arxitekturu üçün Litmus/Chaos Mesh, cloud-level üçün AWS FIS.

**S5: Game Day nədir?**
C: Planlı chaos testing sessiyası – komanda bir araya gəlib scenario execute edir. Rollar: Incident Commander, Scribe, Injector, Observer. Məqsəd: (1) Sistem resiliency test; (2) Incident response prosedurlarını məşq et; (3) Runbook-ları yoxla; (4) Komanda koordinasiyası. Amazon, Netflix, Google regular game day keçirir.

**S6: Production-da chaos experiment etmək təhlükəlidirmi?**
C: Risk var, amma düzgün edilsə minimal: (1) Kiçik blast radius; (2) Off-peak hours; (3) Team hazır, monitoring aktiv; (4) Abort switch; (5) Pre-approved scenarios; (6) Real user-lərə təsiri minimize edilir (feature flags). Əksinə, production-da test etməmək daha risklidir – real insident zamanı daha pis olur.

**S7: Circuit breaker pattern chaos resistance-də necə kömək edir?**
C: Failing service-ə davamlı request göndərməyi dayandırır. 3 state: Closed (normal), Open (failing, request rədd), Half-Open (test mode). Bu, cascading failure qarşısını alır: bir service çökəndə, onu istifadə edən digər servislər də yıxılmaz. Laravel-də custom və ya Ocramius/CircuitBreaker paketi ilə.

**S8: Chaos engineering hansı mərhələdə başlamaq olar?**
C: Prerequisites: (1) Monitoring/observability var (metrics, logs, traces); (2) SLI/SLO müəyyən edilib; (3) Incident response proseduru var; (4) Rollback capability test edilib; (5) Komanda chaos haqqında məlumatlıdır. Başlanğıc addım: staging-də kiçik experiment. Bir dəfəyə production-a atılmayın.

**S9: Laravel app-da hansı chaos scenario-lar test olunmalıdır?**
C: (1) Database primary failover (replica-ya switch); (2) Redis outage (cache fallback); (3) Queue worker crash (retry, DLQ); (4) External API timeout (circuit breaker); (5) High memory/CPU; (6) Network latency; (7) Disk full; (8) Zero-downtime deploy zamanı request handling; (9) Session storage failure; (10) File storage (S3) unavailability.

**S10: MTTR və MTBF nədir?**
C: MTTR (Mean Time To Recovery) – orta bərpa müddəti. Aşağı olmalıdır (incident response effektivliyi). MTBF (Mean Time Between Failures) – orta fəlakətlər arası müddət. Yüksək olmalıdır (reliability). Chaos engineering hər ikisini yaxşılaşdırır: MTBF unknown failure-lar erkən aşkar edilir, MTTR runbook və avtomatlaşdırma sayəsində.

## Best Practices

1. **Start small**: Tək pod, 1% traffic, qısa müddət – sonra böyüt.
2. **Hypothesis-first**: Nə gözləyirsən? Yaz, sonra test et.
3. **Steady state metrics**: SLI əvvəlcədən müəyyən et (error rate, latency).
4. **Abort conditions**: Aydın threshold-lar, avtomatik rollback.
5. **Monitor intensively**: Experiment zamanı real-time dashboard.
6. **Automate**: CI/CD-də scheduled chaos experiments.
7. **Blast radius control**: Kiçik başla, tədricən böyüt.
8. **Team awareness**: Slack notification, on-call xəbərdar.
9. **Post-mortem culture**: Hər experiment sonrası retrospective.
10. **Runbook**: Failure scenarios üçün hazır prosedur.
11. **Business hours**: Off-peak, amma aktiv team olan zaman.
12. **Non-prod first**: Staging-də əvvəl test et, production-a sonra keç.
13. **Game days**: Rüblük planlı chaos testing.
14. **Chaos library**: Sənə aid experiment kataloqu saxla.
15. **Kombine test**: Network + DB + traffic spike – real scenarios.
16. **Learning mindset**: Məqsəd: sistem qırmaq deyil, zəiflik öyrənmək.
17. **Application-level resilience**: Retry, timeout, circuit breaker, bulkhead – kod səviyyəsində.
