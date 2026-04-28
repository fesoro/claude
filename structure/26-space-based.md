# Space-Based Architecture (Architect)

Space-Based Architecture yüksək həcmli, yüksək concurrency yüklərini processing və storage-i
bir neçə processing unit arasında paylayaraq idarə edir. In-memory data grid istifadə edərək
database-i mərkəzi bottleneck olmaqdan çıxarır.

**Əsas anlayışlar:**
- **Processing Unit** — Business logic, in-memory data və replication olan self-contained vahid
- **Virtualized Middleware** — Data sync, messaging və request routing-i idarə edir
- **Data Grid** — Processing unit-lər arasında paylaşılan distributed in-memory cache
- **Messaging Grid** — Request-ləri mövcud processing unit-lərə yönləndirir
- **Data Pump** — In-memory-dən persistent storage-ə async write-lar
- **Data Reader** — Startup zamanı DB-dən data-nı in-memory grid-ə yükləyir

---

## Spring Boot (Java) — Most common for Space-Based

```
src/main/java/com/example/app/
├── processingunit/                         # Processing Units
│   ├── auction/
│   │   ├── AuctionProcessingUnit.java
│   │   ├── handler/
│   │   │   ├── PlaceBidHandler.java
│   │   │   ├── CreateAuctionHandler.java
│   │   │   └── GetAuctionHandler.java
│   │   ├── service/
│   │   │   ├── BidService.java
│   │   │   └── AuctionService.java
│   │   ├── model/
│   │   │   ├── Auction.java
│   │   │   └── Bid.java
│   │   └── cache/
│   │       └── AuctionCacheConfig.java
│   │
│   ├── order/
│   │   ├── OrderProcessingUnit.java
│   │   ├── handler/
│   │   │   ├── PlaceOrderHandler.java
│   │   │   └── GetOrderHandler.java
│   │   ├── service/
│   │   │   └── OrderService.java
│   │   ├── model/
│   │   │   └── Order.java
│   │   └── cache/
│   │       └── OrderCacheConfig.java
│   │
│   └── session/
│       ├── SessionProcessingUnit.java
│       ├── handler/
│       │   └── SessionHandler.java
│       └── model/
│           └── UserSession.java
│
├── middleware/                             # Virtualized Middleware
│   ├── messaging/
│   │   ├── MessagingGrid.java
│   │   ├── MessageRouter.java
│   │   ├── RequestDispatcher.java
│   │   └── LoadBalancer.java
│   │
│   ├── datagrid/
│   │   ├── DataGrid.java                  # In-memory data grid
│   │   ├── DataGridConfig.java
│   │   ├── ReplicationManager.java
│   │   └── PartitionManager.java
│   │
│   ├── datapump/
│   │   ├── DataPump.java                  # Async DB writer
│   │   ├── AuctionDataPump.java
│   │   ├── OrderDataPump.java
│   │   └── DataPumpConfig.java
│   │
│   └── datareader/
│       ├── DataReader.java                # DB -> cache loader
│       ├── AuctionDataReader.java
│       └── OrderDataReader.java
│
├── persistence/                           # Background persistence
│   ├── repository/
│   │   ├── AuctionRepository.java
│   │   └── OrderRepository.java
│   ├── entity/
│   │   ├── AuctionEntity.java
│   │   └── OrderEntity.java
│   └── migration/
│
├── controller/
│   ├── AuctionController.java
│   └── OrderController.java
│
└── config/
    ├── HazelcastConfig.java               # or Apache Ignite, Redis
    ├── SpaceBasedConfig.java
    └── DeploymentConfig.java
```

---

## Golang

