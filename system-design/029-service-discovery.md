# Service Discovery (Middle)

## İcmal

Service discovery - microservices arxitekturasında servisların bir-birini dinamik şəkildə tapması mexanizmidir. Monolitik sistemdə hər şey bir hostname/port-da işləyir, amma mikrosservislərdə yüzlərlə servis fərqli IP və port-larda işləyə bilər və autoscaling zamanı bu dinamik olaraq dəyişir.

Niyə lazımdır?
- **Dynamic IPs** - Container-lar hər restart-da IP dəyişdirir
- **Auto-scaling** - Yeni instance-lar gəlir, köhnələr gedir
- **Multi-region** - Coğrafi olaraq paylanmış servislər
- **Failover** - Fail olan instance traffic-dən çıxarılsın
- **Load balancing** - Healthy instance-lar arasında paylama

Service discovery olmasa, IP-ləri hardcode etməli olarsan - bu production-da qəbul edilməzdir.


## Niyə Vacibdir

Mikroservislər dinamik IP-lərlə işlədiyindən hardcoded endpoint-lər işləmir. Consul, etcd, Kubernetes DNS-based discovery avtomatik service registration və health-aware routing təmin edir. K8s-da bu built-in gəlir, amma mexanizmi bilmək debug üçün vacibdir.

## Əsas Anlayışlar

### 1. Service Registry

Service Registry - bütün servisların siyahısını saxlayan mərkəzi verilənlər bazasıdır. Hər servis:
- **Registration** - start olanda registry-yə qeydiyyat olur
- **Heartbeat** - periodik olaraq "sağam" siqnalı göndərir
- **Deregistration** - dayanarkən qeydiyyatdan çıxır

Registry-də saxlanan məlumat:
- Service name (məs., `user-service`)
- Instance IP və port
- Health status
- Metadata (version, region, tags)

### 2. Client-Side Discovery

Client-side discovery-də client birbaşa registry ilə əlaqə qurur:

```
Client → Registry: "user-service haradadır?"
Registry → Client: "10.0.1.5:8080, 10.0.1.6:8080"
Client → 10.0.1.5:8080: (load balancing edir)
```

Üstünlüklər:
- Client load balancing alqoritmini seçə bilir
- Bir layer az, daha az latency
- Client registry-ni cache edə bilir

Çatışmazlıq:
- Hər dildə client library lazımdır
- Client kompleksity artır
- Client-ə registry haqqında bilmək lazım

Nümunə: Netflix Eureka + Ribbon

### 3. Server-Side Discovery

Server-side discovery-də load balancer/proxy registry ilə əlaqə saxlayır:

```
Client → Load Balancer → Registry lookup → Service
                       (Internal)
```

Üstünlüklər:
- Client sadə qalır (adi HTTP request)
- Dil-müstəqil
- Load balancing mərkəzləşir

Çatışmazlıq:
- Əlavə hop (latency)
- Load balancer SPOF

Nümunə: AWS ELB, Kubernetes Service, Nginx Plus

### 4. DNS-Based Discovery

Ən sadə yanaşma - DNS rekordlarını istifadə et:
- **A record** - domain → IP
- **SRV record** - service → host + port + priority + weight
- **Round-robin DNS** - birdən çox IP qaytarır

Problemləri:
- DNS TTL (cache) real-time dəyişiklikləri görmür
- Health check yoxdur (ölü server-ə də yönləndirir)
- Sadə load balancing

Consul, Kubernetes CoreDNS bu problemləri həll edir (qısa TTL + health check).

### 5. Health Checking

Health check - servisin işlək olub olmadığını yoxlama. Növləri:
- **Liveness probe** - servis işləyir? (restart lazımdır?)
- **Readiness probe** - traffic qəbul edə bilər?
- **Startup probe** - başlatma tamamlanıb?

Implementation:
- **HTTP check** - `/health` endpoint (200 OK)
- **TCP check** - port açıqdır?
- **gRPC check** - gRPC health protocol
- **Script check** - shell komanda icra et

Health check endpoint nümunəsi:
```json
GET /health
{
  "status": "healthy",
  "checks": {
    "database": "ok",
    "redis": "ok",
    "disk": "ok"
  }
}
```

### 6. Service Mesh

Service mesh - service-to-service əlaqələri idarə edən ayrıca infrastructure layer. Sidecar proxy pattern istifadə edir.

Nümunələr: Istio, Linkerd, Consul Connect

Üstünlüklər:
- Application kodunu dəyişmədən:
  - Service discovery
  - Load balancing
  - Circuit breaking
  - Mutual TLS
  - Observability

## Arxitektura

### Consul Architecture

