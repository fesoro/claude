# 64 — Spring Cache

> **Seviyye:** Middle ⭐⭐


## Mündəricat
1. [Spring Cache nədir?](#nedir)
2. [@EnableCaching](#enable)
3. [@Cacheable — cache oxuma](#cacheable)
4. [@CacheEvict — cache silmə](#cacheevict)
5. [@CachePut — cache yeniləmə](#cacheput)
6. [@Caching — birdən çox annotasiya](#caching)
7. [CacheManager növləri](#cachemanager)
8. [Cache key generasiyası](#key)
9. [Caffeine Cache konfiqurasiyası](#caffeine)
10. [Redis Cache konfiqurasiyası](#redis)
11. [İntervyu Sualları](#intervyu)

---

## 1. Spring Cache nədir? {#nedir}

**Spring Cache** — tez-tez çağırılan və baha başa gələn metodların nəticələrini
yadda saxlayan (cache edən) abstraksiya layidir. Verilənlər bazası sorğuları,
xarici API çağırışları üçün ideal.

```
Birinci çağırış:   findUser(1) → DB sorğusu → nəticəni cache-ə yaz → qaytar
İkinci çağırış:    findUser(1) → cache-dən oxu → ANINDA qaytar (DB yox!)
```

---

## 2. @EnableCaching {#enable}

```java
// @SpringBootApplication sinfinə və ya @Configuration sinfinə əlavə et
@SpringBootApplication
@EnableCaching   // cache mexanizmini aktivləşdir
public class MyApplication {
    public static void main(String[] args) {
        SpringApplication.run(MyApplication.class, args);
    }
}
```

```xml
<!-- pom.xml — baza cache dəstəyi spring-boot-starter ilə gəlir -->
<!-- Redis cache üçün: -->
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-data-redis</artifactId>
</dependency>

<!-- Caffeine cache üçün: -->
<dependency>
    <groupId>com.github.ben-manes.caffeine</groupId>
    <artifactId>caffeine</artifactId>
</dependency>
```

---

## 3. @Cacheable — cache oxuma {#cacheable}

```java
@Service
public class UserService {

    private final UserRepository userRepository;

    public UserService(UserRepository userRepository) {
        this.userRepository = userRepository;
    }

    // YANLIŞ — cache yoxdur, hər çağırışda DB sorğusu:
    public User findById(Long id) {
        return userRepository.findById(id)  // HƏR DƏFƏ DB sorğusu!
            .orElseThrow(() -> new UserNotFoundException(id));
    }

    // DOĞRU — nəticə cache-ə yazılır:
    @Cacheable(value = "users", key = "#id")
    public User findById(Long id) {
        // Bu metod yalnız birinci çağırışda icra olunur
        // Sonrakı çağırışlarda cache-dən qaytar
        System.out.println("DB sorğusu: " + id);  // Yalnız bir dəfə çap olunur
        return userRepository.findById(id)
            .orElseThrow(() -> new UserNotFoundException(id));
    }
}
```

### @Cacheable parametrləri:

```java
@Service
public class ProductService {

    // Sadə cache
    @Cacheable("products")
    public List<Product> findAll() {
        return productRepository.findAll();
    }

    // Key SpEL ifadəsi
    @Cacheable(value = "products", key = "#id")
    public Product findById(Long id) {
        return productRepository.findById(id).orElseThrow();
    }

    // Mürəkkəb key — metodun bir neçə parametrindən
    @Cacheable(value = "products", key = "#category + '-' + #page")
    public Page<Product> findByCategory(String category, int page) {
        return productRepository.findByCategory(category, PageRequest.of(page, 20));
    }

    // Şərtli cache — yalnız şərt ödənildikdə cache et
    @Cacheable(value = "products", key = "#id",
               condition = "#id > 0")    // id müsbətdirsə cache et
    public Product findById(Long id) {
        return productRepository.findById(id).orElseThrow();
    }

    // Unless — nəticə boşdursa cache etmə
    @Cacheable(value = "products", key = "#id",
               unless = "#result == null")  // null nəticəni cache etmə
    public Product findById(Long id) {
        return productRepository.findById(id).orElse(null);
    }

    // Unless + nəticəyə görə şərt
    @Cacheable(value = "products", key = "#id",
               unless = "#result.price < 0")   // mənfi qiymətli məhsul cache edilmir
    public Product findById(Long id) {
        return productRepository.findById(id).orElseThrow();
    }

    // Birdən çox cache adı
    @Cacheable({"products", "all-products"})
    public List<Product> findAllActive() {
        return productRepository.findByActiveTrue();
    }
}
```

### Cache miss / hit anlayışı:

```
Cache MISS: key tapılmadı → metod icra olunur → nəticə cache-ə yazılır
Cache HIT:  key tapıldı  → metod icra OLUNMUR → cache-dən qaytarılır
```

---

## 4. @CacheEvict — cache silmə {#cacheevict}

```java
@Service
public class UserService {

    // Bir istifadəçini cache-dən sil
    @CacheEvict(value = "users", key = "#id")
    public void deleteUser(Long id) {
        userRepository.deleteById(id);
        // Metod icra olunduqdan SONRA cache silinir
    }

    // Bütün "users" cache-ini təmizlə
    @CacheEvict(value = "users", allEntries = true)
    public void deleteAllUsers() {
        userRepository.deleteAll();
    }

    // Metoddan ƏVVƏL cache sil (beforeInvocation = true)
    @CacheEvict(value = "users", key = "#id", beforeInvocation = true)
    public void deleteUserSafe(Long id) {
        // Əvvəlcə cache silinir
        // Sonra metod icra olunur
        // Metod xəta versə belə cache silinmiş olur!
        userRepository.deleteById(id);
    }

    // YANLIŞ — yeniləmə zamanı cache-i silməmək:
    public User updateUser(Long id, UserDto dto) {
        // Cache-i silmədik — köhnə məlumat qalır!
        return userRepository.save(mapToUser(id, dto));
    }

    // DOĞRU — yeniləmə zamanı cache-i sil:
    @CacheEvict(value = "users", key = "#id")
    public User updateUser(Long id, UserDto dto) {
        // Cache silindi — növbəti oxumada yeni məlumat gəlir
        return userRepository.save(mapToUser(id, dto));
    }

    private User mapToUser(Long id, UserDto dto) {
        // DTO-dan entity yaradır
        return new User(id, dto.getName(), dto.getEmail());
    }
}
```

---

## 5. @CachePut — cache yeniləmə {#cacheput}

```java
@Service
public class UserService {

    // @Cacheable ilə fərq:
    // @Cacheable → cache varsa metodu icra ETMƏ
    // @CachePut  → HƏR ZAMAN metodu icra et, nəticəni cache-ə YAZ

    // YANLIŞ — @Cacheable yeniləmə üçün işlətmə:
    @Cacheable(value = "users", key = "#user.id")  // cache varsa DB-yə getmir!
    public User updateUser(User user) {
        return userRepository.save(user);  // bu heç vaxt çağırılmaya bilər!
    }

    // DOĞRU — @CachePut hər zaman icra olur və cache-i yeniləyir:
    @CachePut(value = "users", key = "#user.id")
    public User updateUser(User user) {
        User saved = userRepository.save(user);  // HƏR ZAMAN icra olunur
        return saved;  // bu qayıtma dəyəri cache-ə yazılır
    }

    // @CachePut + @CacheEvict birlikdə (birdən çox cache):
    @Caching(
        put = @CachePut(value = "users", key = "#user.id"),
        evict = @CacheEvict(value = "user-list", allEntries = true)
    )
    public User updateUser(User user) {
        return userRepository.save(user);
    }
}
```

---

## 6. @Caching — birdən çox annotasiya {#caching}

```java
@Service
public class ProductService {

    // Eyni metodda birdən çox cache annotasiyası istifadə et:
    @Caching(
        cacheable = {
            @Cacheable(value = "products", key = "#id"),
            @Cacheable(value = "product-details", key = "'detail-' + #id")
        },
        evict = {
            @CacheEvict(value = "product-list", allEntries = true)
        }
    )
    public Product getProductWithRefresh(Long id) {
        return productRepository.findById(id).orElseThrow();
    }

    // Yeniləmə zamanı birdən çox cache üzərində əməliyyat:
    @Caching(
        put = @CachePut(value = "products", key = "#product.id"),
        evict = {
            @CacheEvict(value = "product-list", allEntries = true),
            @CacheEvict(value = "featured-products", allEntries = true)
        }
    )
    public Product updateProduct(Product product) {
        return productRepository.save(product);
    }
}
```

---

## 7. CacheManager növləri {#cachemanager}

### ConcurrentMapCacheManager (default — production üçün tövsiyə edilmir):

```java
// application.properties boş olduqda bu istifadə olunur
// JVM yaddaşında saxlanır — server yenidən başladıqda silinir!
// Distributed mühitdə hər server öz cache-ini saxlayır → qeyri-ardıcıllıq

@Bean
public CacheManager cacheManager() {
    return new ConcurrentMapCacheManager("users", "products");
    // Yalnız inkişaf/test mühiti üçün uyğundur
}
```

### EhCacheManager:

```java
@Bean
public CacheManager ehCacheManager() {
    EhCacheCacheManager cacheManager = new EhCacheCacheManager();
    cacheManager.setCacheManager(ehCacheManagerFactoryBean().getObject());
    return cacheManager;
}

@Bean
public EhCacheManagerFactoryBean ehCacheManagerFactoryBean() {
    EhCacheManagerFactoryBean factory = new EhCacheManagerFactoryBean();
    factory.setConfigLocation(new ClassPathResource("ehcache.xml"));
    factory.setShared(true);
    return factory;
}
```

---

## 8. Cache key generasiyası {#key}

### Default key generasiyası:

```java
// Parametr yoxdur → key = SimpleKey.EMPTY
@Cacheable("products")
public List<Product> findAll() { ... }

// Bir parametr → parametrin özü key olur
@Cacheable("products")
public Product findById(Long id) { ... }   // key = id dəyəri

// Birdən çox parametr → SimpleKey(param1, param2, ...)
@Cacheable("products")
public List<Product> findByCategory(String cat, int page) { ... }
// key = SimpleKey("electronics", 0)
```

### SpEL ilə custom key:

```java
@Service
public class CacheKeyExamples {

    // Parametr adı ilə
    @Cacheable(value = "users", key = "#userId")
    public User findUser(Long userId) { ... }

    // Metodun adı + parametr
    @Cacheable(value = "data", key = "#root.methodName + '-' + #id")
    public Data findData(Long id) { ... }

    // Object-in xüsusiyyəti
    @Cacheable(value = "users", key = "#request.userId")
    public User findUser(UserRequest request) { ... }

    // Birləşdirilmiş key
    @Cacheable(value = "products", key = "T(java.util.Objects).hash(#cat, #page)")
    public Page<Product> findByCategory(String cat, int page) { ... }
}
```

### Custom KeyGenerator:

```java
@Component("customKeyGenerator")
public class CustomKeyGenerator implements KeyGenerator {

    @Override
    public Object generate(Object target, Method method, Object... params) {
        // Sinif adı + metod adı + parametrlər
        return target.getClass().getSimpleName() + ":"
            + method.getName() + ":"
            + Arrays.toString(params);
        // Nəticə: "UserService:findById:[1]"
    }
}

// İstifadə:
@Cacheable(value = "users", keyGenerator = "customKeyGenerator")
public User findById(Long id) { ... }
```

---

## 9. Caffeine Cache konfiqurasiyası {#caffeine}

```xml
<dependency>
    <groupId>com.github.ben-manes.caffeine</groupId>
    <artifactId>caffeine</artifactId>
</dependency>
```

```properties
# application.properties — Caffeine konfiqurasiyası
spring.cache.type=caffeine

# Global Caffeine spec
spring.cache.caffeine.spec=maximumSize=1000,expireAfterWrite=10m

# Cache adları
spring.cache.cache-names=users,products,orders
```

### Java konfiqurasiyası ilə (daha çevik):

```java
@Configuration
@EnableCaching
public class CaffeineConfig {

    @Bean
    public CacheManager cacheManager() {
        CaffeineCacheManager manager = new CaffeineCacheManager();

        // Bütün cache-lər üçün default konfiqurasiya
        manager.setCaffeine(Caffeine.newBuilder()
            .maximumSize(1000)              // maksimum 1000 element
            .expireAfterWrite(10, TimeUnit.MINUTES)   // yazıldıqdan 10 dəq sonra silinir
            .expireAfterAccess(5, TimeUnit.MINUTES)   // son oxumadan 5 dəq sonra silinir
            .recordStats()                 // statistika topla
        );

        return manager;
    }

    // Hər cache üçün fərdi konfiqurasiya:
    @Bean
    public CacheManager customCacheManager() {
        SimpleCacheManager manager = new SimpleCacheManager();

        List<CaffeineCache> caches = List.of(
            buildCache("users", 500, 30),        // 500 element, 30 dəq
            buildCache("products", 2000, 60),    // 2000 element, 60 dəq
            buildCache("orders", 100, 5)         // 100 element, 5 dəq
        );

        manager.setCaches(caches);
        return manager;
    }

    private CaffeineCache buildCache(String name, int size, int minutes) {
        return new CaffeineCache(name,
            Caffeine.newBuilder()
                .maximumSize(size)
                .expireAfterWrite(minutes, TimeUnit.MINUTES)
                .recordStats()
                .build()
        );
    }
}
```

---

## 10. Redis Cache konfiqurasiyası {#redis}

```properties
# application.properties
spring.cache.type=redis
spring.data.redis.host=localhost
spring.data.redis.port=6379
spring.data.redis.password=secret

# Default TTL (time-to-live)
spring.cache.redis.time-to-live=600000   # 10 dəqiqə (millisaniyə)

# Key prefix
spring.cache.redis.key-prefix=myapp:

# Null dəyərləri cache et?
spring.cache.redis.cache-null-values=false
```

### Java konfiqurasiyası ilə Redis cache:

```java
@Configuration
@EnableCaching
public class RedisCacheConfig {

    @Bean
    public RedisCacheManager redisCacheManager(RedisConnectionFactory factory) {
        // Default konfiqurasiya
        RedisCacheConfiguration defaultConfig = RedisCacheConfiguration.defaultCacheConfig()
            .entryTtl(Duration.ofMinutes(10))           // 10 dəqiqə TTL
            .keySerializeWith(RedisSerializationContext.SerializationPair
                .fromSerializer(new StringRedisSerializer()))
            .valueSerializeWith(RedisSerializationContext.SerializationPair
                .fromSerializer(new GenericJackson2JsonRedisSerializer()))
            .disableCachingNullValues();                 // null saxlama

        // Hər cache üçün fərdi konfiqurasiya:
        Map<String, RedisCacheConfiguration> configs = new HashMap<>();

        configs.put("users",
            defaultConfig.entryTtl(Duration.ofMinutes(30)));    // 30 dəq TTL

        configs.put("products",
            defaultConfig.entryTtl(Duration.ofHours(1)));       // 1 saat TTL

        configs.put("sessions",
            defaultConfig.entryTtl(Duration.ofMinutes(5)));     // 5 dəq TTL

        return RedisCacheManager.builder(factory)
            .cacheDefaults(defaultConfig)
            .withInitialCacheConfigurations(configs)
            .build();
    }
}
```

---

## 11. Self-invocation problemi

```java
// YANLIŞ — özünü çağırmaq cache işləmir!
@Service
public class UserService {

    @Cacheable("users")
    public User findById(Long id) {
        return userRepository.findById(id).orElseThrow();
    }

    public void processUser(Long id) {
        User user = findById(id);  // Bu çağırış PROXY-dən keçmir!
        // Cache işləmir — hər dəfə DB sorğusu gedir
    }
}

// DOĞRU — proxy-dən keçmək üçün self-injection istifadə et:
@Service
public class UserService {

    @Autowired
    private UserService self;  // öz proxy-ni inject et

    @Cacheable("users")
    public User findById(Long id) {
        return userRepository.findById(id).orElseThrow();
    }

    public void processUser(Long id) {
        User user = self.findById(id);  // Proxy-dən keçir → cache işləyir!
    }
}
```

---

## İntervyu Sualları {#intervyu}

**S: @Cacheable ilə @CachePut arasındakı fərq nədir?**
C: `@Cacheable` — cache varsa metodu icra etmir, cache-dən qaytarır. `@CachePut` — hər zaman metodu icra edir və nəticəni cache-ə yazır. `@CachePut` cache-i yeniləmək üçün, `@Cacheable` cache-dən oxumaq üçün istifadə olunur.

**S: condition və unless parametrlərinin fərqi nədir?**
C: `condition` — metod çağırılmadan əvvəl yoxlanılır, parametrlərə baxır. `unless` — metod icra olunduqdan sonra yoxlanılır, `#result` ilə nəticəyə baxa bilir.

**S: Cache self-invocation problemi nədir?**
C: Spring cache AOP proxy ilə işləyir. Bir metodun özü həmin sinifin başqa metodunu çağırdıqda proxy-dən keçmir, birbaşa metodun özünə gedir. Buna görə cache işləmir. Həll üçün self-injection (özünü inject etmək) istifadə edilir.

**S: ConcurrentMapCacheManager niyə production-da tövsiyə edilmir?**
C: JVM yaddaşında saxlanır — server restart olduqda silinir, TTL dəstəkləmir, distributed mühitdə hər server öz cache-ini saxlayır (qeyri-ardıcıllıq). Production üçün Redis (distributed) və ya Caffeine (lokal, TTL dəstəkli) tövsiyə edilir.

**S: @CacheEvict-in beforeInvocation parametresi nə edir?**
C: `false` (default) — metod uğurla tamamlandıqdan sonra cache silinir. `true` — metod icra olunmadan əvvəl cache silinir. Metod xəta versə belə cache silinmiş olur.
