# 32 — JDBC Əsasları və JdbcTemplate

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [JDBC nədir və Java stack-ında yeri](#jdbc-nedir)
2. [JDBC API-nın əsas interfeysləri](#api)
3. [Plain JDBC nümunəsi (old-style)](#plain-jdbc)
4. [try-with-resources ilə resurs idarəetməsi](#try-with)
5. [SQL Injection və PreparedStatement](#injection)
6. [Connection pooling və HikariCP](#pooling)
7. [Spring Boot DataSource auto-config](#datasource)
8. [JdbcTemplate — Spring-in yardımçısı](#jdbctemplate)
9. [NamedParameterJdbcTemplate](#named)
10. [SimpleJdbcInsert və KeyHolder](#simple)
11. [RowMapper variantları](#rowmapper)
12. [Transactions və Exception translation](#transactions)
13. [@JdbcTest ilə testləmə](#testing)
14. [Flyway/Liquibase migration-lar](#migrations)
15. [JDBC vs Spring Data JPA](#jdbc-vs-jpa)
16. [Tam User CRUD nümunəsi](#tam-numune)
17. [Ümumi Səhvlər](#sehvler)
18. [İntervyu Sualları](#intervyu)

---

## 1. JDBC nədir və Java stack-ında yeri {#jdbc-nedir}

**JDBC = Java Database Connectivity.** Verilənlər bazası ilə ünsiyyət üçün standart Java API-dir. `java.sql` və `javax.sql` paketlərindədir.

### Java məlumat bazası stack-ı:

```
┌─────────────────────────────────────────┐
│  Spring Data JPA (ən yüksək abstraksiya) │
│  - Repository interfeysləri              │
│  - Method naming convention              │
├─────────────────────────────────────────┤
│  JPA / Hibernate (ORM)                   │
│  - @Entity, EntityManager                │
├─────────────────────────────────────────┤
│  Spring JdbcTemplate (yüngül wrapper)    │
│  - queryForObject, update, query         │
├─────────────────────────────────────────┤
│  JDBC API (standard — hər şeyin əsası)   │
│  - Connection, Statement, ResultSet      │
├─────────────────────────────────────────┤
│  JDBC Driver (PostgreSQL, MySQL və s.)   │
│  - Konkret DB üçün implementasiya        │
├─────────────────────────────────────────┤
│  Database (PostgreSQL, Oracle, MySQL)    │
└─────────────────────────────────────────┘
```

### Real həyat analogiyası:

JDBC — **universal elektrik rozetkasıdır**. Hər ölkənin (DB) fərqli formalı rozetkası var. Driver — həmin rozetkaya uyğun **adapter**. Eyni Java kodu fərqli DB-lərlə işləyir — yalnız driver dəyişir.

### Driver-lər:

```xml
<!-- PostgreSQL -->
<dependency>
    <groupId>org.postgresql</groupId>
    <artifactId>postgresql</artifactId>
</dependency>

<!-- MySQL -->
<dependency>
    <groupId>com.mysql</groupId>
    <artifactId>mysql-connector-j</artifactId>
</dependency>

<!-- H2 (in-memory, test üçün) -->
<dependency>
    <groupId>com.h2database</groupId>
    <artifactId>h2</artifactId>
    <scope>runtime</scope>
</dependency>
```

---

## 2. JDBC API-nın əsas interfeysləri {#api}

| İnterfeys | Rolu |
|---|---|
| `DriverManager` | Driver-ləri idarə edir, Connection yaradır |
| `DataSource` | Connection fabrikası (modern yol) |
| `Connection` | DB-yə aktiv bağlantı |
| `Statement` | Sabit SQL icra edir (təhlükəli — parametr yoxdur) |
| `PreparedStatement` | Parametrli SQL (təhlükəsiz, sürətli) |
| `CallableStatement` | Stored procedure çağırışları |
| `ResultSet` | Sorğu nəticəsinin kursoru |
| `DatabaseMetaData` | DB haqqında məlumat |
| `SQLException` | DB xətaları |

### Obyektlərin əlaqəsi:

```
DataSource
    │
    ▼
Connection
    │
    ├──► Statement         ──► ResultSet
    ├──► PreparedStatement ──► ResultSet
    └──► CallableStatement ──► ResultSet
```

---

## 3. Plain JDBC nümunəsi (old-style) {#plain-jdbc}

Spring-siz JDBC — nə qədər boilerplate olduğunu görmək üçün.

```java
public class PlainJdbcExample {

    public User findById(Long id) {
        Connection conn = null;
        Statement stmt = null;
        ResultSet rs = null;

        try {
            // 1) Driver-i yüklə (köhnə Java 6-dan əvvəl lazım idi)
            Class.forName("org.postgresql.Driver");

            // 2) Connection yarat
            conn = DriverManager.getConnection(
                "jdbc:postgresql://localhost:5432/mydb",
                "user",
                "password"
            );

            // 3) Statement yarat (DİQQƏT — parametri yoxdur, təhlükəli!)
            stmt = conn.createStatement();

            // 4) SQL icra et
            rs = stmt.executeQuery("SELECT id, name, email FROM users WHERE id = " + id);

            // 5) Nəticəni oxu
            if (rs.next()) {
                User u = new User();
                u.setId(rs.getLong("id"));
                u.setName(rs.getString("name"));
                u.setEmail(rs.getString("email"));
                return u;
            }
            return null;

        } catch (SQLException | ClassNotFoundException e) {
            throw new RuntimeException(e);
        } finally {
            // 6) Resursları əks sırada bağla (MÜTLƏQ!)
            try { if (rs != null) rs.close(); } catch (SQLException ignored) {}
            try { if (stmt != null) stmt.close(); } catch (SQLException ignored) {}
            try { if (conn != null) conn.close(); } catch (SQLException ignored) {}
        }
    }
}
```

### Bu kodda problemlər:

- 40 sətir kod bir sadə SELECT üçün
- SQL Injection təhlükəsi (`" + id`)
- finally blok 9 sətir yer tutur
- Exception handling qarışıqdır

---

## 4. try-with-resources ilə resurs idarəetməsi {#try-with}

Java 7+ `try-with-resources` — close() avtomatik çağırılır.

```java
public User findById(Long id) {
    String sql = "SELECT id, name, email FROM users WHERE id = ?";

    try (Connection conn = dataSource.getConnection();
         PreparedStatement ps = conn.prepareStatement(sql)) {

        ps.setLong(1, id);  // parametr təhlükəsiz şəkildə ötürülür

        try (ResultSet rs = ps.executeQuery()) {
            if (rs.next()) {
                User u = new User();
                u.setId(rs.getLong("id"));
                u.setName(rs.getString("name"));
                u.setEmail(rs.getString("email"));
                return u;
            }
            return null;
        }

    } catch (SQLException e) {
        throw new RuntimeException("DB xətası", e);
    }
}
// close() metodu avtomatik çağırılır — finally bloku yoxdur
```

Hələ də boilerplate var — Spring JdbcTemplate bunu həll edir.

---

## 5. SQL Injection və PreparedStatement {#injection}

### TƏHLÜKƏLİ — Statement ilə string concatenation:

```java
// Bu kodda böyük təhlükəsizlik deliyi var
String userInput = request.getParameter("email");
Statement stmt = conn.createStatement();
ResultSet rs = stmt.executeQuery(
    "SELECT * FROM users WHERE email = '" + userInput + "'"
);
```

**Hücum nümunəsi:**

```
İstifadəçi daxil edir: admin@x.com' OR '1'='1

Yaranan SQL:
SELECT * FROM users WHERE email = 'admin@x.com' OR '1'='1'
                                                  ^^^^^^^^^^
                                                  həmişə true — bütün istifadəçilər qaytarılır

Daha pis:
İstifadəçi: '; DROP TABLE users; --
SQL: SELECT * FROM users WHERE email = ''; DROP TABLE users; --'
```

### TƏHLÜKƏSİZ — PreparedStatement ilə ? placeholder-lər:

```java
String sql = "SELECT * FROM users WHERE email = ?";
try (PreparedStatement ps = conn.prepareStatement(sql)) {
    ps.setString(1, userInput);  // driver escape edir
    try (ResultSet rs = ps.executeQuery()) {
        // ...
    }
}
```

### Niyə PreparedStatement təhlükəsizdir?

- Driver SQL-ı əvvəlcədən kompilyasiya edir
- Parametrlər SQL ifadəsi kimi deyil, dəyər kimi göndərilir
- DB heç vaxt parametri kod kimi icra etmir
- Əlavə üstünlük — statement cache-lənir, performance artır

---

## 6. Connection pooling və HikariCP {#pooling}

Hər HTTP request üçün yeni Connection yaratmaq bahalıdır (~50-100ms TCP handshake + authentication). **Connection pool** — hazır bağlantıları saxlayan və təkrar istifadə edən mexanizm.

### HikariCP — Spring Boot default-u:

```
Spring Boot 2+ ilə gələn default pool — HikariCP.
Ən sürətli və kiçik pool implementasiyası.

Pool necə işləyir:
┌─────────────┐
│ Connection  │  ← hazır
│ Connection  │  ← hazır
│ Connection  │  ← istifadədə (servis-1)
│ Connection  │  ← istifadədə (servis-2)
│ Connection  │  ← hazır
└─────────────┘

Request gələndə:
1) Pool-dan hazır bağlantı alınır (microsaniyə)
2) İstifadə olunur
3) Geri qaytarılır — close() real DB bağlantısını bağlamır!
```

### Konfiqurasiya:

```properties
# application.properties
spring.datasource.url=jdbc:postgresql://localhost:5432/mydb
spring.datasource.username=user
spring.datasource.password=secret

# HikariCP konfiqurasiyası
spring.datasource.hikari.maximum-pool-size=20          # maksimum bağlantı sayı
spring.datasource.hikari.minimum-idle=5                # minimum boş bağlantı
spring.datasource.hikari.connection-timeout=30000      # bağlantı gözləmə (ms)
spring.datasource.hikari.idle-timeout=600000           # boş bağlantının ömrü (10 dəq)
spring.datasource.hikari.max-lifetime=1800000          # maks ömür (30 dəq)
spring.datasource.hikari.leak-detection-threshold=60000  # leak detection (1 dəq)
```

### Pool sizing qaydaları:

| Faktor | Qayda |
|---|---|
| CPU core sayı | `pool_size = ((core_count * 2) + effective_spindle_count)` |
| Web app | 10-20 çox vaxt kifayətdir |
| Batch/reporting | Az sayda uzun bağlantı |
| Serverless | Minimum idle = 0 |

---

## 7. Spring Boot DataSource auto-config {#datasource}

Spring Boot classpath-ə baxır, `spring.datasource.url` property-sini görür və avtomatik olaraq `DataSource` bean-ını yaradır. Heç bir kod yazmağa ehtiyac yoxdur.

```properties
# Bu 3 sətir kifayətdir — Spring Boot qalanını edir
spring.datasource.url=jdbc:postgresql://localhost:5432/mydb
spring.datasource.username=app
spring.datasource.password=secret

# Driver class (adətən avtomatik aşkarlanır URL-dan)
spring.datasource.driver-class-name=org.postgresql.Driver
```

### Auto-configured bean-lar:

```java
@Service
public class MyService {

    private final DataSource dataSource;              // HikariCP (default)
    private final JdbcTemplate jdbcTemplate;          // hazırdır
    private final NamedParameterJdbcTemplate named;   // hazırdır
    private final TransactionManager txManager;       // hazırdır

    public MyService(DataSource ds, JdbcTemplate jdbc,
                     NamedParameterJdbcTemplate named) {
        this.dataSource = ds;
        this.jdbcTemplate = jdbc;
        this.named = named;
    }
}
```

### Birdən çox DataSource:

```java
@Configuration
public class MultiDbConfig {

    @Primary
    @Bean
    @ConfigurationProperties("app.datasource.primary")
    public DataSource primaryDataSource() {
        return DataSourceBuilder.create().build();
    }

    @Bean
    @ConfigurationProperties("app.datasource.secondary")
    public DataSource secondaryDataSource() {
        return DataSourceBuilder.create().build();
    }

    @Bean
    public JdbcTemplate primaryJdbc(@Qualifier("primaryDataSource") DataSource ds) {
        return new JdbcTemplate(ds);
    }
}
```

---

## 8. JdbcTemplate — Spring-in yardımçısı {#jdbctemplate}

`JdbcTemplate` — JDBC-nin üzərində yüngül wrapper-dir. Boilerplate-ı (connection/statement/close) yox edir.

### Əsas metodlar:

```java
@Repository
@RequiredArgsConstructor
public class UserRepository {

    private final JdbcTemplate jdbc;

    // 1) queryForObject — tək nəticə (0 və ya 1)
    public User findById(Long id) {
        String sql = "SELECT id, name, email FROM users WHERE id = ?";
        return jdbc.queryForObject(sql, userRowMapper(), id);
        // Nəticə yoxdursa EmptyResultDataAccessException atır
    }

    // 2) queryForObject + primitive
    public int countAll() {
        return jdbc.queryForObject("SELECT count(*) FROM users", Integer.class);
    }

    // 3) query — siyahı qaytarır
    public List<User> findAll() {
        String sql = "SELECT id, name, email FROM users";
        return jdbc.query(sql, userRowMapper());
    }

    // 4) query + parametr
    public List<User> findByCity(String city) {
        String sql = "SELECT id, name, email FROM users WHERE city = ?";
        return jdbc.query(sql, userRowMapper(), city);
    }

    // 5) update — INSERT/UPDATE/DELETE
    public int delete(Long id) {
        String sql = "DELETE FROM users WHERE id = ?";
        return jdbc.update(sql, id);  // təsirlənmiş sətir sayı
    }

    // 6) update — çox parametr
    public int updateEmail(Long id, String newEmail) {
        String sql = "UPDATE users SET email = ? WHERE id = ?";
        return jdbc.update(sql, newEmail, id);
    }

    // 7) batchUpdate — toplu əməliyyat
    public int[] insertBatch(List<User> users) {
        String sql = "INSERT INTO users (name, email) VALUES (?, ?)";
        return jdbc.batchUpdate(sql, new BatchPreparedStatementSetter() {
            @Override
            public void setValues(PreparedStatement ps, int i) throws SQLException {
                User u = users.get(i);
                ps.setString(1, u.getName());
                ps.setString(2, u.getEmail());
            }
            @Override
            public int getBatchSize() {
                return users.size();
            }
        });
    }

    // 8) queryForMap — bir sətir Map kimi
    public Map<String, Object> findAsMap(Long id) {
        String sql = "SELECT id, name, email FROM users WHERE id = ?";
        return jdbc.queryForMap(sql, id);
        // {"id": 1, "name": "Orxan", "email": "..."}
    }

    // 9) queryForList — bir neçə sətir Map kimi
    public List<Map<String, Object>> findAllAsMaps() {
        return jdbc.queryForList("SELECT * FROM users");
    }

    // RowMapper — ResultSet-dən obyekt qurur
    private RowMapper<User> userRowMapper() {
        return (rs, rowNum) -> {
            User u = new User();
            u.setId(rs.getLong("id"));
            u.setName(rs.getString("name"));
            u.setEmail(rs.getString("email"));
            return u;
        };
    }
}
```

---

## 9. NamedParameterJdbcTemplate {#named}

`?` əvəzinə `:adı` istifadə edir — oxunaqlı və daha az xətalı.

```java
@Repository
@RequiredArgsConstructor
public class UserRepository {

    private final NamedParameterJdbcTemplate named;

    // MapSqlParameterSource
    public User findByEmail(String email) {
        String sql = "SELECT id, name, email FROM users WHERE email = :email";

        MapSqlParameterSource params = new MapSqlParameterSource()
            .addValue("email", email);

        return named.queryForObject(sql, params, userRowMapper());
    }

    // Java Map
    public List<User> findByCityAndAge(String city, int minAge) {
        String sql = """
            SELECT id, name, email FROM users
            WHERE city = :city AND age >= :minAge
        """;

        Map<String, Object> params = Map.of(
            "city", city,
            "minAge", minAge
        );

        return named.query(sql, params, userRowMapper());
    }

    // BeanPropertySqlParameterSource — obyektdən avtomatik
    public int insert(User user) {
        String sql = """
            INSERT INTO users (name, email, age)
            VALUES (:name, :email, :age)
        """;

        SqlParameterSource params = new BeanPropertySqlParameterSource(user);
        // user.getName(), getEmail(), getAge() avtomatik götürülür

        return named.update(sql, params);
    }

    // IN clause
    public List<User> findByIds(List<Long> ids) {
        String sql = "SELECT id, name, email FROM users WHERE id IN (:ids)";
        MapSqlParameterSource params = new MapSqlParameterSource("ids", ids);
        return named.query(sql, params, userRowMapper());
    }
}
```

---

## 10. SimpleJdbcInsert və KeyHolder {#simple}

### KeyHolder — generated ID-ni almaq:

```java
public Long insertAndGetId(User user) {
    String sql = "INSERT INTO users (name, email) VALUES (?, ?)";

    KeyHolder keyHolder = new GeneratedKeyHolder();

    jdbc.update(conn -> {
        PreparedStatement ps = conn.prepareStatement(
            sql, Statement.RETURN_GENERATED_KEYS  // MÜHÜM
        );
        ps.setString(1, user.getName());
        ps.setString(2, user.getEmail());
        return ps;
    }, keyHolder);

    return keyHolder.getKey().longValue();
}
```

### SimpleJdbcInsert — daha sadə alternativ:

```java
@Repository
public class UserRepository {

    private final SimpleJdbcInsert insert;

    public UserRepository(DataSource ds) {
        this.insert = new SimpleJdbcInsert(ds)
            .withTableName("users")
            .usingGeneratedKeyColumns("id");
    }

    public Long save(User user) {
        Map<String, Object> params = Map.of(
            "name", user.getName(),
            "email", user.getEmail()
        );
        Number id = insert.executeAndReturnKey(params);
        return id.longValue();
    }
}
```

### SimpleJdbcCall — stored procedure:

```java
SimpleJdbcCall call = new SimpleJdbcCall(dataSource)
    .withProcedureName("calculate_salary");

Map<String, Object> result = call.execute(
    Map.of("employee_id", 42)
);
BigDecimal salary = (BigDecimal) result.get("out_salary");
```

---

## 11. RowMapper variantları {#rowmapper}

Üç fərqli yanaşma:

### 1) Manual RowMapper:

```java
public class UserRowMapper implements RowMapper<User> {
    @Override
    public User mapRow(ResultSet rs, int rowNum) throws SQLException {
        User u = new User();
        u.setId(rs.getLong("id"));
        u.setName(rs.getString("name"));
        u.setEmail(rs.getString("email"));
        u.setAge(rs.getInt("age"));
        return u;
    }
}

// İstifadə
List<User> users = jdbc.query(sql, new UserRowMapper());
```

### 2) Lambda:

```java
RowMapper<User> mapper = (rs, rowNum) -> {
    User u = new User();
    u.setId(rs.getLong("id"));
    u.setName(rs.getString("name"));
    return u;
};

List<User> users = jdbc.query(sql, mapper);
```

### 3) BeanPropertyRowMapper — reflection avtomatik:

```java
// Sütun adları (snake_case) → Java field-lər (camelCase) avtomatik mapping
// user_id → userId, full_name → fullName

RowMapper<User> mapper = new BeanPropertyRowMapper<>(User.class);
List<User> users = jdbc.query(sql, mapper);

// Və ya faktori metodu
List<User> users = jdbc.query(sql, BeanPropertyRowMapper.newInstance(User.class));
```

### 4) DataClassRowMapper (Spring 5.3+) — record-lar üçün:

```java
public record User(Long id, String name, String email) {}

// Konstruktora birbaşa mapping
RowMapper<User> mapper = new DataClassRowMapper<>(User.class);
List<User> users = jdbc.query(sql, mapper);
```

---

## 12. Transactions və Exception translation {#transactions}

### @Transactional — JdbcTemplate ilə də işləyir:

```java
@Service
@RequiredArgsConstructor
public class MoneyTransferService {

    private final JdbcTemplate jdbc;

    @Transactional  // hər iki UPDATE bir transaction-da icra olunur
    public void transfer(Long fromId, Long toId, BigDecimal amount) {
        jdbc.update("UPDATE accounts SET balance = balance - ? WHERE id = ?",
                    amount, fromId);

        if (amount.compareTo(BigDecimal.ZERO) < 0) {
            throw new IllegalArgumentException("Mənfi məbləğ");
            // Bu exception @Transactional-ı rollback etdirir
        }

        jdbc.update("UPDATE accounts SET balance = balance + ? WHERE id = ?",
                    amount, toId);
    }
}
```

Detallı transactions üçün baxın: `92-spring-transactions.md`.

### Exception translation — SQLException → DataAccessException:

Spring checked `SQLException`-ı unchecked `DataAccessException` iyerarxiyasına çevirir.

```java
// Plain JDBC — checked exception, hər yerdə try/catch lazımdır
public User find(Long id) throws SQLException { }  // köhnə üsul

// JdbcTemplate — runtime exception-lar
public User find(Long id) { }  // sadə imza
```

### DataAccessException iyerarxiyası:

```
DataAccessException (abstrakt)
├── CleanupFailureDataAccessException
├── DataIntegrityViolationException   (constraint violation)
│   └── DuplicateKeyException
├── DataAccessResourceFailureException (DB əlçatmazdır)
├── QueryTimeoutException
├── DeadlockLoserDataAccessException
├── IncorrectResultSizeDataAccessException
│   └── EmptyResultDataAccessException (tapılmadı)
├── OptimisticLockingFailureException
└── TransientDataAccessException
```

### Konkret xətaları tut:

```java
try {
    return jdbc.queryForObject(sql, userRowMapper(), id);
} catch (EmptyResultDataAccessException e) {
    return null;  // tapılmadı
} catch (DuplicateKeyException e) {
    throw new ConflictException("Email artıq mövcuddur");
} catch (DataAccessException e) {
    // digər DB xətaları
    log.error("DB xətası", e);
    throw e;
}
```

---

## 13. @JdbcTest ilə testləmə {#testing}

`@JdbcTest` — yalnız JDBC layer-ini yükləyir, bütün tətbiq deyil.

```java
@JdbcTest
@AutoConfigureTestDatabase(replace = AutoConfigureTestDatabase.Replace.NONE)  // real DB istifadə et
@Import(UserRepository.class)  // test ediləcək repository
class UserRepositoryTest {

    @Autowired
    private JdbcTemplate jdbc;

    @Autowired
    private UserRepository userRepo;

    @BeforeEach
    void setUp() {
        jdbc.execute("""
            CREATE TABLE users (
                id BIGINT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100),
                email VARCHAR(100) UNIQUE
            )
        """);
    }

    @Test
    void shouldSaveAndFindUser() {
        User u = new User(null, "Orxan", "orxan@example.com");
        Long id = userRepo.save(u);

        User found = userRepo.findById(id);
        assertThat(found.getName()).isEqualTo("Orxan");
    }
}
```

### H2 in-memory — test və local dev üçün:

```xml
<dependency>
    <groupId>com.h2database</groupId>
    <artifactId>h2</artifactId>
    <scope>test</scope>
</dependency>
```

```properties
# src/test/resources/application.properties
spring.datasource.url=jdbc:h2:mem:testdb;DB_CLOSE_DELAY=-1;MODE=PostgreSQL
spring.datasource.driver-class-name=org.h2.Driver
```

### Testcontainers — real PostgreSQL:

```java
@JdbcTest
@Testcontainers
@AutoConfigureTestDatabase(replace = AutoConfigureTestDatabase.Replace.NONE)
class UserRepositoryIT {

    @Container
    static PostgreSQLContainer<?> postgres = new PostgreSQLContainer<>("postgres:16");

    @DynamicPropertySource
    static void props(DynamicPropertyRegistry reg) {
        reg.add("spring.datasource.url", postgres::getJdbcUrl);
        reg.add("spring.datasource.username", postgres::getUsername);
        reg.add("spring.datasource.password", postgres::getPassword);
    }

    // H2-nin fərqli SQL dialect-i xəta çıxara bilər —
    // real PostgreSQL ilə test daha etibarlıdır
}
```

---

## 14. Flyway/Liquibase migration-lar {#migrations}

Sxem dəyişiklikləri kodla birlikdə versiyalanmalıdır.

### Flyway:

```xml
<dependency>
    <groupId>org.flywaydb</groupId>
    <artifactId>flyway-core</artifactId>
</dependency>
```

```
src/main/resources/db/migration/
├── V1__create_users_table.sql
├── V2__add_email_unique_constraint.sql
└── V3__add_created_at_column.sql
```

```sql
-- V1__create_users_table.sql
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL
);
```

Spring Boot başladıqda Flyway avtomatik migration-ları icra edir.

### Liquibase — alternativ (XML/YAML/JSON format-ında):

```xml
<dependency>
    <groupId>org.liquibase</groupId>
    <artifactId>liquibase-core</artifactId>
</dependency>
```

---

## 15. JDBC vs Spring Data JPA {#jdbc-vs-jpa}

| Aspekt | JdbcTemplate | Spring Data JPA |
|---|---|---|
| **SQL nəzarəti** | Tam — siz yazırsınız | Generator yazır (çox vaxt) |
| **Performans** | Sürətli, proqnozlaşdırılan | Overhead (1st/2nd cache, dirty checking) |
| **Öyrənmə əyrisi** | SQL biliyi kifayətdir | JPA, JPQL, cascade, fetch type |
| **Boilerplate** | Orta (RowMapper-lər) | Az (repository interfeysləri) |
| **Əlaqələr** | Manual JOIN-lar | `@OneToMany`, `@ManyToOne` avtomatik |
| **Uyğunluq** | Mürəkkəb raportlar, batch | CRUD ağırlıqlı domain modelləri |
| **N+1 problemi** | Yoxdur (siz SQL yazırsınız) | Bəli, diqqətli olmaq lazımdır |

### Nə vaxt hansı?

```
JdbcTemplate seç:
- Çox mürəkkəb raportlar/SQL
- Legacy DB sxemlərı
- Performans kritik
- Read-only analitik sorğular
- Batch/ETL işləri

Spring Data JPA seç:
- Yeni layihə, standart CRUD
- Domain model əhəmiyyətlidir
- Əlaqələr çoxdur
- Developer productivity vacibdir

Hər ikisini birlikdə də istifadə etmək olar!
```

---

## 16. Tam User CRUD nümunəsi {#tam-numune}

### 1) Schema:

```sql
-- src/main/resources/db/migration/V1__create_users.sql
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    age INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 2) Domain sinifi:

```java
public class User {
    private Long id;
    private String email;
    private String name;
    private Integer age;
    private LocalDateTime createdAt;

    // konstruktor, getter/setter-lər
    public User() {}
    public User(String email, String name, Integer age) {
        this.email = email;
        this.name = name;
        this.age = age;
    }
    // getter/setter-lər
}
```

### 3) Repository:

```java
@Repository
public class UserRepository {

    private final NamedParameterJdbcTemplate named;
    private final SimpleJdbcInsert insert;

    public UserRepository(DataSource ds, NamedParameterJdbcTemplate named) {
        this.named = named;
        this.insert = new SimpleJdbcInsert(ds)
            .withTableName("users")
            .usingGeneratedKeyColumns("id");
    }

    private static final RowMapper<User> ROW_MAPPER = (rs, rowNum) -> {
        User u = new User();
        u.setId(rs.getLong("id"));
        u.setEmail(rs.getString("email"));
        u.setName(rs.getString("name"));
        u.setAge(rs.getObject("age", Integer.class));  // null-safe
        u.setCreatedAt(rs.getTimestamp("created_at").toLocalDateTime());
        return u;
    };

    // CREATE
    public Long save(User user) {
        Map<String, Object> params = Map.of(
            "email", user.getEmail(),
            "name", user.getName(),
            "age", user.getAge() != null ? user.getAge() : 0
        );
        return insert.executeAndReturnKey(params).longValue();
    }

    // READ one
    public Optional<User> findById(Long id) {
        String sql = """
            SELECT id, email, name, age, created_at
            FROM users WHERE id = :id
        """;
        try {
            User u = named.queryForObject(sql, Map.of("id", id), ROW_MAPPER);
            return Optional.of(u);
        } catch (EmptyResultDataAccessException e) {
            return Optional.empty();
        }
    }

    // READ by email
    public Optional<User> findByEmail(String email) {
        String sql = """
            SELECT id, email, name, age, created_at
            FROM users WHERE email = :email
        """;
        List<User> result = named.query(sql, Map.of("email", email), ROW_MAPPER);
        return result.stream().findFirst();
    }

    // READ all (pagination)
    public List<User> findAll(int limit, int offset) {
        String sql = """
            SELECT id, email, name, age, created_at
            FROM users
            ORDER BY id
            LIMIT :limit OFFSET :offset
        """;
        return named.query(sql,
            Map.of("limit", limit, "offset", offset),
            ROW_MAPPER);
    }

    // UPDATE
    public int update(User user) {
        String sql = """
            UPDATE users
            SET name = :name, age = :age
            WHERE id = :id
        """;
        return named.update(sql, new BeanPropertySqlParameterSource(user));
    }

    // DELETE
    public int delete(Long id) {
        String sql = "DELETE FROM users WHERE id = :id";
        return named.update(sql, Map.of("id", id));
    }

    // COUNT
    public long count() {
        return named.queryForObject(
            "SELECT count(*) FROM users",
            Map.of(),
            Long.class
        );
    }

    // BATCH INSERT
    public int[] saveAll(List<User> users) {
        String sql = """
            INSERT INTO users (email, name, age)
            VALUES (:email, :name, :age)
        """;
        SqlParameterSource[] batch = users.stream()
            .map(BeanPropertySqlParameterSource::new)
            .toArray(SqlParameterSource[]::new);
        return named.batchUpdate(sql, batch);
    }
}
```

### 4) Service:

```java
@Service
@RequiredArgsConstructor
@Transactional
public class UserService {

    private final UserRepository repo;

    public Long create(User user) {
        if (repo.findByEmail(user.getEmail()).isPresent()) {
            throw new IllegalStateException("Email artıq mövcuddur");
        }
        return repo.save(user);
    }

    @Transactional(readOnly = true)
    public User findById(Long id) {
        return repo.findById(id)
            .orElseThrow(() -> new NotFoundException("User " + id));
    }

    @Transactional(readOnly = true)
    public List<User> findAll(int page, int size) {
        return repo.findAll(size, page * size);
    }

    public void update(Long id, User updates) {
        User existing = findById(id);
        existing.setName(updates.getName());
        existing.setAge(updates.getAge());
        int rows = repo.update(existing);
        if (rows == 0) {
            throw new NotFoundException("User " + id);
        }
    }

    public void delete(Long id) {
        int rows = repo.delete(id);
        if (rows == 0) {
            throw new NotFoundException("User " + id);
        }
    }
}
```

---

## Ümumi Səhvlər {#sehvler}

### 1. Resursları bağlamamaq (Java 7-dən əvvəl kod)

```java
// YANLIŞ
Connection c = dataSource.getConnection();
Statement s = c.createStatement();
ResultSet rs = s.executeQuery("SELECT ...");
// close() çağırılmır — connection pool boşalır!

// DOĞRU — try-with-resources
try (Connection c = dataSource.getConnection();
     PreparedStatement ps = c.prepareStatement(sql);
     ResultSet rs = ps.executeQuery()) {
    // ...
}
```

### 2. Statement + user input (SQL Injection)

```java
// YANLIŞ — SQL injection deliyidir!
String sql = "SELECT * FROM users WHERE email = '" + userEmail + "'";
stmt.executeQuery(sql);

// DOĞRU — PreparedStatement
ps.setString(1, userEmail);
```

### 3. Connection pool sizing-ini ignore etmək

```properties
# YANLIŞ — production-da 10 kifayət etməyə bilər
# default maximum-pool-size=10

# Problem: 20 paralel request gəlir → 10 pool-dan alır, 10-u gözləyir
# Simptom: "Connection is not available, request timed out after 30000ms"

# DOĞRU — yüklənməyə görə təyin et
spring.datasource.hikari.maximum-pool-size=20
```

### 4. queryForObject tapılmayan sətir üçün

```java
// YANLIŞ — EmptyResultDataAccessException atır
User u = jdbc.queryForObject(sql, mapper, id);

// DOĞRU — 1-ci variant: try/catch
try {
    return jdbc.queryForObject(sql, mapper, id);
} catch (EmptyResultDataAccessException e) {
    return null;
}

// DOĞRU — 2-ci variant: query + findFirst
return jdbc.query(sql, mapper, id).stream().findFirst().orElse(null);
```

### 5. @Transactional-ı unutmaq

```java
// YANLIŞ — hər update öz transaction-ındadır, birində xəta digəri commit olur
public void transfer(Long from, Long to, BigDecimal amount) {
    jdbc.update("UPDATE ... WHERE id = ?", amount.negate(), from);
    jdbc.update("UPDATE ... WHERE id = ?", amount, to);
    // orta yerdə xəta olsa pul itir!
}

// DOĞRU
@Transactional
public void transfer(...) { ... }
```

### 6. Batch əvəzinə loop-da insert

```java
// YANLIŞ — 1000 user üçün 1000 DB roundtrip
for (User u : users) {
    repo.save(u);
}

// DOĞRU — bir batch
repo.saveAll(users);  // jdbc.batchUpdate daxilən
```

### 7. N+1 problem (JdbcTemplate-də də olur)

```java
// YANLIŞ — hər order üçün ayrıca user sorğusu
List<Order> orders = jdbc.query("SELECT * FROM orders", orderMapper);
for (Order o : orders) {
    User u = jdbc.queryForObject(
        "SELECT * FROM users WHERE id = ?", userMapper, o.getUserId()
    );
    o.setUser(u);
}

// DOĞRU — JOIN ilə bir sorğu
String sql = """
    SELECT o.id, o.total, u.id as user_id, u.name as user_name
    FROM orders o JOIN users u ON o.user_id = u.id
""";
```

---

## İntervyu Sualları {#intervyu}

**S: JDBC nədir?**
C: JDBC (Java Database Connectivity) — Java-da DB-yə əlçatım üçün standart API-dir. `java.sql` paketində yaşayır. Standart interfeys təqdim edir (Connection, Statement, ResultSet), konkret implementasiya isə driver-dən gəlir (PostgreSQL JDBC driver, MySQL driver və s.). Bu sayədə eyni kod fərqli DB-lərlə işləyir.

**S: Statement və PreparedStatement fərqi nədir?**
C: `Statement` — sabit SQL icra edir, hər dəfə yenidən parse/compile olunur, user input string-ini birləşdirmək SQL Injection deliyi yaradır. `PreparedStatement` — `?` placeholder istifadə edir, SQL bir dəfə parse olunur və cache-lənir (daha sürətli), parametrlər escape olunur (təhlükəsiz). İstifadəçi məlumatı ilə işləyən hər SQL mütləq `PreparedStatement` olmalıdır.

**S: Connection pool nə üçündür və Spring Boot-da default-u hansıdır?**
C: Hər HTTP request üçün yeni DB bağlantısı açmaq bahalıdır (~50-100ms TCP + authentication). Pool hazır bağlantıları saxlayır və təkrar istifadə edir. Spring Boot 2+-da default pool — **HikariCP** (ən sürətli və yüngül pool implementasiyasıdır).

**S: JdbcTemplate plain JDBC-dən nə ilə fərqlənir?**
C: JdbcTemplate boilerplate-ı aradan qaldırır: connection açmaq/bağlamaq, exception handling, resource cleanup avtomatik olur. `SQLException` (checked) `DataAccessException` iyerarxiyasına (unchecked) çevrilir. SQL-ı hələ də siz yazırsınız — JdbcTemplate yalnız infrastruktur işini görür.

**S: RowMapper nədir?**
C: `RowMapper<T>` — `ResultSet`-in bir sətrini `T` obyektinə çevirən funksional interfeysdir (`mapRow(ResultSet rs, int rowNum)`). JdbcTemplate hər sətir üçün onu çağırır. Variantları: manual implementasiya, lambda, `BeanPropertyRowMapper` (snake_case → camelCase avtomatik), `DataClassRowMapper` (record-lar üçün).

**S: NamedParameterJdbcTemplate nə üçün lazımdır?**
C: `?` əvəzinə `:adı` sintaksisi istifadə edir. Çoxlu parametri olan sorğularda daha oxunaqlıdır və parametr sırasını qarışdırmaq riski azalır. `Map<String, Object>`, `MapSqlParameterSource` və ya `BeanPropertySqlParameterSource` ilə parametrlər ötürülür.

**S: SQLException-ın Spring-də necə idarə olunur?**
C: Spring onu `DataAccessException` iyerarxiyasına çevirir (exception translation). Bu abstrakt unchecked exception-dur, alt sinifləri: `EmptyResultDataAccessException` (tapılmadı), `DuplicateKeyException` (unique constraint), `DataIntegrityViolationException`, `DeadlockLoserDataAccessException` və s. Bu yolla biznes kodu DB-ya xas xətalardan asılı olmur.

**S: JdbcTemplate ilə generated ID-ni necə alırsınız?**
C: İki yol var: (1) `jdbc.update(PreparedStatementCreator, KeyHolder)` — `Statement.RETURN_GENERATED_KEYS` parametri ilə PreparedStatement hazırlayıb `KeyHolder`-dən oxumaq. (2) Daha sadə: `SimpleJdbcInsert.executeAndReturnKey(params)` — avtomatik generated key qaytarır.

**S: @JdbcTest nə üçündür?**
C: Yalnız JDBC-ə aid bean-ları (DataSource, JdbcTemplate, NamedParameterJdbcTemplate) yükləyən test slice-dır. Controller, Service və digər bean-lar yüklənmir — test sürətli olur. Default olaraq embedded H2 DB istifadə edir; `@AutoConfigureTestDatabase(replace = NONE)` ilə real DB də istifadə etmək olar (Testcontainers ilə birlikdə).

**S: JdbcTemplate və Spring Data JPA arasında necə seçim edirsiniz?**
C: **JdbcTemplate** — mürəkkəb SQL, raportlar, legacy sxemlər, performans kritik ssenarilər, batch ETL işləri üçün. **Spring Data JPA** — yeni CRUD ağırlıqlı layihələr, zəngin domain model, çoxlu əlaqələr üçün. Həmçinin bir layihədə hər ikisini birlikdə istifadə etmək olar: domain CRUD üçün JPA, raportlar üçün JdbcTemplate.

**S: HikariCP pool size-ını necə təyin edirsiniz?**
C: Əsas düstur: `pool_size = (core_count * 2) + effective_spindle_count`. Web tətbiqlər üçün 10-20 adətən kifayət edir. Çox yüksək dəyər dependency-ə görə performansı aşağı salır (DB-də kontenşın artır). Leak detection (`leak-detection-threshold`) unudulmuş bağlantıları aşkarlamağa kömək edir. Production-da monitoring (Micrometer + HikariCP metrics) vacibdir.
