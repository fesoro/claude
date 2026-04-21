# Jakarta EE Migration vs Laravel/PHP Upgrade — Dərin Müqayisə

## Giriş

Jakarta EE migrasiyası — Spring 5-dən Spring 6-ya keçidin ən böyük breaking change-idir. Oracle 2017-ci ildə Java EE spesifikasiyalarını Eclipse Foundation-a verdi, yeni ad **Jakarta EE** oldu. Oracle isə `javax.*` namespace-inə "Oracle brand" sayıldığına görə sahiblik tələb etdi. Nəticə: Eclipse yeni versiyalarda **hər namespace-i `jakarta.*`-a köçürməli oldu**. Spring 6 / Spring Boot 3 yalnız `jakarta.*` API-si ilə işləyir — `javax.*` tamamilə kəsilib. Üstəlik Java 17 minimum tələbdir.

PHP dünyasında belə miqyaslı namespace köçürməsi olmayıb, amma konseptual olaraq oxşar durumlar var: Laravel major upgrade-ləri (10 → 11 → 12), Symfony 2 → 3 → 4, PHP 7 → 8 özü. Bunlar üçün **Rector** (AST əsaslı PHP yenilənmə aləti), **Laravel Shift** xidməti, Symfony-nin `rector/rector` bridge-i var. Miqyas kiçikdir, amma yanaşma eynidir: bir versiyadan başqasına semantik köçürmə.

---

## Spring-də istifadəsi

### 1) Javax → Jakarta — nədir?

Spring 5 koduna baxaq:

```java
// Spring 5 / Boot 2
import javax.persistence.Entity;
import javax.persistence.Id;
import javax.validation.constraints.NotNull;
import javax.servlet.http.HttpServletRequest;
import javax.annotation.PostConstruct;

@Entity
public class Customer {
    @Id Long id;
    @NotNull String email;
}

@Controller
public class HomeController {
    @GetMapping("/")
    public String home(HttpServletRequest req) { ... }
}
```

Spring 6 / Boot 3-də həmin kod:

```java
// Spring 6 / Boot 3
import jakarta.persistence.Entity;
import jakarta.persistence.Id;
import jakarta.validation.constraints.NotNull;
import jakarta.servlet.http.HttpServletRequest;
import jakarta.annotation.PostConstruct;
```

Hər `javax.` ilə başlayan spesifikasiya API-sini `jakarta.` ilə əvəz etməlisən.

### 2) Tələblər

| Komponent | Spring 5 / Boot 2 | Spring 6 / Boot 3 |
|---|---|---|
| Java minimum | 8 (Boot 2.x) | 17 |
| Servlet API | 4.x (`javax.servlet`) | 6.0+ (`jakarta.servlet`) |
| JPA | 2.x (`javax.persistence`) | 3.1+ (`jakarta.persistence`) |
| Validation | 2.x (`javax.validation`) | 3.0+ (`jakarta.validation`) |
| Hibernate | 5.x | 6.x |
| Tomcat | 9.x | 10.1+ |
| Jetty | 9.x / 10.x | 11+ |
| Thymeleaf | 3.0 | 3.1+ |

### 3) Təsirlənən spesifikasiyalar

```
javax.servlet      → jakarta.servlet
javax.persistence  → jakarta.persistence
javax.validation   → jakarta.validation
javax.jms          → jakarta.jms
javax.annotation   → jakarta.annotation
javax.ejb          → jakarta.ejb
javax.transaction  → jakarta.transaction
javax.mail         → jakarta.mail
javax.ws.rs        → jakarta.ws.rs (JAX-RS)
javax.xml.bind     → jakarta.xml.bind (JAXB, artıq ayrıca library-də)
```

Diqqət: `java.*` paketləri (məsələn `java.util.concurrent`) **DƏYIŞMIR** — onlar JDK içindədir. Yalnız `javax.*` spesifikasiya API-ləri köçür.

