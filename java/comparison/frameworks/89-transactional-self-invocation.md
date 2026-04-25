# @Transactional Self-Invocation Gotcha

> **Seviyye:** Intermediate ⭐⭐

## Giriş

Spring-in ən çox qarşılaşılan tələlərindən biri: **eyni class daxilindən `@Transactional` metodu çağırmaq işləmir**. Laravel-dən gələn developerlar bunu gözləmir, çünki PHP-nin Eloquent transaction modeli fərqlidir.

---

## Laravel-da Necədir

Laravel-də transaction-lar `DB::transaction()` bloku içindədir — bilavasitə DB driver-ını çağırır, heç bir proxy yoxdur:

```php
class OrderService
{
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $order = Order::create($data);
            $this->sendNotification($order); // ← normal metod çağırışı, problem yoxdur
            return $order;
        });
    }

    protected function sendNotification(Order $order): void
    {
        // DB::transaction içindədir — eyni transactionda işləyir
        Notification::create(['order_id' => $order->id]);
    }
}
```

Laravel-də `$this->sendNotification()` çağırışı transaction kontekstini miras alır — problem yoxdur.

---

## Java/Spring-də Problem

Spring `@Transactional`-ı **proxy pattern** ilə həyata keçirir. Bean inject olduqda siz əsl class-ı yox, **proxy-ni** alırsınız. Proxy transaction-ı başlatır, sonra əsl metodu çağırır.

**Problem:** `this.` ilə eyni class daxilindən çağırış proxy-ni bypass edir:

```java
@Service
public class OrderService {

    @Transactional
    public Order createOrder(OrderRequest req) {
        Order order = orderRepository.save(new Order(req));
        
        // ❌ PROBLEM: this.sendConfirmation() proxy-ni bypass edir!
        // sendConfirmation üzərindəki @Transactional(REQUIRES_NEW) işləmir
        this.sendConfirmation(order);
        
        return order;
    }

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void sendConfirmation(Order order) {
        // Bu ayrı transaction-da işləməlidir — amma işləmir!
        // createOrder ilə eyni transactionda işləyir
        confirmationRepository.save(new Confirmation(order.getId()));
    }
}
```

```
// Nə baş verir:
// 1. Xarici çağırış → Proxy-yə gəlir → Transaction başlar
// 2. createOrder() əsl object üzərində çağırılır
// 3. this.sendConfirmation() → proxy-ni BYPASS edir
// 4. sendConfirmation-daki @Transactional GÖRÜLMƏYİR
```

---

## Düzgün Həllər

### 1. Metodu ayrı bean-ə çıxar (tövsiyə olunan)

```java
@Service
public class OrderService {

    private final ConfirmationService confirmationService;

    public OrderService(ConfirmationService confirmationService) {
        this.confirmationService = confirmationService;
    }

    @Transactional
    public Order createOrder(OrderRequest req) {
        Order order = orderRepository.save(new Order(req));
        
        // ✓ Ayrı bean → proxy işləyir → REQUIRES_NEW transaction başlayır
        confirmationService.sendConfirmation(order);
        
        return order;
    }
}

@Service
public class ConfirmationService {

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void sendConfirmation(Order order) {
        // ✓ Öz ayrı transactionında işləyir
        confirmationRepository.save(new Confirmation(order.getId()));
    }
}
```

### 2. ApplicationContext vasitəsilə self-inject (az tövsiyə olunan)

```java
@Service
public class OrderService implements ApplicationContextAware {

    private ApplicationContext context;

    @Override
    public void setApplicationContext(ApplicationContext ctx) {
        this.context = ctx;
    }

    @Transactional
    public Order createOrder(OrderRequest req) {
        Order order = orderRepository.save(new Order(req));
        
        // Proxy-dən özünü al
        OrderService self = context.getBean(OrderService.class);
        self.sendConfirmation(order); // ✓ proxy işləyir
        
        return order;
    }

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void sendConfirmation(Order order) {
        confirmationRepository.save(new Confirmation(order.getId()));
    }
}
```

### 3. @Transactional-ı həmişə xarici çağırış üçün saxla

Ən sadə qayda: `@Transactional` yalnız **public metodlarda** olsun, həmin metodlar isə yalnız **xaricdən çağırılsın**. Eyni class daxilindən çağırmaq lazımdırsa, metodu başqa bean-ə daşı.

---

## AOP Proxy Mexanizmi — Qısa İzah

```
İnject olunan bean (proxy):
┌─────────────────────────────────────────┐
│  OrderServiceProxy (Spring yaradır)     │
│  ┌───────────────────────────────────┐  │
│  │  createOrder() — transaction ✓   │  │
│  └───────────────────────────────────┘  │
│         ↓ (proxy → real object)         │
│  ┌───────────────────────────────────┐  │
│  │  OrderService (əsl class)         │  │
│  │    this.sendConfirmation() ──────────┼──→ proxy-ni bypass edir ❌
│  └───────────────────────────────────┘  │
└─────────────────────────────────────────┘
```

---

## @Transactional Digər Tələlər

```java
// ❌ private metodda @Transactional işləmir (proxy override edə bilmir)
@Transactional
private void doSomething() { ... }

// ❌ @Transactional bean olmayan class-da işləmir
public class UtilClass {
    @Transactional
    public void process() { ... } // Spring bu class-ı bilmir
}

// ✓ Düzgün: public + Spring-managed bean
@Service
public class MyService {
    @Transactional
    public void process() { ... }
}
```

---

## Praktik Tapşırıq

Aşağıdakı kodu düzəlt:

```java
@Service
public class UserService {

    @Transactional
    public void registerUser(RegisterRequest req) {
        User user = userRepository.save(new User(req));
        this.sendWelcomeEmail(user); // BU PROBLEM YARADIRMI?
    }

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void sendWelcomeEmail(User user) {
        emailLogRepository.save(new EmailLog(user.getId(), "welcome"));
    }
}
```

**Sual:** `sendWelcomeEmail`-dəki `REQUIRES_NEW` işləyəcəkmi? Niyə? Necə düzəltmək olar?

---

## Əlaqəli Mövzular
- [26 — Transactions](26-transactions.md)
- [54 — Spring Transactions Deep](54-spring-transactions-deep.md)
- [07 — Dependency Injection](07-dependency-injection.md)
- [08 — Constructor Injection](08-constructor-injection-autowired-value-for-beginners.md)
