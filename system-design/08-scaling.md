# Scaling

## Nədir? (What is it?)

Scaling sistemin artan yükü idarə etmək qabiliyyətidir. İstifadəçi sayı artdıqca,
daha çox trafik, daha çox data, daha çox hesablama lazım olur. İki əsas yanaşma var:
vertical (yuxarı) və horizontal (yanlara) scaling.

```
Vertical Scaling:          Horizontal Scaling:
  [Big Server]               [S1] [S2] [S3] [S4]
  16 CPU, 128GB RAM          4 CPU each, 16GB each
  (daha güclü maşın)         (daha çox maşın)
```

## Əsas Konseptlər (Key Concepts)

### Vertical vs Horizontal Scaling

**Vertical Scaling (Scale Up)**
Mövcud serveri daha güclü etmək: daha çox CPU, RAM, SSD.

```
Phase 1: 2 CPU, 4GB RAM   ($50/ay)
Phase 2: 8 CPU, 32GB RAM  ($200/ay)
Phase 3: 32 CPU, 128GB RAM ($800/ay)
Phase 4: ??? (hardware limiti)
```

Üstünlük: Sadə, code dəyişikliyi yoxdur, single point of management
Mənfi: Hardware limiti var, downtime lazım ola bilər, single point of failure, bahalı

**Horizontal Scaling (Scale Out)**
Daha çox server əlavə etmək.

```
Phase 1: 1 server (2 CPU, 4GB)    -> $50/ay
Phase 2: 3 servers                 -> $150/ay
Phase 3: 10 servers                -> $500/ay
Phase 4: 100 servers               -> $5000/ay (linear)
```

Üstünlük: Limitsiz miqyaslanma, fault tolerance, cost-effective
Mənfi: Mürəkkəb arxitektura, distributed system problemləri

### Stateless Design

Horizontal scaling üçün application stateless olmalıdır - heç bir server lokal state
saxlamamalıdır. Hər request istənilən serverə gedə bilər.

```
Stateful (pis):
  Server A-da: $_SESSION['cart'] = [item1, item2]
  Server B-da: $_SESSION['cart'] = []  # user B-yə düşsə, cart boşdur!

Stateless (yaxşı):
  Hər server:  Redis-dən oxuyur -> session['cart'] = [item1, item2]
  İstənilən server eyni cavab verir.
```

State externalize olunmalıdır:
- Session -> Redis/Memcached
- Files -> S3/shared storage
- Cache -> Redis cluster
- Database -> External DB

### Session Management

```
1. Sticky Sessions (session affinity)
   LB həmişə eyni user-i eyni serverə göndərir.
   Mənfi: Bərabər paylanma yoxdur, server düşsə session itirilir.

2. Centralized Session Store
   Redis/Memcached-də session saxlanılır.
   Hər server eyni session-a çata bilər.
   Ən yaxşı yanaşma.

3. Client-side Session (JWT)
   Session data JWT token-də client-də saxlanılır.
   Server-də state yoxdur.
   Mənfi: Token böyük ola bilər, invalidation çətin.
```

### Auto-Scaling

Trafik artanda avtomatik server əlavə et, azalanda sil.

```
Auto-Scaling Rules:
  IF avg_cpu > 70% for 5 min  -> Add 2 instances
  IF avg_cpu < 30% for 10 min -> Remove 1 instance
  IF request_count > 10000/min -> Add 3 instances
  Min instances: 2
  Max instances: 20
  Cooldown period: 5 min (tez-tez scale etməmək üçün)
```

**Scaling Strategies:**
- Target tracking: CPU 60%-dən aşağı saxla
- Step scaling: Threshold-lara görə addım-addım
- Scheduled: Günün vaxtına görə (axşam peak, gecə az)
- Predictive: ML ilə trafiki proqnozla

### Database Scaling

**Read Replicas**
```
Writes -> [Primary DB]
Reads  -> [Replica 1] [Replica 2] [Replica 3]

Write: 1 server
Read:  3 server (3x read capacity)
Replication lag: milliseconds (eventual consistency)
```

