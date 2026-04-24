# Transactions — Dərin Müqayisə

> **Seviyye:** Advanced ⭐⭐⭐

## Giriş

Transaction (tranzaksiya) — "hamısı oldu, ya heç biri olmadı" prinsipidir. Pul köçürməsi, sifariş yaratmaq, stok azaltmaq kimi çoxaddımlı əməliyyatlar üçün **ACID** (Atomicity, Consistency, Isolation, Durability) zəmanəti lazımdır. Əgər yarıda server söndüsə və ya exception atıldısa, bütün dəyişikliklər geri qaytarılmalıdır (`ROLLBACK`).

Spring-də əsas mexanizm **`@Transactional`** annotasiyasıdır — AOP proxy arxasında işləyir. `propagation`, `isolation`, `readOnly`, `timeout`, `rollbackFor`, `noRollbackFor` atributları ilə dəqiq idarə olunur. Programmatic variant üçün **`TransactionTemplate`** və **`PlatformTransactionManager`** var. Reactive tərəfdə isə **`ReactiveTransactionManager`** WebFlux mühitində işləyir.

Laravel-də əsas API **`DB::transaction(fn () => ...)`** closure-based-dir — deadlock halında avtomatik retry edə bilir. Manual rejim üçün `DB::beginTransaction()` / `commit()` / `rollBack()`. Nested transactions savepoint-lərdən istifadə edir. `DB::afterCommit()` helper-i ilə commit-dən sonra iş görmək mümkündür (queued job-larda `ShouldQueueAfterCommit` interface).

Bu sənəd 24-transactions.md-dən daha dərin: propagation, isolation, pitfalls, reactive, XA, saga, testing — real pul köçürmə ssenarisi ilə.

---

## Spring-də istifadəsi

### 1) `@Transactional` — atributlar tam icmalı

```java
@Service
public class OrderService {

    @Transactional(
        propagation = Propagation.REQUIRED,          // Default
        isolation = Isolation.READ_COMMITTED,        // DB default-a əsaslanır
        readOnly = false,                            // Yazma icazəlidir
        timeout = 30,                                // Saniyə
        rollbackFor = { Exception.class },           // Checked exception-da da rollback
        noRollbackFor = { NotFoundException.class }  // Bu exception rollback etmir
    )
    public Order placeOrder(OrderRequest request) {
        // 1) Stok azalt
        inventoryRepo.decrement(request.getProductId(), request.getQty());
        // 2) Sifariş yarat
        Order order = orderRepo.save(new Order(request));
        // 3) Ödəniş qeyd et
        paymentRepo.save(new Payment(order.getId(), request.getAmount()));
        return order;
    }
}
```

**Atribut mənaları:**

- `propagation` — tranzaksiya olan metod başqa tranzaksiya daxilindən çağırılanda necə davransın.
- `isolation` — eyni anda işləyən tranzaksiyalar bir-birini necə görsün.
- `readOnly = true` — Hibernate dirty-check atlayır, DB tərəfindən optimize ola bilir (replicas üçün).
- `timeout` — verilən saniyədən çox çəksə, tranzaksiya ləğv olur (`TransactionTimedOutException`).
- `rollbackFor` — Spring default olaraq yalnız `RuntimeException` və `Error` üçün rollback edir. Checked exception-larda da rollback istəyiriksə, `rollbackFor` lazımdır.
- `noRollbackFor` — bu exception atılsa belə, commit olunsun.

### 2) Propagation — yeddi rejim dərindən

