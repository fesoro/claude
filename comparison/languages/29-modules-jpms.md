# Modullar (Java JPMS vs PHP Composer + Namespaces)

## Giriş

Böyük kod bazalarında "kim kimi görə bilər" sualı kritikdir. Əgər hər paket hər paketi görə bilirsə, arxitektura pozulur — alt-layer `domain` üst-layer `controller`-i import edə bilər və dairəvi asılılıq yaranar. Bu problemi həll etmək üçün dilə "modul sistemi" lazım ola bilər.

**Java 9**-da **Java Platform Module System (JPMS)** — kod adı **Project Jigsaw** — gəldi. JPMS compile-time və run-time səviyyəsində "hansı paket hansı moduldan görünür" qaydalarını tətbiq edir. JDK özü də indi modullardan ibarətdir: `java.base`, `java.sql`, `java.net.http` və s.

**PHP**-də belə bir "modul sistemi" yoxdur. PHP-də `namespace` var (PHP 5.3-dən), Composer PSR-4 autoload var, lakin bunlar yalnız "ad toqquşmasını" həll edir — "görünürlük" qaydasını tətbiq etmir. PHP-də hər `public` sinif hər yerdən istifadə oluna bilər. Arxitektura qaydalarını tətbiq etmək üçün **Deptrac** və ya **PhpArkitect** kimi xarici alətlərdən istifadə olunur.

---

## Java-da istifadəsi

### 1) `module-info.java` — modulun təyini

Hər JPMS modulu üçün kökdə `module-info.java` faylı yazılır. Bu fayl Java 9-dan gəlib.

```java
// src/main/java/module-info.java
module com.company.billing {

    // Bu modul hansı başqa modullardan asılıdır?
    requires java.sql;                         // JDBC
    requires java.net.http;                    // HTTP client
    requires transitive com.company.core;      // asılılarım da görsün

    // Bu modul hansı paketləri başqalarına göstərir?
    exports com.company.billing.api;           // public API
    exports com.company.billing.dto;           // DTO-lar

    // Hansı paketləri xüsusi hədəflə göstəririk?
    exports com.company.billing.internal to com.company.billing.tests;

    // Refleksiya üçün açıq (Jackson, Hibernate və s. üçün lazım)
    opens com.company.billing.entity to hibernate.orm, com.fasterxml.jackson.databind;
    opens com.company.billing.dto;             // bütün dünya üçün (təhlükəsiz deyil)

    // Service Loader pattern
    uses com.company.billing.spi.PaymentProvider;
    provides com.company.billing.spi.PaymentProvider
        with com.company.billing.impl.StripeProvider,
             com.company.billing.impl.PaypalProvider;
}
```

**Əsas açar sözlər:**

- `requires X` — modul X-dən asılıdır (compile + run time).
- `requires transitive X` — məndən asılı olan modullar da X-i avtomatik görür.
- `requires static X` — yalnız compile-time-da lazımdır (run-time-da yoxdursa OK).
- `exports pkg` — paket public şəkildə görünür.
- `exports pkg to M1, M2` — yalnız M1 və M2 görə bilər (qualified export).
- `opens pkg` — refleksiya üçün açıqdır (private sahələrə çatmaq olar).
- `opens pkg to M1` — yalnız göstərilən modullar refleksiya edə bilər.
- `uses I` — bu modul `I` servisini istifadə edir (ServiceLoader).
- `provides I with C` — bu modul `I` üçün `C` implementasiyasını təqdim edir.

### 2) Named, Unnamed və Automatic modullar

Java-da üç növ modul var:

```text
1. Named Module      — module-info.java olan modern modul
2. Unnamed Module    — classpath-də olan köhnə JAR (module-info.java yoxdur)
3. Automatic Module  — module path-də olan, amma module-info.java olmayan JAR
                      (adı MANIFEST.MF-dən və ya fayl adından götürülür)
```

**Keçid strategiyası:** köhnə kodu birbaşa JPMS-ə keçirmək çətindir. Ona görə **automatic modullar** körpü rolunu oynayır — köhnə JAR-ı module path-ə qoyuruq, o "avtomatik" modul olur, amma daxili strukturu eyni qalır (bütün paketlər `exports`-dur).

