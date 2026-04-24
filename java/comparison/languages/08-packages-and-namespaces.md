# Paket ve Namespace — Java vs PHP

> **Seviyye:** Beginner ⭐

## Giris

Boyuk proqramlarda kodu muxtlif bolmelere ayirmaq ve ad toqqushmasini (name collision) qarsisini almaq lazimdir. Java bunu **package** sistemi ile, PHP ise **namespace** sistemi ile hel edir. Heresi de eyni meqsede xidmet edir, lakin mexanizmleri, fayl strukturu ile elaqesi ve asililiq idareetmesi (dependency management) ferqlenir.

---

## Java-da istifadesi

### Package (Paket) sistemi

Java-da her sinif bir pakete aiddir. Paket adi qovluq strukturuna uyghun OLMALIDIR:

```
src/
└── com/
    └── example/
        └── shop/
            ├── model/
            │   ├── Product.java
            │   ├── Order.java
            │   └── User.java
            ├── service/
            │   ├── ProductService.java
            │   └── OrderService.java
            ├── repository/
            │   ├── ProductRepository.java
            │   └── OrderRepository.java
            └── controller/
                └── ProductController.java
```

```java
// Product.java — paket elan etme
package com.example.shop.model;

public class Product {
    private Long id;
    private String name;
    private double price;

    // getters, setters...
}
```

```java
// ProductService.java — bashqa paketden import
package com.example.shop.service;

// Tek sinif import
import com.example.shop.model.Product;
import com.example.shop.repository.ProductRepository;

// Butun paketi import (toevsiye olunmur)
// import com.example.shop.model.*;

// Static import — static metod/saheleri birbashe istifade
import static java.util.stream.Collectors.toList;
import static java.lang.Math.PI;

public class ProductService {

    private final ProductRepository repository;

    public ProductService(ProductRepository repository) {
        this.repository = repository;
    }

    public List<Product> getExpensiveProducts(double minPrice) {
        return repository.findAll().stream()
            .filter(p -> p.getPrice() > minPrice)
            .collect(toList()); // static import sayesinde Collectors.toList() yazmaq lazim deyil
    }
}
```

### Paket adlandirma konvensiyasi

```java
// Ters domain adi konvensiyasi (reverse domain name):
// domain: example.com → paket: com.example
// domain: google.com → paket: com.google
// domain: apache.org → paket: org.apache

package com.example.shop.model;      // Biznes modeli
package com.example.shop.service;    // Biznes mentigi
package com.example.shop.repository; // Database emeliyyatlari
package com.example.shop.controller; // HTTP endpoint-ler
package com.example.shop.config;     // Konfiqurasiya
package com.example.shop.exception;  // Xususi exception-lar
package com.example.shop.util;       // Yardimci sinifler
```

### Erisim seviyyeleri ve paketler

```java
package com.example.shop.model;

public class Product {
    public String name;         // Her yerden elcatandir
    protected String sku;       // Eyni paket + alt sinifler
    String internalCode;        // Package-private (default) — yalniz eyni paket
    private double cost;        // Yalniz bu sinif daxilinde
}

// Package-private sinif — bashqa paketlerden gorünmur
class InternalHelper {
    // Bu sinif yalniz com.example.shop.model paketi daxilinde istifade oluna biler
}
```

### Java 9+ Module sistemi (Project Jigsaw)

Java 9 ile paketlerin ustunde **modul** sistemi elave olundu:

```
my-shop-module/
├── module-info.java          ← Modul taniti
└── com/
    └── example/
        └── shop/
            ├── model/
            │   └── Product.java
            ├── service/
            │   └── ProductService.java
            └── internal/
                └── Helper.java   ← Xaricden gizli
```

