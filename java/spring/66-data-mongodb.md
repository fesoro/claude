# 66 — Spring Data MongoDB — Geniş İzah

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [MongoDB nədir?](#mongodb-nədir)
2. [Spring Data MongoDB konfiqurasiyası](#spring-data-mongodb-konfiqurasiyası)
3. [Document mapping](#document-mapping)
4. [MongoRepository](#mongorepository)
5. [MongoTemplate](#mongotemplate)
6. [Aggregation Pipeline](#aggregation-pipeline)
7. [İntervyu Sualları](#intervyu-sualları)

---

## MongoDB nədir?

**MongoDB** — document-oriented NoSQL database. JSON-benzəri BSON (Binary JSON) formatında saxlayır.

```
PostgreSQL (relational) vs MongoDB (document):

PostgreSQL:
  Table: orders
  Columns: id, customer_id, status, total_amount
  Separate table: order_items (order_id foreign key)
  JOIN lazımdır

MongoDB:
  Collection: orders
  Document: {
    _id: ObjectId("..."),
    customerId: "c1",
    status: "PENDING",
    totalAmount: 149.99,
    items: [         ← embeded, JOIN yoxdur
      {productId: "p1", qty: 2, price: 49.99},
      {productId: "p2", qty: 1, price: 50.01}
    ],
    shippingAddress: {city: "Bakı", street: "Nizami 10"}
  }

MongoDB istifadə sahələri:
  → Flexible schema (müxtəlif struktur)
  → Embedded documents (JOIN-free reads)
  → Horizontal scaling (sharding)
  → High write throughput
  → Document-oriented data (product catalog, user profile)
```

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-mongodb</artifactId>
</dependency>
```

---

## Spring Data MongoDB konfiqurasiyası

```yaml
# application.yml
spring:
  data:
    mongodb:
      uri: mongodb://localhost:27017/orderdb
      # Authentication ilə:
      # uri: mongodb://user:pass@localhost:27017/orderdb?authSource=admin

      # Replica Set:
      # uri: mongodb://node1:27017,node2:27017,node3:27017/orderdb?replicaSet=rs0

      # Atlas (cloud):
      # uri: mongodb+srv://user:pass@cluster.mongodb.net/orderdb

      # Ayrı-ayrı parametrlər
      host: localhost
      port: 27017
      database: orderdb
      username: admin
      password: secret
      authentication-database: admin

      auto-index-creation: true  # Annotation-lar ilə indeks yaradılsın
```

```java
// ─── Proqramatik konfiqurasiya ────────────────────────
@Configuration
@EnableMongoRepositories
public class MongoConfig extends AbstractMongoClientConfiguration {

    @Override
    protected String getDatabaseName() {
        return "orderdb";
    }

    @Override
    public MongoClient mongoClient() {
        ConnectionString connectionString =
            new ConnectionString("mongodb://localhost:27017/orderdb");

        MongoClientSettings settings = MongoClientSettings.builder()
            .applyConnectionString(connectionString)
            .applyToConnectionPoolSettings(pool ->
                pool.maxSize(20).minSize(5))
            .applyToSocketSettings(socket ->
                socket.connectTimeout(5, TimeUnit.SECONDS)
                      .readTimeout(30, TimeUnit.SECONDS))
            .build();

        return MongoClients.create(settings);
    }
}
```

---

## Document mapping

```java
// ─── @Document ────────────────────────────────────────
@Document(collection = "orders")
public class OrderDocument {

    @Id
    private String id; // MongoDB ObjectId-yə çevrilir

    @Field("customer_id")  // Collection-da fərqli ad
    private String customerId;

    private OrderStatus status;
    private BigDecimal totalAmount;

    private List<OrderItemDocument> items; // Embedded documents

    private ShippingAddressDocument shippingAddress;

    @CreatedDate
    private Instant createdAt;

    @LastModifiedDate
    private Instant updatedAt;

    @Version
    private Long version; // Optimistic locking

    // Custom nested document
    private Map<String, Object> metadata;
}

// ─── Embedded document ────────────────────────────────
public class OrderItemDocument {
    // @Document deyil! Embedded — ayrı collection-da saxlanmır

    private String productId;
    private String productName;
    private Integer quantity;
    private BigDecimal unitPrice;
    private BigDecimal totalPrice;
}

public class ShippingAddressDocument {
    private String country;
    private String city;
    private String street;
    private String postalCode;
    private GeoJsonPoint location; // Geo data
}

// ─── İndekslər ────────────────────────────────────────
@Document(collection = "orders")
@CompoundIndex(name = "customer_status_idx",
               def = "{'customerId': 1, 'status': 1}")
public class OrderDocument {

    @Id
    private String id;

    @Indexed
    private String customerId;

    @Indexed(expireAfterSeconds = 86400) // TTL indeks — 1 gün sonra silinir
    private Instant expiresAt;

    @GeoSpatialIndexed(type = GeoSpatialIndexType.GEO_2DSPHERE)
    private GeoJsonPoint location; // Geo indeks

    // Text indeks — full-text search
    @TextIndexed
    private String description;
}
```

---

## MongoRepository

```java
// ─── Repository interface ─────────────────────────────
@Repository
public interface OrderRepository
        extends MongoRepository<OrderDocument, String> {

    // Derived queries
    List<OrderDocument> findByCustomerId(String customerId);
    List<OrderDocument> findByStatus(OrderStatus status);

    Optional<OrderDocument> findByIdAndCustomerId(String id, String customerId);

    List<OrderDocument> findByStatusAndTotalAmountGreaterThan(
        OrderStatus status, BigDecimal amount);

    // @Query ilə custom sorğu
    @Query("{'customerId': ?0, 'status': {$in: ?1}}")
    List<OrderDocument> findByCustomerAndStatuses(
        String customerId, List<OrderStatus> statuses);

    // Projection
    @Query(value = "{'status': ?0}", fields = "{'customerId': 1, 'totalAmount': 1}")
    List<OrderDocument> findSummaryByStatus(OrderStatus status);

    // Count
    long countByStatus(OrderStatus status);

    // Exists
    boolean existsByCustomerIdAndStatus(String customerId, OrderStatus status);

    // Delete
    void deleteByStatusAndCreatedAtBefore(OrderStatus status, Instant before);

    // Pagination
    Page<OrderDocument> findByCustomerId(String customerId, Pageable pageable);

    // Sorting
    List<OrderDocument> findByStatusOrderByCreatedAtDesc(OrderStatus status);
}
```

---

## MongoTemplate

```java
@Service
public class OrderQueryService {

    private final MongoTemplate mongoTemplate;

    // ─── Sadə sorğular ───────────────────────────────
    public OrderDocument findById(String id) {
        return mongoTemplate.findById(id, OrderDocument.class);
    }

    public List<OrderDocument> findByStatus(OrderStatus status) {
        Query query = new Query(Criteria.where("status").is(status));
        return mongoTemplate.find(query, OrderDocument.class);
    }

    // ─── Mürəkkəb Criteria ───────────────────────────
    public List<OrderDocument> findOrders(OrderFilter filter) {
        Criteria criteria = new Criteria();

        if (filter.customerId() != null) {
            criteria.and("customerId").is(filter.customerId());
        }

        if (filter.status() != null) {
            criteria.and("status").is(filter.status());
        }

        if (filter.minAmount() != null) {
            criteria.and("totalAmount").gte(filter.minAmount());
        }

        if (filter.maxAmount() != null) {
            criteria.and("totalAmount").lte(filter.maxAmount());
        }

        if (filter.from() != null && filter.to() != null) {
            criteria.and("createdAt").gte(filter.from()).lte(filter.to());
        }

        Query query = new Query(criteria)
            .with(Sort.by(Sort.Direction.DESC, "createdAt"))
            .limit(filter.limit())
            .skip(filter.offset());

        return mongoTemplate.find(query, OrderDocument.class);
    }

    // ─── Regex sorğu ──────────────────────────────────
    public List<OrderDocument> searchByNote(String keyword) {
        Query query = new Query(
            Criteria.where("note").regex(keyword, "i") // case-insensitive
        );
        return mongoTemplate.find(query, OrderDocument.class);
    }

    // ─── In sorğusu ───────────────────────────────────
    public List<OrderDocument> findByStatuses(List<OrderStatus> statuses) {
        Query query = new Query(
            Criteria.where("status").in(statuses)
        );
        return mongoTemplate.find(query, OrderDocument.class);
    }

    // ─── Embedded document sorğusu ────────────────────
    public List<OrderDocument> findByProductId(String productId) {
        Query query = new Query(
            Criteria.where("items.productId").is(productId)
        );
        return mongoTemplate.find(query, OrderDocument.class);
    }

    // ─── Update ───────────────────────────────────────
    public void updateStatus(String orderId, OrderStatus newStatus) {
        Query query = new Query(Criteria.where("_id").is(orderId));
        Update update = new Update()
            .set("status", newStatus)
            .set("updatedAt", Instant.now());

        mongoTemplate.updateFirst(query, update, OrderDocument.class);
    }

    // Array-ə element əlavə
    public void addItem(String orderId, OrderItemDocument item) {
        Query query = new Query(Criteria.where("_id").is(orderId));
        Update update = new Update()
            .push("items", item)
            .inc("totalAmount", item.getTotalPrice().doubleValue());

        mongoTemplate.updateFirst(query, update, OrderDocument.class);
    }

    // ─── Upsert ───────────────────────────────────────
    public void upsertOrderCounter(String customerId) {
        Query query = new Query(Criteria.where("customerId").is(customerId));
        Update update = new Update().inc("orderCount", 1);
        mongoTemplate.upsert(query, update, "customer_stats");
    }

    // ─── Delete ───────────────────────────────────────
    public void deleteOldCancelledOrders(Instant before) {
        Query query = new Query(
            Criteria.where("status").is(OrderStatus.CANCELLED)
                    .and("createdAt").lt(before)
        );
        DeleteResult result = mongoTemplate.remove(query, OrderDocument.class);
        log.info("Silindi: {} sifariş", result.getDeletedCount());
    }
}
```

---

## Aggregation Pipeline

```java
@Service
public class OrderAnalyticsService {

    private final MongoTemplate mongoTemplate;

    // ─── Sadə aggregation ─────────────────────────────
    public Map<String, Long> countByStatus() {
        Aggregation aggregation = Aggregation.newAggregation(
            Aggregation.group("status").count().as("count"),
            Aggregation.project("count").and("status").previousOperation()
        );

        AggregationResults<Document> results = mongoTemplate.aggregate(
            aggregation, "orders", Document.class);

        return results.getMappedResults().stream()
            .collect(Collectors.toMap(
                doc -> doc.getString("status"),
                doc -> doc.getLong("count")
            ));
    }

    // ─── Revenue by customer ──────────────────────────
    public List<CustomerRevenue> getTopCustomers(int limit) {
        Aggregation aggregation = Aggregation.newAggregation(
            Aggregation.match(
                Criteria.where("status").in(
                    OrderStatus.CONFIRMED, OrderStatus.DELIVERED)),
            Aggregation.group("customerId")
                .count().as("orderCount")
                .sum("totalAmount").as("totalRevenue"),
            Aggregation.sort(Sort.by(Sort.Direction.DESC, "totalRevenue")),
            Aggregation.limit(limit),
            Aggregation.project("orderCount", "totalRevenue")
                .and("customerId").previousOperation()
        );

        AggregationResults<CustomerRevenue> results =
            mongoTemplate.aggregate(aggregation, "orders", CustomerRevenue.class);

        return results.getMappedResults();
    }

    // ─── Date-based aggregation ───────────────────────
    public List<DailyStats> getDailyStats(LocalDate from, LocalDate to) {
        Aggregation aggregation = Aggregation.newAggregation(
            Aggregation.match(
                Criteria.where("createdAt")
                    .gte(from.atStartOfDay().toInstant(ZoneOffset.UTC))
                    .lte(to.plusDays(1).atStartOfDay().toInstant(ZoneOffset.UTC))
            ),
            Aggregation.project()
                .andExpression("dateToString('%Y-%m-%d', createdAt)").as("date")
                .andInclude("totalAmount", "status"),
            Aggregation.group("date")
                .count().as("orderCount")
                .sum("totalAmount").as("revenue"),
            Aggregation.sort(Sort.by("_id"))
        );

        return mongoTemplate.aggregate(aggregation, "orders", DailyStats.class)
            .getMappedResults();
    }

    // ─── Unwind — array elementlərini açmaq ───────────
    public List<ProductStats> getProductStats() {
        Aggregation aggregation = Aggregation.newAggregation(
            Aggregation.unwind("items"), // Hər item ayrı document
            Aggregation.group("items.productId")
                .count().as("orderCount")
                .sum("items.quantity").as("totalQuantity")
                .sum("items.totalPrice").as("totalRevenue"),
            Aggregation.sort(Sort.by(Sort.Direction.DESC, "totalRevenue"))
        );

        return mongoTemplate.aggregate(aggregation, "orders", ProductStats.class)
            .getMappedResults();
    }

    // ─── Lookup (JOIN-like) ───────────────────────────
    public List<Document> getOrdersWithCustomerInfo() {
        Aggregation aggregation = Aggregation.newAggregation(
            Aggregation.lookup(
                "customers",       // from collection
                "customerId",      // local field
                "_id",             // foreign field
                "customerInfo"     // as
            ),
            Aggregation.unwind("customerInfo"),
            Aggregation.project("status", "totalAmount")
                .and("customerInfo.fullName").as("customerName")
                .and("customerInfo.email").as("customerEmail")
        );

        return mongoTemplate.aggregate(aggregation, "orders", Document.class)
            .getMappedResults();
    }
}

record CustomerRevenue(String customerId, long orderCount, BigDecimal totalRevenue) {}
record DailyStats(String date, long orderCount, BigDecimal revenue) {}
record ProductStats(String productId, long orderCount, long totalQuantity, BigDecimal totalRevenue) {}
```

---

## İntervyu Sualları

### 1. MongoDB-nin relational DB-dən əsas fərqi?
**Cavab:** Schema-less — hər document fərqli field-lərə malik ola bilər. JOIN əvəzinə embedded documents — bir document içərisində əlaqəli data. Horizontal scaling (sharding) — data çoxlu server arasında bölünür. ACID transaction-lar köklü deyil (MongoDB 4.0-dan multi-document ACID). Güclü tərəflər: flexible schema, high write throughput, embedded data.

### 2. Embedded vs Referenced document?
**Cavab:** **Embedded** — bir document içərisində digər document. Pros: JOIN yoxdur, bir sorğu ilə oxunur, atomik update. Cons: document ölçüsü (16MB limit), duplicate data. **Referenced** — `customerId` field ilə başqa collection-da ayrı document. Pros: normalizasiya, ölçü məhdudiyyəti yoxdur. Cons: application tərəfindən JOIN (lookup). Tez-tez birlikdə oxunan → embed; az dəyişən böyük data → reference.

### 3. MongoTemplate vs MongoRepository?
**Cavab:** `MongoRepository` — Spring Data repository abstraction; derived query method-lar (`findByCustomerId`), `@Query` annotation; sadə CRUD üçün. `MongoTemplate` — düz MongoDB API; mürəkkəb `Criteria`, aggregation pipeline, bulk operations, update operators (`$push`, `$inc`); tam nəzarət. Repository daha az kod; MongoTemplate daha güclü.

### 4. Aggregation Pipeline nədir?
**Cavab:** MongoDB-nin data processing mechanism-i. Stages ardıcıl tətbiq edilir: `$match` (filter), `$group` (qrupla), `$sort`, `$limit`, `$project` (field seç), `$lookup` (JOIN), `$unwind` (array aç). SQL GROUP BY + JOIN + WHERE kombinasiyasına ekvivalent. Spring Data `Aggregation.newAggregation(stages...)` ilə istifadə edilir.

### 5. MongoDB-də transaction dəstəyi?
**Cavab:** MongoDB 4.0-dan **replica set** üzərindəki multi-document ACID transaction-lar. MongoDB 4.2-dən sharded cluster-da da. `@Transactional` Spring annotation-u MongoTransactionManager ilə işləyir. Lakin embedded document-lar bir document-də atomik — tək document update həmişə atomikdir (transaction lazım deyil). Multi-collection update lazım olduqda transaction istifadə edin.

*Son yenilənmə: 2026-04-10*