```java
// module-info.java
module com.company.app {
    // 'commons-lang3-3.12.jar' avtomatik olaraq 'org.apache.commons.lang3'
    // (MANIFEST.MF-də Automatic-Module-Name dəyəri varsa, o)
    requires org.apache.commons.lang3;
}
```

### 3) Module path vs Class path

```bash
# Köhnə üsul — class path
javac -cp "lib/*" -d out $(find src -name "*.java")
java  -cp "out:lib/*" com.company.app.Main

# Yeni üsul — module path
javac --module-path mods -d out/com.company.app \
    $(find src/com.company.app -name "*.java")
java  --module-path out:mods --module com.company.app/com.company.app.Main
```

**Ciddi fərq:** class path-də hər JAR bir-birini görür (hər şey public). Module path-də yalnız `exports` edilən paketlər görünür — qalanı compile-time xətası verir.

### 4) `jlink` — custom JRE

Modullarla yalnız lazım olan JDK hissələrini yığıb kiçik runtime yarada bilərik. Bu Docker image-lərini yüngülləşdirir.

```bash
# Öz modulunun asılılıqlarını tap
jdeps --print-module-deps --ignore-missing-deps app.jar
# Nəticə: java.base,java.net.http,java.sql

# Custom JRE qur (tam JDK ~300 MB, bizə ~50 MB yetər)
jlink \
    --module-path "$JAVA_HOME/jmods:mods" \
    --add-modules com.company.app,java.base,java.net.http,java.sql \
    --launcher app=com.company.app/com.company.app.Main \
    --compress=zip-9 \
    --no-header-files --no-man-pages \
    --strip-debug \
    --output dist/custom-jre

# İşə sal
./dist/custom-jre/bin/app
```

**Dockerfile ilə nümunə:**

```dockerfile
FROM eclipse-temurin:25-jdk AS builder
WORKDIR /build
COPY . .
RUN ./mvnw -q -DskipTests package
RUN jdeps --print-module-deps --ignore-missing-deps target/app.jar > deps.txt
RUN jlink \
    --module-path "$JAVA_HOME/jmods" \
    --add-modules $(cat deps.txt) \
    --strip-debug --compress=zip-9 --no-header-files --no-man-pages \
    --output /jre

FROM debian:stable-slim
COPY --from=builder /jre /jre
COPY --from=builder /build/target/app.jar /app.jar
ENTRYPOINT ["/jre/bin/java", "-jar", "/app.jar"]
# Nəticə image: ~90 MB əvəzinə ~450 MB
```

### 5) `jdeps` — asılılıq analizi

```bash
# Hansı JDK modullarından asılıyıq?
jdeps --module-path mods --list-deps app.jar

# Paket səviyyəsində analiz
jdeps -verbose:package app.jar

# Hansı daxili JDK API-ləri istifadə olunur? (Java 17+ bunları gizlədib)
jdeps --jdk-internals app.jar
# sun.misc.Unsafe — JDK-nın daxili API-si, gələcəkdə silinə bilər
```

### 6) Strong Encapsulation — `--add-opens`, `--add-exports`

Java 17-dən etibarən JDK daxili paketlər (`sun.*`, `jdk.internal.*`) default bağlıdır. Köhnə kitabxanalar (Hibernate, Mockito, bəzi Spring versiyaları) bunları refleksiyada istifadə edir və xəta verir.

```bash
# "IllegalAccessException: module java.base does not open java.lang to unnamed module"

# Həll: JVM flag-i
java --add-opens java.base/java.lang=ALL-UNNAMED \
     --add-opens java.base/java.util=ALL-UNNAMED \
     -jar app.jar

# --add-exports — qualified export əlavə et (compile-time)
javac --add-exports jdk.compiler/com.sun.tools.javac.api=ALL-UNNAMED MyCode.java
```

`pom.xml`-də:

```xml
<plugin>
    <artifactId>maven-surefire-plugin</artifactId>
    <configuration>
        <argLine>
            --add-opens java.base/java.lang=ALL-UNNAMED
            --add-opens java.base/java.util=ALL-UNNAMED
        </argLine>
    </configuration>
</plugin>
```

### 7) Refleksiya və modullar

