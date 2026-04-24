# @SpringBootApplication Annotasiyasının Dərin İzahı

> **Seviyye:** Beginner ⭐

## Giriş

Hər yeni Spring Boot layihəsində gözümüzün önünə ilk çıxan annotasiya `@SpringBootApplication`-dır. O, main sinfinin üzərində durur və sanki "sehrli" bir şəkildə bütün tətbiqi işə salır. Amma əslində bu annotasiya sehr deyil — üç ayrı annotasiyanın birləşməsidir (meta-annotation).

Laravel-də buna bənzər bir şey yoxdur. Laravel-də `bootstrap/app.php` faylı framework-u yükləyir, `config/app.php`-də service provider-lər siyahısı var və onlar bir-bir çağırılır. Spring Boot isə bu prosesi "auto-configuration" adlı bir sistemlə avtomatlaşdırır.

Bu faylda biz `@SpringBootApplication`-ı tam açıb anlayacağıq — hər komponenti ayrıca, necə işlədiyini və Laravel-dəki qarşılığını göstərəcəyik.

## Spring/Java-də istifadəsi

### 1. Ümumi görünüş

```java
package com.example.demo;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;

@SpringBootApplication
public class DemoApplication {

    public static void main(String[] args) {
        SpringApplication.run(DemoApplication.class, args);
    }
}
```

Budur bütün sehri edən iki sətir: `@SpringBootApplication` və `SpringApplication.run(...)`.

### 2. `@SpringBootApplication` nədir?

Bu annotasiyanın özü Spring Boot mənbə kodunda belə tanımlanıb:

```java
@Target(ElementType.TYPE)
@Retention(RetentionPolicy.RUNTIME)
@Documented
@Inherited
@SpringBootConfiguration   // = @Configuration
@EnableAutoConfiguration
@ComponentScan(excludeFilters = {
    @Filter(type = FilterType.CUSTOM, classes = TypeExcludeFilter.class),
    @Filter(type = FilterType.CUSTOM, classes = AutoConfigurationExcludeFilter.class)
})
public @interface SpringBootApplication {
    // parametrlər...
}
```

Yəni `@SpringBootApplication` əslində **üç annotasiyanın qısa formasıdır**:

1. `@Configuration` (və ya `@SpringBootConfiguration`)
2. `@EnableAutoConfiguration`
3. `@ComponentScan`

Bu üç annotasiyanı ayrıca da yazmaq olar:

```java
@Configuration
@EnableAutoConfiguration
@ComponentScan
public class DemoApplication {

    public static void main(String[] args) {
        SpringApplication.run(DemoApplication.class, args);
    }
}
```

Bu, yuxarıdakı ilə tam eyni işləyir. Amma hər dəfə üç sətir yazmaq əvəzinə, Spring Boot komandası bunları bir annotasiyada birləşdirib.

### 3. `@Configuration` — nə edir?

`@Configuration` bir sinifi "bean definition source" (bean tərif mənbəyi) kimi işarələyir. Yəni bu sinif daxilində `@Bean` metodları varsa, Spring onları tapır və bean kimi qeydiyyatdan keçirir.

```java
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

Main sinfimiz də `@Configuration`-dır — yəni oraya da `@Bean` metodları yaza bilərik:

```java
@SpringBootApplication
public class DemoApplication {

    public static void main(String[] args) {
        SpringApplication.run(DemoApplication.class, args);
    }

