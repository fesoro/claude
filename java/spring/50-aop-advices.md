# 50 — Spring AOP Advices — Geniş İzah

> **Seviyye:** Senior ⭐⭐⭐


## Mündəricat
1. [@Before](#before)
2. [@After](#after)
3. [@AfterReturning](#afterreturning)
4. [@AfterThrowing](#afterthrowing)
5. [JoinPoint istifadəsi](#joinpoint-istifadəsi)
6. [Advice icra sırası](#advice-icra-sırası)
7. [İntervyu Sualları](#intervyu-sualları)

---

## @Before

Hədəf metod çağırılmadan **əvvəl** işləyir:

```java
@Aspect
@Component
public class SecurityAspect {

    // Sadə @Before
    @Before("execution(* com.example.service.*.*(..))")
    public void checkAuthentication(JoinPoint joinPoint) {
        String methodName = joinPoint.getSignature().getName();
        System.out.println("Autentifikasiya yoxlanılır: " + methodName);

        // İstifadəçi autentifikasiya olunmayıbsa exception at
        Authentication auth = SecurityContextHolder.getContext().getAuthentication();
        if (auth == null || !auth.isAuthenticated()) {
            throw new UnauthorizedException("Autentifikasiya tələb olunur");
        }
    }

    // @RequiresRole annotasiyası ilə
    @Before("@annotation(requiresRole)")
    public void checkRole(JoinPoint joinPoint, RequiresRole requiresRole) {
        String requiredRole = requiresRole.value();
        // Rolü yoxla
        if (!hasRole(requiredRole)) {
            throw new AccessDeniedException("Rol tələb olunur: " + requiredRole);
        }
    }

    private boolean hasRole(String role) {
        // SecurityContext-dən rolu yoxla
        return SecurityContextHolder.getContext()
            .getAuthentication()
            .getAuthorities()
            .stream()
            .anyMatch(a -> a.getAuthority().equals("ROLE_" + role));
    }
}
```

**Qeyd:** @Before-da exception atılarsa, hədəf metod çağırılmaz.

---

## @After

Hədəf metod çağırılandan **sonra**, nəticədən asılı olmayaraq işləyir (finally kimi):

```java
@Aspect
@Component
public class ResourceCleanupAspect {

    // Həmişə işləyir — uğurlu olsun ya xəta olsun
    @After("execution(* com.example.service.*.*(..))")
    public void cleanup(JoinPoint joinPoint) {
        String methodName = joinPoint.getSignature().getName();
        System.out.println("Cleanup: " + methodName);
        // Resursları azad et, əlaqələri bağla
    }

    // Praktik nümunə — MDC (Mapped Diagnostic Context) təmizləmə
    @Before("execution(* com.example.service.*.*(..))")
    public void setMDC(JoinPoint joinPoint) {
        MDC.put("method", joinPoint.getSignature().getName());
        MDC.put("requestId", UUID.randomUUID().toString());
    }

    @After("execution(* com.example.service.*.*(..))")
    public void clearMDC() {
        MDC.clear(); // Exception olsun ya olmasın, MDC təmizlənir
    }
}
```

---

## @AfterReturning

Hədəf metod **uğurla** qayıtdıqdan sonra işləyir. Qayıdış dəyərinə çıxış var:

```java
@Aspect
@Component
public class CachingAspect {

    private final CacheService cacheService;

    public CachingAspect(CacheService cacheService) {
        this.cacheService = cacheService;
    }

    // returning="result" — qayıdış dəyərini tut
    @AfterReturning(
        pointcut = "execution(* com.example.service.UserService.findUser(..))",
        returning = "result"  // method imzasındakı parametr adı ilə eyni olmalı
    )
    public void cacheUser(JoinPoint joinPoint, Object result) {
        if (result instanceof User user) {
            // Nəticəni cache-ə əlavə et
            cacheService.put("user:" + user.getId(), user);
            System.out.println("User cache-ə əlavə edildi: " + user.getId());
        }
    }

    // Daha spesifik tip
    @AfterReturning(
        pointcut = "execution(* com.example.repository.OrderRepository.save(..))",
        returning = "savedOrder"
    )
    public void afterOrderSaved(JoinPoint joinPoint, Order savedOrder) {
        System.out.println("Order saxlanıldı, ID: " + savedOrder.getId());
        // Audit log, notification...
    }
}

// Audit nümunəsi
@Aspect
@Component
public class AuditAspect {

    @AfterReturning(
        pointcut = "@annotation(com.example.annotation.Auditable)",
        returning = "returnValue"
    )
    public void audit(JoinPoint joinPoint, Object returnValue) {
        AuditEntry entry = new AuditEntry();
        entry.setMethod(joinPoint.getSignature().toShortString());
        entry.setArgs(Arrays.toString(joinPoint.getArgs()));
        entry.setResult(returnValue != null ? returnValue.toString() : "null");
        entry.setTimestamp(LocalDateTime.now());
        auditRepository.save(entry);
    }
}
```

**Qeyd:** @AfterReturning-da qayıdış dəyərini dəyişdirmək olmaz. Bunun üçün @Around lazımdır.

---

## @AfterThrowing

Hədəf metod **exception** atdıqda işləyir:

```java
@Aspect
@Component
public class ExceptionHandlingAspect {

    private final SlackNotificationService slackService;

    // Bütün exception-lar
    @AfterThrowing(
        pointcut = "execution(* com.example.service.*.*(..))",
        throwing = "exception"  // parametr adı ilə eyni
    )
    public void handleException(JoinPoint joinPoint, Exception exception) {
        String method = joinPoint.getSignature().toShortString();
        log.error("Exception in {}: {}", method, exception.getMessage());

        // Slack-ə bildiriş göndər
        slackService.sendAlert("Exception: " + method + " - " + exception.getMessage());
    }

    // Yalnız müəyyən exception tipi
    @AfterThrowing(
        pointcut = "execution(* com.example.repository.*.*(..))",
        throwing = "sqlException"
    )
    public void handleSQLException(JoinPoint joinPoint, SQLException sqlException) {
        log.error("SQL xətası: {}", sqlException.getMessage());
        // Metric qeyd et
        metrics.incrementCounter("db.errors");
    }

    // DataAccessException üçün
    @AfterThrowing(
        pointcut = "within(com.example.repository.*)",
        throwing = "ex"
    )
    public void handleDataAccessException(JoinPoint jp, DataAccessException ex) {
        log.error("Verilənlər bazası xətası: {}", ex.getMessage());
        throw new ApplicationException("Verilənlər bazasına müraciət uğursuz oldu", ex);
    }
}
```

**Qeyd:** @AfterThrowing exception-ı ləğv etmir — yenidən atılır. Exception-ı ləğv etmək üçün @Around lazımdır.

---

## JoinPoint istifadəsi

```java
@Aspect
@Component
public class DetailedLoggingAspect {

    @Before("execution(* com.example..*.*(..))")
    public void logMethodDetails(JoinPoint joinPoint) {

        // Metod adı
        String methodName = joinPoint.getSignature().getName();

        // Tam sinif adı
        String className = joinPoint.getTarget().getClass().getName();

        // Metod imzası
        String signature = joinPoint.getSignature().toShortString();
        // Nümunə: "UserService.createUser(..)"

        // Parametrlər
        Object[] args = joinPoint.getArgs();
        String argsStr = Arrays.stream(args)
            .map(arg -> arg != null ? arg.toString() : "null")
            .collect(Collectors.joining(", "));

        // Target obyekt (orijinal bean)
        Object target = joinPoint.getTarget();

        // Proxy (Spring-in yaratdığı)
        Object proxy = joinPoint.getThis();

        log.info("[{}] {}({}) çağırıldı",
            className, methodName, argsStr);
    }

    // MethodSignature — daha ətraflı metod məlumatı
    @Before("execution(* com.example.service.*.*(..))")
    public void getMethodDetails(JoinPoint joinPoint) {
        MethodSignature signature = (MethodSignature) joinPoint.getSignature();

        // Return tipi
        Class<?> returnType = signature.getReturnType();

        // Parametr tipləri
        Class<?>[] paramTypes = signature.getParameterTypes();

        // Parametr adları
        String[] paramNames = signature.getParameterNames();

        // Method obyekti
        Method method = signature.getMethod();

        // Metodun annotasiyalarına çıxış
        Loggable loggable = method.getAnnotation(Loggable.class);
        if (loggable != null) {
            System.out.println("Loggable annotation tapıldı");
        }
    }
}
```

---

## Advice icra sırası

```
@Around (əvvəl hissə)
    ↓
@Before
    ↓
[Hədəf Metod]
    ↓
@AfterReturning (uğurlu halda)
@AfterThrowing (exception halında)
    ↓
@After (hər iki halda)
    ↓
@Around (sonra hissə)
```

```java
@Aspect
@Component
@Order(1) // Bir neçə Aspect varsa, sıra
public class FullLifecycleAspect {

    @Around("execution(* com.example.service.UserService.createUser(..))")
    public Object around(ProceedingJoinPoint pjp) throws Throwable {
        System.out.println("1. @Around — əvvəl");
        try {
            Object result = pjp.proceed();
            System.out.println("4. @Around — sonra (uğurlu)");
            return result;
        } catch (Throwable t) {
            System.out.println("4. @Around — sonra (xəta)");
            throw t;
        }
    }

    @Before("execution(* com.example.service.UserService.createUser(..))")
    public void before() {
        System.out.println("2. @Before");
    }

    @After("execution(* com.example.service.UserService.createUser(..))")
    public void after() {
        System.out.println("5. @After");
    }

    @AfterReturning("execution(* com.example.service.UserService.createUser(..))")
    public void afterReturning() {
        System.out.println("3. @AfterReturning");
    }

    @AfterThrowing("execution(* com.example.service.UserService.createUser(..))")
    public void afterThrowing() {
        System.out.println("3. @AfterThrowing");
    }
}

// Uğurlu halda çıxış:
// 1. @Around — əvvəl
// 2. @Before
// [metod işləyir]
// 3. @AfterReturning
// 5. @After
// 4. @Around — sonra (uğurlu)

// Exception halında:
// 1. @Around — əvvəl
// 2. @Before
// [metod exception atır]
// 3. @AfterThrowing
// 5. @After
// 4. @Around — sonra (xəta)
```

---

## İntervyu Sualları

### 1. @After ilə @AfterReturning fərqi nədir?
**Cavab:** @After həmişə işləyir (finally kimi) — həm uğurlu halda, həm exception halında. @AfterReturning yalnız metod uğurla qayıtdıqda işləyir. Exception atıldıqda @AfterReturning çağırılmır.

### 2. @AfterReturning-da qayıdış dəyərini dəyişdirmək olurmu?
**Cavab:** Xeyr. @AfterReturning yalnız oxumaq üçündür. Qayıdış dəyərini dəyişdirmək üçün @Around lazımdır.

### 3. @AfterThrowing exception-ı tutub ləğv edə bilərmi?
**Cavab:** Xeyr. @AfterThrowing exception-ı ləğv edə bilməz — o, yenidən atılır. Exception-ı tutmaq və ya dəyişdirmək üçün @Around istifadə etmək lazımdır.

### 4. JoinPoint ilə ProceedingJoinPoint fərqi nədir?
**Cavab:** JoinPoint — bütün advice-larda (Before, After, AfterReturning, AfterThrowing) istifadə edilə bilər, metod haqqında məlumat verir. ProceedingJoinPoint — yalnız @Around-da istifadə edilir, əlavə olaraq `proceed()` metodu var ki, hədəf metodu çağırmağa imkan verir.

### 5. Advice-ların icra sırası necədir?
**Cavab:** @Around (əvvəl) → @Before → [Metod] → @AfterReturning/@AfterThrowing → @After → @Around (sonra). Bir neçə Aspect varsa, @Order ilə sıra müəyyən edilir.

*Son yenilənmə: 2026-04-10*