```java
// Named modul daxilində refleksiya
module com.company.app {
    requires com.fasterxml.jackson.databind;
    opens com.company.app.dto to com.fasterxml.jackson.databind;
    // Jackson dto paketinin private sahələrinə çata bilər
}

// Kod
public class UserDto {
    private String name;
    private int age;
    // Jackson `name` və `age`-i oxuyur çünki paket `opens`-dir
}
```

Əgər `opens` yoxdursa:

```
com.fasterxml.jackson.databind.exc.InvalidDefinitionException:
Cannot construct instance of `UserDto`:
module com.company.app does not "opens com.company.app.dto" to module com.fasterxml.jackson.databind
```

### 8) Multi-module Maven layihəsi

```text
billing-parent/
 ├── pom.xml                         <!-- parent pom -->
 ├── billing-domain/
 │    ├── pom.xml
 │    └── src/main/java/
 │         ├── module-info.java       // module com.company.billing.domain { exports ... }
 │         └── com/company/billing/domain/Order.java
 ├── billing-persistence/
 │    ├── pom.xml
 │    └── src/main/java/
 │         ├── module-info.java       // requires com.company.billing.domain;
 │         └── com/company/billing/persistence/OrderRepo.java
 ├── billing-service/
 │    └── module-info.java            // requires ...domain, ...persistence;
 └── billing-app/
      └── module-info.java            // requires ...service;
```

`billing-domain/module-info.java`:

```java
module com.company.billing.domain {
    exports com.company.billing.domain;
    exports com.company.billing.domain.events;
    // persistence.internal paketi export edilmir — gizlidir
}
```

`billing-persistence/module-info.java`:

```java
module com.company.billing.persistence {
    requires com.company.billing.domain;
    requires java.sql;
    requires transitive jakarta.persistence;

    exports com.company.billing.persistence.api;
    opens com.company.billing.persistence.entity to org.hibernate.orm.core;
    // entity paketi yalnız Hibernate üçün açıqdır (daha təhlükəsiz)
}
```

### 9) ServiceLoader ilə plugin arxitekturası

```java
// billing-spi modulu — interface
module com.company.billing.spi {
    exports com.company.billing.spi;
}

public interface PaymentProvider {
    String name();
    PaymentResult charge(Money amount, Card card);
}

// billing-stripe modulu — implementasiya
module com.company.billing.stripe {
    requires com.company.billing.spi;
    provides com.company.billing.spi.PaymentProvider
        with com.company.billing.stripe.StripeProvider;
}

// billing-app modulu — istifadəçi
module com.company.billing.app {
    requires com.company.billing.spi;
    uses com.company.billing.spi.PaymentProvider;
}

// Kod
ServiceLoader<PaymentProvider> loader = ServiceLoader.load(PaymentProvider.class);
for (PaymentProvider p : loader) {
    System.out.println("Found: " + p.name());
}
```

### 10) Ümumi problemlər (pitfalls)

**Spring Boot və JPMS:** Spring Boot tam JPMS uyğun deyil. Əgər `module-info.java` yazsanız, Spring `@Autowired` refleksiyası `opens` olmadan sınar. Adətən Spring Boot tətbiqləri **unnamed module** kimi işlədilir (classpath, `module-info.java` yoxdur).

```bash
# Spring Boot JPMS xətası
Caused by: java.lang.reflect.InaccessibleObjectException:
Unable to make field private final X accessible:
module com.company.app does not "opens com.company.app" to unnamed module

# Həlli: opens əlavə et
module com.company.app {
    requires spring.context;
    opens com.company.app to spring.core;
}
```

**Build alətləri:** Gradle 7+ və Maven 3.8+ JPMS-i dəstəkləyir, amma bəzi plugin-lər (jacoco, mockito) əlavə flag tələb edir. Mockito inline mock engine `--add-opens java.base/java.lang=ALL-UNNAMED` istəyir.

**Split packages qadağadır:** İki fərqli modul eyni paket adını export edə bilməz.

```
Error: Package com.company.util in both module A and module B
```

---

## PHP-də istifadəsi

### 1) Namespace və Composer PSR-4

PHP-də "modul" anlayışı yoxdur. `namespace` yalnız ad toqquşmasını həll edir.

