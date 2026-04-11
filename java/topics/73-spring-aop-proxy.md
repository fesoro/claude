# Spring AOP Proxy Mexanizmi — Geniş İzah

## Mündəricat
1. [Proxy nədir?](#proxy-nədir)
2. [JDK Dynamic Proxy](#jdk-dynamic-proxy)
3. [CGLIB Proxy](#cglib-proxy)
4. [Spring-in avtomatik seçimi](#spring-in-avtomatik-seçimi)
5. [Self-invocation problemi](#self-invocation-problemi)
6. [proxyTargetClass=true](#proxytargetclass-true)
7. [AspectJ weaving həlli](#aspectj-weaving-həlli)
8. [İntervyu Sualları](#intervyu-sualları)

---

## Proxy nədir?

Spring AOP **runtime-da proxy** yaradır. Siz bean-ı inject etdikdə, orijinal sinif yerinə proxy almış olursuz. Proxy əsl metodu çağırmadan əvvəl/sonra advice-ları işlədir.

```
Siz inject etdikdə:
@Autowired UserService userService;

Əslində aldığınız:
UserServiceProxy (Spring-in yaratdığı)
    → before advice işlədir
    → original UserService.createUser() çağırır
    → after advice işlədir
```

---

## JDK Dynamic Proxy

**Şərt:** Target sinif ən azı bir interface implement etməlidir.

```java
// Interface var — JDK proxy istifadə edilir
public interface UserService {
    User createUser(CreateUserRequest request);
    User findUser(Long id);
}

@Service
public class UserServiceImpl implements UserService {

    @Override
    public User createUser(CreateUserRequest request) {
        return userRepository.save(new User(request.getEmail()));
    }

    @Override
    public User findUser(Long id) {
        return userRepository.findById(id).orElseThrow();
    }
}

// Spring JDK proxy yaradır:
// Proxy implements UserService
// Her interface metodu çağırılanda AOP işləyir
```

**JDK proxy-nin xüsusiyyətləri:**
- `java.lang.reflect.Proxy` istifadə edir
- Yalnız interface metodlarını proxy-ləyir
- Compile-time yoxlaması mümkündür
- CGLIB-dən bir az sürətlidir

---

## CGLIB Proxy

**Şərt:** Interface yoxdursa (və ya `proxyTargetClass=true` olduqda).

```java
// Interface yoxdur — CGLIB proxy istifadə edilir
@Service
public class ProductService {  // Heç bir interface implement etmir

    @Transactional
    public Product createProduct(Product product) {
        return productRepository.save(product);
    }
}

// Spring CGLIB proxy yaradır:
// ProductServiceCGLIB$$ extends ProductService
// createProduct() override edilir, AOP işləyir
```

**CGLIB-in xüsusiyyətləri:**
- Subclass yaradaraq proxy edir
- `final` sinif və metodları proxy edə bilmir
- `final` sinif üçün exception atılır
- Spring Boot standart olaraq CGLIB istifadə edir (hətta interface varsa belə)

---

## Spring-in avtomatik seçimi

```java
// Spring Boot default: CGLIB (proxyTargetClass=true)
// Buna görə bu konfiqurasiya adətən lazımsızdır

@Configuration
@EnableAspectJAutoProxy // proxyTargetClass=false (default)
public class AopConfig {
    // Interface varsa → JDK proxy
    // Interface yoxdursa → CGLIB
}

@Configuration
@EnableAspectJAutoProxy(proxyTargetClass = true)
public class AopConfig {
    // Həmişə CGLIB — interface olsun ya olmasın
}
```

**Spring Boot-da:**
```yaml
# application.properties
spring.aop.proxy-target-class=true  # default — həmişə CGLIB
```

---

## Self-invocation problemi

**Ən çox rastlanan AOP tuzağı!**

```java
@Service
public class OrderService {

    @Transactional
    public void placeOrder(Order order) {
        // ... sifariş yerləşdir
        validateOrder(order); // ← PROBLEM BURADA!
    }

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void validateOrder(Order order) {
        // Bu @Transactional işləmir!
        // Çünki this.validateOrder() çağırılır
        // proxy-dən keçmir!
    }
}
```

**Niyə baş verir:**

```
Xaricdən çağırma (proxy-dən keçir ✓):
Client → [Proxy] → OrderService.placeOrder()
                            → [Proxy] → validateOrder()  ✓ AOP işləyir

Daxili çağırma (proxy-dən keçmir ✗):
Client → [Proxy] → OrderService.placeOrder()
                            → this.validateOrder()       ✗ AOP işləmir!
```

**Həlllər:**

```java
// Həll 1: Metodu ayrı @Service-ə köçürmək (ən yaxşı həll)
@Service
public class OrderValidationService {

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void validateOrder(Order order) {
        // İndi AOP işləyir — xaricdən çağırılır
    }
}

@Service
public class OrderService {

    private final OrderValidationService validationService;

    @Transactional
    public void placeOrder(Order order) {
        validationService.validateOrder(order); // proxy-dən keçir ✓
    }
}
```

```java
// Həll 2: Self-inject (hack, lakin işləyir)
@Service
public class OrderService {

    @Autowired
    @Lazy // Circular dependency-nin qarşısını almaq üçün
    private OrderService self;

    @Transactional
    public void placeOrder(Order order) {
        self.validateOrder(order); // proxy vasitəsilə çağırılır ✓
    }

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void validateOrder(Order order) {
        // İndi AOP işləyir
    }
}
```

```java
// Həll 3: ApplicationContext-dən bean almaq
@Service
public class OrderService implements ApplicationContextAware {

    private ApplicationContext context;

    @Override
    public void setApplicationContext(ApplicationContext ctx) {
        this.context = ctx;
    }

    @Transactional
    public void placeOrder(Order order) {
        // Proxy-ni birbaşa al
        OrderService proxy = context.getBean(OrderService.class);
        proxy.validateOrder(order); // proxy vasitəsilə ✓
    }
}
```

**Private metodlar:**
```java
@Service
public class UserService {

    // YANLIŞ — private metodlar proxy-ə əlçatmazdır
    @Transactional // İşləmir!
    private User saveUser(User user) {
        return userRepository.save(user);
    }

    // DOĞRU — public metodlar proxy edilir
    @Transactional
    public User saveUser(User user) {
        return userRepository.save(user);
    }
}
```

---

## proxyTargetClass=true

```java
// CGLIB ilə interface vasitəsilə inject etmək
public interface PaymentService {
    void processPayment(Payment payment);
}

@Service
public class PaymentServiceImpl implements PaymentService {
    @Override
    public void processPayment(Payment payment) { ... }
}

// proxyTargetClass=false (JDK) ilə:
@Autowired
PaymentService paymentService; // Interface tipi → işləyir ✓

@Autowired
PaymentServiceImpl paymentServiceImpl; // Impl tipi → XƏTA! ✗
// Proxy PaymentService interface-ini implements edir,
// PaymentServiceImpl-in alt-sinfi deyil

// proxyTargetClass=true (CGLIB) ilə:
@Autowired
PaymentService paymentService; // Interface tipi → işləyir ✓

@Autowired
PaymentServiceImpl paymentServiceImpl; // Impl tipi → İŞLƏYİR ✓
// Proxy PaymentServiceImpl-in alt-sinfidir
```

---

## AspectJ weaving həlli

Self-invocation problemini tam həll etmək üçün **compile-time weaving** (AspectJ):

```xml
<!-- pom.xml -->
<dependency>
    <groupId>org.springframework</groupId>
    <artifactId>spring-aspects</artifactId>
</dependency>

<plugin>
    <groupId>org.codehaus.mojo</groupId>
    <artifactId>aspectj-maven-plugin</artifactId>
    <configuration>
        <aspectLibraries>
            <aspectLibrary>
                <groupId>org.springframework</groupId>
                <artifactId>spring-aspects</artifactId>
            </aspectLibrary>
        </aspectLibraries>
    </configuration>
</plugin>
```

```java
// Compile-time weaving ilə self-invocation işləyir!
@Service
public class OrderService {

    @Transactional
    public void placeOrder(Order order) {
        validateOrder(order); // Proxy lazım deyil — bytecode-a toxunulub
    }

    @Transactional(propagation = Propagation.REQUIRES_NEW)
    public void validateOrder(Order order) {
        // Compile-time weaving ilə işləyir ✓
    }
}
```

---

## İntervyu Sualları

### 1. Spring AOP proxy-ləri nə vaxt yaradılır?
**Cavab:** Application context başlayanda, BeanPostProcessor mərhələsində (AnnotationAwareAspectJAutoProxyCreator). Hər bean üçün Aspect-lərin uyğun olub-olmadığı yoxlanılır, uyğun isə proxy yaradılır.

### 2. JDK proxy vs CGLIB proxy nə zaman istifadə edilir?
**Cavab:** JDK proxy — bean interface implement edirsə və `proxyTargetClass=false` olarsa. CGLIB — interface yoxdursa, ya da `proxyTargetClass=true` olarsa. Spring Boot default olaraq CGLIB istifadə edir.

### 3. Self-invocation niyə AOP-u pozur?
**Cavab:** Spring AOP proxy-bazalıdır. Xaricdən çağırma proxy üzərindən keçir (AOP işləyir). Daxili çağırma (`this.method()`) birbaşa orijinal metoda gedir, proxy-dən keçmir, buna görə AOP işləmir.

### 4. @Transactional private metodda işləyirmi?
**Cavab:** Xeyr. CGLIB proxy subclass yaradır, private metodları override edə bilmir. Bundan əlavə, self-invocation problemi də var. @Transactional həmişə public metodlarda istifadə edilməlidir.

### 5. Self-invocation probleminin ən yaxşı həlli nədir?
**Cavab:** Metodu ayrı @Service-ə köçürmək. Bu həm dizayn baxımından düzgündür (Single Responsibility), həm də texniki problemi tam həll edir.

*Son yenilənmə: 2026-04-10*