```
                    ┌─────────────────┐
                    │ Consul Servers  │
                    │  (Raft Cluster) │
                    │  3 or 5 nodes   │
                    └────────┬────────┘
                             │
        ┌────────────────────┼────────────────────┐
        ↓                    ↓                    ↓
  ┌──────────┐         ┌──────────┐         ┌──────────┐
  │Consul    │         │Consul    │         │Consul    │
  │Agent     │         │Agent     │         │Agent     │
  └────┬─────┘         └────┬─────┘         └────┬─────┘
       │                    │                    │
  ┌────────┐          ┌──────────┐         ┌─────────┐
  │Service A│         │Service B │         │Service C│
  └────────┘          └──────────┘         └─────────┘
```

- **Consul Servers** - Raft ilə consensus, state saxlayır
- **Consul Agents** - hər node-da işləyir, local services-i izləyir
- **Gossip protocol** - agent-lər arasında failure detection

### etcd Architecture

etcd - distributed key-value store, Raft consensus istifadə edir. Kubernetes-in backend store-udur.

```
Client → etcd cluster (Raft, 3/5 nodes)
         ├─ Leader (writes)
         └─ Followers (reads)
```

## Nümunələr

### Consul Integration

```php
<?php
// app/Services/ConsulService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ConsulService
{
    private string $consulUrl;

    public function __construct()
    {
        $this->consulUrl = config('services.consul.url', 'http://localhost:8500');
    }

    public function register(string $serviceName, string $host, int $port, array $tags = []): bool
    {
        $serviceId = "{$serviceName}-" . gethostname() . "-{$port}";

        $response = Http::put("{$this->consulUrl}/v1/agent/service/register", [
            'ID' => $serviceId,
            'Name' => $serviceName,
            'Tags' => $tags,
            'Address' => $host,
            'Port' => $port,
            'Check' => [
                'HTTP' => "http://{$host}:{$port}/health",
                'Interval' => '10s',
                'Timeout' => '5s',
                'DeregisterCriticalServiceAfter' => '60s',
            ],
            'Meta' => [
                'version' => config('app.version', '1.0.0'),
                'environment' => app()->environment(),
            ],
        ]);

        return $response->successful();
    }

    public function deregister(string $serviceName, int $port): bool
    {
        $serviceId = "{$serviceName}-" . gethostname() . "-{$port}";
        $response = Http::put("{$this->consulUrl}/v1/agent/service/deregister/{$serviceId}");
        return $response->successful();
    }

    public function discover(string $serviceName, bool $passingOnly = true): array
    {
        $cacheKey = "consul:service:{$serviceName}";

        return Cache::remember($cacheKey, 10, function () use ($serviceName, $passingOnly) {
            $params = ['service' => $serviceName];
            if ($passingOnly) {
                $params['passing'] = 'true';
            }

            $response = Http::get("{$this->consulUrl}/v1/health/service/{$serviceName}", $params);

            if (!$response->successful()) {
                return [];
            }

            return collect($response->json())->map(fn ($item) => [
                'id' => $item['Service']['ID'],
                'name' => $item['Service']['Service'],
                'address' => $item['Service']['Address'] ?: $item['Node']['Address'],
                'port' => $item['Service']['Port'],
                'tags' => $item['Service']['Tags'] ?? [],
                'meta' => $item['Service']['Meta'] ?? [],
            ])->toArray();
        });
    }

    public function getHealthy(string $serviceName): ?array
    {
        $instances = $this->discover($serviceName, true);
        if (empty($instances)) {
            return null;
        }
        // Simple random load balancing
        return $instances[array_rand($instances)];
    }
}
```

### Service Registration on Boot

```php
<?php
// app/Providers/AppServiceProvider.php

namespace App\Providers;

use App\Services\ConsulService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ConsulService::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        // Registration happens in console command on startup
    }
}
```

### Console Command for Registration

```php
<?php
// app/Console/Commands/RegisterService.php

namespace App\Console\Commands;

use App\Services\ConsulService;
use Illuminate\Console\Command;

class RegisterService extends Command
{
    protected $signature = 'service:register
                            {--name= : Service name}
                            {--host= : Host IP}
                            {--port=8000 : Port}';

    protected $description = 'Register service with Consul';

    public function handle(ConsulService $consul): int
    {
        $name = $this->option('name') ?? config('app.name');
        $host = $this->option('host') ?? gethostbyname(gethostname());
        $port = (int) $this->option('port');

        $result = $consul->register($name, $host, $port, [
            'laravel',
            'v' . config('app.version'),
        ]);

        if ($result) {
            $this->info("Service registered: {$name} at {$host}:{$port}");
            return Command::SUCCESS;
        }

        $this->error("Failed to register service");
        return Command::FAILURE;
    }
}
```

### Service-to-Service Communication