```java
// REQUIRED (default): cari tranzaksiyaya qoşul, yoxdursa yenisini yarat
@Transactional(propagation = Propagation.REQUIRED)
public void normal() { ... }

// REQUIRES_NEW: həmişə yeni tranzaksiya yarat, mövcudu dayandır (suspend)
@Transactional(propagation = Propagation.REQUIRES_NEW)
public void auditLog(String action) {
    // Əsas tranzaksiya rollback olsa belə, audit log qalır
    auditRepo.save(new AuditEntry(action));
}

// SUPPORTS: varsa qoşul, yoxdursa tranzaksiyasız işlə
@Transactional(propagation = Propagation.SUPPORTS, readOnly = true)
public User findById(Long id) { return userRepo.findById(id).orElseThrow(); }

// NOT_SUPPORTED: mövcud tranzaksiyanı dayandır, tranzaksiyasız işlə
@Transactional(propagation = Propagation.NOT_SUPPORTED)
public void sendEmail(String to) {
    // External API — DB tranzaksiyası lazım deyil
    mailClient.send(to);
}

// MANDATORY: mütləq tranzaksiya daxilində olmalı, yoxdursa exception
@Transactional(propagation = Propagation.MANDATORY)
public void internalHelper() { ... }

// NEVER: tranzaksiya varsa exception at
@Transactional(propagation = Propagation.NEVER)
public void mustRunOutside() { ... }

// NESTED: savepoint yaradır — alt tranzaksiya rollback olsa, üst davam edir
@Transactional(propagation = Propagation.NESTED)
public void tryOptionalStep() {
    try {
        riskyStep();
    } catch (Exception e) {
        // Yalnız bu savepoint-ə qədər geri dönür, ümumi tranzaksiya qalır
    }
}
```

**Nümunə: `REQUIRES_NEW` audit üçün:**

```java
@Service
public class PaymentService {

    @Autowired private AuditService audit;

    @Transactional
    public void charge(Long orderId, BigDecimal amount) {
        // ... pul əməliyyatı
        audit.log("CHARGE", orderId);  // REQUIRES_NEW — independent commit
        throw new PaymentFailedException();  // Əsas rollback, audit QALIR
    }
}

@Service
public class AuditService {
    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void log(String action, Long entityId) {
        auditRepo.save(new Audit(action, entityId, Instant.now()));
    }
}
```

### 3) Isolation levels — anomaliyalar ilə

```java
@Transactional(isolation = Isolation.READ_UNCOMMITTED)  // Ən aşağı
@Transactional(isolation = Isolation.READ_COMMITTED)    // PostgreSQL default
@Transactional(isolation = Isolation.REPEATABLE_READ)   // MySQL InnoDB default
@Transactional(isolation = Isolation.SERIALIZABLE)      // Ən yüksək
```

**Dirty read (READ_UNCOMMITTED icazə verir):**

```
T1: UPDATE account SET balance = 500 WHERE id = 1;  -- commit olmayıb
T2: SELECT balance FROM account WHERE id = 1;        -- 500 oxudu (səhv!)
T1: ROLLBACK;                                         -- T2 səhv məlumat aldı
```

**Non-repeatable read (READ_COMMITTED icazə verir, REPEATABLE_READ qoruyur):**

```
T1: SELECT balance FROM account WHERE id = 1;  -- 1000 aldı
T2: UPDATE account SET balance = 800 WHERE id = 1; COMMIT;
T1: SELECT balance FROM account WHERE id = 1;  -- indi 800 (fərqli!)
```

**Phantom read (REPEATABLE_READ-ə qədər icazəli, SERIALIZABLE qoruyur):**

```
T1: SELECT COUNT(*) FROM orders WHERE user_id = 5;  -- 3 nəticə
T2: INSERT INTO orders (user_id, ...) VALUES (5, ...); COMMIT;
T1: SELECT COUNT(*) FROM orders WHERE user_id = 5;  -- indi 4 (phantom!)
```

**Hansını seç?**

- **READ_COMMITTED** — əksər hallar, oxşarı Postgres default.
- **REPEATABLE_READ** — inventar sayımı, hesabat (non-repeatable qorunmalıdır).
- **SERIALIZABLE** — kritik maliyyə əməliyyatı, lakin performans cəzası var.

### 4) Programmatic transactions — `TransactionTemplate`

```java
@Service
public class ReportService {

    private final TransactionTemplate txTemplate;

    public ReportService(PlatformTransactionManager txManager) {
        this.txTemplate = new TransactionTemplate(txManager);
        this.txTemplate.setIsolationLevel(TransactionDefinition.ISOLATION_READ_COMMITTED);
        this.txTemplate.setTimeout(60);
    }

    public Report generate(Long id) {
        return txTemplate.execute(status -> {
            try {
                Data data = dataRepo.load(id);
                if (data.isEmpty()) {
                    status.setRollbackOnly();
                    return null;
                }
                return reportBuilder.build(data);
            } catch (Exception ex) {
                status.setRollbackOnly();
                throw new ReportException(ex);
            }
        });
    }
}
```