```php
<?php
// src/Billing/Order.php
namespace Company\Billing;

class Order
{
    public function __construct(
        public readonly string $id,
        public readonly int $amountCents,
    ) {}
}
```

`composer.json`:

```json
{
    "name": "company/billing",
    "autoload": {
        "psr-4": {
            "Company\\Billing\\": "src/"
        }
    },
    "require": {
        "php": "^8.3",
        "psr/log": "^3.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "qossmic/deptrac-shim": "^2.0"
    }
}
```

**Nota:** PHP-də `public`/`private`/`protected` yalnız sinif səviyyəsindədir — paket səviyyəsi yoxdur. `Company\Billing\Internal\Foo` sinfi tamamən "gizli" ola bilməz — hər kəs `new Foo()` çağıra bilər.

### 2) `@internal` PhpDoc tag

```php
<?php
namespace Company\Billing\Internal;

/**
 * @internal Bu sinif yalnız billing paketinin daxili istifadəsi üçündür.
 *           Xarici kod bunu istifadə etməməlidir.
 */
final class OrderIdGenerator
{
    public function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}
```

Bu sadəcə **konvensiyadır** — dil tətbiq etmir. IDE-lər (PhpStorm) xəbərdarlıq göstərir, amma kod yenə işləyir.

### 3) Deptrac — arxitektura qaydalarını tətbiq et

**Deptrac** PHP-nin JPMS-ə ən yaxın alətidir. O, statik analizlə "hansı layer hansı layer-i görə bilər" qaydalarını yoxlayır.

`deptrac.yaml`:

```yaml
deptrac:
  paths:
    - ./src
  exclude_files:
    - '#.*test.*#'
  layers:
    - name: Domain
      collectors:
        - type: directory
          value: src/Domain/.*
    - name: Application
      collectors:
        - type: directory
          value: src/Application/.*
    - name: Infrastructure
      collectors:
        - type: directory
          value: src/Infrastructure/.*
    - name: Presentation
      collectors:
        - type: directory
          value: src/Presentation/.*

  ruleset:
    Domain: ~                       # Domain heç kimdən asılı deyil
    Application:
      - Domain                      # Application yalnız Domain-i görür
    Infrastructure:
      - Domain
      - Application
    Presentation:
      - Application
      - Domain
  # Əgər Domain-dən Infrastructure-a asılılıq varsa — Deptrac xəta verir
```

Yoxlamaq:

```bash
vendor/bin/deptrac analyse
# Found 2 Rule Violations
# src/Domain/Order.php
#   src/Infrastructure/Db/DbOrderRepo.php must not depend on src/Domain/Order.php
```

CI-də:

```yaml
# .github/workflows/ci.yml
- name: Architecture check
  run: vendor/bin/deptrac analyse --fail-on-uncovered --no-progress
```

### 4) PhpArkitect — fluent API ilə qaydalar

```php
<?php
// arkitect.php
use Arkitect\ClassSet;
use Arkitect\CLI\Config;
use Arkitect\Expression\ForClasses\HaveNameMatching;
use Arkitect\Expression\ForClasses\ResideInOneOfTheseNamespaces;
use Arkitect\Expression\ForClasses\NotDependsOnTheseNamespaces;
use Arkitect\Expression\ForClasses\Extend;
use Arkitect\Rules\Rule;

return static function (Config $config): void {
    $classSet = ClassSet::fromDir(__DIR__ . '/src');

    $domainRule = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces('Company\\Domain'))
        ->should(new NotDependsOnTheseNamespaces(
            'Company\\Infrastructure',
            'Company\\Presentation',
            'Illuminate',           // Laravel
            'Symfony',
            'Doctrine',
        ))
        ->because('Domain layer must be framework-agnostic');

    $controllerRule = Rule::allClasses()
        ->that(new ResideInOneOfTheseNamespaces('Company\\Presentation\\Http'))
        ->should(new HaveNameMatching('*Controller'))
        ->because('all classes in Http namespace must end with Controller');

    $config->add($classSet, $domainRule, $controllerRule);
};
```

```bash
vendor/bin/phparkitect check
```

### 5) PHP 8.1 `final` və `readonly`

Sinifləri bağlamağın yolu:

