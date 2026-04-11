# Cell-based Architecture & Shared-nothing Architecture

## Mündəricat
1. [Shared-nothing Architecture](#shared-nothing-architecture)
2. [Cell-based Architecture](#cell-based-architecture)
3. [Blast Radius İzolyasiyası](#blast-radius-izolyasiyası)
4. [PHP İmplementasiyası](#php-implementasiyası)
5. [İntervyu Sualları](#intervyu-sualları)

---

## Shared-nothing Architecture

```
Hər node tamamilə müstəqildir:
  Shared memory yoxdur
  Shared disk yoxdur
  Coordination minimum

Traditional (Shared):             Shared-nothing:
  ┌───────────────────┐              ┌──────┐ ┌──────┐ ┌──────┐
  │   Node1  Node2    │              │Node1 │ │Node2 │ │Node3 │
  │      ↓      ↓     │              │own DB│ │own DB│ │own DB│
  │  ┌─────────────┐  │              └──────┘ └──────┘ └──────┘
  │  │  Shared DB  │  │              No shared state!
  │  └─────────────┘  │
  └───────────────────┘

✅ Linear scalability (node əlavə et)
✅ No bottleneck (shared resource yoxdur)
✅ Fault isolation (bir node düşsə digərləri normal)
❌ Consistency harder (distributed state)
❌ Cross-node queries complex

Nümunə: Cassandra, Kafka, Dynamo
```

---

## Cell-based Architecture

```
Sistem "cell"-lərə (hüceyrələrə) bölünür
Hər cell müstəqil, tam-funksiyal bir unit

Cell = {App servers + DB + Cache + Queue}

┌──────────────────────────────────────────────────┐
│                    Router                        │
│              (tenant/region/user-based)          │
└───────────┬──────────────┬───────────────────────┘
            │              │
  ┌─────────▼──────┐  ┌────▼──────────┐
  │    Cell A      │  │    Cell B     │
  │                │  │               │
  │  App1  App2    │  │  App1  App2   │
  │  MySQL Redis   │  │  MySQL Redis  │
  │  Queue Worker  │  │  Queue Worker │
  │                │  │               │
  │  Tenants: 1-50 │  │ Tenants:51-100│
  └────────────────┘  └───────────────┘

Cell A problem yaşayırsa → Cell B toxunulmur!
Blast radius: yalnız Cell A-nın tenant-ları
```

---

## Blast Radius İzolyasiyası

```
Niyə vacibdir:
  1000 tenant, 1 DB:
    Ağır tenant → hamı yavaşlayır
    DB migration → hamı təsirlənir
    Outage → hamı görür

  1000 tenant, 10 cell (100 tenant/cell):
    Ağır tenant → yalnız o cell-dəkilər
    DB migration cell-cell edilir (rolling)
    Outage → yalnız o cell

Şirkət nümunəsi:
  Amazon: Availability Zone-lar (cell)
  Netflix: Region-based cells
  Slack: Sharding groups as cells
```

---

## PHP İmplementasiyası

*PHP İmplementasiyası üçün kod nümunəsi:*
```php
// Cell Router — tenant → cell mapping
class CellRouter
{
    public function getCellForTenant(int $tenantId): CellConfig
    {
        // Cache-dən al
        return Cache::rememberForever("cell:tenant:$tenantId", function () use ($tenantId) {
            $cell = DB::table('tenant_cell_mappings')
                ->where('tenant_id', $tenantId)
                ->first();

            if (!$cell) {
                // Yeni tenant → ən az yüklü cell-ə assign et
                $cell = $this->assignToLeastLoadedCell($tenantId);
            }

            return new CellConfig(
                cellId:          $cell->cell_id,
                dbConnection:    "cell_{$cell->cell_id}_db",
                redisConnection: "cell_{$cell->cell_id}_redis",
                queueConnection: "cell_{$cell->cell_id}_queue",
            );
        });
    }

    private function assignToLeastLoadedCell(int $tenantId): object
    {
        // Tenant sayı ən az olan cell-ə assign
        $cell = DB::table('cells')
            ->leftJoin('tenant_cell_mappings as m', 'm.cell_id', '=', 'cells.id')
            ->groupBy('cells.id')
            ->orderByRaw('COUNT(m.tenant_id) ASC')
            ->first();

        DB::table('tenant_cell_mappings')->insert([
            'tenant_id' => $tenantId,
            'cell_id'   => $cell->id,
        ]);

        return $cell;
    }
}

// Middleware — cell context qur
class CellMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = TenantContext::get();
        $cell   = app(CellRouter::class)->getCellForTenant($tenant->id);

        // Dynamic DB connection
        config(["database.default" => $cell->dbConnection]);
        config(["cache.default"    => $cell->redisConnection]);
        config(["queue.default"    => $cell->queueConnection]);

        return $next($request);
    }
}

// Cell health monitoring
class CellHealthMonitor
{
    public function checkAll(): array
    {
        $cells = DB::table('cells')->get();
        $health = [];

        foreach ($cells as $cell) {
            try {
                $dbOk    = DB::connection("cell_{$cell->id}_db")->selectOne('SELECT 1');
                $redisOk = Redis::connection("cell_{$cell->id}_redis")->ping();
                $health[$cell->id] = ['status' => 'healthy', 'db' => true, 'redis' => true];
            } catch (\Exception $e) {
                $health[$cell->id] = ['status' => 'unhealthy', 'error' => $e->getMessage()];
                // Alert ops
                Log::critical("Cell {$cell->id} unhealthy!", ['error' => $e->getMessage()]);
            }
        }

        return $health;
    }
}
```

---

## İntervyu Sualları

**1. Shared-nothing architecture nədir?**
Hər node öz storage-ına, memory-sinə sahibdir, heç bir node ilə paylaşmır. Coordination minimum. Linear scale: node əlavə et, throughput artır. Cassandra, Kafka bu prinsipdə qurulub. Bottleneck yoxdur çünki shared resource yoxdur.

**2. Cell-based architecture-ın faydası nədir?**
Blast radius məhduddur: bir cell-in problemi yalnız o cell-in tenant-larını təsir edir. Rolling operations: migration, deploy cell-cell edilir. Noisy neighbor isolation: ağır tenant yalnız öz cell-ini yavaşladır. Horizontal scale: yeni cell əlavə et.

**3. Cell-based vs sharding fərqi nədir?**
Sharding: yalnız DB-ni bölür. Cell-based: tam stack bölünür (app + DB + cache + queue). Cell daha geniş izolyasiya verir. Sharding DB bottleneck-i həll edir, cell hər növ bottleneck-i.

**4. Tenant assignment necə edilir?**
Consistent hashing: tenant ID-dən hash → cell. Load-based: az tenant-lı cell-ə assign. Geographic: Europe tenant-ları EU cell-ə. Dedicated: premium tenant-lar öz cell-ini alır. Migration: tenant-ı bir cell-dən digərinə köçürmək (zero-downtime).

**5. Cell-based architecture SaaS multi-tenancy ilə necə əlaqəlidir?**
SaaS multi-tenancy: silo (tam ayrı), pool (paylaşımlı), hybrid. Cell-based hybrid-dir: cell daxilindəki tenant-lar resursları paylaşır, amma cell-lər arası izolyasiya var. Premium tenant-lar dedicated cell (silo), standart tenant-lar shared cell (pool).

**6. Zero-downtime cell migration necə edilir?**
1. Yeni cell-ə dual-write başla (hər yeni yazı həm köhnə, həm yeni cell-ə). 2. Köhnə data-nı yeni cell-ə kopyala (backfill). 3. Verification: iki cell data uyğunluğunu yoxla. 4. Traffic-i yeni cell-ə keçir. 5. Köhnə cell-dən dual-write-ı dayandır. Kafka ya da CDC bu proses üçün istifadə edilə bilər.

---

## Anti-patternlər

**1. Cell-lar arasında shared database istifadə etmək**
Cell-lər ayrı app serverə sahibdir, lakin hamısı eyni DB-yə yazır — shared-nothing prinsipi pozulur, DB bottleneck və SPOF olaraq qalır. Hər cell-in öz ayrı DB instance-ı olsun; cell izolyasiyası yalnız tam stack izolyasiyası ilə mənalıdır.

**2. Noisy neighbor-u izləməmək**
Bir tenant çox resurs istifadə edir, o cell-dəki digər tenant-lar yavaşlayır — problem tenant aşkar edilmir, şikayət gəlincə analiz başlayır. Per-tenant resource usage metric-lərini izləyin: CPU, memory, DB connection, request rate; threshold aşıldıqda tenant avtomatik olaraq dedicated cell-ə keçirilsin.

**3. Cell migration-ı downtime ilə etmək**
Tenant bir cell-dən digərinə köçürülərkən servis dayandırılır — SaaS tətbiqində istifadəçilər etkilənir, SLA pozulur. Zero-downtime migration proseduru hazırlayın: dual-write → data sync → traffic switch → cleanup; migration zamanı hər iki cell-ə yazılsın.

**4. Hər tenant üçün ayrı cell açmaq**
10,000 tenant var, hər birinə ayrı cell — infrastructure xərci qat-qat artır, ops yükü idarəolunmaz olur. Tenant-ları cell-lərə qruplayın: standart tenant-lar shared cell-lərdə (amma izolyasiya var), yalnız premium/enterprise tenant-lar dedicated cell alır.

**5. Cell routing-i application içinə gömmək**
`if ($tenantId < 1000) { $db = 'cell1' }` kodu hər yerdə — cell topologiyası dəyişdikdə minlərlə yer düzəldilməlidir. Cell routing-i mərkəzləşdirin: API Gateway ya da dedicated Router servis tenant-ı doğru cell-ə yönləndirir, application routing-dən xəbərsiz olur.

**6. Cell-ları müstəqil deploy etməmək**
Bütün cell-lar eyni anda deploy edilir — bir cell-dəki problem bütün sistemi risk altına alır, rolling deployment üstünlüyü itirilir. Hər cell müstəqil deploy edilsin: canary cell-dən başlayın, monitorinq edin, problemsiz olarsa digər cell-lərə davam edin.
