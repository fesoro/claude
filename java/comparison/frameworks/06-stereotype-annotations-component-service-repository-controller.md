# Stereotype Annotasiyalar: @Component, @Service, @Repository, @Controller

> **Seviyye:** Beginner ⭐

## Giriş

Spring Boot-da ilk öyrəndiyin annotasiyalar `@Component`, `@Service`, `@Repository`, `@Controller` olacaq. Bunlara **stereotype annotations** deyilir — çünki onlar sinfin "rolunu" (stereotype-ini) ifadə edirlər: "bu biznes logikadır", "bu data access-dir", "bu web controller-dir".

Laravel-də bu cür bölünmə yoxdur — sadəcə `app/Services/`, `app/Repositories/`, `app/Http/Controllers/` qovluqları yaradırsan və içinə adi PHP sinifləri qoyursan. Framework onların rolunu bilmir, sadəcə sən qovluq adı ilə bildirirsən.

Spring-də isə annotasiya **mecburi** rol oynayir: o olmadan sinif bean kimi qeydiyyatdan keçmir, dependency injection işləmir, ve Spring onu tapa bilmir. Bu fayl ilk baxışda eyni görünən bu 4 annotasiyanın fərqlərini, nə vaxt hansını istifadə etməyi açıqlayır.

## Spring/Java-də istifadəsi

### 1. @Component — ümumi bean

`@Component` ən ümumi stereotype-dir. "Bu sinfi Spring container-inə bean kimi əlavə et" deməkdir.

```java
package com.example.demo.util;

import org.springframework.stereotype.Component;

@Component
public class StringFormatter {

    public String capitalize(String input) {
        if (input == null || input.isEmpty()) {
            return input;
        }
        return input.substring(0, 1).toUpperCase() + input.substring(1);
    }

    public String toSnakeCase(String input) {
        return input.replaceAll("([a-z])([A-Z])", "$1_$2").toLowerCase();
    }
}
```

İstifadə:

```java
@Service
public class UserService {
    private final StringFormatter formatter;

    public UserService(StringFormatter formatter) {
        this.formatter = formatter;
    }

    public String formatName(String rawName) {
        return formatter.capitalize(rawName);
    }
}
```

Nə vaxt `@Component` istifadə etməli? Sinif konkret bir layer-ə aid deyilsə — məs. utility, helper, formatter, validator, mapper sinifləri.

### 2. @Service — biznes logikası

`@Service` biznes logikası saxlayan sinifləri markalayır. Texniki olaraq `@Component`-lə eyni işi görür, amma **semantik** (məna) baxımından fərqlənir.

```java
package com.example.demo.service;

import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;

@Service
public class OrderService {

    private final OrderRepository orderRepository;
    private final PaymentService paymentService;
    private final NotificationService notificationService;

    public OrderService(OrderRepository orderRepository,
                        PaymentService paymentService,
                        NotificationService notificationService) {
        this.orderRepository = orderRepository;
        this.paymentService = paymentService;
        this.notificationService = notificationService;
    }

    @Transactional
    public Order placeOrder(OrderRequest request) {
        Order order = new Order();
        order.setItems(request.getItems());
        order.setTotal(calculateTotal(request.getItems()));

        paymentService.charge(request.getPaymentMethod(), order.getTotal());
        Order saved = orderRepository.save(order);
        notificationService.sendConfirmation(request.getEmail(), saved);

        return saved;
    }

    private BigDecimal calculateTotal(List<OrderItem> items) {
        return items.stream()
            .map(item -> item.getPrice().multiply(BigDecimal.valueOf(item.getQuantity())))
            .reduce(BigDecimal.ZERO, BigDecimal::add);
    }
}
```

Nə vaxt `@Service` istifadə etməli? Tətbiqin use-case-lərini (sifaris vermek, hesab yaratmaq, hesabat cixarmaq) icra eden sinifler ucun.

### 3. @Repository — data access

`@Repository` verilenler bazasi ile isleyen sinifleri markalayir. `@Component`-den elave bir ozelliyi de var — `@Repository`-li siniflerde PersistenceException avtomatik olaraq Spring-in `DataAccessException`-ina cevrilir (exception translation).

```java
package com.example.demo.repository;

import org.springframework.data.jpa.repository.JpaRepository;
import org.springframework.data.jpa.repository.Query;
import org.springframework.stereotype.Repository;
import java.util.List;
import java.util.Optional;

@Repository
public interface UserRepository extends JpaRepository<User, Long> {

    Optional<User> findByEmail(String email);

    List<User> findByStatus(UserStatus status);

    @Query("SELECT u FROM User u WHERE u.createdAt > :date")
    List<User> findRecentUsers(@Param("date") LocalDateTime date);
}
```