**Aşağı səviyyəli `PlatformTransactionManager`:**

```java
TransactionStatus status = txManager.getTransaction(new DefaultTransactionDefinition());
try {
    // ... iş
    txManager.commit(status);
} catch (Exception ex) {
    txManager.rollback(status);
    throw ex;
}
```

Çox nadir hallarda lazım olur — `TransactionTemplate` daha təhlükəsizdir.

### 5) `@Transactional` pitfalls — ən çox buraxılan səhvlər

**Self-invocation proxy-dən keçmir:**

```java
@Service
public class UserService {

    public void outer() {
        inner();  // @Transactional İŞLƏMİR — eyni obyektdən çağırılıb
    }

    @Transactional
    public void inner() { ... }
}
```

**Səbəb:** Spring AOP proxy yaradır. `userService.inner()` kənardan çağırılsa, proxy tranzaksiyaya daxil edir. Lakin `this.inner()` daxildən çağırılanda proxy-dən keçmir — sadə Java metod çağırışıdır.

**Həllər:**

```java
// 1) Metodu ayrı bean-a çıxar
@Service class Outer { @Autowired Inner inner; void a() { inner.b(); } }
@Service class Inner { @Transactional public void b() { ... } }

// 2) Self-reference (inject özü)
@Autowired @Lazy private UserService self;
public void outer() { self.inner(); }

// 3) AspectJ mode — bytecode weaving, proxy yoxdur
@EnableTransactionManagement(mode = AdviceMode.ASPECTJ)
```

**Private/protected metod:**

```java
@Transactional
private void internal() { ... }  // İŞLƏMİR — proxy yalnız public metodu görür
```

**Checked exception rollback etmir:**

```java
@Transactional
public void save() throws IOException {
    repo.save(entity);
    throw new IOException("..."); // COMMIT OLUR! (RuntimeException deyil)
}

// Həll
@Transactional(rollbackFor = Exception.class)
public void saveFixed() throws IOException { ... }
```

**`try/catch` içində yutmaq:**

```java
@Transactional
public void bug() {
    try {
        repo.save(a);
        repo.save(b);  // Exception
    } catch (Exception ex) {
        log.warn("yutuldu");  // Rollback olmur, a qalır (qismən yazma)
    }
}
```

### 6) Reactive transactions — WebFlux / R2DBC

```java
@Configuration
@EnableTransactionManagement
public class ReactiveConfig {
    @Bean
    public ReactiveTransactionManager txManager(ConnectionFactory cf) {
        return new R2dbcTransactionManager(cf);
    }
}

@Service
public class ReactiveOrderService {

    @Transactional
    public Mono<Order> place(OrderRequest req) {
        return inventoryRepo.decrement(req.productId(), req.qty())
            .then(orderRepo.save(new Order(req)))
            .flatMap(o -> paymentRepo.save(new Payment(o.id(), req.amount()))
                         .thenReturn(o));
    }
}
```

Programmatic reactive:

```java
TransactionalOperator op = TransactionalOperator.create(reactiveTxManager);

public Mono<Order> place(OrderRequest req) {
    return op.transactional(
        inventoryRepo.decrement(...)
            .then(orderRepo.save(...))
    );
}
```

**Vacib:** reactive stream subscribe olunanda `TransactionContextManager` context-ə tranzaksiyanı yerləşdirir. Blocking JDBC-ni reactive context-də qarışdırmaq OLMAZ.

### 7) JTA / distributed transactions — XA protocol

Bir çox resource (2 DB + JMS + Kafka) arasında atomarlıq lazımdırsa XA lazımdır. Two-Phase Commit (2PC) işləyir:

```xml
<!-- Atomikos JTA provider -->
<dependency>
    <groupId>com.atomikos</groupId>
    <artifactId>transactions-spring-boot3-starter</artifactId>
</dependency>
```

```yaml
spring:
  jta:
    atomikos:
      properties:
        service: com.atomikos.icatch.standalone.UserTransactionServiceFactory
  datasource:
    xa:
      data-source-class-name: org.postgresql.xa.PGXADataSource
```

