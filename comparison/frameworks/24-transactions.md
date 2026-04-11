# Tranzaksiyalar (Transactions)

## Giris

Verilenis bazasi tranzaksiyalari bir nece emeliyyatin bir butov olaraq icra olunmasini temin edir - ya hamisi ugurlu olur, ya da hec biri. Meselen, bank kocurmesinde bir hesabdan pul cixarmaq ve diger hesaba yazmaq eyni tranzaksiyada olmalidir. Spring `@Transactional` annotasiyasi ile AOP esasli deklarativ tranzaksiyalar teklif edir, Laravel ise `DB::transaction()` ve manual tranzaksiya idaraesi ile isleyir.

## Spring-de istifadesi

### @Transactional annotasiyasi

```java
@Service
public class TransferService {

    private final AccountRepository accountRepository;
    private final TransferLogRepository transferLogRepository;

    public TransferService(AccountRepository accountRepository,
                           TransferLogRepository transferLogRepository) {
        this.accountRepository = accountRepository;
        this.transferLogRepository = transferLogRepository;
    }

    @Transactional
    public void transfer(Long fromId, Long toId, BigDecimal amount) {
        Account from = accountRepository.findById(fromId)
            .orElseThrow(() -> new AccountNotFoundException(fromId));
        Account to = accountRepository.findById(toId)
            .orElseThrow(() -> new AccountNotFoundException(toId));

        if (from.getBalance().compareTo(amount) < 0) {
            throw new InsufficientFundsException(
                "Hesabda kifayet qeder vesait yoxdur");
        }

        from.setBalance(from.getBalance().subtract(amount));
        to.setBalance(to.getBalance().add(amount));

        accountRepository.save(from);
        accountRepository.save(to);

        // Log yazmaq - her hansi xetada hamisi geri qaytarilir
        TransferLog log = new TransferLog(fromId, toId, amount, LocalDateTime.now());
        transferLogRepository.save(log);
    }
}
```

### Rollback qaydalari

```java
@Service
public class OrderService {

    // Default: yalniz unchecked exception-larda (RuntimeException) rollback olur
    @Transactional
    public void createOrder(OrderDto dto) {
        // RuntimeException atilsa -> rollback
        // Checked Exception atilsa -> commit (!)
    }

    // Mueyyen exception-da rollback
    @Transactional(rollbackFor = Exception.class)
    public void createOrderSafe(OrderDto dto) throws Exception {
        // Isteniley exception-da rollback olur
    }

    // Mueyyen exception-da rollback ETME
    @Transactional(noRollbackFor = EmailException.class)
    public void createOrderWithEmail(OrderDto dto) {
        orderRepository.save(mapToOrder(dto));

        try {
            emailService.sendConfirmation(dto.getEmail());
        } catch (EmailException e) {
            // E-poct xetasi tranzaksiyanin geri qaytarilmasina sebeb olmur
            log.warn("E-poct gonderile bilmedi: {}", e.getMessage());
        }
    }
}
```

### Propagation (Yayilma) seviyeleri

```java
@Service
public class PropagationExamples {

    // REQUIRED (default) - movcud tranzaksiyani istifade et, yoxdursa yenisini yarat
    @Transactional(propagation = Propagation.REQUIRED)
    public void requiredExample() {
        // Eger bu metod basqa @Transactional metoddan cagirilibsa,
        // eyni tranzaksiyada isleyir
    }

    // REQUIRES_NEW - her zaman yeni tranzaksiya yarat
    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void requiresNewExample() {
        // Movcud tranzaksiya dayandrilir, yeni tranzaksiya acilir
        // Bu metod bitdikden sonra evvelki tranzaksiya davam edir
    }

    // NESTED - ic-ice tranzaksiya (savepoint)
    @Transactional(propagation = Propagation.NESTED)
    public void nestedExample() {
        // Savepoint ile isleyir - bu hisse rollback olsa,
        // ust tranzaksiya davam ede biler
    }

    // SUPPORTS - tranzaksiya varsa istifade et, yoxdursa tranzaksiyasiz isle
    @Transactional(propagation = Propagation.SUPPORTS)
    public void supportsExample() {}

    // NOT_SUPPORTED - tranzaksiyanin xaricinde isle
    @Transactional(propagation = Propagation.NOT_SUPPORTED)
    public void notSupportedExample() {}

    // MANDATORY - tranzaksiya olmalidir, yoxsa xeta verir
    @Transactional(propagation = Propagation.MANDATORY)
    public void mandatoryExample() {}

    // NEVER - tranzaksiya olmamaliditr, varsa xeta verir
    @Transactional(propagation = Propagation.NEVER)
    public void neverExample() {}
}
```