Qeyd: `JpaRepository`-den inherit olan interface-lere `@Repository` yazmaq **mecburi deyil** - Spring Data JPA bunu avtomatik edir. Amma oz `@Repository` implementasiyani yazirsansa, annotasiya vacibdir:

```java
@Repository
public class UserCustomRepository {

    private final JdbcTemplate jdbcTemplate;

    public UserCustomRepository(JdbcTemplate jdbcTemplate) {
        this.jdbcTemplate = jdbcTemplate;
    }

    public int countActiveUsers() {
        return jdbcTemplate.queryForObject(
            "SELECT COUNT(*) FROM users WHERE status = 'ACTIVE'",
            Integer.class
        );
    }
}
```

### 4. @Controller və @RestController — web layer

`@Controller` HTTP sorgularini qebul eden sinifleri markalayir. O, klassik MVC pattern ucundur — yani metod View adi qaytarir (HTML template):

```java
package com.example.demo.web;

import org.springframework.stereotype.Controller;
import org.springframework.ui.Model;
import org.springframework.web.bind.annotation.GetMapping;

@Controller
public class HomeController {

    @GetMapping("/")
    public String home(Model model) {
        model.addAttribute("message", "Salam, dunya!");
        return "home";  // templates/home.html-i axtarir (Thymeleaf)
    }
}
```

REST API ucun `@RestController` istifade olunur — bu, `@Controller + @ResponseBody`-nin qisa yoludur. Metodun qaytardigi obyekt avtomatik JSON-a cevrilir:

```java
package com.example.demo.web;

import org.springframework.web.bind.annotation.*;

@RestController
@RequestMapping("/api/users")
public class UserRestController {

    private final UserService userService;

    public UserRestController(UserService userService) {
        this.userService = userService;
    }

    @GetMapping
    public List<UserDTO> getAllUsers() {
        return userService.findAll();
    }

    @GetMapping("/{id}")
    public UserDTO getUser(@PathVariable Long id) {
        return userService.findById(id);
    }

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public UserDTO createUser(@RequestBody UserCreateDTO dto) {
        return userService.create(dto);
    }
}
```

### 5. @Configuration — bean factory

`@Configuration` texniki olaraq `@Component`-in ozel novudur. Icinde `@Bean` metodlari olan sinifleri markalayir:

```java
package com.example.demo.config;

import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

@Configuration
public class AppConfig {

    @Bean
    public RestTemplate restTemplate() {
        return new RestTemplate();
    }

    @Bean
    public ObjectMapper objectMapper() {
        ObjectMapper mapper = new ObjectMapper();
        mapper.registerModule(new JavaTimeModule());
        return mapper;
    }
}
```

`@Configuration` ucuncu-teref kitabxanalarin siniflerini bean etmek ucun istifade olunur — cunki onlara `@Service` annotasiyasi elave ede bilmirik (kodlarini deyisdirmeye ixtiyarimiz yoxdur).

### 6. Component Scan — Spring necə tapır

`@SpringBootApplication` altinda `@ComponentScan` default olaraq main class-in oldugu paketden baslayaraq butun alt paketleri axtarir. Tapilan her `@Component`, `@Service`, `@Repository`, `@Controller`, `@RestController`, `@Configuration` bean kimi qeydiyyatdan kecirilir.

```
com.example.demo/
├── DemoApplication.java              # @SpringBootApplication burada
├── service/
│   └── UserService.java              # @Service - tapilir
├── repository/
│   └── UserRepository.java           # @Repository - tapilir
└── web/
    └── UserController.java           # @RestController - tapilir
```

### 7. Bean adlari

Spring bean-lere avtomatik ad verir: sinif adini kicik herfle baslatmaqla. `UserService` → `userService`, `OrderRepository` → `orderRepository`.

Ozel ad vermek ucun annotasiyaya parametr ver:

```java
@Service("customUserService")
public class UserService {
    // ...
}

// Istifadesi
@Service
public class ReportService {
    public ReportService(@Qualifier("customUserService") UserService userService) {
        // ...
    }
}
```

### 8. Decision Table — hansi annotasiyani nə vaxt istifade etmeli?