### 4) Maven asılılıqlar — dəyişmə nümunəsi

Əvvəl:

```xml
<dependency>
    <groupId>javax.servlet</groupId>
    <artifactId>javax.servlet-api</artifactId>
    <version>4.0.1</version>
    <scope>provided</scope>
</dependency>
<dependency>
    <groupId>javax.persistence</groupId>
    <artifactId>javax.persistence-api</artifactId>
    <version>2.2</version>
</dependency>
<dependency>
    <groupId>javax.validation</groupId>
    <artifactId>validation-api</artifactId>
    <version>2.0.1.Final</version>
</dependency>
```

Sonra:

```xml
<dependency>
    <groupId>jakarta.servlet</groupId>
    <artifactId>jakarta.servlet-api</artifactId>
    <version>6.0.0</version>
    <scope>provided</scope>
</dependency>
<dependency>
    <groupId>jakarta.persistence</groupId>
    <artifactId>jakarta.persistence-api</artifactId>
    <version>3.1.0</version>
</dependency>
<dependency>
    <groupId>jakarta.validation</groupId>
    <artifactId>jakarta.validation-api</artifactId>
    <version>3.0.2</version>
</dependency>
```

### 5) Eclipse Transformer — avtomatik tool

Eclipse Foundation-un rəsmi aləti bytecode və source kod səviyyəsində transformasiya edir:

```bash
# source kodu
java -jar org.eclipse.transformer.cli.jar \
    --overwrite \
    -o src/main/java src/main/java

# yaxud JAR faylı (third-party kitabxana jakarta-uyğunlaşdırmayıbsa)
java -jar org.eclipse.transformer.cli.jar \
    -o vendor/legacy-lib.jar vendor/legacy-lib-jakarta.jar
```

### 6) OpenRewrite — avtomatik refactoring

OpenRewrite-in `spring-boot-migrator` recipe paketi:

```xml
<plugin>
    <groupId>org.openrewrite.maven</groupId>
    <artifactId>rewrite-maven-plugin</artifactId>
    <version>5.40.2</version>
    <configuration>
        <activeRecipes>
            <recipe>org.openrewrite.java.spring.boot3.UpgradeSpringBoot_3_3</recipe>
            <recipe>org.openrewrite.java.migrate.jakarta.JavaxMigrationToJakarta</recipe>
        </activeRecipes>
    </configuration>
    <dependencies>
        <dependency>
            <groupId>org.openrewrite.recipe</groupId>
            <artifactId>rewrite-spring</artifactId>
            <version>5.19.0</version>
        </dependency>
    </dependencies>
</plugin>
```

```bash
./mvnw rewrite:run
```

OpenRewrite kodda `javax.*` import-larını tapıb `jakarta.*` ilə əvəz edir, Maven asılılıqlarını yeniləyir, deprecated API-ləri yeni variantlarla əvəz edir.

### 7) IntelliJ inspection

IntelliJ IDEA 2022.2+ "Migrate Packages and Classes..." refactoring-i verir:
`Refactor → Migrate → Java EE to Jakarta EE`. Kiçik layihələr üçün yüngül variant.

### 8) Spring Boot 2 → 3 upgrade checklist

```
[ ] Java 17+ işlət (Java 8/11-dən keç).
[ ] Maven/Gradle plugin versiyalarını yenilə.
[ ] Spring Boot parent 3.3.x et.
[ ] Dependency-BOM yenilə (spring-cloud-dependencies 2023.0+).
[ ] javax.* → jakarta.* (OpenRewrite və ya manual).
[ ] Hibernate 5 → 6 — HQL dəyişiklikləri (positional parameter artıq yoxdur).
[ ] Tomcat 9 → 10.1 (Servlet 6).
[ ] Spring Security 6 — WebSecurityConfigurerAdapter silinib, SecurityFilterChain bean.
[ ] Spring MVC Trailing slash default false (uri matching).
[ ] Observation API — Sleuth əvəzi (Micrometer Tracing).
[ ] @ConstructorBinding artıq @ConfigurationProperties üçün lazım deyil.
[ ] Actuator httptrace → httpexchanges.
[ ] application.properties — bəzi property prefix-lər dəyişib (server.reactive → reactive.*).
[ ] Thymeleaf 3.1 — context-dəki implicit variable-lər dəyişib.
[ ] Test: JUnit Jupiter only (JUnit 4 Vintage default kapalı).
[ ] Third-party library versiyalarının Jakarta-uyğun olduğunu yoxla.
```

