# Spring Events — Geniş İzah

## Mündəricat
1. [Spring Event mexanizmi nədir?](#spring-event-mexanizmi-nədir)
2. [Custom Event yaratma](#custom-event-yaratma)
3. [Event publish etmə](#event-publish-etmə)
4. [@EventListener](#eventlistener)
5. [Async Events](#async-events)
6. [@TransactionalEventListener](#transactionaleventlistener)
7. [Event ordering](#event-ordering)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Spring Event mexanizmi nədir?

Spring Observer pattern-ini daxili olaraq dəstəkləyir. Bir komponent event publish edir, digərləri subscribe olur. Bu loose coupling təmin edir.

**Built-in Spring events:**
- `ContextRefreshedEvent` — ApplicationContext refresh olduqda
- `ContextStartedEvent` — context.start() çağırıldıqda
- `ContextStoppedEvent` — context.stop() çağırıldıqda
- `ContextClosedEvent` — context bağlandıqda
- `ApplicationReadyEvent` — tətbiq tam hazır olduqda (Spring Boot)

```java
// Built-in event-lərə subscribe olmaq
@Component
public class AppStartupListener {

    @EventListener
    public void onApplicationReady(ApplicationReadyEvent event) {
        System.out.println("Tətbiq tam başladı, DB seed işini başlat");
        // Başlanğıc datasını yüklə
    }

    @EventListener
    public void onContextRefreshed(ContextRefreshedEvent event) {
        System.out.println("ApplicationContext refresh oldu");
    }
}
```

---

## Custom Event yaratma

```java
// 1. ApplicationEvent-dən extend etmək (köhnə üsul)
public class OrderPlacedEvent extends ApplicationEvent {

    private final Order order;

    public OrderPlacedEvent(Object source, Order order) {
        super(source);
        this.order = order;
    }

    public Order getOrder() {
        return order;
    }
}

// 2. POJO olaraq (müasir üsul — Spring 4.2+)
// ApplicationEvent-dən extend etmək məcburi deyil
public class UserRegisteredEvent {

    private final String userId;
    private final String email;
    private final LocalDateTime registeredAt;

    public UserRegisteredEvent(String userId, String email) {
        this.userId = userId;
        this.email = email;
        this.registeredAt = LocalDateTime.now();
    }

    // Getter-lər
    public String getUserId() { return userId; }
    public String getEmail() { return email; }
    public LocalDateTime getRegisteredAt() { return registeredAt; }
}
```

---

## Event publish etmə

```java
@Service
public class UserService {

    private final UserRepository userRepository;
    private final ApplicationEventPublisher eventPublisher;

    // Constructor injection
    public UserService(UserRepository userRepository,
                       ApplicationEventPublisher eventPublisher) {
        this.userRepository = userRepository;
        this.eventPublisher = eventPublisher;
    }

    public User registerUser(RegisterRequest request) {
        // İstifadəçini qeydiyyatdan keçir
        User user = new User(request.getEmail(), request.getName());
        userRepository.save(user);

        // Event publish et — digər komponentlər dinləyəcək
        eventPublisher.publishEvent(new UserRegisteredEvent(
            user.getId().toString(),
            user.getEmail()
        ));

        return user;
    }
}
```

---

## @EventListener

```java
// Sadə event listener
@Component
public class WelcomeEmailListener {

    private final EmailService emailService;

    public WelcomeEmailListener(EmailService emailService) {
        this.emailService = emailService;
    }

    @EventListener
    public void sendWelcomeEmail(UserRegisteredEvent event) {
        // Event publish olduqda avtomatik çağırılır
        emailService.sendWelcome(event.getEmail());
        System.out.println("Xoş gəldin emaili göndərildi: " + event.getEmail());
    }
}

// Notification listener — eyni event-i bir neçə yer dinləyə bilər
@Component
public class NotificationListener {

    @EventListener
    public void createNotification(UserRegisteredEvent event) {
        System.out.println("Bildiriş yaradıldı: " + event.getUserId());
    }
}

// Şərtli listening — condition ilə
@Component
public class PremiumUserListener {

    @EventListener(condition = "#event.email.endsWith('@company.com')")
    public void handleCorporateUser(UserRegisteredEvent event) {
        System.out.println("Korporativ istifadəçi: " + event.getEmail());
    }
}

// Bir neçə event tipini dinləmək
@Component
public class AuditListener {

    @EventListener({UserRegisteredEvent.class, OrderPlacedEvent.class})
    public void onImportantEvents(Object event) {
        System.out.println("Audit: " + event.getClass().getSimpleName());
    }
}
```

---

## Async Events

Standart olaraq event-lər sinxron işləyir (publish edən thread-də). Asinxron etmək üçün:

```java
// 1. @EnableAsync əlavə et
@SpringBootApplication
@EnableAsync
public class MyApplication {
    public static void main(String[] args) {
        SpringApplication.run(MyApplication.class, args);
    }
}

// 2. @Async əlavə et
@Component
public class AsyncEmailListener {

    @Async
    @EventListener
    public void sendEmail(UserRegisteredEvent event) {
        // Bu metod ayrı thread-də işləyir
        // publish edən thread bloklanmır
        System.out.println("Email göndərilir (thread: " +
            Thread.currentThread().getName() + ")");
        emailService.send(event.getEmail());
    }
}
```

**Sinxron vs Asinxron:**
```java
@Component
public class EventDemo {

    private final ApplicationEventPublisher publisher;

    public void doSomething() {
        System.out.println("1. Publish əvvəl");
        publisher.publishEvent(new MyEvent());
        // Sinxron: listener işini bitirənə qədər gözləyir
        // Asinxron (@Async): dərhal davam edir
        System.out.println("3. Publish sonra");
    }
}

@Component
class MyListener {
    @EventListener
    // @Async əlavə edilsə — sinxron olur, yoxdursa asinxron
    public void handle(MyEvent event) {
        System.out.println("2. Listener işləyir");
    }
}
```

---

## @TransactionalEventListener

Transaction-ın müəyyən mərhələsində event-i emal etmək üçün:

```java
@Component
public class OrderEventListener {

    private final NotificationService notificationService;

    public OrderEventListener(NotificationService notificationService) {
        this.notificationService = notificationService;
    }

    // Transaction commit olduqdan SONRA işlə (ən çox istifadə edilən)
    @TransactionalEventListener(phase = TransactionPhase.AFTER_COMMIT)
    public void onOrderPlaced(OrderPlacedEvent event) {
        // DB-yə yazıldıqdan sonra email göndər
        // Əgər transaction rollback olsa — bu çağırılmaz
        notificationService.sendOrderConfirmation(event.getOrder());
    }

    // Transaction commit olmazdan əvvəl
    @TransactionalEventListener(phase = TransactionPhase.BEFORE_COMMIT)
    public void beforeCommit(OrderPlacedEvent event) {
        System.out.println("Commit əvvəl yoxlama aparılır");
    }

    // Transaction rollback olduqdan sonra
    @TransactionalEventListener(phase = TransactionPhase.AFTER_ROLLBACK)
    public void onRollback(OrderPlacedEvent event) {
        System.out.println("Sifariş uğursuz oldu, kompensasiya başlat");
    }

    // Commit ya rollback — hər iki halda
    @TransactionalEventListener(phase = TransactionPhase.AFTER_COMPLETION)
    public void afterCompletion(OrderPlacedEvent event) {
        System.out.println("Transaction tamamlandı");
    }
}
```

**Niyə @TransactionalEventListener vacibdir?**
```java
@Service
public class OrderService {

    @Transactional
    public void placeOrder(Order order) {
        orderRepository.save(order); // DB-yə yazıldı

        // YANLIŞ: Adi @EventListener istifadə etsək
        // email göndərilir, amma sonra exception olub transaction rollback ola bilər
        // O zaman email getmiş olar, amma sifariş DB-də yoxdur!

        // DOĞRU: @TransactionalEventListener(AFTER_COMMIT) istifadə et
        // Email yalnız commit uğurlu olduqdan sonra göndərilər
        eventPublisher.publishEvent(new OrderPlacedEvent(this, order));

        // Burada exception olsa → rollback → email göndərilməz (AFTER_COMMIT sayəsində)
    }
}
```

---

## Event ordering

```java
// @Order ilə event listener-lərin sırası
@Component
public class FirstListener {

    @EventListener
    @Order(1) // Birinci işləyir
    public void handle(UserRegisteredEvent event) {
        System.out.println("1. İlk listener");
    }
}

@Component
public class SecondListener {

    @EventListener
    @Order(2) // İkinci işləyir
    public void handle(UserRegisteredEvent event) {
        System.out.println("2. İkinci listener");
    }
}
```

---

## İntervyu Sualları

### 1. Spring event-ləri standart olaraq sinxron işləyirmi?
**Cavab:** Bəli. `publishEvent()` çağırıldıqda bütün sinxron listener-lər eyni thread-də işləyir. Publisher onlar bitənə qədər gözləyir. Asinxron etmək üçün @Async + @EnableAsync lazımdır.

### 2. @TransactionalEventListener ilə @EventListener fərqi nədir?
**Cavab:** @EventListener event publish olduqda dərhal çağırılır. @TransactionalEventListener isə transaction-ın müəyyən fazasında (AFTER_COMMIT, BEFORE_COMMIT, AFTER_ROLLBACK) çağırılır. Məsələn, AFTER_COMMIT — yalnız DB-yə uğurla yazıldıqdan sonra işləyir.

### 3. Event listener-lər arasında sıranı necə idarə etmək olar?
**Cavab:** @Order annotasiyası ilə. Kiçik rəqəm = daha əvvəl işləyir. Ordered.HIGHEST_PRECEDENCE = ən əvvəl.

### 4. Spring Boot-da tətbiq hazır olduqda kod işlətmək üçün nə istifadə edilir?
**Cavab:** `@EventListener` + `ApplicationReadyEvent`, ya da `CommandLineRunner`, ya da `ApplicationRunner` interface-ləri.

### 5. Event-i publish etmək üçün hansı interface istifadə olunur?
**Cavab:** `ApplicationEventPublisher`. Spring Boot tətbiqlərində `ApplicationContext` da bu interface-i implement edir, amma birbaşa `ApplicationEventPublisher` inject etmək daha semantik düzgündür.

*Son yenilənmə: 2026-04-10*
