# Spring Boot Hello World — Addım-addım Yeni Başlayanlar üçün

> **Seviyye:** Beginner ⭐

## Giriş

Hər yeni framework-ə başlayanda ilk qarşılaşdığımız şey "Hello World"-dur. Laravel-də `composer create-project` bir əmrlə layihə yaradır, sonra `php artisan serve` işə salır və brauzerdə səhifə görürsən. Spring Boot-da bu proses bir qədər fərqlidir — Java ekosistemi öz alətləri və konvensiyaları ilə gəlir.

Bu faylda biz sıfırdan Spring Boot layihəsi yaradıb, ilk `/hello` endpoint-ini yazıb, brauzerdə sınaqdan keçirəcəyik. Hər addımı Laravel-dəki ekvivalenti ilə müqayisə edəcəyik.

## Spring/Java-də istifadəsi

### 1. Spring Initializr (start.spring.io)

Spring Boot layihəsi yaratmaq üçün ən asan yol **Spring Initializr**-dır. Bu, [start.spring.io](https://start.spring.io) saytıdır — orada seçimləri edirsən, `Generate` düyməsini basırsan və ZIP fayl endirirsən.

Seçməli olduğun sahələr:

| Sahə | Nə deməkdir? | Nümunə dəyər |
|---|---|---|
| **Project** | Build system — Maven və ya Gradle | Maven |
| **Language** | Java, Kotlin, Groovy | Java |
| **Spring Boot** | Spring Boot versiyası | 3.3.0 |
| **Group** | Şirkət və ya domen adı (tərsinə) | com.example |
| **Artifact** | Layihənin adı | demo |
| **Name** | Layihənin insan oxunaqlı adı | demo |
| **Description** | Qısa təsvir | Demo project |
| **Package name** | Java əsas paket | com.example.demo |
| **Packaging** | JAR və ya WAR | Jar |
| **Java** | Java versiyası | 21 |

**Dependencies (asılılıqlar)** bölməsində `ADD DEPENDENCIES` düyməsini basıb əlavə et:

- **Spring Web** — REST API və HTTP server üçün (embedded Tomcat)
- **Lombok** — boilerplate kod (getter, setter) azaltmaq üçün
- **Spring Boot DevTools** — hot reload, kod dəyişikliyi zamanı avtomatik yenidən başlama

`Generate` düyməsini basırsan, ZIP endirilir, açırsan və IntelliJ IDEA-da açırsan.

### 2. Yaradılan `pom.xml` strukturu (Maven)

Layihə açıldıqdan sonra kök qovluqda `pom.xml` faylı görünür. Bu, Laravel-dəki `composer.json`-un ekvivalentidir.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<project xmlns="http://maven.apache.org/POM/4.0.0">
    <modelVersion>4.0.0</modelVersion>

    <!-- Spring Boot Parent — bütün versiyaları idarə edir -->
    <parent>
        <groupId>org.springframework.boot</groupId>
        <artifactId>spring-boot-starter-parent</artifactId>
        <version>3.3.0</version>
        <relativePath/>
    </parent>

    <groupId>com.example</groupId>
    <artifactId>demo</artifactId>
    <version>0.0.1-SNAPSHOT</version>
    <name>demo</name>
    <description>Demo project for Spring Boot</description>

    <properties>
        <java.version>21</java.version>
    </properties>

    <dependencies>
        <!-- Web endpoint-lər üçün -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-web</artifactId>
        </dependency>

        <!-- Lombok - getter/setter avtomatik -->
        <dependency>
            <groupId>org.projectlombok</groupId>
            <artifactId>lombok</artifactId>
            <optional>true</optional>
        </dependency>

        <!-- Hot reload -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-devtools</artifactId>
            <scope>runtime</scope>
            <optional>true</optional>
        </dependency>

        <!-- Test -->
        <dependency>
            <groupId>org.springframework.boot</groupId>
            <artifactId>spring-boot-starter-test</artifactId>
            <scope>test</scope>
        </dependency>
    </dependencies>

    <build>
        <plugins>
            <plugin>
                <groupId>org.springframework.boot</groupId>
                <artifactId>spring-boot-maven-plugin</artifactId>
            </plugin>
        </plugins>
    </build>
</project>
```

### 3. Gradle alternativi (`build.gradle`)

Əgər Maven əvəzinə Gradle seçsən, `pom.xml` yerinə `build.gradle` olacaq:

```groovy
plugins {
    id 'java'
    id 'org.springframework.boot' version '3.3.0'
    id 'io.spring.dependency-management' version '1.1.5'
}

group = 'com.example'
version = '0.0.1-SNAPSHOT'

java {
    sourceCompatibility = '21'
}

repositories {
    mavenCentral()
}

dependencies {
    implementation 'org.springframework.boot:spring-boot-starter-web'
    compileOnly 'org.projectlombok:lombok'
    annotationProcessor 'org.projectlombok:lombok'
    developmentOnly 'org.springframework.boot:spring-boot-devtools'
    testImplementation 'org.springframework.boot:spring-boot-starter-test'
}
```

Maven XML formatında, Gradle isə Groovy (və ya Kotlin) DSL formatında yazılır. İkisi də eyni işi görür.

### 4. `DemoApplication.java` — main sinfi

Layihədə `src/main/java/com/example/demo/DemoApplication.java` faylı avtomatik yaradılır:

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

Bu, Java dünyasında tətbiqin **giriş nöqtəsidir**. `main()` metodu JVM tərəfindən çağırılır və Spring Boot özünü işə salır.

`@SpringBootApplication` annotasiyası üç annotasiyanın birləşməsidir:
- `@Configuration` — bean tənzimləmə sinfidir
- `@EnableAutoConfiguration` — classpath-dəki kitabxanalara görə avtomatik konfiqurasiya edir
- `@ComponentScan` — cari paket və alt paketlərdə komponent axtarır

(Detallı izah üçün `80-spring-boot-application-annotation-breakdown.md` faylına bax.)

### 5. İlk REST endpoint

İndi `src/main/java/com/example/demo/` qovluğuna yeni fayl əlavə edək: `HelloController.java`.

```java
package com.example.demo;

import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.RestController;

@RestController
public class HelloController {

    @GetMapping("/hello")
    public String hello() {
        return "Hello, Spring Boot!";
    }

    @GetMapping("/hello/{name}")
    public String helloName(@org.springframework.web.bind.annotation.PathVariable String name) {
        return "Hello, " + name + "!";
    }
}
```

Burada:
- `@RestController` — bu sinif REST API controller-dir, bütün metod qaytarımları birbaşa HTTP response body olaraq yazılır (JSON və ya text).
- `@GetMapping("/hello")` — `GET /hello` sorğusunu bu metoda yönəldir.
- `@PathVariable` — URL-dəki `{name}` hissəsini parametr kimi alır.

### 6. Tətbiqi işə salmaq

Üç əsas yol var:

**Yol 1: Maven CLI**

```bash
./mvnw spring-boot:run
```

`mvnw` — Maven Wrapper, Maven-in özü sistemdə quraşdırılmasa belə, `./mvnw` layihə ilə gələn Maven versiyasını endirib istifadə edir.

**Yol 2: Gradle CLI**

```bash
./gradlew bootRun
```

**Yol 3: IntelliJ IDEA**

`DemoApplication.java` faylını açıb, `main()` metodunun yanında yaşıl "Play" düyməsinə basırsan. IntelliJ avtomatik JVM-i işə salır.

### 7. Test etmək

Terminal-da yeni pəncərə aç:

```bash
# curl ilə
curl http://localhost:8080/hello
# Cavab: Hello, Spring Boot!

curl http://localhost:8080/hello/Orkhan
# Cavab: Hello, Orkhan!
```

Brauzerdə də eyni URL-i açmaq olar: `http://localhost:8080/hello`.

Postman-da `GET http://localhost:8080/hello` sorğusu göndərmək olar.

### 8. `application.properties` — ilk toxunuş

`src/main/resources/application.properties` faylı konfiqurasiya üçündür. Laravel-dəki `.env` + `config/` qovluğunun birləşməsi kimidir.

```properties
# Server port (default 8080)
server.port=8080

# Tətbiq adı
spring.application.name=demo

# Log səviyyəsi
logging.level.root=INFO
logging.level.com.example.demo=DEBUG
```

Port dəyişmək üçün:

```properties
server.port=9000
```

İndi tətbiq `http://localhost:9000/hello`-da işləyir.

### 9. DevTools — hot reload

`spring-boot-devtools` dependency-si olanda, sən `.java` faylını dəyişib IntelliJ-də `Build → Recompile` etdikdə, Spring Boot avtomatik yenidən başlayır (tam restart yox, amma context reload).

Tam "live reload" Laravel-dəki qədər rahat deyil, amma test üçün kifayətdir.

## Laravel/PHP-də istifadəsi

Laravel-də Hello World prosesi daha qısa və tanış gələ bilər.

### 1. Layihə yaratmaq

```bash
composer create-project laravel/laravel app
cd app
```

Bu əmr:
- `composer.json` faylı ilə Laravel skeleton endirir
- `vendor/` qovluğuna bütün asılılıqları yükləyir
- `.env` faylı yaradır
- `APP_KEY` generasiya edir

### 2. İlk route — `routes/web.php`

```php
<?php

use Illuminate\Support\Facades\Route;

Route::get('/hello', function () {
    return 'Hello, Laravel!';
});

Route::get('/hello/{name}', function (string $name) {
    return "Hello, {$name}!";
});
```

### 3. Controller ilə (daha təmiz yanaşma)

```bash
php artisan make:controller HelloController
```

`app/Http/Controllers/HelloController.php`:

```php
<?php

namespace App\Http\Controllers;

class HelloController extends Controller
{
    public function index(): string
    {
        return 'Hello, Laravel!';
    }

    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }
}
```

`routes/web.php`-də:

```php
use App\Http\Controllers\HelloController;

Route::get('/hello', [HelloController::class, 'index']);
Route::get('/hello/{name}', [HelloController::class, 'greet']);
```

### 4. Tətbiqi işə salmaq

```bash
php artisan serve
```

Default olaraq `http://127.0.0.1:8000`-də işləyir.

### 5. Test etmək

```bash
curl http://127.0.0.1:8000/hello
# Cavab: Hello, Laravel!

curl http://127.0.0.1:8000/hello/Orkhan
# Cavab: Hello, Orkhan!
```

### 6. `.env` fayli

Laravel-də konfiqurasiya `.env` faylındadır:

```env
APP_NAME=Laravel
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
```

Port dəyişmək üçün:

```bash
php artisan serve --port=9000
```

## Əsas fərqlər

| Xüsusiyyət | Spring Boot | Laravel |
|---|---|---|
| **Layihə yaradılması** | Spring Initializr (start.spring.io) və ya IntelliJ | `composer create-project laravel/laravel app` |
| **Giriş nöqtəsi** | `DemoApplication.java` — `main()` metodu | `public/index.php` |
| **İşə salma əmri** | `./mvnw spring-boot:run` və ya `./gradlew bootRun` | `php artisan serve` |
| **Default port** | 8080 | 8000 |
| **Route tərifi** | Controller daxilində `@GetMapping` | Ayrıca `routes/web.php` |
| **Controller annotasiyası** | `@RestController`, `@GetMapping` | Sadə sinif, route faylında bağlanır |
| **URL parametri** | `@PathVariable String name` | `function (string $name)` |
| **Konfiqurasiya faylı** | `application.properties` | `.env` + `config/*.php` |
| **Hot reload** | DevTools ilə restart | File save — dərhal görünür (compile yoxdur) |
| **Build sistemi** | Maven və ya Gradle | Composer |
| **Java versiyası tələbi** | JDK 17+ (Spring Boot 3.x) | PHP 8.2+ (Laravel 11) |
| **Embedded server** | Tomcat (daxili) | `php artisan serve` yalnız development |
| **Boot vaxtı** | 3-10 saniyə (JVM yüklənir) | Dərhal (request başına process) |

## Niyə belə fərqlər var?

### 1. Compile olunan vs interpret olunan dil

Java compile olunan dildir — kod əvvəlcə `.class` fayllarına çevrilir, sonra JVM işə salınır. PHP isə interpret olunur — hər sorğu üçün fayl oxunur və icra olunur.

Bu səbəbdən Spring Boot "uzun yaşayan" prosesdir (server daim aktivdir), Laravel isə hər sorğu üçün yenidən başlayır (PHP-FPM və ya Apache mod_php).

### 2. Build sistem fərqi

Java ekosistemi onilliklərdir Maven-ə əsaslanır. `pom.xml` XML-dir, uzun və ağırdır, amma dəqiqdir. Gradle isə daha yeni və ifadəli alternativdir.

Composer isə PHP üçün Ruby-dakı Bundler-dən ilhamlanıb — sadə JSON formatı.

### 3. Route yerləşdirilməsi

Laravel-də route-lar `routes/` qovluğunda mərkəzləşib, çünki PHP-də annotasiya tarixi Java qədər güclü deyil (PHP 8-dən attribute-lər var, amma hələ geniş istifadə olunmur).

Spring-də isə controller sinifinin öz üzərindəki annotasiyalar route-ları təyin edir. Java annotasiya mədəniyyəti bunu təbii edir.

### 4. Embedded server

Spring Boot öz daxilində Tomcat daşıyır — production-da belə JAR faylı birbaşa işə salınır (`java -jar app.jar`). Laravel-də isə production üçün Nginx və ya Apache lazımdır, `php artisan serve` yalnız inkişaf üçündür.

## Ümumi səhvlər (Beginner traps)

### Səhv 1: Port 8080 məşğuldur

```
Web server failed to start. Port 8080 was already in use.
```

**Həll yolları:**

Linux/Mac:
```bash
lsof -i :8080          # hansı proses istifadə edir
kill -9 <PID>          # prosesi öldür
```

Və ya port dəyişdir:
```properties
server.port=8081
```

### Səhv 2: Java versiya uyğunsuzluğu

```
class file has wrong version 65.0, should be 61.0
```

**Səbəb:** pom.xml-də `java.version=21` yazılıb, amma sistemdə Java 17 yüklüdür.

**Həll:**
```bash
java -version          # hansı versiya quraşdırılıb
# SDKMAN ilə versiya dəyişdirmək
sdk use java 21.0.2-tem
```

### Səhv 3: `@GetMapping` tanınmır

IntelliJ qırmızı xətt göstərir: `Cannot resolve symbol 'GetMapping'`.

**Səbəb:** `spring-boot-starter-web` dependency yoxdur.

**Həll:** `pom.xml`-ə əlavə et və `mvn install` işlət.

### Səhv 4: Component scan işləmir

Controller yazılıb, amma 404 qayıdır.

**Səbəb:** `HelloController` `DemoApplication`-dan fərqli paketdədir (məsələn, `com.other.demo`).

**Həll:** Controller-i `com.example.demo` və ya onun alt paketinə qoy.

### Səhv 5: Laravel-də `php artisan serve` işə salınmır

```
Could not open input file: artisan
```

**Səbəb:** `artisan` faylı olan qovluqda deyilsən.

**Həll:** `cd my-laravel-app` əvvəlcə.

## Hansı framework-də var, hansında yoxdur?

### Yalnız Spring Boot-da

- **Spring Initializr**: veb UI ilə layihə yaratmaq
- **Executable JAR**: `java -jar app.jar` ilə tam tətbiqi bir fayldan işə salmaq
- **Embedded Tomcat/Jetty/Undertow**: framework özü ilə web server gətirir

### Yalnız Laravel-də

- **Artisan CLI**: `make:controller`, `make:model` kimi kod generatorlar
- **`.env` fayli**: runtime-da dəyişiklik, restart lazım deyil
- **Tinker REPL**: `php artisan tinker` — interaktiv shell
- **Compile yoxdur**: fayl dəyişəndə dərhal görünür

### İkisində də var, amma fərqlidir

- **Hot reload**: Spring DevTools restart edir, Laravel sadəcə faylı yenidən oxuyur
- **Default port**: Spring 8080, Laravel 8000
- **Route**: Spring annotasiyada, Laravel routes/ faylında

## Növbəti addımlar (roadmap)

Hello World-dən sonra öyrənməyə davam et:

1. `@SpringBootApplication` annotasiyasının dərin izahı (fayl 80)
2. Starter dependency-lər necə işləyir (fayl 81)
3. `@Component`, `@Service`, `@Repository` fərqləri (fayl 82)
4. Dependency Injection və `@Autowired` (fayl 83)
5. `application.properties` və `@Value` (fayl 66)
6. Spring Data JPA ilə DB (fayl 16)
7. Spring Security (fayl 55)

## Mini müsahibə sualları

**Sual 1:** Spring Boot-da `main()` metodu niyə lazımdır? Laravel-də belə bir şey yoxdur — fərqi nədir?

*Cavab:* Java compile olunan dildir və hər Java tətbiqinin giriş nöqtəsi kimi `public static void main(String[] args)` metodu lazımdır. Bu metod JVM tərəfindən çağırılır. Laravel-də isə giriş nöqtəsi `public/index.php`-dir — hər HTTP sorğusu bu faylı icra edir (PHP-FPM via Nginx və ya Apache mod_php). Java "uzun yaşayan" prosesdir, PHP hər sorğu üçün yenidən başlayır.

**Sual 2:** `@RestController` və `@Controller` arasında fərq nədir?

*Cavab:* `@RestController` = `@Controller` + `@ResponseBody`. `@Controller` MVC üçündür — metod view adı qaytarır (məsələn, Thymeleaf template). `@RestController` isə REST API üçündür — qaytarılan obyekt birbaşa JSON və ya text olaraq HTTP response body-yə yazılır.

**Sual 3:** Spring Boot-da DevTools nə edir və niyə production-da işlədilməməlidir?

*Cavab:* DevTools classpath-dəki `.class` fayl dəyişikliklərini izləyir və avtomatik olaraq Spring context-i yenidən yükləyir (tam JVM restart-sız). Bu, development-də məhsuldarlığı artırır. Production-da istifadə etməmək səbəbləri: (1) restart davranışı gözlənilməz ola bilər, (2) performance təsiri var, (3) təhlükəsizlik — DevTools bəzi endpoint-ləri açır. Maven `scope=runtime` + `optional=true` production-da avtomatik xaric edir.