```java
// module-info.java
module com.example.shop {
    // Bu modul hansi paketleri xarice acir
    exports com.example.shop.model;
    exports com.example.shop.service;
    // com.example.shop.internal xarice ACILMIR

    // Bu modul hansi diger modullara asilidir
    requires java.sql;
    requires com.example.commons;

    // Reflection ucun acmaq (framework-ler ucun)
    opens com.example.shop.model to com.fasterxml.jackson.databind;

    // Service Provider Interface
    provides com.example.shop.service.PaymentService
        with com.example.shop.service.impl.StripePaymentService;

    uses com.example.shop.service.PaymentService;
}
```

### Maven — Java asililiq idareetmesi

Maven, Java dunya-sinda en genis yayilmis build ve asililiq idareetme aletidir:

```xml
<!-- pom.xml — Maven layihe fayili -->
<project>
    <groupId>com.example</groupId>
    <artifactId>my-shop</artifactId>
    <version>1.0.0</version>

    <dependencies>
        <!-- Spring Boot -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-web</artifactId>
            <version>3.2.0</version>
        </dependency>

        <!-- Database -->
        <dependency>
            <groupId>org.postgresql</groupId>
            <artifactId>postgresql</artifactId>
            <version>42.7.0</version>
        </dependency>

        <!-- Test -->
        <dependency>
            <groupId>org.junit.jupiter</groupId>
            <artifactId>junit-jupiter</artifactId>
            <version>5.10.0</version>
            <scope>test</scope>
        </dependency>
    </dependencies>
</project>
```

### Gradle — alternativ build aleti

```groovy
// build.gradle (Groovy DSL)
plugins {
    id 'java'
    id 'org.springframework.boot' version '3.2.0'
}

group = 'com.example'
version = '1.0.0'

repositories {
    mavenCentral()
}

dependencies {
    implementation 'org.springframework.boot:spring-boot-starter-web:3.2.0'
    implementation 'org.postgresql:postgresql:42.7.0'
    testImplementation 'org.junit.jupiter:junit-jupiter:5.10.0'
}
```

```kotlin
// build.gradle.kts (Kotlin DSL)
plugins {
    java
    id("org.springframework.boot") version "3.2.0"
}

dependencies {
    implementation("org.springframework.boot:spring-boot-starter-web:3.2.0")
    implementation("org.postgresql:postgresql:42.7.0")
    testImplementation("org.junit.jupiter:junit-jupiter:5.10.0")
}
```

Maven vs Gradle:
- **Maven**: XML-based, daha sadə, konvensiya uzerinde konfiqurasiya
- **Gradle**: Daha cevik, daha suretli (incremental build), Kotlin/Groovy DSL

---

## PHP-de istifadesi

### Namespace sistemi

PHP namespace sistemi Java package-e benzer, lakin fayl sistemine mexburi baglilighi yoxdur (PSR-4 bunu konvensiya ile hel edir):

```php
<?php

// src/Model/Product.php
namespace App\Model;

class Product
{
    public function __construct(
        private readonly ?int $id,
        private readonly string $name,
        private readonly float $price,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getPrice(): float
    {
        return $this->price;
    }
}
```

```php
<?php

// src/Service/ProductService.php
namespace App\Service;

// Tek sinif import
use App\Model\Product;
use App\Repository\ProductRepository;

// Alias ile import — ad toqqushmasi halinda
use App\Model\Product as ShopProduct;
use External\Library\Product as ExternalProduct;

// Funksiya import
use function App\Helpers\format_price;

// Sabit import
use const App\Config\MAX_PRODUCTS;

// Qrup import (PHP 7+)
use App\Model\{Product, Order, User};
use App\Repository\{ProductRepository, OrderRepository};

class ProductService
{
    public function __construct(
        private readonly ProductRepository $repository
    ) {}

    public function getExpensiveProducts(float $minPrice): array
    {
        return array_filter(
            $this->repository->findAll(),
            fn(Product $p) => $p->getPrice() > $minPrice
        );
    }
}
```

### Namespace strukturu ve qovluq uygunlugu