**Connection Pooling**
```
100 PHP processes × 1 DB connection = 100 connections (problem!)

Connection pool ilə:
100 PHP processes -> [Pool: 20 connections] -> DB
Reuse existing connections, azaldır overhead
```

**Sharding** (bax: 26-data-partitioning.md)

## Arxitektura (Architecture)

### Scaling Laravel Arxitekturası

```
[CloudFlare CDN] - static assets, DDoS protection
       |
[AWS ALB] - auto-scaling target group
       |
[EC2 Auto-Scaling Group]
  [App 1] [App 2] [App 3] ... [App N]
  Nginx + PHP-FPM on each
       |
  ┌────┴────┐
  |         |
[ElastiCache]  [RDS Aurora]
(Redis cluster) (MySQL, read replicas)
  |
[S3] - file storage
  |
[SQS] -> [Worker Auto-Scaling Group]
          [Worker 1] [Worker 2] ...
```

### Kubernetes Scaling

```yaml
# Horizontal Pod Autoscaler
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: laravel-app
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: laravel-app
  minReplicas: 3
  maxReplicas: 50
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
  - type: Resource
    resource:
      name: memory
      target:
        type: Utilization
        averageUtilization: 80
  behavior:
    scaleUp:
      stabilizationWindowSeconds: 60
      policies:
      - type: Pods
        value: 4
        periodSeconds: 60
    scaleDown:
      stabilizationWindowSeconds: 300
```

## PHP/Laravel ilə Tətbiq (Implementation with PHP/Laravel)

### Stateless Laravel Configuration

```php
// .env - bütün state external
SESSION_DRIVER=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=sqs
FILESYSTEM_DISK=s3
LOG_CHANNEL=stderr  # Container-friendly logging

// config/session.php
'driver' => 'redis',
'connection' => 'session',

// config/cache.php
'default' => 'redis',

// config/filesystems.php
'default' => 's3',
```

### PHP-FPM Tuning for Scaling

```ini
; /etc/php/8.3/fpm/pool.d/www.conf

; Process management
pm = dynamic
pm.max_children = 50        ; Maximum worker processes
pm.start_servers = 10       ; Start with 10 workers
pm.min_spare_servers = 5    ; Minimum idle workers
pm.max_spare_servers = 20   ; Maximum idle workers
pm.max_requests = 500       ; Restart worker after 500 requests (memory leak prevention)

; Timeouts
request_terminate_timeout = 60s
request_slowlog_timeout = 5s
slowlog = /var/log/php-fpm/slow.log

; Status page (health check üçün)
pm.status_path = /fpm-status
ping.path = /fpm-ping
```

### Database Read/Write Splitting

```php
// config/database.php
'mysql' => [
    'read' => [
        'host' => [
            env('DB_READ_HOST_1', '10.0.1.1'),
            env('DB_READ_HOST_2', '10.0.1.2'),
            env('DB_READ_HOST_3', '10.0.1.3'),
        ],
    ],
    'write' => [
        'host' => [env('DB_WRITE_HOST', '10.0.0.1')],
    ],
    'sticky' => true,  // Write-dən sonra eyni request-də read da primary-dən
    'driver' => 'mysql',
    'database' => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
],
```

```php
// Eloquent avtomatik read/write split edir
$users = User::all();              // READ replica-dan
$user = User::create([...]);       // WRITE primary-ə

// Manual seçim
$data = DB::connection('mysql::read')->select('...');
$data = DB::connection('mysql::write')->select('...'); // Replication lag-dan qaçınmaq üçün
```

### Connection Pooling with Octane

```php
// Laravel Octane - persistent workers, connection reuse
// composer require laravel/octane

// config/octane.php
'server' => env('OCTANE_SERVER', 'swoole'), // or roadrunner

'workers' => env('OCTANE_WORKERS', 'auto'), // auto = CPU count
'task_workers' => env('OCTANE_TASK_WORKERS', 'auto'),
'max_requests' => env('OCTANE_MAX_REQUESTS', 500),

// Warm services (connection reuse)
'warm' => [
    \Illuminate\Database\DatabaseManager::class,
    \Illuminate\Cache\CacheManager::class,
],
```

### Queue Worker Auto-Scaling