    @Bean
    public RestTemplate restTemplate() {
        return new RestTemplate();
    }
}
```

Bu işləyir, amma yaxşı praktika deyil — main sinfi təmiz saxlamaq üçün ayrıca `@Configuration` sinifləri yaratmaq daha yaxşıdır.

### 4. `@EnableAutoConfiguration` — sehrin əsas hissəsi

Bu, Spring Boot-un ən güclü xüsusiyyətidir. O, classpath-də hansı kitabxanaların olduğuna baxır və ona görə avtomatik konfiqurasiya tətbiq edir.

**Necə işləyir?**

Spring Boot kitabxanalarının içində `META-INF/spring/org.springframework.boot.autoconfigure.AutoConfiguration.imports` adlı fayl var (əvvəllər `spring.factories` idi). Bu fayl avtomatik konfiqurasiya siniflərinin siyahısıdır:

```
org.springframework.boot.autoconfigure.jdbc.DataSourceAutoConfiguration
org.springframework.boot.autoconfigure.web.servlet.WebMvcAutoConfiguration
org.springframework.boot.autoconfigure.orm.jpa.HibernateJpaAutoConfiguration
org.springframework.boot.autoconfigure.security.servlet.SecurityAutoConfiguration
...
```

Hər biri şərti annotasiyalarla qorunur:

```java
@Configuration
@ConditionalOnClass({DataSource.class, EmbeddedDatabaseType.class})
@EnableConfigurationProperties(DataSourceProperties.class)
public class DataSourceAutoConfiguration {
    // DataSource bean-i yaradır, əgər classpath-də DataSource sinfi varsa
}
```

Yəni `spring-boot-starter-data-jpa` dependency-sini əlavə edəndə:
- `DataSource` sinfi classpath-də peyda olur
- `@ConditionalOnClass` şərti ödənir
- `DataSourceAutoConfiguration` aktivləşir
- DataSource bean-i avtomatik yaradılır

Sən heç nə etməmisən, sadəcə dependency əlavə etmisən.

**Avtomatik konfiqurasiyanı söndürmək:**

```java
@SpringBootApplication(exclude = {
    DataSourceAutoConfiguration.class,
    SecurityAutoConfiguration.class
})
public class DemoApplication {
    // ...
}
```

Bu, "mən DB istifadə edirəm, amma Spring özü DataSource yaratmasın, mən əllə edəcəyəm" demək olur.

### 5. `@ComponentScan` — komponent axtarışı

Bu annotasiya Spring-ə deyir: "bu paketdən başla və aşağı paketlərdə `@Component`, `@Service`, `@Repository`, `@Controller` olan siniflər axtar".

**Default davranış:** main sinfinin olduğu paket və onun **alt paketləri** skan edilir.

Məsələn:

```
com.example.demo/
├── DemoApplication.java       ← @SpringBootApplication burada
├── controller/
│   └── UserController.java    ← skan olunur (alt paket)
├── service/
│   └── UserService.java       ← skan olunur
└── repository/
    └── UserRepository.java    ← skan olunur
```

Amma əgər controller-i **fərqli root paketdə** yaratsan:

```
com.example.demo/
└── DemoApplication.java

com.other/                      ← fərqli root paket
└── OtherController.java        ← SKAN OLUNMUR!
```

Bu halda `OtherController` tapılmır və 404 alırsan.

**Həll yolları:**

1. Main sinfini yuxarı paketə apar:
```
com.example/
├── DemoApplication.java       ← @SpringBootApplication burada
├── demo/
│   └── ...
└── other/
    └── OtherController.java   ← indi skan olunur
```

2. Və ya `scanBasePackages` ilə əlavə paket göstər:
```java
@SpringBootApplication(scanBasePackages = {"com.example.demo", "com.other"})
public class DemoApplication {
    // ...
}
```

3. Və ya spesifik sinif göstər:
```java
@SpringBootApplication(scanBasePackageClasses = {DemoApplication.class, OtherController.class})
public class DemoApplication {
    // ...
}
```

### 6. `SpringApplication.run(...)` — nə edir?

```java
SpringApplication.run(DemoApplication.class, args);
```

Bu metod arxa planda bir neçə iş görür:

1. **ApplicationContext yaradır** — bütün bean-lərin yaşadığı "konteyner"
2. **Banner çap edir** — terminalda Spring logosu (söndürmək üçün `spring.main.banner-mode=off`)
3. **Component scan işlədir** — `@Component`, `@Service` tapır
4. **Auto-configuration tətbiq edir** — classpath-ə uyğun bean-lər yaradır
5. **Embedded server start edir** — Tomcat 8080 portunda
6. **CommandLineRunner / ApplicationRunner** interface-lərini işlədir
7. **ApplicationReadyEvent** event-i emit edir

Daha ətraflı görmək üçün `--debug` flag-i ilə işə sala bilərsən:

```bash
./mvnw spring-boot:run -Dspring-boot.run.arguments=--debug
```

Bu, "CONDITIONS EVALUATION REPORT" göstərir — hansı auto-config aktivdir, hansı deyil və niyə.

### 7. Tam nümunə: kompleks konfiqurasiya

```java
package com.example.app;