```
project/
├── composer.json
├── src/                          ← App\ namespace koku
│   ├── Model/
│   │   ├── Product.php           ← App\Model\Product
│   │   ├── Order.php             ← App\Model\Order
│   │   └── User.php              ← App\Model\User
│   ├── Service/
│   │   ├── ProductService.php    ← App\Service\ProductService
│   │   └── OrderService.php      ← App\Service\OrderService
│   ├── Repository/
│   │   ├── ProductRepository.php ← App\Repository\ProductRepository
│   │   └── OrderRepository.php   ← App\Repository\OrderRepository
│   ├── Controller/
│   │   └── ProductController.php ← App\Controller\ProductController
│   └── Exception/
│       └── ProductNotFoundException.php
├── tests/                        ← Tests\ namespace koku
│   └── Service/
│       └── ProductServiceTest.php ← Tests\Service\ProductServiceTest
└── vendor/                       ← Composer asililiq qovlugu
```

### Global namespace ve tam yol

```php
<?php

namespace App\Service;

class DateService
{
    public function now(): \DateTime
    {
        // \ ile bashlayir — global namespace
        return new \DateTime();
    }

    public function format(\DateTimeInterface $date): string
    {
        // PHP daxili sinifleri global namespace-dedir
        return $date->format('Y-m-d');
    }

    public function doSomething(): void
    {
        // Global funksiyalar avtomatik tapilir (namespace-de yoxdursa)
        $time = time();
        $len = strlen('salam');

        // Amma bezi hallarda aciq yazmaq lazim ola biler
        $arr = \array_map(fn($x) => $x * 2, [1, 2, 3]);
    }
}
```

### PSR-4 Autoloading

PSR-4 standarti namespace ile fayl yolunu elaqelendirir:

```json
// composer.json
{
    "name": "example/my-shop",
    "autoload": {
        "psr-4": {
            "App\\": "src/",
            "Tests\\": "tests/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    }
}
```

```
PSR-4 qaydasi:
Namespace prefix → Qovluq yolu

App\              → src/
App\Model\Product → src/Model/Product.php
App\Service\OrderService → src/Service/OrderService.php
Tests\Service\ProductServiceTest → tests/Service/ProductServiceTest.php
```

```bash
# Autoloader-i yarat/yenile
composer dump-autoload

# Optimizasiya olunmush autoloader (production)
composer dump-autoload --optimize
```

### Composer — PHP asililiq idareetmesi

```json
// composer.json
{
    "name": "example/my-shop",
    "description": "E-ticaret tetbiqi",
    "type": "project",
    "require": {
        "php": "^8.2",
        "laravel/framework": "^11.0",
        "guzzlehttp/guzzle": "^7.8",
        "predis/predis": "^2.2"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "phpstan/phpstan": "^1.10",
        "laravel/pint": "^1.13"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        },
        "files": [
            "src/helpers.php"
        ]
    },
    "scripts": {
        "test": "phpunit",
        "analyse": "phpstan analyse",
        "format": "pint"
    }
}
```

```bash
# Esas emeliyyatlar
composer init                    # Yeni layihe
composer install                 # Asililiq yukle (composer.lock-a gore)
composer update                  # Asililiq yenile
composer require guzzlehttp/guzzle  # Paket elave et
composer require --dev phpunit/phpunit  # Dev asililiq elave et
composer remove guzzlehttp/guzzle      # Paket sil

# composer.lock — dəqiq versiya kilidi
# Git-e commit olunmalidir (layiheler ucun)
# Library-ler ucun .gitignore-a elave olunur
```

### Composer vs Maven/Gradle muqayisesi

```
Composer:                          Maven:
composer.json                      pom.xml
composer.lock                      (effective POM)
vendor/                            ~/.m2/repository/ (qlobal kesh)
composer install                   mvn install
composer require X                 pom.xml-e manual elave
packagist.org                      Maven Central

Composer:                          Gradle:
composer.json                      build.gradle
composer require X                 implementation 'group:artifact:version'
composer dump-autoload             (avtomatik)
```

---

## Esas ferqler