```php
<?php
// app/Services/ServiceClient.php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ServiceClient
{
    public function __construct(private ConsulService $consul) {}

    public function call(string $serviceName): PendingRequest
    {
        $instance = $this->consul->getHealthy($serviceName);

        if (!$instance) {
            throw new \RuntimeException("No healthy instances for {$serviceName}");
        }

        $baseUrl = "http://{$instance['address']}:{$instance['port']}";

        return Http::baseUrl($baseUrl)
            ->timeout(10)
            ->retry(3, 100, function ($exception, $request) use ($serviceName) {
                // Retry zamanı yeni instance götür
                $instance = $this->consul->getHealthy($serviceName);
                if ($instance) {
                    $request->baseUrl("http://{$instance['address']}:{$instance['port']}");
                }
                return true;
            });
    }
}
```

### Usage Example

```php
<?php
namespace App\Http\Controllers;

use App\Services\ServiceClient;

class OrderController extends Controller
{
    public function __construct(private ServiceClient $client) {}

    public function createOrder(array $data)
    {
        // User service-ə sorğu
        $user = $this->client
            ->call('user-service')
            ->get("/users/{$data['user_id']}")
            ->json();

        // Inventory service-ə sorğu
        $inventory = $this->client
            ->call('inventory-service')
            ->post('/reservations', [
                'product_id' => $data['product_id'],
                'quantity' => $data['quantity'],
            ])->json();

        // Payment service-ə sorğu
        $payment = $this->client
            ->call('payment-service')
            ->post('/charges', [
                'user_id' => $user['id'],
                'amount' => $inventory['total'],
            ])->json();

        return response()->json([
            'user' => $user,
            'inventory' => $inventory,
            'payment' => $payment,
        ]);
    }
}
```

### Health Check Endpoint

```php
<?php
// app/Http/Controllers/HealthController.php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function check()
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'disk' => $this->checkDisk(),
        ];

        $allOk = !in_array(false, array_column($checks, 'healthy'), true);

        return response()->json([
            'status' => $allOk ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $allOk ? 200 : 503);
    }

    public function ready()
    {
        // Readiness - can accept traffic?
        try {
            DB::connection()->getPdo();
            Redis::ping();
            return response()->json(['ready' => true]);
        } catch (\Exception $e) {
            return response()->json(['ready' => false, 'error' => $e->getMessage()], 503);
        }
    }

    public function live()
    {
        // Liveness - is process alive?
        return response()->json(['alive' => true]);
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            return [
                'healthy' => true,
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Exception $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        try {
            $start = microtime(true);
            Redis::ping();
            return [
                'healthy' => true,
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Exception $e) {
            return ['healthy' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkDisk(): array
    {
        $free = disk_free_space('/');
        $total = disk_total_space('/');
        $usedPercent = (($total - $free) / $total) * 100;
        return [
            'healthy' => $usedPercent < 90,
            'used_percent' => round($usedPercent, 2),
        ];
    }
}
```

### Routes

```php
<?php
// routes/api.php

Route::get('/health', [HealthController::class, 'check']);
Route::get('/ready', [HealthController::class, 'ready']);
Route::get('/live', [HealthController::class, 'live']);
```

## Real-World Nümunələr

- **Netflix Eureka** - Client-side discovery, Java ecosystem
- **HashiCorp Consul** - Multi-datacenter, service mesh features
- **etcd** - Kubernetes backend, Raft-based KV store
- **Apache Zookeeper** - Classical, Kafka-da istifadə olunur
- **Kubernetes Service** - DNS + Endpoints, native discovery
- **AWS Cloud Map** - Managed service discovery
- **Istio / Linkerd** - Service mesh-də built-in discovery
- **NATS** - Subject-based discovery

## Praktik Tapşırıqlar

**Q1: Client-side və server-side discovery fərqi?**
Client-side - client birbaşa registry-yə sorğu göndərir və özü instance seçir (Netflix Eureka). Server-side - load balancer/gateway registry ilə əlaqə qurur (AWS ELB, Kubernetes). Client-side daha az latency amma hər dildə client library lazım. Server-side sadə client, amma əlavə hop var.

**Q2: Niyə hardcoded IP istifadə edə bilmərik?**
- Container-lar restart-da IP dəyişdirir
- Auto-scaling yeni instance-lar yaradır
- Failover zamanı failed instance-ları bilmirsən
- Multi-region / multi-AZ deployments
- Blue-green deployment-da trafik yönləndirmə
Hardcode yalnız static infrastructure-da mümkündür, cloud-da yox.

**Q3: Consul və Eureka fərqi?**
Consul: Multi-datacenter native, Raft, HTTP+DNS API, KV store, service mesh features, health check geniş.
Eureka: AP system (availability over consistency), Java-centric, Netflix-in yaratdığı, sadə HTTP, daha az feature.
Consul ümumi məqsədli, Eureka Spring Cloud ilə sıx inteqrasiyada.

