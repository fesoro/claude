# Spring AOP Pointcuts — Geniş İzah

## Mündəricat
1. [Pointcut nədir?](#pointcut-nədir)
2. [execution() ifadəsi](#execution-ifadəsi)
3. [within()](#within)
4. [@annotation()](#annotation)
5. [args()](#args)
6. [bean()](#bean)
7. [@within() və @args()](#within-və-args)
8. [Pointcut-ları birləşdirmək](#pointcut-ları-birləşdirmək)
9. [Named Pointcut (@Pointcut)](#named-pointcut-pointcut)
10. [İntervyu Sualları](#intervyu-sualları)

---

## Pointcut nədir?

**Pointcut** — AOP advice-ının hansı joinpoint-lərə (metod çağırışlarına) tətbiq ediləcəyini müəyyən edən ifadədir.

```java
@Aspect
@Component
public class MyAspect {

    // "execution(...)" — bu bir pointcut ifadəsidir
    @Before("execution(* com.example.service.*.*(..))")
    public void beforeServiceMethods(JoinPoint joinPoint) {
        System.out.println("Service metodu çağırıldı: " +
            joinPoint.getSignature().getName());
    }
}
```

---

## execution() ifadəsi

Ən çox istifadə edilən pointcut növüdür. Metod imzasına görə uyğunlaşır.

**Sintaksis:**
```
execution(modifiers? return-type declaring-type?.method-name(params) throws?)
```

```java
@Aspect
@Component
public class ExecutionExamples {

    // Bütün public metodlar
    @Before("execution(public * *(..))")
    public void allPublicMethods() {}

    // com.example.service paketindəki bütün metodlar
    @Before("execution(* com.example.service.*.*(..))")
    public void allServiceMethods() {}

    // com.example paketinin bütün alt-paketləri daxil
    @Before("execution(* com.example..*.*(..))")
    public void allMethodsInPackageAndSubpackages() {}

    // Konkret sinifin bütün metodları
    @Before("execution(* com.example.service.UserService.*(..))")
    public void allUserServiceMethods() {}

    // Konkret metod
    @Before("execution(* com.example.service.UserService.createUser(..))")
    public void createUserMethod() {}

    // String qaytaran bütün metodlar
    @Before("execution(String com.example..*.*(..))")
    public void methodsReturningString() {}

    // Birinci parametri String olan metodlar
    @Before("execution(* com.example..*.*(String, ..))")
    public void methodsWithStringFirstParam() {}

    // Heç bir parametri olmayan metodlar
    @Before("execution(* com.example..*.*()")
    public void methodsWithNoParams() {}

    // İki parametri olan metodlar
    @Before("execution(* com.example..*.*(*,*))")
    public void methodsWithTwoParams() {}

    // Xüsusi tip qaytaran metod
    @Before("execution(com.example.model.User com.example..*.*(..))")
    public void methodsReturningUser() {}
}
```

**Wildcard-lar:**
- `*` — bir token (paket adı, sinif adı, metod adı, bir parametr tipi)
- `..` — sıfır və ya daha çox (paketlər, parametrlər)

---

## within()

Müəyyən sinif və ya paket daxilindəki bütün metodları hədəf alır:

```java
@Aspect
@Component
public class WithinExamples {

    // Konkret sinif daxilindəki bütün metodlar
    @Before("within(com.example.service.UserService)")
    public void withinUserService() {}

    // Paket daxilindəki bütün siniflər
    @Before("within(com.example.service.*)")
    public void withinServicePackage() {}

    // Alt-paketlər daxil
    @Before("within(com.example..*)")
    public void withinExampleAndSubpackages() {}

    // Interface implementasiyaları
    @Before("within(com.example.service.UserService+)")
    // + işarəsi: UserService və onun bütün alt-sinifləri
    public void withinUserServiceAndSubclasses() {}
}
```

**execution() vs within() fərqi:**
```java
// execution() — metod imzasına baxır
@Before("execution(* com.example.service.*.*(..))")

// within() — sinifin yerləşdiyi yerə baxır
@Before("within(com.example.service.*)")

// Nəticə eynidir, amma within() daha oxunaqlıdır
```

---

## @annotation()

Müəyyən annotasiyaya malik metodları hədəf alır:

```java
// Custom annotation
@Retention(RetentionPolicy.RUNTIME)
@Target(ElementType.METHOD)
public @interface Loggable {}

@Retention(RetentionPolicy.RUNTIME)
@Target(ElementType.METHOD)
public @interface RequiresAdmin {}

@Aspect
@Component
public class AnnotationExamples {

    // @Loggable annotasiyalı metodlar
    @Before("@annotation(com.example.annotation.Loggable)")
    public void loggableMethods(JoinPoint jp) {
        System.out.println("Logging: " + jp.getSignature().getName());
    }

    // @RequiresAdmin annotasiyalı metodlar — annotasiyanı parametr kimi al
    @Before("@annotation(requiresAdmin)")
    public void checkAdmin(JoinPoint jp, RequiresAdmin requiresAdmin) {
        // requiresAdmin annotasiyasının atributlarına çıxış var
        checkAdminPermission();
    }

    // Praktik nümunə — @Transactional annotasiyası olan metodlar
    @Before("@annotation(org.springframework.transaction.annotation.Transactional)")
    public void beforeTransactional(JoinPoint jp) {
        System.out.println("Transaction başlayır: " + jp.getSignature());
    }
}

// İstifadəsi
@Service
public class ProductService {

    @Loggable // Bu metod AOP tərəfindən log ediləcək
    public Product createProduct(Product product) {
        return productRepository.save(product);
    }

    @RequiresAdmin // Bu metod üçün admin icazəsi yoxlanılacaq
    public void deleteProduct(Long id) {
        productRepository.deleteById(id);
    }
}
```

---

## args()

Metodun parametr tiplərinə görə uyğunlaşır:

```java
@Aspect
@Component
public class ArgsExamples {

    // İlk parametri Long olan metodlar
    @Before("args(Long, ..)")
    public void methodsWithLongFirstParam() {}

    // Yalnız bir String parametri olan metodlar
    @Before("args(String)")
    public void methodsWithOnlyStringParam() {}

    // Parametrə birbaşa çıxış
    @Before("execution(* com.example..*.*(..))" +
            " && args(userId, ..)")
    public void methodsWithUserId(JoinPoint jp, Long userId) {
        System.out.println("userId ilə çağırıldı: " + userId);
    }

    // @Around ilə parametri dəyişdirmək
    @Around("execution(* com.example.service.*.*(String, ..)) && args(input, ..)")
    public Object sanitizeInput(ProceedingJoinPoint pjp, String input) throws Throwable {
        // Input-u sanitize et
        String sanitized = input.trim().toLowerCase();
        return pjp.proceed(new Object[]{sanitized}); // dəyişdirilmiş parametrlə çağır
    }
}
```

---

## bean()

Spring bean adına görə uyğunlaşır:

```java
@Aspect
@Component
public class BeanExamples {

    // Konkret bean adı
    @Before("bean(userService)")
    public void onUserService() {}

    // Wildcard ilə — "Service" ilə bitən bütün bean-lar
    @Before("bean(*Service)")
    public void onAllServices() {}

    // Prefix ilə
    @Before("bean(user*)")
    public void onUserPrefixedBeans() {}
}
```

---

## @within() və @args()

```java
@Aspect
@Component
public class AdvancedExamples {

    // @Repository annotasiyalı siniflərdəki bütün metodlar
    @Before("@within(org.springframework.stereotype.Repository)")
    public void repositoryMethods() {
        System.out.println("Repository metodu çağırıldı");
    }

    // @Service annotasiyalı siniflərdəki metodlar
    @Before("@within(org.springframework.stereotype.Service)")
    public void serviceMethods() {}

    // Parametri müəyyən annotasiyalı tipdən olan metodlar
    @Before("@args(com.example.annotation.Validated)")
    public void methodsWithValidatedArgs() {}
}
```

---

## Pointcut-ları birləşdirmək

```java
@Aspect
@Component
public class CombinedPointcuts {

    // AND (&&) — hər iki şərt ödənməlidir
    @Before("execution(* com.example.service.*.*(..)) && " +
            "@annotation(com.example.annotation.Loggable)")
    public void serviceAndLoggable() {}

    // OR (||) — ən azı bir şərt ödənməlidir
    @Before("within(com.example.service.*) || within(com.example.controller.*)")
    public void serviceOrController() {}

    // NOT (!) — şərt ödənməməlidir
    @Before("execution(* com.example..*.*(..)) && " +
            "!execution(* com.example..*.*get*(..))")
    public void notGetters() {}

    // Mürəkkəb birləşmə
    @Before("(within(com.example.service.*) && @annotation(com.example.annotation.Audit)) " +
            "|| bean(*AdminService)")
    public void complexCondition() {}
}
```

---

## Named Pointcut (@Pointcut)

Pointcut ifadəsini bir yerdə müəyyən edib, hər yerdə istifadə etmək:

```java
@Aspect
@Component
public class AppPointcuts {

    // Reusable pointcut-lar
    @Pointcut("execution(* com.example.service.*.*(..))")
    public void serviceLayer() {} // Ad buradan götürülür

    @Pointcut("execution(* com.example.repository.*.*(..))")
    public void repositoryLayer() {}

    @Pointcut("execution(* com.example.controller.*.*(..))")
    public void controllerLayer() {}

    @Pointcut("@annotation(com.example.annotation.Loggable)")
    public void loggableOperation() {}

    @Pointcut("serviceLayer() || repositoryLayer()")
    public void dataAccessLayer() {}

    @Pointcut("serviceLayer() && loggableOperation()")
    public void loggableService() {}
}

// Başqa Aspect-də istifadə
@Aspect
@Component
public class LoggingAspect {

    @Before("com.example.aop.AppPointcuts.serviceLayer()")
    public void logServiceCall(JoinPoint jp) {
        System.out.println("Service çağırıldı: " + jp.getSignature());
    }

    @Around("com.example.aop.AppPointcuts.loggableService()")
    public Object logLoggableService(ProceedingJoinPoint pjp) throws Throwable {
        System.out.println("Loggable service başladı");
        Object result = pjp.proceed();
        System.out.println("Loggable service bitdi");
        return result;
    }
}
```

---

## İntervyu Sualları

### 1. execution() pointcut sintaksisini izah et
**Cavab:** `execution(modifiers? return-type declaring-type?.method-name(params) throws?)`. `*` bir token, `..` sıfır+ token deməkdir. Məsələn: `execution(* com.example.service.*.*(..))` — service paketindəki bütün sinifdəki bütün metodlar.

### 2. @annotation() ilə within() fərqi nədir?
**Cavab:** `@annotation()` — müəyyən annotasiyaya malik metodları hədəf alır. `@within()` isə müəyyən annotasiyaya malik sinifdəki bütün metodları hədəf alır. `@annotation` metod-level, `@within` sinif-level annotasiya yoxlayır.

### 3. Pointcut-ları necə birləşdirmək olar?
**Cavab:** `&&` (AND), `||` (OR), `!` (NOT) operatorları ilə. Məsələn: `execution(* com.example..*.*(..)) && @annotation(Loggable)`.

### 4. @Pointcut annotasiyasının faydası nədir?
**Cavab:** Pointcut ifadəsini bir dəfə yazıb, bir çox yerdə istifadə etməyə imkan verir. Dəyişiklik lazım olduqda bir yerdə dəyişmək kifayətdir. Nəticə: DRY prinsipi, oxunaqlıq.

### 5. bean() pointcut nə zaman istifadəlidir?
**Cavab:** Müəyyən bean adı və ya ad şablonuna görə filtrləmək lazım olduqda. Məsələn: `bean(*Service)` — adı "Service" ilə bitən bütün bean-ların metodlarını hədəf alır.

*Son yenilənmə: 2026-04-10*