| Xususiyyet | Java Packages | PHP Namespaces |
|---|---|---|
| **Sintaksis** | `package com.example.shop;` | `namespace App\Model;` |
| **Import** | `import com.example.shop.Model;` | `use App\Model\Product;` |
| **Wildcard import** | `import com.example.shop.*;` | Yoxdur (`use` ile her sinif ayri) |
| **Qrup import** | Yoxdur | `use App\Model\{A, B, C};` |
| **Fayl-qovluq elaqesi** | MECBURI — paket = qovluq | Konvensiya (PSR-4), mecburi deyil |
| **Bir faylda nece sinif** | 1 public sinif | Konvensiya olaraq 1 (amma ferqli ola biler) |
| **Funksiya import** | Static import: `import static` | `use function App\func;` |
| **Alias** | Yoxdur | `use App\Model as M;` |
| **Modul sistemi** | Java 9+ modules | Yoxdur |
| **Erisim modifikatorlari** | public, protected, package-private, private | public, protected, private |
| **Package-private** | Var (default, achar soz yoxdur) | Yoxdur |
| **Asililiq aleti** | Maven / Gradle | Composer |
| **Paket registri** | Maven Central | Packagist |
| **Autoloading** | ClassLoader (JVM-in daxili) | Composer autoload (PSR-4) |
| **Lock fayili** | pom.xml deqiq versiya / gradle.lock | composer.lock |

---

## Niye bele ferqler var?

### Fayl-qovluq elaqesi

**Java**: Paket adi ve qovluq strukturu BIR-BIRINE uyghun OLMALIDIR. `package com.example.shop.model` yazsan, sinif `com/example/shop/model/` qovlugunda olmalidir. Bu, JVM-in class loading mexanizminin telebider — sinifi tapmaq ucun paket adini qovluq yoluna cevirir.

**PHP**: Namespace ve qovluq arasinda texniki mecburiyyet yoxdur. Siz `namespace App\Model` yazib fayili `whatever/` qovluguna qoya bilersiniz. Lakin PSR-4 standarti bu elaqeni konvensiya ile mueyyenleshdirir ve Composer autoloader buna esaslanaraq isleyir. Praktikada hamisi PSR-4-e uyghun yazir.

### Niye Java ters domain adi istifade edir?

Java-nin `com.example.shop` konvensiyasi qlobal unikalliq temin edir. Iki ferqli sherekedtin eyni adli sinifi varsa (meselen, `Product`), paket adlari ferqli olacaq: `com.amazon.Product` vs `com.ebay.Product`. Bu, Java-nin hec bir merkezi paket registri olmadan uzun muddet islemesinin sebeblerindendir.

PHP-de bu ehtiyac Composer/Packagist ile hel olunur — paket adlari `vendor/package` formatindadir (meselen, `laravel/framework`, `symfony/console`).

### Modul sistemi ferqi

Java 9 modul sistemi paketlerin ustunde elave bir izolyasiya qati yaradir. Belelikle, bir kitabxana oz daxili paketlerini gizleye biler — `exports` ile yalniz aciq API gorünür. PHP-de bele bir mexanizm yoxdur — `public` olan her shey her yerden elcatandir.

### Asililiq idareetmesi

**Maven/Gradle**: Java-nin build alətleri eyni zamanda kompilyasiya, test, paketleme ve deployment islerini de gorur. Maven "Convention over Configuration" prinsipi ile isleyir — standart qovluq strukturu ile konfiqurasiya minimaldır.

**Composer**: Yalniz asililiq idareetmesi ve autoloading ile meshghul olur. Build, kompilyasiya kimi anlayishlar PHP-de yoxdur (interpretasiya olunan dil). Lakin Composer scripts vasitesile test, lint, format kimi emeliyyatlari da idare ede biler.

Her iki alətin ortaq xususiyyeti: **semantik versiyalashma** (`^1.0`, `~2.3`, `>=1.5 <3.0`) desteyidir ve bu, asililiq hell (dependency resolution) ucun istifade olunur.