```php
// config/horizon.php - Laravel Horizon auto-balancing
'environments' => [
    'production' => [
        'supervisor-default' => [
            'connection' => 'redis',
            'queue' => ['default', 'emails', 'notifications'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time', // time or size
            'minProcesses' => 1,
            'maxProcesses' => 20,
            'balanceMaxShift' => 3,     // max 3 process əlavə/çıxar bir dəfəyə
            'balanceCooldown' => 3,     // 3 san gözlə balance arası
            'tries' => 3,
            'timeout' => 60,
            'nice' => 0,
        ],
    ],
],
```

### Caching for Scale

```php
// Aggressive caching pattern
class ProductController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->get('page', 1);
        $cacheKey = "products:list:page:{$page}";

        return Cache::tags(['products'])->remember($cacheKey, 300, function () use ($page) {
            return Product::with('category')
                ->active()
                ->orderBy('created_at', 'desc')
                ->paginate(20, ['*'], 'page', $page);
        });
    }

    public function show(int $id)
    {
        return Cache::remember("product:{$id}", 600, function () use ($id) {
            return Product::with(['category', 'images', 'reviews'])
                ->findOrFail($id);
        });
    }
}
```

## Real-World Nümunələr

**Instagram (PHP/Python):** Django + Cassandra + PostgreSQL + Redis + Memcached.
Horizontal scaling ilə milyardlarla istifadəçi. Database sharding, aggressive caching,
CDN ilə static content. PHP-dən Python-a migration etdilər performance üçün.

**Wikipedia (PHP):** MediaWiki PHP-da yazılıb. Varnish cache + CDN + MySQL replication.
Aggressive caching ilə az server ilə çox trafik handle edir. Database-ə çox az request gedir.

**Slack:** Horizontal scaling. Hər workspace ayrı shard. WebSocket connection-ları
sticky, amma HTTP request-lər stateless. Auto-scaling traffic pattern-ə görə.

**Shopify (Ruby/Rails):** Multi-tenant architecture, tenant-per-shard database.
Pod architecture - hər pod independent cluster. Black Friday üçün pre-scaling.

## Interview Sualları

**S: Vertical vs horizontal scaling fərqi?**
C: Vertical: daha güclü server, sadə amma limitli, single point of failure.
Horizontal: daha çox server, limitless amma complex, fault tolerant. Production-da
hər ikisi istifadə olunur - əvvəl vertical, limit gəldikdə horizontal.

**S: Stateless application nə deməkdir?**
C: Server lokal state saxlamır. Session, cache, files external store-da.
Hər request istənilən serverə gedə bilər, eyni cavab alınar. Horizontal scaling
üçün zəruridir, çünki LB request-ləri istənilən serverə yönləndirə bilər.

**S: 1 milyon concurrent user üçün necə scale edərdiniz?**
C: CDN (static content), Auto-scaling group (app servers), Read replicas (database),
Redis cluster (cache + session), SQS + workers (async processing), Database sharding
(data partitioning). Monitoring ilə bottleneck tapıb optimization.

**S: Database scaling necə edilir?**
C: 1) Query optimization + indexing, 2) Read replicas (read heavy workload),
3) Connection pooling, 4) Caching layer (Redis), 5) Vertical scaling (bigger instance),
6) Sharding (horizontal partitioning), 7) Denormalization, 8) Different DB for different needs.

## Best Practices

1. **Stateless dizayn edin** - İlk gündən state externalize edin
2. **Cache everything** - DB-yə gələn hər request-i azaldın
3. **Async processing** - Yavaş əməliyyatları queue-ya göndərin
4. **Monitor first** - Bottleneck-i tapmazdan optimize etməyin
5. **Database connection limit** - Connection pooling istifadə edin
6. **Graceful shutdown** - Deploy zamanı mövcud request-ləri bitirin
7. **Health checks** - Auto-scaler sağlam instance-ları bilməlidir
8. **Load test** - Production-a qədər scale test edin
9. **Cost optimize** - Reserved instances, spot instances istifadə edin
10. **Start simple** - Premature optimization etməyin, bottleneck olduqda scale edin