```php
<?php
// PHP 8.1+
final class PaymentGateway
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly string $apiKey,
    ) {}

    public function charge(int $cents): PaymentResult
    {
        // ...
    }
}

// PHP 8.2+ readonly class
readonly class Money
{
    public function __construct(
        public int $amount,
        public string $currency,
    ) {}
}
```

`final` uzadılmağa icazə vermir, amma **görünürlüyü** məhdudlaşdırmır.

### 6) Composer monorepo və paketlər

Böyük PHP layihələri çox vaxt Composer monorepo kimi qurulur:

```text
billing-monorepo/
 ├── composer.json                  <!-- root -->
 ├── packages/
 │    ├── billing-domain/
 │    │    ├── composer.json         // "name": "company/billing-domain"
 │    │    └── src/
 │    ├── billing-persistence/
 │    │    ├── composer.json         // "require": {"company/billing-domain": "*"}
 │    │    └── src/
 │    └── billing-http/
 │         ├── composer.json
 │         └── src/
 └── apps/
      └── billing-api/
           └── composer.json
```

Root `composer.json`:

```json
{
    "repositories": [
        { "type": "path", "url": "packages/*" }
    ],
    "require": {
        "company/billing-domain": "*",
        "company/billing-persistence": "*",
        "company/billing-http": "*"
    },
    "config": {
        "sort-packages": true
    }
}
```

Amma burada da "görünürlük" qaydası yoxdur — `billing-http` paketi `billing-domain`-in istənilən public sinfini birbaşa istifadə edə bilər, hətta o sinif "internal" sayılsa belə.

### 7) Symfony Bundle sistemi

Symfony-də hər feature bir **Bundle** kimi paketlənə bilər. Bu Java JPMS-dən daha yumşaqdır — bundle bir konvensiyadır.

```php
<?php
// src/BillingBundle/BillingBundle.php
namespace Company\BillingBundle;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BillingBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');
    }
}
```

Bundle `config/services.yaml`:

```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true
        public: false       # default — daxili servislər public deyil

    Company\BillingBundle\:
        resource: '../src/'
        exclude: '../src/{Entity,DTO}'
```

Burada `public: false` yalnız **DI container-i** üçün gizlilik yaradır — digər bundle-lar bu servisi container-dən birbaşa götürə bilməzlər. Amma `new` ilə hələ də yarada bilərlər.

### 8) Laravel paket strukturu

```text
laravel-billing-package/
 ├── composer.json
 ├── config/billing.php
 ├── database/migrations/
 ├── routes/api.php
 ├── src/
 │    ├── BillingServiceProvider.php
 │    ├── Contracts/PaymentProvider.php
 │    ├── Providers/StripeProvider.php
 │    ├── Http/Controllers/BillingController.php
 │    └── Models/Invoice.php
 └── tests/
```

```php
<?php
namespace Company\LaravelBilling;

use Illuminate\Support\ServiceProvider;

class BillingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/billing.php', 'billing');

        $this->app->bind(
            Contracts\PaymentProvider::class,
            Providers\StripeProvider::class,
        );
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }
}
```

### 9) Qarışıq istifadə — Deptrac + PHPStan

Çoxsaylı layihələrdə Deptrac (arxitektura) + PHPStan (tip) + Rector (refactor) birlikdə istifadə olunur:

```bash
vendor/bin/phpstan analyse --level=8
vendor/bin/deptrac analyse --fail-on-uncovered
vendor/bin/phparkitect check
vendor/bin/rector process --dry-run
```

Bu ekosistem JPMS-in tək paketlə verdiyi qorumadan daha çevikdir, amma dil səviyyəsində deyil — alət səviyyəsindədir.

### 10) PHP-də pitfalls

**Autoload yükü:** Çoxlu paket monorepo-da Composer autoload gec olur. `composer dump-autoload -o` (optimize) və `--classmap-authoritative` flag-i istifadə olunur.

**Dairəvi asılılıq:** PHP bunu avtomatik tutmur. Deptrac bunun üçün `circular_reference_check` dəstəkləyir.

**`use` statement çirkliyi:** Bir fayl çoxlu `use Company\Billing\Internal\...` yaza bilər — heç kim buna mane olmur. Yalnız linter xəbərdarlıq edir.

---

## Əsas fərqlər

