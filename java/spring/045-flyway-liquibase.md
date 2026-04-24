# 045 — Flyway & Liquibase — Geniş İzah
**Səviyyə:** Orta


## Mündəricat
1. [Database Migration nədir?](#database-migration-nədir)
2. [Flyway](#flyway)
3. [Flyway — Spring Boot inteqrasiya](#flyway--spring-boot-inteqrasiya)
4. [Liquibase](#liquibase)
5. [Liquibase — Spring Boot inteqrasiya](#liquibase--spring-boot-inteqrasiya)
6. [Flyway vs Liquibase](#flyway-vs-liquibase)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Database Migration nədir?

**Database Migration** — DB schema dəyişikliklərini versiyalanmış, tətbiq edilə bilən skriptlər kimi idarə etmək.

```
Problem (migration olmadan):
  Developer A: "Mən orders cədvəlinə delivery_date kolonunu əlavə etdim"
  Developer B: "Lokal DB-mdə yoxdur, service crash olur"
  CI/CD: "Test DB-inin sxemi production-dan fərqlidir"

Migration ilə:
  V1__create_orders.sql    → ilk sxem
  V2__add_delivery_date.sql → yeni kolon
  V3__add_index.sql        → performans indeksi
  
  Hər mühitdə (local, staging, production) eyni sıra ilə tətbiq edilir
  Hansı migration tətbiq edilib? → flyway_schema_history cədvəlindən yox, liquibase DATABASECHANGELOG-dan
```

---

## Flyway

```sql
-- ─── Migration fayl adlandırma ────────────────────────
-- V{version}__{description}.sql
-- V1__create_orders_table.sql
-- V2__add_customer_index.sql
-- V2.1__add_delivery_date_column.sql
-- V3__create_order_items_table.sql

-- ─── V1__create_orders_table.sql ─────────────────────
CREATE TABLE customers (
    id          BIGSERIAL PRIMARY KEY,
    full_name   VARCHAR(255) NOT NULL,
    email       VARCHAR(255) NOT NULL UNIQUE,
    phone       VARCHAR(20),
    created_at  TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at  TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE orders (
    id              BIGSERIAL PRIMARY KEY,
    customer_id     BIGINT NOT NULL REFERENCES customers(id),
    status          VARCHAR(50) NOT NULL DEFAULT 'PENDING',
    total_amount    DECIMAL(10, 2) NOT NULL,
    delivery_address TEXT,
    created_at      TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at      TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

CREATE TABLE order_items (
    id          BIGSERIAL PRIMARY KEY,
    order_id    BIGINT NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_id  VARCHAR(255) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    quantity    INTEGER NOT NULL CHECK (quantity > 0),
    unit_price  DECIMAL(10, 2) NOT NULL
);

-- ─── V2__add_indexes.sql ──────────────────────────────
CREATE INDEX idx_orders_customer_id ON orders(customer_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_created_at ON orders(created_at DESC);
CREATE INDEX idx_order_items_order_id ON order_items(order_id);

-- ─── V3__add_delivery_date.sql ────────────────────────
ALTER TABLE orders ADD COLUMN delivery_date DATE;
ALTER TABLE orders ADD COLUMN tracking_number VARCHAR(100);

-- ─── V4__create_outbox_table.sql ─────────────────────
CREATE TABLE outbox_messages (
    id              VARCHAR(36) PRIMARY KEY,
    aggregate_id    VARCHAR(255) NOT NULL,
    aggregate_type  VARCHAR(100) NOT NULL,
    event_type      VARCHAR(100) NOT NULL,
    topic           VARCHAR(255) NOT NULL,
    payload         TEXT NOT NULL,
    status          VARCHAR(20) NOT NULL DEFAULT 'PENDING',
    retry_count     INTEGER NOT NULL DEFAULT 0,
    error_message   TEXT,
    created_at      TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    processed_at    TIMESTAMP WITH TIME ZONE,
    version         BIGINT NOT NULL DEFAULT 0
);

CREATE INDEX idx_outbox_status_created ON outbox_messages(status, created_at)
    WHERE status = 'PENDING';

-- ─── Undo migration (Flyway Pro) ──────────────────────
-- U4__create_outbox_table.sql (undo versiyası)
DROP TABLE IF EXISTS outbox_messages;

-- ─── Repeatable migration ─────────────────────────────
-- R__create_views.sql (her dəfə schema dəyişdikdə yenidən icra)
-- R prefixli — checksum dəyişdikdə yenidən çalışır

CREATE OR REPLACE VIEW active_orders AS
SELECT o.*, c.full_name as customer_name, c.email
FROM orders o
JOIN customers c ON o.customer_id = c.id
WHERE o.status != 'CANCELLED';
```

---

## Flyway — Spring Boot inteqrasiya

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.flywaydb</groupId>
    <artifactId>flyway-core</artifactId>
</dependency>
<!-- PostgreSQL üçün -->
<dependency>
    <groupId>org.flywaydb</groupId>
    <artifactId>flyway-database-postgresql</artifactId>
</dependency>
```

```yaml
# application.yml
spring:
  datasource:
    url: jdbc:postgresql://localhost:5432/orderdb
    username: postgres
    password: secret

  flyway:
    enabled: true
    locations: classpath:db/migration  # Migration faylların yeri
    baseline-on-migrate: false          # Mövcud DB-ni baseline et
    baseline-version: "0"
    out-of-order: false                 # Sıra pozulan migration qadağandır
    validate-on-migrate: true           # Checksum yoxlama
    clean-disabled: true                # Production-da clean() qadağandır!
    table: flyway_schema_history        # Metadata cədvəli

  # Test mühitdə:
  # flyway:
  #   clean-disabled: false
```

```
src/main/resources/
  db/
    migration/
      V1__create_initial_schema.sql
      V2__add_indexes.sql
      V3__add_delivery_date.sql
      V4__create_outbox_table.sql
      R__create_views.sql
```

```java
// ─── Java migration (Flyway) ──────────────────────────
// SQL əvəzinə Java-da migration yazmaq
@Component
public class V5__MigrateOrderData extends BaseJavaMigration {

    @Override
    public void migrate(Context context) throws Exception {
        String selectSql = "SELECT id, metadata FROM orders WHERE metadata IS NOT NULL";
        String updateSql = "UPDATE orders SET new_field = ? WHERE id = ?";

        try (var stmt = context.getConnection().createStatement();
             var rs = stmt.executeQuery(selectSql);
             var ps = context.getConnection().prepareStatement(updateSql)) {

            while (rs.next()) {
                long id = rs.getLong("id");
                String metadata = rs.getString("metadata");

                // Business logic — metadata-dan yeni field extract et
                String extracted = extractFromMetadata(metadata);

                ps.setString(1, extracted);
                ps.setLong(2, id);
                ps.addBatch();
            }
            ps.executeBatch();
        }
    }
}

// ─── Test-də Flyway ───────────────────────────────────
@SpringBootTest
class FlywayMigrationTest {

    @Autowired
    private Flyway flyway;

    @Autowired
    private JdbcTemplate jdbcTemplate;

    @Test
    void allMigrationsShouldBeApplied() {
        MigrationInfo[] info = flyway.info().all();

        for (MigrationInfo migration : info) {
            assertEquals(MigrationState.SUCCESS, migration.getState(),
                "Migration uğursuz: " + migration.getScript());
        }
    }
}
```

---

## Liquibase

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.liquibase</groupId>
    <artifactId>liquibase-core</artifactId>
</dependency>
```

```yaml
# ─── YAML format (daha oxunaqlı) ─────────────────────
# src/main/resources/db/changelog/db.changelog-master.yaml
databaseChangeLog:
  - includeAll:
      path: db/changelog/changes/
      relativeToChangelogFile: false

# db/changelog/changes/001-create-orders-table.yaml
databaseChangeLog:
  - changeSet:
      id: 001
      author: ali.mammadov
      changes:
        - createTable:
            tableName: orders
            columns:
              - column:
                  name: id
                  type: BIGINT
                  autoIncrement: true
                  constraints:
                    primaryKey: true
              - column:
                  name: customer_id
                  type: BIGINT
                  constraints:
                    nullable: false
              - column:
                  name: status
                  type: VARCHAR(50)
                  defaultValue: PENDING
                  constraints:
                    nullable: false
              - column:
                  name: total_amount
                  type: DECIMAL(10, 2)
                  constraints:
                    nullable: false
              - column:
                  name: created_at
                  type: TIMESTAMP WITH TIME ZONE
                  defaultValueComputed: NOW()

  - changeSet:
      id: 002
      author: ali.mammadov
      changes:
        - addColumn:
            tableName: orders
            columns:
              - column:
                  name: delivery_date
                  type: DATE
        - createIndex:
            tableName: orders
            indexName: idx_orders_status
            columns:
              - column:
                  name: status
      rollback:
        - dropColumn:
            tableName: orders
            columnName: delivery_date
        - dropIndex:
            indexName: idx_orders_status
            tableName: orders
```

```xml
<!-- SQL format da dəstəklənir -->
<!-- db/changelog/changes/003-add-tracking.sql -->
--liquibase formatted sql

--changeset vali.aliyev:003
ALTER TABLE orders ADD COLUMN tracking_number VARCHAR(100);
CREATE INDEX idx_orders_tracking ON orders(tracking_number) WHERE tracking_number IS NOT NULL;

--rollback
ALTER TABLE orders DROP COLUMN tracking_number;
DROP INDEX IF EXISTS idx_orders_tracking;
```

---

## Liquibase — Spring Boot inteqrasiya

```yaml
# application.yml
spring:
  liquibase:
    enabled: true
    change-log: classpath:db/changelog/db.changelog-master.yaml
    default-schema: public
    liquibase-schema: public
    database-change-log-table: DATABASECHANGELOG
    database-change-log-lock-table: DATABASECHANGELOGLOCK

  # Test mühitdə rollback:
  # liquibase:
  #   drop-first: true  # Hər test çalışmasında schema sil
```

```java
// ─── Liquibase ilə test ───────────────────────────────
@SpringBootTest
class LiquibaseMigrationTest {

    @Autowired
    private SpringLiquibase liquibase;

    @Autowired
    private JdbcTemplate jdbcTemplate;

    @Test
    void schemaCreatedCorrectly() {
        // orders cədvəlinin mövcudluğunu yoxla
        Integer count = jdbcTemplate.queryForObject(
            "SELECT COUNT(*) FROM information_schema.tables " +
            "WHERE table_name = 'orders'",
            Integer.class
        );
        assertEquals(1, count);
    }

    @Test
    void columnsExist() {
        // Kolon mövcudluğunu yoxla
        Integer count = jdbcTemplate.queryForObject(
            "SELECT COUNT(*) FROM information_schema.columns " +
            "WHERE table_name = 'orders' AND column_name = 'tracking_number'",
            Integer.class
        );
        assertEquals(1, count);
    }
}

// ─── @Sql ilə test ────────────────────────────────────
@DataJpaTest
@AutoConfigureTestDatabase(replace = AutoConfigureTestDatabase.Replace.NONE)
class OrderRepositoryTest {

    @Test
    @Sql("/test-data/sample-orders.sql")
    @Sql(scripts = "/test-data/cleanup.sql",
         executionPhase = Sql.ExecutionPhase.AFTER_TEST_METHOD)
    void shouldFindOrdersByStatus() {
        // Migration + test data
        List<Order> pending = orderRepository.findByStatus(OrderStatus.PENDING);
        assertEquals(3, pending.size());
    }
}
```

---

## Flyway vs Liquibase

```
─────────────────────────────────────────────────────
Xüsusiyyət          Flyway              Liquibase
─────────────────────────────────────────────────────
Format              SQL, Java           SQL, XML, YAML, JSON
Rollback            Pro versiyadə       Pulsuz
Checksums           ✅ SQL faylları      ✅ changeset-lər
DB dəstəyi          20+ DB              30+ DB
Spring Boot         Native              Native
Kubernetes          ✅                  ✅
Dry-run             Pro                 ✅
Diff tool           Pro                 ✅
Community           Böyük               Böyük
Öyrənmə əyrisi      Aşağı               Orta
Mürəkkəb migration  Java migration      Groovy/Java
─────────────────────────────────────────────────────

Flyway üstünlükləri:
  → Sadə SQL → daha çox developer tanış
  → Az konfiqurasiya
  → Sürətli başlanğıc

Liquibase üstünlükləri:
  → YAML/XML format (DB-agnostik)
  → Rollback pulsuz
  → Diff tool (DB ↔ changelog müqayisəsi)
  → Preconditions (şərti migration)

Tövsiyə:
  Sadə layihə, SQL əsas → Flyway
  Mürəkkəb rollback, DB-agnostik → Liquibase
```

---

## İntervyu Sualları

### 1. Database migration niyə lazımdır?
**Cavab:** Schema dəyişikliklərini versiyalanmış şəkildə izləmək — hansı dəyişiklik nə vaxt, kim tərəfindən tətbiq edildi. Bütün mühitlər (local, staging, production) eyni sıra ilə eyni schema alır. Git ilə kod dəyişikliyi ilə birlikdə schema dəyişikliyi commit edilir — onlar arasında sinxronizasiya. Rollback imkanı.

### 2. Flyway necə migration-ları izləyir?
**Cavab:** `flyway_schema_history` cədvəlini yaradır. Hər migration icra edildikdə — version, description, script adı, checksum, uğurlu/uğursuz — qeyd edilir. Növbəti başlanğıcda bu cədvəl yoxlanılır — tətbiq olunmamış migration-lar icra edilir. Checksum verification — migration fayl dəyişdirildikdə xəta verir (validation). Bu, migration-ların dəyişdirilməsini aşkarlayır.

### 3. Flyway vs Liquibase hansını seçmək lazımdır?
**Cavab:** Flyway — sadə SQL migration-lar, az konfigurasiya, aşağı öyrənmə əyrisi. Liquibase — rollback (pulsuz), DB-agnostik format (YAML/XML), diff tool, precondition. Kiçik/orta layihə üçün Flyway tövsiyə edilir — Spring Boot ilə out-of-the-box işləyir. Enterprise, multi-DB support, ya da rollback kritik tələb olduqda Liquibase.

### 4. Production-da Flyway.clean() niyə disabled olmalıdır?
**Cavab:** `clean()` bütün schema-nı — cədvəlləri, indeksləri, data-nı — silir. CI/CD-də test DB-ni reset etmək üçün faydalıdır, amma production-da çalışarsa bütün data məhv olur. Spring Boot-da `flyway.clean-disabled=true` default (Spring Boot 2.2+). Bu, `@FlywayTest` annotasiyasının prodda yanlışlıqla işlənməsindən qoruyur.

### 5. Liquibase preconditions nədir?
**Cavab:** Changeset icra edilmədən əvvəl şərt yoxlaması: `<preConditions><tableExists tableName="orders"/></preConditions>` — cədvəl mövcuddursa changeset tətbiq et; yoxdursa `MARK_RAN` ya da `CONTINUE`. Bu, idempotent migration-lar üçün faydalıdır — eyni changeset fərqli DB vəziyyətlərindən keçən layihələrdə problem yaratmır.

*Son yenilənmə: 2026-04-10*