```java
@Transactional  // JTA-based — bütün XA resurslar coordinate olur
public void transferAndNotify(Long from, Long to, BigDecimal amount) {
    accountRepo1.withdraw(from, amount);    // DB1 (XA)
    accountRepo2.deposit(to, amount);       // DB2 (XA)
    jmsTemplate.send("notify", "transfer"); // JMS (XA)
}
```

**Niyə XA indi az istifadə olunur?**

- 2PC çox ləng — hər resource bloklanır.
- Coordinator tək nöqtə (single point of failure).
- Cloud-native mühit (Kubernetes) XA-nı yaxşı dəstəkləmir.
- Mikroservis memarlığında DB-lər ayrıdır — XA əvəzinə **Saga pattern** üstünlük təşkil edir (hər servis öz lokal tranzaksiyası + kompensasiya hadisəsi).

Saga ilə bağlı ətraflı `system-design/` qovluğundadır.

### 8) Savepoint və nested

```java
@Transactional
public void saveMany(List<Order> orders) {
    JdbcTemplate jdbc = ...;
    Object savepoint = TransactionAspectSupport.currentTransactionStatus().createSavepoint();
    try {
        for (Order o : orders) orderRepo.save(o);
    } catch (Exception e) {
        TransactionAspectSupport.currentTransactionStatus().rollbackToSavepoint(savepoint);
    }
}
```

`Propagation.NESTED` hazır savepoint sintaksisi verir:

```java
@Transactional(propagation = Propagation.NESTED)
public void optionalStep() { ... }
```

### 9) Testing — `@Transactional` test-də

```java
@SpringBootTest
@Transactional  // Hər test rollback olur, DB təmiz qalır
class OrderServiceTest {

    @Autowired OrderService service;

    @Test
    void shouldCreateOrder() {
        Order o = service.place(req);
        assertThat(o.getId()).isNotNull();
        // Test bitəndə rollback — o sətir DB-də qalmır
    }

    @Test
    @Commit  // Bu testdə commit et — sonrakı test üçün data qalsın (nadir)
    void shouldPersist() { ... }
}
```

`@Sql` ilə test data:

```java
@Test
@Sql("/test-data/accounts.sql")
void shouldTransfer() { ... }
```

### 10) Real ssenari — pul köçürməsi (Spring)

```java
@Service
public class TransferService {

    @Transactional(isolation = Isolation.REPEATABLE_READ, timeout = 10,
                   rollbackFor = Exception.class)
    public Transfer transfer(Long fromId, Long toId, BigDecimal amount) {
        if (amount.compareTo(BigDecimal.ZERO) <= 0)
            throw new IllegalArgumentException("amount > 0");

        // Lock ilə oxu — konkuren konfliktlər qarşısı
        Account from = accountRepo.findByIdForUpdate(fromId)
            .orElseThrow(() -> new AccountNotFoundException(fromId));
        Account to = accountRepo.findByIdForUpdate(toId)
            .orElseThrow(() -> new AccountNotFoundException(toId));

        if (from.getBalance().compareTo(amount) < 0)
            throw new InsufficientFundsException();

        from.debit(amount);
        to.credit(amount);
        accountRepo.save(from);
        accountRepo.save(to);

        Transfer tx = transferRepo.save(new Transfer(fromId, toId, amount));
        auditService.logAsync("TRANSFER", tx.getId());  // REQUIRES_NEW
        return tx;
    }
}

public interface AccountRepository extends JpaRepository<Account, Long> {
    @Lock(LockModeType.PESSIMISTIC_WRITE)
    @Query("select a from Account a where a.id = :id")
    Optional<Account> findByIdForUpdate(@Param("id") Long id);
}
```

---

## Laravel-də istifadəsi

### 1) `DB::transaction()` — closure rejimi

```php
use Illuminate\Support\Facades\DB;

$order = DB::transaction(function () use ($request) {
    Inventory::where('product_id', $request->product_id)
        ->decrement('stock', $request->qty);

    $order = Order::create([
        'user_id' => $request->user_id,
        'total' => $request->amount,
    ]);

    Payment::create([
        'order_id' => $order->id,
        'amount' => $request->amount,
    ]);

    return $order;
});
```

Əgər closure-da exception atılsa, avtomatik `rollBack()` olur. Normal qurtarsa — `commit()`.

### 2) Deadlock retry — ikinci parametr

```php
// Deadlock olanda 3 dəfə yenidən cəhd et
DB::transaction(function () {
    // ...
}, attempts: 3);
```