### Praktik numune: REQUIRES_NEW istifadesi

```java
@Service
public class PaymentService {

    private final PaymentRepository paymentRepository;
    private final AuditService auditService;

    @Transactional
    public void processPayment(PaymentRequest request) {
        // Odenish isleyirik
        Payment payment = new Payment(request);
        paymentRepository.save(payment);

        try {
            externalGateway.charge(request);
            payment.setStatus(PaymentStatus.COMPLETED);
        } catch (PaymentException e) {
            payment.setStatus(PaymentStatus.FAILED);
            // Butun tranzaksiya rollback olacaq...

            // AMMA audit log-u itirmemeliyik!
            auditService.logFailedPayment(payment, e);
            throw e;
        }
    }
}

@Service
public class AuditService {

    // REQUIRES_NEW - ust tranzaksiya rollback olsa bele,
    // audit log-u saxlanacaq
    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void logFailedPayment(Payment payment, Exception error) {
        AuditLog log = new AuditLog();
        log.setAction("PAYMENT_FAILED");
        log.setPaymentId(payment.getId());
        log.setError(error.getMessage());
        log.setTimestamp(Instant.now());
        auditLogRepository.save(log);
    }
}
```

### Isolation (Tecridi) seviyeleri

```java
@Service
public class IsolationExamples {

    // READ_UNCOMMITTED - en zeif, dirty read mumkun
    @Transactional(isolation = Isolation.READ_UNCOMMITTED)
    public void readUncommitted() {}

    // READ_COMMITTED - yalniz commit olunmus melumatlari oxu
    @Transactional(isolation = Isolation.READ_COMMITTED)
    public void readCommitted() {}

    // REPEATABLE_READ - eyni sorgu her zaman eyni netice verir
    @Transactional(isolation = Isolation.REPEATABLE_READ)
    public void repeatableRead() {}

    // SERIALIZABLE - en guclu, tam ardicil icra
    @Transactional(isolation = Isolation.SERIALIZABLE)
    public void serializable() {}
}

// Praktik numune
@Service
public class InventoryService {

    // Stok yoxlamasi ucun SERIALIZABLE - race condition-in qarsisini alir
    @Transactional(isolation = Isolation.SERIALIZABLE)
    public void decreaseStock(Long productId, int quantity) {
        Product product = productRepository.findById(productId)
            .orElseThrow();

        if (product.getStock() < quantity) {
            throw new InsufficientStockException();
        }

        product.setStock(product.getStock() - quantity);
        productRepository.save(product);
    }
}
```

### readOnly tranzaksiya

```java
@Service
public class ReportService {

    // Yalniz oxuma tranzaksiyasi - performans optimizasiyasi
    @Transactional(readOnly = true)
    public List<OrderSummary> getMonthlyReport(YearMonth month) {
        return orderRepository.findByMonth(month.getMonthValue(), month.getYear());
    }

    // Timeout ile tranzaksiya
    @Transactional(timeout = 30) // 30 saniye
    public void longRunningOperation() {
        // ...
    }
}
```

### Vacib qeyd: Proxy mehdudiyyeti

```java
@Service
public class ProxyLimitation {

    @Transactional
    public void outerMethod() {
        // Bu isleyir - xaricden cagirilir, proxy intercept edir
        innerMethod();
        // DIQQET: innerMethod()-un @Transactional-i ISLEMEYECEK!
        // Cunki eyni sinif daxilinden cagirilis proxy-den kecmir
    }

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void innerMethod() {
        // Eyni sinifden cagirildiginda yeni tranzaksiya YARANMAYACAQ
    }
}

// HELL: Ayri service-e cixarmaq
@Service
public class InnerService {
    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void innerMethod() {
        // Indi isleyecek - basqa sinifden cagirilir, proxy isleyir
    }
}
```

## Laravel-de istifadesi

### DB::transaction() ile avtomatik tranzaksiya

