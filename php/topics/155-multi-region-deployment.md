# System Design: Multi-Region Deployment

## Mündəricat
1. [Niyə Multi-Region?](#niyə-multi-region)
2. [Deployment Strategiyaları](#deployment-strategiyaları)
3. [Data Replication Challenges](#data-replication-challenges)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Niyə Multi-Region?

```
Səbəblər:

1. Latency:
   Istanbul user → US server: ~150ms
   Istanbul user → Frankfurt server: ~25ms
   6x daha sürətli!

2. Availability:
   US region down → EU region xidmət göstərir
   99.99% → 99.999%+ mümkün

3. Compliance:
   GDPR: Avropa istifadəçilərinin datası Avropada qalmalıdır
   Data residency tələbləri

4. Disaster Recovery:
   Regionda böyük felaket → digər region devralır

Çətinliklər:
  ✗ Data consistency (replication lag)
  ✗ Split-brain problem
  ✗ Əməliyyat mürəkkəbliyi
  ✗ Xərc (2x+ infrastructure)
```

---

## Deployment Strategiyaları

```
Active-Passive (Hot Standby):
  Primary region: bütün traffic
  Secondary: replicated, amma traffic qəbul etmir
  Failover: manual/auto switch

  Üstünlük: Sadə, consistency problemsiz
  Çatışmazlıq: Secondary resurs israfı, failover gecikmə

Active-Active (Multi-Active):
  Bütün region-lar traffic qəbul edir
  Geographically closest region-a yönləndir

  Üstünlük: Ən aşağı latency, resurs efficient
  Çatışmazlıq: Conflict resolution lazım, mürəkkəb

Active-Active ilə Write Sharding:
  Hər region öz user-lərinin datası üçün master
  EU user → EU DB (master), US DB (replica)
  US user → US DB (master), EU DB (replica)
  
  Conflict: EU user US-dən yazırsa?
  → Sticky session: user həmişə öz bölgəsinə gedir

Read-Your-Writes Consistency:
  User yazdıqdan sonra öz yazısını görməlidir
  Yazı: US region
  Oxu birbaşa US-dən (replication lag olmadan)
```

---

## Data Replication Challenges

```
Replication Lag:
  EU-da yazı → US-ə propagate: 50-200ms
  Bu müddətdə US köhnə data görür
  Eventual consistency

Conflict Resolution:
  Eyni record iki region-da eyni anda yazılır
  → Conflict! Hansı qalır?
  
  Strategiyalar:
  Last-Write-Wins (LWW): timestamp-ə görə son yazı qazanır
    Saat fərqindən risk (clock skew)
  
  CRDT (Conflict-free Replicated Data Types):
    Mathematically merge edilə bilən data strukturları
    Counter, Set, Register — konfliktsiz birləşir
  
  Custom merge function:
    Application-specific logic
    "Inventory: min(eu_stock, us_stock)" deyil, true stock bilmək lazımdır

Split-Brain:
  Network partition → region-lar bir-birini görmür
  Hər region independent yazır
  Network bərpa olunanda: conflict
  
  CAP Theorem seçimi:
  CP (Consistency over Partition): yazmaları blokla
  AP (Availability over Partition): konfliktlərə hazır ol, sonra merge et

Global Tables (AWS DynamoDB Global Tables):
  Multi-master, LWW conflict resolution
  Built-in replication
```

---

## PHP İmplementasiyası

```php
<?php
// 1. Region-aware database connection
namespace App\Infrastructure\Database;

class MultiRegionConnection
{
    private array $connections = [];

    public function __construct(
        private array  $regionConfigs,
        private string $currentRegion,
    ) {}

    public function getWriteConnection(): \PDO
    {
        // Həmişə current region master-ına yaz
        return $this->getConnection($this->currentRegion, 'master');
    }

    public function getReadConnection(bool $strongConsistency = false): \PDO
    {
        if ($strongConsistency) {
            // Read-your-writes: master-dan oxu
            return $this->getConnection($this->currentRegion, 'master');
        }

        // Ən yaxın region-dan oxu (replica ok)
        return $this->getConnection($this->currentRegion, 'replica');
    }

    private function getConnection(string $region, string $role): \PDO
    {
        $key = "{$region}:{$role}";

        if (!isset($this->connections[$key])) {
            $config = $this->regionConfigs[$region][$role];
            $this->connections[$key] = new \PDO(
                "mysql:host={$config['host']};dbname={$config['dbname']}",
                $config['user'],
                $config['password'],
            );
        }

        return $this->connections[$key];
    }
}
```

```php
<?php
// 2. Read-Your-Writes Consistency (Session-based)
class ReadYourWritesMiddleware
{
    private const STICKY_READ_TTL = 10; // saniyə

    public function process(Request $request, Handler $handler): Response
    {
        $userId = $request->getAttribute('user_id');

        // Son yazıdan sonra master-da ox
        if ($userId && $this->hasRecentWrite($userId)) {
            $request = $request->withAttribute('strong_consistency', true);
        }

        $response = $handler->handle($request);

        // Yazı əməliyyatı oldusa qeyd et
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $this->markRecentWrite($userId);
        }

        return $response;
    }

    private function hasRecentWrite(string $userId): bool
    {
        return (bool) $this->redis->get("recent_write:{$userId}");
    }

    private function markRecentWrite(string $userId): void
    {
        $this->redis->setex("recent_write:{$userId}", self::STICKY_READ_TTL, '1');
    }
}
```

```php
<?php
// 3. Geo-routing (DNS / Load Balancer level config-u PHP ilə idarə)
class GeoRouter
{
    private array $regionMap = [
        'EU' => ['DE', 'FR', 'NL', 'TR', 'GB', 'IT', 'ES'],
        'US' => ['US', 'CA', 'MX', 'BR'],
        'AP' => ['JP', 'KR', 'AU', 'SG', 'IN'],
    ];

    public function getRegion(string $countryCode): string
    {
        foreach ($this->regionMap as $region => $countries) {
            if (in_array($countryCode, $countries)) {
                return $region;
            }
        }
        return 'US'; // Default
    }

    public function getEndpoint(string $region): string
    {
        return match ($region) {
            'EU' => 'https://eu.api.example.com',
            'AP' => 'https://ap.api.example.com',
            default => 'https://us.api.example.com',
        };
    }
}
```

```php
<?php
// 4. Health Check + Failover
class RegionHealthChecker
{
    private array $regions = [
        'eu-west-1'    => 'https://eu.api.example.com/health',
        'us-east-1'    => 'https://us.api.example.com/health',
        'ap-south-1'   => 'https://ap.api.example.com/health',
    ];

    public function getHealthyRegion(string $preferred): string
    {
        if ($this->isHealthy($preferred)) {
            return $preferred;
        }

        // Failover to next healthy region
        foreach ($this->regions as $region => $endpoint) {
            if ($region !== $preferred && $this->isHealthy($region)) {
                $this->logger->warning("Failover: {$preferred} → {$region}");
                return $region;
            }
        }

        throw new AllRegionsDownException("Bütün region-lar sağlıklı deyil");
    }

    private function isHealthy(string $region): bool
    {
        $cacheKey = "health:{$region}";
        $cached   = $this->cache->get($cacheKey);

        if ($cached !== null) {
            return $cached === 'healthy';
        }

        try {
            $response = $this->http->get($this->regions[$region], ['timeout' => 2]);
            $healthy  = $response->getStatusCode() === 200;
        } catch (\Throwable) {
            $healthy = false;
        }

        $this->cache->set($cacheKey, $healthy ? 'healthy' : 'unhealthy', 30);
        return $healthy;
    }
}
```

---

## İntervyu Sualları

- Active-Active ilə Active-Passive fərqi nədir?
- Replication lag nədir? Read-your-writes consistency necə tətbiq edilir?
- Split-brain problemi nədir? Necə həll edilir?
- GDPR multi-region dizaynına necə təsir edir?
- Conflict resolution strategiyaları hansılardır?
- Region failover zamanı DNS TTL-in əhəmiyyəti nədir?