`QueryException` ilə SQLSTATE 40001 / 1213 (deadlock) alınanda Laravel tranzaksiyanı yenidən başladır. Yalnız **tam deadlock** hallarında — digər xətalar retry olunmur.

### 3) Manual — `beginTransaction / commit / rollBack`

```php
DB::beginTransaction();
try {
    $this->debit($from, $amount);
    $this->credit($to, $amount);
    $this->log($from, $to, $amount);
    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    report($e);
    throw $e;
}
```

`try/finally` ilə cleanup etmək olar, lakin `DB::transaction(fn () => ...)` daha təmizdir — unutmaq olmur.

### 4) Nested transactions — savepoint

```php
DB::transaction(function () {
    User::create([...]);                // Əsas tranzaksiya

    DB::transaction(function () {       // SAVEPOINT trans2
        Profile::create([...]);
        throw new \Exception();         // Yalnız savepoint rollback
    });
    // Burdan sonra da User qalır? XEYIR — iç-tranzaksiyada exception
    // çölə atılsa, əsas tranzaksiya da rollback olur.
});
```

**Vacib:** Laravel nested transaction savepoint yaradır, amma inner exception outer-ə atılanda hər ikisi rollback olur. "Yarım rollback" istəyirsənsə, exception-ı inner-də tutmalısan:

```php
DB::transaction(function () {
    User::create([...]);

    try {
        DB::transaction(function () {
            Profile::create([...]);
            throw new \Exception();
        });
    } catch (\Throwable $e) {
        // Yalnız profil savepoint rollback oldu, user qaldı
        Log::warning('profile failed', ['err' => $e->getMessage()]);
    }
});
```

### 5) Isolation level — üçüncü parametr

```php
use Illuminate\Database\ConnectionInterface;

DB::transaction(function () {
    // ...
}, attempts: 1, isolationLevel: ConnectionInterface::READ_COMMITTED);
```

Laravel 11+ üçün isolation hər driver-ə uyğun SQL komandasını icra edir:

- MySQL: `SET TRANSACTION ISOLATION LEVEL ...`
- Postgres: `SET TRANSACTION ISOLATION LEVEL ...`
- SQLite: yalnız `SERIALIZABLE` dəstəklənir.

Amma faktiki olaraq əksər proyektlərdə isolation DB konfiqurasiyasında qlobal qoyulur, kod səviyyəsində dəyişmək nadirdir.

### 6) Pitfalls

**Lazy transaction — query-dən əvvəl tranzaksiya açılmır:**

```php
DB::transaction(function () {
    $user = User::find(1);              // Bu query tranzaksiya içindədir
    Http::get('https://slow.api');       // Bu YAVAŞ API çağırışıdır!
    $user->update(['x' => 1]);          // İndi də hələ tranzaksiya açıq
});
```

HTTP çağırışı tranzaksiya içində uzun müddət DB lock tutur. Xaricdən çıxar:

```php
$data = Http::get(...);  // Əvvəl
DB::transaction(function () use ($data) {
    // İndi yalnız DB iş
});
```

**N+1 in transaction — çox yavaş:**

```php
DB::transaction(function () use ($orders) {
    foreach ($orders as $o) {
        $o->items;          // Hər iterasiyada ayrı SELECT
        $o->customer;       // Eyni
    }
});
```

`Order::with(['items', 'customer'])->get()` ilə eager load et, sonra tranzaksiyaya gir.

**Uzun tranzaksiya bütün worker-ları bloklayır** — 30 saniyədən uzun sürən tranzaksiya PostgreSQL-də vacuum-u gecikdirir, MySQL-də history bloat yaradır.

### 7) `DB::afterCommit()` və queued jobs

```php
DB::transaction(function () {
    $user = User::create([...]);
    event(new UserRegistered($user));   // Bu listener tranzaksiya daxilində
});
```

Əgər listener email göndərirsə və `UserRegistered` event-i tranzaksiya içində atılıb — listener DB-də hələ olmayan user-i axtara bilər.

**Həll — `ShouldQueueAfterCommit`:**

```php
class SendWelcomeEmail implements ShouldQueue, ShouldQueueAfterCommit
{
    public function handle(UserRegistered $event): void { ... }
}
```

