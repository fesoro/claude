# 58. Spring IoC Container

## Mündəricat
1. [IoC (Inversion of Control) nədir?](#ioc-nedir)
2. [DI vs IoC fərqi](#di-vs-ioc)
3. [BeanFactory vs ApplicationContext](#beanfactory-vs-applicationcontext)
4. [ApplicationContext növləri](#applicationcontext-novleri)
5. [Spring Container başlatma](#spring-container-baslatma)
6. [Container-in bean idarəetməsi](#container-bean-idareetmesi)
7. [İntervyu Sualları](#intervyu-suallar)

---

## IoC (Inversion of Control) nədir?

**IoC** — proqramın axışının idarəsinin developer-dən framework-ə verilməsidir. Ənənəvi proqramlaşdırmada developer obyektləri özü yaradır, IoC-da isə bu məsuliyyət container-ə ötürülür.

### Ənənəvi yanaşma (IoC olmadan)

```java
// YANLIŞ: Obyektləri özümüz idarə edirik
public class OrderService {
    // Dependency-ni özümüz yaradırıq — tight coupling
    private PaymentService paymentService = new PaymentService();
    private EmailService emailService = new EmailService();

    public void placeOrder(Order order) {
        paymentService.processPayment(order);
        emailService.sendConfirmation(order);
    }
}
```

**Problemlər:**
- `OrderService` `PaymentService`-in konkret implementasiyasına bağlıdır
- Unit test üçün mock etmək çətindir
- Dependency-ləri dəyişdirmək üçün kod dəyişikliyi lazımdır

### IoC ilə yanaşma

```java
// DOĞRU: Container dependency-ləri inject edir
@Service
public class OrderService {
    // Dependency-ləri biz yaratmırıq, container inject edir
    private final PaymentService paymentService;
    private final EmailService emailService;

    // Constructor injection — tövsiyə olunan üsul
    public OrderService(PaymentService paymentService, EmailService emailService) {
        this.paymentService = paymentService;
        this.emailService = emailService;
    }

    public void placeOrder(Order order) {
        paymentService.processPayment(order);
        emailService.sendConfirmation(order);
    }
}
```

**IoC-un üstünlükləri:**
- Loose coupling
- Test edilə bilərlik (mocklamaq asan)
- Kodu dəyişmədən implementation dəyişdirmək mümkün
- Dependency-lərin idarəsi mərkəzləşdirilmiş

---

## DI vs IoC fərqi

**IoC** — daha geniş prinsipdir. Proqramın axışının idarəsi developer-dən başqa mexanizmə keçir.

**DI (Dependency Injection)** — IoC-un konkret bir tətbiqidir. Dependency-lər xaricdən inject edilir.

```
IoC (prinsip)
  ├── Dependency Injection (DI)
  │     ├── Constructor Injection
  │     ├── Setter Injection
  │     └── Field Injection
  ├── Service Locator Pattern
  ├── Template Method Pattern
  └── Strategy Pattern
```

```java
// DI — IoC-un bir formasıdır
// Container hansı dependency-nin lazım olduğunu bilir
// və onu avtomatik inject edir

@Service
public class UserService {
    private final UserRepository userRepository; // IoC container inject edir

    public UserService(UserRepository userRepository) {
        this.userRepository = userRepository;
    }
}

// Service Locator — IoC-un başqa forması (tövsiyə olunmur)
public class UserServiceWithLocator {
    public User findUser(Long id) {
        // Service-i özümüz axtarırıq — bu da IoC, amma DI deyil
        UserRepository repo = ServiceLocator.getBean(UserRepository.class);
        return repo.findById(id).orElseThrow();
    }
}
```

---

## BeanFactory vs ApplicationContext

### BeanFactory

`BeanFactory` — Spring container-in ən əsas interfeysidir. Lazy initialization istifadə edir.

```java
// BeanFactory — aşağı səviyyəli container
// Yalnız çox məhdud resurs mühitlərində istifadə edilir (məs: embedded devices)
BeanFactory factory = new XmlBeanFactory(new ClassPathResource("beans.xml"));

// Bean lazy şəkildə yüklənir — yalnız getBean() çağırılanda
MyService service = factory.getBean(MyService.class);
```

**BeanFactory imkanları:**
- Bean yaratmaq və idarə etmək
- Dependency injection
- Bean lifecycle idarəsi

### ApplicationContext

`ApplicationContext` — `BeanFactory`-ni genişləndirir, daha zəngin funksionallıq təqdim edir.

```java
// ApplicationContext — production üçün tövsiyə olunan container
ApplicationContext context = new AnnotationConfigApplicationContext(AppConfig.class);

// Bean eager şəkildə yüklənir — startup zamanı
MyService service = context.getBean(MyService.class);
```

**ApplicationContext əlavə imkanları:**

| Xüsusiyyət | BeanFactory | ApplicationContext |
|-----------|------------|-------------------|
| Bean lifecycle | ✓ | ✓ |
| Dependency Injection | ✓ | ✓ |
| Eager initialization | ✗ | ✓ |
| MessageSource (i18n) | ✗ | ✓ |
| ApplicationEvent | ✗ | ✓ |
| AOP integration | ✗ | ✓ |
| @Transactional | ✗ | ✓ |
| Environment/Profiles | ✗ | ✓ |

---

## ApplicationContext növləri

### 1. AnnotationConfigApplicationContext

Java-based konfiqurasiya üçün:

```java
import org.springframework.context.annotation.AnnotationConfigApplicationContext;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.context.annotation.ComponentScan;

@Configuration
@ComponentScan(basePackages = "com.example")
public class AppConfig {

    @Bean
    public DataSource dataSource() {
        // DataSource bean-i yaradırıq
        HikariDataSource ds = new HikariDataSource();
        ds.setJdbcUrl("jdbc:postgresql://localhost:5432/mydb");
        ds.setUsername("user");
        ds.setPassword("password");
        return ds;
    }
}

// Container-i başlatmaq
public class Main {
    public static void main(String[] args) {
        // Annotation-based konfiqurasiya ilə container yaradılır
        AnnotationConfigApplicationContext context =
            new AnnotationConfigApplicationContext(AppConfig.class);

        // Bean-i əldə etmək
        UserService userService = context.getBean(UserService.class);
        userService.doSomething();

        // Container-i bağlamaq — @PreDestroy metodları çağırılır
        context.close();
    }
}
```

### 2. ClassPathXmlApplicationContext

XML-based konfiqurasiya üçün (köhnə üsul):

```xml
<!-- src/main/resources/applicationContext.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<beans xmlns="http://www.springframework.org/schema/beans"
       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:schemaLocation="http://www.springframework.org/schema/beans
           http://www.springframework.org/schema/beans/spring-beans.xsd">

    <!-- Bean definisiyası -->
    <bean id="userRepository" class="com.example.UserRepositoryImpl"/>

    <bean id="userService" class="com.example.UserService">
        <constructor-arg ref="userRepository"/>
    </bean>
</beans>
```

```java
// XML konfiqurasiya ilə container
ApplicationContext context =
    new ClassPathXmlApplicationContext("applicationContext.xml");

UserService userService = context.getBean("userService", UserService.class);
```

### 3. GenericWebApplicationContext

Web mühiti üçün:

```java
// Spring Boot avtomatik yaradır, manual olaraq nadirdir
// Servlet container ilə inteqrasiya təmin edir
@SpringBootApplication
public class MyApplication {
    public static void main(String[] args) {
        // Spring Boot daxilən AnnotationConfigServletWebServerApplicationContext istifadə edir
        SpringApplication.run(MyApplication.class, args);
    }
}
```

### 4. Hierarchy (Parent-Child Context)

```java
// Parent context — ümumi bean-lər
AnnotationConfigApplicationContext parentContext =
    new AnnotationConfigApplicationContext(InfrastructureConfig.class);

// Child context — spesifik bean-lər
AnnotationConfigApplicationContext childContext =
    new AnnotationConfigApplicationContext();
childContext.setParent(parentContext); // Parent təyin edilir
childContext.register(WebConfig.class);
childContext.refresh();

// Child context parent-in bean-lərini görə bilər
// Parent isə child-in bean-lərini görmür
```

---

## Spring Container başlatma

### Container başlatma mərhələləri

```java
@Configuration
public class AppConfig {

    // 1-ci mərhələ: BeanDefinition oxunması
    // @Configuration, @Component, @Bean annotasiyaları skan edilir

    @Bean
    public ServiceA serviceA() {
        // 2-ci mərhələ: Bean-lər yaradılır (instantiation)
        System.out.println("ServiceA yaradılır...");
        return new ServiceA();
    }

    @Bean
    public ServiceB serviceB(ServiceA serviceA) {
        // 3-cü mərhələ: Dependency-lər inject edilir
        System.out.println("ServiceB yaradılır, ServiceA inject edilir...");
        return new ServiceB(serviceA);
    }
}
```

### Container refresh prosesi

```java
// AbstractApplicationContext.refresh() əsas addımlar:
// 1. prepareRefresh() — başlatma hazırlığı
// 2. obtainFreshBeanFactory() — BeanFactory yaradılır
// 3. prepareBeanFactory() — standard post-processor-lar qeydiyyatdan keçir
// 4. postProcessBeanFactory() — alt siniflər üçün hook
// 5. invokeBeanFactoryPostProcessors() — BeanFactoryPostProcessor-lar işləyir
// 6. registerBeanPostProcessors() — BeanPostProcessor-lar qeydiyyatdan keçir
// 7. initMessageSource() — i18n support
// 8. initApplicationEventMulticaster() — event system
// 9. onRefresh() — xüsusi inisializasiya (məs: web server başlatmaq)
// 10. registerListeners() — event listener-lər
// 11. finishBeanFactoryInitialization() — singleton bean-lər yaradılır
// 12. finishRefresh() — lifecycle processor-lar, event-lər

@SpringBootApplication
public class DemoApplication {
    public static void main(String[] args) {
        // SpringApplication.run() daxilən refresh() çağırır
        ConfigurableApplicationContext ctx = SpringApplication.run(DemoApplication.class, args);

        // Container hazırdır
        System.out.println("Bean sayı: " + ctx.getBeanDefinitionCount());
    }
}
```

### Container-dən bean əldə etmək

```java
@Service
public class BeanAccessDemo {

    // ApplicationContext inject etmək
    private final ApplicationContext applicationContext;

    public BeanAccessDemo(ApplicationContext applicationContext) {
        this.applicationContext = applicationContext;
    }

    public void demonstrateBeanAccess() {
        // Tip ilə əldə etmək
        UserService userService = applicationContext.getBean(UserService.class);

        // Ad ilə əldə etmək
        UserService byName = (UserService) applicationContext.getBean("userService");

        // Ad və tip ilə əldə etmək (type-safe)
        UserService byNameAndType = applicationContext.getBean("userService", UserService.class);

        // Bean mövcudluğunu yoxlamaq
        boolean exists = applicationContext.containsBean("userService");

        // Bütün bean adlarını almaq
        String[] beanNames = applicationContext.getBeanDefinitionNames();

        // Müəyyən tipin bütün bean-lərini almaq
        Map<String, UserRepository> repositories =
            applicationContext.getBeansOfType(UserRepository.class);
    }
}
```

---

## Container-in bean idarəetməsi

### BeanDefinition — bean metadata

```java
import org.springframework.beans.factory.config.BeanDefinition;
import org.springframework.beans.factory.support.DefaultListableBeanFactory;
import org.springframework.beans.factory.support.RootBeanDefinition;

public class BeanDefinitionDemo {
    public static void main(String[] args) {
        DefaultListableBeanFactory factory = new DefaultListableBeanFactory();

        // Proqramatik bean qeydiyyatı
        RootBeanDefinition beanDef = new RootBeanDefinition(UserService.class);
        beanDef.setScope(BeanDefinition.SCOPE_SINGLETON); // singleton scope
        beanDef.setLazyInit(true); // lazy initialization
        factory.registerBeanDefinition("userService", beanDef);

        // Bean-i əldə etmək
        UserService service = factory.getBean(UserService.class);
    }
}
```

### Proqramatik bean qeydiyyatı

```java
@Configuration
public class DynamicBeanConfig implements BeanDefinitionRegistryPostProcessor {

    @Override
    public void postProcessBeanDefinitionRegistry(BeanDefinitionRegistry registry) {
        // Runtime-da bean qeydiyyatı
        for (String serviceName : getServiceNames()) {
            BeanDefinitionBuilder builder =
                BeanDefinitionBuilder.genericBeanDefinition(DynamicService.class)
                    .addConstructorArgValue(serviceName)
                    .setScope(BeanDefinition.SCOPE_SINGLETON);

            registry.registerBeanDefinition(serviceName + "Service",
                builder.getBeanDefinition());
        }
    }

    @Override
    public void postProcessBeanFactory(ConfigurableListableBeanFactory beanFactory) {
        // BeanFactory-yə müdaxilə
    }

    private List<String> getServiceNames() {
        return List.of("payment", "notification", "audit");
    }
}
```

### ApplicationContextAware

```java
// Bean ApplicationContext-ə birbaşa giriş əldə edə bilər
@Component
public class SpringContextHolder implements ApplicationContextAware {

    private static ApplicationContext applicationContext;

    @Override
    public void setApplicationContext(ApplicationContext context) {
        // Container bean-i yaratdıqdan sonra bu metodu çağırır
        SpringContextHolder.applicationContext = context;
    }

    // Static metod vasitəsilə context-ə giriş
    public static <T> T getBean(Class<T> beanClass) {
        return applicationContext.getBean(beanClass);
    }

    public static <T> T getBean(String beanName, Class<T> beanClass) {
        return applicationContext.getBean(beanName, beanClass);
    }
}

// İstifadə nümunəsi
public class LegacyCode {
    public void doSomething() {
        // Spring context-inə giriş olmayan yerdən bean əldə etmək
        UserService userService = SpringContextHolder.getBean(UserService.class);
    }
}
```

### Environment və Properties

```java
@Component
public class EnvironmentDemo {

    private final Environment environment;

    public EnvironmentDemo(Environment environment) {
        this.environment = environment;
    }

    public void showEnvironmentInfo() {
        // Aktiv profillər
        String[] activeProfiles = environment.getActiveProfiles();
        System.out.println("Aktiv profillər: " + Arrays.toString(activeProfiles));

        // Property dəyərini almaq
        String dbUrl = environment.getProperty("spring.datasource.url");
        int port = environment.getProperty("server.port", Integer.class, 8080);

        // Property mövcudluğunu yoxlamaq
        boolean hasProperty = environment.containsProperty("my.custom.property");
    }
}
```

### Container Events

```java
@Component
public class ContainerEventListener {

    // Container hazır olduqda
    @EventListener(ContextRefreshedEvent.class)
    public void onContextRefreshed(ContextRefreshedEvent event) {
        System.out.println("Spring Container başladı!");
    }

    // Container bağlandıqda
    @EventListener(ContextClosedEvent.class)
    public void onContextClosed(ContextClosedEvent event) {
        System.out.println("Spring Container bağlandı!");
    }

    // Application tam başladıqda (Spring Boot)
    @EventListener(ApplicationReadyEvent.class)
    public void onApplicationReady(ApplicationReadyEvent event) {
        System.out.println("Application tam hazırdır!");
    }
}
```

---

## İntervyu Sualları

**S: IoC ilə DI arasındakı fərq nədir?**
C: IoC — proqramın idarəsinin framework-ə verilməsi prinsipidir. DI — bu prinsipın bir tətbiqidir; dependency-lər xaricdən inject edilir. Hər DI, IoC-dur; amma hər IoC, DI deyil (Service Locator da IoC-dur).

**S: BeanFactory ilə ApplicationContext arasındakı fərq nədir?**
C: BeanFactory əsas container-dir, lazy initialization edir. ApplicationContext BeanFactory-ni genişləndirir və əlavə olaraq: eager initialization, i18n (MessageSource), event publishing, AOP integration, profile support kimi imkanlar təqdim edir. Production-da həmişə ApplicationContext istifadə edilir.

**S: Spring Bean nədir?**
C: Spring IoC container tərəfindən idarə olunan Java obyektidir. Container bean-i yaradır, konfiqurasiya edir, lifecycle-ını idarə edir və dependency-lərini inject edir.

**S: AnnotationConfigApplicationContext nə vaxt istifadə edilir?**
C: Java-based konfiqurasiya (@Configuration sinfləri) istifadə edildikdə. Spring Boot bu context-in genişləndirilmiş versiyasını (AnnotationConfigServletWebServerApplicationContext) avtomatik yaradır.

**S: Container-i necə proqramatik olaraq bağlamaq olar?**
C: `context.close()` metodu çağırılır. Bu `@PreDestroy` metodlarını və `DisposableBean.destroy()` metodlarını trigger edir. Spring Boot-da JVM shutdown hook avtomatik əlavə edilir.

**S: ApplicationContextAware nə üçün istifadə edilir?**
C: Bean-in ApplicationContext-ə birbaşa giriş əldə etməsi üçün. Amma bu anti-pattern hesab olunur — mümkün olduqda dependency injection istifadə edilməlidir. Legacy kod ilə inteqrasiyada istifadəli ola bilər.
