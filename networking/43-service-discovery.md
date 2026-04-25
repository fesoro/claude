# Service Discovery (Senior)

## İcmal

Microservices mühitində servislərin bir-birini dinamik tapma prosesidir. Monolith-dən fərqli olaraq, microservices-də service instance-ların IP-ləri sabit deyil — scaling, restart, deployment zamanı dəyişir. Service discovery bu problemi həll edir.

## Niyə Vacibdir

- 10 instance-lı servis deploy olunanda, digər servislər yeni IP-ləri hardan bilir?
- Instance crash edəndə, traffic avtomatik başqasına necə keçir?
- Load balancer-in backend list-i necə aktual qalır?
- Hardcode IP → infra dəyişəndə deployment lazımdır (antipattern)

## Əsas Anlayışlar

### Discovery Patterns

**Client-side discovery:**
```
Client → Service Registry → IP list al → Load balance et → Servisə çat
         (Eureka, Consul)

+ Client özü seçim edir (smart load balancing mümkün)
- Hər client discovery logic-i implement etməlidir
- Eureka (Netflix/Spring), Consul bu modeli dəstəkləyir
```

**Server-side discovery:**
```
Client → Load Balancer/API Gateway → Registry-dən soral → Routing et
         (Nginx, AWS ALB, Envoy)

+ Client sadədir, yalnız bir endpoint bilir
- Əlavə hop (latency)
- AWS ALB + ECS, Kubernetes Service bu modeli istifadə edir
```

### Registration Patterns

**Self-registration:** Servis özü registry-ə yazır
```
App start → consul.register(name="payment-svc", ip=myIP, port=8080)
App stop  → consul.deregister(id=myId)
Crash     → TTL keçəndən sonra avtomatik silinir (health check)
```

**Third-party registration:** Orchestrator yazır
```
Kubernetes: Pod start → kubelet → etcd-yə yaz
Docker: Container start → docker daemon → Consul-a yaz (registrator)
Avantaj: Servis özü registry haqqında bilmir (decoupled)
```

### Health Checks

```
HTTP health check:
  Consul → GET /health → 200 OK → Healthy
  Consul → GET /health → timeout/5xx → Unhealthy → Deregister

TCP health check:
  Consul → TCP connect port 8080 → success → Healthy

TTL-based:
  Servis hər 10s-də consul.pass(checkId) çağırır
  30s çağırılmasa → Unhealthy
```

### DNS-based Service Discovery

```
Kubernetes DNS:
  payment-service.default.svc.cluster.local → ClusterIP

  <service>.<namespace>.svc.<cluster-domain>

  Internal: payment-svc (eyni namespace)
  Cross-ns: payment-svc.payments
  Full:      payment-svc.payments.svc.cluster.local

Consul DNS:
  payment.service.consul → Healthy instance-ların IP-si (round-robin)
  _payment._tcp.service.consul → SRV record (IP + port)
```

## Praktik Baxış

### Kubernetes Service Discovery (Ən Geniş Yayılmış)

```yaml
# Service definition
apiVersion: v1
kind: Service
metadata:
  name: payment-svc
  namespace: payments
spec:
  selector:
    app: payment           # Bu label-lı Pod-ları tap
  ports:
    - port: 80
      targetPort: 8080
  type: ClusterIP          # Internal only

# PHP app-dan istifadə:
# http://payment-svc (eyni namespace)
# http://payment-svc.payments (başqa namespace)
```

```php
// Laravel .env
PAYMENT_SERVICE_URL=http://payment-svc/api

// K8s-in kube-dns-i avtomatik resolve edir
// Container restart olsa da, service IP sabit qalır
// kube-proxy endpoint-ləri aktual saxlayır
```

### Consul Service Discovery

```php
// PHP Consul client
$consul = new SensioLabs\Consul\ServiceFactory()->get('health');

// Healthy instance-ları al
$services = $consul->service('payment-svc', ['passing' => true]);

foreach ($services->json() as $service) {
    $ip   = $service['Service']['Address'];
    $port = $service['Service']['Port'];
    // → HTTP call et
}
```