**Q4: Health check-də liveness və readiness fərqi?**
**Liveness** - process sağdır? Fail olarsa container restart edilir. Misal: deadlock, infinite loop.
**Readiness** - traffic qəbul edə bilər? Fail olarsa traffic-dən çıxarılır, amma restart edilmir. Misal: DB connection hazır deyil, cache warmup gedir.
Liveness çox aqressiv olmamalıdır (unnecessary restarts), readiness dəqiq traffic control üçündür.

**Q5: Niyə Raft istifadə olunur service discovery-də?**
Service registry kritik komponentdir - yanlış məlumat verməsi sistemin fəaliyyətsizləşməsinə səbəb olur. Raft consistency təmin edir:
- Bütün node-lar eyni məlumatı görür
- Network partition-da majority partition işləyir
- Leader election avtomatikdir
Consul, etcd, Zookeeper (ZAB) bunu istifadə edir.

**Q6: DNS-based discovery-nin zəif tərəfləri?**
- **TTL caching** - DNS cache real-time dəyişikliyi gecikdirir
- **Health check yox** - DNS ölü server-i də qaytarır
- **Limited metadata** - sadəcə IP, port yoxdur (SRV record istisna)
- **Load balancing** - yalnız round-robin
Consul DNS interface bu problemləri həll edir - qısa TTL + health check filter.

**Q7: Service mesh nədir və service discovery-dən necə fərqlənir?**
Service mesh - service-to-service communication-ı idarə edən infrastructure layer. Sidecar proxy (Envoy) pattern istifadə edir. Service discovery təmin edir + load balancing, retry, circuit breaking, mTLS, tracing. Discovery service mesh-in bir feature-udur, amma mesh daha genişdir. Istio, Linkerd nümunələrdir.

**Q8: Registration-deregistration yaxşı necə edilir?**
Graceful shutdown pattern-i:
1. Sinyal qəbul et (SIGTERM)
2. Registry-dən deregister et
3. Load balancer yeniləməsinə qədər gözlə (grace period)
4. Mövcud request-ləri tamamla
5. Yeni request-ləri qəbul etmə
6. Connections bağla, shutdown

Beelə yumşaq keçid olur, "connection refused" error yaranmır.

**Q9: Service discovery nə vaxt split-brain ola bilər?**
Network partition-da registry cluster iki hissəyə bölünərsə:
- Minority partition read-only olmalıdır (Raft)
- Majority partition yazılara davam edir
- Split-brain-dan qorunur consensus ilə

Amma service-lər registry-yə çatmasa, cached data istifadə edir. Stale data-ya görə failed instance-a sorğu gedə bilər (retry və circuit breaker lazımdır).

**Q10: Kubernetes service discovery necə işləyir?**
Kubernetes-də:
1. **Service** obyekti yaradılır (stable virtual IP - ClusterIP)
2. **Endpoints/EndpointSlices** - Service-ə aid pod IP-ləri
3. **CoreDNS** - service_name.namespace.svc.cluster.local → ClusterIP
4. **kube-proxy** - iptables/IPVS ilə load balance
5. **Pod** səviyyəsində DNS cache
Pod restart, scale dəyişikliyi avtomatik yansıyır.

## Praktik Baxış

1. **Highly available registry** - Registry SPOF olmamalıdır (3+ nodes)
2. **Implement health checks** - Liveness + readiness
3. **Short health check intervals** - 10-30s tipik
4. **Graceful shutdown** - SIGTERM-də deregister et
5. **Cache with short TTL** - Client-side 10-30s
6. **Use service mesh for complex needs** - Istio, Linkerd
7. **Circuit breaker** - Stale data-dan qorun
8. **Retry with new lookup** - Retry zamanı yeni instance götür
9. **Monitor registration** - Metrics: registered services, health check failures
10. **Secure registry access** - ACL, mTLS
11. **Version your services** - Metadata-da version tag
12. **Separate dev/staging/prod** - Registry namespaces
13. **Document service contracts** - OpenAPI, gRPC proto
14. **Test discovery failures** - Registry unavailable scenarios
15. **Automate registration** - CI/CD-də deploy hook


## Əlaqəli Mövzular

- [Microservices](10-microservices.md) — service discovery-nin əsas istifadə yeri
- [API Gateway](02-api-gateway.md) — discovery ilə dynamic routing
- [Service Mesh](47-service-mesh.md) — discovery + load balancing birlikdə
- [Load Balancing](01-load-balancing.md) — healthy endpoint seçimi
- [Distributed Systems](25-distributed-systems.md) — coordination fundamentalları