Queued job yalnız `COMMIT`-dən sonra worker-a göndərilir. Laravel 11+ default `afterCommit` konfiqurasiyası:

```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'after_commit' => true,   // Bütün redis job-lar commit sonrası
    ],
],
```

Manual inline callback:

```php
DB::transaction(function () use ($user) {
    $user->update(['verified' => true]);
    DB::afterCommit(fn () => Cache::forget("user:{$user->id}"));
});
```

### 8) Distributed transactions in PHP

PHP-də XA real dəstək zəifdir (pdo_mysql XA müəyyən səviyyədə var, amma az istifadə olunur). Praktikada **Saga pattern** seçilir:

```php
class TransferSaga {
    public function execute(int $from, int $to, string $amount): void {
        $step1 = null; $step2 = null;
        try {
            $step1 = DB::transaction(fn () =>
                Account::where('id', $from)->lockForUpdate()->decrement('balance', $amount)
            );
            $step2 = app(ExternalBankClient::class)->credit($to, $amount);
        } catch (\Throwable $e) {
            if ($step2 === null && $step1 !== null) {
                // Kompensasiya
                DB::transaction(fn () =>
                    Account::where('id', $from)->lockForUpdate()->increment('balance', $amount)
                );
            }
            throw $e;
        }
    }
}
```

`league/tactician` paketi ilə command bus arxasında saga orchestrator qurulur. Daha geniş hallarda Temporal PHP SDK, ya da Kafka + hadisələr ilə orchestration.

### 9) Testing — `DatabaseTransactions` trait

```php
use Illuminate\Foundation\Testing\DatabaseTransactions;

class OrderServiceTest extends TestCase
{
    use DatabaseTransactions;   // Hər test rollback

    public function test_creates_order(): void
    {
        $order = $this->service->place($request);
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        // Test bitəndə rollback
    }
}
```

`RefreshDatabase` — migrasiyanı yenidən işlədir (daha ağır). `DatabaseTransactions` sürətli, lakin `DB::transaction()` daxilindədir — `DB::afterCommit` callback-ləri test-də işləməyə bilər. Laravel 11+ `WithoutMiddleware` + `$this->withoutExceptionHandling()` debug üçün faydalıdır.

### 10) Real ssenari — pul köçürməsi (Laravel)

```php
class TransferService
{
    public function transfer(int $fromId, int $toId, string $amount): Transfer
    {
        if (bccomp($amount, '0', 2) <= 0) {
            throw new \InvalidArgumentException('amount must be > 0');
        }

        return DB::transaction(function () use ($fromId, $toId, $amount) {
            $from = Account::where('id', $fromId)->lockForUpdate()->firstOrFail();
            $to   = Account::where('id', $toId)->lockForUpdate()->firstOrFail();

            if (bccomp($from->balance, $amount, 2) < 0) {
                throw new InsufficientFundsException();
            }

            $from->decrement('balance', $amount);
            $to->increment('balance', $amount);

            $tx = Transfer::create([
                'from_id' => $fromId,
                'to_id'   => $toId,
                'amount'  => $amount,
            ]);

            DB::afterCommit(function () use ($tx) {
                AuditLog::dispatch('TRANSFER', $tx->id);   // ShouldQueueAfterCommit
            });

            return $tx;
        }, attempts: 3, isolationLevel: \Illuminate\Database\ConnectionInterface::READ_COMMITTED);
    }
}
```

---

## Əsas fərqlər

