# Spring AOP @Around — Geniş İzah

## Mündəricat
1. [@Around nədir?](#around-nədir)
2. [ProceedingJoinPoint](#proceedingjoinpoint)
3. [Return dəyəri manipulyasiyası](#return-dəyəri-manipulyasiyası)
4. [Exception idarəetməsi](#exception-idarəetməsi)
5. [Performance logging aspect](#performance-logging-aspect)
6. [Caching aspect](#caching-aspect)
7. [Retry aspect](#retry-aspect)
8. [Rate limiting aspect](#rate-limiting-aspect)
9. [İntervyu Sualları](#intervyu-sualları)

---

## @Around nədir?

**@Around** — ən güclü advice növüdür. Hədəf metodun əvvəlini, sonrasını, hətta çağırılıb-çağırılmamasını tam nəzarət altına alır.

```java
@Aspect
@Component
public class BasicAroundAspect {

    @Around("execution(* com.example.service.*.*(..))")
    public Object aroundMethod(ProceedingJoinPoint joinPoint) throws Throwable {

        System.out.println("Metod çağırılmadan əvvəl");

        // Hədəf metodu çağır
        Object result = joinPoint.proceed();

        System.out.println("Metod çağırıldıqdan sonra");

        return result; // Nəticəni geri qaytar
    }
}
```

**Mühüm qaydalar:**
- `ProceedingJoinPoint` istifadə etmək **məcburidir** (@Around-a xasdır)
- `proceed()` çağırmaq **məcburidir** (çağırılmasa, hədəf metod işləmir)
- Qayıdış dəyərini `Object` olaraq qaytarmaq lazımdır
- `throws Throwable` imzada olmalıdır

---

## ProceedingJoinPoint

```java
@Aspect
@Component
public class ProceedingJoinPointDemo {

    @Around("execution(* com.example.service.*.*(..))")
    public Object demo(ProceedingJoinPoint pjp) throws Throwable {

        // Metod adı
        String methodName = pjp.getSignature().getName();

        // Sinif adı
        String className = pjp.getTarget().getClass().getSimpleName();

        // Parametrlər
        Object[] args = pjp.getArgs();

        // Orijinal parametrlərlə çağır
        Object result = pjp.proceed();

        // Dəyişdirilmiş parametrlərlə çağır
        Object[] modifiedArgs = modifyArgs(args);
        Object result2 = pjp.proceed(modifiedArgs);

        return result;
    }

    private Object[] modifyArgs(Object[] originalArgs) {
        // Parametrləri dəyişdirmək
        Object[] modified = Arrays.copyOf(originalArgs, originalArgs.length);
        if (modified.length > 0 && modified[0] instanceof String str) {
            modified[0] = str.trim().toLowerCase(); // Sanitize
        }
        return modified;
    }
}
```

---

## Return dəyəri manipulyasiyası

```java
@Aspect
@Component
public class ReturnValueAspect {

    // Null dəyər qaytarma — metodun çağırılmasının qarşısını al
    @Around("@annotation(com.example.annotation.SkipIfDisabled)")
    public Object skipIfFeatureDisabled(ProceedingJoinPoint pjp) throws Throwable {
        MethodSignature signature = (MethodSignature) pjp.getSignature();
        SkipIfDisabled annotation = signature.getMethod()
            .getAnnotation(SkipIfDisabled.class);

        String featureName = annotation.value();

        if (!featureToggleService.isEnabled(featureName)) {
            // Metodu çağırmadan qayıt
            Class<?> returnType = signature.getReturnType();
            if (returnType == void.class) return null;
            if (returnType == boolean.class) return false;
            if (returnType == int.class) return 0;
            return null; // Object tiplər üçün
        }

        return pjp.proceed();
    }

    // Nəticəni decrypt et (şifrəli DB-dən oxunduqda)
    @Around("@annotation(com.example.annotation.Encrypted)")
    public Object decryptResult(ProceedingJoinPoint pjp) throws Throwable {
        Object result = pjp.proceed();

        if (result instanceof String encrypted) {
            return encryptionService.decrypt(encrypted);
        }

        if (result instanceof List<?> list) {
            return list.stream()
                .map(item -> item instanceof String s ?
                    encryptionService.decrypt(s) : item)
                .collect(Collectors.toList());
        }

        return result;
    }

    // Nəticəni mask et (sensitive data)
    @Around("execution(* com.example.service.UserService.findAll(..))")
    public Object maskSensitiveData(ProceedingJoinPoint pjp) throws Throwable {
        Object result = pjp.proceed();

        if (result instanceof List<?> users) {
            return users.stream()
                .filter(u -> u instanceof User)
                .map(u -> {
                    User user = (User) u;
                    user.setPassword("***");
                    user.setCreditCard(maskCardNumber(user.getCreditCard()));
                    return user;
                })
                .collect(Collectors.toList());
        }

        return result;
    }

    private String maskCardNumber(String cardNumber) {
        if (cardNumber == null || cardNumber.length() < 4) return "****";
        return "**** **** **** " + cardNumber.substring(cardNumber.length() - 4);
    }
}
```

---

## Exception idarəetməsi

```java
@Aspect
@Component
public class ExceptionTranslationAspect {

    // Exception növünü dəyişdirmək
    @Around("within(com.example.repository.*)")
    public Object translateException(ProceedingJoinPoint pjp) throws Throwable {
        try {
            return pjp.proceed();
        } catch (SQLException e) {
            // SQL exception-ı application exception-a çevir
            throw new DataAccessException("Verilənlər bazası xətası", e);
        } catch (ConnectionException e) {
            throw new ServiceUnavailableException("DB bağlantısı uğursuz", e);
        }
    }

    // Uğursuz halda default dəyər qaytar
    @Around("@annotation(com.example.annotation.Fallback)")
    public Object returnFallback(ProceedingJoinPoint pjp) throws Throwable {
        MethodSignature sig = (MethodSignature) pjp.getSignature();
        Fallback fallback = sig.getMethod().getAnnotation(Fallback.class);

        try {
            return pjp.proceed();
        } catch (Exception e) {
            log.warn("Metod uğursuz oldu, fallback qaytarılır: {}", e.getMessage());

            // Fallback dəyərini qaytaraq
            Class<?> returnType = sig.getReturnType();
            if (returnType == List.class) return Collections.emptyList();
            if (returnType == Optional.class) return Optional.empty();
            if (returnType == String.class) return fallback.defaultValue();
            return null;
        }
    }
}
```

---

## Performance logging aspect

```java
@Aspect
@Component
public class PerformanceAspect {

    private static final Logger log = LoggerFactory.getLogger(PerformanceAspect.class);
    private static final long SLOW_THRESHOLD_MS = 1000;

    @Around("execution(* com.example.service.*.*(..))" +
            " || execution(* com.example.repository.*.*(..))")
    public Object measurePerformance(ProceedingJoinPoint pjp) throws Throwable {
        String methodName = pjp.getSignature().toShortString();
        long startTime = System.currentTimeMillis();

        try {
            Object result = pjp.proceed();
            long duration = System.currentTimeMillis() - startTime;

            if (duration > SLOW_THRESHOLD_MS) {
                log.warn("YAVAŞ METOD: {} — {} ms", methodName, duration);
            } else {
                log.debug("Metod: {} — {} ms", methodName, duration);
            }

            // Micrometer metric
            meterRegistry.timer("method.execution",
                "method", methodName,
                "status", "success"
            ).record(duration, TimeUnit.MILLISECONDS);

            return result;
        } catch (Throwable t) {
            long duration = System.currentTimeMillis() - startTime;
            log.error("Metod xəta ilə bitdi: {} — {} ms", methodName, duration);

            meterRegistry.timer("method.execution",
                "method", methodName,
                "status", "error"
            ).record(duration, TimeUnit.MILLISECONDS);

            throw t;
        }
    }
}
```

---

## Caching aspect

```java
@Aspect
@Component
public class CustomCachingAspect {

    private final Map<String, Object> cache = new ConcurrentHashMap<>();

    @Around("@annotation(cacheable)")
    public Object cache(ProceedingJoinPoint pjp, CustomCacheable cacheable) throws Throwable {
        // Cache key yaratmaq
        String key = cacheable.key().isEmpty()
            ? generateKey(pjp)
            : cacheable.key();

        // Cache-dən yoxla
        if (cache.containsKey(key)) {
            log.debug("Cache hit: {}", key);
            return cache.get(key);
        }

        // Cache miss — metodu çağır
        log.debug("Cache miss: {}", key);
        Object result = pjp.proceed();

        // Nəticəni cache-ə əlavə et
        if (result != null) {
            cache.put(key, result);
        }

        return result;
    }

    private String generateKey(ProceedingJoinPoint pjp) {
        return pjp.getSignature().toShortString() +
               Arrays.toString(pjp.getArgs());
    }
}

// Custom annotation
@Retention(RetentionPolicy.RUNTIME)
@Target(ElementType.METHOD)
public @interface CustomCacheable {
    String key() default "";
}
```

---

## Retry aspect

```java
@Aspect
@Component
public class RetryAspect {

    @Around("@annotation(retryable)")
    public Object retry(ProceedingJoinPoint pjp, Retryable retryable) throws Throwable {
        int maxAttempts = retryable.maxAttempts();
        long delay = retryable.delay();
        Class<? extends Throwable>[] retryOn = retryable.retryOn();

        Throwable lastException = null;

        for (int attempt = 1; attempt <= maxAttempts; attempt++) {
            try {
                return pjp.proceed();
            } catch (Throwable e) {
                lastException = e;

                // Bu exception tipi üçün retry etmək lazımdırmı?
                boolean shouldRetry = Arrays.stream(retryOn)
                    .anyMatch(type -> type.isInstance(e));

                if (!shouldRetry) {
                    throw e;
                }

                log.warn("Cəhd {}/{} uğursuz: {}. {} ms sonra yenidən cəhd...",
                    attempt, maxAttempts, e.getMessage(), delay);

                if (attempt < maxAttempts) {
                    Thread.sleep(delay * attempt); // Exponential backoff
                }
            }
        }

        throw new RetryExhaustedException(
            "Bütün " + maxAttempts + " cəhd uğursuz oldu", lastException);
    }
}

// Custom annotation
@Retention(RetentionPolicy.RUNTIME)
@Target(ElementType.METHOD)
public @interface Retryable {
    int maxAttempts() default 3;
    long delay() default 1000; // ms
    Class<? extends Throwable>[] retryOn() default {Exception.class};
}

// İstifadəsi
@Service
public class ExternalApiService {

    @Retryable(maxAttempts = 3, delay = 500,
               retryOn = {HttpClientErrorException.class, ConnectException.class})
    public String callExternalApi(String endpoint) {
        return restTemplate.getForObject(endpoint, String.class);
    }
}
```

---

## Rate limiting aspect

```java
@Aspect
@Component
public class RateLimitingAspect {

    private final Map<String, RateLimiter> limiters = new ConcurrentHashMap<>();

    @Around("@annotation(rateLimit)")
    public Object rateLimit(ProceedingJoinPoint pjp, RateLimit rateLimit) throws Throwable {
        String key = pjp.getSignature().toShortString();

        RateLimiter limiter = limiters.computeIfAbsent(key,
            k -> RateLimiter.create(rateLimit.permitsPerSecond()));

        if (!limiter.tryAcquire()) {
            throw new TooManyRequestsException(
                "Rate limit aşıldı: " + rateLimit.permitsPerSecond() + " req/s");
        }

        return pjp.proceed();
    }
}
```

---

## İntervyu Sualları

### 1. @Around niyə digər advice-lardan daha güclüdür?
**Cavab:** @Around hədəf metodun əvvəlini və sonrasını tam nəzarət altına alır. Metodu çağırmamaq, parametrləri dəyişdirmək, qayıdış dəyərini dəyişdirmək, exception-ı tutmaq/dəyişdirmək — hamısı mümkündür.

### 2. @Around-da proceed() çağırılmasa nə baş verir?
**Cavab:** Hədəf metod heç vaxt çağırılmır. Bu bəzən istənilədir (feature flag, rate limiting). Lakin təsadüfən unudulsa — bug-dur, metod heç vaxt işləməz.

### 3. @Around-da parametrləri dəyişdirmək üçün nə etmək lazımdır?
**Cavab:** `pjp.proceed(modifiedArgs)` çağırılır. `modifiedArgs` — dəyişdirilmiş parametrlər massivi. Orijinal massiv kopyalanıb dəyişdirilməlidir.

### 4. @Around exception-ı tutsa, ona nə etmək olar?
**Cavab:** Exception-ı ləğv edib default dəyər qaytarmaq, başqa exception atılmaq, və ya exception-ı log edib yenidən atmaq olar. `throw e` yazılmasa — exception udulur və metod normal qayıtmış kimi görünür.

### 5. Retry logic üçün niyə @Around daha uyğundur?
**Cavab:** Çünki @Around metodu bir neçə dəfə çağıra bilər (`proceed()` döngüdə çağırılır). Digər advice-lar bu imkana malik deyil.

*Son yenilənmə: 2026-04-10*