```php
use Illuminate\Support\Facades\DB;

class TransferService
{
    public function transfer(int $fromId, int $toId, float $amount): void
    {
        DB::transaction(function () use ($fromId, $toId, $amount) {
            $from = Account::findOrFail($fromId);
            $to = Account::findOrFail($toId);

            if ($from->balance < $amount) {
                throw new InsufficientFundsException(
                    'Hesabda kifayet qeder vesait yoxdur'
                );
            }

            $from->decrement('balance', $amount);
            $to->increment('balance', $amount);

            TransferLog::create([
                'from_account_id' => $fromId,
                'to_account_id' => $toId,
                'amount' => $amount,
            ]);
        });
        // Exception atilsa avtomatik rollback olur
        // Ugurlu biterse avtomatik commit olur
    }
}
```

### Manual tranzaksiya idaraesi

```php
class OrderService
{
    public function createOrder(array $data): Order
    {
        DB::beginTransaction();

        try {
            $order = Order::create([
                'user_id' => auth()->id(),
                'total' => 0,
            ]);

            $total = 0;
            foreach ($data['items'] as $item) {
                $product = Product::findOrFail($item['product_id']);

                if ($product->stock < $item['quantity']) {
                    throw new InsufficientStockException(
                        "{$product->name} ucun kifayet qeder stok yoxdur"
                    );
                }

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $item['quantity'],
                    'price' => $product->price,
                ]);

                $product->decrement('stock', $item['quantity']);
                $total += $product->price * $item['quantity'];
            }

            $order->update(['total' => $total]);

            DB::commit();

            return $order;

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
```

### Retry mexanizmi

```php
// DB::transaction() ikinci parametr olaraq retry sayi qebul edir
DB::transaction(function () {
    // Deadlock olduqda avtomatik yeniden cehd edir
    $user = User::lockForUpdate()->find(1);
    $user->increment('balance', 100);
}, 5); // 5 defe yeniden cehd et
```

### Savepoint-ler

```php
class ComplexOrderService
{
    public function processOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create($data['order']);

            // IC-ice tranzaksiya - avtomatik savepoint yaranir
            try {
                DB::transaction(function () use ($order, $data) {
                    $this->processPayment($order, $data['payment']);
                });
            } catch (PaymentException $e) {
                // Yalniz odenish hissesi rollback olur (savepoint-e)
                // Sifaris saxlanir
                $order->update(['status' => 'payment_failed']);
                Log::warning('Odenish ugursuz oldu', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return $order;
        });
    }
}
```

### Pessimistic Locking

```php
class InventoryService
{
    public function decreaseStock(int $productId, int $quantity): void
    {
        DB::transaction(function () use ($productId, $quantity) {
            // SELECT ... FOR UPDATE - digerleri bu setri deyise bilmez
            $product = Product::lockForUpdate()->findOrFail($productId);

            if ($product->stock < $quantity) {
                throw new InsufficientStockException();
            }

            $product->decrement('stock', $quantity);
        });
    }

    // Shared lock - oxuma ucun kilidlemek
    public function getStockLevel(int $productId): int
    {
        return DB::transaction(function () use ($productId) {
            $product = Product::sharedLock()->findOrFail($productId);
            return $product->stock;
        });
    }
}
```

### Eloquent model hadiselerinde tranzaksiya

```php
// afterCommit - tranzaksiya ugurla bitdikden sonra isleyir
class Order extends Model
{
    protected static function booted(): void
    {
        static::created(function (Order $order) {
            // PROBLEM: tranzaksiya henuz commit olmaya biler!
            // Bildiris gondersek ve tranzaksiya rollback olsa, yanlisdir
        });
    }
}

// DOGRU YANASMA:
// Observer-de afterCommit istifade etmek
class OrderObserver
{
    public $afterCommit = true;

    public function created(Order $order): void
    {
        // Bu yalniz tranzaksiya commit olduqdan sonra isleyir
        Notification::send($order->user, new OrderCreatedNotification($order));
    }
}

// Queued job-larda afterCommit
dispatch(new ProcessOrder($order))->afterCommit();
```

### Birden cox database baglantisi ile tranzaksiya

```php
// Ferqli database-lerde tranzaksiya
DB::connection('mysql')->transaction(function () {
    // MySQL emeliyyatlari
});

DB::connection('pgsql')->transaction(function () {
    // PostgreSQL emeliyyatlari
});

// DIQQET: Laravel distributed transaction desteklsemir
// Iki ferqli DB-de atomik tranzaksiya lazimsa, manual hell yazmaq lazimdir
```