import org.springframework.boot.SpringApplication;
import org.springframework.boot.autoconfigure.SpringBootApplication;
import org.springframework.boot.autoconfigure.jdbc.DataSourceAutoConfiguration;
import org.springframework.context.ApplicationContext;

@SpringBootApplication(
    scanBasePackages = {
        "com.example.app",
        "com.example.shared"
    },
    exclude = {
        DataSourceAutoConfiguration.class  // özümüz DataSource yaradacağıq
    }
)
public class AppApplication {

    public static void main(String[] args) {
        ApplicationContext context = SpringApplication.run(AppApplication.class, args);

        // Bütün bean adlarını çap et
        String[] beanNames = context.getBeanDefinitionNames();
        System.out.println("Total beans: " + beanNames.length);
    }
}
```

### 8. Paket iyerarxiyası niyə vacibdir?

Bu ən ümumi beginner səhvidir. Baxaq:

**SƏHV struktur:**

```
src/main/java/
└── DemoApplication.java       ← default paketdə!
└── UserController.java
```

Bu işləmir çünki:
- Default paket (`package` yazılmadan) ümumiyyətlə pis praktikadır
- `@ComponentScan` default paketi düzgün idarə etmir

**DÜZGÜN struktur:**

```
src/main/java/com/example/demo/
├── DemoApplication.java           ← package com.example.demo;
├── controller/
│   └── UserController.java        ← package com.example.demo.controller;
└── service/
    └── UserService.java           ← package com.example.demo.service;
```

## Laravel/PHP-də istifadəsi

Laravel-də "auto-configuration" fərqli bir mexanizmlə — **Service Provider**-lər vasitəsilə baş verir.

### 1. Laravel-də giriş nöqtəsi

Hər sorğu üçün `public/index.php` çağırılır:

```php
<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
```

### 2. `bootstrap/app.php` (Laravel 11)

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // middleware konfiqurasiyası
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // exception handling
    })->create();
```

### 3. Service Provider-lər (Laravel 10 və əvvəl)

`config/app.php`-də:

```php
'providers' => ServiceProvider::defaultProviders()->merge([
    // Framework provider-ləri
    Illuminate\Auth\AuthServiceProvider::class,
    Illuminate\Broadcasting\BroadcastServiceProvider::class,
    Illuminate\Database\DatabaseServiceProvider::class,
    Illuminate\Cache\CacheServiceProvider::class,

    // Tətbiq provider-ləri
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\RouteServiceProvider::class,
])->toArray(),
```

### 4. Service Provider nümunəsi

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * register() — yalnız container-ə bağlama.
     * Spring-də @Configuration + @Bean-ə bənzəyir.
     */
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, function ($app) {
            return new StripeGateway(config('services.stripe.key'));
        });
    }

    /**
     * boot() — bütün provider-lər register edildikdən sonra.
     * Event listener, route override və s.
     */
    public function boot(): void
    {
        // ...
    }
}
```

### 5. Package Auto-Discovery (Laravel-in "auto-configuration"-ı)

Laravel-də composer paketinin `composer.json`-unda:

```json
{
    "extra": {
        "laravel": {
            "providers": [
                "Vendor\\Package\\PackageServiceProvider"
            ],
            "aliases": {
                "Package": "Vendor\\Package\\Facades\\Package"
            }
        }
    }
}
```

Bu, Laravel-ə deyir: "məni install edəndə bu provider-i avtomatik qeydiyyatdan keçir". Bu, Spring Boot-un `AutoConfiguration.imports` faylının qarşılığıdır, amma daha sadədir.

### 6. Component scan qarşılığı

Laravel-də "component scan" yoxdur, əvəzinə **PSR-4 autoloading** var:

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/"
        }
    }
}
```

Yəni `App\Http\Controllers\UserController` sinfi `app/Http/Controllers/UserController.php` faylından yüklənir. Amma bu Laravel-ə sinfi avtomatik "qeydiyyatdan keçir" demir — sən onu route-da göstərməlisən.

## Əsas fərqlər

