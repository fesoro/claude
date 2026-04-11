# Annotasiyalar vs Atributlar — Java vs PHP

## Giris

Java-da **annotation** (@), PHP-de ise **attribute** (#[]) mexanizmi kod haqqinda melumat (metadata) elave etmek ucun istifade olunur. Bu metadata kompilyator, framework ve ya runtime terefinden oxunur ve muxtelif meqsedlerle istifade olunur: routing, validation, dependency injection, ORM mapping ve s.

Java annotation-lari 2004-cu ilden (Java 5), PHP attribute-lari ise 2020-ci ilden (PHP 8.0) movcuddur. Her ikisi eyni problemi hel etse de, sintaksis ve ishleme mexanizmleri ferqlenir.

---

## Java-da istifadesi

### Daxili (built-in) annotasiyalar

```java
public class BuiltInAnnotations {

    // @Override — metod ust sinifden override olundugunu bildirir
    // Kompilyator xetasi verir eger ust sinifde bele metod yoxdursa
    @Override
    public String toString() {
        return "BuiltInAnnotations instansiyasi";
    }

    // @Deprecated — metodun kohne oldugunu ve istifade olunmamali oldugunu bildirir
    @Deprecated(since = "2.0", forRemoval = true)
    public void oldMethod() {
        // Bu metod gelecek versiyada silinecek
    }

    // @SuppressWarnings — kompilyator xeberdarliqlarlni susdurur
    @SuppressWarnings("unchecked")
    public void uncheckedOperation() {
        List rawList = new ArrayList();
        rawList.add("test");
    }

    // @FunctionalInterface — interfeysde yalniz 1 abstract metod olmali
    @FunctionalInterface
    interface Calculator {
        double calculate(double a, double b);
    }
}
```

### Xususi (custom) annotasiya yaratma

Java-da annotasiya `@interface` ile yaradilir:

```java
import java.lang.annotation.*;

// Meta-annotasiyalar ile annotasiyanin davranishini teyyin edirik

@Retention(RetentionPolicy.RUNTIME)  // Ne vaxt movcuddur
@Target(ElementType.METHOD)          // Harada istifade oluna biler
@Documented                          // Javadoc-a daxil edilsin
public @interface RateLimit {
    int maxRequests() default 100;         // Susmaya gore 100
    int periodSeconds() default 60;        // Susmaya gore 60 saniye
    String message() default "Limitə catdiniz";
}

// Istifadesi
public class ApiController {

    @RateLimit(maxRequests = 10, periodSeconds = 30)
    public Response searchProducts(String query) {
        // ...
    }

    @RateLimit // default deyerlerle: 100 sorgu/60 saniye
    public Response listProducts() {
        // ...
    }
}
```

### Retention Policy — annotasiya ne vaxt movcuddur

```java
// SOURCE — yalniz menbe kodda, kompilyasiyada silinir
// Meselen: @Override, @SuppressWarnings
@Retention(RetentionPolicy.SOURCE)
public @interface Todo {
    String value();
}

// CLASS — .class faylinda qalir, amma runtime-da elcatan deyil
// Susmaya gore bu secilir
@Retention(RetentionPolicy.CLASS)
public @interface NonNull {
}

// RUNTIME — runtime-da Reflection ile oxuna biler
// Framework-ler ucun en cox istifade olunan
@Retention(RetentionPolicy.RUNTIME)
public @interface Cacheable {
    int ttl() default 3600;
}
```

### Target — annotasiyanin harada istifade olunacagi

```java
@Target({
    ElementType.TYPE,           // Sinif, interfeys, enum
    ElementType.METHOD,         // Metod
    ElementType.FIELD,          // Sahe
    ElementType.PARAMETER,     // Metod parametri
    ElementType.CONSTRUCTOR,   // Konstruktor
    ElementType.LOCAL_VARIABLE,// Lokal deyishen
    ElementType.ANNOTATION_TYPE, // Bashqa annotasiya uzerinde
    ElementType.PACKAGE,       // Paket
    ElementType.TYPE_PARAMETER,// Generic tip parametri (Java 8+)
    ElementType.TYPE_USE       // Her yerde tip istifade olunan (Java 8+)
})
public @interface MyAnnotation {}
```

### Reflection ile annotasiya oxuma

```java
import java.lang.reflect.Method;

public class AnnotationProcessor {

    public static void processRateLimits(Class<?> controllerClass) {
        for (Method method : controllerClass.getDeclaredMethods()) {

            // Metodda RateLimit annotasiyasi var mi?
            if (method.isAnnotationPresent(RateLimit.class)) {
                RateLimit limit = method.getAnnotation(RateLimit.class);

                System.out.printf(
                    "Metod: %s → Max: %d sorgu / %d saniye%n",
                    method.getName(),
                    limit.maxRequests(),
                    limit.periodSeconds()
                );
            }
        }
    }

    public static void main(String[] args) {
        processRateLimits(ApiController.class);
    }
}
```

### Real dunya numunesi — Spring Framework annotasiyalari

```java
import org.springframework.web.bind.annotation.*;
import org.springframework.beans.factory.annotation.Autowired;
import jakarta.validation.constraints.*;

@RestController
@RequestMapping("/api/users")
public class UserController {

    @Autowired // Dependency Injection
    private UserService userService;

    @GetMapping("/{id}")
    public User getUser(@PathVariable Long id) {
        return userService.findById(id);
    }

    @PostMapping
    public User createUser(@Valid @RequestBody CreateUserRequest request) {
        return userService.create(request);
    }
}

// Validation annotasiyalari
public class CreateUserRequest {

    @NotBlank(message = "Ad bosh ola bilmez")
    @Size(min = 2, max = 50)
    private String name;

    @Email(message = "Duzgun email daxil edin")
    @NotNull
    private String email;

    @Min(value = 18, message = "Yash 18-den az ola bilmez")
    private int age;
}

// JPA/Hibernate annotasiyalari
@Entity
@Table(name = "users")
public class User {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false, length = 50)
    private String name;

    @OneToMany(mappedBy = "user", cascade = CascadeType.ALL)
    private List<Order> orders;
}
```

### Annotation Processing — kompilyasiya zamani isleme

Java-da annotasiyalar kompilyasiya vaxtinda islene biler (Lombok, MapStruct, Dagger buna numunedir):

```java
// Lombok annotasiyalari — kompilyasiya vaxtinda kod generasiya edir
@Data           // getter, setter, toString, equals, hashCode yaradir
@Builder        // Builder pattern yaradir
@AllArgsConstructor
@NoArgsConstructor
public class Product {
    private Long id;
    private String name;
    private double price;
}

// Yuxaridaki annotasiyalar sayesinde bu ishleyir:
Product p = Product.builder()
    .id(1L)
    .name("Kitab")
    .price(25.99)
    .build();
```

---

## PHP-de istifadesi

### PHP 8.0 Attributes — esas sintaksis

PHP 8-den evvel metadata ucun **DocBlock** comentleri istifade olunurdu. PHP 8 ile native attribute desteyi geldi:

```php
<?php

// Kohne yol — DocBlock (hələ de geniş istifade olunur)
/**
 * @Route("/api/users", methods={"GET"})
 * @deprecated Bu metod 3.0 versiyasinda silinecek
 */
function oldWay() {}

// Yeni yol — PHP 8 Attributes
#[Route('/api/users', methods: ['GET'])]
#[Deprecated(reason: 'Bu metod 3.0 versiyasinda silinecek')]
function newWay() {}
```

### Daxili (built-in) atributlar

```php
<?php

class BuiltInAttributes
{
    // #[Deprecated] — PHP 8.4+
    #[Deprecated(message: "yeniMetod() istifade edin", since: "2.0")]
    public function kohneMetod(): void
    {
        // ...
    }

    // #[Override] — PHP 8.3+
    #[Override]
    public function toString(): string
    {
        return 'numune';
    }

    // #[SensitiveParameter] — PHP 8.2+ (stack trace-de deyeri gizledir)
    public function login(
        string $username,
        #[SensitiveParameter] string $password
    ): bool {
        // Xeta bash verse, stack trace-de $password gizli olacaq
        throw new RuntimeException("Test xetasi");
        // Stack trace: login("admin", Object(SensitiveParameterValue))
    }
}
```

### Xususi (custom) atribut yaratma

```php
<?php

use Attribute;

// #[Attribute] ile sinfi atribut kimi isharet edirik
#[Attribute(Attribute::TARGET_METHOD)]
class RateLimit
{
    public function __construct(
        public readonly int $maxRequests = 100,
        public readonly int $periodSeconds = 60,
        public readonly string $message = 'Limitə catdiniz'
    ) {}
}

// Target parametrleri:
// Attribute::TARGET_CLASS
// Attribute::TARGET_METHOD
// Attribute::TARGET_PROPERTY
// Attribute::TARGET_PARAMETER
// Attribute::TARGET_FUNCTION
// Attribute::TARGET_CLASS_CONSTANT
// Attribute::TARGET_ALL (susmaya gore)
// Attribute::IS_REPEATABLE (eyni yerde tekrar istifade)

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Route
{
    public function __construct(
        public readonly string $path,
        public readonly string $method = 'GET',
        public readonly ?string $name = null,
        public readonly array $middleware = []
    ) {}
}

// Istifadesi
class UserController
{
    #[Route('/users', method: 'GET', name: 'users.index')]
    #[Route('/api/users', method: 'GET', name: 'api.users.index')]
    public function index(): array
    {
        return ['users' => []];
    }

    #[Route('/users/{id}', method: 'GET')]
    #[RateLimit(maxRequests: 30, periodSeconds: 60)]
    public function show(int $id): array
    {
        return ['user' => $id];
    }

    #[Route('/users', method: 'POST')]
    #[RateLimit(maxRequests: 5, periodSeconds: 60)]
    public function store(): array
    {
        return ['created' => true];
    }
}
```

### Reflection ile atribut oxuma

```php
<?php

class AttributeProcessor
{
    /**
     * Controller sinifinden butun route atributlarini tapir
     */
    public static function extractRoutes(string $controllerClass): array
    {
        $routes = [];
        $reflection = new ReflectionClass($controllerClass);

        foreach ($reflection->getMethods() as $method) {
            // Route atributlarini al
            $routeAttributes = $method->getAttributes(Route::class);

            foreach ($routeAttributes as $attribute) {
                // newInstance() caghiranda atribut sinifinin konstruktoru isleyir
                $route = $attribute->newInstance();

                $routes[] = [
                    'path' => $route->path,
                    'method' => $route->method,
                    'name' => $route->name,
                    'handler' => $controllerClass . '::' . $method->getName(),
                ];
            }

            // RateLimit atributunu yoxla
            $rateLimits = $method->getAttributes(RateLimit::class);
            foreach ($rateLimits as $attr) {
                $limit = $attr->newInstance();
                echo sprintf(
                    "%s: max %d sorgu / %d saniye\n",
                    $method->getName(),
                    $limit->maxRequests,
                    $limit->periodSeconds
                );
            }
        }

        return $routes;
    }
}

// Istifade
$routes = AttributeProcessor::extractRoutes(UserController::class);
print_r($routes);
```

### Validation atributlari — praktik numune

```php
<?php

#[Attribute(Attribute::TARGET_PROPERTY)]
class NotBlank
{
    public function __construct(
        public readonly string $message = 'Bu sahe bosh ola bilmez'
    ) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Email
{
    public function __construct(
        public readonly string $message = 'Duzgun email daxil edin'
    ) {}
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Min
{
    public function __construct(
        public readonly int $value,
        public readonly string $message = ''
    ) {}
}

// DTO sinifinde istifade
class CreateUserRequest
{
    public function __construct(
        #[NotBlank(message: 'Ad bosh ola bilmez')]
        public readonly string $name,

        #[NotBlank]
        #[Email]
        public readonly string $email,

        #[Min(value: 18, message: 'Yash 18-den az ola bilmez')]
        public readonly int $age
    ) {}
}

// Validator
class Validator
{
    public function validate(object $object): array
    {
        $errors = [];
        $reflection = new ReflectionClass($object);

        foreach ($reflection->getProperties() as $property) {
            $value = $property->getValue($object);

            foreach ($property->getAttributes() as $attribute) {
                $attrInstance = $attribute->newInstance();

                if ($attrInstance instanceof NotBlank && empty($value)) {
                    $errors[$property->getName()][] = $attrInstance->message;
                }

                if ($attrInstance instanceof Email && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$property->getName()][] = $attrInstance->message;
                }

                if ($attrInstance instanceof Min && $value < $attrInstance->value) {
                    $message = $attrInstance->message ?: "Minimum deyer: {$attrInstance->value}";
                    $errors[$property->getName()][] = $message;
                }
            }
        }

        return $errors;
    }
}

// Istifade
$request = new CreateUserRequest('', 'invalid-email', 15);
$validator = new Validator();
$errors = $validator->validate($request);
// [
//   'name' => ['Ad bosh ola bilmez'],
//   'email' => ['Duzgun email daxil edin'],
//   'age' => ['Yash 18-den az ola bilmez']
// ]
```

### Laravel-de atributlar (real numune)

```php
<?php

use Illuminate\Routing\Controller;
use Illuminate\Routing\Attributes\Route;
use Illuminate\Routing\Attributes\Middleware;

// Laravel 11+ atribut destekli routing
#[Middleware('auth')]
class OrderController extends Controller
{
    #[Route('/orders', methods: ['GET'])]
    public function index()
    {
        return Order::all();
    }

    #[Route('/orders/{order}', methods: ['GET'])]
    public function show(Order $order)
    {
        return $order;
    }

    #[Route('/orders', methods: ['POST'])]
    #[Middleware('throttle:10,1')]
    public function store(CreateOrderRequest $request)
    {
        return Order::create($request->validated());
    }
}
```

---

## Esas ferqler

| Xususiyyet | Java Annotations | PHP Attributes |
|---|---|---|
| **Sintaksis** | `@AnnotationName` | `#[AttributeName]` |
| **Movcud versiya** | Java 5 (2004) | PHP 8.0 (2020) |
| **Elan sintaksisi** | `@interface` | `#[Attribute]` ile isharelenmish sinif |
| **Retention Policy** | SOURCE, CLASS, RUNTIME | Yalniz runtime (Reflection ile) |
| **Kompilyasiya zamani ishleme** | Var (Annotation Processor) | Yoxdur |
| **Target** | `@Target(ElementType.X)` | `Attribute::TARGET_X` |
| **Tekrarlana bilme** | `@Repeatable` ile | `Attribute::IS_REPEATABLE` ile |
| **Deyer tipleri** | Primitiv, String, Class, enum, annotation, array | Istənilən PHP tipi |
| **Reflection** | `getAnnotation()`, `isAnnotationPresent()` | `getAttributes()`, `newInstance()` |
| **DocBlock alternativi** | Annotation-dan evvel Javadoc istifade olunurdu | Attribute-dan evvel DocBlock istifade olunurdu |

---

## Niye bele ferqler var?

### Sintaksis ferqi

Java `@` simvolunu secdi, cunki annotasiyalar 2004-cu ilde elave olunanda bu simvol hecbir yerde istifade olunmurdu ve gorunus cehetden aydın idi.

PHP `#[...]` sintaksisini secdi, cunki:
- `@` artiq xeta suppression operatoru kimi istifade olunurdu (`@file_get_contents()`)
- `#` simvolu evvelce tek setirlik comment kimi istifade olunurdu, amma `#[` kombinasiyasi hecbir movcud kodda olmadigi ucun geriye uyghunlugu pozmir
- Kohne PHP versiyalarinda `#[...]` comment kimi qebul olunur, yeni kod kohne versiyada sadece ignore olunur

### Retention Policy ferqi

Java-da uc retention seviyyesi var, cunki Java kompilyasiya olunan dildir ve muxtelif merhelede metadata lazim ola biler:
- **SOURCE**: Yalniz development vaxtinda (IDE, linter)
- **CLASS**: Bytecode analiz alatleri ucun
- **RUNTIME**: Framework-ler ucun (Spring, Hibernate)

PHP interpretasiya olunan dildir — kompilyasiya merhəlesi yoxdur. Buna gore yalniz runtime seviyyesinde metadata movcuddur ve bu, Reflection API vasitesile oxunur.

### Annotation Processing ferqi

Java-nin en guclu xususiyyetlerindenb biri kompilyasiya zamani annotation processing-dir. Lombok, MapStruct, Dagger kimi alətler kompilyasiya vaxtinda annotasiyalari oxuyub yeni kod generasiya edir. Bu, runtime performansina tesir etmir — lazim olan kod artiq kompilyasiya merhelesinde yaranir.

PHP-de bu mumkun deyil, cunki kompilyasiya merhəlesi yoxdur. PHP attribute-lari yalniz runtime-da Reflection ile oxunur. Lakin bu catishmazliq OPcache ve preloading ile azaldilir — reflection neticeleri keshlenə biler.