## Esas ferqler

| Xususiyyet | Spring | Laravel |
|---|---|---|
| **Esas yanasma** | `@Transactional` annotasiya (deklarativ) | `DB::transaction()` closure (proqramci) |
| **Propagation** | 7 seviye (REQUIRED, REQUIRES_NEW, ...) | Yoxdur (savepoint ile oxsar) |
| **Isolation** | Annotasiya ile mueyyen etmek | DB seviyyesinde konfiqurasiya |
| **Rollback** | Avtomatik (RuntimeException ucun) | Closure-da exception atilsa |
| **Rollback qaydalari** | `rollbackFor`, `noRollbackFor` | Yoxdur - her exception rollback edir |
| **readOnly** | `@Transactional(readOnly=true)` | Yoxdur (built-in) |
| **Timeout** | `timeout` parametri | Yoxdur (built-in) |
| **Retry** | Manual | `DB::transaction($fn, $retries)` |
| **Savepoint** | `NESTED` propagation | Ic-ice `DB::transaction()` |
| **Pessimistic lock** | `@Lock` annotasiyasi | `lockForUpdate()`, `sharedLock()` |
| **Proxy mehdudiyyeti** | Var (eyni sinifden cagirma) | Yoxdur (closure esasli) |

## Niye bele ferqler var?

**Spring-in yanasmasi:** Spring tranzaksiya idaraesini AOP vasitesile heyata kecirir. `@Transactional` annotasiyasi proxy yaradir ve metod cagirildiqda avtomatik olaraq tranzaksiya acir/bagladlir. Bu deklarativ yanasma kodu temiz saxlayir - tranzaksiya metiqi business logic-den ayridir. Propagation seviyeleri murakkeb enterprise ssenarileri ucun lazimdir - meselen, audit log tranzaksiyasinin esas tranzaksiyadan asili olmamasi.

**Laravel-in yanasmasi:** Laravel closure esasli yanasma istifade edir - `DB::transaction(function () { ... })`. Bu yanasma daha sadedir ve PHP-nin dinamik tebietine uygundir. Propagation seviyeleri yoxdur, cunki PHP-nin qisamuddeitli proses modeli enterprise Java-nin murakkeb tranzaksiya ssenarilarini tez-tez teleb etmir. Ic-ice tranzaksiyalar avtomatik olaraq savepoint-lere cevrilir.

**Proxy mehdudiyyeti:** Spring-in en boyuk problemi proxy mehdudiyyetidir - eyni sinif daxilinden `@Transactional` metod cagirma islemez. Laravel-de bu problem yoxdur, cunki tranzaksiya closure ile idare olunur, proxy yoxdur.

**Isolation seviyeleri:** Her ikisinde mumkundur, amma Spring bunu annotasiya seviyyesinde, Laravel ise database konfiqurasiya seviyyesinde edir. Praktikada cogu tetbiq default isolation seviyyesini istifade edir.

## Hansi framework-de var, hansinda yoxdur?

- **Propagation seviyeleri** - Yalniz Spring-de. Laravel-de REQUIRED, REQUIRES_NEW kimi 7 ferqli yayilma seviyelesi yoxdur.
- **readOnly tranzaksiya** - Yalniz Spring-de. DB optimizasiyasi ucun (meselen, replica oxumaq ucun).
- **Timeout** - Yalniz Spring-de. Tranzaksiyanin maksimum meuddeitni mueyyen etmek.
- **rollbackFor / noRollbackFor** - Yalniz Spring-de. Hansi exception-larda rollback olacagini deyismek.
- **Retry mexanizmi** - Laravel-de built-in (`DB::transaction($fn, 5)`). Spring-de manual ve ya `@Retryable` (ayri modul) istifade olunur.
- **lockForUpdate() / sharedLock()** - Laravel Eloquent-de daha temiz sintaksis. Spring-de JPA `@Lock` annotasiyasi ve ya native query ile.
- **afterCommit** - Laravel-de job ve observer-lerde tranzaksiya commit olduqdan sonra isletme daxili mexanizmdir. Spring-de `TransactionSynchronizationManager` ile mumkundur, amma daha cox kod teleb edir.
- **`$afterCommit = true`** - Laravel Observer-lerinde bir property ile commit-sonrasi isletme tanimlanir. Spring-de oxsar funksionalliq ucun daha cox konfiqurasiya lazimdir.
