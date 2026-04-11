# Microservices — Service Discovery

## Mündəricat
1. [Problem: Dinamik Endpoint-lər](#problem-dinamik-endpointlər)
2. [Client-side vs Server-side Discovery](#client-side-vs-server-side-discovery)
3. [Kubernetes DNS](#kubernetes-dns)
4. [Consul](#consul)
5. [Health Checks](#health-checks)
6. [PHP İmplementasiyası](#php-implementasiyası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Problem: Dinamik Endpoint-lər

```
// Bu kod statik IP konfiqurasiyasının dinamik mühitdəki problemlərini izah edir
Static IP/hostname ilə problem:

config: PAYMENT_SERVICE_URL=http://10.0.0.5:8080

❌ IP dəyişə bilər (container restart, auto-scaling)
❌ Bir neçə instance varsa hansına müraciət et?
❌ Sağlam instance-ı necə tap?

Həll: Service Discovery
  Servislər öz endpoint-lərini registry-ə qeydiyyat etdirir
  Consumer registry-dən endpoint-i öyrənir
```

---

## Client-side vs Server-side Discovery

```
// Bu kod client-side və server-side discovery pattern-lərini diaqramla müqayisə edir
Client-side Discovery:
  Client → Registry → IP list alır → birini seç → call

  ┌────────┐   1. Query    ┌──────────┐
  │ Client │─────────────►│ Registry │
  │        │◄─────────────│          │
  │        │  2. IP list  └──────────┘
  │        │
  │        │  3. LB + call ┌──────────┐
  │        │──────────────►│ Service  │
  └────────┘               └──────────┘

  ✅ Client LB logic-ini kontrol edir
  ❌ Hər client LB kodunu implement etməlidir
  ❌ Dil/framework-ə bağımlılıq

Server-side Discovery:
  Client → Load Balancer → Registry → Service

  ┌────────┐  1. Call   ┌────────────┐  2. Lookup  ┌──────────┐
  │ Client │───────────►│   LB/Proxy │────────────►│ Registry │
  │        │            │            │◄────────────│          │
  │        │            │            │  3. IP list └──────────┘
  │        │◄───────────│            │
  │        │  Response  └──────┬─────┘
  └────────┘                   │ 4. Call
                         ┌─────▼──────┐
                         │  Service   │
                         └────────────┘

  ✅ Client sadədir (discovery logic yoxdur)
  ✅ LB centralized
  ✅ Dil-agnostic
  Nümunə: Kubernetes (kube-proxy + DNS), AWS ELB
```

---

## Kubernetes DNS

```
// Bu kod Kubernetes DNS-in service adlarını avtomatik həll etmə mexanizmini göstərir
Kubernetes-də ən sadə service discovery:

Service yarandıqda DNS adı avtomatik:
  <service-name>.<namespace>.svc.cluster.local

Nümunə:
  payment-service.default.svc.cluster.local:8080
  user-service.production.svc.cluster.local:80

Qısa formalar (eyni namespace-dədirsə):
  payment-service:8080
  user-service

K8s DNS həll zənciri:
  payment-service → 
  payment-service.default →
  payment-service.default.svc →
  payment-service.default.svc.cluster.local →
  10.96.x.x (ClusterIP)

Service növləri:
  ClusterIP: Yalnız cluster daxilində (default)
  NodePort: Host port-u açır
  LoadBalancer: Cloud LB (AWS ELB, GCP LB)
  Headless: DNS → birbaşa pod IP-ləri (stateful apps)
```

*Headless: DNS → birbaşa pod IP-ləri (stateful apps) üçün kod nümunəsi:*
```yaml
# Bu kod Kubernetes Service və Deployment resurslarını service discovery üçün konfiqurasiya edir
# kubernetes/payment-service.yaml
apiVersion: v1
kind: Service
metadata:
  name: payment-service
  namespace: production
spec:
  selector:
    app: payment
  ports:
    - port: 80
      targetPort: 8080
  type: ClusterIP

---
# PHP app deployment
apiVersion: apps/v1
kind: Deployment
metadata:
  name: php-app
spec:
  replicas: 3
  template:
    spec:
      containers:
        - name: php
          env:
            - name: PAYMENT_SERVICE_URL
              value: "http://payment-service.production.svc.cluster.local"
            - name: USER_SERVICE_URL
              value: "http://user-service"  # Eyni namespace
```

---

## Consul

```
// Bu kod Consul-un service registry, health check və multi-datacenter arxitekturasını izah edir
HashiCorp Consul — Service Mesh + Discovery:

Xüsusiyyətlər:
  Service Registry: Servislər özlərini qeydiyyat etdirir
  Health Checking: Sağlam servisləri filtr edir
  KV Store: Konfiqurasiya saxlamaq üçün
  Service Mesh: mTLS, traffic management
  Multi-datacenter: DC-lər arası discovery

Arxitektura:
  ┌────────────────────────────────────┐
  │         Consul Servers (3-5)       │
  │         (Raft consensus)           │
  └────────────────┬───────────────────┘
                   │
        ┌──────────┼──────────┐
        ▼          ▼          ▼
  ┌──────────┐ ┌──────────┐ ┌──────────┐
  │  Node 1  │ │  Node 2  │ │  Node 3  │
  │ Consul   │ │ Consul   │ │ Consul   │
  │ Agent    │ │ Agent    │ │ Agent    │
  │ + App    │ │ + App    │ │ + App    │
  └──────────┘ └──────────┘ └──────────┘
  
Service registration:
  Agent-lər local service-ləri monitor edir
  Health check keçirsə → registry-ə əlavə et
  Health check uğursuz → registry-dən çıxar
```

---

## Health Checks

```
// Bu kod Kubernetes-in liveness, readiness və startup probe növlərini izah edir
K8s health check növləri:

Liveness Probe:
  "Container canlıdır?"
  Fail → Container restart et
  
Readiness Probe:
  "Container traffic almağa hazırdır?"
  Fail → Service endpoint-dən çıxar (traffic göndərmə)
  
Startup Probe:
  "Container başladı?"
  Yavaş başlayan app-lar üçün (liveness probe-dan əvvəl)
```

*Yavaş başlayan app-lar üçün (liveness probe-dan əvvəl) üçün kod nümunəsi:*
```yaml
# Bu kod Kubernetes pod-da liveness, readiness və startup probe konfiqurasiyasını göstərir
# kubernetes health checks
spec:
  containers:
    - name: php-fpm
      livenessProbe:
        httpGet:
          path: /health/live
          port: 80
        initialDelaySeconds: 10
        periodSeconds: 10
        failureThreshold: 3
        
      readinessProbe:
        httpGet:
          path: /health/ready
          port: 80
        initialDelaySeconds: 5
        periodSeconds: 5
        failureThreshold: 2
        
      startupProbe:
        httpGet:
          path: /health/startup
          port: 80
        failureThreshold: 30
        periodSeconds: 10
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Bu kod PHP-də liveness, readiness health check endpoint-lərini və Consul qeydiyyatını göstərir
// Health check endpoints
class HealthController extends Controller
{
    // Liveness: process canlıdır?
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }
    
    // Readiness: traffic almağa hazır?
    public function ready(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis'    => $this->checkRedis(),
            'queue'    => $this->checkQueue(),
        ];
        
        $allOk = !in_array(false, $checks, true);
        
        return response()->json([
            'status' => $allOk ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $allOk ? 200 : 503);
    }
    
    private function checkDatabase(): bool
    {
        try {
            DB::selectOne('SELECT 1');
            return true;
        } catch (\Exception) {
            return false;
        }
    }
    
    private function checkRedis(): bool
    {
        try {
            return Redis::ping() === 'PONG';
        } catch (\Exception) {
            return false;
        }
    }
    
    private function checkQueue(): bool
    {
        // Queue işləyirmi? Son 5 dəqiqədə heartbeat varmı?
        return Cache::get('queue:heartbeat', false) !== false;
    }
}

// Consul ilə service registration (PHP SDK)
class ConsulServiceRegistrar
{
    public function register(): void
    {
        $http = new \GuzzleHttp\Client(['base_uri' => 'http://consul:8500']);
        
        $http->put('/v1/agent/service/register', [
            'json' => [
                'ID'      => 'php-app-' . gethostname(),
                'Name'    => 'php-app',
                'Address' => gethostname(),
                'Port'    => 80,
                'Tags'    => ['production', 'v1'],
                'Check'   => [
                    'HTTP'     => 'http://' . gethostname() . '/health/ready',
                    'Interval' => '10s',
                    'Timeout'  => '5s',
                ],
            ],
        ]);
    }
    
    public function discover(string $serviceName): string
    {
        $http = new \GuzzleHttp\Client(['base_uri' => 'http://consul:8500']);
        
        $response = $http->get("/v1/health/service/$serviceName?passing=true");
        $services = json_decode($response->getBody(), true);
        
        if (empty($services)) {
            throw new ServiceNotFoundException("$serviceName tapılmadı");
        }
        
        // Random load balancing
        $service = $services[array_rand($services)];
        $address = $service['Service']['Address'];
        $port    = $service['Service']['Port'];
        
        return "http://$address:$port";
    }
}
```

---

## İntervyu Sualları

**1. Service discovery niyə lazımdır?**
Mikroservislər dinamik IP-lərə sahibdir (container restart, auto-scaling). Static IP config mümkün deyil. Service Discovery registry-dən həmişə up-to-date, sağlam endpoint-i öyrənir. Kubernetes DNS, Consul, Eureka həll variantlarıdır.

**2. Client-side vs Server-side discovery fərqi nədir?**
Client-side: Client registry-dən IP alır, özü LB edir (Netflix Eureka). Server-side: Client LB/proxy-yə gedir, proxy registry-dən soruşur (Kubernetes kube-proxy, AWS ELB). Server-side sadədir, dil-agnostikdir. Client-side daha çevikdir amma hər client LB logic lazımdır.

**3. K8s-də liveness vs readiness probe fərqi nədir?**
Liveness: "container canlıdır?" — fail olsa restart edilir. Readiness: "traffic almağa hazırdır?" — fail olsa service endpoint-dən çıxarılır (traffic kəsilir). Readiness: DB connection qurulana qədər traffic alma. Liveness: deadlock, memory leak-dən restart.

**4. Consul nədir, K8s DNS-dən fərqi nədir?**
K8s DNS: yalnız Kubernetes daxili, sadə, built-in. Consul: multi-platform (VM + container + bare metal), multi-datacenter, KV store, service mesh, health check rich. Hybrid infrastructure-da Consul daha uyğundur. Pure K8s: DNS yetər.

**5. K8s Service növlərini izah et.**
ClusterIP (default): yalnız cluster daxilində əlçatan. NodePort: hər node-un portunu açır, dışarıdan əlçatan. LoadBalancer: cloud provider-ın LB-ini yaradır (AWS ELB). Headless Service (`clusterIP: None`): DNS birbaşa pod IP-lərini qaytarır — StatefulSet, database cluster-lar üçün.

**6. Service mesh nədir, niyə lazımdır?**
Servis-to-servis kommunikasiyanı idarə edən infra layer (Istio, Linkerd). Sidecar proxy (Envoy) hər pod-a əlavə edilir: mTLS, retry, circuit break, traffic shifting (canary deploy), observability — app kodu dəyişmədən. Mikroservis sayı artdıqca manual implementation çətin olur, service mesh centralize edir.

---

## Anti-patternlər

**1. Static IP/hostname ilə servis ünvanlarını hardcode etmək**
`$url = "http://192.168.1.10:8080/api"` — container restart olunanda IP dəyişir, servis çatılmaz olur. Service discovery istifadə edin: K8s-də DNS adı (`http://order-service/api`), Consul-da service name ilə sorğu edin.

**2. Health check olmadan servisi registry-ə qeydiyyat etmək**
Servis qeydiyyatda var, amma artıq up deyil — registry-dən alınan endpoint-ə sorğu gedir, xəta qaytarılır. Liveness və readiness probe-ları mütləq konfiqurasiya edin; registry yalnız sağlam instance-ları göstərsin, xəstə instance-lar avtomatik siyahıdan çıxsın.

**3. Client-side load balancing-də instance siyahısını cache etməmək**
Hər sorğuda registry-dən instance-ları almaq — registry-ə həddindən artıq yük düşür, latency artır. İnstance siyahısını müvəqqəti cache edin (məs: 30 saniyə) və arxa planda refresh edin; registry xəta versə köhnə siyahı ilə işləməyə davam edin.

**4. Servis registry-ni SPOF (Single Point of Failure) kimi buraxmaq**
Consul ya da Eureka single node — registry çöksə bütün servis-to-servis kommunikasiya pozulur. Registry-ni cluster rejimində qurun: K8s DNS built-in replikalıdır; Consul üçün ən azı 3 node-lu raft cluster qurun.

**5. Readiness probe olmadan yeni pod-a dərhal trafik göndərmək**
Pod started, amma PHP-FPM, DB bağlantısı hənüz hazır deyil — ilk sorğular xəta alır. Readiness probe konfiqurasiya edin: probe pass verənə qədər pod-a trafik göndərilməsin; `initialDelaySeconds` ilə başlanğıc zamanı nəzərə alın.

**6. Servis adlarını environment variable ilə yox, sabit string ilə yazmaq**
`$host = "payment-service"` kod içindədir — fərqli mühitlərdə (staging, prod) servis adları dəyişəndə kodu dəyişmək lazımdır. Servis endpoint-lərini environment variable ilə inject edin: `$host = env('PAYMENT_SERVICE_HOST', 'payment-service')`; konfiqurasiya kod-dan ayrı olsun.
