# @Qualifier & @Primary — Birdən Çox Bean Həlli

> **Seviyye:** Intermediate ⭐⭐

## Giriş

Spring-in DI container-i bir interface-in birdən çox implementasiyasını görəndə **hansını inject edəcəyini bilmir**. Laravel-in container-i binding-ə əsaslanır; Spring-in öz mexanizmi var.

---

## Laravel-da Necədir

Laravel-də interface binding `AppServiceProvider`-da açıq şəkildə edilir:

```php
// AppServiceProvider.php
public function register(): void
{
    // Default binding — hansı class istifadə olunacaq
    $this->app->bind(
        PaymentGatewayInterface::class,
        StripePaymentGateway::class
    );

    // Context-based binding
    $this->app->when(OrderController::class)
              ->needs(PaymentGatewayInterface::class)
              ->give(PaypalPaymentGateway::class);
}
```

---

## Java/Spring-də Problem

Spring-in `@Autowired` birdən çox uyğun bean görəndə `NoUniqueBeanDefinitionException` atır:

```java
public interface PaymentGateway {
    PaymentResult charge(BigDecimal amount);
}

@Service
public class StripeGateway implements PaymentGateway {
    @Override
    public PaymentResult charge(BigDecimal amount) { ... }
}

@Service
public class PaypalGateway implements PaymentGateway {
    @Override
    public PaymentResult charge(BigDecimal amount) { ... }
}
```

```java
@Service
public class OrderService {

    private final PaymentGateway gateway;

    // ❌ XƏTA: Spring hansını inject edəcəyini bilmir
    // NoUniqueBeanDefinitionException: expected single matching bean 
    // but found 2: stripeGateway, paypalGateway
    public OrderService(PaymentGateway gateway) {
        this.gateway = gateway;
    }
}
```

---

## Həll 1: @Primary — Default Bean

```java
@Service
@Primary  // ← Default olaraq bu istifadə olunacaq
public class StripeGateway implements PaymentGateway {
    @Override
    public PaymentResult charge(BigDecimal amount) { ... }
}

@Service
public class PaypalGateway implements PaymentGateway {
    @Override
    public PaymentResult charge(BigDecimal amount) { ... }
}
```

```java
@Service
public class OrderService {
    private final PaymentGateway gateway;

    // ✓ @Primary olan StripeGateway inject olur
    public OrderService(PaymentGateway gateway) {
        this.gateway = gateway;
    }
}
```

---

## Həll 2: @Qualifier — Spesifik Bean

```java
@Service
@Qualifier("stripe")
public class StripeGateway implements PaymentGateway { ... }

@Service
@Qualifier("paypal")
public class PaypalGateway implements PaymentGateway { ... }
```

```java
@Service
public class OrderService {
    private final PaymentGateway stripeGateway;
    private final PaymentGateway paypalGateway;

    public OrderService(
            @Qualifier("stripe") PaymentGateway stripeGateway,
            @Qualifier("paypal") PaymentGateway paypalGateway) {
        this.stripeGateway = stripeGateway;
        this.paypalGateway = paypalGateway;
    }
}
```

---

## Həll 3: Bean Adı ilə Injection

`@Qualifier` olmadan da Spring **bean adına** (class adının lowercase-i) əsasən resolve edə bilər:

```java
@Service
public class StripeGateway implements PaymentGateway { ... }
// Bean adı: "stripeGateway"

@Service
public class PaypalGateway implements PaymentGateway { ... }
// Bean adı: "paypalGateway"
```

```java
@Service
public class OrderService {
    private final PaymentGateway stripeGateway;  // ← adı "stripeGateway" — uyğun gəlir

    public OrderService(PaymentGateway stripeGateway) {
        this.stripeGateway = stripeGateway;
    }
}
```

---

## Həll 4: Map/List Injection — Hamısını Al

```java
@Service
public class PaymentRouter {

    private final Map<String, PaymentGateway> gateways;

    // ✓ Bütün PaymentGateway bean-lərini map kimi inject et
    // Key = bean adı, Value = bean instance
    public PaymentRouter(Map<String, PaymentGateway> gateways) {
        this.gateways = gateways;
    }

    public PaymentResult charge(String provider, BigDecimal amount) {
        PaymentGateway gateway = gateways.get(provider + "Gateway");
        if (gateway == null) throw new IllegalArgumentException("Unknown provider: " + provider);
        return gateway.charge(amount);
    }
}
```

```java
// List olaraq da inject etmək olar
@Service
public class PaymentService {

    private final List<PaymentGateway> allGateways;

    public PaymentService(List<PaymentGateway> allGateways) {
        this.allGateways = allGateways; // [stripeGateway, paypalGateway]
    }
}
```

---

## Custom @Qualifier Annotation

Daha oxunaqlı kod üçün öz qualifier annotasiyasını yarat:

```java
@Qualifier
@Retention(RetentionPolicy.RUNTIME)
@Target({ElementType.FIELD, ElementType.PARAMETER, ElementType.TYPE})
public @interface Stripe {}

@Qualifier
@Retention(RetentionPolicy.RUNTIME)
@Target({ElementType.FIELD, ElementType.PARAMETER, ElementType.TYPE})
public @interface Paypal {}
```

```java
@Service
@Stripe
public class StripeGateway implements PaymentGateway { ... }

@Service
@Paypal
public class PaypalGateway implements PaymentGateway { ... }
```

```java
@Service
public class OrderService {

    public OrderService(@Stripe PaymentGateway stripeGateway) {
        // ✓ Çox oxunaqlı, type-safe
    }
}
```

---

## @ConditionalOnProperty — Profile-based Seçim

Production-da hansı implementation aktiv olacağını `application.properties`-dən idarə et:

```java
@Service
@ConditionalOnProperty(name = "payment.provider", havingValue = "stripe", matchIfMissing = true)
public class StripeGateway implements PaymentGateway { ... }

@Service
@ConditionalOnProperty(name = "payment.provider", havingValue = "paypal")
public class PaypalGateway implements PaymentGateway { ... }
```

```properties
# application-prod.properties
payment.provider=stripe

# application-test.properties
payment.provider=paypal
```

---

## Qısa Qərar Cədvəli

| Vəziyyət | Həll |
|----------|------|
| Bir implementation hər yerdə default | `@Primary` |
| Fərqli yerlərdə fərqli implementation | `@Qualifier("name")` |
| Bütün implementation-lar lazımdır | `Map<String, T>` injection |
| Env/profile əsasında seçim | `@ConditionalOnProperty` |
| Oxunaqlı, type-safe seçim | Custom `@Qualifier` annotation |

---

## Praktik Tapşırıq

```java
public interface NotificationSender {
    void send(String userId, String message);
}
```

Bu interface üçün 3 implementation yaz: `EmailSender`, `SmsSender`, `PushSender`. Sonra `NotificationService` yaz ki:
1. Default olaraq Email istifadə etsin
2. Bütün sender-lərə eyni vaxtda göndərən `sendAll()` metodu olsun
3. `application.properties`-dəki `notification.channel` dəyərinə görə hansı sender-in aktiv olacağını tənzimlə

---

## Əlaqəli Mövzular
- [07 — Dependency Injection](07-dependency-injection.md)
- [08 — Constructor Injection](08-constructor-injection-autowired-value-for-beginners.md)
- [51 — Spring Profiles](51-spring-profiles-environment.md)
- [91 — Circular Dependencies](91-circular-dependencies.md)