| Sinfin rolu | Annotasiya | Numune |
|---|---|---|
| HTTP REST endpoint | `@RestController` | `UserController`, `OrderController` |
| HTML/Thymeleaf səhifə | `@Controller` | `HomeController`, `DashboardController` |
| Biznes logikasi | `@Service` | `OrderService`, `PaymentService` |
| DB erisimi (JPA, JDBC) | `@Repository` | `UserRepository`, `ProductRepository` |
| Utility / helper | `@Component` | `EmailValidator`, `PriceFormatter` |
| 3rd-party bean yaratmaq | `@Configuration` + `@Bean` | `RestTemplate`, `ObjectMapper` |
| Scheduled task, Event listener | `@Component` | `DailyReportScheduler` |
| Custom filter, interceptor | `@Component` | `RequestLoggingFilter` |

### 9. Tam numune — butun layerlerle

```java
// Entity
@Entity
@Table(name = "products")
public class Product {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;
    private String name;
    private BigDecimal price;
    // getter/setter
}

// Repository — DB access
@Repository
public interface ProductRepository extends JpaRepository<Product, Long> {
    List<Product> findByNameContainingIgnoreCase(String name);
}

// Utility — format kimi
@Component
public class PriceFormatter {
    public String format(BigDecimal price) {
        return String.format("%.2f AZN", price);
    }
}

// Service — biznes logika
@Service
public class ProductService {
    private final ProductRepository repository;
    private final PriceFormatter formatter;

    public ProductService(ProductRepository repository, PriceFormatter formatter) {
        this.repository = repository;
        this.formatter = formatter;
    }

    public List<ProductDTO> search(String query) {
        return repository.findByNameContainingIgnoreCase(query).stream()
            .map(p -> new ProductDTO(p.getId(), p.getName(), formatter.format(p.getPrice())))
            .toList();
    }
}

// Controller — HTTP layer
@RestController
@RequestMapping("/api/products")
public class ProductController {
    private final ProductService service;

    public ProductController(ProductService service) {
        this.service = service;
    }

    @GetMapping("/search")
    public List<ProductDTO> search(@RequestParam String q) {
        return service.search(q);
    }
}
```

## Laravel/PHP-de istifadesi

### 1. Laravel-de stereotype annotasiyalar yoxdur

Laravel-de `@Service`, `@Repository` kimi annotasiyalar **yoxdur**. Layer-leri sadece qovluq strukturu ile ayrilir:

```
app/
├── Http/Controllers/       # Controller layer
├── Models/                 # Eloquent modelleri (entity + repository qarisimi)
├── Services/               # Biznes logika (ozun yaradirsan)
└── Repositories/           # Repository pattern (ozun yaradirsan, mecburi deyil)
```

### 2. Controller numunesi

```php
<?php
// app/Http/Controllers/ProductController.php

namespace App\Http\Controllers;

use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ProductService $service
    ) {}

    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q');
        $results = $this->service->search($query);
        return response()->json($results);
    }
}
```

Qeyd: burada hec bir annotasiya yoxdur - Laravel sinfin HTTP controller oldugunu qovluq konvensiyasindan (və ya `Controller` sinfindən inherit olmaqdan) bilir.

### 3. Service numunesi

```php
<?php
// app/Services/ProductService.php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductService
{
    public function search(string $query): Collection
    {
        return Product::where('name', 'like', "%{$query}%")
            ->get()
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'price' => number_format($p->price, 2) . ' AZN',
            ]);
    }
}
```

### 4. Eloquent Model — Repository və Entity bir yerde

Laravel Eloquent sistemi fərqli felsefə ilə gelir - **Active Record** pattern. Model həm entity (məlumat saxlayir) həm də repository kimi isleyir (DB sorgulari üçün metodlari var):

```php
<?php
// app/Models/Product.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['name', 'price'];

    // Query scope - repository metodu kimi
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}

// Istifade
Product::create(['name' => 'Laptop', 'price' => 1500]);
Product::active()->where('price', '<', 2000)->get();
Product::find(1)->update(['price' => 1400]);
```

Spring-de Entity + Repository ayri-ayri siniflerdir, Laravel-de ise ikisi birdir (Active Record).

### 5. Repository Pattern — opsional

Eger Laravel-de Repository layer istəyirsen, özün yaradirsan:

```php
<?php
// app/Repositories/ProductRepository.php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Support\Collection;

class ProductRepository
{
    public function search(string $query): Collection
    {
        return Product::where('name', 'like', "%{$query}%")->get();
    }

    public function findById(int $id): ?Product
    {
        return Product::find($id);
    }
}

// Service Provider-de bind (opsional - Laravel avtomatik resolve edir)
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->singleton(ProductRepository::class);
}
```

