# 95 — Circular Dependency — Səbəblər və Həllər

> **Seviyye:** Middle ⭐⭐

## Mündəricat
1. [Circular Dependency nədir?](#circular-dependency-nədir)
2. [Növləri](#növləri)
3. [Spring-in yanaşması](#spring-in-yanaşması)
4. [Həll üsulları](#həll-üsulları)
5. [Real layihədə yenidən dizayn](#real-layihədə-yenidən-dizayn)
6. [İntervyu Sualları](#intervyu-sualları)

---

## Circular Dependency nədir?

A class B-yə, B class A-ya dependency varsa — **circular dependency**. Spring startup-da BeanCurrentlyInCreationException atar.

```
OrderService → UserService → OrderService

OrderService needs UserService to instantiate
UserService needs OrderService to instantiate
→ Neither can be created → DEADLOCK
```

```
org.springframework.beans.factory.BeanCurrentlyInCreationException:
Error creating bean with name 'orderService': Requested bean is currently in creation:
Is there an unresolvable circular reference?
```

---

## Növləri

### Direct circular dependency:

```java
@Service
public class OrderService {
    @Autowired
    private UserService userService; // UserService-ə dependency
}

@Service
public class UserService {
    @Autowired
    private OrderService orderService; // OrderService-ə dependency ← CIRCULAR!
}
```

### Indirect circular dependency:

```java
// A → B → C → A
@Service class A { @Autowired B b; }
@Service class B { @Autowired C c; }
@Service class C { @Autowired A a; } // ← circular!
```

---

## Spring-in yanaşması

**Constructor injection** (Spring-in tövsiyə etdiyi yol) circular dependency-ni **startup-da** tapır — çünki hər iki bean eyni anda lazımdır.

**Field/Setter injection** bəzən keçir — Spring singleton bean-ləri öncə yaradır, sonra inject edir. Amma bu yalnız görünüşdədir; problem dərin səviyyədə qalır.

```java
// Constructor injection → BAŞLAYANDA XƏTA:
@Service
public class A {
    private final B b;
    public A(B b) { this.b = b; }
}

@Service
public class B {
    private final A a;
    public B(A a) { this.a = a; } // ← Spring bu xətanı göstərir
}
```

---

## Həll üsulları

### 1. Dizaynı düzəlt — ən yaxşı həll

Circular dependency adətən **yanlış dizayn işarəsi**dir. Shared logic üçüncü service-ə köçürülməlidir.

```java
// Problem: OrderService ↔ UserService

// Həll: Shared logic ayrı class-a:
@Service
public class OrderUserCoordinatorService {
    private final OrderRepository orderRepo;
    private final UserRepository userRepo;

    public void assignOrderToUser(Long orderId, Long userId) {
        Order order = orderRepo.findById(orderId).orElseThrow();
        User user = userRepo.findById(userId).orElseThrow();
        order.setUser(user);
        orderRepo.save(order);
    }
}

// İndi OrderService → UserService dependency yoxdur:
@Service
public class OrderService {
    private final OrderRepository orderRepo;
    // UserService dependency çıxarıldı
}

@Service
public class UserService {
    private final UserRepository userRepo;
    // OrderService dependency çıxarıldı
}
```

### 2. @Lazy injection

```java
@Service
public class OrderService {
    private final UserService userService;

    // @Lazy — proxy yaradır, real bean lazım olanda inject edir:
    public OrderService(@Lazy UserService userService) {
        this.userService = userService;
    }
}
```

**Nə baş verir:** Spring `UserService` üçün proxy yaradır. İlk method çağırıldıqda real bean inject edilir. Startup-da deadlock olmur.

**Diqqət:** @Lazy problemi görünmaz edir, həll etmir. Unit test-lərdə çaşdıra bilər.

### 3. Setter injection (konkret hallar üçün)

```java
@Service
public class OrderService {
    private UserService userService;

    @Autowired
    public void setUserService(UserService userService) {
        this.userService = userService;
    }
}
```

**Constructor injection-dan fərq:** Spring bean-i öncə yaradır (constructor ilə), sonra setter-lə inject edir. Amma bu yanlış dizayn işarəsini gizlədir.

### 4. ApplicationContext-dən lazım olanda almaq

```java
@Service
public class OrderService {
    @Autowired
    private ApplicationContext ctx;

    public void process() {
        // Bean yalnız lazım olduqda alınır — startup-da injection yoxdur:
        UserService userService = ctx.getBean(UserService.class);
        userService.doSomething();
    }
}
```

**Mənfi cəhəti:** Service Locator pattern — DI prinsipini pozur, test etmək çətinləşir.

### 5. Event-driven yanaşma — qopuq coupling

```java
// OrderService istəmir ki UserService-dən asılı olsun.
// Event publish edir, UserService dinləyir.

// Event class:
public record OrderCreatedEvent(Long orderId, Long userId) {}

@Service
public class OrderService {
    private final ApplicationEventPublisher eventPublisher;
    private final OrderRepository orderRepo;

    public Order createOrder(CreateOrderRequest req) {
        Order order = orderRepo.save(new Order(req));
        // UserService-i birbaşa çağırmır, event publish edir:
        eventPublisher.publishEvent(new OrderCreatedEvent(order.getId(), req.getUserId()));
        return order;
    }
}

@Service
public class UserService {
    @EventListener
    public void onOrderCreated(OrderCreatedEvent event) {
        // UserService öz işini görür — OrderService-dən asılı deyil
        updateUserOrderCount(event.userId());
    }
}
```

**Üstünlük:** Real decoupling. OrderService ↔ UserService dependency sıfırlanır.

### 6. Interface ilə abstraction

```java
// Interface yaratmaq:
public interface OrderProcessor {
    void process(Order order);
}

@Service
public class UserService implements OrderProcessor {
    @Override
    public void process(Order order) { ... }
}

@Service
public class OrderService {
    private final OrderProcessor processor; // Interface-ə dependency, UserService-ə deyil

    public OrderService(OrderProcessor processor) {
        this.processor = processor;
    }
}
```

---

## Real layihədə yenidən dizayn

### Klassik problem — bidirectional dependency:

```
UserService    ←→   OrderService
- findUser()        - createOrder()
- getOrders()       - getUserOrders()
```

`UserService.getOrders()` → OrderService-i çağırır
`OrderService.getUserOrders()` → UserService-i çağırır → CIRCULAR

### Həll — responsibility ayrılması:

```java
// UserService — yalnız user domain:
@Service
public class UserService {
    private final UserRepository userRepo;

    public User findUser(Long id) {
        return userRepo.findById(id).orElseThrow();
    }

    public void updateUser(Long id, UpdateUserRequest req) { ... }
    // getOrders() ← bu buraya aid DEYİL
}

// OrderService — yalnız order domain:
@Service
public class OrderService {
    private final OrderRepository orderRepo;
    private final UserRepository userRepo; // Service deyil, Repository inject et

    public List<Order> getUserOrders(Long userId) {
        // UserService çağırmır, birbaşa repository işlədir:
        return orderRepo.findByUserId(userId);
    }
}

// UserOrderQueryService — cross-domain query:
@Service
public class UserOrderQueryService {
    private final UserRepository userRepo;
    private final OrderRepository orderRepo;

    public UserWithOrdersDto getUserWithOrders(Long userId) {
        User user = userRepo.findById(userId).orElseThrow();
        List<Order> orders = orderRepo.findByUserId(userId);
        return new UserWithOrdersDto(user, orders);
    }
}
```

---

## İntervyu Sualları

**S: Circular dependency nədir, niyə yaranır?**
C: İki bean bir-birinə dependency olduqda — A B-yə, B A-ya. Adətən yanlış responsibility ayrılması nəticəsindədir. Spring constructor injection ilə startup-da aşkar edir.

**S: @Lazy həqiqi həll sayılırmı?**
C: Xeyr. @Lazy problemi startup-dan runtime-a keçirir — gizlədir. Həqiqi həll dizaynı düzəltmək: shared logic ayrı class-a köçürmək, event-driven arxitektura, interface abstraction.

**S: Field injection circular dependency-ni niyə keçirir?**
C: Spring field injection-da bean-i öncə proxy ilə yaradır, sonra field-i inject edir. Constructor injection-da isə bean yaradılarkən bütün parametrlər lazımdır — deadlock aşkar olunur.

**S: Circular dependency izin verməmək üçün necə konfiqurasiya?**
C: Spring Boot 2.6+-da default olaraq bağlıdır. Manual: `spring.main.allow-circular-references=false` (artıq default false). Bu setting bir circular dependency olduqda birbaşa startup xətası verir.
