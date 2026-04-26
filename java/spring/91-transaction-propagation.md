# 91 — Transaction Propagation — Spring-də @Transactional Dərinliyə

> **Seviyye:** Middle ⭐⭐

## Mündəricat
1. [Transaction Propagation nədir?](#transaction-propagation-nədir)
2. [7 Propagation növü](#7-propagation-növü)
3. [Isolation Levels](#isolation-levels)
4. [Self-Invocation problemi](#self-invocation-problemi)
5. [Read-Only transactions](#read-only-transactions)
6. [Laravel ilə müqayisə](#laravel-ilə-müqayisə)
7. [İntervyu Sualları](#intervyu-sualları)

---

## Transaction Propagation nədir?

`@Transactional(propagation = ...)` — mövcud bir transaction varsa, yeni `@Transactional` method çağırıldıqda **nə baş verməlidir** sualına cavab verir.

```
// OrderService.placeOrder() → @Transactional
//   → InventoryService.reserve() → @Transactional(propagation = ?)
//   → PaymentService.charge() → @Transactional(propagation = ?)

// Sual: reserve() öz transactionunu açmışmı, yoxsa placeOrder()-un transaction-una qoşulub?
// Cavab: Propagation type müəyyən edir.
```

---

## 7 Propagation növü

### REQUIRED (default)

Mövcud transaction varsa — ona qoşul. Yoxdursa — yeni aç.

```java
@Service
public class OrderService {

    @Transactional  // TX-1 açır
    public void placeOrder(Order order) {
        orderRepo.save(order);
        inventoryService.reserve(order); // TX-1-ə qoşulur
        paymentService.charge(order);    // TX-1-ə qoşulur
    }
}

@Service
public class InventoryService {

    @Transactional(propagation = Propagation.REQUIRED) // mövcud TX-1-ə qoşulur
    public void reserve(Order order) {
        // burada exception → bütün TX-1 rollback olur!
        inventory.decrease(order.getProductId(), order.getQty());
    }
}
```

**Diqqət:** `reserve()` exception atsа, `placeOrder()` da rollback olur — hər şey bir transactiondadır.

---

### REQUIRES_NEW

Həmişə yeni transaction açır. Mövcud transaction **suspend** olunur.

```java
@Service
public class AuditService {

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void logAction(String action) {
        // Öz ayrı TX-2-si var
        // TX-1 rollback olsa belə, audit log qalır!
        auditRepo.save(new AuditLog(action));
    }
}

@Service
public class OrderService {

    @Transactional  // TX-1
    public void placeOrder(Order order) {
        orderRepo.save(order);
        auditService.logAction("order.placed"); // TX-2 açır, TX-1 suspend

        throw new RuntimeException("Sifarişdə xəta!");
        // TX-1 rollback olur → order silinir
        // TX-2 isə artıq commit olunub → audit qalır
    }
}
```

**İstifadə yeri:** Audit log, email notification — ana əməliyyat uğursuz olsa belə, bunlar qalmalıdır.

---

### NESTED

Ana transaction içində **savepoint** yaradır. Inner rollback yalnız o hissəni geri alır.

```java
@Transactional(propagation = Propagation.NESTED)
public void optionalStep(Order order) {
    // Savepoint: SP1
    try {
        riskyRepo.save(order);
    } catch (Exception e) {
        // Yalnız SP1-dən bu yana olanlar rollback
        // Ana transaction davam edir
        log.warn("Optional step failed, continuing");
    }
}
```

**Diqqət:** JPA + Hibernate-lə NESTED tam işləmir. JDBC-də düzgün işləyir. Praktikada REQUIRES_NEW daha çox istifadə olunur.

---

### SUPPORTS

Transaction varsa iştirak edir, yoxdursa da işləyir (transaction olmadan).

```java
@Transactional(propagation = Propagation.SUPPORTS)
public List<Order> findAll() {
    // Transaction varsa — onunla
    // Yoxdursa — transaction-sız oxur
    return orderRepo.findAll();
}
```

---

### NOT_SUPPORTED

Transaction-suz işləməlidir. Mövcud transaction suspend olunur.

```java
@Transactional(propagation = Propagation.NOT_SUPPORTED)
public void sendEmail(String to, String body) {
    // Email göndərmək transaction içindən olmaz (uzun sürməməli)
    emailService.send(to, body);
}
```

---

### MANDATORY

Mövcud transaction **mütləq** olmalıdır. Yoxdursa — exception.

```java
@Transactional(propagation = Propagation.MANDATORY)
public void updateStock(int productId, int qty) {
    // Yalnız transaction içindən çağrılabilər
    // Birbaşa çağırılarsa: IllegalTransactionStateException
    stockRepo.update(productId, qty);
}
```

---

### NEVER

Transaction varsa — exception atar.

```java
@Transactional(propagation = Propagation.NEVER)
public List<Report> generateReport() {
    // Transaction içindən çağrılmamalıdır
    // Long-running report generation
    return reportRepo.findAll();
}
```

---

## Isolation Levels

```java
@Transactional(isolation = Isolation.READ_COMMITTED) // default PostgreSQL
public void process() { ... }
```

| Isolation | Dirty Read | Non-Repeatable Read | Phantom Read |
|-----------|-----------|---------------------|--------------|
| READ_UNCOMMITTED | Var | Var | Var |
| READ_COMMITTED | Yoxdur | Var | Var |
| REPEATABLE_READ | Yoxdur | Yoxdur | Var |
| SERIALIZABLE | Yoxdur | Yoxdur | Yoxdur |

**Praktikada:** PostgreSQL default-u `READ_COMMITTED`. Yüksək isolation = aşağı performance.

```java
// Konkret problem üçün:
@Transactional(isolation = Isolation.REPEATABLE_READ)
public void processPayment(Long orderId) {
    Order order = orderRepo.findById(orderId).orElseThrow();
    // Eyni transaction içində yenidən oxusanız, eyni dəyər gəlir
    // Dirty read, non-repeatable read olmaz
    order.setStatus("PAID");
    orderRepo.save(order);
}
```

---

## Self-Invocation problemi

**Ən çox edilən səhv.** Spring `@Transactional`-ı proxy ilə həyata keçirir. Eyni class içindən çağırış proxy-ni bypass edir.

```java
@Service
public class OrderService {

    @Transactional
    public void placeOrder(Order order) {
        orderRepo.save(order);
        this.sendNotification(order); // ← PROBLEM! Proxy bypass olur
    }

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void sendNotification(Order order) {
        // Bu annotation işləMİR!
        // Çünki this.sendNotification() çağırışı proxy-dən keçmir
        notificationRepo.save(new Notification(order));
    }
}
```

**Həll 1 — Ayrı service:**
```java
@Service
public class NotificationService {

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void send(Order order) {
        notificationRepo.save(new Notification(order));
    }
}

@Service
public class OrderService {
    @Autowired
    private NotificationService notificationService;

    @Transactional
    public void placeOrder(Order order) {
        orderRepo.save(order);
        notificationService.send(order); // ✅ Proxy üzərindən keçir
    }
}
```

**Həll 2 — ApplicationContext-dən self-reference:**
```java
@Service
public class OrderService {

    @Autowired
    private ApplicationContext ctx;

    @Transactional
    public void placeOrder(Order order) {
        orderRepo.save(order);
        // Proxy-dən keçirir:
        ctx.getBean(OrderService.class).sendNotification(order);
    }

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void sendNotification(Order order) {
        notificationRepo.save(new Notification(order));
    }
}
```

---

## Read-Only transactions

```java
@Transactional(readOnly = true)
public List<Order> findUserOrders(Long userId) {
    return orderRepo.findByUserId(userId);
}
```

**Faydaları:**
- Hibernate **dirty checking** deaktiv olur → daha sürətli
- DB driver read replica-ya yönləndirə bilər
- JPA snapshot yaratmır

**Qayda:** Yalnız oxuma əməliyyatları üçün. Write metodlarda `readOnly = true` istifadə etmək exception verə bilər.

---

## Laravel ilə müqayisə

```php
// Laravel — sadə, tek növ transaction:
DB::transaction(function () {
    Order::create([...]);
    Inventory::decrease(...);
    // Biri fail → hamısı rollback
});

// Manual savepoint (nadir):
DB::transaction(function () {
    DB::savepoint('sp1');
    try {
        riskyOperation();
    } catch (Exception $e) {
        DB::rollbackToSavepoint('sp1');
    }
});
```

```java
// Spring — 7 növ propagation, deklarativ:
@Transactional(propagation = Propagation.REQUIRES_NEW)
public void auditLog(String action) { ... }
```

**Əsas fərq:**
- Laravel-də propagation konsepti yoxdur — hər `DB::transaction()` yeni transaction açır
- Spring-də `@Transactional` annotasiyaları composition-a imkan verir
- PHP hər request üçün yenidən başlayır; Java long-lived thread-lər — transaction leak riski var

---

## İntervyu Sualları

**S: REQUIRED ilə REQUIRES_NEW arasındakı fərq?**
C: REQUIRED mövcud transaction-a qoşulur; xəta olsa ikisi də rollback olur. REQUIRES_NEW həmişə yeni, müstəqil transaction açır; parent rollback olsa da child öz commitini saxlayır.

**S: Self-invocation niyə problem yaradır?**
C: Spring `@Transactional`-ı CGLIB/JDK proxy ilə həyata keçirir. `this.method()` çağırışı proxy-dən keçmir, birbaşa class-a gedir — buna görə `@Transactional` annotation-ı ignore edilir.

**S: readOnly = true niyə istifadə olunur?**
C: Hibernate dirty checking deaktiv olur, JPA 1st level cache snapshot yaratmır, DB sürücüsü read replica-ya yönləndirə bilər — hamısı birlikdə oxuma performansını artırır.

**S: Unchecked vs Checked exception — fərqi nədir?**
C: Spring default olaraq yalnız unchecked exception-da (RuntimeException) rollback edir. Checked exception rollback etmir. `@Transactional(rollbackFor = Exception.class)` bütün exception-larda rollback edir.

```java
@Transactional(rollbackFor = Exception.class)
public void process() throws IOException {
    // Checked exception da rollback edəcək
}
```