### Laravel + Service Discovery Pattern

```php
// ServiceDiscovery wrapper (production-da cache ilə)
class ServiceDiscovery
{
    public function resolve(string $serviceName): string
    {
        return Cache::remember(
            "service:{$serviceName}",
            seconds: 10,  // 10s cache — stale risk vs performance trade-off
            callback: fn() => $this->fetchFromConsul($serviceName)
        );
    }

    private function fetchFromConsul(string $service): string
    {
        // Consul API çağırışı
        $response = Http::get("http://consul:8500/v1/health/service/{$service}", [
            'passing' => true
        ]);

        $instances = $response->json();
        // Random instance seç (client-side load balancing)
        $instance = $instances[array_rand($instances)];

        return sprintf(
            'http://%s:%d',
            $instance['Service']['Address'],
            $instance['Service']['Port']
        );
    }
}
```

### Trade-offs

```
DNS-based (K8s Service):
  + Sadə, heç bir client library lazım deyil
  + Caching natural (DNS TTL)
  - DNS caching stale instance-a yönlədə bilər
  - Port discovery əlavə mexanizm lazımdır

Consul/etcd direct:
  + Real-time health status
  + Rich metadata (tags, meta)
  - Client library lazımdır
  - Əlavə dependency

Service Mesh (Istio/Linkerd):
  + Application-transparent (sidecar proxy)
  + mTLS, circuit breaking, metrics pulsuz
  - Komplekslik artır
  - Resource overhead (hər Pod-da sidecar)
```

### Common Mistakes

```
❌ Service discovery result-ı uzun müddət cache etmək
   → Unhealthy instance-a traffic gedə bilər

❌ Health check endpoint-ini da rate limit-ə salmaq
   → Consul/K8s servisi unhealthy hesab edər

❌ Health check-in DB-ni test etməsi
   → DB problemini bütün instance-lar görür → hamısı unhealthy → cascade

❌ Service adını hardcode etmək
   → ENV var istifadə et: PAYMENT_SERVICE=payment-svc

❌ Deregistration-ı unudmaq (graceful shutdown)
   → In-flight request-lər kəsilər
```

## Nümunələr

### Kubernetes Readiness vs Liveness

```yaml
livenessProbe:    # Fail → Container restart
  httpGet:
    path: /health/live   # Sadə: process alive?
    port: 8080
  initialDelaySeconds: 10
  periodSeconds: 10

readinessProbe:   # Fail → Service-dən çıxar (traffic göndərmə)
  httpGet:
    path: /health/ready  # DB bağlantısı var? Cache warm?
    port: 8080
  initialDelaySeconds: 5
  periodSeconds: 5
```

```php
// Laravel health endpoints
Route::get('/health/live', fn() => response()->json(['status' => 'ok']));

Route::get('/health/ready', function () {
    // DB yoxla
    DB::connection()->getPdo();
    // Redis yoxla
    Redis::ping();
    return response()->json(['status' => 'ready']);
});
```

### Graceful Shutdown

```php
// Laravel: SIGTERM gəldikdə cari request-i bitir, yenisini qəbul etmə
// Kubernetes terminationGracePeriodSeconds: 30 (default)

// artisan command
pcntl_signal(SIGTERM, function () {
    Log::info('SIGTERM received, graceful shutdown...');
    // Queue worker: --stop-when-empty ilə başlat
});
```

## Praktik Tapşırıqlar

1. **K8s DNS test:** Pod içindən `nslookup kubernetes.default` — nə qaytar?
2. **Service endpoint:** `kubectl get endpoints payment-svc` — hansı Pod-lar var?
3. **Health check simulate:** Bir Pod-un /health-ini 5xx qaytar, Kubernetes nə edir?
4. **Consul UI:** Local-da Consul çalışdır, servis register et, health check fail et

## Əlaqəli Mövzular

- [DNS - Domain Name System](07-dns.md)
- [Load Balancing](18-load-balancing.md)
- [API Gateway](21-api-gateway.md)
- [IP Addressing for Backend Developers](41-ip-addressing.md)
- [mTLS Deep Dive](35-mtls-deep-dive.md)
