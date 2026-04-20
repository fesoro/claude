# Space-Based Architecture

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