### 9) Hibernate 5 → 6 — əsas breaking

```java
// Hibernate 5 — OK
Query q = session.createQuery("FROM User WHERE id = ?1");
q.setParameter(1, 42L);

// Hibernate 6 — positional parametr tamamilə yox
Query q = session.createQuery("FROM User WHERE id = :id", User.class);
q.setParameter("id", 42L);
```

Sequence generator default dəyişib — `hibernate.id.new_generator_mappings=true` (artıq yoxdur, həmişə true). Əvvəlki id generatoru istəyirsənsə `@GenericGenerator` istifadə etməlisən.

### 10) Third-party library yoxlama

```bash
# jdeps ilə javax.* import-ı axtar
jdeps --class-path "target/app.jar" -verbose:class target/app.jar | grep javax
```

Əgər vendor library-si hələ `javax.*`-dədirsə, iki variant:
1. Vendor-dan Jakarta versiya gözlə.
2. Eclipse Transformer ilə JAR-ı lokal çevirib istifadə et (müvəqqəti həll).

---

## Laravel / PHP-də istifadəsi

### 1) Laravel 10 → 11 upgrade — əsas dəyişikliklər

Laravel 11 (2024) çərçivə strukturunu sadələşdirdi — `app/Http/Kernel.php` və `app/Console/Kernel.php` silindi, hər şey `bootstrap/app.php`-yə keçdi.

```php
// Laravel 10 — app/Http/Kernel.php
class Kernel extends HttpKernel
{
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            ...
        ],
    ];

    protected $routeMiddleware = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        ...
    ];
}
```

```php
// Laravel 11 — bootstrap/app.php
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
        $middleware->web(append: [
            \App\Http\Middleware\AddCustomHeader::class,
        ]);
        $middleware->alias([
            'subscribed' => \App\Http\Middleware\EnsureUserIsSubscribed::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (Throwable $e) {
            // custom report
        });
    })
    ->create();
```

### 2) Laravel 11 → 12 — daha kiçik miqyas

Laravel 12 əsasən dependencies yeniləmə, PHP 8.2 minimum, bəzi deprecated API silinib. Major kodu sındırmır — adətən `composer update` və `composer.json`-da version bump kifayət edir.

### 3) Rector — PHP avtomatik yenilənmə

```bash
composer require rector/rector --dev
```

```php
// rector.php
use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Laravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/config',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php83: true)
    ->withSets([
        LevelSetList::UP_TO_PHP_83,
        LaravelSetList::LARAVEL_110,
        LaravelSetList::LARAVEL_CODE_QUALITY,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
    ]);
```

```bash
./vendor/bin/rector process --dry-run
./vendor/bin/rector process
```

Rector edir:
- `array('a' => 1)` → `['a' => 1]`
- Property/parametr type-larını infer edir
- Deprecated funksiya adlarını yeniləyir
- Null-safe operator tətbiq edir
- Named argument refactor edir
- Laravel-specific: `Kernel.php` → `bootstrap/app.php` köçürməsi də var

### 4) Laravel Shift — kommersiya xidməti

```
https://laravelshift.com
```

GitHub repo bağlayırsan, Shift tətbiqin köhnə versiyadan yeni versiyaya avtomatik PR açır. `bootstrap/app.php`-yə keçid, route yenilənmə, deprecated alert-lər — hamısını PR kimi göstərir. Pulludur, amma böyük monolit üçün saatlarla işi bir saata çevirir.

