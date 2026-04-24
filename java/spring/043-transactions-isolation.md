# 043 — Spring Transaction Isolation Levels — Geniş İzah
**Səviyyə:** İrəli


## Mündəricat
1. [Isolation nədir?](#isolation-nədir)
2. [Dirty Read, Non-repeatable Read, Phantom Read](#dirty-read-non-repeatable-read-phantom-read)
3. [Isolation Level-lər](#isolation-level-lər)
4. [Spring-də konfiqurasiya](#spring-də-konfiqurasiya)
5. [Optimistic vs Pessimistic Locking](#optimistic-vs-pessimistic-locking)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Isolation nədir?

**Transaction Isolation** — paralel transactionların bir-birinin datalarından necə təcrid ediləcəyini müəyyən edir. ACID-in "I" hissəsidir.

Yüksək isolation = daha az concurrent problem, amma daha aşağı performans.
Aşağı isolation = yüksək performans, amma data uyğunsuzluq riski.

---

## Dirty Read, Non-repeatable Read, Phantom Read

```
Dirty Read — Commit olmamış datanı oxumaq:
  T1: UPDATE balance = 1000 (commit etmədi)
  T2: READ balance → 1000 (dirty!)
  T1: ROLLBACK
  T2: 1000-ü istifadə edir — yanlış data!

Non-repeatable Read — Eyni sorğunun fərqli nəticə vermesi:
  T1: READ balance → 500
  T2: UPDATE balance = 800, COMMIT
  T1: READ balance → 800 (dəyişdi!)
  T1: eyni transactionda fərqli nəticə

Phantom Read — Yeni row-ların görünməsi:
  T1: SELECT * WHERE age > 18 → 10 row
  T2: INSERT user (age=25), COMMIT
  T1: SELECT * WHERE age > 18 → 11 row (phantom!)
```

---

## Isolation Level-lər

| Level | Dirty Read | Non-repeatable | Phantom |
|-------|-----------|----------------|---------|
| READ_UNCOMMITTED | ✓ mümkün | ✓ mümkün | ✓ mümkün |
| READ_COMMITTED | ✗ qorunan | ✓ mümkün | ✓ mümkün |
| REPEATABLE_READ | ✗ qorunan | ✗ qorunan | ✓ mümkün |
| SERIALIZABLE | ✗ qorunan | ✗ qorunan | ✗ qorunan |

**Performans:** READ_UNCOMMITTED > READ_COMMITTED > REPEATABLE_READ > SERIALIZABLE

---

## Spring-də konfiqurasiya

```java
// READ_UNCOMMITTED — ən aşağı, ən sürətli
// Dirty data oxunur — adətən istifadə olunmur
@Transactional(isolation = Isolation.READ_UNCOMMITTED)
public BigDecimal getApproximateBalance() {
    // Statistika üçün, dəqiq deyil
    return accountRepository.sumAllBalances();
}

// READ_COMMITTED — PostgreSQL, Oracle default
// Dirty read yoxdur, non-repeatable/phantom mümkündür
@Transactional(isolation = Isolation.READ_COMMITTED)
public Order processOrder(Long orderId) {
    Order order = orderRepository.findById(orderId).orElseThrow();
    // Başqa transaction bu arada order-i dəyişə bilər
    return order;
}

// REPEATABLE_READ — MySQL InnoDB default
// Eyni row-u dəfələrlə oxusaq eyni nəticə
@Transactional(isolation = Isolation.REPEATABLE_READ)
public void generateReport() {
    List<Sale> sales = saleRepository.findAll(); // Cəmi 100
    BigDecimal total = calculateTotal(sales);
    // Bu arada başqa transaction sale əlavə edə bilər (phantom)
    // Amma mövcud row-lar dəyişməz
    reportRepository.save(new Report(total));
}

// SERIALIZABLE — ən yüksək, ən yavaş
// Tamamilə ardıcıl icra kimi davranır
@Transactional(isolation = Isolation.SERIALIZABLE)
public void criticalBankTransfer(Long fromId, Long toId, BigDecimal amount) {
    // Heç bir paralel əməliyyat data uyğunsuzluğuna gətirmir
    Account from = accountRepository.findById(fromId).orElseThrow();
    Account to = accountRepository.findById(toId).orElseThrow();
    from.debit(amount);
    to.credit(amount);
}

// DEFAULT — DB-nin default isolation level-ini istifadə et
@Transactional(isolation = Isolation.DEFAULT)
public void defaultIsolation() {
    // PostgreSQL: READ_COMMITTED
    // MySQL: REPEATABLE_READ
    // Oracle: READ_COMMITTED
}
```

---

## Optimistic vs Pessimistic Locking

### Optimistic Locking — @Version

```java
@Entity
public class Product {

    @Id
    @GeneratedValue
    private Long id;

    private String name;
    private Integer stock;

    @Version // Optimistic locking — versiya nömrəsi
    private Long version;
}

// İki istifadəçi eyni anda dəyişdirsə:
// T1: SELECT product (version=1), UPDATE stock=9, version=2 → OK
// T2: SELECT product (version=1), UPDATE stock=8, version=2 → OptimisticLockException!
// T2-nin version=1 idi, amma DB-də artıq version=2 var

@Service
public class ProductService {

    @Transactional
    public Product updateStock(Long id, int newStock) {
        Product product = productRepository.findById(id).orElseThrow();
        product.setStock(newStock);
        // OptimisticLockException atılsa — retry məntiqi
        return productRepository.save(product);
    }

    // Retry ilə
    @Retryable(value = OptimisticLockingFailureException.class,
               maxAttempts = 3, backoff = @Backoff(delay = 100))
    @Transactional
    public Product updateStockWithRetry(Long id, int newStock) {
        Product product = productRepository.findById(id).orElseThrow();
        product.setStock(newStock);
        return productRepository.save(product);
    }
}
```

### Pessimistic Locking — SELECT FOR UPDATE

```java
@Repository
public interface AccountRepository extends JpaRepository<Account, Long> {

    // PESSIMISTIC_WRITE — SELECT ... FOR UPDATE
    // Başqa transactionlar bu row-u lock açılana qədər oxuya/yaza bilmir
    @Lock(LockModeType.PESSIMISTIC_WRITE)
    @Query("SELECT a FROM Account a WHERE a.id = :id")
    Optional<Account> findByIdForUpdate(@Param("id") Long id);

    // PESSIMISTIC_READ — SELECT ... FOR SHARE
    // Başqa transactionlar oxuya bilir, yaza bilmir
    @Lock(LockModeType.PESSIMISTIC_READ)
    @Query("SELECT a FROM Account a WHERE a.id = :id")
    Optional<Account> findByIdForRead(@Param("id") Long id);
}

@Service
public class TransferService {

    @Transactional
    public void transfer(Long fromId, Long toId, BigDecimal amount) {
        // Deadlock qarşısını almaq üçün həmişə eyni sırada lock al
        Long firstId = Math.min(fromId, toId);
        Long secondId = Math.max(fromId, toId);

        Account first = accountRepository.findByIdForUpdate(firstId).orElseThrow();
        Account second = accountRepository.findByIdForUpdate(secondId).orElseThrow();

        Account from = fromId.equals(firstId) ? first : second;
        Account to = toId.equals(firstId) ? first : second;

        from.debit(amount);
        to.credit(amount);
    }
}
```

### Optimistic vs Pessimistic müqayisəsi

```
Optimistic Locking:
  + Yüksək performans (heç bir DB lock yoxdur)
  + Deadlock riski yoxdur
  - Conflict olduqda retry lazımdır
  - Yüksək conflict ssenarilərdə zəifdir
  İdeal: az conflict, çox read əməliyyatı

Pessimistic Locking:
  + Data consistency zəmanəti verir
  + Conflict olduqda wait edir, xəta atmır
  - Daha aşağı performans (DB lock)
  - Deadlock riski var
  İdeal: yüksək conflict, bank transfer, inventory deduction
```

---

## İntervyu Sualları

### 1. Phantom read nədir?
**Cavab:** Eyni SELECT sorğusu bir transaction daxilində fərqli say row qaytaranda baş verir. T1 `WHERE age > 18` sorğusu icra edir (10 row), T2 yeni row əlavə edir, T1 eyni sorğunu yenidən icra edəndə 11 row görür. SERIALIZABLE ilə həll olunur.

### 2. @Version necə işləyir?
**Cavab:** Entity-dəki `@Version` field hər update-də avtomatik artır. Update zamanı Hibernate `WHERE id = ? AND version = ?` şərtini əlavə edir. Başqa transaction eyni aradan versiyonu dəyişdirirsə, WHERE şərti heç bir row tapmir → `OptimisticLockingFailureException` atılır.

### 3. PESSIMISTIC_WRITE ilə deadlock necə olur?
**Cavab:** T1: A-nı lock alır, B-ni gözləyir. T2: B-ni lock alır, A-nı gözləyir. Hər ikisi bir-birini gözlədiyindən deadlock baş verir. Həll: hər zaman resource-ları eyni sırada lock almaq (ID-yə görə sort edin).

### 4. Hansı isolation level daha çox istifadə edilir?
**Cavab:** Əksər production sistemlər READ_COMMITTED (PostgreSQL default) istifadə edir. Bank köçürmə kimi kritik ssenarilərdə REPEATABLE_READ yaxud SERIALIZABLE. Yüksək performans tələb olunanda READ_COMMITTED + Optimistic Locking kombinasiyası populyardır.

### 5. Optimistic Locking fail olduqda nə etmək lazımdır?
**Cavab:** `OptimisticLockingFailureException` tutulur və əməliyyat retry edilir. Spring Retry `@Retryable` annotasiyası bu prosesi avtomatlaşdırır. Retry sayı məhdudlanmalıdır (3-5 cəhd) — əks halda sonsuz loop riski var.

*Son yenilənmə: 2026-04-10*