| Xüsusiyyət | Spring Boot | Laravel |
|---|---|---|
| **Giriş nöqtəsi** | `main()` metodu | `public/index.php` |
| **Main annotasiya / funksiya** | `@SpringBootApplication` | `bootstrap/app.php` + Service Provider-lər |
| **Avtomatik konfiqurasiya** | `AutoConfiguration.imports` + `@ConditionalOn*` | Package auto-discovery (`composer.json` extra) |
| **Komponent axtarışı** | `@ComponentScan` (classpath scanning) | PSR-4 autoloading + manual registration |
| **Bean qeydiyyatı** | Annotasiya (`@Service`, `@Component`) | Service Provider-də `$this->app->bind()` |
| **Ömür başlanğıcı** | Tətbiq işə düşəndə bir dəfə | Hər HTTP sorğusu üçün yenidən |
| **Exclude etmək** | `exclude = {XxxAutoConfiguration.class}` | `config/app.php`-dən provider-i çıxarmaq |
| **Şərti aktivləşmə** | `@ConditionalOnClass`, `@ConditionalOnProperty` | Provider-in `register()` metodunda `if` |
| **Banner** | Spring Boot ASCII art (default) | Yoxdur |
| **Paket strukturu önəmi** | Vacib (scan root) | Az vacib (PSR-4 autoload) |

## Niyə belə fərqlər var?

### Spring Boot-un yanaşması

1. **Uzun yaşayan JVM**: Java tətbiqi bir dəfə start olur və saatlarla işləyir. Ona görə başlanğıcda bir az vaxt sərf edib bütün konfiqurasiyanı hazırlamaq əlverişlidir.

2. **Compile time type safety**: Java statik tipli dildir. Annotasiyalar compile zamanında tanınır, xətalar başlanğıcda çıxır.

3. **Classpath scanning mədəniyyəti**: Java-da Reflection API çox güclüdür. Spring classpath-dəki bütün `.class` fayllarını oxuyub annotasiyaları analiz edə bilir.

4. **Opinionated defaults**: Spring Boot-un felsefəsi: "90% halda eyni konfiqurasiya lazımdır, biz onu default edirik, sən yalnız fərqli olanı yaz".

### Laravel-in yanaşması

1. **Request-per-process**: PHP-də hər sorğu yeni prosesdir (və ya FPM worker-idir). Start-up ucuz olmalıdır, ona görə provider-lər sadə və sürətli yüklənir.

2. **Explicit registration**: Laravel-də provider-lər əllə qeydiyyatdan keçir (indi bootstrap/app.php-də, əvvəl config/app.php-də). Bu, daha az "sehr" deməkdir.

3. **Package auto-discovery**: Laravel bir az "auto-config" özəlliyi verir — paket quraşdırılanda composer hook-u ilə provider-lər avtomatik tapılır.

4. **Dinamik tip sistemi**: PHP-də compile time yoxlama zəifdir, ona görə avtomatik konfiqurasiya həmişə runtime-da test edilir.

## Ümumi səhvlər (Beginner traps)

### Səhv 1: Main sinfini aşağı paketə qoymaq

```
com.example.demo.main/
└── DemoApplication.java       ← @SpringBootApplication burada

com.example.demo.controller/    ← component scan bunu GÖRMÜR!
└── UserController.java
```

`@ComponentScan` default olaraq main sinfinin paketini və **alt paketlərini** skan edir. Əgər main `com.example.demo.main`-dadırsa, `com.example.demo.controller` **alt paket deyil**, qardaş paketdir.

**Həll:** Main sinfi `com.example.demo` paketinə qoy.

### Səhv 2: Default paket istifadə etmək

```java
// Yanlış - package tərifi yoxdur
public class DemoApplication {
    // ...
}
```

Spring Boot default paketi (package declaration olmayan) düzgün idarə etmir. Həmişə paket ver.

### Səhv 3: `SpringApplication.run` unudulmaq

```java
@SpringBootApplication
public class DemoApplication {
    public static void main(String[] args) {
        // SpringApplication.run(...) yazılmayıb — tətbiq başlanmır!
    }
}
```

Annotasiya sadəcə "metadata"-dır. Tətbiqi işə salan `SpringApplication.run(...)` metodudur.

### Səhv 4: Birdən çox `@SpringBootApplication`

```java
// Fayl 1
@SpringBootApplication
public class App1 { }

// Fayl 2
@SpringBootApplication
public class App2 { }
```

Bir tətbiqdə yalnız bir main sinfi olmalıdır. İki `@SpringBootApplication` olsa, component scan qarışır.

