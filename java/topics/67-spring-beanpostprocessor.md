# Spring BeanPostProcessor — Geniş İzah

## Mündəricat
1. [BeanPostProcessor nədir?](#beanpostprocessor-nədir)
2. [postProcessBeforeInitialization](#postprocessbeforeinitialization)
3. [postProcessAfterInitialization](#postprocessafterinitialization)
4. [BeanFactoryPostProcessor](#beanfactorypostprocessor)
5. [BeanDefinitionRegistryPostProcessor](#beandefinitionregistrypostprocessor)
6. [Real nümunələr](#real-nümunələr)
7. [Ordering](#ordering)
8. [İntervyu Sualları](#intervyu-sualları)

---

## BeanPostProcessor nədir?

**BeanPostProcessor** — Spring container-da hər bean yaradıldıqdan sonra, initialization mərhələsinin əvvəl və sonrasında müdaxilə etməyə imkan verən interfeysdır.

```java
public interface BeanPostProcessor {
    // @PostConstruct / afterPropertiesSet() əvvəl
    Object postProcessBeforeInitialization(Object bean, String beanName);

    // @PostConstruct / afterPropertiesSet() sonra
    Object postProcessAfterInitialization(Object bean, String beanName);
}
```

**Bean lifecycle-da yeri:**
```
Bean yaradıldı
    → DI tamamlandı
    → BeanPostProcessor.postProcessBeforeInitialization()
    → @PostConstruct / afterPropertiesSet()
    → BeanPostProcessor.postProcessAfterInitialization()   ← AOP proxy burada yaradılır
    → Bean istifadəyə hazır
```

---

## postProcessBeforeInitialization

```java
@Component
public class LoggingBeanPostProcessor implements BeanPostProcessor {

    @Override
    public Object postProcessBeforeInitialization(Object bean, String beanName) {
        // Hər bean üçün initialization əvvəl çağırılır
        System.out.println("BEFORE init: " + beanName +
                           " [" + bean.getClass().getSimpleName() + "]");

        // Eyni bean-i qaytarmaq lazımdır (və ya dəyişdirilmiş versiyasını)
        return bean;
    }

    @Override
    public Object postProcessAfterInitialization(Object bean, String beanName) {
        return bean; // Dəyişiklik yoxdur
    }
}
```

---

## postProcessAfterInitialization

Bu mərhələdə AOP proxy-lər yaradılır. Siz de öz proxy-nizi yarada bilərsiniz:

```java
@Component
public class TimingBeanPostProcessor implements BeanPostProcessor {

    @Override
    public Object postProcessBeforeInitialization(Object bean, String beanName) {
        return bean;
    }

    @Override
    public Object postProcessAfterInitialization(Object bean, String beanName) {
        // Yalnız @Timed annotasiyalı siniflər üçün proxy yarat
        if (bean.getClass().isAnnotationPresent(Timed.class)) {
            return Proxy.newProxyInstance(
                bean.getClass().getClassLoader(),
                bean.getClass().getInterfaces(),
                (proxy, method, args) -> {
                    long start = System.currentTimeMillis();
                    Object result = method.invoke(bean, args);
                    long duration = System.currentTimeMillis() - start;
                    System.out.println(method.getName() + " took " + duration + "ms");
                    return result;
                }
            );
        }
        return bean;
    }
}
```

---

## BeanFactoryPostProcessor

**BeanFactoryPostProcessor** — BeanDefinition-lar yaradıldıqdan sonra, lakin bean instance-ları yaradılmadan əvvəl işləyir. BeanDefinition-ları dəyişdirməyə imkan verir.

```java
@Component
public class CustomBeanFactoryPostProcessor implements BeanFactoryPostProcessor {

    @Override
    public void postProcessBeanFactory(ConfigurableListableBeanFactory beanFactory) {
        // BeanDefinition-ı dəyişdirmək
        BeanDefinition bd = beanFactory.getBeanDefinition("userService");

        // Scope-u dəyişdirmək
        bd.setScope(BeanDefinition.SCOPE_PROTOTYPE);

        // Property dəyərini dəyişdirmək
        bd.getPropertyValues().add("maxRetries", 5);

        System.out.println("BeanDefinition dəyişdirildi: userService");
    }
}
```

**PropertySourcesPlaceholderConfigurer** — ən məşhur BeanFactoryPostProcessor:
```java
// @Value("${app.name}") işləməsi üçün Spring avtomatik əlavə edir
@Bean
public static PropertySourcesPlaceholderConfigurer placeholderConfigurer() {
    return new PropertySourcesPlaceholderConfigurer();
}
```

---

## BeanDefinitionRegistryPostProcessor

**BeanDefinitionRegistryPostProcessor** — BeanFactoryPostProcessor-dən daha erkən işləyir. Yeni BeanDefinition-lar əlavə etməyə imkan verir.

```java
@Component
public class DynamicBeanRegistrar implements BeanDefinitionRegistryPostProcessor {

    @Override
    public void postProcessBeanDefinitionRegistry(BeanDefinitionRegistry registry) {
        // Proqramatik şəkildə yeni bean qeydiyyatdan keçirmək
        RootBeanDefinition beanDef = new RootBeanDefinition(DynamicService.class);
        beanDef.setScope(BeanDefinition.SCOPE_SINGLETON);
        registry.registerBeanDefinition("dynamicService", beanDef);
        System.out.println("dynamicService qeydiyyatdan keçirildi");
    }

    @Override
    public void postProcessBeanFactory(ConfigurableListableBeanFactory beanFactory) {
        // BeanFactoryPostProcessor hissəsi
    }
}
```

---

## Real nümunələr

### @Autowired — BeanPostProcessor tərəfindən işlənir

Spring-in `AutowiredAnnotationBeanPostProcessor` sinfi `@Autowired` annotasiyasını emal edir:

```java
// Bu annotation-ı emal edən məhz BeanPostProcessor-dur
@Service
public class UserService {

    @Autowired  // AutowiredAnnotationBeanPostProcessor tərəfindən inject edilir
    private UserRepository userRepository;
}
```

### Custom annotation emalı

```java
// Custom annotation
@Retention(RetentionPolicy.RUNTIME)
@Target(ElementType.FIELD)
public @interface Encrypted {}

// Bu annotation-ı emal edən BeanPostProcessor
@Component
public class EncryptionBeanPostProcessor implements BeanPostProcessor {

    @Override
    public Object postProcessAfterInitialization(Object bean, String beanName) {
        Field[] fields = bean.getClass().getDeclaredFields();

        for (Field field : fields) {
            if (field.isAnnotationPresent(Encrypted.class)) {
                field.setAccessible(true);
                try {
                    String value = (String) field.get(bean);
                    if (value != null) {
                        // Dəyəri şifrələ
                        field.set(bean, encrypt(value));
                    }
                } catch (IllegalAccessException e) {
                    throw new RuntimeException(e);
                }
            }
        }
        return bean;
    }

    private String encrypt(String value) {
        // Sadə nümunə üçün
        return "ENC(" + value + ")";
    }
}
```

---

## Ordering

Bir neçə BeanPostProcessor olduqda sıra @Order və ya Ordered interfeysi ilə müəyyən edilir:

```java
// 1. @Order annotasiyası ilə
@Component
@Order(1) // Aşağı rəqəm = daha əvvəl işləyir
public class FirstBeanPostProcessor implements BeanPostProcessor {
    // ...
}

@Component
@Order(2)
public class SecondBeanPostProcessor implements BeanPostProcessor {
    // ...
}

// 2. Ordered interfeysi ilə
@Component
public class PriorityBeanPostProcessor implements BeanPostProcessor, Ordered {

    @Override
    public int getOrder() {
        return Ordered.HIGHEST_PRECEDENCE; // Ən əvvəl işlə
    }

    @Override
    public Object postProcessBeforeInitialization(Object bean, String beanName) {
        return bean;
    }

    @Override
    public Object postProcessAfterInitialization(Object bean, String beanName) {
        return bean;
    }
}
```

**Qeyd:** BeanPostProcessor-lər digər bean-lardan əvvəl yaradılır. Əgər BeanPostProcessor başqa bir bean-a @Autowired ilə bağlıdırsa, həmin bean da erkən yaradılır.

---

## İntervyu Sualları

### 1. BeanPostProcessor nə üçün istifadə edilir?
**Cavab:** Bean initialization mərhələsinin əvvəl və sonrasında müdaxilə etmək üçün. AOP proxy yaratma, @Autowired injection, @Scheduled emalı — hamısı BeanPostProcessor-lar vasitəsilə həyata keçirilir.

### 2. BeanPostProcessor ilə BeanFactoryPostProcessor fərqi nədir?
**Cavab:** BeanFactoryPostProcessor bean instance-ları yaradılmadan əvvəl BeanDefinition-ları dəyişdirir. BeanPostProcessor isə hər bean yaradıldıqdan sonra, initialization mərhələsinin ətrafında işləyir.

### 3. postProcessAfterInitialization-da null qaytarsaq nə baş verir?
**Cavab:** Həmin bean üçün null register edilir. Bu çox təhlükəlidir — digər bean-lar həmin bean-ı inject edə bilməz. Həmişə dəyişdirilmiş və ya orijinal bean-ı qaytarmaq lazımdır.

### 4. Spring AOP proxy-ləri hansı mərhələdə yaradılır?
**Cavab:** `AnnotationAwareAspectJAutoProxyCreator` (BeanPostProcessor-dur) `postProcessAfterInitialization` mərhələsində AOP proxy-lər yaradır.

### 5. BeanPostProcessor özü bean-a @Autowired ilə inject edilə bilərmi?
**Cavab:** Texniki olaraq bəli, amma diqqətli olmaq lazımdır. BeanPostProcessor-lar çox erkən yaradılır, buna görə özlərinin inject etdiyi bean-lar BeanPostProcessor emalından keçməyə bilər. Spring bu barədə xəbərdarlıq edir.

*Son yenilənmə: 2026-04-10*
