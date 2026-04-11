# Spring AOP — Əsas Konseptlər

## Mündəricat
1. [AOP nədir?](#aop-nədir)
2. [Cross-cutting concerns](#cross-cutting-concerns)
3. [AOP terminologiyası](#aop-terminologiyası)
4. [Spring AOP vs AspectJ](#spring-aop-vs-aspectj)
5. [@EnableAspectJAutoProxy](#enableaspectjautoproxy)
6. [Sadə Aspect nümunəsi](#sadə-aspect-nümunəsi)
7. [İntervyu Sualları](#intervyu-sualları)

---

## AOP nədir?

**AOP (Aspect-Oriented Programming)** — proqramın müxtəlif yerlərində təkrarlanan məntiqin (logging, security, transaction) ayrı modulda toplanmasına imkan verən proqramlaşdırma paradiqmasıdır.

```java
// YANLIŞ — məntiq hər yerdə təkrarlanır
@Service
public class UserService {
    public User createUser(CreateUserRequest req) {
        // Hər metodda eyni logging kodu
        log.info("createUser başladı: {}", req.getEmail());
        long start = System.currentTimeMillis();

        // Əsas iş məntiqi
        User user = new User(req.getEmail());
        userRepository.save(user);

        // Yenə logging
        log.info("createUser bitdi, {} ms", System.currentTimeMillis() - start);
        return user;
    }
}

@Service
public class OrderService {
    public Order createOrder(CreateOrderRequest req) {
        // Eyni logging kodu burada da!
        log.info("createOrder başladı");
        long start = System.currentTimeMillis();

        Order order = new Order(req.getUserId());
        orderRepository.save(order);

        log.info("createOrder bitdi, {} ms", System.currentTimeMillis() - start);
        return order;
    }
}
```

```java
// DOĞRU — AOP ilə logging bir yerdə toplanır
@Aspect
@Component
public class LoggingAspect {

    @Around("execution(* com.example.service.*.*(..))")
    public Object logExecutionTime(ProceedingJoinPoint joinPoint) throws Throwable {
        log.info("{} başladı", joinPoint.getSignature().getName());
        long start = System.currentTimeMillis();

        Object result = joinPoint.proceed(); // əsl metodu çağır

        log.info("{} bitdi, {} ms",
            joinPoint.getSignature().getName(),
            System.currentTimeMillis() - start);
        return result;
    }
}

// İndi UserService və OrderService sadədir:
@Service
public class UserService {
    public User createUser(CreateUserRequest req) {
        // Yalnız iş məntiqi — logging Aspect tərəfindən edilir
        User user = new User(req.getEmail());
        return userRepository.save(user);
    }
}
```

---

## Cross-cutting concerns

**Cross-cutting concerns** — tətbiqin bir çox modulunda lazım olan, lakin əsas iş məntiqi ilə birbaşa əlaqəsi olmayan funksionallıqdır:

| Concern | İzah |
|---------|------|
| **Logging** | Metod giriş/çıxışı, parametrlər, vaxt |
| **Security** | İcazə yoxlaması, autentifikasiya |
| **Transaction** | @Transactional — Spring AOP-la işləyir |
| **Caching** | @Cacheable — Spring AOP-la işləyir |
| **Exception handling** | Xəta tutma və çevirmə |
| **Performance monitoring** | Metrik toplanması |
| **Rate limiting** | API çağırış limiti |
| **Retry** | Uğursuz əməliyyatların təkrarlanması |

---

## AOP terminologiyası

### 1. Aspect (Aspekt)
Cross-cutting concern-i ehtiva edən modul:
```java
@Aspect  // Bu sinif bir Aspect-dir
@Component
public class SecurityAspect {
    // Aspect içindəki hər şey burada
}
```

### 2. Advice (Məsləhət)
Aspect-in nə vaxt işləyəcəyini müəyyən edən kod:
```java
@Aspect
@Component
public class ExampleAspect {

    @Before("...")        // Metod çağırılmadan əvvəl
    public void before() {}

    @After("...")         // Metod çağırılandan sonra (həmişə)
    public void after() {}

    @AfterReturning("...") // Uğurlu qayıtmadan sonra
    public void afterReturning() {}

    @AfterThrowing("...")  // Exception atıldıqdan sonra
    public void afterThrowing() {}

    @Around("...")         // Metodun ətrafında tam nəzarət
    public Object around(ProceedingJoinPoint pjp) throws Throwable {
        return pjp.proceed();
    }
}
```

### 3. Joinpoint (Birləşmə nöqtəsi)
Advice-ın tətbiq edilə biləcəyi yer. Spring AOP-da yalnız **method execution** joinpoint-dir:
```java
// Bu bir joinpoint-dir — metodun çağırılması
userService.createUser(request);
```

### 4. Pointcut (Kəsişmə nöqtəsi ifadəsi)
Hansı joinpoint-lərə advice tətbiq ediləcəyini müəyyən edən ifadə:
```java
@Pointcut("execution(* com.example.service.*.*(..))")
public void serviceLayer() {} // Bu pointcut ifadəsidir

@Before("serviceLayer()") // Pointcut-dan istifadə
public void beforeService(JoinPoint joinPoint) {}
```

### 5. Target Object
Aspect-in tətbiq edildiyi orijinal bean:
```java
// UserService — target object
// Spring onun yerinə proxy yaradır
@Service
public class UserService {
    public void createUser() { ... }
}
```

### 6. Proxy
Spring AOP-da target-ın yerinə keçən, advice-ları tətbiq edən obyekt. JDK dynamic proxy və ya CGLIB proxy ola bilər.

### 7. Weaving (Toxuma)
Aspect-in target kod ilə birləşdirilməsi prosesi:
- **Compile-time weaving** — AspectJ kompilyasiya zamanı toxuyur
- **Load-time weaving** — class yüklənəndə toxuyur
- **Runtime weaving** — Spring AOP işlədikdə (proxy vasitəsilə)

---

## Spring AOP vs AspectJ

| Xüsusiyyət | Spring AOP | AspectJ |
|------------|-----------|---------|
| Weaving | Runtime (proxy) | Compile/Load-time |
| Joinpoint növləri | Yalnız method execution | Method, constructor, field access, ... |
| Spring bean tələbi | Bəli (yalnız Spring bean-larına işləyir) | Xeyr (istənilən Java sinfinə) |
| Performans | Bir az yavaş (proxy overhead) | Daha sürətli |
| Konfiqurasiya | Sadə | Mürəkkəb |
| İstifadə halı | Əksər tətbiqlər | Xüsusi hallar |

**Spring AOP-un məhdudiyyətləri:**
```java
@Service
public class UserService {

    @Autowired
    private UserService self; // Hack — özünü inject etmək

    public void outerMethod() {
        // YANLIŞ: self-invocation — AOP işləmir!
        this.innerMethod(); // proxy-dən keçmir

        // DOĞRU: self inject edilmiş bean üzərindən
        self.innerMethod(); // proxy-dən keçir
    }

    @Transactional
    public void innerMethod() {
        // Bu @Transactional this.innerMethod() çağırılanda işləmir!
    }
}
```

---

## @EnableAspectJAutoProxy

```java
// Spring Boot-da avtomatik aktivdir
// Manual tətbiqlər üçün:
@Configuration
@EnableAspectJAutoProxy // Spring AOP-u aktivləşdirir
public class AopConfig {
}

// proxyTargetClass=true — həmişə CGLIB istifadə et
@Configuration
@EnableAspectJAutoProxy(proxyTargetClass = true)
public class AopConfig {
}
```

---

## Sadə Aspect nümunəsi

```java
@Aspect
@Component
public class AuditAspect {

    private final AuditRepository auditRepository;

    public AuditAspect(AuditRepository auditRepository) {
        this.auditRepository = auditRepository;
    }

    // @Audit annotasiyalı metodları izlə
    @Around("@annotation(com.example.annotation.Audit)")
    public Object auditMethod(ProceedingJoinPoint joinPoint) throws Throwable {
        String methodName = joinPoint.getSignature().getName();
        String className = joinPoint.getTarget().getClass().getSimpleName();
        Object[] args = joinPoint.getArgs();

        AuditLog log = new AuditLog();
        log.setMethod(className + "." + methodName);
        log.setTimestamp(LocalDateTime.now());

        try {
            Object result = joinPoint.proceed(args);
            log.setStatus("SUCCESS");
            return result;
        } catch (Exception e) {
            log.setStatus("FAILED");
            log.setError(e.getMessage());
            throw e;
        } finally {
            auditRepository.save(log);
        }
    }
}

// Custom annotation
@Retention(RetentionPolicy.RUNTIME)
@Target(ElementType.METHOD)
public @interface Audit {}

// İstifadəsi
@Service
public class PaymentService {

    @Audit // Bu metod audit ediləcək
    public void processPayment(Payment payment) {
        // ödəniş məntiqi
    }
}
```

---

## İntervyu Sualları

### 1. AOP nədir və hansı problemləri həll edir?
**Cavab:** AOP (Aspect-Oriented Programming) cross-cutting concern-ləri (logging, security, transaction) ayrı modulda toplamağa imkan verir. Kod duplikasiyasını aradan qaldırır, separation of concerns təmin edir.

### 2. Spring AOP-da hansı Advice növləri var?
**Cavab:** @Before, @After, @AfterReturning, @AfterThrowing, @Around. @Around ən güclüsüdür — metodun əvvəl və sonrasına müdaxilə edə bilir, hətta onu çağırmaya da bilər.

### 3. Pointcut ilə Advice fərqi nədir?
**Cavab:** Pointcut — hansı metodlara (joinpoint-lərə) tətbiq ediləcəyini müəyyən edən ifadədir. Advice — həmin metodlarda nə ediləcəyini müəyyən edən koddur.

### 4. Spring AOP-un məhdudiyyətləri nələrdir?
**Cavab:** Yalnız Spring bean-larına işləyir. Yalnız method execution joinpoint-ini dəstəkləyir. Self-invocation (bir sinifdən öz metodunu çağırmaq) proxy-dən keçmədiyindən AOP işləmir.

### 5. Spring AOP AspectJ-dən necə fərqlənir?
**Cavab:** Spring AOP runtime-da proxy mexanizmi ilə işləyir, yalnız method execution-ı dəstəkləyir. AspectJ compile/load-time weaving istifadə edir, field access, constructor call daxil olmaqla bütün joinpoint növlərini dəstəkləyir.

*Son yenilənmə: 2026-04-10*