| Xüsusiyyət | Spring | Laravel |
|---|---|---|
| Əsas API | `@Transactional` (AOP proxy) | `DB::transaction(fn())` (closure) |
| Propagation rejimləri | 7 variant (REQUIRED, REQUIRES_NEW, NESTED, ...) | Yalnız savepoint-based nested |
| Isolation atribut | `@Transactional(isolation=...)` | `DB::transaction($cb, $attempts, $iso)` |
| Deadlock retry | Manual (`@Retryable`) | Built-in `attempts` parametri |
| Read-only opt | `readOnly = true` (Hibernate dirty-check skip) | Ayrıca `DB::connection('replica')` |
| Timeout | `timeout = 30` | DB driver level (yoxdur direct) |
| Rollback semantikası | `RuntimeException` default, `rollbackFor` ilə genişlət | Hər exception rollback edir |
| Self-invocation | AOP proxy-də KEÇMİR (bug-gen) | Closure-based, problem yoxdur |
| Nested | `Propagation.NESTED` savepoint | Avtomatik savepoint |
| Programmatic | `TransactionTemplate` | `DB::beginTransaction()/commit/rollBack` |
| Reactive | `ReactiveTransactionManager` (R2DBC) | N/A (PHP async nadir) |
| XA / distributed | JTA + Atomikos / Narayana | Praktiki olaraq yoxdur — Saga |
| After commit callback | `TransactionSynchronization.afterCommit()` | `DB::afterCommit()` / `ShouldQueueAfterCommit` |
| Testing rollback | `@Transactional` test class | `DatabaseTransactions` trait |
| Test commit force | `@Commit` | N/A (manual `DB::commit()`) |

---

## Niyə belə fərqlər var?

**Spring-in AOP fəlsəfəsi.** Java-da annotasiya + CGLIB proxy = deklarativ tranzaksiya. Developer metoda `@Transactional` yazır, aspect başında `begin`, sonunda `commit` qoyur. Bu çox güclüdür (propagation, isolation dərin), amma **self-invocation tələsi** yaradır — proxy yalnız kənar çağırışları tutur.

**Laravel-in closure fəlsəfəsi.** PHP-də `DB::transaction(fn () => ...)` açıq və gözlə görünür — nə başlanğıcı, nə sonu gizli deyil. Self-invocation problemi yoxdur, çünki proxy yoxdur. Amma propagation kimi zəngin semantika da yoxdur — nested = savepoint, vəssalam.

**Deadlock retry.** Laravel avtomatik retry aparır (`attempts: 3`). Spring-də bu default deyil — `@Retryable` + Spring Retry lazımdır. PHP-nin request-per-process modelində deadlock adi haldır (Eloquent N+1, chunked update) və Laravel ekosistemi retry-ı "first-class" edib.

**Rollback default.** Spring `RuntimeException` default seçib çünki Java-da checked/unchecked ayrımı var. Laravel bu ayrımı tanımır — hər exception rollback edir. PHP developer üçün sadələşir, Java developer üçün "checked exception commit edir" tələsi qalır (`rollbackFor = Exception.class` lazımdır).

**Reactive transactions.** Spring WebFlux bütün reactive stack qurub — `R2dbcTransactionManager` sürətlə context-ə tranzaksiya yerləşdirir. Laravel-də reactive paradiqma yoxdur — fiber-based Swoole/OpenSwoole Octane-da belə, tranzaksiya adi PDO blocking-dir.

**JTA / XA.** Spring enterprise mirası (JEE) XA-nı birinci sinif göstərir — Atomikos, Narayana hazır. Amma cloud-native dünyada XA az istifadə olunur; Saga üstünlük təşkil edir (hər ikisində manual). Laravel heç vaxt XA rəsmi dəstəkləməyib — PHP ekosisteminin ümumi seçimi.

**AfterCommit semantics.** Hər ikisində var — Spring `TransactionSynchronization.afterCommit()`, Laravel `DB::afterCommit()`. Laravel `ShouldQueueAfterCommit` interface-i queue-ya göndərməni commit sonrasına təxirə salır — bu Laravel-in queue-first fəlsəfəsi ilə uyğundur.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring-də:**
- Propagation 7 rejimi (REQUIRES_NEW, MANDATORY, NEVER, ...) — Laravel-də yalnız default + savepoint
- `readOnly = true` — Hibernate dirty-check skip, DB replica hint
- `timeout` atribut — tranzaksiya səviyyəsində
- `rollbackFor` / `noRollbackFor` — exception-lar üçün dəqiq idarə
- `ReactiveTransactionManager` — R2DBC üçün non-blocking tranzaksiya
- JTA / XA — Atomikos, Narayana ilə distributed 2PC
- `@Transactional(propagation = NESTED)` deklarativ savepoint
- Programmatic `TransactionTemplate` — yüksək səviyyəli
- `@Commit` test annotation — test-i commit etməyə məcbur
- `@Sql` ilə test data yükləmə