### 5) PHP 7 → 8 migration

PHP 8-də ən çox istifadəçiyə təsir edən dəyişikliklər:

```php
// PHP 8: named arguments
http_get(url: 'https://example.com', timeout: 5);

// PHP 8: match expression
$level = match($status) {
    200, 201, 204 => 'ok',
    301, 302 => 'redirect',
    404 => 'not_found',
    default => 'error',
};

// PHP 8: nullsafe operator
$city = $user?->address?->city ?? 'unknown';

// PHP 8.1: readonly
class Point {
    public function __construct(
        public readonly float $x,
        public readonly float $y,
    ) {}
}

// PHP 8.1: enums
enum Status: string {
    case Pending = 'pending';
    case Active  = 'active';
    case Closed  = 'closed';
}

// PHP 8.3: typed class constants
class Config {
    public const string DEFAULT_LOCALE = 'en';
}
```

Rector PHP 7 → 8 üçün bütün bu pattern-ləri avtomatik yazır.

### 6) `composer.json` constraint yenilənməsi

```json
{
    "require": {
        "php": "^8.3",
        "laravel/framework": "^11.32",
        "laravel/sanctum": "^4.0",
        "laravel/tinker": "^2.9",
        "spatie/laravel-permission": "^6.0",
        "doctrine/dbal": "^4.0"
    },
    "require-dev": {
        "fakerphp/faker": "^1.23",
        "laravel/pint": "^1.18",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.0",
        "pestphp/pest": "^3.0",
        "phpunit/phpunit": "^11.0"
    }
}
```

```bash
composer why-not laravel/framework 11.*
composer outdated --direct
composer update --with-dependencies
```

### 7) Roave/BackwardCompatibilityCheck

Kitabxana müəllifləri üçün — public API-də breaking dəyişikliyi aşkar edir:

```bash
composer require --dev roave/backward-compatibility-check

./vendor/bin/roave-backward-compatibility-check
```

```
[BC] CHANGED: The parameter $id of App\Service\UserService#find() changed from int to string
[BC] REMOVED: Method App\Service\UserService#findOld() was removed
```

### 8) Symfony 2 → 3 → 4 tarixi

Symfony 4 (2017) çərçivəsini Spring Boot-a oxşar "convention over configuration"-a gətirdi — `app/` silindi, `src/` və `config/` qaldı. Yanaşma Jakarta köçürməsinə bənzəmir (namespace dəyişməyib), amma struktural dəyişiklik miqyasında analoqdur.

### 9) Spring Boot 2 → 3 vs Laravel 10 → 12 yanaşı

| Addım | Spring Boot 2 → 3 | Laravel 10 → 12 |
|---|---|---|
| 1. Minimum runtime | Java 17+ | PHP 8.3 |
| 2. Parent bump | `<parent>spring-boot 3.3</parent>` | `"laravel/framework": "^12"` |
| 3. Namespace | `javax.*` → `jakarta.*` (məcburi) | Yoxdur |
| 4. Yeni struktur | — | `bootstrap/app.php` strukturu (11-də) |
| 5. DB layer | Hibernate 6 — HQL positional yox | Nothing major |
| 6. Security layer | SecurityFilterChain bean | Sanctum 4 minor dəyişiklik |
| 7. Avtomatik tool | OpenRewrite recipes | Rector + Laravel Shift |
| 8. Third-party yoxlaması | `jdeps` + Eclipse Transformer | `composer outdated` |
| 9. Test | JUnit 4 Vintage silinir | PHPUnit 11, Pest 3 |
| 10. CI rerun | Full rebuild + integration | `composer install` + test |

### 10) `composer.json` → minimum stability + constraint

```json
{
    "minimum-stability": "stable",
    "prefer-stable": true,
    "config": {
        "platform": {
            "php": "8.3.10"
        },
        "platform-check": true,
        "sort-packages": true
    }
}
```