| Aspekt | Java (JPMS) | PHP (Composer + Tools) |
|---|---|---|
| Modul sistemi dildə | Bəli (Java 9+) | Yoxdur |
| Görünürlük qaydası | `exports` (compile-time) | Yalnız konvensiya (`@internal`) |
| Asılılıq bəyanı | `requires` | `composer.json` `require` |
| Refleksiya qorunması | `opens` tələb edir | Həmişə açıqdır |
| Paket səviyyəsində encapsulation | Bəli | Yoxdur |
| Service discovery | `provides/uses` (ServiceLoader) | Composer plugin / Container |
| Custom runtime | `jlink` ilə mümkün | Yoxdur (PHP runtime monolit) |
| Asılılıq analizi | `jdeps` (built-in) | `composer why`, Deptrac |
| Keçid strategiyası | Automatic modules | Yoxdur — birbaşa `require` |
| Build time yoxlaması | javac JPMS-i icra edir | Deptrac/PhpArkitect (CI) |
| Split package | Qadağandır | İcazəlidir (namespace fərqli olsa) |
| Transitive export | `requires transitive` | Yoxdur (hər asılılıq öz-özü) |

---

## Niyə belə fərqlər var?

**Java uzun həyat dövrünə hazırlaşır.** JPMS 2008-də Project Jigsaw kimi başladı, 2017-də (Java 9) relizə çıxdı. JDK özünü parçalayıb yenidən qurmaq üçün "strong encapsulation" vacib idi — artıq `sun.misc.Unsafe` kimi daxili sinifləri istifadə edə bilməyək. JDK modul sistemi eyni zamanda kitabxana müəllifləri üçündür — Oracle öz ekosisteminin stabilliyini qorumaq istəyir.

**PHP web scripting dili kimi başladı.** PHP 4-də namespace belə yox idi — hər şey global idi. PHP 5.3 namespace gətirdi (2009). PHP icması modul sistemi əvəzinə **Composer**-i seçdi (2012) — bu, konkret problemi (ad və asılılıq idarəetmə) həll edirdi. Görünürlük üçün isə xarici alətlər (PHPStan, Deptrac) ekosistem formalaşdırdı.

**Performans fərqi:** Java JIT optimizasiyası üçün "bu metod kimin tərəfindən çağırıla bilər" bilməsi faydalıdır. JPMS inlining-ə kömək edir. PHP-də hər sorğu yenidən yüklənir — modul metadata-sı əsasən lazımsızdır.

**Mədəniyyət:** Java kütləvi, stabil, konservativ korporativ dildir — JPMS bu ruhdadır. PHP çevik, "bu işləsə yaxşıdır" ruhundadır — rigid modul sistemi icma mədəniyyətinə zidd olardı.

---

## Hansı dildə var, hansında yoxdur?

**Yalnız Java-da:**

- `module-info.java` dil konstruksiyası
- `requires transitive` (asılılığın keçişi)
- `opens` (refleksiya icazəsi)
- `uses`/`provides` ServiceLoader inteqrasiyası
- `jlink` — custom runtime image
- `jdeps` — dil səviyyəsində asılılıq analizi
- Split package qadağası (compile-time)
- Qualified export (`exports X to Y`)
- JDK-nın özünün modullaşdırılması (`java.base`, `java.sql`)
- Automatic modules — köhnə JAR-lar üçün körpü

**Yalnız PHP-də (və ya PHP ekosistemində daha güclü):**

- Composer — sadə, populyar paket meneceri (Java Maven/Gradle-a uyğundur, amma daha sadə)
- Paketləri bir file-da birləşdirmək (`phar` arxivi)
- Runtime paket yükləmə (`composer require` birbaşa çalışan sistemə)
- `@internal` kimi informal qaydalar

**İkisində də var:**

- Namespace / paket
- Asılılıq bəyanı
- Versiya semantikası (Java `pom.xml`, PHP `composer.json`)
- Lock fayl (`pom.xml` + `dependency:tree` vs `composer.lock`)

**İkisində də yoxdur və ya məhduddur:**

- Paket daxilində "friend" əlaqələri (C++ `friend` kimi)
- Runtime-da modul aktivləşdirmək/deaktivləşdirmək (OSGi Java-da var, amma JPMS-dən fərqlidir)