### Səhv 5: Exclude edilmiş auto-config-i istifadə etmək

```java
@SpringBootApplication(exclude = DataSourceAutoConfiguration.class)
public class App { }

@Repository
public interface UserRepository extends JpaRepository<User, Long> { }
// DataSource yoxdur — UserRepository çalışmır!
```

Auto-config-i exclude etdinsə, özün əvəzedicisini qurmalısan.

## Hansı framework-də var, hansında yoxdur?

### Yalnız Spring Boot-da

- **`@ConditionalOn*` annotasiyaları**: `@ConditionalOnClass`, `@ConditionalOnProperty`, `@ConditionalOnMissingBean` — bean-in şərtlə yaradılması
- **`AutoConfiguration.imports` fayl**: standart yol ilə auto-config qoşmaq
- **Classpath scanning**: annotasiyaya görə avtomatik bean qeydiyyatı
- **Banner customization**: `banner.txt` faylı ilə ASCII art
- **ApplicationContext inspect**: runtime-da bütün bean adlarını görmək

### Yalnız Laravel-də

- **Package auto-discovery**: composer install-dan sonra avtomatik provider qeydiyyatı
- **Artisan CLI**: `php artisan make:provider` ilə provider yaratmaq
- **Facade sistemi**: static-looking API container-dən
- **register() / boot() fazaları**: iki mərhələli provider lifecycle

### İkisində də var, fərqli formada

- **Avtomatik konfiqurasiya**: Spring-də classpath-ə görə, Laravel-də composer-ə görə
- **Dependency yükləməsi**: Spring-də Maven/Gradle, Laravel-də Composer
- **Application bootstrap**: Spring-də `SpringApplication.run`, Laravel-də `bootstrap/app.php`

## Actuator qısa qeyd

Spring Boot Actuator tətbiqin "sağlamlıq" vəziyyətini və metriklərini göstərir:

```xml
<dependency>
    <groupId>org.springframework.boot</groupId>
    <artifactId>spring-boot-starter-actuator</artifactId>
</dependency>
```

Actuator əlavə olunanda yeni endpoint-lər açılır:
- `/actuator/health` — sağlamlıq statusu
- `/actuator/info` — tətbiq məlumatı
- `/actuator/metrics` — performance metriklər

(Detallı izah üçün fayl 70-ə bax.)

## Mini müsahibə sualları

**Sual 1:** `@SpringBootApplication` hansı üç annotasiyanın birləşməsidir və hər biri nə edir?

*Cavab:* `@SpringBootApplication` = `@Configuration` + `@EnableAutoConfiguration` + `@ComponentScan`.
- `@Configuration` — sinfi bean definition source edir, `@Bean` metodları dəstəkləyir
- `@EnableAutoConfiguration` — classpath-dəki kitabxanalara görə avtomatik bean-lər yaradır (`AutoConfiguration.imports` faylı vasitəsilə)
- `@ComponentScan` — cari paket və alt paketlərdə `@Component`, `@Service`, `@Repository`, `@Controller` axtarır və bean kimi qeydiyyatdan keçirir

**Sual 2:** Main sinfin `com.example.demo` paketindədir, amma `com.other` paketində olan controller-lər tapılmır. Nə etməli?

*Cavab:* Üç həll yolu:
1. Bütün kodu `com.example.demo` altında birləşdir
2. `@SpringBootApplication(scanBasePackages = {"com.example.demo", "com.other"})` yaz
3. Main sinfini daha yuxarı paketə (`com.example` və ya `com`) apar ki, hər iki paket alt paket olsun

**Sual 3:** `@EnableAutoConfiguration` necə işləyir? Hansı mexanizm onu "ağıllı" edir?

*Cavab:* Spring Boot hər starter içində `META-INF/spring/org.springframework.boot.autoconfigure.AutoConfiguration.imports` faylı saxlayır — bu fayl auto-config siniflərinin siyahısıdır. Tətbiq başlarkən bu fayllar oxunur, bütün auto-config sinifləri yüklənir, amma hər biri `@ConditionalOnClass`, `@ConditionalOnProperty` kimi şərtlərlə qorunur. Şərt ödənirsə (məsələn, DataSource sinfi classpath-dədir), auto-config aktivləşir və bean-lər yaradılır. Bu, "dependency əlavə et, işə düşür" effektini verir.
