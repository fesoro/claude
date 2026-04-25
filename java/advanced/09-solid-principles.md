# 09 — SOLID Prinsipləri — Java ilə Geniş İzah

> **Seviyye:** Advanced ⭐⭐⭐


## Mündəricat
1. [S — Single Responsibility](#s--single-responsibility)
2. [O — Open/Closed](#o--openclosed)
3. [L — Liskov Substitution](#l--liskov-substitution)
4. [I — Interface Segregation](#i--interface-segregation)
5. [D — Dependency Inversion](#d--dependency-inversion)
6. [İntervyu Sualları](#intervyu-sualları)

---

## S — Single Responsibility

**Bir sinifin yalnız bir dəyişmə səbəbi olmalıdır.**

```java
// YANLIŞ — bir sinif çox şey edir
public class OrderService {

    public Order createOrder(OrderRequest request) {
        // 1. Validation
        if (request.getItems().isEmpty()) {
            throw new IllegalArgumentException("Sifariş boş ola bilməz");
        }

        // 2. Business logic
        Order order = buildOrder(request);

        // 3. DB saxlama
        String sql = "INSERT INTO orders ...";
        jdbcTemplate.update(sql, ...);

        // 4. Mail göndərmə
        MimeMessage message = mailSender.createMimeMessage();
        // ...

        // 5. Log
        FileWriter logWriter = new FileWriter("orders.log");
        logWriter.write("Order created: " + order.getId());

        return order;
    }
}

// DOĞRU — hər sinif bir məsuliyyət
@Service
public class OrderService {
    private final OrderValidator validator;
    private final OrderRepository repository;
    private final OrderNotificationService notificationService;

    public Order createOrder(OrderRequest request) {
        validator.validate(request);
        Order order = buildOrder(request);
        Order saved = repository.save(order);
        notificationService.notifyOrderCreated(saved);
        return saved;
    }
}

@Component
class OrderValidator {
    public void validate(OrderRequest request) {
        if (request.getItems().isEmpty()) throw new ValidationException("Boş sifariş");
    }
}

@Service
class OrderNotificationService {
    public void notifyOrderCreated(Order order) {
        emailService.sendConfirmation(order);
    }
}
```

---

## O — Open/Closed

**Siniflər genişləndirməyə açıq, dəyişməyə qapalı olmalıdır.**

```java
// YANLIŞ — yeni endirim növü əlavə etdikdə sinfi dəyişmək lazımdır
public class OrderPriceCalculator {

    public BigDecimal calculate(Order order, String discountType) {
        BigDecimal price = order.getBasePrice();

        if ("STUDENT".equals(discountType)) {
            price = price.multiply(new BigDecimal("0.8")); // 20% endirim
        } else if ("SENIOR".equals(discountType)) {
            price = price.multiply(new BigDecimal("0.75")); // 25% endirim
        } else if ("EMPLOYEE".equals(discountType)) { // ← Yeni növ əlavə etmək = sinfi dəyişmək
            price = price.multiply(new BigDecimal("0.5"));
        }

        return price;
    }
}

// DOĞRU — yeni endirim növü üçün yeni sinif əlavə et
public interface DiscountStrategy {
    BigDecimal applyDiscount(BigDecimal price);
}

@Component("studentDiscount")
public class StudentDiscountStrategy implements DiscountStrategy {
    @Override
    public BigDecimal applyDiscount(BigDecimal price) {
        return price.multiply(new BigDecimal("0.8"));
    }
}

@Component("seniorDiscount")
public class SeniorDiscountStrategy implements DiscountStrategy {
    @Override
    public BigDecimal applyDiscount(BigDecimal price) {
        return price.multiply(new BigDecimal("0.75"));
    }
}

// Yeni növ → yeni sinif, mövcud kod dəyişmir
@Component("employeeDiscount")
public class EmployeeDiscountStrategy implements DiscountStrategy {
    @Override
    public BigDecimal applyDiscount(BigDecimal price) {
        return price.multiply(new BigDecimal("0.5"));
    }
}

@Service
public class OrderPriceCalculator {

    private final Map<String, DiscountStrategy> strategies;

    public OrderPriceCalculator(Map<String, DiscountStrategy> strategies) {
        this.strategies = strategies;
    }

    public BigDecimal calculate(Order order, String discountType) {
        DiscountStrategy strategy = strategies.getOrDefault(
            discountType, price -> price); // Default: endirim yox
        return strategy.applyDiscount(order.getBasePrice());
    }
}
```

---

## L — Liskov Substitution

**Alt sinif, üst sinifin yerini tutmalıdır (davranış dəyişməməlidir).**

```java
// YANLIŞ — alt sinif üst sinifin kontraktını pozur
public class Rectangle {
    protected int width;
    protected int height;

    public void setWidth(int width) { this.width = width; }
    public void setHeight(int height) { this.height = height; }
    public int area() { return width * height; }
}

public class Square extends Rectangle {
    @Override
    public void setWidth(int width) {
        this.width = width;
        this.height = width; // ← LSP pozuntusu!
    }

    @Override
    public void setHeight(int height) {
        this.height = height;
        this.width = height; // ← LSP pozuntusu!
    }
}

// Kod square-i rectangle kimi istifadə edəndə:
Rectangle rect = new Square();
rect.setWidth(5);
rect.setHeight(10);
// Gözlənilən: 5*10=50, Əsl: 10*10=100 (LSP pozuldu!)

// DOĞRU — ayrı hierarchy
public interface Shape {
    int area();
}

public class Rectangle implements Shape {
    private final int width;
    private final int height;
    public Rectangle(int width, int height) { ... }
    public int area() { return width * height; }
}

public class Square implements Shape {
    private final int side;
    public Square(int side) { ... }
    public int area() { return side * side; }
}

// Spring-də LSP nümunəsi
public interface PaymentService {
    PaymentResult process(PaymentRequest request);
    boolean refund(String transactionId, BigDecimal amount);
}

public class CreditCardPaymentService implements PaymentService {
    @Override
    public PaymentResult process(PaymentRequest request) { ... }

    @Override
    public boolean refund(String transactionId, BigDecimal amount) {
        // Həqiqi refund — LSP uyğun
        return cardProvider.refund(transactionId, amount);
    }
}

public class CashPaymentService implements PaymentService {
    @Override
    public PaymentResult process(PaymentRequest request) { ... }

    // YANLIŞ — UnsupportedOperationException atmaq LSP pozuntur
    // @Override
    // public boolean refund(...) { throw new UnsupportedOperationException(); }

    // DOĞRU — interface-i ayır (ISP ilə)
    @Override
    public boolean refund(String transactionId, BigDecimal amount) {
        // Cash üçün refund mümkün amma fərqli prosesdir
        cashDrawer.returnCash(amount);
        return true;
    }
}
```

---

## I — Interface Segregation

**Müştərilər istifadə etmədikləri metodlara məcbur edilməməlidir.**

```java
// YANLIŞ — böyük interface
public interface AnimalBehavior {
    void eat();
    void sleep();
    void fly();    // Bütün heyvanlar uçmur!
    void swim();   // Bütün heyvanlar üzmür!
    void run();
}

public class Dog implements AnimalBehavior {
    @Override public void eat() { ... }
    @Override public void sleep() { ... }
    @Override public void fly() {
        throw new UnsupportedOperationException(); // Saçma!
    }
    @Override public void swim() { ... }
    @Override public void run() { ... }
}

// DOĞRU — kiçik, xüsuslaşmış interface-lər
public interface Eatable { void eat(); }
public interface Sleepable { void sleep(); }
public interface Flyable { void fly(); }
public interface Swimmable { void swim(); }
public interface Runnable { void run(); }

public class Dog implements Eatable, Sleepable, Swimmable, Runnable {
    @Override public void eat() { ... }
    @Override public void sleep() { ... }
    @Override public void swim() { ... }
    @Override public void run() { ... }
    // fly() yoxdur — lazım deyil!
}

public class Bird implements Eatable, Sleepable, Flyable {
    @Override public void eat() { ... }
    @Override public void sleep() { ... }
    @Override public void fly() { ... }
}

// Spring-də ISP nümunəsi
// UserRepository-ni ayır
public interface UserReadRepository {
    Optional<User> findById(Long id);
    List<User> findAll();
    Optional<User> findByEmail(String email);
}

public interface UserWriteRepository {
    User save(User user);
    void deleteById(Long id);
}

// CQRS pattern-ə uyğun
@Service
public class UserQueryService {
    private final UserReadRepository readRepository; // Yalnız read
}

@Service
public class UserCommandService {
    private final UserWriteRepository writeRepository; // Yalnız write
}
```

---

## D — Dependency Inversion

**Yüksək səviyyəli modullar aşağı səviyyəli modullara bağlı olmamalıdır. Hər ikisi abstraksiyana bağlı olmalıdır.**

```java
// YANLIŞ — yüksək səviyyəli kod aşağı səviyyəli implementasiyaya bağlıdır
public class OrderService {

    // Birbaşa implementasiyaya bağımlılıq
    private MySqlOrderRepository repository = new MySqlOrderRepository();
    private SmtpEmailService emailService = new SmtpEmailService();

    public void createOrder(Order order) {
        repository.save(order);
        emailService.send(order.getUserEmail(), "Sifariş qəbul edildi");
    }
    // MySql → PostgreSQL keçdikdə OrderService dəyişməlidir!
    // SMTP → SendGrid keçdikdə OrderService dəyişməlidir!
}

// DOĞRU — abstraksiyana bağımlılıq (DI ilə)
public interface OrderRepository {
    Order save(Order order);
}

public interface EmailService {
    void send(String to, String subject, String body);
}

@Service
public class OrderService {

    // Abstraksiyana bağımlılıq — implementasiyadan asılı deyil
    private final OrderRepository repository;
    private final EmailService emailService;

    // Spring DI — implementasiyanı inject edir
    public OrderService(OrderRepository repository, EmailService emailService) {
        this.repository = repository;
        this.emailService = emailService;
    }

    public void createOrder(Order order) {
        repository.save(order);
        emailService.send(order.getUserEmail(), "Sifariş", "Qəbul edildi");
    }
}

// İmplementasiyalar — OrderService bunları bilmir
@Repository
public class JpaOrderRepository implements OrderRepository {
    private final JpaRepository<OrderEntity, Long> jpa;
    @Override
    public Order save(Order order) { return mapper.toDomain(jpa.save(mapper.toEntity(order))); }
}

@Service
public class SendGridEmailService implements EmailService {
    @Override
    public void send(String to, String subject, String body) {
        sendGridClient.send(to, subject, body);
    }
}

// Test üçün mock
public class FakeEmailService implements EmailService {
    private final List<String> sentEmails = new ArrayList<>();
    @Override
    public void send(String to, String subject, String body) {
        sentEmails.add(to);
    }
}
```

---

## İntervyu Sualları

### 1. SRP-ni bir cümlədə izah edin.
**Cavab:** Bir sinifin yalnız bir məsuliyyəti (bir dəyişmə səbəbi) olmalıdır. Əgər sinifi dəyişmək üçün iki fərqli səbəb tapıla bilərsə — sinif iki ayrı sinifə bölünməlidir.

### 2. OCP-ni real layihədə necə tətbiq edersiniz?
**Cavab:** Strategy pattern — yeni davranış üçün yeni sinif yazılır, mövcud kod dəyişmir. Plugin architecture — yeni feature-lar extension point-lər vasitəsilə əlavə olunur. Abstract Factory — yeni əşya tipi üçün yeni factory, mövcud factory-lər dəyişmir.

### 3. LSP-nin pozulması necə aşkarlanır?
**Cavab:** `instanceof` yoxlaması çox varsa (polimorfizm işləmir). Alt sinif metodunda `UnsupportedOperationException` atılırsa. Alt sinif üst sinifin şərtlərini (precondition, postcondition) pozursa. Həll: daha kiçik interface-lər (ISP), ya da kompozisiya (inheritance əvəzinə).

### 4. ISP niyə vacibdir?
**Cavab:** Böyük interface — implementorları istifadə etmədikləri metodlara məcbur edir. Bu boş implementasiyalara (`throw new UnsupportedOperationException`) yol açır. Kiçik interface-lər: daha az coupling, daha asan test, daha çevik dizayn.

### 5. DIP Spring ilə necə tətbiq olunur?
**Cavab:** Spring IoC container DIP-in praktik realizasiyasıdır. `@Service`, `@Repository` interface-ləri implement edir. `@Autowired` / constructor injection abstraksiyanı inject edir — implementasiyadan xəbərsizdir. Test zamanı mock/stub inject edilir — production kodu dəyişmir.

*Son yenilənmə: 2026-04-10*