### 6. Auto-binding — annotasiya əvezinə

Laravel-in Service Container sinifleri constructor-a baxaraq avtomatik yaradir. Annotasiya lazim deyil - sadece tip yazmaq kifayetdir:

```php
class ProductController extends Controller
{
    // Laravel avtomatik ProductService yaradir ve verir
    public function __construct(
        private readonly ProductService $service
    ) {}
}
```

## Esas Ferqler

| Xususiyyet | Spring Boot | Laravel |
|---|---|---|
| **Stereotype annotasiyalar** | `@Component`, `@Service`, `@Repository`, `@Controller` | Yoxdur |
| **Sinfin rolu necə bildirilir** | Annotasiya ilə | Qovluq adi ile (konvensiya) |
| **Controller markalama** | `@Controller` / `@RestController` mecburi | Base `Controller` sinifindən inherit |
| **Service layer** | `@Service` annotasiyasi | Sadecə qovluq `app/Services/` |
| **Repository layer** | `@Repository` + `JpaRepository` interface | Eloquent Model (Active Record) |
| **Bean adi** | Avtomatik (camelCase), özel ad `@Service("x")` | Sinif adi + namespace |
| **Component Scan** | Default: alt paketleri tarar | Namespace-based autoloading (PSR-4) |
| **Exception translation** | `@Repository` JPA exception-lari cevirir | Yoxdur (özün idarə edirsen) |
| **Config sinifleri** | `@Configuration` + `@Bean` | Service Provider `register()` |
| **Pattern** | Data Mapper (Entity + Repository ayri) | Active Record (Model her seyi edir) |

## Niye Bele Ferqler Var?

### Spring-in yanasmasi

1. **Annotasiya medeniyyəti**: Java annotasiyalari metadata kimi isləyir. Spring `@Service` ilə sinfin rolunu bildirir, bu AOP (Aspect-Oriented Programming) üçün də vacibdir. Meselen, butun `@Service`-lere avtomatik logging əlavə etmek olar.

2. **Explicit over implicit**: Java felsefesinde sinfin rolu aciq olmalidir. Annotasiya bir növ "sənədləşdirmə"-dir — kodu oxuyan dərhal bilir ki, bu sinif nə edir.

3. **`@Repository` xüsusi xidmət göstərir**: O, databases specific exception-lari (Hibernate JDBCException və s.) Spring-in ümumi `DataAccessException`-ina çevirir. Bu, data layer-i database texnologiyasindan ayirir.

4. **Interface-based Repository**: Spring Data JPA isteyir ki, Repository interface olsun — implementasiyani framework özü compile zamani yaradir (dynamic proxy). Bu, Java-nin statik tip sistemi ilə uyğundur.

### Laravel-in yanasmasi

1. **Convention over configuration**: Laravel qovluq adinin ve namespace-in kifayət etdiyini düşünür. `app/Services/UserService.php` - hamı bilir ki, bu service-dir. Annotasiyaya ehtiyac yoxdur.

2. **Active Record modeli**: Eloquent PHP ekosisteminin Rails-dən əxz etdiyi bir modeldir. Model həm data, həm DB methodlarını ehtiva edir. Bu sadə CRUD üçün çox rahatdir, amma böyük logikada test etmək çətinleşir.

3. **Type-hint ile auto-resolution**: PHP 7+ type-hint sistemi Laravel Service Container-ə konstruktordan asılılıqları tapmağa icazə verir — annotasiya lazım olmur.

4. **Dinamik dil**: PHP annotasiyaları yeni əlavədir (PHP 8+). Laravel 10 da bunlardan istifadə etmir — çünki tarixən PHP reflection + convention ilə işləyib.

## Ümumi Sehvler (Beginner Traps)

### 1. `@Service` unutmaq — bean tapilmir

```java
// SEHV - annotasiya yoxdur
public class UserService {
    // ...
}

// Tetbiq islemir:
// UnsatisfiedDependencyException: No qualifying bean of type 'UserService'
```

Hel yolu: `@Service` əlavə et.

### 2. Main sinfinden kenar paketde bean

```
com.example.demo/
├── DemoApplication.java       # @SpringBootApplication burada
└── ...

com.other/
└── UserService.java           # @Service var, amma TAPILMAZ
```

Default `@ComponentScan` yalniz `com.example.demo` və alt paketlərini tarayir. `com.other` gorulmur.

