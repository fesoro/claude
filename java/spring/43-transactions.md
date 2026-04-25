# 43 — Spring Transactions (@Transactional) — Geniş İzah

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [@Transactional nədir?](#transactional-nədir)
2. [Propagation növləri](#propagation-növləri)
3. [Rollback qaydaları](#rollback-qaydaları)
4. [readOnly=true](#readonly-true)
5. [Timeout](#timeout)
6. [Praktik nümunələr](#praktik-nümunələr)
7. [İntervyu Sualları](#intervyu-sualları)

---

## @Transactional nədir?

**@Transactional** — metod ya da sinif üçün transaction idarəetməsini Spring-ə həvalə edən annotasiyalardır. Spring AOP proxy vasitəsilə işləyir.

```java
@Service
public class OrderService {

    private final OrderRepository orderRepository;
    private final InventoryService inventoryService;
    private final PaymentService paymentService;

    // YANLIŞ — transaction yoxdur
    public void placeOrder(Order order) {
        orderRepository.save(order);
        inventoryService.reduce(order.getItems()); // Burda exception → DB-də order qalır!
        paymentService.charge(order);
    }

    // DOĞRU — atomik əməliyyat
    @Transactional
    public void placeOrder(Order order) {
        orderRepository.save(order);
        inventoryService.reduce(order.getItems()); // Exception → hər şey geri alınır
        paymentService.charge(order);
    }
}
```

**Default davranış:**
- `propagation = REQUIRED` — mövcud transaction varsa istifadə et, yoxdursa yeni aç
- `isolation = DEFAULT` — DB-nin default isolation level-i
- `readOnly = false`
- `rollbackFor` — yalnız `RuntimeException` və `Error` üçün rollback
- `timeout = -1` — timeout yoxdur

---

## Propagation növləri

```java
@Service
public class TransactionDemo {

    // REQUIRED (default) — mövcud transaction-a qoşul, yoxdursa yeni başlat
    @Transactional(propagation = Propagation.REQUIRED)
    public void required() {
        // Caller transaction-ı varsa — ona qoşulur
        // Yoxdursa — yeni transaction açır
    }

    // REQUIRES_NEW — həmişə yeni transaction açır, mövcudu dayandırır
    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void requiresNew() {
        // Caller transaction-ından MÜSTƏQIL çalışır
        // Caller rollback etse belə, bu commit ola bilər
        // !! Audit log, notification göndərmək üçün ideal !!
    }

    // SUPPORTS — transaction varsa iştirak edir, yoxdursa transaction-suz işləyir
    @Transactional(propagation = Propagation.SUPPORTS)
    public void supports() {
        // Read-only əməliyyatlar üçün
    }

    // NOT_SUPPORTED — transaction-u dayandırır, transaction-suz işləyir
    @Transactional(propagation = Propagation.NOT_SUPPORTED)
    public void notSupported() {
        // Uzun müddətli read-only əməliyyat
    }

    // NEVER — transaction varsa exception atır
    @Transactional(propagation = Propagation.NEVER)
    public void never() {
        // Bu metod heç vaxt transaction daxilində çağırılmamalıdır
    }

    // MANDATORY — mövcud transaction olmalıdır, yoxdursa exception
    @Transactional(propagation = Propagation.MANDATORY)
    public void mandatory() {
        // Bu metod yalnız başqa @Transactional metod tərəfindən çağırılmalıdır
    }

    // NESTED — savepoint yaradır (REQUIRED-in alt transaction-u)
    @Transactional(propagation = Propagation.NESTED)
    public void nested() {
        // Ana transaction rollback etsə, bu da rollback olur
        // Bu rollback etse, ana transaction davam edə bilər (savepoint)
    }
}
```

**REQUIRES_NEW praktik nümunəsi:**
```java
@Service
public class OrderService {

    private final AuditService auditService;

    @Transactional
    public void placeOrder(Order order) {
        try {
            processOrder(order);
        } catch (Exception e) {
            // Order uğursuz — amma audit qeyd edilməlidir!
            auditService.logFailure(order, e); // REQUIRES_NEW — öz transaction-ında
            throw e;
        }
    }
}

@Service
public class AuditService {

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void logFailure(Order order, Exception e) {
        // Order transaction rollback olsa belə bu commit olur
        auditRepository.save(new AuditLog(order.getId(), "FAILED", e.getMessage()));
    }
}
```

---

## Rollback qaydaları

```java
// Default — yalnız RuntimeException və Error üçün rollback
@Transactional
public void defaultRollback() throws Exception {
    orderRepository.save(order);
    throw new IOException("IO xəta"); // ← Rollback OLMUR! (checked exception)
}

// Checked exception üçün rollback
@Transactional(rollbackFor = Exception.class)
public void rollbackOnCheckedException() throws Exception {
    orderRepository.save(order);
    throw new IOException("IO xəta"); // ← Rollback olur
}

// Spesifik exception üçün
@Transactional(rollbackFor = {OrderException.class, PaymentException.class})
public void specificRollback() throws OrderException {
    // ...
}

// Rollback etməmək — noRollbackFor
@Transactional(noRollbackFor = EntityNotFoundException.class)
public void noRollbackOnNotFound() {
    // EntityNotFoundException atılsa rollback olmur
}
```

---

## readOnly=true

```java
// readOnly=true — DB optimizasiyası + dirty checking yoxdur
@Transactional(readOnly = true)
public List<UserDto> getAllUsers() {
    // Hibernate dirty checking etmir — daha sürətli
    // DB replica-ya yönləndirilə bilər (read-only replica)
    return userRepository.findAll().stream()
        .map(userMapper::toDto)
        .collect(Collectors.toList());
}

@Transactional(readOnly = true)
public Page<Product> searchProducts(String query, Pageable pageable) {
    return productRepository.findByNameContaining(query, pageable);
}

// Repository-də default — read metodlar readOnly
@Repository
public interface UserRepository extends JpaRepository<User, Long> {

    @Transactional(readOnly = true)
    @Override
    List<User> findAll();

    @Transactional(readOnly = true)
    Optional<User> findByEmail(String email);
}

// Sinif üzərindəki @Transactional — default readOnly
@Service
@Transactional(readOnly = true) // Bütün metodlar readOnly
public class UserQueryService {

    public User findById(Long id) {
        return userRepository.findById(id).orElseThrow();
    }

    public List<User> findAll() {
        return userRepository.findAll();
    }

    // Yazma əməliyyatı üçün override
    @Transactional // readOnly=false (default)
    public User save(User user) {
        return userRepository.save(user);
    }
}
```

---

## Timeout

```java
// 30 saniyə keçsə — TransactionTimedOutException
@Transactional(timeout = 30)
public void longRunningOperation() {
    // 30 saniyə daxilində bitməlidir
    processLargeDataset();
}

// Deadlock riskini azaltmaq üçün qısa timeout
@Transactional(timeout = 5)
public void criticalOperation() {
    orderRepository.lockAndUpdate(orderId);
}
```

---

## Praktik nümunələr

```java
@Service
public class TransferService {

    private final AccountRepository accountRepository;

    // Bank köçürməsi — atomic əməliyyat
    @Transactional
    public void transfer(Long fromId, Long toId, BigDecimal amount) {
        Account from = accountRepository.findByIdWithLock(fromId); // PESSIMISTIC_WRITE
        Account to = accountRepository.findByIdWithLock(toId);

        if (from.getBalance().compareTo(amount) < 0) {
            throw new InsufficientFundsException("Balans kifayət deyil");
        }

        from.debit(amount);
        to.credit(amount);

        accountRepository.save(from);
        accountRepository.save(to);
        // Exception atılsa — hər iki dəyişiklik geri alınır
    }
}

// Repository-də
@Repository
public interface AccountRepository extends JpaRepository<Account, Long> {

    @Lock(LockModeType.PESSIMISTIC_WRITE)
    @Query("SELECT a FROM Account a WHERE a.id = :id")
    Optional<Account> findByIdWithLock(@Param("id") Long id);
}
```

---

## İntervyu Sualları

### 1. @Transactional işləməyi dayandırdıqda niyə?
**Cavab:** İki əsas səbəb: (1) **Self-invocation** — aynı class daxilindən çağırma proxy-dən keçmir. (2) **Private metod** — CGLIB proxy private metodları override edə bilmir. Həll: metodu ayrı `@Service`-ə köçürmək.

### 2. REQUIRED vs REQUIRES_NEW fərqi?
**Cavab:** `REQUIRED` — mövcud transaction varsa ona qoşulur, yoxdursa yeni açır (default). `REQUIRES_NEW` — həmişə yeni müstəqil transaction açır, mövcudu dayandırır. Audit log, notification kimi caller rollback etse belə qeyd edilməli əməliyyatlar üçün `REQUIRES_NEW` istifadə edilir.

### 3. Niyə checked exception-da rollback olmur?
**Cavab:** Java-nın `checked exception` konsepti — bu exception-ları gözləmək və idarə etmək mümkündür, buna görə Spring default olaraq rollback etmir. `rollbackFor = Exception.class` ilə bütün exception-lar üçün rollback aktivləşdirilə bilər.

### 4. readOnly=true nə fayda verir?
**Cavab:** (1) Hibernate dirty checking-i devre dışı buraxır — daha az yaddaş, daha sürətli flush. (2) Bəzi DB driver-ları/ORM-lər read-only transaction üçün optimizasiyalar edir. (3) Spring Data JPA routing konfiguruyasiyası ilə read replica-ya yönləndirə bilər. Yalnız read əməliyyatları üçün tövsiyə olunur.

### 5. @Transactional sinfin özünə qoyulsaqdı nə baş verir?
**Cavab:** Sinifdəki bütün public metodlara tətbiq olunur. Method-level annotation sinif annotation-ını override edir. Pattern: service-ə `@Transactional(readOnly = true)` qoy, write metodlarına `@Transactional` (readOnly=false) qoy — bu yanaşma default olaraq read optimizasiya verir.

*Son yenilənmə: 2026-04-10*