**Yalnız Laravel-də:**
- `attempts` parametri — deadlock avto-retry hazır
- `DB::afterCommit()` + `ShouldQueueAfterCommit` — queue ilə tight integration
- `after_commit: true` — bütün queue job-lar default commit sonrası
- Closure-based API — self-invocation problemi yoxdur
- `DatabaseTransactions` trait — hər test avto-rollback
- `lockForUpdate()` Eloquent metodu — SELECT FOR UPDATE qısa yol

---

## Best Practices

1. **Tranzaksiyanı qısa saxla.** HTTP çağırışı, file I/O, email göndərmək tranzaksiya XARİCİNDƏ olmalıdır. Yalnız DB iş tranzaksiyada qalsın.
2. **`readOnly = true`** Spring-də oxu metodları üçün qoy — Hibernate dirty-check atlayır, performans yüksəlir, replica-ya route etmək asanlaşır.
3. **Isolation default-da qal.** `READ_COMMITTED` 99% hal üçün düzgündür. Yalnız inventar / hesabat kimi xüsusi hallarda `REPEATABLE_READ` / `SERIALIZABLE` seç.
4. **Lock əl ilə qoy** — `SELECT ... FOR UPDATE` konkuren konflikti qarşısında dəqiqdir. Spring `@Lock(LockModeType.PESSIMISTIC_WRITE)`, Laravel `lockForUpdate()`.
5. **Spring-də self-invocation-dan qaç** — əgər eyni sinifdən çağırırsansa, metodu ayrı bean-a çıxar və ya `AspectJ` mode istifadə et.
6. **Checked exception-larda Spring-də `rollbackFor = Exception.class`** yaz — yoxsa commit olunacaq.
7. **Laravel-də `DB::afterCommit()`** istifadə et — event/notification commit sonrasına təxir etmək üçün.
8. **Queue job-ları `ShouldQueueAfterCommit`** marka et — DB-də hələ yaradılmamış entity üzərində iş görməsin.
9. **Timeout qoy** (Spring) / SQL `statement_timeout` (Postgres) — saxlanmış tranzaksiya prod DB-ni öldürür.
10. **XA-dan uzaq dur** — əvəzinə Saga + outbox pattern (system-design/51-cdc-outbox.md).
11. **Hər test rollback-da olsun** — `@Transactional` / `DatabaseTransactions` defaultdur, yalnız xüsusi hallarda `@Commit`.
12. **Deadlock-u qəbul et** — Laravel-də `attempts: 3`, Spring-də `@Retryable(retryFor = CannotAcquireLockException.class)`.
13. **Uzun batch job-u chunk-lara böl** — bir tranzaksiyada 1M sətri UPDATE etmə; 10k-lıq chunk-lar və ayrı-ayrı tranzaksiyalar.
14. **N+1-i tranzaksiyaya daxil etmə** — əvvəl `with()` / `@EntityGraph` ilə eager load, sonra tranzaksiya.
15. **Audit log-u `REQUIRES_NEW` / ayrı bağlantıda yaz** — əsas rollback olsa belə audit qalır.

---

## Yekun

Spring `@Transactional` annotasiyası güclü və dərindir — 7 propagation rejimi, 4 isolation, timeout, readOnly, rollbackFor — enterprise ssenarilər üçün ideal. Lakin AOP proxy ilə self-invocation tələsi və checked exception default rollback etməməsi kimi incə pitfalls var. Reactive və JTA tərəfləri də birinci sinif mövcuddur, baxmayaraq ki XA indi Saga-ya yerini verir.

Laravel `DB::transaction()` closure-based API-si sadədir — self-invocation problemi yoxdur, deadlock retry avtomatikdir, `DB::afterCommit()` / `ShouldQueueAfterCommit` queue ilə mükəmməl inteqrasiya olur. Lakin propagation zəngin deyil, timeout atributu yoxdur, distributed tranzaksiya üçün Saga manual yazılmalıdır.

Müsahibədə bu fərqlərin arxasındakı fəlsəfəni izah edə bilsən — AOP proxy vs closure, JTA vs Saga, sinxron vs reactive — yalnız API-ni bilən namizəddən üstün olarsan. Əsas sual həmişə eyni qalır: "Tranzaksiya qısadırmı? Lock düzgün qoyulub? Deadlock retry var? After-commit side-effect düzgün yerdədir?" — bu dörd sualı hər layihədə yoxla.