`platform-check: true` olanda composer runtime-da `php.version` yoxlayır — yanlış versiyada başlamır.

---

## Əsas fərqlər

| Məsələ | Jakarta EE Migration | Laravel/PHP Upgrade |
|---|---|---|
| Miqyas | Bütün Java EE namespace-ləri | Framework + bəzi API |
| Səbəb | Oracle brand mülkiyyəti | Framework təkamülü |
| Versiya siçrayışı | Spring 5 → 6 bir dəfə | Laravel hər 12 ay bir major |
| Minimum runtime | Java 17 | PHP 8.3 (Laravel 12) |
| Avtomatik tool | Eclipse Transformer, OpenRewrite, IntelliJ | Rector, Laravel Shift |
| Source kod dəyişikliyi | Hər `javax.` import | Kiçik — struktural |
| Bytecode çevrilməsi | Eclipse Transformer JAR-da edə bilər | Yoxdur (PHP interpretə olunur) |
| Third-party risk | Yüksək — köhnə JAR-lar javax | Aşağı — Composer versiya mümkün |
| Hazırlıq müddəti | Böyük layihə üçün həftələr | Laravel Shift ilə saatlar |
| Geri dönüş | Branch qaydasında | Branch + composer.lock |
| Container server | Tomcat 9 → 10.1 mütləq | Docker image refresh |
| ORM dəyişikliyi | Hibernate 6 — HQL fərqi | Eloquent — adətən uyğun |

---

## Niyə belə fərqlər var?

**Java EE-nin tarixi.** Oracle 2010-cu ildə Sun-u aldı, Java EE onun brand-ı oldu. 2017-də Eclipse Foundation-a verildi, amma Oracle `javax.*` adını qoruyub saxladı — "bizim ticari nişanımızdır". Eclipse yeni versiyalarda `jakarta.*` namespace-inə keçməli oldu. Bu, texniki yox, hüquqi-kommersiya problemdən doğulub.

**PHP-nin vendor-agnostic kökləri.** PHP özü namespace-ləri gec gətirdi (5.3, 2009), əsas standart kitabxana da ənənəvi olaraq qlobal funksiyalardır (`str_replace`, `array_map`). Tək vendor (Oracle) olmadığından belə mülkiyyət müşkülü yaranmadı.

**Bytecode vs source.** Java-da kompilyasiya edilmiş JAR-ların daxilində də `javax.*` package istinadları var — bunları dəyişmək üçün **bytecode transformation** lazımdır (Eclipse Transformer bunu edir). PHP-də `eval`-dən başqa runtime kompilyasiya yoxdur — source dəyişdi, iş bitdi.

**Laravel major sürəti.** Laravel 2024-dən bəri ildə bir major çıxarır (10, 11, 12). Hər biri kiçik miqyaslı upgrade — çünki minor breaking change-lər LTS arasında yayılıb. Spring isə Spring 5 → 6 keçidi üçün 5 il gözlədi (2017 → 2022) və bütün breaking change-ləri tək dəfəyə topladı.

**Avtomatik tool mədəniyyəti.** Java-da OpenRewrite AST-əsaslı refactoring ilə "recipe" kitabxanası yaradıb — bütün Spring versiyaları arasında köçürmə recipe kimi mövcuddur. PHP-də Rector eyni ideyanı tutdu — AST parser + transformation rule-lar. Hər iki ekosistem avtomatik upgrade-ə həmin həddə yetişib.

**Hibernate 5 → 6 niyə ağrılı?** Hibernate 6 bütün SQL generatorunu yenidən yazdı (Antlr 4 parser, yeni SQM model). Bu, namespace dəyişikliyinə əlavə **funksional** breaking change-lər də gətirdi. Bir çox layihə Hibernate upgrade-ini Jakarta migration-dan daha çətin saydı.

---