Hel yolu: ya sinfi düzgün paketə ver, ya `@SpringBootApplication(scanBasePackages = {"com.example.demo", "com.other"})` yaz.

### 3. `@Controller` yerinə `@RestController` istifade etməmek

```java
@Controller  // SEHV API ucun
@GetMapping("/api/users")
public List<UserDTO> getUsers() {
    return userService.findAll();
    // 404 qayidir! Spring "users" adli view axtarir.
}
```

Hel yolu: REST API ucun hemise `@RestController` istifade et.

### 4. `@Repository` və `@Service`-i qarisdirmaq

```java
// SEHV - mentiqsel layer-lər qarişdirilib
@Repository
public class OrderService {
    public void placeOrder(...) {
        // biznes logika burada olmamalidir
    }
}
```

Hel yolu: Repository DB erisimi ucun, Service biznes logika ucun. Bezen `@Service` DB sorgusu `@Repository`-dan alir və biznes qaydalari tətbiq edir.

### 5. Interface-in implementasiyasini da bean etmek

```java
@Repository  // SEHV - eyni bean 2 defe qeydiyyata dusur
public class UserRepositoryImpl implements UserRepository {
    // ...
}

@Repository
public interface UserRepository extends JpaRepository<User, Long> {
    // ...
}
```

Spring Data JPA interface-dan avtomatik implementasiya yaradir. Eger sen de `@Repository` yazmisansa, iki ferqli bean olur ve konflikt yaranir.

### 6. `@Configuration`-da `@Bean` unutmaq

```java
@Configuration
public class AppConfig {
    public RestTemplate restTemplate() {  // SEHV - @Bean yoxdur
        return new RestTemplate();
    }
}
```

Hel yolu: `@Bean` annotasiyasi vacibdir, yoxsa Spring metoda baxmir.

### 7. Field injection sıxıltı

```java
@Service
public class BadService {
    @Autowired  // Field injection - test etmek cetin olur
    private UserRepository repo;
}
```

Hel yolu: constructor injection ile yaz (fayl 83 bunu acıqlayır).

## Mini Musahibe Suallari

### 1. `@Component`, `@Service`, `@Repository` arasinda texniki ferq varmi?

`@Service` və `@Repository` əslində `@Component`-dən inherit olur, ona görə bean qeydiyyatı baxımından eynidirlər. Lakin fərqlər var:

- **Semantik (məna)**: `@Service` = biznes logika, `@Repository` = data access, `@Component` = ümumi. Bu, kodu oxuyan üçün aydınlıq yaradır.
- **`@Repository` əlavə olaraq exception translation verir**: JPA/Hibernate exception-larını Spring-in `DataAccessException`-ına çevirir.
- **AOP targeting**: AOP-da "bütün `@Service`-ləri wrap et" kimi qaydalar yazmaq mümkündür.

Kod danısmir — amma convention və future feature-lər üçün duzgun annotasiyani istifadə etmek vacibdir.

### 2. Laravel-də `@Service` annotasiyası niyə yoxdur?

Laravel convention-over-configuration felsefesindedir: sinfin `app/Services/` qovluğunda olmasi onun service olduğunu bildirmek ucun kifayetdir. PHP-nin Service Container-i type-hint ilə (constructor parametrinə baxaraq) asılılıqlari avtomatik tapir — annotasiya lazim olmur.

Üstəlik, Laravel Eloquent-i Active Record pattern istifadə edir — yəni Model hem data hem DB sorgulari üçün metodlar saxlayir. Spring-de bu ayrı-ayri siniflere bölünüb (`@Entity` + `@Repository`), ona görə də daha çox annotasiya lazim olur.

### 3. `@RestController` ilə `@Controller` fərqi nədir? Hansını nə vaxt istifadə edirsən?

`@RestController = @Controller + @ResponseBody`. Ferq netice qaytarma davranisindadir:

- `@Controller`: metod `String` qaytaranda Spring onu view adi hesab edir (məs. `return "home";` → `templates/home.html` render olunur).
- `@RestController`: metodun qaytardigi obyekt avtomatik JSON-a cevrilir (və ya string response body kimi gedir).

Istifade qaydasi:
- **REST API** (JSON endpoint-ler) → `@RestController`.
- **Server-side rendered HTML** (Thymeleaf, Freemarker) → `@Controller`.

Müasir Spring Boot layiheleri ekser hallarda API yazir, ona gorə `@RestController` daha çox görünür.
