# Circular Dependencies & Bean Initialization

> **Seviyye:** Intermediate ⭐⭐

## Giriş

Spring DI container-i bean-ləri inject etdikdə iki bean bir-birini constructor-da tələb edərsə **circular dependency error** alırsın. Laravel-in service container-i bu problemi sessizliklə həll edir, Spring isə startup-da xəta ilə dayandırır.

---

## Laravel-da Necədir

Laravel-in IoC container-i **lazy resolution** istifadə edir — service container circular dependency-ni otomatik aradan qaldırır:

```php
// Laravel-də circular dep problem deyil
class ServiceA
{
    public function __construct(private ServiceB $b) {}
}

class ServiceB
{
    public function __construct(private ServiceA $a) {}
}

// Laravel bunu həll edir — xəta yoxdur
$a = app(ServiceA::class); // ✓ işləyir
```

---

## Java/Spring-də Problem

Spring-in **constructor injection** (tövsiyə olunan) circular dependency-ni startup-da aşkarlayır:

```java
@Service
public class ServiceA {
    private final ServiceB serviceB;

    public ServiceA(ServiceB serviceB) { // Spring: ServiceB lazımdır
        this.serviceB = serviceB;
    }
}

@Service
public class ServiceB {
    private final ServiceA serviceA;

    public ServiceB(ServiceA serviceA) { // Spring: ServiceA lazımdır
        this.serviceA = serviceA;
    }
}
```

```
Application startup-da xəta:
BeanCurrentlyInCreationException: 
Error creating bean with name 'serviceA': 
Requested bean is currently in creation: 
Is there an unresolvable circular reference?
```

---

## Həll 1: Yenidən Dizayn (Ən Doğru Həll)

Circular dependency çox vaxt **dizayn problemidir**. Həll: aralarında ortaq funksionallığı üçüncü bean-ə çıxar:

```java
// ❌ Circular dep
// ServiceA → ServiceB → ServiceA

// ✓ Həll: SharedService çıxar
@Service
public class SharedService {
    public void sharedMethod() { ... }
}

@Service
public class ServiceA {
    private final SharedService sharedService;
    public ServiceA(SharedService sharedService) {
        this.sharedService = sharedService;
    }
}

@Service
public class ServiceB {
    private final SharedService sharedService;
    public ServiceB(SharedService sharedService) {
        this.sharedService = sharedService;
    }
}
```

---

## Həll 2: @Lazy Annotation

Birini lazy inject et — ilk istifadə anında yüklənəcək:

```java
@Service
public class ServiceA {
    private final ServiceB serviceB;

    public ServiceA(@Lazy ServiceB serviceB) {  // ✓ Lazy proxy inject olur
        this.serviceB = serviceB;
    }
}

@Service
public class ServiceB {
    private final ServiceA serviceA;

    public ServiceB(ServiceA serviceA) {
        this.serviceA = serviceA;
    }
}
```

`@Lazy` proxy yaradır — real bean ilk çağırışda initialize olur. Bu circular dep-i aradan qaldırır amma dizayn problemi qalır.

---

## Həll 3: Setter Injection

```java
@Service
public class ServiceA {
    private ServiceB serviceB;

    // Constructor injection yoxdur — circular dep yoxdur
    @Autowired
    public void setServiceB(ServiceB serviceB) {
        this.serviceB = serviceB;
    }
}
```

Setter injection Spring 4+ üçün circular dep-i həll edir amma `final` field istifadə edə bilmirsən — buna görə tövsiyə olunmur.

---

## Bean Initialization Sırası

Circular dep olmadan da **initialization sırası** vacibdir:

```java
@Service
public class CacheService {
    
    private final Map<String, Object> cache = new HashMap<>();
    
    @PostConstruct  // Bean tamamilə hazır olduqdan SONRA çağırılır
    public void warmUp() {
        // ✓ Bütün inject-lər tamamlanıb
        // database-dən data yüklə, cache-i doldur
        cache.put("config", configRepository.findAll());
    }
    
    @PreDestroy  // Bean destroy olmadan ÖNCE çağırılır
    public void cleanup() {
        cache.clear();
    }
}
```

```java
// Bean sırası vacibdirsə: @DependsOn
@Service
@DependsOn("cacheService")  // CacheService əvvəl initialize olsun
public class ProductService {
    
    @Autowired
    private CacheService cacheService;
    
    @PostConstruct
    public void init() {
        // CacheService artıq hazırdır
        var config = cacheService.get("config");
    }
}
```

---

## Startup Validasiyası

Spring Boot 2.6+ default olaraq circular dep-ləri qadağan edir:

```properties
# application.properties
# Default: false (circular dep = startup error)
spring.main.allow-circular-references=false

# Köhnə proyektlər üçün (tövsiyə olunmur):
spring.main.allow-circular-references=true
```

---

## Praktik Tapşırıq

Aşağıdakı circular dep-i düzəlt:

```java
@Service
public class UserService {
    public UserService(OrderService orderService) { ... }
    
    public boolean canPlaceOrder(Long userId) { ... }
}

@Service  
public class OrderService {
    public OrderService(UserService userService) { ... }
    
    public Order createOrder(Long userId, OrderRequest req) {
        if (!userService.canPlaceOrder(userId)) throw new ForbiddenException();
        ...
    }
}
```

**Sual:** Buradakı circular dep-in kökü nədir? `canPlaceOrder` metodu hansı servisdə olmalıdır?

---

## Əlaqəli Mövzular
- [07 — Dependency Injection](07-dependency-injection.md)
- [08 — Constructor Injection](08-constructor-injection-autowired-value-for-beginners.md)
- [50 — Spring Bean Lifecycle Deep](50-spring-bean-lifecycle-deep.md)
- [92 — @Qualifier & Bean Resolution](92-qualifier-primary.md)