## Hansı framework-də var, hansında yoxdur?

**Yalnız Spring/Java-da:**
- Namespace köçürməsi miqyasında (yüz minlərlə layihəni təsirləndirən) miqrasiya.
- Eclipse Transformer — bytecode-dakı package reference-ləri çevirmək.
- OpenRewrite — AST-əsaslı recipe katalogu (Spring, JUnit, Hibernate).
- JAR-dan JAR-a transformation (kitabxana kodu dəyişmədən uyğunlaşdırma).
- Container server versiyası (Tomcat 9 → 10.1) upgrade-in bir hissəsi kimi.
- `jdeps` kommand-ı ilə static analysis.

**Yalnız Laravel/PHP-də:**
- Rector — PHP AST transformation aləti.
- Laravel Shift — kommersiya upgrade xidməti (avtomatik PR).
- `composer.json` platform constraint — CI-da PHP versiyası yoxlaması.
- `composer why-not` — asılılıq ziddiyətini aşkar etmək.
- Roave/BackwardCompatibilityCheck — library müəllifləri üçün.

---

## Best Practices

- **Feature branch**: upgrade ayrıca branch-də başla, master-ə merge etmə. CI hamısı yaşıl olanda merge.
- **Pilot service**: microservices varsa, ilk olaraq kiçik, az business-critical servisi köçür. Təcrübə topla, sonra monolit.
- **Version bump incremental**: Spring Boot 2.7 → 3.0 → 3.3; Laravel 10 → 11 → 12. Bir anda iki major atlamağa çalışma — breaking change-lər üst-üstə düşür, debug çətinləşir.
- **OpenRewrite/Rector dry-run**: avtomatik tool işlətməzdən əvvəl dry-run, dəyişiklikləri review et, sonra apply.
- **Test coverage əvvəlcədən**: upgrade-dən əvvəl test coverage-ı 70%-dən aşağı deyilsə, upgrade riski çoxdur. Əvvəl test yaz.
- **Third-party yoxlaması**: `jdeps` (Java) və ya `composer outdated` (PHP) ilə hansı kitabxana yeni versiya tələb edir siyahı çıxar.
- **Deprecation log**: yeni versiyada `--warning-mode all` (Gradle) və ya `deprecation` channel (Laravel) aç. Deprecated API-ləri əvvəldən dəyiş.
- **Staging mühiti**: production-a göndərməzdən əvvəl tam staging-də 1 həftə traffic üzərində işlət.
- **Rollback plan**: composer.lock / Maven effective-pom.xml ayrıca saxla. Problem olsa quick revert mümkün olsun.
- **Observability**: yeni version-da error rate, latency, memory metriklərini diqqətlə izlə — bəzi GC behavior-lar Java 17+-da fərqlidir.

---

## Yekun

Jakarta EE migrasiyası Java ekosisteminin ən böyük breaking change-idir — hüquqi səbəblə yaranmış olsa da, hər Spring tətbiqini təsirləndirib. Eclipse Transformer + OpenRewrite birlikdə iş miqyasını 90% avtomatlaşdırır, qalan 10% isə manual review və third-party yoxlamasıdır. Hibernate 6 və Spring Security 6 əlavə qatılıq gətirir.

Laravel/PHP tərəfdə upgrade miqyası kiçikdir. Namespace köçürməsi yoxdur, Rector AST-transformation edir, Laravel Shift kommersiya xidməti PR açır. Laravel 11-in struktural dəyişikliyi (`bootstrap/app.php`) ən böyük Laravel upgrade-i idi — Jakarta miqyasında deyil.

Hər iki ekosistem indi avtomatik upgrade alətləri ilə zəngindir. Qaçırılmamalı nüanslar: test coverage, third-party asılılıqlar, staging test və rollback planı. Düzgün hazırlıqla həm Spring 2 → 3, həm də Laravel 10 → 12 keçidi həftələr yox, günlərlə ölçülə bilər.