```
project/
├── cmd/
│   ├── processing-unit/
│   │   └── main.go                        # Starts a processing unit
│   └── middleware/
│       └── main.go                        # Starts middleware
│
├── internal/
│   ├── processingunit/
│   │   ├── auction/
│   │   │   ├── unit.go
│   │   │   ├── handler/
│   │   │   │   ├── place_bid.go
│   │   │   │   ├── create_auction.go
│   │   │   │   └── get_auction.go
│   │   │   ├── service/
│   │   │   │   └── auction_service.go
│   │   │   ├── model/
│   │   │   │   ├── auction.go
│   │   │   │   └── bid.go
│   │   │   └── cache/
│   │   │       └── auction_cache.go
│   │   │
│   │   ├── order/
│   │   │   ├── unit.go
│   │   │   ├── handler/
│   │   │   ├── service/
│   │   │   ├── model/
│   │   │   └── cache/
│   │   │
│   │   └── unit.go                        # Processing unit interface
│   │
│   ├── middleware/
│   │   ├── messaging/
│   │   │   ├── grid.go
│   │   │   ├── router.go
│   │   │   ├── dispatcher.go
│   │   │   └── load_balancer.go
│   │   │
│   │   ├── datagrid/
│   │   │   ├── grid.go
│   │   │   ├── replication.go
│   │   │   └── partition.go
│   │   │
│   │   ├── datapump/
│   │   │   ├── pump.go                    # Interface
│   │   │   ├── auction_pump.go
│   │   │   └── order_pump.go
│   │   │
│   │   └── datareader/
│   │       ├── reader.go
│   │       ├── auction_reader.go
│   │       └── order_reader.go
│   │
│   ├── persistence/
│   │   ├── repository/
│   │   │   ├── auction_repo.go
│   │   │   └── order_repo.go
│   │   └── postgres/
│   │       └── connection.go
│   │
│   ├── handler/
│   │   ├── auction_handler.go
│   │   └── order_handler.go
│   │
│   └── config/
│       └── config.go
│
├── pkg/
│   ├── datagrid/
│   │   └── redis_grid.go
│   └── messaging/
│       └── nats_messaging.go
├── go.mod
└── Makefile
```

---

## Hazelcast Configuration (Java)

```java
// HazelcastConfig.java
@Configuration
public class HazelcastConfig {

    @Bean
    public HazelcastInstance hazelcastInstance() {
        Config config = new Config();
        config.setClusterName("auction-cluster");

        // Map config: auction data grid
        MapConfig auctionMapConfig = new MapConfig("auctions");
        auctionMapConfig.setBackupCount(1);           // 1 sync backup replica
        auctionMapConfig.setAsyncBackupCount(1);      // 1 async backup
        auctionMapConfig.setEvictionConfig(
            new EvictionConfig()
                .setEvictionPolicy(EvictionPolicy.LRU)
                .setMaxSizePolicy(MaxSizePolicy.PER_NODE)
                .setSize(10_000)
        );
        // Near cache: local thread-level cache
        NearCacheConfig nearCache = new NearCacheConfig("auctions");
        nearCache.setTimeToLiveSeconds(60);
        auctionMapConfig.setNearCacheConfig(nearCache);
        config.addMapConfig(auctionMapConfig);

        // Network config: member discovery
        NetworkConfig network = config.getNetworkConfig();
        JoinConfig join = network.getJoin();
        join.getMulticastConfig().setEnabled(false);
        join.getKubernetesConfig()
            .setEnabled(true)
            .setProperty("namespace", "auction-system")
            .setProperty("service-name", "hazelcast");

        return Hazelcast.newHazelcastInstance(config);
    }
}
```

---

## Data Partitioning Strategy

```
Partition Key seçimi:
  Auction system: auction_id → partition key
  - Eyni auction-un bütün bid-ləri eyni partition-da
  - Partition = 1 processing unit üzərindədir
  - Race condition yoxdur (single-threaded partition processing)

  E-commerce: user_id → partition key
  - Eyni user-in bütün session data-sı eyni partition-da
  - HTTP request routing: user_id hash → processing unit

Hazelcast partition count (default: 271):
  - Prime number seçilir (271) — better distribution
  - Hər member ~271/N partition alır (N = member sayı)
  - Member əlavə olunanda partitions rebalance olunur

Backup strategy:
  - backupCount=1: primary düşəndə backup avtomatik primary olur
  - asyncBackupCount=1: additional async backup (weaker consistency)
  - Production: backupCount=2 (3 kopya total) → 2 node failure tolerates
```

---

## When to Use Space-Based

```
✓ İstifadə et:
  - Auctions, flash sales (concurrent writes per entity)
  - Online gaming (real-time state per player/room)
  - Trading platforms (order book per instrument)
  - Session-heavy apps (100K+ concurrent sessions)
  - Traffic spikes: sports events, concerts (unpredictable load)

✗ İstifadə etmə:
  - ACID transactions tələb olunan sistemlər
  - Strong consistency critical (financial ledger)
  - Data > memory capacity (hundreds of GB)
  - Operational simplicity prioritetdir

Alternativlər:
  - Yüksək yük amma daha sadə → Horizontal scaling + Redis cache
  - Per-entity concurrency → Actor model (25-reactive-actor-model.md)
  - Global distribution → Cell-based architecture (27-cell-based-architecture.md)
```