---

## Best Practices

### Java (JPMS)

1. **Yeni layihə üçün:** `module-info.java` yaz, hər modulu kiçik saxla (SRP).
2. **Köhnə layihə üçün:** əvvəlcə paketləri təmizlə, sonra automatic modul-dan named modula keç. Birdən böyük migrasiya etmə.
3. **Spring Boot istifadə edirsənsə:** classpath-də qal, `module-info.java` yazma (təcrübədə daha asandır).
4. **Kitabxana müəllifi isən:** mütləq `Automatic-Module-Name` MANIFEST.MF-yə əlavə et — istifadəçilərin `requires` yaza bilməsi üçün.
5. **`opens` istifadəsi:** qualified export et (`opens X to Y`), universal `opens` etmə.
6. **`jlink` və Docker:** production image-ləri üçün custom JRE yarat, image ölçüsü 3-5 dəfə kiçilir.
7. **`jdeps --jdk-internals`** CI-də işlət — daxili JDK API-lərinin istifadəsini izlə.
8. **Split package-dən qaç:** fərqli modullar fərqli paket adlarında olsun.
9. **`requires transitive` ehtiyatla:** bunu yalnız API-də həqiqətən ötürülən tiplər üçün istifadə et.

### PHP (Composer + Tools)

1. **Hər layihədə Deptrac və ya PhpArkitect** qur — layer qaydalarını CI-də məcburi et.
2. **Monorepo istifadə et** böyük layihələrdə — `packages/` qovluğu altında hər modul ayrı composer paketi.
3. **`@internal` PhpDoc** daxili sinifləri işarələ — IDE-lər kömək edir.
4. **`final` və `readonly` sinfləri** default olsun — qaçılmaz deyilsə.
5. **`composer why` və `composer why-not`** — asılılıq ağacını analiz et.
6. **`composer dump-autoload -o --classmap-authoritative`** production-da.
7. **PHPStan level 8** + **Deptrac** + **Rector** üçlüyünü CI-də işlət.
8. **Laravel/Symfony package-lər** üçün ServiceProvider/Bundle konvensiyasına riayət et.
9. **Composer PSR-4** strikt saxla — bir namespace bir directory-ə uyğun gəlsin.
10. **Dairəvi asılılığı tut** — `composer audit` və Deptrac-la.

---

## Yekun

- **Java 9 JPMS** (Project Jigsaw) — dil səviyyəsində modul sistemi. `module-info.java`-da `requires`, `exports`, `opens`, `uses`, `provides` bəyan olunur. Compile-time-da tətbiq olunur.
- **PHP-də modul sistemi yoxdur** — yalnız `namespace` (ad) və Composer (asılılıq) var. Görünürlüyü **Deptrac**, **PhpArkitect**, `@internal` PhpDoc ilə tətbiq edirik.
- **JPMS üstünlükləri:** strong encapsulation, `jlink` ilə kiçik runtime, `jdeps` analiz, JDK-nın özünün modullaşdırılması.
- **JPMS çatışmazlıqları:** migrasiya çətindir, Spring Boot ekosistemi tam uyğunlaşmayıb, `opens` flag-ləri tələb olunur.
- **PHP ekosistem həlli:** Deptrac/PhpArkitect CI-də arxitektura qaydalarını tətbiq edir. Bu, dil səviyyəsində deyil, amma çevikdir.
- **Automatic modules** — Java-da köhnə JAR-ları JPMS-ə keçirmək üçün körpü.
- **`jlink`** — custom JRE yaradır, Docker image-lərini 3-5 dəfə kiçildir.
- **Spring Boot JPMS-ə tam hazır deyil** — real dünyada çoxu layihə classpath-də qalır.
- **PHP Symfony Bundle və Laravel Service Provider** — konvensiyaya əsaslanan "package" təcrübəsi, amma dil tətbiqi yoxdur.
- **Yekun:** JPMS qaydaları dilin özünə tikir, PHP isə qaydaları xarici alətlər vasitəsilə əlavə edir. Böyük korporativ layihələrdə hər ikisi öz yolunda effektivdir, amma JPMS daha güclü təhlükəsizlik zəmanəti verir.
